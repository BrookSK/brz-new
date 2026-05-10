-- Adicionar status 'cancelada' às parcelas do carnê
ALTER TABLE carne_parcelas MODIFY COLUMN status ENUM(
    'pendente',
    'aguardando_pagamento',
    'parcialmente_paga',
    'paga',
    'vencida',
    'em_atraso',
    'reemitida',
    'cancelada'
) NOT NULL DEFAULT 'pendente';

-- Adicionar status 'cancelado' ao carnê
ALTER TABLE carnes MODIFY COLUMN status ENUM(
    'aguardando_primeira_parcela',
    'ativo',
    'em_andamento',
    'com_atraso',
    'quitado',
    'inadimplente',
    'liberado_envio',
    'encerrado',
    'cancelado'
) NOT NULL DEFAULT 'aguardando_primeira_parcela';
