-- Script para criar a tabela obras
-- Execute este script no seu banco de dados MySQL

CREATE TABLE IF NOT EXISTS obras (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    localizacao VARCHAR(500),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    cidade VARCHAR(100) NOT NULL,
    uf CHAR(2) NOT NULL,
    status ENUM('planejamento', 'execucao', 'concluida', 'suspensa', 'cancelada') NOT NULL DEFAULT 'planejamento',
    situacao ENUM('normal', 'atrasada', 'emergencia', 'prioritaria') NOT NULL DEFAULT 'normal',
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_inicio DATE NULL,
    data_prevista DATE NULL,
    data_conclusao DATE NULL,
    orcamento_total DECIMAL(15, 2) NULL,
    orcamento_utilizado DECIMAL(15, 2) NULL DEFAULT 0.00,
    responsavel VARCHAR(255) NULL,
    observacoes TEXT NULL,
    PRIMARY KEY (id),
    INDEX idx_status (status),
    INDEX idx_situacao (situacao),
    INDEX idx_cidade (cidade),
    INDEX idx_uf (uf),
    INDEX idx_data_cadastro (data_cadastro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserir dados de exemplo
INSERT INTO obras (
    nome, 
    descricao, 
    localizacao, 
    latitude, 
    longitude, 
    cidade, 
    uf, 
    status, 
    situacao, 
    data_inicio, 
    data_prevista, 
    orcamento_total, 
    responsavel) 
VALUES(
    'Rede de Esgoto', 
    'Instalação de rede coletora de esgoto na cidade', 
    'Av. Tânus Saliba, 151', 
    -19.954317548676865, 
    -44.34148894824422 
    'Juatuba', 
    'MG', 
    'Em execucao', 
    'Concluída', 
    '2024-01-15', 
    '2024-06-30', 
    2500000.00, 
    'Eng. João Silva'
    ),
    (
    'Rede de Esgoto', 
    'Instalação de rede coletora de esgoto no centro da cidade', 
    'R. José Issy, 115', 
    -16.743212560420353, 
    -48.516516992690285
    'Vianópolis', 
    'GO', 
    'execucao', 
    'normal', 
    '2024-01-15', 
    '2024-06-30', 
    2500000.00, 
    'Eng. José Pereira'
    )

-- Verificar se a tabela foi criada corretamente
DESCRIBE obras;

-- Mostrar alguns registros de exemplo
SELECT * FROM obras LIMIT 5;
