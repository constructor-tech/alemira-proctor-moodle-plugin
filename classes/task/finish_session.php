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

use availability_proctor\session;

defined('MOODLE_INTERNAL') || die();

/**
 * Retries the finish request for a session, when the first attempt failed.
 *
 * The Developer Guide asks that a finish request be resent if the connection
 * fails or the server answers 5xx. An ad-hoc task is how that retry happens:
 * throwing from execute() hands the problem back to Moodle's scheduler, which
 * retries with its own increasing delay rather than a loop written here.
 *
 * The submission path calls the API directly first, so this only runs when that
 * call did not settle the session. Recording continues until it does.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finish_session extends \core\task\adhoc_task {
    /**
     * The task's name, as shown on the ad-hoc task administration page.
     *
     * Declared rather than inherited. \core\task\adhoc_task supplies a default on
     * current releases, but \core\task\task_base declares get_name() abstract, and
     * this plugin runs on releases as old as 3.8 -- where an abstract method left
     * unimplemented is a fatal error the moment the class is loaded. Declaring it
     * costs three lines and removes the question; it also means the admin page
     * names the task instead of showing a raw class name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_finishsession', 'availability_proctor');
    }

    /**
     * Queue a retry for one session.
     *
     * @param int $sessionid Row id in availability_proctor_sess.
     * @return void
     */
    public static function queue(int $sessionid): void {
        $task = new self();
        $task->set_custom_data(['sessionid' => $sessionid]);

        // Queued rather than run inline: the student has already submitted, and the
        // response to their submission should not wait on a server that is failing.
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Retry the finish request.
     *
     * @return void
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $sessionid = (int) ($data['sessionid'] ?? 0);

        if (empty($sessionid)) {
            return;
        }

        if (!session::request_finish_by_id($sessionid)) {
            // Throwing marks the task as failed, and Moodle reschedules it. Anything
            // permanent -- a session that no longer exists, or a 4xx from the server
            // -- is reported as settled by request_finish_by_id(), so this only fires
            // for failures worth trying again.
            throw new \moodle_exception(
                'error_finishfailed',
                'availability_proctor',
                '',
                null,
                'Session id ' . $sessionid
            );
        }
    }
}
