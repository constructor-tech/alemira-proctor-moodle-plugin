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
use availability_proctor\config;
use availability_proctor\session;
use availability_proctor\session_cache;
use availability_proctor\utils;

/**
 * Event observer callbacks.
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observers {
    /**
     * When attempt is started, update entry accordingly
     *
     * @param stdClass $event Event
     */
    public static function attempt_started($event) {
        global $DB, $PAGE, $USER;

        $accesscode = session_cache::get_accesscode();

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

        // Under the SDK model this is where the session binds to the attempt. The
        // session was created before the attempt existed -- the launch page hands
        // off to core's startattempt.php, which runs the pre-flight chain and
        // creates the attempt itself -- so this event is the first moment that
        // knows both halves.
        //
        // The three gates above are deliberately the same three, in the same order,
        // as handle_start_attempt(): the two methods are halves of one flow and must
        // agree on who is proctored. Gate 2 carries extra weight here because
        // db/events.php wires attempt_preview_started to this same callback.
        //
        // The return is load-bearing, not tidiness. Everything below is the hosted
        // flow's entries-table bookkeeping: falling through would mint one
        // availability_proctor_entries row per SDK attempt, with status 'started'
        // and no accesscode, polluting the log the hosted flow reads -- and would
        // then fire session_started naming an $entry->id that no SDK session has.
        if (config::is_sdk_model()) {
            self::bind_sdk_session($cm, $attempt);
            return;
        }

        $inhibitredirect = false;
        if ($accesscode) {
            // If we have an access code here, we are coming from Proctor.
            $inhibitredirect = true;
            $entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);
        }

        if (!empty($entry)) {
            if (empty($entry->attemptid)) {
                $entry->attemptid = $attempt->id;
                $entry->timemodified = time();
            }
            if (in_array($entry->status, ['new', 'scheduled', 'started'])) {
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
                    session_cache::set_reset();
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
                session_cache::set_reset();
            } else {
                $inhibitredirect = false;
            }
        }

        if ($inhibitredirect) {
            return;
        }

        \availability_proctor\event\session_started::create([
            'objectid' => $entry->id,
            'context'  => \context_module::instance($cmid),
            'userid'   => $USER->id,
        ])->trigger();
    }

    /**
     * Bind this student's open SDK session to the attempt core has just created.
     *
     * Binding is once-only: the session is a permission that has not yet been
     * spent, and session::bind_attempt() settles two simultaneous requests with a
     * conditional UPDATE rather than a schema constraint the database cannot
     * express portably.
     *
     * In the plugin the SDK flow came from, this ran as a side effect of the quiz
     * access rule's prevent_access() on the first attempt.php request, because that
     * was the only request that knew both the session and the attempt id. That rule
     * is not part of this plugin, and this event is a better place for the work
     * anyway: the attempt id arrives from an event describing an attempt core just
     * created for this user on this quiz, rather than from a URL parameter the
     * student supplied.
     *
     * One capability is lost in the move and is worth being explicit about:
     * prevent_access() ran on every page load, so a transient failure could recover
     * on the next request, whereas this fires exactly once. A session left active
     * but unbound is still recoverable -- the routing branch sends the student back
     * to the launch page, which reuses the same open session -- but it is a real
     * difference from the source and belongs in the release notes.
     *
     * @param \cm_info $cm Course module info for the quiz.
     * @param \stdClass $attempt The quiz attempt record from the event snapshot.
     * @return bool True when this call bound the session.
     */
    protected static function bind_sdk_session($cm, $attempt): bool {
        global $USER;

        $session = session::get_open_for_quiz((int) $cm->instance, (int) $USER->id);

        if ($session === null || !empty($session->attemptid)) {
            // Either no session at all, or this student's open session is already
            // bound to a different attempt -- so this attempt has none of its own.
            return false;
        }

        // Only a session that actually started proctoring may be spent. A pending
        // row means the SDK never ran, so there is no recording to attach to.
        if ($session->state !== session::STATE_ACTIVE) {
            return false;
        }

        // An unspent permission does not last forever. Past the window the row is
        // closed, so the student's next launch creates a fresh session instead of
        // reusing a stale one -- which is why the expiry check belongs here, where
        // the refusal can be acted on, and not in the routing branch, where it
        // would fire on the iframe handoff and close the session underneath the
        // one request that must keep it.
        if (session::is_bind_expired($session)) {
            session::transition($session, session::STATE_ABANDONED, 'bind_timeout');
            return false;
        }

        // Redundant here and kept deliberately. Core created this attempt for this
        // user on this quiz, so it cannot be another student's -- but the check
        // costs three lines, and the method would otherwise silently depend on a
        // guarantee its signature does not state.
        if (
            (int) $attempt->userid !== (int) $USER->id
                || (int) $attempt->quiz !== (int) $cm->instance
        ) {
            return false;
        }

        // False means a simultaneous request bound this session to another attempt
        // first. bind_attempt() has already re-read the row, so nothing further is
        // needed here beyond not treating it as bound.
        return session::bind_attempt($session, (int) $attempt->id);
    }

    /**
     * Move an SDK session to finished, because its attempt is over, and stop the
     * recording.
     *
     * Marking the session finished in Moodle only closes this plugin's own gate.
     * The recording keeps running until the Constructor server is told, and the SDK
     * gives the page no way to say so -- so without the second half the student has
     * to notice the SDK sidebar's own Finish exam button, and everything between
     * submitting and pressing it is recorded for no reason.
     *
     * @param \core\event\base $event The attempt event: submitted or abandoned.
     * @return bool True when this attempt had an SDK session and it was handled.
     */
    protected static function finish_sdk_session($event): bool {
        $attemptid = (int) $event->objectid;

        // Read before marking. mark_submitted() moves the row to FINISHED, which
        // would hide whether it had already passed through FINISHING -- and that
        // distinction decides whether the finish API is called at all, below.
        $session = session::get_by_attempt($attemptid);
        if ($session === null) {
            return false;
        }

        // An abandoned attempt reaches the same terminal state as a submitted one
        // but for a different reason, and only the event class distinguishes them.
        // Recording which fired keeps that visible to a reviewer: an abandoned
        // attempt means the student never submitted.
        $reason = $event instanceof \mod_quiz\event\attempt_abandoned
            ? 'attempt_abandoned'
            : null;

        session::mark_submitted($attemptid, $reason);

        if (!empty($session->timefinishsent)) {
            return true;
        }

        // A session that already reached FINISHING was closed by the SDK itself,
        // through its own Finish exam button -- timefinishing is set only from
        // event.php's examFinished branch. Constructor has already recorded that
        // session as completed by the user, and calling the finish API now would
        // overwrite that with "completed by remote system", misreporting who ended
        // the exam. Since examFinished submits the attempt, this is the normal path
        // whenever the student uses the SDK's button, not an edge case.
        if (!empty($session->timefinishing)) {
            return true;
        }

        // Attempted inline first, so the recording stops while the student is still
        // looking at the page. A failure is handed to an ad-hoc task rather than
        // retried here, because the response to their submission must not wait on a
        // server that is struggling.
        if (!session::request_finish_by_id((int) $session->id)) {
            task\finish_session::queue((int) $session->id);
        }

        return true;
    }

    /**
     * Finish attempt on attempt finish event.
     *
     * @param stdClass $event Event
     */
    public static function attempt_submitted($event) {
        global $DB;
        $cmid = $event->get_context()->instanceid;
        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        $userid = $event->userid;

        // The SDK path runs first, and its position is load-bearing. This method
        // returns early further down when there is no accesscode, which is
        // hosted-only logic -- an SDK session never has one. Handling it after that
        // point would mean no SDK session ever reached FINISHED: Constructor would
        // never be told to stop recording, and the student's camera would keep
        // running after they submitted.
        //
        // Guarded on the session rather than on config::is_sdk_model(), so that a
        // session started before an administrator switched the site's model still
        // finishes under the model it began with. Also cheap: on any unproctored
        // quiz this is one indexed lookup that finds nothing.
        if (self::finish_sdk_session($event)) {
            return;
        }

        $course = get_course($event->courseid);
        $modinfo = get_fast_modinfo($course->id, $userid);
        $cm = $modinfo->get_cm($cmid);

        $accesscode = session_cache::get_accesscode();
        if (!empty($accesscode)) {
            session_cache::clear_accesscode();
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

        \availability_proctor\event\session_finished::create([
            'objectid' => $entry->id,
            'context'  => \context_module::instance($cmid),
            'userid'   => $userid,
        ])->trigger();
    }

    /**
     * Close the SDK session for an attempt core has abandoned.
     *
     * Core abandons an attempt when it is overdue and the quiz's overdue handling
     * says so, which cron reaches without the student present. That is as terminal
     * as a submission -- the student will never return to it -- so the session must
     * not be left open.
     *
     * Only the SDK side is done here. The hosted flow has no abandonment handling
     * today and this does not add one: doing so would change the behaviour of exams
     * customers are running now, and it is not what this work is for. It is also why
     * this is a separate callback rather than another entry point into
     * attempt_submitted -- see the note in db/events.php about $event->userid being
     * the cron user on this event.
     *
     * @param \mod_quiz\event\attempt_abandoned $event Event
     * @return void
     */
    public static function attempt_abandoned($event) {
        self::finish_sdk_session($event);
    }

    /**
     * Remove entries on attempt deletion
     *
     * @param stdClass $event Event
     */
    public static function attempt_deleted($event) {
        global $DB;

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        $cm = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);

        common::reset_entry([
            'cmid' => $cm->id,
            'attemptid' => $attempt->id,
        ]);

        // NTG-185: the SDK model's own rows, which core knows nothing about. Without
        // this a session row and its denial rows outlive the attempt they describe --
        // personal data naming a student, pointing at an attempt id that no longer
        // exists, kept indefinitely and still reachable in the session log.
        //
        // Deleted rather than kept for audit, deliberately: a teacher deleting an
        // attempt is telling the site that record should go, the privacy provider
        // already treats both tables as the student's data, and a session whose
        // attempt is gone cannot be reviewed for anything -- its attempt link would
        // 404.
        //
        // Not guarded by config::is_sdk_model(). On a hosted site both tables are
        // empty, so these are no-ops; guarding them would instead mean that a site
        // which ever switched models kept its pre-switch rows forever. That is the
        // opposite of the routing and binding branches, where running SDK code on a
        // hosted site would change what a student sees.
        $DB->delete_records(session::TABLE, ['attemptid' => (int) $attempt->id]);
        $DB->delete_records(session::TABLE_DENIED, ['attemptid' => (int) $attempt->id]);
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

        // NTG-185: the same clean-up one level up, for the SDK model's tables.
        //
        // The entries delete above stays exactly as it is, unguarded by module type:
        // it keys on cmid, which is unique site-wide, so it cannot touch another
        // module's rows. The SDK tables key on quizid, which is only unique within
        // the quiz module -- so here the module type has to be checked, or an
        // assignment with the same instance id would take a quiz's sessions with it.
        if (($event->other['modulename'] ?? '') !== 'quiz') {
            return;
        }

        $quizid = (int) ($event->other['instanceid'] ?? 0);
        if (empty($quizid)) {
            return;
        }

        $DB->delete_records(session::TABLE, ['quizid' => $quizid]);
        $DB->delete_records(session::TABLE_DENIED, ['quizid' => $quizid]);
    }

    /**
     * Erase rows left behind when a whole course was deleted.
     *
     * Deleting a course does not go through course_delete_module(), so
     * course_module_deleted() never fires for it. remove_course_contents() calls
     * quiz_delete_instance() directly and triggers no per-module event at all --
     * which is also why attempt_deleted() does not fire there either.
     *
     * So this cleans up by the only thing still true afterwards: the quiz the rows
     * name no longer exists. Nothing else identifies them, because
     * course_content_deleted fires *after* the {quiz} rows are gone, so the quiz ids
     * cannot be looked up from the course by then.
     *
     * Keying on the absence of the quiz rather than on the deletion of a course is
     * deliberate. It is idempotent, it does not depend on which core path did the
     * deleting, and it therefore also covers any future route that removes a quiz
     * without firing an event -- which is the failure this whole clean-up exists for.
     *
     * @param \core\event\course_content_deleted $event The course content event.
     * @return void
     */
    public static function course_content_deleted(\core\event\course_content_deleted $event) {
        self::purge_orphans();
    }

    /**
     * Delete session and denial rows whose quiz no longer exists.
     *
     * Shared by the course_content_deleted observer and the nightly purge_orphans
     * task, so a deletion path that fires no event costs a delay rather than an
     * orphan.
     *
     * Two statements -- select the ids, then delete by id -- rather than one DELETE
     * carrying its own subquery. The single-statement form was measured working on
     * MySQL, but a DELETE whose WHERE clause reads the table being deleted from is
     * exactly the shape MySQL rejects in other arrangements, and this runs on
     * PostgreSQL too. Selecting first is unambiguous on both, and the id list is
     * small: it only ever holds rows whose quiz has already been deleted.
     *
     * @return int How many rows were removed, for the task to report.
     */
    public static function purge_orphans(): int {
        global $DB;

        $removed = 0;

        foreach ([session::TABLE, session::TABLE_DENIED] as $table) {
            // The denial table's quizid is nullable -- a refusal can be recorded with
            // no quiz identified -- and a NULL quizid is not an orphan, it is a row
            // that never named a quiz. Excluding it keeps those records instead of
            // deleting them on the first sweep.
            $ids = $DB->get_fieldset_sql(
                'SELECT t.id FROM {' . $table . '} t
                  WHERE t.quizid IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM {quiz} q WHERE q.id = t.quizid)'
            );

            if (!$ids) {
                continue;
            }

            $DB->delete_records_list($table, 'id', $ids);
            $removed += count($ids);
        }

        return $removed;
    }
}
