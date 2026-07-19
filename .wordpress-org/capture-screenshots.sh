#!/usr/bin/env bash
#
# Captures the WordPress.org directory screenshots from the running
# Docker environment.
#
#   ./docker/wp.sh up && ./docker/wp.sh seed
#   ./.wordpress-org/capture-screenshots.sh
#
# The screenshots must match the numbered captions under "== Screenshots
# =="  in readme.txt. Change one, change the other.
#
# Authentication: a temporary must-use plugin is written before the run
# and deleted after, so an auth bypass never persists in the repository
# or in a running environment between captures. It refuses to act on any
# host that is not localhost, and requires a token.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
SHIM="docker/mu-plugins/zz-screenshot-auth.php"
OUT="$(pwd)/.wordpress-org"
WORK="$(mktemp -d)"
BASE="http://localhost:8080/wp-admin/admin.php?page=olmbox-ai-inbox-for-contact-form-7"
TOKEN="&olmbox_shot=screenshot-session-token"

if [ ! -x "$CHROME" ]; then
  echo "Google Chrome not found at $CHROME" >&2
  exit 1
fi

cleanup() {
  rm -f "$SHIM"
  rm -rf "$WORK"
}
trap cleanup EXIT

cat > "$SHIM" <<'PHP'
<?php
/**
 * Plugin Name: Olmbox screenshot support (temporary)
 * Description: Written and deleted by .wordpress-org/capture-screenshots.sh. Never commit this file.
 *
 * @package CF7AIC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Establishes a real cookie session rather than filtering
 * `determine_current_user`: wp-admin gates on auth_redirect(), which
 * validates the auth cookie directly and ignores that filter.
 */
add_action(
	'init',
	static function () {
		if ( is_user_logged_in() ) {
			return;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( 0 !== strpos( $host, 'localhost:' ) ) {
			return;
		}

		$token = isset( $_GET['olmbox_shot'] ) ? sanitize_text_field( wp_unslash( $_GET['olmbox_shot'] ) ) : '';
		if ( ! hash_equals( 'screenshot-session-token', $token ) ) {
			return;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
			)
		);

		if ( ! $admins ) {
			return;
		}

		wp_set_current_user( (int) $admins[0] );
		wp_set_auth_cookie( (int) $admins[0] );
		wp_safe_redirect( remove_query_arg( 'olmbox_shot' ) );
		exit;
	},
	1
);

add_action(
	'admin_head',
	static function () {
		echo '<style>
			.update-nag,
			#wpbody-content > .notice,
			#wpbody-content > .updated,
			#wpbody-content > .error,
			.notice.notice-info,
			#wp-admin-bar-updates,
			#wp-admin-bar-comments,
			#footer-upgrade { display: none !important; }
			#wpbody-content { padding-top: 12px; }
			/*
			 * The Provider tab auto-loads models on page load; the seeded
			 * key is a placeholder, so that request returns an auth error
			 * a real configured install would never show. This hides an
			 * artefact of the fixture, not a genuine product state.
			 */
			#cf7aic-model-status { display: none !important; }
		</style>';
	},
	100
);
PHP

shot() {
  local name="$1" query="$2" w="$3" h="$4"
  local out="$OUT/${name}.png"
  rm -f "$out"

  # Chrome does not reliably exit after --screenshot here, so it is
  # backgrounded and killed once the file size stops changing. Each run
  # carries the token because a killed Chrome never flushes its cookie
  # jar, leaving the next run logged out.
  "$CHROME" --headless=new --disable-gpu --no-first-run --no-default-browser-check \
    --user-data-dir="$WORK/profile" --window-size="${w},${h}" --hide-scrollbars \
    --force-device-scale-factor=1 --virtual-time-budget=8000 \
    --screenshot="$out" "${BASE}${query}${TOKEN}" >/dev/null 2>&1 &
  local pid=$! prev=0 size=0

  for _ in $(seq 1 40); do
    sleep 1
    [ -f "$out" ] || continue
    size=$(stat -f%z "$out" 2>/dev/null || echo 0)
    if [ "$size" -gt 0 ] && [ "$size" = "$prev" ]; then break; fi
    prev=$size
  done

  kill "$pid" 2>/dev/null || true
  wait "$pid" 2>/dev/null || true

  if [ -s "$out" ]; then
    printf "  %-14s %sx%-5s %s KB\n" "$name" "$w" "$h" "$((size / 1024))"
  else
    printf "  %-14s FAILED\n" "$name" >&2
    return 1
  fi
}

echo "Capturing screenshots (captions live in readme.txt):"
shot screenshot-1 "&section=submissions"          1440 900
shot screenshot-2 "&section=submissions&id=5"     1440 1280
shot screenshot-3 "&section=settings&tab=general" 1440 900
shot screenshot-4 "&section=settings&tab=provider" 1440 900
shot screenshot-5 "&section=settings&tab=usage"   1440 900

echo
echo "Written to .wordpress-org/ — upload to SVN assets/, not the plugin zip."
