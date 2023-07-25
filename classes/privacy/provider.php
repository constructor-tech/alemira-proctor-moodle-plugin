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

namespace availability_proctor\privacy;

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\userlist;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\transform;


/**
 * Implementation of the privacy subsystem plugin provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'availability_proctor_entries',
            [
                'courseid' => 'privacy:metadata:availability_proctor_entries:courseid',
                'cmid' => 'privacy:metadata:availability_proctor_entries:cmid',
                'attemptid' => 'privacy:metadata:availability_proctor_entries:attemptid',
                'userid' => 'privacy:metadata:availability_proctor_entries:userid',
                'accesscode' => 'privacy:metadata:availability_proctor_entries:accesscode',
                'status' => 'privacy:metadata:availability_proctor_entries:status',
                'review_link' => 'privacy:metadata:availability_proctor_entries:review_link',
                'archiveurl' => 'privacy:metadata:availability_proctor_entries:archiveurl',
                'timecreated' => 'privacy:metadata:availability_proctor_entries:timecreated',
                'timemodified' => 'privacy:metadata:availability_proctor_entries:timemodified',
                'timescheduled' => 'privacy:metadata:availability_proctor_entries:timescheduled',
                'score' => 'privacy:metadata:availability_proctor_entries:score',
                'comment' => 'privacy:metadata:availability_proctor_entries:comment',
                'threshold' => 'privacy:metadata:availability_proctor_entries:threshold',
                'warnings' => 'privacy:metadata:availability_proctor_entries:warnings',
                'sessionstart' => 'privacy:metadata:availability_proctor_entries:sessionstart',
                'sessionend' => 'privacy:metadata:availability_proctor_entries:sessionend',
            ],
            'privacy:metadata:availability_proctor_entries'
        );

        return $collection;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT userid FROM {availability_proctor_entries} WHERE cmid = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {availability_proctor_entries} pe ON pe.cmid = cm.id
                 WHERE pe.userid = :userid AND contextlevel = :contextlevel
        ";

        $contextlist->add_from_sql($sql, ['userid' => $userid, 'contextlevel' => CONTEXT_MODULE]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params = $contextparams;

        $sql = "SELECT
                    pe.*
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {availability_proctor_entries} pe ON pe.cmid = cm.id
                 WHERE (
                    pe.userid = :userid AND
                    c.id {$contextsql}
                )
        ";

        $params['userid'] = $userid;
        $data = $DB->get_records_sql($sql, $params);

        foreach ($data as $entry) {
            $context = \context_module::instance($entry->cmid);

            $datetimes = ['timecreated', 'timemodified', 'timescheduled', 'sessionstart', 'sessionend'];
            foreach ($datetimes as $field) {
                if ($entry->{$field}) {
                    $entry->{$field} = transform::datetime($entry->{$field});
                }
            }

            // This field does not contain information specific to user.
            unset($entry->warningstitles);

            writer::with_context($context)
                ->export_data([get_string('privacy:path', 'availability_proctor')], $entry);
        }

    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $DB->delete_records('availability_proctor_entries', ['cmid' => $context->instanceid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['cmid' => $context->instanceid], $userinparams);

        $DB->delete_records_select('availability_proctor_entries', "cmid = :cmid AND userid {$userinsql}", $params);
    }

    /**
     * Delete personal information for a specific user and context(s)
     *
     * @param approved_contextlist $contextlist list of context for deletetion
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cmid = $context->instanceid;
            $DB->delete_records('availability_proctor_entries', [
                'cmid' => $cmid,
                'userid' => $userid,
            ]);
        }
    }
}
