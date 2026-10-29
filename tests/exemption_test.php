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
 * Tests that an exempt learner is not proctored under the Webapp model.
 *
 * The start-attempt check already skipped them; the attempt_started observer and
 * the quiz fader did not, so the fader sent them to Proctor mid-attempt.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\observers::attempt_started
 * @covers     \availability_proctor\utils::handle_proctoring_fader
 */
final class exemption_test extends \advanced_testcase {
    /** @var \stdClass The quiz. */
    private $quiz;

    /** @var \stdClass The course. */
    private $course;

    /**
     * A Webapp site with a proctored quiz holding one question.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        set_config('proctormodel', config::MODEL_WEBAPP, 'availability_proctor');

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->quiz = $generator->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id,
            'grade' => 100.0,
            'sumgrades' => 1,
            'timelimit' => 1800,
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $this->quiz);

        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'proctor', 'duration' => 30]],
            'showc' => [true],
        ]), ['id' => $this->quiz->cmid]);
        rebuild_course_cache($this->course->id, true);
    }

    /**
     * Create an enrolled student, exempt from proctoring on this quiz if asked.
     *
     * @param bool $exempt Grant availability/proctor:exemptproctoring.
     * @return \stdClass
     */
    private function make_student(bool $exempt): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');

        if ($exempt) {
            $role = $this->getDataGenerator()->create_role();
            $context = \context_module::instance($this->quiz->cmid);
            assign_capability('availability/proctor:exemptproctoring', CAP_ALLOW, $role, $context->id);
            role_assign($role, $user->id, $context->id);
            accesslib_clear_all_caches_for_unit_testing();
        }

        return $DB->get_record('user', ['id' => $user->id]);
    }

    /**
     * Start an attempt as the current user, which fires attempt_started.
     *
     * @param \stdClass $user The learner.
     */
    private function start_attempt(\stdClass $user): void {
        $quizobj = utils::quiz_settings_classname()::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);
    }

    /**
     * An exempt learner's attempt writes no Webapp entry row.
     */
    public function test_exempt_attempt_writes_no_entry(): void {
        global $DB;

        $student = $this->make_student(true);
        $this->setUser($student);
        $this->start_attempt($student);

        $this->assertSame(0, $DB->count_records('availability_proctor_entries'));
    }

    /**
     * An exempt learner gets no fader on the attempt pages; others still do.
     */
    public function test_exempt_learner_gets_no_fader(): void {
        state::$attempt = ['cm_id' => (int) $this->quiz->cmid, 'course_id' => (int) $this->course->id];

        $this->setUser($this->make_student(true));
        $this->assertSame('', utils::handle_proctoring_fader(null));

        $this->setUser($this->make_student(false));
        $this->assertNotSame('', utils::handle_proctoring_fader(null));
    }
}
