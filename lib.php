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
 * @copyright  based on work by 2017 Max Pomazuev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use availability_examus2\condition;
use availability_examus2\state;

/**
 * Hooks into head rendering. Adds proctoring fader/shade and accompanying javascript
 * This is used to prevent users from seeing questions before it is known that
 * attempt is viewed thorough Examus WebApp
 *
 * @return string
 */
function availability_examus2_before_standard_html_head() {
    global $DB, $USER, $PAGE;

    $context = context_system::instance();

    if (has_capability('availability/examus2:logaccess', $context)) {
        $title = get_string('log_section', 'availability_examus2');
        $url = new \moodle_url('/availability/condition/examus2/index.php');
        $icon = new \pix_icon('i/log', '');
        $node = navigation_node::create($title, $url, navigation_node::TYPE_CUSTOM, null, null, $icon);

        // $this->page->primarynav->children->add($node);
        // $PAGE->flatnav->add($node);
    }

    if (isset(state::$attempt['attempt_id'])) {
        $attemptid = state::$attempt['attempt_id'];
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt || $attempt->state != 'inprogress') {
            return '';
        }
    } else {
        return '';
    }

    $cmid = state::$attempt['cm_id'];
    $courseid = state::$attempt['course_id'];

    $modinfo = get_fast_modinfo($courseid);
    $cm = $modinfo->get_cm($cmid);
    $course = $cm->get_course();

    if (!condition::user_in_proctored_groups($cm, $USER->id)) {
        return '';
    }

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
        return;
    }

    // Check that theres more rules, which pass.
    // If we have no examus accesstoken (condition fails),
    // but the module is still avalible, this means we should not
    // enfoce proctoring.
    $availibilityinfo = new \core_availability\info_module($cm);
    $reason = '';
    $isavailiblegeneral = $availibilityinfo->is_available($reason, false, $USER->id);
    if (!$isavailiblegeneral) {
        return '';
    }
    $entry = $condition->create_entry_for_cm($USER->id, $cm);

    $timebracket = \availability_examus2\common::get_timebracket_for_cm('quiz', $cm);
    $timebracket = $timebracket ? $timebracket : [];

    if (empty($timebracket['start'])) {
        $timebracket['start'] = time();
    }
    if (empty($timebracket['end'])) {
        $timebracket['end'] = $timebracket['start'] + ($condition->duration * 60);
    }

    $client = new \availability_examus2\client();
    $data = $client->exam_data($condition, $course, $cm);
    $userdata = $client->user_data($USER);
    $timedata = $client->time_data($timebracket);
    $attemptdata = $client->attempt_data($entry->accesscode, $PAGE->url->out(false));

    $data = array_merge($data, $userdata, $timedata, $attemptdata);

    if (in_array($entry->status, ['started', 'scheduled', 'new'])) {
        $formdata = $client->get_form('start', $data);

        // Our entry is active, we are showing user as fader.
        ob_start();
        include(dirname(__FILE__).'/templates/proctoring_fader.php');
        $output = ob_get_clean();
        return $output;
    }
}

function availability_examus2_after_require_login() {
    global $PAGE, $DB, $USER, $cm, $course;
    $scriptname = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null;

    if ($scriptname != '/mod/quiz/startattempt.php') {
        return;
    }

    $modinfo = get_fast_modinfo($course->id);
    $cm = $modinfo->get_cm($cm->id);

    $condition = \availability_examus2\condition::get_examus_condition($cm);
    if (!$condition) {
        return;
    }

    // We want to let previews to happen without proctoring.
    $quizobj = \quiz::create($cm->instance, $USER->id);
    if ($quizobj->is_preview_user()) {
        return;
    }

    // No need to schedule an exam.
    if (!$condition->schedulingrequired) {
        return;
    }

    $entry = $condition->create_entry_for_cm($USER->id, $cm);

    if ($entry->status == 'started' || $entry->status == 'scheduled') {
        return;
    }

    if ($entry->status == 'new') {
        $timebracket = \availability_examus2\common::get_timebracket_for_cm('quiz', $cm);
        $timebracket = $timebracket ? $timebracket : [];

        if (empty($timebracket['start'])) {
            $timebracket['start'] = time();
        }
        if (empty($timebracket['end'])) {
            $timebracket['end'] = $timebracket['start'] + ($condition->duration * 60);
        }

        $location = new \moodle_url('/mod/quiz/index.php', ['id' => $cm->id]);

        $client = new \availability_examus2\client();
        $data = $client->exam_data($condition, $course, $cm);
        $userdata = $client->user_data($USER);
        $timedata = $client->time_data($timebracket);
        $attemptdata = $client->attempt_data($entry->accesscode, $location->out(false));

        $data = array_merge($data, $userdata, $timedata, $attemptdata);
        $data['schedule'] = true;

        $formdata = $client->get_form('start', $data);

        $pagetitle = "Redirecting to Examus";
        include(dirname(__FILE__).'/templates/redirect.php');
        die();
    }
}
