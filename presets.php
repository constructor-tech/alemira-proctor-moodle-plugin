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
 * Admin page: list of global proctoring presets.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use availability_proctor\preset;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$sort = optional_param('sort', 'name', PARAM_ALPHAEXT);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

$context = context_system::instance();
$listurl = new moodle_url('/availability/condition/proctor/presets.php');
$sortedurl = new moodle_url($listurl, ['sort' => $sort, 'dir' => $dir]);

$PAGE->set_url($listurl);
$PAGE->set_context($context);

require_login();
require_capability('availability/proctor:managepresets', $context);

$PAGE->set_title(get_string('presets', 'availability_proctor'));
$PAGE->navbar->includesettingsbase = true;
$PAGE->set_pagelayout('admin');
admin_externalpage_setup('availability_proctor_presets', '', []);

// Handle list-page actions.
if ($action && $id) {
    require_sesskey();
    switch ($action) {
        case 'setdefault':
            preset::set_default($id);
            redirect($listurl, get_string('preset_default_changed', 'availability_proctor'));
            break;

        case 'duplicate':
            $newid = preset::duplicate($id);
            redirect(new moodle_url('/availability/condition/proctor/preset_edit.php', ['id' => $newid]));
            break;

        case 'delete':
            try {
                preset::delete($id);
                redirect($listurl, get_string('preset_deleted', 'availability_proctor'));
            } catch (\moodle_exception $e) {
                redirect($listurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
            }
            break;
    }
}

$presets = preset::get_all_global($sort, $dir);
// One DB pass instead of one LIKE-scan per preset.
$usedset = preset::get_used_preset_ids();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('presets', 'availability_proctor'));

$createurl = new moodle_url('/availability/condition/proctor/preset_edit.php');
echo html_writer::div(
    $OUTPUT->single_button($createurl, get_string('preset_create', 'availability_proctor'), 'get'),
    'mb-3'
);

// Render a sortable column header. Clicking cycles ASC -> DESC on the same column,
// or sets ASC when switching to a new column. Takes the DB column to sort by and
// the header label; returns HTML.
$sortheader = function ($column, $label) use ($listurl, $sort, $dir) {
    $nextdir = 'ASC';
    $arrow = '';
    if ($sort === $column) {
        $nextdir = ($dir === 'ASC') ? 'DESC' : 'ASC';
        $arrow = ' ' . ($dir === 'ASC' ? '&#x25B2;' : '&#x25BC;');
    }
    $url = new moodle_url($listurl, ['sort' => $column, 'dir' => $nextdir]);
    return html_writer::link($url, $label) . $arrow;
};

$table = new html_table();
$table->head = [
    $sortheader('name', get_string('name')),
    get_string('preset_settings_summary', 'availability_proctor'),
    $sortheader('is_default', get_string('preset_is_default', 'availability_proctor')),
    $sortheader('is_system', get_string('preset_is_system', 'availability_proctor')),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

// Resolve a string key built from a stored preset value, falling back to the raw
// value when no such string exists. $key is the language string identifier to try,
// $raw the stored value, shown escaped when the string is missing.
//
// Preset columns hold enum-like values, but nothing guarantees the value came
// from the form: a restored preset, a direct SQL edit or a brand adding an option
// all reach this page unchecked. Without this guard such a value renders as
// [[web_camera_main_view_xxx]] and logs a debugging warning. Same approach as
// log::format_status() and log::format_reason().
$optionlabel = function (string $key, string $raw): string {
    if (!get_string_manager()->string_exists($key, 'availability_proctor')) {
        return s($raw);
    }
    return get_string($key, 'availability_proctor');
};

// Build a short human-readable summary of the main preset parameters for display
// in the preset list. Takes a decoded preset record; returns an HTML fragment.
$summary = function ($p) use ($optionlabel) {
    $items = [];

    if (!empty($p->mode)) {
        $items[] = get_string('proctoring_mode', 'availability_proctor') . ': '
            . $optionlabel($p->mode . '_mode', $p->mode);
    }
    if (!empty($p->identification)) {
        $map = [
            'face_and_passport' => 'face_passport_identification',
            'passport' => 'passport_identification',
            'face' => 'face_identification',
            'skip' => 'skip_identification',
        ];
        if (isset($map[$p->identification])) {
            $items[] = get_string('identification', 'availability_proctor') . ': '
                . get_string($map[$p->identification], 'availability_proctor');
        }
    }
    if (!empty($p->webcameramainview)) {
        $items[] = get_string('web_camera_main_view', 'availability_proctor') . ': '
            . $optionlabel('web_camera_main_view_' . $p->webcameramainview, $p->webcameramainview);
    }
    if (!empty($p->securebrowser)) {
        $label = get_string('enable_secure_browser', 'availability_proctor') . ': '
            . get_string('secure_browser_enabled', 'availability_proctor');
        if (!empty($p->securebrowserlevel)) {
            $label .= ' (' . $optionlabel(
                'secure_browser_level_' . $p->securebrowserlevel,
                $p->securebrowserlevel
            ) . ')';
        }
        $items[] = $label;
    } else {
        $items[] = get_string('enable_secure_browser', 'availability_proctor') . ': '
            . get_string('secure_browser_disabled', 'availability_proctor');
    }

    if (!empty($p->auxiliarycamera)) {
        $items[] = get_string('auxiliary_camera', 'availability_proctor') . ': '
            . get_string('auxiliary_camera_on', 'availability_proctor');
    } else {
        $items[] = get_string('auxiliary_camera', 'availability_proctor') . ': '
            . get_string('auxiliary_camera_off', 'availability_proctor');
    }

    return html_writer::alist($items, ['class' => 'm-0 pl-3']);
};

foreach ($presets as $p) {
    $editurl = new moodle_url('/availability/condition/proctor/preset_edit.php', ['id' => $p->id]);
    $actions = [];
    $actions[] = html_writer::link($editurl, get_string('edit'));

    $actions[] = html_writer::link(
        new moodle_url($listurl, ['action' => 'duplicate', 'id' => $p->id, 'sesskey' => sesskey()]),
        get_string('preset_duplicate', 'availability_proctor')
    );

    if (empty($p->is_default)) {
        $actions[] = html_writer::link(
            new moodle_url($listurl, ['action' => 'setdefault', 'id' => $p->id, 'sesskey' => sesskey()]),
            get_string('preset_set_default', 'availability_proctor')
        );
    }

    if (preset::can_delete($p, $usedset)) {
        $deleteurl = new moodle_url($listurl, ['action' => 'delete', 'id' => $p->id, 'sesskey' => sesskey()]);
        $actions[] = $OUTPUT->action_link(
            $deleteurl,
            get_string('delete'),
            new confirm_action(get_string('preset_delete_confirm', 'availability_proctor'))
        );
    }

    $table->data[] = [
        format_string(preset::display_name($p)),
        $summary($p),
        !empty($p->is_default) ? $OUTPUT->pix_icon('i/checked', '') : '',
        !empty($p->is_system) ? $OUTPUT->pix_icon('i/checked', '') : '',
        implode(' | ', $actions),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
