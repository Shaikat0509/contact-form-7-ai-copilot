# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"Olmbox AI Inbox for Contact Form 7" — a WordPress plugin that adds an AI Inbox to Contact Form 7. Every submission to a selected form gets an AI-drafted summary, suggested reply, category, priority, and confidence score, which an admin reviews before anything is sent.

**Published** on WordPress.org since 1.0.0: <https://wordpress.org/plugins/olmbox-ai-inbox-for-contact-form-7/>. Requires PHP 8.1+, WordPress 6.8+, and Contact Form 7 (a hard dependency, declared via `Requires Plugins`).

Anything released from here reaches real installs. Treat `main`, the `release` branch, and the SVN repository as production surfaces, not scratch space.

**The central product invariant: this plugin never emails a visitor automatically.** At submission time it only ever *logs a row*. The single code path that calls `wp_mail()` to a visitor is `ReplyService::send()`, reached only from the `cf7aic_send_reply` AJAX action — an explicit, confirmed admin click. Any change that would let AI-generated text reach a visitor without that click contradicts the plugin's WordPress.org description, its privacy claims, and the 1.x→2.0 migration notice shown to existing users. Do not introduce one.

## Commands

```bash
composer install            # PHPCS + WordPress Coding Standards (dev only)
composer run lint           # phpcs against phpcs.xml.dist
vendor/bin/phpcbf --standard=phpcs.xml.dist .   # auto-fix what's fixable

npm install
npm run build:css           # Tailwind -> admin/assets/css/admin.css (minified)
npm run watch:css           # rebuild on change

php -l <file>               # syntax check a single file
```

There is a PHPUnit integration suite (`./docker/wp.sh test`) and CI runs it on every push and pull request (see **CI** below). There are no browser/e2e tests: the suite covers the schema migration, the review workflow, the retention cap, encryption, and the AI response normalizers, but not the admin UI or the CF7 submission hook end to end. Those two are checked by hand against the Docker environment.

CI runs the same suite, so a green local run and a green CI run mean the same thing — but CI runs it from scratch, which has already caught bugs a warm working copy could not (see **Things that only fail on a clean machine**).

One thing `phpcs` *is* strong evidence for: text-domain consistency. The `WordPress.WP.I18n` sniff whitelists exactly one domain, so any `__()` call using the wrong one fails the run.

### Docker verification environment (preferred)

```bash
./docker/wp.sh up       # start stack, install WP + CF7, activate plugin (idempotent)
./docker/wp.sh seed     # populate the Inbox with representative submissions
./docker/wp.sh wp ...   # any WP-CLI command, e.g. ./docker/wp.sh wp plugin list
./docker/wp.sh logs     # Apache/PHP output and WP_DEBUG_LOG
./docker/wp.sh test     # PHPUnit integration suite (args pass through)
./docker/wp.sh reset    # destroy volumes for a clean install
```

`WP_IMAGE_TAG` picks the WordPress version, so both claims in `readme.txt` are testable rather than asserted:

```bash
WP_IMAGE_TAG=6.8-php8.2-apache ./docker/wp.sh up   # "Requires at least"
WP_IMAGE_TAG=7.0-php8.2-apache ./docker/wp.sh up   # "Tested up to" (the default)
```

**Confirm the version actually running** with `./docker/wp.sh wp core version` rather than trusting the tag you passed. A surviving volume silently reuses the previous install, which is exactly how a run believed to be testing 7.0 turned out to be 6.8.3.

Tests run inside the WordPress container against a throwaway `wordpress_test` database, so they never touch seeded dev data. `docker/install-wp-tests.sh` fetches WordPress core's PHPUnit harness on first use — a large download, cached in the gitignored `docker/.wp-tests-lib/`. Run a single file with `./docker/wp.sh test --filter InstallerMigrationTest`.

Tests that perform DDL (the migration suite) escape `WP_UnitTestCase`'s per-test transaction, because DDL implicitly commits — those rebuild the table in `tear_down` rather than relying on rollback.

