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
 * Tests for preset storage and its deletion guards.
 *
 * Conditions keep an independent value snapshot, while preset_id records the
 * selected source for UI and explicit refresh. Deletion guards and complete SDK
 * fields protect the preset-management workflow.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\preset
 */
final class preset_test extends \advanced_testcase {
    /**
     * Every seeded preset has all sixteen valid SDK snapshot values.
     */
    public function test_seeded_presets_contain_complete_sdk_configuration(): void {
        $this->resetAfterTest();

        foreach (preset::get_all_global() as $preset) {
            foreach (config::SDK_FIELDS as $key => $prop) {
                $this->assertTrue(property_exists($preset, $prop), $preset->name . ': ' . $prop);
                $this->assertTrue(
                    config::is_valid_sdk_snapshot_value($key, $preset->$prop),
                    $preset->name . ': ' . $prop
                );
            }
        }
    }

    /**
     * Duplicate and personal-preset saves deep-copy all sixteen SDK values.
     */
    public function test_duplicate_and_personal_save_copy_all_sdk_fields(): void {
        $this->resetAfterTest();

        $fields = preset::default_field_values();
        $fields['sdkalwaysshowchat'] = config::SDK_YES;
        $fields['sdkobserveworkspace'] = '42';
        $fields['sdksyscheckhelpurl'] = 'https://example.org/support';
        $fields['sdkdisabledworkers'] = "watchFocus\nwatchMic";
        $fields['sdkidentificationbullets'] = '{"en":["Show ID"]}';
        $fields['sdktranslationsoverride'] = '{"en":{"title":"Ready"}}';

        $source = (object) array_merge($fields, [
            'name' => 'SDK copy source',
            'type' => preset::TYPE_GLOBAL,
        ]);
        $sourceid = preset::save($source);
        $duplicate = preset::get_by_id(preset::duplicate($sourceid, 'SDK copy duplicate'));

        $owner = $this->getDataGenerator()->create_user();
        $personal = preset::get_by_id(preset::save_user_preset($owner->id, 'SDK personal copy', $fields));

        foreach (config::SDK_FIELDS as $prop) {
            $this->assertSame($fields[$prop], $duplicate->$prop, 'duplicate: ' . $prop);
            $this->assertSame($fields[$prop], $personal->$prop, 'personal: ' . $prop);
        }
    }

    /**
     * Preset validation rejects unsafe Room Scan, URL and JSON values.
     */
    public function test_sdk_validation_rejects_invalid_values(): void {
        $fields = preset::default_field_values();
        $fields['sdkobserveworkspace'] = '-1';
        $fields['sdksyscheckhelpurl'] = 'javascript:alert(1)';
        $fields['sdkidentificationbullets'] = '{broken';
        $fields['sdktranslationsoverride'] = 'not json';

        $errors = preset::sdk_validation_errors($fields);

        $this->assertArrayHasKey('sdkobserveworkspace', $errors);
        $this->assertArrayHasKey('sdksyscheckhelpurl', $errors);
        $this->assertArrayHasKey('sdkidentificationbullets', $errors);
        $this->assertArrayHasKey('sdktranslationsoverride', $errors);
    }

    /**
     * A Webapp-form edit omits SDK controls but must retain the stored values.
     */
    public function test_partial_update_preserves_hidden_sdk_fields(): void {
        $this->resetAfterTest();

        $fields = preset::default_field_values();
        $fields['sdkalwaysshowchat'] = config::SDK_YES;
        $fields['sdkobserveworkspace'] = '37';
        $fields['sdksyscheckhelpurl'] = 'https://example.org/preserved';
        $fields['sdkdisabledworkers'] = "watchFocus\nwatchMic";

        $id = preset::save((object) array_merge($fields, [
            'name' => 'Hidden SDK preservation',
            'type' => preset::TYPE_GLOBAL,
        ]));
        $before = preset::get_by_id($id);

        preset::save((object) [
            'id' => $id,
            'name' => 'Hidden SDK preservation edited',
            'calculator' => 'simple',
        ]);
        $after = preset::get_by_id($id);

        foreach (config::SDK_FIELDS as $prop) {
            $this->assertSame($before->$prop, $after->$prop, $prop);
        }
        $this->assertSame('simple', $after->calculator);
    }

