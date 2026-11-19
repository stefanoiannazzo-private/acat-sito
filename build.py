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
        <button class="nav__link nav__link--menu" type="button" aria-haspopup="true"{' data-current="page"' if current == 'servizi' else ''}>Servizi <svg class="chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <div class="nav__menu">
          <div class="wrap grid">
            <div class="menu-col span-3">
              <p class="t-label">Assicurazioni</p>
              <a href="assicurazione-auto-moto.html">{li_ic("car-front")}<b>Auto e moto</b><span>RC, furto, incendio, cristalli</span></a>
              <a href="assicurazione-casa-famiglia.html">{li_ic("house")}<b>Casa e famiglia</b><span>Abitazione e responsabilità civile</span></a>
              <a href="assicurazione-vita-impresa.html">{li_ic("heart-handshake")}<b>Vita e impresa</b><span>Risparmio, infortuni, attività</span></a>
              <a href="preventivo-rc-auto.html">{li_ic("calendar-check")}<b>Calcola la RC online</b><span>Stima in un minuto</span></a>
            </div>
            <div class="menu-col span-3">
              <p class="t-label">Pratiche auto</p>
              <a href="passaggio-di-proprieta.html">{li_ic("key-round")}<b>Passaggio di proprietà</b><span>Allo sportello STA in agenzia</span></a>
              <a href="immatricolazioni.html">{li_ic("file-plus-2")}<b>Immatricolazioni</b><span>Targhe e documento unico</span></a>
              <a href="patenti.html">{li_ic("id-card")}<b>Patenti</b><span>Convalida e rinnovo</span></a>
            </div>
            <a class="menu-card span-3 start-10" href="servizi.html">
              <div class="panel">{u("1761014586555", "Due mani si passano le chiavi di un'auto", sizes="300px", lazy=False, cls="frame__img").replace(' fetchpriority="high"', '')}</div>
              <span class="t-card">Tutti i servizi</span>
              <span class="t-small muted">Assicurazioni e sportello STA in un solo ufficio</span>
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
        <span>&copy; 2026 ACAT SERVIZI SRLS · Via Coazze 35, 10094 Giaveno (TO) · P. IVA 12789310013</span>
        <nav class="footer__legal-links" aria-label="Note legali"><a href="privacy.html">Privacy</a><a href="cookie.html">Cookie</a><a class="credits" href="credits.html">Crediti foto</a></nav>
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
        <form class="form" action="preventivo.php" method="post" data-endpoint="/preventivo.php" data-sent="La tua richiesta di preventivo è arrivata. Ti rispondiamo negli orari d'agenzia." data-sending="Invio in corso…" data-failed="Invio non riuscito: i dati sono ancora qui. Riprova oppure chiamaci al 329 754 2063." data-fix="Controlla {{n}} campi evidenziati." novalidate>
          <div class="hp" aria-hidden="true"><label for="f-web">Lascia vuoto questo campo</label><input id="f-web" name="sito_web" type="text" tabindex="-1" autocomplete="off"></div>
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
          <p class="t-small muted">Ti rispondiamo negli orari d'agenzia. Usiamo i tuoi dati solo per rispondere alla richiesta: leggi l'<a class="link" href="privacy.html">informativa privacy</a>.</p>
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


# ---------------------------------------------------------------- PAGINE LEGALI
TITOLARE = "ACAT SERVIZI SRLS"
SEDE = "Via Coazze 35, 10094 Giaveno (TO)"
PIVA = "12789310013"
AGG = "18 novembre 2026"


def legal_page(name, title, h1, lead, body_html, desc):
    body = hero(h1, lead,
                wk("f1549890", "Una via del centro di Giaveno con la torre antica e la fontana", lazy=False, pos="30% 50%"),
                '<a class="btn btn--secondary" href="index.html">Torna alla home</a>', float_card=False, kicker="Note legali")
    body = body.replace('class="hero hero--cover"', 'class="hero hero--cover hero--compact"')
    body += f'''
  <section class="section fx-sec legal" aria-label="{h1}">
    <div class="wrap"><div class="legal__body">
      <p class="legal__updated">Ultimo aggiornamento: {AGG}</p>
{body_html}
    </div></div>
  </section>
'''
    page(name, head(title, desc, "word-highlight"), "", DEAL, body,
         footer("Un preventivo senza impegno", HOURS + ", in Via Coazze.",
                u("1560518883", "Una piccola casa di legno con un mazzo di chiavi su un tavolo", sizes="(max-width: 639px) 160vw, 100vw", cls="")))


