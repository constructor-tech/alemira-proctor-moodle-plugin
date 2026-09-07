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

use stdClass;

/**
 * Client class
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string Date format string, default ISO8601 not accepted by API */
    const ISO8601U = "Y-m-d\TH:i:s.uO";

    /**
     * NTG-185: seconds to wait for the finish request before giving up.
     *
     * Deliberately short, and applied to the connect phase as well as the whole
     * request. finish_sdk_session() is called inline from attempt_submitted, so
     * this is time the student spends staring at a page that has already accepted
     * their submission. A slow server hands the work to the ad-hoc task instead,
     * which is what the retry path is for.
     *
     * @var int
     */
    const SDK_FINISH_TIMEOUT = 10;

    /**
     * Create Moodle's HTTP client in web, task and CLI contexts.
     *
     * The legacy Webapp request path normally reaches code that has already loaded
     * filelib.php. SDK New entry and finish retries can run without that incidental
     * include, so loading the defining library here avoids a context-dependent
     * fatal error.
     *
     * @return \curl Moodle HTTP client.
     */
    protected static function curl_client() {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        return new \curl();
    }

    /**
     * Maps moodle languages to proctoring languages
     * Not used in map, because no reliable way to deduce from source lang:
     * fr_CH, it_CH.
     * @var array
     */
    const LANGUAGE_MAP = [
        'ar' => 'ar',
        'de' => 'de',
        'de_ch' => 'de-ch',
        'el' => 'el',
        'en' => 'en',
        'es' => 'es',
        'fr' => 'fr',
        'hu' => 'hu',
        'id' => 'id',
        'it' => 'it',
        'kk' => 'kk',
        'lt' => 'lt',
        'ms' => 'ms',
        'pt' => 'pt',
        'ro' => 'ro',
        'ru' => 'ru',
        'th' => 'th',
        'tr' => 'tr',
        'vi' => 'vi',
        'zh_cn' => 'zh-cn',
        'zh' => 'zh-cn',
    ];

    /** @var string Secret key for signing JWT token */
    protected $jwtsecret;

    /** @var string Integration name provided by Proctor by Constructor */
    protected $integrationname;

    /** @var string API URL */
    protected $proctorurl;

    /** @var string Account id withing company */
    protected $accountid;

    /** @var string Company name */
    protected $accountname;

    /** @var bool Company name */
    protected $useremails;

    /** @var condition Availability condition */
    protected $condition;

    /**
     * Initializes variables form plugin config and availability condition
     * @param condition $condition Availability condition
     */
    public function __construct($condition = null) {
        $this->condition = $condition;
        $this->proctorurl = get_config('availability_proctor', 'proctor_url');
        $this->integrationname = get_config('availability_proctor', 'integration_name');
        $this->jwtsecret = get_config('availability_proctor', 'jwt_secret');
        $this->accountid = get_config('availability_proctor', 'account_id');
        $this->accountname = get_config('availability_proctor', 'account_name');
        $this->useremails = get_config('availability_proctor', 'user_emails');
    }

    /**
     * Generates API URL for method
     * @param string $method API method
     * @param string $sessionid Session ID
     * @param string $sessionmethod Session method
     * @return string
     */
    public function api_url($method, $sessionid = null, $sessionmethod = null) {
        $url = 'https://' . $this->proctorurl . '/api/v2/integration/simple/' . $this->integrationname . '/';

        $url .= $method . '/';

        if (!empty($sessionid)) {
            $url .= $sessionid . '/';

            if (!empty($sessionmethod)) {
                $url .= $sessionmethod . '/';
            }
        }

        return $url;
    }

    /**
     * Generates form URL for method
     * @param string $method API method
     * @return string
     */
    public function form_url($method) {
        $baseurl = 'https://' . $this->proctorurl . '/integration/simple/' . $this->integrationname . '/';

        return $baseurl . $method . '/';
    }

    /**
     * Sends `finish` request to api
     * @param string $sessionid Proctoring session id
     * @param string $redirecturl Redirect to this URL after finishing
     * @return array
     */
    public function finish_session($sessionid, $redirecturl) {
        return $this->request('sessions', $sessionid, 'finish', [
            'sessionFinishUrl' => $redirecturl,
        ]);
    }

    /**
     * NTG-185: ask Constructor Proctor to finish an SDK session.
     *
     * A separate method from finish_session() above rather than a second signature
     * on it. That method is the call every existing customer's Webapp flow makes
     * on submit; changing its shape to serve the SDK model would change the one
     * thing this merge must not touch. The two differ in more than arity anyway --
     * see the three points below -- so sharing one body would mean a method whose
     * behaviour depended on which model called it.
     *
     * Static because its caller has a session row and no condition: session.php
     * runs from an ad-hoc task as well as from the request, and there is no
     * condition to construct a client from in either. It reads what it needs from
     * config::get() instead.
     *
     * Three deliberate differences from the Webapp call:
     *
     * 1. sessionFinishUrl is not sent. It tells the SDK where to send the student
     *    when the exam ends, and any navigation takes the launch page down with it
     *    -- including a video upload still in flight. The Webapp flow can send it
     *    safely because Constructor hosts the outer page; under the SDK model that
     *    page is ours. The student still reaches review: proctor.js redirects once
     *    videoUploaded resolves.
     * 2. It reports success or failure rather than returning the response, because
     *    its caller has to decide whether to queue a retry.
     * 3. It signs with the ported jwt::request_token() rather than Firebase. The
     *    Webapp half uses the Firebase library Moodle core bundles at lib/php-jwt
     *    and carries a class_exists() branch because that library's API moved
     *    between versions; the SDK half has neither. The two JWT stacks stay
     *    separate on purpose -- see classes/jwt.php.
     *
     * @param string $sessionkey The sessionId claim identifying the session.
     * @return bool True when the session is settled and no retry is needed.
     */
    public static function finish_sdk_session(string $sessionkey): bool {
        $config = config::get();

        if (!config::is_configured($config)) {
            // There is nothing to call, and no retry will supply a missing
            // credential. Reported as settled so the ad-hoc task stops rather than
            // retrying a misconfiguration every run until the queue is cleared.
            debugging(
                'availability_proctor: cannot finish session ' . $sessionkey . ', plugin is not configured.',
                DEBUG_DEVELOPER
            );
            return true;
        }

        $url = sprintf(
            '%s/api/v2/integration/simple/%s/sessions/%s/finish/',
            config::server_origin($config),
            rawurlencode($config->integrationname),
            rawurlencode($sessionkey)
        );

        $curl = self::curl_client();
        $curl->setHeader([
            'Content-Type: application/json',
            'Authorization: JWT ' . jwt::request_token($config->jwtsecret),
        ]);

        $response = $curl->post($url, '{}', [
            'CURLOPT_TIMEOUT' => self::SDK_FINISH_TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::SDK_FINISH_TIMEOUT,
        ]);

        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);

        if ($status === 200) {
            return true;
        }

        // 4xx means the server understood the request and refused it, so an
        // identical repeat is refused identically -- only 5xx and transport
        // failures are worth another attempt. Returning true on 4xx is what stops
        // the ad-hoc task retrying a permanent refusal forever.
        $retryable = $status === 0 || $status >= 500;

        debugging(sprintf(
            'availability_proctor: finish request for session %s returned %d (%s). %s',
            $sessionkey,
            $status,
            $curl->error !== '' ? $curl->error : substr((string) $response, 0, 200),
            $retryable ? 'Will retry.' : 'Not retrying.'
        ), DEBUG_DEVELOPER);

        return !$retryable;
    }

    /**
     * Get the authoritative status of an SDK session from Constructor.
     *
     * Used only by the instructor-controlled New entry action. A new session must
     * never be created while the previous one is still recording, so transport,
     * authentication and malformed-response failures are all fail-closed.
     *
     * @param string $sessionkey The sessionId claim identifying the session.
     * @return \stdClass Decoded status response. A 404 is returned as status
     *     `not_found`, which the caller may accept only for a session that never
     *     reached Constructor.
     * @throws \moodle_exception When the status cannot be verified safely.
     */
    public static function get_sdk_session_status(string $sessionkey): \stdClass {
        $config = config::get();

        if (!config::is_configured($config)) {
            throw new \moodle_exception('error_sdk_status_unavailable', 'availability_proctor');
        }

        $url = sprintf(
            '%s/api/v2/integration/simple/%s/sessions/%s/status/',
            config::server_origin($config),
            rawurlencode($config->integrationname),
            rawurlencode($sessionkey)
        );

        $curl = self::curl_client();
        $curl->setHeader([
            'Content-Type: application/json',
            'Authorization: JWT ' . jwt::request_token($config->jwtsecret),
        ]);

        $response = $curl->get($url, [], [
            'CURLOPT_TIMEOUT' => self::SDK_FINISH_TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::SDK_FINISH_TIMEOUT,
        ]);
        $info = $curl->get_info();
        $httpstatus = (int) ($info['http_code'] ?? 0);

        if ($httpstatus === 404) {
            return (object) [
                'status' => 'not_found',
                'sessionStart' => null,
                'sessionEnd' => null,
            ];
        }

        if ($httpstatus !== 200) {
            debugging(sprintf(
                'availability_proctor: status request returned %d (%s).',
                $httpstatus,
                $curl->error !== '' ? $curl->error : substr((string) $response, 0, 200)
            ), DEBUG_DEVELOPER);
            throw new \moodle_exception('error_sdk_status_unavailable', 'availability_proctor');
        }

        $decoded = json_decode((string) $response);
        if (!is_object($decoded) || empty($decoded->status)) {
            debugging(
                'availability_proctor: Constructor returned a malformed SDK session status response.',
                DEBUG_DEVELOPER
            );
            throw new \moodle_exception('error_sdk_status_unavailable', 'availability_proctor');
        }

        return $decoded;
    }

    /**
     * Generates form data, action, method to a API-method and payload
     * @param string $method API-method
     * @param array $payload Payload to be send via form
     * @return array
     */
    public function get_form($method, $payload) {
        $key = $this->jwtsecret;
        $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

        return [
            'action' => $this->form_url($method),
            'token' => $jwt,
            'method' => 'POST',
        ];
    }

    /**
     * Decodes JWT message
     * @param string $message encoded JWT
     * @return stdClass
     */
    public function decode($message) {
        // For versions of php-jwt >= 6.0.0
        // Moodle bundles lower version at time of writing this.
        if (class_exists('\Firebase\JWT\Key')) {
            $key = new \Firebase\JWT\Key($this->jwtsecret, 'HS256');
            return \Firebase\JWT\JWT::decode($message, $key);
        } else {
            $key = $this->jwtsecret;
            return \Firebase\JWT\JWT::decode($message, $key, ['HS256']);
        }
    }

    /**
     * Sends API request
     * @param string $method API-method
     * @param string $sessionid Session ID
     * @param string $sessionmethod Session method
     * @param array $body Request body
     * @return array
     */
    public function request($method, $sessionid = null, $sessionmethod = null, $body = []) {
        $key = $this->jwtsecret;
        $url = $this->api_url($method, $sessionid, $sessionmethod);
        $payload = ['exp' => time() + (5 * 60)];
        $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

        $jsondata = json_encode($body);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsondata),
            'Accept: application/json',
            'Authorization: JWT ' . $jwt,
        ];

        $curl = self::curl_client();
        $curl->setHeader($headers);

        $result = $curl->post($url, $jsondata, [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
        ]);

        $code = $curl->get_errno();
        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? $info['http_code'] : 0;

        if ($code != 0) {
            throw new \invalid_response_exception('Curl Error: ' . $result);
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            throw new \invalid_response_exception('Non 200 HTTP code: ' . $httpcode . ' Body:' . $result);
        } else {
            return json_decode($result);
        }
    }

    /**
     * Format exam-related data for API
     * @param stdClass $course Course object
     * @param \cm_info $cm Cm
     * @return array
     */
    public function exam_data($course, $cm) {
        $conditiondata = $this->condition->to_json();

        $customrules = $conditiondata['customrules'];
        $customrules = empty($customrules) ? '' : $customrules;

        $scoring = $conditiondata['scoring'];
        foreach ($scoring as $key => $value) {
            if (is_null($value)) {
                unset($scoring->$key);
            }
        }

        // Enforce rule -> warning suppression: when an "allow" rule is on,
        // the corresponding warning is forced off in the API payload regardless
        // of what is stored on the condition. Source of truth lives on preset.
        $rules = (array) $conditiondata['rules'];
        $warnings = (array) $conditiondata['warnings'];
        foreach (preset::RULE_WARNING_MAP as $rkey => $wkey) {
            if (!empty($rules[$rkey])) {
                $warnings[$wkey] = false;
            }
        }
        $conditiondata['warnings'] = (object) $warnings;

        // Demo exams must never be connected to a live proctor, regardless of
        // the preset's configured proctoring mode. This avoids proctoring
        // costs on sessions that produce no recording (see CONTRIB ticket
        // for demo exam mode / post-exam review override).
        $proctoringmode = $conditiondata['mode'];
        if (!empty($conditiondata['istrial'])) {
            $proctoringmode = 'offline';
        }

        $data = [
            'accountId' => $this->accountid,
            'accountName' => $this->accountname,
            'examId' => $cm->id,
            'examName' => $cm->name,
            'courseName' => $course->fullname,
            'courseCode' => $course->shortname,
            'duration' => common::get_quiz_time_limit($cm),
            'schedule' => false,
            'proctoring' => $conditiondata['mode'],
            'userAgreementUrl' => $conditiondata['useragreementurl'],
            'identification' => $conditiondata['identification'],
            'trial' => $conditiondata['istrial'],
            'auxiliaryCamera' => $conditiondata['auxiliarycamera'],
            'scoreConfig' => $scoring,
            'visibleWarnings' => $conditiondata['warnings'],
            'secureBrowser' => $conditiondata['securebrowser'],
            'secureBrowserLevel' => $conditiondata['securebrowserlevel'],
            'allowToUseAdditionalResources' => $conditiondata['allowtouseadditionalresources'],
            'allowMultipleDisplays' => $conditiondata['allowmultipledisplays'],
            'allowVirtualEnvironment' => $conditiondata['allowvirtualenvironment'],
            'checkIdPhotoQuality' => $conditiondata['checkidphotoquality'],
            'webCameraMainView' => $conditiondata['webcameramainview'],
            'calculator' => $conditiondata['calculator'],
            'desktopAppForbiddenProcesses' => $conditiondata['forbiddenprocesses'],
            'desktopAppAllowedProcesses' => $conditiondata['allowedprocesses'],
            'streamsPreset' => $conditiondata['streamspreset'],
            'sendManualWarningsToLearner' => $conditiondata['sendmanualwarningstolearner'],
            'allowRoomScanAuxCamera' => $conditiondata['allowroomscanauxcamera'],
            'rules' => array_merge(
                (array)$conditiondata['rules'],
                ['custom_rules' => $customrules]
            ),
        ];

        // Assigned proctors (Live review mode only). condition::to_json() already
        // nulls this out for non-online modes, so only attach when present.
        if (!empty($conditiondata['proctoremails'])) {
            $data['proctorEmails'] = $conditiondata['proctoremails'];
        }

        return $data;
    }

    /**
     * Format user-related data for API
     * @param stdClass $user User object
     * @param string|null $moodlelang user's language according to moodle
     * @return array
     */
    public function user_data($user, $moodlelang = null) {
        global $PAGE;
        $conditiondata = $this->condition->to_json();

        $userpicture = new \user_picture($user);
        $userpicture->size = 1; // Size f1.
        $userpicture->includetoken = $user->id;
        $profileimageurl = $userpicture->get_url($PAGE)->out(false);

        $special = null;
        foreach (profile_get_user_fields_with_data($user->id) as $field) {
            // Read the record property rather than calling get_shortname(): the
            // getter only exists from Moodle 3.11 and is a fatal error below it.
            // See AP-11152 -- this is the crash that fix was for, and the ported
            // tree had reintroduced it.
            if ($field->field->shortname != "proctor_special_accommodations") {
                continue;
            }

            $special = strip_tags($field->data);
        }

        $data = [
            'userId' => $user->username,
            'firstName' => $user->firstname,
            'lastName' => $user->lastname,
            'thirdName' => $user->middlename,
            'email' => $this->useremails ? $user->email : null,
            'specialAccommodationsInfo' => $special,
            'preliminaryCheck' => [
                'enabled' => $conditiondata['preliminarycheck'],
                'photo_url' => $profileimageurl,
            ],
        ];

        if ($moodlelang) {
            $lang = $this->map_language($moodlelang);
            if ($lang) {
                $data['language'] = $lang;
            }
        }

        return $data;
    }

    /**
     * Format session-related data for API
     * @param string $sessionid Session Id
     * @param string $url Session URL
     * @return array
     */
    public function attempt_data($sessionid, $url) {
        return [
            'sessionId' => $sessionid,
            'sessionUrl' => $url,
        ];
    }

    /**
     * Format time-related data for API
     * @param array $timebracket start and end date
     * @return array
     */
    public function time_data($timebracket) {
        $dt = new \DateTime();
        $dt->setTimezone(new \DateTimeZone('+0000'));

        $dt->setTimestamp($timebracket['start']);
        $start = $dt->format(\DateTime::ISO8601);

        $dt->setTimestamp($timebracket['end']);
        $end = $dt->format(\DateTime::ISO8601);

        return [
            'startDate' => $start,
            'endDate' => $end,
        ];
    }

    /**
     * Converts moodle language to API language
     * @param string $lang Moodle language
     * @return string API language
     */
    public function map_language($lang) {
        if (isset(self::LANGUAGE_MAP[$lang])) {
            return self::LANGUAGE_MAP[$lang];
        } else {
            $lang = explode('_', $lang)[0];

            if (isset(self::LANGUAGE_MAP[$lang])) {
                return self::LANGUAGE_MAP[$lang];
            } else {
                return null;
            }
        }
    }
}
