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

namespace availability_proctor;
use stdClass;
use availability_proctor\condition;

/**
 * Collection of static methods, used throughout the code
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class common {
    /**
     * @var integer Time for entry to expire
     */
    const EXPIRATION_SLACK = 15 * 60;

    /**
     * Finds most recent entry
     * @param \stdClass $entry Entry
     * @return \stdClass|null entry or null
     */
    public static function most_recent_entry($entry) {
        global $DB;

        $entries = $DB->get_records('availability_proctor_entries', [
            'userid' => $entry->userid,
            'courseid' => $entry->courseid,
            'cmid' => $entry->cmid,
            'status' => 'new',
        ], '-timecreated');
        $entry = reset($entries);
        return $entry;
    }

    /**
     * create entry if not exist
     * @param condition $condition condition
     * @param integer $userid User id
     * @param integer $cm Cm id
     * @return stdClass
     */
    public static function create_entry(condition $condition, $userid, $cm) {
        global $DB;

        $courseid = $cm->course;

        $entries = $DB->get_records('availability_proctor_entries', [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cm->id,
        ], 'id');

        foreach ($entries as $entry) {
            if (in_array($entry->status, ['started', 'new'])) {
                return $entry;
            }

            if ($entry->status == 'scheduled') {
                if ($condition->autorescheduling) {
                    if (time() > $entry->timescheduled + self::EXPIRATION_SLACK) {
                        $entry->timemodified = time();
                        $entry->status = 'rescheduled';

                        $DB->update_record('availability_proctor_entries', $entry);
                        $entry = self::reset_entry(['id' => $entry->id]);
                    }
                }
                return $entry;
            }
        }

        if ($cm->modname == 'quiz') {
            // Use Moodle's user-aware quiz settings so user and group overrides
            // affect the number of Proctor entries in the same way as attempts.
            $settingsclass = utils::quiz_settings_classname();
            $quiz = $settingsclass::create($cm->instance, $userid);
            $allowedattempts = $quiz->get_num_attempts_allowed();
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
            $entry->id = $DB->insert_record('availability_proctor_entries', $entry);

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

        $oldentry = $DB->get_record('availability_proctor_entries', $conditions);

        $notinited = $oldentry && $oldentry->status == 'new';

        if ($oldentry && !$notinited) {
            $oldentry->status = "force_reset";
            $DB->update_record('availability_proctor_entries', $oldentry);
        }

        if ($oldentry && (!$notinited || $force)) {
            $entries = $DB->get_records('availability_proctor_entries', [
                'userid' => $oldentry->userid,
                'courseid' => $oldentry->courseid,
                'cmid' => $oldentry->cmid,
                'status' => 'new',
            ]);

            if (count($entries) == 0 || $force) {
                if ($force) {
                    foreach ($entries as $old) {
                        $old->status = 'force_reset';
                        $DB->update_record('availability_proctor_entries', $old);
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

                $entry->id = $DB->insert_record('availability_proctor_entries', $entry);

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
            'status' => 'new',
        ];

        if (!empty($cmid)) {
            $condition['cmid'] = $cmid;
        }

        $DB->delete_records('availability_proctor_entries', $condition);
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

    /**
     * Get timebracket for cm of type
     * @param string $type Type of cm
     * @param \stdClass $cm Course-module
     * @return array start and end time for cm
     */
    public static function get_timebracket_for_cm($type, $cm, $userid) {
        global $DB;
        $id = $cm->instance;

        $timebracket = [];
        switch ($type) {
            case 'quiz':
                $quiz = $DB->get_record('quiz', [ 'id' => $id ]);
                quiz_update_effective_access($quiz, $userid);
                $start = $quiz->timeopen;
                $end = $quiz->timeclose;

                $timebracket = [ 'start' => $start, 'end' => $end ];
                break;

            case 'assign':
                $context = \context_module::instance($cm->id);
                $assign = new \assign($context, $cm, null);
                $assign->update_effective_access($userid);
                $instance = $assign->get_instance($userid);

                $start = $instance->allowsubmissionsfromdate;
                $end = $instance->duedate;

                $timebracket = [ 'start' => $start, 'end' => $end ];
                break;
        }

        // Fill the void.
        if (empty($timebracket['start'])) {
            $timebracket['start'] = strtotime('2022-01-01');
        }
        if (empty($timebracket['end'])) {
            $timebracket['end'] = strtotime('2032-01-01');
        }

        return $timebracket;
    }

    /**
     * Parses date string in ISO8601 ignoring fractions of a second
     * @param string $date Date-string with or without fractions
     * @return int timestamp
     **/
    public static function parse_date($date) {
        if (!empty($date)) {
            $date = preg_replace('/\.\d+/', '', $date);
            $datetime = \DateTime::createFromFormat(\DateTime::ISO8601, $date);
            return $datetime->getTimestamp();
        }
    }

    /**
     * Compute effective proctoring duration in minutes.
     * A quiz must have a time limit; activities without a native time limit
     * keep the existing condition::MAX_LIMIT fallback.
     *
     * Earlier releases took the duration from the condition instead, so an
     * attempt started before the upgrade may run on a quiz with no time limit. Passing that
     * stored duration lets such an attempt finish; a new attempt is refused.
     *
     * @param \stdClass|\cm_info $cm Course module
     * @param int|null $storedduration Condition duration for an attempt already running.
     * @param int|null $userid Learner whose quiz overrides apply; none reads the quiz as set.
     * @return int duration in minutes
     * @throws \moodle_exception When a quiz has no time limit and no stored duration is given.
     */
    public static function get_quiz_time_limit($cm, $storedduration = null, $userid = null) {
        global $DB;
        if (!empty($cm->modname) && $cm->modname === 'quiz') {
            if ($userid) {
                $timelimit = utils::quiz_for_user((int) $cm->instance, (int) $userid)->timelimit;
            } else {
                $timelimit = $DB->get_field('quiz', 'timelimit', ['id' => $cm->instance]);
            }
            if (empty($timelimit) && $storedduration !== null) {
                return (int) $storedduration;
            }
            if (empty($timelimit)) {
                throw new \moodle_exception('error_notimelimit', 'availability_proctor');
            }

            return (int) ceil($timelimit / 60);
        }

        return condition::MAX_LIMIT;
    }
}
