# Olmbox AI Inbox for Contact Form 7

An AI Inbox for your Contact Form 7 forms: every submission gets an AI-drafted summary, suggested reply, and priority for you to review and send — nothing is ever emailed automatically.

![License: GPL v2+](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)
![Requires PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)
![Requires WordPress 6.8+](https://img.shields.io/badge/WordPress-6.8%2B-21759b.svg)
![Requires Contact Form 7](https://img.shields.io/badge/requires-Contact%20Form%207-00a8e8.svg)

## What it does

**This plugin never sends AI-generated content automatically.** When a visitor submits your chosen form:

1. Contact Form 7 sends its own notification email exactly as it always has — untouched.
2. The submission is sent to your configured AI provider for analysis.
3. A new entry lands in the **AI Inbox** with an AI-drafted summary, a suggested reply, a category, a priority, and a confidence score (with a short explanation of the AI's reasoning).

From there, you review it: read the original message and the AI's suggestion, edit the reply if you want to change anything, and click **Send Reply** only when you're ready. A confirmation step stands between you and every send — the AI drafts, you decide.

## AI Inbox

- Filter by status (New / Reviewed / Replied / Archived), priority, category, or date, and search by name, email, or message.
- Each submission has its own review screen: customer info, the original message exactly as submitted, the AI summary, an editable suggested reply, and the full AI analysis.
- Actions: Save Draft, Send Reply (with confirmation), Mark Reviewed, Archive, Delete.
- Low-confidence AI analysis (below 60%) is flagged so you know to look more closely before replying.
- A Dashboard section (and a native WordPress Dashboard widget) show what's new, what needs review, and how many replies have gone out.

## Bring your own API key

The plugin does not include or resell AI access — you provide your own API key for one of:

- OpenAI
- Anthropic
- Google Gemini
- OpenRouter

Once a key is entered, the Model field becomes a dropdown populated live from that provider's API. No request is ever sent to an AI provider until a valid key is configured.

## Installation

1. Install and activate [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) (required).
2. Upload and activate Olmbox AI Inbox for Contact Form 7.
3. Go to **Contact → AI Copilot → Settings**.
4. On the **AI Provider** tab, choose a provider, enter your API key, pick a model, and run the connection test.
5. On the **General** tab, turn on AI Copilot and choose which form(s) it should apply to.
6. Optionally customize the base system prompt on the **Prompt** tab.
7. Switch to the **AI Inbox** section any time to review and send replies.

See [`readme.txt`](readme.txt) for the full WordPress.org-format description, FAQ, and changelog.

## Privacy

The text a visitor submits is sent only to the AI provider you configure, solely to generate the summary/reply/classification shown in the AI Inbox — never to the plugin author, and no telemetry is collected. The plugin keeps a local log (most recent 200 submissions to your configured forms) in your own database only; it's pruned automatically and deleted entirely on uninstall.

See [`readme.txt`](readme.txt) for the full list of external AI providers this plugin can connect to, and links to each one's Terms of Service and Privacy Policy.

## Development

This is a PSR-4-namespaced (`CF7AIC\`) plugin with no runtime dependencies — Composer and npm are dev tooling only.

```bash
# PHP: install PHPCS + WordPress Coding Standards, then lint
composer install
composer run lint

# CSS: install Tailwind, then build admin/assets/css/admin.css
npm install
npm run build:css      # one-shot, minified
npm run watch:css      # rebuild on change while iterating
```

Admin styling is authored in [`admin/assets/css/tailwind.src.css`](admin/assets/css/tailwind.src.css) and compiled to the static `admin/assets/css/admin.css` that's actually enqueued — Tailwind only runs at build time, so the shipped plugin has no CDN or runtime CSS/JS framework dependency.

## License

GPL v2 or later — see [`LICENSE`](LICENSE).
