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

/**
 * Tests for the SDK hand-off into Constructor Secure Browser.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\secure_browser
 */
final class secure_browser_test extends \advanced_testcase {
    /**
     * A key logs in only its session owner, is consumed, and marks the new
     * Moodle browser session as the native SDK exam route.
     */
    public function test_authenticate_consumes_session_bound_key(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $sessionid = $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'secure-browser-test',
            'quizid' => 321,
            'userid' => $user->id,
            'state' => session::STATE_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeactive' => $now,
        ]);
        $session = $DB->get_record(session::TABLE, ['id' => $sessionid]);
        set_config('seamless_auth', 1, 'availability_proctor');

        $url = secure_browser::entry_url($session, $user);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->setGuestUser();
        // Hide session header errors, as core's complete_user_login() test does.
        $authenticated = @secure_browser::authenticate($query['token'], (int) $session->id);

        $this->assertEquals($session->id, $authenticated->id);
        $this->assertEquals($user->id, $USER->id);
        $this->assertTrue(session_cache::is_sdk_secure_browser_session((int) $session->id));
        $this->assertFalse($DB->record_exists('user_private_key', [
            'script' => secure_browser::KEY_SCRIPT,
            'value' => $query['token'],
            'instance' => $session->id,
        ]));
    }

    /**
     * Reloading a launch page reuses its pending key without affecting another session.
     */
    public function test_entry_url_reuses_pending_key_for_same_session(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $sessionid = $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'secure-browser-reuse-test',
            'quizid' => 987,
            'userid' => $user->id,
            'state' => session::STATE_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeactive' => $now,
        ]);
        $session = $DB->get_record(session::TABLE, ['id' => $sessionid]);
        set_config('seamless_auth', 1, 'availability_proctor');

        // A bridge minted by an earlier launch-page load, a few seconds ago.
        $pending = create_user_key(
            secure_browser::KEY_SCRIPT,
            (int) $user->id,
            (int) $sessionid,
            false,
            $now + secure_browser::KEY_LIFETIME - 5
        );
        $othersessionid = $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'secure-browser-other-session',
            'quizid' => 988,
            'userid' => $user->id,
            'state' => session::STATE_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeactive' => $now,
        ]);
        $otherpending = create_user_key(
            secure_browser::KEY_SCRIPT,
            (int) $user->id,
            (int) $othersessionid,
            false,
            $now + secure_browser::KEY_LIFETIME
        );

        $url = secure_browser::entry_url($session, $user);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame($pending, $query['token']);
        $this->assertEquals(2, $DB->count_records('user_private_key', [
            'script' => secure_browser::KEY_SCRIPT,
            'userid' => $user->id,
        ]));
        $this->assertTrue($DB->record_exists('user_private_key', [
            'script' => secure_browser::KEY_SCRIPT,
            'value' => $otherpending,
            'instance' => $othersessionid,
        ]));
    }

    /**
     * A nearly expired bridge is replaced so a new hand-off has time to open.
     */
    public function test_entry_url_renews_key_with_less_than_ten_minutes_left(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $sessionid = $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'secure-browser-renew-test',
            'quizid' => 989,
            'userid' => $user->id,
            'state' => session::STATE_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeactive' => $now,
        ]);
        $session = $DB->get_record(session::TABLE, ['id' => $sessionid]);
        $oldtoken = create_user_key(
            secure_browser::KEY_SCRIPT,
            (int) $user->id,
            (int) $sessionid,
            false,
            $now + 599
        );

        $url = secure_browser::entry_url($session, $user);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertNotEquals($oldtoken, $query['token']);
        $this->assertTrue($DB->record_exists('user_private_key', [
            'script' => secure_browser::KEY_SCRIPT,
            'value' => $oldtoken,
            'instance' => $sessionid,
        ]));
        $this->assertEquals(2, $DB->count_records('user_private_key', [
            'script' => secure_browser::KEY_SCRIPT,
            'userid' => $user->id,
            'instance' => $sessionid,
        ]));
    }

    /**
     * A valid key for another learner cannot authenticate against the session.
     */
    public function test_authenticate_rejects_wrong_session_owner(): void {
        global $DB;

        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $now = time();
        $sessionid = $DB->insert_record(session::TABLE, (object) [
            'sessionkey' => 'secure-browser-owner-test',
            'quizid' => 654,
            'userid' => $owner->id,
            'state' => session::STATE_ACTIVE,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeactive' => $now,
        ]);
        set_config('seamless_auth', 1, 'availability_proctor');
        $token = get_user_key(
            secure_browser::KEY_SCRIPT,
            (int) $other->id,
            (int) $sessionid,
            false,
            time() + secure_browser::KEY_LIFETIME
        );

        $this->expectException(\moodle_exception::class);
        secure_browser::authenticate($token, (int) $sessionid);
    }
}
