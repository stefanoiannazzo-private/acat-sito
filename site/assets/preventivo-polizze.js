/* Preventivo online per polizze vita e danni: tipo di polizza, capitale, domande del questionario, stima e richiesta. */
(() => {
  const $ = (s) => document.querySelector(s);
  const form = $('#pp');
  if (!form) return;
  const api = (cosa, q = '') => fetch(`api/opzioni.php?cosa=${cosa}${q}`, { headers: { Accept: 'application/json' } }).then((r) => r.json());
  const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
    .then(async (r) => { let d = {}; try { d = await r.json(); } catch (e) { /* risposta non JSON */ } return d.ok !== undefined ? d : { ok: false, errore: 'Servizio non raggiungibile. Riprova o chiamaci.' }; });
  const euro = (v) => '€ ' + Number(v).toLocaleString('it-IT', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const el = {
    tipo: $('#pp-tipo'), dati: $('#pp-dati'), capitale: $('#pp-capitale'), capHint: $('#pp-capitale-hint'), domande: $('#pp-domande'), fraz: $('#pp-fraz'),
    contatti: $('#pv-contatti'), err: $('#pv-err'), submit: $('#pv-submit'), out: $('#pv-out'), off: document.querySelector('.pv-off'),
  };
  let cfg = { mostra_prezzo: 'subito' }, fase = 'calcolo', q = null;

  const errore = (m, campo) => {
    el.err.textContent = m; el.err.hidden = !m;
    form.querySelectorAll('[aria-invalid]').forEach((x) => x.removeAttribute('aria-invalid'));
    if (campo) { campo.setAttribute('aria-invalid', 'true'); campo.focus(); }
  };
  const mostra = (quale) => ['empty', 'res', 'ok'].forEach((k) => { el.out.querySelector('.pv-out__' + k).hidden = k !== quale; });
  // importi all'italiana: 100.000 o 100000 o 100.000,50
  const numero = (t) => { t = String(t).replace(/[€\s]/g, ''); if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.'); else if (/^\d{1,3}(\.\d{3})+$/.test(t)) t = t.replace(/\./g, ''); return parseFloat(t); };

  // ---------- tipo di polizza → questionario
  el.tipo.addEventListener('change', async () => {
    errore('');
    mostra('empty');
    el.dati.hidden = true;
    if (!el.tipo.value) return;
    const r = await api('questionario', '&categoria=' + encodeURIComponent(el.tipo.value)).catch(() => ({ ok: false }));
    if (!r.ok) return errore('Questo tipo di polizza non è disponibile online in questo momento. Chiamaci.');
    q = r;
    const eu = (v) => Number(v).toLocaleString('it-IT') + ' €';
    el.capHint.textContent = 'La somma che la polizza paga' + (r.capitale_minimo ? ' · minimo ' + eu(r.capitale_minimo) : '') + (r.capitale_massimo ? ' · massimo ' + eu(r.capitale_massimo) : '');
    el.domande.innerHTML = r.domande.map((d, i) => `
      <div class="pv-field"><label for="pp-d${i}">${esc(d.testo)}</label>
        <select id="pp-d${i}" data-domanda="${esc(d.testo)}" required><option value="">Scegli…</option>${d.risposte.map((x) => `<option>${esc(x)}</option>`).join('')}</select>
        ${d.aiuto ? `<p class="pv-hint">${esc(d.aiuto)}</p>` : ''}</div>`).join('');
    el.fraz.innerHTML = r.frazionamenti.map((f) => `<option value="${esc(f.valore)}">${esc(f.nome)}</option>`).join('');
    el.dati.hidden = false;
  });

  // ---------- invio
  const dati = () => {
    const risposte = {};
    el.domande.querySelectorAll('[data-domanda]').forEach((s) => { risposte[s.dataset.domanda] = s.value; });
    return { categoria_id: +el.tipo.value || 0, capitale: el.capitale.value.trim(), risposte, frazionamento: el.fraz.value || 'annuale' };
  };
  const contatti = () => ({ nome: $('#pv-nome').value.trim(), email: $('#pv-email').value.trim(), telefono: $('#pv-tel').value.trim(),
    note: $('#pv-note').value.trim(), consenso: $('#pv-consenso').checked, sito_web: $('#pv-sito').value });
  const controlla = (d, conContatti) => {
    if (!d.categoria_id) return ['Scegli il tipo di polizza.', el.tipo];
    const cap = numero(d.capitale);
    if (!(cap > 0)) return ['Indica il capitale da assicurare.', el.capitale];
    if (q && q.capitale_minimo && cap < q.capitale_minimo) return ['Il capitale minimo è ' + euro(q.capitale_minimo) + '.', el.capitale];
    if (q && q.capitale_massimo && cap > q.capitale_massimo) return ['Il capitale massimo è ' + euro(q.capitale_massimo) + '.', el.capitale];
    const vuota = [...el.domande.querySelectorAll('select')].find((s) => !s.value);
    if (vuota) return ['Rispondi a tutte le domande.', vuota];
    if (conContatti) {
      const c = contatti();
      if (!c.nome) return ['Scrivi nome e cognome.', $('#pv-nome')];
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.email)) return ['Controlla l\'indirizzo email.', $('#pv-email')];
      if (!c.consenso) return ['Serve il consenso al trattamento dei dati per inviarti il preventivo.', $('#pv-consenso')];
    }
    return null;
  };
  const disegna = (s) => {
    $('#pv-tot').textContent = euro(s.totale);
    const rata = $('#pv-rata');
    rata.hidden = !(s.rate > 1);
    rata.textContent = s.rate > 1 ? `${s.rate} rate da ${euro(s.rata)} (pagamento ${s.frazionamento})` : '';
    $('#pv-meta').textContent = `${s.tipo_polizza} · capitale ${euro(s.capitale)}`;
    $('#pv-righe').innerHTML = s.righe.concat(s.sconti || []).map((r) => `<li><span>${esc(r.nome)}</span><b>${euro(r.importo)}</b></li>`).join('');
    $('#pv-avviso').textContent = s.avviso || '';
  };
  const occupato = (on) => { el.submit.disabled = on; el.submit.classList.toggle('is-busy', on); };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const conContatti = fase === 'richiesta' || cfg.mostra_prezzo !== 'subito';
    const d = dati();
    const p = controlla(d, conContatti);
    if (p) return errore(p[0], p[1]);
    errore('');
    occupato(true);
    const r = conContatti ? await post('api/richiesta.php', { ...d, ...contatti() }) : await post('api/calcola.php', d);
    occupato(false);
    if (!r.ok) return errore(r.errore || 'Qualcosa non ha funzionato. Riprova.');
    if (conContatti) {
      if (r.stima) disegna(r.stima);
      mostra(cfg.mostra_prezzo === 'subito' || !r.stima ? 'ok' : 'res');
      if (cfg.mostra_prezzo !== 'subito') { $('#pv-richiedi').hidden = true; const ok = document.createElement('p'); ok.className = 'pv-out__sent'; ok.textContent = 'Richiesta inviata: ti ricontattiamo con il preventivo definitivo.'; el.out.querySelector('.pv-out__res').append(ok); }
      form.querySelectorAll('input, select, button').forEach((x) => { x.disabled = true; });
    } else {
      disegna(r.stima);
      mostra('res');
    }
    if (window.matchMedia('(max-width: 1023px)').matches) el.out.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  form.addEventListener('change', () => { const res = el.out.querySelector('.pv-out__res'); if (fase === 'calcolo' && !res.hidden) res.classList.add('is-stale'); });
  form.addEventListener('submit', () => el.out.querySelector('.pv-out__res').classList.remove('is-stale'));

  $('#pv-richiedi').addEventListener('click', () => {
    fase = 'richiesta';
    el.contatti.hidden = false;
    el.submit.firstChild.textContent = 'Invia la richiesta';
    el.contatti.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => $('#pv-nome').focus({ preventScroll: true }), 400);
  });

  // ---------- avvio
  (async () => {
    try {
      const c = await api('config');
      if (!c.ok) throw new Error();
      cfg = c;
      if (!c.attivo) {
        el.off.textContent = 'Il preventivatore online è momentaneamente sospeso. Chiamaci o scrivici: ti prepariamo noi il preventivo.';
        el.off.hidden = false; form.hidden = true; return;
      }
      const t = await api('tipi_polizza');
      if (!t.ok || !t.tipi.length) {
        el.off.textContent = 'Il preventivo online per queste polizze sarà disponibile a breve. Intanto chiamaci o scrivici: ti prepariamo noi il preventivo.';
        el.off.hidden = false; form.hidden = true; return;
      }
      el.tipo.innerHTML = '<option value="">Scegli…</option>' + t.tipi.map((x) => `<option value="${x.id}">${esc(x.nome.replace(/^Ramo (vita|danni) · /, ''))}</option>`).join('');
      if (t.tipi.length === 1) { el.tipo.value = String(t.tipi[0].id); el.tipo.dispatchEvent(new Event('change')); }
      if (c.mostra_prezzo !== 'subito') { el.contatti.hidden = false; el.submit.firstChild.textContent = 'Calcola e invia la richiesta'; }
    } catch (e) {
      el.off.textContent = 'Il preventivatore non è raggiungibile in questo momento. Chiamaci o scrivici: ti prepariamo noi il preventivo.';
      el.off.hidden = false; form.hidden = true;
    }
  })();
})();
