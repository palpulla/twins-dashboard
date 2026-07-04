#!/usr/bin/env python3
"""Review-proof carousel cards + financing overlay for Twins Garage Doors.
Deterministic PIL rendering so review quotes are verbatim (no AI text drift).
Quotes are real Google reviews pulled live from the GBP listing 2026-07-04."""
import os
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.dirname(os.path.abspath(__file__))
SKILL_LOGO = "/Users/daniel/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/2e920e9f-f81d-4336-9ef5-17b8c40f5700/f8e6f3b0-3a0c-4e55-bd81-2884c0f7e894/skills/twins-media-generator/assets/logo/twins_logo_text_final.png"

NAVY = (30, 43, 71)
NAVY_DARK = (20, 29, 48)
GOLD = (245, 179, 36)
GOLD_LIGHT = (255, 217, 102)
WHITE = (255, 255, 255)
SIZE = 1080
F = "/System/Library/Fonts/Supplemental/"

def font(name, size):
    return ImageFont.truetype(F + name, size)

def star_row(draw, cx, y, n=5, r=26, gap=18, color=GOLD):
    import math
    for i in range(n):
        x0 = cx - (n * (2 * r + gap) - gap) / 2 + i * (2 * r + gap) + r
        pts = []
        for k in range(10):
            ang = -math.pi / 2 + k * math.pi / 5
            rad = r if k % 2 == 0 else r * 0.42
            pts.append((x0 + rad * math.cos(ang), y + rad * math.sin(ang)))
        draw.polygon(pts, fill=color)

def wrap(draw, text, fnt, maxw):
    words, lines, cur = text.split(), [], ""
    for w in words:
        t = (cur + " " + w).strip()
        if draw.textlength(t, font=fnt) <= maxw:
            cur = t
        else:
            lines.append(cur)
            cur = w
    lines.append(cur)
    return lines

def base_card():
    im = Image.new("RGB", (SIZE, SIZE), NAVY)
    d = ImageDraw.Draw(im)
    # subtle vertical gradient to navy_dark
    for y in range(SIZE):
        t = y / SIZE
        c = tuple(int(NAVY[i] + (NAVY_DARK[i] - NAVY[i]) * t) for i in range(3))
        d.line([(0, y), (SIZE, y)], fill=c)
    # gold top bar
    d.rectangle([0, 0, SIZE, 14], fill=GOLD)
    return im, d

def logo_img(width):
    lg = Image.open(SKILL_LOGO).convert("RGBA")
    # white bg logo -> put on white rounded chip for legibility
    ratio = width / lg.width
    return lg.resize((width, int(lg.height * ratio)))

def paste_logo_chip(im, cx, y, width=300):
    lg = logo_img(width - 40)
    chip_w, chip_h = width, lg.height + 36
    chip = Image.new("RGBA", (chip_w, chip_h), (0, 0, 0, 0))
    cd = ImageDraw.Draw(chip)
    cd.rounded_rectangle([0, 0, chip_w, chip_h], radius=24, fill=(255, 255, 255, 255))
    chip.paste(lg, (20, 18), lg)
    im.paste(chip, (int(cx - chip_w / 2), y), chip)
    return chip_h

def footer(im, d):
    txt = "4.9★ Rated  ·  698 Google Reviews  ·  Madison, WI"
    fnt = font("Arial Bold.ttf", 34)
    # star glyph may not render in Arial Bold; use word instead
    txt = "4.9 Rated  ·  698 Google Reviews  ·  Madison, WI"
    w = d.textlength(txt, font=fnt)
    d.text(((SIZE - w) / 2, SIZE - 78), txt, font=fnt, fill=GOLD_LIGHT)

def quote_card(fname, quote, attrib):
    im, d = base_card()
    star_row(d, SIZE / 2, 150)
    # big decorative quote mark
    d.text((70, 180), "“", font=font("Georgia Bold.ttf", 260), fill=GOLD)
    qf = font("Georgia.ttf", 58)
    lines = wrap(d, quote, qf, 880)
    if len(lines) > 7:
        qf = font("Georgia.ttf", 50)
        lines = wrap(d, quote, qf, 900)
    lh = qf.size + 22
    total = len(lines) * lh
    y = max(320, (SIZE - total) / 2 - 40)
    for ln in lines:
        w = d.textlength(ln, font=qf)
        d.text(((SIZE - w) / 2, y), ln, font=qf, fill=WHITE)
        y += lh
    af = font("Arial Bold.ttf", 42)
    w = d.textlength(attrib, font=af)
    d.text(((SIZE - w) / 2, y + 30), attrib, font=af, fill=GOLD)
    footer(im, d)
    im.save(os.path.join(OUT, fname))

