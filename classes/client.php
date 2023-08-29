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

/**
 * Client class
 */
class client {
    /** @var string Date format string, default ISO8601 not accepted by API */
    const ISO8601U = "Y-m-d\TH:i:s.uO";

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
        'zh' => 'zh-cn'
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

    /** @var \availability_proctor\condition Availability condition */
    protected $condition;

    /**
     * Initializes variables form plugin config and availability condition
     * @param \availability_proctor\condition $condition Availability condition
     */
    public function __construct($condition=null) {
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
     * @param string $sessionmethod Mession method
     * @return string
     */
    public function api_url($method, $sessionid=null, $sessionmethod=null) {
        $url = 'https://'.$this->proctorurl.'/api/v2/integration/simple/'.$this->integrationname.'/';

        $url .= $method.'/';

        if (!empty($sessionid)) {
            $url .= $sessionid.'/';

            if (!empty($sessionmethod)) {
                $url .= $sessionmethod.'/';
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
        $baseurl = 'https://'.$this->proctorurl.'/integration/simple/'.$this->integrationname.'/';

        return $baseurl.$method.'/';
    }

    /**
     * Sends `finish` request to api
     * @param string $sessionid Proctoring session id
     * @param string $redirecturl Redirect to this URL after finishing
     * @return string
     */
    public function finish_session($sessionid, $redirecturl) {
        return $this->request('sessions', $sessionid, 'finish', [
            'sessionFinishUrl' => $redirecturl,
        ]);
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
     * @return array
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
     * @param string $sessionmethod Mession method
     * @param array $body Request body
     * @return array
     */
    public function request($method, $sessionid = null, $sessionmethod = null, $body = []) {
        $key = $this->jwtsecret;
        $url = $this->api_url($method, $sessionid, $sessionmethod);
        $payload = ['exp' => time() + 30];
        $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

        $jsondata = json_encode($body);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsondata),
            'Accept: application/json',
            'Authorization: JWT ' . $jwt,
        ];

        $curl = new \curl();
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

        $data = [
            'accountId' => $this->accountid,
            'accountName' => $this->accountname,
            'examId' => $cm->id,
            'examName' => $cm->name,
            'courseName' => $course->fullname,
            'duration' => $conditiondata['duration'],
            'schedule' => false,
            'proctoring' => $conditiondata['mode'],
            'userAgreementUrl' => $conditiondata['useragreementurl'],
            'identification' => $conditiondata['identification'],
            'trial' => $conditiondata['istrial'],
            'auxiliaryCamera' => $conditiondata['auxiliarycamera'],
            'scoreConfig' => $scoring,
            'visibleWarnings' => $conditiondata['warnings'],
            'ldb' => $conditiondata['ldb'],
            'allowMultipleDisplays' => $conditiondata['allowmultipledisplays'],
            'allowVirtualEnvironment' => $conditiondata['allowvirtualenvironment'],
            'checkIdPhotoQuality' => $conditiondata['checkidphotoquality'],
            'webCameraMainView' => $conditiondata['webcameramainview'],
            'rules' => array_merge(
                (array)$conditiondata['rules'],
                ['custom_rules' => $customrules]
            ),
        ];

        return $data;
    }

    /**
     * Format biometry-related data for API
     * @param stdClass $user User object
     * @return array
     */
    public function biometry_data($user) {
        global $PAGE;
        $userpicture = new \user_picture($user);
        $userpicture->size = 1; // Size f1.
        $userpicture->includetoken = $user->id;
        $profileimageurl = $userpicture->get_url($PAGE)->out(false);

        $conditiondata = $this->condition->to_json();

        return [
            'biometricIdentification' => [
                'enabled' => $conditiondata['biometryenabled'],
                'skip_fail' => $conditiondata['biometryskipfail'],
                'flow' => $conditiondata['biometryflow'],
                'theme' => $conditiondata['biometrytheme'],
                'photo_url' => $profileimageurl,
            ],
        ];
    }

    /**
     * Format user-related data for API
     * @param stdClass $user User object
     * @param string|null $moodlelang user's language according to moodle
     * @return array
     */
    public function user_data($user, $moodlelang = null) {
        $data = [
            'userId' => $user->username,
            'firstName' => $user->firstname,
            'lastName' => $user->lastname,
            'thirdName' => $user->middlename,
            'email' => $this->useremails ? $user->email : null,
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
