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
$accesscode = required_param('proctor_accesscode', PARAM_ALPHANUM);

$seamlessauth = get_config('availability_proctor', 'seamless_auth');

if ($seamlessauth && $token) {
    // Look up the entry first so we can validate the token is bound to this specific exam.
    if (!$entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode])) {
        throw new \moodle_exception('error_no_entry_found', 'availability_proctor');
    }

    $key = validate_user_key($token, 'availability_proctor', $entry->id);

    if (!$user = $DB->get_record('user', ['id' => $key->userid])) {
        throw new \moodle_exception('invaliduserid');
    }

    core_user::require_active_user($user, true, true);

    // Delete the key immediately — token is single-use.
    $DB->delete_records('user_private_key', ['id' => $key->id]);

    complete_user_login($user);

    $entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);
    if ($entry) {
        \availability_proctor\event\user_logged_in_via_token::create([
            'objectid' => $entry->id,
            'context'  => \context_module::instance($entry->cmid),
            'userid'   => $user->id,
        ])->trigger();
    }
}

// Without a token (seamless auth disabled, or learner manually following the
// link) we still need an authenticated session before touching the session
// cache state in handle_accesscode_param. No-op for users already logged in.
require_login();

\availability_proctor\utils::handle_accesscode_param($accesscode);
