-- Script para criar a tabela gemeo_digital
-- Armazena projetos (gêmeos digitais) sincronizados da pasta projetos/{cidade}
-- Execute este script no banco de dados MySQL
--
-- Se a tabela já existir sem a coluna cidade, execute:
-- ALTER TABLE gemeo_digital ADD COLUMN cidade VARCHAR(100) NOT NULL DEFAULT '';
-- ALTER TABLE gemeo_digital ADD UNIQUE KEY uk_cidade_nome (cidade, nome_projeto);
-- ALTER TABLE gemeo_digital ADD INDEX idx_cidade (cidade);

CREATE TABLE IF NOT EXISTS gemeo_digital (
    id_gemeo INT(11) NOT NULL AUTO_INCREMENT,
    data_processamento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inicio_inspecao DATETIME NULL,
    fim_inspecao DATETIME NULL,
    nome_projeto VARCHAR(255) NOT NULL,
    caminho_p4d VARCHAR(255) NOT NULL DEFAULT '',
    caminho_ortofoto VARCHAR(255) NOT NULL DEFAULT '',
    caminho_3d VARCHAR(255) NOT NULL DEFAULT '',
    quantidade_fotos INT(4) NOT NULL DEFAULT 0,
    altura_media FLOAT NULL DEFAULT 0,
    angulo_camera FLOAT NULL DEFAULT 0,
    latitude_central FLOAT NULL DEFAULT 0,
    longitude_central FLOAT NULL DEFAULT 0,
    altitude_inicial FLOAT NULL DEFAULT 0,
    cidade VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_gemeo),
    UNIQUE KEY uk_cidade_nome (cidade, nome_projeto),
    INDEX idx_cidade (cidade),
    INDEX idx_data_processamento (data_processamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
