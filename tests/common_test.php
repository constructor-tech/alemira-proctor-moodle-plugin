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
 * Tests for the duration sent to Constructor Proctor.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\common
 */
final class common_test extends \advanced_testcase {
    /**
     * The Moodle quiz time limit is converted from seconds to whole minutes.
     */
    public function test_quiz_time_limit_is_returned_in_minutes(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 90,
        ]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);

        $this->assertSame(2, common::get_quiz_time_limit($cm));
    }

    /**
     * A Webapp quiz without a Moodle time limit must not silently become an
     * eight-hour proctoring session.
     */
    public function test_quiz_without_time_limit_is_rejected(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 0,
        ]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_notimelimit', 'availability_proctor'));
        common::get_quiz_time_limit($cm);
    }

    /**
     * An attempt started before quizzes needed a time limit keeps the
     * condition's stored duration, so it can still be finished after upgrade.
     */
    public function test_running_attempt_without_time_limit_uses_stored_duration(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 0,
        ]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $client = new client(new condition((object) ['type' => 'proctor', 'duration' => 45]));

        $this->assertSame(45, $client->exam_data($course, $cm, true)['duration']);

        $this->expectException(\moodle_exception::class);
        $client->exam_data($course, $cm);
    }

    /**
     * Activities without a native Moodle time limit keep their existing
     * fallback so this quiz-specific rule cannot break SCORM or Assignment.
     */
    public function test_non_quiz_activity_keeps_existing_fallback(): void {
        $cm = (object) [
            'modname' => 'assign',
            'instance' => 1,
        ];

        $this->assertSame(condition::MAX_LIMIT, common::get_quiz_time_limit($cm));
    }

    /**
     * A Moodle user override must permit the matching number of Proctor entries.
     */
    public function test_user_attempt_override_allows_new_entry(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'attempts' => 1,
        ]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'attempts' => 2,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($quiz->cmid);
        $first = condition::make_entry($course->id, $cm->id, $user->id);
        $first->status = 'finished';
        $first->id = $DB->insert_record('availability_proctor_entries', $first);

        $created = common::create_entry(
            new condition((object) ['type' => 'proctor']),
            $user->id,
            $cm
        );

        $this->assertNotEquals($first->id, $created->id);
        $this->assertSame('new', $created->status);
    }

    /**
     * A Moodle group override must permit the matching number of Proctor entries.
     */
    public function test_group_attempt_override_allows_new_entry(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->assertTrue(groups_add_member($group->id, $user->id));
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'attempts' => 1,
        ]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'groupid' => $group->id,
            'attempts' => 2,
        ]);

        $cm = get_fast_modinfo($course)->get_cm($quiz->cmid);
        $first = condition::make_entry($course->id, $cm->id, $user->id);
        $first->status = 'finished';
        $first->id = $DB->insert_record('availability_proctor_entries', $first);

        $created = common::create_entry(
            new condition((object) ['type' => 'proctor']),
            $user->id,
            $cm
        );

        $this->assertNotEquals($first->id, $created->id);
        $this->assertSame('new', $created->status);
    }

    /**
     * Without an override, the quiz-wide attempt limit must still be enforced.
     */
    public function test_quiz_attempt_limit_still_reuses_last_entry(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'attempts' => 1,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($quiz->cmid);
        $first = condition::make_entry($course->id, $cm->id, $user->id);
        $first->status = 'finished';
        $first->id = $DB->insert_record('availability_proctor_entries', $first);

        $returned = common::create_entry(
            new condition((object) ['type' => 'proctor']),
            $user->id,
            $cm
        );

        $this->assertEquals($first->id, $returned->id);
        $this->assertCount(1, $DB->get_records('availability_proctor_entries', [
            'cmid' => $cm->id,
            'userid' => $user->id,
        ]));
    }
}
