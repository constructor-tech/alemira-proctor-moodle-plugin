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
 * Builds the session JWT payload from Moodle data.
 *
 * Every claim the Developer Guide marks mandatory is included: userId,
 * accountId, accountName, examId, examName, duration, proctoring, startDate,
 * endDate, sessionId and sessionUrl. Claim names are case-sensitive and have no
 * aliases, so they are written exactly as documented.
 *
 * Where Moodle cannot supply a mandatory claim, this class throws rather than
 * substituting a value. A silently invented duration or start date would
 * produce a session that looks configured but does not match the exam, and the
 * failure would surface much later as a confusing proctoring result.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payload {
    /**
     * Maximum lengths the Developer Guide specifies, for the fields where
     * Moodle can realistically exceed them.
     *
     * Truncating is the right behaviour here, unlike with the mandatory claims
     * above: a course name longer than 450 characters is a cosmetic problem,
     * not a misconfigured exam.
     */
    const MAXLEN = [
        'firstName' => 255,
        'lastName' => 255,
        'examName' => 500,
        'courseName' => 450,
        'sessionUrl' => 2550,
        'reviewUrl' => 600,
    ];

    /**
     * Build the payload for one proctoring session.
     *
     * Takes no attempt: the token is signed before the attempt exists, because the
     * student is handed off to core's startattempt.php to create it. That is why
     * sessionUrl points at the quiz rather than at a specific attempt.
     *
     * @param \stdClass $quiz Row from quiz.
     * @param \stdClass $course Row from course.
     * @param \stdClass $user The student.
     * @param \stdClass $settings Row from availability_proctor.
     * @param \stdClass $session Row from availability_proctor_sess.
     * @param \stdClass $config Plugin configuration.
     * @param \stdClass $cm The quiz course module.
     * @return array The claims, ready to sign.
     * @throws \moodle_exception When a mandatory claim cannot be built.
     */
    public static function build(
        \stdClass $quiz,
        \stdClass $course,
        \stdClass $user,
        \stdClass $settings,
        \stdClass $session,
        \stdClass $config,
        \stdClass $cm
    ): array {

        $duration = self::duration_minutes($quiz);
        // PHP 7.0 requires long list syntax.
        // phpcs:disable Universal.Lists.DisallowLongListSyntax.Found
        list($startdate, $enddate) = self::exam_window($quiz, $duration);

        $payload = [
            // User. Only userId is mandatory; the names are sent so that the
            // proctor and reviewer see a person rather than an id.
            'userId' => (string) $user->id,
            'firstName' => self::clip($user->firstname, 'firstName'),
            'lastName' => self::clip($user->lastname, 'lastName'),
            'language' => self::language_code($user),

            // Organisation.
            'accountId' => (string) $config->accountid,
            'accountName' => (string) $config->accountname,

            // Exam.
            'examId' => (string) $quiz->id,
            'examName' => self::clip($quiz->name, 'examName'),
            'courseName' => self::clip($course->fullname, 'courseName'),
            'duration' => $duration,
            'proctoring' => $settings->proctoringmode,
            'identification' => $settings->identification,
            'startDate' => $startdate,
            'endDate' => $enddate,

            // Scheduling is out of scope, so the student must never be asked to
            // book a slot before starting.
            'schedule' => false,

            // Session.
            'sessionId' => $session->sessionkey,
            'sessionUrl' => self::clip(
                self::session_url_for_settings($cm, $session, $user, $settings),
                'sessionUrl'
            ),

            // Where the proctoring result is delivered. Sent per session so the
            // result reaches this site even when several share one integration, and
            // so that a site moving domain does not need the review_url on the
            // Constructor side changed to match.
            'reviewUrl' => self::clip(self::review_url(), 'reviewUrl'),

            // Camera and monitoring.
            'webCameraMainView' => $settings->webcameramainview,
            'streamsPreset' => $settings->streamspreset,
            'auxiliaryCamera' => (bool) $settings->auxiliarycamera,
            'allowRoomScanAuxCamera' => (bool) $settings->allowroomscanauxcamera,
            'allowMultipleDisplays' => (bool) $settings->allowmultipledisplays,
            'allowVirtualEnvironment' => (bool) $settings->allowvirtualenvironment,

            // Identity.
            'checkIdPhotoQuality' => (bool) $settings->checkidphotoquality,

            // Conduct.
            'calculator' => $settings->calculator,
            'trial' => (bool) $settings->istrial,
            'sendManualWarningsToLearner' => (bool) $settings->sendmanualwarningstolearner,
            'rules' => self::rules($settings),
            'visibleWarnings' => self::decode_set($settings->warnings),

            // Environment.
            //
            // Secure Browser is passed through because the settings form exposes it,
            // but note the tension: this integration exists so that proctoring works
            // without a desktop application, and the same-origin iframe design has
            // not been tested with it switched on.
            'secureBrowser' => (bool) $settings->securebrowser,
        ];

        // Optional claims are added only when they carry a value. Sending an empty
        // string for a URL, or an empty list for the process rules, does not mean
        // "unset" to the server: an empty desktopAppForbiddenProcesses replaces the
        // server's preset list with nothing at all.
        if (!empty($settings->useragreementurl)) {
            $payload['userAgreementUrl'] = $settings->useragreementurl;
        }

        $scoring = self::decode_set($settings->scoring);
        if ($scoring) {
            $payload['scoreConfig'] = $scoring;
        }

        // Live-proctor settings are meaningless in the other modes, and assigning
        // proctors to an unproctored exam would be a confusing no-op on the
        // dashboard.
        // config::split_lines(), not the rule class: Moodle's autoloader only covers
        // classes/, so rule.php exists only when core has loaded the access rule
        // itself. Calling a static method on it from here is a fatal "class not
        // found" -- which is what happened the first time a live-proctor session was
        // ever started, since this branch is the only caller in that mode.
        if ($settings->proctoringmode === 'online') {
            $emails = config::split_lines((string) $settings->proctoremails);
            if ($emails) {
                $payload['proctorEmails'] = $emails;
            }
        }

        // The preliminary check compares the student against a reference picture,
        // so it needs one. Moodle's profile picture is the only candidate, and the
        // URL has to carry a token: the Constructor server fetches it with no Moodle
        // session, and would otherwise get the login page instead of an image.
        if (!empty($settings->preliminarycheck)) {
            $photourl = self::profile_picture_url($user);
            if ($photourl !== null) {
                $payload['preliminaryCheck'] = [
                    'enabled' => true,
                    'photo_url' => $photourl,
                ];
            }
        }

        if (!empty($settings->securebrowser)) {
            $payload['secureBrowserLevel'] = $settings->securebrowserlevel;
            $payload['allowToUseAdditionalResources'] = (bool) $settings->allowtouseadditionalresources;

            foreach (
                ['allowedprocesses' => 'desktopAppAllowedProcesses',
                    'forbiddenprocesses' => 'desktopAppForbiddenProcesses'] as $column => $claim
            ) {
                $list = config::split_lines((string) $settings->$column);
                if ($list) {
                    $payload[$claim] = $list;
                }
            }
        }

        // The email claim is optional and carries personal data off-site, so it
        // is only sent when the administrator opts in.
        if (!empty($config->senduseremails) && !empty($user->email)) {
            $payload['email'] = $user->email;
        }

        return $payload;
    }

    /**
     * The rules claim.
     *
     * allow_to_use_calculator is derived from the calculator setting rather than
     * stored separately, so the sidebar rule and the calculator the SDK actually
     * offers cannot contradict each other.
     *
     * @param \stdClass $settings Row from availability_proctor.
     * @return array
     */
    protected static function rules(\stdClass $settings): array {
        $rules = self::decode_set($settings->rules);

        // Fill in anything the stored set is missing, so the claim always states
        // every rule rather than leaving the server to apply defaults for some of
        // them and our values for the rest.
        foreach (config::RULES as $rule => $default) {
            if (!array_key_exists($rule, $rules)) {
                $rules[$rule] = $default;
            }
        }

        $rules['allow_to_use_calculator'] = $settings->calculator !== 'off';

        if (!empty($settings->customrules)) {
            $rules['custom_rules'] = $settings->customrules;
        }

        return $rules;
    }

    /**
     * Decode one of the JSON-encoded settings columns.
     *
     * The value arrives in one of three shapes and all three are legitimate.
     * condition.php keeps rules/warnings/scoring as stdClass (see the cast at
     * condition.php:522), preset::decode() hands back arrays, and a row read
     * straight from the database still holds the raw JSON string. Accepting only
     * the string was a TypeError on every SDK launch, since the condition path
     * never produces one.
     *
     * @param mixed $value Stored JSON, or the already-decoded set.
     * @return array Empty when unset or unparseable.
     */
    protected static function decode_set($value): array {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * A tokenised URL for the student's profile picture, or null if they have none.
     *
     * The token matters: the Constructor server fetches this without a Moodle
     * session, so an ordinary pluginfile URL would return the login page.
     *
     * @param \stdClass $user The student.
     * @return string|null
     */
    protected static function profile_picture_url(\stdClass $user) {
        global $PAGE;

        if (empty($user->picture)) {
            return null;
        }

        // user_picture is a global output class on Moodle 3.11. The namespaced
        // core\output\user_picture class is newer and fatals on the SDK's minimum
        // supported Moodle version. The global name remains available on current
        // Moodle releases and is already used by the Webapp payload builder.
        $picture = new \user_picture($user);
        $picture->size = 1;
        $picture->includetoken = $user->id;

        return $picture->get_url($PAGE)->out(false);
    }

    /**
     * Exam duration in minutes, from the quiz time limit.
     *
     * duration is mandatory. Moodle stores timelimit in seconds and uses 0 for
     * "no time limit", which cannot be expressed as a duration, so a proctored
     * quiz must have a time limit set.
     *
     * <!-- TODO: not documented -- the Developer Guide does not say how the
     * server treats duration: 0, so this fails loudly rather than guessing a
     * fallback. -->
     *
     * @param \stdClass $quiz Row from quiz.
     * @return int Minutes.
     * @throws \moodle_exception When the quiz has no time limit.
     */
    public static function duration_minutes(\stdClass $quiz): int {
        if (empty($quiz->timelimit)) {
            throw new \moodle_exception('error_notimelimit', 'availability_proctor');
        }

        // Round up: a 90-second quiz is one minute of proctoring, not zero.
        return (int) max(1, ceil($quiz->timelimit / MINSECS));
    }

    /**
     * The startDate and endDate claims, as ISO 8601 with an explicit offset.
     *
     * Both are mandatory, but Moodle's timeopen and timeclose are both
     * optional and default to 0. The fallback is therefore explicit:
     *
     * - timeopen unset: start now. The student is launching the exam at this
     *   moment, so "now" is the honest value, and the Guide says that for an
     *   unscheduled exam these are set per session anyway.
     * - timeclose unset: start plus the duration, plus a grace margin.
     *
     * The grace margin exists because endDate is a hard boundary on the
     * Constructor Proctor side while the Moodle timer is what actually governs the
     * attempt. Without margin, a student who starts at the same instant the
     * session is created would race their own deadline, and any clock skew
     * between Moodle and Constructor Proctor would cut the exam short.
     *
     * timeclose is honoured when set, but never earlier than the duration
     * allows: a close date that leaves less time than the time limit would
     * otherwise produce a session that expires mid-exam.
     *
     * @param \stdClass $quiz Row from quiz.
     * @param int $duration Duration in minutes.
     * @return array [startDate, endDate] as ISO 8601 strings.
     */
    public static function exam_window(\stdClass $quiz, int $duration): array {
        $utc = new \DateTimeZone('UTC');

        $starttimestamp = !empty($quiz->timeopen) ? (int) $quiz->timeopen : time();

        // An exam that opened in the past starts now, not retroactively.
        $starttimestamp = max($starttimestamp, time());

        $start = (new \DateTimeImmutable('@' . $starttimestamp))->setTimezone($utc);

        // Grace margin over the bare duration. Generous on purpose: overrunning
        // endDate costs nothing, while undershooting it truncates an exam.
        $minimumend = $start->add(new \DateInterval('PT' . ($duration + self::grace_minutes()) . 'M'));

        if (!empty($quiz->timeclose)) {
            $close = (new \DateTimeImmutable('@' . (int) $quiz->timeclose))->setTimezone($utc);
            $end = $close > $minimumend ? $close : $minimumend;
        } else {
            $end = $minimumend;
        }

        // ATOM includes the offset, which the Guide requires.
        return [
            $start->format(\DateTimeInterface::ATOM),
            $end->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Grace minutes added to the exam window beyond the quiz time limit.
     *
     * Covers the SDK preparation steps, which take roughly a minute and a half
     * and happen after the session is created but before the quiz timer starts,
     * plus any clock skew between Moodle and the Constructor server.
     *
     * @return int Minutes.
     */
    protected static function grace_minutes(): int {
        return 30;
    }

    /**
     * The sessionUrl claim: the quiz this session covers.
     *
     * Points at the quiz rather than at a specific attempt, because no attempt
     * exists when the token is signed -- core's startattempt.php creates it after
     * the SDK has started. The claim is metadata during an ordinary SDK session,
     * but Constructor Secure Browser opens it in the native app; the resolved
     * per-session form therefore replaces this plain URL with a seamless-auth
     * bridge when needed. A reviewer following the plain form lands on the quiz
     * rather than on the exact attempt, which the session row records instead.
     *
     * @param \stdClass $cm The quiz course module.
     * @return string Absolute URL.
     */
    public static function session_url(\stdClass $cm): string {
        // Using out(false) leaves the parameter separators as plain ampersands. The
        // default escapes them for embedding in HTML, which would corrupt the
        // URL once it is read as a JSON string.
        return (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false);
    }

    /**
     * Resolve the session URL used by this particular SDK session.
     *
     * The SDK treats sessionUrl as metadata during an ordinary browser session.
     * Constructor Secure Browser additionally opens it in the native app, whose
     * cookie store does not contain the learner's Moodle login. When seamless
     * authorization is enabled, use a session-bound one-time login bridge for
     * that hand-off. All other SDK sessions retain the plain quiz URL.
     *
     * @param \stdClass $cm The quiz course module.
     * @param \stdClass $session SDK session row.
     * @param \stdClass $user Learner record.
     * @param \stdClass $settings Resolved quiz proctoring settings.
     * @return string Absolute URL.
     */
    protected static function session_url_for_settings(
        \stdClass $cm,
        \stdClass $session,
        \stdClass $user,
        \stdClass $settings
    ): string {
        if (!empty($settings->securebrowser) && get_config('availability_proctor', 'seamless_auth')) {
            return secure_browser::entry_url($session, $user);
        }

        return self::session_url($cm);
    }

    /**
     * The reviewUrl claim: where Constructor Proctor posts the result.
     *
     * api_sdk.php, NOT api.php. The two endpoints resolve the same sessionId field
     * against different tables -- api.php against entries.accesscode, api_sdk.php
     * against sess.sessionkey -- so a result sent to the wrong one finds no row.
     * That failure is silent in the worst way: api.php answers HTTP 200 with
     * "Entry was not found", which tells Constructor the call succeeded, so it never
     * retries and the conclusion is lost with nothing recorded anywhere. See
     * plans/phase6.md.
     *
     * Sending this per session is what lets one site run both models under one set
     * of credentials. The integration's server-side review_url keeps pointing at
     * api.php for the Webapp flow; this claim overrides it for SDK sessions only.
     *
     * No query parameters: the endpoint does one thing, so there is nothing to
     * dispatch on. api.php carries a method parameter because it also handles
     * scheduling events, which do not arise here -- build() sends
     * 'schedule' => false.
     *
     * @return string Absolute URL.
     */
    public static function review_url(): string {
        return (new \moodle_url('/availability/condition/proctor/api_sdk.php'))->out(false);
    }

    /**
     * The student's language as a 2-character ISO 639-1 code.
     *
     * Moodle language codes carry suffixes such as en_us or pt_br, and the claim
     * accepts only the two-letter code.
     *
     * @param \stdClass $user The student.
     * @return string
     */
    protected static function language_code(\stdClass $user): string {
        $lang = !empty($user->lang) ? $user->lang : current_language();

        return substr(strtolower($lang), 0, 2);
    }

    /**
     * Truncate a value to the maximum length the Guide allows for that claim.
     *
     * @param string|null $value The value.
     * @param string $claim Claim name, as a key of MAXLEN.
     * @return string
     */
    protected static function clip($value, string $claim): string {
        $value = (string) $value;

        if (!isset(self::MAXLEN[$claim])) {
            return $value;
        }

        // Deliberately core_text, not substr: names and course titles contain multi-byte
        // characters and cutting mid-character would produce invalid UTF-8.
        return \core_text::substr($value, 0, self::MAXLEN[$claim]);
    }
}
