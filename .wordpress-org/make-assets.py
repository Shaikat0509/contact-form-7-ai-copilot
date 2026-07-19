#!/usr/bin/env python3
"""
Generates WordPress.org plugin directory assets.

Colours are taken from the plugin's own admin CSS (tailwind.src.css) so
the directory listing reads as the same product as the admin screen:
  --color-sidebar    #14162a
  --color-sidebar-fg #b8bbd4

Everything is drawn at 4x and downsampled with LANCZOS, because PIL's
draw primitives have no antialiasing of their own.
"""

import os
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join(os.path.dirname(__file__), ".")
os.makedirs(OUT, exist_ok=True)

NAVY = (20, 22, 42)
NAVY_LIFT = (38, 42, 78)
LAVENDER = (184, 187, 212)
WHITE = (255, 255, 255)
ACCENT = (122, 162, 247)
GOLD = (245, 197, 24)

FONT_BOLD = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
FONT_REG = "/System/Library/Fonts/Supplemental/Arial.ttf"

SS = 4  # supersample factor


def font(path, size):
    return ImageFont.truetype(path, size)


def gradient(size, top_left, bottom_right):
    """Diagonal gradient, drawn small then upscaled — cheap and smooth."""
    w, h = size
    small = Image.new("RGB", (64, 64))
    px = small.load()
    for y in range(64):
        for x in range(64):
            t = (x / 63 * 0.55) + (y / 63 * 0.45)
            px[x, y] = tuple(
                int(top_left[i] + (bottom_right[i] - top_left[i]) * t) for i in range(3)
            )
    return small.resize((w, h), Image.LANCZOS)


def sparkle(d, cx, cy, r, colour):
    """Four-point star — the 'AI' cue, kept geometric rather than literal."""
    thin = r * 0.30
    d.polygon(
        [(cx, cy - r), (cx + thin, cy - thin), (cx + r, cy),
         (cx + thin, cy + thin), (cx, cy + r), (cx - thin, cy + thin),
         (cx - r, cy), (cx - thin, cy - thin)],
        fill=colour,
    )


def envelope(d, x, y, w, h, stroke, body, flap_colour):
    """Open envelope: an inbox that has been read, not an unopened letter."""
    r = int(h * 0.16)
    d.rounded_rectangle([x, y, x + w, y + h], radius=r, fill=None,
                        outline=body, width=stroke)
    # Flap as two strokes meeting at the centre dip. Endpoints are inset
    # from the corners so the stroke stays inside the rounded rect rather
    # than overshooting it at the top edge.
    inset = w * 0.10
    d.line([(x + inset, y + h * 0.22), (x + w / 2, y + h * 0.58)],
           fill=flap_colour, width=stroke, joint="curve")
    d.line([(x + w / 2, y + h * 0.58), (x + w - inset, y + h * 0.22)],
           fill=flap_colour, width=stroke, joint="curve")


def make_icon(px):
    s = px * SS
    img = gradient((s, s), NAVY, NAVY_LIFT).convert("RGBA")

    # Mask to a rounded square so the tile reads well on any background.
    mask = Image.new("L", (s, s), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, s - 1, s - 1],
                                           radius=int(s * 0.22), fill=255)
    img.putalpha(mask)

    d = ImageDraw.Draw(img)
    # Envelope sits slightly below centre to leave the sparkle clear air
    # in the top-right; at 128px an overlapping mark turns to mush.
    ew, eh = s * 0.48, s * 0.35
    ex, ey = (s - ew) / 2, s * 0.38
    envelope(d, ex, ey, ew, eh, max(2, int(s * 0.036)), WHITE, ACCENT)
    sparkle(d, s * 0.775, s * 0.225, s * 0.105, GOLD)

    return img.resize((px, px), Image.LANCZOS)


def make_banner(w, h):
    sw, sh = w * SS, h * SS
    img = gradient((sw, sh), NAVY, NAVY_LIFT).convert("RGBA")
    d = ImageDraw.Draw(img)

    # Soft accent arc bleeding off the right edge, for depth without noise.
    d.ellipse([sw * 0.66, -sh * 0.55, sw * 1.35, sh * 1.15],
              fill=(28, 31, 58, 255))
    d.ellipse([sw * 0.74, -sh * 0.30, sw * 1.28, sh * 0.95],
              fill=(34, 38, 70, 255))

    # Mark, vertically centred on the left.
    tile = sh * 0.46
    tx, ty = sw * 0.055, (sh - tile) / 2
    d.rounded_rectangle([tx, ty, tx + tile, ty + tile],
                        radius=int(tile * 0.24), fill=(30, 33, 62, 255))
    # Same geometry as make_icon() — it is one mark, and the two assets
    # sit next to each other on the directory page.
    ew, eh = tile * 0.48, tile * 0.35
    envelope(d, tx + (tile - ew) / 2, ty + tile * 0.38, ew, eh,
             max(2, int(tile * 0.052)), WHITE, ACCENT)
    sparkle(d, tx + tile * 0.775, ty + tile * 0.225, tile * 0.105, GOLD)

    text_x = tx + tile + sw * 0.045
    f_name = font(FONT_BOLD, int(sh * 0.215))
    f_sub = font(FONT_REG, int(sh * 0.101))
    f_tag = font(FONT_REG, int(sh * 0.082))

    name_y = sh * 0.235
    d.text((text_x, name_y), "Olmbox", font=f_name, fill=WHITE)

    nb = d.textbbox((text_x, name_y), "Olmbox", font=f_name)
    sub_y = nb[3] + sh * 0.045
    d.text((text_x, sub_y), "AI Inbox for Contact Form 7",
           font=f_sub, fill=LAVENDER)

    sb = d.textbbox((text_x, sub_y), "AI Inbox for Contact Form 7", font=f_sub)
    tag_y = sb[3] + sh * 0.055
    d.text((text_x, tag_y), "Review AI-drafted replies before anything is sent",
           font=f_tag, fill=(140, 145, 175))

    return img.convert("RGB").resize((w, h), Image.LANCZOS)


for px in (128, 256):
    make_icon(px).save(os.path.join(OUT, f"icon-{px}x{px}.png"))

make_banner(772, 250).save(os.path.join(OUT, "banner-772x250.png"))
make_banner(1544, 500).save(os.path.join(OUT, "banner-1544x500.png"))

for f in sorted(x for x in os.listdir(OUT) if x.endswith(".png")):
    p = os.path.join(OUT, f)
    print(f"{f:24} {Image.open(p).size}  {os.path.getsize(p) / 1024:.0f} KB")
