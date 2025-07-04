<?php

/**
 * Processor.php
 *
 * Processor Module
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
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Device;

use App\Facades\LibrenmsConfig;
use App\Models\Eventlog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\Discovery\DiscoveryItem;
use LibreNMS\Interfaces\Discovery\DiscoveryModule;
use LibreNMS\Interfaces\Polling\PollerModule;
use LibreNMS\Interfaces\Polling\ProcessorPolling;
use LibreNMS\Model;
use LibreNMS\OS;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\Util\Oid;

class Processor extends Model implements DiscoveryModule, PollerModule, DiscoveryItem
{
    protected static $table = 'processors';
    protected static $primaryKey = 'processor_id';

    private $valid = true;

    public $processor_id;
    public $device_id;
    public $processor_type;
    public $processor_usage;
    public $processor_oid;
    public $processor_index;
    public $processor_descr;
    public $processor_precision;
    public $entPhysicalIndex;
    public $hrDeviceIndex;
    public $processor_perc_warn;

    /**
     * Processor constructor.
     *
     * @param  string  $type
     * @param  int  $device_id
     * @param  string  $oid
     * @param  int|string  $index
     * @param  string  $description
     * @param  int  $precision  The returned value will be divided by this number (should be factor of 10) If negative this oid returns idle cpu
     * @param  int|null  $current_usage
     * @param  int|null  $warn_percent
     * @param  int|null  $entPhysicalIndex
     * @param  int|null  $hrDeviceIndex
     * @return static
     */
    public static function discover(
        $type,
        $device_id,
        $oid,
        $index,
        $description = 'Processor',
        $precision = 1,
        $current_usage = null,
        $warn_percent = null,
        $entPhysicalIndex = null,
        $hrDeviceIndex = null
    ) {
        $proc = new static();
        $proc->processor_type = $type;
        $proc->device_id = $device_id;
        $proc->processor_index = (string) $index;
        $proc->processor_descr = $description;
        $proc->processor_precision = $precision;
        $proc->processor_usage = $current_usage;
        $proc->entPhysicalIndex = $entPhysicalIndex;
        $proc->hrDeviceIndex = $hrDeviceIndex;

        // handle string indexes
        if (Str::contains($oid, '"')) {
            $oid = preg_replace_callback('/"([^"]+)"/', function ($matches) {
                return Oid::encodeString($matches[1])->oid;
            }, $oid);
        }
        $proc->processor_oid = '.' . ltrim($oid, '.');

        $proc->processor_perc_warn = $warn_percent ?? LibrenmsConfig::get('processor_perc_warn', 75);

        // validity not checked yet
        if (is_null($proc->processor_usage)) {
            $data = snmp_get(device_by_id_cache($proc->device_id), $proc->processor_oid, '-Ovq');
            $proc->valid = ($data !== false);
            if (! $proc->valid) {
                return $proc;
            }
            $proc->processor_usage = static::processData($data, $proc->processor_precision);
        }

        d_echo('Discovered ' . get_called_class() . ' ' . print_r($proc->toArray(), true));

        return $proc;
    }

    public static function fromYaml(OS $os, $index, array $data)
    {
        $precision = empty($data['precision']) ? 1 : $data['precision'];

        return static::discover(
            empty($data['type']) ? $os->getName() : $data['type'],
            $os->getDeviceId(),
            $data['num_oid'],
            isset($data['index']) ? $data['index'] : $index,
            empty($data['descr']) ? 'Processor' : trim($data['descr']),
            $precision,
            static::processData($data['value'], $precision),
            $data['warn_percent'] ?? null,
            $data['entPhysicalIndex'] ?? null,
            $data['hrDeviceIndex'] ?? null
        );
    }

    public static function runDiscovery(OS $os)
    {
        // check yaml first
        $processors = self::processYaml($os);

        // if no processors found, check OS discovery (which will fall back to HR and UCD if not implemented
        if (empty($processors)) {
            $processors = $os->discoverProcessors();
        }

        foreach ($processors as $processor) {
            $processor->processor_descr = substr($processor->processor_descr, 0, 64);
            $processor->processor_type = substr($processor->processor_type, 0, 16);
        }

        if (isset($processors) && is_array($processors)) {
            self::sync(
                $os->getDeviceId(),
                $processors,
                ['device_id', 'processor_index', 'processor_type'],
                ['processor_usage', 'processor_perc_warn']
            );
        }

        dbDeleteOrphans(static::$table, ['devices.device_id']);

        echo PHP_EOL;
    }

    public static function poll(OS $os)
    {
        $processors = dbFetchRows('SELECT * FROM processors WHERE device_id=?', [$os->getDeviceId()]);

        if ($os instanceof ProcessorPolling) {
            $data = $os->pollProcessors($processors);
        } else {
            $data = static::pollProcessors($os, $processors);
        }

        $rrd_def = RrdDefinition::make()->addDataset('usage', 'GAUGE', -273, 1000);

        foreach ($processors as $index => $processor) {
            extract($processor); // extract db fields to variables
            /** @var int $processor_id */
            /** @var string $processor_type */
            /** @var int $processor_index */
            /** @var int $processor_usage */
            /** @var string $processor_descr */
            if (array_key_exists($processor_id, $data)) {
                $usage = round($data[$processor_id], 2);
                Log::info("$processor_descr: $usage%");

                $rrd_name = ['processor', $processor_type, $processor_index];
                $fields = ['usage' => $usage];
                $tags = ['processor_type' => $processor_type, 'processor_index' => $processor_index, 'rrd_name' => $rrd_name, 'rrd_def' => $rrd_def];
                app('Datastore')->put($os->getDeviceArray(), 'processors', $tags, $fields);

                if ($usage != $processor_usage) {
                    dbUpdate(['processor_usage' => $usage], 'processors', '`processor_id` = ?', [$processor_id]);
                }
            }
        }
    }

    private static function pollProcessors(OS $os, $processors)
    {
        if (empty($processors)) {
            return [];
        }

        $oids = array_column($processors, 'processor_oid');

        // don't fetch too many at a time TODO build into snmp_get_multi_oid?
        $snmp_data = [];
        foreach (array_chunk($oids, get_device_oid_limit($os->getDeviceArray())) as $oid_chunk) {
            $multi_data = snmp_get_multi_oid($os->getDeviceArray(), $oid_chunk);
            $snmp_data = array_merge($snmp_data, $multi_data);
        }

        d_echo($snmp_data);

        $results = [];
        foreach ($processors as $processor) {
            if (isset($snmp_data[$processor['processor_oid']])) {
                $value = static::processData(
                    $snmp_data[$processor['processor_oid']],
                    $processor['processor_precision']
                );
            } else {
                $value = 0;
            }

            $results[$processor['processor_id']] = $value;
        }

        return $results;
    }

    private static function processData($data, $precision)
    {
        if (preg_match('/([0-9]{1,5}(\.[0-9]+)?)/', $data, $matches) !== 1) {
            return null;
        }
        $value = (float) $matches[1];

        if ($precision < 0) {
            // idle value, subtract from 100
            $value = 100 - ($value / ($precision * -1));
        } elseif ($precision > 1) {
            $value = $value / $precision;
        }

        return $value;
    }

    public static function processYaml(OS $os)
    {
        $discovery = $os->getDiscovery('processors');

        if (empty($discovery)) {
            d_echo("No YAML Discovery data.\n");

            return [];
        }

        return YamlDiscovery::discover($os, get_called_class(), $discovery);
    }

    /**
     * Is this sensor valid?
     * If not, it should not be added to or in the database
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * Get an array of this sensor with fields that line up with the database.
     *
     * @param  array  $exclude  exclude columns
     * @return array
     */
    public function toArray($exclude = [])
    {
        $array = [
            'processor_id' => $this->processor_id,
            'entPhysicalIndex' => (int) $this->entPhysicalIndex,
            'hrDeviceIndex' => (int) $this->hrDeviceIndex,
            'device_id' => $this->device_id,
            'processor_oid' => $this->processor_oid,
            'processor_index' => $this->processor_index,
            'processor_type' => $this->processor_type,
            'processor_usage' => $this->processor_usage,
            'processor_descr' => $this->processor_descr,
            'processor_precision' => (int) $this->processor_precision,
            'processor_perc_warn' => (int) $this->processor_perc_warn,
        ];

        return array_diff_key($array, array_flip($exclude));
    }

    /**
     * @param  Processor  $processor
     */
    public static function onCreate($processor)
    {
        $message = "Processor Discovered: {$processor->processor_type} {$processor->processor_index} {$processor->processor_descr}";
        Eventlog::log($message, $processor->device_id, static::$table, Severity::Notice, $processor->processor_id);

        parent::onCreate($processor);
    }

    /**
     * @param  Processor  $processor
     */
    public static function onDelete($processor)
    {
        $message = "Processor Removed: {$processor->processor_type} {$processor->processor_index} {$processor->processor_descr}";
        Eventlog::log($message, $processor->device_id, static::$table, Severity::Notice, $processor->processor_id);

        parent::onDelete($processor); // TODO: Change the autogenerated stub
    }
}
