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
 * Availability plugin for integration with Examus proctoring system.
 *
 * @package    availability_examus2
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use availability_examus2\condition;
use availability_examus2\state;
use availability_examus2\common;

/**
 * Hooks into head rendering. Adds proctoring fader/shade and accompanying javascript
 * This is used to prevent users from seeing questions before it is known that
 * attempt is viewed thorough Examus WebApp
 *
 * @return string
 */
function availability_examus2_before_standard_html_head() {
    global $DB, $USER;

    $context = context_system::instance();

    if (has_capability('availability/examus2:logaccess', $context)) {
        $title = get_string('log_section', 'availability_examus2');
        $url = new \moodle_url('/availability/condition/examus2/index.php');
        $icon = new \pix_icon('i/log', '');
        $node = navigation_node::create($title, $url, navigation_node::TYPE_CUSTOM, null, null, $icon);

    }

    // If there is no active attempt, do nothing
    if (isset(state::$attempt['attempt_id'])) {
        $attemptid = state::$attempt['attempt_id'];
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt || $attempt->state != \quiz_attempt::IN_PROGRESS) {
            return '';
        } else {
            return availability_examus2_handle_proctoring_fader($attempt);
        }
    } else {
        return '';
    }
}

function availability_examus2_after_config() {
    $accesscode = optional_param('accesscode', null, PARAM_RAW);

    if (!empty($accesscode)) {
        availability_examus2_handle_accesscode_param($accesscode);
    }
}

/**
 * This hook is used for exams that require scheduling.
 **/
function availability_examus2_after_require_login() {
    global $USER, $cm, $course;

    // User is trying to start an attempt, redirect to examus if it is not started.
    $scriptname = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null;
    if ($scriptname == '/mod/quiz/startattempt.php') {
        availability_examus2_handle_start_attempt($course, $cm, $USER);
    }
}

function availability_examus2_handle_proctoring_fader($attempt) {
    global $DB, $USER, $PAGE, $SESSION;

    $cmid = state::$attempt['cm_id'];
    $courseid = state::$attempt['course_id'];

    $modinfo = get_fast_modinfo($courseid);
    $cm = $modinfo->get_cm($cmid);
    $course = $cm->get_course();

    $condition = condition::get_examus_condition($cm);

    if (!$condition) {
        return '';
    }

    if ($condition->noprotection) {
        return '';
    }

    // We want to let previews to happen without proctoring.
    $quizobj = \quiz::create($cm->instance, $USER->id);
    if ($quizobj->is_preview_user()) {
        return '';
    }

    $entry = common::create_entry($condition, $USER->id, $cm);

    if (
        !empty($SESSION->availability_examus2_accesscode) &&
            $entry->accesscode != $SESSION->availability_examus2_accesscode
    ) {
        $SESSION->availability_examus2_accesscode = null;
        $SESSION->availibility_examus2_reset = true;
    }

    $timebracket = common::get_timebracket_for_cm('quiz', $cm);
    $lang = current_language();

    $client = new \availability_examus2\client($condition);
    $data = $client->exam_data($course, $cm);
    $userdata = $client->user_data($USER, $lang);
    $biometrydata = $client->biometry_data($USER);

    $timedata = $client->time_data($timebracket);
    $pageurl = $PAGE->url;
    $pageurl->param('accesscode', $entry->accesscode);
    $attemptdata = $client->attempt_data($entry->accesscode, $pageurl->out(false));

    $data = array_merge($data, $userdata, $timedata, $attemptdata, $biometrydata);

    if ($condition->schedulingrequired && empty($entry->timescheduled)) {
        $data['schedule'] = true;
    }

    $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);
    $attemptinprogess = $attempt && $attempt->state == \quiz_attempt::IN_PROGRESS;

    if ($entryisactive || $attemptinprogess) {
        // We have to pass formdata in any case because exam can be opened outside iframe.
        $formdata = $client->get_form('start', $data);
        $entryreset = isset($SESSION->availibility_examus2_reset) && $SESSION->availibility_examus2_reset;

        // Our entry is active, we are showing user a fader.
        ob_start();
        include(dirname(__FILE__).'/templates/proctoring_fader.php');
        $output = ob_get_clean();
        return $output;
    }
}

