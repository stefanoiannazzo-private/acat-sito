#!/usr/bin/env python3
"""Genera le 4 pagine del sito ACAT Servizi da parti condivise (testa, nav, footer)."""
from PIL import Image
import os, html, re

ROOT = os.path.dirname(os.path.abspath(__file__))
os.chdir(ROOT)

PHONE = "329 754 2063"
TEL = "tel:+393297542063"
HOURS = "Lunedì 15:30–19, da martedì a venerdì 9–12 e 15:30–19"
MAP = "https://www.openstreetmap.org/search?query=Via%20Coazze%2035%20Giaveno"
RESERVIO = "https://acat-servizi-snc-giaveno.reservio.com/"
CTA = "Richiedi un preventivo"
CTA_HREF = "contatti.html#booking"
ARROW = '<svg class="m-arrow" viewBox="0 0 12 12" aria-hidden="true"><path class="m-arrow__stem" d="M1 6h8"/><path class="m-arrow__head" d="M4.5 2.5 8 6l-3.5 3.5"/></svg>'

UNS = {}
for f in (os.listdir("cand") if os.path.isdir("cand") else []):
    if f.endswith(".jpg"):
        w, h = Image.open("cand/" + f).size
        UNS[f.split("-")[0]] = (w, h)


import subprocess
def _dims(f):
    o = subprocess.run(["ffprobe", "-v", "error", "-select_streams", "v", "-show_entries", "stream=width,height", "-of", "csv=p=0", f], capture_output=True, text=True).stdout.strip()
    return tuple(int(x) for x in o.split(","))


def u(pid, alt, sizes="(max-width: 1023px) 100vw, 50vw", lazy=True, pos=None, cls="frame__img", extra=""):
    files = []
    for tag in ("800", "1400", "2400"):
        f = f"assets/img/u-{pid}-{tag}.webp"
        if os.path.exists(f):
            files.append((f, *_dims(f)))
    files = sorted({x[1]: x for x in files}.values(), key=lambda x: x[1])
    big = files[-1]
    st = f' style="--ly-pos: {pos}; object-position: {pos}"' if pos else ""
    ld = ' loading="lazy" decoding="async"' if lazy else ' fetchpriority="high"'
    c = f' class="{cls}"' if cls else ""
    srcset = ", ".join(f"{f} {w}w" for f, w, h in files)
    return (f'<img{c} src="{files[1][0] if len(files) > 1 else big[0]}" srcset="{srcset}" sizes="{sizes}" width="{big[1]}" height="{big[2]}" alt="{html.escape(alt)}"'
            f'{ld}{st} data-source="stand-in" data-subject="{html.escape(alt)}"{extra}>')


def IC(name, cls="li-ic__svg"):
    svg = open(f"assets/icons/ui-{name}.svg").read().strip()
    svg = re.sub(r'<svg[^>]*?viewBox="([^"]+)"[^>]*>', lambda m: f'<svg class="{cls}" viewBox="{m.group(1)}" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">', svg, count=1)
    return svg


def icon_inner(name):
    t = open(f"assets/icons/ui-{name}.svg").read()
    return re.search(r'<svg[^>]*>(.*)</svg>', t, re.S).group(1)


def hub_svg():
    import math
    cx, cy, R = 320, 270, 200
    nodes = [("Auto e moto", "car-front"), ("Casa", "house"), ("Vita", "heart-handshake"),
             ("Impresa", "briefcase-business"), ("Pratiche STA", "file-text"), ("Patenti", "id-card")]
    lines, dots = [], []
    for i, (lab, ic) in enumerate(nodes):
        a = math.radians(-90 + i * 60)
        x, y = cx + R * math.cos(a), cy + R * math.sin(a)
        lines.append(f'<line class="hub__line" x1="{cx}" y1="{cy}" x2="{x:.1f}" y2="{y:.1f}" style="--i:{i}"/>')
        ly = y + 62 if math.sin(a) > -0.2 else y - 52
        dots.append(f'''<g class="hub__node" style="--i:{i}"><circle cx="{x:.1f}" cy="{y:.1f}" r="38"/>
<g class="hub__ic" transform="translate({x-14:.1f} {y-14:.1f}) scale(1.1667)">{icon_inner(ic)}</g>
<text x="{x:.1f}" y="{ly:.1f}" text-anchor="middle">{lab}</text></g>''')
    return f'''<svg class="hub" viewBox="0 0 640 560" role="img" aria-label="ACAT Servizi al centro, collegata ai rami auto e moto, casa, vita, impresa, pratiche STA e patenti">
<circle class="hub__orbit" cx="{cx}" cy="{cy}" r="{R}"/>
<circle class="hub__orbit hub__orbit--in" cx="{cx}" cy="{cy}" r="120"/>
{''.join(lines)}
<g class="hub__core"><circle class="hub__pulse" cx="{cx}" cy="{cy}" r="74"/><circle cx="{cx}" cy="{cy}" r="74"/>
<g class="hub__ic hub__ic--core" transform="translate({cx-18} {cy-40}) scale(1.5)">{icon_inner("shield-check")}</g>
<text x="{cx}" y="{cy+30}" text-anchor="middle">ACAT</text></g>
{''.join(dots)}
</svg>'''


def li_ic(name):
    return f'<span class="li-ic">{IC(name)}</span>'


WIKI = {
    "f136888384": ("photo-f136888384", 1280, 749, [500, 960, 1280]),
    "f1549890": ("photo-f1549890", 1280, 962, [500, 960, 1280]),
    "f90507450": ("photo-f90507450", 1280, 465, [500, 960, 1280]),
}


def wk(fid, alt, sizes="(max-width: 1023px) 100vw, 50vw", lazy=True, pos=None, cls="frame__img", extra=""):
    base, w, h, ws = WIKI[fid]
    srcs = ", ".join(f"assets/img/{base}{'' if x == 1280 else '-' + str(x)}.webp {x}w" for x in ws)
    st = f' style="--ly-pos: {pos}; object-position: {pos}"' if pos else ""
    ld = ' loading="lazy" decoding="async"' if lazy else ' fetchpriority="high"'
    c = f' class="{cls}"' if cls else ""
    return (f'<img{c} src="assets/img/{base}.webp" srcset="{srcs}" sizes="{sizes}" width="{w}" height="{h}" '
            f'alt="{html.escape(alt)}"{ld}{st} data-source="stand-in" data-subject="{html.escape(alt)}"{extra}>')


