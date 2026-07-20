---
title: Privacy and data
description: What is sent where, what is stored, and what is never collected.
order: 4
---

## What leaves your site

When a submission arrives on an enabled form, the submitted field values are sent to
the AI provider **you** configured, using **your** key, for the sole purpose of
generating the analysis shown in your inbox.

Additionally, when you click **Test Connection** or **Load Models** on the settings
screen, your API key is sent to that provider to authenticate the request.

That is the complete list. No other site or visitor data is transmitted.

## What is never sent

- Nothing is sent to the plugin author. There is no phone-home, no licence check and
  no update ping beyond WordPress.org's own.
- No telemetry or analytics of any kind is collected.
- No request is made to any provider before you have configured a key, and none is
  made for forms you have not enabled.

## What is stored on your site

One database table holding the most recent 200 submissions to your enabled forms:
the submitted fields, the visitor's contact details, the AI's analysis, and — once
sent — the reply that was actually emailed.

This lives only in your own database. It is pruned automatically as new submissions
arrive, and deleted entirely when the plugin is uninstalled.

## Third-party services

Because the analysis happens at a provider you choose, that provider's terms and
privacy policy apply to the text you send. Their current policies:

- **OpenAI** — [Terms of Use](https://openai.com/policies/terms-of-use/), [Privacy Policy](https://openai.com/policies/privacy-policy/)
- **Anthropic** — [Commercial Terms](https://www.anthropic.com/legal/commercial-terms), [Privacy Policy](https://www.anthropic.com/legal/privacy)
- **Google Gemini** — [API Additional Terms](https://ai.google.dev/gemini-api/terms), [Privacy Policy](https://policies.google.com/privacy)
- **OpenRouter** — [Terms of Service](https://openrouter.ai/terms), [Privacy Policy](https://openrouter.ai/privacy)

## A note for GDPR and similar regimes

If your visitors are covered by GDPR or comparable law, sending their message text to
a third-party AI provider is a processing activity you are responsible for
disclosing. Practically, that usually means naming the provider you have chosen in
your own privacy policy and confirming your legal basis.

Olmbox deliberately makes this a decision you take rather than one it takes for you:
it does nothing until you pick a provider and enable specific forms.

Nothing here is legal advice.
