-- Polizze stipulate: compagnie, prodotti, polizze dei clienti, documenti, avvisi di scadenza.

CREATE TABLE compagnie (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL UNIQUE,
  portale_url VARCHAR(255) NULL,
  telefono VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  codice_agenzia VARCHAR(60) NULL,
  note TEXT NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO compagnie (nome, portale_url) VALUES
('Allianz', 'https://www.allianz.it'),
('Generali', 'https://www.generali.it'),
('Italiana Assicurazioni', 'https://www.italiana.it'),
('Prima Assicurazioni', 'https://www.prima.it'),
('AXA', 'https://www.axa.it');

CREATE TABLE prodotti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compagnia_id INT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  ramo VARCHAR(30) NOT NULL DEFAULT 'altro',
  descrizione VARCHAR(255) NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_prodotti (compagnia_id, nome),
  CONSTRAINT fk_prodotti_compagnia FOREIGN KEY (compagnia_id) REFERENCES compagnie(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polizze (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  compagnia_id INT UNSIGNED NOT NULL,
  prodotto_id INT UNSIGNED NULL,
  preventivo_id INT UNSIGNED NULL,
  numero VARCHAR(60) NULL,
  oggetto VARCHAR(190) NULL,
  premio_annuo DECIMAL(10,2) NULL,
  frazionamento VARCHAR(12) NOT NULL DEFAULT 'annuale',
  data_stipula DATE NULL,
  scadenza DATE NOT NULL,
  avvisa_cliente TINYINT(1) NOT NULL DEFAULT 1,
  stato VARCHAR(12) NOT NULL DEFAULT 'attiva',
  note TEXT NULL,
  creato_il DATETIME NOT NULL,
  aggiornato_il DATETIME NOT NULL,
  KEY idx_polizze_cliente (cliente_id),
  KEY idx_polizze_scadenza (stato, scadenza),
  CONSTRAINT fk_polizze_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_polizze_compagnia FOREIGN KEY (compagnia_id) REFERENCES compagnie(id) ON DELETE RESTRICT,
  CONSTRAINT fk_polizze_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE SET NULL,
  CONSTRAINT fk_polizze_preventivo FOREIGN KEY (preventivo_id) REFERENCES preventivi(id) ON DELETE SET NULL,
  CONSTRAINT chk_polizze_fraz CHECK (frazionamento IN ('annuale','semestrale','trimestrale','mensile'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documenti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  polizza_id INT UNSIGNED NULL,
  nome VARCHAR(190) NOT NULL,
  file VARCHAR(80) NOT NULL UNIQUE,
  mime VARCHAR(100) NOT NULL,
  dimensione INT UNSIGNED NOT NULL,
  descrizione VARCHAR(190) NULL,
  caricato_da VARCHAR(190) NULL,
  creato_il DATETIME NOT NULL,
  KEY idx_documenti_cliente (cliente_id),
  CONSTRAINT fk_documenti_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_documenti_polizza FOREIGN KEY (polizza_id) REFERENCES polizze(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un avviso per polizza, scadenza e destinatario: impedisce i doppi invii.
CREATE TABLE avvisi_scadenza (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  polizza_id INT UNSIGNED NOT NULL,
  evento VARCHAR(12) NOT NULL,
  data_evento DATE NOT NULL,
  destinatario VARCHAR(10) NOT NULL,
  email VARCHAR(190) NULL,
  esito VARCHAR(10) NOT NULL,
  tentativi TINYINT UNSIGNED NOT NULL DEFAULT 1,
  errore VARCHAR(255) NULL,
  creato_il DATETIME NOT NULL,
  UNIQUE KEY uq_avvisi (polizza_id, evento, data_evento, destinatario),
  CONSTRAINT fk_avvisi_polizza FOREIGN KEY (polizza_id) REFERENCES polizze(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO impostazioni (chiave, valore, descrizione) VALUES
('avvisi_attivi', '1', '1 = invia gli avvisi di scadenza delle polizze'),
('avvisi_destinatari', 'agenzia', 'agenzia = solo all''agenzia; agenzia_cliente = all''agenzia e al cliente (se la polizza lo prevede)'),
('avvisi_email_agenzia', '', 'Indirizzo dell''agenzia per gli avvisi di scadenza (vuoto = quello degli avvisi generali)'),
('avvisi_giorni_agenzia', '15', 'Giorni di anticipo per l''avviso all''agenzia'),
('avvisi_giorni_cliente', '15', 'Giorni di anticipo per l''avviso al cliente'),
('avvisi_ora', '9', 'Ora del giorno (0-23) da cui partono gli avvisi'),
('avvisi_rate', '1', '1 = avvisa anche per le rate semestrali, trimestrali e mensili'),
('avvisi_oggetto_agenzia', 'Scadenza {tipo_scadenza} il {scadenza}: {cliente} ({compagnia})', 'Oggetto dell''avviso all''agenzia'),
('avvisi_testo_agenzia', 'Il cliente {cliente} ha la polizza {prodotto} di {compagnia}{numero} che scade il {scadenza} ({tipo_scadenza}). Frazionamento: {frazionamento}. Premio annuo: {premio}.\n\nContatti del cliente: {email_cliente} · {telefono_cliente}', 'Testo dell''avviso all''agenzia'),
('avvisi_oggetto_cliente', 'La tua polizza {prodotto} scade il {scadenza}', 'Oggetto dell''avviso al cliente'),
('avvisi_testo_cliente', 'Gentile {cliente},\nti ricordiamo che la tua polizza {prodotto} di {compagnia}{numero} scade il {scadenza}.\n\nPer rinnovarla o valutare insieme altre soluzioni chiamaci al 329 754 2063 oppure rispondi a questa email: ci pensiamo noi.', 'Testo dell''avviso al cliente'),
('documenti_max_mb', '10', 'Dimensione massima di un documento caricato (MB)');
