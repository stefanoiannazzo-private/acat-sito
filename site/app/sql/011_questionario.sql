-- Polizze non auto (vita e danni): premio = capitale assicurato × tasso (per mille),
-- poi le risposte al questionario del prodotto aggiungono o tolgono una % o un importo fisso.
ALTER TABLE prodotti
  ADD COLUMN tasso DECIMAL(10,4) NULL AFTER veicoli,
  ADD COLUMN capitale_minimo DECIMAL(12,2) NULL AFTER tasso,
  ADD COLUMN capitale_massimo DECIMAL(12,2) NULL AFTER capitale_minimo;

CREATE TABLE prodotti_domande (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prodotto_id INT UNSIGNED NOT NULL,
  testo VARCHAR(255) NOT NULL,
  aiuto VARCHAR(255) NULL,
  ordine SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  CONSTRAINT fk_domande_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prodotti_risposte (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  domanda_id INT UNSIGNED NOT NULL,
  testo VARCHAR(160) NOT NULL,
  tipo VARCHAR(12) NOT NULL DEFAULT 'percentuale',
  valore DECIMAL(10,2) NOT NULL DEFAULT 0,
  ordine SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  CONSTRAINT fk_risposte_domanda FOREIGN KEY (domanda_id) REFERENCES prodotti_domande(id) ON DELETE CASCADE,
  CONSTRAINT chk_risposte_tipo CHECK (tipo IN ('percentuale','importo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE preventivi
  ADD COLUMN categoria_id INT UNSIGNED NULL AFTER prodotto_id,
  ADD COLUMN capitale DECIMAL(12,2) NULL AFTER categoria_id;
