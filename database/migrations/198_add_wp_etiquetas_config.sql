-- Migration: Adicionar configurações para integração com WordPress Etiquetas
-- O sistema agora faz requisições para o WordPress para criar etiquetas, containers, faturas e embarques.

INSERT INTO configuracoes_sistema (chave, valor) VALUES 
('wp_etiquetas_url', 'https://etiquetas.brazilianashop.com.br'),
('wp_etiquetas_api_key', 'hBkXYUYPe5bvjujrI4W9r5yCWSVekw7ao529uGYbkBEyknW4tPhsx8mHMIANyNXl')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
