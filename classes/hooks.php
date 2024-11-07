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
        global $DB, $USER;

        $html = '';

        // If there is no active attempt, do nothing.
        if (isset(state::$attempt['attempt_id'])) {
            $attemptid = state::$attempt['attempt_id'];
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
            if (!$attempt || $attempt->state != \quiz_attempt::IN_PROGRESS) {
                $html = '';
            } else {
                $html = utils::handle_proctoring_fader($attempt);
            }
        }

        if ($hook) {
            $hook->add_html($html);
        } else {
            return $html;
        }
    }
}
