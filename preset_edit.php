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
 * Admin page: create / edit a single proctoring preset.
 *
 * @package    availability_proctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use availability_proctor\preset;
use availability_proctor\preset_form;

$id = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
$listurl = new moodle_url('/availability/condition/proctor/presets.php');
$pageurl = new moodle_url('/availability/condition/proctor/preset_edit.php', $id ? ['id' => $id] : []);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);

require_login();
require_capability('availability/proctor:managepresets', $context);

$PAGE->set_title(get_string('preset_edit', 'availability_proctor'));
$PAGE->navbar->includesettingsbase = true;
$PAGE->set_pagelayout('admin');
admin_externalpage_setup('availability_proctor_presets', '', []);

$form = new preset_form($pageurl);

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($id) {
    $existing = preset::get_by_id($id);
    if (!$existing) {
        throw new \moodle_exception('error_preset_not_found', 'availability_proctor');
    }
    // Form expects rules/warnings/scoring as arrays; preset::decode already does that.
    $form->set_data($existing);
}

if ($data = $form->get_data()) {
    unset($data->submitbutton);
    // Empty selects -> null.
    foreach (['mode', 'identification', 'webcameramainview', 'streamspreset'] as $field) {
        if (empty($data->$field)) {
            $data->$field = null;
        }
    }
    // Mirror the Calculator dropdown into the rule shown to the learner:
    // off -> not allowed; simple/scientific -> allowed.
    if (!isset($data->rules) || !is_array($data->rules)) {
        $data->rules = [];
    }
    $data->rules['allow_to_use_calculator'] = !empty($data->calculator) && $data->calculator !== 'off';

    // Enforce rule -> warning suppression: when an "allow" rule is on,
    // force the corresponding warning off in the saved preset.
    if (!isset($data->warnings) || !is_array($data->warnings)) {
        $data->warnings = [];
    }
    $rulewarningmap = [
        'allow_to_use_websites' => 'warning_change_active_window_on_computer',
        'allow_voices' => 'warning_voice_detected',
        'allow_wrong_gaze_direction' => 'warning_avert_eyes',
        'allow_absence_in_frame' => 'warning_no_user_in_frame',
    ];
    foreach ($rulewarningmap as $rkey => $wkey) {
        if (!empty($data->rules[$rkey])) {
            $data->warnings[$wkey] = 0;
        }
    }

    $newid = preset::save($data);
    redirect($listurl, get_string('preset_saved', 'availability_proctor'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('preset_edit', 'availability_proctor') : get_string('preset_create', 'availability_proctor'));
$form->display();

// Live-uncheck the auto-suppressed warning when its corresponding allow-rule is toggled on.
$PAGE->requires->js_init_code(<<<'JS'
(function() {
    var map = {
        'rules[allow_to_use_websites]': 'warnings[warning_change_active_window_on_computer]',
        'rules[allow_voices]': 'warnings[warning_voice_detected]',
        'rules[allow_wrong_gaze_direction]': 'warnings[warning_avert_eyes]',
        'rules[allow_absence_in_frame]': 'warnings[warning_no_user_in_frame]'
    };
    function sync() {
        Object.keys(map).forEach(function(rname) {
            var rule = document.querySelector('input[name="' + rname + '"]');
            var warn = document.querySelector('input[name="' + map[rname] + '"]');
            if (!rule || !warn) { return; }
            if (rule.checked) {
                warn.checked = false;
            }
        });
    }
    Object.keys(map).forEach(function(rname) {
        var rule = document.querySelector('input[name="' + rname + '"]');
        if (rule) { rule.addEventListener('change', sync); }
    });
    sync();
})();
JS
);

echo $OUTPUT->footer();
