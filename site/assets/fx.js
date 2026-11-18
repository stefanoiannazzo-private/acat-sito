/*
  ACAT Servizi - effetti di scorrimento ispirati al template Adon, riscritti da zero con GSAP + ScrollTrigger.
  1. titoli che entrano inclinati e si raddrizzano parola per parola
  2. foto che si scoprono con una maschera rotonda dall'angolo in basso a sinistra
  3. parole giganti che scorrono in orizzontale in direzioni opposte
  4. transizione tra le pagine: blu, "ACAT" lettera per lettera, onda rossa
  5. carte a ventaglio che si aprono da una card centrale (sezione fissata)
  6. cerchio con lo scudo che si allarga fino a coprire lo schermo
  7. fascia di testo enorme in scorrimento continuo
  Con "riduci movimento" attivo resta tutto fermo e leggibile.
*/
(function () {
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const html = document.documentElement;
  const store = {
    get(k) { try { return sessionStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { sessionStorage.setItem(k, v); } catch { /* niente */ } },
    del(k) { try { sessionStorage.removeItem(k); } catch { /* niente */ } },
  };

  /* ---------------------------------------------------------- 4. transizione tra pagine */
  function transition() {
    const tr = document.createElement('div');
    tr.className = 'tr';
    tr.setAttribute('aria-hidden', 'true');
    tr.innerHTML = '<div class="tr__veil"></div><div class="tr__word">' +
      'ACAT'.split('').map(c => `<span>${c}</span>`).join('') +
      '</div><div class="tr__wave"></div>';
    document.body.append(tr);
    const veil = tr.querySelector('.tr__veil'), word = tr.querySelector('.tr__word'),
          letters = [...word.children], wave = tr.querySelector('.tr__wave');
    const g = window.gsap;

    // arrivo da un'altra pagina del sito: l'onda rossa copre tutto e sale scoprendo la pagina
    if (store.get('acat-tr') === '1' && g && !reduced) {
      store.del('acat-tr');
      tr.classList.add('is-on');
      g.set(wave, { yPercent: 0, borderRadius: '0 0 50% 50% / 0 0 14% 14%' });
      g.to(wave, { yPercent: -112, duration: 0.95, ease: 'power3.inOut', delay: 0.1,
        onComplete: () => tr.classList.remove('is-on') });
    }

    document.addEventListener('click', e => {
      const a = e.target.closest('a[href]');
      if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0 || a.target === '_blank') return;
      const url = new URL(a.getAttribute('href'), location.href);
      if (url.origin !== location.origin || !/\.html$/.test(url.pathname)) return;
      if (url.pathname === location.pathname) return;   // stessa pagina (#ancora): scorre e basta
      if (reduced || !g) return;
      e.preventDefault();
      tr.classList.add('is-on');
      g.set(veil, { opacity: 0 });
      g.set(letters, { opacity: 0, y: 24 });
      g.set(word, { opacity: 1 });
      g.set(wave, { yPercent: 112, borderRadius: '50% 50% 0 0 / 14% 14% 0 0' });
      g.timeline({ onComplete: () => { store.set('acat-tr', '1'); location.href = url.href; } })
        .to(veil, { opacity: 1, duration: 0.35, ease: 'power2.out' })
        .to(letters, { opacity: 1, y: 0, duration: 0.45, stagger: 0.07, ease: 'power3.out' }, '-=0.1')
        .to(word, { opacity: 0.25, duration: 0.25 }, '+=0.15')
        .to(wave, { yPercent: 0, borderRadius: '0% 0% 0 0 / 0% 0% 0 0', duration: 0.75, ease: 'power3.inOut' }, '-=0.1');
    });
    // tornando indietro col browser la pagina non resta coperta
    addEventListener('pageshow', ev => { if (ev.persisted) tr.classList.remove('is-on'); });
  }

  /* ---------------------------------------------------------- 1. titoli che si raddrizzano */
  function splitWords(el) {
    if (el.dataset.fxSplit) return [];
    el.dataset.fxSplit = '1';
    if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
    const out = [];
    const walk = node => [...node.childNodes].forEach(n => {
      if (n.nodeType === 1) return walk(n);
      if (n.nodeType !== 3) return;
      const frag = document.createDocumentFragment();
      n.textContent.split(/(\s+)/).forEach(part => {
        if (!part) return;
        if (/^\s+$/.test(part)) { frag.append(' '); return; }
        const w = document.createElement('span');
        w.className = 'fx-w'; w.setAttribute('aria-hidden', 'true');
        const i = document.createElement('span');
        i.className = 'fx-w__in'; i.textContent = part;
        w.append(i); frag.append(w); out.push(i);
      });
      n.replaceWith(frag);
    });
    walk(el);
    return out;
  }
  function titles(g) {
    document.querySelectorAll('main h2, footer.closing h2, .fx-dark__lead').forEach(h => {
      if (h.closest('.hero')) return;
      const words = splitWords(h);
      if (!words.length) return;
      g.from(words, {
        opacity: 0, xPercent: 35, skewX: -22, filter: 'blur(6px)',
        duration: 1.1, ease: 'power3.out', stagger: 0.045,
        scrollTrigger: { trigger: h, start: 'top 88%', once: true },
      });
    });
  }

  /* ---------------------------------------------------------- 2. foto con maschera rotonda */
  function masks(g) {
    document.querySelectorAll('main .frame--photo, footer .close__media, .fx-reveal').forEach(el => {
      g.fromTo(el, { clipPath: 'circle(0% at 0% 100%)' }, {
        clipPath: 'circle(150% at 0% 100%)', ease: 'none',
        scrollTrigger: { trigger: el, start: 'top 95%', end: 'top 30%', scrub: 0.6 },
      });
      const img = el.querySelector('img');
      if (img) g.fromTo(img, { scale: 1.18 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: el, start: 'top 95%', end: 'bottom 40%', scrub: 0.6 } });
    });
  }

  /* ---------------------------------------------------------- 3. parole giganti */
  function mega(g) {
    document.querySelectorAll('.fx-mega').forEach(sec => {
      const a = sec.querySelector('.fx-mega__row--a'), b = sec.querySelector('.fx-mega__row--b');
      const st = { trigger: sec, start: 'top bottom', end: 'bottom top', scrub: 0.5 };
      if (a) g.fromTo(a, { xPercent: 8 }, { xPercent: -22, ease: 'none', scrollTrigger: st });
      if (b) g.fromTo(b, { xPercent: -18 }, { xPercent: 12, ease: 'none', scrollTrigger: { ...st } });
    });
  }

  /* ---------------------------------------------------------- 5. carte a ventaglio */
  function fan(g, mm) {
    document.querySelectorAll('.fx-fan').forEach(sec => {
      const stage = sec.querySelector('.fx-fan__stage');
      const phone = sec.querySelector('.fx-fan__phone');
      const cards = [...sec.querySelectorAll('.fx-fan__card')];
      if (!stage || !cards.length) return;
      mm.add('(min-width: 900px)', () => {
        sec.classList.add('is-live');
        const n = cards.length;
        const tl = g.timeline({ scrollTrigger: { trigger: sec, start: 'top top', end: '+=140%', pin: true, scrub: 0.7, anticipatePin: 1 } });
        cards.forEach((c, i) => {
          tl.fromTo(c, { x: 0, y: 0, rotate: (i - (n - 1) / 2) * 3, scale: 0.86 },
            { x: () => (i - (n - 1) / 2) * (c.offsetWidth + 16), y: 0, rotate: 0, scale: 1, ease: 'power2.inOut', duration: 1 }, 0);
        });
        if (phone) tl.fromTo(phone, { opacity: 1, scale: 1 }, { opacity: 0, scale: 0.92, duration: 0.6, ease: 'power1.in' }, 0.45);
        return () => sec.classList.remove('is-live');
      });
    });
  }

  /* ---------------------------------------------------------- 6. cerchio che riempie lo schermo */
  function circle(g, mm) {
    document.querySelectorAll('.fx-circle').forEach(sec => {
      const dot = sec.querySelector('.fx-circle__dot'), ic = sec.querySelector('.fx-circle__ic');
      if (!dot) return;
      mm.add('(min-width: 640px)', () => {
        sec.classList.add('is-live');
        const cover = () => Math.hypot(innerWidth, innerHeight) / dot.offsetWidth * 1.08;
        g.timeline({ scrollTrigger: { trigger: sec, start: 'top top', end: '+=80%', pin: true, scrub: 0.6, invalidateOnRefresh: true } })
          .fromTo(dot, { scale: 1 }, { scale: cover, ease: 'power2.in', duration: 1 }, 0)
          .fromTo(ic, { scale: 1, opacity: 1 }, { scale: 6, opacity: 0, ease: 'power1.in', duration: 0.55 }, 0.05);
        return () => sec.classList.remove('is-live');
      });
    });
  }

  /* ---------------------------------------------------------- 7. fascia in scorrimento */
  function marquee(g) {
    document.querySelectorAll('.fx-marquee__track').forEach(track => {
      const tw = g.to(track, { xPercent: -50, duration: 28, ease: 'none', repeat: -1 });
      // scorrendo la pagina la fascia accelera un poco, poi torna alla sua velocità
      ScrollTrigger.create({ trigger: track, start: 'top bottom', end: 'bottom top',
        onUpdate: s => { g.to(tw, { timeScale: 1 + Math.min(4, Math.abs(s.getVelocity()) / 400), duration: 0.2, overwrite: true });
                         g.to(tw, { timeScale: 1, duration: 0.8, delay: 0.2 }); } });
    });
  }

  function start() {
    transition();
    const g = window.gsap;
    if (!g || !window.ScrollTrigger || reduced) { html.classList.add('fx-still'); return; }
    g.registerPlugin(ScrollTrigger);
    html.classList.add('fx-on');
    const mm = g.matchMedia();
    titles(g); masks(g); mega(g); fan(g, mm); circle(g, mm); marquee(g);
    // le foto e i font cambiano le altezze: ScrollTrigger rimisura quando sono pronti
    // (anche i "momenti" del kit cambiano l'altezza delle loro sezioni quando partono)
    let t = 0, lastH = 0;
    const refresh = () => { clearTimeout(t); t = setTimeout(() => ScrollTrigger.refresh(), 150); };
    addEventListener('load', refresh);
    document.fonts?.ready.then(refresh);
    new ResizeObserver(() => { const h = document.body.scrollHeight; if (Math.abs(h - lastH) > 40) { lastH = h; refresh(); } }).observe(document.body);
    new MutationObserver(refresh).observe(html, { attributes: true, attributeFilter: ['data-moments-ready'] });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