    /**
     * Insert a global preset.
     *
     * @param array $extra Columns to set.
     * @return \stdClass The stored row.
     */
    private function make_global(array $extra = []): \stdClass {
        global $DB;
        $row = (object) array_merge([
            'name' => 'Preset ' . random_int(1000, 9999),
            'type' => preset::TYPE_GLOBAL,
            'is_system' => 0,
            'is_default' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $extra);
        $row->id = $DB->insert_record(preset::TABLE, $row);
        return $DB->get_record(preset::TABLE, ['id' => $row->id]);
    }

    /**
     * The seeded presets exist after install, exactly one of them is the default,
     * and get_default() returns it.
     */
    public function test_seeding_leaves_exactly_one_default(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertGreaterThan(0, $DB->count_records(preset::TABLE));
        $this->assertSame(1, $DB->count_records(
            preset::TABLE,
            ['is_default' => 1, 'type' => preset::TYPE_GLOBAL]
        ));

        $default = preset::get_default();
        $this->assertNotFalse($default);
        $this->assertEquals(1, $default->is_default);
    }

    /**
     * The default preset must not be deletable: an exam that inherits from it
     * would have nothing to read at exam time.
     */
    public function test_default_preset_cannot_be_deleted(): void {
        $this->resetAfterTest();

        $default = preset::get_default();

        $this->assertFalse(preset::can_delete($default));
    }

    /**
     * System presets are shipped by the plugin and must survive.
     */
    public function test_system_preset_cannot_be_deleted(): void {
        $this->resetAfterTest();

        $system = $this->make_global(['is_system' => 1]);

        $this->assertFalse(preset::can_delete($system));
    }

    /**
     * An ordinary global preset that nothing uses can be deleted.
     */
    public function test_unused_global_preset_can_be_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $spare = $this->make_global();

        $this->assertTrue(preset::can_delete($spare));

        preset::delete($spare->id);
        $this->assertFalse($DB->record_exists(preset::TABLE, ['id' => $spare->id]));
    }

    /**
     * A preset an exam still points at must not be deletable.
     *
     * The exam stores only preset_id, so deleting the row would silently change
     * how that exam is proctored the next time it is read.
     */
    public function test_preset_used_by_an_exam_cannot_be_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $used = $this->make_global();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance(['course' => $course->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'proctor', 'preset_id' => (int) $used->id]],
            'showc' => [true],
        ]), ['id' => $quiz->cmid]);

        $this->assertTrue(preset::is_used_by_exams($used->id));
        $this->assertFalse(preset::can_delete($used));

        $this->expectException(\moodle_exception::class);
        preset::delete($used->id);
    }

    /**
     * Deleting the last global preset is refused, so the site never ends up with
     * no preset for a new exam to start from.
     */
    public function test_last_global_preset_cannot_be_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        // Clear the seeded rows, leaving one deletable-looking global preset.
        $DB->delete_records(preset::TABLE);
        $only = $this->make_global();

        $this->assertFalse(preset::can_delete($only));
    }

    /**
     * set_default() moves the flag rather than adding a second default.
     */
    public function test_set_default_moves_the_flag(): void {
        global $DB;
        $this->resetAfterTest();

        $new = $this->make_global();
        preset::set_default($new->id);

        $this->assertSame(1, $DB->count_records(
            preset::TABLE,
            ['is_default' => 1, 'type' => preset::TYPE_GLOBAL]
        ));
        $this->assertEquals(1, $DB->get_field(preset::TABLE, 'is_default', ['id' => $new->id]));
    }

    /**
     * ensure_default_exists() repairs a site whose default was somehow removed --
     * the safety net get_defaults() relies on.
     */
    public function test_ensure_default_exists_repairs_a_missing_default(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->set_field(preset::TABLE, 'is_default', 0, []);
        $this->assertSame(0, $DB->count_records(preset::TABLE, ['is_default' => 1]));

        preset::ensure_default_exists();

        $this->assertSame(1, $DB->count_records(
            preset::TABLE,
            ['is_default' => 1, 'type' => preset::TYPE_GLOBAL]
        ));
    }

    /**
     * A personal preset belongs to its owner: another user cannot delete it.
     */
    public function test_user_preset_is_owned(): void {
        global $DB;
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $id = preset::save_user_preset($owner->id, 'Mine', ['mode' => 'online']);
        $this->assertNotEmpty($id);

        $this->assertCount(1, preset::get_user_presets($owner->id));
        $this->assertCount(0, preset::get_user_presets($other->id));

        // Another user's delete is refused outright rather than silently ignored,
        // and the row survives it.
        try {
            preset::delete_user_preset($id, $other->id);
            $this->fail('deleting another user\'s preset must raise');
        } catch (\moodle_exception $e) {
            $this->assertTrue($DB->record_exists(preset::TABLE, ['id' => $id]));
        }

        preset::delete_user_preset($id, $owner->id);
        $this->assertFalse($DB->record_exists(preset::TABLE, ['id' => $id]));
    }
}