PRIVACY = f'''
      <h2>1. Chi tratta i tuoi dati</h2>
      <p>Il titolare del trattamento è <strong>{TITOLARE}</strong>, con sede in {SEDE}, P. IVA {PIVA}. Puoi contattarci al telefono <a class="link" href="{TEL}">{PHONE}</a>, in agenzia negli orari di apertura o con il modulo della pagina <a class="link" href="contatti.html#booking">Contatti</a>.</p>

      <h2>2. Quali dati raccogliamo</h2>
      <p><strong>Dati di navigazione.</strong> Il server che ospita il sito registra in automatico alcune informazioni tecniche (indirizzo IP, data e ora della visita, pagina richiesta, tipo di browser). Servono a far funzionare il sito e a proteggerlo da abusi e non vengono usate per identificarti.</p>
      <p><strong>Dati che ci invii.</strong> Se compili il modulo "Richiedi un preventivo" raccogliamo nome e cognome, indirizzo email, la polizza o la pratica che ti interessa e, se lo indichi, il comune di residenza. Ti chiediamo di non inserire nel modulo dati sanitari o altri dati particolari.</p>
      <p><strong>Preventivatore RC auto e moto.</strong> Per calcolare la stima usiamo il comune di residenza, la data di nascita del contraente, il veicolo e le garanzie scelte. Finché premi solo "Calcola la stima" questi dati servono al calcolo e non vengono conservati. Se chiedi il preventivo definitivo registriamo anche nome, email, telefono (facoltativo) e le eventuali note, insieme alla stima calcolata, e creiamo una scheda cliente per ricontattarti e inviarti via email il preventivo definitivo. Il link nell'email apre una pagina personale da cui puoi accettarlo.</p>
      <p><strong>Clienti.</strong> Per i clienti che stipulano una polizza tramite l'agenzia registriamo nella scheda le polizze (compagnia, prodotto, numero, premio e scadenze) e conserviamo copia dei documenti contrattuali che ci consegni. Usiamo questi dati per gestire il rapporto, ricordarti le scadenze (anche via email, quando previsto per la tua polizza) e adempiere agli obblighi di legge degli intermediari assicurativi. I documenti sono conservati in un'area riservata accessibile solo al personale dell'agenzia.</p>

      <h2>3. Perché li usiamo e su quale base</h2>
      <ul>
        <li>Rispondere alla tua richiesta e prepararti un preventivo: misure precontrattuali richieste da te (art. 6, par. 1, lett. b del Regolamento UE 2016/679, "GDPR").</li>
        <li>Garantire la sicurezza del sito e prevenire abusi: legittimo interesse del titolare (art. 6, par. 1, lett. f GDPR).</li>
        <li>Adempiere a obblighi di legge, se necessario (art. 6, par. 1, lett. c GDPR).</li>
      </ul>
      <p>Non usiamo i dati del sito per pubblicità, profilazione o decisioni automatizzate e non li vendiamo a nessuno. Se dalla richiesta nasce un contratto assicurativo o una pratica, riceverai in agenzia l'informativa specifica prevista per quel servizio.</p>

      <h2>4. Per quanto tempo li conserviamo</h2>
      <p>Conserviamo le richieste inviate dal modulo e dal preventivatore per il tempo necessario a gestirle e comunque non oltre 12 mesi dall'ultimo contatto; se diventi nostro cliente, i dati della scheda sono conservati per la durata del rapporto e per i tempi previsti dagli obblighi di legge, salvo che nasca un rapporto contrattuale o che la legge imponga tempi diversi. I dati di navigazione sono conservati dal fornitore di hosting per i tempi tecnici previsti dal servizio.</p>

      <h2>5. Chi può vederli</h2>
      <p>I dati sono trattati dal personale dell'agenzia autorizzato e dai fornitori che ci aiutano a far funzionare il sito e la posta elettronica, come il fornitore di hosting, nominati responsabili del trattamento. Le compagnie assicurative ricevono i tuoi dati solo se serve per prepararti un preventivo o un contratto che hai chiesto. I dati restano nell'Unione Europea.</p>

      <h2>6. Servizi esterni collegati</h2>
      <p>Il sito contiene semplici link alla prenotazione online (Reservio) e alla mappa (OpenStreetMap). Questi servizi si attivano solo se fai clic e trattano i dati secondo le loro informative.</p>

      <h2>7. I tuoi diritti</h2>
      <p>Puoi chiedere in ogni momento di accedere ai tuoi dati, correggerli, cancellarli, limitarne il trattamento, opporti al trattamento basato sul legittimo interesse e ricevere i dati in un formato leggibile (artt. 15–22 GDPR). Per farlo contattaci con i recapiti indicati al punto 1. Se ritieni che il trattamento violi il GDPR puoi presentare un reclamo al Garante per la protezione dei dati personali (<a class="link" href="https://www.garanteprivacy.it">garanteprivacy.it</a>).</p>

      <h2>8. È obbligatorio darci i dati?</h2>
      <p>No, ma senza nome, email e oggetto della richiesta non possiamo risponderti tramite il modulo. In alternativa puoi chiamarci o passare in agenzia.</p>

      <h2>9. Modifiche</h2>
      <p>Possiamo aggiornare questa informativa. La data dell'ultima modifica è indicata in cima alla pagina.</p>
'''

