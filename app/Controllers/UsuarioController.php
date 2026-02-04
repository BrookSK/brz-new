<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Services\PdfPedidoService;
use App\Models\Usuario;
use App\Models\PedidoEcommerce;
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

        $orcamentosAssessoria = [];
        try {
            $orcModel = new AssessoriaOrcamento();
            $orcamentosAssessoria = $orcModel->getByUsuarioId((int) $usuario['id'], 10);
        } catch (\Exception $e) {
            $orcamentosAssessoria = [];
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
            'orcamentos_assessoria' => $orcamentosAssessoria
        ]);
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
        $usuario = $this->usuarioModel->find($this->authService->getUsuarioLogado()['id']);
        
        $this->view('usuario/meus-dados', [
            'usuario' => $usuario
        ]);
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
        
        // Mapeamento de campos do formulário para colunas do banco
        $mapeamento = [
            'nome' => 'nome',
            'email' => 'email',
            'telefone' => 'telefone',
            'documento' => 'documento',
            'cep' => 'cep',
            'endereco' => 'endereco',
            'numero' => 'numero',
            'complemento' => 'complemento',
            'bairro' => 'bairro',
            'cidade' => 'cidade',
            'estado' => 'estado',
            'data_nascimento' => 'data_nascimento',
            'pais_residencia' => 'pais_residencia',
            'notificacoes_email' => 'notificacoes_email',
            'notificacoes_sms' => 'notificacoes_sms',
            'idioma' => 'idioma'
        ];
        
        // Adicionar apenas campos que existem na tabela
        foreach ($mapeamento as $campoForm => $colunaBanco) {
            if (in_array($colunaBanco, $colunas)) {
                if ($colunaBanco === 'notificacoes_email' || $colunaBanco === 'notificacoes_sms') {
                    $dadosAtualizacao[$colunaBanco] = isset($dados[$campoForm]) ? 1 : 0;
                } else {
                    $dadosAtualizacao[$colunaBanco] = $dados[$campoForm] ?? '';
                }
            }
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

        if (empty($dados['documento'])) {
            $erros[] = 'Documento é obrigatório';
        }

        if (empty($dados['data_nascimento'])) {
            $erros[] = 'Data de nascimento é obrigatória';
        }

        if (empty($dados['pais_residencia'])) {
            $erros[] = 'País de residência é obrigatório';
        }

        $pais = strtoupper(trim((string) ($dados['pais_residencia'] ?? 'BR')));
        if ($pais === 'BR') {
            $doc = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
            if ($doc === '' || strlen($doc) < 11) {
                $erros[] = 'CPF é obrigatório para residentes no Brasil';
            }
        }

        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if (empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if (empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';
        if (empty($dados['estado'])) $erros[] = 'Estado é obrigatório';
        
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        return $erros;
    }
}
