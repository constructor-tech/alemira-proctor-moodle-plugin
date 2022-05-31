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

use availability_examus2\common;
use availability_examus2\condition;
use availability_examus2\client;

/**
 * Sends scheduled exams to examus
 */
class update_scheduled_exams extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_update_scheduled_exams', 'availability_examus2');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        return;
        global $DB;
        $courses = get_courses();
        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course);
            $instancesbytypes = $modinfo->get_instances();
            foreach ($instancesbytypes as $type => $instances) {
                $timebrackets = common::get_timebrackets_for_cms($type, $instances);

                foreach ($instances as $cm) {
                    if ($cm->availability) {
                        $condition = condition::get_examus_condition($cm);
                    } else {
                        continue;
                    }

                    if (isset($timebrackets[$cm->instance])) {
                        $timebracket = $timebrackets[$cm->instance];
                    } else {
                        $timebracket = null;
                    }

                    if (!$condition || !$condition->schedulingrequired) {
                        continue;
                    }

                    if (!$timebracket) {
                        continue;
                    }

                    mtrace('Checking ' . $type . ' ' . $cm->id);

                    $context = \context_module::instance($cm->id);
                    $users = get_enrolled_users($context, '', 0, $userfields = 'u.id');
                    $userids = [];
                    foreach ($users as $user) {
                        $userids[] = $user->id;
                    }
                    if (empty($userids)) {
                        return;
                    }

                    $client = new client();
                    $data = $client->exam_data($condition, $course, $cm);
                    $timedata = $client->time_data($timebracket);

                    $data = array_merge($data, $timedata, ['userids' => implode(',', $userids)]);

                    $checksum = $client->checksum($data);
                    $oldchecksum = null;

                    $recorddata = (object) [
                        'cmid' => $cm->id,
                        'courseid' => $course->id,
                        'checksum' => $checksum,
                        'timeuploaded' => time(),
                    ];

                    $sentexams = $DB->get_records('availability_examus2_exams', ['cmid' => $cm->id]);
                    $sentexam = reset($sentexams);

                    if ($sentexam) {
                        $oldchecksum = $sentexam->checksum;

                        $recorddata->id = $sentexam->id;
                        $DB->update_record('availability_examus2_exams', $recorddata);
                    } else {
                        $DB->insert_record('availability_examus2_exams', $recorddata);
                    }

                    if ($oldchecksum != $checksum) {
                        mtrace('scheduling ' . $type . ' ' . $cm->id . ', checksum changed, ' . count($userids) . ' users');
                        // $result = $client->request('exams', $data);

                        // create the instance.
                        $task = new update_scheduled_exam();
                        $task->set_custom_data([
                            'examdata' => $data,
                            'courseid' => $course->id,
                            'cmid' => $cm->id,
                            'userids' => $userids,
                        ]);

                        // queue it.
                        \core\task\manager::queue_adhoc_task($task);

                    } else {
                        mtrace('Skiping ' . $type . ' ' . $cm->id . ', checksum match');
                    }
                }

            }
        }
    }
}
