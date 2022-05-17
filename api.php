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
 * @copyright  based on work by 2017 Max Pomazuev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use availability_examus2\client;

require_once('../../../config.php');

global $DB;

$url = new \moodle_url('/availability/condition/examus2/api.php');

$auth = empty($_SERVER['HTTP_AUTHORIZATION']) ? null : $_SERVER['HTTP_AUTHORIZATION'];

if (empty($auth) || !preg_match('/JWT /', $auth)) {
    echo('Not auth provided');
    exit;
}
$token = explode(' ', $_SERVER['HTTP_AUTHORIZATION'])[1];

$client = new client();

try {
    $client->decode($token);
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    echo('Signature verification failed');
    exit;
}

$requestBody = file_get_contents('php://input');
if (empty($requestBody)) {
    echo('No request body');
    exit;
}

$request = json_decode($requestBody);

$accesscode = $request->sessionId;
$entry = $DB->get_record('availability_examus2_entries', ['accesscode' => $accesscode]);

$method = optional_param('method', '', PARAM_TEXT);

$handlers = [];
$handlers['review'] = function($entry, $request) {
    global $DB;
    if (isset($request->reportUrl)) {
        $entry->review_link = $request->reportUrl;
    }

    $timenow = time();

    $sessionstart = null;
    if (!empty($request->sessionStart)) {
        $sessionstart = DateTime::createFromFormat(DateTime::ISO8601, $request->sessionStart);
        $sessionstart = $sessionstart->getTimestamp();
    }
    $sessionend = null;
    if (!empty($request->sessionEnd)) {
        $sessionend = DateTime::createFromFormat(DateTime::ISO8601, $request->sessionEnd);
        $sessionend = $sessionend->getTimestamp();

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
    if($event == 'scheduled') {
        $entry->status = 'Scheduled';
    } elseif(!$entry->attemptid) {
        common::reset_entry(['accesscode' => $entry->accesscode]);
    }

    if($request->start) {
        $timescheduled = DateTime::createFromFormat(DateTime::ISO8601, $request->start);
        $entry->timescheduled = $timescheduled->getTimestamp();
    }

    $DB->update_record('availability_examus2_entries', $entry);

    // if (!$entry->attemptid && $status != 'Scheduled') {
    // common::reset_entry(['accesscode' => $entry->accesscode]);
    //}

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