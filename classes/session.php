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
 * The proctoring session state machine, and the access gate that reads it.
 *
 * Design note, because the states are not symmetrical and the asymmetry is
 * deliberate: PENDING and FINISHED block access. FINISHING and ABANDONED allow
 * recovery only while Constructor has not supplied terminal session data.
 *
 * The reason is that the SDK gives us no way to prevent or intercept the student
 * pressing its own "Finish exam" button, and no way to distinguish a deliberate
 * close from a crash. If those events blocked access, a student who pressed the
 * wrong sidebar button, or whose SDK crashed, would be locked out of an exam
 * mid-attempt and lose their work. That is a worse outcome than an unproctored
 * tail, which is visible to the reviewer through the timefinishing timestamp.
 *
 * Once sessionEnd or a result webhook proves recording ended, access is blocked
 * until an instructor creates New entry. That preserves the attempt and answers
 * without allowing an unproctored tail or reusing a closed Constructor session.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session {
    /** @var string Session row created by launch.php; no JWT signed yet. Blocks access. */
    const STATE_PENDING = 'pending';

    /** @var string token.php signed a JWT. Blocks nothing: this is the working state. */
    const STATE_ACTIVE = 'active';

    /** @var string examFinished resolved; normally followed immediately by submission. */
    const STATE_FINISHING = 'finishing';

    /** @var string The attempt was submitted. Terminal, and blocks access. */
    const STATE_FINISHED = 'finished';

    /** @var string appUnmounted or an instructor replacement closed the local row. */
    const STATE_ABANDONED = 'abandoned';

    /** @var string Table holding session rows. */
    const TABLE = 'availability_proctor_sess';

    /** @var string Table holding blocked access attempts. */
    const TABLE_DENIED = 'availability_proctor_deny';

    /**
     * States from which each state may be entered.
     *
     * Transitions are one-way. ABANDONED is reachable from any non-terminal
     * state because the SDK can unmount at any point.
     *
     * @var array
     */
    const TRANSITIONS = [
        self::STATE_ACTIVE => [self::STATE_PENDING, self::STATE_ACTIVE],
        self::STATE_FINISHING => [self::STATE_ACTIVE],
        // PENDING is included because the attempt exists, with a running timer,
        // from the moment the launch page creates it -- before token.php has made
        // the session active. A student who closes the tab during preparation, or
        // whose token request fails, leaves a pending session against a live
        // attempt, and core will eventually abandon that attempt and fire
        // attempt_abandoned. Without this the row would sit in PENDING forever:
        // never terminal, timefinished never set, and prevent_access() reporting
        // state_pending for an attempt that is actually dead.
        self::STATE_FINISHED => [
            self::STATE_PENDING,
            self::STATE_ACTIVE,
            self::STATE_FINISHING,
            self::STATE_ABANDONED,
        ],
        self::STATE_ABANDONED => [self::STATE_PENDING, self::STATE_ACTIVE, self::STATE_FINISHING],
    ];

    /** @var array States in which a guarded quiz script may be served. */
    const STATES_ALLOWING_ACCESS = [
        self::STATE_ACTIVE,
        self::STATE_FINISHING,
        self::STATE_ABANDONED,
    ];

    /** @var array States in which a session is still live and may be reused. */
    const STATES_OPEN = [
        self::STATE_PENDING,
        self::STATE_ACTIVE,
        self::STATE_FINISHING,
    ];

    /**
     * Fetch the newest session row bound to an attempt, if there is one.
     *
     * New entry deliberately permits sequential Constructor sessions to share a
     * Moodle attempt. Every caller that enforces access or closes an attempt must
     * therefore use the newest row, while older rows remain immutable report
     * history.
     *
     * @param int $attemptid Quiz attempt id.
     * @return \stdClass|null The row, or null when the attempt has no session.
     */
    public static function get_by_attempt(int $attemptid) {
        global $DB;

        $records = $DB->get_records(
            self::TABLE,
            ['attemptid' => $attemptid],
            'id DESC',
            '*',
            0,
            1
        );
        $record = $records ? reset($records) : null;

        return $record ?: null;
    }

    /**
     * Fetch the newest session row for a learner and quiz, regardless of state.
     *
     * @param int $quizid Quiz id.
     * @param int $userid The student.
     * @return \stdClass|null
     */
    public static function get_latest_for_quiz(int $quizid, int $userid) {
        global $DB;

        $records = $DB->get_records(
            self::TABLE,
            ['quizid' => $quizid, 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );

        return $records ? reset($records) : null;
    }

    /**
     * Create a fresh SDK session, optionally pre-bound to an existing attempt.
     *
     * Normal launches are unbound because core has not created the attempt yet.
     * New entry is different: it intentionally resumes an existing in-progress
     * attempt, so binding at creation removes any ambiguity between its old and
     * new Constructor sessions.
     *
     * @param int $quizid Quiz id.
     * @param int $userid The student.
     * @param int|null $attemptid Existing attempt for New entry, or null.
     * @return \stdClass The inserted row.
     */
    protected static function create(int $quizid, int $userid, $attemptid = null): \stdClass {
        global $DB;

        $now = time();
        $record = new \stdClass();
        $record->attemptid = $attemptid;
        $record->quizid = $quizid;
        $record->userid = $userid;
        $record->sessionkey = self::make_session_key();
        $record->state = self::STATE_PENDING;
        $record->timecreated = $now;
        $record->timemodified = $now;
        if ($attemptid !== null) {
            $record->timebound = $now;
        }

        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Fetch this student's open session for a quiz, if there is one.
     *
     * "Open" means not yet terminal, so a finished or abandoned session does not
     * block a fresh launch. Ordered newest first: an interrupted launch can leave
     * an older open row behind, and the most recent one is the session the student
     * is actually in.
     *
     * @param int $quizid Quiz id.
     * @param int $userid The student.
     * @return \stdClass|null
     */
    public static function get_open_for_quiz(int $quizid, int $userid) {
        global $DB;

        // PHP 7.0 requires long list syntax.
        // phpcs:disable Universal.Lists.DisallowLongListSyntax.Found
        list($insql, $inparams) = $DB->get_in_or_equal(self::STATES_OPEN, SQL_PARAMS_NAMED, 'st');

        $records = $DB->get_records_select(
            self::TABLE,
            "quizid = :quizid AND userid = :userid AND state $insql "
                . 'AND (sessionend IS NULL OR sessionend = 0) '
                . 'AND (timeresult IS NULL OR timeresult = 0)',
            array_merge($inparams, ['quizid' => $quizid, 'userid' => $userid]),
            'id DESC',
            '*',
            0,
            1
        );

        return $records ? reset($records) : null;
    }

    /**
     * Return this student's open session for a quiz, creating one if needed.
     *
     * Created before the attempt exists, and therefore keyed on the quiz and the
     * student rather than on an attempt id. The student is handed off to core's
     * startattempt.php, which runs the pre-flight chain and creates the attempt
     * itself; the session binds to that attempt afterwards.
     *
     * Reloading the launch page must not create a second session: the Constructor
     * Proctor server keeps a single record per sessionId, so a second row would
     * describe a session that does not separately exist.
     *
     * @param int $quizid Quiz id.
     * @param int $userid The student.
     * @return \stdClass The session row.
     */
    public static function get_or_create(int $quizid, int $userid): \stdClass {
        global $DB;

        if ($existing = self::get_open_for_quiz($quizid, $userid)) {
            // A previous visit can leave an active but unbound row behind when
            // the learner closes the launch page before Moodle creates the quiz
            // attempt. Reusing it after the bind window has expired guarantees
            // that attempt_started will reject it, but only after core has
            // created a live attempt. Retire it here instead, while a fresh
            // session can still be created before the attempt exists.
            if (empty($existing->attemptid) && self::is_bind_expired($existing)) {
                self::transition($existing, self::STATE_ABANDONED, 'bind_timeout');
            } else {
                return $existing;
            }
        }

        // Never silently replace a session for a Moodle attempt that is still in
        // progress. This is the timeout recovery case: Constructor ended, the
        // answers still exist, and only an instructor may authorize a fresh
        // sessionId through New entry.
        $latest = self::get_latest_for_quiz($quizid, $userid);
        if ($latest && !empty($latest->attemptid)) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $latest->attemptid]);
            $attemptclass = utils::quiz_attempt_classname();
            if ($attempt && $attempt->state === $attemptclass::IN_PROGRESS) {
                throw new \moodle_exception('error_sessionended_newentry', 'availability_proctor');
            }
        }

        return self::create($quizid, $userid);
    }

    /**
     * Create the instructor-requested replacement for an ended SDK session.
     *
     * This mirrors Webapp New entry at the Moodle boundary: it preserves the
     * existing quiz attempt, answers and timer while creating a new Constructor
     * sessionId. The additional remote-status check is SDK-specific and prevents
     * two recordings being active for the same attempt.
     *
     * @param int $sessionid Session row selected in the report.
     * @param \stdClass|null $remotestatus Injected Constructor status for tests;
     *     production callers omit it and the API is queried when needed.
     * @return \stdClass|null The new row, or null when a newer entry already exists.
     * @throws \moodle_exception When replacement is unsafe or cannot be verified.
     */
    public static function create_new_entry(int $sessionid, $remotestatus = null) {
        global $DB;

        $initial = $DB->get_record(self::TABLE, ['id' => $sessionid]);
        if (!$initial) {
            return null;
        }

        $lockpart = !empty($initial->attemptid)
            ? 'attempt_' . (int) $initial->attemptid
            : 'session_' . (int) $initial->id;
        $factory = \core\lock\lock_config::get_lock_factory('availability_proctor');
        $lock = $factory->get_lock('new_entry_' . $lockpart, 10);
        if (!$lock) {
            throw new \moodle_exception('error_sdk_new_entry_busy', 'availability_proctor');
        }

        try {
            $current = $DB->get_record(self::TABLE, ['id' => $sessionid]);
            if (!$current) {
                return null;
            }

            // A repeated request against the old report row must not create a
            // second replacement. This is the server-side idempotency guarantee;
            // the form's sesskey protects a different concern (CSRF).
            if (!empty($current->attemptid)) {
                $latest = self::get_by_attempt((int) $current->attemptid);
                if (!$latest || (int) $latest->id !== (int) $current->id) {
                    return null;
                }

                $attempt = $DB->get_record('quiz_attempts', ['id' => $current->attemptid]);
                $attemptclass = utils::quiz_attempt_classname();
                if (!$attempt || $attempt->state !== $attemptclass::IN_PROGRESS) {
                    throw new \moodle_exception(
                        'error_sdk_new_entry_attempt_finished',
                        'availability_proctor'
                    );
                }
            }

            $open = self::get_open_for_quiz((int) $current->quizid, (int) $current->userid);
            if ($open && (int) $open->id !== (int) $current->id) {
                return null;
            }

            $hasterminalresult = !empty($current->sessionend) || !empty($current->timeresult);
            $neveractivated = $current->state === self::STATE_PENDING
                && empty($current->timeactive)
                && empty($current->sessionstart)
                && empty($current->timeresult);

            if (!$hasterminalresult && !$neveractivated) {
                $status = $remotestatus ?: client::get_sdk_session_status($current->sessionkey);
                $statusvalue = strtolower((string) ($status->status ?? ''));

                if ($statusvalue !== 'finished') {
                    // A missing unbound pending session never reached Constructor
                    // and is safe to replace. A bound attempt with the same answer
                    // is suspicious and stays fail-closed.
                    $safelymissing = $statusvalue === 'not_found'
                        && empty($current->attemptid)
                        && empty($current->sessionstart);
                    if (!$safelymissing) {
                        throw new \moodle_exception(
                            'error_sdk_new_entry_session_active',
                            'availability_proctor'
                        );
                    }
                } else {
                    self::record_remote_status($current, $status);
                }
            }

            // Keep the old row as report history, but remove it from the set that
            // get_or_create() can reuse. A terminal row needs no state mutation.
            if (in_array($current->state, self::STATES_OPEN, true)) {
                self::transition($current, self::STATE_ABANDONED, 'new_entry');
            }

            return self::create(
                (int) $current->quizid,
                (int) $current->userid,
                empty($current->attemptid) ? null : (int) $current->attemptid
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * Mint a fresh sessionId claim.
     *
     * Random rather than derived. Stability across a launch-page reload comes from
     * reading the value back off the persisted row in get_or_create(), not from
     * recomputing it: any derivation would have to be a pure function of inputs
     * that are all still identical on a reload, and the attempt id -- the one
     * genuinely stable input -- does not exist yet at this point.
     *
     * Carries a site fingerprint so two Moodle sites sharing an integration cannot
     * collide, but a hash of the site identifier rather than the identifier itself.
     * $CFG->siteidentifier is not always the short hash it looks like -- on some
     * installations it has the hostname appended, which made this claim over a
     * hundred characters long and put the site's hostname inside an identifier that
     * is sent off-site. Twelve hex characters of it distinguish sites just as well.
     *
     * The Developer Guide caps sessionId at 230 characters; this is 52.
     *
     * @return string
     */
    protected static function make_session_key(): string {
        global $CFG;

        return 'moodle-' . substr(md5($CFG->siteidentifier), 0, 12)
            . '-' . md5(random_string(32) . microtime(true));
    }

    /**
     * Bind an unbound session to the attempt the student has reached.
     *
     * This is the moment the handoff completes. The session is created before the
     * attempt exists, so between activation and this call it is a permission that
     * has not yet been spent -- which is why binding is once-only and why an
     * unbound session expires (see is_bind_expired()).
     *
     * The UPDATE is conditional on attemptid still being NULL, which does the work
     * of a unique constraint the schema cannot express portably: whichever request
     * arrives first binds, and a simultaneous second request affects zero rows and
     * is told to re-read. Doing this with a SELECT followed by an UPDATE would
     * leave a window in which two attempts could bind to one session.
     *
     * @param \stdClass $session The unbound session row.
     * @param int $attemptid The attempt to bind to.
     * @return bool True when this call performed the binding.
     */
    public static function bind_attempt(\stdClass $session, int $attemptid): bool {
        global $DB;

        $now = time();

        $bound = $DB->execute(
            'UPDATE {' . self::TABLE . '}
                SET attemptid = :attemptid, timebound = :timebound, timemodified = :timemodified
              WHERE id = :id AND attemptid IS NULL',
            [
                'attemptid' => $attemptid,
                'timebound' => $now,
                'timemodified' => $now,
                'id' => $session->id,
            ]
        );

        // The execute() call reports whether the statement ran, not how many rows it
        // touched, so the outcome has to be read back. A re-read also gives the
        // caller the winning attempt id when another request bound first.
        $fresh = $DB->get_record(self::TABLE, ['id' => $session->id]);
        if (!$fresh) {
            return false;
        }

        $session->attemptid = $fresh->attemptid;
        $session->timebound = $fresh->timebound;
        $session->timemodified = $fresh->timemodified;

        return $bound && (int) $fresh->attemptid === $attemptid;
    }

    /**
     * Has an unbound session been waiting too long to be spent?
     *
     * An active session with no attempt is a permission nobody has used yet, so it
     * must not sit indefinitely. The window covers the SDK preparation steps
     * (~75 seconds) and a pre-flight exchange such as a quiz password, with margin.
     *
     * Deliberately not tied to the SDK's own reconnect timeout. That one measures
     * inactivity after a disconnect on a session that is already recording, is
     * configured on the Constructor side, and cannot be read from here -- so
     * tracking it would couple this to a value we can neither see nor change.
     *
     * The window itself is config::BIND_WINDOW, a fixed constant rather than a
     * site setting: it is internal tuning, not something an institution needs to
     * configure.
     *
     * @param \stdClass $session The session row.
     * @return bool
     */
    public static function is_bind_expired(\stdClass $session): bool {
        if (!empty($session->attemptid)) {
            return false;
        }

        // Measured from activation, not creation: the clock should start when the
        // permission became usable, not when the student opened the launch page.
        $since = (int) ($session->timeactive ?: $session->timecreated);

        return (time() - $since) > config::BIND_WINDOW;
    }

    /**
     * Move a session to a new state, recording when and why.
     *
     * Refuses transitions that are not in TRANSITIONS, so a replayed or
     * out-of-order request from the browser cannot walk a session backwards.
     *
     * @param \stdClass $session The session row.
     * @param string $newstate One of the STATE_* constants.
     * @param string|null $reason Which SDK event drove this, for the record.
     * @return bool True when the state changed, false when the transition was refused.
     */
    public static function transition(\stdClass $session, string $newstate, $reason = null): bool {
        global $DB;

        if (!isset(self::TRANSITIONS[$newstate])) {
            return false;
        }

        if (!in_array($session->state, self::TRANSITIONS[$newstate], true)) {
            return false;
        }

        $now = time();

        $stampfields = [
            self::STATE_ACTIVE => 'timeactive',
            self::STATE_FINISHING => 'timefinishing',
            self::STATE_FINISHED => 'timefinished',
            self::STATE_ABANDONED => 'timeabandoned',
        ];
        $stampfield = $stampfields[$newstate];

        $update = new \stdClass();
        $update->id = $session->id;
        $update->state = $newstate;
        $update->timemodified = $now;

        // Stamp only the first entry into a state. Re-entering ACTIVE is allowed,
        // so that reloading the launch page before the exam starts can re-sign a
        // JWT, but that must not move timeactive: it marks when proctoring began,
        // and overwriting it would hide the gap a reload leaves in the recording.
        if (empty($session->{$stampfield})) {
            $update->$stampfield = $now;
        }

        // The reason is still recorded on a same-state transition. examStarted
        // arrives while the session is already ACTIVE, and it is the only signal
        // distinguishing a session that reached the exam from one abandoned during
        // preparation -- so skipping the write here would lose it entirely.
        if ($reason !== null) {
            $update->reason = $reason;
        }

        $DB->update_record(self::TABLE, $update);

        $session->state = $newstate;
        if (isset($update->$stampfield)) {
            $session->$stampfield = $now;
        }
        if ($reason !== null) {
            $session->reason = $reason;
        }

        return true;
    }

    /**
     * Record that the SDK reported examStarted.
     *
     * Only a session that is already ACTIVE is touched. examStarted is reported
     * by the learner's own tab, so it is untrusted input, and token.php must stay
     * the only route to ACTIVE: a PENDING row moved here would unlock the quiz
     * without a JWT ever being signed or the SDK ever running.
     *
     * The ACTIVE -> ACTIVE transition keeps timeactive and only writes the reason,
     * which distinguishes "started the exam" from "abandoned during preparation".
     *
     * @param \stdClass $session The session row.
     * @return bool True when the reason was recorded.
     */
    public static function record_exam_started(\stdClass $session): bool {
        if ($session->state !== self::STATE_ACTIVE) {
            return false;
        }

        return self::transition($session, self::STATE_ACTIVE, 'exam_started');
    }

    /**
     * Record that the video upload finished.
     *
     * Kept apart from the state machine on purpose. videoUploaded can resolve
     * after the attempt was submitted and the row is already FINISHED, so
     * treating it as a transition would mean rejecting a perfectly normal
     * event. A FINISHED row with a null videouploaded is a reviewer signal that
     * the recording may be incomplete, not a broken state.
     *
     * @param \stdClass $session The session row.
     * @return void
     */
    public static function record_video_uploaded(\stdClass $session) {
        global $DB;

        if (!empty($session->videouploaded)) {
            return;
        }

        $now = time();

        $DB->update_record(self::TABLE, (object) [
            'id' => $session->id,
            'videouploaded' => $now,
            'timemodified' => $now,
        ]);

        $session->videouploaded = $now;
    }

    /**
     * Where to send the student once the exam is over and the video is safe.
     *
     * The review page, matching what the reference plugin asks Constructor to do
     * through sessionFinishUrl. Core decides what that page actually shows: a
     * quiz whose review options withhold the attempt redirects on to the quiz
     * view page by itself, so no permission check is needed here.
     *
     * Returns null whenever there is no attempt to review -- a session abandoned
     * during the preparation steps never bound one -- and whenever the attempt is
     * still open. The second case matters: videoUploaded can resolve while the
     * student is still answering questions, which happens when the SDK's own
     * Finish exam button is pressed against an attempt that then fails to submit,
     * and navigating away from a live attempt would be the opposite of helpful.
     *
     * @param \stdClass $session The session row.
     * @return string|null Absolute URL, or null when the student should stay put.
     */
    public static function return_url(\stdClass $session) {
        global $DB;

        if (empty($session->attemptid)) {
            return null;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $session->attemptid]);
        $attemptclass = utils::quiz_attempt_classname();
        if (!$attempt || $attempt->state === $attemptclass::IN_PROGRESS) {
            return null;
        }

        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        // out(false) leaves the separators as plain ampersands: this is read as a
        // JSON string by the browser, not embedded in HTML.
        return (new \moodle_url('/mod/quiz/review.php', [
            'attempt' => (int) $session->attemptid,
            'cmid' => (int) $cm->id,
        ]))->out(false);
    }

    /**
     * Would this session permit a guarded quiz script to be served?
     *
     * @param \stdClass|null $session The session row, or null when there is none.
     * @return bool
     */
    public static function allows_access($session): bool {
        if ($session === null) {
            return false;
        }

        if (!empty($session->sessionend) || !empty($session->timeresult)) {
            return false;
        }

        return in_array($session->state, self::STATES_ALLOWING_ACCESS, true);
    }

    /**
     * Is this request coming from a top-level page rather than our iframe?
     *
     * Fail-open by design. Only Sec-Fetch-Dest: document is treated as a
     * top-level navigation and refused; an absent header passes. A proxy that
     * strips the header, or a browser that does not send it, must not lock a
     * student out of an exam.
     *
     * Worth being clear about what this is and is not. Once a session is
     * ACTIVE, the server cannot otherwise tell our iframe from a second tab:
     * same origin, same cookies, same user. A tab switch raises no proctoring
     * warning either (measured), so it is caught only if a human reviews the
     * video, and never in auto mode. That makes this header check the only
     * automatic control in the ACTIVE state, rather than the defence-in-depth
     * layer it was first taken for.
     *
     * <!-- TODO: not diagnosed -- whether the SDK focus watcher would fire on a
     * tab switch given a sessionUrl that points at the page actually running the
     * SDK. The test configuration pointed it elsewhere. This check is correct
     * either way, so nothing here depends on the answer. -->
     *
     * @return bool True when the request is definitely a top-level navigation.
     */
    public static function is_top_level_request(): bool {
        // Header names arrive from the web server upper-cased with dashes as
        // underscores. Read $_SERVER directly: this is a request header rather
        // than user-supplied input, so there is no Moodle parameter API for it.
        $dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';

        return $dest === 'document';
    }

    /**
     * Record a blocked access attempt.
     *
     * Deduplicated, because the guard this was written for ran on every attempt
     * page load: without this, one blocked student holding down refresh would
     * insert unbounded rows. Same attempt and same reason inside the configured
     * window counts once.
     *
     * The SDK-only route guard in lib.php calls this for attempt.php, summary.php,
     * processattempt.php and autosave.ajax.php. Webapp requests never reach that
     * guard.
     *
     * @param string $reason One of the documented reason codes.
     * @param string $script Entry point that denied: attempt, summary, processattempt or autosave.
     * @param int|null $attemptid Attempt involved, when known.
     * @param int|null $quizid Quiz involved, when known.
     * @param \stdClass|null $session Session row involved, when there was one.
     * @return void
     */
    public static function record_denial(
        string $reason,
        string $script,
        $attemptid = null,
        $quizid = null,
        $session = null
    ) {
        global $DB, $USER;

        $now = time();

        // The window is config::DENIAL_WINDOW, a fixed constant rather than a
        // site setting: it is internal tuning, not something an institution
        // needs to configure.
        if ($attemptid !== null) {
            $recent = $DB->record_exists_select(
                self::TABLE_DENIED,
                'attemptid = :attemptid AND reason = :reason AND timecreated > :since',
                [
                    'attemptid' => $attemptid,
                    'reason' => $reason,
                    'since' => $now - config::DENIAL_WINDOW,
                ]
            );
            if ($recent) {
                return;
            }
        }

        $DB->insert_record(self::TABLE_DENIED, (object) [
            'attemptid' => $attemptid,
            'quizid' => $quizid,
            'userid' => $USER->id,
            'sessionid' => $session->id ?? null,
            'sessionstate' => $session->state ?? null,
            'reason' => $reason,
            'script' => $script,
            'timecreated' => $now,
        ]);
    }

    /**
     * Ask Constructor Proctor to finish a session, and record that it worked.
     *
     * Idempotent: a session already marked as finished on the server is left alone,
     * so a queued retry that overlaps a successful direct call does no harm.
     *
     * @param int $sessionid Row id.
     * @return bool True when the session is settled and no retry is needed.
     */
    public static function request_finish_by_id(int $sessionid): bool {
        global $DB;

        $session = $DB->get_record(self::TABLE, ['id' => $sessionid]);

        if (!$session) {
            // The session is gone, so there is nothing left to finish and nothing a
            // retry could achieve.
            return true;
        }

        if (!empty($session->timefinishsent)) {
            return true;
        }

        // finish_sdk_session(), not finish_session(): the latter is the Webapp
        // flow's own call -- an instance method taking a redirect URL as well --
        // and calling it statically with one argument was a fatal Error on every
        // SDK submit. See the docblock on finish_sdk_session() for why they are
        // separate methods rather than one.
        if (!client::finish_sdk_session($session->sessionkey)) {
            return false;
        }

        $now = time();
        $DB->update_record(self::TABLE, (object) [
            'id' => $session->id,
            'timefinishsent' => $now,
            'timemodified' => $now,
        ]);

        return true;
    }

    /**
     * Store a proctoring result arriving from the webhook.
     *
     * Constructor Proctor repeats this call as fields become known, so every field
     * is overwritten each time rather than filled in only when empty: the newest
     * message is the authoritative one.
     *
     * @param \stdClass $session The session row.
     * @param array $result Decoded webhook body.
     * @return void
     */
    public static function record_result(\stdClass $session, array $result) {
        global $DB;

        $now = time();

        $update = new \stdClass();
        $update->id = $session->id;
        $update->timemodified = $now;
        $update->timeresult = $now;

        $update->conclusion = isset($result['conclusion'])
            ? (string) $result['conclusion'] : null;
        $update->comment = isset($result['comment']) ? (string) $result['comment'] : null;
        $update->reporturl = isset($result['reportUrl']) ? (string) $result['reportUrl'] : null;
        $update->archiveurl = isset($result['archive']) ? (string) $result['archive'] : null;

        // Absent and zero are different: a missing score means not yet calculated,
        // while zero means calculated as clean.
        $update->score = isset($result['score']) && is_numeric($result['score'])
            ? (int) $result['score'] : null;

        foreach (
            ['threshold' => 'threshold', 'warnings' => 'resultwarnings',
                'warningTitles' => 'warningtitles'] as $key => $column
        ) {
            $update->$column = isset($result[$key]) ? json_encode($result[$key]) : null;
        }

        foreach (['sessionStart' => 'sessionstart', 'sessionEnd' => 'sessionend'] as $key => $column) {
            $update->$column = !empty($result[$key]) ? self::parse_date($result[$key]) : null;
        }

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Store the timestamps returned by Constructor's session-status endpoint.
     *
     * Unlike record_result(), this must not touch conclusions, scores or links:
     * the status response does not carry them, and clearing a later webhook's
     * forensic data while creating New entry would be data loss.
     *
     * @param \stdClass $session Local SDK session row.
     * @param \stdClass $status Decoded Constructor status response.
     * @return void
     */
    public static function record_remote_status(\stdClass $session, \stdClass $status) {
        global $DB;

        $update = (object) [
            'id' => $session->id,
            'timemodified' => time(),
        ];
        $changed = false;

        foreach (['sessionStart' => 'sessionstart', 'sessionEnd' => 'sessionend'] as $key => $column) {
            if (!empty($status->$key)) {
                $parsed = self::parse_date((string) $status->$key);
                if ($parsed !== null) {
                    $update->$column = $parsed;
                    $session->$column = $parsed;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $DB->update_record(self::TABLE, $update);
        }
    }

    /**
     * Convert an ISO 8601 timestamp from the webhook into a Unix timestamp.
     *
     * @param string $value The reported date and time.
     * @return int|null Null when it cannot be parsed, so a bad value does not become 1970.
     */
    protected static function parse_date(string $value) {
        try {
            return (new \DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mark the session for an attempt as finished, because the attempt is over.
     *
     * Submission alone drives the FINISHED state, and it is detected on the
     * server rather than reported by the browser. That matters: it means the
     * row still reaches a terminal state when the browser dies immediately
     * after submitting, which a browser-reported transition could not promise.
     *
     * @param int $attemptid Quiz attempt id.
     * @param string|null $reason Why the attempt ended, when it was not a plain submission.
     * @return void
     */
    public static function mark_submitted(int $attemptid, $reason = null) {
        $session = self::get_by_attempt($attemptid);
        if ($session === null) {
            return;
        }

        // A session still PENDING when its attempt ends never started proctoring
        // at all: the student left during preparation, or token.php failed. Worth
        // distinguishing in the record from a session that ran and finished
        // normally, since the two look identical once both are FINISHED.
        if ($reason === null && $session->state === self::STATE_PENDING) {
            $reason = 'never_started';
        }

        self::transition($session, self::STATE_FINISHED, $reason);
    }
}
