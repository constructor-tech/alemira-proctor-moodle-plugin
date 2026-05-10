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
 * Utils class
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Returns the correct quiz settings class name for this Moodle version.
     *
     * @return string Fully qualified class name
     */
    public static function quiz_settings_classname() {
        return class_exists('\mod_quiz\quiz_settings') ? '\mod_quiz\quiz_settings' : 'quiz';
    }

    /**
     * Returns the correct quiz attempt class name for this Moodle version.
     *
     * @return string Fully qualified class name
     */
    public static function quiz_attempt_classname() {
        return class_exists('\mod_quiz\quiz_attempt') ? '\mod_quiz\quiz_attempt' : 'quiz_attempt';
    }

    /**
     * Provides logic for proctoring fader, exit as soon as possible if
     * no protection is required.
     *
     * @param \stdClass $attempt Attempt
     * @return string HTML output for the fader overlay
     */
    public static function handle_proctoring_fader($attempt) {
        global $DB, $USER, $PAGE, $SESSION;

        $cmid = state::$attempt['cm_id'];
        $courseid = state::$attempt['course_id'];

        $modinfo = get_fast_modinfo($courseid);
        $cm = $modinfo->get_cm($cmid);
        $course = $cm->get_course();

        $condition = condition::get_proctor_condition($cm);

        if (!$condition) {
            return '';
        }

        // We want to let previews to happen without proctoring.
        $quizobj = static::quiz_settings_classname()::create($cm->instance, $USER->id);
        if ($quizobj->is_preview_user()) {
            return '';
        }

        if (!$condition->user_in_proctored_groups($USER->id)) {
            return '';
        }

        // Hide Moodle chrome (header, left drawer, section breadcrumb, back button)
        // during a proctored attempt. Always applied once we've confirmed the
        // activity is proctored and the user is in scope.
        $output = self::get_lockdown_css();

        $entry = common::create_entry($condition, $USER->id, $cm);

        if (
            !empty($SESSION->availability_proctor_accesscode) &&
                $entry->accesscode != $SESSION->availability_proctor_accesscode
        ) {
            $SESSION->availability_proctor_accesscode = null;
            $SESSION->availability_proctor_reset = true;
        }

        $timebracket = common::get_timebracket_for_cm('quiz', $cm, $USER->id);
        $lang = current_language();

        $client = new client($condition);
        $data = $client->exam_data($course, $cm);
        $userdata = $client->user_data($USER, $lang);

        $timedata = $client->time_data($timebracket);
        $starturl = self::generate_start_url($entry, $USER);
        $attemptdata = $client->attempt_data($entry->accesscode, $starturl);

        $data = array_merge($data, $userdata, $timedata, $attemptdata);

        if ($condition->schedulingrequired && empty($entry->timescheduled)) {
            $data['schedule'] = true;
        }

        $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);
        $attemptinprogess = $attempt && $attempt->state == utils::quiz_attempt_classname()::IN_PROGRESS;

        if ($entryisactive || $attemptinprogess) {
            // We have to pass formdata in any case because exam can be opened outside iframe.
            $formdata = $client->get_form('start', $data);
            $entryreset = isset($SESSION->availability_proctor_reset) && $SESSION->availability_proctor_reset;

            // Our entry is active, we are showing the user a fader.
            ob_start();
            include(dirname(__FILE__).'/../templates/proctoring_fader.php');
            $output .= ob_get_clean();
        }

        return $output;
    }

    /**
     * CSS injected into the head during a proctored quiz attempt. Hides Moodle
     * chrome — top header, left navigation drawer, and the section breadcrumb
     * above the quiz title — so the learner only sees the quiz itself.
     *
     * Scoped to `body.path-mod-quiz` as a safety net (hook already only runs on
     * in-progress proctored attempts, but the class scoping prevents accidental
     * application if the hook ever fires elsewhere).
     *
     * @return string <style> block
     */
    public static function get_lockdown_css() {
        $css = <<<CSS
            /* Top header / navbar */
            body.path-mod-quiz #page-header,
            body.path-mod-quiz header#page-header,
            body.path-mod-quiz header.navbar,
            body.path-mod-quiz nav.navbar,
            body.path-mod-quiz .navbar-fixed-top,
            /* Section breadcrumb / secondary nav above the quiz title */
            body.path-mod-quiz #page-navbar,
            body.path-mod-quiz nav.breadcrumb-nav,
            body.path-mod-quiz .breadcrumb-nav,
            body.path-mod-quiz .breadcrumb,
            body.path-mod-quiz .secondary-navigation,
            body.path-mod-quiz nav.moremenu,
            /* Left navigation drawer */
            body.path-mod-quiz [data-region="drawer"],
            body.path-mod-quiz [data-region="fixed-drawer"],
            body.path-mod-quiz #nav-drawer,
            body.path-mod-quiz .drawer,
            body.path-mod-quiz .drawer-left,
            body.path-mod-quiz .drawer-toggles,
            body.path-mod-quiz button[data-toggler="drawers"],
            body.path-mod-quiz .drawercontent,
            /* Back buttons / activity navigation / tertiary nav row */
            body.path-mod-quiz .tertiary-navigation,
            body.path-mod-quiz .btn-back,
            body.path-mod-quiz a.back-button,
            body.path-mod-quiz .activity-header .back,
            body.path-mod-quiz .activity-nav,
            body.path-mod-quiz .activity-navigation,
            body.path-mod-quiz [data-action="back"],
            body.path-mod-quiz a[aria-label*="Back" i],
            body.path-mod-quiz a[aria-label*="ack to" i],
            body.path-mod-quiz .previouslink,
            body.path-mod-quiz .continuelink {
                display: none !important;
            }
            /* Reclaim the space freed by the hidden header/drawer */
            body.path-mod-quiz #page,
            body.path-mod-quiz #page-wrapper,
            body.path-mod-quiz #page-content,
            body.path-mod-quiz.drawer-open-left #page,
            body.path-mod-quiz.pagelayout-incourse #page {
                margin-left: 0 !important;
                margin-top: 0 !important;
                padding-left: 0 !important;
                padding-top: 0 !important;
            }
