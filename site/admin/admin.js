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
  // ricerca di una persona o di un'azienda dell'anagrafica (campo testo + id nascosto)
  document.querySelectorAll('[data-cerca-clienti]').forEach((inp) => {
    const hid = inp.form.querySelector('[name="' + inp.dataset.campo + '"]'), list = document.getElementById(inp.getAttribute('list'));
    let map = {}, timer;
    inp.addEventListener('input', () => {
      hid.value = map[inp.value] || '';
      clearTimeout(timer);
      if (inp.value.length < 2 || hid.value) return;
      timer = setTimeout(async () => {
        const r = await fetch('cerca.php?cosa=clienti&tipo=' + inp.dataset.cercaClienti + '&q=' + encodeURIComponent(inp.value)).then((x) => x.json());
        map = {}; list.innerHTML = '';
        for (const x of r.r || []) { map[x.n] = x.id; const o = document.createElement('option'); o.value = x.n; list.append(o); }
        hid.value = map[inp.value] || '';
      }, 200);
    });
  });
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
    for (const x of r.r || []) { const o = document.createElement('option'); o.value = x.id; o.textContent = x.n; o.dataset.categoria = x.c || ''; prod.append(o); }
  });
  prod?.addEventListener('change', () => {
    const c = prod.selectedOptions[0]?.dataset.categoria, sel = document.querySelector('select[data-categoria]');
    if (c && c !== '0' && sel) sel.value = c;
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

/* campi obbligatori: avviso rosso sotto il campo invece del fumetto del browser */
document.addEventListener('invalid', (e) => {
  const f = e.target;
  if (!(f instanceof HTMLElement) || !f.form) return;
  e.preventDefault();
  f.classList.add('is-invalid');
  const box = f.closest('label, .field, .campo, td') || f.parentElement;
  let m = box.querySelector(':scope > .obbligo');
  if (!m) { m = document.createElement('small'); m.className = 'obbligo'; box.append(m); }
  m.textContent = f.validity.valueMissing ? 'Campo obbligatorio' : (f.validationMessage || 'Valore non valido');
  const primo = f.form.querySelector('.is-invalid');
  if (primo === f) f.focus();
}, true);
document.addEventListener('input', (e) => {
  const f = e.target;
  if (!f.classList?.contains('is-invalid') || !f.checkValidity()) return;
  f.classList.remove('is-invalid');
  (f.closest('label, .field, .campo, td') || f.parentElement).querySelector(':scope > .obbligo')?.remove();
});
document.addEventListener('change', (e) => e.target.dispatchEvent?.(new Event('input', { bubbles: true })));

/* preventivo: garanzie della compagnia mentre si scrive, importo proposto, controllo dei doppioni immediato */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('defin');
  const datiEl = document.getElementById('garanzie-dati');
  if (!form || !datiEl) return;
  const dati = JSON.parse(datiEl.textContent);
  const nuova = form.querySelector('[data-garanzie]'), imp = form.querySelector('[name="nuova_importo"]');
  const comp = form.querySelector('[name="compagnia"]'), list = document.getElementById(nuova.getAttribute('list'));
  const norm = (t) => t.trim().toLowerCase().replace(/\s+/g, ' ');
  const num = (t) => parseFloat(String(t).replace(/\./g, '').replace(',', '.'));
  const elenco = () => {
    const c = norm(comp.value);
    if (dati.prodotto.length && (!c || c === norm(dati.prodotto_compagnia || ''))) return dati.prodotto;
    return dati.compagnie[c] || [];
  };
  const riempi = () => {
    list.innerHTML = '';
    const presenti = new Set(campi().filter((f) => f !== nuova).map((f) => norm(f.value)));
    for (const g of elenco()) { if (presenti.has(norm(g.nome))) continue; const o = document.createElement('option'); o.value = g.nome; list.append(o); }
  };
  const campi = () => [...form.querySelectorAll('[name^="riga_nome"], [name="nuova_nome"]')].filter((f) => {
    const t = f.closest('tr')?.querySelector('[name^="riga_togli"]');
    return !(t && t.checked);
  });
  const segna = (f, msg) => {
    const box = f.closest('td');
    let m = box.querySelector(':scope > .obbligo');
    f.setCustomValidity(msg || '');
    f.classList.toggle('is-invalid', !!msg);
    if (msg) { if (!m) { m = document.createElement('small'); m.className = 'obbligo'; box.append(m); } m.textContent = msg; }
    else m?.remove();
  };
  const controlla = () => {
    const visti = new Set();
    form.querySelectorAll('[name^="riga_nome"], [name="nuova_nome"]').forEach((f) => segna(f, ''));
    for (const f of campi()) {
      const k = norm(f.value);
      if (!k) continue;
      if (visti.has(k)) segna(f, 'Garanzia già presente nel preventivo'); else visti.add(k);
    }
  };
  nuova.addEventListener('focus', riempi);
  comp.addEventListener('input', riempi);
  nuova.addEventListener('input', () => {
    const g = elenco().find((x) => norm(x.nome) === norm(nuova.value));
    if (g && !imp.value) {
      let v = null;
      if (g.tipo === 'fisso') v = g.valore;
      else if (g.tipo === 'perc_rc') { const rc = num(form.querySelector('[name="riga_importo[0]"]')?.value || ''); if (!isNaN(rc)) v = rc * g.valore / 100; }
      if (v !== null) imp.value = v.toFixed(2).replace('.', ',');
    }
  });
  form.addEventListener('input', (e) => { if (e.target.matches('[name^="riga_nome"], [name="nuova_nome"]')) controlla(); });
  form.addEventListener('change', (e) => { if (e.target.matches('[name^="riga_togli"]')) controlla(); });
  riempi(); controlla();
});
