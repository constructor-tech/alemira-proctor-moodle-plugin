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
 * The proctored launch page.
 *
 * This page hosts the Constructor Proctor SDK and embeds the quiz attempt in a
 * same-origin iframe. It must never reload once a session is running: the server
 * accepts init() again with the same sessionId, but the recording gains a gap
 * covering the reload and the student has to repeat every preparation step. That
 * constraint is the whole reason the quiz lives in an iframe rather than on this
 * page directly.
 *
 * The attempt is created by core, not here: on examStarted the iframe loads
 * startattempt.php so that the pre-flight chain (quiz password, Safe Exam
 * Browser) runs as it would for any other quiz. The session therefore exists
 * before the attempt does, and binds to it on the first attempt.php request.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use availability_proctor\config;
use availability_proctor\launcher;
use availability_proctor\session;

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quiz:attempt', $context);

$PAGE->set_url('/availability/condition/proctor/launch.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));

// The embedded layout gives the SDK the whole viewport. The SDK renders its
// preparation modal into this page, so Moodle's header, drawers and footer would
// otherwise frame it, and would also offer the student links out of a proctored
// session.
$PAGE->set_pagelayout('embedded');

// The styles.css file at the plugin root is picked up automatically by
// theme_config::get_css_files(), which scans every installed plugin -- no
// explicit $PAGE->requires->css() call needed, and one here would only add a
// second, redundant <link> for the same rules.

$backurl = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);

// Everything below reports failure through the same page rather than throwing,
// because a student who arrives here is trying to sit an exam: a stack trace or a
// blank screen tells them nothing they can act on.
$failure = null;

// NTG-185: the version floor, checked before anything else that could mask it.
//
// A below-floor site would already fail the next check, because
// is_enabled_for_quiz() consults is_sdk_model() -- but it would fail as "this quiz
// is not proctored", which is untrue and sends whoever investigates to the quiz
// settings instead of to the Moodle version. This message is aimed at an
// administrator rather than the student, since it names a cause only they can act
// on, and it is the one failure here that no change to the quiz can fix.
if (!config::is_sdk_available()) {
    $failure = get_string('error_sdk_unsupported', 'availability_proctor');
} else if (!config::is_enabled_for_quiz((int) $cm->instance)) {
    $failure = get_string('error_notproctored', 'availability_proctor');
} else if (!config::is_configured()) {
    // Deliberately vague to the student: which settings are missing is useful to
    // an administrator and meaningless to a student. The detail is in the
    // settings page, which the student cannot see anyway.
    $failure = get_string('error_notconfigured_learner', 'availability_proctor');
}

// The attempt is deliberately NOT created here. The student is handed off to
// core's startattempt.php inside the iframe, which runs the pre-flight chain --
// the quiz password, Safe Exam Browser, offline attempts -- and creates the
// attempt itself. Creating it here would skip that chain entirely, which is how
// a password-protected quiz could be opened without the password.
//
// The consequence is that the session exists before the attempt does, so it is
// keyed on the quiz and the student, and binds to the attempt later. It also
// means the quiz timer no longer starts while the student works through the SDK
// preparation steps.
if ($failure === null) {
    try {
        launcher::check_can_launch($cm, $USER);
    } catch (moodle_exception $e) {
        // Thrown when the quiz cannot produce a valid session at all: no time
        // limit, or no questions. The message is already aimed at the student.
        $failure = $e->getMessage();
    }
}

$proctorsession = null;
if ($failure === null) {
    $proctorsession = session::get_or_create((int) $cm->instance, (int) $USER->id);
}

echo $OUTPUT->header();

if ($failure !== null) {
    echo $OUTPUT->notification($failure, \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->single_button($backurl, get_string('backtoquiz', 'availability_proctor'), 'get');
    echo $OUTPUT->footer();
    die();
}

// Endpoints are keyed on the session rather than on an attempt, because no
// attempt exists yet. sesskey is included because the state endpoints change
// server state, and the token endpoint takes one too so that a third-party page
// cannot cause a session to be minted for a student who merely visits it.
$tokenurl = (new moodle_url('/availability/condition/proctor/token.php', [
    'sessionid' => $proctorsession->id,
    'sesskey' => sesskey(),
]))->out(false);

$eventurl = (new moodle_url('/availability/condition/proctor/event.php', [
    'sessionid' => $proctorsession->id,
    'sesskey' => sesskey(),
]))->out(false);

// What the iframe loads on examStarted: core's own start-attempt entry point,
// which requires a sesskey and POSTs its pre-flight form back to itself, so the
// whole exchange stays inside the frame.
//
// out(false) throughout: the default escapes parameter separators for HTML text,
// which corrupts the URL once it is read as a JavaScript string. The reference
// plugin hit exactly this, with sesskey arriving named "amp;sesskey".
$starturl = (new moodle_url('/mod/quiz/startattempt.php', [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
]))->out(false);

$templatecontext = [
    'quizname' => format_string($cm->name),
    'starturl' => $starturl,
    'tokenurl' => $tokenurl,
    'eventurl' => $eventurl,
    'backurl' => $backurl->out(false),
    // Served from js/ rather than amd/src/, and that location is load-bearing.
    // Moodle scans amd/src/ when it assembles lib/amd/build/first.min.js, so a file
    // placed there is concatenated into that bundle regardless of any build
    // configuration. This module is an ES module using a dynamic import(), which is
    // invalid AMD syntax -- while it lived under amd/src/ the bundle failed to parse
    // on every page of the site, and because Moodle draws its navigation from that
    // bundle, the primary navigation and user menu vanished site-wide. Keeping it
    // out of amd/src/ is the fix; keeping it off the Makefile's AMD_MODULES list is
    // not sufficient on its own.
    //
    // Versioned so an updated module actually reaches the browser. This file is
    // fetched by a plain script tag rather than through Moodle's AMD loader, so it
    // gets none of the cache-busting core normally applies, and a stale copy would
    // be indistinguishable from a bug.
    'modulesrc' => (new moodle_url(
        '/availability/condition/proctor/js/proctor.js',
        ['v' => get_config('availability_proctor', 'version')]
    ))->out(false),
    // Passed through rather than hardcoded a second time in proctor.js, so there
    // is exactly one place that names the SDK's URL.
    'sdksrc' => launcher::SDK_MODULE_URL,
];

echo $OUTPUT->render_from_template('availability_proctor/launch', $templatecontext);

echo $OUTPUT->footer();
