<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
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
        
        // Obter pedidos reais do usuário
        try {
            $pedidos = $this->pedidoModel->getPedidos($usuario['id'], 10, 0);
        } catch (\Exception $e) {
            // Se houver erro, usar array vazio e registrar log
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

            $stmtTotal = $db->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = ?');
            $stmtTotal->execute([(int) $usuario['id']]);
            $stats['total_pedidos'] = (int) $stmtTotal->fetchColumn();

            $stmtAtivos = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id = ? AND status IN ('pendente','processando','enviado')");
            $stmtAtivos->execute([(int) $usuario['id']]);
            $stats['pedidos_ativos'] = (int) $stmtAtivos->fetchColumn();

            if ($moedaCol !== null) {
                $stmtSum = $db->prepare("SELECT UPPER(COALESCE({$moedaCol}, 'BRL')) AS moeda, SUM(COALESCE({$totalCol},0)) AS total FROM pedidos WHERE usuario_id = ? GROUP BY UPPER(COALESCE({$moedaCol}, 'BRL'))");
                $stmtSum->execute([(int) $usuario['id']]);
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
                $stmtSum = $db->prepare("SELECT SUM(COALESCE({$totalCol},0)) AS total FROM pedidos WHERE usuario_id = ?");
                $stmtSum->execute([(int) $usuario['id']]);
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
        
        $usuario = $this->authService->getUsuarioLogado();
        $pagina = $request->getParam('pagina', 1);
        $limite = 10;
        $offset = ($pagina - 1) * $limite;
        
        // Obter pedidos reais do usuário
        try {
            $pedidos = $this->pedidoModel->getPedidos($usuario['id'], $limite, $offset);
        } catch (\Exception $e) {
            // Se houver erro, usar array vazio e registrar log
            error_log('Erro ao obter pedidos do usuário: ' . $e->getMessage());
            $pedidos = [];
        }
        
        $this->view('usuario/meus-pedidos', [
            'usuario' => $usuario,
            'pedidos' => $pedidos,
            'pagina' => $pagina,
            'total' => count($pedidos),
            'total_paginas' => ceil(count($pedidos) / $limite)
        ]);
    }

    public function pedidoDetalhes(Request $request) {
        $this->authService->requerAutenticacao();
        
        $pedidoId = $request->getParam('id');
        $usuario = $this->authService->getUsuarioLogado();
        
        // Debug
        error_log("Tentando acessar detalhes do pedido ID: {$pedidoId} para usuário: {$usuario['id']}");
        
        try {
            $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
            
            error_log("Pedido encontrado: " . ($pedido ? 'SIM' : 'NÃO'));
            if ($pedido) {
                error_log("Dono do pedido: {$pedido['usuario_id']}, Usuário logado: {$usuario['id']}");
                error_log("Itens do pedido: " . count($pedido['items']));
                error_log("Valor total: " . ($pedido['valor_total'] ?? 0));
            }
            
            if (!$pedido || $pedido['usuario_id'] != $usuario['id']) {
                error_log("Acesso negado ao pedido {$pedidoId}");
                $this->view('errors/404');
                return;
            }
            
            $historico = $this->pedidoModel->getRastreamento($pedidoId);

            $paymentDetails = null;
            $pixQrCode = null;
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
        
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        return $erros;
    }
}
