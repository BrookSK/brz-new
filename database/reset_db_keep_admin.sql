-- ATENÇÃO: Este script APAGA os dados do banco (mantém apenas usuários admin na tabela usuarios).
-- Use por sua conta e risco.
-- Recomendado: fazer backup antes de rodar.

-- Você pode rodar este arquivo sempre que quiser zerar o banco.

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_SAFE_UPDATES = 0;

-- Truncar todas as tabelas do schema atual, exceto 'usuarios'
DROP PROCEDURE IF EXISTS brz_reset_database_keep_admin;
DELIMITER $$
CREATE PROCEDURE brz_reset_database_keep_admin()
BEGIN
    DECLARE old_fk_checks INT DEFAULT 1;
    DECLARE done INT DEFAULT 0;
    DECLARE tbl VARCHAR(255);
    DECLARE cur CURSOR FOR
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
          AND table_name <> 'usuarios'
          AND table_name NOT IN ('configuracoes_sistema', 'configuracoes_moeda', 'email_templates', 'eventos_sistema', 'webhooks', 'auditoria_logs')
          AND table_name NOT LIKE 'configuracoes\_%'
          AND table_name NOT LIKE '%template%';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    -- Garantir que FKs fiquem desabilitadas também dentro do procedure
    SET old_fk_checks = @@FOREIGN_KEY_CHECKS;
    SET FOREIGN_KEY_CHECKS = 0;

    -- Mantém somente usuários admin (suporta colunas `role` ou `perfil`)
    SET @has_role := (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'usuarios'
          AND column_name = 'role'
    );
    SET @has_perfil := (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'usuarios'
          AND column_name = 'perfil'
    );

    IF @has_role > 0 THEN
        SET @del = 'DELETE FROM `usuarios` WHERE COALESCE(`role`, \'\') <> \'admin\';';
        PREPARE delstmt FROM @del;
        EXECUTE delstmt;
        DEALLOCATE PREPARE delstmt;
    ELSEIF @has_perfil > 0 THEN
        SET @del = 'DELETE FROM `usuarios` WHERE COALESCE(`perfil`, \'\') <> \'admin\';';
        PREPARE delstmt FROM @del;
        EXECUTE delstmt;
        DEALLOCATE PREPARE delstmt;
    END IF;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO tbl;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        -- Em alguns ambientes, TRUNCATE pode falhar por constraints de FK (erro 1701)
        -- Mesmo com FOREIGN_KEY_CHECKS=0. Usar DELETE + reset de AUTO_INCREMENT quando aplicável.
        SET @q = CONCAT('DELETE FROM `', tbl, '`;');
        PREPARE stmt FROM @q;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @has_ai := (
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = tbl
              AND extra LIKE '%auto_increment%'
        );
        IF @has_ai > 0 THEN
            SET @qai = CONCAT('ALTER TABLE `', tbl, '` AUTO_INCREMENT = 1;');
            PREPARE stmtai FROM @qai;
            EXECUTE stmtai;
            DEALLOCATE PREPARE stmtai;
        END IF;
    END LOOP;
    CLOSE cur;

    -- Ajustar AUTO_INCREMENT de usuarios para continuar após o maior id admin
    SET @max_id := (SELECT COALESCE(MAX(id), 0) FROM usuarios);
    SET @next_id := @max_id + 1;
    SET @q2 := CONCAT('ALTER TABLE `usuarios` AUTO_INCREMENT = ', @next_id, ';');
    PREPARE stmt2 FROM @q2;
    EXECUTE stmt2;
    DEALLOCATE PREPARE stmt2;

    -- Restaurar configuração original
    SET FOREIGN_KEY_CHECKS = old_fk_checks;
END$$
DELIMITER ;

CALL brz_reset_database_keep_admin();
DROP PROCEDURE IF EXISTS brz_reset_database_keep_admin;

SET FOREIGN_KEY_CHECKS = 1;
SET SQL_SAFE_UPDATES = 1;
