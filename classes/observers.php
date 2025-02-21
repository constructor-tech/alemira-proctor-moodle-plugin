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

namespace availability_proctor;

use availability_proctor\state;
use availability_proctor\client;
use availability_proctor\common;
use availability_proctor\condition;
use availability_proctor\utils;

class observers {
    /**
     * When attempt is started, update entry accordingly
     *
     * @param stdClass $event Event
     */
    public static function attempt_started($event) {
        global $DB, $SESSION, $PAGE, $USER;

        $accesscode = isset($SESSION->availability_proctor_accesscode) ? $SESSION->availability_proctor_accesscode : null;

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);

        $course = get_course($event->courseid);
        $modinfo = get_fast_modinfo($course->id, $USER->id);
        $cmid = $event->get_context()->instanceid;
        $cm = $modinfo->get_cm($cmid);

        $condition = condition::get_proctor_condition($cm);
        if (!$condition || !$attempt) {
            return;
        }

        // We want to let previews to happen without proctoring.
        $quizobj = utils::quiz_settings_classname()::create($cm->instance, $USER->id);
        if ($quizobj->is_preview_user()) {
            return;
        }

        if (!$condition->user_in_proctored_groups($USER->id)) {
            return;
        }

        $inhibitredirect = false;
        if ($accesscode) {
            // If we have an access code here, we are coming from Proctor by Constructor.
            $inhibitredirect = true;
            $entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);
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
            $DB->update_record('availability_proctor_entries', $entry);

            if ($entry->status == "started" && $entry->attemptid != $attempt->id) {
                $entry = common::create_entry($condition, $USER->id, $cm);

                if ($accesscode) {
                    // The user is coming from proctor, we can't redirect.
                    // We have to let user know that they need to restart manually.
                    $inhibitredirect = true;
                    $SESSION->availability_proctor_reset = true;
                } else {
                    // The user is not coming from proctor.
                    $inhibitredirect = false;
                }
            }
        } else {
            $entry = common::create_entry($condition, $USER->id, $cm);
            $entry->attemptid = $attempt->id;
            $entry->status = "started";
            $entry->timemodified = time();
            $DB->update_record('availability_proctor_entries', $entry);

            if ($accesscode) {
                $inhibitredirect = true;
                $SESSION->availability_proctor_reset = true;
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
    public static function attempt_submitted($event) {
        global $DB, $SESSION;
        $cmid = $event->get_context()->instanceid;
        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        $userid = $event->userid;

        $course = get_course($event->courseid);
        $modinfo = get_fast_modinfo($course->id, $userid);
        $cm = $modinfo->get_cm($cmid);

        if (!empty($SESSION->availability_proctor_accesscode)) {
            $accesscode = $SESSION->availability_proctor_accesscode;
            unset($SESSION->availability_proctor_accesscode);
        }

        $entries = $DB->get_records('availability_proctor_entries', [
            'userid' => $userid,
            'courseid' => $event->courseid,
            'cmid' => $cmid,
            'status' => "started",
        ], '-id');

        if (!empty($accesscode)) {
            $entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);
            if ($entry) {
                $entries[] = $entry;
            }
        } else {
            return;
        }

        // We want to let previews to happen without proctoring.
        $quizobj = utils::quiz_settings_classname()::create($cm->instance, $userid);
        if ($quizobj->is_preview_user()) {
            return;
        }

        $condition = condition::get_proctor_condition($cm);
        if (!$condition || !$condition->user_in_proctored_groups($userid)) {
            return;
        }

        foreach ($entries as $entry) {
            $entry->status = "finished";
            if (empty($entry->attemptid)) {
                $entry->attemptid = $attempt->id;
            }
            $DB->update_record('availability_proctor_entries', $entry);
        }
        $entry = reset($entries);

        $redirecturl = new \moodle_url('/mod/quiz/review.php', ['attempt' => $attempt->id, 'cmid' => $cmid]);

        $client = new client();
        $client->finish_session($entry->accesscode, $redirecturl->out(false));
    }

    /**
     * Remove entries on attempt deletion
     *
     * @param stdClass $event Event
     */
    public static function attempt_deleted($event) {
        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        $cm = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);

        common::reset_entry([
            'cmid' => $cm->id,
            'attemptid' => $attempt->id,
        ]);
    }

    /**
     * Attempt viewed
     *
     * @param \mod_quiz\event\attempt_viewed $event Event
     */
    public static function attempt_viewed($event) {
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

    /**
     * User enrolment deleted handles
     *
     * @param \core\event\user_enrolment_deleted $event Event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        $userid = $event->relateduserid;

        common::delete_empty_entries($userid, $event->courseid);
    }

    /**
     * Course module deleted handler
     *
     * @param \core\event\course_module_deleted $event Event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;
        $cmid = $event->contextinstanceid;
        $DB->delete_records('availability_proctor_entries', ['cmid' => $cmid]);
    }
}
