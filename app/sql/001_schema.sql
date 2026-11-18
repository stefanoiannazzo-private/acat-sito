-- Schema del preventivatore RC auto di ACAT Servizi (MySQL 8 / MariaDB 10.5+)

CREATE TABLE impostazioni (
  chiave VARCHAR(64) PRIMARY KEY,
  valore TEXT NULL,
  descrizione VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comuni (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codice_istat CHAR(6) NOT NULL UNIQUE,
  nome VARCHAR(100) NOT NULL,
  sigla CHAR(2) NOT NULL,
  provincia VARCHAR(60) NOT NULL,
  regione VARCHAR(60) NOT NULL,
  cap CHAR(5) NULL,
  coefficiente DECIMAL(6,3) NOT NULL DEFAULT 1.000,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_comuni_nome (nome),
  KEY idx_comuni_sigla (sigla),
  CONSTRAINT chk_comuni_coeff CHECK (coefficiente > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fasce_eta (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  eta_min TINYINT UNSIGNED NOT NULL,
  eta_max TINYINT UNSIGNED NOT NULL,
  tipo ENUM('percentuale','importo') NOT NULL DEFAULT 'percentuale',
  valore DECIMAL(10,2) NOT NULL DEFAULT 0,
  descrizione VARCHAR(120) NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_fasce_range CHECK (eta_min <= eta_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE marche (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  tipo_veicolo ENUM('auto','moto') NOT NULL DEFAULT 'auto',
  coefficiente DECIMAL(6,3) NOT NULL DEFAULT 1.000,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_marche (tipo_veicolo, nome),
  CONSTRAINT chk_marche_coeff CHECK (coefficiente > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modelli (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  marca_id INT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  anno_inizio SMALLINT UNSIGNED NULL,
  anno_fine SMALLINT UNSIGNED NULL,
  alimentazione VARCHAR(30) NULL,
  coefficiente DECIMAL(6,3) NOT NULL DEFAULT 1.000,
  valore_indicativo DECIMAL(10,2) NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_modelli (marca_id, nome),
  CONSTRAINT fk_modelli_marca FOREIGN KEY (marca_id) REFERENCES marche(id) ON DELETE RESTRICT,
  CONSTRAINT chk_modelli_coeff CHECK (coefficiente > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La garanzia principale (RC) è una sola; ogni altra garanzia deve dipendere da lei.
CREATE TABLE garanzie (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codice VARCHAR(30) NOT NULL UNIQUE,
  nome VARCHAR(80) NOT NULL,
  descrizione VARCHAR(255) NULL,
  principale TINYINT(1) NOT NULL DEFAULT 0,
  dipende_da INT UNSIGNED NULL,
  tipo_calcolo ENUM('fisso','perc_rc','perc_valore') NOT NULL DEFAULT 'fisso',
  valore DECIMAL(10,2) NOT NULL DEFAULT 0,
  applica_coefficienti TINYINT(1) NOT NULL DEFAULT 0,
  ordine SMALLINT NOT NULL DEFAULT 100,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_garanzie_rc FOREIGN KEY (dipende_da) REFERENCES garanzie(id) ON DELETE RESTRICT,
  CONSTRAINT chk_garanzie_legame CHECK ((principale = 1 AND dipende_da IS NULL) OR (principale = 0 AND dipende_da IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE preventivi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creato_il DATETIME NOT NULL,
  comune_id INT UNSIGNED NULL,
  comune_nome VARCHAR(120) NULL,
  data_nascita DATE NULL,
  eta TINYINT UNSIGNED NULL,
  modello_id INT UNSIGNED NULL,
  veicolo VARCHAR(200) NULL,
  garanzie TEXT NULL,
  dettaglio TEXT NULL,
  totale DECIMAL(10,2) NULL,
  nome VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  telefono VARCHAR(40) NULL,
  note TEXT NULL,
  consenso_privacy TINYINT(1) NOT NULL DEFAULT 0,
  stato ENUM('nuovo','contattato','chiuso') NOT NULL DEFAULT 'nuovo',
  ip_hash CHAR(64) NULL,
  KEY idx_preventivi_data (creato_il),
  KEY idx_preventivi_stato (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE utenti_admin (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  nome VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  ruolo ENUM('admin','operatore') NOT NULL DEFAULT 'operatore',
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_accesso DATETIME NULL,
  creato_il DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tentativi_accesso (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash CHAR(64) NOT NULL,
  email VARCHAR(190) NULL,
  creato_il DATETIME NOT NULL,
  KEY idx_tentativi (ip_hash, creato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE limiti_api (
  ip_hash CHAR(64) NOT NULL,
  finestra INT UNSIGNED NOT NULL,
  conteggio INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ip_hash, finestra)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE log_modifiche (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utente_id INT UNSIGNED NULL,
  utente VARCHAR(190) NULL,
  azione VARCHAR(20) NOT NULL,
  tabella VARCHAR(40) NOT NULL,
  record_id INT UNSIGNED NULL,
  dettagli TEXT NULL,
  creato_il DATETIME NOT NULL,
  KEY idx_log_data (creato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
