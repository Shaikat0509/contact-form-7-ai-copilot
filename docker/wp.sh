#!/usr/bin/env bash
#
# Helper for the disposable WordPress environment (see docker-compose.yml).
#
#   ./docker/wp.sh up       start the stack and install WordPress if needed
#   ./docker/wp.sh setup    (re-)run the install + seed steps only
#   ./docker/wp.sh wp ...   run a WP-CLI command, e.g. ./docker/wp.sh wp plugin list
#   ./docker/wp.sh seed     populate the AI Inbox with representative submissions
#   ./docker/wp.sh test     run the PHPUnit integration suite (args pass through)
#   ./docker/wp.sh logs     tail the PHP/Apache log and WP_DEBUG_LOG
#   ./docker/wp.sh down     stop the stack, keep the database
#   ./docker/wp.sh reset    destroy everything including volumes
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

SLUG="olmbox-ai-inbox-for-contact-form-7"
URL="http://localhost:8080"
ADMIN_USER="admin"
ADMIN_PASS="admin"
ADMIN_EMAIL="dev@example.test"

dc() { docker compose "$@"; }
wp() { docker compose run --rm -T cli "$@"; }

# WP-CLI exits non-zero when WordPress is not installed yet; that is the
# signal we branch on, not an error worth aborting the script over.
is_installed() { wp core is-installed >/dev/null 2>&1; }

setup() {
  echo "==> Waiting for WordPress files to be provisioned"
  for _ in $(seq 1 30); do
    if wp core version >/dev/null 2>&1; then break; fi
    sleep 2
  done

  if is_installed; then
    echo "==> WordPress already installed, skipping core install"
  else
    echo "==> Installing WordPress"
    wp core install \
      --url="$URL" \
      --title="Olmbox AI Inbox dev" \
      --admin_user="$ADMIN_USER" \
      --admin_password="$ADMIN_PASS" \
      --admin_email="$ADMIN_EMAIL" \
      --skip-email
  fi

  echo "==> Installing Contact Form 7 (hard dependency)"
  wp plugin is-installed contact-form-7 >/dev/null 2>&1 \
    || wp plugin install contact-form-7
  wp plugin activate contact-form-7

  echo "==> Activating $SLUG"
  wp plugin activate "$SLUG"

  echo "==> Enabling AI for Contact Form 7's default form"
  form_id="$(wp post list --post_type=wpcf7_contact_form --format=ids | tr -d '\r' | awk '{print $1}')"
  if [ -z "$form_id" ]; then
    echo "    !! No CF7 form found — skipping. Create one, then re-run setup."
  else
    wp option update cf7aic_general --format=json \
      "{\"enabled\":true,\"form_ids\":[$form_id]}"
    echo "    form_id=$form_id"
  fi

  echo
  echo "Ready:  $URL/wp-admin/admin.php?page=$SLUG"
  echo "Login:  $ADMIN_USER / $ADMIN_PASS   (local only)"
}

cmd="${1:-up}"
shift || true

case "$cmd" in
  up)
    # Create the bind-mount source before compose does. Left to Docker,
    # it is created root-owned, and on CI the unprivileged runner user
    # then cannot write the test library into it.
    mkdir -p .wp-tests-lib
    dc up -d --wait db wordpress
    setup
    ;;
  setup)
    setup
    ;;
  wp)
    wp "$@"
    ;;
  seed)
    wp eval-file "wp-content/plugins/$SLUG/docker/seed.php"
    ;;
  test)
    if [ ! -f ".wp-tests-lib/includes/functions.php" ]; then
      echo "WordPress test library missing — running installer first."
      ./install-wp-tests.sh
    fi
    # The library lives on the host and survives `reset`, but the test
    # database does not — it is in a volume. Recreate it unconditionally
    # so a reset does not leave `test` failing in the harness's own
    # bootstrap, where the cause is not obvious.
    dc exec -T db mariadb -uroot -pwordpress \
      -e "CREATE DATABASE IF NOT EXISTS wordpress_test;" >/dev/null
    # Runs in the WordPress container: it already has PHP 8.2, mysqli and
    # a route to the db service. vendor/ arrives via the plugin bind mount.
    dc exec -T \
      -e WP_TESTS_DIR=/var/www/html/wp-tests-lib \
      -w "/var/www/html/wp-content/plugins/$SLUG" \
      wordpress \
      php vendor/bin/phpunit "$@"
    ;;
  logs)
    dc logs -f wordpress &
    dc run --rm -T cli tail -f /var/www/html/wp-content/debug.log 2>/dev/null || true
    ;;
  down)
    dc down
    ;;
  reset)
    # `--profile tools` is required, not optional: `down` only touches
    # services whose profiles are active, so a leftover cli container
    # (from an interrupted `compose run`) is invisible to a plain
    # `down -v` — and --remove-orphans does not catch it either, because
    # it is a defined service rather than an orphan. It goes on holding
    # the webroot volume, `down -v` reports "Resource is still in use"
    # and continues, and the next `up` silently reuses the old WordPress
    # install. That looks like a clean reset but is not one, which is
    # exactly how a "verified on WP 7.0" run can really be 6.8.
    dc --profile tools down -v --remove-orphans
    remaining="$(docker volume ls --filter name=olmbox-ai-inbox -q)"
    if [ -n "$remaining" ]; then
      echo "Volumes survived down -v; forcing removal:" >&2
      echo "$remaining" | sed 's/^/  /' >&2
      docker volume rm $remaining >/dev/null
    fi
    echo "Volumes destroyed. Run './docker/wp.sh up' for a clean install."
    ;;
  *)
    echo "Unknown command: $cmd" >&2
    sed -n '3,12p' "$0" >&2
    exit 1
    ;;
esac
