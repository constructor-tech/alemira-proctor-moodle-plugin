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
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {

    /**
     * get_javascript_strings
     *
     * @return array
     */
    protected function get_javascript_strings() {
        global $PAGE;
        $PAGE->requires->string_for_js('showmore', 'core_form');
        $PAGE->requires->string_for_js('showless', 'core_form');
        $PAGE->requires->string_for_js('savechanges', 'core');
        $PAGE->requires->string_for_js('cancel', 'core');

        $strings = [
            'title', 'proctoring_mode', 'online_mode', 'offline_mode', 'auto_mode',
            'rules',
            'allow_to_use_websites', 'allow_to_use_books', 'allow_to_use_paper', 'allow_to_use_messengers',
            'allow_to_use_calculator', 'allow_to_use_excel', 'allow_to_use_human_assistant',
            'allow_absence_in_frame', 'allow_voices', 'allow_wrong_gaze_direction',
            'auto_rescheduling', 'enable', 'scheduling_required',
            'identification', 'face_passport_identification', 'face_identification',
            'passport_identification', 'skip_identification', 'enable_secure_browser',
            'is_trial', 'custom_rules', 'user_agreement_url', 'select_groups',
            'web_camera_main_view', 'web_camera_main_view_front', 'web_camera_main_view_side',
            'secure_browser_level',
            'secure_browser_level_basic', 'secure_browser_level_medium', 'secure_browser_level_high',
            'allowtouseadditionalresources',
            'allowmultipledisplays', 'allowvirtualenvironment', 'checkidphotoquality',
            'calculator', 'streamspreset', 'preliminary_check',
            'auxiliary_camera', 'auxiliary_camera_off', 'auxiliary_camera_on',
            'allowed_processes', 'forbidden_processes', 'processes_list_hint',
            'sendmanualwarningstolearner', 'allowroomscanauxcamera',
            'preset_section_mode', 'preset_section_identity', 'preset_section_camera',
            'preset_section_securebrowser', 'preset_section_rules',
            'preset_section_warnings', 'preset_section_scoring', 'preset_section_exam',
            'load_preset', 'save_personal_preset', 'save_personal_preset_prompt',
            'save_personal_preset_hint', 'global_presets', 'personal_presets',
            'no_presets', 'preset_delete_confirm', 'preset_saved', 'delete',
            'error_preset_name_required', 'error_useragreementurl',
            'loaded_preset', 'loaded_preset_none',
            // Inline hints shown under each field in the exam form.
            'proctoring_mode_help', 'sendmanualwarningstolearner_help',
            'identification_help', 'checkidphotoquality_help', 'preliminary_check_help',
            'web_camera_main_view_help', 'auxiliary_camera_help',
            'allowroomscanauxcamera_help', 'allowmultipledisplays_help',
            'streamspreset_help',
            'enable_secure_browser_help', 'secure_browser_level_help',
            'allowtouseadditionalresources_help', 'allowed_processes_help',
            'forbidden_processes_help', 'allowvirtualenvironment_help',
            'calculator_help', 'user_agreement_url_help',
            'is_trial_help', 'custom_rules_help',
            'warnings_help', 'scoring_help', 'scoring_section_hint', 'scoring_cheater_level_help',
            'allow_to_use_websites_help', 'allow_to_use_books_help',
            'allow_to_use_paper_help', 'allow_to_use_messengers_help',
            'allow_to_use_excel_help', 'allow_to_use_human_assistant_help',
            'allow_absence_in_frame_help', 'allow_voices_help',
            'allow_wrong_gaze_direction_help',
            // Per-warning hints rendered next to suppressible warnings in the
            // activity-edit form (form.js shows hint icons on these four).
            'warning_change_active_window_on_computer_help', 'warning_voice_detected_help',
            'warning_avert_eyes_help', 'warning_no_user_in_frame_help',
        ];

        foreach (condition::WARNINGS as $key => $value) {
            $strings[] = $key;
        }

        foreach (condition::SCORING as $key => $value) {
            $strings[] = 'scoring_'.$key;
        }

        foreach (condition::STREAMS_PRESET_OPTIONS as $key) {
            $strings[] = 'streamspreset_'.$key;
        }

        foreach (condition::CALCULATOR_OPTIONS as $key) {
            $strings[] = 'calculator_'.$key;
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
        global $DB, $USER, $CFG;

        $defaultpreset = preset::get_default();
        $defaults = $defaultpreset ? clone $defaultpreset : new \stdClass();
        $defaults->groups = [];

        $groups = $DB->get_records('groups', ['courseid' => $course->id], 'name', 'id,name');

        // Build preset lists for the load-preset picker. Override `name` with
        // the localized display name so the picker shows the seeded preset
        // titles in the user's language; storage stays on the canonical key.
        $globalpresets = array_values(preset::get_all_global());
        foreach ($globalpresets as $gp) {
            $gp->name = preset::display_name($gp);
        }
        $userpresets = array_values(preset::get_user_presets($USER->id));
        foreach ($userpresets as $up) {
            $up->name = preset::display_name($up);
        }

        $context = [
            'ajaxurl' => $CFG->wwwroot . '/availability/condition/proctor/ajax_preset.php',
            'sesskey' => sesskey(),
            'courseid' => (int) $course->id,
            'global_presets' => $globalpresets,
            'user_presets' => $userpresets,
            'hidden_fields' => array_values(brand::HIDDEN_FORM_FIELDS),
        ];

        return [
            condition::RULES,
            condition::WARNINGS,
            condition::SCORING,
            condition::STREAMS_PRESET_OPTIONS,
            $defaults,
            $groups,
            $context,
        ];
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
