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
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * Certory brand overlay. The release script copies this on top of the
 * default brand.php when packaging the Certory build, after applying the
 * same textual substitutions that every other source file goes through.
 */
class brand {
    const ID = 'certory';
    const DISPLAY_NAME = 'Proctor by Certory';
    const DEFAULT_PROCTOR_URL = 'proctoring-1.certory.org';

    const HIDDEN_INTEGRATION_SETTINGS = [
        'user_emails',
        'seamless_auth',
    ];

    const HIDDEN_FORM_FIELDS = [
        // preset_form section meta-keys (hide entire section block).
        'preset_section_mode',
        'preset_section_identity',
        'preset_section_securebrowser',

        // Generic section meta-keys for the preset edit form.
        'warnings',
        'scoring',

        // Individual field keys.
        'mode',
        'identification',
        'checkidphotoquality',
        'preliminarycheck',               // demo/mock exam
        'sendmanualwarningstolearner',
        'webcameramainview',
        'auxiliarycamera',
        'allowroomscanauxcamera',
        'streamspreset',
        'securebrowser',
        'securebrowserlevel',
        'allowtouseadditionalresources',
        'allowedprocesses',
        'forbiddenprocesses',
        'allowvirtualenvironment',
        'proctoremails',
    ];

    public static function is_setting_visible($key) {
        return !in_array($key, static::HIDDEN_INTEGRATION_SETTINGS, true);
    }

    public static function is_form_field_visible($name) {
        return !in_array($name, static::HIDDEN_FORM_FIELDS, true);
    }
}
