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
 * @copyright  2019-2023 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

$token = optional_param('token', null, PARAM_ALPHANUM);
$accesscode = required_param('proctor_accesscode', PARAM_RAW);

$seamlessauth = get_config('availability_proctor', 'seamless_auth');

if ($seamlessauth && $token) {
    $script = 'availability_proctor';
    $key = validate_user_key($token, $script, null);

    if (!$user = $DB->get_record('user', ['id' => $key->userid])) {
        print_error('invaliduserid');
    }

    core_user::require_active_user($user, true, true);

    // Emulate normal session.
    enrol_check_plugins($user);
    \core\session\manager::set_user($user);
}

availability_proctor_handle_accesscode_param($accesscode);

/**
 * If accesscode param is provider, find entry, handle it's state.
 * @param string $accesscode Accesscode/SessionId value
 */
function availability_proctor_handle_accesscode_param($accesscode) {
    global $SESSION, $DB;

    // User is coming from proctor, reset is done if it was requested before.
    unset($SESSION->availability_proctor_reset);

    $SESSION->availability_proctor_accesscode = $accesscode;

    // We know accesscode is passed in params.
    $entry = $DB->get_record('availability_proctor_entries', [
        'accesscode' => $accesscode,
    ]);

    // If entry exists, we need to check if we have a newer one.
    if ($entry) {
        $modinfo = get_fast_modinfo($entry->courseid);
        $cminfo = $modinfo->get_cm($entry->cmid);

        $condition = \availability_proctor\condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return;
        }

        $newentry = \availability_proctor\common::most_recent_entry($entry);
        if ($newentry && $newentry->id != $entry->id) {
            $entry = $newentry;
            $SESSION->availability_proctor_reset = true;
        }

        $modinfo = get_fast_modinfo($entry->courseid);
        $cminfo = $modinfo->get_cm($entry->cmid);

        // The entry is already finished or canceled, we need to reset it.
        if (!in_array($entry->status, ['new', 'scheduled', 'started'])) {
            $entry = \availability_proctor\common::create_entry($condition, $entry->userid, $cminfo);
            $SESSION->availability_proctor_reset = true;
        }
    } else {
        // If entry does not exist, we need to create a new one and redirect.
        $SESSION->availability_proctor_reset = true;
    }

    if ($entry) {
        $quizurl = new \moodle_url('/mod/quiz/view.php', ['id' => $cminfo->id]);
        redirect($quizurl);
        exit;
    } else {
        throw new \moodle_exception('error_no_entry_found', 'availability_proctor');
    }

}
