# Olmbox — website

The Astro marketing and documentation site for **Olmbox AI Inbox for
Contact Form 7**.

## This is an orphan branch

It shares no commit history with the plugin branches and contains no
plugin code. That is deliberate: the site and the plugin are separate
projects that happen to live in one repository, and keeping their
histories apart means site commits never appear in the plugin's log,
and nothing here can ever be swept into a distribution zip.

Because the histories are unrelated, do not merge `website` into a
plugin branch or vice versa.

## Branch layout

| Branch | Contains |
|---|---|
| `main` | Plugin source — integration target and source of truth |
| `dev` | Day-to-day plugin work, merged into `main` |
| `release` | Shippable plugin state; WordPress.org submissions are cut from here |
| `website` | This branch — the Astro site |

## Status

Nothing is scaffolded yet. The plugin itself is in the WordPress.org
review queue; see `readme.txt` on `main` for its current state.

When scaffolding starts, note that the plugin's admin UI palette is
defined in `admin/assets/css/tailwind.src.css` on `main` — the site
should reuse those values rather than inventing a second brand.
