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
 * Availability plugin for integration with Examus proctoring system.
 *
 * @package    availability_examus2
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @copyright  based on work by 2017 Max Pomazuev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_examus2\task;

defined('MOODLE_INTERNAL') || die();

use availability_examus2\common;
use availability_examus2\condition;
use availability_examus2\client;

/**
 *
 */
class update_scheduled_exam extends \core\task\adhoc_task {
    protected $sentexams = null;

    public function execute() {
        global $DB;

        $params = $this->get_custom_data();

        //TODO: remove;
        if(!isset($params->courseid) || !isset($params->cmid)){
            return;
        }

        $courseid = $params->courseid;
        $cmid = $params->cmid;

        $userids = $params->userids;
        $examdata = $params->examdata;


        list($course, $cm) = get_course_and_cm_from_cmid($cmid);
        $condition = condition::get_examus_condition($cm);

        if(!$condition->schedulingrequired){
            return;
        }

        $query = 'SELECT * FROM {availability_examus2_exams} WHERE cmid = :cmid';
        $query .= ' AND '.$DB->sql_isnotempty('{availability_examus2_exams}', 'userid', true, false);

        $sentexams = $DB->get_records_sql($query, ['cmid' => $cmid]);

        foreach ($sentexams as $sentexam) {
            $sentuserid = (int) $sentexam->userid;
            $this->sentexams[$sentuserid] = $sentexam;
        }

        foreach ($userids as $userid) {
            $userid = (int) $userid;
            $this->schedule_exam($condition, $userid, $course, $cm, $examdata);
        }


    }

    public function schedule_exam($condition, $userid, $course, $cm, $examdata){
        global $DB;

        $sentexam = isset($this->sentexams[$userid]) ? $this->sentexams[$userid] : null;

        $userentries = [];
        $userentries[$cm->id] = $DB->get_records('availability_examus2_entries', [
            'userid' => $userid,
            'courseid' => $course->id,
            'cmid' => $cm->id
        ], 'id');


        $type = $cm->name;

        $client = new client();
        $data = $client->exam_data($condition, $course, $cm);
        $timebracket = common::get_timebracket_for_cm($type, $cm);
        $timedata = $client->time_data($timebracket);

        if(!$timedata){
            return;
        }

        $data = array_merge($data, $timedata);

        $checksum = $client->checksum($data);
        $oldchecksum = null;

        $recorddata = (object) [
            'cmid' => $cm->id,
            'courseid' => $course->id,
            'checksum' => $checksum,
            'userid' => $userid,
            'timeuploaded' => time(),
        ];

        $entry = $condition->create_entry_for_cm($userid, $cm, $userentries);

        if ($sentexam) {
            $oldchecksum = $sentexam->checksum;

            $recorddata->id = $sentexam->id;
            $DB->update_record('availability_examus2_exams', $recorddata);
        } else {
            $DB->insert_record('availability_examus2_exams', $recorddata);
        }

        if ($oldchecksum != $checksum) {
            mtrace('Sending ' . $type . ' ' . $cm->id . ', user ' . $userid . ' checksum changed');
            $result = $client->request('exams', $data);
        }

    }
}
