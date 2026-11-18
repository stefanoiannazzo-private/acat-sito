-- Valori di partenza: SONO ESEMPI da sostituire dal pannello con le tariffe reali di ACAT.

INSERT INTO impostazioni (chiave, valore, descrizione) VALUES
('mostra_prezzo', 'subito', 'subito = la stima compare dopo il calcolo; dopo_contatti = solo dopo che il visitatore ha lasciato nome ed email'),
('premio_minimo_rc', '250', 'Importo minimo della RC (euro), dopo coefficienti e supplementi'),
('arrotondamento', 'euro', 'centesimi = arrotonda ai centesimi; euro = arrotonda all''euro'),
('eta_minima', '18', 'Età minima del contraente per calcolare la stima'),
('email_notifiche', '', 'Indirizzo che riceve le richieste di preventivo definitivo (vuoto = nessuna email)'),
('preventivatore_attivo', '1', '1 = il preventivatore pubblico funziona; 0 = mostra un messaggio di servizio sospeso'),
('avviso', 'Stima indicativa e non vincolante, calcolata sui dati inseriti. Non è un''offerta contrattuale: il premio definitivo dipende dalla compagnia, dalla classe di merito e dalla verifica dei dati in agenzia.', 'Testo mostrato sotto il risultato');

INSERT INTO garanzie (id, codice, nome, descrizione, principale, dipende_da, tipo_calcolo, valore, applica_coefficienti, ordine) VALUES
(1, 'RC', 'Responsabilità civile (RC)', 'Obbligatoria: danni causati a persone, animali e cose', 1, NULL, 'fisso', 400.00, 1, 10);
INSERT INTO garanzie (codice, nome, descrizione, principale, dipende_da, tipo_calcolo, valore, applica_coefficienti, ordine) VALUES
('CRISTALLI', 'Cristalli', 'Parabrezza, lunotto e vetri laterali', 0, 1, 'fisso', 60.00, 0, 20),
('FURTO', 'Furto', 'Furto totale o parziale del veicolo', 0, 1, 'perc_rc', 15.00, 0, 30),
('INCENDIO', 'Incendio', 'Incendio, esplosione e fulmine', 0, 1, 'perc_rc', 8.00, 0, 40),
('VANDALICI', 'Atti vandalici', 'Danni causati da terzi non identificati', 0, 1, 'fisso', 70.00, 0, 50),
('KASKO', 'Kasko', 'Danni al proprio veicolo anche se la colpa è tua', 0, 1, 'perc_rc', 60.00, 0, 60),
('COLLISIONE', 'Collisione', 'Danni al proprio veicolo in urto con un altro veicolo identificato', 0, 1, 'perc_rc', 30.00, 0, 70);

INSERT INTO fasce_eta (eta_min, eta_max, tipo, valore, descrizione) VALUES
(18, 22, 'percentuale', 40.00, 'Neopatentati e giovanissimi'),
(23, 25, 'percentuale', 20.00, 'Giovani conducenti'),
(26, 75, 'percentuale', 0.00, 'Nessun supplemento'),
(76, 120, 'percentuale', 10.00, 'Over 75');
