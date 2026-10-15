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
 * Tests for the site-level proctoring model setting.
 *
 * These protect the upgrade path. The model is chosen site-wide, and an existing
 * site must keep the Webapp flow it already runs; only a clean install starts on
 * SDK. Getting this wrong would move every customer's live exams onto a different
 * integration, so the fallback direction is asserted rather than assumed.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\config
 */
final class config_test extends \advanced_testcase {
    /**
     * With nothing written, the model reads as Webapp.
     *
     * This is the case a partial or failed upgrade lands in, and it must produce
     * the behaviour the site already had rather than the new one.
     */
    public function test_unset_setting_reads_as_webapp(): void {
        $this->resetAfterTest();
        unset_config('proctormodel', 'availability_proctor');

        $this->assertSame(config::MODEL_WEBAPP, config::proctoring_model());
        $this->assertFalse(config::is_sdk_model());
    }

    /**
     * Each known model is honoured as written.
     */
    public function test_known_models_are_honoured(): void {
        $this->resetAfterTest();

        set_config('proctormodel', config::MODEL_WEBAPP, 'availability_proctor');
        $this->assertSame(config::MODEL_WEBAPP, config::proctoring_model());
        $this->assertFalse(config::is_sdk_model());

        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');
        $this->assertSame(config::MODEL_SDK, config::proctoring_model());
        $this->assertTrue(config::is_sdk_model());
    }

    /**
     * Anything that is not an exact known model reads as Webapp.
     *
     * The clamp exists because the two failure directions are not equivalent:
     * falling back to Webapp gives a site what it already had, while falling
     * through to SDK would enable an integration nobody chose.
     *
     * @dataProvider rejected_value_provider
     * @param mixed $value Value found in the config row.
     */
    public function test_unknown_values_fall_back_to_webapp($value): void {
        $this->resetAfterTest();
        set_config('proctormodel', $value, 'availability_proctor');

        $this->assertSame(config::MODEL_WEBAPP, config::proctoring_model());
        $this->assertFalse(config::is_sdk_model());
    }

    /**
     * Values that must never enable SDK mode.
     *
     * @return array[]
     */
    public static function rejected_value_provider(): array {
        return [
            'empty string' => [''],
            'wrong case' => ['SDK'],
            'trailing space' => ['sdk '],
            'unknown name' => ['constructor'],
            'numeric' => ['1'],
            'zero' => ['0'],
        ];
    }

    /**
     * The clean-install hook selects SDK, and does so by writing the row rather
     * than leaving the setting to its default.
     */
    public function test_install_hook_selects_sdk(): void {
        global $CFG;
        $this->resetAfterTest();
        unset_config('proctormodel', 'availability_proctor');

        require_once($CFG->dirroot . '/availability/condition/proctor/db/install.php');
        xmldb_availability_proctor_install();

        $this->assertSame(config::MODEL_SDK, get_config('availability_proctor', 'proctormodel'));
        $this->assertSame(config::MODEL_SDK, config::proctoring_model());
    }

    /**
     * A brand without the SDK model runs Webapp even where 'sdk' is already stored,
     * and leaves the stored row as it is.
     */
    public function test_brand_without_sdk_ignores_a_stored_sdk_model(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/availability/condition/proctor/tests/fixtures/config_without_sdk_brand.php');
        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');

        $this->assertFalse(config_without_sdk_brand::is_sdk_available());
        $this->assertFalse(config_without_sdk_brand::is_sdk_model());
        $this->assertSame(config::MODEL_SDK, get_config('availability_proctor', 'proctormodel'));
    }

    /**
     * A brand without the SDK model starts a clean install on Webapp.
     */
    public function test_brand_without_sdk_installs_on_webapp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/availability/condition/proctor/tests/fixtures/config_without_sdk_brand.php');

