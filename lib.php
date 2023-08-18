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

use availability_proctor\state;
use availability_proctor\utils;

/**
 * Hooks into head rendering. Adds proctoring fader/shade and accompanying javascript
 * This is used to prevent users from seeing questions before it is known that
 * attempt is viewed thorough Proctor by Constructor WebApp
 *
 * @return string
 */
function availability_proctor_before_standard_html_head() {
    global $DB, $USER;

    // If there is no active attempt, do nothing.
    if (isset(state::$attempt['attempt_id'])) {
        $attemptid = state::$attempt['attempt_id'];
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt || $attempt->state != \quiz_attempt::IN_PROGRESS) {
            return '';
        } else {
            return utils::handle_proctoring_fader($attempt);
        }
    } else {
        return '';
    }
}

/**
 * This hook is used for exams that require scheduling.
 **/
function availability_proctor_after_require_login() {
    global $USER, $DB;

    // User is trying to start an attempt, redirect to proctor if it is not started.
    $scriptname = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null;
    if ($scriptname == '/mod/quiz/startattempt.php') {
        $cmid = required_param('cmid', PARAM_INT); // Course module id.

        if (!$cm = get_coursemodule_from_id('quiz', $cmid)) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
            throw new \moodle_exception("coursemisconf");
        }

        utils::handle_start_attempt($course, $cm, $USER);
    }
}
