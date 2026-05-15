CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL,
    role ENUM('admin', 'comercial') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    bairro VARCHAR(120) NOT NULL,
    cep VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS negocios_comerciais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendedor_id INT UNSIGNED NOT NULL,
    vendedor_nome VARCHAR(120) NOT NULL,
    origem VARCHAR(50) NOT NULL,
    forma_contato VARCHAR(50) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    status VARCHAR(40) NOT NULL,
    proxima_acao VARCHAR(80) NOT NULL,
    motivo_perda VARCHAR(255) NULL,
    bairro VARCHAR(120) NULL,
    municipio VARCHAR(120) NULL,
    perfil VARCHAR(50) NOT NULL,
    nome_cliente VARCHAR(150) NOT NULL,
    cliente_id INT UNSIGNED NULL,
    telefone VARCHAR(120) NULL,
    email VARCHAR(150) NULL,
    servico VARCHAR(20) NOT NULL,
    total_cacambas INT UNSIGNED NOT NULL DEFAULT 0,
    valor_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_por_cacamba DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_perdido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_negocios_usuario FOREIGN KEY (vendedor_id) REFERENCES usuarios(id),
    CONSTRAINT fk_negocios_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migracao segura para ambientes existentes.
SET @has_idx_clientes_nome := (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'clientes'
            AND INDEX_NAME = 'idx_clientes_nome'
);
SET @sql := IF(@has_idx_clientes_nome = 0, 'CREATE INDEX idx_clientes_nome ON clientes (nome)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_cliente_id := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'cliente_id'
);
SET @sql := IF(@has_cliente_id = 0, 'ALTER TABLE negocios_comerciais ADD COLUMN cliente_id INT UNSIGNED NULL AFTER nome_cliente', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_telefone := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'telefone'
);
SET @sql := IF(@has_telefone = 0, 'ALTER TABLE negocios_comerciais ADD COLUMN telefone VARCHAR(120) NULL AFTER cliente_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_email := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'email'
);
SET @sql := IF(@has_email = 0, 'ALTER TABLE negocios_comerciais ADD COLUMN email VARCHAR(150) NULL AFTER telefone', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_valor_por_cacamba := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'valor_por_cacamba'
);
SET @sql := IF(@has_valor_por_cacamba = 0, 'ALTER TABLE negocios_comerciais ADD COLUMN valor_por_cacamba DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER valor_total', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_bairro := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'bairro'
);
SET @sql := IF(@has_bairro = 0, 'ALTER TABLE negocios_comerciais ADD COLUMN bairro VARCHAR(120) NULL AFTER motivo_perda', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_municipio := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'municipio'
);
SET @sql := IF(@has_municipio = 0, 'ALTER TABLE negocios_comerciais ADD COLUMN municipio VARCHAR(120) NULL AFTER bairro', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_regiao := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'regiao'
);
SET @has_bairro_now := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'bairro'
);
SET @sql := IF(@has_regiao = 1 AND @has_bairro_now = 1, 'UPDATE negocios_comerciais SET bairro = COALESCE(bairro, regiao) WHERE TRIM(COALESCE(bairro, "")) = ""', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk_negocios_cliente := (
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND CONSTRAINT_NAME = 'fk_negocios_cliente'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@has_fk_negocios_cliente = 0, 'ALTER TABLE negocios_comerciais ADD CONSTRAINT fk_negocios_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx_negocios_cliente_id := (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND INDEX_NAME = 'idx_negocios_cliente_id'
);
SET @sql := IF(@has_idx_negocios_cliente_id = 0, 'CREATE INDEX idx_negocios_cliente_id ON negocios_comerciais (cliente_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_contato := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'contato'
);
SET @has_telefone_now := (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'negocios_comerciais'
            AND COLUMN_NAME = 'telefone'
);
SET @sql := IF(@has_contato = 1 AND @has_telefone_now = 1, 'UPDATE negocios_comerciais SET telefone = COALESCE(telefone, contato) WHERE telefone IS NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_contato = 1, 'ALTER TABLE negocios_comerciais DROP COLUMN contato', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Senha temporaria padrao para seed: Trocar123!
-- Trocar imediatamente apos primeiro login.
INSERT INTO usuarios (username, display_name, role, password_hash, is_active)
VALUES
('admretec', 'Adm Retec', 'admin', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1),
('higor', 'Higor', 'comercial', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1),
('gustavo', 'Gustavo', 'comercial', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1),
('nicolas', 'Nicolas', 'comercial', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1),
('kaue', 'Kaue', 'comercial', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1),
('miguel', 'Miguel', 'comercial', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1),
('carla', 'Carla', 'comercial', '$2y$10$fnT/XF66HCCw2P0LSTrDwu1/fwSF4cggsN888J3x82ZR7Q2Mk96uO', 1)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    role = VALUES(role),
    is_active = VALUES(is_active);
