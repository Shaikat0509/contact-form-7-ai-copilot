# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"Olmbox AI Inbox for Contact Form 7" — a WordPress plugin that adds an AI Inbox to Contact Form 7. Every submission to a selected form gets an AI-drafted summary, suggested reply, category, priority, and confidence score, which an admin reviews before anything is sent.

Distributed on WordPress.org. Requires PHP 8.1+, WordPress 6.8+, and Contact Form 7 (a hard dependency, declared via `Requires Plugins`).

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

There is no test suite yet — no PHPUnit, no Playwright, no CI. Verification today is `phpcs` plus exercising the plugin in the Docker environment below. Treat "lint passes" as a weak signal, not proof a change works.

One thing `phpcs` *is* strong evidence for: text-domain consistency. The `WordPress.WP.I18n` sniff whitelists exactly one domain, so any `__()` call using the wrong one fails the run.

### Docker verification environment (preferred)

```bash
./docker/wp.sh up       # start stack, install WP + CF7, activate plugin (idempotent)
./docker/wp.sh seed     # populate the Inbox with representative submissions
./docker/wp.sh wp ...   # any WP-CLI command, e.g. ./docker/wp.sh wp plugin list
./docker/wp.sh logs     # Apache/PHP output and WP_DEBUG_LOG
./docker/wp.sh reset    # destroy volumes for a clean install
```

Admin UI at `http://localhost:8080/wp-admin/admin.php?page=olmbox-ai-inbox-for-contact-form-7`, login `admin` / `admin` (local only, never reachable off localhost).

Two details worth knowing before changing `docker/`:

- The plugin is bind-mounted under its **WordPress.org slug**, not this repo's directory name (`cf7-ai-copilot`). That mirrors the distribution zip's layout, so the environment exercises the shipped structure. Edits are live — no rebuild needed.
- The `cli` service sits behind a compose profile so `up --wait` never waits on it. It runs to completion and exits, which `--wait` otherwise reports as a failed service.

`docker/seed.php` writes through `SubmissionsRepository` rather than raw SQL, so seeding also exercises the insert path. Its fixtures deliberately cover every `ai_status` plus a sub-60% confidence score — those drive branches in the Inbox and detail views that an empty table cannot show.

`docker/` is excluded from `phpcs` (see `phpcs.xml.dist`): it is dev tooling that never ships, and its WP-CLI scripts have top-level variables that are globals by definition.

### The Local by Flywheel site (legacy)

### Running against the local WordPress install

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

Bootstrap: `cf7-ai-copilot.php` guards PHP/WP versions using only PHP 7.0-compatible syntax (so an old-PHP host gets a notice, never a parse error), defines `CF7AIC_*` constants, registers the autoloader, and boots `Plugin::get_instance()->init()` on `plugins_loaded`.

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

## Release surface

Version numbers appear in three places that must stay in sync: the `Version:` header and `CF7AIC_VERSION` constant in `cf7-ai-copilot.php`, and `Stable tag` in `readme.txt`. `readme.txt` is the WordPress.org-format source of truth for the description, FAQ, changelog, and the external-services disclosure; `README.md` is the GitHub-facing summary. A user-visible behavior change needs both updated, plus a changelog entry.

There is no packaging script in the repo — the distribution zip has been built ad hoc. Whatever builds it must exclude dev tooling (`vendor/`, `node_modules/`, `docker/`, `composer.*`, `package*.json`, `phpcs.xml.dist`, `tailwind.src.css`, `CLAUDE.md`) while keeping the compiled `admin/assets/css/admin.css`. The zip's root directory must be named for the WordPress.org slug, not this repo's directory name.
