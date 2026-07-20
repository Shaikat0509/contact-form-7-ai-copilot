---
title: FAQ
description: Common questions about behaviour, failures and compatibility.
order: 5
---

## Does this ever email my visitors automatically?

No. At submission time the plugin only writes a row to its own log. The single code
path that emails a visitor runs in direct response to you clicking **Send Reply** and
confirming.

This is the plugin's central guarantee rather than a preference, and it is not
configurable — there is no setting that turns automatic sending on.

## Does it change how Contact Form 7 behaves?

No. Olmbox hooks CF7's public actions and never edits its files or alters its
outgoing mail. Your existing notification emails, validation rules and spam filtering
keep working exactly as they did.

It deliberately runs *after* CF7 has sent its own notification, which also means a
submission blocked by an anti-spam plugin never reaches the AI at all.

## What happens if the AI request fails?

Contact Form 7 carries on exactly as before — the submission is still received and
the normal notification still goes out. The submission still appears in your inbox so
you can reply by hand; it simply has no AI analysis, and the row records the error.

An AI failure never interrupts or breaks a form submission.

## Is my API key safe?

It is encrypted at rest in the WordPress options table and never exposed to the
frontend. The [privacy page](/docs/privacy) describes what that does and does not
protect against, in more detail than a FAQ answer allows.

## How much does it cost to run?

That depends on your provider and model, and on how many submissions your enabled
forms receive. Olmbox itself is free.

The relevant detail is that it makes one API call per submission, not one per output
— the summary, reply, category, priority and confidence all come back together.
Choosing a smaller, cheaper model in the settings is the main lever if volume is
high.

## Can I use it on several forms?

Yes, any number. Tick them on the **General** tab. It is worth being selective:
analysis is most useful on support and sales enquiries, and least useful on
newsletter signups where every message is identical.

## Does it work with multisite?

The plugin is built as a standard single-site plugin and each site keeps its own
settings and its own submission log. It has not been specifically tested as a
network-activated plugin, so treat that configuration as unsupported for now.

## Where is the source?

On [GitHub](https://github.com/Shaikat0509/contact-form-7-ai-copilot). It is
GPL v2-or-later, the same licence as WordPress itself.
