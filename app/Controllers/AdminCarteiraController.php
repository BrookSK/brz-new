<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminCarteiraController extends Controller {
    
    public function __construct() {
        // Garantir que as tabelas existam
        $this->verificarCriarTabelas();
    }
    
    private function verificarCriarTabelas() {
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Criar tabela de carteiras se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `carteiras` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `saldo_usd` decimal(10,2) DEFAULT 0.00,
                    `saldo_brl` decimal(10,2) DEFAULT 0.00,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_usuario_id` (`usuario_id`),
                    KEY `idx_saldo_usd` (`saldo_usd`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Criar tabela de transações se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `transacoes_carteira` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `tipo` enum('credito','debito','conversao') NOT NULL,
                    `valor_usd` decimal(10,2) DEFAULT 0.00,
                    `valor_brl` decimal(10,2) DEFAULT 0.00,
                    `taxa_conversao` decimal(10,6) DEFAULT 1.000000,
                    `descricao` text,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_usuario_id` (`usuario_id`),
                    KEY `idx_tipo` (`tipo`),
                    KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
        } catch (\Exception $e) {
            // Silenciar erros de criação de tabela
        }
    }
    
    public function adicionarCredito(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $data = json_decode(file_get_contents('php://input'), true);
        $usuarioId = $data['usuario_id'] ?? 0;
        $valor = $data['valor'] ?? 0;
        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            $descricao = 'Crédito adicionado pelo admin';
        }
        
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Verificar se usuário existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioId]);
            if (!$stmt->fetch()) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Garantir carteira existe
            $this->garantirCarteiraUsuario($pdo, $usuarioId);

            // Ensure modalidade column exists
            try {
                $stCols = $pdo->query('DESCRIBE transacoes_carteira');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (!in_array('modalidade', $cols, true)) {
                    $pdo->exec("ALTER TABLE transacoes_carteira ADD COLUMN modalidade varchar(10) DEFAULT 'normal'");
                }
            } catch (\Exception $e) {}
            
            // Adicionar crédito (sempre em USD)
            $stmt = $pdo->prepare("UPDATE carteiras SET saldo_usd = saldo_usd + ?, updated_at = NOW() WHERE usuario_id = ?");
            $stmt->execute([$valor, $usuarioId]);
            
            // Registrar transação como Normal
            $stmt = $pdo->prepare("
                INSERT INTO transacoes_carteira 
                (usuario_id, tipo, valor_usd, descricao, modalidade, created_at) 
                VALUES (?, 'credito', ?, ?, 'normal', NOW())
            ");
            $stmt->execute([$usuarioId, $valor, $descricao]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Crédito adicionado com sucesso']);
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function debitarCredito(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $data = json_decode(file_get_contents('php://input'), true);
        $usuarioId = (int) ($data['usuario_id'] ?? 0);
        $valor = (float) ($data['valor'] ?? 0);
        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            $descricao = 'Débito realizado pelo admin';
        }

        if ($usuarioId <= 0 || $valor <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            // Verificar se usuário existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioId]);
            if (!$stmt->fetch()) {
                throw new \Exception('Usuário não encontrado');
            }

            // Garantir carteira existe
            $this->garantirCarteiraUsuario($pdo, $usuarioId);

            // Verificar saldo disponível
            $stmt = $pdo->prepare("SELECT saldo_usd FROM carteiras WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            $saldoAtual = (float) ($stmt->fetchColumn() ?: 0);

            if ($valor > $saldoAtual) {
                throw new \Exception('Saldo insuficiente. Saldo atual: $' . number_format($saldoAtual, 2) . ' USD');
            }

            // Ensure modalidade column exists
            try {
                $stCols = $pdo->query('DESCRIBE transacoes_carteira');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (!in_array('modalidade', $cols, true)) {
                    $pdo->exec("ALTER TABLE transacoes_carteira ADD COLUMN modalidade varchar(10) DEFAULT 'normal'");
                }
            } catch (\Exception $e) {}

            // Debitar valor (subtrair do saldo)
            $stmt = $pdo->prepare("UPDATE carteiras SET saldo_usd = saldo_usd - ?, updated_at = NOW() WHERE usuario_id = ?");
            $stmt->execute([$valor, $usuarioId]);

            // Registrar transação como débito
            $stmt = $pdo->prepare("
                INSERT INTO transacoes_carteira 
                (usuario_id, tipo, valor_usd, descricao, modalidade, created_at) 
                VALUES (?, 'debito', ?, ?, 'normal', NOW())
            ");
            $stmt->execute([$usuarioId, $valor, $descricao]);

            $pdo->commit();

            $novoSaldo = $saldoAtual - $valor;
            echo json_encode(['success' => true, 'message' => 'Débito realizado com sucesso', 'novo_saldo' => $novoSaldo]);

        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    public function converterParaBRL(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $data = json_decode(file_get_contents('php://input'), true);
        $usuarioId = $data['usuario_id'] ?? 0;
        $valorUSD = $data['valor_usd'] ?? 0;
        $taxaConversao = $data['taxa_conversao'] ?? \App\Core\ExchangeRate::getUsdToBrl();
        
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Verificar carteira e saldo
            $stmt = $pdo->prepare("SELECT saldo_usd FROM carteiras WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            $carteira = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$carteira || $carteira['saldo_usd'] < $valorUSD) {
                throw new \Exception('Saldo insuficiente');
            }
            
            $valorBRL = $valorUSD * $taxaConversao;
            
            // Atualizar carteira
            $stmt = $pdo->prepare("
                UPDATE carteiras 
                SET saldo_usd = saldo_usd - ?, 
                    saldo_brl = saldo_brl + ?, 
                    updated_at = NOW() 
                WHERE usuario_id = ?
            ");
            $stmt->execute([$valorUSD, $valorBRL, $usuarioId]);
            
            // Registrar transação
            $stmt = $pdo->prepare("
                INSERT INTO transacoes_carteira 
                (usuario_id, tipo, valor_usd, valor_brl, taxa_conversao, descricao, created_at) 
                VALUES (?, 'conversao', ?, ?, ?, 'Conversão USD para BRL', NOW())
            ");
            $stmt->execute([$usuarioId, $valorUSD, $valorBRL, $taxaConversao]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Conversão realizada com sucesso',
                'valor_brl' => $valorBRL
            ]);
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    public function getSaldo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $usuarioId = $request->getParam('usuario_id');
        
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $this->garantirCarteiraUsuario($pdo, $usuarioId);
            
            $stmt = $pdo->prepare("SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            $carteira = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'saldo_usd' => $carteira['saldo_usd'],
                'saldo_brl' => $carteira['saldo_brl']
            ]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    public function getExtrato(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $usuarioId = $request->getParam('usuario_id');
        $pagina = $request->getParam('pagina', 1);
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $stmt = $pdo->prepare("
                SELECT * FROM transacoes_carteira 
                WHERE usuario_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$usuarioId, $limite, $offset]);
            $transacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Total para paginação
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM transacoes_carteira WHERE usuario_id = ?");
            $stmtTotal->execute([$usuarioId]);
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
            echo json_encode([
                'success' => true,
                'transacoes' => $transacoes,
                'total' => $total,
                'total_paginas' => $totalPaginas,
                'pagina_atual' => $pagina
            ]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    public function getStatsGerais() {
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Estatísticas gerais
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_usuarios,
                    SUM(saldo_usd) as total_usd,
                    SUM(saldo_brl) as total_brl,
                    AVG(saldo_usd) as media_usd
                FROM carteiras
            ");
            $stmt->execute();
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Transações recentes
            $stmt = $pdo->prepare("
                SELECT t.*, u.nome as usuario_nome 
                FROM transacoes_carteira t 
                LEFT JOIN usuarios u ON t.usuario_id = u.id 
                ORDER BY t.created_at DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $transacoes_recentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'transacoes_recentes' => $transacoes_recentes
            ]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    private function garantirCarteiraUsuario($pdo, $usuarioId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)");
        $stmt->execute([$usuarioId]);
    }
    
    public function adicionarCreditosEmLote(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $data = json_decode(file_get_contents('php://input'), true);
        $usuarios = $data['usuarios'] ?? [];
        $valor = $data['valor'] ?? 0;
        $descricao = $data['descricao'] ?? 'Crédito em lote adicionado pelo admin';
        
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $sucessos = 0;
            $erros = [];
            
            foreach ($usuarios as $usuarioId) {
                try {
                    // Verificar se usuário existe
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
                    $stmt->execute([$usuarioId]);
                    if (!$stmt->fetch()) {
                        $erros[] = "Usuário ID {$usuarioId} não encontrado";
                        continue;
                    }
                    
                    // Garantir carteira existe
                    $this->garantirCarteiraUsuario($pdo, $usuarioId);
                    
                    // Adicionar crédito
                    $stmt = $pdo->prepare("UPDATE carteiras SET saldo_usd = saldo_usd + ?, updated_at = NOW() WHERE usuario_id = ?");
                    $stmt->execute([$valor, $usuarioId]);
                    
                    // Registrar transação
                    $stmt = $pdo->prepare("
                        INSERT INTO transacoes_carteira 
                        (usuario_id, tipo, valor_usd, descricao, modalidade, created_at) 
                        VALUES (?, 'credito', ?, ?, 'normal', NOW())
                    ");
                    $stmt->execute([$usuarioId, $valor, $descricao]);
                    
                    $sucessos++;
                    
                } catch (\Exception $e) {
                    $erros[] = "Erro no usuário ID {$usuarioId}: " . $e->getMessage();
                }
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Processado: {$sucessos} sucessos, " . count($erros) . " erros",
                'sucessos' => $sucessos,
                'erros' => $erros
            ]);
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
