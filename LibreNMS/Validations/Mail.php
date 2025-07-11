<?php

/**
 * Mail.php
 *
 * -Description-
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

namespace LibreNMS\Validations;

use App\Facades\LibrenmsConfig;
use LibreNMS\Validator;

class Mail extends BaseValidation
{
    protected static $RUN_BY_DEFAULT = false;

    /**
     * Validate this module.
     * To return ValidationResults, call ok, warn, fail, or result methods on the $validator
     *
     * @param  Validator  $validator
     */
    public function validate(Validator $validator): void
    {
        if (LibrenmsConfig::get('alert.transports.mail') === true) {
            $run_test = 1;
            if (! LibrenmsConfig::has('alert.default_mail')) {
                $validator->fail('default_mail config option needs to be specified to test email');
                $run_test = 0;
            } elseif (LibrenmsConfig::get('email_backend') == 'sendmail') {
                if (! LibrenmsConfig::has('email_sendmail_path')) {
                    $validator->fail('You have selected sendmail but not configured email_sendmail_path');
                    $run_test = 0;
                } elseif (! file_exists(LibrenmsConfig::get('email_sendmail_path'))) {
                    $validator->fail('The configured email_sendmail_path is not valid');
                    $run_test = 0;
                }
            } elseif (LibrenmsConfig::get('email_backend') == 'smtp') {
                if (! LibrenmsConfig::has('email_smtp_host')) {
                    $validator->fail('You have selected SMTP but not configured an SMTP host');
                    $run_test = 0;
                }
                if (! LibrenmsConfig::has('email_smtp_port')) {
                    $validator->fail('You have selected SMTP but not configured an SMTP port');
                    $run_test = 0;
                }
                if (LibrenmsConfig::get('email_smtp_auth')
                    && (! LibrenmsConfig::has('email_smtp_username') || ! LibrenmsConfig::has('email_smtp_password'))
                ) {
                    $validator->fail('You have selected SMTP auth but have not configured both username and password');
                    $run_test = 0;
                }
            }//end if
            if ($run_test == 1) {
                $email = LibrenmsConfig::get('alert.default_mail');
                try {
                    \LibreNMS\Util\Mail::send($email, 'Test email', 'Testing email from NMS');
                    $validator->ok('Email has been sent');
                } catch (\Exception $e) {
                    $validator->fail("Issue sending email to $email with error " . $e->getMessage());
                }
            }
        }//end if
    }
}
