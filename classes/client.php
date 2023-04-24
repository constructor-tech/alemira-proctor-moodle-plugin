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
 * Availability plugin for integration with Alemira proctoring system.
 *
 * @package    availability_alemira
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_alemira;

/**
 * Client class
 */
class client {
    const ISO8601U = "Y-m-d\TH:i:s.uO";

    // Not used in map, because no reliable way to deduce from source lang.
    // fr_CH, it_CH.
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

    protected $jwtsecret;
    protected $integrationname;
    protected $alemiraurl;
    protected $accountid;
    protected $accountname;
    protected $useremails;
    protected $condition;

    public function __construct($condition) {
        $this->condition = $condition;
        $this->alemiraurl = get_config('availability_alemira', 'alemira_url');
        $this->integrationname = get_config('availability_alemira', 'integration_name');
        $this->jwtsecret = get_config('availability_alemira', 'jwt_secret');
        $this->accountid = get_config('availability_alemira', 'account_id');
        $this->accountname = get_config('availability_alemira', 'account_name');
        $this->useremails = get_config('availability_alemira', 'user_emails');
    }

    public function api_url($method) {
        $baseurl = 'https://'.$this->alemiraurl.'/api/v2/integration/simple/'.$this->integrationname.'/';

        return $baseurl.$method.'/';
    }

    public function form_url($method) {
        $baseurl = 'https://'.$this->alemiraurl.'/integration/simple/'.$this->integrationname.'/';

        return $baseurl.$method.'/';
    }

    public function get_finish_url($sessionid, $redirecturl) {
        $finishurl = $this->form_url('finish');
        $finishurl .= $sessionid;
        $finishurl .= '/?redirectUrl='.urlencode($redirecturl);

        return $finishurl;
    }

    public function get_form($method, $payload) {
        $key = $this->jwtsecret;
        $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

        return [
            'action' => $this->form_url($method),
            'token' => $jwt,
            'method' => 'POST',
        ];
    }

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

    public function request($method, $body = []) {
        $key = $this->jwtsecret;
        $url = $this->api_url($method);
        $payload = ['exp' => time() + 30];
        $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

        $headers = [
            'Content-Type: application/json',
            'Authorization: JWT ' . $jwt
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false) {
            echo 'Curl Error: ' . curl_error($ch) . "\n";
            return;
        }

        if ($code < 200 || $code >= 300) {
            echo "Non-200 code";
            var_dump($result);
            return;
        } else {
            return json_decode($result);
        }
    }

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
            'rules' => array_merge(
                (array)$conditiondata['rules'],
                ['custom_rules' => $customrules]
            ),
        ];

        return $data;
    }

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

    public function attempt_data($sessionid, $url) {
        return [
            'sessionId' => $sessionid,
            'sessionUrl' => $url,
        ];
    }

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
