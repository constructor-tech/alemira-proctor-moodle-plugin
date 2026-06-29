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
 * External function: save a personal proctoring preset.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use availability_proctor\preset;

/**
 * Save a personal preset owned by the authenticated user.
 */
class save_user_preset extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID used for the capability check'),
            'name'     => new external_value(PARAM_TEXT, 'Human-readable preset name'),
            'payload'  => new external_value(PARAM_RAW, 'JSON-encoded preset field values'),
        ]);
    }

    public static function execute(int $courseid, string $name, string $payload): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name'     => $name,
            'payload'  => $payload,
        ]);

        $coursecontext = context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $name = trim($params['name']);
        if ($name === '') {
            throw new \moodle_exception('error_preset_name_required', 'availability_proctor');
        }

        $fields = json_decode($params['payload'], true);
        if (!is_array($fields)) {
            throw new \moodle_exception('error_invalid_payload', 'availability_proctor');
        }

        $id = preset::save_user_preset($USER->id, $name, $fields);
        $record = preset::get_by_id($id);

        // Return the full preset as JSON so the JS picker can push it into ctx.user_presets
        // and immediately render it without a page reload. The preset object contains nested
        // arrays (rules/warnings/scoring) that cannot be declared cleanly in the Moodle
        // external-type system, so we encode the whole record here and let JS parse it.
        return ['ok' => true, 'preset' => json_encode($record)];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok'     => new external_value(PARAM_BOOL, 'Whether the preset was saved successfully'),
            'preset' => new external_value(PARAM_RAW, 'JSON-encoded saved preset record'),
        ]);
    }
}
