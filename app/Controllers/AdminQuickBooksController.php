<?php
namespace App\Controllers;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\QuickBooksService;
use Config\Database;
class AdminQuickBooksController extends Controller {
    private function qb(): QuickBooksService { return new QuickBooksService(); }
    public function index(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $qb=$this->qb(); $configurado=$qb->isConfigurado(); $conectado=false; $companyInfo=null; $token=null;
        if($configurado){try{$conectado=$qb->isConectado();if($conectado){$token=$qb->getToken();try{$companyInfo=$qb->getCompanyInfo();}catch(\Throwable $e){}}}catch(\Throwable $e){}}
        $pdo=Database::getConnection();
        $stmt = $pdo->query(
            "SELECT chave, valor FROM configuracoes_sistema
             WHERE chave IN ('qb_client_id','qb_client_secret','qb_redirect_uri','qb_ambiente','qb_ativo','qb_verifier_token')"
        );
        $config = $stmt ? $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
        $logs=$conectado?$qb->getLogsRecentes(20):[];
        require __DIR__.'/../Views/admin/quickbooks/index.php';
    }
    public function oauthCallback(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $code=(string)($req->getParam('code')??''); $realmId=(string)($req->getParam('realmId')??''); $state=(string)($req->getParam('state')??'');
        if($code===''||$realmId===''){header('Location: /admin/quickbooks?erro=callback_invalido'); exit;}
        try{$qb=$this->qb(); $qb->trocarCodigoPorToken($code,$realmId); header('Location: /admin/quickbooks?sucesso=conectado'); exit;}
        catch(\Throwable $ex){header('Location: /admin/quickbooks?erro='.urlencode($ex->getMessage())); exit;}
    }
    public function conectar(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $qb=$this->qb();
        if(!$qb->isConfigurado()){header('Location: /admin/quickbooks?erro=nao_configurado'); exit;}
        $state=bin2hex(random_bytes(16)); $_SESSION['qb_oauth_state']=$state;
        header('Location: '.$qb->getAuthorizationUrl($state)); exit;
    }
    public function desconectar(Request $req) {
        (new AuthService())->requerPerfil('admin');
        (new QuickBooksService())->revogarToken();
        header('Location: /admin/quickbooks?sucesso=desconectado'); exit;
    }
    public function salvarConfig(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $pdo=Database::getConnection();
        $campos=['qb_client_id','qb_client_secret','qb_redirect_uri','qb_ambiente','qb_ativo','qb_verifier_token'];
        foreach ($campos as $chave) {
            $val = (string) ($req->getParam($chave) ?? '');
            // Não sobrescrever o secret se vier vazio (campo password em branco = não alterado)
            if ($chave === 'qb_client_secret' && $val === '') {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO configuracoes_sistema (chave, valor)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
            )->execute([$chave, $val]);
        }
        header('Location: /admin/quickbooks?sucesso=config_salva'); exit;
    }
    public function invoices(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $qb=$this->qb();
        if(!$qb->isConectado()){header('Location: /admin/quickbooks?erro=nao_conectado'); exit;}
        $filtros=['data_inicio'=>(string)($req->getParam('data_inicio')??date('Y-m-01')),'data_fim'=>(string)($req->getParam('data_fim')??date('Y-m-d')),'status'=>(string)($req->getParam('status')??''),'limit'=>100];
        try{$result=$qb->listarInvoices($filtros); $invoices=$result['QueryResponse']['Invoice']??[];}catch(\Throwable $ex){$invoices=[];$erro=$ex->getMessage();}
        require __DIR__.'/../Views/admin/quickbooks/invoices.php';
    }
    public function sincronizarPedido(Request $req) {
        (new AuthService())->requerPerfil('admin');
        header('Content-Type: application/json');
        $pedidoId=(int)($req->getParam('pedido_id')??0);
        if($pedidoId<=0){echo json_encode(['ok'=>false,'erro'=>'ID invalido']); return;}
        try{
            $pdo = Database::getConnection();

            // Buscar pedido
            $ped = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
            $ped->execute([$pedidoId]);
            $pedido = $ped->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                echo json_encode(['ok' => false, 'erro' => 'Pedido não encontrado']);
                return;
            }

            // Buscar usuário separadamente (evita assumir nomes de colunas)
            $usuarioId = (int) ($pedido['usuario_id'] ?? 0);
            if ($usuarioId > 0) {
                $uStmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                $uStmt->execute([$usuarioId]);
                $usuario = $uStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                // Normalizar campos que podem ter nomes diferentes
                $pedido['nome']     = $usuario['nome']     ?? $usuario['name']  ?? '';
                $pedido['email']    = $usuario['email']    ?? '';
                $pedido['telefone'] = $usuario['telefone'] ?? '';
                $pedido['cpf']      = $usuario['cpf']      ?? $usuario['documento'] ?? '';
            }
            $itStmt = $pdo->prepare('SELECT * FROM pedido_itens WHERE pedido_id = ?');
            $itStmt->execute([$pedidoId]);
            $itens = $itStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Enriquecer cada item com o nome do produto (coluna pode ser 'name' ou 'nome')
            foreach ($itens as &$item) {
                $prodId = (int) ($item['produto_id'] ?? 0);
                if ($prodId > 0) {
                    $pStmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
                    $pStmt->execute([$prodId]);
                    $prod = $pStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $item['produto_nome'] = $prod['name'] ?? $prod['nome'] ?? $prod['title'] ?? 'Produto #' . $prodId;
                } else {
                    $item['produto_nome'] = $item['produto_nome'] ?? 'Produto';
                }
            }
            unset($item);

            // Normalizar total BRL do pedido
            $totalBrl = 0.0;
            foreach (['total', 'valor_total', 'total_brl', 'valor_total_brl', 'amount', 'valor'] as $col) {
                if (!empty($pedido[$col]) && (float) $pedido[$col] > 0) {
                    $totalBrl = (float) $pedido[$col];
                    break;
                }
            }
            $pedido['total_brl'] = $totalBrl;

            // Buscar split de pagamentos da tabela pedido_pagamentos (se existir)
            $pagamentos = [];
            try {
                $pgStmt = $pdo->prepare(
                    "SELECT componente, gateway, metodo, valor, moeda
                     FROM pedido_pagamentos
                     WHERE pedido_id = ? AND status IN ('paid','approved','confirmed')
                     ORDER BY id ASC"
                );
                $pgStmt->execute([$pedidoId]);
                $pagamentos = $pgStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                // tabela pode não existir em instalações antigas
            }
            $pedido['_pagamentos'] = $pagamentos;

            $qb  = $this->qb();
            $res = $qb->criarInvoiceDePedido($pedido, $itens, $pedido);
            echo json_encode(['ok'=>true,'qb_invoice_id'=>$res['Invoice']['Id']??null]);
        }catch(\Throwable $ex){echo json_encode(['ok'=>false,'erro'=>$ex->getMessage()]);}
    }

    /**
     * POST /admin/quickbooks/sincronizar-lote
     * Sincroniza todos os pedidos a partir de uma data que ainda não foram sincronizados
     */
    public function sincronizarLote(Request $req) {
        (new AuthService())->requerPerfil('admin');
        header('Content-Type: application/json');
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $dataInicio = trim((string) ($req->getParam('data_inicio') ?? '2026-04-29'));
        if ($dataInicio === '') $dataInicio = '2026-04-29';
        $dataFim = trim((string) ($req->getParam('data_fim') ?? ''));
        $maxPedidos = (int) ($req->getParam('max') ?: 25); // Limitar por request para evitar timeout
        if ($maxPedidos <= 0 || $maxPedidos > 50) $maxPedidos = 25;

        try {
            $pdo = Database::getConnection();
            $qb = $this->qb();

            if (!$qb->isConectado()) {
                echo json_encode(['ok' => false, 'erro' => 'QuickBooks não está conectado']);
                return;
            }

            // Garantir tabela de mapeamento existe
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS quickbooks_pedido_map (
                    id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL,
                    qb_invoice_id VARCHAR(50) NULL, qb_payment_id VARCHAR(50) NULL,
                    ambiente VARCHAR(20) NOT NULL DEFAULT 'sandbox',
                    sincronizado_em DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_qb_pedido_amb (pedido_id, ambiente)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // Garantir coluna ambiente existe
                try { $pdo->exec("ALTER TABLE quickbooks_pedido_map ADD COLUMN ambiente VARCHAR(20) NOT NULL DEFAULT 'sandbox' AFTER qb_payment_id"); } catch (\Throwable $e) {}
                // Atualizar unique key
                try { $pdo->exec("ALTER TABLE quickbooks_pedido_map DROP INDEX uq_qb_pedido"); } catch (\Throwable $e) {}
                try { $pdo->exec("ALTER TABLE quickbooks_pedido_map ADD UNIQUE KEY uq_qb_pedido_amb (pedido_id, ambiente)"); } catch (\Throwable $e) {}
            } catch (\Throwable $e) {}

            $ambiente = $qb->getAmbiente();

            // Buscar pedidos no período que NÃO estão no mapeamento PARA ESTE AMBIENTE
            $sql = "SELECT p.id FROM pedidos p
                    WHERE p.created_at >= ?
                    " . ($dataFim !== '' ? "AND p.created_at <= ?" : "") . "
                    AND p.deleted_at IS NULL
                    AND p.id NOT IN (SELECT pedido_id FROM quickbooks_pedido_map WHERE pedido_id IS NOT NULL AND ambiente = ?)
                    ORDER BY p.id ASC
                    LIMIT " . (int) $maxPedidos;
            $params = [$dataInicio . ' 00:00:00'];
            if ($dataFim !== '') $params[] = $dataFim . ' 23:59:59';
            $params[] = $ambiente;
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $pedidoIds = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            // Contar total pendente (para informar ao frontend)
            $sqlCount = "SELECT COUNT(*) FROM pedidos p
                    WHERE p.created_at >= ?
                    " . ($dataFim !== '' ? "AND p.created_at <= ?" : "") . "
                    AND p.deleted_at IS NULL
                    AND p.id NOT IN (SELECT pedido_id FROM quickbooks_pedido_map WHERE pedido_id IS NOT NULL AND ambiente = ?)";
            $paramsCount = [$dataInicio . ' 00:00:00'];
            if ($dataFim !== '') $paramsCount[] = $dataFim . ' 23:59:59';
            $paramsCount[] = $ambiente;
            $stCount = $pdo->prepare($sqlCount);
            $stCount->execute($paramsCount);
            $totalPendente = (int) ($stCount->fetchColumn() ?: 0);

            $resultados = ['total' => count($pedidoIds), 'total_pendente' => $totalPendente, 'sucesso' => 0, 'erros' => []];

            foreach ($pedidoIds as $pedidoId) {
                try {
                    $ped = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
                    $ped->execute([$pedidoId]);
                    $pedido = $ped->fetch(\PDO::FETCH_ASSOC);
                    if (!$pedido) continue;

                    // Buscar usuário
                    $usuarioId = (int) ($pedido['usuario_id'] ?? 0);
                    if ($usuarioId > 0) {
                        $uStmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                        $uStmt->execute([$usuarioId]);
                        $usuario = $uStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $pedido['nome']     = $usuario['nome'] ?? $usuario['name'] ?? '';
                        $pedido['email']    = $usuario['email'] ?? '';
                        $pedido['telefone'] = $usuario['telefone'] ?? $usuario['phone'] ?? '';
                        $pedido['cpf']      = $usuario['cpf'] ?? $usuario['documento'] ?? '';
                    }

                    // Buscar itens
                    $itStmt = $pdo->prepare('SELECT * FROM pedido_itens WHERE pedido_id = ?');
                    $itStmt->execute([$pedidoId]);
                    $itens = $itStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    foreach ($itens as &$item) {
                        $prodId = (int) ($item['produto_id'] ?? 0);
                        if ($prodId > 0) {
                            $pStmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
                            $pStmt->execute([$prodId]);
                            $prod = $pStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $item['produto_nome'] = $prod['name'] ?? $prod['nome'] ?? 'Produto #' . $prodId;
                        }
                    }
                    unset($item);

                    // Total BRL
                    foreach (['total', 'valor_total'] as $col) {
                        if (!empty($pedido[$col]) && (float) $pedido[$col] > 0) {
                            $pedido['total_brl'] = (float) $pedido[$col];
                            break;
                        }
                    }

                    // Pagamentos
                    try {
                        $pgStmt = $pdo->prepare("SELECT componente, gateway, metodo, valor, moeda FROM pedido_pagamentos WHERE pedido_id = ? AND status IN ('paid','approved','confirmed') ORDER BY id ASC");
                        $pgStmt->execute([$pedidoId]);
                        $pedido['_pagamentos'] = $pgStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Throwable $e) { $pedido['_pagamentos'] = []; }

                    $qb->criarInvoiceDePedido($pedido, $itens, $pedido);
                    $resultados['sucesso']++;

                    // Pausa para não sobrecarregar a API do QB (rate limit: ~500 req/min em produção)
                    usleep(500000); // 500ms
                } catch (\Throwable $e) {
                    $resultados['erros'][] = '#' . $pedidoId . ': ' . $e->getMessage();
                    error_log('[QB_LOTE] Erro pedido #' . $pedidoId . ': ' . $e->getMessage());
                }
            }

            // Void invoices de pedidos cancelados/na lixeira que já foram sincronizados
            $voidCount = 0;
            try {
                $sqlVoid = "SELECT qm.pedido_id, qm.qb_invoice_id FROM quickbooks_pedido_map qm
                    INNER JOIN pedidos p ON p.id = qm.pedido_id
                    WHERE qm.ambiente = ?
                    AND (p.deleted_at IS NOT NULL OR LOWER(COALESCE(p.status,'')) = 'cancelado')
                    AND qm.qb_invoice_id IS NOT NULL
                    AND qm.pedido_id NOT IN (SELECT entidade_id FROM quickbooks_sync_log WHERE acao = 'void' AND status = 'success' AND entidade_id IS NOT NULL)";
                $stVoid = $pdo->prepare($sqlVoid);
                $stVoid->execute([$ambiente]);
                $pedidosVoid = $stVoid->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($pedidosVoid as $pv) {
                    try {
                        $qb->voidInvoiceDePedido((int) $pv['pedido_id']);
                        $voidCount++;
                        usleep(200000);
                    } catch (\Throwable $e) {
                        $resultados['erros'][] = 'Void #' . $pv['pedido_id'] . ': ' . $e->getMessage();
                    }
                }
            } catch (\Throwable $e) {}

            $resultados['voided'] = $voidCount;

            echo json_encode(['ok' => true, 'resultados' => $resultados]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
    }
}
