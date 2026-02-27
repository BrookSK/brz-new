-- Ajusta índice UNIQUE para suportar múltiplos componentes (produto/imposto) compartilhando o mesmo payment_id
-- Necessário para Mercado Pago marketplace split (application_fee) no /v1/payments

-- Remove índice antigo (se existir)
ALTER TABLE pedido_pagamentos DROP INDEX uk_pp_gateway_payment;

-- Cria índice novo por componente
ALTER TABLE pedido_pagamentos
  ADD UNIQUE KEY uk_pp_gateway_payment_comp (gateway, payment_id, componente);
