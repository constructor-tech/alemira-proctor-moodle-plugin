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
 * Tests for SDK session payload construction.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\payload
 */
final class payload_test extends \advanced_testcase {
    /**
     * Secure Browser gets a session-bound login bridge only when both controls
     * are enabled; ordinary SDK sessions keep the public quiz URL.
     */
    public function test_secure_browser_session_url_uses_seamless_auth_bridge(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cm = (object) ['id' => 42];
        $session = (object) ['id' => 73];
        $settings = (object) ['securebrowser' => true];
        set_config('seamless_auth', 1, 'availability_proctor');

        $method = new \ReflectionMethod(payload::class, 'session_url_for_settings');
        $method->setAccessible(true);
        $url = $method->invoke(null, $cm, $session, $user, $settings);

        $this->assertStringContainsString('/availability/condition/proctor/sdk_entry.php', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('73', $query['sessionid']);

        $key = validate_user_key(
            $query['token'],
            secure_browser::KEY_SCRIPT,
            (int) $session->id
        );
        $this->assertEquals($user->id, $key->userid);

        $settings->securebrowser = false;
        $plainurl = $method->invoke(null, $cm, $session, $user, $settings);
        $this->assertStringContainsString('/mod/quiz/view.php?id=42', $plainurl);
        $this->assertStringNotContainsString('token=', $plainurl);
    }

    /**
     * A learner's override of the time limit and close date reaches the
     * duration and endDate claims, so Proctor gets the time Moodle gives them.
     */
    public function test_user_override_sets_duration_and_end_date(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timelimit' => 1800,
            'timeclose' => $now + HOURSECS,
        ]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'timelimit' => 5400,
            'timeclose' => $now + 3 * HOURSECS,
        ]);

        $effective = utils::quiz_for_user((int) $quiz->id, (int) $user->id);
        $duration = payload::duration_minutes($effective);
        $this->assertSame(90, $duration);

        // PHP 7.0 requires long list syntax.
        // phpcs:disable Universal.Lists.DisallowLongListSyntax.Found
        list(, $enddate) = payload::exam_window($effective, $duration);
        // phpcs:enable Universal.Lists.DisallowLongListSyntax.Found
        $expected = (new \DateTimeImmutable('@' . ($now + 3 * HOURSECS)))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(\DateTimeInterface::ATOM);
        $this->assertSame($expected, $enddate);
    }

    /**
     * A profile picture URL must use the output class available on Moodle 3.11.
     */
    public function test_profile_picture_url_uses_compatible_output_class(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $user->picture = 1;

        $method = new \ReflectionMethod(payload::class, 'profile_picture_url');
        $method->setAccessible(true);
        $url = $method->invoke(null, $user);

        $this->assertNotEmpty($url);
        $this->assertStringContainsString('/tokenpluginfile.php/', $url);
        $this->assertStringContainsString('/user/icon/', $url);
    }
}
