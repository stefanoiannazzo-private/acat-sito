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
- Variables (facoltative): `FTP_DIR` (cartella del sito sul server: su Aruba `/www.clientetest.it/`), `FTP_PROTOCOL` (`ftps` predefinito, oppure `ftp`)

Poi **Actions > Pubblica sul server > Run workflow** per la prima pubblicazione.

## Da completare prima della messa online
- Email da pubblicare; in `preventivo.php` impostare `DESTINATARIO` con la casella che riceve i preventivi (poi `python3 tools/dist.py`).
- Nel pannello Aruba lasciare **disattivato** "Limita accesso FTP" (o consentire gli IP di GitHub), altrimenti la pubblicazione automatica non si collega.
- Numero di iscrizione al RUI da aggiungere nel footer (obbligatorio per gli intermediari assicurativi).
- Privacy e Cookie (`privacy.html`, `cookie.html`) sono un modello standard: da far verificare a un consulente privacy.
- SSL attivo: il redirect a https è già acceso in `.htaccess`.
- Foto: ora sono stand-in da Unsplash e Wikimedia (crediti in `credits.html`).

## Preventivatore RC auto e moto
- Pagina pubblica: `preventivo-rc-auto.html` (script `assets/preventivo.js`), che chiama le API in `api/`.
- Pannello: `/admin/` (pagine in `admin/`). Il **primo accesso** crea l'amministratore e chiede la password del database (secret `DB_PASS`).
- Codice condiviso in `app/` (bloccato dal web): calcolo in `app/lib/Calcolo.php`, tabelle del pannello in `app/lib/Tabelle.php`.
- Database: le modifiche sono in `app/sql/NNN_*.sql|php` e vengono applicate da sole alla prima apertura del pannello.
- Configurazione: `site/app/config.php` viene creato dal workflow con i secrets `DB_*`. Non va mai nel repository.
- Test: `composer install && vendor/bin/phpunit`.
- Dati iniziali: comuni dall'elenco ISTAT (via github.com/matteocontrini/comuni-json); marche e modelli auto ricavati da github.com/ilyasozkurt/automobile-models-and-specs (solo nomi e anni) più alcune marche recenti; moto: elenco di partenza delle marche e dei modelli più diffusi. Da completare dal pannello. Tariffe, coefficienti e fasce d'età sono valori di esempio.