        $this->assertSame(config::MODEL_WEBAPP, config_without_sdk_brand::initial_proctoring_model());
    }

    /**
     * The shipped brand offers the SDK model: a clean install starts on it and a
     * stored 'sdk' runs it on a supported Moodle.
     */
    public function test_default_brand_offers_the_sdk_model(): void {
        $this->resetAfterTest();
        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');

        $this->assertTrue(brand::is_sdk_model_enabled());
        $this->assertSame(config::MODEL_SDK, config::initial_proctoring_model());
        $this->assertTrue(config::is_sdk_available());
        $this->assertTrue(config::is_sdk_model());
    }

    /**
     * The upgrade step pins the Webapp model for a site that has none recorded.
     */
    public function test_upgrade_step_pins_webapp(): void {
        global $CFG;
        $this->resetAfterTest();
        unset_config('proctormodel', 'availability_proctor');

        // upgrade_plugin_savepoint() lives in upgradelib.php (a web upgrade has it
        // loaded already) and refuses to move a component's recorded version
        // backwards -- so the recorded version is wound back to match the
        // $oldversion being simulated, exactly as it would be on a real site.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/availability/condition/proctor/db/upgrade.php');
        set_config('version', 2026071706, 'availability_proctor');
        xmldb_availability_proctor_upgrade(2026071706);

        $this->assertSame(config::MODEL_WEBAPP, get_config('availability_proctor', 'proctormodel'));
    }

    /**
     * The upgrade step must not overwrite a model an admin has already chosen.
     *
     * Relevant on a site that takes two releases in one upgrade run, or where the
     * step is reached again after a savepoint failure.
     */
    public function test_upgrade_step_preserves_an_existing_choice(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');

        // upgrade_plugin_savepoint() lives in upgradelib.php (a web upgrade has it
        // loaded already) and refuses to move a component's recorded version
        // backwards -- so the recorded version is wound back to match the
        // $oldversion being simulated, exactly as it would be on a real site.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/availability/condition/proctor/db/upgrade.php');
        set_config('version', 2026071706, 'availability_proctor');
        xmldb_availability_proctor_upgrade(2026071706);

        $this->assertSame(config::MODEL_SDK, get_config('availability_proctor', 'proctormodel'));
    }

    /**
     * Every alias a ported SDK file reads resolves to a stored setting.
     *
     * Asserted as a whole rather than one name at a time, because the failure this
     * catches is silent by construction: a ported file reads $config->somename off
     * the object get() returns, and an alias missing from SETTING_NAMES makes that
     * read null with nothing to report it. senduseremails shipped that way -- the
     * email claim was never sent whatever the administrator had chosen. A missing
     * alias now fails here instead of at exam time.
     */
    public function test_every_alias_resolves_to_a_stored_setting(): void {
        $this->resetAfterTest();

        // A value per stored name, distinct so a crossed wire is visible rather
        // than passing by coincidence.
        $written = [];
        foreach (config::SETTING_NAMES as $alias => $storedname) {
            $written[$alias] = 'value-for-' . $storedname;
            set_config($storedname, $written[$alias], 'availability_proctor');
        }

        $config = config::get();

        foreach ($written as $alias => $expected) {
            $this->assertObjectHasProperty(
                $alias,
                $config,
                "config::get() must expose '{$alias}'; a ported file reads it by that name"
            );
            $this->assertSame(
                $expected,
                $config->$alias,
                "'{$alias}' must resolve to the value stored under its own setting name"
            );
        }
    }

    /**
     * The administrator's choice about sending learner email addresses reaches the
     * SDK payload builder.
     *
     * Pinned on its own because it is the one alias that carries personal data
     * off-site, so both directions matter: opting in must send it, and opting out
     * must not. It read as "not configured" under both settings until the alias was
     * added, which is the safe direction and exactly why nobody noticed.
     */
    public function test_the_user_emails_choice_is_readable_under_the_sdk_name(): void {
        $this->resetAfterTest();

        set_config('user_emails', 1, 'availability_proctor');
        $this->assertNotEmpty(config::get()->senduseremails);

        set_config('user_emails', 0, 'availability_proctor');
        $this->assertEmpty(config::get()->senduseremails);
    }

    /**
     * A demo exam reaches the SDK payload builder as post-exam review, even when
     * its preset is set to live proctoring, so a demo session never connects to a
     * live proctor. A regular exam keeps the preset's mode.
     */
    public function test_demo_exam_is_never_live_proctored_under_sdk(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $demo = $generator->create_module('quiz', ['course' => $course->id, 'timelimit' => 1800]);
        $regular = $generator->create_module('quiz', ['course' => $course->id, 'timelimit' => 1800]);

        foreach ([$demo->cmid => true, $regular->cmid => false] as $cmid => $istrial) {
            $DB->set_field('course_modules', 'availability', json_encode([
                'op' => '&',
                'c' => [['type' => 'proctor', 'mode' => 'online', 'istrial' => $istrial]],
                'showc' => [true],
            ]), ['id' => $cmid]);
        }
        rebuild_course_cache($course->id, true);

        $this->assertSame('offline', config::get_quiz_settings((int) $demo->id)->proctoringmode);
        $this->assertSame('online', config::get_quiz_settings((int) $regular->id)->proctoringmode);
    }
}
