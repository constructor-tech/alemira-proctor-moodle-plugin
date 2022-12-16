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

use availability_examus2\state;
use availability_examus2\client;
use availability_examus2\common;
use availability_examus2\condition;

/**
 * When attempt is started, update entry accordingly
 *
 * @param stdClass $event Event
 */
function avalibility_examus2_attempt_started_handler($event) {
    global $DB, $SESSION, $PAGE, $USER;

    $accesscode = isset($SESSION->accesscode) ? $SESSION->accesscode : null;

    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);

    $course = get_course($event->courseid);
    $modinfo = get_fast_modinfo($course->id, $USER->id);
    $cmid = $event->get_context()->instanceid;
    $cm = $modinfo->get_cm($cmid);

    $condition = condition::get_examus_condition($cm);
    if (!$condition || !$attempt) {
        return;
    }

    // We want to let previews to happen without proctoring.
    $quizobj = \quiz::create($cm->instance, $USER->id);
    if ($quizobj->is_preview_user()) {
        return;
    }

    $inhibitredirect = false;
    if ($accesscode) {
        // If we have an access code here, we are coming from Examus.
        $inhibitredirect = true;
        $entry = $DB->get_record('availability_examus2_entries', ['accesscode' => $accesscode]);
    }

    if (!empty($entry)) {
        if (empty($entry->attemptid)) {
            $entry->attemptid = $attempt->id;
            $entry->timemodified = time();
        }
        if (in_array($entry->status, ['new', 'scheduled' , 'started'])) {
            $entry->status = "started";
            $entry->timemodified = time();
        }
        $DB->update_record('availability_examus2_entries', $entry);

        if ($entry->status == "started" && $entry->attemptid != $attempt->id) {
            $entry = common::create_entry($condition, $USER->id, $cm);

            if ($accesscode) {
                // The user is coming from examus, we can't redirect.
                // We have to let user know that they need to restart manually.
                $inhibitredirect = true;
                $SESSION->availability_examus2_reset = true;
            } else {
                // The user is not coming from examus.
                $inhibitredirect = false;
            }
        }
    } else {
        $entry = common::create_entry($condition, $USER->id, $cm);
        $entry->attemptid = $attempt->id;
        $entry->status = "started";
        $entry->timemodified = time();
        $DB->update_record('availability_examus2_entries', $entry);

        if ($accesscode) {
            $inhibitredirect = true;
            $SESSION->availability_examus2_reset = true;
        } else {
            $inhibitredirect = false;
        }
    }

    if ($inhibitredirect) {
        return;
    }
}

/**
 * Finish attempt on attempt finish event.
 *
 * @param stdClass $event Event
 */
function avalibility_examus2_attempt_submitted_handler($event) {
    global $DB, $SESSION;
    $cmid = $event->get_context()->instanceid;
    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);

    $cm = get_coursemodule_from_id('quiz', $cmid);

    $userid = $event->userid;

    if (!empty($SESSION->accesscode)) {
        $accesscode = $SESSION->accesscode;
        unset($SESSION->accesscode);
    }

    $entries = $DB->get_records('availability_examus2_entries', [
        'userid' => $userid,
        'courseid' => $event->courseid,
        'cmid' => $cmid,
        'status' => "started"
    ], '-id');

    if (isset($accesscode)) {
        $entry = $DB->get_record('availability_examus2_entries', ['accesscode' => $accesscode]);

        $entries[] = $entry;
    } else {
        return;
    }

    // We want to let previews to happen without proctoring.
    $quizobj = \quiz::create($cm->instance, $userid);
    if ($quizobj->is_preview_user()) {
        return;
    }

    foreach ($entries as $entry) {
        $entry->status = "finished";
        if (empty($entry->attemptid)) {
            $entry->attemptid = $attempt->id;
        }
        $DB->update_record('availability_examus2_entries', $entry);
    }
    $entry = reset($entries);

    core_shutdown_manager::register_function(function() use ($entry) {
        $headers = headers_list();
        header_remove('location');

        $location = null;
        foreach ($headers as $header) {
            preg_match('/^location\s*:\s*(.*)$/is', $header, $matches);
            if (!empty($matches[1])) {
                $location = $matches[1];
            }
        }

        if ($location) {
            ob_end_clean();

            $client = new client(null);
            $newlocation = $client->get_finish_url($entry->accesscode, $location);

            header('Location: ' . $newlocation);
            $formdata = ['action' => $newlocation, 'method' => 'GET'];
            $pagetitle = "Redirecting to Examus";
            include(dirname(__FILE__).'/templates/redirect.php');
        }
    });
}

/**
 * Remove entries on attempt deletion
 *
 * @param stdClass $event Event
 */
function avalibility_examus2_attempt_deleted_handler($event) {
    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
    $cm = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);

    common::reset_entry([
        'cmid' => $cm->id,
        'attemptid' => $attempt->id
    ]);
}

/**
 * User enrolment deleted handles
 *
 * @param \core\event\user_enrolment_deleted $event Event
 */
function avalibility_examus2_user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
    $userid = $event->relateduserid;

    common::delete_empty_entries($userid, $event->courseid);
}

/**
 * Course module deleted handler
 *
 * @param \core\event\course_module_deleted $event Event
 */
function avalibility_examus2_course_module_deleted(\core\event\course_module_deleted $event) {
    global $DB;
    $cmid = $event->contextinstanceid;
    $DB->delete_records('availability_examus2', ['cmid' => $cmid]);
}


/**
 * Attempt viewed
 *
 * @param \mod_quiz\event\attempt_viewed $event Event
 */
function avalibility_examus2_attempt_viewed_handler($event) {
    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
    $quiz = $event->get_record_snapshot('quiz', $attempt->quiz);

    // Storing attempt and CM for future use.
    state::$attempt = [
        'cm_id' => $event->get_context()->instanceid,
        'cm' => $event->get_context(),
        'course_id' => $event->courseid,
        'attempt_id' => $event->objectid,
        'quiz_id' => $quiz->id,
    ];
}
