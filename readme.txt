=== Olmbox AI Inbox for Contact Form 7 ===
Contributors: shaikat2142
Tags: contact form 7, ai, openai, anthropic, gemini
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI Inbox for your Contact Form 7 forms: AI-drafted summaries and replies for you to review and send — nothing is emailed automatically.

== Description ==

Olmbox AI Inbox for Contact Form 7 adds an AI-powered review inbox to your Contact Form 7 forms, without modifying Contact Form 7 itself.

**This plugin never sends AI-generated content automatically.** When a visitor submits one of your chosen forms, the plugin:

1. Lets Contact Form 7 send its own notification email exactly as it always has.
2. Sends the submission to your configured AI provider for analysis.
3. Adds a new entry to your **AI Inbox** with an AI-drafted summary, a suggested reply, a category, a priority, and a confidence score (with a short explanation of the AI's reasoning).

From there, you review it: read the original message and the AI's suggestion, edit the reply if you want to change anything, and click **Send Reply** only when you're ready. A confirmation step stands between you and every send — the AI drafts, you decide.

= AI Inbox =

* Filter by status (New / Reviewed / Replied / Archived), priority, category, or date, and search by name, email, or message.
* Each submission has its own review screen: customer info, the original message exactly as submitted, the AI summary, an editable suggested reply, and the full AI analysis.
* Actions: Save Draft, Send Reply (with confirmation), Mark Reviewed, Archive, Delete.
* Low-confidence AI analysis (below 60%) is flagged so you know to look more closely before replying.
* A Dashboard section and a WordPress Dashboard widget show what's new, what needs review, and how many replies have gone out, with a quick link into the Inbox.

= Bring your own API key =

The plugin does not include or resell AI access. You provide your own API key for one of the following providers:

* OpenAI
* Anthropic
* Google Gemini
* OpenRouter

Once a key is entered, the Model field becomes a dropdown populated live from that provider's API, so you can pick a model by name instead of typing an identifier from memory.

No request is ever sent to an AI provider until you have entered a valid API key.

= Privacy =

This plugin sends the text a visitor submits to your form to the AI provider you configure, solely to generate the summary, suggested reply, and classification shown in the AI Inbox. No data is sent to the plugin author, and no telemetry or analytics of any kind is collected.

The plugin keeps a local log, on your own site's database, of the most recent 200 submissions to your configured forms (visible in the AI Inbox) — including the submitted fields, the visitor's contact details, the AI's analysis, and (once sent) the reply that was actually emailed. Older entries are pruned automatically. This log is stored only in your own database, never transmitted anywhere else, and is permanently deleted when the plugin is uninstalled.

== External services ==

This plugin connects to the AI provider you choose and configure with your own API key, to analyze new form submissions and generate a summary, suggested reply, category, priority, and confidence score. No request is ever sent until you have entered a valid API key for one of the providers below, and requests only happen for submissions to the forms you've explicitly enabled.

What is sent: the submitted form field values (e.g. name, email, message) and, on the AI Provider tab, your API key (to authenticate) when you click "Test Connection" or "Load Models". No other site or visitor data is sent.

* **OpenAI** — used when you select OpenAI as your provider. [Terms of Use](https://openai.com/policies/terms-of-use/), [Privacy Policy](https://openai.com/policies/privacy-policy/).
* **Anthropic** — used when you select Anthropic as your provider. [Commercial Terms of Service](https://www.anthropic.com/legal/commercial-terms), [Privacy Policy](https://www.anthropic.com/legal/privacy).
* **Google Gemini** — used when you select Google Gemini as your provider. [Gemini API Additional Terms of Service](https://ai.google.dev/gemini-api/terms), [Google Privacy Policy](https://policies.google.com/privacy).
* **OpenRouter** — used when you select OpenRouter as your provider (OpenRouter itself routes the request to whichever underlying model you pick). [Terms of Service](https://openrouter.ai/terms), [Privacy Policy](https://openrouter.ai/privacy).

== Installation ==

1. Install and activate Contact Form 7 (required).
2. Upload and activate Olmbox AI Inbox for Contact Form 7.
3. Go to **Contact → Olmbox**, then switch to the **Settings** section.
4. On the **AI Provider** tab, choose a provider, enter your API key, pick a model from the dropdown, and run the connection test.
5. On the **General** tab, turn on Olmbox and choose which form(s) it should apply to.
6. Optionally customize the base system prompt on the **Prompt** tab.
7. Switch to the **AI Inbox** section any time to review and send replies.

== Frequently Asked Questions ==

= Does this send anything automatically? =

No. AI analysis runs in the background after each submission, but the resulting summary, reply, and classification only ever sit in your AI Inbox. Nothing is emailed to the visitor until you personally click "Send Reply" and confirm.

= Does this modify Contact Form 7? =

No. The plugin only uses Contact Form 7's public hooks (such as `wpcf7_mail_sent`) and never edits CF7 core files or its outgoing mail.

= What happens if the AI request fails? =

Contact Form 7 continues to work exactly as it did before — the visitor's submission is still received and the normal notification email is still sent. The submission still appears in your AI Inbox so you can reply manually; it's just missing the AI's summary/suggestion, and the Inbox tells you why.

= Where is my API key stored? =

In the WordPress options table, encrypted at rest. It is never exposed in any frontend page, script, or REST response.

== Screenshots ==

1. AI Inbox — every submission with its status, priority, category, and confidence, plus filters.
2. Submission review screen — original message, AI summary, editable suggested reply, and full analysis.
3. General tab — enable Olmbox and choose which forms it applies to.
4. AI Provider tab — choose a provider, pick a model from the live dropdown, and test the connection.
5. Usage tab — model and estimated token usage.

== Changelog ==

= 1.0.0 =
* Initial release: an AI Inbox for your Contact Form 7 forms. Every submission gets an AI-drafted summary, suggested reply, category, priority, and confidence score (with reasoning) — nothing is ever emailed automatically; replies are only ever sent after an explicit, confirmed "Send Reply" click.
* AI Inbox: filterable/searchable submission list with status, priority, category, and confidence badges.
* Full-page Submission Details review screen with Save Draft, Send Reply, Mark Reviewed, Archive, and Delete actions.
* A Dashboard section and a WordPress Dashboard widget summarizing new, needs-review, and replied counts plus this month's AI usage.
* AI generation is a single request per submission (summary, reply, and classification all come from one combined API call).
* Bring-your-own-key support for OpenAI, Anthropic, Gemini, and OpenRouter, with a live model picker.
* Usage tab with model and estimated token usage.
