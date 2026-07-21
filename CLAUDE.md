# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

The Astro marketing and documentation site for **Olmbox AI Inbox for Contact Form 7**, a published WordPress plugin. Live at <https://olmbox.pages.dev>.

**This is an orphan branch.** It shares no commit history with the plugin branches (`main`, `dev`, `release`) and contains no plugin code. That is deliberate: site commits stay out of the plugin's log, and site files can never be swept into a distribution zip.

**Never merge `website` into a plugin branch or vice versa.** The histories are unrelated, so git will not produce a sensible diff — it will union two unrelated trees and dump the entire plugin into the site, or the reverse.

The plugin lives on the other branches. Check it out as a separate worktree rather than switching branches here.

## Commands

Astro 7 requires Node >= 22.12; the pinned version is in `.nvmrc`. The system default on this machine is older, so select it explicitly:

```bash
nvm use                # or: export PATH="$HOME/.nvm/versions/node/v22.17.0/bin:$PATH"
npm install
npm run dev            # http://localhost:4321
npm run build          # static output in dist/
npm run preview        # serve the built output
npm run check          # type-check templates AND validate content frontmatter
```

`npm run check` is the one worth running before committing: it validates every content collection entry against its schema, so a post missing a `date` or `image`, or a doc missing its `order`, fails there rather than rendering wrong.

## Structure

```
src/content/docs/     Documentation (order: sets sidebar position)
src/content/blog/     Posts (draft: true hides from production builds)
src/content.config.ts Collection schemas
src/consts.ts         Facts about the plugin — keep true to readme.txt on main
src/layouts/          BaseLayout (head/meta/OG), DocsLayout (sidebar)
src/pages/            Routes; [...slug].astro for docs and blog
src/styles/global.css Design tokens and .prose-doc styling
public/screenshots/   Copied from .wordpress-org/ on main
public/blog/          Generated feature images
scripts/              make-post-images.py
```

## Conventions that matter

**Facts about the plugin live in `src/consts.ts`**, not scattered through pages. Requirements, the provider list and URLs must stay true to `readme.txt` on `main`. When the plugin changes, that file is what needs updating.

**Colours come from the plugin's own admin stylesheet** (`--color-accent` `#6d5efc`, sidebar `#14162a`). The site and the WordPress admin screen should read as one product. Do not invent a second palette.

**No webfonts.** The plugin ships zero runtime dependencies; a marketing site that blocks render on a CDN font would undercut the claim.

**Screenshots are real**, captured from the plugin running in Docker — never mockups. They are trimmed content-aware, so `width`/`height` on each `<img>` must match the actual file or the page jumps as images load.

**Blog feature images are generated, not sourced.** `python3 scripts/make-post-images.py` reads each post's frontmatter and writes `public/blog/<slug>.png`. Deriving them from frontmatter means a card cannot show a title the post no longer has. Run it after adding or retitling a post.

**Drafts are the content gate.** `draft: true` renders in `npm run dev` but is excluded from production builds, so a post can be committed, reviewed in a pull request, and published later by flipping one field.

## Deployment

Pushes to `website` run `.github/workflows/site.yml`: `astro check`, build, an assertion that at least 8 HTML pages were produced (a misconfigured collection otherwise yields a successful build containing almost nothing), then deploy to Cloudflare Pages.

The deploy step **skips itself** when `CLOUDFLARE_API_TOKEN` / `CLOUDFLARE_ACCOUNT_ID` are absent, so the workflow is green rather than red before the account is connected. Those secrets are set.

Two things learned the hard way:

- **There is no "Run workflow" button.** `workflow_dispatch` only surfaces when the workflow file exists on the repository's *default* branch, and this one lives only on `website`. A push is how you trigger a deploy.
- **Cloudflare's production branch must be `website`.** It defaults to `main`, which on this repo is the plugin — with the default, deploys land as previews at `website.olmbox.pages.dev` while the apex serves nothing.

After deploying, **verify against the immutable deployment URL first** (`<hash>.olmbox.pages.dev`, printed in the job log). The apex takes up to a minute to propagate, and checking it immediately produces 404s that look like a failed deploy but are not.

Set the `SITE_URL` repository variable if a custom domain is attached; it feeds canonical URLs and the sitemap, and is the only place the host is named.
