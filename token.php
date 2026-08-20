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
 * Returns the data the launch page needs to start proctoring, as JSON:
 * serverOrigin, integrationName, jwt and config.
 *
 * The JWT is signed here so that jwt_secret never reaches the browser. This is
 * also the only place that moves a session from pending to active, which makes it
 * the gate the whole enforcement rests on: without a successful call here, the
 * access rule serves no questions.
 *
 * config carries the quiz's SDK configuration parameters -- plain client-side
 * flags for proctoring.init(), never JWT claims, so they take no part in
 * payload::build() and the server never sees them. Only values that differ from
 * the SDK's own defaults are included; proctor.js spreads them as top-level
 * init() keys, never nested under a config property, per the Developer Guide.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use availability_proctor\config;
use availability_proctor\jwt;
use availability_proctor\payload;
use availability_proctor\session;

$sessionid = required_param('sessionid', PARAM_INT);

require_login(null, false);
require_sesskey();

// AJAX_SCRIPT makes Moodle render exceptions as JSON with an HTTP error status,
// which the fetch() caller in proctor.js can read and show to the student. Every
// failure below therefore throws rather than printing.

// Keyed on the session rather than an attempt: at this point no attempt exists
// yet. Core's startattempt.php creates it inside the iframe, once the SDK has
// started and the pre-flight chain has run.
$proctorsession = $DB->get_record(\availability_proctor\session::TABLE, ['id' => $sessionid]);
if (!$proctorsession) {
    throw new moodle_exception('error_nosession', 'availability_proctor');
}

// Only the owner of a session may mint a token for it. Without this check any
// authenticated user could obtain a signed JWT naming someone else's session,
// and the resulting recording would be filed against them.
if ((int) $proctorsession->userid !== (int) $USER->id) {
    throw new moodle_exception('error_notyoursession', 'availability_proctor');
}

$quiz = $DB->get_record('quiz', ['id' => $proctorsession->quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

require_capability('mod/quiz:attempt', context_module::instance($cm->id));

$settings = config::get_quiz_settings((int) $quiz->id);
if ($settings === null) {
    throw new moodle_exception('error_notproctored', 'availability_proctor');
}

$pluginconfig = config::get();
$missing = config::missing_settings($pluginconfig);
if ($missing) {
    // The student sees a generic message; which settings are missing goes in the
    // debug channel, where only an administrator with debugging on will see it.
    throw new moodle_exception(
        'error_notconfigured_learner',
        'availability_proctor',
        '',
        null,
        'Missing settings: ' . implode(', ', $missing)
    );
}

if (!in_array($proctorsession->state, session::STATES_OPEN, true)) {
    throw new moodle_exception('error_sessionfinished', 'availability_proctor');
}

// Build the payload before the transition, so that a payload that cannot be built
// leaves the session pending rather than active-but-unusable.
$claims = payload::build($quiz, $course, $USER, $settings, $proctorsession, $pluginconfig, $cm);

$token = jwt::sign($claims, $pluginconfig->jwtsecret);

// Only now is the session live. Re-entering active is allowed: the student may
// reload the launch page before the exam starts, and the server keeps a single
// session record for a repeated sessionId.
if (!session::transition($proctorsession, session::STATE_ACTIVE, 'token_issued')) {
    throw new moodle_exception(
        'error_badtransition',
        'availability_proctor',
        '',
        null,
        'Cannot activate from state ' . $proctorsession->state
    );
}

// NTG-185: read from site settings, not from the exam settings object.
//
// This previously decoded $settings->sdkconfig, which the plugin the SDK flow came
// from stored per quiz. Here exam settings come from the availability condition,
// and sdkconfig is not one of condition::PROPS -- so that read always found
// nothing and every site silently got the SDK's own built-in defaults, with eight
// dead settings shipped alongside. It did not fail loudly because an absent value
// is a legitimate one here.
//
// sdk_config() returns an object rather than an array for a reason worth keeping:
// an empty PHP array encodes as [] rather than {}, and proctor.js spreads this
// value as an object.
// $settings carries the exam's own SDK overrides, so a quiz can differ from the
// site default. Resolution order is exam override -> site setting -> shipped
// default; see config::sdk_config().
$sdkconfig = config::sdk_config($pluginconfig, $settings);

echo json_encode([
    'serverOrigin' => config::server_origin($pluginconfig),
    'integrationName' => $pluginconfig->integrationname,
    'jwt' => $token,
    'config' => $sdkconfig,
]);
