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

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * End-to-end tests for what happens to a proctoring session as a learner sits an exam.
 *
 * session_test.php pins the state machine's rules; this file pins the journeys --
 * which real event drives which transition. They are separate because the rules can
 * be right while nothing calls them, which is the failure that leaves a camera
 * recording after a student has gone.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\observers
 * @covers     \availability_proctor\session
 */
final class lifecycle_test extends \advanced_testcase {
    /** @var \stdClass The quiz. */
    private $quiz;

    /** @var \stdClass The learner. */
    private $user;

    /**
     * A course with a quiz holding one question, and an enrolled learner.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->quiz = $generator->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'grade' => 100.0,
            'sumgrades' => 1,
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);

        $this->user = $generator->create_user();
        $generator->enrol_user($this->user->id, $course->id);

        // Credentials, so the plugin takes its configured path. Without them
        // finishing a session short-circuits with a debugging() call about a
        // misconfigured site, which is a different code path from the one these
        // tests are about.
        set_config('proctor_url', 'https://proctor.invalid', 'availability_proctor');
        set_config('integration_name', 'test-integration', 'availability_proctor');
        set_config('jwt_secret', 'test-secret', 'availability_proctor');
        set_config('account_id', 'test-account-id', 'availability_proctor');
        set_config('account_name', 'test-account', 'availability_proctor');
    }

    /**
     * Finishing a session asks Constructor to stop recording. That host does not
     * exist in a test, so the request fails and the failure is reported through
     * debugging() -- expected here, and separate from the state these tests check,
     * which the plugin writes before the call is attempted.
     */
    private function ignore_unreachable_finish_api(): void {
        $this->assertDebuggingCalledCount(1);
    }

