-- Anagrafica clienti, stati modificabili, preventivi collegati al cliente, registro delle email.

CREATE TABLE stati_cliente (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codice VARCHAR(30) NULL UNIQUE,
  nome VARCHAR(60) NOT NULL,
  colore VARCHAR(7) NOT NULL DEFAULT '#5a6880',
  ordine SMALLINT NOT NULL DEFAULT 100,
  sistema TINYINT(1) NOT NULL DEFAULT 0,
  attivo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gli stati "di sistema" vengono assegnati in automatico: si possono rinominare ma non eliminare.
INSERT INTO stati_cliente (codice, nome, colore, ordine, sistema) VALUES
('nuovo', 'Nuovo contatto', '#b3302a', 10, 1),
('richiamato', 'Cliente richiamato', '#b26a00', 20, 0),
('preventivo_inviato', 'Preventivo inviato', '#2563a8', 30, 1),
('preventivo_accettato', 'Preventivo accettato', '#1f7a4d', 40, 1),
('attivo', 'Cliente attivo', '#0e2340', 50, 0),
('inattivo', 'Cliente inattivo', '#7a8496', 60, 0);

CREATE TABLE clienti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  telefono VARCHAR(40) NULL,
  codice_fiscale VARCHAR(16) NULL,
  data_nascita DATE NULL,
  comune_id INT UNSIGNED NULL,
  indirizzo VARCHAR(190) NULL,
  note TEXT NULL,
  stato_id INT UNSIGNED NOT NULL,
  origine VARCHAR(12) NOT NULL DEFAULT 'sito',
  consenso_privacy TINYINT(1) NOT NULL DEFAULT 0,
  creato_il DATETIME NOT NULL,
  aggiornato_il DATETIME NOT NULL,
  KEY idx_clienti_email (email),
  KEY idx_clienti_nome (nome),
  CONSTRAINT fk_clienti_stato FOREIGN KEY (stato_id) REFERENCES stati_cliente(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clienti_comune FOREIGN KEY (comune_id) REFERENCES comuni(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE preventivi
  MODIFY stato VARCHAR(20) NOT NULL DEFAULT 'nuovo',
  ADD COLUMN cliente_id INT UNSIGNED NULL AFTER id,
  ADD COLUMN origine VARCHAR(12) NOT NULL DEFAULT 'sito' AFTER creato_il,
  ADD COLUMN creato_da VARCHAR(190) NULL AFTER origine,
  ADD COLUMN compagnia VARCHAR(80) NULL,
  ADD COLUMN decorrenza DATE NULL,
  ADD COLUMN valido_fino DATE NULL,
  ADD COLUMN messaggio TEXT NULL,
  ADD COLUMN righe_definitive TEXT NULL,
  ADD COLUMN totale_definitivo DECIMAL(10,2) NULL,
  ADD COLUMN token_hash CHAR(64) NULL,
  ADD COLUMN inviato_il DATETIME NULL,
  ADD COLUMN accettato_il DATETIME NULL,
  ADD UNIQUE KEY uq_preventivi_token (token_hash),
  ADD KEY idx_preventivi_cliente (cliente_id),
  ADD CONSTRAINT fk_preventivi_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE;

UPDATE preventivi SET stato = 'in_lavorazione' WHERE stato = 'contattato';

CREATE TABLE email_inviate (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NULL,
  preventivo_id INT UNSIGNED NULL,
  tipo VARCHAR(30) NOT NULL,
  destinatario VARCHAR(190) NOT NULL,
  oggetto VARCHAR(255) NOT NULL,
  esito VARCHAR(10) NOT NULL,
  errore VARCHAR(255) NULL,
  utente VARCHAR(190) NULL,
  creato_il DATETIME NOT NULL,
  KEY idx_email_data (creato_il),
  CONSTRAINT fk_email_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_preventivo FOREIGN KEY (preventivo_id) REFERENCES preventivi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO impostazioni (chiave, valore, descrizione) VALUES
('email_mittente_nome', 'ACAT Servizi', 'Nome del mittente nelle email ai clienti'),
('email_risposta', '', 'Indirizzo a cui rispondono i clienti (vuoto = la casella che invia)'),
('validita_giorni', '30', 'Giorni di validità proposti per il preventivo definitivo'),
('messaggio_preventivo', 'Abbiamo confrontato le proposte delle compagnie con cui lavoriamo e ti abbiamo preparato il preventivo qui sotto. Per accettarlo basta un clic sul pulsante: ti richiamiamo noi per firmare e completare la polizza.', 'Messaggio standard nella email del preventivo');
