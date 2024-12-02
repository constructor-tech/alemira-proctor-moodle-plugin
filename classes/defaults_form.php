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
 * @copyright  2019-2023 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class defaults_form extends \moodleform {
    protected function get_group_options() {
        global $DB;
        $courses = get_courses();
        $options = [];
        foreach ($courses as $course) {
            $groups = $DB->get_records('groups', ['courseid' => $course->id], 'name', 'id,name');
            if (empty($groups)) {
                continue;
            }
            $options[$course->id] = ['name' => $course->fullname, 'options' => $groups];
        }
        return $options;
    }

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'proctoring_settings', get_string('defaults_proctoring_settings', 'availability_proctor'));

        $mform->addElement('text', 'duration', get_string('duration', 'availability_proctor'));
        $mform->setType('duration', PARAM_INT);
        $mform->addRule('duration', null, 'numeric');

        $mform->addElement('select', 'mode', get_string('proctoring_mode', 'availability_proctor'), [
            '' => '',
            'online' => get_string('online_mode', 'availability_proctor'),
            'offline' => get_string('offline_mode', 'availability_proctor'),
            'auto' => get_string('auto_mode', 'availability_proctor'),
            'identification' => get_string('identification_mode', 'availability_proctor'),
        ]);

        $mform->addElement('select', 'identification_mode', get_string('identification', 'availability_proctor'), [
            '' => '',
            'face_and_passport' => get_string('face_passport_identification', 'availability_proctor'),
            'passport' => get_string('passport_identification', 'availability_proctor'),
            'face' => get_string('face_identification', 'availability_proctor'),
            'skip' => get_string('skip_identification', 'availability_proctor'),
        ]);

        $mform->addElement('select', 'webcameramainview', get_string('web_camera_main_view', 'availability_proctor'), [
            '' => '',
            'front' => get_string('web_camera_main_view_front', 'availability_proctor'),
            'side' => get_string('web_camera_main_view_side', 'availability_proctor'),
        ]);

        $mform->addElement('advcheckbox', 'schedulingrequired', get_string('scheduling_required', 'availability_proctor'));
        $mform->setType('schedulingrequired', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'autorescheduling', get_string('auto_rescheduling', 'availability_proctor'));
        $mform->setType('autorescheduling', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'auxiliarycamera', get_string('auxiliary_camera',  'availability_proctor'));
        $mform->setType('auxiliarycamera', PARAM_BOOL);
        $mform->addElement('select', 'auxiliarycameramode', get_string('auxiliary_camera_mode', 'availability_proctor'), [
            '' => '',
            'photo' => get_string('auxiliary_camera_mode_photo', 'availability_proctor'),
            'video' => get_string('auxiliary_camera_mode_video', 'availability_proctor'),
        ]);

        $mform->addElement('advcheckbox', 'securebrowser', get_string('enable_secure_browser',  'availability_proctor'));
        $mform->setType('securebrowser', PARAM_BOOL);
        $mform->addElement('select', 'securebrowserlevel', get_string('secure_browser_level', 'availability_proctor'), [
            '' => '',
            'basic' => get_string('secure_browser_level_basic', 'availability_proctor'),
            'medium' => get_string('secure_browser_level_medium', 'availability_proctor'),
            'high' => get_string('secure_browser_level_high', 'availability_proctor'),
        ]);
        $mform->addElement('textarea', 'allowedprocesses', get_string('allowed_processes', 'availability_proctor'));
        $mform->addElement('textarea', 'forbiddenprocesses', get_string('forbidden_processes', 'availability_proctor'));

        $mform->addElement('advcheckbox', 'allowmultipledisplays', get_string('allowmultipledisplays',  'availability_proctor'));
        $mform->setType('allowmultipledisplays', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'allowvirtualenvironment', get_string('allowvirtualenvironment',  'availability_proctor'));
        $mform->setType('allowvirtualenvironment', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'checkidphotoquality', get_string('checkidphotoquality',  'availability_proctor'));
        $mform->setType('checkidphotoquality', PARAM_BOOL);

        $mform->addElement('url', 'useragreementurl', get_string('user_agreement_url', 'availability_proctor'));
        $mform->setType('useragreementurl', PARAM_URL);

        $mform->addElement('select', 'calculator', get_string('calculator', 'availability_proctor'), [
            'off' => get_string('calculator_off', 'availability_proctor'),
            'simple' => get_string('calculator_simple', 'availability_proctor'),
            'scientific' => get_string('calculator_scientific', 'availability_proctor'),
        ]);

        $mform->addElement('header', 'proctoring_rules', get_string('rules', 'availability_proctor'));

        foreach (condition::RULES as $key => $value) {
            $mform->addElement('advcheckbox', 'rules['.$key.']', get_string($key, 'availability_proctor'));
            $mform->setType('rules['.$key.']', PARAM_BOOL);
            $mform->setDefault('rules['.$key.']', $value);
        }

        $mform->addElement('textarea', 'customrules', get_string('custom_rules', 'availability_proctor'));
        $mform->setType('customrules', PARAM_TEXT);

        $mform->addElement('header', 'visible_warnings', get_string('visible_warnings', 'availability_proctor'));
        foreach (condition::WARNINGS as $key => $value) {
            $mform->addElement('advcheckbox', 'warnings['.$key.']', get_string($key, 'availability_proctor'));
            $mform->setType('warnings['.$key.']', PARAM_BOOL);
            $mform->setDefault('warnings['.$key.']', $value);
        }

        $mform->addElement('header', 'scoring_params', get_string('scoring_params_header', 'availability_proctor'));
        foreach (condition::SCORING as $key => $field) {
            $mform->addElement('float', 'scoring['.$key.']', get_string('scoring_'.$key, 'availability_proctor'));
            $mform->addRule('scoring['.$key.']', null, 'numeric');
            $mform->setDefault('scoring['.$key.']', $field['default']);
        }

        $mform->addElement('header', 'biometry_header', get_string('biometry_header', 'availability_proctor'));

        $mform->addElement('advcheckbox', 'biometryenabled', get_string('biometry_enabled', 'availability_proctor'));
        $mform->setType('advcheckbox', PARAM_BOOL);
        $mform->addElement('advcheckbox', 'biometryskipfail', get_string('biometry_skipfail', 'availability_proctor'));
        $mform->setType('biometryskipfail', PARAM_BOOL);
        $mform->addElement('text', 'biometryflow', get_string('biometry_flow', 'availability_proctor'));
        $mform->setType('biometryflow', PARAM_TEXT);
        $mform->addElement('text', 'biometrytheme', get_string('biometry_theme', 'availability_proctor'));
        $mform->setType('biometrytheme', PARAM_TEXT);

        $mform->addElement('header', 'proctored_groups', get_string('select_groups', 'availability_proctor'));
        $coursegroups = $this->get_group_options();

        foreach ($coursegroups as $courseid => $value) {
            $coursename = $value['name'];
            $options = $value['options'];
            $elements = [];
            foreach ($options as $groupid => $group) {
                $fieldname = 'groups['.$courseid.']['.$group->id.']';
                $elements[] = $mform->createElement('checkbox', $fieldname, $group->name);
                $elements[] = $mform->createElement('html', '<br>');
            }
            $mform->addGroup($elements, 'availablefromgroup', $coursename, ' ', false);
        }

        $this->add_action_buttons();

        foreach (condition::BOOL_DEFAULTS as $key => $value) {
            $mform->setDefault($key, $value);
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['duration']) && $data['duration'] % 30 != 0) {
            $errors['duration'] = get_string('error_setduration', 'availability_proctor');
        }

        foreach (condition::SCORING as $key => $field) {
            if (!empty($data['scoring'][$key])) {
                $value = $data['scoring'][$key];

                if ($value > $field['max'] || $value < $field['min']) {
                    $error = get_string('error_not_in_range', 'availability_proctor');
                    $errors['scoring[' . $key . ']'] = sprintf($error, $field['min'], $field['max']);
                }
            }
        }

        return $errors;
    }
}
