-- Cria tabela para persistir opções selecionadas por produto (tipo -> opções)
-- Idempotente: pode ser executado múltiplas vezes sem erro

CREATE TABLE IF NOT EXISTS produto_atributo_opcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    tipo_id INT NOT NULL,
    opcao_id INT NOT NULL,
    created_at DATETIME NULL,
    UNIQUE KEY uq_produto_atributo_opcao (produto_id, tipo_id, opcao_id),
    KEY idx_pao_produto (produto_id),
    KEY idx_pao_tipo (tipo_id),
    KEY idx_pao_opcao (opcao_id)
);
