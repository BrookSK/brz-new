-- Migration 197: Trigger para impedir status "Caixa Fechada" sem medidas preenchidas
-- Proteção no nível do banco de dados (última barreira)
-- Impede que QUALQUER caminho (sistema, phpMyAdmin, query manual) 
-- coloque o pedido como produto_consolidado/consolidado sem peso+dimensões.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_pedidos_bloquear_caixa_fechada_sem_medidas$$

CREATE TRIGGER trg_pedidos_bloquear_caixa_fechada_sem_medidas
BEFORE UPDATE ON pedidos
FOR EACH ROW
BEGIN
    -- Só valida se o status está sendo ALTERADO para um dos status de ciclo fechado
    IF NEW.status != OLD.status 
       AND LOWER(TRIM(NEW.status)) IN (
           'produto_consolidado', 
           'consolidado', 
           'em_transporte', 
           'aguardando_liberacao_aduaneira', 
           'enviado_ao_destinatario', 
           'enviado', 
           'entregue'
       ) THEN
        IF (COALESCE(NEW.peso_total, 0) <= 0 
            OR COALESCE(NEW.altura, 0) <= 0 
            OR COALESCE(NEW.largura, 0) <= 0 
            OR COALESCE(NEW.comprimento, 0) <= 0) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Não é possível alterar para Caixa Fechada (ou status seguintes) sem preencher peso_total, altura, largura e comprimento.';
        END IF;
    END IF;
END$$

DELIMITER ;