COOKIE = f'''
      <h2>1. Cosa sono i cookie</h2>
      <p>I cookie sono piccoli file che un sito salva nel browser. Strumenti simili, come la memoria di sessione del browser ("sessionStorage"), funzionano in modo analogo.</p>

      <h2>2. Cosa usa questo sito</h2>
      <p>Questo sito <strong>non usa cookie di profilazione, di statistica o di terze parti</strong>. Font, animazioni e immagini sono ospitati sul sito stesso: aprendo le pagine non vengono contattati servizi esterni.</p>
      <p>Il sito usa soltanto uno strumento tecnico:</p>
      <ul>
        <li><strong>acat-tr</strong> (sessionStorage): ricorda per un istante che stai passando da una pagina all'altra, così da mostrare l'animazione di passaggio. Viene cancellato subito dopo e comunque alla chiusura della scheda. Non contiene dati personali.</li>
        <li><strong>acat_admin</strong> (cookie tecnico di sessione): usato solo nell'area riservata al personale dell'agenzia per mantenere l'accesso. Scade alla chiusura del browser. I visitatori del sito non lo ricevono.</li>
      </ul>
      <p>Il server di hosting può impostare cookie tecnici necessari al funzionamento e alla sicurezza del servizio.</p>

      <h2>3. Serve il consenso?</h2>
      <p>No. Per gli strumenti tecnici strettamente necessari la legge non richiede il consenso (art. 122 del Codice privacy e Linee guida del Garante del 10 giugno 2021), per questo il sito non mostra un banner. Se in futuro aggiungeremo strumenti di statistica o di marketing, ti chiederemo prima il consenso e aggiorneremo questa pagina.</p>

      <h2>4. Link esterni</h2>
      <p>I link alla prenotazione online (Reservio) e alla mappa (OpenStreetMap) aprono siti di terzi, che usano i propri cookie secondo le loro informative.</p>

      <h2>5. Come gestire i cookie dal browser</h2>
      <p>Puoi cancellare o bloccare cookie e dati dei siti dalle impostazioni del tuo browser. Bloccare gli strumenti tecnici di questo sito non ne impedisce l'uso: si perde solo l'animazione di passaggio tra le pagine.</p>

      <h2>6. Titolare</h2>
      <p>{TITOLARE}, {SEDE}, P. IVA {PIVA}. Per i dettagli sul trattamento dei dati leggi l'<a class="link" href="privacy.html">informativa privacy</a>.</p>
'''

legal_page("privacy.html", "Privacy policy: ACAT Servizi, Giaveno", "Privacy policy",
           "Come ACAT SERVIZI SRLS tratta i dati di chi visita il sito e ci scrive dal modulo dei preventivi.",
           PRIVACY, "Informativa privacy del sito di ACAT SERVIZI SRLS, agenzia di assicurazioni e pratiche auto in Via Coazze 35 a Giaveno.")
legal_page("cookie.html", "Cookie policy: ACAT Servizi, Giaveno", "Cookie policy",
           "Questo sito usa solo strumenti tecnici: niente profilazione, niente statistiche, niente banner.",
           COOKIE, "Cookie policy del sito di ACAT SERVIZI SRLS: solo strumenti tecnici, nessun cookie di profilazione o di terze parti.")

print("ok")


