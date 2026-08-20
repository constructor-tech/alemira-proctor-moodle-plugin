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
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\availability_proctor\observers::course_module_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\availability_proctor\observers::user_enrolment_deleted',
        'internal' => false,
    ],
    [
        // NTG-185. Deleting a course fires no per-module event: remove_course_contents()
        // calls quiz_delete_instance() directly, so course_module_deleted never runs for
        // it and the SDK model's session rows would outlive the course. This is the only
        // signal that a course's contents are gone, and it arrives after the {quiz} rows
        // have already been removed -- which is why the callback keys on the absence of
        // the quiz rather than on the course id it carries.
        'eventname' => '\core\event\course_content_deleted',
        'callback' => '\availability_proctor\observers::course_content_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\availability_proctor\observers::attempt_submitted',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_started',
        'callback' => '\availability_proctor\observers::attempt_started',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_preview_started',
        'callback' => '\availability_proctor\observers::attempt_started',
        'internal' => false,
    ],
    [
        // An abandoned attempt is as terminal as a submitted one: the student will
        // never return to it, so an SDK session must not stay open -- it would never
        // reach a terminal state, and Constructor would still consider it recording.
        //
        // Deliberately NOT wired to attempt_submitted, which is where the plugin the
        // SDK flow came from put it. That plugin's callback did nothing but SDK work;
        // this one carries the hosted flow's logic as well, and this event is not
        // safe to feed it. Core fires attempt_abandoned from process_abandon(), which
        // cron reaches for overdue attempts, and it sets no 'userid' -- so
        // \core\event\base fills in $USER->id (base.php:204), the cron user rather
        // than the student, with the student in relateduserid instead. The hosted
        // logic keys on $event->userid, so handing it this event would have it query
        // entries and build modinfo for the wrong user. Its accesscode check would
        // most likely return first, but "probably harmless" is not a basis for
        // pointing a new event at the flow every customer is running.
        'eventname' => '\mod_quiz\event\attempt_abandoned',
        'callback' => '\availability_proctor\observers::attempt_abandoned',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_deleted',
        'callback' => '\availability_proctor\observers::attempt_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_viewed',
        'callback' => '\availability_proctor\observers::attempt_viewed',
        'internal' => false,
    ],
];
