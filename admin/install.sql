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
    regiao VARCHAR(120) NULL,
    perfil VARCHAR(50) NOT NULL,
    nome_cliente VARCHAR(150) NOT NULL,
    contato VARCHAR(120) NOT NULL,
    servico VARCHAR(20) NOT NULL,
    total_cacambas INT UNSIGNED NOT NULL DEFAULT 0,
    valor_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_perdido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_negocios_usuario FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
