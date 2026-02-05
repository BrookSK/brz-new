<?php
namespace App\Controllers;

class AdminUsuariosHelper {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $this->verificarCriarTabelaUsuarios();
    }
    
    private function verificarCriarTabelaUsuarios() {
        try {
            $this->pdo->exec("\
                CREATE TABLE IF NOT EXISTS `usuarios` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `nome` varchar(255) NOT NULL,
                    `email` varchar(255) NOT NULL,
                    `senha` varchar(255) NOT NULL,
                    `perfil` varchar(50) DEFAULT 'cliente',
                    `cpf` varchar(14) DEFAULT NULL,
                    `telefone` varchar(20) DEFAULT NULL,
                    `ativo` tinyint(1) DEFAULT 1,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_email` (`email`),
                    KEY `idx_ativo` (`ativo`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Se a tabela já existia sem a coluna perfil, tenta adicioná-la.
            try {
                $cols = $this->getColunasUsuarios();
                if (is_array($cols) && !in_array('perfil', $cols, true)) {
                    $this->pdo->exec("ALTER TABLE `usuarios` ADD COLUMN `perfil` varchar(50) DEFAULT 'cliente'");
                }
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
            // Silenciar erros de criação de tabela
        }
    }

    private function slugify(string $text): string {
        $text = trim((string) $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = strtolower((string) $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');
        if ($text === '') {
            $text = 'rep';
        }
        return substr($text, 0, 120);
    }
    
    public function getUsuariosComCarteira($busca = '', $limite = 12, $offset = 0) {
        $sql = "SELECT u.*, 
                       COALESCE(w.saldo_usd, 0) as carteira_usd,
                       COALESCE(w.saldo_brl, 0) as carteira_brl
                FROM usuarios u 
                LEFT JOIN carteiras w ON u.id = w.usuario_id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($busca)) {
            $sql .= " AND (u.nome LIKE :busca OR u.email LIKE :busca OR u.cpf LIKE :busca)";
            $params[':busca'] = "%{$busca}%";
        }
        
        $sql .= " ORDER BY u.created_at DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $usuarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Garantir carteira para todos os usuários
        foreach ($usuarios as &$usuario) {
            if ($usuario['carteira_usd'] === null) {
                $this->garantirCarteiraUsuario($usuario['id']);
                $usuario['carteira_usd'] = 0;
                $usuario['carteira_brl'] = 0;
            }
            
            // Adicionar estatísticas de pedidos
            $usuario['total_pedidos'] = $this->getTotalPedidos($usuario['id']);
            $usuario['total_gasto'] = $this->getTotalGasto($usuario['id']);
        }
        
        return $usuarios;
    }
    
    public function getTotalUsuarios($busca = '') {
        $sql = "SELECT COUNT(*) as total FROM usuarios u";
        $params = [];
        
        if (!empty($busca)) {
            $sql .= " WHERE (u.nome LIKE :busca OR u.email LIKE :busca OR u.cpf LIKE :busca)";
            $params[':busca'] = "%{$busca}%";
        }
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }
    
    public function getUsuarioComCarteira($id) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, COALESCE(w.saldo_usd, 0) as carteira_usd, COALESCE(w.saldo_brl, 0) as carteira_brl 
            FROM usuarios u 
            LEFT JOIN carteiras w ON u.id = w.usuario_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($usuario && $usuario['carteira_usd'] === null) {
            $this->garantirCarteiraUsuario($usuario['id']);
            $usuario['carteira_usd'] = 0;
            $usuario['carteira_brl'] = 0;
        }
        
        return $usuario;
    }
    
    public function getStatsUsuarios() {
        $stats = [];
        
        // Total de usuários
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM usuarios");
        $stmt->execute();
        $stats['total_usuarios'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
        
        // Total em carteiras
        $stmt = $this->pdo->prepare("SELECT SUM(COALESCE(w.saldo_usd, 0)) as total_carteira_usd FROM usuarios u LEFT JOIN carteiras w ON u.id = w.usuario_id");
        $stmt->execute();
        $stats['total_carteira_usd'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total_carteira_usd'] ?? 0;
        
        // Usuários ativos (últimos 7 dias)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $stats['usuarios_ativos'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Novos usuários hoje
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['usuarios_hoje'] = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
        
        return $stats;
    }
    
    public function getPedidosUsuario($usuarioId, $limite = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $usuarioId);
        $stmt->bindValue(2, (int)$limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function criarUsuario($dados) {
        try {
            $colunas = $this->getColunasUsuarios();

            $documento = $dados['documento'] ?? ($dados['cpf'] ?? null);
            if (in_array('documento', $colunas) && empty($documento)) {
                throw new \Exception('Documento é obrigatório');
            }

            $insertCols = [];
            $placeholders = [];
            $params = [];

            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'nome', $dados['nome'] ?? null);
            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'email', $dados['email'] ?? null);
            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'senha', !empty($dados['senha']) ? password_hash($dados['senha'], PASSWORD_DEFAULT) : null);
            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'telefone', $dados['telefone'] ?? null);
            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'cpf', $dados['cpf'] ?? null);
            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'documento', $documento);
            $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'suite', $dados['suite'] ?? null);
            $perfil = strtolower(trim((string) ($dados['perfil'] ?? 'cliente')));
            if ($perfil === '') {
                $perfil = 'cliente';
            }
            // Compatibilidade: alguns schemas usam `role` em vez de `perfil`
            if (in_array('perfil', $colunas, true)) {
                $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'perfil', $perfil);
            } else {
                $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'role', $perfil);
            }

            if ($perfil === 'representante') {
                $slug = $this->slugify((string) ($dados['nome'] ?? ''));
                $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'representante_slug', $slug);
            }

            if (in_array('ativo', $colunas)) {
                $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'ativo', (int)($dados['ativo'] ?? 1));
            } elseif (in_array('status', $colunas)) {
                $status = ((int)($dados['ativo'] ?? 1) === 1) ? 'ativo' : 'inativo';
                $this->addIfColumnExists($insertCols, $placeholders, $params, $colunas, 'status', $status);
            }

            if (empty($insertCols)) {
                throw new \Exception('Nenhuma coluna válida encontrada para criar usuário');
            }

            $sql = 'INSERT INTO usuarios (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $usuarioId = $this->pdo->lastInsertId();

            if (in_array('suite', $colunas, true) && empty($dados['suite'])) {
                try {
                    $stmtSw = $this->pdo->prepare('UPDATE usuarios SET `suite` = ? WHERE id = ? AND (`suite` IS NULL OR `suite` = 0)');
                    $stmtSw->execute([(int) $usuarioId, (int) $usuarioId]);
                } catch (\Exception $e) {
                }
            }
            
            // Criar carteira para o usuário
            $this->garantirCarteiraUsuario($usuarioId);
            
            return $usuarioId;
            
        } catch (\Exception $e) {
            throw new \Exception('Erro ao criar usuário: ' . $e->getMessage());
        }
    }
    
    public function atualizarUsuario($id, $dados) {
        try {
            $colunas = $this->getColunasUsuarios();

            $setParts = [];
            $params = [];

            $this->setIfColumnExists($setParts, $params, $colunas, 'nome', $dados['nome'] ?? null);
            $this->setIfColumnExists($setParts, $params, $colunas, 'email', $dados['email'] ?? null);
            $this->setIfColumnExists($setParts, $params, $colunas, 'telefone', $dados['telefone'] ?? null);

            if (in_array('cpf', $colunas)) {
                $this->setIfColumnExists($setParts, $params, $colunas, 'cpf', $dados['cpf'] ?? null);
            }
            if (in_array('documento', $colunas)) {
                $documento = $dados['documento'] ?? ($dados['cpf'] ?? null);
                if (!empty($documento)) {
                    $this->setIfColumnExists($setParts, $params, $colunas, 'documento', $documento);
                }
            }

            if (in_array('ativo', $colunas)) {
                $this->setIfColumnExists($setParts, $params, $colunas, 'ativo', (int)($dados['ativo'] ?? 1));
            } elseif (in_array('status', $colunas)) {
                $status = ((int)($dados['ativo'] ?? 1) === 1) ? 'ativo' : 'inativo';
                $this->setIfColumnExists($setParts, $params, $colunas, 'status', $status);
            }

            if (!empty($dados['senha']) && in_array('senha', $colunas)) {
                $this->setIfColumnExists($setParts, $params, $colunas, 'senha', password_hash($dados['senha'], PASSWORD_DEFAULT));
            }

            if (array_key_exists('perfil', $dados)) {
                $perfil = strtolower(trim((string) ($dados['perfil'] ?? '')));
                if ($perfil !== '') {
                    if (in_array('perfil', $colunas, true)) {
                        $this->setIfColumnExists($setParts, $params, $colunas, 'perfil', $perfil);
                    } else {
                        $this->setIfColumnExists($setParts, $params, $colunas, 'role', $perfil);
                    }
                }
            }

            // Representante: manter slug em sincronia com o nome (quando disponível)
            $perfilEfetivo = strtolower(trim((string) ($dados['perfil'] ?? '')));
            if ($perfilEfetivo === 'representante') {
                $slug = $this->slugify((string) ($dados['nome'] ?? ''));
                $this->setIfColumnExists($setParts, $params, $colunas, 'representante_slug', $slug);
            }

            if (in_array('updated_at', $colunas)) {
                $setParts[] = 'updated_at = NOW()';
            }

            if (empty($setParts)) {
                throw new \Exception('Nenhuma coluna válida encontrada para atualizar usuário');
            }

            $sql = 'UPDATE usuarios SET ' . implode(', ', $setParts) . ' WHERE id = ?';
            $params[] = $id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
            
        } catch (\Exception $e) {
            throw new \Exception('Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }

    private function getColunasUsuarios() {
        try {
            $stmt = $this->pdo->query('DESCRIBE usuarios');
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function addIfColumnExists(&$cols, &$placeholders, &$params, $existingCols, $col, $value) {
        if (in_array($col, $existingCols)) {
            $cols[] = $col;
            $placeholders[] = '?';
            $params[] = $value;
        }
    }

    private function setIfColumnExists(&$setParts, &$params, $existingCols, $col, $value) {
        if (in_array($col, $existingCols)) {
            $setParts[] = $col . ' = ?';
            $params[] = $value;
        }
    }
    
    public function excluirUsuario($id) {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar se usuário tem pedidos
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE usuario_id = ?");
            $stmt->execute([$id]);
            $totalPedidos = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            if ($totalPedidos > 0) {
                // Em vez de excluir, apenas desativar
                $stmt = $this->pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                // Excluir carteira e transações
                $stmt = $this->pdo->prepare("DELETE FROM transacoes_carteira WHERE usuario_id = ?");
                $stmt->execute([$id]);
                
                $stmt = $this->pdo->prepare("DELETE FROM carteiras WHERE usuario_id = ?");
                $stmt->execute([$id]);
                
                // Excluir usuário
                $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new \Exception('Erro ao excluir usuário: ' . $e->getMessage());
        }
    }
    
    private function garantirCarteiraUsuario($usuarioId) {
        // Verificar se a tabela carteiras existe
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `carteiras` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `usuario_id` int(11) NOT NULL,
                `saldo_usd` decimal(10,2) DEFAULT 0.00,
                `saldo_brl` decimal(10,2) DEFAULT 0.00,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_usuario_id` (`usuario_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)");
        $stmt->execute([$usuarioId]);
    }
    
    private function getTotalPedidos($usuarioId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
    }
    
    private function getTotalGasto($usuarioId) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total), 0) as valor FROM pedidos WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC)['valor'] ?? 0;
    }
}
