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
        // Which integration delivers proctoring. Listed first because it decides
        // how every setting below it is used.
        //
        // The default is deliberately the hosted model rather than the newer SDK
        // one. db/install.php writes 'sdk' for a clean install and db/upgrade.php
        // writes 'hosted' for an existing site, so this default is only ever
        // reached if neither ran -- and in that case an existing site must come up
        // with the behaviour it already had. Declaring 'sdk' here would invert
        // that: a partial or failed upgrade would silently move live exams onto a
        // different integration.
        //
        // NTG-185: the SDK model requires Moodle 4.5, so on an older site it is not
        // offered -- an administrator should not be able to choose a model that
        // config::is_sdk_model() will then decline to run.
        //
        // The stored value is deliberately NOT rewritten when it is unavailable. A
        // site that has 'sdk' stored (a restored database, a copied config, or
        // forced_plugin_settings) runs the hosted flow and keeps the row, so that
        // upgrading to 4.5 puts the intended model into effect rather than requiring
        // the setting to be found and re-entered. Overwriting it would also leave the
        // administrator no way to tell that their choice had been discarded rather
        // than never applied.
        $modeloptions = [
            \availability_proctor\config::MODEL_HOSTED =>
                new lang_string('settings_proctormodel_hosted', 'availability_proctor'),
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
            \availability_proctor\config::MODEL_HOSTED,
            $modeloptions
        ));

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

        // NTG-185: the SDK's init() parameters.
        //
        // Shown only under the SDK model -- under the hosted one Constructor's own
        // page supplies these and nothing this plugin sends carries them, so the
        // section would be sixteen controls that do nothing.
        //
        // Booleans are configselect, never configcheckbox. A checkbox draws its tick
        // from the stored value rather than the declared default, so a setting that
        // has never been written renders unticked beside the words "Default: Yes",
        // and saving the page stores the 0 the empty box implies. That switched off
        // seven settings in a single save when this section was first tried, the
        // full-screen violation alerts among them. A select stores the chosen value
        // explicitly and shows the declared default when nothing is stored.
        if (\availability_proctor\config::is_sdk_model()) {
            $pluginsettings->add(new admin_setting_heading(
                'availability_proctor/sdk_heading',
                new lang_string('settings_sdk_heading', 'availability_proctor'),
                new lang_string('settings_sdk_heading_desc', 'availability_proctor')
            ));

            $yesno = [0 => new lang_string('no'), 1 => new lang_string('yes')];

            // Rendered group by group rather than straight down SITE_SDK_SETTINGS.
            // That map is alphabetical, which put disabledWorkers -- the one setting
            // here that can leave an exam unmonitored -- fifth of sixteen, between two
            // cosmetic ones. SITE_SDK_GROUPS carries the reading order and the
            // headings; SITE_SDK_SETTINGS stays the name-to-parameter map.
            foreach (\availability_proctor\config::SITE_SDK_GROUPS as $groupstring => $names) {
                $pluginsettings->add(new admin_setting_heading(
                    'availability_proctor/' . $groupstring,
                    new lang_string($groupstring, 'availability_proctor'),
                    ''
                ));

                foreach ($names as $name) {
                    // A name here with no entry in SITE_SDK_SETTINGS is a typo in this
                    // plugin, not a site's problem: skip it rather than raise a notice
                    // across the whole settings page.
                    $key = \availability_proctor\config::SITE_SDK_SETTINGS[$name] ?? null;
                    if ($key === null) {
                        continue;
                    }

                    $stringid = \availability_proctor\config::SITE_SDK_STRINGS[$name];
                    $label = new lang_string($stringid, 'availability_proctor');
                    $desc = new lang_string($stringid . '_desc', 'availability_proctor');
                    $default = \availability_proctor\config::SDK_CONFIG[$key] ?? null;

                    if (is_bool($default)) {
                        $pluginsettings->add(new admin_setting_configselect(
                            'availability_proctor/' . $name,
                            $label,
                            $desc,
                            $default ? 1 : 0,
                            $yesno
                        ));
                        continue;
                    }

                    if ($key === \availability_proctor\config::ROOM_SCAN_SETTING) {
                        // Seconds on this page, milliseconds on the wire; sdk_config()
                        // multiplies. 0 is a real choice meaning "no Room Scan screen".
                        $pluginsettings->add(new admin_setting_configtext(
                            'availability_proctor/' . $name,
                            $label,
                            $desc,
                            (string) (int) ($default / \availability_proctor\config::ROOM_SCAN_SCALE),
                            PARAM_INT,
                            6
                        ));
                        continue;
                    }

                    if (is_array($default)) {
                        // disabledWorkers is one worker name per line; the other two
                        // are nested language-keyed JSON. Both are read back by
                        // config::cast_sdk_value(), which decides on the content.
                        $pluginsettings->add(new admin_setting_configtextarea(
                            'availability_proctor/' . $name,
                            $label,
                            $desc,
                            implode("\n", $default),
                            PARAM_RAW
                        ));
                        continue;
                    }

                    $pluginsettings->add(new admin_setting_configtext(
                        'availability_proctor/' . $name,
                        $label,
                        $desc,
                        (string) $default,
                        PARAM_RAW_TRIMMED
                    ));
                }
            }
        }
    }
}
