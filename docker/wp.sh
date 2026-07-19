#!/usr/bin/env bash
#
# Helper for the disposable WordPress environment (see docker-compose.yml).
#
#   ./docker/wp.sh up       start the stack and install WordPress if needed
#   ./docker/wp.sh setup    (re-)run the install + seed steps only
#   ./docker/wp.sh wp ...   run a WP-CLI command, e.g. ./docker/wp.sh wp plugin list
#   ./docker/wp.sh seed     populate the AI Inbox with representative submissions
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
  logs)
    dc logs -f wordpress &
    dc run --rm -T cli tail -f /var/www/html/wp-content/debug.log 2>/dev/null || true
    ;;
  down)
    dc down
    ;;
  reset)
    dc down -v
    echo "Volumes destroyed. Run './docker/wp.sh up' for a clean install."
    ;;
  *)
    echo "Unknown command: $cmd" >&2
    sed -n '3,12p' "$0" >&2
    exit 1
    ;;
esac