# ---------------------------------------------------------------- PAGINE DEI SINGOLI SERVIZI
SVC = [
  dict(slug="assicurazione-auto-moto", group="Assicurazioni", icon="car-front", name="Auto e moto", sub="RC, furto, incendio, cristalli",
       title="Assicurazione auto e moto a Giaveno | ACAT Servizi",
       desc="RC auto e moto e garanzie accessorie a Giaveno: confrontiamo più compagnie per auto, moto, ciclomotori e veicoli commerciali.",
       h1="Assicurazione auto e moto",
       lead="RC obbligatoria e garanzie accessorie per auto, moto, ciclomotori e veicoli commerciali. Confrontiamo più compagnie e ti spieghiamo cosa copre ogni proposta.",
       hero=("1686170017528", "Un motociclista percorre una strada di montagna tra le rocce", "50% 60%"),
       side=("1687856265836", "Un'auto su una strada di campagna con le colline sullo sfondo", "50% 50%"),
       h2="Le coperture per il tuo veicolo",
       intro="Partiamo dall'RC, obbligatoria per circolare, e aggiungiamo solo le garanzie che ti servono davvero.",
       items=[("shield-check", "Responsabilità civile (RC)", "Obbligatoria: copre i danni causati a persone, animali e cose"),
              ("lock-keyhole", "Furto e rapina", "Il veicolo rubato, in tutto o in parte"),
              ("flame", "Incendio", "Danni da incendio, esplosione e fulmine"),
              ("glass-water", "Cristalli", "Parabrezza, lunotto e vetri laterali"),
              ("cloud-lightning", "Eventi naturali e atti vandalici", "Grandine, alluvioni, danni da terzi non identificati"),
              ("car", "Kasko e collisione", "I danni al tuo veicolo anche quando la colpa è tua"),
              ("life-buoy", "Assistenza stradale e tutela legale", "Soccorso, auto sostitutiva, spese legali")],
       note="Garanzie, franchigie e massimali dipendono dalla compagnia e dal contratto scelto: te li mostriamo prima della firma.",
       steps_h="Come funziona",
       steps=[("message-circle", "Ci racconti veicolo e conducente", "Marca, modello, uso, chi guida e la tua classe di merito."),
              ("search", "Confrontiamo le compagnie", "Mettiamo a confronto le proposte di più compagnie partner."),
              ("pen-line", "Scegli e firmi in agenzia", "Ti spieghiamo ogni voce e firmi solo quando è tutto chiaro."),
              ("refresh-cw", "Ti seguiamo dopo", "Rinnovi, variazioni, cambio veicolo e denuncia dei sinistri.")],
       docs_h="Cosa portare",
       docs=["Documento unico o carta di circolazione", "Patente e codice fiscale di chi guida", "Il contratto o l'ultima quietanza della polizza attuale"]),
  dict(slug="assicurazione-casa-famiglia", group="Assicurazioni", icon="house", name="Casa e famiglia", sub="Abitazione e responsabilità civile",
       title="Assicurazione casa e famiglia a Giaveno | ACAT Servizi",
       desc="Polizze per la casa e la famiglia a Giaveno: incendio, furto, responsabilità civile, infortuni e salute con più compagnie.",
       h1="Assicurazione casa e famiglia",
       lead="La casa in cui vivi, le cose che contiene e le persone a cui tieni. Costruiamo la polizza intorno alla tua famiglia, confrontando più compagnie.",
       hero=("1570129477492", "Una casa bianca con portico e prato all'inglese", "50% 60%"),
       side=("1543342384", "Due genitori tengono in braccio il loro neonato vicino alla finestra", "50% 40%"),
       h2="Cosa possiamo proteggere",
       intro="Le garanzie si combinano: scegli quelle che servono alla tua casa e alla tua famiglia.",
       items=[("flame", "Incendio e danni all'abitazione", "Il fabbricato e il contenuto, anche in affitto"),
              ("lock-keyhole", "Furto in casa", "Beni rubati e danni causati dai ladri"),
              ("droplets", "Acqua condotta ed eventi atmosferici", "Perdite d'acqua, grandine e vento, secondo la polizza"),
              ("users", "Responsabilità civile della famiglia", "I danni che tu o i tuoi familiari causate ad altri"),
              ("paw-print", "Animali domestici", "I danni causati a terzi dal tuo cane o gatto"),
              ("hand-heart", "Infortuni e salute", "Coperture per te e per i tuoi familiari")],
       note="Ogni compagnia definisce garanzie ed esclusioni a modo suo: le confrontiamo insieme prima di scegliere.",
       steps_h="Come funziona",
       steps=[("message-circle", "Ci parli della casa", "Proprietà o affitto, metratura, anno di costruzione, chi ci vive."),
              ("search", "Confrontiamo le compagnie", "Scegliamo le garanzie e mettiamo a confronto più proposte."),
              ("pen-line", "Firmi in agenzia", "Ti spieghiamo massimali, franchigie ed esclusioni."),
              ("refresh-cw", "Ti seguiamo dopo", "Variazioni, rinnovi e assistenza in caso di sinistro.")],
       docs_h="Utile avere con te",
       docs=["Indirizzo e dati dell'abitazione (metratura, piano, anno)", "Eventuale polizza in corso", "Documento d'identità e codice fiscale"]),
  dict(slug="assicurazione-vita-impresa", group="Assicurazioni", icon="heart-handshake", name="Vita e impresa", sub="Risparmio, infortuni, attività",
       title="Assicurazioni vita, risparmio e impresa a Giaveno | ACAT Servizi",
       desc="Polizze vita, risparmio, infortuni e coperture per negozi, artigiani, aziende e professionisti a Giaveno, con più compagnie.",
       h1="Vita, risparmio e impresa",
       lead="Proteggere chi dipende da te, mettere da parte qualcosa per il futuro, assicurare il tuo lavoro. Ne parliamo con calma, in agenzia.",
       hero=("1758520144437", "Una consulente ascolta un cliente seduta alla scrivania", "50% 40%"),
       side=("1758518731462", "Due clienti firmano documenti con una consulente", "50% 50%"),
       h2="Per te e per la tua attività",
       intro="Coperture per la persona e per chi lavora in proprio, scelte tra più compagnie.",
       items=[("heart-pulse", "Vita e caso morte", "Un capitale per la famiglia se ti succede qualcosa"),
              ("piggy-bank", "Risparmio e previdenza", "Piani di accumulo e integrazione della pensione"),
              ("hand-heart", "Infortuni", "Coperture per infortuni sul lavoro e nel tempo libero"),
              ("store", "Negozi e artigiani", "Locali, merci, attrezzature e responsabilità verso i clienti"),
              ("briefcase-business", "Professionisti", "Responsabilità civile professionale"),
              ("truck", "Aziende e veicoli aziendali", "Sede, dipendenti e mezzi di lavoro")],
       note="Per le polizze vita e di risparmio ricevi prima della firma la documentazione precontrattuale prevista dalla legge.",
       steps_h="Come funziona",
       steps=[("calendar-clock", "Fissiamo un appuntamento", "In agenzia o al telefono, quando ti è comodo."),
              ("message-circle", "Analizziamo le tue esigenze", "Famiglia, lavoro, obiettivi e cosa hai già."),
              ("search", "Ti proponiamo le soluzioni", "Confrontiamo più compagnie e te le spieghiamo."),
              ("refresh-cw", "Le rivediamo nel tempo", "Quando cambia la tua vita o la tua attività.")],
       docs_h="Utile avere con te",
       docs=["Documento d'identità e codice fiscale", "Le polizze che hai già", "Per l'impresa: visura camerale e dati dell'attività"]),
  dict(slug="passaggio-di-proprieta", group="Pratiche auto", icon="key-round", name="Passaggio di proprietà", sub="Allo sportello STA in agenzia",
       title="Passaggio di proprietà auto e moto a Giaveno | ACAT Servizi",
       desc="Passaggio di proprietà di auto e moto usate a Giaveno allo sportello STA: autentica della firma e documento unico in tempo reale.",
       h1="Passaggio di proprietà auto e moto",
       lead="Compri o vendi un'auto o una moto usata? Allo sportello STA in agenzia autentichiamo la firma e registriamo il passaggio, con il nuovo documento unico in tempo reale.",
       hero=("1653565217811", "Una mano tiene la chiave di un'auto davanti alla carrozzeria", "50% 50%"),
       side=("1450101499163", "Una mano firma un contratto su una scrivania", "50% 50%"),
       h2="Cosa facciamo per te",
       intro="Seguiamo tutta la pratica, tra privati o da un rivenditore, nello stesso ufficio.",
       items=[("pen-line", "Autentica della firma del venditore", "Sull'atto di vendita, direttamente in agenzia"),
              ("receipt", "Calcolo di imposte e diritti", "IPT e diritti dipendono dal veicolo e dalla provincia"),
              ("file-text", "Registrazione tramite STA", "Aggiornamento di PRA e Motorizzazione"),
              ("badge-check", "Nuovo documento unico", "Intestato al nuovo proprietario, rilasciato in tempo reale"),
              ("shield-check", "Assicurazione del veicolo", "Se vuoi, prepariamo anche la nuova polizza")],
       note="La legge prevede che il passaggio sia registrato entro 60 giorni dall'autentica della firma.",
       steps_h="Come funziona",
       steps=[("calendar-clock", "Fissi un appuntamento", "Venditore e acquirente, oppure chi ha l'atto già firmato."),
              ("pen-line", "Firma e autentica", "Il venditore firma l'atto di vendita davanti a noi."),
              ("receipt", "Paghi imposte e diritti", "Ti diciamo prima l'importo esatto per il tuo veicolo."),
              ("badge-check", "Esci con il documento", "Il nuovo documento unico è pronto in agenzia.")],
       docs_h="Cosa portare",
       docs=["Documento unico, oppure carta di circolazione e certificato di proprietà", "Documento d'identità e codice fiscale di venditore e acquirente", "Per società e ditte: i dati dell'azienda e del legale rappresentante"]),
  dict(slug="immatricolazioni", group="Pratiche auto", icon="file-plus-2", name="Immatricolazioni", sub="Targhe e documento unico",
       title="Immatricolazioni, targhe e duplicati a Giaveno | ACAT Servizi",
       desc="Immatricolazioni di veicoli nuovi ed esteri, targhe, duplicati e radiazioni allo sportello STA di ACAT Servizi a Giaveno.",
       h1="Immatricolazioni, targhe e duplicati",
       lead="Prime iscrizioni, veicoli provenienti dall'estero, targhe da sostituire e documenti persi: le pratiche del tuo veicolo allo sportello STA, nello stesso ufficio.",
       hero=("1603881359318", "Un'auto percorre una strada di montagna tra gli abeti", "50% 55%"),
       side=("1761738217531", "Un'auto bianca nuova in un salone espositivo", "50% 55%"),
       h2="Le pratiche che seguiamo",
       intro="Ogni pratica ha i suoi documenti: ti diciamo prima cosa serve per il tuo caso.",
       items=[("file-plus-2", "Prima immatricolazione", "Targhe e documento unico per un veicolo nuovo"),
              ("globe", "Veicoli dall'estero", "Reimmatricolazione in Italia di auto e moto importate"),
              ("refresh-cw", "Targhe", "Sostituzione di targhe deteriorate, smarrite o rubate"),
              ("copy", "Duplicati e aggiornamenti", "Documento unico perso o rubato, cambio di residenza"),
              ("recycle", "Radiazioni", "Cancellazione dal PRA per demolizione o esportazione")],
       note="Tempi e importi dipendono dal tipo di pratica e dal veicolo: te li comunichiamo prima di iniziare.",
       steps_h="Come funziona",
       steps=[("message-circle", "Ci dici di cosa hai bisogno", "Di persona o al telefono, anche per un primo consiglio."),
              ("clipboard-list", "Ti diamo l'elenco dei documenti", "Solo quelli che servono davvero per la tua pratica."),
              ("file-text", "Avviamo la pratica", "Tramite lo Sportello Telematico dell'Automobilista."),
              ("badge-check", "Ritiri targhe e documento", "In agenzia, appena la pratica è completata.")],
       docs_h="Di solito servono",
       docs=["Documento d'identità e codice fiscale dell'intestatario", "Documenti del veicolo (certificato di conformità o documenti esteri)", "In caso di furto o smarrimento: la denuncia"]),
  dict(slug="patenti", group="Pratiche auto", icon="id-card", name="Patenti", sub="Convalida e rinnovo",
       title="Rinnovo e duplicato della patente a Giaveno | ACAT Servizi",
       desc="Rinnovo (convalida) e duplicato della patente di guida a Giaveno: ti seguiamo nella pratica, dalla visita medica alla nuova patente.",
       h1="Rinnovo e duplicato della patente",
       lead="La patente sta per scadere o l'hai persa? Seguiamo la pratica dall'inizio alla fine, nello stesso ufficio in cui fai le polizze.",
       hero=("1595969578325", "Una mano sul volante di un'auto in viaggio", "50% 45%"),
       side=("1454165804606", "Mani su un portatile e su fogli di documenti", "50% 50%"),
       h2="Cosa facciamo per te",
       intro="Ti diciamo cosa serve, prepariamo i documenti e ti seguiamo fino alla nuova patente.",
       items=[("refresh-cw", "Rinnovo (convalida)", "Con la visita medica presso un medico abilitato"),
              ("copy", "Duplicato", "Per smarrimento, furto o deterioramento"),
              ("hourglass", "Patente scaduta", "Ti spieghiamo cosa fare per tornare a guidare"),
              ("stethoscope", "Visita medica", "Ti indichiamo come prenotarla e cosa portare")],
       note="Dopo la convalida la Motorizzazione stampa la nuova patente e la spedisce all'indirizzo di residenza.",
       steps_h="Ogni quanto si rinnova la patente B",
       table=[("Fino a 50 anni", "ogni 10 anni"), ("Da 50 a 70 anni", "ogni 5 anni"), ("Da 70 a 80 anni", "ogni 3 anni"), ("Oltre 80 anni", "ogni 2 anni")],
       steps=[],
       docs_h="Cosa portare",
       docs=["La patente (anche se scaduta) e un documento d'identità", "Codice fiscale", "Fototessere recenti", "Gli occhiali o le lenti, se li usi alla guida"]),
]


