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
 * @copyright  2019-2024 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor\event;

/**
 * Event fired when a user is logged in via seamless auth token.
 *
 * @package    availability_proctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_logged_in_via_token extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'availability_proctor_entries';
    }

    /**
     * Returns the event name shown in logs.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_user_logged_in_via_token', 'availability_proctor');
    }

    /**
     * Returns the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' was logged in automatically via a seamless auth token " .
            "for proctoring entry id '{$this->objectid}'.";
    }

    /**
     * Returns the URL associated with this event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/availability/condition/proctor/entry.php');
    }
}
