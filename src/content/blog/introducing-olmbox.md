---
title: 'Introducing Olmbox: an AI inbox that never sends for you'
description: Why the plugin drafts replies instead of sending them, and what that constraint bought us.
date: 2026-07-20
author: Olmbox
draft: false
---

Most "AI for your contact form" tools are built around automation: a message arrives,
a model writes something back, and it goes out. That is a reasonable product. It is
not this one.

Olmbox analyses every Contact Form 7 submission and drafts a reply — then stops. The
draft sits in an inbox until a human reads it and clicks send.

## Why draft-only is the whole design

The failure mode of automated replies is not that they are bad on average. It is that
the worst one gets sent too. A model that writes a good reply to 95 out of 100
enquiries will confidently mishandle the refund request, the legal threat, or the
message from someone in distress — and it will do so in your company's voice, before
anyone has read it.

Reviewing 100 drafts is much less work than writing 100 replies, and it costs you
nothing on the 95 that were fine.

So the constraint is architectural rather than a setting. There is exactly one code
path that emails a visitor, and it runs only in response to a confirmed click in the
admin. At submission time the plugin does nothing but write a row to its own table.
There is no toggle to turn that into automatic sending, because the moment such a
toggle exists, the guarantee stops being a guarantee.

## What one API call buys

Each submission produces five things: a summary, a suggested reply, a category, a
priority and a confidence score with reasoning.

These come back from a **single** request. The model is asked for one JSON object
containing all five, rather than being asked five times. Triage that is five times
richer therefore costs the same as triage that is not, which is what makes it
reasonable to run on every submission rather than only the ones you already suspect
are important.

## Confidence is a feature, not decoration

The score matters most when it is low. A short, vague message — *"hey do you do the
thing for the bigger plan?"* — is exactly where a drafted reply is most likely to
guess wrong, and it is exactly where a confident-looking draft would be most
dangerous if it sent itself.

Anything under 60% is flagged in the list. The flag is not a warning that the plugin
is broken; it is the plugin telling you which three of today's forty messages deserve
your full attention.

## Failures should cost you the analysis, never the message

If the provider times out, the submission is still captured, still appears in the
inbox, and still tells you what went wrong. Contact Form 7's own notification email
has already gone out by then, untouched.

An AI feature that can lose a customer enquiry when an API has a bad afternoon is
worse than no AI feature. The ordering here is deliberate: Olmbox runs *after* CF7
has finished its own work, so there is nothing it can interrupt.

## Bring your own key

There is no resold access and no subscription. You supply a key for OpenAI,
Anthropic, Gemini or OpenRouter, it is encrypted at rest in your own database, and no
request is made to anyone until you have configured one.

No telemetry is collected. Nothing is sent to us, because there is no "us" to send it
to — the plugin has no server component at all.

## Where it is

The plugin is in review for the WordPress.org directory. The source is on
[GitHub](https://github.com/Shaikat0509/contact-form-7-ai-copilot), GPL v2-or-later,
and the [documentation](/docs) covers installation and the review workflow.

If you try it, the thing worth reporting back is not whether the replies are good —
it is whether reviewing them is faster than writing them. That is the only claim the
design actually makes.