def svc_page(c):
    hp, ha, hpos = c["hero"]
    sp, sa, spos = c["side"]
    items = "\n".join(f'          <li>{li_ic(i)}<span class="rows__name">{n}<small>{d}</small></span></li>' for i, n, d in c["items"])
    if c.get("table"):
        rows = "".join(f"<tr><th scope=\"row\">{a}</th><td>{b}</td></tr>" for a, b in c["table"])
        main = f'''<table class="svc-table"><caption class="sr-only">{c["steps_h"]}</caption><tbody>{rows}</tbody></table>
        <p class="svc-note">Per le altre categorie e per chi ha patologie particolari la durata può essere diversa: te lo diciamo noi.</p>'''
    else:
        main = '<ol class="svc-steps">' + "".join(
            f'<li><span class="svc-steps__n">{k:02d}</span>{li_ic(i)}<h3>{t}</h3><p>{d}</p></li>' for k, (i, t, d) in enumerate(c["steps"], 1)) + "</ol>"
    docs = "".join(f"<li>{li_ic('clipboard-check')}<span>{d}</span></li>" for d in c["docs"])
    more = "".join(
        f'<a href="{o["slug"]}.html">{li_ic(o["icon"])}<b>{o["name"]}</b><span>{o["sub"]}</span></a>'
        for o in SVC if o["slug"] != c["slug"])
    body = hero(c["h1"], c["lead"],
                u(hp, ha, sizes="100vw", lazy=False, pos=hpos),
                (f'<a class="btn btn--secondary" href="preventivo-rc-auto.html">Calcola la stima online</a>' if c["slug"] == "assicurazione-auto-moto" else f'<a class="btn btn--secondary" href="{TEL}">Chiama il {PHONE}</a>'), float_card=False, kicker=c["group"])
    body += f'''
  <section class="section svc-sec" id="what" aria-labelledby="what-h">
    <div class="wrap grid grid--top">
      <div class="span-5">
        <div class="section-head">
          <h2 id="what-h">{c["h2"]}</h2>
          <p>{c["intro"]}</p>
        </div>
        <ul class="rows rows--ic">
{items}
        </ul>
        <p class="svc-note">{c["note"]}</p>
      </div>
      <div class="frame frame--photo frame--fill span-6 start-7 svc-photo">
        {u(sp, sa, sizes="(max-width: 1023px) 100vw, 640px", pos=spos)}
      </div>
    </div>
  </section>

  <section class="section svc-sec svc-sec--tint" id="how" aria-labelledby="how-h">
    <div class="wrap grid grid--top">
      <div class="span-7">
        <div class="section-head"><h2 id="how-h">{c["steps_h"]}</h2></div>
        {main}
      </div>
      <aside class="svc-docs span-4 start-9" aria-labelledby="docs-h">
        <h3 id="docs-h">{c["docs_h"]}</h3>
        <ul>{docs}</ul>
        <a class="btn btn--primary" href="{CTA_HREF}">{CTA}{ARROW}</a>
      </aside>
    </div>
  </section>

  <section class="section svc-sec" id="more" aria-labelledby="more-h">
    <div class="wrap">
      <div class="section-head section-head--split"><h2 id="more-h">Gli altri servizi</h2><a class="link" href="servizi.html">Tutti i servizi</a></div>
      <nav class="svc-more" aria-label="Altri servizi">{more}</nav>
    </div>
  </section>
'''
    page(c["slug"] + ".html", head(c["title"], c["desc"], "word-highlight"), "servizi", DEAL, body,
         footer("Un preventivo senza impegno", HOURS + ", in Via Coazze.",
                u("1733522688659", "Una strada a tornanti in una valle alpina in autunno", sizes="(max-width: 639px) 160vw, 100vw", cls="")))