def head(title, desc, moment, extra_css="", diagram=False):
    pre = f'<link rel="modulepreload" href="assets/moments/{moment}/moment.js">'
    dg = '<link rel="stylesheet" href="assets/diagrams/diagram.css">\n' if diagram else ""
    return f'''<!doctype html>
<html lang="it" data-type="service" data-radius="soft" data-buttons="arrow" data-motion>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{title}</title>
<meta name="description" content="{desc}">
<meta property="og:type" content="website">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{desc}">
<meta property="og:image" content="share.jpg">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="#f4f6f9">
<!-- <link rel="canonical" href="https://[dominio del cliente]/"> -->
<meta name="site-kind" content="other">
<meta name="page-type" content="service">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif&family=Instrument+Sans:wdth,wght@75..100,400..600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/system.css">
<link rel="stylesheet" href="assets/motion/motion.css">
<link rel="stylesheet" href="assets/site.css">
{dg}<style>
:root {{
  --p-bg: #f4f6f9; --p-surface: #e6ecf4; --p-ink: #0e2340; --p-muted: #4a5a72;
  --p-accent: #b3302a; --p-accent-ink: #f4f6f9; --p-action: #b3302a; --p-action-ink: #f4f6f9;
  --p-inv-bg: #0e2340; --p-inv-surface: #1c304c; --p-inv-ink: #f4f6f9; --p-inv-muted: #949faf;
  --p-inv-accent: #f4f6f9; --p-inv-accent-ink: #0e2340; --p-inv-action: #f4f6f9; --p-inv-action-ink: #0e2340;
  --font-display: "Instrument Serif", Georgia, serif;
  --font-text: "Instrument Sans", system-ui, sans-serif;
  --display-weight: 400;
  --display-tracking: -0.012em;
  --display-tracking-xl: -0.016em;
  --ly-scale: 1.12;
}}
</style>
<style>
@media (min-width: 640px) and (max-width: 1023px) {{ .grid > .frame--fill {{ aspect-ratio: 3 / 1 !important; }} }}
@media (max-width: 1023px) {{ .grid > .frame--fill {{ height: auto; aspect-ratio: 2 / 1; margin-top: var(--s-7); }} }}
@media (min-width: 1024px) and (max-width: 1279px) {{ .hero .btn-row > .btn {{ width: 100%; }} }}
@media (max-width: 639px) {{ .hide-phone {{ display: none; }} .grid > .frame--fill {{ aspect-ratio: 3 / 1 !important; }} }}
@media (max-width: 1023px) {{ .rows--long .rows__name small {{ display: none; }} }}
@media (max-width: 639px) {{ html body .nav .nav__bar > .nav__actions {{ display: none !important; }} html body .nav .nav__bar > .nav__toggle {{ margin-left: auto !important; }} }}
.nav__logo img {{ display: block; height: 30px; width: auto; }}
.rows .rows__name small {{ display: block; font-weight: 400; color: var(--muted); font-size: var(--t-body); margin-top: 2px; }}
{extra_css}
</style>
<script>document.documentElement.classList.add('m-enter');setTimeout(()=>document.documentElement.classList.add('m-enter-done'),700)</script>
<script>document.documentElement.classList.add('mo-js');setTimeout(()=>document.documentElement.classList.add('mo-safe'),2500)</script>
<link rel="modulepreload" href="assets/moments/moments.js">
<link rel="modulepreload" href="assets/moments/lib.js">
{pre}
</head>
'''


def nav(current):
    def cur(k):
        return ' aria-current="page"' if k == current else ""
    return f'''<header class="nav">
  <div class="wrap nav__bar">
    <a class="skip-link" href="#top">Vai al contenuto</a>
    <a class="nav__logo" href="index.html"><img src="logo-acat.svg" alt="ACAT Servizi" width="152" height="32"></a>
    <nav class="nav__links" aria-label="Principale">
      <div class="nav__item"><a class="nav__link" href="index.html"{cur('home')}>Home</a></div>
      <div class="nav__item">
        <a class="nav__link" href="servizi.html"{cur('servizi')}>Servizi <svg class="chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        <div class="nav__menu">
          <div class="wrap grid">
            <div class="menu-col span-3">
              <p class="t-label">Assicurazioni</p>
              <a href="servizi.html#insurance">{li_ic("car-front")}<b>Auto e moto</b><span>RC, furto, incendio, cristalli</span></a>
              <a href="servizi.html#insurance">{li_ic("house")}<b>Casa e famiglia</b><span>Abitazione e responsabilità civile</span></a>
              <a href="servizi.html#insurance">{li_ic("heart-handshake")}<b>Vita e impresa</b><span>Risparmio, infortuni, attività</span></a>
            </div>
            <div class="menu-col span-3">
              <p class="t-label">Pratiche auto</p>
              <a href="servizi.html#registry">{li_ic("key-round")}<b>Passaggio di proprietà</b><span>Allo sportello STA in agenzia</span></a>
              <a href="servizi.html#registry">{li_ic("file-plus-2")}<b>Immatricolazioni</b><span>Targhe e documento unico</span></a>
              <a href="servizi.html#registry">{li_ic("id-card")}<b>Patenti</b><span>Convalida e rinnovo</span></a>
            </div>
            <a class="menu-card span-3 start-10" href="servizi.html#registry">
              <div class="panel">{u("1761014586555", "Due mani si passano le chiavi di un'auto", sizes="300px", lazy=False, cls="frame__img").replace(' fetchpriority="high"', '')}</div>
              <span class="t-card">Sportello STA</span>
              <span class="t-small muted">Pratiche auto nello stesso ufficio</span>
            </a>
          </div>
        </div>
      </div>
      <div class="nav__item"><a class="nav__link" href="chi-siamo.html"{cur('chi')}>Chi siamo</a></div>
      <div class="nav__item"><a class="nav__link" href="contatti.html"{cur('contatti')}>Contatti</a></div>
    </nav>
    <div class="nav__actions">
      <a class="btn btn--primary" href="{CTA_HREF}">{CTA}{ARROW}</a>
    </div>
    <button class="nav__toggle" type="button" aria-expanded="false" aria-label="Menu">
      <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M2 5h14M2 13h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </button>
  </div>
</header>
'''


