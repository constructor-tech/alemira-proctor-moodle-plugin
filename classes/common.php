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
 * Availability plugin for integration with Alemira proctoring system.
 *
 * @package    availability_alemira
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_alemira;
use \stdClass;
use availability_alemira\condition;

/**
 * Collection of static methods, used throughout the code
 */
class common {
    /**
     * Finds most recent entry
     * @todo Rework this function to be more readable
     * @param \stdClass $entry Entry
     * @return \stdClass|null entry or null
     */
    public static function most_recent_entry($entry) {
        global $DB;

        $entries = $DB->get_records('availability_alemira_entries', [
            'userid' => $entry->userid,
            'courseid' => $entry->courseid,
            'cmid' => $entry->cmid,
            'status' => 'new'
        ], '-timecreated');
        $entry = reset($entries);
        return $entry;
    }

    /**
     * create entry if not exist
     *
     * @param integer $userid User id
     * @param integer $cm Cm id
     * @return stdClass
     */
    public static function create_entry(condition $condition, $userid, $cm) {
        global $DB;

        $courseid = $cm->course;

        $entries = $DB->get_records('availability_alemira_entries', [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cm->id,
        ], 'id');

        foreach ($entries as $entry) {
            if (in_array($entry->status, ['started', 'scheduled', 'new'])) {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if ($condition->autorescheduling) {
                // Was schduled and not completed.
                $scheduled = !$entry->attemptid && $entry->status == 'scheduled';
                // Consider expired, giving 15 minutes slack.
                $expired = time() > $entry->timescheduled + self::EXPIRATION_SLACK;

                if ($scheduled && $expired) {
                    $entry->timemodified = time();
                    $entry->status = 'rescheduled';

                    $DB->update_record('availability_alemira_entries', $entry);
                    $entry = self::reset_entry(['id' => $entry->id]);
                    return $entry;
                }

            }
        }

        if ($cm->modname == 'quiz') {
            $quiz = \quiz_access_manager::load_quiz_and_settings($cm->instance);
            $allowedattempts = $quiz->attempts;
            $allowedattempts = $allowedattempts > 0 ? $allowedattempts : null;
        } else {
            $allowedattempts = null;
        }

        $usedentries = 0;
        foreach ($entries as $entry) {
            if (!in_array($entry->status, ['rescheduled', 'canceled', 'force_reset'])) {
                $usedentries++;
            }
        }

        // Create new entry if not exists already.
        // Respect limited number of attempts.
        if (is_null($allowedattempts) || $usedentries < $allowedattempts) {
            $entry = condition::make_entry($courseid, $cm->id, $userid);
            $entry->id = $DB->insert_record('availability_alemira_entries', $entry);

            return $entry;
        } else {
            // Taking last entry no matter the status.
            // This is done to trigger "exam finished" page.
            $entry = end($entries);
            return $entry;
        }
    }

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

        $oldentry = $DB->get_record('availability_alemira_entries', $conditions);

        $notinited = $oldentry && $oldentry->status == 'new';

        if ($oldentry && !$notinited) {
            $oldentry->status = "force_reset";
            $DB->update_record('availability_alemira_entries', $oldentry);
        }

        if ($oldentry && (!$notinited || $force)) {
            $entries = $DB->get_records('availability_alemira_entries', [
                'userid' => $oldentry->userid,
                'courseid' => $oldentry->courseid,
                'cmid' => $oldentry->cmid,
                'status' => 'new'
            ]);

            if (count($entries) == 0 || $force) {
                if ($force) {
                    foreach ($entries as $old) {
                        $old->status = 'force_reset';
                        $DB->update_record('availability_alemira_entries', $old);
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

                $entry->id = $DB->insert_record('availability_alemira_entries', $entry);

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

        $DB->delete_records('availability_alemira_entries', $condition);
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

    public static function get_timebracket_for_cm($type, $cm) {
        $timebrackets = self::get_timebrackets_for_cms($type, [$cm]);
        $timebracket = reset($timebrackets);
        $timebracket = $timebracket ? $timebracket : [];

        // Fill the void.
        if (empty($timebracket['start'])) {
            $timebracket['start'] = strtotime('2022-01-01');
        }
        if (empty($timebracket['end'])) {
            $timebracket['end'] = strtotime('2032-01-01');
        }

        return $timebracket;
    }

    public static function get_timebrackets_for_cms($type, $cms) {
        global $DB;
        $ids = [];
        $results = [];
        foreach ($cms as $cm) {
            $ids[] = $cm->instance;
        }
        switch ($type) {
            case 'quiz':
                $quizes = $DB->get_records_list('quiz', 'id', $ids);
                foreach ($quizes as $quiz) {
                    $start = $quiz->timeopen;
                    $end = $quiz->timeclose;

                    if ($start == 0 || $end == 0) {
                        continue;
                    }

                    $results[$quiz->id] = [ 'start' => $start, 'end' => $end ];
                }
                break;

            case 'assign':
                $assigns = $DB->get_records_list('assign', 'id', $ids);
                foreach ($assigns as $assign) {
                    $start = $assign->allowsubmissionsfromdate;
                    $end = $assign->duedate;

                    if ($start == 0 || $end == 0) {
                        continue;
                    }

                    $results[$assign->id] = [ 'start' => $start, 'end' => $end ];
                }
                break;
        }

        return $results;
    }

    /**
     * Parses date string in ISO8601 ignoring fractions of a second
     **/
    public static function parse_date($date) {
        if (!empty($date)) {
            $date = preg_replace('/\.\d+/', '', $date);
            $datetime = \DateTime::createFromFormat(\DateTime::ISO8601, $date);
            return $datetime->getTimestamp();
        }

    }

}
