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
        $authenticated = secure_browser::authenticate($query['token'], (int) $session->id);

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
