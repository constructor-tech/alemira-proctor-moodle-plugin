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
 * Receives the proctoring result for an SDK-model session.
 *
 * This is the reviewUrl the session token points at. Constructor calls it when a
 * conclusion is known, and calls it again as fields such as the score are filled
 * in, so it must be safe to receive more than once for one session.
 *
 * Separate from api.php, which serves the Webapp model, because the two share
 * almost nothing: api.php resolves sessionId against
 * availability_proctor_entries.accesscode and this one against
 * availability_proctor_sess.sessionkey; api.php verifies its token with the
 * Firebase JWT library that Moodle core bundles at lib/php-jwt (autoloaded via
 * lib/classes/component.php -- there is no submodule and nothing to vendor) and
 * this one with the plugin's own jwt::verify(); and the two answer with different status codes for the same
 * outcomes. In particular api.php answers HTTP 200 when a signature fails, which
 * is wrong but is also the contract Constructor has been talking to at customer
 * sites for years -- so it is left exactly as it is, and this endpoint refuses
 * properly instead. See plans/phase6.md.
 *
 * Deliberately outside Moodle's login flow: the caller is a server, not a person.
 * Authentication is the Authorization: JWT header, verified against jwt_secret,
 * which is also what proves the request really came from Constructor Proctor.
 * There is no sesskey here and there should not be -- this is not a browser form.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No cookies: this is a server-to-server call, so there is no session to resume,
// and starting one per webhook would litter the session store.
define('NO_MOODLE_COOKIES', true);

// Keep debugging out of the response body. This endpoint answers with a status
// code that Constructor acts on, and header() cannot run once anything has been
// printed -- so on a site with debugdisplay on, a debugging() call before the
// answer (an unknown session, a differing accountId) both polluted the JSON and
// silently lost the 401/400/503, letting a refused call read as accepted. With
// this constant debugging() still records, into the server error log, so the
// diagnostic value is kept and the contract is not.
define('NO_DEBUG_DISPLAY', true);

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../../config.php');

use availability_proctor\config;
use availability_proctor\jwt;
use availability_proctor\session;

/**
 * Answer the caller and stop.
 *
 * Bodies are kept terse and free of internal detail: this endpoint is reachable by
 * anyone, so its replies should confirm or deny without describing the site.
 *
 * @param int $status HTTP status code.
 * @param string $message Short plain-text body.
 */
function availability_proctor_sdk_respond(int $status, string $message) {
    header('Content-Type: text/plain; charset=utf-8', true, $status);
    echo $message;
    die();
}

/**
 * The bearer token from the request headers, if there is one.
 *
 * Moodle has no wrapper for raw request headers. getallheaders() covers the
 * supported SAPIs; the redirect- prefixed variant appears when Apache rewrites
 * the request and would otherwise hide the Authorization header.
 *
 * @return string|null
 */
function availability_proctor_sdk_bearer_token() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    foreach ($headers as $name => $value) {
        $name = strtolower((string) $name);
        if ($name !== 'authorization' && $name !== 'redirect-http-authorization') {
            continue;
        }

        // Format is "JWT <token>", per the Developer Guide.
        if (preg_match('/^\s*JWT\s+(\S+)\s*$/i', (string) $value, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    availability_proctor_sdk_respond(405, 'POST required');
}

$pluginconfig = config::get();
if (empty($pluginconfig->jwtsecret)) {
    // Without the shared secret nothing can be verified, so accepting the body
    // would mean trusting an unauthenticated request.
    availability_proctor_sdk_respond(503, 'Not configured');
}

$token = availability_proctor_sdk_bearer_token();
if ($token === null) {
    availability_proctor_sdk_respond(401, 'Authorization required');
}

if (jwt::verify($token, $pluginconfig->jwtsecret) === null) {
    availability_proctor_sdk_respond(401, 'Invalid token');
}

$body = file_get_contents('php://input');
$result = json_decode((string) $body, true);

if (!is_array($result) || empty($result['sessionId'])) {
    availability_proctor_sdk_respond(400, 'Invalid body');
}

$proctorsession = $DB->get_record(session::TABLE, ['sessionkey' => (string) $result['sessionId']]);

if (!$proctorsession) {
    // Logged, because a result arriving for a session this site cannot find is
    // exactly the kind of silent loss that is impossible to diagnose afterwards.
    debugging('availability_proctor: result webhook for unknown session '
        . clean_param((string) $result['sessionId'], PARAM_TEXT), DEBUG_DEVELOPER);

    // 200 rather than 404 on purpose: the guide has Constructor Proctor retry on failure,
    // and a session this site does not know about will never appear, so retrying
    // would repeat forever. Reporting success ends it.
    availability_proctor_sdk_respond(200, 'Unknown session, ignored');
}

// The accountId is recorded when it differs from ours but is NOT used to reject the
// result. The Developer Guide describes it as the company id in your system, yet
// what comes back need not be the value this plugin sent, and dropping a genuine
// conclusion over that would lose it permanently and silently.
//
// Nothing is given up by not checking it: the JWT signature already proves the
// caller holds jwt_secret, and sessionId is matched against a random per-session
// key minted on this site, so a result cannot be steered onto the wrong session.
if (
    !empty($result['accountId']) && !empty($pluginconfig->accountid)
        && (string) $result['accountId'] !== (string) $pluginconfig->accountid
) {
    debugging('availability_proctor: result webhook accountId '
        . clean_param((string) $result['accountId'], PARAM_TEXT)
        . ' differs from the configured ' . $pluginconfig->accountid
        . '; storing anyway', DEBUG_DEVELOPER);
}

session::record_result($proctorsession, $result);

availability_proctor_sdk_respond(200, 'OK');