def hero(h1, lead, img, secondary, float_card=True, kicker="", tall=False, facts=False):
    img = img.replace('class="frame__img"', 'class="hero__bg"')
    img = re.sub(r'sizes="[^"]*"', 'sizes="100vw"', img)
    fx = f'''
      <ul class="hero__facts-ic">
        <li>{li_ic("shield-check")}<span><b>Dal 1992</b>a Giaveno</span></li>
        <li>{li_ic("umbrella")}<span><b>Più compagnie</b>un solo consulente</span></li>
        <li>{li_ic("file-text")}<span><b>Sportello STA</b>pratiche auto</span></li>
      </ul>''' if facts else ""
    k = f'<p class="hero__kicker">{kicker}</p>' if kicker else ""
    return f'''  <section class="hero hero--cover{' hero--tall' if tall else ''}" data-enter aria-labelledby="hero-h">
    {img}
    <div class="hero__veil" aria-hidden="true"></div>
    <div class="wrap hero__inner">
      <div class="hero__text">
        {k}
        <h1 id="hero-h">{h1}</h1>
        <p class="hero__lead">{lead}</p>
        <div class="btn-row"><a class="btn btn--primary" href="{CTA_HREF}">{CTA}{ARROW}</a>{secondary}</div>
      </div>{fx}
    </div>
  </section>
'''


def footer(close_h, close_line, close_img):
    return f'''<footer class="footer closing g2m" id="contact" aria-labelledby="close-h">
  <div class="wrap">
    <div class="closing__cta close--photo">
      <div class="section-head--split">
        <div><h2 id="close-h">{close_h}</h2><p class="t-lead">{close_line}</p></div>
        <a class="btn btn--primary" href="{CTA_HREF}">{CTA}{ARROW}</a>
      </div>
      <div class="close__media">{close_img}</div>
    </div>
    <div class="closing__links">
      <div class="grid">
        <div class="span-4 stack-4">
          <a class="nav__logo" href="index.html"><img src="logo-acat.svg" alt="ACAT Servizi" width="152" height="32"></a>
          <p class="footer__lede">Assicurazioni e pratiche auto in Via Coazze 35, 10094 Giaveno (TO).</p>
        </div>
        <div class="span-2 footer__col"><h3>Servizi</h3><ul><li><a href="servizi.html#insurance">Assicurazioni</a></li><li><a href="servizi.html#registry">Pratiche auto</a></li></ul></div>
        <div class="span-2 footer__col"><h3>Agenzia</h3><ul><li><a href="chi-siamo.html">Chi siamo</a></li><li><a href="index.html#method">Come lavoriamo</a></li></ul></div>
        <div class="span-2 footer__col"><h3>Contatti</h3><ul><li><a href="{TEL}">{PHONE}</a></li><li><a href="contatti.html#booking">Scrivici</a></li><li><a href="{RESERVIO}">Prenota online</a></li></ul></div>
        <div class="span-2 footer__col"><h3>Orari</h3><ul><li><a href="contatti.html#visit">Lun 15:30–19</a></li><li><a href="contatti.html#visit">Mar–ven 9–12, 15:30–19</a></li></ul></div>
      </div>
      <div class="footer__legal">
        <span>&copy; 2026 ACAT Servizi</span>
        <div class="socials"></div>
        <a class="credits" href="credits.html">Fotografie via Unsplash e Wikimedia Commons</a>
      </div>
    </div>
  </div>
</footer>
'''


SCRIPTS = f'''<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="assets/fx.js"></script>
<script type="module" src="assets/system.js"></script>
<script type="module" src="assets/motion/motion.js"></script>
'''


def page(name, headhtml, current, deal, body, foot, diagram=False):
    s = headhtml + "<body>\n" + f"<!-- type: service\n     deal: {deal}\n-->\n" + nav(current) + '\n<main id="top">\n' + body + "</main>\n\n" + foot + SCRIPTS
    if diagram:
        s += '<script type="module" src="assets/diagrams/mount.js"></script>\n'
    s += "</body>\n</html>\n"
    open(name, "w").write(s)


DEAL = "hero split, nav nav-mega, close close-photo, footer footer-columns, rhythm space, buttons arrow, mark client logo, palette brand (#0E2340 / #F4F6F9 / #B3302A), type instrument-serif"