CSS;
        return '<style>' . $css . '</style>';
    }

    /**
     * Generate exam start url, with auth token if enabled in config.
     *
     * @param \stdClass $entry Proctor entry record
     * @param \stdClass $user User record
     * @return string The start URL
     */
    public static function generate_start_url($entry, $user) {
        $urlparams = ['proctor_accesscode' => $entry->accesscode];

        if (get_config('availability_proctor', 'seamless_auth')) {
            // Token is valid for 3 months.
            // We want a timeframe log enough for the user to pass a quiz but clean the db at some point.
            $tokenvaliduntil = time() + (3 * 60 * 60 * 24);
            $urlparams['token'] = get_user_key('availability_proctor', $user->id, null, false, $tokenvaliduntil);
        }

        $url = new \moodle_url('/availability/condition/proctor/entry.php', $urlparams);

        return $url->out(false);
    }

    /**
     * When an attempt is started, see if we are in proctoring, reset old entries,
     * redirect to proctoring if needed.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $cm Course module record
     * @param \stdClass $user User record
     * @return void
     */
    public static function handle_start_attempt($course, $cm, $user) {
        global $SESSION, $DB;
        $modinfo = get_fast_modinfo($course->id);
        $cminfo = $modinfo->get_cm($cm->id);

        $condition = condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return;
        }

        // We want to let previews to happen without proctoring.
        $quizobj = static::quiz_settings_classname()::create($cminfo->instance, $user->id);
        if ($quizobj->is_preview_user()) {
            return;
        }

        if (!$condition->user_in_proctored_groups($user->id)) {
            return;
        }

        $accesscode = isset($SESSION->availability_proctor_accesscode) ? $SESSION->availability_proctor_accesscode : null;
        $entry = null;
        $reset = false;
        if ($accesscode) {
            $entry = $DB->get_record('availability_proctor_entries', [
                'accesscode' => $accesscode,
            ]);

            // Entry is old.
            if ($entry && !in_array($entry->status, ['new', 'scheduled', 'started'])) {
                $reset = true;
            }

            // Entry belongs to other cm.
            if ($entry && $entry->cmid != $cminfo->id) {
                $reset = true;
            }

            if (!$entry) {
                $reset = true;
            }

            if ($reset) {
                unset($SESSION->availability_proctor_accesscode);
                $SESSION->availability_proctor_reset = true;
            }

            // We don't want to redirect at this stage.
            // Because its possible that the user is working through Web-app.
            return;
        } else {
            $entry = common::create_entry($condition, $user->id, $cminfo);
        }

        // The attempt is already started, letting it open.
        if ($entry->status == 'started') {
            return;
        }

        $timebracket = common::get_timebracket_for_cm('quiz', $cminfo, $user->id);

        $starturl = self::generate_start_url($entry, $user);

        $lang = current_language();

        $client = new \availability_proctor\client($condition);
        $data = $client->exam_data($course, $cminfo);
        $userdata = $client->user_data($user, $lang);
        $timedata = $client->time_data($timebracket);
        $attemptdata = $client->attempt_data($entry->accesscode, $starturl);

        $data = array_merge($data, $userdata, $timedata, $attemptdata);

        if ($condition->schedulingrequired) {
            $data['schedule'] = true;
        }

        $formdata = $client->get_form('start', $data);

        $pagetitle = get_string('redirecting_to_proctor', 'availability_proctor',
            get_string('pluginname', 'availability_proctor'));
        $gobuttonlabel = get_string('proctor_go_to_system', 'availability_proctor');

        include(dirname(__FILE__).'/../templates/redirect.php');
        die();
    }

    /**
     * If accesscode param is provided, find entry, handle its state.
     *
     * @param string $accesscode Accesscode/SessionId value
     * @return void
     */
    public static function handle_accesscode_param($accesscode) {
        global $SESSION, $DB, $CFG;

        // User is coming from proctor, reset is done if it was requested before.
        unset($SESSION->availability_proctor_reset);

        $SESSION->availability_proctor_accesscode = $accesscode;

        // We know accesscode is passed in params.
        $entry = $DB->get_record('availability_proctor_entries', [
            'accesscode' => $accesscode,
        ]);

        // If entry exists, we need to check if we have a newer one.
        if ($entry) {
            $modinfo = get_fast_modinfo($entry->courseid);
            $cminfo = $modinfo->get_cm($entry->cmid);

            $condition = \availability_proctor\condition::get_proctor_condition($cminfo);
            if (!$condition) {
                return;
            }

            $newentry = \availability_proctor\common::most_recent_entry($entry);
            if ($newentry && $newentry->id != $entry->id) {
                $entry = $newentry;
                $SESSION->availability_proctor_reset = true;
            }

            $modinfo = get_fast_modinfo($entry->courseid);
            $cminfo = $modinfo->get_cm($entry->cmid);

            // The entry is already finished or canceled, we need to reset it.
            if (!in_array($entry->status, ['new', 'scheduled', 'started'])) {
                $entry = \availability_proctor\common::create_entry($condition, $entry->userid, $cminfo);
                $SESSION->availability_proctor_reset = true;
            }
        } else {
            // If entry does not exist, we need to create a new one and redirect.
            $SESSION->availability_proctor_reset = true;
        }

        if ($entry) {
            if (isset($CFG->availability_proctor_quiz_start_url) && is_callable($CFG->availability_proctor_quiz_start_url)) {
                $urlfunction = $CFG->availability_proctor_quiz_start_url;
                $quizurl = $urlfunction($entry->courseid, $cminfo->id);
            } else if (!empty($entry->attemptid)) {
                // Attempt already exists (e.g. user came back from a re-triggered pre-check on an in-progress attempt).
                // Go straight to the attempt so the user doesn't have to click "Continue your attempt" again.
                $quizurl = new \moodle_url('/mod/quiz/attempt.php', ['attempt' => $entry->attemptid]);
            } else {
                // No attempt yet: jump to startattempt.php which will create the attempt and redirect into it.
                // handle_start_attempt() will short-circuit because the session accesscode is already set above.
                $quizurl = new \moodle_url('/mod/quiz/startattempt.php', [
                    'cmid' => $cminfo->id,
                    'sesskey' => sesskey(),
                ]);
            }
            redirect($quizurl);
            exit;
        } else {
            throw new \moodle_exception('error_no_entry_found', 'availability_proctor');
        }
    }
}
