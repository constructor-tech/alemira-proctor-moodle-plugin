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
        if (class_exists('\mod_quiz\quiz_settings')) {
            return '\mod_quiz\quiz_settings';
        }

        // Before 4.2 the class is `quiz`, declared in attemptlib.php rather than
        // under mod/quiz/classes/ -- so Moodle's autoloader never finds it and the
        // name alone is not enough. Returning it unloaded made every caller fatal on
        // first use, which on the SDK path surfaced as a session that reached
        // "the exam is ending" and stopped: the redirect is built here, and the
        // error left it empty. Measured on 3.11.18.
        require_once(self::quiz_attemptlib_path());

        return 'quiz';
    }

    /**
     * The quiz row as Moodle applies it to one learner.
     *
     * User and group overrides can give a learner a longer time limit or a
     * different open or close date. Anything sent to Constructor Proctor must use
     * these values, or Proctor is told the learner has less time than Moodle
     * gives them.
     *
     * @param int $quizid Quiz id.
     * @param int $userid The learner.
     * @return \stdClass Quiz row with the learner's overrides applied.
     */
    public static function quiz_for_user(int $quizid, int $userid): \stdClass {
        return self::quiz_settings_classname()::create($quizid, $userid)->get_quiz();
    }

    /**
     * Returns the correct quiz attempt class name for this Moodle version.
     *
     * @return string Fully qualified class name
     */
    public static function quiz_attempt_classname() {
        if (class_exists('\mod_quiz\quiz_attempt')) {
            return '\mod_quiz\quiz_attempt';
        }

        // Same as above: pre-4.2 this class lives in attemptlib.php and is not
        // autoloadable.
        require_once(self::quiz_attemptlib_path());

        return 'quiz_attempt';
    }

    /**
     * Path to mod/quiz/attemptlib.php.
     *
     * Held in one place so the two helpers above cannot disagree about it.
     *
     * @return string
     */
    protected static function quiz_attemptlib_path() {
        global $CFG;

        return $CFG->dirroot . '/mod/quiz/attemptlib.php';
    }

    /**
     * Queues the fader AMD module and returns a hidden marker element carrying
     * its config. $formdata includes a signed JWT, so combined with the
     * strings it can exceed js_call_amd()'s ~1024-character argument-size
     * guidance (Moodle logs a debugging warning past that). The data travels
     * via a data-attribute instead, per Moodle's own suggestion in that
     * warning; the AMD module reads it back off the DOM.
     *
     * @param array $strings {awaitingProctoring, instructions, reset}
     * @param array|null $formdata {action, method, token}
     * @param bool $reset
     * @return string HTML snippet to append to the page output
     */
    private static function fader_amd_markup($strings, $formdata, $reset) {
        global $PAGE;

        $config = json_encode([
            'strings' => $strings,
            'formdata' => $formdata,
            'reset' => $reset,
        ]);

        $PAGE->requires->js_call_amd('availability_proctor/fader', 'init');

        return \html_writer::tag('div', '', [
            'id' => 'availability-proctor-fader-config',
            'data-config' => $config,
            'style' => 'display:none',
        ]);
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

        // Under the SDK model the chrome is all this function contributes. A learner
        // must see the same stripped-down exam page on either model -- SDK mode is
        // offered as a second option to customers whose browsers refuse the Webapp
        // flow's cookies, not as a different experience -- so the lockdown above is
        // shared, and everything below it is not.
        //
        // What follows is Webapp machinery and actively breaks the SDK flow: it calls
        // common::create_entry(), writing an availability_proctor_entries row for a
        // session the SDK already tracks in its own table, and renders the Webapp
        // hand-off form. The SDK serves the quiz inside the launch page's iframe, so
        // that form lands on top of the questions and sends the learner back to a
        // "Before the exam" screen they have already completed.
        //
        // Measured on 2026-08-18: entries row written at 13:47:01 against attempt 596
        // created at 13:47:00, with the session already bound and active.
        if (config::is_sdk_model()) {
            return $output;
        }

        $entry = common::create_entry($condition, $USER->id, $cm);

        $sessionaccesscode = session_cache::get_accesscode();
        if (!empty($sessionaccesscode) && $entry->accesscode != $sessionaccesscode) {
            session_cache::clear_accesscode();
            session_cache::set_reset();
        }

        $timebracket = common::get_timebracket_for_cm('quiz', $cm, $USER->id);
        $lang = current_language();

        $client = new client($condition);
        // This head runs on every page of an attempt that has already started,
        // so it must not refuse one begun before a time limit was required.
        $data = $client->exam_data($course, $cm, true, $USER->id);
        $userdata = $client->user_data($USER, $lang);

        $timedata = $client->time_data($timebracket);
        $starturl = self::generate_start_url($entry, $USER);
        $attemptdata = $client->attempt_data($entry->accesscode, $starturl);

        $data = array_merge($data, $userdata, $timedata, $attemptdata);

        $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);
        $attemptinprogess = $attempt && $attempt->state == self::quiz_attempt_classname()::IN_PROGRESS;

        if ($entryisactive || $attemptinprogess) {
            // We have to pass formdata in any case because exam can be opened outside iframe.
            $formdata = $client->get_form('start', $data);
            $entryreset = session_cache::is_reset();

            // Our entry is active, we are showing the user a fader.
            $strings = [
                'awaitingProctoring' => get_string('fader_awaiting_proctoring', 'availability_proctor'),
                'instructions' => get_string('fader_instructions', 'availability_proctor'),
                'reset' => get_string('fader_reset', 'availability_proctor'),
            ];
            $output .= self::fader_amd_markup($strings, $formdata, $entryreset);
        }

        return $output;
    }

    /**
     * Returns a <script> block that hides Moodle navigation chrome via JS.
     * Runs after DOMContentLoaded and again after short delays to override
     * any Moodle JS that reopens the drawer.
     *
     * @return string <style> and <script> blocks
     */
    public static function get_hide_chrome_js() {
        // CSS injected immediately via a <style> element — no body class required,
        // no DOMContentLoaded wait. Covers first paint before any JS runs.
        $immediatecss =
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

        return '<style>' . $immediatecss . '</style>' .
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

    /**
     * CSS injected into the head during a proctored quiz attempt. Hides Moodle
     * chrome — top header, left navigation drawer, and the section breadcrumb
     * above the quiz title — so the learner only sees the quiz itself.
     *
     * Scoped to `body.path-mod-quiz` as a safety net (the hook already only runs
     * on in-progress proctored attempts, but the class scoping prevents accidental
     * application if it ever fires elsewhere).
     *
     * Client-side lockdown only — a determined user can still navigate via a direct
     * URL. The MutationObserver in get_hide_chrome_js() re-hides elements on every
     * DOM change; keep the selector list tight to limit observer callback cost on
     * content-heavy pages.
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
            /* Left navigation drawers only. Keep the right block drawer because
               it contains Quiz navigation during an attempt. */
            body.path-mod-quiz #theme_boost-drawers-courseindex,
            body.path-mod-quiz #theme_boost-drawers-primary,
            body.path-mod-quiz #nav-drawer,
            body.path-mod-quiz .drawer-left,
            body.path-mod-quiz .drawer-left-toggle,
            body.path-mod-quiz button[data-target="theme_boost-drawers-courseindex"],
            body.path-mod-quiz button[data-target="theme_boost-drawers-primary"],
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
        global $DB, $OUTPUT;
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

        // Exemption, checked for BOTH models and therefore here rather than inside
        // handle_start_attempt_sdk(), where it used to live.
        //
        // It was SDK-only because that is where it was needed to make the flow work
        // at all; on a Webapp site the capability existed, had a lang string, could
        // be granted -- and did nothing, so a role built to skip proctoring was
        // still sent to the WebApp. Nothing about the capability is model-specific,
        // and one check covering both is one fewer place to forget.
        //
        // Quiz only, matching what the capability's own description promises.
        // handle_start_attempt_scorm() and handle_start_attempt_assign() do not
        // check it: widening it to them is a larger behaviour change than this is,
        // and the string does not offer it.
        if (
            has_capability(
                'availability/proctor:exemptproctoring',
                \context_module::instance($cminfo->id),
                $user
            )
        ) {
            return;
        }

        // Under the SDK model the student goes to this plugin's own launch page,
        // which runs the Constructor SDK and embeds the attempt in a same-origin
        // iframe, instead of being posted to a Constructor-hosted page.
        //
        // Everything below this block is the Webapp flow and is reached unchanged
        // whenever the site is not on the SDK model -- which is every site that
        // upgraded from an earlier release. config::is_sdk_model() is tested first
        // for that reason: on a Webapp site the cost of this branch is one already
        // cached config read, and nothing else here is evaluated.
        //
        // Placed after the three gates above because each one is needed: gate 1
        // produces the condition the exam settings come from, gate 2 keeps teacher
        // previews unproctored under both models (db/events.php wires
        // attempt_preview_started to the same observer, which relies on it), and
        // gate 3 is group scoping, so that if the known 'groups' defect is ever
        // repaired both models start honouring it in the same release.
        //
        // Placed before the accesscode block below because that block is Webapp
        // machinery -- entry.php sets proctor_accesscode when the student returns
        // from the WebApp -- and it ends in an unconditional return. A branch after
        // it would be unreachable for any student still carrying an accesscode, and
        // would mint an unused Webapp entry row on every SDK launch.
        if (config::is_sdk_model()) {
            static::handle_start_attempt_sdk($cminfo, $user);

            // Return, always. handle_start_attempt_sdk() either redirects to the
            // launch page -- redirect() exits on its own -- or returns to let the
            // launch page's own iframe through to core's startattempt.php. In the
            // second case there is nothing further for this function to do, and
            // falling through would run the Webapp flow underneath: it mints an
            // availability_proctor_entries row and renders the Webapp redirect
            // form, so the iframe receives that form instead of the quiz, no
            // attempt is ever created, and the SDK later reports examFinished
            // against a session that never bound.
            //
            // Measured on 2026-08-18: entries row id=1 was written at the exact
            // second the iframe requested startattempt.php, and the 2712-byte
            // reply carried no stylesheet or script request -- the signature of
            // the redirect template rather than a quiz page.
            //
            // The plugin this was ported from could not hit this: its lib.php
            // gate had no Webapp flow after the branch, so returning was implicit.
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
        // Continuing an attempt also passes through startattempt.php; only a
        // new attempt needs the quiz time limit.
        $attemptrunning = (bool) quiz_get_user_attempt_unfinished($cminfo->instance, $user->id);
        $data = $client->exam_data($course, $cminfo, $attemptrunning, $user->id);
        $userdata = $client->user_data($user, $lang);
        $timedata = $client->time_data($timebracket);
        $attemptdata = $client->attempt_data($entry->accesscode, $starturl);

        $data = array_merge($data, $userdata, $timedata, $attemptdata);

        $formdata = $client->get_form('start', $data);

        $pagetitle = get_string(
            'redirecting_to_proctor',
            'availability_proctor',
            get_string('pluginname', 'availability_proctor')
        );
        $gobuttonlabel = get_string('proctor_go_to_system', 'availability_proctor');

        echo $OUTPUT->render_from_template('availability_proctor/redirect', [
            'pagetitle' => $pagetitle,
            'gobuttonlabel' => $gobuttonlabel,
            'action' => $formdata['action'],
            'method' => $formdata['method'],
            'hastoken' => isset($formdata['token']),
            'token' => $formdata['token'] ?? '',
        ]);
        die();
    }

    /**
     * Send a student starting a proctored quiz to the SDK launch page.
     *
     * Returns instead of redirecting in the one case where the student should
     * reach core's startattempt.php directly: this request is the launch page's
     * own iframe handing off. Otherwise it redirects and does not return --
     * redirect() exits by itself. (Exemption is the caller's gate, alongside the
     * other three, because it applies to both models.)
     *
     * This runs before the attempt exists, which is the whole point: the launch
     * page deliberately does not create the attempt either. The iframe loads
     * core's startattempt.php so the pre-flight chain (quiz password, Safe Exam
     * Browser) runs as it would for any other quiz, and the session binds to the
     * attempt core creates. Redirecting after the attempt existed would leave a
     * running timer on an exam the student has not seen -- the same reason this
     * plugin dispatches on $SCRIPT rather than a page URL (NTG-182).
     *
     * @param \cm_info $cminfo Course module info for the quiz.
     * @param \stdClass $user The student.
     * @return void
     */
    protected static function handle_start_attempt_sdk($cminfo, $user) {
        // The exemptproctoring check used to be here, and is now in the caller,
        // which reaches both models. It was checked here because in the plugin the
        // SDK flow came from it belonged to the quiz access rule's
        // prevent_access(), which was not carried across -- but nothing about it is
        // SDK-specific, and leaving it here left the Webapp flow with a capability
        // that silently did nothing.

        // Let the request through when this student already has a live session for
        // this quiz and either the launch page's iframe is handing off, or the
        // request belongs to the separately authenticated Constructor Secure
        // Browser Moodle session. Both routes are server-bound to the same SDK row;
        // an ordinary top-level browser tab has neither signal and still redirects.
        //
        // Without this the iframe's request would arrive here, be redirected back to
        // the launch page, and loop forever with no attempt ever created.
        //
        // The session row is the signal because it is server-side state we own. A
        // marker in the URL cannot work: startattempt.php reads only cmid, forcenew
        // and page, and the pre-flight form rebuilds its action from
        // start_attempt_url() casting every hidden field to PARAM_INT, so nothing of
        // ours survives a quiz-password round trip. Nor can it be forged from the
        // browser -- reaching STATE_ACTIVE requires token.php to have signed a JWT
        // for this student.
        //
        // Testing for the absence of an attempt instead would not work either:
        // get_open_for_quiz() filters on STATES_OPEN and a session stays open after
        // binding, so on a later page load the attempt exists and the loop returns.
        //
        // The frame check is fail-open on purpose: is_top_level_request() counts
        // only an explicit Sec-Fetch-Dest: document, so a proxy that strips the
        // header passes through rather than looping. Being wrong in that direction
        // merely sends the student to the launch page.
        $proctorsession = session::get_open_for_quiz((int) $cminfo->instance, (int) $user->id);

        $isnativebrowser = $proctorsession !== null
            && session_cache::is_sdk_secure_browser_session((int) $proctorsession->id);

        if (
            $proctorsession !== null
                && $proctorsession->state === session::STATE_ACTIVE
                && (!session::is_top_level_request() || $isnativebrowser)
        ) {
            // The active session plus the request route proves that this is either
            // the launch iframe or its authenticated Secure Browser replacement.
            // Hide Moodle's site and course navigation on core's pre-flight page
            // just as we do once the attempt exists. The quiz block drawer is
            // deliberately preserved so Quiz navigation remains usable during
            // the questions.
            state::$lockdown = true;
            return;
        }

        redirect(new \moodle_url('/availability/condition/proctor/launch.php', [
            'cmid' => $cminfo->id,
        ]));
    }

    /**
     * Capability that marks a user as staff for an assign or SCORM activity.
     *
     * The quiz equivalent of is_preview_user(): staff open the activity to grade,
     * review or configure it, not to sit it, so proctoring must not apply to them.
     * Both capabilities belong to teacher, editingteacher and manager by default,
     * and never to student.
     */
    const STAFF_CAPABILITIES = [
        'assign' => 'mod/assign:grade',
        'scorm' => 'mod/scorm:viewreport',
    ];

    /**
     * Whether the user manages this assign or SCORM activity rather than sits it.
     *
     * Without this a teacher opening a proctored assignment to grade it was sent to
     * Proctor, and the fader covered every page of the activity, the settings form
     * included. Group membership alone cannot tell them apart: with no groups set,
     * user_in_proctored_groups() is true for everyone.
     *
     * @param \cm_info $cminfo Course module info.
     * @param int $userid The user.
     * @return bool True when proctoring must not apply to this user.
     */
    public static function is_activity_staff(\cm_info $cminfo, int $userid): bool {
        if (!isset(self::STAFF_CAPABILITIES[$cminfo->modname])) {
            return false;
        }

        return has_capability(
            self::STAFF_CAPABILITIES[$cminfo->modname],
            \context_module::instance($cminfo->id),
            $userid
        );
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

        // Staff grading or configuring the activity are not sitting it.
        if (self::is_activity_staff($cminfo, (int) $USER->id)) {
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

        $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);

        // Always inject chrome-hiding JS when user is on a proctored SCORM page.
        $output .= self::get_hide_chrome_js();

        if ($entryisactive) {
            $formdata = $client->get_form('start', $data);
            $entryreset = session_cache::is_reset();

            $strings = [
                'awaitingProctoring' => get_string('fader_awaiting_proctoring', 'availability_proctor'),
                'instructions' => get_string('fader_instructions', 'availability_proctor'),
                'reset' => get_string('fader_reset', 'availability_proctor'),
            ];
            $output .= self::fader_amd_markup($strings, $formdata, $entryreset);
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
        global $DB, $OUTPUT;

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

        // Staff grading or configuring the activity are not sitting it.
        if (self::is_activity_staff($cminfo, (int) $user->id)) {
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

        $formdata = $client->get_form('start', $data);

        $pagetitle = get_string(
            'redirecting_to_proctor',
            'availability_proctor',
            get_string('pluginname', 'availability_proctor')
        );
        $gobuttonlabel = get_string('proctor_go_to_system', 'availability_proctor');

        echo $OUTPUT->render_from_template('availability_proctor/redirect', [
            'pagetitle' => $pagetitle,
            'gobuttonlabel' => $gobuttonlabel,
            'action' => $formdata['action'],
            'method' => $formdata['method'],
            'hastoken' => isset($formdata['token']),
            'token' => $formdata['token'] ?? '',
        ]);
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
        global $DB, $OUTPUT;

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

        // Staff grading or configuring the activity are not sitting it.
        if (self::is_activity_staff($cminfo, (int) $user->id)) {
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

        $formdata = $client->get_form('start', $data);

        $pagetitle = get_string(
            'redirecting_to_proctor',
            'availability_proctor',
            get_string('pluginname', 'availability_proctor')
        );
        $gobuttonlabel = get_string('proctor_go_to_system', 'availability_proctor');

        echo $OUTPUT->render_from_template('availability_proctor/redirect', [
            'pagetitle' => $pagetitle,
            'gobuttonlabel' => $gobuttonlabel,
            'action' => $formdata['action'],
            'method' => $formdata['method'],
            'hastoken' => isset($formdata['token']),
            'token' => $formdata['token'] ?? '',
        ]);
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

        // Staff grading or configuring the activity are not sitting it.
        if (self::is_activity_staff($cminfo, (int) $USER->id)) {
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

        $entryisactive = in_array($entry->status, ['started', 'scheduled', 'new']);

        // Always inject chrome-hiding JS when user is on a proctored assign page.
        $output .= self::get_hide_chrome_js();

        if ($entryisactive) {
            $formdata = $client->get_form('start', $data);
            $entryreset = session_cache::is_reset();

            $strings = [
                'awaitingProctoring' => get_string('fader_awaiting_proctoring', 'availability_proctor'),
                'instructions' => get_string('fader_instructions', 'availability_proctor'),
                'reset' => get_string('fader_reset', 'availability_proctor'),
            ];
            $output .= self::fader_amd_markup($strings, $formdata, $entryreset);
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
            } else {
                // Land on the quiz view page instead of creating the attempt here. The entry
                // iframe is loaded by the Proctor web app while the learner is still in the
                // system check, so jumping into startattempt.php at this point would create the
                // attempt (and start the exam timer) before proctoring begins. The attempt is
                // created only when the user clicks "Attempt quiz" after passing the check;
                // handle_start_attempt() short-circuits on the accesscode stored above.
                $quizurl = new \moodle_url('/mod/quiz/view.php', ['id' => $cminfo->id]);
            }
            redirect($quizurl);
            exit;
        } else {
            throw new \moodle_exception('error_no_entry_found', 'availability_proctor');
        }
    }
}
