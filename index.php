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

require_once('../../../config.php');
require_once($CFG->libdir . "/formslib.php");
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();

require_login();
require_capability('availability/proctor:logaccess', $context);

global $PAGE;

$baseurl = '/availability/condition/proctor/index.php';

$action = optional_param('action', 'index', PARAM_ALPHA);

if ($action == 'renew') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    $force = optional_param('force', false, PARAM_TEXT);
    // Public v2.7 links do not carry a model and must remain Webapp actions.
    $model = optional_param('model', \availability_proctor\config::MODEL_WEBAPP, PARAM_ALPHA);

    if (!in_array($model, \availability_proctor\config::MODEL_OPTIONS, true)) {
        throw new \invalid_parameter_exception('Unknown proctoring model.');
    }

    try {
        $created = $model === \availability_proctor\config::MODEL_SDK
            ? \availability_proctor\session::create_new_entry($id)
            : \availability_proctor\common::reset_entry(['id' => $id], $force);
    } catch (\moodle_exception $e) {
        redirect(
            'index.php',
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if ($created) {
        redirect(
            'index.php',
            get_string('new_entry_created', 'availability_proctor'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            'index.php',
            get_string('entry_exist', 'availability_proctor'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

if ($action == 'index') {
    $PAGE->set_url(new \moodle_url($baseurl));
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('report_heading', 'availability_proctor'));

    echo $OUTPUT->header();
    echo html_writer::start_div('availability-proctor-report');
    echo $OUTPUT->heading(get_string('report_heading', 'availability_proctor'));
    echo html_writer::tag(
        'p',
        get_string('report_intro', 'availability_proctor'),
        ['class' => 'availability-proctor-report-intro']
    );
    $filters = [
        'courseid'     => optional_param('courseid', null, PARAM_INT),
        'timefinish'   => optional_param('timefinish', null, PARAM_INT),
        'moduleid'     => optional_param('moduleid', null, PARAM_INT),
        'userquery'    => optional_param('userquery', null, PARAM_TEXT),
        'status'       => optional_param('status', null, PARAM_TEXT),
    ];

    // New report links use native date inputs. Keep accepting the old array
    // parameters so bookmarks created by the public plugin continue to work.
    $fromdate = optional_param('fromdate', '', PARAM_RAW_TRIMMED);
    $todate = optional_param('todate', '', PARAM_RAW_TRIMMED);
    $fromraw = optional_param_array('from', [], PARAM_INT);
    $toraw = optional_param_array('to', [], PARAM_INT);

    if ($fromdate === '' && isset($fromraw['day'], $fromraw['month'], $fromraw['year'])) {
        $fromdate = sprintf('%04d-%02d-%02d', $fromraw['year'], $fromraw['month'], $fromraw['day']);
    }
    if ($todate === '' && isset($toraw['day'], $toraw['month'], $toraw['year'])) {
        $todate = sprintf('%04d-%02d-%02d', $toraw['year'], $toraw['month'], $toraw['day']);
    }

    foreach (['fromdate' => $fromdate, 'todate' => $todate] as $name => $value) {
        if (
            preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
                && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            $filters[$name] = $value;
        }
    }

    $page = optional_param('page', 0, PARAM_INT);
    $log = new \availability_proctor\log($filters, $page);
    $log->render_filter_form();
    $log->render_table();
    echo html_writer::end_div();
}

if ($action == 'show') {
    $id = required_param('id', PARAM_INT);
    // Old marketplace links predate SDK reports and therefore point to Webapp.
    // New links always carry the source so equal numeric ids cannot cross tables.
    $model = optional_param('model', \availability_proctor\config::MODEL_WEBAPP, PARAM_ALPHA);
    if (!in_array($model, \availability_proctor\config::MODEL_OPTIONS, true)) {
        throw new \invalid_parameter_exception('Unknown proctoring model.');
    }

    $url = new \moodle_url($baseurl, ['action' => $action, 'id' => $id, 'model' => $model]);
    $PAGE->set_url($url);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('report_details_heading', 'availability_proctor'));

    echo $OUTPUT->header();
    echo html_writer::start_div('availability-proctor-report');
    echo $OUTPUT->heading(get_string('report_details_heading', 'availability_proctor'));

    $logdetails = new \availability_proctor\log_details($id, $url, $model);
    $logdetails->render();
    echo html_writer::end_div();
}


echo $OUTPUT->footer();
