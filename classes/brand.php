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
 * Brand identity constants consumed at runtime.
 *
 * This file ships the Constructor (default) identity. The release script
 * overlays a brand-specific copy from branding/<brand>/classes/brand.php
 * when packaging a non-default variant.
 */
class brand {
    /** Brand identifier slug. */
    const ID = 'constructor';

    /** Human-readable plugin name shown in plugin lists and settings titles. */
    const DISPLAY_NAME = 'Constructor Proctor';

    /**
     * Default hostname pre-filled into the proctor_url admin setting on a
     * fresh install. The runtime always prepends https:// before this value
     * (see client::api_url / client::form_url), so store the host only.
     */
    const DEFAULT_PROCTOR_URL = '';

    /**
     * Integration-settings keys (the second part of admin_setting_configtext's
     * 'availability_proctor/<key>' identifier) hidden from the admin settings
     * page for this brand. Empty array = show everything.
     */
    const HIDDEN_INTEGRATION_SETTINGS = [];

    /**
     * Form element names hidden from preset_form for this brand. The literal
     * string 'rules' hides the entire allowed-items section (header + every
     * rules[<key>] checkbox). Empty array = show all.
     */
    const HIDDEN_FORM_FIELDS = [];

    /**
     * Should this integration setting be rendered on the admin page?
     *
     * @param string $key The setting name, without the plugin prefix.
     * @return bool True when the brand does not hide it.
     */
    public static function is_setting_visible($key) {
        return !in_array($key, static::HIDDEN_INTEGRATION_SETTINGS, true);
    }

    /**
     * Should this activity-form field, or whole section, be rendered?
     *
     * Note that hiding a field is not only cosmetic: condition::__construct forces
     * every hidden field to the default preset's value, so what a brand hides it
     * also decides. See condition::PRESET_FIELDS.
     *
     * @param string $name The field name, or a section name such as 'rules'.
     * @return bool True when the brand does not hide it.
     */
    public static function is_form_field_visible($name) {
        return !in_array($name, static::HIDDEN_FORM_FIELDS, true);
    }
}
