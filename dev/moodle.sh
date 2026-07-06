#!/usr/bin/env bash
# Local Moodle dev stand for the Proctor availability plugin.
#
# One command per Moodle version; settings, courses, and integration config
# persist across restarts (named volumes + config.php in the source clone).
#
# Usage:
#   ./moodle.sh up <version> [--clean]   start (clone + install on first run)
#   ./moodle.sh down <version>           stop, keep all data
#   ./moodle.sh reset <version>          stop and DELETE all data for version
#   ./moodle.sh ps                       show stand status
#   ./moodle.sh urls                     print URLs and credentials
#
# Versions: 310 (Moodle 3.10, PHP 7.4), 311 (3.11, PHP 7.4),
#           405 (4.5, PHP 8.2), 500 (5.0, PHP 8.3)
#
# --clean: do not mount the plugin working tree (for testing zip installs
#          through the admin UI, like a real client).

set -euo pipefail
cd "$(dirname "$0")"

ADMIN_USER="admin"
ADMIN_PASS="Test-1234"
MOODLE_GIT="https://github.com/moodle/moodle.git"

branch_for() {
  case "$1" in
    310) echo "MOODLE_310_STABLE" ;;
    311) echo "MOODLE_311_STABLE" ;;
    405) echo "MOODLE_405_STABLE" ;;
    500) echo "MOODLE_500_STABLE" ;;
    *) echo "Unknown version: $1 (use 310|311|405|500)" >&2; return 1 ;;
  esac
}

port_for() {
  case "$1" in
    310) echo 8310 ;;
    311) echo 8311 ;;
    405) echo 8405 ;;
    500) echo 8500 ;;
  esac
}

compose() {
  local ver="$1" clean="$2"; shift 2
  local files=(-f compose.yaml)
  if [ "$clean" != "1" ]; then
    files+=(-f compose.plugin.yaml)
  fi
  docker compose "${files[@]}" --profile "$ver" "$@"
}

ensure_sources() {
  local ver="$1"
  local branch; branch="$(branch_for "$ver")"
  if [ ! -f ".moodle/$ver/version.php" ]; then
    echo ">> Cloning Moodle $branch (shallow) into dev/.moodle/$ver ..."
    mkdir -p .moodle
    git clone --depth 1 --branch "$branch" "$MOODLE_GIT" ".moodle/$ver"
  fi
}

install_if_needed() {
  local ver="$1"
  local port; port="$(port_for "$ver")"
  if [ -f ".moodle/$ver/config.php" ]; then
    echo ">> config.php exists — skipping install (data persisted)"
    return 0
  fi
  echo ">> First run: installing Moodle $ver (this takes a couple of minutes)..."
  docker compose -f compose.yaml --profile "$ver" exec -T -u root "web$ver" \
    chown -R www-data:www-data /var/www/moodledata
  docker compose -f compose.yaml --profile "$ver" exec -T -u www-data "web$ver" \
    php admin/cli/install.php \
      --lang=en \
      --wwwroot="http://localhost:$port" \
      --dataroot=/var/www/moodledata \
      --dbtype=mariadb \
      --dbhost="db$ver" \
      --dbname=moodle \
      --dbuser=moodle \
      --dbpass=moodle \
      --prefix=mdl_ \
      --fullname="Moodle $ver — Proctor plugin dev" \
      --shortname="m$ver" \
      --summary="" \
      --adminuser="$ADMIN_USER" \
      --adminpass="$ADMIN_PASS" \
      --adminemail="admin@example.com" \
      --agree-license \
      --non-interactive
  echo ">> Registering installed plugins (upgrade.php)..."
  docker compose -f compose.yaml --profile "$ver" exec -T -u www-data "web$ver" \
    php admin/cli/upgrade.php --non-interactive || true
}

cmd="${1:-}"; shift || true
case "$cmd" in
  up)
    ver="${1:?version required (310|311|405|500)}"; shift || true
    clean=0
    [ "${1:-}" = "--clean" ] && clean=1
    branch_for "$ver" >/dev/null
    ensure_sources "$ver"
    compose "$ver" "$clean" up -d
    install_if_needed "$ver"
    port="$(port_for "$ver")"
    echo ""
    echo "Moodle $ver is up: http://localhost:$port  ($ADMIN_USER / $ADMIN_PASS)"
    if [ "$clean" = "1" ]; then
      echo "Clean mode: plugin NOT mounted — install a zip via Site administration > Plugins."
    else
      echo "Plugin mounted live from the working tree (availability/condition/proctor)."
      echo "After code changes: Site administration > Development > Purge caches."
    fi
    ;;
  down)
    ver="${1:?version required}"
    compose "$ver" 0 down
    echo "Stopped Moodle $ver. Data kept — './moodle.sh up $ver' resumes where you left off."
    ;;
  reset)
    ver="${1:?version required}"
    read -r -p "Delete ALL data for Moodle $ver (DB, moodledata, config.php)? [y/N] " ans
    if [ "${ans:-n}" = "y" ]; then
      compose "$ver" 0 down -v || true
      rm -f ".moodle/$ver/config.php"
      echo "Moodle $ver wiped. Next 'up' performs a fresh install."
    else
      echo "Cancelled."
    fi
    ;;
  ps)
    docker compose -f compose.yaml --profile 310 --profile 311 --profile 405 --profile 500 ps
    ;;
  urls)
    for v in 310 311 405 500; do
      echo "Moodle $v: http://localhost:$(port_for "$v")  ($ADMIN_USER / $ADMIN_PASS)"
    done
    ;;
  *)
    sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
    ;;
esac
