# Local Moodle dev stand

Run any supported Moodle version locally with one command, with the Proctor
plugin mounted from the working tree. Settings, courses, users, and the
integration config **persist across restarts** — data lives in named Docker
volumes and `config.php` inside the per-version source clone.

## Requirements

- Docker with Compose v2
- ~1 GB of disk per Moodle version (source clone + DB + moodledata)

## Quick start

```bash
cd dev
./moodle.sh up 310        # Moodle 3.10 at http://localhost:8310
./moodle.sh up 500        # Moodle 5.0  at http://localhost:8500
```

First run per version: clones Moodle sources (shallow), starts MariaDB +
Apache/PHP, installs Moodle via CLI, and registers the plugin. Subsequent
runs just start the containers — everything you configured is still there.

Admin login: `admin` / `Test-1234`

## Version matrix

| Key | Moodle | PHP | MariaDB | URL |
|-----|--------|-----|---------|-----|
| 310 | 3.10 (client-reported) | 7.4 | 10.5 | http://localhost:8310 |
| 311 | 3.11 (first with `get_shortname()`) | 7.4 | 10.5 | http://localhost:8311 |
| 405 | 4.5 LTS | 8.2 | 10.11 | http://localhost:8405 |
| 500 | 5.0 | 8.3 | 10.11 | http://localhost:8500 |

Versions can run simultaneously — ports and volumes do not overlap.

## Commands

```bash
./moodle.sh up <ver>            # start (installs on first run)
./moodle.sh up <ver> --clean    # start WITHOUT the plugin mount (zip-install testing)
./moodle.sh down <ver>          # stop, keep all data
./moodle.sh reset <ver>         # stop and DELETE all data (asks for confirmation)
./moodle.sh ps                  # container status
./moodle.sh urls                # URLs and credentials
```

## How the plugin is delivered

Default mode bind-mounts the repo root into
`availability/condition/proctor` in every Moodle. Code edits are visible
immediately; after changing PHP classes purge caches
(*Site administration → Development → Purge caches*). Version bumps in
`version.php` trigger the normal Moodle upgrade flow on next admin visit.

`--clean` mode skips the mount so you can exercise the real client install
path: build a zip (`make release-docker` in the repo root) and upload it via
*Site administration → Plugins → Install plugins*.

## Notes

- Proctor integration settings (name/secret/account) are not preconfigured —
  set what you need in *Admin → Plugins → Availability restrictions →
  Proctor* exactly like on a real installation. Outbound calls to a Proctor
  API work from localhost; callbacks from a remote Proctor to your local
  Moodle will not reach it.
- Moodle source clones live in `dev/.moodle/<ver>` (gitignored). They are
  shadowed inside the container's plugin directory via tmpfs, so Moodle
  never scans them.
- To wipe one version and start fresh: `./moodle.sh reset <ver>`.
