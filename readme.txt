=== Contact Form 7 AI Copilot ===
Contributors: cf7aicopilot
Tags: contact form 7, ai, openai, anthropic, gemini
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI Inbox for one Contact Form 7 form: every submission gets an AI-drafted summary, suggested reply, and priority for you to review and send — nothing is ever emailed automatically.

== Description ==

Contact Form 7 AI Copilot adds an AI-powered review inbox to a single Contact Form 7 form on your site, without modifying Contact Form 7 itself.

**This plugin never sends AI-generated content automatically.** When a visitor submits your chosen form, the plugin:

1. Lets Contact Form 7 send its own notification email exactly as it always has.
2. Sends the submission to your configured AI provider for analysis.
3. Adds a new entry to your **AI Inbox** with an AI-drafted summary, a suggested reply, a category, a priority, and a confidence score (with a short explanation of the AI's reasoning).

From there, you review it: read the original message and the AI's suggestion, edit the reply if you want to change anything, and click **Send Reply** only when you're ready. A confirmation step stands between you and every send — the AI drafts, you decide.

= AI Inbox =

* Filter by status (New / Reviewed / Replied / Archived), priority, category, or date, and search by name, email, or message.
* Each submission has its own review screen: customer info, the original message exactly as submitted, the AI summary, an editable suggested reply, and the full AI analysis.
* Actions: Save Draft, Send Reply (with confirmation), Mark Reviewed, Archive, Delete.
* Low-confidence AI analysis (below 60%) is flagged so you know to look more closely before replying.
* A Dashboard widget shows what's new, what needs review, and how many replies have gone out, with a quick link into the Inbox.

= Bring your own API key =

The plugin does not include or resell AI access. You provide your own API key for one of the following providers:

* OpenAI
* Anthropic
* Google Gemini
* OpenRouter

Once a key is entered, the Model field becomes a dropdown populated live from that provider's API, so you can pick a model by name instead of typing an identifier from memory.

No request is ever sent to an AI provider until you have entered a valid API key.

= Free plan limits =

* One Contact Form 7 form
* 20 AI analyses per calendar month, resetting on the 1st

= Privacy =

This plugin sends the text a visitor submits to your form to the AI provider you configure, solely to generate the summary, suggested reply, and classification shown in the AI Inbox. No data is sent to the plugin author, and no telemetry or analytics of any kind is collected.

The plugin keeps a local log, on your own site's database, of the most recent 200 submissions to your configured form (visible in the AI Inbox) — including the submitted fields, the visitor's contact details, the AI's analysis, and (once sent) the reply that was actually emailed. Older entries are pruned automatically. This log is stored only in your own database, never transmitted anywhere else, and is permanently deleted when the plugin is uninstalled.

== Installation ==

1. Install and activate Contact Form 7 (required).
2. Upload and activate Contact Form 7 AI Copilot.
3. Go to **Contact → AI Copilot**, then switch to the **Settings** section.
4. On the **AI Provider** tab, choose a provider, enter your API key, pick a model from the dropdown, and run the connection test.
5. On the **General** tab, turn on AI Copilot and choose the one form it should apply to.
6. Optionally customize the base system prompt on the **Prompt** tab.
7. Switch to the **AI Inbox** section any time to review and send replies.

== Frequently Asked Questions ==

= Does this send anything automatically? =

No. AI analysis runs in the background after each submission, but the resulting summary, reply, and classification only ever sit in your AI Inbox. Nothing is emailed to the visitor until you personally click "Send Reply" and confirm.

= Does this modify Contact Form 7? =

No. The plugin only uses Contact Form 7's public hooks (such as `wpcf7_mail_sent`) and never edits CF7 core files or its outgoing mail.

= What happens if the AI request fails or my monthly limit is reached? =

Contact Form 7 continues to work exactly as it did before — the visitor's submission is still received and the normal notification email is still sent. The submission still appears in your AI Inbox so you can reply manually; it's just missing the AI's summary/suggestion, and the Inbox tells you why.

= Where is my API key stored? =

In the WordPress options table, encrypted at rest. It is never exposed in any frontend page, script, or REST response.

= I'm upgrading from an earlier version that sent replies automatically — what happens to my data? =

Your existing submission log is preserved and automatically migrated to the new schema (each row is marked as not-yet-reviewed, since nothing was reviewed under the old model). You'll see a one-time notice confirming the update and explaining the new review-before-send workflow.

== Screenshots ==

1. AI Inbox — every submission with its status, priority, category, and confidence, plus filters.
2. Submission review screen — original message, AI summary, editable suggested reply, and full analysis.
3. General tab — enable AI Copilot and choose your form.
4. AI Provider tab — choose a provider, pick a model from the live dropdown, and test the connection.
5. Usage tab — monthly quota, model, and estimated token usage.

== Changelog ==

= 2.0.0 =
* Removed automatic AI email sending. AI analysis (summary, suggested reply, category, priority, confidence score with reasoning) now lands in a new AI Inbox for review; replies are only ever sent after an explicit, confirmed "Send Reply" click.
* Added the AI Inbox: filterable/searchable submission list with status, priority, category, and confidence badges.
* Added a full-page Submission Details review screen with Save Draft, Send Reply, Mark Reviewed, Archive, and Delete actions.
* Added a WordPress Dashboard widget summarizing new, needs-review, and replied counts plus monthly AI usage.
* Consolidated AI generation into a single request per submission (summary, reply, and classification all come from one API call).
* Added Model and Estimated Token Usage to the Usage tab.
* Extended the submissions database schema (category, priority, confidence, confidence reason, review/reply audit trail); existing installs are migrated automatically and notified once.

= 1.0.0 =
* Initial release: AI auto-reply, AI summary, one-form scope, monthly usage limit, bring-your-own-key support for OpenAI/Anthropic/Gemini/OpenRouter, a customizable base prompt, a live model picker, and a Submissions log with a detail popup.

== Upgrade Notice ==

= 2.0.0 =
Automatic AI email sending has been removed in favor of a review-before-send AI Inbox — see the FAQ if you're upgrading from 1.0.0. Your existing submissions are migrated automatically.