    /**
     * Start an attempt on the quiz for the learner.
     *
     * @return \quiz_attempt
     */
    private function start_attempt() {
        // Resolved through the plugin's own helpers: the classes moved into the
        // mod_quiz namespace in 4.2, and this plugin still supports 3.8.
        $settingsclass = utils::quiz_settings_classname();
        $attemptclass = utils::quiz_attempt_classname();

        $quizobj = $settingsclass::create($this->quiz->id, $this->user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $this->user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        return $attemptclass::create($attempt->id);
    }

    /**
     * Create a proctoring session bound to the attempt, in the given state.
     *
     * @param int $attemptid The attempt.
     * @param string $state Starting state.
     * @return \stdClass The stored row.
     */
    private function make_session(int $attemptid, string $state = session::STATE_ACTIVE): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'k-' . $attemptid . '-' . random_int(1000, 9999),
            'quizid' => (int) $this->quiz->id,
            'userid' => (int) $this->user->id,
            'attemptid' => $attemptid,
            'state' => $state,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeactive' => $state === session::STATE_PENDING ? null : $now,
        ]);
        return $DB->get_record(session::TABLE, ['id' => $id]);
    }

    /**
     * Reload a session row.
     *
     * @param int $id Row id.
     * @return \stdClass
     */
    private function reload(int $id): \stdClass {
        global $DB;
        return $DB->get_record(session::TABLE, ['id' => $id]);
    }

    /**
     * SCENARIO: the learner presses "Submit all and finish".
     *
     * The terminal state is written server-side from core's attempt_submitted, not
     * from the browser, so it holds even if the tab dies at the moment of
     * submission. Nothing else stops Constructor recording.
     */
    public function test_submitting_the_attempt_finishes_the_session(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());

        $attempt->process_finish(time(), false);
        $this->ignore_unreachable_finish_api();

        $after = $this->reload($session->id);
        $this->assertSame(session::STATE_FINISHED, $after->state);
        $this->assertNotEmpty($after->timefinished, 'submission must stamp when the exam ended');
    }

    /**
     * SCENARIO: the learner presses the SDK's "Finish exam" and never submits.
     *
     * examFinished moves the session to FINISHING and then submits the attempt for
     * them, which fires attempt_submitted and drives the session to FINISHED. Before
     * that, a learner who pressed Finish exam first was stranded: the attempt stayed
     * "In progress" and could be neither continued nor submitted.
     */
    public function test_finish_proctoring_submits_the_attempt_and_finishes(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());

        // What event.php's examfinished branch does.
        session::transition($session, session::STATE_FINISHING, 'exam_finished');
        $this->assertSame(session::STATE_FINISHING, $this->reload($session->id)->state);

        $attemptclass = utils::quiz_attempt_classname();
        $attemptobj = $attemptclass::create($attempt->get_attemptid());
        $this->assertSame($attemptclass::IN_PROGRESS, $attemptobj->get_attempt()->state);
        $attemptobj->process_finish(time(), false);

        $after = $this->reload($session->id);
        $this->assertSame(
            session::STATE_FINISHED,
            $after->state,
            'pressing Finish exam must not leave the learner in a state they cannot exit'
        );
        $this->assertNotEmpty($after->timefinishing, 'the moment recording stopped must be recorded');
    }

    /**
     * SCENARIO: the learner closes the tab and comes back ten minutes later.
     *
     * The session is still open, so it is found and reused rather than a second one
     * being created -- the sessionId has to stay the same across a relaunch, or
     * Constructor sees two exams.
     */
    public function test_returning_later_reuses_the_open_session(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());

        $found = session::get_open_for_quiz((int) $this->quiz->id, (int) $this->user->id);

        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);
        $this->assertSame(
            $session->sessionkey,
            $found->sessionkey,
            'a relaunch must reuse the same sessionId'
        );
    }

    /**
     * SCENARIO: the learner comes back after the exam is over.
     *
     * A finished session is not open, so nothing is offered for reuse and the
     * learner cannot resume proctoring against a submitted attempt.
     */
    public function test_a_finished_session_is_not_reused(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());
        $attempt->process_finish(time(), false);
        $this->ignore_unreachable_finish_api();

        $this->assertSame(session::STATE_FINISHED, $this->reload($session->id)->state);
        $this->assertNull(
            session::get_open_for_quiz((int) $this->quiz->id, (int) $this->user->id),
            'a finished exam must not hand back a reusable session'
        );
    }

    /**
     * SCENARIO: the quiz time limit runs out and core abandons the attempt.
     *
     * The session still has to reach a terminal state, and the reason has to say the
     * learner never submitted -- otherwise an abandoned attempt is indistinguishable
     * from a completed one in the record.
     */
    public function test_time_running_out_abandons_the_session(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());

        $attempt->process_abandon(time(), false);
        $this->ignore_unreachable_finish_api();

        $after = $this->reload($session->id);
        $this->assertSame(session::STATE_FINISHED, $after->state);
        $this->assertSame(
            'attempt_abandoned',
            $after->reason,
            'an abandoned attempt must be distinguishable from a submitted one'
        );
    }

    /**
     * SCENARIO: the learner leaves during the pre-exam check, before proctoring starts.
     *
     * The attempt exists with a running timer from the moment the launch page creates
     * it, so core eventually abandons it. The session must not sit in PENDING forever,
     * and the record must show proctoring never began.
     */
    public function test_leaving_during_preparation_is_recorded_as_never_started(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid(), session::STATE_PENDING);

        session::mark_submitted($attempt->get_attemptid());

        $after = $this->reload($session->id);
        $this->assertSame(session::STATE_FINISHED, $after->state);
        $this->assertSame(
            'never_started',
            $after->reason,
            'a session that never became active must say so'
        );
    }

    /**
     * SCENARIO: the learner submits, then the browser sends a late event.
     *
     * event.php is driven by the learner's own tab, so a replayed or delayed message
     * is untrusted input. It must not walk a finished session backwards.
     */
    public function test_a_late_browser_event_cannot_reopen_a_finished_session(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());
        $attempt->process_finish(time(), false);
        $this->ignore_unreachable_finish_api();

        $finished = $this->reload($session->id);
        $this->assertSame(session::STATE_FINISHED, $finished->state);

        $this->assertFalse(session::transition($finished, session::STATE_ACTIVE));
        $this->assertFalse(session::transition($finished, session::STATE_FINISHING));
        $this->assertSame(session::STATE_FINISHED, $this->reload($session->id)->state);
    }

    /**
     * Submitting twice must not move the recorded finish time.
     *
     * timefinished is when the exam ended; a repeated event must not rewrite it.
     */
    public function test_finishing_twice_keeps_the_first_timestamp(): void {
        $attempt = $this->start_attempt();
        $session = $this->make_session($attempt->get_attemptid());

        $attempt->process_finish(time(), false);
        $this->ignore_unreachable_finish_api();
        $first = $this->reload($session->id)->timefinished;

        session::mark_submitted($attempt->get_attemptid());

        $this->assertSame($first, $this->reload($session->id)->timefinished);
    }

    /**
     * New entry keeps the Moodle attempt but gives Constructor a fresh sessionId.
     */
    public function test_new_entry_preserves_attempt_and_is_idempotent(): void {
        global $DB;

        $attempt = $this->start_attempt();
        $old = $this->make_session($attempt->get_attemptid());
        $remote = (object) [
            'status' => 'finished',
            'sessionStart' => '2026-08-22T18:40:00Z',
            'sessionEnd' => '2026-08-22T18:42:00Z',
        ];

        $new = session::create_new_entry((int) $old->id, $remote);

        $this->assertNotNull($new);
        $this->assertNotEquals($old->id, $new->id);
        $this->assertNotSame($old->sessionkey, $new->sessionkey);
        $this->assertEquals($attempt->get_attemptid(), $new->attemptid);
        $this->assertSame(session::STATE_PENDING, $new->state);
        $this->assertNotEmpty($new->timebound);

        $oldafter = $this->reload($old->id);
        $this->assertSame(session::STATE_ABANDONED, $oldafter->state);
        $this->assertSame('new_entry', $oldafter->reason);
        $this->assertNotEmpty($oldafter->sessionend);
        $attemptclass = utils::quiz_attempt_classname();
        $this->assertSame(
            $attemptclass::IN_PROGRESS,
            $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->get_attemptid()])
        );
        $this->assertEquals($new->id, session::get_by_attempt($attempt->get_attemptid())->id);

        $this->assertNull(
            session::create_new_entry((int) $old->id, $remote),
            'replaying the old action must not create a second replacement'
        );
        $this->assertSame(2, $DB->count_records(session::TABLE, [
            'attemptid' => $attempt->get_attemptid(),
        ]));
    }

    /**
     * A teacher cannot create a parallel recording while Constructor is active.
     */
    public function test_new_entry_refuses_an_active_constructor_session(): void {
        global $DB;

        $attempt = $this->start_attempt();
        $old = $this->make_session($attempt->get_attemptid());

        try {
            session::create_new_entry((int) $old->id, (object) ['status' => 'started']);
            $this->fail('an active Constructor session must not be replaced');
        } catch (\moodle_exception $e) {
            $this->assertSame(
                get_string('error_sdk_new_entry_session_active', 'availability_proctor'),
                $e->getMessage()
            );
        }

        $this->assertSame(session::STATE_ACTIVE, $this->reload($old->id)->state);
        $this->assertSame(1, $DB->count_records(session::TABLE, [
            'attemptid' => $attempt->get_attemptid(),
        ]));
    }

    /**
     * Submission settles only the newest session after New entry.
     */
    public function test_submission_after_new_entry_finishes_the_newest_session(): void {
        $attempt = $this->start_attempt();
        $old = $this->make_session($attempt->get_attemptid());
        $new = session::create_new_entry((int) $old->id, (object) [
            'status' => 'finished',
            'sessionEnd' => '2026-08-22T18:42:00Z',
        ]);
        session::transition($new, session::STATE_ACTIVE, 'token_issued');

        $attempt->process_finish(time(), false);
        $this->ignore_unreachable_finish_api();

        $this->assertSame(session::STATE_ABANDONED, $this->reload($old->id)->state);
        $this->assertSame(session::STATE_FINISHED, $this->reload($new->id)->state);
    }

    /**
     * A terminal webhook blocks silent reuse until an instructor creates New entry.
     */
    public function test_external_end_requires_new_entry_for_an_open_attempt(): void {
        global $DB;

        $attempt = $this->start_attempt();
        $old = $this->make_session($attempt->get_attemptid());
        $DB->set_field(session::TABLE, 'sessionend', time(), ['id' => $old->id]);
        $DB->set_field(session::TABLE, 'timeresult', time(), ['id' => $old->id]);
        $ended = $this->reload($old->id);

        $this->assertFalse(session::allows_access($ended));
        $this->assertNull(session::get_open_for_quiz((int) $this->quiz->id, (int) $this->user->id));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(
            get_string('error_sessionended_newentry', 'availability_proctor')
        );
        session::get_or_create((int) $this->quiz->id, (int) $this->user->id);
    }
}
