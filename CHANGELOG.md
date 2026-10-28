# Changelog

Notable changes to the Proctor by Constructor availability condition
(`availability_proctor`), newest first.

This file starts at v2.8. Earlier releases are recorded in the Git history.

## Unreleased

- Fixed teachers being sent to Proctor when opening a proctored assignment or
  SCORM activity, which blocked grading. Users who can grade the assignment or
  view SCORM reports now see the normal Moodle page, as quiz previews already
  did.
- Demo (trial) exams now always start in post-exam review mode instead of the
  preset's proctoring mode, under both the Webapp and SDK models, so a demo
  session is never connected to a live proctor.
- Fixed the Proctor entry limit ignoring Moodle quiz attempt overrides. A learner
  granted extra attempts by a user or group override can now start a new
  proctored attempt instead of being returned to their last finished one.
- Added the missing `cachedef_session` language string and replaced raw SQL in
  the upgrade steps with Moodle's DML API (plugin review issues #39 and #40).
- Fixed SDK relaunch after an earlier session was activated but never bound to a
  quiz attempt. Expired unbound sessions are now replaced before Moodle creates
  the attempt, preventing a live attempt from being stranded with `no_session`.
- Moved the site-wide Proctoring model selector below Seamless authorization.
- Moved all sixteen SDK configuration parameters exclusively into global and
  personal presets. The integration settings page and activity restriction form
  no longer expose editable SDK configuration controls. Preset forms show the
  five SDK groups only under the SDK model, without a redundant outer section;
  Webapp edits keep the stored values hidden and unchanged.
- Selecting a preset now copies all sixteen SDK values into the condition as a
  hidden snapshot. Unlinking a preset by editing another exam field displays
  **Custom** and preserves the snapshot; later preset edits are not retroactive,
  while explicitly re-selecting the preset refreshes it.
- Added preset validation for non-negative whole Room Scan seconds, optional
  HTTP(S) support URLs, and the two support-managed JSON fields. Duplicate and
  Save as personal preset deep-copy the complete SDK snapshot.
- Fixed the SDK hand-off to Constructor Secure Browser so Chrome does not show
  an unnecessary leave-page confirmation before opening the native app.
- Added a session-bound, single-use seamless-login bridge for SDK Secure Browser
  sessions. The native app can open the protected Moodle quiz without asking the
  learner to log in again, while ordinary top-level quiz tabs remain blocked.
- Pass the seamless-login bridge through the SDK's `LDBLaunchUrl` parameter.
  Without this explicit parameter, the SDK launches the current Moodle launch
  page in the native app's empty cookie store and Moodle displays its login page.
- After authenticating Secure Browser, return to the SDK launch page rather than
  opening the quiz directly. This ensures system checks and recording start
  before Moodle permits the attempt.
- Fixed SDK token generation on Moodle 3.11 when developer debugging requires
  the quiz module context before generating the learner profile image URL.
- Fixed PHP 7.0 compatibility by avoiding PHP 7.1 array-destructuring syntax
  in production code.
- Fixed SDK launch with preliminary identity checks on Moodle 3.11 by using the
  cross-version global `user_picture` output class.

## v2.8 (2026-08-20)

### Upgrade impact — read before updating a live site

An upgrade from v2.7 or earlier stays on the **Webapp** model and preserves
existing conditions, credentials, entries and report history. Clean installations
start on **SDK**. The intentional behavior changes are described below.

#### Quiz duration now comes from Moodle

Both Webapp and SDK use *Quiz settings ▸ Timing ▸ Time limit* as the proctoring
duration. The old duration stored inside the availability condition is no longer
sent. A proctored quiz without a Moodle time limit is rejected instead of silently
opening a 480-minute session. Non-quiz Webapp activities keep the existing
480-minute fallback.

Check existing quiz time limits before upgrading, especially where the old
condition duration differed from the Moodle value.

#### Scheduling controls were removed

