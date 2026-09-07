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
}
