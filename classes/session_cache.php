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
 * @copyright  2019-2026 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

/**
 * Wraps the per-session accesscode/reset state behind Moodle's Cache API
 * (MODE_SESSION) instead of touching $SESSION directly.
 *
 * @package    availability_proctor
 * @copyright  2019-2026 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_cache {
    /**
     * Returns the session-mode cache instance for this plugin.
     *
     * @return \cache_session
     */
    protected static function cache() {
        return \cache::make('availability_proctor', 'session');
    }

    /**
     * The proctoring accesscode carried by the current user's session, if any.
     *
     * Its presence is what tells the hosted flow that this request came back from
     * the WebApp rather than straight from the activity page.
     *
     * @return string|null The stored accesscode, or null if unset.
     */
    public static function get_accesscode() {
        $value = self::cache()->get('accesscode');
        return $value === false ? null : $value;
    }

    /**
     * Remember the accesscode the learner returned from the WebApp with.
     *
     * @param string $accesscode
     * @return void
     */
    public static function set_accesscode($accesscode) {
        self::cache()->set('accesscode', $accesscode);
    }

    /**
     * Forget the accesscode, so later requests are treated as ordinary ones.
     *
     * @return void
     */
    public static function clear_accesscode() {
        self::cache()->delete('accesscode');
    }

    /**
     * Is the learner being told to restart proctoring?
     *
     * Set when the entry they arrived with can no longer be used, which the fader
     * turns into a "start again" message rather than a silent failure.
     *
     * @return bool Whether the reset flag is set.
     */
    public static function is_reset() {
        return (bool) self::cache()->get('reset');
    }

    /**
     * Ask the learner to restart proctoring on the next page they see.
     *
     * @return void
     */
    public static function set_reset() {
        self::cache()->set('reset', true);
    }

    /**
     * Clear the restart prompt, once it has been shown.
     *
     * @return void
     */
    public static function clear_reset() {
        self::cache()->delete('reset');
    }
}
