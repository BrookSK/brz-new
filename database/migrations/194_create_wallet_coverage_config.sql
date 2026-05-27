-- Migration: Configuração de cobertura da carteira + histórico de alterações
-- A carteira pode cobrir: 'subtotal_taxa' (subtotal + taxa_servico) ou 'subtotal_taxa_impostos' (subtotal + taxa_servico + impostos + imposto_local)

-- Inserir configuração padrão (modo atual: subtotal + taxa_servico)
INSERT IGNORE INTO configuracoes_sistema (chave, valor, descricao)
VALUES ('carteira_cobertura_modo', 'subtotal_taxa', 'Modo de cobertura da carteira: subtotal_taxa (subtotal + taxa de serviço) ou subtotal_taxa_impostos (subtotal + taxa + impostos)');

-- Tabela de histórico de alterações
CREATE TABLE IF NOT EXISTS `carteira_cobertura_historico` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `modo_anterior` varchar(50) NOT NULL,
    `modo_novo` varchar(50) NOT NULL,
    `alterado_por` int(11) DEFAULT NULL COMMENT 'usuario_id do admin',
    `alterado_por_nome` varchar(200) DEFAULT NULL,
    `motivo` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
