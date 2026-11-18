#!/usr/bin/env python3
"""Copia in site/ solo i file che il sito pubblicato usa davvero (pagine, css, js, immagini).
site/ è la cartella che va sul server: la carica il workflow GitHub Actions via FTP."""
import hashlib, os, re, shutil

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(ROOT)
PAGES = ["index.html", "chi-siamo.html", "servizi.html", "contatti.html", "privacy.html", "cookie.html", "credits.html", "assicurazione-auto-moto.html", "assicurazione-casa-famiglia.html", "assicurazione-vita-impresa.html", "passaggio-di-proprieta.html", "immatricolazioni.html", "patenti.html"]
EXTRA_ROOT = ["preventivo.php"]
EXTRA = ["favicon.svg", "share.jpg", "robots.txt", ".htaccess", "preventivo.php", "sitemap.php",
         "vendor/fonts/fonts.css", "vendor/fonts/instrument-sans-latin.woff2", "vendor/fonts/instrument-serif-latin.woff2",
         "vendor/fonts/OFL-InstrumentSans.txt", "vendor/fonts/OFL-InstrumentSerif.txt",
         "vendor/gsap/gsap.min.js", "vendor/gsap/ScrollTrigger.min.js",
         "assets/moments/lib.js", "assets/moments/moments.js"]
EXTRA += [f"assets/moments/{m}/moment.{e}" for m in ("photo-chapters", "picture-develops", "pinned-chapters", "word-highlight") for e in ("css", "js")]

refs = set()
for p in PAGES:
    t = open(p, encoding="utf-8").read()
    refs |= set(re.findall(r'(?:src|href)="([^"#:?]+)"', t))
    for m in re.findall(r'srcset="([^"]+)"', t):
        refs |= {part.strip().split(" ")[0] for part in m.split(",")}
refs = {r for r in refs if not r.startswith(("http", "mailto", "tel")) and not r.endswith(".html")}
files = sorted(set(PAGES) | refs | {e for e in EXTRA if os.path.exists(e)})
missing = [f for f in files if not os.path.exists(f)]
if missing:
    raise SystemExit("File mancanti: " + ", ".join(missing))

shutil.rmtree("site", ignore_errors=True)
for f in files:
    d = os.path.join("site", f)
    os.makedirs(os.path.dirname(d), exist_ok=True)
    shutil.copy2(f, d)
# sul server niente risorse esterne: font e GSAP dal sito stesso
FONT_LINKS = re.compile(r'<link rel="preconnect" href="https://fonts\.googleapis\.com">\s*<link rel="preconnect" href="https://fonts\.gstatic\.com" crossorigin>\s*<link href="https://fonts\.googleapis\.com/css2[^"]*" rel="stylesheet">')
for p in PAGES:
    d = os.path.join("site", p)
    t = open(d, encoding="utf-8").read()
    t = FONT_LINKS.sub('<link rel="preload" href="vendor/fonts/instrument-sans-latin.woff2" as="font" type="font/woff2" crossorigin>\n<link rel="stylesheet" href="vendor/fonts/fonts.css">', t)
    t = t.replace("https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/", "vendor/gsap/")
    # numero di versione su css e js: a ogni pubblicazione il browser scarica i file cambiati
    def _ver(m):
        f = os.path.join("site", m.group(2))
        if not os.path.exists(f):
            return m.group(0)
        h = hashlib.md5(open(f, "rb").read()).hexdigest()[:8]
        return f'{m.group(1)}="{m.group(2)}?v={h}"'
    t = re.sub(r'(href|src)="((?:assets|vendor)/[^"?]+\.(?:css|js))"', _ver, t)
    if "googleapis" in t or "cdnjs" in t:
        raise SystemExit(f"{p}: risorse esterne rimaste")
    open(d, "w", encoding="utf-8").write(t)
print(f"site/: {len(files)} file, {sum(os.path.getsize(f) for f in files) // 1024} KB")
