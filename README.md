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

## Local development

Astro 7 needs Node >= 22.12; the pinned version is in `.nvmrc`.

```bash
nvm use          # or otherwise select the version in .nvmrc
npm install
npm run dev      # http://localhost:4321
npm run build    # static output in dist/
npm run check    # type-check templates and validate content frontmatter
```

## Structure

```
src/content/docs/    Documentation pages (order: sets sidebar position)
src/content/blog/    Blog posts (draft: true hides from production builds)
src/pages/           Routes
src/consts.ts        Plugin facts — keep in step with readme.txt on main
src/styles/          Design tokens, lifted from the plugin's admin CSS
public/screenshots/  Copied from .wordpress-org/ on main
```

Two conventions worth keeping:

- **Facts about the plugin live in `src/consts.ts`**, not scattered
  through pages. Requirements and the provider list must stay true to
  `readme.txt` on `main`.
- **Colours come from the plugin's own palette** (`--color-accent`
  `#6d5efc`, sidebar `#14162a`). The site and the admin screen should
  read as one product.

## Content review gate

Posts with `draft: true` render in `npm run dev` but are excluded from
production builds, so a post can be committed, reviewed in a pull
request, and published later by flipping one field.

## Deployment

Pushes to `website` build via `.github/workflows/site.yml` and deploy to
Cloudflare Pages. The deploy step **skips itself** unless
`CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID` are set in repository
secrets, so the workflow is green rather than red before the account is
connected.

Set the `SITE_URL` repository variable once a custom domain is attached;
it feeds canonical URLs and the sitemap.
