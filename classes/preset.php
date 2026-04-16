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

/**
 * Availability plugin for integration with Proctor by Constructor.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Reusable proctoring preset (exam conduct policy).
 *
 * Stores the subset of proctoring fields enumerated in
 * condition::PRESET_FIELDS plus identity metadata
 * (name, is_default, is_system, type, userid).
 */
class preset {
    const TABLE = 'availability_proctor_presets';

    const TYPE_GLOBAL = 'global';
    const TYPE_USER = 'user';

    /** @var array Fields stored as JSON TEXT. */
    const JSON_FIELDS = ['rules', 'warnings', 'scoring'];

    /**
     * @var array Smart defaults applied to the seeded default preset
     * when no legacy value is present. These reflect the most common
     * "low-friction post-exam review" configuration.
     */
    const SMART_DEFAULTS = [
        // Exam mode: post-exam review, no proctor scheduling required.
        'mode' => 'offline',
        'schedulingrequired' => false,
        'autorescheduling' => false,
        // Identity: full face + ID with photo quality check, plus a
        // self-test step so the learner verifies their setup.
        'identification' => 'face_and_passport',
        'checkidphotoquality' => true,
        'preliminarycheck' => true,
        // Camera and monitoring.
        'webcameramainview' => 'front',
        'auxiliarycamera' => false,
        'allowroomscanauxcamera' => false,
        'streamspreset' => 'default',
        'sendmanualwarningstolearner' => true,
        // Secure browser off by default; minimal level when enabled.
        'securebrowser' => false,
        'securebrowserlevel' => 'basic',
        'allowvirtualenvironment' => false,
        // Resources and environment.
        'allowtouseadditionalresources' => false,
        'allowmultipledisplays' => false,
        'calculator' => 'off',
    ];

    /**
     * Load a preset by id.
     *
     * @param int $id
     * @return stdClass|false DB record (with JSON fields decoded) or false.
     */
    public static function get_by_id($id) {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        if (!$record) {
            return false;
        }
        return self::decode($record);
    }

