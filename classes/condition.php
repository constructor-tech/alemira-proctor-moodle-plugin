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
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use core_availability\info_module;
use moodle_exception;
use stdClass;

/**
 * Proctor by Constructor condition
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /**
     * @var array List of (de-)serializable properties.
     *
     * IMPORTANT: keep this list in sync with PRESET_FIELDS below — every
     * preset field is also a serializable property. Anything added to
     * PROPS that should live on a preset must also be added to
     * PRESET_FIELDS, and vice versa.
     */
    const PROPS = [
        'duration', 'mode', 'schedulingrequired', 'autorescheduling',
        'istrial', 'identification', 'useragreementurl',
        'securebrowser', 'securebrowserlevel',
        'allowtouseadditionalresources',
        'allowmultipledisplays', 'allowvirtualenvironment',
        'checkidphotoquality', 'webcameramainview',
        'scoring', 'warnings', 'rules', 'customrules', 'groups', 'preliminarycheck',
        'calculator', 'auxiliarycamera',
        'forbiddenprocesses', 'allowedprocesses', 'streamspreset',
        'sendmanualwarningstolearner', 'allowroomscanauxcamera',
    ];

    /**
     * @var array Subset of PROPS that belongs to a reusable preset
     * (exam conduct policy). Excludes connection settings, exam-level fields
     * (istrial, customrules, groups) and duration (computed at runtime).
     */
    const PRESET_FIELDS = [
        'mode', 'schedulingrequired', 'autorescheduling',
        'identification', 'checkidphotoquality', 'useragreementurl', 'preliminarycheck',
        'webcameramainview', 'auxiliarycamera', 'allowroomscanauxcamera',
        'streamspreset', 'sendmanualwarningstolearner',
        'securebrowser', 'securebrowserlevel',
        'allowedprocesses', 'forbiddenprocesses', 'allowvirtualenvironment',
        'allowtouseadditionalresources', 'allowmultipledisplays', 'calculator',
        'rules', 'warnings', 'scoring',
    ];

    /**
     * @var int Fallback exam duration in minutes, used when the activity
     * (e.g. a Moodle Quiz) has no time limit configured.
     */
    const MAX_LIMIT = 480;

    /** @var array List of default values for visible warnings */
    const WARNINGS = [
        'warning_extra_user_in_frame' => true,
        'warning_substitution_user' => true,
        'warning_no_user_in_frame' => true,
        'warning_avert_eyes' => true,
        'warning_change_active_window_on_computer' => true,
        'warning_forbidden_device' => true,
        'warning_voice_detected' => true,
        'warning_phone' => true,
    ];

    /** @var array List of default values for rules */
    const RULES = [
        'allow_to_use_websites' => false,
        'allow_to_use_books' => false,
        'allow_to_use_paper' => true,
        'allow_to_use_messengers' => false,
        'allow_to_use_calculator' => true,
        'allow_to_use_excel' => false,
        'allow_to_use_human_assistant' => false,
        'allow_absence_in_frame' => false,
        'allow_voices' => false,
        'allow_wrong_gaze_direction' => false,
    ];

    /** @var array List of default values and limits for scoring */
    const SCORING = [
        'cheater_level' => ['min' => 0, 'max' => 100, 'default' => null],
        'extra_user' => ['min' => 0, 'max' => 10, 'default' => null],
        'user_replaced' => ['min' => 0, 'max' => 10, 'default' => null],
        'absent_user' => ['min' => 0, 'max' => 10, 'default' => null],
        'look_away' => ['min' => 0, 'max' => 10, 'default' => null],
        'active_window_changed' => ['min' => 0, 'max' => 10, 'default' => null],
        'forbidden_device' => ['min' => 0, 'max' => 10, 'default' => null],
        'voice' => ['min' => 0, 'max' => 10, 'default' => null],
        'phone' => ['min' => 0, 'max' => 10, 'default' => null],
    ];

    /** @var array List of default values for boolean exam properties */
    const BOOL_DEFAULTS = [
        'autorescheduling' => false,
        'istrial' => false,
        'securebrowser' => false,
        'allowtouseadditionalresources' => false,
        'auxiliarycamera' => false,
        'allowmultipledisplays' => false,
        'allowvirtualenvironment' => false,
        'checkidphotoquality' => false,
        'preliminarycheck' => false,
        'sendmanualwarningstolearner' => true,
        'allowroomscanauxcamera' => false,
    ];

    /** @var array List of possible calculator options */
    const CALCULATOR_OPTIONS = [
        'off', 'scientific', 'simple',
    ];

    /** @var array List of possible streamsPreset options */
    const STREAMS_PRESET_OPTIONS = [
        'default', 'no_video', 'no_ai_detection', 'no_webcam', /* 'auxcam_and_desktop', 'auxcam_only' */
    ];

    

    /** @var array List of possible aux camera options */
    const SECURE_BROWSER_LEVELS = [
        'basic', 'medium', 'high'
    ];

    /** @var int Exam duration */
    public $duration = 60;

    /** @var string Proctoring mode */
    public $mode = 'online';

    /** @var string Main camera */
    public $webcameramainview = 'front';

    /** @var string Default calendar mode */
    public $schedulingrequired = true;

    /** @var bool Reschedule when exam was missed */
    public $autorescheduling = false;

    /** @var bool Is trial exam */
    public $istrial = false;

    /** @var array exam rules */
    public $rules = [];

    /** @var array warning rules */
    public $warnings = [];

    /** @var array scoring rules */
    public $scoring  = [];

    /** @var string identification method **/
    public $identification;

    /** @var string User agreement URL */
    public $useragreementurl = null;

    /** @var bool Auxiliary camera enabled */
    public $auxiliarycamera = false;

    

    /** @var bool Allow to use multiple displays */
    public $allowmultipledisplays = false;

    /** @var bool Allow to use virtual machines */
    public $allowvirtualenvironment = false;

    /** @var bool Check the quality of ID photo */
    public $checkidphotoquality = false;

    /** @var bool Secure browser enabled */
    public $securebrowser = false;

    /** @var string Secure browser level of security */
    public $securebrowserlevel = 'basic';

    /** @var bool Allow additional resources in Secure Browser */
    public $allowtouseadditionalresources = false;

    /** @var calculator */
    public $calculator = 'off';

    /** @var bool Preliminary check enabled */
    public $preliminarycheck = false;

    /** @var string List of custom rules */
    public $customrules = null;

    /** @var array List of allowed processes */
    public $allowedprocesses = null;

    /** @var array List of forbidden processes */
    public $forbiddenprocesses = null;

    /** @var array Apply condition to specified groups */
    public $groups = [];

    /** @var string Stream settings preset */
    public $streamspreset = null;

    /** @var bool Send manual warnings to learner */
    public $sendmanualwarningstolearner = true;

    /** @var bool Allow room scan using aux camera */
    public $allowroomscanauxcamera = false;

    /** @var int|null Linked preset id (when condition is preset-backed). */
    public $preset_id = null;

    /**
     * Construct
     *
     * @param stdClass $structure Structure
     */
    public function __construct($structure) {
        // If the saved structure references a preset, hydrate the preset
        // fields from the DB onto the same flat properties used by all
        // downstream code. Exam-level fields (istrial, customrules, groups)
        // and explicit overrides on the structure are applied on top.
        if (!empty($structure->preset_id)) {
            $preset = preset::get_by_id((int) $structure->preset_id);
            if ($preset) {
                $this->preset_id = (int) $structure->preset_id;
                $structure = self::merge_preset_into_structure($structure, $preset);
            }
        }

        $scoringdefaults = [];
        foreach (self::SCORING as $key => $row) {
            $scoringdefaults[$key] = isset($row['default']) ? $row['default'] : null;
        }

        if (!empty($structure->duration)) {
            $this->duration = $structure->duration;
        }

        if (!empty($structure->mode)) {
            $this->mode = $structure->mode;
        }

        if (!empty($structure->securebrowserlevel)) {
            $this->securebrowserlevel = $structure->securebrowserlevel;
        }

        if (!empty($structure->webcameramainview)) {
            $this->webcameramainview = $structure->webcameramainview;
        }

        if (isset($structure->scheduling_required) && $structure->scheduling_required !== null) {
            $this->schedulingrequired = $structure->scheduling_required;
        } else {
            $manualmodes = ['online', 'identification'];
            $this->schedulingrequired = in_array($this->mode, $manualmodes);
        }
        if (isset($structure->auto_rescheduling)) {
            $this->autorescheduling = $structure->auto_rescheduling;
        }

        foreach (self::BOOL_DEFAULTS as $key => $default) {
            $this->$key = isset($structure->$key) ? $structure->$key : $default;
        }
        if (isset($structure->allowtouseadditionalresources)) {
            $this->allowtouseadditionalresources = (bool) $structure->allowtouseadditionalresources;
        }

        if (!empty($structure->warnings)) {
            $warnings = array_merge(self::WARNINGS, (array)$structure->warnings);
            $this->warnings = (object)$warnings;
        } else {
            $this->warnings = (object)self::WARNINGS;
        }

        if (!empty($structure->rules)) {
            $rules = array_merge(self::RULES, (array)$structure->rules);
            $this->rules = (object)$rules;
        } else {
            $this->rules = (object)self::RULES;
        }

        if (!empty($structure->scoring)) {
            $scoring = array_merge($scoringdefaults, (array)$structure->scoring);
            $this->scoring = (object)$scoring;
        } else {
            $this->scoring = (object)$scoringdefaults;
        }

        if (!empty($structure->customrules)) {
            $this->customrules = $structure->customrules;
        }

        if (!empty($structure->calculator)) {
            $this->calculator = $structure->calculator;
        }

        if (!empty($structure->identification)) {
            $this->identification = $structure->identification;
        }

        if (!empty($structure->useragreementurl)) {
            $this->useragreementurl = $structure->useragreementurl;
        }

        if (!empty($structure->groups)) {
            $this->groups = $structure->groups;
        }

        

        if (!empty($structure->allowedprocesses)) {
            $this->allowedprocesses = $structure->allowedprocesses;
        }

        if (!empty($structure->forbiddenprocesses)) {
            $this->forbiddenprocesses = $structure->forbiddenprocesses;
        }

        if (!empty($structure->streamspreset)) {
            $this->streamspreset = $structure->streamspreset;
        }

        // Brand-hidden fields are always forced to the default preset's value,
        // overriding whatever the saved structure or a tampered submission set.
        // This is the backend security boundary for visibility (mirror of the
        // form-side gating in preset_form).
        $defaultpreset = preset::get_default();
        if ($defaultpreset) {
            foreach (brand::HIDDEN_FORM_FIELDS as $key) {
                if (!in_array($key, self::PRESET_FIELDS, true)) {
                    continue;
                }
                if (!property_exists($defaultpreset, $key) && !isset($defaultpreset->$key)) {
                    continue;
                }
                $value = $defaultpreset->$key;
                // rules/warnings/scoring are kept as stdClass elsewhere in this
                // class; preset::decode() returns them as arrays, so cast back.
                if (in_array($key, ['rules', 'warnings', 'scoring'], true)) {
                    $value = (object) (is_array($value) ? $value : (array) $value);
                }
                $this->$key = $value;
            }
        }

        $this->validate();
    }

    /**
     * Apply preset values to a saved structure for consumption by the
     * existing flat-field __construct logic. Fields explicitly set on
     * the structure win over preset values.
     *
     * @param stdClass $structure Saved availability structure
     * @param stdClass $preset Decoded preset record (from preset::get_by_id)
     * @return stdClass merged structure
     */
    protected static function merge_preset_into_structure($structure, $preset) {
        $merged = clone $structure;
        foreach (self::PRESET_FIELDS as $field) {
            if (!isset($merged->$field) && isset($preset->$field)) {
                $merged->$field = $preset->$field;
            }
        }
        // Legacy field name in saved structures.
        if (!isset($merged->scheduling_required) && isset($preset->schedulingrequired)) {
            $merged->scheduling_required = $preset->schedulingrequired;
        }
        if (!isset($merged->auto_rescheduling) && isset($preset->autorescheduling)) {
            $merged->auto_rescheduling = $preset->autorescheduling;
        }
        return $merged;
    }

    /**
     * Validates values of interal structures, clamps scoring values to min/max
     *
     * @return null
     */
    public function validate() {
        // rules/warnings/scoring are accessed as objects below, but callers
        // (from_json, brand-hidden-field override, raw structure import) may
        // hand them in as arrays. Normalize once here so the loops below work
        // regardless of how the caller wrote the field.
        if (!is_object($this->rules)) {
            $this->rules = (object) (array) $this->rules;
        }
        if (!is_object($this->warnings)) {
            $this->warnings = (object) (array) $this->warnings;
        }
        if (!is_object($this->scoring)) {
            $this->scoring = (object) (array) $this->scoring;
        }

        $keys = array_keys(self::RULES);
        foreach ($this->rules as $key => $value) {
            if (!in_array($key, $keys)) {
                unset($this->rules->{$key});
            } else {
                $this->rules->{$key} = (bool) $this->rules->{$key};
            }
        }

        $keys = array_keys(self::WARNINGS);
        foreach ($this->warnings as $key => $value) {
            if (!in_array($key, $keys)) {
                unset($this->warnings->{$key});
            } else {
                $this->warnings->{$key} = (bool) $this->warnings->{$key};
            }
        }

        $keys = array_keys(self::SCORING);
        foreach ($this->scoring as $key => $value) {
            if (!in_array($key, $keys)) {
                unset($this->scoring->{$key});
            } else {
                $specs = self::SCORING[$key];
                if ($value !== null) {
                    $value = floatval($value);
                    $value = min($specs['max'], $value);
                    $value = max($specs['min'], $value);
                }

                $this->scoring->{$key} = $value;
            }
        }

        if (!in_array($this->calculator, self::CALCULATOR_OPTIONS)) {
            $this->calculator = 'off';
        }
        if (!in_array($this->streamspreset, self::STREAMS_PRESET_OPTIONS)) {
            $this->streamspreset = 'default';
        }
    }

    /**
     * Import from external communication
     * @param array $data Data array to be mapped to propeties
     * @return null
     */
    public function from_json($data) {
        foreach ($this::PROPS as $prop) {
            if (in_array($prop, ['rules'])) {
                continue;
            }
            if (isset($data[$prop])) {
                $this->{$prop} = $data[$prop];
            }
        }
        if (isset($data['allowtouseadditionalresources'])) {
            $this->allowtouseadditionalresources = (bool) $data['allowtouseadditionalresources'];
        }

        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                $key = $rule['key'];
                $value = $rule['value'];
                $this->rules->{$key} = $value;
            }
        }
        $this->validate();
    }

    /**
     * Export for external communication
     *
     * @return Array of properties of current condition
     */
    public function to_json() {
        foreach ($this::PROPS as $prop) {
            $result[$prop] = $this->{$prop};
        }

        foreach ($this::WARNINGS as $warn) {
            $result[$prop] = $this->{$prop};
        }

        if (empty($result['rules'])) {
            $result['rules'] = [];
        }

        $allowedprocesses = $result['allowedprocesses'];
        $allowedprocesses = is_string($allowedprocesses) ? trim($allowedprocesses) : '';
        $allowedprocesses = preg_split('/\R+/', $allowedprocesses);
        $allowedprocesses = array_filter($allowedprocesses);
        $result['allowedprocesses'] = empty($allowedprocesses) ? null : $allowedprocesses;

        $forbiddenprocesses = $result['forbiddenprocesses'];
        $forbiddenprocesses = is_string($forbiddenprocesses) ? trim($forbiddenprocesses) : '';
        $forbiddenprocesses = preg_split('/\R+/', $forbiddenprocesses);
        $forbiddenprocesses = array_filter($forbiddenprocesses);
        $result['forbiddenprocesses'] = empty($forbiddenprocesses) ? null : $forbiddenprocesses;

        return $result;
    }

    /**
     * has proctor condition
     *
     * @param \cm_info $cm Cm
     * @return bool
     */
    public static function has_proctor_condition($cm) {
        $conditions = self::get_conditions($cm);
        return !empty($conditions);
    }

    /**
     * get proctor conditions
     *
     * @param \cm_info $cm Cm
     * @return array
     */
    public static function get_proctor_condition($cm) {
        $conds = self::get_conditions($cm);
        return $conds && isset($conds[0]) ? $conds[0] : null;
    }

    /**
     * get proctor conditions
     *
     * @param \cm_info $cm Cm
     * @return array
     */
    private static function get_conditions($cm) {
        $info = new info_module($cm);
        try {
            $tree = $info->get_availability_tree();
            $tree = $tree->get_all_children('\\availability_proctor\\condition');
        } catch (moodle_exception $e) {
            return null;
        }

        return $tree;
    }

    /**
     * Export for moodle storage
     *
     * @return object
     */
    public function save() {
        $data = [
            'type' => 'proctor',
            'duration' => (int) $this->duration,
            'mode' => (string) $this->mode,
            'scheduling_required' => false,
            'auto_rescheduling' => false,
            'rules' => (array) $this->rules,
            'warnings' => (array) $this->warnings,
            'scoring' => (array) $this->scoring,
            'istrial' => (bool) $this->istrial,
            'identification' => $this->identification,
            'useragreementurl' => $this->useragreementurl,
            'auxiliarycamera' => (bool) $this->auxiliarycamera,
            'customrules' => $this->customrules,
            'calculator' => $this->calculator,
            'securebrowser' => $this->securebrowser,
            'securebrowserlevel' => $this->securebrowserlevel,
            'allowtouseadditionalresources' => (bool) $this->allowtouseadditionalresources,
            'allowedprocesses' => $this->allowedprocesses,
            'forbiddenprocesses' => $this->forbiddenprocesses,
            'streamspreset' => $this->streamspreset,
            'sendmanualwarningstolearner' => (bool) $this->sendmanualwarningstolearner,
            'allowroomscanauxcamera' => (bool) $this->allowroomscanauxcamera,
            'preliminarycheck' =>  (bool) $this->preliminarycheck,
        ];
        if (!empty($this->preset_id)) {
            $data['preset_id'] = (int) $this->preset_id;
        }
        return (object) $data;
    }

    /**
     * Initialize new entry, ready to write to DB
     * @param integer $courseid
     * @param integer $cmid
     * @param integer $userid
     * @return \stdClass entry
     */
    public static function make_entry($courseid, $cmid, $userid=null) {
        $timenow = time();
        $entry = new stdClass();
        $entry->courseid = $courseid;
        $entry->cmid = $cmid;
        $entry->accesscode = is_null($userid) ? '' : md5(uniqid(rand(), 1));
        $entry->status = is_null($userid) ? null : 'new';
        $entry->timecreated = $timenow;
        $entry->timemodified = $timenow;
        $entry->userid = $userid;

        return $entry;
    }

    /**
     * Check if condition is limited to groups, and if the user is part of those groups.
     *
     * @param \cm_info $cm Cm
     * @return int $userid userid
     */
    public function user_in_proctored_groups($userid) {
        global $DB;
        $groups = $this->groups;
        if (empty($groups)) {
            return true;
        }

        // Validate that groups are still there.
        [$insql, $inparams] = $DB->get_in_or_equal($groups);
        $groups = $DB->get_fieldset_select('groups', 'id', 'id ' . $insql, $inparams);
        if (empty($groups)) {
            return true;
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        $usergroups = $DB->get_records('groups_members', ['userid' => $user->id], null, 'groupid');

        foreach ($usergroups as $usergroup) {
            if (in_array($usergroup->groupid, $this->groups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * is available
     *
     * @param bool $not Not
     * @param \core_availability\info $info Info
     * @param string $grabthelot grabthelot
     * @param int $userid User id
     * @return bool
     */
    public function is_available($not,
            \core_availability\info $info, $grabthelot, $userid) {

        if ($this->user_in_proctored_groups($userid)) {
            $allow = !WS_SERVER;
        } else {
            $allow = true;
        }

        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    /**
     * get description
     *
     * @param string $full Full
     * @param bool $not True if NOT is in force
     * @param \core_availability\info $info Info
     * @return string
     */
    public function get_description($full, $not, \core_availability\info $info) {
        if (WS_SERVER) {
            return get_string('description_no_webservices', 'availability_proctor');
        } else {
            return get_string('description_proctor', 'availability_proctor',
                get_string('pluginname', 'availability_proctor'));
        }
    }

    /**
     * Get debug string
     * Implements abstract method `core_availability\condition::get_debug_string`
     *
     * @return string
     */
    protected function get_debug_string() {
        return '#proctoring ' . $this->mode;
    }

}
