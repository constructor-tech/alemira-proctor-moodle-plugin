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

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * Decides whether a proctored launch may begin.
 *
 * This class used to create the quiz attempt as well. It no longer does: the
 * launch page hands the student off to core's startattempt.php inside the iframe,
 * and core creates the attempt after running its own pre-flight chain.
 *
 * That handoff is why so little is left here. Duplicating core's flow meant
 * duplicating its access checks, and the duplicate was silently missing the
 * pre-flight step -- so a quiz protected by a password could be started without
 * one. Everything below is limited to the two things core cannot know about: that
 * a proctoring session needs a duration, and that there is something to proctor.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class launcher {
    /**
     * Where the SDK is served from.
     *
     * Remote ES module only: there is no npm package and no version segment in
     * the URL, so this always serves the current build. It cannot go through
     * Moodle's AMD pipeline, which is why the launch template loads it with a
     * script type="module" tag instead.
     *
     * @var string
     */
    const SDK_MODULE_URL = 'https://sdk.web.proctor.constructor.app/proctoring.js';

    /**
     * Check that a proctored launch can produce a valid session.
     *
     * Only the proctoring-specific preconditions are checked here. Whether the
     * student may attempt the quiz at all -- open and close dates, the attempt
     * limit, the delay between attempts, the password -- is core's decision, made
     * inside startattempt.php once the iframe reaches it. Answering those
     * questions here as well would mean maintaining a second copy of core's rules
     * that can fall out of step with the real one.
     *
     * @param \cm_info $cm The quiz course module.
     * @param \stdClass $user The student.
     * @return void
     * @throws \moodle_exception When no proctoring session could be valid.
     */
    public static function check_can_launch(\cm_info $cm, \stdClass $user) {
        // Resolved at runtime rather than imported: the class moved into the
        // mod_quiz namespace in 4.2, and this plugin still supports older releases.
        $settingsclass = utils::quiz_settings_classname();
        $quizobj = $settingsclass::create($cm->instance, $user->id);

        // A quiz with no questions cannot be attempted, and core's own message for
        // this would arrive only after the student had sat through the SDK
        // preparation steps.
        if (!$quizobj->has_questions()) {
            throw new \moodle_exception('error_noquestions', 'availability_proctor');
        }

        // The duration JWT claim is mandatory and comes from the quiz time limit, so
        // without one there is no valid session to create. Checked here as well as
        // in the settings form, because the time limit can be removed after
        // proctoring was switched on.
        payload::duration_minutes($quizobj->get_quiz());
    }
}
