-- Migration: Oferta de Produto Gratuito Aleatório no Carrinho

-- Adicionar coluna elegivel_oferta_gratis na tabela produtos
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS elegivel_oferta_gratis TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Produto elegível para oferta gratuita no carrinho';

-- Configuração global: adicionar coluna direta na tabela configuracoes_sistema (schema single_row)
-- Se a tabela usar schema single_row (sem coluna categoria/chave), adicionar coluna direta
ALTER TABLE configuracoes_sistema ADD COLUMN IF NOT EXISTS oferta_gratuita_ativa TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ativar oferta de produto gratuito no carrinho';

-- Tabela para registrar ofertas já consumidas/recusadas por usuário
CREATE TABLE IF NOT EXISTS oferta_gratuita_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    produto_id INT DEFAULT NULL COMMENT 'Produto oferecido (NULL se não houve produto elegível)',
    categoria_id INT DEFAULT NULL COMMENT 'Categoria predominante do carrinho',
    acao ENUM('aceita', 'recusada', 'removida') NOT NULL,
    carrinho_id INT DEFAULT NULL,
    pedido_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colunas extras em carrinho_items para identificar item gratuito
ALTER TABLE carrinho_items ADD COLUMN IF NOT EXISTS is_free_offer TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE carrinho_items ADD COLUMN IF NOT EXISTS free_offer_original_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE carrinho_items ADD COLUMN IF NOT EXISTS free_offer_exempt_tax TINYINT(1) NOT NULL DEFAULT 0;

-- Colunas extras em pedido_itens para persistir dados do item gratuito
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS is_free_offer TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS free_offer_original_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS free_offer_exempt_tax TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS free_offer_tax_teorico DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS free_offer_taxa_servico DECIMAL(10,2) DEFAULT NULL;
