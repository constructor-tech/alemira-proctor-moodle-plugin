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
     * missing from save() until NTG-185, so the hosted flow sent the property
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
     * NTG-185: a per-exam SDK override of "No" survives the constructor.
     *
     * These six parameters are three-state -- '' inherits the preset, '0' and '1'
     * override it -- and the constructor read them with !empty(), which rejects '0'.
     * Every "No" a teacher saved was therefore read back as inherit, silently taking
     * the preset's value at exam time instead. save() stored '0' correctly, so the
     * stored JSON looked right and only the exam behaved wrong; no test covered the
     * '0' case, which is why 40 green tests never saw it. Measured 2026-08-18.
     *
     * @dataProvider sdk_override_provider
     * @param string $prop The condition property carrying the override.
     */
    public function test_sdk_override_no_is_read($prop): void {
        $this->resetAfterTest();

        $condition = new condition((object) ['type' => 'proctor', $prop => '0']);

        $this->assertSame('0', $condition->$prop, "{$prop} must keep an explicit No");
    }

    /**
     * The same six, overridden to "Yes".
     *
     * @dataProvider sdk_override_provider
     * @param string $prop The condition property carrying the override.
     */
    public function test_sdk_override_yes_is_read($prop): void {
        $this->resetAfterTest();

        $condition = new condition((object) ['type' => 'proctor', $prop => '1']);

        $this->assertSame('1', $condition->$prop, "{$prop} must keep an explicit Yes");
    }

    /**
     * An absent key and an explicit empty string both mean inherit, and must leave
     * the property at its initialiser rather than becoming a '0' override.
     *
     * @dataProvider sdk_override_provider
     * @param string $prop The condition property carrying the override.
     */
    public function test_sdk_override_inherit_stays_empty($prop): void {
        $this->resetAfterTest();

        $absent = new condition((object) ['type' => 'proctor']);
        $explicit = new condition((object) ['type' => 'proctor', $prop => '']);

        $this->assertSame('', $absent->$prop, "{$prop} must inherit when the key is absent");
        $this->assertSame('', $explicit->$prop, "{$prop} must inherit on an empty string");
    }

    /**
     * The six SDK parameters an exam may override.
     *
     * Taken from config::SDK_PER_QUIZ rather than listed here, so a parameter added
     * to that map is covered without editing this file.
     *
     * @return array[]
     */
    public static function sdk_override_provider(): array {
        $cases = [];
        foreach (\availability_proctor\config::SDK_PER_QUIZ as $prop) {
            $cases[$prop] = [$prop];
        }
        return $cases;
    }

    /**
     * NTG-185: save() writes an override back and drops an inherit.
     *
     * The stored JSON is rebuilt exclusively from save()'s return value, so a key it
     * omits is destroyed on every activity edit. Inherit is written as an absent key
     * rather than an empty string, so "inherit" has one representation only.
     */
    public function test_save_round_trips_sdk_overrides(): void {
        $this->resetAfterTest();

        $structure = (object) [
            'type' => 'proctor',
            'sdkobserveworkspace' => '0',
            'sdkalwaysshowchat' => '1',
        ];

        $saved = (new condition($structure))->save();

        $this->assertSame('0', $saved->sdkobserveworkspace);
        $this->assertSame('1', $saved->sdkalwaysshowchat);
        $this->assertObjectNotHasProperty('sdkallowcameraselection', $saved);

        // And the round trip: what save() produced must read back the same.
        $reread = new condition($saved);
        $this->assertSame('0', $reread->sdkobserveworkspace);
        $this->assertSame('1', $reread->sdkalwaysshowchat);
        $this->assertSame('', $reread->sdkallowcameraselection);
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
     * The site setting supplies a parameter the exam says nothing about.
     *
     * This is the layer that replaced the preset: it is always present, so a value
     * reaches the browser whether or not the exam is linked to anything.
     */
    public function test_site_setting_applies_when_the_exam_is_silent(): void {
        $this->resetAfterTest();

        set_config('sdkbeforeexamlist', 1, 'availability_proctor');

        $sdk = config::sdk_config();

        $this->assertTrue($sdk->beforeExamList);
    }

    /**
     * An exam override beats the site setting for the six an exam may vary.
     */
    public function test_exam_override_beats_the_site_setting(): void {
        $this->resetAfterTest();

        set_config('sdkalwaysshowchat', 1, 'availability_proctor');

        $condition = new condition((object) ['type' => 'proctor', 'sdkalwaysshowchat' => config::SDK_NO]);
        $settings = (object) ['sdkalwaysshowchat' => $condition->sdkalwaysshowchat];

        $this->assertFalse(
            config::sdk_config(null, $settings)->alwaysShowChat,
            'the teacher\'s No must win over the site Yes'
        );
    }

    /**
     * With nothing configured anywhere, the shipped defaults are what is sent.
     *
     * The site settings page shows these as its declared defaults, so what an
     * administrator reads there is what an unconfigured site actually uses.
     */
    public function test_shipped_defaults_apply_to_an_unconfigured_site(): void {
        $this->resetAfterTest();

        $sdk = config::sdk_config();

        $this->assertTrue($sdk->focusRespectsRules);
        $this->assertTrue($sdk->fullScreenWatchFocusAlert);
        $this->assertFalse($sdk->beforeExamList);
        $this->assertSame(config::SDK_CONFIG['observeWorkspace'], $sdk->observeWorkspace);
    }

    /**
     * The Room Scan setting is entered in seconds and sent in milliseconds.
     *
     * Sending the form value unscaled would ask the SDK for 15000 seconds.
     */
    public function test_room_scan_is_scaled_to_milliseconds(): void {
        $this->resetAfterTest();

        set_config('sdkobserveworkspace', '30', 'availability_proctor');

        $this->assertSame(30 * config::ROOM_SCAN_SCALE, config::sdk_config()->observeWorkspace);
    }

    /**
     * An empty support URL is dropped rather than sent empty: the SDK has no
     * default for it and an empty string is not a URL.
     */
    public function test_empty_support_url_is_dropped(): void {
        $this->resetAfterTest();

        $this->assertObjectNotHasProperty('syscheckHelpURL', config::sdk_config());

        set_config('sdksyscheckhelpurl', 'https://example.org/help', 'availability_proctor');
        $this->assertSame('https://example.org/help', config::sdk_config()->syscheckHelpURL);
    }
}
