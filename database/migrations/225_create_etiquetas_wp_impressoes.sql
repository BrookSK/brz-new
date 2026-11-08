-- Controle de impressão das etiquetas do Correio Internacional (/admin/etiquetas-wp).
-- Uma linha por pacote do WordPress (wp_post_id). Atualizada toda vez que o PDF da etiqueta é baixado.
-- O controller também cria a tabela sozinho (CREATE TABLE IF NOT EXISTS) se esta migration não rodar.
CREATE TABLE IF NOT EXISTS etiquetas_wp_impressoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wp_post_id INT NOT NULL,
    pedido_id INT NULL,
    tracking_code VARCHAR(120) NULL,
    vezes INT NOT NULL DEFAULT 1,
    primeira_impressao_em DATETIME NOT NULL,
    ultima_impressao_em DATETIME NOT NULL,
    ultimo_usuario_id INT NULL,
    UNIQUE KEY uniq_wp_post (wp_post_id),
    KEY idx_pedido (pedido_id),
    KEY idx_tracking (tracking_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
