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

/* calcolo passo per passo: tipo di polizza → compagnia (→ prodotto) → garanzie (RC fissa) → frazionamento → annualità */
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('calc-dati');
  if (!el) return;
  const { prodotti, scelte, nomi_fraz: nomiFraz } = JSON.parse(el.textContent);
  const $ = (id) => document.getElementById(id);
  const tipo = $('calc-tipo'), comp = $('calc-compagnia'), prod = $('calc-prodotto'), prodBox = $('calc-prodotto-box');
  const gar = $('calc-garanzie'), fraz = $('calc-fraz'), dur = $('calc-durata');
  const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const opt = (v, t, sel) => `<option value="${esc(v)}"${sel ? ' selected' : ''}>${esc(t)}</option>`;
  let prima = true; // al primo giro ripristino le scelte (modulo ricaricato dopo un errore)

  const delTipo = () => prodotti.filter((p) => String(p.categoria) === tipo.value);
  const inUso = () => {
    const l = delTipo();
    if (!comp.value) return [];
    if (comp.value === 'tutte') return l;
    const c = l.filter((p) => String(p.compagnia_id) === comp.value);
    if (c.length > 1 && prod.value) return c.filter((p) => String(p.id) === prod.value);
    return c.length > 1 ? [c[0]] : c;
  };

  const aggiornaCompagnie = () => {
    const l = delTipo(), viste = new Map();
    l.forEach((p) => viste.set(String(p.compagnia_id), p.compagnia));
    const v = prima ? String(scelte.compagnia || '') : comp.value;
    comp.innerHTML = opt('', tipo.value ? 'Scegli…' : 'Scegli prima il tipo di polizza')
      + [...viste].map(([id, n]) => opt(id, n, v === id)).join('')
      + (viste.size > 1 ? opt('tutte', 'Confronta tutte le compagnie (prezzo migliore)', v === 'tutte') : '');
    if (viste.size === 1 && !comp.value) comp.value = [...viste.keys()][0];
    aggiornaProdotti();
  };
  const aggiornaProdotti = () => {
    const c = delTipo().filter((p) => comp.value && comp.value !== 'tutte' && String(p.compagnia_id) === comp.value);
    prodBox.hidden = c.length < 2;
    const v = prima ? String(scelte.prodotto_id || '') : prod.value;
    prod.innerHTML = c.length > 1 ? c.map((p, i) => opt(p.id, p.nome, v ? v === String(p.id) : i === 0)).join('') : (c[0] ? opt(c[0].id, c[0].nome, true) : '');
    aggiornaResto();
  };
  const capBox = $('calc-capitale-box'), cap = $('calc-capitale'), dom = $('calc-domande'), contr = $('calc-contraente');
  const norm = (t) => String(t).trim().toLowerCase().replace(/\s+/g, ' ');
  const modoCapitale = () => delTipo().some((p) => p.tipo === 'capitale');
  const aggiornaCapitale = (l) => {
    const si = modoCapitale();
    gar.hidden = si; capBox.hidden = !si; cap.disabled = !si; cap.required = si;
    $('calc-passo3').textContent = si ? 'Capitale e domande al cliente' : 'Garanzie';
    $('calc-durata-box').hidden = si; dur.disabled = si;
    if (contr) { contr.hidden = si; contr.querySelectorAll('input, select').forEach((f) => { f.disabled = si; }); }
    if (!si) { dom.innerHTML = ''; return; }
    if (prima && scelte.capitale) cap.value = String(scelte.capitale).replace('.', ',');
    const mn = Math.min(...l.map((p) => p.capitale_minimo || Infinity)), mx = Math.max(...l.map((p) => p.capitale_massimo || 0));
    const eu = (v) => v.toLocaleString('it-IT') + ' €';
    $('calc-capitale-aiuto').textContent = 'La somma che la polizza paga' + (isFinite(mn) ? ' · minimo ' + eu(mn) : '') + (mx ? ' · massimo ' + eu(mx) : '');
    // domande unite per testo fra i prodotti in uso
    const prec = {};
    dom.querySelectorAll('[data-domanda]').forEach((x) => { prec[norm(x.dataset.domanda)] = x.querySelector('select').value; });
    if (prima) Object.entries(scelte.risposte || {}).forEach(([k, v]) => { prec[norm(k)] = v; });
    const unite = new Map();
    l.forEach((p) => p.domande.forEach((d) => {
      const k = norm(d.testo);
      if (!unite.has(k)) unite.set(k, { testo: d.testo, aiuto: d.aiuto, risposte: [] });
      d.risposte.forEach((r) => { if (!unite.get(k).risposte.some((x) => norm(x) === norm(r))) unite.get(k).risposte.push(r); });
    }));
    dom.innerHTML = [...unite.values()].map((d, i) => `<label data-domanda="${esc(d.testo)}">${esc(d.testo)}<input type="hidden" name="dom[${i}]" value="${esc(d.testo)}">
      <select name="risp[${i}]" required>${opt('', 'Scegli…')}${d.risposte.map((r) => opt(r, r, norm(prec[norm(d.testo)] || '') === norm(r))).join('')}</select>
      ${d.aiuto ? `<small class="hint">${esc(d.aiuto)}</small>` : ''}</label>`).join('') || '<p class="hint">Questo prodotto non ha domande: il premio dipende solo dal capitale.</p>';
  };
  const aggiornaResto = () => {
    const l = inUso();
    aggiornaCapitale(l);
    // garanzie: quelle del prodotto (o comuni a tutti, se si confronta); la RC è sempre inclusa
    const presenti = new Map();
    l.forEach((p) => p.garanzie.forEach((g) => { if (!presenti.has(g.codice)) presenti.set(g.codice, g); }));
    const spuntate = prima ? new Set(scelte.garanzie) : new Set([...gar.querySelectorAll('input:checked:not(:disabled)')].map((x) => x.value));
    if (!l.length) gar.innerHTML = '<p class="muted">Scegli tipo di polizza e compagnia.</p>';
    else {
      const rc = [...presenti.values()].filter((g) => g.rc), altre = [...presenti.values()].filter((g) => !g.rc);
      gar.innerHTML = rc.map((g) => `<label class="check"><input type="checkbox" checked disabled> <b>${esc(g.nome)}</b> <small class="hint">obbligatoria, sempre inclusa</small></label>`).join('')
        + (altre.length ? '<p class="hint">Garanzie facoltative, in aggiunta alla RC:</p>' : '<p class="hint">Questo prodotto non ha garanzie facoltative.</p>')
        + altre.map((g) => `<label class="check"><input type="checkbox" name="garanzie[]" value="${esc(g.codice)}"${spuntate.has(g.codice) ? ' checked' : ''}> ${esc(g.nome)}</label>`).join('')
        + (comp.value === 'tutte' ? '<p class="hint">Confrontando tutte le compagnie, quelle che non hanno una garanzia scelta vengono escluse dal confronto.</p>' : '');
    }
    const f = new Set(), d = new Set([1]);
    l.forEach((p) => { p.frazionamenti.forEach((x) => f.add(x)); p.durate.forEach((x) => d.add(x)); });
    if (!f.size) f.add('annuale');
    const vf = prima ? scelte.frazionamento : fraz.value, vd = prima ? String(scelte.durata) : dur.value;
    fraz.innerHTML = Object.keys(nomiFraz).filter((k) => f.has(k)).map((k) => opt(k, nomiFraz[k], k === vf)).join('');
    dur.innerHTML = [...d].sort((a, b) => a - b).map((a) => opt(a, a === 1 ? '1 anno' : a + ' anni', String(a) === vd)).join('');
  };

  tipo.addEventListener('change', aggiornaCompagnie);
  comp.addEventListener('change', aggiornaProdotti);
  prod.addEventListener('change', aggiornaResto);
  aggiornaCompagnie();
  prima = false;
});

