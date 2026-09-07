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

/**
 * Tests for the clean-up observers.
 *
 * The SDK tables key on quizid, which is only unique within the quiz module, so
 * every delete has to check the module type first -- otherwise an assignment
 * sharing an instance id takes a quiz's sessions with it. That is the bug these
 * tests exist to catch, since nothing in the interface would reveal it.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\observers
 */
final class observers_test extends \advanced_testcase {
    /**
     * Insert a session row against a quiz id.
     *
     * @param int $quizid The quiz instance id.
     * @param int $userid The learner.
     * @return int The row id.
     */
    private function make_session(int $quizid, int $userid = 2): int {
        global $DB;
        $now = time();
        return $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'k-' . $quizid . '-' . $userid . '-' . random_int(1000, 9999),
            'quizid' => $quizid,
            'userid' => $userid,
            'state' => session::STATE_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Deleting a quiz removes that quiz's sessions.
     */
    public function test_deleting_a_quiz_removes_its_sessions(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance(['course' => $course->id]);
        $this->make_session($quiz->id);

        $this->assertSame(1, $DB->count_records(session::TABLE, ['quizid' => $quiz->id]));

        course_delete_module($quiz->cmid);

        $this->assertSame(0, $DB->count_records(session::TABLE, ['quizid' => $quiz->id]));
    }

    /**
     * Deleting a non-quiz activity must not touch quiz sessions that happen to
     * share its instance id.
     *
     * quizid is unique only within the quiz module, so without the module-type
     * check in course_module_deleted() an assignment delete would wipe a live
     * quiz's proctoring sessions.
     */
    public function test_deleting_an_assignment_leaves_quiz_sessions_alone(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id]);

        // A session belonging to a quiz that happens to carry the assignment's
        // instance id -- the collision the module-type check exists to survive.
        $this->make_session((int) $assign->id);

        course_delete_module($assign->cmid);

        $this->assertSame(
            1,
            $DB->count_records(session::TABLE, ['quizid' => (int) $assign->id]),
            'an assignment delete must not remove sessions keyed by quiz id'
        );
    }

    /**
     * Unenrolling a learner clears the Webapp flow's unused entries.
     *
     * SDK session rows are deliberately left alone: they carry the record of a
     * proctored exam (state, timings, result) and are evidence about an attempt
     * that still exists. They are removed when the quiz itself goes, or by
     * purge_orphans(), not when someone loses access to the course.
     */
    public function test_unenrolling_clears_unused_webapp_entries(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance(['course' => $course->id]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $entry = $DB->insert_record('availability_proctor_entries', (object) [
            'courseid' => $course->id,
            'cmid' => $quiz->cmid,
            'userid' => $user->id,
            'accesscode' => '',
            'status' => 'new',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $session = $this->make_session($quiz->id, $user->id);

        $instance = $DB->get_record(
            'enrol',
            ['courseid' => $course->id, 'enrol' => 'manual'],
            '*',
            IGNORE_MULTIPLE
        );
        enrol_get_plugin('manual')->unenrol_user($instance, $user->id);

        $this->assertFalse(
            $DB->record_exists('availability_proctor_entries', ['id' => $entry]),
            'an unused Webapp entry is cleared with the enrolment'
        );
        $this->assertTrue(
            $DB->record_exists(session::TABLE, ['id' => $session]),
            'the SDK session is a record of a proctored exam and must survive'
        );
    }

    /**
     * purge_orphans() removes sessions whose quiz no longer exists.
     */
    public function test_purge_orphans_removes_sessions_with_no_quiz(): void {
        global $DB;
        $this->resetAfterTest();

        $orphan = $this->make_session(999999);

        $this->assertTrue($DB->record_exists(session::TABLE, ['id' => $orphan]));
        $this->assertGreaterThanOrEqual(1, observers::purge_orphans());
        $this->assertFalse($DB->record_exists(session::TABLE, ['id' => $orphan]));
    }

    /**
     * A live quiz's sessions survive a purge.
     */
    public function test_purge_orphans_keeps_live_sessions(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance(['course' => $course->id]);
        $keep = $this->make_session($quiz->id);

        observers::purge_orphans();

        $this->assertTrue($DB->record_exists(session::TABLE, ['id' => $keep]));
    }
}
