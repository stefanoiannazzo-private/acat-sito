/* Preventivatore RC auto e moto: modulo a tre passaggi, ricerca del comune, marche e modelli, stima e richiesta. */
(() => {
  const $ = (s) => document.querySelector(s);
  const form = $('#pv');
  if (!form) return;
  const api = (cosa, q = '') => fetch(`api/opzioni.php?cosa=${cosa}${q}`, { headers: { Accept: 'application/json' } }).then((r) => r.json());
  const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
    .then(async (r) => { let d = {}; try { d = await r.json(); } catch (e) { /* risposta non JSON */ } return d.ok !== undefined ? d : { ok: false, errore: 'Servizio non raggiungibile. Riprova o chiamaci.' }; });
  const euro = (v) => v === null ? 'su richiesta' : '€ ' + Number(v).toLocaleString('it-IT', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const el = {
    comune: $('#pv-comune'), comuneId: $('#pv-comune-id'), lista: $('#pv-comuni'), nascita: $('#pv-nascita'),
    marca: $('#pv-marca'), modello: $('#pv-modello'), garanzie: $('#pv-garanzie'), contatti: $('#pv-contatti'),
    err: $('#pv-err'), submit: $('#pv-submit'), out: $('#pv-out'), off: document.querySelector('.pv-off'),
  };
  let cfg = { mostra_prezzo: 'subito', eta_minima: 18 }, fase = 'calcolo', ultimaStima = null;

  const errore = (m, campo) => {
    el.err.textContent = m; el.err.hidden = !m;
    form.querySelectorAll('[aria-invalid]').forEach((x) => x.removeAttribute('aria-invalid'));
    if (campo) { campo.setAttribute('aria-invalid', 'true'); campo.focus(); }
  };
  const mostra = (quale) => ['empty', 'res', 'ok'].forEach((k) => { el.out.querySelector('.pv-out__' + k).hidden = k !== quale; });

  // ---------- data di nascita: niente date future, minimo l'età richiesta
  const oggi = new Date();
  const maxD = new Date(oggi.getFullYear() - 14, oggi.getMonth(), oggi.getDate());
  el.nascita.max = maxD.toISOString().slice(0, 10);

  // ---------- comune: ricerca con elenco accessibile
  let timer, attivo = -1, risultati = [];
  const chiudi = () => { el.lista.hidden = true; el.comune.setAttribute('aria-expanded', 'false'); attivo = -1; el.comune.removeAttribute('aria-activedescendant'); };
  const scegli = (c) => { el.comune.value = c.nome; el.comuneId.value = c.id; chiudi(); el.comune.removeAttribute('aria-invalid'); };
  const evidenzia = (i) => {
    attivo = i;
    [...el.lista.children].forEach((li, k) => li.setAttribute('aria-selected', String(k === i)));
    if (i >= 0) { el.comune.setAttribute('aria-activedescendant', 'pv-c-' + i); el.lista.children[i].scrollIntoView({ block: 'nearest' }); }
  };
  el.comune.addEventListener('input', () => {
    el.comuneId.value = '';
    clearTimeout(timer);
    const q = el.comune.value.trim();
    if (q.length < 2) return chiudi();
    timer = setTimeout(async () => {
      const r = await api('comuni', '&q=' + encodeURIComponent(q)).catch(() => ({ comuni: [] }));
      risultati = r.comuni || [];
      el.lista.innerHTML = risultati.length ? risultati.map((c, i) => `<li role="option" id="pv-c-${i}" aria-selected="false" data-i="${i}">${esc(c.nome)}</li>`).join('')
        : '<li class="pv-none" aria-disabled="true">Nessun comune trovato</li>';
      el.lista.hidden = false; el.comune.setAttribute('aria-expanded', 'true');
      const esatto = risultati.findIndex((c) => c.nome.toLowerCase().startsWith(q.toLowerCase() + ' ('));
      if (risultati.length === 1 || esatto === 0) evidenzia(0);
    }, 180);
  });
  el.comune.addEventListener('keydown', (e) => {
    if (el.lista.hidden || !risultati.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); evidenzia(Math.min(attivo + 1, risultati.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); evidenzia(Math.max(attivo - 1, 0)); }
    else if (e.key === 'Enter' && attivo >= 0) { e.preventDefault(); scegli(risultati[attivo]); }
    else if (e.key === 'Escape') chiudi();
  });
  el.lista.addEventListener('mousedown', (e) => { const li = e.target.closest('[data-i]'); if (li) { e.preventDefault(); scegli(risultati[+li.dataset.i]); } });
  el.comune.addEventListener('blur', () => setTimeout(() => {
    if (!el.comuneId.value && risultati.length && attivo >= 0) scegli(risultati[attivo]);
    chiudi();
  }, 120));

  // ---------- marche e modelli
  const caricaMarche = async () => {
    const tipo = form.querySelector('[name="tipo"]:checked').value;
    el.marca.innerHTML = '<option value="">Caricamento…</option>';
    el.modello.innerHTML = '<option value="">Scegli prima la marca</option>'; el.modello.disabled = true;
    const r = await api('marche', '&tipo=' + tipo).catch(() => ({ marche: [] }));
    el.marca.innerHTML = '<option value="">Scegli la marca</option>' + (r.marche || []).map((m) => `<option value="${m.id}">${esc(m.nome)}</option>`).join('');
  };
  form.querySelectorAll('[name="tipo"]').forEach((x) => x.addEventListener('change', caricaMarche));
  el.marca.addEventListener('change', async () => {
    el.modello.disabled = true;
    el.modello.innerHTML = '<option value="">Caricamento…</option>';
    if (!el.marca.value) { el.modello.innerHTML = '<option value="">Scegli prima la marca</option>'; return; }
    const r = await api('modelli', '&marca=' + el.marca.value).catch(() => ({ modelli: [] }));
    el.modello.innerHTML = '<option value="">Scegli il modello</option>' + (r.modelli || []).map((m) => `<option value="${m.id}">${esc(m.nome)}</option>`).join('');
    el.modello.disabled = false;
  });

  // ---------- garanzie
  const caricaGaranzie = async () => {
    const r = await api('garanzie').catch(() => ({ garanzie: [] }));
    el.garanzie.innerHTML = (r.garanzie || []).map((g) => `
      <label class="pv-g${g.principale ? ' pv-g--main' : ''}">
        <input type="checkbox" value="${g.id}"${g.principale ? ' checked disabled' : ''}>
        <span class="pv-g__box" aria-hidden="true"></span>
        <span class="pv-g__txt"><b>${esc(g.nome)}</b>${g.descrizione ? `<small>${esc(g.descrizione)}</small>` : ''}</span>
        ${g.principale ? '<span class="pv-g__tag">Inclusa</span>' : ''}
      </label>`).join('');
  };

  // ---------- invio
  const dati = () => ({
    comune_id: +el.comuneId.value || 0, nascita: el.nascita.value, modello_id: +el.modello.value || 0,
    garanzie: [...el.garanzie.querySelectorAll('input:checked:not(:disabled)')].map((x) => +x.value),
  });
  const contatti = () => ({ nome: $('#pv-nome').value.trim(), email: $('#pv-email').value.trim(), telefono: $('#pv-tel').value.trim(),
    note: $('#pv-note').value.trim(), consenso: $('#pv-consenso').checked, sito_web: $('#pv-sito').value });
  const controlla = (d, conContatti) => {
    if (!d.comune_id) return ['Scegli il comune di residenza dall\'elenco.', el.comune];
    if (!d.nascita) return ['Inserisci la data di nascita.', el.nascita];
    if (!el.marca.value) return ['Scegli la marca del veicolo.', el.marca];
    if (!d.modello_id) return ['Scegli il modello del veicolo.', el.modello];
    if (conContatti) {
      const c = contatti();
      if (!c.nome) return ['Scrivi nome e cognome.', $('#pv-nome')];
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.email)) return ['Controlla l\'indirizzo email.', $('#pv-email')];
      if (!c.consenso) return ['Serve il consenso al trattamento dei dati per inviarti il preventivo.', $('#pv-consenso')];
    }
    return null;
  };
  const disegna = (s) => {
    ultimaStima = s;
    $('#pv-tot').textContent = euro(s.totale) + (s.su_richiesta ? ' +' : '');
    $('#pv-meta').textContent = `${s.veicolo} · ${s.comune} · ${s.eta} anni`;
    $('#pv-righe').innerHTML = s.righe.map((r) => `<li><span>${esc(r.nome)}</span><b>${euro(r.importo)}</b></li>`).join('');
    $('#pv-avviso').textContent = s.avviso || '';
  };
  const occupato = (on, testo) => { el.submit.disabled = on; el.submit.classList.toggle('is-busy', on); if (testo) el.submit.firstChild.textContent = testo; };

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
      if (cfg.mostra_prezzo !== 'subito') { el.out.querySelector('#pv-richiedi').hidden = true; const ok = document.createElement('p'); ok.className = 'pv-out__sent'; ok.textContent = 'Richiesta inviata: ti ricontattiamo con il preventivo definitivo.'; el.out.querySelector('.pv-out__res').append(ok); }
      form.querySelectorAll('input, select, button').forEach((x) => { x.disabled = true; });
    } else {
      disegna(r.stima);
      mostra('res');
    }
    if (window.matchMedia('(max-width: 1023px)').matches) el.out.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  // dopo una stima, ogni modifica ai dati chiede di ricalcolare
  form.addEventListener('change', () => { if (fase === 'calcolo' && ultimaStima && !el.out.querySelector('.pv-out__res').hidden) el.out.querySelector('.pv-out__res').classList.add('is-stale'); });
  form.addEventListener('submit', () => el.out.querySelector('.pv-out__res').classList.remove('is-stale'));

  $('#pv-richiedi').addEventListener('click', () => {
    fase = 'richiesta';
    el.contatti.hidden = false;
    $('#pv-contatti-hint').textContent = 'Lasciaci i tuoi contatti: ti mandiamo il preventivo definitivo, confrontando più compagnie.';
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
      if (c.mostra_prezzo !== 'subito') { el.contatti.hidden = false; el.submit.firstChild.textContent = 'Calcola e invia la richiesta'; }
      await Promise.all([caricaMarche(), caricaGaranzie()]);
    } catch (e) {
      el.off.textContent = 'Il preventivatore non è raggiungibile in questo momento. Chiamaci o scrivici: ti prepariamo noi il preventivo.';
      el.off.hidden = false; form.hidden = true;
    }
  })();
})();
