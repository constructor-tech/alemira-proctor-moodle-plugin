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
 * Tests for the plugin's cache definitions.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class caches_test extends \advanced_testcase {
    /**
     * Every cache definition needs a cachedef_ string, or the cache administration
     * page shows the raw identifier.
     */
    public function test_every_cache_definition_has_a_language_string(): void {
        global $CFG;

        $definitions = [];
        require($CFG->dirroot . '/availability/condition/proctor/db/caches.php');

        $this->assertNotEmpty($definitions);
        $strings = get_string_manager();
        foreach (array_keys($definitions) as $name) {
            $this->assertTrue(
                $strings->string_exists('cachedef_' . $name, 'availability_proctor'),
                "Missing string cachedef_{$name}"
            );
        }
    }
}
