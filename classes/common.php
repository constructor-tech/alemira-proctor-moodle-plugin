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
 * @package    availability_examus
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @copyright  based on work by 2017 Max Pomazuev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_examus2;
use \stdClass;
defined('MOODLE_INTERNAL') || die();

/**
 * Collection of static methods, used throughout the code
 */
class common {
    /**
     * Resets user entry ensuring there is only one 'not inited' entry.
     * If there is already one not inited entry, return it(unless forse reset is requested)
     * @todo Rework this function to be more readable
     * @param array $conditions array of conditions
     * @param boolean $force
     * @return \stdClass|null entry or null
     */
    public static function reset_entry($conditions, $force = false) {
        global $DB;

        $oldentry = $DB->get_record('availability_examus2_entries', $conditions);

        $notinited = $oldentry && $oldentry->status == 'new';

        if ($oldentry && (!$notinited || $force)) {
            $entries = $DB->get_records('availability_examus2_entries', [
                'userid' => $oldentry->userid,
                'courseid' => $oldentry->courseid,
                'cmid' => $oldentry->cmid,
                'status' => 'new'
            ]);

            if (count($entries) == 0 || $force) {
                if ($force) {
                    foreach ($entries as $old) {
                        $old->status = "Force reset";
                        $DB->update_record('availability_examus2_entries', $old);
                    }
                }

                $timenow = time();
                $entry = new stdClass();
                $entry->userid = $oldentry->userid;
                $entry->courseid = $oldentry->courseid;
                $entry->cmid = $oldentry->cmid;
                $entry->accesscode = md5(uniqid(rand(), 1));
                $entry->status = 'new';
                $entry->timecreated = $timenow;
                $entry->timemodified = $timenow;

                $entry->id = $DB->insert_record('availability_examus2_entries', $entry);

                return $entry;
            } else {
                return null;
            }
        }
    }

    /**
     * Deletes entries that have "new" status
     * @param integer|string $userid
     * @param integer|string $courseid
     * @param integer|string|null $cmid
     */
    public static function delete_empty_entries($userid, $courseid, $cmid = null) {
        global $DB;

        $condition = [
          'userid' => $userid,
          'courseid' => $courseid,
          'status' => 'new'
        ];

        if (!empty($cmid)) {
            $condition['cmid'] = $cmid;
        }

        $DB->delete_records('availability_examus2_entries', $condition);
    }

    /**
     * Format timestamp as YYYY.MM.DD HH:MM, converting from GMT to user's timezone
     * @param integer $timestamp Timestamp in GMT
     * @return string|null
     */
    public static function format_date($timestamp) {
        $date = $timestamp ? usergetdate($timestamp) : null;

        if ($date) {
            return (
                '<b>' .
                $date['year'] . '.' .
                str_pad($date['mon'], 2, 0, STR_PAD_LEFT) . '.' .
                str_pad($date['mday'], 2, 0, STR_PAD_LEFT) . '</b> ' .
                str_pad($date['hours'], 2, 0, STR_PAD_LEFT) . ':' .
                str_pad($date['minutes'], 2, 0, STR_PAD_LEFT)
            );
        } else {
            return null;
        }
    }

    public static function get_timebracket_for_cm($type, $cm){
        $timebrackets = self::get_timebrackets_for_cms($type, [$cm]);
        return reset($timebrackets);
    }

    public static function get_timebrackets_for_cms($type, $cms){
        global $DB;
        $ids = [];
        $results = [];
        foreach($cms as $cm) {
            $ids[] = $cm->instance;
        }
        switch($type) {
            case 'quiz':
                $quizes = $DB->get_records_list('quiz', 'id', $ids);
                foreach($quizes as $quiz) {
                    $start = $quiz->timeopen;
                    $end = $quiz->timeclose;

                    if($start == 0 || $end == 0){
                        continue;
                    }

                    $results[$quiz->id] = [ 'start' => $start, 'end' => $end ];
                }
                break;

            case 'assign':
                $assigns = $DB->get_records_list('assign', 'id', $ids);
                foreach($assigns as $assign) {
                    $start = $assign->allowsubmissionsfromdate;
                    $end = $assign->duedate;

                    if($start == 0 || $end == 0){
                        continue;
                    }

                    $results[$assign->id] = [ 'start' => $start, 'end' => $end ];
                }
                break;
        }
        return $results;
    }
}
