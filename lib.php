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
use availability_proctor\hooks;
use availability_proctor\session_cache;

/**
 * Hooks into head rendering. Adds proctoring fader/shade and accompanying javascript
 * This is used to prevent users from seeing questions before it is known that
 * attempt is viewed thorough Proctor by Constructor WebApp
 *
 * @return string
 */
function availability_proctor_before_standard_html_head() {
    return hooks::before_standard_head_html_generation();
}

/**
 * This hook is used for exams that require scheduling.
 **/
function availability_proctor_after_require_login() {
    global $USER, $DB, $SCRIPT;

    // User is trying to start an attempt, redirect to proctor if it is not started.
    // $SCRIPT (set by initialise_fullme() in setup.php) is the path of the actually
    // executing script relative to wwwroot. Do NOT use qualified_me()/$PAGE->url here:
    // mod/quiz/startattempt.php sets its page URL to view.php before require_login(),
    // so the URL-based value never matches and the attempt would be created (and the
    // quiz timer started) before the user is redirected to proctoring.
    $scriptname = $SCRIPT;
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

    // SCORM: intercept the view page to enforce proctoring before the SCO is launched.
    if ($scriptname == '/mod/scorm/view.php') {
        $id = optional_param('id', 0, PARAM_INT);

        if ($id) {
            if (!$cm = get_coursemodule_from_id('scorm', $id)) {
                throw new \moodle_exception('invalidcoursemodule');
            }
            if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
                throw new \moodle_exception("coursemisconf");
            }

            utils::handle_start_attempt_scorm($course, $cm, $USER);
        }
    }

    // SCORM player: user clicked Enter on the view page; apply lockdown if session has
    // a proctor accesscode (set by scorm.php bridge on the preceding view.php request).
    if ($scriptname == '/mod/scorm/player.php') {
        if (!empty(session_cache::get_accesscode())) {
            \availability_proctor\state::$lockdown = true;
        }
    }

    // Assign: intercept the view page to enforce proctoring before the assignment is shown.
    if ($scriptname == '/mod/assign/view.php') {
        $id = optional_param('id', 0, PARAM_INT);

        if ($id) {
            if (!$cm = get_coursemodule_from_id('assign', $id)) {
                throw new \moodle_exception('invalidcoursemodule');
            }
            if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
                throw new \moodle_exception("coursemisconf");
            }

            utils::handle_start_attempt_assign($course, $cm, $USER);
        }
    }
}

/**
 * Extend homepage navigation
 * @param navigation_node $parentnode The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param context_course $context Cource context
 **/
function availability_proctor_extend_navigation_frontpage(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
) {
    if (has_capability('availability/proctor:logaccess', $context)) {
        $title = get_string('log_section', 'availability_proctor',
            get_string('pluginname', 'availability_proctor'));
        $url = new \moodle_url('/availability/condition/proctor/index.php');
        $icon = new \pix_icon('i/log', '');
        $node = navigation_node::create($title, $url, navigation_node::TYPE_SETTING, null, null, $icon);

        $parentnode->add_node($node);
    }
}
