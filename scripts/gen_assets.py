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
write("logo.svg", """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 48" role="img" aria-labelledby="logoTitle">
<title id="logoTitle">CineVault</title>
<defs>
  <linearGradient id="lg1" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#ff5f6d"/>
    <stop offset="1" stop-color="#8b5cf6"/>
  </linearGradient>
</defs>
<circle cx="20" cy="24" r="18" fill="url(#lg1)"/>
<circle cx="20" cy="24" r="7" fill="#0b0b12"/>
<circle cx="20" cy="14" r="2.4" fill="#0b0b12"/>
<circle cx="29" cy="19" r="2.4" fill="#0b0b12"/>
<circle cx="29" cy="29" r="2.4" fill="#0b0b12"/>
<circle cx="20" cy="34" r="2.4" fill="#0b0b12"/>
<circle cx="11" cy="29" r="2.4" fill="#0b0b12"/>
<circle cx="11" cy="19" r="2.4" fill="#0b0b12"/>
<text x="46" y="31" font-family="'Poppins','Segoe UI',sans-serif" font-size="22" font-weight="700" fill="currentColor">Cine<tspan fill="#ff5f6d">Vault</tspan></text>
</svg>""")

# --------------------------------------------------------------- hero ----
def hero(name, c1, c2, seed):
    import random
    random.seed(seed)
    circles = ""
    for i in range(14):
        cx = random.randint(0, 1600)
        cy = random.randint(0, 700)
        r = random.randint(20, 160)
        op = round(random.uniform(0.03, 0.14), 2)
        circles += f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="#ffffff" opacity="{op}"/>\n'
    bars = ""
    for i in range(24):
        x = i * (1600/24)
        h = random.randint(60, 220)
        bars += f'<rect x="{x:.1f}" y="{700-h}" width="{(1600/24)*0.5:.1f}" height="{h}" fill="#000000" opacity="0.07"/>\n'
    write(name, f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 700" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
<defs>
  <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="{c1}"/>
    <stop offset="1" stop-color="{c2}"/>
  </linearGradient>
</defs>
<rect width="1600" height="700" fill="url(#bg)"/>
{circles}
{bars}
</svg>""")

hero("hero-1.svg", "#1b0f33", "#5b1e42", 1)
hero("hero-2.svg", "#0f1f33", "#1e5b57", 2)
hero("hero-3.svg", "#331b0f", "#5b3c1e", 3)

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
