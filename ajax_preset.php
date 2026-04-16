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
 * AJAX endpoint for personal preset save/delete from the exam settings form.
 *
 * @package    availability_proctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');

use availability_proctor\preset;

require_login();
require_sesskey();

header('Content-Type: application/json');

$action = required_param('action', PARAM_ALPHA);

try {
    if ($action === 'save') {
        $name = required_param('name', PARAM_TEXT);
        $payload = required_param('payload', PARAM_RAW);
        $fields = json_decode($payload, true);
        if (!is_array($fields)) {
            throw new \moodle_exception('error_invalid_payload', 'availability_proctor');
        }
        $name = trim($name);
        if ($name === '') {
            throw new \moodle_exception('error_preset_name_required', 'availability_proctor');
        }
        $id = preset::save_user_preset($USER->id, $name, $fields);
        $record = preset::get_by_id($id);
        echo json_encode(['ok' => true, 'preset' => $record]);

    } else if ($action === 'delete') {
        $id = required_param('id', PARAM_INT);
        preset::delete_user_preset($id, $USER->id);
        echo json_encode(['ok' => true, 'id' => $id]);

    } else {
        throw new \moodle_exception('error_invalid_action', 'availability_proctor');
    }

} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
