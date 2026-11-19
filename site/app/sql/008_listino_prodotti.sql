-- Listino per prodotto: ogni prodotto (di una compagnia) ha le sue garanzie e regole di prezzo.

ALTER TABLE prodotti
  ADD COLUMN preventivabile TINYINT(1) NOT NULL DEFAULT 0 AFTER descrizione,
  ADD COLUMN veicoli VARCHAR(10) NOT NULL DEFAULT 'entrambi' AFTER preventivabile,
  ADD COLUMN premio_minimo DECIMAL(10,2) NULL AFTER veicoli,
  ADD COLUMN premio_massimo DECIMAL(10,2) NULL AFTER premio_minimo;

-- le garanzie appartengono a un prodotto; il codice è unico dentro il prodotto
ALTER TABLE garanzie
  ADD COLUMN prodotto_id INT UNSIGNED NULL AFTER id,
  DROP INDEX codice,
  ADD UNIQUE KEY uq_garanzie_prodotto (prodotto_id, codice),
  ADD CONSTRAINT fk_garanzie_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE;

-- modi di pagamento offerti dal prodotto, con maggiorazione (+) o sconto (-) in percentuale
CREATE TABLE prodotti_frazionamenti (
  prodotto_id INT UNSIGNED NOT NULL,
  frazionamento VARCHAR(12) NOT NULL,
  variazione DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (prodotto_id, frazionamento),
  CONSTRAINT fk_pf_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE,
  CONSTRAINT chk_pf_fraz CHECK (frazionamento IN ('annuale','semestrale','trimestrale','mensile'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- durate offerte (1 anno sempre; 2-5 anni = pluriennale) con sconto in percentuale
CREATE TABLE prodotti_durate (
  prodotto_id INT UNSIGNED NOT NULL,
  anni TINYINT UNSIGNED NOT NULL,
  sconto DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (prodotto_id, anni),
  CONSTRAINT fk_pd_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE,
  CONSTRAINT chk_pd_anni CHECK (anni BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sconti promozionali con validità (percentuale o importo fisso sul premio annuo)
CREATE TABLE promozioni (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prodotto_id INT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  tipo VARCHAR(12) NOT NULL DEFAULT 'percentuale',
  valore DECIMAL(10,2) NOT NULL,
  dal DATE NULL,
  al DATE NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_promo_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE,
  CONSTRAINT chk_promo_tipo CHECK (tipo IN ('percentuale','importo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- correzioni del prodotto sopra la base comune: moltiplicano l'RC quando la condizione è vera
CREATE TABLE prodotti_correzioni (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prodotto_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  eta_min TINYINT UNSIGNED NULL,
  eta_max TINYINT UNSIGNED NULL,
  chiave VARCHAR(80) NULL,
  moltiplicatore DECIMAL(6,3) NOT NULL DEFAULT 1,
  descrizione VARCHAR(120) NULL,
  CONSTRAINT fk_corr_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE,
  CONSTRAINT chk_corr_tipo CHECK (tipo IN ('eta','provincia','regione','marca','veicolo')),
  CONSTRAINT chk_corr_molt CHECK (moltiplicatore > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE preventivi
  ADD COLUMN prodotto_id INT UNSIGNED NULL AFTER modello_id,
  ADD COLUMN frazionamento VARCHAR(12) NULL AFTER prodotto_id,
  ADD COLUMN durata_anni TINYINT UNSIGNED NULL AFTER frazionamento,
  ADD CONSTRAINT fk_preventivi_prodotto FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE SET NULL;
