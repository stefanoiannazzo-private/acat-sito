# Piano: preventivatore RC auto + pannello admin (ACAT Servizi)

Vincoli dell'hosting (Aruba Hosting Easy Linux): PHP 8.x, MySQL 8 (5 DB, 1 GB), Apache + .htaccess, FTP, niente SSH.
=> Backend in PHP 8 + PDO/MySQL senza framework pesanti; pubblicazione via GitHub Actions FTP; nessuna password nel repository.

## Fase 0 - Prerequisiti
- DNS verificati, dominio e SSL attivi, prima pubblicazione automatica da GitHub riuscita.
- Creare un database MySQL dal pannello Aruba; dati di accesso nei Secrets di GitHub (DB_HOST, DB_NAME, DB_USER, DB_PASS).
- Repository privato (contiene logica di prezzo).

## Fase 1 - Struttura del progetto
- `site/` parte pubblica (sito attuale + pagina preventivatore).
- `site/admin/` pannello amministrazione (protetto).
- `app/` codice PHP condiviso (connessione DB, calcolo premio, validazioni), bloccato dal web con .htaccess.
- `sql/` migrazioni numerate (001_schema.sql, 002_dati_base.sql, ...) + tabella `schema_version`.
- `config.php` generato dal workflow a partire dai Secrets, mai nel repository.

## Fase 2 - Schema del database
- `comuni` (codice ISTAT, nome, sigla provincia, regione, coefficiente RC, attivo).
- `fasce_eta` (età minima, età massima, tipo supplemento % o €, valore) - es. sotto i 25 anni +X%.
- `marche` (nome, attiva) / `modelli` (marca, nome, alimentazione, cilindrata o kW, coefficiente o classe di rischio, valore indicativo, attivo).
- `garanzie` (codice, nome, descrizione, tipo calcolo: importo fisso / % sull'RC / % sul valore veicolo, valore base, obbligatoria sì/no, ordine, attiva). RC = garanzia principale, sempre inclusa; tutte le altre sono accessorie e richiedono l'RC (vincolo applicato da DB e codice).
- Garanzie iniziali: RC, Cristalli, Furto, Incendio, Atti vandalici, Kasko, Collisione.
- `preventivi` (dati inseriti, dettaglio calcolo, totale, data, stato, contatto del cliente, consenso privacy).
- `utenti_admin` (email, password con hash, ultimo accesso) e `log_modifiche` (chi ha cambiato cosa e quando).

## Fase 3 - Dati iniziali
- Comuni: elenco ufficiale ISTAT (~7.900 comuni) importato da CSV; coefficiente 1,00 di partenza, da tarare.
- Marche e modelli: dataset aperto importato da CSV (copertura ampia ma non garantita completa); integrazione/manutenzione da pannello. Per dati professionali (codici Infocar/Eurotax) serve un fornitore a pagamento.
- Fasce d'età e prezzi base delle 7 garanzie: forniti da ACAT.

## Fase 4 - Pannello admin
- Accesso con email e password (hash), sessione sicura, protezione CSRF, blocco dopo tentativi errati; verifica in 2 passaggi opzionale.
- CRUD con ricerca, filtri e paginazione per: comuni, fasce d'età, marche, modelli, garanzie.
- Import/export CSV per comuni e modelli; modifica rapida dei coefficienti.
- Elenco preventivi ricevuti con stato (nuovo / contattato / chiuso) ed esportazione.
- Pagina "Prova calcolo" per verificare le tariffe prima di pubblicarle.

## Fase 5 - Motore di calcolo
- RC = base RC x coeff. comune x coeff. veicolo, + supplemento fascia d'età.
- Accessorie = secondo il tipo di calcolo di ogni garanzia (fisso, % RC, % valore veicolo).
- Arrotondamenti, minimi/massimi, importi IVA/imposte da definire.
- Test automatici (PHPUnit) eseguiti da GitHub Actions a ogni push.

## Fase 6 - Preventivatore pubblico
- Pagina "Preventivo RC auto" nello stile del sito: comune (select con ricerca), data di nascita, marca -> modello, garanzie (RC fissa + accessorie a scelta).
- Risultato con dettaglio per garanzia e totale, dichiarato come stima indicativa non vincolante.
- "Richiedi il preventivo definitivo": salva nel DB e avvisa l'agenzia via email.

## Fase 7 - Sicurezza, privacy, conformità
- Query preparate, validazione lato server, limiti di invio, area admin non indicizzata.
- Aggiornare privacy policy (nuovi dati: data di nascita, veicolo, comune) e tempi di conservazione.
- Verificare con ACAT/consulente gli obblighi IVASS su stime online e informativa precontrattuale.

## Fase 8 - Pubblicazione e manutenzione
- Workflow: test -> genera config.php -> carica via FTP (esclusi file sensibili).
- Migrazioni applicate dal pannello admin (pagina "Aggiorna database") o da phpMyAdmin.
- Backup: quelli giornalieri di Aruba + esportazione periodica del DB.

## Decisioni aperte
1. Supplemento età: percentuale o importo fisso? Soglia unica (es. < 25 anni) o più fasce?
2. Come si calcolano le accessorie: importo fisso, % sull'RC o % sul valore del veicolo (serve il valore)?
3. Il veicolo incide sul prezzo? Con quale dato: coefficiente per modello, classe di rischio, cilindrata/kW?
4. Il risultato si mostra subito al visitatore o solo dopo che lascia i contatti?
5. Prima della Fase 6: aggiungere classe di merito (bonus-malus), tipo di guida, km annui?
6. Quante persone accedono al pannello?
