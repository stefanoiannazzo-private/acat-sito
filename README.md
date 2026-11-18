# Sito ACAT Servizi (Giaveno)

Sito statico di 4 pagine per ACAT Servizi, agenzia di assicurazioni e pratiche auto in Via Coazze 35, Giaveno.

## Struttura
- `site/` — **la cartella che va online**: solo i file usati dalle pagine. La pubblica il workflow `.github/workflows/deploy.yml` via FTP a ogni push su `main` che la modifica.
- `build.py` — genera `index.html`, `chi-siamo.html`, `servizi.html`, `contatti.html` (testi, menu, footer condivisi).
- `tools/dist.py` — copia in `site/` le pagine e i soli file che usano.
- `assets/site.css`, `assets/fx.js` — stile e animazioni del sito (hero, icone, effetti allo scroll, transizione tra pagine).
- `assets/system.*`, `assets/motion/`, `assets/moments/` — dal kit taste-skill (licenza MIT, vedi `assets/LICENSE-taste-skill.txt`).
- `vendor/` — font (SIL OFL) e GSAP (licenza standard GreenSock) ospitati sul sito: in `site/` le pagine non chiamano servizi esterni.
- `sitemap.php` — genera `/sitemap.xml` con il dominio su cui gira il sito.
- `PLAN.md` — scelte di progetto, fonti dei dati, voce dei testi.

## Modificare il sito
```bash
python3 build.py        # rigenera le pagine (serve ffprobe e Pillow)
python3 tools/dist.py   # aggiorna site/
python3 -m http.server  # anteprima su http://localhost:8000
git add -A && git commit -m "..." && git push   # il push pubblica site/
```

## Pubblicazione (una tantum)
In GitHub: **Settings > Secrets and variables > Actions**
- Secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` (dati FTP del pannello hosting)
- Variables (facoltative): `FTP_DIR` (cartella del sito sul server, predefinita `/`), `FTP_PROTOCOL` (`ftps` predefinito, oppure `ftp`)

Poi **Actions > Pubblica sul server > Run workflow** per la prima pubblicazione.

## Da completare prima della messa online
- Email da pubblicare; in `preventivo.php` impostare `DESTINATARIO` con la casella che riceve i preventivi (poi `python3 tools/dist.py`).
- Nel pannello Aruba lasciare **disattivato** "Limita accesso FTP" (o consentire gli IP di GitHub), altrimenti la pubblicazione automatica non si collega.
- Numero di iscrizione al RUI da aggiungere nel footer (obbligatorio per gli intermediari assicurativi).
- Privacy e Cookie (`privacy.html`, `cookie.html`) sono un modello standard: da far verificare a un consulente privacy.
- Dopo l'attivazione dell'SSL: togliere i `#` dal redirect https in `.htaccess`.
- Foto: ora sono stand-in da Unsplash e Wikimedia (crediti in `credits.html`).
