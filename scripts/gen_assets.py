#!/usr/bin/env python3
"""Generates original placeholder SVG art for the CineVault demo site.
All artwork is procedurally generated (gradients + shapes) — no third-party
or copyrighted imagery is used anywhere in this project.
"""
import os

OUT = os.path.join(os.path.dirname(__file__), "..", "assets", "img")
os.makedirs(OUT, exist_ok=True)

def write(name, content):
    path = os.path.join(OUT, name)
    with open(path, "w") as f:
        f.write(content)
    print("wrote", path)

# ---------------------------------------------------------------- logo ----
# A plain monochrome wordmark (play-button mark + "CineVault" in white, one
# accent-colored syllable) — matches the client's Figma file, which uses a
# simple white logo + a single gold accent color site-wide, no gradients.
write("logo.svg", """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 48" role="img" aria-labelledby="logoTitle">
<title id="logoTitle">CineVault</title>
<circle cx="19" cy="24" r="17" fill="none" stroke="#ffffff" stroke-width="2"/>
<path d="M15 16.5 L30 24 L15 31.5 Z" fill="#cc9601"/>
<text x="44" y="31" font-family="'Oswald','Arial Narrow',sans-serif" font-size="22" font-weight="700" letter-spacing="-0.3" fill="#ffffff">Cine<tspan fill="#cc9601">Vault</tspan></text>
</svg>""")

# --------------------------------------------------------------- hero ----
# A procedural stand-in for the client's cinema-auditorium photo: rows of
# seats in deep reds receding toward a lit center aisle, dark vignette top
# and bottom — same composition/mood as the Figma banner image, built from
# shapes instead of a licensed stock photo.
def hero(name, seed, seat_hue):
    import random
    random.seed(seed)
    w, h = 1600, 700
    rows = ""
    n_rows = 11
    for r in range(n_rows):
        t = r / (n_rows - 1)
        row_y = 120 + t * (h - 160)
        scale = 0.35 + t * 0.75
        seat_w = 46 * scale
        seat_h = 30 * scale
        gap = 10 * scale
        row_w = w * (0.55 + t * 0.5)
        x0 = (w - row_w) / 2
        n_seats = max(4, int(row_w / (seat_w + gap)))
        shade = 30 + int(t * 60)
        fill = f"rgb({min(255, seat_hue[0] + shade)},{seat_hue[1] + shade // 3},{seat_hue[2] + shade // 3})"
        for i in range(n_seats):
            # leave a gap in the middle for the center aisle
            if n_seats // 2 - 1 <= i <= n_seats // 2:
                continue
            sx = x0 + i * (seat_w + gap)
            rows += f'<rect x="{sx:.1f}" y="{row_y:.1f}" width="{seat_w:.1f}" height="{seat_h:.1f}" rx="{4*scale:.1f}" fill="{fill}" opacity="0.92"/>\n'
    write(name, f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
<defs>
  <radialGradient id="aisle{seed}" cx="50%" cy="8%" r="75%">
    <stop offset="0" stop-color="#2a0a0a"/>
    <stop offset="55%" stop-color="#140404"/>
    <stop offset="100%" stop-color="#050202"/>
  </radialGradient>
  <linearGradient id="vignette{seed}" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#000000" stop-opacity="0.55"/>
    <stop offset="18%" stop-color="#000000" stop-opacity="0"/>
    <stop offset="80%" stop-color="#000000" stop-opacity="0"/>
    <stop offset="100%" stop-color="#000000" stop-opacity="0.65"/>
  </linearGradient>
</defs>
<rect width="{w}" height="{h}" fill="url(#aisle{seed})"/>
{rows}
<rect width="{w}" height="{h}" fill="url(#vignette{seed})"/>
</svg>""")

hero("hero-1.svg", 1, (120, 10, 10))
hero("hero-2.svg", 2, (130, 14, 12))
hero("hero-3.svg", 3, (110, 8, 14))

# ------------------------------------------------------- favorite cards --
def poster(name, title, c1, c2, seed):
    import random
    random.seed(seed)
    shapes = ""
    for i in range(6):
        cx = random.randint(0, 400)
        cy = random.randint(0, 300)
        r = random.randint(30, 120)
        op = round(random.uniform(0.06, 0.18), 2)
        shapes += f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="#ffffff" opacity="{op}"/>\n'
    write(name, f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
<defs>
  <linearGradient id="g{seed}" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="{c1}"/>
    <stop offset="1" stop-color="{c2}"/>
  </linearGradient>
</defs>
<rect width="400" height="300" fill="url(#g{seed})"/>
{shapes}
<rect x="0" y="230" width="400" height="70" fill="#000000" opacity="0.35"/>
<text x="20" y="272" font-family="'Poppins','Segoe UI',sans-serif" font-size="26" font-weight="700" fill="#ffffff">{title}</text>
</svg>""")

poster("fav-1.svg", "The Silent Horizon", "#2b2d42", "#8d99ae", 11)
poster("fav-2.svg", "Midnight Runner", "#3a0ca3", "#f72585", 12)
poster("fav-3.svg", "Eclipse of Dawn", "#0b3d2e", "#2dd4bf", 13)

# fallback poster used by JS when an API result has no image
poster("fallback-poster.svg", "No Poster", "#2a2a35", "#4a4a5a", 99)

print("done")