/* prodotto: nella variazione di prezzo mostro solo il campo che serve per il "Quando" scelto */
document.addEventListener('DOMContentLoaded', () => {
  const mostra = (tr) => {
    const t = tr.querySelector('[name$="[tipo]"]').value;
    tr.querySelectorAll('.cond').forEach((c) => { c.hidden = !c.dataset.per.split(' ').includes(t) && !(t === '' && c.dataset.per === ''); });
  };
  document.querySelectorAll('[data-correzione]').forEach((tr) => { mostra(tr); tr.querySelector('[name$="[tipo]"]').addEventListener('change', () => mostra(tr)); });
});

/* avvisi di esito: si chiudono con la ×; quelli di conferma spariscono da soli dopo qualche secondo, gli errori restano */
document.addEventListener('DOMContentLoaded', () => {
  const chiudi = (a) => { a.classList.add('esce'); setTimeout(() => { a.remove(); }, 300); };
  document.querySelectorAll('.avviso').forEach((a) => {
    a.querySelector('.avviso__chiudi')?.addEventListener('click', () => chiudi(a));
    if (!a.classList.contains('flash--errore')) setTimeout(() => chiudi(a), 6000);
  });
});

/* Prova calcolo: ricalcolo automatico a ogni modifica, senza premere "Calcola" */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('prova'), box = document.getElementById('prova-risultato'), stato = document.getElementById('prova-stato');
  if (!form || !box) return;
  let timer, ultimo = '', n = 0;
  const pronto = () => { const ci = form.querySelector('[name="comune_id"]'); return [...form.querySelectorAll('[required]:not(:disabled)')].every((f) => f.value.trim() !== '') && (!ci || ci.disabled || ci.value !== ''); };
  const calcola = async () => {
    if (!pronto()) return;
    const dati = new FormData(form);
    dati.set('dal_vivo', '1');
    const chiave = new URLSearchParams(dati).toString();
    if (chiave === ultimo) return;
    ultimo = chiave;
    const mio = ++n;
    box.classList.add('agg'); stato.textContent = 'aggiorno…';
    try {
      const html = await fetch('prova.php', { method: 'POST', body: dati, credentials: 'same-origin' }).then((r) => { if (!r.ok) throw new Error(); return r.text(); });
      if (mio !== n) return;
      box.innerHTML = html; stato.textContent = 'aggiornato';
    } catch { if (mio === n) stato.textContent = 'non riesco ad aggiornare: premi Calcola'; }
    finally { if (mio === n) box.classList.remove('agg'); }
  };
  const pianifica = () => { clearTimeout(timer); timer = setTimeout(calcola, 350); };
  form.addEventListener('change', pianifica);
  form.addEventListener('input', pianifica);
  form.addEventListener('submit', (e) => { if (pronto()) { e.preventDefault(); ultimo = ''; calcola(); } });
});

