#!/usr/bin/env bash
#
# Builds the WordPress.org distribution zip.
#
#   ./bin/build-zip.sh            # build from HEAD
#   ./bin/build-zip.sh <ref>      # build from a tag or commit
#
# Uses `git archive`, so only committed files can end up in the zip and
# the exclusions live in .gitattributes rather than in a list here that
# would silently drift. The archive root is named for the WordPress.org
# slug, which is what WordPress unpacks into wp-content/plugins/ — it is
# deliberately NOT this repository's directory name.
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SLUG="olmbox-ai-inbox-for-contact-form-7"
REF="${1:-HEAD}"
OUT_DIR="dist"

main_file="${SLUG}.php"

version="$(sed -n 's/^ \* Version: *\(.*\)$/\1/p' "$main_file" | tr -d ' \r')"
constant="$(sed -n "s/^define( 'CF7AIC_VERSION', '\(.*\)' );$/\1/p" "$main_file" | tr -d ' \r')"
stable="$(sed -n 's/^Stable tag: *\(.*\)$/\1/p' readme.txt | tr -d ' \r')"

# These three drift apart easily and the mismatch is invisible until a
# user reports the wrong version, so refuse to build rather than warn.
if [ "$version" != "$constant" ] || [ "$version" != "$stable" ]; then
  echo "Version mismatch — refusing to build:" >&2
  echo "  ${main_file} header:   ${version}" >&2
  echo "  CF7AIC_VERSION:        ${constant}" >&2
  echo "  readme.txt Stable tag: ${stable}" >&2
  exit 1
fi

if ! git diff --quiet HEAD -- . 2>/dev/null; then
  echo "Note: working tree has uncommitted changes; they will NOT be in the zip."
fi

mkdir -p "$OUT_DIR"
zip_path="${OUT_DIR}/${SLUG}-${version}.zip"
rm -f "$zip_path"

git archive --format=zip --prefix="${SLUG}/" -o "$zip_path" "$REF"

echo "Built ${zip_path} ($(du -h "$zip_path" | cut -f1)), version ${version}"
echo
echo "Top level:"
unzip -Z1 "$zip_path" | awk -F/ '{print $2}' | grep -v '^$' | sort -u | sed 's/^/  /'

# Anything here would be a packaging bug: dev tooling or secrets shipped
# to every user of the plugin.
echo
leaked="$(unzip -Z1 "$zip_path" \
  | grep -Ei '(^|/)(docker|tests|bin|node_modules|vendor|\.git|\.wordpress-org)/|\.(lock|dist)$|composer\.json|package\.json|CLAUDE\.md|tailwind\.src\.css' || true)"

# Anything dotted at the archive root is dev residue by default —
# .phpunit.result.cache reached a built zip precisely because the
# pattern list above enumerated known offenders instead of rejecting
# the whole class of them.
leaked="${leaked}$(unzip -Z1 "$zip_path" | awk -F/ 'NF>1 && $2 ~ /^\./ {print}' || true)"
if [ -n "$leaked" ]; then
  echo "FAIL: dev files present in the zip:" >&2
  echo "$leaked" | sed 's/^/  /' >&2
  exit 1
fi
echo "OK: no dev tooling in the zip."
