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
use quiz;
use stdClass;
use availability_proctor\state;

/**
 * Proctor by Constructor condition
 */
class condition extends \core_availability\condition {
    /** @var array List of (de-)serializable properties */
    const PROPS = [
        'duration', 'mode', 'schedulingrequired', 'autorescheduling',
        'istrial', 'identification', 'useragreementurl',
        'securebrowser', 'securebrowserlevel',
        'allowmultipledisplays', 'allowvirtualenvironment',
        'checkidphotoquality', 'webcameramainview',
        'scoring', 'warnings', 'rules', 'customrules', 'groups',
        'biometryenabled', 'biometryskipfail', 'biometryflow', 'biometrytheme',
        'calculator', 'auxiliarycamera', 'auxiliarycameramode',
        'forbiddenprocesses', 'allowedprocesses',
    ];

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
        'auxiliarycamera' => false,
        'allowmultipledisplays' => false,
        'allowvirtualenvironment' => false,
        'checkidphotoquality' => false,
        'biometryenabled' => false,
        'biometryskipfail' => false,
    ];

    /** @var array List of possible calculator options */
    const CALCULATOR_OPTIONS = [
        'off', 'scientific', 'simple',
    ];

    /** @var array List of possible aux camera options */
    const AUX_CAMERA_MODES = [
        'photo', 'video',
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

    /** @var string Auxiliary camera mode */
    public $auxiliarycameramode = 'video';

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

    /** @var calculator */
    public $calculator = 'off';

    /** @var bool Biometric identification enabled */
    public $biometryenabled = false;

    /** @var bool Biometric identification skips failures */
    public $biometryskipfail = false;

    /** @var string Biometric flow name*/
    public $biometryflow = null;

    /** @var string Biometric identification screen theme name */
    public $biometrytheme = null;

    /** @var string List of custom rules */
    public $customrules = null;

    /** @var array List of allowed processes */
    public $allowedprocesses = null;

    /** @var array List of forbidden processes */
    public $forbiddenprocesses = null;

    /** @var array Apply condition to specified groups */
    public $groups = [];

    /**
     * Construct
     *
     * @param stdClass $structure Structure
     */
    public function __construct($structure) {
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
        if (!isset($structure->auto_rescheduling)) {
            $this->autorescheduling = $structure->auto_rescheduling;
        }

        foreach (self::BOOL_DEFAULTS as $key => $default) {
            $this->$key = isset($structure->$key) ? $structure->$key : $default;
        }

        if (!empty($structure->warnings)) {
            $warnings = array_merge(self::WARNINGS, (array)$structure->warnings);
            $this->warnings = (object)$warnings;
        } else {
            $this->warnings = (object)self::WARNINGS;
        }

        if (!empty($structure->rules)) {
            $rules = array_merge(self::RULES, (array)$structure->rules);
            $this->rules = $structure->rules;
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

        if (!empty($structure->biometryflow)) {
            $this->biometryflow = $structure->biometryflow;
        }

        if (!empty($structure->biometrytheme)) {
            $this->biometrytheme = $structure->biometrytheme;
        }

        if (!empty($structure->groups)) {
            $this->groups = $structure->groups;
        }

        if (!empty($structure->auxiliarycameramode)) {
            $this->auxiliarycameramode = $structure->auxiliarycameramode;
        }

        if (!empty($structure->allowedprocesses)) {
            $this->allowedprocesses = $structure->allowedprocesses;
        }

        if (!empty($structure->forbiddenprocesses)) {
            $this->forbiddenprocesses = $structure->forbiddenprocesses;
        }

        $this->validate();
    }

    /**
     * Validates values of interal structures, clamps scoring values to min/max
     *
     * @return null
     */
    public function validate() {
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
        $econds = self::get_conditions($cm);
        return (bool) $econds;
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
        return (object) [
            'type' => 'proctor',
            'duration' => (int) $this->duration,
            'mode' => (string) $this->mode,
            'scheduling_required' => (bool) $this->schedulingrequired,
            'auto_rescheduling' => (bool) $this->autorescheduling,
            'rules' => (array) $this->rules,
            'warnings' => (array) $this->warnings,
            'scoring' => (array) $this->scoring,
            'istrial' => (bool) $this->istrial,
            'identification' => $this->identification,
            'useragreementurl' => $this->useragreementurl,
            'auxiliarycamera' => (bool) $this->auxiliarycamera,
            'auxiliarycameramode' => (string) $this->auxiliarycameramode,
            'customrules' => $this->customrules,
            'calculator' => $this->calculator,
            'securebrowser' => $this->securebrowser,
            'securebrowserlevel' => $this->securebrowserlevel,
            'allowedprocesses' => $this->allowedprocesses,
            'forbiddenprocesses' => $this->forbiddenprocesses,
        ];
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
     * Check if condition is limiteted to groups, and is user is part
     * of these groups.
     * There is possibility to make this method private and move it
     * to has_examus_condition, or maybe something else.
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
            return get_string('description_proctor', 'availability_proctor');
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
