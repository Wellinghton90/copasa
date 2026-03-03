-- Tabela só para respostas a mensagens (não vira card de nova mensagem).
-- mensagem_id = id da mensagem em sistema_mensagens que está sendo respondida.
CREATE TABLE IF NOT EXISTS sistema_respostas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    mensagem_id INT(11) NOT NULL,
    usuario_id INT(5) NOT NULL,
    usuario VARCHAR(150) NOT NULL,
    texto TEXT NOT NULL,
    data DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (id),
    KEY idx_mensagem_id (mensagem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
