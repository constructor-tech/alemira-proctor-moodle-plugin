# Changelog

Notable changes to the Proctor by Constructor availability condition
(`availability_proctor`), newest first.

This file starts at v2.8. Earlier releases are recorded only in the commit history.

## v2.8 (2026-08-20)

### Upgrade impact — read before updating a site running live exams

Nothing in this release changes how an existing exam behaves until somebody edits it. Both items
below are about settings that were being silently discarded and are now kept.

**A site upgrading from v2.7 or earlier stays on the hosted proctoring model.** The new SDK model is
opt-in per site; see below.

#### Group restrictions on a proctoring condition now take effect

The proctoring condition has always offered a group restriction, but the selection was never stored:
Moodle rebuilds the stored restriction from scratch on every activity edit, and this plugin omitted
the group list when doing so. The practical result was that the restriction never applied and
**every learner in scope was proctored**, whatever the teacher had selected.

The selection is now stored and honoured.

- **Existing exams are unaffected.** They carry an empty group list, which means "everyone", exactly
  as before.
- **The change appears the first time a teacher re-saves an activity** with groups selected. From
  that point on, learners outside the selected groups are *not* proctored — which is what the
  setting always said it did.
- If your policy relies on everyone being proctored regardless of group, leave the group restriction
  empty. Reviewing existing proctored activities for a stale group selection before rolling this out
  is worthwhile.

#### Four more exam settings now keep the teacher's choice

The same defect affected four settings that are also available on a preset. Because the exam's own
value was discarded, the effective value came from the preset instead, so overriding the preset for
a single exam appeared to do nothing:

- Main webcam view
- Allow multiple displays
- Allow virtual environment
- Check ID photo quality

All four are now stored per exam and take precedence over the preset. As above, existing exams keep
their current behaviour until re-saved.

#### "Send user emails" now works under the SDK model

The setting was read under the wrong name in the SDK code path, so learner email addresses were
never sent to the Proctor Dashboard regardless of the setting, and the Dashboard showed a generated
address instead. The hosted model was not affected. If your site has this enabled and uses the SDK
model, real email addresses will start reaching the Dashboard after this upgrade.

### Added

- **SDK proctoring model.** A second way of running a proctored session, alongside the existing
  hosted flow. Instead of sending the learner to a Constructor-hosted page, the Constructor Proctor
  SDK runs on this plugin's own launch page with the quiz embedded in a same-origin iframe. It was
  added for sites whose browsers refuse the hosted flow's third-party cookies.
  - Chosen **site-wide** under *Site administration ▸ Plugins ▸ Availability restrictions ▸ Proctor
    by Constructor ▸ Settings*, not per exam.
  - **Quizzes only.** Assignments and SCORM packages always use the hosted model.
  - **Needs Moodle 3.11 or newer.** The plugin still installs and runs the hosted model on 3.8–3.10;
    a site below the floor behaves exactly as a hosted site even if the setting says SDK.
  - **Upgrading sites keep the hosted model.** Only a clean install starts on SDK.
  - The hosted flow is unchanged and remains supported.
- **Sixteen SDK interface settings** on the integration settings page (shown only under the SDK
  model), six of which an individual exam may override. They control what the learner sees during
  the session: Room Scan duration, focus and full-screen alerts, the chat panel, the identification
  onboarding text, translation overrides and so on.
- **New capability `availability/proctor:exemptproctoring`** — lets a role sit a proctored quiz
  without proctoring. Honoured under both proctoring models. Quizzes only: assignments and SCORM
  packages ignore it. Granted to nobody by default; a site that does not grant it sees no change.
- **New scheduled task**, *Remove proctoring records for deleted quizzes*, running nightly. A safety
  net: it removes session records belonging to quizzes that no longer exist, covering deletion paths
  that fire no event (deleting a whole course is one).
- Privacy API coverage for the new session tables, including export and deletion.

### Changed

- Proctoring session records for the SDK model live in two new tables
  (`availability_proctor_sess`, `availability_proctor_deny`). The hosted model continues to use
  `availability_proctor_entries`; no data is migrated.

### Fixed

Each of these was a setting that appeared to do nothing. See *Upgrade impact* above for what changes
on a live site and when.

- Group restrictions on a proctoring condition were never stored, so every learner in scope was
  proctored.
- Main webcam view, Allow multiple displays, Allow virtual environment and Check ID photo quality
  were never stored per exam, so the preset's value was used instead of the teacher's.
- "Send user emails" was read under the wrong name in the SDK code path and had no effect there.
- An unrecognised value in one of the six per-exam SDK interface settings switched that setting off
  instead of falling back to the site default. It could reach the browser as a negative Room Scan
  duration, or with full-screen and focus violation alerts silently disabled. Such a value now
  defers to the site setting. Values chosen on the exam form were never affected.
