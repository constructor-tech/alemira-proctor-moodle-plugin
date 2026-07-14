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
 * Bridge page for Assignment proctoring.
 *
 * @package    availability_proctor
 * @copyright  2019-2023 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

$id                 = required_param('id', PARAM_INT);
$proctor_accesscode = required_param('proctor_accesscode', PARAM_RAW);

require_login();

$entry = $DB->get_record('availability_proctor_entries', ['accesscode' => $proctor_accesscode]);

if ($entry && (int)$entry->cmid === $id && (int)$entry->userid === (int)$USER->id
        && in_array($entry->status, ['new', 'scheduled', 'started'])) {
    if ($entry->status !== 'started') {
        $DB->set_field('availability_proctor_entries', 'status', 'started', ['id' => $entry->id]);
    }
    \availability_proctor\session_cache::set_accesscode($proctor_accesscode);
}

redirect(new moodle_url('/mod/assign/view.php', ['id' => $id, 'proctor_lockdown' => '1']));
