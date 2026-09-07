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
 * Single-use Moodle login bridge for an SDK session in Secure Browser.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$sessionid = required_param('sessionid', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

$proctorsession = \availability_proctor\secure_browser::authenticate($token, $sessionid);

$cm = get_coursemodule_from_instance(
    'quiz',
    (int) $proctorsession->quizid,
    0,
    false,
    MUST_EXIST
);

// Authentication is only the native-browser hand-off. Return to the plugin
// launch page so the SDK initializes inside Secure Browser and performs its
// system checks before Moodle serves the quiz in the launch page's iframe.
// Redirecting straight to mod/quiz/view.php would create a valid Moodle attempt
// while the Constructor session remained ready_to_start and unrecorded.
redirect(new moodle_url('/availability/condition/proctor/launch.php', [
    'cmid' => (int) $cm->id,
]));
