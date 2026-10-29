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

/**
 * This file provides API interface in accordance to
 * Proctor by Constructor Simple Integration specification. It has not skip moodle's
 * standard auth flow.
 */
// phpcs:disable moodle.Files.RequireLogin.Missing

use availability_proctor\client;
use availability_proctor\common;

require_once('../../../config.php');

global $DB;

// Moodle has no wrapper for raw HTTP request headers. Prefer getallheaders(),
// but keep the server-variable fallback used by the Webapp integration: on
// PHP-FPM before 7.3 getallheaders() is unavailable, and some web-server
// configurations expose Authorization only through one of these variables.
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = null;
if (is_array($headers)) {
    foreach ($headers as $hname => $hvalue) {
        $lname = strtolower($hname);
        if ($lname === 'authorization' || $lname === 'redirect-http-authorization') {
            $auth = $hvalue;
            break;
        }
    }
}

if (empty($auth)) {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    } else if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
}

if (empty($auth) || !preg_match('/JWT /', $auth)) {
    echo('Not auth provided');
    exit;
}
$token = explode(' ', $auth)[1];

$client = new client(null);

try {
    $client->decode($token);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    echo('Signature verification failed');
    exit;
}

$requestbody = file_get_contents('php://input');
if (empty($requestbody)) {
    http_response_code(400);
    echo('No request body');
    exit;
}

$request = json_decode($requestbody);
if (!is_object($request) || empty($request->sessionId)) {
    http_response_code(400);
    echo('Invalid request body');
    exit;
}

$accesscode = $request->sessionId;

$entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);

$method = optional_param('method', '', PARAM_TEXT);

$handlers = [];
$handlers['review'] = function ($entry, $request) {
    global $DB;
    if (isset($request->reportUrl)) {
        $entry->review_link = $request->reportUrl;
    }
    if (isset($request->archive)) {
        $entry->archiveurl = $request->archive;
    }

    $timenow = time();

    $sessionstart = null;
    if (!empty($request->sessionStart)) {
        $sessionstart = common::parse_date($request->sessionStart);
    }
    $sessionend = null;
    if (!empty($request->sessionEnd)) {
        $sessionend = common::parse_date($request->sessionEnd);
    }

    $warningtitles = $request->warningTitles ?? null;
    $warningtitles = !empty($warningtitles) ? $warningtitles : null;

    // status is NOTNULL in install.xml, so a payload without a conclusion must
    // leave the stored value alone rather than write null -- PostgreSQL refuses
    // a null there outright, and on MySQL it would erase a status already set.
    if (isset($request->conclusion)) {
        $entry->status = (string) $request->conclusion;
    }
    $entry->timemodified = $timenow;

    $entry->comment = isset($request->comment) ? (string) $request->comment : null;

    // score is an int column. Cast rather than assign the decoded JSON value
    // straight through: Moodle's DML binds parameters as strings, so a
    // fractional score arrives at the database as '92.5', which MySQL silently
    // rounds to 93 while PostgreSQL rejects with "invalid input syntax for type
    // bigint" -- failing the webhook write, so the result never lands and the
    // exam shows no outcome. Absent and zero stay distinct: a missing score
    // means not yet calculated, zero means calculated as clean. This mirrors
    // what the SDK path already does in session::apply_result().
    $entry->score = isset($request->score) && is_numeric($request->score)
        ? (int) $request->score : null;

    $entry->threshold = isset($request->threshold) ? json_encode($request->threshold) : null;
    $entry->sessionstart = $sessionstart;
    $entry->sessionend = $sessionend;
    $entry->warnings = isset($request->warnings) ? json_encode($request->warnings) : null;
    $entry->warningstitles = json_encode($warningtitles);

    $DB->update_record('availability_proctor_entries', $entry);
};

$handlers['schedule'] = function ($entry, $request) use ($accesscode) {
    global $DB;
    $event = $request->event;

    if ($event == 'scheduled') {
        if ($entry->status != 'new') {
            $entry = common::most_recent_entry($entry);
            if (!$entry) {
                $entry = common::reset_entry(['accesscode' => $accesscode], true);
            }
        }

        $entry->status = 'scheduled';
        $entry->timescheduled = common::parse_date($request->start);
        $DB->update_record('availability_proctor_entries', $entry);
    } else {
        $entry->status = 'canceled';
        $entry->timescheduled = null;
        $DB->update_record('availability_proctor_entries', $entry);
        common::reset_entry(['accesscode' => $accesscode], true);
    }
};

if ($entry) {
    if (isset($handlers[$method])) {
        $handlers[$method]($entry, $request);
    } else {
        echo 'Success';
    }
} else {
    echo 'Entry was not found';
}
