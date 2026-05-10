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
 * Preset edit form.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing proctoring presets.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // ---- Preset identity ----
        $mform->addElement('header', 'preset_identity', get_string('preset_section_identity_meta', 'availability_proctor'));
        $mform->addElement('text', 'name', get_string('preset_name', 'availability_proctor'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'is_default', get_string('preset_is_default', 'availability_proctor'));
        $mform->setType('is_default', PARAM_BOOL);

        // ---- Exam mode ----
        $mform->addElement('header', 'preset_section_mode', get_string('preset_section_mode', 'availability_proctor'));
        $mform->addElement('select', 'mode', get_string('proctoring_mode', 'availability_proctor'), [
            'online' => get_string('online_mode', 'availability_proctor'),
            'offline' => get_string('offline_mode', 'availability_proctor'),
            'auto' => get_string('auto_mode', 'availability_proctor'),
        ]);
        $mform->addRule('mode', null, 'required', null, 'client');
        $mform->addHelpButton('mode', 'proctoring_mode', 'availability_proctor');
        $mform->addElement('advcheckbox', 'sendmanualwarningstolearner',
            get_string('sendmanualwarningstolearner', 'availability_proctor'));
        $mform->setType('sendmanualwarningstolearner', PARAM_BOOL);
        $mform->addHelpButton('sendmanualwarningstolearner', 'sendmanualwarningstolearner', 'availability_proctor');
        // Only meaningful in Live (online) proctoring mode.
        $mform->hideIf('sendmanualwarningstolearner', 'mode', 'neq', 'online');

        // ---- Identity ----
        $mform->addElement('header', 'preset_section_identity', get_string('preset_section_identity', 'availability_proctor'));
        $mform->addElement('select', 'identification', get_string('identification', 'availability_proctor'), [
            'face_and_passport' => get_string('face_passport_identification', 'availability_proctor'),
            'passport' => get_string('passport_identification', 'availability_proctor'),
            'face' => get_string('face_identification', 'availability_proctor'),
            'skip' => get_string('skip_identification', 'availability_proctor'),
        ]);
        $mform->addRule('identification', null, 'required', null, 'client');
        $mform->addHelpButton('identification', 'identification', 'availability_proctor');
        $mform->addElement('advcheckbox', 'checkidphotoquality', get_string('checkidphotoquality', 'availability_proctor'));
        $mform->setType('checkidphotoquality', PARAM_BOOL);
        $mform->addHelpButton('checkidphotoquality', 'checkidphotoquality', 'availability_proctor');
        // Only meaningful when an ID document is captured (Face and ID, Only ID).
        $mform->hideIf('checkidphotoquality', 'identification', 'in', ['face', 'skip']);
        $mform->addElement('advcheckbox', 'preliminarycheck', get_string('preliminary_check', 'availability_proctor'));
        $mform->setType('preliminarycheck', PARAM_BOOL);
        $mform->addHelpButton('preliminarycheck', 'preliminary_check', 'availability_proctor');
        // Only meaningful when the learner's face is captured.
        $mform->hideIf('preliminarycheck', 'identification', 'in', ['passport', 'skip']);

        // ---- Camera and monitoring ----
        $mform->addElement('header', 'preset_section_camera', get_string('preset_section_camera', 'availability_proctor'));
        $mform->addElement('select', 'webcameramainview', get_string('web_camera_main_view', 'availability_proctor'), [
            'front' => get_string('web_camera_main_view_front', 'availability_proctor'),
            'side' => get_string('web_camera_main_view_side', 'availability_proctor'),
        ]);
        $mform->addRule('webcameramainview', null, 'required', null, 'client');
        $mform->addHelpButton('webcameramainview', 'web_camera_main_view', 'availability_proctor');
        $mform->addElement('select', 'auxiliarycamera', get_string('auxiliary_camera', 'availability_proctor'), [
            0 => get_string('auxiliary_camera_off', 'availability_proctor'),
            1 => get_string('auxiliary_camera_on', 'availability_proctor'),
        ]);
        $mform->setType('auxiliarycamera', PARAM_BOOL);
        $mform->addHelpButton('auxiliarycamera', 'auxiliary_camera', 'availability_proctor');
        $mform->addElement('advcheckbox', 'allowroomscanauxcamera', get_string('allowroomscanauxcamera', 'availability_proctor'));
        $mform->setType('allowroomscanauxcamera', PARAM_BOOL);
        $mform->addHelpButton('allowroomscanauxcamera', 'allowroomscanauxcamera', 'availability_proctor');
        $mform->hideIf('allowroomscanauxcamera', 'auxiliarycamera', 'eq', 0);
        $mform->addElement('advcheckbox', 'allowmultipledisplays', get_string('allowmultipledisplays', 'availability_proctor'));
        $mform->setType('allowmultipledisplays', PARAM_BOOL);
        $mform->addHelpButton('allowmultipledisplays', 'allowmultipledisplays', 'availability_proctor');

        $streamspresetoptions = [];
        foreach (condition::STREAMS_PRESET_OPTIONS as $key) {
            $streamspresetoptions[$key] = get_string('streamspreset_' . $key, 'availability_proctor');
        }
        $mform->addElement('select', 'streamspreset', get_string('streamspreset', 'availability_proctor'), $streamspresetoptions);
        $mform->addRule('streamspreset', null, 'required', null, 'client');
        $mform->addHelpButton('streamspreset', 'streamspreset', 'availability_proctor');

        // ---- Secure Browser ----
        $mform->addElement('header', 'preset_section_securebrowser',
            get_string('preset_section_securebrowser', 'availability_proctor'));
        $mform->addElement('advcheckbox', 'securebrowser', get_string('enable_secure_browser', 'availability_proctor'));
        $mform->setType('securebrowser', PARAM_BOOL);
        $mform->addHelpButton('securebrowser', 'enable_secure_browser', 'availability_proctor');
        $mform->addElement('select', 'securebrowserlevel', get_string('secure_browser_level', 'availability_proctor'), [
            'basic' => get_string('secure_browser_level_basic', 'availability_proctor'),
            'medium' => get_string('secure_browser_level_medium', 'availability_proctor'),
            'high' => get_string('secure_browser_level_high', 'availability_proctor'),
        ]);
        $mform->addHelpButton('securebrowserlevel', 'secure_browser_level', 'availability_proctor');
        $mform->hideIf('securebrowserlevel', 'securebrowser', 'eq', 0);
        $mform->addElement('advcheckbox', 'allowtouseadditionalresources',
            get_string('allowtouseadditionalresources', 'availability_proctor'));
        $mform->setType('allowtouseadditionalresources', PARAM_BOOL);
        $mform->addHelpButton('allowtouseadditionalresources', 'allowtouseadditionalresources', 'availability_proctor');
        $mform->hideIf('allowtouseadditionalresources', 'securebrowser', 'eq', 0);
        $mform->addElement('textarea', 'allowedprocesses', get_string('allowed_processes', 'availability_proctor'));
        $mform->setType('allowedprocesses', PARAM_TEXT);
        $mform->addHelpButton('allowedprocesses', 'allowed_processes', 'availability_proctor');
        $mform->hideIf('allowedprocesses', 'securebrowser', 'eq', 0);
        $mform->addElement('textarea', 'forbiddenprocesses', get_string('forbidden_processes', 'availability_proctor'));
        $mform->setType('forbiddenprocesses', PARAM_TEXT);
        $mform->addHelpButton('forbiddenprocesses', 'forbidden_processes', 'availability_proctor');
        $mform->hideIf('forbiddenprocesses', 'securebrowser', 'eq', 0);
        $mform->addElement('advcheckbox', 'allowvirtualenvironment',
            get_string('allowvirtualenvironment', 'availability_proctor'));
        $mform->setType('allowvirtualenvironment', PARAM_BOOL);
        $mform->addHelpButton('allowvirtualenvironment', 'allowvirtualenvironment', 'availability_proctor');
        $mform->hideIf('allowvirtualenvironment', 'securebrowser', 'eq', 0);

        // ---- Allow during exam ----
        $mform->addElement('header', 'preset_section_rules', get_string('preset_section_rules', 'availability_proctor'));

        foreach (condition::RULES as $key => $default) {
            // Calculator availability is controlled by the Calculator dropdown
            // below, which renders the same allowance to the learner.
            if ($key === 'allow_to_use_calculator') {
                continue;
            }
            $mform->addElement('advcheckbox', 'rules[' . $key . ']', get_string($key, 'availability_proctor'));
            $mform->setType('rules[' . $key . ']', PARAM_BOOL);
            $mform->addHelpButton('rules[' . $key . ']', $key, 'availability_proctor');
        }

        $calculatoroptions = [];
        foreach (condition::CALCULATOR_OPTIONS as $key) {
            $calculatoroptions[$key] = get_string('calculator_' . $key, 'availability_proctor');
        }
        $mform->addElement('select', 'calculator', get_string('calculator', 'availability_proctor'), $calculatoroptions);
        $mform->addHelpButton('calculator', 'calculator', 'availability_proctor');

        $mform->addElement('url', 'useragreementurl', get_string('user_agreement_url', 'availability_proctor'), ['size' => 60]);
        $mform->setType('useragreementurl', PARAM_URL);
        $mform->addHelpButton('useragreementurl', 'user_agreement_url', 'availability_proctor');

        // ---- Warnings ----
        $mform->addElement('header', 'preset_section_warnings', get_string('preset_section_warnings', 'availability_proctor'));
        $mform->addElement('static', 'warnings_section_hint', '',
            \html_writer::div(get_string('warnings_help', 'availability_proctor'), 'text-muted small'));
        // Warnings that get suppressed when the corresponding allow-rule is on.
        $warningtorule = array_flip(preset::RULE_WARNING_MAP);
        foreach (condition::WARNINGS as $key => $default) {
            $mform->addElement('advcheckbox', 'warnings[' . $key . ']', get_string($key, 'availability_proctor'));
            $mform->setType('warnings[' . $key . ']', PARAM_BOOL);
            $mform->addHelpButton('warnings[' . $key . ']', $key, 'availability_proctor');
            // Suppress the warning when the matching allow-rule is on.
            if (isset($warningtorule[$key])) {
                $mform->disabledIf('warnings[' . $key . ']', 'rules[' . $warningtorule[$key] . ']', 'eq', 1);
            }
        }

        // ---- Scoring ----
        $mform->addElement('header', 'preset_section_scoring', get_string('preset_section_scoring', 'availability_proctor'));
        $mform->addElement('static', 'scoring_section_hint', '',
            \html_writer::div(get_string('scoring_section_hint', 'availability_proctor'), 'text-muted small'));
        foreach (condition::SCORING as $key => $field) {
            $mform->addElement('float', 'scoring[' . $key . ']', get_string('scoring_' . $key, 'availability_proctor'),
                ['size' => 6, 'style' => 'width: 6em']);
            // cheater_level has its own more specific help; others share one.
            $helpid = $key === 'cheater_level' ? 'scoring_cheater_level' : 'scoring';
            $mform->addHelpButton('scoring[' . $key . ']', $helpid, 'availability_proctor');
        }

        $this->add_action_buttons();
    }

    /**
     * Server-side validation of the preset form.
     *
     * @param array $data submitted form data
     * @param array $files submitted files
     * @return array map of fieldname => error string
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (condition::SCORING as $key => $field) {
            if (isset($data['scoring'][$key]) && $data['scoring'][$key] !== '' && $data['scoring'][$key] !== null) {
                $value = (float) $data['scoring'][$key];
                if ($value > $field['max'] || $value < $field['min']) {
                    $errors['scoring[' . $key . ']'] = sprintf(
                        get_string('error_not_in_range', 'availability_proctor'),
                        $field['min'], $field['max']
                    );
                }
            }
        }
        // Custom user-friendly Terms and Conditions URL message.
        if (!empty($data['useragreementurl'])) {
            $url = trim($data['useragreementurl']);
            if (!preg_match('~^https?://~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors['useragreementurl'] = get_string('error_useragreementurl', 'availability_proctor');
            }
        }
        return $errors;
    }
}
