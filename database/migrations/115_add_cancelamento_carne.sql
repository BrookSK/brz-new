-- Adicionar status 'cancelado' ao ENUM do carnê
ALTER TABLE carnes MODIFY COLUMN status ENUM(
    'aguardando_primeira_parcela',
    'ativo',
    'em_andamento',
    'com_atraso',
    'quitado',
    'inadimplente',
    'liberado_envio',
    'encerrado',
    'aviso_cancelamento',
    'cancelado'
) NOT NULL DEFAULT 'aguardando_primeira_parcela';

-- Campos de controle de cancelamento
ALTER TABLE carnes ADD COLUMN aviso_cancelamento_em DATETIME NULL;
ALTER TABLE carnes ADD COLUMN cancelado_em DATETIME NULL;
ALTER TABLE carnes ADD COLUMN motivo_cancelamento TEXT NULL;

-- Configs de cancelamento
INSERT IGNORE INTO configuracoes_sistema (chave, valor) VALUES
('carne_meses_atraso_cancelamento', '2'),
('carne_dias_aviso_cancelamento', '7');