for _c in SVC:
    svc_page(_c)


# ---------------------------------------------------------------- PREVENTIVATORE RC AUTO
pv_body = hero("Calcola la tua RC auto e moto",
               "Una stima in un minuto: comune di residenza, data di nascita e veicolo. Poi, se vuoi, ti richiamiamo con il preventivo definitivo confrontando più compagnie.",
               u("1687856265836", "Un'auto su una strada di campagna con le colline sullo sfondo", sizes="100vw", lazy=False, pos="50% 55%"),
               f'<a class="btn btn--secondary" href="{TEL}">Preferisci chiamare? {PHONE}</a>', float_card=False, kicker="Preventivo online")
pv_body = pv_body.replace(f'<a class="btn btn--primary" href="{CTA_HREF}">{CTA}{ARROW}</a>', f'<a class="btn btn--primary" href="#calcolo">Inizia la stima{ARROW}</a>', 1)
pv_body += f'''
  <section class="section svc-sec pv-sec" id="calcolo" aria-labelledby="pv-h">
    <div class="wrap grid grid--top">
      <div class="span-7">
        <div class="section-head"><h2 id="pv-h">La tua stima</h2><p>Tutti i campi servono per il calcolo. Nessun dato viene salvato finché non chiedi di essere ricontattato.</p></div>
        <p class="pv-off" hidden role="status"></p>
        <form class="pv" id="pv" novalidate>
          <fieldset class="pv-step">
            <legend><span class="pv-step__n">1</span>Chi è il contraente</legend>
            <div class="pv-field pv-combo">
              <label for="pv-comune">Comune di residenza</label>
              <input id="pv-comune" type="text" autocomplete="off" placeholder="Scrivi almeno 2 lettere, es. Giaveno" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pv-comuni" required>
              <ul id="pv-comuni" role="listbox" hidden></ul>
              <input type="hidden" name="comune_id" id="pv-comune-id">
            </div>
            <div class="pv-field">
              <label for="pv-nascita">Data di nascita</label>
              <input id="pv-nascita" name="nascita" type="date" required min="1910-01-01">
            </div>
          </fieldset>
          <fieldset class="pv-step">
            <legend><span class="pv-step__n">2</span>Il veicolo</legend>
            <div class="pv-toggle" role="radiogroup" aria-label="Tipo di veicolo">
              <label><input type="radio" name="tipo" value="auto" checked>{IC("car-front", "pv-ic")} Auto</label>
              <label><input type="radio" name="tipo" value="moto">{IC("bike", "pv-ic")} Moto e scooter</label>
            </div>
            <div class="pv-row">
              <div class="pv-field"><label for="pv-marca">Marca</label><select id="pv-marca" required><option value="">Caricamento…</option></select></div>
              <div class="pv-field"><label for="pv-modello">Modello</label><select id="pv-modello" required disabled><option value="">Scegli prima la marca</option></select></div>
            </div>
          </fieldset>
          <fieldset class="pv-step">
            <legend><span class="pv-step__n">3</span>Le garanzie</legend>
            <p class="pv-hint">La responsabilità civile è obbligatoria ed è sempre inclusa. Aggiungi le garanzie che ti interessano.</p>
            <div class="pv-garanzie" id="pv-garanzie"></div>
          </fieldset>
          <fieldset class="pv-step pv-contatti" id="pv-contatti" hidden>
            <legend><span class="pv-step__n">4</span>I tuoi contatti</legend>
            <p class="pv-hint" id="pv-contatti-hint">Ti mandiamo il preventivo definitivo, confrontando più compagnie.</p>
            <div class="pv-row">
              <div class="pv-field"><label for="pv-nome">Nome e cognome</label><input id="pv-nome" autocomplete="name"></div>
              <div class="pv-field"><label for="pv-email">Email</label><input id="pv-email" type="email" autocomplete="email"></div>
            </div>
            <div class="pv-row">
              <div class="pv-field"><label for="pv-tel">Telefono <span class="pv-opt">(facoltativo)</span></label><input id="pv-tel" type="tel" autocomplete="tel"></div>
              <div class="pv-field"><label for="pv-note">Note <span class="pv-opt">(facoltative)</span></label><input id="pv-note" placeholder="Es. classe di merito, scadenza attuale"></div>
            </div>
            <div class="hp" aria-hidden="true"><label>Sito web <input id="pv-sito" tabindex="-1" autocomplete="off"></label></div>
            <label class="pv-check"><input type="checkbox" id="pv-consenso"> Ho letto l'<a class="link" href="privacy.html" target="_blank">informativa privacy</a> e acconsento al trattamento dei miei dati per ricevere il preventivo.</label>
          </fieldset>
          <p class="pv-err" id="pv-err" role="alert" hidden></p>
          <button class="btn btn--primary pv-submit" type="submit" id="pv-submit">Calcola la stima{ARROW}</button>
        </form>
      </div>
      <aside class="pv-out span-4 start-9" id="pv-out" aria-live="polite">
        <div class="pv-out__empty">
          {li_ic("shield-check")}
          <h3>Il risultato compare qui</h3>
          <p>Compila i tre passaggi e premi "Calcola la stima".</p>
          <ul class="pv-out__points"><li>{li_ic("clock")}<span>Meno di un minuto</span></li><li>{li_ic("umbrella")}<span>Più compagnie a confronto in agenzia</span></li><li>{li_ic("badge-check")}<span>Nessun impegno</span></li></ul>
        </div>
        <div class="pv-out__res" hidden>
          <p class="pv-out__label">Stima annuale indicativa</p>
          <p class="pv-out__tot" id="pv-tot"></p>
          <p class="pv-out__meta" id="pv-meta"></p>
          <ul class="pv-out__rows" id="pv-righe"></ul>
          <p class="pv-out__note" id="pv-avviso"></p>
          <button class="btn btn--primary" type="button" id="pv-richiedi">Richiedi il preventivo definitivo{ARROW}</button>
        </div>
        <div class="pv-out__ok" hidden>
          {li_ic("badge-check")}
          <h3>Richiesta inviata</h3>
          <p id="pv-ok-txt">Ti ricontattiamo al più presto con il preventivo definitivo. Se hai fretta chiamaci al <a class="link" href="{TEL}">{PHONE}</a>.</p>
        </div>
      </aside>
    </div>
  </section>
'''
page("preventivo-rc-auto.html",
     head("Preventivo RC auto e moto online a Giaveno | ACAT Servizi",
          "Calcola una stima della tua RC auto o moto con ACAT Servizi a Giaveno: comune, età e veicolo. Poi il preventivo definitivo confrontando più compagnie.",
          "word-highlight"),
     "servizi", DEAL, pv_body,
     footer("Preferisci parlarne di persona?", HOURS + ", in Via Coazze.",
            u("1733522688659", "Una strada a tornanti in una valle alpina in autunno", sizes="(max-width: 639px) 160vw, 100vw", cls="")))
# lo script del preventivatore solo in questa pagina
_p = open("preventivo-rc-auto.html").read()
open("preventivo-rc-auto.html", "w").write(_p.replace('<script src="assets/fx.js"></script>', '<script src="assets/fx.js"></script>\n<script src="assets/preventivo.js" defer></script>', 1))
