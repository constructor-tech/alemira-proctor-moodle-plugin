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
 * Tests for defaults passed to a new proctoring restriction.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\frontend
 */
final class frontend_test extends \advanced_testcase {
    /**
     * An upgraded site keeps its Custom rules default but not legacy groups.
     */
    public function test_legacy_custom_rules_are_kept_without_groups(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('default_proctoring_settings', json_encode((object) [
            'customrules' => "Keep desk clear.\nNo headphones.",
            'groups' => [
                $course->id => [17 => 1],
            ],
        ]), 'availability_proctor');

        $params = $this->get_init_params($course);
        $defaults = $params[4];

        $this->assertSame("Keep desk clear.\nNo headphones.", $defaults->customrules);
        $this->assertSame([], $defaults->groups);
    }

    /**
     * A clean install does not gain a Custom rules default.
     */
    public function test_missing_legacy_config_does_not_add_custom_rules(): void {
        $this->resetAfterTest();
        unset_config('default_proctoring_settings', 'availability_proctor');

        $course = $this->getDataGenerator()->create_course();
        $params = $this->get_init_params($course);
        $defaults = $params[4];

        $this->assertObjectNotHasProperty('customrules', $defaults);
        $this->assertSame([], $defaults->groups);
    }

    /**
     * Malformed legacy data is ignored instead of reaching the form.
     */
    public function test_invalid_legacy_custom_rules_are_ignored(): void {
        $this->resetAfterTest();
        set_config('default_proctoring_settings', '{invalid-json', 'availability_proctor');

        $course = $this->getDataGenerator()->create_course();
        $params = $this->get_init_params($course);

        $this->assertObjectNotHasProperty('customrules', $params[4]);
    }

    /**
     * The activity form receives hidden snapshot metadata, not editable SDK controls.
     */
    public function test_init_params_include_complete_hidden_sdk_snapshot(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $params = $this->get_init_params($course);
        $context = $params[6];

        $this->assertSame(array_values(config::SDK_FIELDS), $context['sdk_fields']);
        $this->assertCount(16, $context['sdk_defaults']);
        $this->assertArrayNotHasKey('sdk_model', $context);
        $this->assertArrayNotHasKey('sdk_per_quiz', $context);
    }

    /**
     * Returns the parameters passed to the availability form JavaScript.
     *
     * @param \stdClass $course Course record.
     * @return array
     */
    private function get_init_params($course) {
        $method = new \ReflectionMethod(frontend::class, 'get_javascript_init_params');
        $method->setAccessible(true);

        return $method->invoke(new frontend(), $course);
    }
}
