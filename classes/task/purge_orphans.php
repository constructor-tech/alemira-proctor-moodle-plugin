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

namespace availability_proctor\task;

use availability_proctor\observers;

defined('MOODLE_INTERNAL') || die();

/**
 * Removes session and denial rows whose quiz no longer exists.
 *
 * A safety net rather than the primary mechanism. Two observers already erase these
 * rows when a quiz or a course is deleted, and on a healthy site this task finds
 * nothing every time it runs.
 *
 * It exists because the defect it cleans up after was precisely a deletion path that
 * fired no event. Core removes quizzes from more than one place -- course_delete_module()
 * fires an event, remove_course_contents() does not -- and a future version could add
 * another. Personal data outliving the quiz it names is the one failure here that is
 * silent: nothing reports it, and it is only visible to whoever thinks to look. A daily
 * sweep turns any such gap into a delay instead of an orphan.
 *
 * Cheap enough to run daily: the query is an index-supported NOT EXISTS over two small
 * tables, and it deletes nothing on a site where the observers did their job.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_orphans extends \core\task\scheduled_task {
    /**
     * The task's name, as shown in the scheduled task administration page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purgeorphans', 'availability_proctor');
    }

    /**
     * Remove any orphaned rows.
     *
     * @return void
     */
    public function execute() {
        $removed = observers::purge_orphans();

        // Silent when there is nothing to do, which is the normal case. A line in the
        // cron log when rows are removed matters, though: it means a deletion path
        // reached the database without firing an event, and that is worth noticing
        // rather than quietly tidying away.
        if ($removed > 0) {
            mtrace("availability_proctor: removed $removed orphaned row(s) whose quiz no longer exists.");
        }
    }
}
