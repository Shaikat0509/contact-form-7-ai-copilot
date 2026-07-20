---
title: Installation
description: Get Olmbox running alongside Contact Form 7 in a few minutes.
order: 1
---

## Requirements

Olmbox is a companion to Contact Form 7 rather than a replacement, so CF7 must be
installed and active. It is declared as a hard dependency, which means WordPress
itself will stop you activating Olmbox without it.

| Requirement | Version |
| --- | --- |
| WordPress | 6.8 or newer |
| PHP | 8.1 or newer |
| Contact Form 7 | Any current version |

You will also need an API key from one of the supported providers. Olmbox does not
include or resell AI access — see [Configuration](/docs/configuration).

## Install the plugin

1. Install and activate **Contact Form 7** if it is not already active.
2. Upload and activate **Olmbox AI Inbox for Contact Form 7**.
3. Go to **Contact → Olmbox** in the WordPress admin.

On activation the plugin creates one database table for the submission log. Nothing
else on your site is modified.

## First-run setup

Olmbox stays inert until you both add a key and choose which forms it applies to.
There is no default-on behaviour.

1. Open **Contact → Olmbox → Settings → AI Provider**.
2. Pick a provider, paste your API key, and click **Load Models**.
3. Choose a model, then use **Test Connection** to confirm the key works.

<figure>
  <img src="/screenshots/settings-provider.png" alt="The AI Provider settings tab showing a provider dropdown, a masked API key, a model selector with a Load Models button, and a Test Connection button" width="1200" height="561" loading="lazy" decoding="async" />
  <figcaption>The AI Provider tab. Once a key is saved it is shown masked — the full value is never displayed again.</figcaption>
</figure>

4. Switch to the **General** tab, tick **Enable Olmbox**, and select the forms that
   should be analysed.

<figure>
  <img src="/screenshots/settings-general.png" alt="The General settings tab with an Enable Olmbox checkbox and a list of Contact Form 7 forms to select" width="1200" height="499" loading="lazy" decoding="async" />
  <figcaption>Nothing is analysed until both the switch is on and at least one form is ticked.</figcaption>
</figure>

Submissions to any form you have not ticked are ignored entirely — no request is
made and no row is logged.

## Verifying it works

Submit one of your enabled forms as a visitor would. Within a few seconds a new row
should appear under **Contact → Olmbox → AI Inbox**.

<figure>
  <img src="/screenshots/inbox.png" alt="The AI Inbox listing submissions with status, name, email, form, date, priority, category and confidence columns" width="1200" height="750" loading="lazy" decoding="async" />
  <figcaption>A populated inbox. The badge beside AI Inbox in the sidebar counts submissions still awaiting review.</figcaption>
</figure>

If the row appears but has no analysis, the submission was captured and the AI step
failed — the row will say why. That is by design: a provider outage should never
cost you the message itself.

> Contact Form 7 sends its own notification email before Olmbox runs. If your site
> cannot send mail at all, CF7 reports a mail failure and Olmbox never sees the
> submission. Fix mail delivery first if nothing is being logged.
