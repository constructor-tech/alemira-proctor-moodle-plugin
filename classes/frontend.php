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
 * Frontend class
 */
class frontend extends \core_availability\frontend {

    /**
     * get_javascript_strings
     *
     * @return array
     */
    protected function get_javascript_strings() {
        $strings = [
            'title', 'error_setduration', 'duration', 'proctoring_mode', 'online_mode',
            'rules', 'offline_mode', 'identification_mode', 'auto_mode', 'allow_to_use_websites',
            'allow_to_use_books', 'allow_to_use_paper', 'allow_to_use_messengers',
            'allow_to_use_calculator', 'allow_to_use_excel', 'allow_to_use_human_assistant',
            'allow_absence_in_frame', 'allow_voices', 'allow_wrong_gaze_direction',
            'auto_rescheduling', 'enable', 'scheduling_required',
            'identification', 'face_passport_identification', 'face_identification',
            'passport_identification', 'skip_identification', 'enable_ldb',
            'is_trial', 'custom_rules', 'user_agreement_url', 'select_groups',
            'web_camera_main_view', 'web_camera_main_view_front', 'web_camera_main_view_side',
            'visible_warnings', 'scoring_params_header',
            'allowmultipledisplays', 'allowvirtualenvironment', 'checkidphotoquality',
            'calculator', 'calculator_off', 'calculator_simple', 'calculator_scientific',
            'biometry_header', 'biometry_enabled', 'biometry_skipfail', 'biometry_flow',
            'biometry_theme',
            'auxiliary_camera', 'auxiliary_camera_mode', 'auxiliary_camera_mode_photo',
            'auxiliary_camera_mode_video',
        ];

        foreach (condition::WARNINGS as $key => $value) {
            $strings[] = $key;
        }

        foreach (condition::SCORING as $key => $value) {
            $strings[] = 'scoring_'.$key;
        }

        return $strings;
    }

    /**
     * get_javascript_init_params
     *
     * @param \stdClass $course Course object
     * @param \cm_info $cm Cm
     * @param \section_info $section Section
     * @return array
     */
    protected function get_javascript_init_params($course, \cm_info $cm = null,
            \section_info $section = null) {
        global $DB;
        $rules = condition::RULES;
        $warnings = condition::WARNINGS;
        $scoring = condition::SCORING;

        $defaults = common::get_default_proctoring_settings();

        $groupdefaults = [];
        if (isset($defaults->groups)) {
            $groupdefaults = (array)$defaults->groups;
            $coursekey = (int)$course->id;
            $groupdefaults = isset($groupdefaults[$coursekey]) ? $groupdefaults[$coursekey] : [];
            $groupdefaults = array_keys((array)$groupdefaults);
        }
        $defaults->groups = $groupdefaults;;

        $groups = $DB->get_records('groups', ['courseid' => $course->id], 'name', 'id,name');

        return [$rules, $warnings, $scoring, $defaults, $groups];
    }

    /**
     * allow_add
     *
     * @param \stdClass $course Course object
     * @param \cm_info $cm Cm
     * @param \section_info $section Section
     * @return bool
     */
    protected function allow_add($course, \cm_info $cm = null,
            \section_info $section = null) {
        return true;
    }
}