def cover_card():
    im, d = base_card()
    h = paste_logo_chip(im, SIZE / 2, 140, 460)
    star_row(d, SIZE / 2, 140 + h + 120, r=38, gap=26)
    hf = font("Arial Black.ttf", 92)
    for i, ln in enumerate(["Madison Trusts", "Twins Garage Doors"]):
        w = d.textlength(ln, font=hf)
        d.text(((SIZE - w) / 2, 560 + i * 115), ln, font=hf, fill=WHITE)
    sf = font("Arial Bold.ttf", 52)
    s = "4.9 stars · 698 Google Reviews"
    w = d.textlength(s, font=sf)
    d.text(((SIZE - w) / 2, 830), s, font=sf, fill=GOLD)
    sf2 = font("Arial.ttf", 40)
    s2 = "Real reviews from your neighbors"
    w = d.textlength(s2, font=sf2)
    d.text(((SIZE - w) / 2, 910), s2, font=sf2, fill=GOLD_LIGHT)
    im.save(os.path.join(OUT, "card_0_cover.png"))

def cta_card():
    im, d = base_card()
    h = paste_logo_chip(im, SIZE / 2, 120, 400)
    hf = font("Arial Black.ttf", 84)
    for i, ln in enumerate(["Join 698 Happy", "Neighbors"]):
        w = d.textlength(ln, font=hf)
        d.text(((SIZE - w) / 2, 400 + i * 105), ln, font=hf, fill=WHITE)
    # offer pill
    pf = font("Arial Bold.ttf", 54)
    offer = "$0 Service Call  ·  24/7"
    pw = d.textlength(offer, font=pf) + 100
    d.rounded_rectangle([(SIZE - pw) / 2, 660, (SIZE + pw) / 2, 770], radius=55, fill=GOLD)
    d.text(((SIZE - d.textlength(offer, font=pf)) / 2, 685), offer, font=pf, fill=NAVY_DARK)
    tf = font("Arial Black.ttf", 72)
    phone = "(608) 888-8785"
    w = d.textlength(phone, font=tf)
    d.text(((SIZE - w) / 2, 830), phone, font=tf, fill=GOLD_LIGHT)
    sf2 = font("Arial.ttf", 38)
    s2 = "Family-owned · Madison, WI"
    w = d.textlength(s2, font=sf2)
    d.text(((SIZE - w) / 2, 940), s2, font=sf2, fill=WHITE)
    im.save(os.path.join(OUT, "card_6_cta.png"))

def financing_overlay():
    src = Image.open(os.path.join(OUT, "down-net_http20240812-100-gjtoco.jpg")).convert("RGB")
    src = src.resize((1080, 1080))
    d = ImageDraw.Draw(src)
    # bottom navy band (also covers CompanyCam watermark)
    band_top = 1080 - 150
    d.rectangle([0, band_top, 1080, 1080], fill=NAVY)
    d.rectangle([0, band_top, 1080, band_top + 8], fill=GOLD)
    f1 = font("Arial Black.ttf", 46)
    t1 = "New Door · Financing Available"
    w = d.textlength(t1, font=f1)
    d.text(((1080 - w) / 2, band_top + 24), t1, font=f1, fill=WHITE)
    f2 = font("Arial Bold.ttf", 36)
    t2 = "GoodLeap financing  ·  (608) 888-8785"
    w = d.textlength(t2, font=f2)
    d.text(((1080 - w) / 2, band_top + 86), t2, font=f2, fill=GOLD)
    src.save(os.path.join(OUT, "financing_install.png"))

QUOTES = [
    ("card_1_saundra.png",
     "I was most impressed when Twins responded to my request at 2:00am.",
     "— Saundra B. · Google review"),
    ("card_2_tasha.png",
     "This company responded to my inquiry for service within 30 minutes even though I sent them an email around 7:00PM.",
     "— Tasha H. · Google review"),
    ("card_3_shirley.png",
     "The 5 star rating and the fact you serve Deerfield made the decision.",
     "— Shirley B. · Deerfield, WI"),
    ("card_4_elaine.png",
     "Honestly, they deserve more than 5 stars. Super responsive, prompt and very fair pricing.",
     "— Elaine · Google review"),
    ("card_5_sean.png",
     "The best customer service I've received in years... Charles and Nick worked quickly, did the job well, and exceeded my already high expectations.",
     "— Sean M. D. · Google review"),
]

cover_card()
for f, q, a in QUOTES:
    quote_card(f, q, a)
cta_card()
financing_overlay()
print("done:", [f for f in sorted(os.listdir(OUT)) if f.startswith(("card_", "financing_"))])
