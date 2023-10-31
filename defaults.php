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

require_once('../../../config.php');
require_once($CFG->libdir . "/formslib.php");
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();

$url = '/availability/condition/proctor/defaults.php';
$PAGE->set_url(new moodle_url($url));
$PAGE->set_context($context);

require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_title(get_string('defaults', 'availability_proctor'));

$PAGE->requires->js_call_amd('availability_proctor/admin', 'init');

$PAGE->navbar->includesettingsbase = true;

$PAGE->set_pagelayout('admin');
admin_externalpage_setup('availability_proctor_defaults', '', []);

$form = new \availability_proctor\defaults_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url($url));
} else {
    $data = $form->get_data();
    if ($data) {
        unset($data->submitbutton);

        // Empty selects are NULLed.
        if (empty($data->mode)) {
            $data->mode = null;
        }
        if (empty($data->identification_mode)) {
            $data->identification_mode = null;
        }
        if (empty($data->webcameramainview)) {
            $data->webcameramainview = null;
        }

        \availability_proctor\common::set_default_proctoring_settings($data);
    } else {
        $defaults = \availability_proctor\common::get_default_proctoring_settings();
        $form->set_data($defaults);
    }
}

// Header.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('defaults', 'availability_proctor'));

$form->display();

// Footer.
echo $OUTPUT->footer();