/* prodotto: i campi solo per l'auto (es. veicoli assicurati) compaiono solo se la classificazione è nell'area Auto */
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.querySelector('[data-classificazione]');
  if (!sel) return;
  const aggiorna = () => {
    const o = sel.selectedOptions[0];
    const area = o && o.parentElement.tagName === 'OPTGROUP' ? o.parentElement.label : '';
    document.querySelectorAll('[data-solo-auto]').forEach((x) => { x.hidden = area !== 'Auto'; });
  };
  sel.addEventListener('change', aggiorna);
  aggiorna();
});

/* questionario: "+ Aggiungi una risposta" duplica l'ultima riga vuota della domanda */
document.addEventListener('click', (e) => {
  const b = e.target.closest('[data-aggiungi-risposta]');
  if (!b) return;
  const tbody = b.closest('.domanda').querySelector('.table--risposte tbody');
  const ultima = tbody.lastElementChild, nuova = ultima.cloneNode(true);
  const k = 'n' + Date.now();
  nuova.querySelectorAll('[name]').forEach((f) => { f.name = f.name.replace(/\[r\]\[[^\]]+\]/, `[r][${k}]`); if (f.type !== 'checkbox' && f.tagName !== 'SELECT') f.value = ''; });
  nuova.querySelector('td:last-child').innerHTML = '';
  tbody.append(nuova);
  nuova.querySelector('input')?.focus();
});