Admin UI at `http://localhost:8080/wp-admin/admin.php?page=olmbox-ai-inbox-for-contact-form-7`, login `admin` / `admin` (local only, never reachable off localhost). Captured mail at `http://localhost:8025`.

**Mail is load-bearing in this environment, not a convenience.** CF7 fires `wpcf7_mail_sent` only after its own send succeeds, so with no mail transport CF7 returns `mail_failed`, the hook never fires, and no submission is ever logged — the plugin looks broken while behaving exactly as designed. A Mailpit service plus `docker/mu-plugins/olmbox-dev-mail.php` routes mail over SMTP so the path is exercisable.

To drive a real submission end to end (pretty permalinks are off, hence `rest_route`):

```bash
curl -s -X POST "http://localhost:8080/?rest_route=/contact-form-7/v1/contact-forms/<FORM_ID>/feedback" \
  -F "_wpcf7=<FORM_ID>" -F "_wpcf7_unit_tag=wpcf7-f<FORM_ID>-o1" \
  -F "your-name=Test" -F "your-email=visitor@example.test" \
  -F "your-subject=Subject" -F "your-message=Body"
```

Expect `"status":"mail_sent"` and one new row. Mailpit is also how to check the central invariant empirically: after a submission, `http://localhost:8025/api/v1/messages` should contain CF7's admin notification and **nothing addressed to the visitor**.

Two details worth knowing before changing `docker/`:

- The plugin is bind-mounted under its **WordPress.org slug**, not this repo's directory name (`cf7-ai-copilot`). That mirrors the distribution zip's layout, so the environment exercises the shipped structure. Edits are live — no rebuild needed.
- The `cli` service sits behind a compose profile so `up --wait` never waits on it. It runs to completion and exits, which `--wait` otherwise reports as a failed service.

`docker/seed.php` writes through `SubmissionsRepository` rather than raw SQL, so seeding also exercises the insert path. Its fixtures deliberately cover every `ai_status` plus a sub-60% confidence score — those drive branches in the Inbox and detail views that an empty table cannot show.

`docker/` is excluded from `phpcs` (see `phpcs.xml.dist`): it is dev tooling that never ships, and its WP-CLI scripts have top-level variables that are globals by definition.

### The Local by Flywheel site (legacy)

The repo also sits inside a Local by Flywheel site (`cf7-ai-copilot.local`) — that is why the working directory is `wp-content/plugins/cf7-ai-copilot`. Prefer Docker above; reach for Local only when you specifically need that site's existing data. WP-CLI needs Local's MySQL socket passed explicitly, and **the socket path is not stable** — Local mints a new run directory each time, and only sites currently running have one. Never hardcode it; discover it, and try each candidate:

```bash
find "$HOME/Library/Application Support/Local/run" -name mysqld.sock
```

Then, with the one that answers:

```bash
SOCK="<path from above>"
php -d mysqli.default_socket="$SOCK" -d pdo_mysql.default_socket="$SOCK" \
  /usr/local/bin/wp option get siteurl
```

If every candidate returns "Error establishing a database connection", the site is stopped — start it in the Local app first. WP-CLI also prints an unrelated `react/promise` deprecation notice on this setup; ignore it.

`wp eval-file <script.php>` with that prefix is the most direct way to exercise services against real data — instantiate a repository or service and call it, rather than trying to drive the admin UI. Write such scripts to the scratchpad directory, not into the plugin.

## Architecture

Bootstrap: `olmbox-ai-inbox-for-contact-form-7.php` guards PHP/WP versions using only PHP 7.0-compatible syntax (so an old-PHP host gets a notice, never a parse error), defines `CF7AIC_*` constants, registers the autoloader, and boots `Plugin::get_instance()->init()` on `plugins_loaded`.

`includes/Plugin.php` is the composition root — the only place objects are wired together. There is no DI container; dependencies are constructed there and passed down constructors. If a class needs a new collaborator, add it to that constructor call.

