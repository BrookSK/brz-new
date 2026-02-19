<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Services\CpfValidator;
use App\Services\PdfPedidoService;
use App\Models\Usuario;
use App\Models\PedidoEcommerce;
use App\Models\Endereco;
use App\Models\Carrinho;
use App\Models\AssessoriaOrcamento;

class UsuarioController extends Controller {
    private $authService;
    private $usuarioModel;
    private $pedidoModel;
    private $carrinhoModel;
    private $paymentService;

    public function __construct() {
        $this->authService = new AuthService();
        $this->usuarioModel = new Usuario();
        $this->pedidoModel = new PedidoEcommerce();
        $this->carrinhoModel = new Carrinho();
        $this->paymentService = new PaymentService();
    }

    public function dashboard(Request $request) {
        $this->authService->requerAutenticacao();
        
        $usuario = $this->authService->getUsuarioLogado();
        
        // Obter pedidos reais do usuário
        try {
            $pedidos = $this->pedidoModel->getPedidos($usuario['id']);
        } catch (\Exception $e) {
            // Se houver erro, usar array vazio e registrar log
            error_log('Erro ao obter pedidos do usuário: ' . $e->getMessage());
            $pedidos = [];
        }
        
        $this->view('usuario/dashboard', [
            'usuario' => $usuario,
            'pedidos' => $pedidos,
            'total_pedidos' => count($pedidos),
            'pedidos_recentes' => array_slice($pedidos, 0, 5)
        ]);
    }

