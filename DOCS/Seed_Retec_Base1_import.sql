-- Import script for DOCS/Seed_Retec_Base1.csv into negocios_comerciais
-- MySQL 8+ / MariaDB compatible (uses basic SQL functions only)
--
-- Header mapping (CSV -> DB):
-- VENDEDOR -> vendedor_nome (+ vendedor_id via usuarios.display_name)
-- Origem -> origem
-- Forma de Contato -> forma_contato
-- DATA Inicio -> data_inicio
-- DATA Fim -> data_fim
-- Status -> status
-- Proxima acao -> proxima_acao
-- Motivo Perda -> motivo_perda
-- Bairro -> bairro
-- Municipio -> municipio
-- Perfil -> perfil
-- Nome -> nome_cliente
-- Contato -> telefone
-- Servico -> servico
-- Observacao -> observacao
--
-- DB fields without source in CSV (fixed defaults):
-- cliente_id=NULL, email=NULL, cep=NULL,
-- total_cacambas=0, valor_total=0.00, valor_por_cacamba=0.00, valor_perdido=0.00

START TRANSACTION;

DROP TABLE IF EXISTS seed_retec_base1_staging;
CREATE TABLE seed_retec_base1_staging (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendedor VARCHAR(120) NULL,
    origem VARCHAR(50) NULL,
    forma_contato VARCHAR(50) NULL,
    data_inicio_raw VARCHAR(20) NULL,
    data_fim_raw VARCHAR(20) NULL,
    status_raw VARCHAR(60) NULL,
    proxima_acao_raw VARCHAR(100) NULL,
    motivo_perda_raw VARCHAR(255) NULL,
    bairro_raw VARCHAR(120) NULL,
    municipio_raw VARCHAR(120) NULL,
    perfil_raw VARCHAR(60) NULL,
    nome_raw VARCHAR(150) NULL,
    contato_raw VARCHAR(120) NULL,
    servico_raw VARCHAR(20) NULL,
    observacao_raw TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If LOCAL INFILE is disabled, enable in your connection/session first.
-- This CSV has trailing ';' at end of each line, so one extra column is consumed into @c16.
LOAD DATA LOCAL INFILE '/home/arsilva/REPO/lp_retecsp/DOCS/Seed_Retec_Base1.csv'
INTO TABLE seed_retec_base1_staging
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ';'
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(@c1, @c2, @c3, @c4, @c5, @c6, @c7, @c8, @c9, @c10, @c11, @c12, @c13, @c14, @c15, @c16)
SET
    vendedor = NULLIF(TRIM(@c1), ''),
    origem = NULLIF(TRIM(@c2), ''),
    forma_contato = NULLIF(TRIM(@c3), ''),
    data_inicio_raw = NULLIF(TRIM(@c4), ''),
    data_fim_raw = NULLIF(TRIM(@c5), ''),
    status_raw = NULLIF(TRIM(@c6), ''),
    proxima_acao_raw = NULLIF(TRIM(@c7), ''),
    motivo_perda_raw = NULLIF(TRIM(@c8), ''),
    bairro_raw = NULLIF(TRIM(@c9), ''),
    municipio_raw = NULLIF(TRIM(@c10), ''),
    perfil_raw = NULLIF(TRIM(@c11), ''),
    nome_raw = NULLIF(TRIM(@c12), ''),
    contato_raw = NULLIF(TRIM(@c13), ''),
    servico_raw = NULLIF(TRIM(@c14), ''),
    observacao_raw = NULLIF(TRIM(@c15), '');

-- Main insert with transformations and seller-id mapping.
INSERT INTO negocios_comerciais (
    vendedor_id,
    vendedor_nome,
    origem,
    forma_contato,
    data_inicio,
    data_fim,
    status,
    proxima_acao,
    motivo_perda,
    cep,
    bairro,
    municipio,
    perfil,
    nome_cliente,
    cliente_id,
    telefone,
    email,
    servico,
    total_cacambas,
    valor_total,
    valor_por_cacamba,
    valor_perdido,
    observacao
)
SELECT
    u.id AS vendedor_id,
    t.vendedor_nome,
    t.origem,
    t.forma_contato,
    t.data_inicio,
    t.data_fim,
    t.status_norm,
    t.proxima_acao,
    t.motivo_perda,
    NULL AS cep,
    t.bairro,
    t.municipio,
    t.perfil,
    t.nome_cliente,
    NULL AS cliente_id,
    t.telefone,
    NULL AS email,
    t.servico,
    0 AS total_cacambas,
    0.00 AS valor_total,
    0.00 AS valor_por_cacamba,
    0.00 AS valor_perdido,
    t.observacao
FROM (
    SELECT
        s.id,
        TRIM(s.vendedor) AS vendedor_nome,
        CASE
            WHEN LOWER(TRIM(s.vendedor)) IN ('kaue', 'kauê') THEN 'Kaue'
            WHEN LOWER(TRIM(s.vendedor)) = 'higor' THEN 'Higor'
            WHEN LOWER(TRIM(s.vendedor)) = 'gustavo' THEN 'Gustavo'
            WHEN LOWER(TRIM(s.vendedor)) = 'nicolas' THEN 'Nicolas'
            WHEN LOWER(TRIM(s.vendedor)) = 'miguel' THEN 'Miguel'
            WHEN LOWER(TRIM(s.vendedor)) = 'carla' THEN 'Carla'
            ELSE TRIM(s.vendedor)
        END AS vendedor_nome_lookup,
        COALESCE(NULLIF(TRIM(s.origem), ''), 'Google') AS origem,
        COALESCE(NULLIF(TRIM(s.forma_contato), ''), 'Whatsapp') AS forma_contato,
        CASE
            WHEN NULLIF(TRIM(s.data_inicio_raw), '') IS NULL THEN NULL
            ELSE STR_TO_DATE(
                CASE
                    WHEN TRIM(s.data_inicio_raw) = '22/42026' THEN '22/04/2026'
                    ELSE REPLACE(REPLACE(TRIM(s.data_inicio_raw), '/0226', '/2026'), '/2022', '/2026')
                END,
                '%d/%m/%Y'
            )
        END AS data_inicio,
        CASE
            WHEN NULLIF(TRIM(s.data_fim_raw), '') IS NULL THEN NULL
            ELSE STR_TO_DATE(
                CASE
                    WHEN TRIM(s.data_fim_raw) = '22/42026' THEN '22/04/2026'
                    ELSE REPLACE(REPLACE(TRIM(s.data_fim_raw), '/0226', '/2026'), '/2022', '/2026')
                END,
                '%d/%m/%Y'
            )
        END AS data_fim,
        CASE
            WHEN LOWER(TRIM(s.status_raw)) = 'em negociação' THEN 'Em negociacao'
            WHEN LOWER(TRIM(s.status_raw)) = 'em negociacao' THEN 'Em negociacao'
            WHEN LOWER(TRIM(s.status_raw)) = 'venda perdida' THEN 'Venda Perdida'
            WHEN LOWER(TRIM(s.status_raw)) = 'venda realizada' THEN 'Venda Realizada'
            ELSE COALESCE(NULLIF(TRIM(s.status_raw), ''), 'Em negociacao')
        END AS status_norm,
        CASE
            WHEN NULLIF(TRIM(s.proxima_acao_raw), '') IS NOT NULL THEN TRIM(s.proxima_acao_raw)
            WHEN LOWER(TRIM(s.status_raw)) = 'venda perdida' THEN 'Pos-perda'
            WHEN LOWER(TRIM(s.status_raw)) = 'venda realizada' THEN 'Pos-venda'
            ELSE 'Acompanhar Follow-up'
        END AS proxima_acao,
        CASE
            WHEN TRIM(COALESCE(s.motivo_perda_raw, '')) IN ('', '-') THEN NULL
            ELSE TRIM(s.motivo_perda_raw)
        END AS motivo_perda,
        CASE
            WHEN TRIM(COALESCE(s.bairro_raw, '')) IN ('', '-') THEN NULL
            ELSE TRIM(s.bairro_raw)
        END AS bairro,
        CASE
            WHEN TRIM(COALESCE(s.municipio_raw, '')) IN ('', '-') THEN NULL
            ELSE TRIM(s.municipio_raw)
        END AS municipio,
        COALESCE(NULLIF(TRIM(s.perfil_raw), ''), 'Pessoal Fisica') AS perfil,
        COALESCE(NULLIF(TRIM(s.nome_raw), ''), 'Nao informado') AS nome_cliente,
        CASE
            WHEN TRIM(COALESCE(s.contato_raw, '')) IN ('', '-') THEN NULL
            ELSE TRIM(s.contato_raw)
        END AS telefone,
        COALESCE(NULLIF(TRIM(s.servico_raw), ''), '4m') AS servico,
        CASE
            WHEN TRIM(COALESCE(s.observacao_raw, '')) IN ('', '-') THEN NULL
            ELSE TRIM(s.observacao_raw)
        END AS observacao
    FROM seed_retec_base1_staging s
) t
INNER JOIN usuarios u
    ON u.display_name COLLATE utf8mb4_unicode_ci = t.vendedor_nome_lookup COLLATE utf8mb4_unicode_ci
WHERE t.data_inicio IS NOT NULL;

COMMIT;

-- Validation queries (run after import)
SELECT COUNT(*) AS staging_rows FROM seed_retec_base1_staging;

SELECT
    COUNT(*) AS unmatched_sellers
FROM seed_retec_base1_staging s
LEFT JOIN usuarios u
    ON u.display_name COLLATE utf8mb4_unicode_ci = (
        CASE
            WHEN LOWER(TRIM(s.vendedor)) IN ('kaue', 'kauê') THEN 'Kaue'
            WHEN LOWER(TRIM(s.vendedor)) = 'higor' THEN 'Higor'
            WHEN LOWER(TRIM(s.vendedor)) = 'gustavo' THEN 'Gustavo'
            WHEN LOWER(TRIM(s.vendedor)) = 'nicolas' THEN 'Nicolas'
            WHEN LOWER(TRIM(s.vendedor)) = 'miguel' THEN 'Miguel'
            WHEN LOWER(TRIM(s.vendedor)) = 'carla' THEN 'Carla'
            ELSE TRIM(s.vendedor)
        END
    ) COLLATE utf8mb4_unicode_ci
WHERE u.id IS NULL;

SELECT vendedor_nome, status, COUNT(*) AS total
FROM negocios_comerciais
GROUP BY vendedor_nome, status
ORDER BY vendedor_nome, status;
