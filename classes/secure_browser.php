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

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * Authenticates the SDK hand-off into Constructor Secure Browser.
 *
 * Secure Browser has its own cookie store, so it cannot reuse the Moodle login
 * from Chrome or Edge. A short-lived, single-use Moodle private key bridges that
 * one hand-off. The key is bound to both the learner and the SDK session row;
 * consuming it marks only the new Moodle browser session as the native exam
 * session, leaving the normal browser unable to use top-level quiz pages.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secure_browser {
    /** @var string Private-key namespace, separate from legacy Webapp entry keys. */
    const KEY_SCRIPT = 'availability_proctor_sdk_secure_browser';

    /** @var int Eight hours, matching the established Webapp seamless-auth window. */
    const KEY_LIFETIME = 28800;

    /**
     * Build the one-time URL Constructor Secure Browser opens for an SDK session.
     *
     * After authenticating the native cookie session, sdk_entry.php returns to
     * launch.php. The SDK must initialize inside Secure Browser before Moodle
     * exposes the quiz; the bridge must never redirect straight to the activity.
     *
     * @param \stdClass $session SDK session row.
     * @param \stdClass $user Learner record.
     * @return string Absolute URL.
     */
    public static function entry_url(\stdClass $session, \stdClass $user): string {
        $validuntil = time() + self::KEY_LIFETIME;
        $token = get_user_key(
            self::KEY_SCRIPT,
            (int) $user->id,
            (int) $session->id,
            false,
            $validuntil
        );

        return (new \moodle_url('/availability/condition/proctor/sdk_entry.php', [
            'sessionid' => (int) $session->id,
            'token' => $token,
        ]))->out(false);
    }

    /**
     * Consume a hand-off key, log in its learner and mark this browser session.
     *
     * @param string $token Single-use Moodle private key.
     * @param int $sessionid SDK session row id.
     * @return \stdClass The authenticated SDK session row.
     */
    public static function authenticate($token, int $sessionid): \stdClass {
        global $DB;

        if (!get_config('availability_proctor', 'seamless_auth')) {
            throw new \moodle_exception('invalidkey');
        }

        $key = validate_user_key($token, self::KEY_SCRIPT, $sessionid);
        $session = $DB->get_record(session::TABLE, ['id' => $sessionid]);

        if (!$session) {
            throw new \moodle_exception('error_nosession', 'availability_proctor');
        }
        if ((int) $key->userid !== (int) $session->userid) {
            throw new \moodle_exception('error_notyoursession', 'availability_proctor');
        }
        if (!session::allows_access($session)) {
            throw new \moodle_exception('error_sessionfinished', 'availability_proctor');
        }

        $user = $DB->get_record('user', ['id' => $key->userid]);
        if (!$user) {
            throw new \moodle_exception('invaliduserid');
        }
        \core_user::require_active_user($user, true, true);

        // Consume before establishing the login. A repeated URL must never create
        // another authenticated browser session, even if the first redirect is
        // interrupted after complete_user_login().
        $DB->delete_records('user_private_key', ['id' => $key->id]);
        complete_user_login($user);
        session_cache::set_sdk_secure_browser_session((int) $session->id);

        return $session;
    }
}
