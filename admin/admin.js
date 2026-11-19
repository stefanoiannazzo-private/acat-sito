/* Pannello: conferme prima di azioni distruttive, menu su telefono, ricerca comuni, modelli per marca */
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-conferma');
  if (msg && !window.confirm(msg)) e.preventDefault();
});
document.addEventListener('DOMContentLoaded', () => {
  const t = document.querySelector('.top__menu'), side = document.getElementById('side');
  t?.addEventListener('click', () => { const o = side.classList.toggle('is-open'); t.setAttribute('aria-expanded', String(o)); });

  const c = document.querySelector('[data-cerca-comuni]');
  if (c) {
    const hid = c.form.querySelector('[name="comune_id"]'), list = document.getElementById(c.getAttribute('list'));
    let map = {}, timer;
    c.addEventListener('input', () => {
      hid.value = map[c.value] || '';
      clearTimeout(timer);
      if (c.value.length < 2 || hid.value) return;
      timer = setTimeout(async () => {
        const r = await fetch('cerca.php?cosa=comuni&q=' + encodeURIComponent(c.value)).then((x) => x.json());
        map = {}; list.innerHTML = '';
        for (const x of r.r || []) { map[x.n] = x.id; const o = document.createElement('option'); o.value = x.n; list.append(o); }
        hid.value = map[c.value] || '';
      }, 200);
    });
  }
  const m = document.querySelector('[data-marca]'), mo = document.querySelector('[data-modello]');
  m?.addEventListener('change', async () => {
    mo.innerHTML = '<option value="">Scegli…</option>';
    if (!m.value) return;
    const r = await fetch('cerca.php?cosa=modelli&marca=' + m.value).then((x) => x.json());
    for (const x of r.r || []) { const o = document.createElement('option'); o.value = x.id; o.textContent = x.n; mo.append(o); }
  });
});

/* conferma sul singolo pulsante e invio automatico dei menu di stato */
document.addEventListener('click', (e) => {
  const b = e.target.closest('[data-conferma-btn]');
  if (b && !window.confirm(b.getAttribute('data-conferma-btn'))) e.preventDefault();
});
document.addEventListener('change', (e) => {
  if (e.target.matches('[data-auto-submit]')) e.target.form.submit();
});
document.documentElement.classList.add('js');

/* Polizze: prodotti della compagnia, scadenza proposta e date calcolate (stessa logica del server) */
document.addEventListener('DOMContentLoaded', () => {
  const comp = document.querySelector('[data-compagnia]'), prod = document.querySelector('[data-prodotto]');
  comp?.addEventListener('change', async () => {
    prod.innerHTML = '<option value="">–</option>';
    if (!comp.value) return;
    const r = await fetch('cerca.php?cosa=prodotti&compagnia=' + comp.value).then((x) => x.json());
    for (const x of r.r || []) { const o = document.createElement('option'); o.value = x.id; o.textContent = x.n; prod.append(o); }
  });

  const f = document.getElementById('polizza');
  if (f) {
    const MESI = { annuale: 12, semestrale: 6, trimestrale: 3, mensile: 1 };
    const sc = f.querySelector('[data-scadenza]'), st = f.querySelector('[data-stipula]'), fr = f.querySelector('[data-fraz]');
    const iso = (d) => d.toISOString().slice(0, 10);
    const parse = (s) => { const [y, m, g] = s.split('-').map(Number); return new Date(Date.UTC(y, m - 1, g)); };
    const addM = (d, n) => { const y = d.getUTCFullYear(), m = d.getUTCMonth() + n, g = d.getUTCDate(); const last = new Date(Date.UTC(y, m + 1, 0)).getUTCDate(); return new Date(Date.UTC(y, m, Math.min(g, last))); };
    const it = (s) => s ? s.split('-').reverse().join('/') : '–';
    const out = (k, v) => { f.querySelector(`[data-out="${k}"]`).textContent = v; };
    const calcola = () => {
      if (!sc.value) { ['prossima', 'agenzia', 'cliente'].forEach((k) => out(k, '–')); return; }
      const m = MESI[fr.value] || 12, fine = parse(sc.value), oggi = iso(new Date());
      let pros = null;
      for (let k = 12 / m - 1; k >= 0; k--) { const d = iso(addM(fine, -k * m)); if (d >= oggi) { pros = d; break; } }
      if (!pros) { out('prossima', 'già passata: da rinnovare'); out('agenzia', '–'); out('cliente', '–'); return; }
      const meno = (s, g) => { const d = parse(s); d.setUTCDate(d.getUTCDate() - g); return iso(d); };
      out('prossima', it(pros) + (pros === sc.value ? ' (annualità)' : ' (rata)'));
      out('agenzia', it(meno(pros, +f.dataset.giorniAgenzia)));
      const cli = f.querySelector('[name="avvisa_cliente"]').checked && f.dataset.clienteAttivo === '1';
      out('cliente', cli ? it(meno(pros, +f.dataset.giorniCliente)) : 'nessun avviso');
    };
    st.addEventListener('change', () => { if (st.value && !sc.value) { sc.value = iso(addM(parse(st.value), 12)); } calcola(); });
    [sc, fr, f.querySelector('[name="avvisa_cliente"]')].forEach((x) => x.addEventListener('change', calcola));
    calcola();
  }

  document.querySelectorAll('input[type="file"][data-max]').forEach((i) => i.addEventListener('change', () => {
    const file = i.files[0];
    i.setCustomValidity(file && file.size > +i.dataset.max ? 'Il file supera il limite consentito.' : '');
    i.reportValidity();
  }));
});
