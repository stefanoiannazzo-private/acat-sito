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