# ---------------------------------------------------------------- HOME
home_body = hero(
    "Assicurazioni e pratiche auto a Giaveno",
    "Dal 1992 in Via Coazze confrontiamo più compagnie per assicurare auto, casa, famiglia e impresa, e sbrighiamo allo sportello STA le pratiche del tuo veicolo.",
    u("1775163024488", "Due persone rivedono i documenti di una polizza su un tavolo", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 100vw, 1080px", lazy=False, pos="50% 50%"),
    '<a class="btn btn--secondary" href="servizi.html">Vedi i servizi</a>', kicker="Agenzia assicurativa e pratiche auto", tall=True, facts=True,
) + f'''

  <section class="section fx-sec fx-mega" aria-hidden="true">
    <div class="wrap fx-mega__meta"><span>(Dal 1992)</span><span>(Via Coazze, Giaveno)</span></div>
    <div class="fx-mega__row fx-mega__row--a">Assicurazioni</div>
    <div class="fx-mega__row fx-mega__row--b">Pratiche <em>auto</em></div>
  </section>
  <section class="section" id="services" aria-labelledby="services-h">
    <div class="wrap grid grid--top">
      <div class="span-5">
        <div class="section-head">
          <h2 id="services-h">Polizze e pratiche auto in un solo ufficio</h2>
          <p class="hide-phone">Dalla RC auto al passaggio di proprietà, segui tutto con le stesse persone.</p>
        </div>
        <ul class="rows rows--ic">
          <li>{li_ic("car-front")}<span class="rows__name">Auto e moto<small>RC, furto e incendio, cristalli, assistenza</small></span></li>
          <li>{li_ic("house")}<span class="rows__name">Casa e famiglia<small>Abitazione, responsabilità civile, infortuni</small></span></li>
          <li>{li_ic("heart-handshake")}<span class="rows__name">Vita e impresa<small>Risparmio, attività, artigiani e partite IVA</small></span></li>
          <li>{li_ic("file-text")}<span class="rows__name">Pratiche auto STA<small>Passaggi di proprietà, targhe, documento unico</small></span></li>
        </ul>
        <p class="services__more hide-phone"><a class="link" href="servizi.html">Vedi tutti i servizi</a></p>
      </div>
      <div class="frame frame--photo frame--fill span-6 start-7">
        {u("1635965453398", "Una strada di montagna a tornanti con un'auto in salita", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 100vw, 1000px", pos="50% 60%")}
      </div>
    </div>
  </section>


  <section class="section fx-sec fx-fan" id="rami" aria-labelledby="rami-h">
    <div class="wrap">
      <div class="fx-fan__head"><h2 id="rami-h">Sei rami, un solo sportello</h2><p>Dalla polizza dell'auto alla patente: scegli da dove partire.</p></div>
      <div class="fx-fan__stage">
        <div class="fx-fan__phone" aria-hidden="true"><img src="logo-acat.svg" alt=""><span>I nostri servizi</span><em>Scorri per aprire</em></div>
        <a class="fx-fan__card" href="servizi.html#insurance">{li_ic("car-front")}<span><b>Auto e moto</b><small>RC, furto, incendio, cristalli</small></span></a>
        <a class="fx-fan__card" href="servizi.html#insurance">{li_ic("house")}<span><b>Casa</b><small>Abitazione e RC famiglia</small></span></a>
        <a class="fx-fan__card" href="servizi.html#insurance">{li_ic("heart-handshake")}<span><b>Vita</b><small>Protezione e risparmio</small></span></a>
        <a class="fx-fan__card" href="servizi.html#insurance">{li_ic("briefcase-business")}<span><b>Impresa</b><small>Attività e professioni</small></span></a>
        <a class="fx-fan__card" href="servizi.html#registry">{li_ic("file-text")}<span><b>Pratiche STA</b><small>Passaggi, targhe, documento unico</small></span></a>
        <a class="fx-fan__card" href="servizi.html#registry">{li_ic("id-card")}<span><b>Patenti</b><small>Convalida e duplicati</small></span></a>
      </div>
    </div>
  </section>
  <section class="section" id="method" data-moment="photo-chapters" aria-labelledby="method-h">
    <div class="wrap mo-ph">
      <div class="mo-ph__head"><h2 id="method-h">Come scegliamo la tua polizza</h2><p>Quattro passaggi, sempre con la stessa persona di riferimento in agenzia.</p></div>
      <ol class="mo-ph__list">
        <li class="mo-ph__ch">
          <figure class="mo-ph__pic">{u("1758227365187", "Sagome di legno di una famiglia e di una casa con le chiavi su un tavolo", sizes="(max-width: 639px) 240vw, (max-width: 1023px) 200vw, 100vw", cls="")}</figure>
          <h3>Ascoltiamo cosa vuoi proteggere</h3>
          <p>Partiamo da te: la famiglia, la casa, i veicoli e il lavoro, con le polizze che hai già.</p>
        </li>
        <li class="mo-ph__ch">
          <figure class="mo-ph__pic">{u("1454165804606", "Mani su un portatile e su fogli di confronto tra offerte", sizes="(max-width: 639px) 240vw, (max-width: 1023px) 200vw, 100vw", cls="")}</figure>
          <h3>Confrontiamo più compagnie</h3>
          <p>Siamo plurimandatari: mettiamo a confronto garanzie, franchigie e prezzi di compagnie diverse.</p>
        </li>
        <li class="mo-ph__ch">
          <figure class="mo-ph__pic">{u("1450101499163", "Una mano firma un contratto su una scrivania", sizes="(max-width: 639px) 240vw, (max-width: 1023px) 200vw, 100vw", cls="")}</figure>
          <h3>Firmi solo quando è chiaro</h3>
          <p>Ti spieghiamo ogni clausola prima della firma, con i documenti della compagnia sul tavolo.</p>
        </li>
        <li class="mo-ph__ch">
          <figure class="mo-ph__pic">{u("1687856265836", "Un'auto su una strada di campagna con le colline sullo sfondo", sizes="(max-width: 639px) 240vw, (max-width: 1023px) 200vw, 100vw", cls="")}</figure>
          <h3>Ti seguiamo dopo la firma</h3>
          <p>Per un sinistro, una scadenza o un cambio di veicolo passi in agenzia, in Via Coazze.</p>
        </li>
      </ol>
    </div>
  </section>


  <section class="section fx-sec fx-circle" aria-hidden="true">
    <div class="fx-circle__dot"><span class="fx-circle__ic">{IC("shield-check", "")}</span></div>
  </section>

  <section class="section fx-sec fx-dark" id="perche" aria-labelledby="perche-h">
    <div class="wrap">
      <h2 id="perche-h">Perché scegliere ACAT</h2>
    </div>
    <div class="fx-marquee" aria-hidden="true"><div class="fx-marquee__track">
      <span>Assicurazioni · <i>Pratiche auto</i> · Giaveno · Val Sangone ·</span><span>Assicurazioni · <i>Pratiche auto</i> · Giaveno · Val Sangone ·</span>
    </div></div>
    <div class="wrap">
      <ul class="fx-facts">
        <li>{li_ic("shield-check")}<b>Dal 1992</b><span>Un'agenzia di famiglia in Via Coazze, a Giaveno.</span></li>
        <li>{li_ic("umbrella")}<b>Più compagnie</b><span>Allianz, Generali, Italiana Assicurazioni, Prima e AXA: confrontiamo le proposte per te.</span></li>
        <li>{li_ic("file-text")}<b>Sportello STA</b><span>Targhe e documento unico rilasciati in tempo reale.</span></li>
        <li>{li_ic("clock")}<b>Orari</b><span>{HOURS}.</span></li>
      </ul>
    </div>

    <div class="wrap fx-partners__head"><p class="t-label">Le compagnie con cui lavoriamo</p></div>
    <div class="fx-logos"><ul class="fx-marquee__track fx-logos__track"><li><img src="assets/img/partner/allianz.png" alt="Allianz" loading="lazy" decoding="async" class="fx-logo--allianz"></li><li><img src="assets/img/partner/generali.png" alt="Generali" loading="lazy" decoding="async" class="fx-logo--generali"></li><li><img src="assets/img/partner/italiana.png" alt="Italiana Assicurazioni" loading="lazy" decoding="async" class="fx-logo--italiana"></li><li><img src="assets/img/partner/prima.png" alt="Prima" loading="lazy" decoding="async" class="fx-logo--prima"></li><li><img src="assets/img/partner/axa.png" alt="AXA" loading="lazy" decoding="async" class="fx-logo--axa"></li><li aria-hidden="true"><img src="assets/img/partner/allianz.png" alt="" loading="lazy" decoding="async" class="fx-logo--allianz"></li><li aria-hidden="true"><img src="assets/img/partner/generali.png" alt="" loading="lazy" decoding="async" class="fx-logo--generali"></li><li aria-hidden="true"><img src="assets/img/partner/italiana.png" alt="" loading="lazy" decoding="async" class="fx-logo--italiana"></li><li aria-hidden="true"><img src="assets/img/partner/prima.png" alt="" loading="lazy" decoding="async" class="fx-logo--prima"></li><li aria-hidden="true"><img src="assets/img/partner/axa.png" alt="" loading="lazy" decoding="async" class="fx-logo--axa"></li><li aria-hidden="true"><img src="assets/img/partner/allianz.png" alt="" loading="lazy" decoding="async" class="fx-logo--allianz"></li><li aria-hidden="true"><img src="assets/img/partner/generali.png" alt="" loading="lazy" decoding="async" class="fx-logo--generali"></li><li aria-hidden="true"><img src="assets/img/partner/italiana.png" alt="" loading="lazy" decoding="async" class="fx-logo--italiana"></li><li aria-hidden="true"><img src="assets/img/partner/prima.png" alt="" loading="lazy" decoding="async" class="fx-logo--prima"></li><li aria-hidden="true"><img src="assets/img/partner/axa.png" alt="" loading="lazy" decoding="async" class="fx-logo--axa"></li><li aria-hidden="true"><img src="assets/img/partner/allianz.png" alt="" loading="lazy" decoding="async" class="fx-logo--allianz"></li><li aria-hidden="true"><img src="assets/img/partner/generali.png" alt="" loading="lazy" decoding="async" class="fx-logo--generali"></li><li aria-hidden="true"><img src="assets/img/partner/italiana.png" alt="" loading="lazy" decoding="async" class="fx-logo--italiana"></li><li aria-hidden="true"><img src="assets/img/partner/prima.png" alt="" loading="lazy" decoding="async" class="fx-logo--prima"></li><li aria-hidden="true"><img src="assets/img/partner/axa.png" alt="" loading="lazy" decoding="async" class="fx-logo--axa"></li></ul></div>
  </section>
  <section class="section" id="faq" aria-labelledby="faq-h">
    <div class="wrap grid grid--top">
      <div class="span-5">
        <div class="section-head">
          <h2 id="faq-h">Domande frequenti</h2>
          <p class="faq__intro">Le risposte alle richieste che riceviamo più spesso allo sportello.</p>
        </div>
        <p class="faq__more"><a class="link" href="contatti.html#booking">Scrivici la tua domanda</a></p>
      </div>
      <div class="faq span-6 start-7">
        <div class="faq__item"><h3>{li_ic("shield-check")}<span>Lavorate con una sola compagnia?</span></h3><p>No. Siamo una sub-agenzia plurimandataria: lavoriamo con Allianz, Generali, Italiana Assicurazioni, Prima e AXA e ti proponiamo la più adatta al tuo caso.</p></div>
        <div class="faq__item"><h3>{li_ic("refresh-cw")}<span>La RC auto si rinnova da sola?</span></h3><p>No. Dal 2013 la RC auto non ha più il tacito rinnovo: alla scadenza scegli se rinnovare o cambiare.</p></div>
        <div class="faq__item"><h3>{li_ic("key-round")}<span>Fate il passaggio di proprietà?</span></h3><p>Sì. Come studio di consulenza automobilistica con sportello STA seguiamo il passaggio e il nuovo documento unico.</p></div>
        <div class="faq__item"><h3>{li_ic("id-card")}<span>Posso convalidare la patente da voi?</span></h3><p>Sì, ci occupiamo della convalida della patente. In agenzia ti diciamo quali documenti portare.</p></div>
        <div class="faq__item"><h3>{li_ic("clock")}<span>Quando siete aperti?</span></h3><p>{HOURS}. Sabato e domenica l'agenzia è chiusa.</p></div>
        <div class="faq__item faq__item--tablet"><h3>{li_ic("calendar-check")}<span>Posso prenotare un appuntamento?</span></h3><p>Sì. Puoi prenotarlo online oppure passare in agenzia negli orari di apertura.</p></div>
      </div>
    </div>
  </section>
'''
page("index.html",
     head("ACAT Servizi: assicurazioni e pratiche auto a Giaveno",
          "Sub-agenzia plurimandataria in Via Coazze a Giaveno dal 1992: polizze auto, casa, vita e impresa e pratiche auto allo sportello STA.",
          "photo-chapters",
          ".services__more { margin-top: var(--s-6); }\n@media (min-width: 1024px) and (max-width: 1279px) { #faq .faq__item:nth-child(5) { display: none; } }\n@media not ((min-width: 640px) and (max-width: 1023px)) { #faq .faq__item--tablet { display: none; } }\n@media (max-width: 1023px) { #faq .faq__intro { display: none; } }\n@media (max-width: 639px) { #faq .faq__item:nth-child(4), #faq .faq__more { display: none; } }"),
     "home", DEAL + "\n     moments: photo-chapters in #method", home_body,
     footer("Un preventivo senza impegno", HOURS + ", in Via Coazze.",
            u("1560518883", "Una piccola casa di legno con un mazzo di chiavi su un tavolo", sizes="(max-width: 639px) 160vw, 100vw", cls="")))

# ---------------------------------------------------------------- CHI SIAMO
DG = ('{"layout":"hub","size":"band","ground":"none","motion":{"pulse":"none"},'
      '"alt":"ACAT Servizi al centro, collegata ai rami auto, casa, vita, impresa, pratiche STA e patenti.",'
      '"center":{"label":"ACAT Servizi","icon":"ui:shield-check"},'
      '"spokes":[{"label":"Auto e moto","icon":"ui:car-front"},{"label":"Casa","icon":"ui:house"},'
      '{"label":"Vita","icon":"ui:heart-pulse"},{"label":"Impresa","icon":"ui:briefcase-business"},'
      '{"label":"Pratiche STA","icon":"ui:file-text"},{"label":"Patenti","icon":"ui:id-card"}]}')
chi_body = hero(
    "Agenzia assicurativa a Giaveno dal 1992",
    "ACAT Servizi nasce dalla famiglia Torelli: consulenza assicurativa in ogni ramo e studio di consulenza automobilistica, nello stesso ufficio di Via Coazze.",
    wk("f136888384", "La piazza di Giaveno con la torre campanaria e la fontana", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 160vw, 1080px", lazy=False, pos="22% 50%"),
    '<a class="btn btn--secondary" href="servizi.html">Vedi i servizi</a>', float_card=False, kicker="Chi siamo",
) + f'''
  <section class="section" id="story" aria-labelledby="story-h">
    <div class="wrap grid grid--top">
      <div class="span-6">
        <div class="section-head">
          <h2 id="story-h">Più di trent'anni in Val Sangone</h2>
        </div>
        <div class="stack-4 story__text">
          <p class="t-lead">Nel 1992 la famiglia Torelli apre ACAT Servizi a Giaveno per offrire consulenza assicurativa in ogni ramo.</p>
          <p>Oggi lavoriamo come sub-agenzia plurimandataria con compagnie primarie, ognuna con la propria specializzazione. Così possiamo cercare la copertura giusta anche quando il budget conta.</p>
          <p class="story__extra">Accanto alle polizze c'è lo studio di consulenza automobilistica: pratiche STA e convalida delle patenti si fanno qui, senza un secondo appuntamento altrove.</p>
        </div>
      </div>
      <figure class="develop span-6 start-7" data-moment="picture-develops">{wk("f1549890", "Una via del centro di Giaveno con la torre antica e la fontana", sizes="(max-width: 1023px) 100vw, 628px", cls="")}</figure>
    </div>
  </section>

  <section class="section" id="network" aria-labelledby="network-h">
    <div class="wrap grid grid--top">
      <div class="span-5">
        <div class="section-head">
          <h2 id="network-h">Più compagnie, una sola agenzia</h2>
          <p class="hide-phone">Come plurimandatari non vendiamo un solo marchio: per ogni rischio scegliamo la compagnia più adatta.</p>
        </div>
        <ul class="rows rows--ic">
          <li>{li_ic("shield-check")}<span class="rows__name">Sub-agenzia plurimandataria<small>Allianz, Generali, Italiana Assicurazioni, Prima e AXA</small></span></li>
          <li>{li_ic("clipboard-check")}<span class="rows__name">Studio di consulenza automobilistica<small>Sportello STA e convalida patenti</small></span></li>
          <li>{li_ic("map-pin")}<span class="rows__name">Un solo ufficio<small>Via Coazze 35, Giaveno, aperto dal lunedì al venerdì</small></span></li>
        </ul>
      </div>
      <div class="frame span-6 start-7 network__frame">
        {hub_svg()}
      </div>
    </div>
  </section>
'''
page("chi-siamo.html",
     head("Chi siamo: ACAT Servizi, agenzia di famiglia a Giaveno",
          "La storia di ACAT Servizi: dal 1992 la famiglia Torelli segue assicurazioni e pratiche auto a Giaveno, come sub-agenzia plurimandataria.",
          "picture-develops",
          ".develop { margin: 0; border-radius: var(--r-lg); overflow: hidden; height: min(600px, var(--ly-fit, 600px)); }\n"
          ".develop > img { display: block; width: 100%; height: 100%; object-fit: cover; }\n"
          "@media (max-width: 1023px) { .develop { height: auto; aspect-ratio: 3 / 1; margin-top: var(--s-7); } }\n"
          ".network__frame { height: 600px; display: flex; align-items: flex-start; justify-content: center; padding: 0; background: var(--p-surface); border-radius: var(--r-lg); }\n.network__frame .dg { height: 100%; }\n"
          ".network__frame .dg { width: 100%; }\n@media (max-width: 1023px) { .story__extra { display: none; } }\n"
          "@media (max-width: 1023px) { .network__frame { min-height: 0; height: 420px; margin-top: var(--s-7); } #network .rows__name small { display: none; } }",
          diagram=False),
     "chi", DEAL + "\n     moments: picture-develops in #story", chi_body,
     footer("Passa a trovarci in Via Coazze", HOURS + ".",
            u("1732717566587", "Una mano tiene una piccola casa scura su uno sfondo verde", sizes="(max-width: 639px) 160vw, 100vw", cls="")),
     diagram=False)

# ---------------------------------------------------------------- SERVIZI
serv_body = hero(
    "Assicurazioni e sportello STA a Giaveno",
    "Dal 1992 polizze in tutti i rami con più compagnie e uno sportello STA per targhe, documento unico e patenti, nello stesso ufficio.",
    u("1761014586544", "Una mano consegna le chiavi di un'auto a un'altra mano", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 100vw, 1080px", lazy=False, pos="50% 45%"),
    '<a class="btn btn--secondary" href="#registry">Vedi le pratiche auto</a>', kicker="I nostri servizi",
) + f'''

  <section class="section fx-sec fx-mega" aria-hidden="true">
    <div class="wrap fx-mega__meta"><span>(Assicurazioni in tutti i rami)</span><span>(Sportello Telematico dell'Automobilista)</span></div>
    <div class="fx-mega__row fx-mega__row--a">Polizze</div>
    <div class="fx-mega__row fx-mega__row--b">Sportello <em>STA</em></div>
  </section>
  <section class="section" id="insurance" style="--hm: 2" aria-labelledby="insurance-h">
    <div class="wrap grid grid--top">
      <div class="span-5">
        <div class="section-head">
          <h2 id="insurance-h">Assicurazioni in tutti i rami</h2>
          <p class="insurance__intro">Per ogni polizza confrontiamo le proposte di più compagnie prima di consigliarti.</p>
        </div>
        <ul class="rows rows--ic rows--long">
          <li>{li_ic("car-front")}<span class="rows__name">RC auto e moto<small>Auto, moto, ciclomotori e veicoli commerciali</small></span></li>
          <li>{li_ic("flame")}<span class="rows__name">Furto, incendio e cristalli<small>Garanzie accessorie per il tuo veicolo</small></span></li>
          <li>{li_ic("house")}<span class="rows__name">Casa e responsabilità civile<small>L'abitazione e i danni causati a terzi</small></span></li>
          <li>{li_ic("hand-heart")}<span class="rows__name">Infortuni e salute<small>Coperture per te e la tua famiglia</small></span></li>
          <li>{li_ic("piggy-bank")}<span class="rows__name">Vita e risparmio<small>Protezione e piani di accumulo</small></span></li>
          <li>{li_ic("briefcase-business")}<span class="rows__name">Impresa e professioni<small>Negozi, artigiani, aziende e liberi professionisti</small></span></li>
        </ul>
      </div>
      <div class="frame frame--photo frame--fill span-6 start-7">
        {u("1517554558809", "Un padre porta in braccio due bambine in un prato fiorito", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 100vw, 1000px", pos="45% 50%")}
      </div>
    </div>
  </section>

  <section class="section statement" id="sta" data-moment="word-highlight" aria-label="Studio di consulenza automobilistica">
    <div class="wrap">
      <p class="t-label">Studio di consulenza automobilistica</p>
      <p class="mo-wh__text">ACAT Servizi non è solo assicurazioni: come studio di consulenza automobilistica svolgiamo tutte le pratiche STA e la convalida delle patenti, <strong>nello stesso ufficio</strong> in cui firmi la polizza.</p>
    </div>
  </section>

  <section class="section" id="registry" style="--hm: 2" aria-labelledby="registry-h">
    <div class="wrap grid grid--top">
      <div class="span-5">
        <div class="section-head">
          <h2 id="registry-h">Pratiche auto allo sportello STA</h2>
          <p>Con lo Sportello Telematico dell'Automobilista targhe e documento unico si ottengono in tempo reale.</p>
        </div>
        <ul class="rows rows--ic rows--long">
          <li>{li_ic("key-round")}<span class="rows__name">Passaggio di proprietà<small>Auto e moto usate, tra privati o da un rivenditore</small></span></li>
          <li>{li_ic("file-plus-2")}<span class="rows__name">Immatricolazione<small>Prima iscrizione, targhe e documento unico</small></span></li>
          <li>{li_ic("copy")}<span class="rows__name">Annotazioni e duplicati<small>Cambi di residenza, perdita o furto dei documenti</small></span></li>
          <li>{li_ic("id-card")}<span class="rows__name">Convalida della patente<small>Rinnovo e duplicato della patente di guida</small></span></li>
        </ul>
      </div>
      <div class="frame frame--photo frame--fill span-6 start-7">
        {u("1603881359318", "Un'auto percorre una strada di montagna tra gli abeti", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 100vw, 1000px", pos="50% 55%")}
      </div>
    </div>
  </section>
'''
page("servizi.html",
     head("Servizi: assicurazioni e pratiche auto a Giaveno | ACAT Servizi",
          "Polizze auto, casa, vita e impresa con più compagnie e pratiche STA, passaggi di proprietà e patenti in Via Coazze a Giaveno.",
          "word-highlight", "@media (max-width: 1023px) { .insurance__intro { display: none; } }\n@media (min-width: 640px) and (max-width: 1023px) { #insurance .grid > .frame--fill { aspect-ratio: 4 / 1 !important; } }\n@media (max-width: 639px) { #insurance .grid > .frame--fill { aspect-ratio: 4 / 5 !important; } #registry .grid > .frame--fill { aspect-ratio: 3 / 4 !important; } #registry .rows__name small { display: block !important; } }"),
     "servizi", DEAL + "\n     moments: word-highlight in #sta", serv_body,
     footer("Un preventivo senza impegno", HOURS + ", in Via Coazze.",
            u("1733522688659", "Una strada a tornanti in una valle alpina in autunno", sizes="(max-width: 639px) 160vw, 100vw", cls="")))

# ---------------------------------------------------------------- CONTATTI
cont_body = hero(
    "Contatti e orari di ACAT Servizi a Giaveno",
    f"Ci trovi in Via Coazze 35 dal lunedì al venerdì. Chiamaci al {PHONE}, scrivici per un preventivo oppure prenota online un appuntamento.",
    wk("f1549890", "Una via del centro di Giaveno con la torre antica e la fontana", sizes="(max-width: 639px) 160vw, (max-width: 1023px) 160vw, 1080px", lazy=False, pos="30% 50%"),
    f'<a class="btn btn--secondary" href="{RESERVIO}">Prenota un appuntamento</a>', float_card=False, kicker="Contatti",
) + f'''
  <section class="section" id="booking" style="--hm: 2" aria-labelledby="booking-h">
    <div class="wrap grid grid--top">
      <div class="span-5 stack-6" id="visit">
        <div class="section-head">
          <h2 id="booking-h">{CTA}</h2>
          <p>Scrivi cosa vuoi assicurare o quale pratica ti serve: ti ricontattiamo negli orari d'agenzia.</p>
        </div>
        <div class="stack-4">
          <div class="visit__line">{li_ic("phone")}<p class="visit__phone"><a class="link" href="{TEL}">{PHONE}</a></p></div>
          <div class="visit__line">{li_ic("map-pin")}<address class="visit__address">Via Coazze 35<br>10094 Giaveno (TO)</address></div>
          <a class="link" href="{MAP}">Indicazioni stradali</a>
        </div>
        <div class="visit__line">{li_ic("clock")}<dl class="hours" data-tz="Europe/Rome"><dt data-days="mon">Lunedì</dt><dd>15:30–19</dd><dt data-days="tue-fri">Martedì–venerdì</dt><dd>9–12 e 15:30–19</dd><dt data-days="sat,sun">Sabato e domenica</dt><dd>Chiuso</dd></dl></div>
      </div>
      <div class="span-6 start-7 form-card">
        <form class="form" action="mailto:preventivo@acatservizi.example" method="post" enctype="text/plain" data-endpoint="" data-sent="La tua richiesta di preventivo è arrivata. Ti rispondiamo negli orari d'agenzia." novalidate>
          <div class="field"><label for="f-name">Nome e cognome</label>
            <input id="f-name" name="nome" type="text" autocomplete="name" required aria-describedby="f-name-err">
            <p class="field__error" id="f-name-err" hidden>Scrivi il tuo nome e cognome.</p></div>
          <div class="field"><label for="f-email">Email</label>
            <input id="f-email" name="email" type="email" autocomplete="email" inputmode="email" required aria-describedby="f-email-err">
            <p class="field__error" id="f-email-err" hidden>Scrivi un indirizzo email con la @, per esempio nome@esempio.it.</p></div>
          <div class="field"><label for="f-what">Polizza o pratica</label>
            <input id="f-what" name="richiesta" type="text" autocomplete="off" required aria-describedby="f-what-err">
            <p class="field__error" id="f-what-err" hidden>Scrivi cosa vuoi assicurare o quale pratica ti serve.</p></div>
          <div class="field"><label for="f-town">Comune di residenza</label>
            <input id="f-town" name="comune" type="text" autocomplete="address-level2" aria-describedby="f-town-err">
            <p class="field__error" id="f-town-err" hidden>Scrivi il comune in cui vivi.</p></div>
          <div class="btn-row"><button class="btn btn--primary" type="submit">{CTA}</button></div>
          <p class="form__status" role="status" data-form-status></p>
          <p class="t-small muted">Ti rispondiamo negli orari d'agenzia.</p>
        </form>
      </div>
      <figure class="frame frame--photo span-12 booking__pano">{u("1733522688659", "Una strada a tornanti in una valle alpina in autunno", sizes="(max-width: 639px) 200vw, (max-width: 1023px) 160vw, 100vw", pos="50% 55%")}</figure>
    </div>
  </section>

  <section class="section" id="steps" style="--h: 2; --hm: 2" data-moment="pinned-chapters" aria-labelledby="steps-h">
    <div class="wrap mo-pc__grid">
      <div class="mo-pc__text">
        <h2 id="steps-h">Come arriva il tuo preventivo</h2>
        <ol class="mo-pc__steps">
          <li class="mo-pc__step">
            <h3>Ci scrivi o passi in agenzia</h3>
            <p>Con il modulo qui sopra, prenotando online o in Via Coazze negli orari di apertura.</p>
            <figure class="mo-pc__visual">{u("1653565217811", "Una mano tiene la chiave di un'auto davanti alla carrozzeria", sizes="(max-width: 1023px) 100vw, 628px", cls="")}</figure>
          </li>
          <li class="mo-pc__step">
            <h3>Confrontiamo le compagnie</h3>
            <p>Mettiamo a confronto le proposte delle compagnie con cui collaboriamo, garanzia per garanzia.</p>
            <figure class="mo-pc__visual">{u("1454165804606", "Mani su un portatile e su fogli di confronto tra offerte", sizes="(max-width: 1023px) 100vw, 628px", cls="")}</figure>
          </li>
          <li class="mo-pc__step">
            <h3>Ti presentiamo la proposta</h3>
            <p>Ti spieghiamo prezzo, franchigie ed esclusioni. Decidi tu, senza impegno fino alla firma.</p>
            <figure class="mo-pc__visual">{u("1450101499163", "Una mano firma un contratto su una scrivania", sizes="(max-width: 1023px) 100vw, 628px", cls="")}</figure>
          </li>
        </ol>
      </div>
      <div class="mo-pc__visuals frame"></div>
    </div>
  </section>
'''
page("contatti.html",
     head("Contatti: ACAT Servizi in Via Coazze a Giaveno",
          "Indirizzo, orari e modulo per richiedere un preventivo ad ACAT Servizi, agenzia di assicurazioni e pratiche auto a Giaveno.",
          "pinned-chapters",
          ".form-card { background: var(--g-alt); border-radius: var(--r-lg); padding: var(--s-7); }\n"
          "@media (max-width: 639px) { .form-card { padding: var(--s-5); } }\n.booking__pano { margin: var(--s-7) 0 0; aspect-ratio: 6 / 1; border-radius: var(--r-lg); overflow: hidden; }\n.booking__pano img { width: 100%; height: 100%; object-fit: cover; }\n@media (max-width: 1023px) { .booking__pano { aspect-ratio: 3 / 2; } }\n@media (max-width: 639px) { .booking__pano { aspect-ratio: 4 / 5; } }\n"
          "@media (max-width: 1023px) { .form-card { margin-top: var(--s-7); grid-column: 1 / -1 !important; } }\n@media (min-width: 640px) and (max-width: 1023px) { #booking { --h: 2; } #steps { height: calc(2 * 100svh) !important; } }\n@media (max-width: 639px) { #steps { height: calc(2 * 100lvh) !important; } }"),
     "contatti", DEAL + "\n     moments: pinned-chapters in #steps", cont_body,
     footer("Passa a trovarci in Via Coazze", HOURS + ".",
            u("1560518883", "Una piccola casa di legno con un mazzo di chiavi su un tavolo", sizes="(max-width: 639px) 160vw, 100vw", cls="")))

print("ok")
