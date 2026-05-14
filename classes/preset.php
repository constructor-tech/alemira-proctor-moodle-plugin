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
 * @copyright  2026 Constructor Tech
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
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset {
    const TABLE = 'availability_proctor_presets';

    const TYPE_GLOBAL = 'global';
    const TYPE_USER = 'user';

    /** @var array Fields stored as JSON TEXT. */
    const JSON_FIELDS = ['rules', 'warnings', 'scoring'];

    /** @var array Identity columns stripped when exposing preset values as defaults. */
    const IDENTITY_FIELDS = [
        'id', 'name', 'type', 'userid',
        'is_default', 'is_system',
        'timecreated', 'timemodified',
    ];

    /** @var int Maximum personal presets a single user may own. */
    const MAX_USER_PRESETS = 50;

    /**
     * @var array Allow-rule => warning suppression map. When the allow-rule is
     * enabled, the corresponding warning is forced off so we never alert on
     * behaviour the policy has explicitly permitted.
     */
    const RULE_WARNING_MAP = [
        'allow_to_use_websites' => 'warning_change_active_window_on_computer',
        'allow_voices' => 'warning_voice_detected',
        'allow_wrong_gaze_direction' => 'warning_avert_eyes',
        'allow_absence_in_frame' => 'warning_no_user_in_frame',
    ];

    /**
     * Canonical (locale-independent) names of the three seeded starter presets.
     * The seeder dedupes on these literal strings, so changing the Moodle UI
     * language between upgrades doesn't cause re-seeding under a localized name.
     * display_name() maps them back to localized strings for UI rendering.
     */
    const SEED_NAME_HIGH_STAKES = 'High-stakes';
    const SEED_NAME_LOW_STAKES = 'Low-stakes';
    const SEED_NAME_OPEN_BOOK = 'Open-book';

    /**
     * Return the field values used to pre-populate the form when an admin
     * creates a new preset (not a copy of an existing one). The values come
     * from the current default global preset row — i.e. whichever preset
     * has is_default=1 in the DB (Low-stakes after a fresh install).
     *
     * Identity columns (id, name, type, is_default, is_system, ...) are
     * stripped so the result can be fed straight to moodleform::set_data().
     *
     * @return array decoded preset field values (rules/warnings/scoring as arrays)
     */
    public static function get_defaults() {
        // Cached per-request; admin-only path so a few hits per page are common.
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $default = self::get_default();
        if (!$default) {
            // Safety net: seeding never ran or every preset was deleted.
            self::ensure_default_exists();
            $default = self::get_default();
        }
        $values = (array) $default;
        foreach (self::IDENTITY_FIELDS as $field) {
            unset($values[$field]);
        }
        $cache = $values;
        return $cache;
    }

    /**
     * Return a localized display name for a preset record. The DB stores
     * locale-independent canonical names for the seeded system presets;
     * this helper maps them back to the user's language for UI rendering.
     * Non-system presets (user-renamed system rows, custom global presets,
     * personal presets) are returned verbatim.
     *
     * @param stdClass $record decoded preset record (or DB row)
     * @return string display name for the current language
     */
    public static function display_name($record) {
        if (empty($record->is_system)) {
            return $record->name;
        }
        $map = [
            self::SEED_NAME_HIGH_STAKES => 'preset_display_high_stakes',
            self::SEED_NAME_LOW_STAKES => 'preset_display_low_stakes',
            self::SEED_NAME_OPEN_BOOK => 'preset_display_open_book',
        ];
        if (isset($map[$record->name])) {
            return get_string($map[$record->name], 'availability_proctor');
        }
        return $record->name;
    }

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
     * Per-request cached: called on every condition read via the hidden-field
     * enforcement path, and we don't want a DB hit each time.
     *
     * @return stdClass|false
     */
    public static function get_default() {
        global $DB;
        static $cache = null;
        static $cached = false;
        if ($cached) {
            return $cache;
        }
        $record = $DB->get_record(self::TABLE, ['is_default' => 1, 'type' => self::TYPE_GLOBAL]);
        if (!$record) {
            // Fall back to any global preset.
            $record = $DB->get_record(self::TABLE, ['type' => self::TYPE_GLOBAL], '*', IGNORE_MULTIPLE);
        }
        $cache = $record ? self::decode($record) : false;
        $cached = true;
        return $cache;
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
     * Only fields in condition::PRESET_FIELDS are honoured; any other keys
     * in the input array are dropped so a malicious client cannot smuggle
     * extra columns (e.g. is_system) through the AJAX endpoint.
     *
     * @param int $userid
     * @param string $name
     * @param array $fields decoded preset field values (rules/warnings/scoring as arrays)
     * @return int new preset id
     */
    public static function save_user_preset($userid, $name, $fields) {
        global $DB;

        $userid = (int) $userid;
        $owned = $DB->count_records(self::TABLE, ['type' => self::TYPE_USER, 'userid' => $userid]);
        if ($owned >= self::MAX_USER_PRESETS) {
            throw new \moodle_exception('error_preset_user_quota', 'availability_proctor',
                '', self::MAX_USER_PRESETS);
        }

        $whitelisted = [];
        foreach (condition::PRESET_FIELDS as $key) {
            if (array_key_exists($key, $fields)) {
                $whitelisted[$key] = $fields[$key];
            }
        }
        $data = (object) $whitelisted;
        $data->name = $name;
        $data->type = self::TYPE_USER;
        $data->userid = $userid;
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
        // Personal presets are never default/system. Exam values are inlined
        // into course_modules.availability at save time (see condition::save()),
        // so a dangling preset_id reference after deletion is harmless — the
        // exam keeps working from its own copy. No usage check needed.
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
     * For each entry in brand::HIDDEN_FORM_FIELDS that maps to a preset
     * field, replace the submitted value with the current default global
     * preset's value. Section meta-keys in the hidden list are skipped.
     *
     * This is the backend half of the brand visibility model: the form gate
     * stops admins seeing the field, this helper stops a crafted POST from
     * sneaking a value through.
     *
     * @param object|array $data form-submitted data (modified in place for objects)
     * @return object|array same shape with hidden fields overridden
     */
    public static function apply_hidden_field_defaults($data) {
        if (empty(brand::HIDDEN_FORM_FIELDS)) {
            return $data;
        }
        $default = self::get_default();
        if (!$default) {
            return $data;
        }
        foreach (brand::HIDDEN_FORM_FIELDS as $key) {
            if (!in_array($key, condition::PRESET_FIELDS, true)) {
                continue;
            }
            $value = isset($default->$key) ? $default->$key : null;
            if (is_array($data)) {
                $data[$key] = $value;
            } else {
                $data->$key = $value;
            }
        }
        return $data;
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

        self::normalize_before_save($data);

        if (!empty($data->name)) {
            self::assert_name_available($data);
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
        }

        // Only global presets may carry is_default=1. Force the flag off for
        // personal or any other future type, so misconstructed records can't
        // leave the table in a misleading state.
        if ($record->type !== self::TYPE_GLOBAL) {
            $record->is_default = 0;
        }

        if (empty($record->id)) {
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
     * Refuse to save a preset whose name collides with another preset in
     * the same scope. Global presets are unique by (name); personal presets
     * are unique by (name, userid).
     *
     * @param stdClass $data preset record being saved
     * @throws \moodle_exception on collision
     */
    protected static function assert_name_available($data) {
        global $DB;
        $type = isset($data->type) ? $data->type : self::TYPE_GLOBAL;
        $params = ['name' => $data->name, 'type' => $type];
        if ($type === self::TYPE_USER) {
            $params['userid'] = (int) ($data->userid ?? 0);
        }
        // Allow re-saving the same record.
        $sql = "SELECT id FROM {" . self::TABLE . "} WHERE name = :name AND type = :type";
        if ($type === self::TYPE_USER) {
            $sql .= " AND userid = :userid";
        }
        if (!empty($data->id)) {
            $sql .= " AND id <> :id";
            $params['id'] = (int) $data->id;
        }
        if ($DB->record_exists_sql($sql, $params)) {
            throw new \moodle_exception('error_preset_name_taken', 'availability_proctor');
        }
    }

    /**
     * Normalize a preset record in-place before persisting, ensuring two
     * invariants regardless of which caller (admin form, AJAX, seeder) builds
     * the record:
     *
     *  - Calculator dropdown drives the learner-visible "allow calculator"
     *    rule (off => not allowed; simple/scientific => allowed).
     *  - When an allow-rule is on, its paired warning is forced off so we
     *    never alert on behaviour the policy explicitly permits.
     *
     * @param stdClass $data preset record (mutated in place)
     * @return void
     */
    public static function normalize_before_save($data) {
        // Calculator dropdown -> allow_to_use_calculator rule.
        if (isset($data->calculator)) {
            $data->rules = isset($data->rules) ? (array) $data->rules : [];
            $data->rules['allow_to_use_calculator'] = !empty($data->calculator) && $data->calculator !== 'off';
        }

        // Rule -> warning suppression.
        if (isset($data->rules)) {
            $rules = (array) $data->rules;
            $warnings = isset($data->warnings) ? (array) $data->warnings : [];
            foreach (self::RULE_WARNING_MAP as $rkey => $wkey) {
                if (!empty($rules[$rkey])) {
                    $warnings[$wkey] = 0;
                }
            }
            $data->warnings = $warnings;
        }
    }

    /**
     * Mark the given preset as the single default global preset.
     *
     * Refuses if the id does not exist or refers to a non-global preset,
     * so callers cannot accidentally clear every is_default flag against
     * a stale/invalid id.
     *
     * @param int $id
     * @throws \moodle_exception when the preset is missing or not global
     */
    public static function set_default($id) {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        if ($record->type !== self::TYPE_GLOBAL) {
            throw new \moodle_exception('error_preset_not_found', 'availability_proctor');
        }
        // Two writes form a single invariant ("exactly one default global"),
        // so wrap them in a transaction to avoid leaving zero defaults if the
        // second statement fails.
        $transaction = $DB->start_delegated_transaction();
        $DB->execute(
            "UPDATE {" . self::TABLE . "} SET is_default = 0 WHERE type = ? AND id <> ?",
            [self::TYPE_GLOBAL, $id]
        );
        $DB->set_field(self::TABLE, 'is_default', 1, ['id' => $id]);
        $transaction->allow_commit();
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

        // Delete + promote-next form a single invariant ("at least one default
        // global preset exists at all times"). Wrap in a transaction so a
        // failure in either step rolls back the other.
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::TABLE, ['id' => $id]);

        if (!empty($record->is_default) && $record->type === self::TYPE_GLOBAL) {
            $next = $DB->get_record(self::TABLE, ['type' => self::TYPE_GLOBAL], '*', IGNORE_MULTIPLE);
            if ($next) {
                self::set_default($next->id);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Check whether a preset can be deleted.
     * Blocks deletion when the preset is the default, is system,
     * is the last remaining global preset, or is referenced by an existing exam.
     *
     * To avoid an N+1 query when listing many presets, callers may pre-fetch
     * the in-use ids via get_used_preset_ids() and pass them in as $usedset.
     * When omitted, per-call is_used_by_exams() runs the LIKE scan itself.
     *
     * @param stdClass $record raw DB record
     * @param array|null $usedset associative map of preset_id => true, or null to query
     * @return bool
     */
    public static function can_delete($record, $usedset = null) {
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
        if ($usedset !== null) {
            if (isset($usedset[(int) $record->id])) {
                return false;
            }
        } else if (self::is_used_by_exams($record->id)) {
            return false;
        }
        return true;
    }

    /**
     * @var array Tables whose `availability` JSON may carry a preset_id node.
     * Availability conditions can attach to activities (course_modules) and
     * to whole sections (course_sections), so both must be scanned.
     */
    const AVAILABILITY_TABLES = ['course_modules', 'course_sections'];

    /**
     * Detects if any availability JSON (on a course module or section)
     * references this preset.
     *
     * @param int $id
     * @return bool
     */
    public static function is_used_by_exams($id) {
        global $DB;
        // The availability JSON contains a node like {"type":"proctor","preset_id":N,...}.
        // The trailing character is either ',' (more keys follow) or '}' (last key).
        // Anchoring on the trailing char prevents preset 1 from matching "preset_id":12.
        $id = (int)$id;
        $params = [
            'p1' => '%"preset_id":' . $id . ',%',
            'p2' => '%"preset_id":' . $id . '}%',
        ];
        foreach (self::AVAILABILITY_TABLES as $table) {
            $like = $DB->sql_like('availability', ':p1') . ' OR ' . $DB->sql_like('availability', ':p2');
            if ($DB->record_exists_select($table, $like, $params)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Single-pass collection of every preset_id referenced from any
     * availability JSON on a course module or section. Use to avoid N LIKE
     * scans when checking many presets in a row (e.g. the admin list page).
     *
     * @return array associative map preset_id (int) => true
     */
    public static function get_used_preset_ids() {
        global $DB;
        $used = [];
        foreach (self::AVAILABILITY_TABLES as $table) {
            // Pre-filter to rows whose availability text contains the literal
            // "preset_id" — a single LIKE-scan with no false positives downstream
            // since parsing happens server-side.
            $rows = $DB->get_records_select(
                $table,
                $DB->sql_like('availability', ':needle'),
                ['needle' => '%"preset_id":%'],
                '',
                'id, availability'
            );
            foreach ($rows as $row) {
                if (preg_match_all('/"preset_id":\s*(\d+)/', (string) $row->availability, $matches)) {
                    foreach ($matches[1] as $m) {
                        $used[(int) $m] = true;
                    }
                }
            }
        }
        return $used;
    }

    /**
     * Duplicate a preset under a new name.
     *
     * @param int $id
     * @param string|null $name new name (defaults to original + " (copy)")
     * @return int new preset id
     */
    public static function duplicate($id, $name = null) {
        global $DB;
        $record = self::get_by_id($id);
        if (!$record) {
            throw new \moodle_exception('error_preset_not_found', 'availability_proctor');
        }
        unset($record->id);
        $record->is_default = 0;
        $record->is_system = 0;
        if ($name === null) {
            // Find a free name: "X (copy)", "X (copy 2)", "X (copy 3)", …
            $candidate = $record->name . ' (copy)';
            $suffix = 2;
            $params = ['type' => $record->type];
            $where = 'name = :name AND type = :type';
            if ($record->type === self::TYPE_USER) {
                $where .= ' AND userid = :userid';
                $params['userid'] = (int) $record->userid;
            }
            while ($DB->record_exists_select(self::TABLE, $where, $params + ['name' => $candidate])) {
                $candidate = $record->name . ' (copy ' . $suffix . ')';
                $suffix++;
                if ($suffix > 100) {
                    // Pathological case — fall back to a timestamped name.
                    $candidate = $record->name . ' (copy ' . time() . ')';
                    break;
                }
            }
            $name = $candidate;
        }
        $record->name = $name;
        return self::save($record);
    }

    /**
     * Seed system presets from the brand-specific catalog at preset_seed::definitions().
     * Idempotent: only inserts presets that don't already exist by name.
     *
     * @return void
     */
    public static function seed_initial_presets() {
        foreach (preset_seed::definitions() as $def) {
            self::seed_preset_if_missing($def['name'], $def['fields'], $def['is_default']);
        }
    }

    /**
     * Return the field values shared by every seeded preset (rules all off
     * except paper, warnings all on, scoring at coded defaults, sensible
     * neutrals for the rest). Called from preset_seed implementations to
     * build full preset records.
     *
     * @return array
     */
    public static function default_field_values() {
        $rules_all_false = [];
        foreach (condition::RULES as $k => $_) {
            $rules_all_false[$k] = false;
        }
        // Paper allowed by default (matches condition::RULES default).
        $rules_all_false['allow_to_use_paper'] = true;

        $warnings_all_on = [];
        foreach (condition::WARNINGS as $k => $_) {
            $warnings_all_on[$k] = true;
        }

        $scoring_defaults = [];
        foreach (condition::SCORING as $k => $row) {
            $scoring_defaults[$k] = isset($row['default']) ? $row['default'] : null;
        }

        return [
            'preliminarycheck' => 0,
            'allowroomscanauxcamera' => 0,
            'calculator' => 'off',
            'allowmultipledisplays' => 0,
            'allowtouseadditionalresources' => 0,
            'allowvirtualenvironment' => 0,
            'streamspreset' => 'default',
            'webcameramainview' => 'front',
            'useragreementurl' => null,
            'rules' => $rules_all_false,
            'warnings' => $warnings_all_on,
            'scoring' => $scoring_defaults,
        ];
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
     * Ensure at least one default global preset exists. Called as a safety
     * net when get_defaults() is asked for a default but none is set; in
     * normal flow seed_initial_presets() runs in db/install.php so this is
     * only exercised if every preset row has been removed manually.
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

        // No global preset exists at all — recreate the canonical starter set.
        self::seed_initial_presets();
        $seeded = $DB->get_record(self::TABLE, ['is_default' => 1, 'type' => self::TYPE_GLOBAL]);
        if (!$seeded) {
            // Seeding failed silently (DB write rejected, race, ...). Fail
            // loudly rather than letting callers receive an empty record.
            throw new \moodle_exception('error_preset_default_missing', 'availability_proctor');
        }
        return $seeded->id;
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
                if (!is_array($decoded)) {
                    // Corrupted column — surface the issue in developer logs
                    // rather than silently falling back to an empty config.
                    debugging(sprintf(
                        'availability_proctor: failed to decode JSON field "%s" on preset id=%s',
                        $field,
                        isset($record->id) ? $record->id : '?'
                    ), DEBUG_DEVELOPER);
                    $record->$field = [];
                } else {
                    $record->$field = $decoded;
                }
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