Calendar booking and automatic rescheduling are no longer supported. The Webapp
request now always sends `schedule: false`; the unused controls have been removed,
and legacy true values can no longer reopen the booking flow.

#### Group restrictions now persist

Earlier versions displayed group choices but omitted them when Moodle rebuilt the
condition, so the saved list was empty and every learner was proctored. A group
selection made and saved after this upgrade is now stored and honoured. Existing
conditions remain empty until a teacher explicitly selects groups.

Legacy site-wide group defaults are not migrated: group IDs are course-specific,
and applying values that v2.7 never persisted could unexpectedly exempt learners.

#### Legacy Custom rules remain available

The v2.7 site-wide Custom rules text continues to prefill new restrictions on an
upgraded site. Existing restrictions keep their own saved Custom rules. This field
remains exam-level and is not added to presets.

#### Four more exam settings now keep the teacher's choice

The same save omission affected these per-exam fields:

- Main webcam view
- Allow multiple displays
- Allow virtual environment
- Check ID photo quality

They are now stored per exam and take precedence over the preset. Existing exams
keep their effective behavior until a teacher saves a new choice.

### Added

- **SDK proctoring model.** The Constructor Proctor SDK runs on the plugin launch
  page with the quiz in a same-origin iframe.
  - The model is selected site-wide in the plugin settings.
  - SDK is available for quizzes on Moodle 3.11 or later.
  - Moodle 3.6–3.10 continues to run Webapp, even if an SDK value was restored
    into the database.
  - Assignment and SCORM always use Webapp.
- Sixteen SDK interface settings stored only on presets and copied into each
  configured quiz as an independent snapshot.
- Capability `availability/proctor:exemptproctoring`, granted to no role by
  default and enforced under both models for quizzes.
- A nightly task that removes SDK records belonging to deleted quizzes.
- Privacy API coverage for the SDK session tables.
- Instructor-controlled **New entry** recovery for an ended SDK session. It
  starts a new Constructor session and recording without deleting the learner's
  in-progress Moodle attempt, saved answers or remaining time.

### Changed

- SDK quiz pre-flight pages now hide Moodle site and course navigation after
  proctoring starts. Quiz attempt pages keep their right-hand Quiz navigation
  while continuing to hide links that leave the protected activity.
- SDK session records use `availability_proctor_sess` and
  `availability_proctor_deny`. Webapp continues to use
  `availability_proctor_entries`; no historical rows are migrated.
- Reports show Webapp and SDK history together regardless of the active model.
  Detail links carry the source model so equal numeric IDs cannot open a row from
  the wrong table.
- Reports now use responsive filters, clear status and model badges, contextual
  scores, grouped review actions and a consistent detail view. External report
  links are limited to HTTP(S), and user identity fields follow Moodle's access
  rules. Technical Webapp and SDK states are grouped into six reviewer-facing
  statuses; exact source values remain available in session details. Legacy
  Webapp `success` outcomes are displayed and filtered as Accepted. Completed
  sessions default to newest-first order, with unfinished sessions below them.
- The brief SDK preparation screen now uses a centered, responsive loading state
  with clearer progress and leave-page guidance.
- SDK reports recognize terminal webhook data even when the browser-owned local
  state is stale. Ended sessions block quiz access until an instructor creates a
  New entry, and sequential sessions remain visible as separate report rows.

### Fixed

- Upgraded sites retain their v2.7 Custom rules default for new restrictions.
- Group restrictions and the four per-exam fields listed above are now persisted.
- **Send user emails** now works under SDK; it previously read the wrong setting
  name. Webapp was not affected.
- Missing SDK snapshots on upgraded conditions resolve from the migrated/default
  preset and then shipped-safe values; malformed stored fields fall back safely.
- SDK HTTP requests now load Moodle's HTTP client library explicitly, so New
  entry status checks and background finish retries do not depend on another page
  having loaded that library first.
