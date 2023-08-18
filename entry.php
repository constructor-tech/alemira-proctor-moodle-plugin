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

    complete_user_login($user);
}

\availability_proctor\utils::handle_accesscode_param($accesscode);
