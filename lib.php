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

use availability_proctor\state;
use availability_proctor\utils;
use availability_proctor\hooks;
use availability_proctor\session_cache;
use availability_proctor\condition;
use availability_proctor\config;
use availability_proctor\session;

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

    $guardedscripts = [
        '/mod/quiz/attempt.php' => 'attempt',
        '/mod/quiz/summary.php' => 'summary',
        '/mod/quiz/processattempt.php' => 'processattempt',
        // Moodle rolls back autosave's database transaction when the guard
        // throws, so a blocked autosave saves nothing and leaves no denial row.
        '/mod/quiz/autosave.ajax.php' => 'autosave',
    ];
    if (isset($guardedscripts[$scriptname])) {
        availability_proctor_guard_sdk_quiz_request($guardedscripts[$scriptname]);
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
 * Guard SDK quiz pages with the session created by the launch page.
 *
 * Webapp sites return before any request or database inspection. Moodle already
 * validates the attempt itself; this adds only the proctoring-specific checks
 * that core cannot know about.
 *
 * @param string $script attempt, summary, processattempt or autosave.
 * @return void
 */
function availability_proctor_guard_sdk_quiz_request($script) {
    global $DB, $USER;

    if (!config::is_sdk_model()) {
        return;
    }

    $attemptid = optional_param('attempt', 0, PARAM_INT);
    if (empty($attemptid)) {
        return;
    }

    $attempt = $DB->get_record(
        'quiz_attempts',
        ['id' => $attemptid],
        'id, quiz, userid, preview'
    );
    if (!$attempt || (int) $attempt->userid !== (int) $USER->id || !empty($attempt->preview)) {
        // Invalid ownership and preview access remain core's responsibility.
        return;
    }

    $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }

    $modinfo = get_fast_modinfo($cm->course, $USER->id);
    $cminfo = $modinfo->get_cm($cm->id);
    $proctorcondition = condition::get_proctor_condition($cminfo);
    if (!$proctorcondition || !$proctorcondition->user_in_proctored_groups($USER->id)) {
        return;
    }

    if (has_capability('availability/proctor:exemptproctoring', \context_module::instance($cm->id), $USER)) {
        return;
    }

    $proctorsession = session::get_by_attempt($attemptid);

    // attempt_started normally performs this binding. Recover here if that
    // synchronous event was interrupted, so the guard cannot strand a learner
    // who has a valid active session.
    if ($proctorsession === null) {
        $open = session::get_open_for_quiz((int) $attempt->quiz, (int) $USER->id);
        if ($open && empty($open->attemptid) && $open->state === session::STATE_ACTIVE) {
            if (session::is_bind_expired($open)) {
                session::transition($open, session::STATE_ABANDONED, 'bind_timeout');
            } else {
                session::bind_attempt($open, $attemptid);
                if ((int) $open->attemptid === $attemptid) {
                    $proctorsession = $open;
                }
            }
        }
    }

    $continueurl = new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);

    if ($proctorsession === null) {
        session::record_denial('no_session', $script, $attemptid, (int) $attempt->quiz);
        throw new \moodle_exception('error_nosession', 'availability_proctor', $continueurl);
    }

    if (
        (int) $proctorsession->userid !== (int) $USER->id
            || (int) $proctorsession->quizid !== (int) $attempt->quiz
    ) {
        session::record_denial('not_owner', $script, $attemptid, (int) $attempt->quiz, $proctorsession);
        throw new \moodle_exception('error_notyoursession', 'availability_proctor', $continueurl);
    }

    if (!session::allows_access($proctorsession)) {
        $externallyended = !empty($proctorsession->sessionend) || !empty($proctorsession->timeresult);
        $reason = $externallyended
            ? 'external_session_ended'
            : ($proctorsession->state === session::STATE_FINISHED
                ? 'state_finished' : 'state_' . $proctorsession->state);
        session::record_denial($reason, $script, $attemptid, (int) $attempt->quiz, $proctorsession);
        $errorcode = ($externallyended || $proctorsession->state === session::STATE_ABANDONED)
            ? 'error_sessionended_newentry'
            : ($proctorsession->state === session::STATE_FINISHED
                ? 'error_sessionfinished' : 'error_nosession');
        throw new \moodle_exception($errorcode, 'availability_proctor', $continueurl);
    }

    // processattempt.php and autosave.ajax.php are POST targets rather than
    // rendered pages. For the two rendered routes, an explicit top-level
    // navigation is normally a direct-tab attempt. Constructor Secure Browser is
    // the one valid top-level route: sdk_entry.php marked this separate Moodle
    // browser session only after consuming a learner- and SDK-session-bound
    // private key.
    $isnativebrowser = session_cache::is_sdk_secure_browser_session((int) $proctorsession->id);
    if ($script !== 'processattempt' && session::is_top_level_request() && !$isnativebrowser) {
        session::record_denial('sec_fetch_dest', $script, $attemptid, (int) $attempt->quiz, $proctorsession);
        throw new \moodle_exception('error_nosession', 'availability_proctor', $continueurl);
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
        $title = get_string(
            'log_section',
            'availability_proctor',
            get_string('pluginname', 'availability_proctor')
        );
        $url = new \moodle_url('/availability/condition/proctor/index.php');
        $icon = new \pix_icon('i/log', '');
        $node = navigation_node::create($title, $url, navigation_node::TYPE_SETTING, null, null, $icon);

        $parentnode->add_node($node);
    }
}
