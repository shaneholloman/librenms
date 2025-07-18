<?php

/**
 * CiHelper.php
 *
 * Code for CI operation
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2020 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Util;

use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

class CiHelper
{
    private ?array $changed = null;
    private ?array $os = null;
    private array $unitEnv = [];
    private array $duskEnv = ['APP_ENV' => 'testing'];
    private ?Snmpsim $snmpsim = null;

    private array $completedChecks = [
        'lint' => false,
        'style' => false,
        'unit' => false,
        'web' => false,
    ];
    private array $ciDefaults = [
        'quiet' => [
            'lint' => true,
            'style' => true,
            'unit' => false,
            'web' => false,
        ],
    ];
    private array $flags = [
        'lint_enable' => true,
        'style_enable' => true,
        'unit_enable' => true,
        'web_enable' => false,
        'lint_skip' => false,
        'style_skip' => false,
        'unit_skip' => false,
        'web_skip' => false,
        'lint_skip_php' => false,
        'lint_skip_phpstan' => false,
        'lint_skip_python' => false,
        'lint_skip_bash' => false,
        'unit_os' => false,
        'unit_docs' => false,
        'unit_svg' => false,
        'unit_modules' => false,
        'docs_changed' => false,
        'ci' => false,
        'commands' => false,
        'fail-fast' => false,
        'full' => false,
        'quiet' => false,
        'os-modules-only' => false,
    ];

    public function enable($check, $enabled = true): void
    {
        $this->flags["{$check}_enable"] = $enabled;
    }

    public function duskHeadless(): void
    {
        $this->duskEnv['CHROME_HEADLESS'] = 1;
    }

    public function enableDb(): void
    {
        $this->unitEnv['DBTEST'] = 1;
    }

    public function enableSnmpsim(): void
    {
        if ($this->snmpsim === null) {
            $this->snmpsim = new Snmpsim('127.1.6.2', 1162);
            $this->snmpsim->setupVenv();
            $this->snmpsim->start();
        }

        $this->unitEnv['SNMPSIM'] = '127.1.6.2:1162';
    }

    public function setModules(array $modules): void
    {
        $this->unitEnv['TEST_MODULES'] = implode(',', $modules);
        $this->flags['unit_modules'] = true;
        $this->enableDb();
        $this->enableSnmpsim();
    }

    public function setOS(array $os): void
    {
        $this->os = $os;
        $this->flags['unit_os'] = true;
        $this->enableDb();
        $this->enableSnmpsim();
    }

    public function setFlags(array $flags): void
    {
        foreach (array_intersect_key($flags, $this->flags) as $key => $value) {
            $this->flags[$key] = $value;
        }
    }

    public function run(): int
    {
        $return = 0;
        foreach (array_keys($this->completedChecks) as $check) {
            $ret = $this->runCheck($check);

            if ($this->flags['fail-fast'] && $ret !== 0 && $ret !== 250) {
                return $ret;
            } else {
                $return += $ret;
            }
        }

        return $return;
    }

    /**
     * Confirm that all possible checks have been completed
     *
     * @return bool
     */
    public function allChecksComplete(): bool
    {
        return array_reduce($this->completedChecks, function ($result, $check) {
            return $result && $check;
        }, false);
    }

    /**
     * Get a flag value
     */
    public function getFlag(string $name): ?bool
    {
        return $this->flags[$name] ?? null;
    }

    /**
     * Fetch all flags
     *
     * @return bool[]
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Runs phpunit
     *
     * @return int the return value from phpunit (0 = success)
     */
    public function checkUnit(): int
    {
        $phpunit_cmd = [$this->checkPhpExec('phpunit'), '--colors=always', '--fail-on-all-issues'];

        if ($this->flags['fail-fast']) {
            $phpunit_cmd[] = '--stop-on-defect';
        }

        if (Debug::isVerbose()) {
            $phpunit_cmd[] = '--debug';
        }

        // exclusive tests
        if ($this->flags['unit_os']) {
            echo 'Only checking os: ' . implode(', ', $this->os) . PHP_EOL;
            $filter = implode('.*|', $this->os);
            // include tests that don't have data providers and only data sets that match
            array_push($phpunit_cmd, '--group', 'os');
            if ($this->flags['os-modules-only']) {
                array_push($phpunit_cmd, '--filter', "/::testOS with data set \"$filter.*\"$/");
            } else {
                array_push($phpunit_cmd, '--filter', "/::test[A-Za-z]+$|::testOSDetection|::test[A-Za-z]+ with data set \"$filter.*\"$/");
            }
        } elseif ($this->flags['unit_docs']) {
            array_push($phpunit_cmd, '--group', 'docs');
        } elseif ($this->flags['unit_svg']) {
            $phpunit_cmd[] = 'tests/SVGTest.php';
        } elseif ($this->flags['unit_modules'] || $this->flags['os-modules-only']) {
            $phpunit_cmd[] = 'tests/OSModulesTest.php';
        }

        return $this->execute('unit', $phpunit_cmd, false, $this->unitEnv);
    }

    /**
     * Runs phpcs --standard=PSR2 against the code base
     *
     * @return int the return value from phpcs (0 = success)
     */
    public function checkStyle(): int
    {
        $cs_cmd = [
            $this->checkPhpExec('php-cs-fixer'),
            '--config=.php-cs-fixer.php',
            'fix',
            '-v',
        ];

        $files = $this->flags['full'] ? [] : $this->changed['php'];
        $cs_cmd = array_merge($cs_cmd, $files);

        return $this->execute('style', $cs_cmd);
    }

    public function checkWeb(): int
    {
        if (! $this->flags['ci']) {
            echo "Warning: dusk may erase your primary database, do not use yet\n";

            return 0;
        }

        if ($this->canCheck('web')) {
            echo "Preparing for web checks\n";
            $this->execute('config:clear', ['php', 'artisan', 'config:clear'], true);
            $this->execute('dusk:chrome-driver', ['php', 'artisan', 'dusk:chrome-driver', '--detect'], true);

            // check if web server is running
            $server = new Process(['php', '-S', '127.0.0.1:8000', base_path('server.php')], public_path(), ['APP_ENV' => 'dusk.testing']);
            $server->setTimeout(3600)
                ->setIdleTimeout(3600)
                ->start();
            $server->waitUntil(function ($type, $output) {
                return strpos($output, 'Development Server (http://127.0.0.1:8000) started') !== false;
            });
            if ($server->isRunning()) {
                echo "Started server http://127.0.0.1:8000\n";
            }
        }

        $dusk_cmd = ['php', 'artisan', 'dusk'];

        if ($this->flags['fail-fast']) {
            array_push($dusk_cmd, '--stop-on-error', '--stop-on-failure');
        }

        return $this->execute('web', $dusk_cmd, false, $this->duskEnv);
    }

    /**
     * Runs php -l and tests for any syntax errors
     *
     * @return int the return value from running php -l (0 = success)
     */
    public function checkLint(): int
    {
        $return = 0;
        if (! $this->flags['lint_skip_php']) {
            $php_lint_cmd = [$this->checkPhpExec('parallel-lint')];

            // matches a substring of the relative path, leading / is treated as absolute path
            array_push($php_lint_cmd, '--exclude', 'vendor/');

            $files = $this->flags['full'] ? ['./'] : $this->changed['php'];
            $php_lint_cmd = array_merge($php_lint_cmd, $files);

            $return += $this->execute('PHP lint', $php_lint_cmd);

            if (! $this->flags['lint_skip_phpstan']) {
                $phpstan_cmd = [$this->checkPhpExec('phpstan'), 'analyze', '--no-interaction',  '--memory-limit=2G'];
                $return += $this->execute('PHPStan Deprecated', array_merge($phpstan_cmd, ['--configuration=phpstan-deprecated.neon']));
                $return += $this->execute('PHPStan', $phpstan_cmd);
            }
        }

        if (! $this->flags['lint_skip_python']) {
            $py_lint_cmd = [$this->checkPythonExec('pylint'), '-E', '-j', '0'];

            $files = $this->flags['full']
                ? explode(PHP_EOL, rtrim(shell_exec("find . -name '*.py' -not -path './vendor/*' -not -path './tests/*' -not -path './.python_venvs/*'")))
                : $this->changed['python'];

            $py_lint_cmd = array_merge($py_lint_cmd, $files);
            $return += $this->execute('Python lint', $py_lint_cmd);
        }

        if (! $this->flags['lint_skip_bash']) {
            $files = $this->flags['full']
                ? explode(PHP_EOL, rtrim(shell_exec("find . -name '*.sh' -not -path './node_modules/*' -not -path './vendor/*'")))
                : $this->changed['bash'];

            $bash_cmd = array_merge(['scripts/bash_lint.sh'], $files);
            $return += $this->execute('Bash lint', $bash_cmd);
        }

        return $return;
    }

    /**
     * Run the specified check and return the return value.
     * Make sure it isn't skipped by SKIP_TYPE_CHECK env variable and hasn't been run already
     *
     * @param  string  $type  type of check lint, style, or unit
     * @return int the return value from the check (0 = success)
     */
    private function runCheck($type): int
    {
        if ($method = $this->canCheck($type)) {
            $ret = $this->$method();
            $this->completedChecks[$type] = true;

            return $ret;
        }

        if ($this->flags["{$type}_enable"] && $this->flags["{$type}_skip"]) {
            echo ucfirst($type) . " check skipped.\n";
        }

        return 0;
    }

    /**
     * @param  string  $type
     * @return false|string the method name to run
     */
    private function canCheck($type)
    {
        if ($this->flags["{$type}_skip"] || $this->completedChecks[$type]) {
            return false;
        }

        $method = 'check' . ucfirst($type);
        if (method_exists($this, $method) && $this->flags["{$type}_enable"]) {
            return $method;
        }

        return false;
    }

    /**
     * Run a check command
     *
     * @param  string  $name  name for status output
     * @param  array  $command
     * @param  bool  $silence  silence the status ouput (still shows error output)
     * @param  array  $env  environment to set
     * @return int
     */
    private function execute(string $name, $command, $silence = false, $env = null): int
    {
        $start = microtime(true);
        $proc = new Process($command, null, $env);

        if ($this->flags['commands']) {
            $prefix = '';
            if ($env) {
                $prefix .= http_build_query($env, '', ' ') . ' ';
            }

            echo $prefix . $proc->getCommandLine() . PHP_EOL;

            return 250;
        }

        if (! $silence) {
            echo "Running $name check... ";
        }

        $space = strrpos($name, ' ');
        $type = substr($name, $space ? $space + 1 : 0);
        $quiet = ($this->flags['ci'] && isset($this->ciDefaults['quiet'][$type])) ? $this->ciDefaults['quiet'][$type] : $this->flags['quiet'];

        $proc->setTimeout(7200)->setIdleTimeout(3600);
        if (! ($silence || $quiet)) {
            echo PHP_EOL;

            // Run the process synchronously with a callback to handle output
            $proc->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    fwrite(STDERR, $buffer);
                } else {
                    echo $buffer;
                }
            });
        } else {
            $proc->run();
        }

        $duration = sprintf('%.2fs', microtime(true) - $start);
        if ($proc->getExitCode() > 0) {
            if (! $silence) {
                echo "failed ($duration)\n";
            }
            echo $proc->getOutput() . PHP_EOL;
            echo $proc->getErrorOutput() . PHP_EOL;
        } elseif (! $silence) {
            echo "success ($duration)\n";
        }

        return $proc->getExitCode();
    }

    public function checkEnvSkips(): void
    {
        $this->flags['unit_skip'] = $this->flags['unit_skip'] || getenv('SKIP_UNIT_CHECK');
        $this->flags['lint_skip'] = $this->flags['lint_skip'] || getenv('SKIP_LINT_CHECK');
        $this->flags['web_skip'] = $this->flags['web_skip'] || getenv('SKIP_WEB_CHECK');
        $this->flags['style_skip'] = $this->flags['style_skip'] || getenv('SKIP_STYLE_CHECK');
    }

    public function detectChangedFiles(): void
    {
        $changed_files = trim(getenv('FILES')) ?:
            exec("git diff --diff-filter=d --name-only master | tr '\n' ' '|sed 's/,*$//g'");

        $this->flags['full'] = $this->flags['full'] || empty($changed_files); // don't disable full if already set
        $files = $changed_files ? explode(' ', $changed_files) : [];

        $this->changed = (new FileCategorizer($files))->categorize();
        $this->parseChangedFiles();
    }

    private function parseChangedFiles(): void
    {
        if ($this->flags['full'] || ! empty($this->changed['full-checks'])) {
            $this->flags['full'] = true; // make sure full is set and skip changed file parsing

            return;
        }
        $this->os = $this->os ?: $this->changed['os'];

        $this->setFlags([
            'lint_skip_php' => empty($this->changed['php']),
            'lint_skip_phpstan' => $this->flags['ci'] || empty($this->changed['php']),
            'lint_skip_python' => empty($this->changed['python']),
            'lint_skip_bash' => empty($this->changed['bash']),
            'unit_os' => $this->getFlag('unit_os') || (! empty($this->changed['os']) && empty(array_diff($this->changed['php'], $this->changed['os-files']))),
            'unit_docs' => ! empty($this->changed['docs']) && empty($this->changed['php']),
            'unit_svg' => ! empty($this->changed['svg']) && empty($this->changed['php']),
            'docs_changed' => ! empty($this->changed['docs']),
        ]);

        $this->setFlags([
            'unit_skip' => empty($this->changed['php']) && ! array_sum(Arr::only($this->getFlags(), ['unit_os', 'unit_docs', 'unit_svg', 'unit_modules', 'docs_changed'])),
            'lint_skip' => array_sum(Arr::only($this->getFlags(), ['lint_skip_php', 'lint_skip_phpstan', 'lint_skip_python', 'lint_skip_bash'])) === 4,
            'style_skip' => ! $this->flags['ci'] && empty($this->changed['php']),
            'web_skip' => empty($this->changed['php']) && empty($this->changed['resources']),
        ]);
    }

    /**
     * Check for a PHP executable and return the path to it
     * If it does not exist, run composer.
     * If composer isn't installed, print error and exit.
     *
     * @param  string  $exec  the name of the executable to check
     * @return string path to the executable
     */
    private function checkPhpExec(string $exec): string
    {
        $path = "vendor/bin/$exec";

        if (is_executable($path)) {
            return $path;
        }

        echo "Running composer install to install developer dependencies.\n";
        passthru('scripts/composer_wrapper.php install');

        if (is_executable($path)) {
            return $path;
        }

        echo "\nRunning installing deps with composer failed.\n You should try running './scripts/composer_wrapper.php install' by hand\n";
        echo "You can find more info at https://docs.librenms.org/Developing/Validating-Code/\n";
        exit(1);
    }

    /**
     * Check for a Python executable and return the path to it
     * If it does not exist, run pip3.
     * If pip3 isn't installed, print error and exit.
     *
     * @param  string  $exec  the name of the executable to check
     * @return string path to the executable
     */
    private function checkPythonExec(string $exec): string
    {
        $home = getenv('HOME');
        $path = "$home/.local/bin/$exec";

        if (is_executable($path)) {
            return $path;
        }

        // check system
        $system_path = rtrim(exec('which pylint 2>/dev/null'));
        if (is_executable($system_path)) {
            return $system_path;
        }

        echo "Running pip3 install to install developer dependencies.\n";
        passthru("pip3 install --user $exec"); // probably wrong in other cases...

        if (is_executable($path)) {
            return $path;
        }

        echo "\nRunning installing deps with pip3 failed.\n You should try running 'pip3 install --user ";
        echo $exec;
        echo "' by hand\n";
        echo "You can find more info at https://docs.librenms.org/Developing/Validating-Code/\n";
        exit(1);
    }
}
