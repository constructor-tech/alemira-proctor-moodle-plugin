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
 * Tests for the SDK session state machine.
 *
 * transition() is what stops a replayed or out-of-order request from the browser
 * walking a session backwards -- event.php is driven by the learner's own tab, so
 * every move it asks for is untrusted input. These tests pin the legal moves, the
 * refused ones, and the timestamp rule that makes a reload safe.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\session
 */
final class session_test extends \advanced_testcase {
    /**
     * Insert a session row in the given state.
     *
     * @param string $state One of session::STATE_*.
     * @return \stdClass The stored row.
     */
    private function make_session(string $state): \stdClass {
        global $DB;
        $now = time();
        $row = (object) [
            'sessionkey' => 'key-' . $state . '-' . $now . '-' . random_int(1000, 9999),
            'quizid' => 1,
            'userid' => 2,
            'attemptid' => 1,
            'state' => $state,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $row->id = $DB->insert_record(session::TABLE, $row);
        return $DB->get_record(session::TABLE, ['id' => $row->id]);
    }

    /**
     * Every move the lifecycle allows must be accepted.
     *
     * @dataProvider legal_transition_provider
     * @param string $from Starting state.
     * @param string $to Requested state.
     */
    public function test_legal_transitions_are_accepted(string $from, string $to): void {
        global $DB;
        $this->resetAfterTest();

        $session = $this->make_session($from);

        $this->assertTrue(session::transition($session, $to), "{$from} -> {$to} must be allowed");
        $this->assertSame($to, $DB->get_field(session::TABLE, 'state', ['id' => $session->id]));
    }

    /**
     * Legal moves, taken from session::TRANSITIONS so the data cannot drift
     * from the constant it is meant to describe.
     *
     * @return array[]
     */
    public static function legal_transition_provider(): array {
        $cases = [];
        foreach (session::TRANSITIONS as $to => $froms) {
            foreach ($froms as $from) {
                $cases["{$from} -> {$to}"] = [$from, $to];
            }
        }
        return $cases;
    }

    /**
     * A move the lifecycle does not list must be refused and must not write.
     *
     * The finished -> active case is the one that matters: it is what a replayed
     * token.php request looks like after the exam has already been submitted.
     *
     * @dataProvider illegal_transition_provider
     * @param string $from Starting state.
     * @param string $to Requested state.
     */
    public function test_illegal_transitions_are_refused(string $from, string $to): void {
        global $DB;
        $this->resetAfterTest();

        $session = $this->make_session($from);

        $this->assertFalse(session::transition($session, $to), "{$from} -> {$to} must be refused");
        $this->assertSame(
            $from,
            $DB->get_field(session::TABLE, 'state', ['id' => $session->id]),
            'a refused transition must leave the row alone'
        );
    }

    /**
     * Moves that are not in TRANSITIONS, including every way back out of a
     * terminal state.
     *
     * @return array[]
     */
    public static function illegal_transition_provider(): array {
        $all = [session::STATE_PENDING, session::STATE_ACTIVE, session::STATE_FINISHING,
            session::STATE_FINISHED, session::STATE_ABANDONED];
        $cases = [];
        foreach ($all as $from) {
            foreach ($all as $to) {
                $legal = session::TRANSITIONS[$to] ?? [];
                if (!in_array($from, $legal, true)) {
                    $cases["{$from} -> {$to}"] = [$from, $to];
                }
            }
        }
        return $cases;
    }

    /**
     * Re-entering ACTIVE must not move timeactive.
     *
     * Reloading the launch page before the exam starts re-signs a JWT and asks for
     * ACTIVE again. timeactive marks when proctoring began; overwriting it would
     * hide the gap a reload leaves in the recording.
     */
    public function test_reentering_active_keeps_the_original_timestamp(): void {
        global $DB;
        $this->resetAfterTest();

        $session = $this->make_session(session::STATE_PENDING);
        session::transition($session, session::STATE_ACTIVE);
        $first = $DB->get_field(session::TABLE, 'timeactive', ['id' => $session->id]);

        $this->assertNotEmpty($first, 'entering ACTIVE must stamp timeactive');

        // Re-enter ACTIVE, as a launch-page reload does.
        $reread = $DB->get_record(session::TABLE, ['id' => $session->id]);
        $this->assertTrue(session::transition($reread, session::STATE_ACTIVE));

        $this->assertSame(
            $first,
            $DB->get_field(session::TABLE, 'timeactive', ['id' => $session->id]),
            'timeactive must record when proctoring began, not the latest reload'
        );
    }

    /**
     * A stale unbound session must be replaced before Moodle creates an attempt.
     *
     * Otherwise a later attempt_started event rejects the session as expired,
     * leaving a live Moodle attempt with no proctoring session and showing the
     * learner error_nosession after all SDK checks have completed.
     */
    public function test_get_or_create_replaces_an_expired_unbound_session(): void {
        global $DB;
        $this->resetAfterTest();

        $old = $this->make_session(session::STATE_ACTIVE);
        $expired = time() - config::BIND_WINDOW - 1;
        $DB->update_record(session::TABLE, (object) [
            'id' => $old->id,
            'attemptid' => null,
            'timecreated' => $expired,
            'timemodified' => $expired,
            'timeactive' => $expired,
        ]);

        $new = session::get_or_create((int) $old->quizid, (int) $old->userid);
        $old = $DB->get_record(session::TABLE, ['id' => $old->id]);

        $this->assertNotEquals($old->id, $new->id);
        $this->assertSame(session::STATE_ABANDONED, $old->state);
        $this->assertSame('bind_timeout', $old->reason);
        $this->assertSame(session::STATE_PENDING, $new->state);
        $this->assertEmpty($new->attemptid);
    }

    /**
     * Reloading during the bind window still reuses the same Constructor session.
     */
    public function test_get_or_create_reuses_a_fresh_unbound_session(): void {
        global $DB;
        $this->resetAfterTest();

        $existing = $this->make_session(session::STATE_ACTIVE);
        $DB->set_field(session::TABLE, 'attemptid', null, ['id' => $existing->id]);

        $found = session::get_or_create((int) $existing->quizid, (int) $existing->userid);

        $this->assertEquals($existing->id, $found->id);
        $this->assertSame(session::STATE_ACTIVE, $found->state);
        $this->assertSame(1, $DB->count_records(session::TABLE));
    }

    /**
     * An unknown state name is refused rather than stored.
     */
    public function test_unknown_state_is_refused(): void {
        global $DB;
        $this->resetAfterTest();

        $session = $this->make_session(session::STATE_ACTIVE);

        $this->assertFalse(session::transition($session, 'nonsense'));
        $this->assertSame(
            session::STATE_ACTIVE,
            $DB->get_field(session::TABLE, 'state', ['id' => $session->id])
        );
    }

    /**
     * Only the states that allow access may serve a guarded quiz script.
     *
     * PENDING must not be in that list: the attempt exists with a running timer
     * before token.php has made the session active, so serving the quiz then would
     * hand out questions before proctoring had started.
     */
    public function test_pending_does_not_allow_access(): void {
        $this->assertNotContains(
            session::STATE_PENDING,
            session::STATES_ALLOWING_ACCESS,
            'a pending session must never serve the quiz'
        );
        $this->assertContains(session::STATE_ACTIVE, session::STATES_ALLOWING_ACCESS);
    }

    /**
     * Only an explicit document navigation is considered top-level. Missing
     * headers remain fail-open so a proxy cannot lock a learner out.
     */
    public function test_top_level_detection_fails_open(): void {
        $before = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? null;

        try {
            unset($_SERVER['HTTP_SEC_FETCH_DEST']);
            $this->assertFalse(session::is_top_level_request());

            $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
            $this->assertFalse(session::is_top_level_request());

            $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
            $this->assertTrue(session::is_top_level_request());
        } finally {
            if ($before === null) {
                unset($_SERVER['HTTP_SEC_FETCH_DEST']);
            } else {
                $_SERVER['HTTP_SEC_FETCH_DEST'] = $before;
            }
        }
    }
}
