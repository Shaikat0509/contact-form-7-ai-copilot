#!/usr/bin/env python3
"""
Generates a feature image for every blog post.

Reads the title and date straight out of each post's frontmatter and
writes public/blog/<slug>.png at 1200x630 — the size social platforms
expect for a card. Deriving them from frontmatter means a post cannot
end up with an image showing a title it no longer has.

    python3 scripts/make-post-images.py

Requires Pillow. Colours match the plugin's admin palette, same as the
rest of the site.
"""

import datetime
import os
import re
import textwrap

from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
POSTS = os.path.join(ROOT, "src", "content", "blog")
OUT = os.path.join(ROOT, "public", "blog")

W, H = 1200, 630
SS = 2  # supersample; PIL shapes have no antialiasing of their own

NAVY = (20, 22, 42)
NAVY_LIFT = (43, 47, 87)
ACCENT = (169, 155, 255)
BLUE = (122, 162, 247)
GOLD = (245, 197, 24)
MUTED = (150, 155, 190)
WHITE = (255, 255, 255)

BOLD = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
REG = "/System/Library/Fonts/Supplemental/Arial.ttf"


def frontmatter(path):
    """Minimal frontmatter reader — enough for the fields used here."""
    text = open(path, encoding="utf-8").read()
    match = re.match(r"^---\n(.*?)\n---", text, re.S)
    if not match:
        return {}
    data = {}
    for line in match.group(1).splitlines():
        if ":" not in line or line.startswith(" "):
            continue
        key, _, value = line.partition(":")
        value = value.strip().strip("'\"")
        data[key.strip()] = value
    return data


def gradient(size, start, end):
    small = Image.new("RGB", (64, 64))
    px = small.load()
    for y in range(64):
        for x in range(64):
            t = (x / 63 * 0.55) + (y / 63 * 0.45)
            px[x, y] = tuple(
                int(start[i] + (end[i] - start[i]) * t) for i in range(3)
            )
    return small.resize(size, Image.LANCZOS)


def sparkle(d, cx, cy, r, colour):
    thin = r * 0.30
    d.polygon(
        [(cx, cy - r), (cx + thin, cy - thin), (cx + r, cy),
         (cx + thin, cy + thin), (cx, cy + r), (cx - thin, cy + thin),
         (cx - r, cy), (cx - thin, cy - thin)],
        fill=colour,
    )


def envelope(d, x, y, w, h, stroke):
    d.rounded_rectangle([x, y, x + w, y + h], radius=int(h * 0.16),
                        outline=WHITE, width=stroke)
    inset = w * 0.10
    d.line([(x + inset, y + h * 0.22), (x + w / 2, y + h * 0.58)],
           fill=BLUE, width=stroke, joint="curve")
    d.line([(x + w / 2, y + h * 0.58), (x + w - inset, y + h * 0.22)],
           fill=BLUE, width=stroke, joint="curve")


def make(title, date, dest):
    sw, sh = W * SS, H * SS
    img = gradient((sw, sh), NAVY, NAVY_LIFT).convert("RGB")
    d = ImageDraw.Draw(img)

    # Depth, bleeding off the right edge like the WordPress.org banner.
    d.ellipse([sw * 0.70, -sh * 0.45, sw * 1.35, sh * 1.05], fill=(30, 33, 62))
    d.ellipse([sw * 0.80, -sh * 0.22, sw * 1.28, sh * 0.86], fill=(37, 41, 76))

    pad = sw * 0.075

    # Mark and wordmark, top left.
    tile = sh * 0.115
    d.rounded_rectangle([pad, sh * 0.10, pad + tile, sh * 0.10 + tile],
                        radius=int(tile * 0.24), fill=(30, 33, 62))
    ew, eh = tile * 0.48, tile * 0.35
    envelope(d, pad + (tile - ew) / 2, sh * 0.10 + tile * 0.38, ew, eh,
             max(2, int(tile * 0.052)))
    sparkle(d, pad + tile * 0.775, sh * 0.10 + tile * 0.225, tile * 0.105, GOLD)

    f_mark = ImageFont.truetype(BOLD, int(sh * 0.048))
    d.text((pad + tile + sw * 0.022, sh * 0.10 + tile * 0.30), "Olmbox",
           font=f_mark, fill=WHITE)

    # Title. Font size steps down as the title grows so long headlines
    # stay inside the card instead of overflowing it.
    length = len(title)
    if length <= 42:
        size, wrap_at = 0.108, 24
    elif length <= 70:
        size, wrap_at = 0.088, 30
    else:
        size, wrap_at = 0.072, 37

    f_title = ImageFont.truetype(BOLD, int(sh * size))
    lines = textwrap.wrap(title, width=wrap_at)[:4]
    line_h = sh * size * 1.22
    block_h = line_h * len(lines)
    y = sh * 0.60 - block_h / 2

    for line in lines:
        d.text((pad, y), line, font=f_title, fill=WHITE)
        y += line_h

    # Accent rule and date, bottom left.
    rule_y = sh * 0.86
    d.rectangle([pad, rule_y, pad + sw * 0.055, rule_y + sh * 0.008], fill=ACCENT)

    f_meta = ImageFont.truetype(REG, int(sh * 0.036))
    d.text((pad, rule_y + sh * 0.030), date, font=f_meta, fill=MUTED)

    img.resize((W, H), Image.LANCZOS).save(dest, optimize=True)


def main():
    os.makedirs(OUT, exist_ok=True)
    made = 0

    for name in sorted(os.listdir(POSTS)):
        if not name.endswith((".md", ".mdx")):
            continue
        slug = os.path.splitext(name)[0]
        meta = frontmatter(os.path.join(POSTS, name))
        title = meta.get("title", slug)
        # Match how the site renders dates, so the card and the page do
        # not disagree about the same post.
        date = meta.get("date", "")
        try:
            parsed = datetime.date.fromisoformat(date)
            date = f"{parsed.day} {parsed.strftime('%B %Y')}"
        except ValueError:
            pass

        dest = os.path.join(OUT, f"{slug}.png")
        make(title, date, dest)
        made += 1
        print(f"  {slug}.png  ({os.path.getsize(dest) // 1024} KB)  {title[:52]}")

    print(f"\n{made} feature image(s) written to public/blog/")


if __name__ == "__main__":
    main()
