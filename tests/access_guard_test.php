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
require_once($CFG->dirroot . '/availability/condition/proctor/lib.php');

/**
 * Tests for the SDK-only quiz route guard.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::availability_proctor_guard_sdk_quiz_request
 */
final class access_guard_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private $course;

    /** @var \stdClass Proctored quiz. */
    private $quiz;

    /** @var \stdClass Learner. */
    private $student;

    /**
     * Create a proctored quiz and an enrolled learner.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->quiz = $generator->create_module('quiz', [
            'course' => $this->course->id,
            'timelimit' => 1800,
            'grade' => 100.0,
            'sumgrades' => 1,
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);

        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'proctor']],
            'showc' => [true],
        ]), ['id' => $this->quiz->cmid]);
        rebuild_course_cache($this->course->id, true);

        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setUser($this->student);

        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
    }

    /**
     * Restore request globals changed by the tests.
     */
    protected function tearDown(): void {
        unset($_GET['attempt'], $_POST['attempt'], $_SERVER['HTTP_SEC_FETCH_DEST']);
        parent::tearDown();
    }

    /**
     * Start an attempt for the learner.
     *
     * @return \stdClass Attempt row.
     */
    private function start_attempt(): \stdClass {
        $settingsclass = utils::quiz_settings_classname();
        $quizobj = $settingsclass::create((int) $this->quiz->id, (int) $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $now = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $now, false, (int) $this->student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $now);

        return quiz_attempt_save_started($quizobj, $quba, $attempt);
    }

    /**
     * Create and activate an unbound SDK session.
     *
     * @return \stdClass Session row.
     */
    private function active_session(): \stdClass {
        $session = session::get_or_create((int) $this->quiz->id, (int) $this->student->id);
        session::transition($session, session::STATE_ACTIVE, 'token');
        return $session;
    }

    /**
     * Invoke the guard with the request parameter Moodle supplies.
     *
     * @param string $script Guarded script name.
     * @param int $attemptid Attempt id.
     */
    private function guard(string $script, int $attemptid): void {
        $_GET['attempt'] = $attemptid;
        $_POST['attempt'] = $attemptid;
        try {
            availability_proctor_guard_sdk_quiz_request($script);
        } finally {
            unset($_GET['attempt'], $_POST['attempt']);
        }
    }

    /**
     * Webapp sites must never enter the SDK guard.
     */
    public function test_webapp_model_is_untouched(): void {
        $attempt = $this->start_attempt();
        set_config('proctormodel', config::MODEL_WEBAPP, 'availability_proctor');
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';

        $this->guard('attempt', (int) $attempt->id);
        $this->assertTrue(true);
    }

    /**
     * A live bound session may serve the attempt inside the SDK iframe.
     */
    public function test_live_iframe_request_is_allowed(): void {
        $this->active_session();
        $attempt = $this->start_attempt();

        $this->guard('attempt', (int) $attempt->id);
        $this->assertFalse(session::is_top_level_request());
    }

    /**
     * A direct tab cannot reuse the iframe's live session.
     */
    public function test_top_level_request_is_blocked_and_recorded(): void {
        global $DB;

        $this->active_session();
        $attempt = $this->start_attempt();
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';

        try {
            $this->guard('attempt', (int) $attempt->id);
            $this->fail('A top-level attempt request must be blocked.');
        } catch (\moodle_exception $e) {
            $this->assertSame(get_string('error_nosession', 'availability_proctor'), $e->getMessage());
        }

        $this->assertTrue($DB->record_exists(session::TABLE_DENIED, [
            'attemptid' => $attempt->id,
            'reason' => 'sec_fetch_dest',
        ]));
    }

    /**
     * The top-level page inside the separately authenticated Secure Browser is
     * allowed without weakening the normal direct-tab denial above.
     */
    public function test_secure_browser_top_level_request_is_allowed(): void {
        $session = $this->active_session();
        $attempt = $this->start_attempt();
        session_cache::set_sdk_secure_browser_session((int) $session->id);
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';

        $this->guard('attempt', (int) $attempt->id);

        $this->assertTrue(session_cache::is_sdk_secure_browser_session((int) $session->id));
    }

    /**
     * An answer POST without a valid session is refused.
     */
    public function test_process_attempt_without_session_is_blocked(): void {
        $attempt = $this->start_attempt();

        $this->expectException(\moodle_exception::class);
        $this->guard('processattempt', (int) $attempt->id);
    }

    /**
     * An open attempt page keeps autosaving while the session is live.
     */
    public function test_live_autosave_is_allowed(): void {
        $this->active_session();
        $attempt = $this->start_attempt();
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';

        $this->guard('autosave', (int) $attempt->id);
        $this->assertFalse(session::is_top_level_request());
    }

    /**
     * Once Constructor ends the session, an open attempt page cannot keep
     * autosaving answers.
     */
    public function test_autosave_after_session_ended_is_blocked(): void {
        global $DB;

        $session = $this->active_session();
        $attempt = $this->start_attempt();
        $DB->set_field(session::TABLE, 'sessionend', time(), ['id' => $session->id]);
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';

        $this->expectException(\moodle_exception::class);
        $this->guard('autosave', (int) $attempt->id);
    }

    /**
     * The guard repairs a missed event binding instead of locking out a learner.
     */
    public function test_active_unbound_session_is_recovered(): void {
        global $DB;

        $attempt = $this->start_attempt();
        $session = $this->active_session();

        $this->guard('attempt', (int) $attempt->id);

        $this->assertEquals(
            $attempt->id,
            $DB->get_field(session::TABLE, 'attemptid', ['id' => $session->id])
        );
    }

    /**
     * Constructor terminal data blocks an otherwise-active local row.
     */
    public function test_externally_ended_session_requires_new_entry(): void {
        global $DB;

        $session = $this->active_session();
        $attempt = $this->start_attempt();
        $DB->set_field(session::TABLE, 'sessionend', time(), ['id' => $session->id]);
        $DB->set_field(session::TABLE, 'timeresult', time(), ['id' => $session->id]);

        try {
            $this->guard('attempt', (int) $attempt->id);
            $this->fail('an ended Constructor session must not serve questions');
        } catch (\moodle_exception $e) {
            $this->assertSame(
                get_string('error_sessionended_newentry', 'availability_proctor'),
                $e->getMessage()
            );
        }

        $this->assertTrue($DB->record_exists(session::TABLE_DENIED, [
            'attemptid' => $attempt->id,
            'reason' => 'external_session_ended',
        ]));
    }
}
