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
 * @copyright  2026 Constructor Tech
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
    // This admin page only manages global presets; personal presets are
    // owned by individual users and handled through the activity form.
    if ($existing->type !== preset::TYPE_GLOBAL) {
        throw new \moodle_exception('error_preset_not_found', 'availability_proctor');
    }
    // Form expects rules/warnings/scoring as arrays; preset::decode already does that.
    $form->set_data($existing);
} else {
    // New preset: pre-populate from the current default global preset
    // so admins start from the same baseline as the seeded default.
    $form->set_data((object) preset::get_defaults());
}

if ($data = $form->get_data()) {
    unset($data->submitbutton);
    // Empty selects -> null.
    foreach (['mode', 'identification', 'webcameramainview', 'streamspreset'] as $field) {
        if (empty($data->$field)) {
            $data->$field = null;
        }
    }
    // Calculator->rule mirroring and rule->warning suppression are applied
    // inside preset::save() via normalize_before_save().
    $newid = preset::save($data);
    redirect($listurl, get_string('preset_saved', 'availability_proctor'));
}

echo $OUTPUT->header();
$headingkey = $id ? 'preset_edit' : 'preset_create';
echo $OUTPUT->heading(get_string($headingkey, 'availability_proctor'));
$form->display();

// Live-uncheck the auto-suppressed warning when its corresponding allow-rule is toggled on.
// Map is derived from preset::RULE_WARNING_MAP so all sources (server save, server form,
// client JS) agree on the same set of pairs.
$jsmap = [];
foreach (preset::RULE_WARNING_MAP as $rkey => $wkey) {
    $jsmap['rules[' . $rkey . ']'] = 'warnings[' . $wkey . ']';
}
$PAGE->requires->js_init_code(
    'var availabilityProctorRuleWarningMap = ' . json_encode($jsmap) . ';' . "\n" .
    <<<'JS'
(function() {
    var map = availabilityProctorRuleWarningMap;
    // Moodle's advcheckbox renders TWO inputs sharing the same name — a hidden
    // fallback (value=0) and the visible checkbox. We must target the visible
    // one or .checked reads/writes apply to the hidden input and do nothing.
    function findCheckbox(name) {
        return document.querySelector('input[type="checkbox"][name="' + name + '"]');
    }
    function sync() {
        Object.keys(map).forEach(function(rname) {
            var rule = findCheckbox(rname);
            var warn = findCheckbox(map[rname]);
            if (!rule || !warn) { return; }
            if (rule.checked) {
                warn.checked = false;
            }
        });
    }
    Object.keys(map).forEach(function(rname) {
        var rule = findCheckbox(rname);
        if (rule) { rule.addEventListener('change', sync); }
    });
    sync();
})();
JS
);

echo $OUTPUT->footer();
