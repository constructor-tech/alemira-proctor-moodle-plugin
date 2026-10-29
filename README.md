# Proctor by Constructor for Moodle

`availability_proctor` is a Moodle availability condition that protects an
activity with Constructor Proctor. It supports two site-wide integration models:

- **Webapp** redirects the learner to the Constructor-hosted proctoring page.
- **SDK** runs Constructor Proctor on Moodle's launch page and embeds the quiz in
  a same-origin iframe.

A Constructor Proctor account and integration credentials are required.

## Requirements and compatibility

| Model | Moodle | Activities |
|---|---|---|
| Webapp | 3.6 or later | Quiz, Assignment and SCORM |
| SDK | 3.11 or later | Quiz only |

MySQL/MariaDB and PostgreSQL are supported through Moodle's database API.
Production PHP remains compatible with PHP 7.0 for legacy Moodle installations.

Existing sites upgrading from v2.7 or earlier remain on Webapp. Clean
installations start on SDK. The model can be changed under the plugin settings;
Assignment and SCORM continue to use Webapp regardless of that choice.

## Installation

1. Download the latest ZIP from the
   [GitHub releases page](https://github.com/constructor-tech/alemira-proctor-moodle-plugin/releases).
2. In Moodle, open *Site administration ▸ Plugins ▸ Install plugins*.
3. Upload the ZIP and complete the installation.

For Apache with `mod_php`, enable `mod_rewrite` and allow `.htaccess` overrides
for the plugin directory. If overrides are disabled, apply the rules from the
included `.htaccess` file in the relevant virtual host configuration.

## Integration setup

1. Request the integration name, secret, account name and account ID from the
   Constructor Proctor team.
2. Open *Site administration ▸ Plugins ▸ Availability restrictions ▸ Proctor by
   Constructor ▸ Settings*.
3. Enter the integration values and choose the site-wide **Webapp** or **SDK**
   model.
4. Configure whether learner email addresses are sent and whether seamless
   Moodle authentication is enabled.

SDK interface configuration is managed only through global and personal
presets; the integration settings page contains no SDK configuration section.

### Content Security Policy

Moodle sends no Content-Security-Policy header of its own. If your web server
or proxy adds one, the SDK model needs at least:

- `script-src`: `https://sdk.web.proctor.constructor.app`, and inline module
  scripts, because the launch page starts the SDK from an inline
  `<script type="module">`.
- `connect-src`: the Proctor server URL from the integration settings.
- `frame-src` and `frame-ancestors`: `'self'`, because the quiz runs in a
  same-origin iframe on the launch page.

The SDK may contact further Constructor services. Test a launch with the
browser console open, and ask Constructor support for the current list.

## Add proctoring to a quiz

1. In the quiz settings, set *Timing ▸ Time limit*. Both models use this value as
   the proctoring duration; a proctored quiz without a time limit cannot start.
2. Under *Restrict access*, choose *Add restriction… ▸ Proctor by Constructor*.
3. Load a preset or configure the visible exam fields such as proctoring mode,
   identification, cameras, secure browser, rules, warnings and scoring.
4. Add exam-specific Custom rules when needed.
5. Optionally select Moodle groups. With no groups selected, every learner in
   scope is proctored; otherwise only members of the selected groups are.

Calendar booking and automatic rescheduling are not supported by this version.

Each preset always stores all sixteen SDK configuration parameters. They are
shown as five direct groups on the preset form only while the site uses the SDK
model; Webapp sites hide them because Webapp ignores them. Selecting a preset
copies those values into the quiz as a hidden snapshot. Editing another exam
field changes the displayed preset state to **Custom** but preserves the SDK
snapshot. Later preset edits do not change configured quizzes; select the preset
again to refresh the snapshot intentionally. Assignment, SCORM and every Webapp
flow ignore these SDK values.

## Reports and additional attempts

The report under *Site administration ▸ Reports ▸ Proctor by Constructor* shows
historical Webapp and SDK sessions together, even after the site model changes.

For Webapp, an administrator can use **New entry** to create a fresh Constructor
entry. For SDK, the button is available on the latest ended session while its
Moodle quiz attempt is still in progress. It creates a new Constructor session
and recording while preserving the learner's Moodle attempt, saved answers and
remaining time. The current Constructor session must be confirmed as ended, so
two recordings cannot be active for the same attempt.

## Special accommodations

The Webapp payload can include a learner's special accommodations:

1. Open *Site administration ▸ Users ▸ User profile fields*.
2. Create a **Text area** profile field.
3. Set its short name to `proctor_special_accommodations`.

## Upgrade behavior

- Existing sites remain on Webapp and retain their stored exams and history.
- Legacy site defaults that fit the preset model are migrated into an editable
  global preset.
- The legacy site-wide Custom rules text continues to prefill new restrictions.
- Legacy site-wide group defaults are not applied because group IDs are
  course-specific and v2.7 did not persist them on saved conditions.

See [CHANGELOG.md](CHANGELOG.md) for complete release impact.

## Development

These commands apply to the source repository, not the customer release ZIP.

Clone the repository into:

```text
[moodle-root]/availability/condition/proctor
```

For the included Docker development environments and PHPUnit commands, see the
[development guide](https://github.com/constructor-tech/alemira-proctor-moodle-plugin/blob/master/dev/README.md)
in the source repository.

To build locally:

```bash
npm install
make yui-build
make release
```

Requirements are Node.js 20, npm and Python 3. Docker alternatives are available:

```bash
make yui-build-docker
make release-docker
```

Before creating a release, bump `$plugin->version` in `version.php`. Do not reuse
a version already installed by a customer. `utils/release.py --force` overwrites
an existing local archive and must only be used deliberately.

After building, inspect every ZIP before publishing. The archive must contain one
top-level plugin directory and must not contain local notes, credentials, build
containers or release tooling.
