-- Classificazione delle polizze: tre aree (Auto, Ramo danni, Ramo vita) e le categorie sotto ciascuna,
-- con i rami ministeriali di riferimento (Codice delle assicurazioni private, art. 2).

CREATE TABLE categorie (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  chiave VARCHAR(30) NULL UNIQUE,
  nome VARCHAR(100) NOT NULL,
  codice VARCHAR(40) NULL,
  ordine SMALLINT NOT NULL DEFAULT 100,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_categorie_parent (parent_id),
  CONSTRAINT fk_categorie_parent FOREIGN KEY (parent_id) REFERENCES categorie(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorie (id, parent_id, chiave, nome, codice, ordine) VALUES
(1, NULL, 'auto', 'Auto', 'Rami 3, 10', 10),
(2, NULL, 'danni', 'Ramo danni', 'Rami 1-18', 20),
(3, NULL, 'vita', 'Ramo vita', 'Rami I-VI', 30);

INSERT INTO categorie (parent_id, chiave, nome, codice, ordine) VALUES
(1, 'rc_auto', 'RC auto e moto', 'Ramo 10', 10),
(1, 'cvt', 'Corpi veicoli terrestri (incendio, furto, kasko, cristalli, eventi)', 'Ramo 3', 20),
(1, 'infortuni_conducente', 'Infortuni del conducente', 'Ramo 1', 30),
(1, 'tutela_auto', 'Tutela legale auto', 'Ramo 17', 40),
(1, 'assistenza_auto', 'Assistenza stradale', 'Ramo 18', 50),
(1, 'natanti', 'Natanti (RC e corpi)', 'Rami 6, 12', 60),
(2, 'infortuni', 'Infortuni', 'Ramo 1', 10),
(2, 'salute', 'Malattia e salute', 'Ramo 2', 20),
(2, 'casa', 'Casa e famiglia (multirischi)', 'Rami 8, 9, 13', 30),
(2, 'incendio', 'Incendio ed elementi naturali', 'Ramo 8', 40),
(2, 'altri_danni_beni', 'Furto e altri danni ai beni', 'Ramo 9', 50),
(2, 'rc_generale', 'RC generale e professionale', 'Ramo 13', 60),
(2, 'impresa', 'Impresa, commercio e artigiani (multirischi)', 'Rami 8, 9, 13, 16', 70),
(2, 'trasporti', 'Trasporti e merci', 'Rami 4-7, 11', 80),
(2, 'credito_cauzioni', 'Credito e cauzioni', 'Rami 14, 15', 90),
(2, 'perdite_pecuniarie', 'Perdite pecuniarie', 'Ramo 16', 100),
(2, 'tutela_legale', 'Tutela legale', 'Ramo 17', 110),
(2, 'assistenza', 'Assistenza e viaggi', 'Ramo 18', 120),
(3, 'tcm', 'Caso morte e temporanee (TCM)', 'Ramo I', 10),
(3, 'rivalutabili', 'Risparmio: rivalutabili e gestioni separate', 'Ramo I', 20),
(3, 'unit_index', 'Investimento: unit e index linked', 'Ramo III', 30),
(3, 'multiramo', 'Multiramo', 'Rami I + III', 40),
(3, 'ltc', 'Long Term Care (non autosufficienza)', 'Ramo IV', 50),
(3, 'capitalizzazione', 'Capitalizzazione', 'Ramo V', 60),
(3, 'previdenza', 'Previdenza complementare (PIP, fondi pensione)', 'Rami I, III, VI', 70);

ALTER TABLE prodotti
  ADD COLUMN categoria_id INT UNSIGNED NULL AFTER compagnia_id,
  ADD CONSTRAINT fk_prodotti_categoria FOREIGN KEY (categoria_id) REFERENCES categorie(id) ON DELETE SET NULL;

UPDATE prodotti p JOIN categorie c ON c.chiave = CASE p.ramo
    WHEN 'rc_auto' THEN 'rc_auto' WHEN 'auto_cvt' THEN 'cvt' WHEN 'casa' THEN 'casa' WHEN 'famiglia' THEN 'casa'
    WHEN 'infortuni' THEN 'infortuni' WHEN 'salute' THEN 'salute' WHEN 'vita' THEN 'tcm' WHEN 'risparmio' THEN 'rivalutabili'
    WHEN 'impresa' THEN 'impresa' WHEN 'professionisti' THEN 'rc_generale' WHEN 'viaggi' THEN 'assistenza' ELSE NULL END
  SET p.categoria_id = c.id;

ALTER TABLE prodotti DROP COLUMN ramo;

ALTER TABLE polizze
  ADD COLUMN categoria_id INT UNSIGNED NULL AFTER prodotto_id,
  ADD CONSTRAINT fk_polizze_categoria FOREIGN KEY (categoria_id) REFERENCES categorie(id) ON DELETE SET NULL;

UPDATE polizze po JOIN prodotti pr ON pr.id = po.prodotto_id SET po.categoria_id = pr.categoria_id WHERE po.categoria_id IS NULL;
