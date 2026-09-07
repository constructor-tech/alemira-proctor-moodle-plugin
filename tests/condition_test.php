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
 * Tests for the availability condition's stored settings.
 *
 * Core rebuilds the stored availability JSON from condition::save() alone, with
 * no merge against the previous value, so a property that save() omits is
 * destroyed the next time anyone edits the activity. That has already happened
 * to several fields; these tests pin down the ones that have been repaired.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\condition
 */
final class condition_test extends \advanced_testcase {
    /**
     * A structure shaped like one written by the shipped version of the plugin.
     *
     * @param array $extra Keys to add or override.
     * @return \stdClass
     */
    private static function structure(array $extra = []): \stdClass {
        return (object) array_merge([
            'type' => 'proctor',
            'duration' => 30,
            'mode' => 'online',
            'rules' => (object) [],
            'warnings' => (object) [],
            'scoring' => (object) [],
            'istrial' => false,
            'identification' => 'face',
            'calculator' => 'off',
            'securebrowser' => false,
            'securebrowserlevel' => 'basic',
            'streamspreset' => 'default',
        ], $extra);
    }

    /**
     * Settings that reach the proctoring payload must be written by save(), or
     * the teacher's choice never leaves the form.
     *
     * All three are in PROPS and are read by client::exam_data(), but were
     * missing from save() until NTG-185, so the Webapp flow sent the property
     * default rather than what was configured. SDK mode reads them too.
     *
     * @dataProvider persisted_field_provider
     * @param string $field Property name.
     */
    public function test_boolean_settings_are_persisted(string $field): void {
        $this->resetAfterTest();

        $saved = (new condition(self::structure([$field => true])))->save();

        $this->assertObjectHasProperty($field, $saved);
        $this->assertTrue($saved->$field);
    }

    /**
     * The false case has to be asserted separately: a field absent from save()
     * and a field saved as false both read back as false, so only the true case
     * proves the key is written, and only the false case proves the teacher can
     * turn it back off.
     *
     * @dataProvider persisted_field_provider
     * @param string $field Property name.
     */
    public function test_boolean_settings_round_trip_false(string $field): void {
        $this->resetAfterTest();

        $saved = (new condition(self::structure([$field => false])))->save();
        $reloaded = new condition($saved);

        $this->assertObjectHasProperty($field, $saved);
        $this->assertFalse($saved->$field);
        $this->assertFalse($reloaded->$field);
    }

    /**
     * Booleans repaired in NTG-185.
     *
     * @return array[]
     */
    public static function persisted_field_provider(): array {
        return [
            'allowmultipledisplays' => ['allowmultipledisplays'],
            'allowvirtualenvironment' => ['allowvirtualenvironment'],
            'checkidphotoquality' => ['checkidphotoquality'],
        ];
    }

    /**
     * validate() must clamp every enum-like field to its option list.
     *
     * calculator and streamspreset were clamped from the start; webcameramainview
     * and securebrowserlevel were not, so a value that did not come from the form
     * -- a restored preset, a direct SQL edit, a brand adding an option -- reached
     * the report page, where it was interpolated into a language string key with
     * no string_exists() guard, and the page rendered [[web_camera_main_view_xxx]].
     *
     * @dataProvider clamped_field_provider
     * @param string $field Property name.
     * @param string $valid A value that must survive untouched.
     * @param string $fallback The documented default an unknown value falls back to.
     */
    public function test_enum_fields_are_clamped(string $field, string $valid, string $fallback): void {
        $this->resetAfterTest();

        $kept = new condition(self::structure([$field => $valid]));
        $kept->validate();
        $this->assertSame($valid, $kept->$field, "{$field}: a valid value must pass through");

        $clamped = new condition(self::structure([$field => 'no-such-option']));
        $clamped->validate();
        $this->assertSame($fallback, $clamped->$field, "{$field}: an unknown value must fall back");
    }

    /**
     * Enum-like fields, with a valid value and the default they fall back to.
     *
     * @return array[]
     */
    public static function clamped_field_provider(): array {
        return [
            'calculator' => ['calculator', 'scientific', 'off'],
            'streamspreset' => ['streamspreset', 'no_video', 'default'],
            'webcameramainview' => ['webcameramainview', 'side', 'front'],
            'securebrowserlevel' => ['securebrowserlevel', 'high', 'basic'],
        ];
    }

    /**
     * A structure with no optional keys at all still constructs, and lands on
     * the documented defaults rather than on nulls.
     */
    public function test_minimal_structure_uses_defaults(): void {
        $this->resetAfterTest();

        $condition = new condition((object) ['type' => 'proctor']);

        $this->assertSame('online', $condition->mode);
        $this->assertSame('off', $condition->calculator);
        $this->assertSame('default', $condition->streamspreset);
        foreach (array_keys(self::persisted_field_provider()) as $field) {
            $this->assertFalse($condition->$field, "{$field} should default to false");
        }
    }

    /**
     * Legacy booking values must not reactivate the retired scheduling flow.
     *
     * Older conditions and migrated presets can still contain both historical
     * field-name variants. They remain readable for upgrade compatibility, but
     * runtime payload builders must always see scheduling disabled.
     */
    public function test_legacy_scheduling_values_are_ignored(): void {
        $this->resetAfterTest();

        $condition = new condition(self::structure([
            'scheduling_required' => true,
            'auto_rescheduling' => true,
            'schedulingrequired' => true,
            'autorescheduling' => true,
        ]));
        $saved = $condition->save();

        $this->assertFalse($condition->schedulingrequired);
        $this->assertFalse($condition->autorescheduling);
        $this->assertFalse($saved->scheduling_required);
        $this->assertFalse($saved->auto_rescheduling);
    }

