<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Models\Usuario;
use App\Models\PedidoEcommerce;
use App\Models\Carrinho;

class UsuarioController extends Controller {
    private $authService;
    private $usuarioModel;
    private $pedidoModel;
    private $carrinhoModel;

    public function __construct() {
        $this->authService = new AuthService();
        $this->usuarioModel = new Usuario();
        $this->pedidoModel = new PedidoEcommerce();
        $this->carrinhoModel = new Carrinho();
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
        $total_pedidos = count($pedidos);
        
        $this->view('usuario/minha-conta', [
            'usuario' => $usuario,
            'enderecos' => $enderecos,
            'pedidos' => $pedidos,
            'pedidos_recentes' => $pedidos_recentes,
            'total_pedidos' => $total_pedidos
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
        
        try {
            $pedido = $this->pedidoModel->getComDetalhes($pedidoId);
            
            if (!$pedido || $pedido['usuario_id'] !== $usuario['id']) {
                $this->view('errors/404');
                return;
            }
            
            $historico = $this->pedidoModel->getRastreamento($pedidoId);
            
            $this->view('usuario/pedido-detalhes', [
                'pedido' => $pedido,
                'historico' => $historico,
                'usuario' => $usuario
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro ao obter detalhes do pedido: ' . $e->getMessage());
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
