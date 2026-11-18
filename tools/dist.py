#!/usr/bin/env python3
"""Copia in site/ solo i file che il sito pubblicato usa davvero (pagine, css, js, immagini).
site/ è la cartella che va sul server: la carica il workflow GitHub Actions via FTP."""
import os, re, shutil

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(ROOT)
PAGES = ["index.html", "chi-siamo.html", "servizi.html", "contatti.html", "credits.html"]
EXTRA_ROOT = ["preventivo.php"]
EXTRA = ["favicon.svg", "share.jpg", "robots.txt", ".htaccess", "preventivo.php",
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
print(f"site/: {len(files)} file, {sum(os.path.getsize(f) for f in files) // 1024} KB")