    /**
     * An old condition with no SDK fields resolves and saves a complete safe snapshot.
     */
    public function test_missing_sdk_snapshot_is_completed(): void {
        $this->resetAfterTest();

        $condition = new condition((object) ['type' => 'proctor']);
        $saved = $condition->save();

        foreach (config::SDK_FIELDS as $key => $prop) {
            $this->assertTrue(property_exists($saved, $prop), $prop);
            $this->assertTrue(config::is_valid_sdk_snapshot_value($key, $saved->$prop), $prop);
        }
    }

    /**
     * All sixteen copied values survive an unrelated activity edit and reload.
     */
    public function test_sdk_snapshot_round_trips_all_fields(): void {
        $this->resetAfterTest();

        $snapshot = config::sdk_snapshot_defaults();
        $snapshot['sdkalwaysshowchat'] = config::SDK_YES;
        $snapshot['sdkobserveworkspace'] = '0';
        $snapshot['sdksyscheckhelpurl'] = 'https://example.org/help';
        $snapshot['sdkdisabledworkers'] = "watchFocus\nwatchMic";
        $snapshot['sdkidentificationbullets'] = '{"en":["Show your face"]}';
        $snapshot['sdktranslationsoverride'] = '{"en":{"beforeExam.title":"Ready"}}';

        $structure = (object) array_merge(['type' => 'proctor', 'mode' => 'online'], $snapshot);
        $saved = (new condition($structure))->save();
        $saved->mode = 'offline';
        $resaved = (new condition($saved))->save();

        foreach ($snapshot as $prop => $expected) {
            $this->assertSame($expected, $resaved->$prop, $prop);
        }
    }

    /**
     * save() writes the group restriction back.
     *
     * It was in PROPS and read by __construct, but never written, so core rebuilt
     * the stored JSON without it and the teacher's choice vanished on the next
     * activity edit. user_in_proctored_groups() reads an empty list as "everyone",
     * so the loss quietly widened every restriction to the whole course.
     */
    public function test_save_round_trips_groups(): void {
        $this->resetAfterTest();

        $saved = (new condition((object) [
            'type' => 'proctor',
            'groups' => [7, 9],
        ]))->save();

        $this->assertSame([7, 9], $saved->groups);
        $this->assertSame([7, 9], (new condition($saved))->groups);
    }

    /**
     * save() writes the main camera view back, for the same reason.
     */
    public function test_save_round_trips_webcameramainview(): void {
        $this->resetAfterTest();

        $saved = (new condition((object) [
            'type' => 'proctor',
            'webcameramainview' => 'side',
        ]))->save();

        $this->assertSame('side', $saved->webcameramainview);
        $this->assertSame('side', (new condition($saved))->webcameramainview);
    }

    /**
     * Editing a preset does not change an already copied condition snapshot;
     * explicitly selecting it again does.
     */
    public function test_preset_snapshot_is_stable_until_reselected(): void {
        $this->resetAfterTest();

        $data = (object) array_merge(preset::default_field_values(), [
            'name' => 'Stable SDK snapshot',
            'type' => preset::TYPE_GLOBAL,
            'sdkalwaysshowchat' => config::SDK_NO,
        ]);
        $presetid = preset::save($data);

        $selected = (new condition((object) [
            'type' => 'proctor',
            'preset_id' => $presetid,
        ]))->save();

        $data->id = $presetid;
        $data->sdkalwaysshowchat = config::SDK_YES;
        preset::save($data);

        $reloaded = new condition($selected);
        $reselected = new condition((object) ['type' => 'proctor', 'preset_id' => $presetid]);

        $this->assertSame(config::SDK_NO, $reloaded->sdkalwaysshowchat);
        $this->assertSame(config::SDK_YES, $reselected->sdkalwaysshowchat);
    }

    /**
     * Runtime uses the snapshot with correct wire types and ignores old site rows.
     */
    public function test_sdk_config_uses_only_snapshot_with_correct_types(): void {
        $this->resetAfterTest();
        set_config('sdkalwaysshowchat', 0, 'availability_proctor');

        $snapshot = config::sdk_snapshot_defaults();
        $snapshot['sdkalwaysshowchat'] = config::SDK_YES;
        $snapshot['sdkobserveworkspace'] = '30';
        $snapshot['sdksyscheckhelpurl'] = 'https://example.org/help';
        $snapshot['sdkdisabledworkers'] = "watchFocus\nwatchMic";
        $snapshot['sdkidentificationbullets'] = '{"en":["Show your face","Show your ID"]}';
        $snapshot['sdktranslationsoverride'] = '{"en":{"beforeExam.title":"Ready"}}';

        $sdk = config::sdk_config((object) $snapshot);

        $this->assertTrue($sdk->alwaysShowChat);
        $this->assertSame(30 * config::ROOM_SCAN_SCALE, $sdk->observeWorkspace);
        $this->assertSame('https://example.org/help', $sdk->syscheckHelpURL);
        $this->assertSame(['watchFocus', 'watchMic'], $sdk->disabledWorkers);
        $this->assertSame(['en' => ['Show your face', 'Show your ID']], $sdk->identificationOnboardingBullets);
        $this->assertSame(['en' => ['beforeExam.title' => 'Ready']], $sdk->translationsOverride);
    }

    /**
     * A blank support URL is intentionally omitted from the SDK config object.
     */
    public function test_empty_snapshot_support_url_is_dropped(): void {
        $this->resetAfterTest();

        $settings = (object) config::sdk_snapshot_defaults();
        $this->assertObjectNotHasProperty('syscheckHelpURL', config::sdk_config($settings));
    }
}
