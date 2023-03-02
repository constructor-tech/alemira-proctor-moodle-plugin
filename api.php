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
 * Availability plugin for integration with Examus proctoring system.
 *
 * @package    availability_examus2
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This file provides API interface in accordance to
 * Examus Simple Integration specification. It has not skip moodle's
 * standard auth flow.
 */
// phpcs:disable moodle.Files.RequireLogin.Missing

use availability_examus2\client;
use availability_examus2\common;

require_once('../../../config.php');

global $DB;

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} else {
    $auth = null;
}

if (empty($auth) || !preg_match('/JWT /', $auth)) {
    echo('Not auth provided');
    exit;
}
$token = explode(' ', $_SERVER['HTTP_AUTHORIZATION'])[1];

$client = new client(null);

try {
    $client->decode($token);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    echo('Signature verification failed');
    exit;
}

$requestbody = file_get_contents('php://input');
if (empty($requestbody)) {
    echo('No request body');
    exit;
}

$request = json_decode($requestbody);

$accesscode = $request->sessionId;

$entry = $DB->get_record('availability_examus2_entries', ['accesscode' => $accesscode]);

$method = optional_param('method', '', PARAM_TEXT);

$handlers = [];
$handlers['review'] = function($entry, $request) {
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

    $warningtitles = $request->warningTitles;
    $warningtitles = !empty($warningtitles) ? $warningtitles : null;

    $entry->status = $request->conclusion;
    $entry->timemodified = $timenow;

    $entry->comment = $request->comment;
    $entry->score = $request->score;
    $entry->threshold = json_encode($request->threshold);
    $entry->sessionstart = $sessionstart;
    $entry->sessionend = $sessionend;
    $entry->warnings = json_encode($request->warnings);
    $entry->warningstitles = json_encode($warningtitles);

    $DB->update_record('availability_examus2_entries', $entry);
};

$handlers['schedule'] = function($entry, $request) {
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
        $DB->update_record('availability_examus2_entries', $entry);
    } else {
        $entry->status = 'canceled';
        $entry->timescheduled = null;
        $DB->update_record('availability_examus2_entries', $entry);
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
