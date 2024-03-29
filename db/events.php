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

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => 'core\event\course_module_deleted',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'callback' => 'avalibility_proctor_course_module_deleted',
        'internal' => false,
    ],
    [
        'eventname' => 'core\event\user_enrolment_deleted',
        'callback' => 'avalibility_proctor_user_enrolment_deleted',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'internal' => false,
    ],
    [
        'eventname' => 'mod_quiz\event\attempt_submitted',
        'callback' => 'avalibility_proctor_attempt_submitted_handler',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'internal' => false,
    ],
    [
        'eventname' => 'mod_quiz\event\attempt_started',
        'callback' => 'avalibility_proctor_attempt_started_handler',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'internal' => false,
    ],
    [
        'eventname' => 'mod_quiz\event\attempt_preview_started',
        'callback' => 'avalibility_proctor_attempt_started_handler',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'internal' => false,
    ],
    [
        'eventname' => 'mod_quiz\event\attempt_deleted',
        'callback' => 'avalibility_proctor_attempt_deleted_handler',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'internal' => false,
    ],
    [
        'eventname' => 'mod_quiz\event\attempt_viewed',
        'callback' => 'avalibility_proctor_attempt_viewed_handler',
        'includefile' => '/availability/condition/proctor/locallib.php',
        'internal' => false,
    ],
];
