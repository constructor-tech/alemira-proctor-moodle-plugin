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
 * Records the SDK session events that only the browser can observe.
 *
 * The SDK exposes its lifecycle as four promises in the page and offers no
 * server-side callback, so examFinished, videoUploaded and appUnmounted can only
 * reach Moodle by being reported from JavaScript. This endpoint is that channel,
 * and the session table is the only forensic record of what the SDK did.
 *
 * The finished state is still never set from here directly. examFinished submits
 * the attempt, and classes/observers.php sets the state server-side when core
 * fires attempt_submitted -- so the terminal state remains driven by the
 * attempt, which is what survives a browser dying mid-exam. What this endpoint
 * reports is only ever what the SDK did.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use availability_proctor\session;
use availability_proctor\utils;

$sessionid = required_param('sessionid', PARAM_INT);
$event = required_param('event', PARAM_ALPHA);

require_login(null, false);
require_sesskey();

// The reply. Only the state is always present; videouploaded adds the URL to
// send the student back to.
$response = [];

$allowed = ['examstarted', 'examfinished', 'videouploaded', 'appunmounted'];
if (!in_array($event, $allowed, true)) {
    throw new moodle_exception('error_unknownevent', 'availability_proctor');
}

// Keyed on the session, not an attempt: these events can arrive before the
// student has reached one, which is exactly what appUnmounted during the
// preparation steps looks like.
$proctorsession = $DB->get_record(session::TABLE, ['id' => $sessionid]);
if (!$proctorsession) {
    throw new moodle_exception('error_nosession', 'availability_proctor');
}

if ((int) $proctorsession->userid !== (int) $USER->id) {
    throw new moodle_exception('error_notyoursession', 'availability_proctor');
}

switch ($event) {
    case 'examstarted':
        // Nothing to transition: token.php already made the session active, and
        // the student cannot reach the questions before it did. Recorded only so
        // that the reason column shows how far the session actually got, which
        // distinguishes "never started the exam" from "started and abandoned".
        //
        // The return value is ignored on purpose. If this arrives out of order,
        // after examFinished or appUnmounted, the transition is refused and the
        // session keeps its later state -- which is the safe outcome, since a
        // report must never walk a session backwards into active.
        session::transition($proctorsession, session::STATE_ACTIVE, 'exam_started');
        break;

    case 'examfinished':
        // Recording has stopped, so the attempt is submitted here rather than
        // left for the student to submit themselves.
        //
        // Leaving it open was the original design, on the reasoning that blocking
        // a student who pressed Finish exam before submitting would strand them.
        // Measured on 2026-08-13, that reasoning inverted: the launch page treats
        // a finishing session as reusable but token.php cannot re-activate it, so
        // the student was stranded anyway -- an attempt showing "In progress" that
        // could be neither continued nor submitted. Submitting closes the attempt
        // through the same path as the Submit all and finish button, which fires
        // attempt_submitted, which drives the session to finished and tells
        // Constructor the exam is over. So finishing stops being a state a
        // student can be stuck in.
        //
        // Answers are safe: Moodle stores them on each page move and through
        // autosave, so what is submitted is what the student had entered. Anything
        // unanswered is submitted unanswered, exactly as pressing Finish exam
        // implies.
        session::transition($proctorsession, session::STATE_FINISHING, 'exam_finished');

        if (!empty($proctorsession->attemptid)) {
            // Resolved at runtime: the class moved into the mod_quiz namespace in
            // 4.2 and this plugin still supports older releases.
            $attemptclass = utils::quiz_attempt_classname();
            $attemptobj = $attemptclass::create((int) $proctorsession->attemptid);

            // Only an attempt still being sat can be finished. A student who
            // submitted first and then pressed Finish exam reaches here with the
            // attempt already closed, and process_finish() would throw.
            if ($attemptobj->get_attempt()->state === $attemptclass::IN_PROGRESS) {
                $attemptobj->process_finish(time(), false);

                // Re-read: the observer fired by process_finish() moved the row on
                // to finished, and the response below reports the session state.
                $proctorsession = $DB->get_record(session::TABLE, ['id' => $sessionid])
                    ?: $proctorsession;
            }
        }
        break;

    case 'videouploaded':
        // Not a state transition. This can arrive after the attempt was submitted
        // and the row is already finished, so treating it as one would mean
        // rejecting a normal event.
        session::record_video_uploaded($proctorsession);

        // The recording is safely on the server, so the student can now leave the
        // launch page. Where to send them is decided here rather than in the
        // browser because it needs the attempt id, which the launch page never
        // had: the token is signed before core creates the attempt.
        //
        // Deliberately not Constructor's own sessionFinishUrl, which the finish
        // API accepts and the reference plugin uses. That navigates as soon as the
        // exam ends, which in this plugin's design would take the launch page --
        // and any upload still running on it -- down with it. Waiting for this
        // event and redirecting ourselves reaches the same place with nothing
        // left in flight. See classes/client.php.
        $returnurl = session::return_url($proctorsession);
        if ($returnurl !== null) {
            $response['returnurl'] = $returnurl;
        }
        break;

    case 'appunmounted':
        // The SDK closed before the normal end of the flow, and it does not tell
        // us whether that was deliberate or a crash. Access stays allowed for the
        // same reason as examfinished.
        session::transition($proctorsession, session::STATE_ABANDONED, 'app_unmounted');
        break;
}

$response['state'] = $proctorsession->state;

echo json_encode($response);