`Helpers/Autoloader.php` maps `CF7AIC\` → `includes/`, one class per file, filename identical to the class name. **Composer autoloading is deliberately not used** — the shipped plugin has zero runtime dependencies. Composer and npm are build-time only; `vendor/` and `node_modules/` are gitignored and must never be required at runtime.

### The submission path

`wpcf7_mail_sent` → `CF7\SubmissionHandler` → `Services\SubmissionService::process()` → `Services\AIService::analyze()` → `Database\SubmissionsRepository::insert()`.

Two deliberate choices worth preserving:

- The hook is `wpcf7_mail_sent`, **not** `wpcf7_before_send_mail`. Nothing needs to run before CF7's own send, and hooking after means a submission blocked by an anti-spam plugin never reaches the AI at all.
- `SubmissionHandler` is registered unconditionally, *outside* the `is_admin()` branch in `Plugin::init()`. CF7 processes submissions through `admin-ajax.php` (where `is_admin()` is true) or a plain frontend POST (where it is false). Gating it on `is_admin()` silently breaks the non-AJAX path.

`SubmissionService::process()` always inserts exactly one row — on success, on provider failure, and when no API key is configured (`ai_status` distinguishes them). AI failures never interrupt or break the CF7 submission; they are logged to a row, optionally to `error_log` behind `WP_DEBUG_LOG`, and fired as `cf7aic_ai_error`.

### AI providers

`Interfaces\AIProviderInterface` ← `AI\AbstractProvider` ← the four concrete providers (OpenAI, Anthropic, Gemini, OpenRouter). `AbstractProvider` owns transport: timeouts, `sslverify`, `WP_Error` handling, JSON decoding, error-message extraction. Concrete providers know only their endpoint and payload shape. `AI\ProviderFactory` is the single slug→class map; adding a provider means one new class, one `case` there, and one entry in `Settings\Repository::PROVIDERS`.

`AbstractProvider::request()` throws only on transport failure — a non-2xx status is returned normally, because a connection test and a real generation call disagree about what counts as success.

`AIService` makes **one** request per submission: the model returns a single JSON object containing summary, reply, category, priority, confidence, and reasoning together, so five outputs cost one API call. It defensively strips markdown code fences before decoding. `ClassificationService` / `ConfidenceService` normalize the model's free-form category/priority/confidence into the fixed vocabulary the DB and UI expect — never trust those fields raw.

### Settings and secrets

`Settings\Repository` owns the shape, defaults, and sanitization for three option groups (`cf7aic_general`, `cf7aic_provider`, `cf7aic_prompt`), each a single serialized option so a whole tab reads in one `get_option()`.

API keys are encrypted at rest by `Helpers\Encryption` (AES-256-CBC, key derived from `wp_salt('auth')`). `get_provider()` returns the key **decrypted** — it is for provider classes only. Never echo it; use `get_masked_api_key()` for display. The documented threat model covers a leaked options table, not a server compromise where `wp-config.php` is also readable; don't overstate it in user-facing copy.

### Database

One custom table, `{prefix}cf7aic_submissions`, owned entirely by `Database\Installer`. `SCHEMA_VERSION` gates `maybe_migrate()`, which runs on every `plugins_loaded` but is a single `get_option()` once current.

`status` and `ai_status` mean different things and are easy to confuse: `status` is the review workflow state (`new`/`reviewed`/`replied`/`archived`), `ai_status` is the AI generation outcome (`success`/`partial`/`failed`/`no_api_key`). In the 1.x schema, `status` meant the latter — `migrate_v1_status_column()` renames it before `dbDelta()` repurposes the name, because `dbDelta` can add and alter columns but cannot rename them.

The table is capped at `MAX_ROWS = 200`, pruned on insert, and dropped on uninstall.

### Admin UI

`Admin\Menu` registers one submenu page under CF7's `wpcf7` menu and scopes asset loading to that page's hook (plus CSS only on `index.php` for the dashboard widget). `Admin\AdminPage` is the single page callback: it renders a self-contained shell (sidebar + topbar) inside `.wrap` and routes on `?section=` to `DashboardPage`, `InboxView`, `SubmissionDetailPage` (when `?id=` is present), or `SettingsPage`.

The `<hr class="wp-header-end" />` in `AdminPage::render()` is load-bearing — without it, core's `common.js` walks into the custom topbar and injects other plugins' admin notices into the middle of the layout.

`SettingsPage::TABS` is an explicit slug→partial whitelist, so a crafted `?tab=` can never include an arbitrary file. Partials in `admin/views/` are `require`-d template parts with deliberately unprefixed locals.

`Admin\AjaxController` handles all seven AJAX actions. Every handler repeats its `current_user_can( Menu::CAPABILITY )` and `check_ajax_referer( self::NONCE_ACTION, 'nonce', false )` checks inline rather than delegating, so the check is always visible in the same scope as the `$_POST` access it guards. Keep that pattern when adding actions — it reads as duplication but is intentional.

## Conventions

- WordPress Coding Standards via `phpcs.xml.dist` (`WordPress-Extra` + `WordPress-Docs` + `PHPCompatibilityWP`). `WordPress.Files.FileName` is excluded wholesale because PSR-4 filenames are an architectural choice.
- Prefix everything global with `cf7aic` / `CF7AIC`. The text domain is `olmbox-ai-inbox-for-contact-form-7` (note: it does *not* match the `CF7AIC` code prefix or the directory name — copy it exactly).
- Every user-facing string is translated; every echoed value is escaped at output.
- Every PHP file starts with a docblock and an `ABSPATH` guard.
- `phpcs:ignore` requires a real justification after `--` explaining why the rule is safe here, not just the rule name. Existing DB-query ignores follow this closely; match that standard.
- Yoda conditions, tabs for indentation, `array()` over `[]` — WPCS enforces these.
- Admin CSS is authored in `admin/assets/css/tailwind.src.css` and compiled to `admin/assets/css/admin.css`, which is the file actually enqueued and committed. **Never hand-edit `admin.css`** — edit the source and run `npm run build:css`. Tailwind runs only at build time; the shipped plugin has no CDN or runtime framework dependency.
- Commit messages are sentence-case imperative summaries of intent, no prefixes or emoji (see `git log`).

## Branches

| Branch | Contains |
|---|---|
| `main` | Plugin source — integration target and source of truth. Protected. |
| `dev` | Day-to-day plugin work, merged to `main` via PR. |
| `release` | Shippable state. Protected. Currently behind `main`; releases have been cut from tags, not this branch. |
| `website` | **Orphan branch** — the Astro marketing site. Shares no history with the plugin. |

`website` has an unrelated history on purpose, so site commits never appear in the plugin's log and site files can never reach a zip. **Never merge it into a plugin branch or vice versa** — git will happily union two unrelated trees and dump the whole site into the plugin.

Work on it through a worktree rather than switching branches in place, which would tear down the plugin tree and the Docker mounts:

```bash
git worktree add ../olmbox-website website
```

The site has its own `README.md`, toolchain (Astro, Node 22 via `.nvmrc`) and CI (`.github/workflows/site.yml`, deploying to Cloudflare Pages). It is live at <https://olmbox.pages.dev>.

## Things that only fail on a clean machine

These were all found by running from scratch, and none could have been reproduced in a warm working copy. Worth knowing before concluding "works locally, must be fine":

- **Bind-mount inode swap.** `install-wp-tests.sh` originally replaced the test-library directory with `rm -rf` + `mkdir`. That directory is bind-mounted into a running container, so replacing it allocates a new inode the mount does not point at — the container kept seeing an empty directory while the host copy was fully populated. It now clears contents and keeps the directory.
- **A reset that did not reset.** `wp.sh reset` used a plain `docker compose down -v`, which ignores services whose profile is inactive. A leftover `cli` container held the webroot volume, `down -v` said "Resource is still in use" and continued, and the next `up` reused the old install. Teardown now activates the `tools` profile and fails loudly if a volume survives.
- **Root-owned bind mounts.** If Docker creates the bind-mount source, it is root-owned and an unprivileged CI runner cannot write to it. `wp.sh up` creates it first.

The common thread: **a green status is not the same as the thing working.** In this project a "success" has variously meant a skipped step, a deploy to the wrong environment, and an API field lagging reality. Check the artifact — the version actually running, the file actually served, the bytes actually shipped.

## Release surface

Version numbers appear in three places that must stay in sync: the `Version:` header and `CF7AIC_VERSION` constant in `olmbox-ai-inbox-for-contact-form-7.php`, and `Stable tag` in `readme.txt`. `readme.txt` is the WordPress.org-format source of truth for the description, FAQ, changelog, and the external-services disclosure; `README.md` is the GitHub-facing summary. A user-visible behavior change needs both updated, plus a changelog entry.

### Building the zip

```bash
./bin/build-zip.sh          # from HEAD, into dist/
./bin/build-zip.sh v1.0.0   # from a tag
```

It packages with `git archive`, so **only committed files can ship** — an uncommitted experiment cannot leak into a release. Exclusions live in `.gitattributes` as `export-ignore`, not in a list inside the script, so there is one place to change when a new dev directory appears. The archive root is named for the WordPress.org slug rather than this repo's directory name.

Two guards, both of which have already caught real mistakes:

- It **refuses to build** if the `Version:` header, `CF7AIC_VERSION`, and `readme.txt`'s `Stable tag` disagree. Those three drift easily and the mismatch is invisible until a user reports the wrong version.
- It fails if dev tooling reaches the zip, including **any dot-file at the archive root** — `.phpunit.result.cache` and later `.github/` both got packaged before that check existed. Enumerating known offenders cannot catch the next one; rejecting the whole class can.

### CI

`.github/workflows/ci.yml` runs on pushes and pull requests to `main`, `dev`, `release`:

| Job | Does |
|---|---|
| `PHPCS` | lint, which is also the text-domain check |
| `PHPUnit (WordPress 6.8)` / `(7.0)` | full suite against both declared versions |
| `Build distribution zip` | runs `bin/build-zip.sh` on every commit |

Packaging runs on every commit rather than only at release, so a version mismatch or a leaked file surfaces on the pull request that caused it.

The test job asserts which WordPress it is actually running before testing anything. That is not ceremony — it is the guard against the stale-volume bug described above.

`main` and `release` are protected: pull request required (0 approvals, since this is a solo project), all four checks must pass, branches must be up to date, no force pushes, no deletions.

### Releasing

`.github/workflows/release.yml` fires when a **GitHub release is published** — not on a push, so cutting a release stays deliberate. It refuses to publish if the tag disagrees with the plugin version, rebuilds the zip from the tag, and attaches it.

Full sequence for a new version:

1. Bump **all three** version fields (the build refuses otherwise).
2. Add a `readme.txt` changelog entry.
3. Merge to `main` via PR, green CI.
4. `git tag -a vX.Y.Z && git push origin vX.Y.Z`
5. `gh release create vX.Y.Z` — this triggers the workflow.
6. Update SVN (below).

### WordPress.org SVN

The directory serves whatever `Stable tag` points at, so **trunk alone is not a release** — the tag has to exist or users get a listing with no installable version.

```bash
svn co https://plugins.svn.wordpress.org/olmbox-ai-inbox-for-contact-form-7 olmbox-svn
# copy the zip's contents into trunk/ (trunk IS the plugin directory, no nesting)
svn add --force trunk assets
svn ci -m "Release X.Y.Z" --username shaikat2142
svn cp trunk tags/X.Y.Z && svn ci -m "Tag X.Y.Z"
```

`assets/` is a **sibling of `trunk/`, never inside it** — banners, icons and screenshots feed the directory page and must not be shipped to users. They live in `.wordpress-org/` here and are excluded from the zip.

The SVN commit needs a password, which is the maintainer's to enter. Prepare the working copy, then hand over the commit.

### Directory assets

`.wordpress-org/` holds the banner, icon and five screenshots, plus the scripts that generate them:

- `make-assets.py` — banner and icon, drawn from the admin palette so the listing and the plugin look like one product.
- `capture-screenshots.sh` — captures the five screenshots from the running Docker environment via headless Chrome. It writes a temporary localhost-only, token-gated auth shim, uses it, and **deletes it on exit**, so no auth bypass is ever committed or left running.

Screenshot numbering must match the captions under `== Screenshots ==` in `readme.txt`. Change one, change the other.