    /**
     * Get the default preset (one is guaranteed to exist after install).
     *
     * @return stdClass|false
     */
    public static function get_default() {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['is_default' => 1, 'type' => self::TYPE_GLOBAL]);
        if (!$record) {
            // Fall back to any global preset.
            $record = $DB->get_record(self::TABLE, ['type' => self::TYPE_GLOBAL], '*', IGNORE_MULTIPLE);
        }
        return $record ? self::decode($record) : false;
    }

    /**
     * List all personal presets owned by the given user.
     *
     * @param int $userid
     * @return stdClass[]
     */
    public static function get_user_presets($userid) {
        global $DB;
        $records = $DB->get_records(self::TABLE, [
            'type' => self::TYPE_USER,
            'userid' => $userid,
        ], 'name ASC');
        return array_map([self::class, 'decode'], $records);
    }

    /**
     * Save a new personal preset owned by the given user.
     *
     * @param int $userid
     * @param string $name
     * @param array $fields decoded preset field values (rules/warnings/scoring as arrays)
     * @return int new preset id
     */
    public static function save_user_preset($userid, $name, $fields) {
        $data = (object) $fields;
        unset($data->id);
        $data->name = $name;
        $data->type = self::TYPE_USER;
        $data->userid = (int) $userid;
        $data->is_default = 0;
        $data->is_system = 0;
        return self::save($data);
    }

    /**
     * Delete a personal preset, only if owned by the given user.
     *
     * @param int $id
     * @param int $userid
     * @throws \moodle_exception when ownership check fails or preset not deletable
     */
    public static function delete_user_preset($id, $userid) {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        if ($record->type !== self::TYPE_USER || (int)$record->userid !== (int)$userid) {
            throw new \moodle_exception('error_preset_not_found', 'availability_proctor');
        }
        // Personal presets are never default/system and don't appear in legacy
        // exam JSON, so the global can_delete checks would block them only via
        // is_used_by_exams (rare). Delete directly.
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List all global presets.
     *
     * @param string $sort sort column (name | is_default | is_system)
     * @param string $dir  sort direction (ASC | DESC)
     * @return stdClass[]
     */
    public static function get_all_global($sort = 'name', $dir = 'ASC') {
        global $DB;
        $allowed = ['name', 'is_default', 'is_system'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'name';
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $records = $DB->get_records(self::TABLE, ['type' => self::TYPE_GLOBAL], "$sort $dir, name ASC");
        return array_map([self::class, 'decode'], $records);
    }

    /**
     * Save (insert or update) a preset record. Decoded JSON fields
     * on the input object are encoded back to TEXT before write.
     *
     * @param stdClass $data
     * @return int preset id
     */
    public static function save($data) {
        global $DB;

        // Enforce rule -> warning suppression at the source: when an "allow"
        // rule is on, the corresponding warning is forced off in the saved
        // preset. This keeps stored data consistent regardless of caller.
        if (isset($data->rules)) {
            $rulewarningmap = [
                'allow_to_use_websites' => 'warning_change_active_window_on_computer',
                'allow_voices' => 'warning_voice_detected',
                'allow_wrong_gaze_direction' => 'warning_avert_eyes',
                'allow_absence_in_frame' => 'warning_no_user_in_frame',
            ];
            $rules = (array) $data->rules;
            $warnings = isset($data->warnings) ? (array) $data->warnings : [];
            foreach ($rulewarningmap as $rk => $wk) {
                if (!empty($rules[$rk])) {
                    $warnings[$wk] = 0;
                }
            }
            $data->warnings = $warnings;
        }

        $now = time();
        $record = self::encode(clone $data);
        $record->timemodified = $now;

        if (empty($record->id)) {
            $record->timecreated = $now;
            if (empty($record->type)) {
                $record->type = self::TYPE_GLOBAL;
            }
            if (!isset($record->is_default)) {
                $record->is_default = 0;
            }
            if (!isset($record->is_system)) {
                $record->is_system = 0;
            }
            $id = $DB->insert_record(self::TABLE, $record);
            $record->id = $id;
        } else {
            $DB->update_record(self::TABLE, $record);
        }

        if (!empty($record->is_default) && $record->type === self::TYPE_GLOBAL) {
            self::set_default($record->id);
        }

        return $record->id;
    }

    /**
     * Mark the given preset as the single default global preset.
     *
     * @param int $id
     */
    public static function set_default($id) {
        global $DB;
        $DB->execute(
            "UPDATE {" . self::TABLE . "} SET is_default = 0 WHERE type = ? AND id <> ?",
            [self::TYPE_GLOBAL, $id]
        );
        $DB->set_field(self::TABLE, 'is_default', 1, ['id' => $id]);
    }

    /**
     * Delete a preset, enforcing invariants.
     *
     * @param int $id
     * @throws \moodle_exception when deletion is not allowed
     */
    public static function delete($id) {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        if (!self::can_delete($record)) {
            throw new \moodle_exception('error_preset_cannot_delete', 'availability_proctor');
        }

        $DB->delete_records(self::TABLE, ['id' => $id]);

        // If we removed the default, promote another global preset.
        if (!empty($record->is_default) && $record->type === self::TYPE_GLOBAL) {
            $next = $DB->get_record(self::TABLE, ['type' => self::TYPE_GLOBAL], '*', IGNORE_MULTIPLE);
            if ($next) {
                self::set_default($next->id);
            }
        }
    }

    /**
     * Check whether a preset can be deleted.
     * Blocks deletion when the preset is the default, is system,
     * is the last remaining global preset, or is referenced by an existing exam.
     *
     * @param stdClass $record raw DB record
     * @return bool
     */
    public static function can_delete($record) {
        global $DB;
        if (!empty($record->is_default)) {
            return false;
        }
        if (!empty($record->is_system)) {
            return false;
        }
        if ($record->type === self::TYPE_GLOBAL) {
            $count = $DB->count_records(self::TABLE, ['type' => self::TYPE_GLOBAL]);
            if ($count <= 1) {
                return false;
            }
        }
        if (self::is_used_by_exams($record->id)) {
            return false;
        }
        return true;
    }

    /**
     * Detects if any course module's availability JSON references this preset.
     *
     * @param int $id
     * @return bool
     */
    public static function is_used_by_exams($id) {
        global $DB;
        // The availability JSON contains a node like {"type":"proctor","preset_id":N,...}.
        // A LIKE against the JSON text is sufficient since preset_id values are integers.
        $needle = '%"preset_id":' . (int)$id . '%';
        return $DB->record_exists_select(
            'course_modules',
            $DB->sql_like('availability', ':needle'),
            ['needle' => $needle]
        );
    }

    /**
     * Duplicate a preset under a new name.
     *
     * @param int $id
     * @param string|null $name new name (defaults to original + " (copy)")
     * @return int new preset id
     */
    public static function duplicate($id, $name = null) {
        $record = self::get_by_id($id);
        if (!$record) {
            throw new \moodle_exception('error_preset_not_found', 'availability_proctor');
        }
        unset($record->id);
        $record->is_default = 0;
        $record->is_system = 0;
        if ($name === null) {
            $name = $record->name . ' (copy)';
        }
        $record->name = $name;
        return self::save($record);
    }

    /**
     * Seed the three recommended starter presets (idempotent — only inserts
     * presets that don't already exist by name). Sets Low-stakes as default.
     *
     * @return void
     */
    public static function seed_initial_presets() {
        // Build complete rules / warnings / scoring with sensible defaults.
        $rulesAllFalse = [];
        foreach (condition::RULES as $k => $_) { $rulesAllFalse[$k] = false; }
        // Paper allowed by default (matches condition::RULES default).
        $rulesAllFalse['allow_to_use_paper'] = true;

        $warningsAllOn = [];
        foreach (condition::WARNINGS as $k => $_) { $warningsAllOn[$k] = true; }

        $scoringDefaults = [];
        foreach (condition::SCORING as $k => $row) {
            $scoringDefaults[$k] = isset($row['default']) ? $row['default'] : null;
        }

        $base = [
            'preliminarycheck' => 0,
            'allowroomscanauxcamera' => 0,
            'calculator' => 'off',
            'allowmultipledisplays' => 0,
            'allowtouseadditionalresources' => 0,
            'allowvirtualenvironment' => 0,
            'streamspreset' => 'default',
            'webcameramainview' => 'front',
            'useragreementurl' => null,
            'rules' => $rulesAllFalse,
            'warnings' => $warningsAllOn,
            'scoring' => $scoringDefaults,
        ];

        // High-stakes: Live + Face & ID + Secure Browser (medium) + Aux camera.
        self::seed_preset_if_missing(
            get_string('preset_seed_high_stakes', 'availability_proctor'),
            array_merge($base, [
                'mode' => 'online',
                'sendmanualwarningstolearner' => 1,
                'identification' => 'face_and_passport',
                'checkidphotoquality' => 1,
                'auxiliarycamera' => 1,
                'securebrowser' => 1,
                'securebrowserlevel' => 'medium',
            ]),
            false
        );

        // Low-stakes (default): Review + Only face, no Secure Browser, no aux cam.
        // Paper notes are NOT allowed for low-stakes exams.
        $lowStakesRules = $rulesAllFalse;
        $lowStakesRules['allow_to_use_paper'] = false;
        self::seed_preset_if_missing(
            get_string('preset_seed_low_stakes', 'availability_proctor'),
            array_merge($base, [
                'mode' => 'offline',
                'sendmanualwarningstolearner' => 0,
                'identification' => 'face',
                'checkidphotoquality' => 0,
                'auxiliarycamera' => 0,
                'securebrowser' => 0,
                'securebrowserlevel' => 'basic',
                'rules' => $lowStakesRules,
            ]),
            true
        );

        // Open-book: Review + Only face + websites/books/look-away allowed.
        $openBookRules = $rulesAllFalse;
        $openBookRules['allow_to_use_websites'] = true;
        $openBookRules['allow_to_use_books'] = true;
        $openBookRules['allow_wrong_gaze_direction'] = true;
        self::seed_preset_if_missing(
            get_string('preset_seed_open_book', 'availability_proctor'),
            array_merge($base, [
                'mode' => 'offline',
                'sendmanualwarningstolearner' => 0,
                'identification' => 'face',
                'checkidphotoquality' => 0,
                'auxiliarycamera' => 0,
                'securebrowser' => 0,
                'securebrowserlevel' => 'basic',
                'rules' => $openBookRules,
            ]),
            false
        );
    }

    /**
     * Insert a system preset if no preset with the same name already exists.
     *
     * @param string $name display name
     * @param array $fields preset field values
     * @param bool $isdefault whether to flag this preset as the global default
     */
    protected static function seed_preset_if_missing($name, $fields, $isdefault) {
        global $DB;
        if ($DB->record_exists(self::TABLE, ['name' => $name, 'type' => self::TYPE_GLOBAL])) {
            return;
        }
        $data = (object) $fields;
        $data->name = $name;
        $data->type = self::TYPE_GLOBAL;
        $data->is_system = 1;
        $data->is_default = $isdefault ? 1 : 0;
        self::save($data);
    }

    /**
     * Ensure at least one default global preset exists.
     * Used both on fresh install and as a safety net.
     *
     * @return int id of the default preset
     */
    public static function ensure_default_exists() {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['is_default' => 1, 'type' => self::TYPE_GLOBAL]);
        if ($existing) {
            return $existing->id;
        }

        $any = $DB->get_record(self::TABLE, ['type' => self::TYPE_GLOBAL], '*', IGNORE_MULTIPLE);
        if ($any) {
            self::set_default($any->id);
            return $any->id;
        }

        // Seed from previously-stored defaults blob if present, otherwise plain defaults.
        $legacy = common::get_default_proctoring_settings();
        $data = self::seed_record_from_legacy($legacy);
        $data->name = get_string('preset_default_name', 'availability_proctor');
        $data->is_default = 1;
        $data->is_system = 1;
        $data->type = self::TYPE_GLOBAL;
        return self::save($data);
    }

    /**
     * Build a preset record (decoded form) from the legacy
     * `default_proctoring_settings` config blob, falling back to
     * the hardcoded defaults declared on the condition class.
     *
     * @param stdClass|null $legacy
     * @return stdClass
     */
    public static function seed_record_from_legacy($legacy) {
        $legacy = $legacy ?: new stdClass();
        $data = new stdClass();

        // Scalars: copy if present, otherwise let DB defaults apply.
        $scalars = array_diff(condition::PRESET_FIELDS, self::JSON_FIELDS);
        foreach ($scalars as $field) {
            if (isset($legacy->$field)) {
                $data->$field = $legacy->$field;
            }
        }
        foreach (condition::BOOL_DEFAULTS as $field => $default) {
            if (in_array($field, condition::PRESET_FIELDS, true) && !isset($data->$field)) {
                $data->$field = $default;
            }
        }

        // Apply smart defaults for any field still unset.
        foreach (self::SMART_DEFAULTS as $field => $value) {
            if (!isset($data->$field)) {
                $data->$field = $value;
            }
        }

        // JSON fields: merge legacy on top of class defaults.
        $data->rules = isset($legacy->rules)
            ? array_merge(condition::RULES, (array)$legacy->rules)
            : condition::RULES;
        $data->warnings = isset($legacy->warnings)
            ? array_merge(condition::WARNINGS, (array)$legacy->warnings)
            : condition::WARNINGS;

        $scoringdefaults = [];
        foreach (condition::SCORING as $key => $row) {
            $scoringdefaults[$key] = isset($row['default']) ? $row['default'] : null;
        }
        $data->scoring = isset($legacy->scoring)
            ? array_merge($scoringdefaults, (array)$legacy->scoring)
            : $scoringdefaults;

        return $data;
    }

    /**
     * Decode JSON_FIELDS from TEXT to associative arrays on a DB record.
     *
     * @param stdClass $record
     * @return stdClass
     */
    protected static function decode($record) {
        foreach (self::JSON_FIELDS as $field) {
            if (!empty($record->$field) && is_string($record->$field)) {
                $decoded = json_decode($record->$field, true);
                $record->$field = is_array($decoded) ? $decoded : [];
            } else {
                $record->$field = [];
            }
        }
        return $record;
    }

    /**
     * Encode JSON_FIELDS from arrays/objects to TEXT for DB write.
     *
     * @param stdClass $record
     * @return stdClass
     */
    protected static function encode($record) {
        foreach (self::JSON_FIELDS as $field) {
            if (isset($record->$field) && !is_string($record->$field)) {
                $record->$field = json_encode($record->$field);
            }
        }
        return $record;
    }
}