    public function minhaConta(Request $request) {
        $this->authService->requerAutenticacao();
        
        $usuario = $this->usuarioModel->find($this->authService->getUsuarioLogado()['id']);
        
        // Obter enderecos do usuário
        $enderecos = $this->usuarioModel->getEnderecos($usuario['id']);
        
        // Obter pedidos reais do usuário (usuario_id ou cliente_id)
        $pedidos = [];
        $pedidosWhere = 'p.usuario_id = ?';
        $pedidosParams = [(int) $usuario['id']];
        try {
            $db = \Config\Database::getConnection();

            $colsPedidos = [];
            try {
                $stmtCols = $db->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $clienteId = null;
            if (is_array($colsPedidos) && in_array('cliente_id', $colsPedidos, true)) {
                $colsClientes = [];
                try {
                    $stmtColsCli = $db->query('DESCRIBE clientes');
                    $colsClientes = $stmtColsCli ? $stmtColsCli->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsClientes = [];
                }

                // 1) Match por clientes.usuario_id (quando existir)
                if (!$clienteId && is_array($colsClientes) && in_array('usuario_id', $colsClientes, true)) {
                    try {
                        $stmtCli = $db->prepare('SELECT id FROM clientes WHERE usuario_id = ? ORDER BY id DESC LIMIT 1');
                        $stmtCli->execute([(int) $usuario['id']]);
                        $cid = $stmtCli->fetchColumn();
                        if ($cid) {
                            $clienteId = (int) $cid;
                        }
                    } catch (\Exception $e) {
                    }
                }

                // 2) Match por email
                $email = (string) ($usuario['email'] ?? '');
                if (!$clienteId && $email !== '' && is_array($colsClientes) && in_array('email', $colsClientes, true)) {
                    try {
                        $stmtCli = $db->prepare('SELECT id FROM clientes WHERE email = ? ORDER BY id DESC LIMIT 1');
                        $stmtCli->execute([$email]);
                        $cid = $stmtCli->fetchColumn();
                        if ($cid) {
                            $clienteId = (int) $cid;
                        }
                    } catch (\Exception $e) {
                    }
                }

                // 3) Match por documento/cpf
                $documento = (string) ($usuario['cpf_cnpj'] ?? ($usuario['documento'] ?? ($usuario['cpf'] ?? '')));
                $documento = preg_replace('/\D+/', '', $documento);
                if (!$clienteId && $documento !== '') {
                    $docCol = null;
                    foreach (['cpf_cnpj', 'documento', 'cpf', 'cnpj'] as $c) {
                        if (is_array($colsClientes) && in_array($c, $colsClientes, true)) {
                            $docCol = $c;
                            break;
                        }
                    }
                    if ($docCol !== null) {
                        try {
                            $stmtCli = $db->prepare('SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(' . $docCol . ", '.', ''), '-', ''), '/', '') = ? ORDER BY id DESC LIMIT 1");
                            $stmtCli->execute([$documento]);
                            $cid = $stmtCli->fetchColumn();
                            if ($cid) {
                                $clienteId = (int) $cid;
                            }
                        } catch (\Exception $e) {
                            // fallback simples sem replace
                            try {
                                $stmtCli = $db->prepare('SELECT id FROM clientes WHERE ' . $docCol . ' = ? ORDER BY id DESC LIMIT 1');
                                $stmtCli->execute([$documento]);
                                $cid = $stmtCli->fetchColumn();
                                if ($cid) {
                                    $clienteId = (int) $cid;
                                }
                            } catch (\Exception $e2) {
                            }
                        }
                    }
                }

                // 4) Match por telefone
                $telefone = (string) ($usuario['celular'] ?? ($usuario['telefone'] ?? ''));
                $telefone = preg_replace('/\D+/', '', $telefone);
                $telCol = null;
                foreach (['celular', 'telefone'] as $c) {
                    if (is_array($colsClientes) && in_array($c, $colsClientes, true)) {
                        $telCol = $c;
                        break;
                    }
                }

                if (!$clienteId && $telefone !== '' && $telCol !== null) {
                    try {
                        $stmtCli = $db->prepare('SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $telCol . ', "(", ""), ")", ""), "-", ""), " ", ""), "+", "") = ? ORDER BY id DESC LIMIT 1');
                        $stmtCli->execute([$telefone]);
                        $cid = $stmtCli->fetchColumn();
                        if ($cid) {
                            $clienteId = (int) $cid;
                        }
                    } catch (\Exception $e) {
                        try {
                            $stmtCli = $db->prepare('SELECT id FROM clientes WHERE ' . $telCol . ' = ? ORDER BY id DESC LIMIT 1');
                            $stmtCli->execute([$telefone]);
                            $cid = $stmtCli->fetchColumn();
                            if ($cid) {
                                $clienteId = (int) $cid;
                            }
                        } catch (\Exception $e2) {
                        }
                    }
                }
            }

            if ($clienteId) {
                $pedidosWhere = '(p.usuario_id = ? OR p.cliente_id = ?)';
                $pedidosParams = [(int) $usuario['id'], (int) $clienteId];
            }

            $stmtPedidos = $db->prepare('SELECT p.* FROM pedidos p WHERE ' . $pedidosWhere . ' ORDER BY p.created_at DESC LIMIT 10 OFFSET 0');
            $stmtPedidos->execute($pedidosParams);
            $pedidos = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log('Erro ao obter pedidos do usuário: ' . $e->getMessage());
            $pedidos = [];
        }
        
        $pedidos_recentes = array_slice($pedidos, 0, 5);

        // Estatísticas reais (sem depender da lista limitada)
        $stats = [
            'total_pedidos' => 0,
            'pedidos_ativos' => 0,
            'total_gasto_brl' => 0.0,
            'total_gasto_usd' => 0.0,
        ];
        try {
            $db = \Config\Database::getConnection();
            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE pedidos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $totalCol = null;
            foreach (['valor_total', 'total', 'amount'] as $c) {
                if (is_array($cols) && in_array($c, $cols, true)) {
                    $totalCol = $c;
                    break;
                }
            }
            if ($totalCol === null) {
                $totalCol = 'valor_total';
            }

            $moedaCol = null;
            foreach (['moeda', 'currency'] as $c) {
                if (is_array($cols) && in_array($c, $cols, true)) {
                    $moedaCol = $c;
                    break;
                }
            }

            $stmtTotal = $db->prepare('SELECT COUNT(*) FROM pedidos p WHERE ' . $pedidosWhere);
            $stmtTotal->execute($pedidosParams);
            $stats['total_pedidos'] = (int) $stmtTotal->fetchColumn();

            $stmtAtivos = $db->prepare("SELECT COUNT(*) FROM pedidos p WHERE {$pedidosWhere} AND p.status IN ('pendente','processando','enviado')");
            $stmtAtivos->execute($pedidosParams);
            $stats['pedidos_ativos'] = (int) $stmtAtivos->fetchColumn();

            if ($moedaCol !== null) {
                $stmtSum = $db->prepare("SELECT UPPER(COALESCE(p.{$moedaCol}, 'BRL')) AS moeda, SUM(COALESCE(p.{$totalCol},0)) AS total FROM pedidos p WHERE {$pedidosWhere} GROUP BY UPPER(COALESCE(p.{$moedaCol}, 'BRL'))");
                $stmtSum->execute($pedidosParams);
                $rows = $stmtSum->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $m = strtoupper((string) ($r['moeda'] ?? 'BRL'));
                    $t = floatval($r['total'] ?? 0);
                    if ($m === 'USD') {
                        $stats['total_gasto_usd'] += $t;
                    } else {
                        $stats['total_gasto_brl'] += $t;
                    }
                }
            } else {
                $stmtSum = $db->prepare("SELECT SUM(COALESCE(p.{$totalCol},0)) AS total FROM pedidos p WHERE {$pedidosWhere}");
                $stmtSum->execute($pedidosParams);
                $stats['total_gasto_brl'] = floatval($stmtSum->fetchColumn() ?: 0);
            }
        } catch (\Exception $e) {
        }

        $carteiraSaldoBrl = 0.0;
        $carteiraSaldoUsd = 0.0;
        $usdBrlRate = 5.5;
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? LIMIT 1');
            $stmt->execute([(int) $usuario['id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $carteiraSaldoUsd = (float) ($row['saldo_usd'] ?? 0);
            $carteiraSaldoBrl = (float) ($row['saldo_brl'] ?? 0);

            try {
                $stmtRate = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                foreach (['sistema_usd_brl_rate', 'usd_brl_rate'] as $k) {
                    try {
                        $stmtRate->execute([$k]);
                        $val = $stmtRate->fetchColumn();
                        $v = (float) str_replace(',', '.', trim((string) ($val ?? '')));
                        if ($v > 0) {
                            $usdBrlRate = $v;
                            break;
                        }
                    } catch (\Exception $e) {
                    }
                }
            } catch (\Exception $e) {
            }

            if ($usdBrlRate <= 0) {
                $usdBrlRate = 5.5;
            }

            if ($usdBrlRate <= 0 || $usdBrlRate === 5.5) {
                try {
                    $stmtTx = $db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                    $r = $stmtTx ? $stmtTx->fetch(\PDO::FETCH_ASSOC) : null;
                    if (is_array($r) && isset($r['taxa_conversao'])) {
                        $v = (float) $r['taxa_conversao'];
                        if ($v > 0) {
                            $usdBrlRate = $v;
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
            $carteiraSaldoBrl = 0.0;
            $carteiraSaldoUsd = 0.0;
            $usdBrlRate = 5.5;
        }

        $carteiraSaldoBrlEquiv = (float) $carteiraSaldoBrl;
        if ($carteiraSaldoUsd > 0 && $usdBrlRate > 0) {
            $carteiraSaldoBrlEquiv = (float) ($carteiraSaldoBrl + ($carteiraSaldoUsd * $usdBrlRate));
        }

        $orcamentosAssessoria = [];
        try {
            $orcModel = new AssessoriaOrcamento();
            $orcamentosAssessoria = $orcModel->getByUsuarioId((int) $usuario['id'], 10);
        } catch (\Exception $e) {
            $orcamentosAssessoria = [];
        }

        $carteiraTransacoes = [];
        $carteiraRendimentoResumo = [
            'credito_usd' => 0.0,
            'credito_brl' => 0.0,
            'debito_usd' => 0.0,
            'debito_brl' => 0.0,
        ];
        try {
            $db = \Config\Database::getConnection();

            if (!$db instanceof \PDO) {
                throw new \Exception('Conexão com o banco indisponível');
            }

            $temTabela = false;
            try {
                $stmtT = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
                $stmtT->execute(['transacoes_carteira']);
                $temTabela = (bool) $stmtT->fetchColumn();
            } catch (\Exception $e) {
                $temTabela = false;
            }

            if ($temTabela) {
                $stmtTx = $db->prepare('SELECT id, tipo, valor_usd, valor_brl, descricao, created_at FROM transacoes_carteira WHERE usuario_id = ? ORDER BY created_at DESC, id DESC LIMIT 50');
                $stmtTx->execute([(int) $usuario['id']]);
                $carteiraTransacoes = $stmtTx->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($carteiraTransacoes as $t) {
                    $desc = (string) ($t['descricao'] ?? '');
                    if (stripos($desc, 'Rendimento Clube') === false) {
                        continue;
                    }
                    $tipo = strtolower(trim((string) ($t['tipo'] ?? '')));
                    $vUsd = (float) ($t['valor_usd'] ?? 0);
                    $vBrl = (float) ($t['valor_brl'] ?? 0);
                    if ($tipo === 'credito') {
                        $carteiraRendimentoResumo['credito_usd'] += $vUsd;
                        $carteiraRendimentoResumo['credito_brl'] += $vBrl;
                    } elseif ($tipo === 'debito') {
                        $carteiraRendimentoResumo['debito_usd'] += $vUsd;
                        $carteiraRendimentoResumo['debito_brl'] += $vBrl;
                    }
                }
            }
        } catch (\Exception $e) {
            $carteiraTransacoes = [];
        }
        
        $this->view('usuario/minha-conta', [
            'usuario' => $usuario,
            'enderecos' => $enderecos,
            'pedidos' => $pedidos,
            'pedidos_recentes' => $pedidos_recentes,
            'total_pedidos' => (int) ($stats['total_pedidos'] ?? 0),
            'total_gasto_brl' => (float) ($stats['total_gasto_brl'] ?? 0),
            'total_gasto_usd' => (float) ($stats['total_gasto_usd'] ?? 0),
            'pedidos_ativos' => (int) ($stats['pedidos_ativos'] ?? 0),
            'orcamentos_assessoria' => $orcamentosAssessoria,
            'carteira_saldo_brl' => (float) $carteiraSaldoBrl,
            'carteira_saldo_usd' => (float) $carteiraSaldoUsd,
            'carteira_usd_brl_rate' => (float) $usdBrlRate,
            'carteira_saldo_brl_equiv' => (float) $carteiraSaldoBrlEquiv,
            'carteira_transacoes' => $carteiraTransacoes,
            'carteira_rendimento_resumo' => $carteiraRendimentoResumo,
            'stripe_enabled' => (bool) $this->paymentService->isStripeEnabled(),
            'stripe_publishable_key' => (string) $this->paymentService->getStripePublishableKey()
        ]);
    }

    private function garantirTabelaCarteiraRecargas(\PDO $db): void {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `carteira_recargas` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `usuario_id` int(11) NOT NULL,
                `moeda` varchar(3) NOT NULL DEFAULT 'USD',
                `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                `gateway` varchar(20) DEFAULT NULL,
                `payment_id` varchar(191) DEFAULT NULL,
                `invoice_url` text,
                `status` varchar(30) NOT NULL DEFAULT 'pending',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `paid_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_usuario_id` (`usuario_id`),
                KEY `idx_gateway_payment` (`gateway`, `payment_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    public function carteiraRecargaCriar(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioSessao = $this->authService->getUsuarioLogado();
        $usuarioId = (int) ($usuarioSessao['id'] ?? 0);
        if ($usuarioId <= 0) {
            $this->json(['success' => false, 'error' => 'Usuário inválido'], 400);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $moeda = strtoupper(trim((string) ($data['moeda'] ?? 'BRL')));
        $valor = (float) str_replace(',', '.', (string) ($data['valor'] ?? '0'));
        $metodo = strtolower(trim((string) ($data['metodo'] ?? 'pix')));

        $cardHolderName = (string) ($data['card_holder_name'] ?? '');
        $cardNumber = (string) ($data['card_number'] ?? '');
        $cardExpiryMonth = (string) ($data['card_expiry_month'] ?? '');
        $cardExpiryYear = (string) ($data['card_expiry_year'] ?? '');
        $cardCvv = (string) ($data['card_cvv'] ?? '');
        $installments = (int) ($data['installments'] ?? 1);
        if ($installments <= 0) {
            $installments = 1;
        }

        if (!in_array($moeda, ['BRL', 'USD'], true)) {
            $this->json(['success' => false, 'error' => 'Moeda inválida'], 400);
            return;
        }
        if ($valor <= 0) {
            $this->json(['success' => false, 'error' => 'Valor inválido'], 400);
            return;
        }

        if ($moeda === 'BRL' && !in_array($metodo, ['pix', 'boleto', 'cartao_credito'], true)) {
            $metodo = 'pix';
        }

        try {
            $db = \Config\Database::getConnection();
            $this->garantirTabelaCarteiraRecargas($db);

            $stmtIns = $db->prepare("INSERT INTO carteira_recargas (usuario_id, moeda, valor, status, created_at, updated_at) VALUES (:uid, :moeda, :valor, 'pending', NOW(), NOW())");
            $stmtIns->execute([
                ':uid' => $usuarioId,
                ':moeda' => $moeda,
                ':valor' => $valor,
            ]);
            $recargaId = (int) $db->lastInsertId();
            if ($recargaId <= 0) {
                $this->json(['success' => false, 'error' => 'Não foi possível criar recarga'], 500);
                return;
            }

            $usuario = $this->usuarioModel->find($usuarioId);
            $nome = (string) ($usuario['nome'] ?? ($usuarioSessao['nome'] ?? 'Cliente'));
            $email = (string) ($usuario['email'] ?? ($usuarioSessao['email'] ?? ''));
            $documento = (string) ($usuario['cpf_cnpj'] ?? ($usuario['documento'] ?? ($usuario['cpf'] ?? '')));
            $telefone = (string) ($usuario['celular'] ?? ($usuario['telefone'] ?? ''));

            $descricao = 'Recarga Carteira #' . $recargaId;

            if ($moeda === 'USD') {
                $pi = $this->paymentService->createStripePaymentIntentCarteiraRecarga($recargaId, $valor, $descricao, ['email' => $email]);
                if (empty($pi['success'])) {
                    $this->json(['success' => false, 'error' => (string) ($pi['error'] ?? 'Falha ao iniciar pagamento Stripe')], 500);
                    return;
                }

                $paymentIntentId = (string) ($pi['payment_intent_id'] ?? '');
                if ($paymentIntentId !== '') {
                    $stmtUp = $db->prepare("UPDATE carteira_recargas SET gateway = 'stripe', payment_id = :pid, status = 'pending', updated_at = NOW() WHERE id = :id");
                    $stmtUp->execute([':pid' => $paymentIntentId, ':id' => $recargaId]);
                }

                $this->json([
                    'success' => true,
                    'recarga_id' => $recargaId,
                    'moeda' => 'USD',
                    'valor' => $valor,
                    'stripe_required' => true,
                    'payment_intent_id' => $paymentIntentId,
                    'client_secret' => (string) ($pi['client_secret'] ?? ''),
                ]);
                return;
            }

            $billingType = 'PIX';
            if ($metodo === 'boleto') {
                $billingType = 'BOLETO';
            } elseif ($metodo === 'cartao_credito') {
                $billingType = 'CREDIT_CARD';
            }

            $payload = [
                'billingType' => $billingType,
                'customer_name' => $nome,
                'customer_email' => $email,
                'customer_phone' => $telefone,
                'customer_document' => $documento,
                'externalReference' => 'wallet_topup_' . $recargaId,
                'products' => [
                    [
                        'sku' => 'RECARGA_' . $recargaId,
                        'name' => $descricao,
                        'quantity' => 1,
                        'unit_value' => (int) round($valor * 100),
                        'type' => 'service',
                    ]
                ],
                'products_value_cents' => (int) round($valor * 100),
                'shipping_value_cents' => 0,
                'discount_value_cents' => 0,
            ];

            if ($billingType === 'CREDIT_CARD') {
                $payload['card_holder_name'] = $cardHolderName;
                $payload['card_number'] = $cardNumber;
                $payload['card_expiry_month'] = $cardExpiryMonth;
                $payload['card_expiry_year'] = $cardExpiryYear;
                $payload['card_cvv'] = $cardCvv;
                $payload['installments'] = $installments;
            }

            $res = $this->paymentService->processarPagamento($payload, $valor, 'BRL', $descricao);
            if (empty($res['success'])) {
                $this->json(['success' => false, 'error' => (string) ($res['error'] ?? 'Falha ao iniciar pagamento')], 500);
                return;
            }

            $paymentId = (string) ($res['payment_id'] ?? '');
            $invUrl = (string) ($res['invoiceUrl'] ?? '');
            $stmtUp = $db->prepare("UPDATE carteira_recargas SET gateway = 'appmax', payment_id = :pid, invoice_url = :inv, status = 'pending', updated_at = NOW() WHERE id = :id");
            $stmtUp->execute([
                ':pid' => $paymentId,
                ':inv' => $invUrl,
                ':id' => $recargaId,
            ]);

            $this->json([
                'success' => true,
                'recarga_id' => $recargaId,
                'moeda' => 'BRL',
                'valor' => $valor,
                'gateway' => 'appmax',
                'payment_id' => $paymentId,
                'status' => (string) ($res['status'] ?? 'pending'),
                'invoiceUrl' => $invUrl,
                'bankSlipUrl' => (string) ($res['bankSlipUrl'] ?? ''),
                'digitableLine' => (string) ($res['digitableLine'] ?? ''),
                'pix' => (isset($res['pix']) && is_array($res['pix'])) ? $res['pix'] : null,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function carteiraRecargaStripeFinalizar(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioSessao = $this->authService->getUsuarioLogado();
        $usuarioId = (int) ($usuarioSessao['id'] ?? 0);

        $recargaId = (int) ($request->getParam('recarga_id') ?? 0);
        $paymentIntentId = trim((string) ($request->getParam('payment_intent_id') ?? ''));
        if ($usuarioId <= 0 || $recargaId <= 0 || $paymentIntentId === '') {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $this->garantirTabelaCarteiraRecargas($db);

            $stmt = $db->prepare('SELECT id, usuario_id FROM carteira_recargas WHERE id = ? LIMIT 1');
            $stmt->execute([$recargaId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row) || (int) ($row['usuario_id'] ?? 0) !== $usuarioId) {
                $this->json(['success' => false, 'error' => 'Recarga não encontrada'], 404);
                return;
            }

            $pi = $this->paymentService->retrieveStripePaymentIntent($paymentIntentId);
            $status = strtoupper((string) ($pi['status'] ?? ''));
            if ($status !== 'SUCCEEDED') {
                $this->json(['success' => false, 'error' => 'Pagamento não confirmado', 'status' => $status], 400);
                return;
            }

            $stmtUp = $db->prepare("UPDATE carteira_recargas SET gateway = 'stripe', payment_id = :pid, status = 'confirmed', paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = :id");
            $stmtUp->execute([':pid' => $paymentIntentId, ':id' => $recargaId]);

            $this->json([
                'success' => true,
                'recarga_id' => $recargaId,
                'payment_intent_id' => $paymentIntentId,
                'status' => $status,
                'message' => 'Pagamento confirmado. A recarga será creditada na carteira em instantes.'
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function meusDados(Request $request) {
        $this->authService->requerAutenticacao();
        
        if ($request->getMethod() === 'POST') {
            $dados = $request->getParams();
            
            $erros = $this->validarDadosPessoais($dados);
            
            if (empty($erros)) {
                try {
                    // Obter usuário logado
                    $usuarioId = $this->authService->getUsuarioLogado()['id'];
                    
                    // Verificar estrutura da tabela antes de atualizar
                    $this->verificarEstruturaTabela();
                    
                    // Preparar dados para atualização (apenas campos que existem)
                    $dadosAtualizacao = $this->prepararDadosAtualizacao($dados);

                    // Registrar aceite de termos quando solicitado
                    if (!empty($dados['aceitar_termos'])) {
                        try {
                            $stmtCols = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                            if (is_array($cols)) {
                                if (in_array('termos_aceitos_em', $cols, true)) {
                                    $dadosAtualizacao['termos_aceitos_em'] = date('Y-m-d H:i:s');
                                }
                                if (in_array('termos_aceitos_ip', $cols, true)) {
                                    $dadosAtualizacao['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                                }
                                if (in_array('termos_versao', $cols, true)) {
                                    $dadosAtualizacao['termos_versao'] = '1.0';
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    
                    // Atualizar senha se fornecida
                    if (!empty($dados['senha_atual']) && !empty($dados['senha_nova'])) {
                        if ($dados['senha_nova'] === $dados['senha_confirmacao']) {
                            $this->usuarioModel->updatePassword($usuarioId, $dados['senha_nova']);
                        } else {
                            $_SESSION['message'] = 'As senhas não conferem!';
                            $_SESSION['message_type'] = 'danger';
                            $this->redirect('/meus-dados');
                            return;
                        }
                    }
                    
                    // Atualizar dados do usuário
                    $this->usuarioModel->update($usuarioId, $dadosAtualizacao);

                    // Salvar Endereço de Entrega (único/principal) na tabela enderecos
                    $this->salvarEnderecoEntrega((int) $usuarioId, $dados);
                    
                    // Registrar log (com tratamento de erro)
                    try {
                        $this->authService->registrarLogAuditoria(
                            $usuarioId,
                            'atualizar_perfil',
                            'usuarios',
                            $usuarioId,
                            null,
                            $dadosAtualizacao
                        );
                    } catch (\Exception $e) {
                        error_log('Erro ao registrar log de auditoria: ' . $e->getMessage());
                        // Continuar mesmo se o log falhar
                    }
                    
                    $_SESSION['message'] = 'Dados atualizados com sucesso!';
                    $_SESSION['message_type'] = 'success';
                    
                } catch (\Exception $e) {
                    $_SESSION['message'] = 'Erro ao atualizar dados: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'danger';
                    error_log('Erro em meusDados: ' . $e->getMessage());
                }
                
                $this->redirect('/meus-dados');
                return;
            }
        }
        
        // Obter dados completos do usuário
        $usuarioId = (int) ($this->authService->getUsuarioLogado()['id'] ?? 0);
        $usuario = $this->usuarioModel->find($usuarioId);

        $enderecoEntrega = null;
        try {
            $enderecos = $this->usuarioModel->getEnderecos($usuarioId);
            if (is_array($enderecos) && !empty($enderecos)) {
                $enderecoEntrega = $enderecos[0];
            }
        } catch (\Exception $e) {
            $enderecoEntrega = null;
        }
        
        $this->view('usuario/meus-dados', [
            'usuario' => $usuario,
            'enderecoEntrega' => $enderecoEntrega
        ]);
    }

    private function salvarEnderecoEntrega(int $usuarioId, array $dados): void {
        if ($usuarioId <= 0) {
            return;
        }

        $db = \Config\Database::getConnection();

        $cols = [];
        try {
            $stmtCols = $db->query('DESCRIBE enderecos');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        if (empty($cols)) {
            return;
        }

        $usuarioCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
        if ($usuarioCol === '') {
            return;
        }

        $principalCol = in_array('principal', $cols, true) ? 'principal' : (in_array('is_principal', $cols, true) ? 'is_principal' : '');

        $map = [
            'cep' => ['cep'],
            'endereco' => ['endereco', 'logradouro'],
            'numero' => ['numero'],
            'complemento' => ['complemento'],
            'bairro' => ['bairro'],
            'cidade' => ['cidade'],
            'estado' => ['estado', 'uf'],
            'pais' => ['pais', 'country'],
        ];

        $payload = [];
        foreach ($map as $inputKey => $colCandidates) {
            $val = isset($dados[$inputKey]) ? (string) $dados[$inputKey] : '';
            $val = trim($val);
            $colFound = null;
            foreach ($colCandidates as $c) {
                if (in_array($c, $cols, true)) {
                    $colFound = $c;
                    break;
                }
            }
            if ($colFound !== null) {
                $payload[$colFound] = $val;
            }
        }

        if ($principalCol !== '') {
            $payload[$principalCol] = 1;
        }

        try {
            $stmt = $db->prepare('SELECT id FROM enderecos WHERE ' . $usuarioCol . ' = :uid' . ($principalCol !== '' ? (' AND ' . $principalCol . ' = 1') : '') . ' ORDER BY id DESC LIMIT 1');
            $stmt->bindValue(':uid', $usuarioId, \PDO::PARAM_INT);
            $stmt->execute();
            $existingId = (int) ($stmt->fetchColumn() ?: 0);

            if ($existingId > 0) {
                $set = [];
                $params = [':id' => $existingId];
                foreach ($payload as $col => $val) {
                    $set[] = $col . ' = :' . $col;
                    $params[':' . $col] = $val;
                }
                if (!empty($set)) {
                    $sql = 'UPDATE enderecos SET ' . implode(', ', $set) . ' WHERE id = :id';
                    $stUp = $db->prepare($sql);
                    $stUp->execute($params);
                }
            } else {
                $colsIns = [$usuarioCol];
                $valsIns = [':uid'];
                $params = [':uid' => $usuarioId];
                foreach ($payload as $col => $val) {
                    $colsIns[] = $col;
                    $valsIns[] = ':' . $col;
                    $params[':' . $col] = $val;
                }
                $sql = 'INSERT INTO enderecos (' . implode(', ', $colsIns) . ') VALUES (' . implode(', ', $valsIns) . ')';
                $stIn = $db->prepare($sql);
                $stIn->execute($params);
                $existingId = (int) $db->lastInsertId();
            }

            // Garantir único principal
            if ($principalCol !== '' && $existingId > 0) {
                $st = $db->prepare('UPDATE enderecos SET ' . $principalCol . ' = 0 WHERE ' . $usuarioCol . ' = :uid AND id <> :id');
                $st->execute([':uid' => $usuarioId, ':id' => $existingId]);
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    public function meusEnderecos(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioId = (int) ($this->authService->getUsuarioLogado()['id'] ?? 0);
        $usuario = $this->usuarioModel->find($usuarioId);

        $enderecos = [];
        try {
            $enderecos = $this->usuarioModel->getEnderecos($usuarioId);
        } catch (\Exception $e) {
            $enderecos = [];
        }

        $this->view('usuario/meus-enderecos', [
            'usuario' => $usuario,
            'enderecos' => $enderecos,
        ]);
    }

    public function salvarEndereco(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioId = (int) ($this->authService->getUsuarioLogado()['id'] ?? 0);
        $dados = $request->getParams();
        $enderecoId = (int) ($dados['id'] ?? 0);

        try {
            $db = \Config\Database::getConnection();
            if (!$db instanceof \PDO) {
                throw new \Exception('Conexão com o banco indisponível');
            }

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE enderecos');
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $usuarioCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
            if ($usuarioCol === '') {
                throw new \Exception('Tabela de endereços incompatível');
            }

            $principalCol = in_array('principal', $cols, true) ? 'principal' : (in_array('is_principal', $cols, true) ? 'is_principal' : '');

            if ($enderecoId > 0) {
                $stOwn = $db->prepare('SELECT ' . $usuarioCol . ' FROM enderecos WHERE id = ? LIMIT 1');
                $stOwn->execute([(int) $enderecoId]);
                $uidEnd = (int) ($stOwn->fetchColumn() ?: 0);
                if ($uidEnd !== $usuarioId) {
                    throw new \Exception('Endereço inválido');
                }
            }

            $map = [
                'tipo' => ['tipo'],
                'pais' => ['pais', 'country'],
                'cep' => ['cep'],
                'endereco' => ['endereco', 'logradouro'],
                'numero' => ['numero'],
                'complemento' => ['complemento'],
                'bairro' => ['bairro'],
                'cidade' => ['cidade'],
                'estado' => ['estado', 'uf'],
            ];

            $payload = [];
            foreach ($map as $inputKey => $colCandidates) {
                $val = isset($dados[$inputKey]) ? trim((string) $dados[$inputKey]) : '';
                $colFound = null;
                foreach ($colCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $colFound = $c;
                        break;
                    }
                }
                if ($colFound !== null) {
                    $payload[$colFound] = $val;
                }
            }

            if (empty($payload['pais']) && in_array('pais', $cols, true)) {
                $payload['pais'] = 'BR';
            }

            if ($enderecoId > 0) {
                $set = [];
                $params = [':id' => (int) $enderecoId];
                foreach ($payload as $col => $val) {
                    $set[] = $col . ' = :' . $col;
                    $params[':' . $col] = $val;
                }
                if (!empty($set)) {
                    $sql = 'UPDATE enderecos SET ' . implode(', ', $set) . ' WHERE id = :id';
                    $st = $db->prepare($sql);
                    $st->execute($params);
                }
            } else {
                $colsIns = [$usuarioCol];
                $valsIns = [':uid'];
                $params = [':uid' => $usuarioId];

                foreach ($payload as $col => $val) {
                    $colsIns[] = $col;
                    $valsIns[] = ':' . $col;
                    $params[':' . $col] = $val;
                }

                if ($principalCol !== '' && (string) ($dados['principal'] ?? '') === '1') {
                    $colsIns[] = $principalCol;
                    $valsIns[] = ':principal';
                    $params[':principal'] = 1;
                }

                $sql = 'INSERT INTO enderecos (' . implode(', ', $colsIns) . ') VALUES (' . implode(', ', $valsIns) . ')';
                $st = $db->prepare($sql);
                $st->execute($params);
                $enderecoId = (int) $db->lastInsertId();
            }

            if ($principalCol !== '' && $enderecoId > 0 && (string) ($dados['principal'] ?? '') === '1') {
                $st = $db->prepare('UPDATE enderecos SET ' . $principalCol . ' = 0 WHERE ' . $usuarioCol . ' = :uid AND id <> :id');
                $st->execute([':uid' => $usuarioId, ':id' => $enderecoId]);
            }

            $_SESSION['message'] = 'Endereço salvo com sucesso!';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao salvar endereço: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/meus-enderecos');
    }

    public function excluirEndereco(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioId = (int) ($this->authService->getUsuarioLogado()['id'] ?? 0);
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $this->redirect('/meus-enderecos');
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            if (!$db instanceof \PDO) {
                throw new \Exception('Conexão com o banco indisponível');
            }

            $cols = [];
            $stmtCols = $db->query('DESCRIBE enderecos');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            $usuarioCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
            if ($usuarioCol === '') {
                throw new \Exception('Tabela de endereços incompatível');
            }

            $stOwn = $db->prepare('SELECT ' . $usuarioCol . ' FROM enderecos WHERE id = ? LIMIT 1');
            $stOwn->execute([$id]);
            $uidEnd = (int) ($stOwn->fetchColumn() ?: 0);
            if ($uidEnd !== $usuarioId) {
                throw new \Exception('Endereço inválido');
            }

            $st = $db->prepare('DELETE FROM enderecos WHERE id = ? LIMIT 1');
            $st->execute([$id]);

            $_SESSION['message'] = 'Endereço excluído.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao excluir endereço: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/meus-enderecos');
    }

    public function definirEnderecoPrincipal(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioId = (int) ($this->authService->getUsuarioLogado()['id'] ?? 0);
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $this->redirect('/meus-enderecos');
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            if (!$db instanceof \PDO) {
                throw new \Exception('Conexão com o banco indisponível');
            }

            $cols = [];
            $stmtCols = $db->query('DESCRIBE enderecos');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            $usuarioCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
            $principalCol = in_array('principal', $cols, true) ? 'principal' : (in_array('is_principal', $cols, true) ? 'is_principal' : '');
            if ($usuarioCol === '' || $principalCol === '') {
                throw new \Exception('Tabela de endereços incompatível');
            }

            $stOwn = $db->prepare('SELECT ' . $usuarioCol . ' FROM enderecos WHERE id = ? LIMIT 1');
            $stOwn->execute([$id]);
            $uidEnd = (int) ($stOwn->fetchColumn() ?: 0);
            if ($uidEnd !== $usuarioId) {
                throw new \Exception('Endereço inválido');
            }

            $db->beginTransaction();
            $st1 = $db->prepare('UPDATE enderecos SET ' . $principalCol . ' = 0 WHERE ' . $usuarioCol . ' = ?');
            $st1->execute([$usuarioId]);
            $st2 = $db->prepare('UPDATE enderecos SET ' . $principalCol . ' = 1 WHERE id = ?');
            $st2->execute([$id]);
            $db->commit();

            $_SESSION['message'] = 'Endereço principal atualizado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            try {
                if (isset($db) && $db instanceof \PDO && $db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (\Exception $e2) {
            }
            $_SESSION['message'] = 'Erro ao definir principal: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/meus-enderecos');
    }

    public function avatarUpload(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioId = $this->authService->getUsuarioLogado()['id'];

        if (!isset($_FILES['avatar']) || empty($_FILES['avatar']['tmp_name'])) {
            $_SESSION['message'] = 'Selecione uma foto para enviar.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-dados');
            return;
        }

        $file = $_FILES['avatar'];

        if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['message'] = 'Erro ao enviar a foto.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-dados');
            return;
        }

        if (($file['size'] ?? 0) > (3 * 1024 * 1024)) {
            $_SESSION['message'] = 'A foto deve ter no máximo 3MB.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-dados');
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed[$mime])) {
            $_SESSION['message'] = 'Formato inválido. Envie JPG, PNG ou WebP.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-dados');
            return;
        }

        $uploadsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0775, true);
        }

        if (!is_dir($uploadsDir) || !is_writable($uploadsDir)) {
            $_SESSION['message'] = 'Diretório de upload não está disponível.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-dados');
            return;
        }

        $ext = $allowed[$mime];
        $filename = 'u' . (int)$usuarioId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $_SESSION['message'] = 'Não foi possível salvar a foto.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-dados');
            return;
        }

        $relativeUrl = '/uploads/avatars/' . $filename;

        try {
            $stmt = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
            $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $candidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto'];
            $colunaAvatar = null;
            foreach ($candidates as $c) {
                if (in_array($c, $colunas)) {
                    $colunaAvatar = $c;
                    break;
                }
            }

            if (!$colunaAvatar) {
                @unlink($destPath);
                $_SESSION['message'] = 'Sua tabela de usuários não possui coluna para foto de perfil.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/meus-dados');
                return;
            }

            $usuarioAtual = $this->usuarioModel->find($usuarioId);
            $old = $usuarioAtual[$colunaAvatar] ?? '';

            $this->usuarioModel->update($usuarioId, [$colunaAvatar => $relativeUrl]);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['usuario_avatar'] = $relativeUrl;

            if (!empty($old) && is_string($old) && strpos($old, '/uploads/avatars/') === 0) {
                $oldPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $old);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $_SESSION['message'] = 'Foto de perfil atualizada com sucesso!';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            @unlink($destPath);
            $_SESSION['message'] = 'Erro ao atualizar foto de perfil.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/meus-dados');
    }

    public function avatarRemover(Request $request) {
        $this->authService->requerAutenticacao();

        $usuarioId = $this->authService->getUsuarioLogado()['id'];

        try {
            $stmt = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
            $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $candidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto'];
            $colunaAvatar = null;
            foreach ($candidates as $c) {
                if (in_array($c, $colunas)) {
                    $colunaAvatar = $c;
                    break;
                }
            }

            if (!$colunaAvatar) {
                $_SESSION['message'] = 'Sua tabela de usuários não possui coluna para foto de perfil.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/meus-dados');
                return;
            }

            $usuarioAtual = $this->usuarioModel->find($usuarioId);
            $old = $usuarioAtual[$colunaAvatar] ?? '';

            $this->usuarioModel->update($usuarioId, [$colunaAvatar => null]);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['usuario_avatar'] = null;

            if (!empty($old) && is_string($old) && strpos($old, '/uploads/avatars/') === 0) {
                $oldPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $old);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $_SESSION['message'] = 'Foto de perfil removida.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao remover foto de perfil.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/meus-dados');
    }
    
    private function verificarEstruturaTabela() {
        try {
            $stmt = $this->usuarioModel->getConnection()->query("DESCRIBE usuarios");
            $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $colunasNecessarias = ['nome', 'email', 'telefone', 'documento', 'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'notificacoes_email', 'notificacoes_sms', 'idioma'];
            
            foreach ($colunasNecessarias as $coluna) {
                if (!in_array($coluna, $colunas)) {
                    error_log("Coluna ausente na tabela usuarios: {$coluna}");
                }
            }
        } catch (\Exception $e) {
            error_log('Erro ao verificar estrutura da tabela: ' . $e->getMessage());
        }
    }

    public function pedidoPdf(Request $request) {
        $this->authService->requerAutenticacao();

        $pedidoId = $request->getParam('id');
        $usuario = $this->authService->getUsuarioLogado();

        try {
            $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
            if (!$pedido || $pedido['usuario_id'] != $usuario['id']) {
                $this->view('errors/404');
                return;
            }

            $itens = $pedido['items'] ?? [];

            $paymentDetails = null;
            if (!empty($pedido['payment_gateway']) && $pedido['payment_gateway'] === 'appmax') {
                $billingType = strtoupper((string) ($pedido['forma_pagamento'] ?? ''));
                if ($billingType === 'CARTAO_CREDITO') {
                    $billingType = 'CREDIT_CARD';
                }

                $invoiceUrl = (string) ($pedido['payment_invoice_url'] ?? ($pedido['invoice_url'] ?? ($pedido['invoiceUrl'] ?? '')));
                $bankSlipUrl = (string) ($pedido['payment_bank_slip_url'] ?? ($pedido['bank_slip_url'] ?? ($pedido['bankSlipUrl'] ?? '')));
                $digitableLine = (string) ($pedido['payment_digitable_line'] ?? ($pedido['digitable_line'] ?? ($pedido['digitableLine'] ?? ($pedido['linha_digitavel'] ?? ''))));

                $paymentDetails = [
                    'billingType' => $billingType,
                    'invoiceUrl' => $invoiceUrl !== '' ? $invoiceUrl : null,
                    'bankSlipUrl' => $bankSlipUrl !== '' ? $bankSlipUrl : null,
                    'digitableLine' => $digitableLine !== '' ? $digitableLine : null,
                    'status' => (string) ($pedido['payment_status'] ?? ''),
                ];
            } else {
                try {
                    if (!empty($pedido['pagamento_gateway']) && $pedido['pagamento_gateway'] === 'asaas' && !empty($pedido['pagamento_transacao'])) {
                        $paymentDetails = $this->paymentService->obterPagamentoAsaas((string) $pedido['pagamento_transacao']);
                    } elseif (!empty($pedido['payment_gateway']) && $pedido['payment_gateway'] === 'asaas' && !empty($pedido['payment_id'])) {
                        $paymentDetails = $this->paymentService->obterPagamentoAsaas((string) $pedido['payment_id']);
                    }
                } catch (\Exception $e) {
                    $paymentDetails = null;
                }
            }

            $svc = new PdfPedidoService();
            $html = $svc->renderPedidoHtml($pedido, is_array($itens) ? $itens : [], is_array($paymentDetails) ? $paymentDetails : null);
            $svc->outputPdfOrHtml($html, 'pedido_' . (string) ($pedido['codigo_pedido'] ?? $pedido['id'] ?? $pedidoId));
        } catch (\Exception $e) {
            echo 'Erro ao gerar PDF: ' . $e->getMessage();
        }
    }

    public function reemitirPagamento(Request $request) {
        $this->authService->requerAutenticacao();

        $pedidoId = (int) $request->getParam('id');
        $usuario = $this->authService->getUsuarioLogado();

        if (empty($pedidoId)) {
            $this->redirect('/meus-pedidos');
            return;
        }

        try {
            $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
            if (!$pedido || (int) ($pedido['usuario_id'] ?? 0) !== (int) ($usuario['id'] ?? 0)) {
                $this->redirect('/meus-pedidos');
                return;
            }

            $gateway = (string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''));
            if ($gateway !== 'asaas') {
                $this->redirect('/pedido/detalhes/' . $pedidoId . '?reemitido=0');
                return;
            }

            $this->paymentService->reemitirCobrancaAsaasPorPedido($pedidoId);
            $this->redirect('/pedido/detalhes/' . $pedidoId . '?reemitido=1');
        } catch (\Exception $e) {
            $this->redirect('/pedido/detalhes/' . $pedidoId . '?reemitido=0');
        }
    }
    
    private function prepararDadosAtualizacao($dados) {
        // Obter colunas existentes na tabela
        try {
            $stmt = $this->usuarioModel->getConnection()->query("DESCRIBE usuarios");
            $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            // Se não conseguir verificar, usar array básico
            $colunas = ['id', 'email', 'senha'];
        }
        
        $dadosAtualizacao = [];

        // Mapeamento flexível (compatibilidade com diferentes schemas)
        $mapeamento = [
            'nome' => ['nome', 'name'],
            'email' => ['email'],
            'telefone' => ['telefone', 'phone'],
            'documento' => ['documento', 'cpf'],
            'cep' => ['cep', 'zip_code'],
            'endereco' => ['endereco', 'address'],
            'numero' => ['numero', 'number'],
            'complemento' => ['complemento'],
            'bairro' => ['bairro', 'neighborhood'],
            'cidade' => ['cidade', 'city'],
            'estado' => ['estado', 'state'],
            'data_nascimento' => ['data_nascimento', 'birth_date'],
            'pais_residencia' => ['pais_residencia', 'country_of_residence'],
            'notificacoes_email' => ['notificacoes_email'],
            'notificacoes_sms' => ['notificacoes_sms'],
            'idioma' => ['idioma']
        ];

        foreach ($mapeamento as $campoForm => $colunasBanco) {
            $colunaBanco = null;
            foreach ($colunasBanco as $c) {
                if (in_array($c, $colunas, true)) {
                    $colunaBanco = $c;
                    break;
                }
            }
            if ($colunaBanco === null) {
                continue;
            }

            if ($colunaBanco === 'notificacoes_email' || $colunaBanco === 'notificacoes_sms') {
                $dadosAtualizacao[$colunaBanco] = isset($dados[$campoForm]) ? 1 : 0;
                continue;
            }

            $val = $dados[$campoForm] ?? null;
            if ($campoForm === 'documento') {
                $doc = preg_replace('/\D+/', '', (string) ($val ?? ''));
                $val = $doc === '' ? null : $doc;
            }
            $dadosAtualizacao[$colunaBanco] = $val;
        }
        
        // Adicionar campos obrigatórios se existirem
        if (in_array('perfil', $colunas)) {
            $dadosAtualizacao['perfil'] = 'cliente';
        }
        if (in_array('status', $colunas)) {
            $dadosAtualizacao['status'] = 'ativo';
        }
        if (in_array('creditos_disponiveis', $colunas)) {
            $dadosAtualizacao['creditos_disponiveis'] = 0;
        }
        
        return $dadosAtualizacao;
    }

    public function meusPedidos(Request $request) {
        $this->authService->requerAutenticacao();
        
        $usuarioSessao = $this->authService->getUsuarioLogado();
        $usuario = $this->usuarioModel->find((int) ($usuarioSessao['id'] ?? 0));
        $pagina = (int) $request->getParam('pagina', 1);
        if ($pagina < 1) {
            $pagina = 1;
        }
        $limite = 10;
        $offset = ($pagina - 1) * $limite;
        
        // Obter pedidos reais do usuário
        try {
            $pedidos = $this->pedidoModel->getPedidos((int) ($usuarioSessao['id'] ?? 0), $limite, $offset);
        } catch (\Exception $e) {
            // Se houver erro, usar array vazio e registrar log
            error_log('Erro ao obter pedidos do usuário: ' . $e->getMessage());
            $pedidos = [];
        }

        $total = 0;
        try {
            $total = $this->pedidoModel->getTotalPedidosUsuario((int) ($usuarioSessao['id'] ?? 0));
        } catch (\Exception $e) {
            $total = is_array($pedidos) ? count($pedidos) : 0;
        }
        $totalPaginas = (int) ceil(((int) $total) / $limite);
        if ($totalPaginas < 1) {
            $totalPaginas = 1;
        }
        
        $this->view('usuario/meus-pedidos', [
            'usuario' => $usuario,
            'pedidos' => $pedidos,
            'pagina' => $pagina,
            'total' => (int) $total,
            'total_paginas' => $totalPaginas
        ]);
    }

    public function pedidoDetalhes(Request $request) {
        $this->authService->requerAutenticacao();
        
        $pedidoId = $request->getParam('id');
        $usuarioSessao = $this->authService->getUsuarioLogado();
        $usuario = $this->usuarioModel->find((int) ($usuarioSessao['id'] ?? 0));
        
        // Debug
        error_log("Tentando acessar detalhes do pedido ID: {$pedidoId} para usuário: {$usuarioSessao['id']}");
        
        try {
            $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
            
            error_log("Pedido encontrado: " . ($pedido ? 'SIM' : 'NÃO'));
            if ($pedido) {
                error_log("Dono do pedido: {$pedido['usuario_id']}, Usuário logado: {$usuarioSessao['id']}");
                error_log("Itens do pedido: " . count($pedido['items']));
                error_log("Valor total: " . ($pedido['valor_total'] ?? 0));
            }
            
            if (!$pedido || $pedido['usuario_id'] != ($usuarioSessao['id'] ?? null)) {
                error_log("Acesso negado ao pedido {$pedidoId}");
                $this->view('errors/404');
                return;
            }
            
            $historico = $this->pedidoModel->getRastreamento($pedidoId);

            $paymentDetails = null;
            $pixQrCode = null;

            $gatewayPedido = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
            if ($gatewayPedido === 'appmax') {
                $billingType = strtoupper((string) ($pedido['forma_pagamento'] ?? ''));
                if ($billingType === 'CARTAO_CREDITO') {
                    $billingType = 'CREDIT_CARD';
                }

                $invoiceUrl = (string) ($pedido['payment_invoice_url'] ?? ($pedido['invoice_url'] ?? ($pedido['invoiceUrl'] ?? '')));
                $bankSlipUrl = (string) ($pedido['payment_bank_slip_url'] ?? ($pedido['bank_slip_url'] ?? ($pedido['bankSlipUrl'] ?? '')));
                $digitableLine = (string) ($pedido['payment_digitable_line'] ?? ($pedido['digitable_line'] ?? ($pedido['digitableLine'] ?? ($pedido['linha_digitavel'] ?? ''))));

                $paymentDetails = [
                    'billingType' => $billingType,
                    'invoiceUrl' => $invoiceUrl !== '' ? $invoiceUrl : null,
                    'bankSlipUrl' => $bankSlipUrl !== '' ? $bankSlipUrl : null,
                    'digitableLine' => $digitableLine !== '' ? $digitableLine : null,
                    'status' => (string) ($pedido['payment_status'] ?? ''),
                ];

                if ($billingType === 'PIX') {
                    $pixImg = (string) ($pedido['payment_pix_encoded_image'] ?? ($pedido['pix_encoded_image'] ?? ($pedido['pix_qr_base64'] ?? ($pedido['pix_qr'] ?? ''))));
                    $pixPayload = (string) ($pedido['payment_pix_payload'] ?? ($pedido['pix_payload'] ?? ($pedido['pix_emv'] ?? ($pedido['pix_copy_paste'] ?? ''))));
                    if ($pixImg !== '' || $pixPayload !== '') {
                        $pixQrCode = [
                            'encodedImage' => $pixImg !== '' ? $pixImg : null,
                            'payload' => $pixPayload !== '' ? $pixPayload : null,
                        ];
                    }
                }
            } elseif ($gatewayPedido === 'stripe') {
                $invoiceUrl = (string) ($pedido['payment_invoice_url'] ?? ($pedido['invoice_url'] ?? ($pedido['invoiceUrl'] ?? '')));
                $paymentDetails = [
                    'billingType' => 'CREDIT_CARD',
                    'invoiceUrl' => $invoiceUrl !== '' ? $invoiceUrl : null,
                    'status' => (string) ($pedido['payment_status'] ?? ''),
                ];
            } else {
                try {
                    if (!empty($pedido['pagamento_gateway']) && $pedido['pagamento_gateway'] === 'asaas' && !empty($pedido['pagamento_transacao'])) {
                        $paymentDetails = $this->paymentService->obterPagamentoAsaas((string) $pedido['pagamento_transacao']);
                        if (strtoupper((string) ($paymentDetails['billingType'] ?? '')) === 'PIX') {
                            try {
                                $pixQrCode = $this->paymentService->obterPixQrCodeAsaas((string) $pedido['pagamento_transacao']);
                            } catch (\Exception $e) {
                            }
                        }
                    } elseif (!empty($pedido['payment_gateway']) && $pedido['payment_gateway'] === 'asaas' && !empty($pedido['payment_id'])) {
                        $paymentDetails = $this->paymentService->obterPagamentoAsaas((string) $pedido['payment_id']);
                        if (strtoupper((string) ($paymentDetails['billingType'] ?? '')) === 'PIX') {
                            try {
                                $pixQrCode = $this->paymentService->obterPixQrCodeAsaas((string) $pedido['payment_id']);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }
            
            $this->view('usuario/pedido-detalhes', [
                'pedido' => $pedido,
                'historico' => $historico,
                'usuario' => $usuario,
                'paymentDetails' => $paymentDetails,
                'pixQrCode' => $pixQrCode
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro ao obter detalhes do pedido: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            $this->view('errors/500');
        }
    }

    private function validarDadosPessoais($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) {
            $erros[] = 'Nome é obrigatório';
        }
        
        if (empty($dados['telefone'])) {
            $erros[] = 'Telefone é obrigatório';
        }

        if (empty($dados['data_nascimento'])) {
            $erros[] = 'Data de nascimento é obrigatória';
        }

        if (empty($dados['pais_residencia'])) {
            $erros[] = 'País de residência é obrigatório';
        }

        $pais = strtoupper(trim((string) ($dados['pais_residencia'] ?? 'BR')));
        if ($pais === '') {
            $pais = 'BR';
        }

        $doc = CpfValidator::onlyDigits((string) ($dados['documento'] ?? ''));
        if ($pais === 'BR') {
            if ($doc === '' || strlen($doc) < 11) {
                $erros[] = 'CPF é obrigatório para residentes no Brasil';
            } elseif (strlen($doc) === 11 && !CpfValidator::isValid($doc)) {
                $erros[] = 'CPF inválido';
            }
        } else {
            if ($doc !== '' && strlen($doc) === 11 && !CpfValidator::isValid($doc)) {
                $erros[] = 'CPF inválido';
            }
        }

        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if (empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if ($pais === 'BR' && empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';

        $estado = '';
        if (isset($dados['estado']) && trim((string) $dados['estado']) !== '') {
            $estado = trim((string) $dados['estado']);
        } elseif (isset($dados['estado_text']) && trim((string) $dados['estado_text']) !== '') {
            $estado = trim((string) $dados['estado_text']);
        } elseif (isset($dados['estado_ui']) && trim((string) $dados['estado_ui']) !== '') {
            $estado = trim((string) $dados['estado_ui']);
        }
        if ($estado === '') $erros[] = 'Estado é obrigatório';
        
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        return $erros;
    }
}
