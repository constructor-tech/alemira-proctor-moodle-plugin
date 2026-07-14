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
        global $DB, $USER, $PAGE;

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

        $sessionaccesscode = session_cache::get_accesscode();
        if (!empty($sessionaccesscode) && $entry->accesscode != $sessionaccesscode) {
            session_cache::clear_accesscode();
            session_cache::set_reset();
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
            $entryreset = session_cache::is_reset();

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
    /**
     * Returns a <script> block that hides Moodle navigation chrome via JS.
     * Runs after DOMContentLoaded and again after short delays to override
     * any Moodle JS that reopens the drawer.
     */
    public static function get_hide_chrome_js() {
        // CSS injected immediately via a <style> element — no body class required,
        // no DOMContentLoaded wait. Covers first paint before any JS runs.
        $immediate_css =
            '#nav-drawer,[data-region="drawer"],[data-region="fixed-drawer"],' .
            '.drawer,.drawer-left,.drawer-left-toggle,.drawer-toggles,' .
            'button[data-toggler="drawers"],.drawercontent,' .
            '#page-header,header#page-header,header.navbar,nav.navbar,.navbar,' .
            '#page-navbar,.secondary-navigation,.tertiary-navigation,' .
            '.activity-navigation,[data-region="blocks-column"]' .
            '{display:none!important;visibility:hidden!important}' .
            '#page,#page-wrapper,#page-content,.main-inner' .
            '{margin-left:0!important;padding-left:0!important;' .
            'margin-top:0!important;padding-top:0!important;' .
            'width:100%!important;max-width:100%!important}';

        return '<style>' . $immediate_css . '</style>' .
            '<script>(function(){' .
            'var HIDE=[' .
                '"#nav-drawer","[data-region=\'drawer\']","[data-region=\'fixed-drawer\']",' .
                '".drawer",".drawer-left",".drawer-left-toggle",".drawer-toggles",' .
                '"button[data-toggler=\'drawers\']",".drawercontent",' .
                '"#page-header","header#page-header","header.navbar","nav.navbar",".navbar",' .
                '"#page-navbar",".secondary-navigation",".tertiary-navigation",' .
                '".activity-navigation","[data-region=\'blocks-column\']"' .
            '];' .
            'var FIX=["#page","#page-wrapper","#page-content",".main-inner"];' .
            'function hide(){' .
                'HIDE.forEach(function(s){' .
                    'try{document.querySelectorAll(s).forEach(function(el){' .
                        'el.style.setProperty("display","none","important");' .
                        'el.style.setProperty("visibility","hidden","important");' .
                    '});}catch(e){}' .
                '});' .
                'if(document.body){' .
                    'document.body.classList.remove("drawer-open-left","drawer-open-right");' .
                '}' .
                'FIX.forEach(function(s){' .
                    'try{var el=document.querySelector(s);if(el){' .
                        'el.style.setProperty("margin-left","0","important");' .
                        'el.style.setProperty("padding-left","0","important");' .
                        'el.style.setProperty("margin-top","0","important");' .
                        'el.style.setProperty("padding-top","0","important");' .
                        'el.style.setProperty("width","100%","important");' .
                        'el.style.setProperty("max-width","100%","important");' .
                    '}}catch(e){}' .
                '});' .
            '}' .
            'hide();' .
            'document.addEventListener("DOMContentLoaded",function(){' .
                'hide();' .
                'setTimeout(hide,300);setTimeout(hide,800);setTimeout(hide,2000);' .
                'var obs=new MutationObserver(function(){hide();});' .
                'obs.observe(document.documentElement,{childList:true,subtree:true});' .
            '});' .
            '})()</script>';
    }

    // Client-side CSS/JS lockdown only — a determined user can still navigate via direct URL.
    // The MutationObserver in get_hide_chrome_js() re-hides elements on every DOM change;
    // keep the selector list tight to limit observer callback cost on content-heavy pages.
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
            /* SCORM lockdown — mirrors the quiz rules above */
            body.path-mod-scorm #page-header,
            body.path-mod-scorm header#page-header,
            body.path-mod-scorm header.navbar,
            body.path-mod-scorm nav.navbar,
            body.path-mod-scorm .navbar-fixed-top,
            body.path-mod-scorm #page-navbar,
            body.path-mod-scorm nav.breadcrumb-nav,
            body.path-mod-scorm .breadcrumb-nav,
            body.path-mod-scorm .breadcrumb,
            body.path-mod-scorm .secondary-navigation,
            body.path-mod-scorm nav.moremenu,
            body.path-mod-scorm [data-region="drawer"],
            body.path-mod-scorm [data-region="fixed-drawer"],
            body.path-mod-scorm #nav-drawer,
            body.path-mod-scorm .drawer,
            body.path-mod-scorm .drawer-left,
            body.path-mod-scorm .drawer-toggles,
            body.path-mod-scorm button[data-toggler="drawers"],
            body.path-mod-scorm .drawercontent,
            body.path-mod-scorm .tertiary-navigation,
            body.path-mod-scorm .btn-back,
            body.path-mod-scorm a.back-button,
            body.path-mod-scorm .activity-header .back,
            body.path-mod-scorm .activity-nav,
            body.path-mod-scorm .activity-navigation,
            body.path-mod-scorm [data-action="back"],
            body.path-mod-scorm a[aria-label*="Back" i],
            body.path-mod-scorm a[aria-label*="ack to" i],
            body.path-mod-scorm .previouslink,
            body.path-mod-scorm .continuelink {
                display: none !important;
            }
            body.path-mod-scorm #page,
            body.path-mod-scorm #page-wrapper,
            body.path-mod-scorm #page-content,
            body.path-mod-scorm.drawer-open-left #page,
            body.path-mod-scorm.pagelayout-incourse #page {
                margin-left: 0 !important;
                margin-top: 0 !important;
                padding-left: 0 !important;
                padding-top: 0 !important;
            }
            /* Assign lockdown — mirrors the quiz/scorm rules above */
            body.path-mod-assign #page-header,
            body.path-mod-assign header#page-header,
            body.path-mod-assign header.navbar,
            body.path-mod-assign nav.navbar,
            body.path-mod-assign .navbar-fixed-top,
            body.path-mod-assign #page-navbar,
            body.path-mod-assign nav.breadcrumb-nav,
            body.path-mod-assign .breadcrumb-nav,
            body.path-mod-assign .breadcrumb,
            body.path-mod-assign .secondary-navigation,
            body.path-mod-assign nav.moremenu,
            body.path-mod-assign [data-region="drawer"],
            body.path-mod-assign [data-region="fixed-drawer"],
            body.path-mod-assign #nav-drawer,
            body.path-mod-assign .drawer,
            body.path-mod-assign .drawer-left,
            body.path-mod-assign .drawer-toggles,
            body.path-mod-assign button[data-toggler="drawers"],
            body.path-mod-assign .drawercontent,
            body.path-mod-assign .tertiary-navigation,
            body.path-mod-assign .btn-back,
            body.path-mod-assign a.back-button,
            body.path-mod-assign .activity-header .back,
            body.path-mod-assign .activity-nav,
            body.path-mod-assign .activity-navigation,
            body.path-mod-assign [data-action="back"],
            body.path-mod-assign a[aria-label*="Back" i],
            body.path-mod-assign a[aria-label*="ack to" i],
            body.path-mod-assign .previouslink,
            body.path-mod-assign .continuelink {
                display: none !important;
            }
            body.path-mod-assign #page,
            body.path-mod-assign #page-wrapper,
            body.path-mod-assign #page-content,
            body.path-mod-assign.drawer-open-left #page,
            body.path-mod-assign.pagelayout-incourse #page {
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
            // Token expires in 8 hours — enough to cover any exam window.
            // Bound to this specific entry so it cannot be replayed against a different activity.
            // entry.php deletes the key immediately after use (single-use).
            $tokenvaliduntil = time() + (8 * 60 * 60);
            $urlparams['token'] = get_user_key('availability_proctor', $user->id, $entry->id, false, $tokenvaliduntil);
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
        global $DB;
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

        $accesscode = session_cache::get_accesscode();
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
                session_cache::clear_accesscode();
                session_cache::set_reset();
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
     * Proctoring fader for SCORM activities. Shows the overlay on the SCORM view page
     * while waiting for the Proctor WebApp confirmation signal.
     *
     * @param \cm_info|\stdClass $cm Course module
     * @return string HTML output for the fader overlay
     */
    public static function handle_proctoring_fader_scorm($cm) {
        global $USER;

        $courseid = $cm->course;
        $modinfo = get_fast_modinfo($courseid);
        $cminfo = $modinfo->get_cm($cm->id);
        $course = $cminfo->get_course();

        $condition = condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return '';
        }

        if (!$condition->user_in_proctored_groups($USER->id)) {
            return '';
        }

        $output = self::get_lockdown_css();

        $entry = common::create_entry($condition, $USER->id, $cminfo);

        $sessionaccesscode = session_cache::get_accesscode();
        if (!empty($sessionaccesscode) && $entry->accesscode != $sessionaccesscode) {
            session_cache::clear_accesscode();
            session_cache::set_reset();
        }

        $timebracket = common::get_timebracket_for_cm('scorm', $cminfo, $USER->id);
        $lang = current_language();

        $client = new client($condition);
        $data = $client->exam_data($course, $cminfo);
        $userdata = $client->user_data($USER, $lang);
        $timedata = $client->time_data($timebracket);
        $starturl = self::generate_start_url($entry, $USER);
        $attemptdata = $client->attempt_data($entry->accesscode, $starturl);

        $data = array_merge($data, $userdata, $timedata, $attemptdata);

        if ($condition->schedulingrequired && empty($entry->timescheduled)) {
            $data['schedule'] = true;
        }

        $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);

        // Always inject chrome-hiding JS when user is on a proctored SCORM page.
        $output .= self::get_hide_chrome_js();

        if ($entryisactive) {
            $formdata = $client->get_form('start', $data);
            $entryreset = session_cache::is_reset();

            ob_start();
            $attempt = null;
            include(dirname(__FILE__).'/../templates/proctoring_fader.php');
            $output .= ob_get_clean();
        }

        return $output;
    }

    /**
     * When a SCORM view page is accessed, check proctoring status and redirect
     * to Proctor if no valid session exists.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $cm Course module record
     * @param \stdClass $user User record
     * @return void
     */
    public static function handle_start_attempt_scorm($course, $cm, $user) {
        global $DB;

        // proctor_lockdown=1 is appended by scorm.php bridge — mark lockdown immediately
        // regardless of condition, so hooks.php can inject CSS/JS even when the CM has
        // no availability_proctor condition configured.
        if (optional_param('proctor_lockdown', 0, PARAM_INT)) {
            state::$lockdown = true;
        }

        $modinfo = get_fast_modinfo($course->id);
        $cminfo = $modinfo->get_cm($cm->id);

        $condition = condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return;
        }

        if (!$condition->user_in_proctored_groups($user->id)) {
            return;
        }

        $accesscode = session_cache::get_accesscode();

        if ($accesscode) {
            $entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);

            if ($entry && !in_array($entry->status, ['new', 'scheduled', 'started'])) {
                session_cache::clear_accesscode();
                session_cache::set_reset();
            }

            if ($entry && $entry->cmid != $cminfo->id) {
                session_cache::clear_accesscode();
                session_cache::set_reset();
            }

            // User is coming from Proctor — mark lockdown and let through.
            state::$lockdown = true;
            return;
        }

        $entry = common::create_entry($condition, $user->id, $cminfo);

        if ($entry->status === 'started') {
            // Ensure session has the accesscode so handle_proctoring_fader_scorm can show the fader.
            if (empty(session_cache::get_accesscode())) {
                session_cache::set_accesscode($entry->accesscode);
            }
            return;
        }

        $timebracket = common::get_timebracket_for_cm('scorm', $cminfo, $user->id);
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
     * When an assign view page is accessed, check proctoring status and redirect
     * to Proctor if no valid session exists.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $cm Course module record
     * @param \stdClass $user User record
     * @return void
     */
    public static function handle_start_attempt_assign($course, $cm, $user) {
        global $DB;

        // proctor_lockdown=1 is appended by assign.php bridge.
        if (optional_param('proctor_lockdown', 0, PARAM_INT)) {
            state::$lockdown = true;
        }

        $modinfo = get_fast_modinfo($course->id);
        $cminfo = $modinfo->get_cm($cm->id);

        $condition = condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return;
        }

        if (!$condition->user_in_proctored_groups($user->id)) {
            return;
        }

        $accesscode = session_cache::get_accesscode();

        if ($accesscode) {
            $entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $accesscode]);

            if ($entry && !in_array($entry->status, ['new', 'scheduled', 'started'])) {
                session_cache::clear_accesscode();
                session_cache::set_reset();
            }

            if ($entry && $entry->cmid != $cminfo->id) {
                session_cache::clear_accesscode();
                session_cache::set_reset();
            }

            state::$lockdown = true;
            return;
        }

        $entry = common::create_entry($condition, $user->id, $cminfo);

        if ($entry->status === 'started') {
            if (empty(session_cache::get_accesscode())) {
                session_cache::set_accesscode($entry->accesscode);
            }
            return;
        }

        $timebracket = common::get_timebracket_for_cm('assign', $cminfo, $user->id);
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
     * Proctoring fader for assignment activities. Shows the overlay on the assign view page
     * while waiting for the Proctor WebApp confirmation signal.
     *
     * @param \cm_info|\stdClass $cm Course module
     * @return string HTML output for the fader overlay
     */
    public static function handle_proctoring_fader_assign($cm) {
        global $USER;

        $courseid = $cm->course;
        $modinfo = get_fast_modinfo($courseid);
        $cminfo = $modinfo->get_cm($cm->id);
        $course = $cminfo->get_course();

        $condition = condition::get_proctor_condition($cminfo);
        if (!$condition) {
            return '';
        }

        if (!$condition->user_in_proctored_groups($USER->id)) {
            return '';
        }

        $output = self::get_lockdown_css();

        $entry = common::create_entry($condition, $USER->id, $cminfo);

        $sessionaccesscode = session_cache::get_accesscode();
        if (!empty($sessionaccesscode) && $entry->accesscode != $sessionaccesscode) {
            session_cache::clear_accesscode();
            session_cache::set_reset();
        }

        $timebracket = common::get_timebracket_for_cm('assign', $cminfo, $USER->id);
        $lang = current_language();

        $client = new client($condition);
        $data = $client->exam_data($course, $cminfo);
        $userdata = $client->user_data($USER, $lang);
        $timedata = $client->time_data($timebracket);
        $starturl = self::generate_start_url($entry, $USER);
        $attemptdata = $client->attempt_data($entry->accesscode, $starturl);

        $data = array_merge($data, $userdata, $timedata, $attemptdata);

        if ($condition->schedulingrequired && empty($entry->timescheduled)) {
            $data['schedule'] = true;
        }

        $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);

        // Always inject chrome-hiding JS when user is on a proctored assign page.
        $output .= self::get_hide_chrome_js();

        if ($entryisactive) {
            $formdata = $client->get_form('start', $data);
            $entryreset = session_cache::is_reset();

            ob_start();
            $attempt = null;
            include(dirname(__FILE__).'/../templates/proctoring_fader.php');
            $output .= ob_get_clean();
        }

        return $output;
    }

    /**
     * If accesscode param is provided, find entry, handle its state.
     *
     * @param string $accesscode Accesscode/SessionId value
     * @return array|void Returns ['modname'=>..., 'cmid'=>...] for SCORM/assign, void for quiz (redirect happens internally)
     */
    public static function handle_accesscode_param($accesscode) {
        global $DB, $CFG;

        // User is coming from proctor, reset is done if it was requested before.
        session_cache::clear_reset();

        session_cache::set_accesscode($accesscode);

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
                session_cache::set_reset();
            }

            $modinfo = get_fast_modinfo($entry->courseid);
            $cminfo = $modinfo->get_cm($entry->cmid);

            // The entry is already finished or canceled, we need to reset it.
            if (!in_array($entry->status, ['new', 'scheduled', 'started'])) {
                $entry = \availability_proctor\common::create_entry($condition, $entry->userid, $cminfo);
                session_cache::set_reset();
            }
        } else {
            // If entry does not exist, we need to create a new one and redirect.
            session_cache::set_reset();
        }

        if ($entry) {
            if (isset($CFG->availability_proctor_quiz_start_url) && is_callable($CFG->availability_proctor_quiz_start_url)) {
                $urlfunction = $CFG->availability_proctor_quiz_start_url;
                $quizurl = $urlfunction($entry->courseid, $cminfo->id);
            } else if ($cminfo->modname === 'scorm') {
                // Redirect to the bridge page (scorm.php) rather than directly to
                // scorm/view.php.  The bridge page runs in the real browser context
                // (Proctor's content frame, full cookies), validates the accesscode,
                // writes it to the session, then redirects to scorm/view.php.  This
                // avoids the require_login failure that occurs when the accesscode
                // redirect happens inside Proctor's sandboxed entry iframe.
                $quizurl = new \moodle_url('/availability/condition/proctor/scorm.php', [
                    'id' => $cminfo->id,
                    'proctor_accesscode' => $entry->accesscode,
                ]);
            } else if ($cminfo->modname === 'assign') {
                $quizurl = new \moodle_url('/availability/condition/proctor/assign.php', [
                    'id' => $cminfo->id,
                    'proctor_accesscode' => $entry->accesscode,
                ]);
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
