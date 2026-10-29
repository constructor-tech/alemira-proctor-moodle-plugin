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
 * Tests that staff are not proctored on assign and SCORM activities.
 *
 * A teacher opening a proctored assignment to grade it must reach the normal
 * Moodle page: no redirect to Proctor, no fader, no proctoring entry.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\utils
 */
final class activity_staff_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private $course;

    /**
     * Create a course for the activities.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // The SCORM generator refuses to run without a current user.
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Create a proctored activity and return its course module info.
     *
     * @param string $modname assign or scorm.
     * @return \cm_info
     */
    private function make_proctored(string $modname): \cm_info {
        global $DB;

        $module = $this->getDataGenerator()->create_module($modname, ['course' => $this->course->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'proctor']],
            'showc' => [true],
        ]), ['id' => $module->cmid]);
        rebuild_course_cache($this->course->id, true);

        return get_fast_modinfo($this->course->id)->get_cm($module->cmid);
    }

    /**
     * Create a user enrolled in the course with the given role.
     *
     * @param string $role Role shortname.
     * @return \stdClass
     */
    private function make_user(string $role): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $role);
        return $user;
    }

    /**
     * Teachers are staff, students are not, on both activity types.
     *
     * @dataProvider role_provider
     * @param string $modname assign or scorm.
     * @param string $role Role shortname.
     * @param bool $expected Whether the role counts as staff.
     */
    public function test_staff_detection(string $modname, string $role, bool $expected): void {
        $cminfo = $this->make_proctored($modname);
        $user = $this->make_user($role);

        $this->assertSame($expected, utils::is_activity_staff($cminfo, (int) $user->id));
    }

    /**
     * Default roles and whether each is staff.
     *
     * @return array[]
     */
    public static function role_provider(): array {
        $cases = [];
        foreach (['assign', 'scorm'] as $modname) {
            $cases["{$modname} student"] = [$modname, 'student', false];
            $cases["{$modname} teacher"] = [$modname, 'teacher', true];
            $cases["{$modname} editingteacher"] = [$modname, 'editingteacher', true];
        }
        return $cases;
    }

    /**
     * The fader is not added to any page of the activity for a teacher.
     *
     * @dataProvider modname_provider
     * @param string $modname assign or scorm.
     */
    public function test_teacher_gets_no_fader(string $modname): void {
        global $DB;

        $cminfo = $this->make_proctored($modname);
        $this->setUser($this->make_user('editingteacher'));

        $fader = $modname === 'assign'
            ? utils::handle_proctoring_fader_assign($cminfo)
            : utils::handle_proctoring_fader_scorm($cminfo);

        $this->assertSame('', $fader);
        $this->assertSame(0, $DB->count_records('availability_proctor_entries'));
    }

    /**
     * Opening the activity as a teacher returns without redirecting or creating an entry.
     *
     * The learner branch ends in die(), so reaching the assertions is itself the
     * proof that the teacher was not sent to Proctor.
     *
     * @dataProvider modname_provider
     * @param string $modname assign or scorm.
     */
    public function test_teacher_is_not_sent_to_proctor(string $modname): void {
        global $DB;

        $cminfo = $this->make_proctored($modname);
        $teacher = $this->make_user('teacher');
        $this->setUser($teacher);

        $cm = $cminfo->get_course_module_record();
        if ($modname === 'assign') {
            utils::handle_start_attempt_assign($this->course, $cm, $teacher);
        } else {
            utils::handle_start_attempt_scorm($this->course, $cm, $teacher);
        }

        $this->assertSame(0, $DB->count_records('availability_proctor_entries'));
        $this->assertFalse(state::$lockdown);
    }

    /**
     * Activity types covered by the staff check.
     *
     * @return array[]
     */
    public static function modname_provider(): array {
        return [
            'assign' => ['assign'],
            'scorm' => ['scorm'],
        ];
    }
}
