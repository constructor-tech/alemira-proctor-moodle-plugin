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
 * @copyright  2019-2024 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

use availability_proctor\utils;


/**
 * Hook callbacks for Moodle 4.4+ hook system.
 *
 * @package    availability_proctor
 * @copyright  2019-2024 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hooks {
    /**
     * Hooks into head rendering. Adds proctoring fader/shade and accompanying javascript
     * This is used to prevent users from seeing questions before it is known that
     * attempt is viewed thorough Proctor by Constructor WebApp
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook If the hook is passed, the hook implementation will
     *                                                                     be used. If not, the legacy implementation will
     *                                                                     be used.
     * @return string|void The legacy implementation will return a string, the hook implementation will return nothing.
     */
    public static function before_standard_head_html_generation($hook = null) {
        global $DB, $USER, $PAGE;

        $html = '';

        // Quiz: existing behaviour — fader shown on in-progress quiz attempt pages.
        if (isset(state::$attempt['attempt_id'])) {
            $attemptid = state::$attempt['attempt_id'];
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
            if (!$attempt || $attempt->state != utils::quiz_attempt_classname()::IN_PROGRESS) {
                $html = '';
            } else {
                $html = utils::handle_proctoring_fader($attempt);
            }
        }

        // SCORM: fader shown on the SCORM view page when there is an active proctoring entry.
        if (!$html && !empty($PAGE->cm) && $PAGE->cm->modname === 'scorm') {
            $html = utils::handle_proctoring_fader_scorm($PAGE->cm);
        }

        // Assign: fader shown on the assign view page when there is an active proctoring entry.
        if (!$html && !empty($PAGE->cm) && $PAGE->cm->modname === 'assign') {
            $html = utils::handle_proctoring_fader_assign($PAGE->cm);
        }

        // Lockdown: hide Moodle navigation chrome on SCORM/assign pages when Proctor
        // is active. state::$lockdown is set by handle_start_attempt_scorm/assign
        // (which runs in availability_proctor_after_require_login, before this hook),
        // mirroring exactly how quiz uses state::$attempt.
        if (state::$lockdown) {
            if (!$html) {
                $html .= utils::get_lockdown_css();
            }
            $html .= utils::get_hide_chrome_js();
        }

        if ($hook) {
            $hook->add_html($html);
        } else {
            return $html;
        }
    }
}
