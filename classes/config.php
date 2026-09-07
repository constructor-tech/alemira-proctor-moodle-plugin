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

defined('MOODLE_INTERNAL') || die();

/**
 * Site-level configuration for the plugin.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /**
     * @var string The student is redirected to a Constructor-hosted page, which
     * runs the proctoring session and sends them back to the quiz.
     *
     * The original model, and the only one before NTG-185. Every site upgrading
     * from an earlier release stays on it.
     */
    const MODEL_WEBAPP = 'webapp';

    /**
     * @var string The Constructor SDK runs on this site's own launch page, with
     * the quiz embedded in a same-origin iframe.
     *
     * Quiz only. Assign and SCORM always use the Webapp model.
     */
    const MODEL_SDK = 'sdk';

    /** @var array List of possible proctoring models */
    const MODEL_OPTIONS = [
        self::MODEL_WEBAPP, self::MODEL_SDK,
    ];

    /**
     * How long an activated SDK session may wait to be bound to an attempt.
     *
     * An active session with no attempt is a permission nobody has spent yet, so
     * it must not sit indefinitely.
     *
     * Not exposed as a site setting: this is internal tuning, not something an
     * institution needs to weigh in on. Ten minutes covers the SDK preparation
     * steps (~75 seconds) and a pre-flight exchange such as a quiz password,
     * with margin. See session::is_bind_expired().
     *
     * @var int Seconds.
     */
    const BIND_WINDOW = 10 * MINSECS;

    /**
     * How long to wait before logging the same blocked access attempt again.
     *
     * Not exposed as a site setting, for the same reason as BIND_WINDOW. One
     * minute keeps the denial log from filling up if a student holds down
     * refresh on a blocked page. See session::record_denial().
     *
     * @var int Seconds.
     */
    const DENIAL_WINDOW = MINSECS;

    /**
     * Which proctoring model this site uses.
     *
     * The single read point for the setting: callers must use this rather than
     * get_config(), because the two failure directions are not equivalent.
     * Falling back to Webapp gives a site the behaviour it already had, while
     * falling through to SDK would move live exams onto an integration nobody
     * chose. So anything that is not an exact known model reads as Webapp --
     * including false, which is what get_config() returns when the setting has
     * never been written.
     *
     * Note that a missing setting is not expected in practice: db/install.php
     * writes it for a clean install and db/upgrade.php writes it for an existing
     * site. This clamp is the third line of defence behind those two, covering a
     * partial upgrade or a hand-edited config row.
     *
     * @return string One of MODEL_OPTIONS.
     */
    public static function proctoring_model(): string {
        $model = get_config('availability_proctor', 'proctormodel');

        if (!is_string($model) || !in_array($model, self::MODEL_OPTIONS, true)) {
            return self::MODEL_WEBAPP;
        }

        return $model;
    }

    /**
     * The Moodle version the SDK proctoring model requires.
     *
     * 3.11, and it is the version the flow was *verified* on rather than the oldest
     * one the code might run on. Measured on 3.11.18: the launch page loads, the
     * quiz renders inside the same-origin iframe, the timer runs, and a re-attempt
     * opens a fresh session. The iframe design rests on Moodle serving
     * X-Frame-Options: sameorigin for this plugin's own pages, and 3.11 emits that
     * from the same lib/weblib.php code 4.5 does.
     *
     * Verifying it also found the one real incompatibility: before 4.2 the quiz
     * classes live in mod/quiz/attemptlib.php rather than under mod/quiz/classes/,
     * so Moodle's autoloader never finds them. utils::quiz_attempt_classname() and
     * quiz_settings_classname() now load that file themselves. Without it the exam
     * ran to the end and then stopped at "the exam is ending", because the redirect
     * to the review page is built from those classes.
     *
     * 3.8 to 3.10 are NOT covered by this floor: the plugin installs there and runs
     * the Webapp flow (see version.php), but nobody has put the SDK through an exam
     * on them. Lowering this further is a legitimate one-line change once someone
     * has. It should not be lowered on the strength of the code looking compatible
     * -- that reasoning is exactly what hid the attemptlib defect above.
     *
     * @var int
     */
    const SDK_MIN_VERSION = 2021051700;

    /**
     * Can the SDK proctoring model run on this Moodle?
     *
     * Independent of what the site has configured -- this answers only whether the
     * platform is new enough. See is_sdk_model() for the question callers usually
     * want.
     *
     * @return bool
     */
    public static function is_sdk_available(): bool {
        global $CFG;

        return isset($CFG->version) && $CFG->version >= self::SDK_MIN_VERSION;
    }

    /**
     * Is this site running the SDK proctoring model?
     *
     * The floor check lives here, in the one method every consumer already calls,
     * rather than at each call site. Nine places read the model and every one of
     * them routes behaviour -- the start-attempt redirect, the attempt binding, both
     * report readers, the settings section. Gating here means a site below the floor
     * behaves exactly as a Webapp site, with no half state, from one change; gating
     * at each call site would be nine chances to miss one, and the most damaging to
     * miss is utils.php's redirect, where the student would be sent to a launch page
     * that cannot work.
     *
     * Note that proctoring_model() deliberately does NOT apply the floor. It reports
     * what is stored, which is what the settings page must display and what an
     * administrator needs in order to understand why their chosen model is not in
     * effect. The floor answers "is the SDK running", not "what does the row say".
     *
     * @return bool
     */
    public static function is_sdk_model(): bool {
        return self::proctoring_model() === self::MODEL_SDK && self::is_sdk_available();
    }

    /**
     * Settings under the names the ported SDK code reads them by.
     *
     * The two plugins named the same settings differently. This plugin's
     * settings.php has shipped proctor_url, integration_name, jwt_secret,
     * account_id, account_name and user_emails to customers for years, so those
     * names stay; the ported files ask for serverorigin, integrationname,
     * jwtsecret, accountid, accountname and senduseremails. get() exposes both,
     * and neither side needs editing.
     *
     * Doing this as a map rather than renaming either side is the point: renaming
     * the stored settings would silently blank every existing customer's
     * credentials, and renaming the reads would mean touching every ported file --
     * including api_sdk.php, whose 503 "Not configured" answer depends on the
     * secret being found.
     *
     * Every alias a ported file reads MUST be listed here, and senduseremails is
     * why the rule is worth stating. It was missing, so payload::build() read
     * $config->senduseremails off an object that never carried it: the email claim
     * was silently never sent, whatever the administrator had chosen, and the
     * Proctor Dashboard showed a generated address instead of the learner's. It
     * did not fail loudly because an absent claim is a legitimate one -- the same
     * reason the Webapp flow, which reads the stored name directly in
     * client::__construct(), was unaffected and so gave no clue that the two
     * models disagreed.
     *
     * One shape difference this map does NOT resolve: proctor_url holds a host,
     * with https:// prepended at runtime, while the SDK code's serverorigin was a
     * full URL. get() passes the stored value through unchanged; converting it is
     * config::server_origin()'s job.
     *
     * @var array SDK name => stored setting name.
     */
    const SETTING_NAMES = [
        'serverorigin' => 'proctor_url',
        'integrationname' => 'integration_name',
        'jwtsecret' => 'jwt_secret',
        'accountid' => 'account_id',
        'accountname' => 'account_name',
        'senduseremails' => 'user_emails',
    ];

    /**
     * Every plugin setting, readable under either naming convention.
     *
     * Returns all stored settings under their own names, plus an alias for each
     * entry in SETTING_NAMES. So $config->jwt_secret and $config->jwtsecret are
     * the same value, and a Webapp file and a ported file can share one object.
     *
     * An absent setting is left absent rather than defaulted to the empty string:
     * callers distinguish "not configured" from "configured as empty", and
     * api_sdk.php answers 503 on the former.
     *
     * @return \stdClass All settings for this plugin.
     */
    public static function get(): \stdClass {
        $config = (object) (array) get_config('availability_proctor');

        foreach (self::SETTING_NAMES as $sdkname => $storedname) {
            if (isset($config->$storedname)) {
                $config->$sdkname = $config->$storedname;
            }
        }

        return $config;
    }

    /**
     * The credentials a session cannot be created without.
     *
     * Named on the SDK side of SETTING_NAMES rather than the stored side, because
     * these names are what the ported callers read off the object get() returns.
     * Using the stored names here would mean editing every one of those call sites
     * to close a gap that is only ever visible in a developer-only debug string.
     *
     * The consequence, accepted: token.php's debug message names serverorigin
     * while the settings page calls the same field Proctor URL. An administrator
     * with debugging on sees the internal name; nobody else sees it at all.
     *
     * @var array
     */
    const REQUIRED = ['serverorigin', 'integrationname', 'jwtsecret', 'accountid', 'accountname'];

    /**
     * The examRules claim, with the value each rule takes when the exam does not
     * state it.
     *
     * payload::rules() fills any rule the stored set omits from this list, so the
     * claim always states every rule. Without it the server would apply its own
     * defaults to the rules we left out and ours to the rest, which makes what a
     * student is actually permitted depend on which fields happened to be saved.
     *
     * allow_to_use_paper is the only entry defaulting to true. That is the value
     * the SDK flow was verified with, and exam policy is not this merge's business
     * to revisit.
     *
     * The warnings and scoring sets are deliberately NOT here. Measured:
     * payload.php reads neither -- the condition stores complete sets of both and
     * payload::decode_set() reads those. Adding them would create a second source
     * of truth for exam policy, which is the same reason the SDK's 28-column
     * per-quiz table was not ported.
     *
     * @var array
     */
    const RULES = [
        'allow_to_use_websites' => false,
        'allow_to_use_books' => false,
        'allow_to_use_paper' => true,
        'allow_to_use_messengers' => false,
        'allow_to_use_excel' => false,
        'allow_to_use_human_assistant' => false,
        'allow_absence_in_frame' => false,
        'allow_voices' => false,
        'allow_wrong_gaze_direction' => false,
    ];

    /**
     * The SDK init() parameters and their shipped-safe values.
     *
     * All sixteen values are configured only on presets. Selecting a preset copies
     * them into the activity condition as a hidden snapshot; runtime resolution
     * reads that snapshot and never follows the preset row or a site setting. This
     * constant is therefore only the complete fallback for old conditions and
     * damaged/missing preset values.
     *
     * @var array SDK parameter => typed wire default.
     */
    const SDK_CONFIG = [
        'allowCameraSelection' => false,
        'alwaysShowChat' => false,
        'beforeExamList' => false,
        'desktopCheckFirst' => false,
        // Empty on purpose, and that is not the same as omitting the parameter.
        // finalise_sdk_config() always sends this key, and the SDK's own default is
        // ['watchUploading', 'ping'], so an empty list overrides that default and
        // leaves every background check running. A deliberate departure from the
        // documented default: this is a proctoring plugin, so a check is switched off
        // only because an administrator decided to switch it off, on Constructor's
        // advice. Nothing stored is affected -- no released version shipped this
        // setting (see db/upgrade.php, savepoint 2026082000).
        'disabledWorkers' => [],
        'experiment_watchWindowSize' => false,
        'focusRespectsRules' => true,
        'fullScreenWatchFocusAlert' => true,
        'identificationOnboardingBullets' => [],
        // Milliseconds on the wire. The preset form takes seconds and
        // sdk_config() multiplies -- see ROOM_SCAN_SETTING.
        'observeWorkspace' => 15000,
        'openAgreementLinksInIframe' => false,
        'shouldEnableAudioNotification' => true,
        // No, against the SDK's own default of true. Submitting the quiz ends the
        // proctoring session on its own, so the panel's button is a second way to
        // stop rather than the only one, and a button that ends proctoring beside a
        // quiz still open is the more confusing of the two states to land a learner in.
        'showFinishExamButtonForSessionsWithoutProctor' => false,
        'showSyscheckSupportNew' => false,
        // No default in the Developer Guide: the SDK simply shows no link. An empty
        // string is therefore meaningfully different from any URL, and the key is
        // dropped from the payload rather than sent empty (see sdk_config()).
        'syscheckHelpURL' => '',
        'translationsOverride' => [],
    ];

    /**
     * The parameter whose form value is in seconds while the SDK takes milliseconds.
     *
     * Held as a constant rather than hardcoded at the conversion site so the two
     * places that need to know -- sdk_config() and preset validation --
     * cannot disagree about which field is scaled.
     *
     * The Developer Guide calls observeWorkspace the minimum display time for the
     * Room Scan screen, not a polling interval; the label and help text say so.
     *
     * @var string
     */
    const ROOM_SCAN_SETTING = 'observeWorkspace';

    /**
     * How many milliseconds one unit of the Room Scan form field represents.
     *
     * @var int
     */
    const ROOM_SCAN_SCALE = 1000;

    /** @var array SDK parameter => preset/condition snapshot property. */
    const SDK_FIELDS = [
        'allowCameraSelection' => 'sdkallowcameraselection',
        'alwaysShowChat' => 'sdkalwaysshowchat',
        'beforeExamList' => 'sdkbeforeexamlist',
        'desktopCheckFirst' => 'sdkdesktopcheckfirst',
        'disabledWorkers' => 'sdkdisabledworkers',
        'experiment_watchWindowSize' => 'sdkexperimentwatchwindowsize',
        'focusRespectsRules' => 'sdkfocusrespectsrules',
        'fullScreenWatchFocusAlert' => 'sdkfullscreenwatchfocusalert',
        'identificationOnboardingBullets' => 'sdkidentificationbullets',
        'observeWorkspace' => 'sdkobserveworkspace',
        'openAgreementLinksInIframe' => 'sdkopenagreementlinksiniframe',
        'shouldEnableAudioNotification' => 'sdkaudionotification',
        'showFinishExamButtonForSessionsWithoutProctor' => 'sdkshowfinishexambutton',
        'showSyscheckSupportNew' => 'sdkshowsyschecksupportnew',
        'syscheckHelpURL' => 'sdksyscheckhelpurl',
        'translationsOverride' => 'sdktranslationsoverride',
    ];

    /**
     * How the SDK fields are grouped and ordered on the preset form.
     *
     * The first group follows the learner's own path through the pre-exam check
     * rather than the alphabet, and the two fields a site should not touch without
     * advice from Constructor sit last.
     *
     * @var array Heading lang string identifier => ordered snapshot properties.
     */
    const SDK_GROUPS = [
        'settings_sdk_group_before' => [
            'sdkbeforeexamlist',
            'sdkdesktopcheckfirst',
            'sdkallowcameraselection',
            'sdkobserveworkspace',
            'sdkopenagreementlinksiniframe',
            'sdkshowsyschecksupportnew',
            'sdksyscheckhelpurl',
        ],
        'settings_sdk_group_during' => [
            'sdkalwaysshowchat',
            'sdkaudionotification',
            'sdkshowfinishexambutton',
        ],
        'settings_sdk_group_violations' => [
            'sdkfocusrespectsrules',
            'sdkfullscreenwatchfocusalert',
        ],
        'settings_sdk_group_wording' => [
            'sdkidentificationbullets',
            'sdktranslationsoverride',
        ],
        'settings_sdk_group_advanced' => [
            'sdkdisabledworkers',
            'sdkexperimentwatchwindowsize',
        ],
    ];

    /**
     * The lang string identifier for each SDK preset field.
     *
     * An explicit map rather than a name transformation. Deriving the string key by
     * inserting an underscore after "sdk" happens to work for these sixteen, but it
     * breaks silently the moment a column name contains the substring twice or a
     * label needs different wording from its column -- and a missing string surfaces
     * as a debugging notice on a teacher's form, not as a test failure.
     *
     * @var array Snapshot property => lang string identifier.
     */
    const SDK_FIELD_STRINGS = [
        'sdkallowcameraselection' => 'settings_sdk_allowcameraselection',
        'sdkalwaysshowchat' => 'settings_sdk_alwaysshowchat',
        'sdkbeforeexamlist' => 'settings_sdk_beforeexamlist',
        'sdkdesktopcheckfirst' => 'settings_sdk_desktopcheckfirst',
        'sdkdisabledworkers' => 'settings_sdk_disabledworkers',
        'sdkexperimentwatchwindowsize' => 'settings_sdk_experimentwatchwindowsize',
        'sdkfocusrespectsrules' => 'settings_sdk_focusrespectsrules',
        'sdkfullscreenwatchfocusalert' => 'settings_sdk_fullscreenwatchfocusalert',
        'sdkidentificationbullets' => 'settings_sdk_identificationbullets',
        'sdkobserveworkspace' => 'settings_sdk_observeworkspace',
        'sdkopenagreementlinksiniframe' => 'settings_sdk_openagreementlinksiniframe',
        'sdkaudionotification' => 'settings_sdk_audionotification',
        'sdkshowfinishexambutton' => 'settings_sdk_showfinishexambuttonforsessionswithoutproctor',
        'sdkshowsyschecksupportnew' => 'settings_sdk_showsyschecksupportnew',
        'sdksyscheckhelpurl' => 'settings_sdk_syscheckhelpurl',
        'sdktranslationsoverride' => 'settings_sdk_translationsoverride',
    ];

    /** @var string Stored representation of an enabled boolean parameter. */
    const SDK_YES = 'yes';

    /** @var string Stored representation of a disabled boolean parameter. */
    const SDK_NO = 'no';

    /**
     * The origin to load the SDK from, as an absolute origin with no trailing slash.
     *
     * Converts what this plugin stores into what the SDK code expects, and that
     * conversion is the reason this method exists rather than the callers reading
     * the setting. The stored proctor_url is a bare host -- PARAM_HOST, with
     * 'https://' prepended at each Webapp use site, see client::api_url() -- while
     * the ported code was written against a serverorigin holding a full URL.
     *
     * The trailing slash is stripped because Constructor matches registered
     * origins exactly, so an origin ending in / matches no entry in the allowlist.
     * This value reaches the browser as the serverOrigin the SDK is fetched from,
     * where that mismatch shows up as a blank page rather than as an error.
     *
     * An unset host returns the empty string rather than a bare 'https://', which
     * would read as configured to anything doing a truthiness check. Whether the
     * site is configured is is_configured()'s question alone.
     *
     * @param \stdClass|null $config Configuration to read, or null to load it.
     * @return string
     */
    public static function server_origin($config = null): string {
        $config = $config ?? self::get();

        $host = trim((string) ($config->serverorigin ?? ''));
        if ($host === '') {
            return '';
        }

        // Tolerate a stored value that already carries a scheme. The settings form
        // rejects one through PARAM_HOST, but this setting can also be written by
        // CLI, by an admin preset or by forced_plugin_settings, and prepending a
        // second scheme would produce a URL matching no registered origin.
        if (!preg_match('#^https?://#i', $host)) {
            $host = 'https://' . $host;
        }

        return rtrim($host, '/');
    }

    /**
     * Which required credentials this site has not set.
     *
     * Empty is treated as missing, not as a deliberate value: a blank integration
     * name or secret cannot produce a working session, so the two cases have the
     * same remedy and there is nothing to be gained by telling them apart.
     *
     * @param \stdClass|null $config Configuration to check, or null to load it.
     * @return array Names of the missing settings, in REQUIRED order.
     */
    public static function missing_settings($config = null): array {
        $config = $config ?? self::get();

        $missing = [];
        foreach (self::REQUIRED as $name) {
            if (empty($config->$name)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Is the plugin configured well enough to create a session?
     *
     * @param \stdClass|null $config Configuration to check, or null to load it.
     * @return bool
     */
    public static function is_configured($config = null): bool {
        return self::missing_settings($config) === [];
    }

    /**
     * The SDK init() configuration for one saved condition snapshot.
     *
     * Returns an object rather than an array on purpose. This is encoded as the
     * 'config' member of token.php's JSON response and spread into an object by
     * proctor.js, and an empty PHP array encodes as [] rather than {} -- which
     * would arrive as a list and break the spread. An object stays an object
     * however few members it has.
     *
     * Presets are copied into the condition when selected. Reading a preset row here
     * would make later preset edits retroactive, while reading site settings would
     * reintroduce a second configuration source. Missing or invalid snapshot values
     * therefore fall back per field to SDK_CONFIG.
     *
     * @param \stdClass|null $settings Condition-derived settings, or null for safe defaults.
     * @return \stdClass
     */
    public static function sdk_config($settings = null): \stdClass {
        $sdkconfig = self::SDK_CONFIG;

        foreach (self::SDK_FIELDS as $key => $prop) {
            if ($settings === null || !property_exists($settings, $prop)) {
                continue;
            }

            $value = $settings->$prop;
            $sdkconfig[$key] = self::scale_if_room_scan($key, self::cast_sdk_value($key, $value));
        }

        return self::finalise_sdk_config($sdkconfig);
    }

    /**
     * Complete safe values in their preset/condition storage representation.
     *
     * @return array Snapshot property => stored value.
     */
    public static function sdk_snapshot_defaults(): array {
        $defaults = [];

        foreach (self::SDK_FIELDS as $key => $prop) {
            $value = self::SDK_CONFIG[$key];
            if (is_bool($value)) {
                $defaults[$prop] = $value ? self::SDK_YES : self::SDK_NO;
            } else if ($key === self::ROOM_SCAN_SETTING) {
                $defaults[$prop] = (string) (int) ($value / self::ROOM_SCAN_SCALE);
            } else if (is_array($value)) {
                // All three are optional. Blank keeps seeded preset forms clean and
                // resolves to the complete safe empty array on the wire.
                $defaults[$prop] = '';
            } else {
                $defaults[$prop] = (string) $value;
            }
        }

        return $defaults;
    }

    /**
     * Validate one stored SDK snapshot value without coercing invalid input.
     *
     * @param string $key SDK parameter name.
     * @param mixed $value Stored preset/condition value.
     * @return bool
     */
    public static function is_valid_sdk_snapshot_value(string $key, $value): bool {
        if (!array_key_exists($key, self::SDK_CONFIG)) {
            return false;
        }

        $default = self::SDK_CONFIG[$key];
        if (is_bool($default)) {
            return $value === self::SDK_YES || $value === self::SDK_NO;
        }

        if ($key === self::ROOM_SCAN_SETTING) {
            return is_int($value) ? $value >= 0 : ctype_digit((string) $value);
        }

        if ($key === 'syscheckHelpURL') {
            $value = trim((string) $value);
            return $value === '' || (preg_match('~^https?://~i', $value)
                && filter_var($value, FILTER_VALIDATE_URL) !== false);
        }

        if ($key === 'identificationOnboardingBullets' || $key === 'translationsOverride') {
            if (is_array($value)) {
                return true;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return true;
            }
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
        }

        // disabledWorkers is intentionally a line-based textarea, including the
        // empty string. No worker names are invented or parsed as JSON.
        if ($key === 'disabledWorkers') {
            return is_string($value) || is_array($value);
        }

        return is_scalar($value) || $value === null;
    }

    /**
     * Convert the Room Scan value from the seconds every form takes to the
     * milliseconds the SDK expects.
     *
     * Zero survives and keeps its meaning: the Developer Guide defines
     * observeWorkspace 0 as "hide the Room Scan screen", so it is a choice rather
     * than an absence.
     *
     * @param string $key The SDK parameter name.
     * @param mixed $value The cast value.
     * @return mixed
     */
    protected static function scale_if_room_scan(string $key, $value) {
        if ($key === self::ROOM_SCAN_SETTING) {
            return ((int) $value) * self::ROOM_SCAN_SCALE;
        }

        return $value;
    }

    /**
     * Last pass over a resolved parameter set before it goes to the browser.
     *
     * @param array $sdkconfig Resolved parameters.
     * @return \stdClass
     */
    protected static function finalise_sdk_config(array $sdkconfig): \stdClass {
        // A blank support URL is dropped rather than sent empty: the SDK has no
        // default for it and an empty string is not a URL.
        if (($sdkconfig['syscheckHelpURL'] ?? '') === '') {
            unset($sdkconfig['syscheckHelpURL']);
        }

        // Returned as an object, not an array: this is json_encoded as the 'config'
        // member of token.php's response and spread into an object by proctor.js, and
        // an empty PHP array encodes as [] rather than {}.
        return (object) $sdkconfig;
    }

    /**
     * Coerce a stored value to the type its SDK parameter expects.
     *
     * Driven by the type of the shipped default, so a parameter added to SDK_CONFIG
     * is handled without touching this method.
     *
     * The bool branch is the one that matters: settings and form fields are stored
     * as strings, "0" is truthy in PHP, and the three-state fields store the words
     * yes and no -- so a naive cast would send a non-empty string where the SDK
     * expects false, and the parameter would appear to be ignored.
     *
     * @param string $key The SDK parameter name.
     * @param mixed $value The stored value.
     * @return mixed
     */
    protected static function cast_sdk_value(string $key, $value) {
        $default = self::SDK_CONFIG[$key] ?? null;

        if (is_bool($default)) {
            if ($value === self::SDK_YES) {
                return true;
            }
            if ($value === self::SDK_NO) {
                return false;
            }
            return $default;
        }

        if (is_int($default)) {
            return self::is_valid_sdk_snapshot_value($key, $value) ? (int) $value : $default;
        }

        if (is_array($default)) {
            if (is_array($value)) {
                return $value;
            }

            $value = (string) $value;

            // This field is deliberately one worker name per line. Do not infer
            // JSON from its contents: a support-provided worker name beginning with
            // '[' or '{' must remain one literal row rather than changing formats.
            if ($key === 'disabledWorkers') {
                return self::split_lines($value);
            }

            // The other two array parameters are support-provided JSON.
            $trimmed = ltrim($value);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $decoded = json_decode($value, true);
                return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : $default;
            }

            return $trimmed === '' ? [] : $default;
        }

        if ($key === 'syscheckHelpURL' && !self::is_valid_sdk_snapshot_value($key, $value)) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Property names payload::build() reads off a settings object, where the
     * condition calls them something else.
     *
     * The SDK code was written against a per-quiz settings table with its own
     * column names. That table is not part of this plugin -- exam settings live in
     * the condition -- so get_quiz_settings() adapts one to the other.
     *
     * Measured: of the 24 properties payload::build() reads, 23 already carry the
     * same name on the condition, so this map holds the single exception rather
     * than a full translation table. Kept as a map anyway, so a second divergence
     * has an obvious place to go instead of being hardcoded into the adapter.
     *
     * @var array Settings property name => condition property name.
     */
    const SETTINGS_ALIASES = [
        'proctoringmode' => 'mode',
    ];

    /**
     * Is this quiz proctored under the SDK model?
     *
     * Two questions in one, and both have to be yes: is this site running the SDK
     * model at all, and does this quiz carry a proctoring condition.
     *
     * The site-model check belongs here rather than at each call site because
     * launch.php is a URL a student can be handed. Without it, someone on a Webapp
     * site who is given a launch.php?cmid=... link would be shown an SDK launch
     * page for a quiz that is actually running the Webapp flow. One check in the
     * shared helper closes that for every caller at once.
     *
     * Takes a quiz instance id rather than a cm id because that is what its
     * callers have; the cm is resolved here.
     *
     * @param int $quizid Quiz instance id.
     * @return bool
     */
    public static function is_enabled_for_quiz(int $quizid): bool {
        return self::get_quiz_settings($quizid) !== null;
    }

    /**
     * The exam settings for a quiz, as the SDK payload builder expects them.
     *
     * An adapter over the availability condition, not a second source of truth.
     * condition::__construct has already applied preset defaults and validate()
     * has already clamped every enumerated value -- including the four sdk*
     * overrides, which it did not until the claim in this docblock was checked --
     * so this copies what it finds and does not re-derive anything.
     *
     * Returns null whenever the quiz is not proctored under the SDK model, which
     * is how both callers detect that case -- so a Webapp site returns null here
     * for every quiz, including quizzes that do carry a proctoring condition.
     *
     * @param int $quizid Quiz instance id.
     * @return \stdClass|null Settings object, or null when not applicable.
     */
    public static function get_quiz_settings(int $quizid) {
        if (!self::is_sdk_model()) {
            return null;
        }

        $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        // Read through modinfo rather than the course_modules row: the condition
        // is parsed out of the availability JSON, and get_fast_modinfo() is what
        // holds the parsed form. Going to the raw column would mean parsing it a
        // second way.
        try {
            $cminfo = get_fast_modinfo($cm->course)->get_cm($cm->id);
        } catch (\moodle_exception $e) {
            return null;
        }

        $condition = condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return null;
        }

        return self::settings_from_condition($condition);
    }

    /**
     * Copy a condition's exam settings onto a plain object under the names the
     * SDK payload builder uses.
     *
     * Every property in condition::PROPS is copied, not just the ones payload.php
     * happens to read today. Copying the whole set means a claim added to the
     * payload later finds its value already present, rather than reading null off
     * an object that silently never carried it -- a failure that would surface as
     * a missing JWT claim at exam time and nowhere earlier.
     *
     * @param condition $condition The condition to read.
     * @return \stdClass
     */
    protected static function settings_from_condition(condition $condition): \stdClass {
        $settings = new \stdClass();

        foreach (condition::PROPS as $prop) {
            $settings->$prop = $condition->$prop ?? null;
        }

        foreach (self::SETTINGS_ALIASES as $target => $source) {
            $settings->$target = $condition->$source ?? null;
        }

        return $settings;
    }

    /**
     * One trimmed, non-empty entry per line of a textarea.
     *
     * Used for the claims the Developer Guide takes as JSON lists -- proctor
     * addresses and the Secure Browser process lists -- which are entered one per
     * line because that is far easier to read and edit than JSON in a textarea.
     *
     * It lives on this class, which has no other reason to hold a string helper,
     * because payload::build() needs it and must be able to reach it from
     * anywhere. In the plugin this was ported from the same helper sat on the
     * quiz access rule, and calling it from payload.php was a fatal
     * "class not found": Moodle's autoloader only covers classes/, so a rule
     * class at the plugin root is loaded only when core itself asks for the
     * access rule. That failure surfaced the first time a live-proctor session
     * was started. Keeping it here means there is no such load order to get
     * wrong.
     *
     * @param string $text Raw textarea contents.
     * @return array
     */
    public static function split_lines(string $text): array {
        $lines = preg_split('/\R/', $text);

        return array_values(array_filter(array_map('trim', $lines), function ($line) {
            return $line !== '';
        }));
    }
}
