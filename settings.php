<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Availability plugin for integration with Proctor by Constructor.
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = null;
    $pluginsettings = new admin_settingpage('manageavailabilityproctor', new lang_string('settings', 'availability_proctor'));

    $pluginname = get_string('pluginname', 'availability_proctor');

    $logpage = new admin_externalpage(
        'availability_proctor_log',
        get_string('log_section', 'availability_proctor', $pluginname),
        $CFG->wwwroot . '/availability/condition/proctor/index.php',
        'availability/proctor:logaccess'
    );

    $presetspage = new admin_externalpage(
        'availability_proctor_presets',
        get_string('presets', 'availability_proctor'),
        $CFG->wwwroot . '/availability/condition/proctor/presets.php',
        'availability/proctor:managepresets'
    );

    $category = new admin_category('availability_proctor_admin', new lang_string('pluginname', 'availability_proctor'));

    $ADMIN->add('availabilitysettings', $category);
    $ADMIN->add('reports', $logpage);
    $ADMIN->add('availability_proctor_admin', $presetspage);
    $ADMIN->add('availability_proctor_admin', $pluginsettings);

    if ($ADMIN->fulltree) {
        $pluginsettings->add(new admin_setting_configtext(
            'availability_proctor/proctor_url',
            new lang_string('settings_proctor_url', 'availability_proctor', $pluginname),
            new lang_string('settings_proctor_url_desc', 'availability_proctor'),
            \availability_proctor\brand::DEFAULT_PROCTOR_URL,
            PARAM_HOST
        ));

        $pluginsettings->add(new admin_setting_configtext(
            'availability_proctor/integration_name',
            new lang_string('settings_integration_name', 'availability_proctor'),
            new lang_string('settings_integration_name_desc', 'availability_proctor'),
            '',
            PARAM_TEXT
        ));

        $pluginsettings->add(new admin_setting_configpasswordunmask(
            'availability_proctor/jwt_secret',
            new lang_string('settings_jwt_secret', 'availability_proctor'),
            new lang_string('settings_jwt_secret_desc', 'availability_proctor'),
            ''
        ));

        $pluginsettings->add(new admin_setting_configtext(
            'availability_proctor/account_id',
            new lang_string('settings_account_id', 'availability_proctor'),
            new lang_string('settings_account_id_desc', 'availability_proctor'),
            '',
            PARAM_TEXT
        ));

        $pluginsettings->add(new admin_setting_configtext(
            'availability_proctor/account_name',
            new lang_string('settings_account_name', 'availability_proctor'),
            new lang_string('settings_account_name_desc', 'availability_proctor'),
            '',
            PARAM_TEXT
        ));

        if (\availability_proctor\brand::is_setting_visible('user_emails')) {
            $pluginsettings->add(new admin_setting_configcheckbox(
                'availability_proctor/user_emails',
                new lang_string('settings_user_emails', 'availability_proctor', $pluginname),
                new lang_string('settings_user_emails_desc', 'availability_proctor'),
                1
            ));
        }

        if (\availability_proctor\brand::is_setting_visible('seamless_auth')) {
            $pluginsettings->add(new admin_setting_configcheckbox(
                'availability_proctor/seamless_auth',
                new lang_string('settings_seamless_auth', 'availability_proctor'),
                new lang_string('settings_seamless_auth_desc', 'availability_proctor'),
                1
            ));
        }

        // A brand without the SDK model gets no selector and no unsupported note;
        // config::is_sdk_model() also ignores a stored 'sdk' for that brand.
        if (\availability_proctor\brand::is_sdk_model_enabled()) {
            // Which integration delivers proctoring. It is rendered immediately after
            // Seamless authorization. SDK configuration belongs only to presets, so no
            // SDK configuration section follows on this integration settings page.
            //
            // The default is the model a clean install picks. A CLI install, and the
            // PHPUnit and Behat sites, apply every declared default after
            // db/install.php has run and overwrite what it wrote, so a 'webapp'
            // default here put those sites on Webapp while a web install got SDK.
            // Existing sites are not affected: db/upgrade.php writes 'webapp' before
            // Moodle applies defaults, and an upgrade only fills settings that have no
            // value.
            //
            // NTG-185: the SDK model requires Moodle 3.11, so on an older site it is not
            // offered -- an administrator should not be able to choose a model that
            // config::is_sdk_model() will then decline to run.
            //
            // The stored value is deliberately NOT rewritten when it is unavailable. A
            // site that has 'sdk' stored (a restored database, a copied config, or
            // forced_plugin_settings) runs the Webapp flow and keeps the row, so that
            // upgrading to 3.11 puts the intended model into effect rather than requiring
            // the setting to be found and re-entered. Overwriting it would also leave the
            // administrator no way to tell that their choice had been discarded rather
            // than never applied.
            $modeloptions = [
                \availability_proctor\config::MODEL_WEBAPP =>
                    new lang_string('settings_proctormodel_webapp', 'availability_proctor'),
            ];

            if (\availability_proctor\config::is_sdk_available()) {
                $modeloptions[\availability_proctor\config::MODEL_SDK] =
                    new lang_string('settings_proctormodel_sdk', 'availability_proctor');
            } else {
                $pluginsettings->add(new admin_setting_description(
                    'availability_proctor/proctormodel_unsupported',
                    '',
                    new lang_string('settings_proctormodel_unsupported', 'availability_proctor')
                ));
            }

            $pluginsettings->add(new admin_setting_configselect(
                'availability_proctor/proctormodel',
                new lang_string('settings_proctormodel', 'availability_proctor'),
                new lang_string('settings_proctormodel_desc', 'availability_proctor', $pluginname),
                \availability_proctor\config::initial_proctoring_model(),
                $modeloptions
            ));
        }
    }
}
