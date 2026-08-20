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
        'proctoremails',
        // NTG-185: the six SDK parameters an exam may override. Three-state: '' takes
        // the preset's value, 'yes'/'no' override it (Room Scan carries its number
        // directly). config::SDK_PER_QUIZ is the authoritative list -- these names
        // must match its values.
        //
        // The other ten are site-wide; see config::SDK_SITE_ONLY.
        //
        // Deliberately NOT added to PRESET_FIELDS. That list is the escape hatch from
        // the brand override below: a field in both HIDDEN_FORM_FIELDS and
        // PRESET_FIELDS is force-taken from the default preset, ignoring what the
        // teacher saved.
        'sdkallowcameraselection', 'sdkalwaysshowchat', 'sdkfocusrespectsrules',
        'sdkfullscreenwatchfocusalert', 'sdkobserveworkspace', 'sdkshowfinishexambutton',
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
        'mic_muted' => ['min' => 0, 'max' => 10, 'default' => null],
        'mic_no_device' => ['min' => 0, 'max' => 10, 'default' => null],
        'mic_no_sound' => ['min' => 0, 'max' => 10, 'default' => null],
        'camera_no_device' => ['min' => 0, 'max' => 10, 'default' => null],
        'camera_no_picture' => ['min' => 0, 'max' => 10, 'default' => null],
        'no_aux_camera_photo' => ['min' => 0, 'max' => 10, 'default' => null],
        'no_ping' => ['min' => 0, 'max' => 10, 'default' => null],
        'desktop_request_pending' => ['min' => 0, 'max' => 10, 'default' => null],
        'account_collision' => ['min' => 0, 'max' => 10, 'default' => null],
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

    /** @var array List of possible main camera positions */
    const WEBCAMERA_MAIN_VIEW_OPTIONS = [
        'front', 'side',
    ];

    /** @var array List of possible Secure Browser restriction levels */
    const SECURE_BROWSER_LEVELS = [
        'basic', 'medium', 'high',
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

    /** @var array|null Emails of proctors assigned to this exam (Live review mode only) */
    public $proctoremails = null;

    /** @var string Stream settings preset */
    public $streamspreset = null;

    /** @var bool Send manual warnings to learner */
    public $sendmanualwarningstolearner = true;

    /** @var bool Allow room scan using aux camera */
    public $allowroomscanauxcamera = false;

    /*
     * NTG-185: the SDK init() parameters this exam may override.
     *
     * Every one initialises to config::SDK_INHERIT (the empty string), and that is
     * the guarantee the upgrade rests on: a quiz saved by an earlier release carries
     * none of these keys, __construct therefore never assigns them, and the exam
     * keeps taking the site setting -- which is exactly what it did before this
     * release existed.
     *
     * The booleans store 'yes' or 'no' rather than true/false because a two-valued
     * field cannot express "inherit". config::is_sdk_override() is what reads them.
     */

    /** @var string Let the learner pick a camera: '', 'yes' or 'no'. */
    public $sdkallowcameraselection = '';

    /** @var string Keep the chat panel open: '', 'yes' or 'no'. */
    public $sdkalwaysshowchat = '';




    /** @var string Focus detection respects exam rules: '', 'yes' or 'no'. */
    public $sdkfocusrespectsrules = '';

    /** @var string Full-screen violation alerts: '', 'yes' or 'no'. */
    public $sdkfullscreenwatchfocusalert = '';

    /**
     * @var string Room Scan minimum display time, in SECONDS, or '' to inherit.
     *
     * Seconds here and in the forms; config::sdk_config() multiplies by
     * ROOM_SCAN_SCALE for the wire, where the SDK expects milliseconds. '0' is a
     * real choice meaning "hide the Room Scan screen", so it must not be confused
     * with '' -- which is why this is a string rather than an int.
     */
    public $sdkobserveworkspace = '';



    /** @var string Finish exam button without a proctor: '', 'yes' or 'no'. */
    public $sdkshowfinishexambutton = '';



    /**
     * @var int|null Linked preset id (when the condition is preset-backed).
     *
     * The property is presetid; the key it is stored under in the availability
     * JSON is still preset_id. Those cannot be brought into line: the stored key
     * is read by yui/src/form/js/form.js and matched by preset::is_used_by_exams()
     * with a LIKE on '"preset_id":', so renaming it would orphan every saved exam.
     */
    public $presetid = null;

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
                $this->presetid = (int) $structure->preset_id;
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
            $this->$key = isset($structure->$key) ? (bool) $structure->$key : $default;
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

        if (!empty($structure->proctoremails)) {
            $this->proctoremails = $structure->proctoremails;
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

        // NTG-185: the SDK overrides, read as one loop rather than thirteen blocks so
        // that a parameter added to config::SDK_PER_QUIZ needs no edit here.
        //
        // Compared against '' rather than tested with !empty(): these parameters are
        // three-state, so '0' means "No" and is as much a teacher's choice as '1'.
        // !empty() rejects '0', which silently turned every "No" back into inherit --
        // measured 2026-08-18 for five of the six, the sixth escaping only because it
        // had a special case of its own below. save() stores '0' correctly, so the
        // loss was invisible in the stored JSON and surfaced only at exam time.
        //
        // An absent key and an explicit '' must both leave the property at its
        // initialiser, which is what "inherit" is.
        foreach (config::SDK_PER_QUIZ as $prop) {
            if (isset($structure->$prop) && (string) $structure->$prop !== '') {
                $this->$prop = (string) $structure->$prop;
            }
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
                } else if (array_key_exists($key, self::BOOL_DEFAULTS)) {
                    $value = (bool) $value;
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
        // These two were not clamped while the two above were. __construct takes
        // whatever !empty() lets through, so a value that did not come from the
        // form -- a restored preset, a direct SQL edit, a future brand adding an
        // option -- reached the report page and the API payload unchecked. Each
        // falls back to its own property default, so an unrecognised value
        // behaves as if it had never been set.
        if (!in_array($this->webcameramainview, self::WEBCAMERA_MAIN_VIEW_OPTIONS, true)) {
            $this->webcameramainview = 'front';
        }
        if (!in_array($this->securebrowserlevel, self::SECURE_BROWSER_LEVELS, true)) {
            $this->securebrowserlevel = 'basic';
        }

        // NTG-185: the six SDK overrides, clamped for the same reason as the two
        // above -- __construct takes whatever the structure hands it, so a value
        // that did not come from the form (a structure built in code, a direct SQL
        // edit, an option a later release removes) reached config::sdk_config()
        // unchecked.
        //
        // Unrecognised falls back to SDK_INHERIT, never to SDK_NO, and that
        // direction is the whole point. is_sdk_override() counts any non-empty
        // string as a deliberate override, and cast_sdk_value() then reduces it
        // with (bool) (int) -- so 'maybe' reached the browser as false, silently
        // switching the parameter off instead of deferring to the site setting.
        // Two of the six are focusRespectsRules and fullScreenWatchFocusAlert, so
        // being wrong in that direction means violation alerts off.
        //
        // Driven off the type of the shipped default, exactly as
        // config::cast_sdk_value() is, so a parameter added to SDK_PER_QUIZ is
        // covered without editing this.
        foreach (config::SDK_PER_QUIZ as $key => $prop) {
            $value = (string) ($this->$prop ?? '');

            if ($value === config::SDK_INHERIT) {
                continue;
            }

            if (is_bool(config::SDK_CONFIG[$key] ?? null)) {
                if ($value !== config::SDK_YES && $value !== config::SDK_NO) {
                    $this->$prop = config::SDK_INHERIT;
                }
                continue;
            }

            // Room Scan, the one numeric parameter: whole seconds. ctype_digit
            // accepts '0', which is a real choice meaning "hide the Room Scan
            // screen", and rejects a negative or fractional value, which is not a
            // duration.
            if (!ctype_digit($value)) {
                $this->$prop = config::SDK_INHERIT;
            }
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

        // Demo exam mode must never connect to a live proctor. Override the
        // stored mode to offline so the proctoremails suppression below and
        // any other mode-dependent logic automatically applies.
        if (!empty($result['istrial'])) {
            $result['mode'] = 'offline';
        }

        // Proctor emails: one address per line, normalized to a list of strings.
        // Only meaningful in Live (online) review mode; cleared otherwise so the
        // API payload never carries assigned proctors for non-live exams.
        $proctoremails = $result['proctoremails'];
        $proctoremails = is_string($proctoremails) ? trim($proctoremails) : '';
        $proctoremails = preg_split('/\R+/', $proctoremails);
        $proctoremails = array_filter(array_map('trim', $proctoremails));
        $result['proctoremails'] = (empty($proctoremails) || $result['mode'] !== 'online')
            ? null : array_values($proctoremails);

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
     * Whatever this method returns IS the stored availability JSON: core's
     * availability tree rebuilds the column from each condition's save() with no
     * merge against the previous value (see core_availability\tree::save()). So a
     * property omitted here is not merely left alone, it is destroyed the next
     * time anyone edits the activity.
     *
     * No property in PROPS is omitted any more, and keeping it that way is the
     * whole maintenance rule for this method: adding a field means touching PROPS,
     * the public property, __construct AND this method, or the field is destroyed
     * on the first activity edit after a teacher sets it. Five fields have been
     * lost that way already -- 'groups', 'webcameramainview' and the three
     * booleans annotated below -- each silent until a customer noticed the setting
     * did nothing.
     *
     * Five repairs, and each one is a behaviour change on an upgraded site rather
     * than a no-op, so they are written up in CHANGELOG.md rather than only here.
     * What they have in common: the stored JSON never carried the field, so the
     * effective value came from the property default or the preset. Now the
     * teacher's own choice is stored and wins. Nothing is retroactive -- an exam
     * saved by an earlier release still carries no value and behaves exactly as it
     * did until someone re-saves the activity.
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
            'proctoremails' => $this->proctoremails,
            'calculator' => $this->calculator,
            'securebrowser' => $this->securebrowser,
            'securebrowserlevel' => $this->securebrowserlevel,
            'allowtouseadditionalresources' => (bool) $this->allowtouseadditionalresources,
            // Added in NTG-185. These three were in PROPS and read by
            // client::exam_data() but never written here, so the hosted flow has
            // been sending the property default rather than the teacher's choice.
            // SDK mode reads them too, so leaving them out would ship three
            // settings that silently do nothing.
            'allowmultipledisplays' => (bool) $this->allowmultipledisplays,
            'allowvirtualenvironment' => (bool) $this->allowvirtualenvironment,
            'checkidphotoquality' => (bool) $this->checkidphotoquality,
            'allowedprocesses' => $this->allowedprocesses,
            'forbiddenprocesses' => $this->forbiddenprocesses,
            'streamspreset' => $this->streamspreset,
            'sendmanualwarningstolearner' => (bool) $this->sendmanualwarningstolearner,
            'allowroomscanauxcamera' => (bool) $this->allowroomscanauxcamera,
            'preliminarycheck' => (bool) $this->preliminarycheck,
            // Both were in PROPS, collected by the form and read by __construct,
            // but never written here -- so core rebuilt the stored JSON without
            // them and the teacher's choice was destroyed on every activity edit.
            //
            // groups is the one that changes who sits a proctored exam:
            // user_in_proctored_groups() treats an empty list as "everyone", so
            // losing it silently widened each restriction to the whole course.
            // Restrictions saved before this fix already hold an empty list --
            // this stops further loss, it cannot recover what was already dropped.
            'groups' => array_values((array) $this->groups),
            'webcameramainview' => $this->webcameramainview,
        ];

        // NTG-185: the SDK overrides. This block is the one that matters --
        // core_availability\tree::save() rebuilds the stored JSON exclusively from
        // what this method returns, with no merge against what was there before, so
        // any key omitted here is destroyed on every activity edit. That has already
        // happened four times in this plugin (see the note on allowmultipledisplays
        // above), which is why these are written from config::SDK_PER_QUIZ rather
        // than listed by hand: the loop cannot fall out of step with the map.
        //
        // The form now offers Yes/No only, with the preset's value preselected, so a
        // saved exam always states a value and it is written unconditionally. An
        // empty property is still skipped rather than stored: that is what a
        // structure built in code (or by an older build) looks like, and writing ''
        // would turn "say nothing, take the preset" into a stored non-answer that
        // config::is_sdk_override() would still have to special-case.
        foreach (config::SDK_PER_QUIZ as $prop) {
            $value = (string) ($this->$prop ?? '');
            if ($value !== '') {
                $data[$prop] = $value;
            }
        }
        if (!empty($this->presetid)) {
            $data['preset_id'] = (int) $this->presetid;
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
    public static function make_entry($courseid, $cmid, $userid = null) {
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
    public function is_available(
        $not,
        \core_availability\info $info,
        $grabthelot,
        $userid
    ) {

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
            return get_string(
                'description_proctor',
                'availability_proctor',
                get_string('pluginname', 'availability_proctor')
            );
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