function availability_examus2_handle_accesscode_param($accesscode) {
    global $SESSION, $DB;

    // User is coming from examus, reset is done if it was requested before.
    unset($SESSION->availibility_examus2_reset);

    $SESSION->availability_examus2_accesscode = $accesscode;

    // We know accesscode is passed in params.
    $entry = $DB->get_record('availability_examus2_entries', [
        'accesscode' => $accesscode,
    ]);

    // If entry exists, we need to check if we have a newer one.
    if ($entry) {
        $modinfo = get_fast_modinfo($entry->courseid);
        $cminfo = $modinfo->get_cm($entry->cmid);

        $condition = condition::get_examus_condition($cminfo);
        if (!$condition) {
            return;
        }

        $newentry = common::most_recent_entry($entry);
        if ($newentry && $newentry->id != $entry->id) {
            $entry = $newentry;
            $SESSION->availibility_examus2_reset = true;
        }

        $modinfo = get_fast_modinfo($entry->courseid);
        $cminfo = $modinfo->get_cm($entry->cmid);

        // The entry is already finished or canceled, we need to reset it
        if (!in_array($entry->status, ['new', 'scheduled', 'started'])) {
            $entry = common::create_entry($condition, $entry->userid, $cminfo);
            $SESSION->availibility_examus2_reset = true;
        }
    } else {
        // If entry does not exist, we need to create a new one and redirect
        $SESSION->availibility_examus2_reset = true;
    }

}

function availability_examus2_handle_start_attempt($course, $cm, $user){
    global $SESSION, $DB;
    $modinfo = get_fast_modinfo($course->id);
    $cminfo = $modinfo->get_cm($cm->id);

    $condition = condition::get_examus_condition($cminfo);
    if (!$condition) {
        return;
    }

    // We want to let previews to happen without proctoring.
    $quizobj = \quiz::create($cminfo->instance, $user->id);
    if ($quizobj->is_preview_user()) {
        return;
    }

    $accesscode = isset($SESSION->availibility_examus2_accesscode) ? $SESSION->availibility_examus2_accesscode : null;
    $entry = null;
    $reset = false;
    if ($accesscode) {
        $entry = $DB->get_record('availability_examus2_entries', [
            'accesscode' => $accesscode,
        ]);

        // Entry is old
        if ($entry && !in_array($entry->status, ['new', 'scheduled', 'started'])) {
            $reset = true;
        }

        // Entry belongs to other cm.
        if ($entry && $entry->cmid != $cminfo->id) {
            $reset = true;
        }

        if (!$entry) {
            $reset = true;
        }

        if ($reset) {
            unset($SESSION->availability_examus2_accesscode);
            $SESSION->availibility_examus2_reset = true;
        }

        // We don't want to redirect at this stage.
        // Because its possible that the user is working through Web-app.
        return;
    } else {
        $entry = common::create_entry($condition, $user->id, $cminfo);
    }

    // The attempt is already started, letting it open.
    if ($entry->status == 'started') {
        return;
    }

    $timebracket = common::get_timebracket_for_cm('quiz', $cminfo);

    $location = new \moodle_url('/mod/quiz/view.php', [
        'id' => $cminfo->id,
        'accesscode' => $entry->accesscode,
    ]);

    $lang = current_language();

    $client = new \availability_examus2\client($condition);
    $data = $client->exam_data($course, $cminfo);
    $userdata = $client->user_data($user, $lang);
    $biometrydata = $client->biometry_data($user);
    $timedata = $client->time_data($timebracket);
    $attemptdata = $client->attempt_data($entry->accesscode, $location->out(false));

    $data = array_merge($data, $userdata, $timedata, $attemptdata, $biometrydata);

    if ($condition->schedulingrequired) {
        $data['schedule'] = true;
    }

    $formdata = $client->get_form('start', $data);

    $pagetitle = "Redirecting to Examus";

    include(dirname(__FILE__).'/templates/redirect.php');
    die();
}
