-- Anagrafiche: persone fisiche e aziende, sedi delle aziende, legami persona-azienda.
-- "nome" resta il nome completo mostrato ovunque (nome + cognome, oppure ragione sociale).
ALTER TABLE clienti
  ADD COLUMN tipo VARCHAR(8) NOT NULL DEFAULT 'persona' AFTER id,
  ADD COLUMN nome_proprio VARCHAR(80) NULL AFTER nome,
  ADD COLUMN cognome VARCHAR(80) NULL AFTER nome_proprio,
  ADD COLUMN partita_iva VARCHAR(16) NULL AFTER codice_fiscale,
  ADD COLUMN pec VARCHAR(190) NULL AFTER email,
  ADD COLUMN codice_sdi VARCHAR(7) NULL AFTER partita_iva,
  ADD COLUMN cap VARCHAR(10) NULL AFTER indirizzo,
  ADD COLUMN citta VARCHAR(100) NULL AFTER cap,
  ADD COLUMN provincia VARCHAR(60) NULL AFTER citta,
  ADD COLUMN nazione VARCHAR(60) NULL AFTER provincia,
  ADD INDEX idx_clienti_tipo (tipo),
  ADD CONSTRAINT chk_clienti_tipo CHECK (tipo IN ('persona','azienda'));

-- chi ha un codice di 11 cifre è un'azienda (partita IVA / CF numerico)
UPDATE clienti SET tipo = 'azienda', partita_iva = codice_fiscale WHERE codice_fiscale REGEXP '^[0-9]{11}$';

-- persone: nome = prima parola, cognome = il resto (da controllare a mano nei casi dubbi)
UPDATE clienti SET
  nome_proprio = SUBSTRING_INDEX(TRIM(nome), ' ', 1),
  cognome = NULLIF(TRIM(SUBSTRING(TRIM(nome), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(nome), ' ', 1)) + 1)), '')
WHERE tipo = 'persona';

CREATE TABLE clienti_sedi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  indirizzo VARCHAR(190) NULL,
  cap VARCHAR(10) NULL,
  citta VARCHAR(100) NULL,
  provincia VARCHAR(60) NULL,
  nazione VARCHAR(60) NULL,
  telefono VARCHAR(40) NULL,
  note VARCHAR(255) NULL,
  CONSTRAINT fk_sedi_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clienti_legami (
  persona_id INT UNSIGNED NOT NULL,
  azienda_id INT UNSIGNED NOT NULL,
  ruolo VARCHAR(80) NULL,
  PRIMARY KEY (persona_id, azienda_id),
  INDEX idx_legami_azienda (azienda_id),
  CONSTRAINT fk_legami_persona FOREIGN KEY (persona_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_legami_azienda FOREIGN KEY (azienda_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
