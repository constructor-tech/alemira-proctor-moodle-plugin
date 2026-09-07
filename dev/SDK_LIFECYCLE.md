# SDK session lifecycle

This document records the verified Moodle/Constructor boundary. It is an
internal maintainer reference and is excluded from customer ZIP files.

## Normal flow

1. `launch.php` validates access and creates or reuses one pending local session.
2. `token.php` signs the SDK payload and activates that session.
3. Constructor runs system checks and starts recording.
4. Moodle creates the quiz attempt; the `attempt_started` observer binds the
   session to it. Guarded quiz routes use the newest session for that attempt.
5. A Moodle submission finishes the local row and asks Constructor to finish.
   Constructor reports this as `Closed by remote system`.
6. The result webhook stores the final conclusion, score, report and recording.

SDK configuration is site-wide. Saved values are sent to every subsequently
started SDK session. They do not change a session that has already initialized.
Six supported fields may be overridden by an exam restriction. Some values are
visible only when their related SDK step is enabled.

The learner's email address is included only when **Send user emails** is
enabled. When disabled, the claim is omitted rather than sent empty.

## Secure Browser hand-off

Constructor Secure Browser uses a separate cookie store and cannot reuse the
learner's Chrome or Edge Moodle login. When both **Require Secure Browser** and
**Seamless authorization** are enabled, the SDK payload therefore carries a
short-lived, single-use Moodle entry URL instead of the ordinary quiz URL.

That URL is bound to the learner and the exact SDK session row. Consuming it
logs the learner into the native app's Moodle session, removes the key and marks
only that cookie session as the Secure Browser route. It then returns to
`launch.php` inside the native app, where the SDK performs its system checks and
starts proctoring before embedding the quiz. Redirecting the bridge straight to
the quiz is forbidden: it would let Moodle create an attempt while Constructor
remained `ready_to_start`. The normal browser still cannot open the protected
attempt in a top-level tab. Secure Browser sessions also omit the plugin's
ordinary leave-page guard because replacing the browser page with the native app
is an intentional transition.

The URL must also be passed to `proctoring.init()` as `LDBLaunchUrl`. The SDK's
AppDownload screen otherwise replaces only the protocol of
`window.location.href`, causing the native app to reopen the unauthenticated
Moodle launch page instead of consuming the hand-off key.

## Other closure paths

- SDK **Finish exam** and a live-proctor close both send `examFinished`. Moodle
  submits the attempt, but does not call the remote finish endpoint. Constructor
  shows `Closed by user` or `Closed by the proctor`; Moodle cannot distinguish
  those two actors from the SDK event.
- Closing the tab does not immediately end either the Moodle attempt or the
  Constructor session. Re-entry during Constructor's reconnect window reuses
  the same session and Moodle attempt after system checks run again.
- After the Constructor reconnect timeout, the session is terminal and its ID
  cannot be reused. A later result webhook may be the first terminal evidence
  Moodle receives, while the Moodle attempt is still in progress.

## Status and access rules

- Final conclusions `accepted` and `rejected` take precedence in reports.
- Terminal data without a final conclusion is shown as **Needs review**.
- Pending/active sessions are **Not started** or **In progress**.
- Locally abandoned sessions without terminal evidence are **Closed**.
- `sessionEnd` or `timeresult` blocks access even if the local state is stale.
  The learner is told that an instructor must create **New entry**.
- Attempt and summary pages require both a session that still permits access and
  either an iframe request or the authenticated native Secure Browser marker.
  Opening the same live attempt in an ordinary top-level tab is rejected and
  recorded as `sec_fetch_dest`; the valid exam route remains usable.
- Answer POSTs require an access-permitting session but do not use the
  top-level-document check because `processattempt.php` is a POST target.
- The Constructor status API is used only when an instructor requests New entry.
  It exposes whether the session is finished, not the dashboard closure actor.

## New entry recovery

New entry is instructor-controlled and applies only to the newest SDK row whose
Moodle attempt is still in progress.

1. Lock the attempt to make repeated requests idempotent.
2. Require terminal webhook data, a never-started pending row, or a remote
   `finished` status. Fail closed if status cannot be verified.
3. Mark the old open local row as replaced; never delete it.
4. Create a fresh pending row with a new Constructor session ID, pre-bound to the
   same Moodle attempt.
5. The learner continues the same attempt with saved answers and remaining
   Moodle time, but produces a separate Constructor recording and report row.

Late events from the replaced session may update that old report row, but cannot
submit the attempt protected by the newer session.

## Verified New entry scenario

The complete recovery path was verified on 2026-08-23 with one 30-minute Moodle
attempt and two sequential Constructor sessions:

- Constructor timed out the first session while Moodle kept the attempt open.
- New entry created one fresh session pre-bound to the same attempt.
- A repeated request was idempotent and created no additional session.
- Re-entry preserved the saved answer and original timer; disconnected time was
  not refunded.
- Normal submission finished the replacement session and the Moodle attempt.
- Reports retained both sessions with distinct report/video links and the same
  Moodle attempt.

If the learner does not return before the Moodle time limit, Moodle's configured
overdue handling applies. New entry never pauses or extends the quiz timer and
cannot recover an attempt after Moodle has closed it.
