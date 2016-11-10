<?php

namespace availability_examus;

defined('MOODLE_INTERNAL') || die();
use stdClass;

class condition extends \core_availability\condition
{

    public function __construct($structure)
    {
    }

    private static function delete_empty_entry($userid, $courseid, $cmid)
    {
        global $DB;
        $DB->delete_records('availability_examus', array(
            'userid' => $userid, 'courseid' => $courseid, 'cmid' => $cmid, 'status' => 'Not inited'));
    }

    private static function examus_enabled_for($cm)
    {
        # XXX This may fall
        return strpos($cm->availability, '"c":[{"type":"examus"}]') !== false;
    }

    public function save()
    {
    }

    public function is_available($not,
                                 \core_availability\info $info, $grabthelot, $userid)
    {
        if (in_array('examus', $_SESSION)) {
            $allow = True;
        } else {
            $allow = False;
        }

        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    public function get_description($full, $not, \core_availability\info $info)
    {
        return get_string('use_examus', 'availability_examus');
    }

    protected function get_debug_string()
    {
        return in_array('examus', $_SESSION) ? 'YES' : 'NO';
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event)
    {
        global $DB;
        $cmid = $event->contextinstanceid;
        $DB->delete_records('availability_examus', array('cmid' => $cmid));
    }

    private static function create_entry_if_not_exist($userid, $courseid, $cmid)
    {
        global $DB;
        $entry = $DB->get_record('availability_examus', array(
            'userid' => $userid, 'courseid' => $courseid, 'cmid' => $cmid));
        if (!$entry) {
            $timenow = time();
            $entry = new stdClass();
            $entry->userid = $userid;
            $entry->courseid = $courseid;
            $entry->cmid = $cmid;
            $entry->accesscode = md5(uniqid(rand(), 1));
            $entry->status = 'Not inited';
            $entry->timecreated = $timenow;
            $entry->timemodified = $timenow;
            $DB->insert_record('availability_examus', $entry);
        }
    }
    public static function course_module_updated(\core\event\course_module_updated $event)
    {
        global $DB;
        $cmid = $event->contextinstanceid;
        $course = get_course($event->courseid);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cmid);

        // XXX this may fall

        if (self::examus_enabled_for($cm)) {
            $users = get_enrolled_users($event->get_context());
            foreach ($users as $user) {
                self::create_entry_if_not_exist($user->id, $event->courseid, $cmid);
            }
        } else {
            $users = get_enrolled_users($event->get_context());
            foreach ($users as $user) {
                self::delete_empty_entry($user->id, $event->courseid, $cmid);
            }
        }
    }

    public static function user_enrolment_created_updated(\core\event\user_enrolment_created $event)
    {
        global $DB;
        $cmid = $event->contextinstanceid;
        $course = get_course($event->courseid);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cmid);
        $userid = $event->relateduserid;

        if (self::examus_enabled_for($cm)) {
            self::create_entry_if_not_exist($userid, $event->courseid, $cmid);
        }
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event)
    {
        global $DB;
        $cmid = $event->contextinstanceid;
        $course = get_course($event->courseid);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cmid);
        $userid = $event->relateduserid;

        if (self::examus_enabled_for($cm)) {
            self::delete_empty_entry($userid, $event->courseid, $cmid);
        }
    }

}