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
                    
                    // Preparar dados para atualização
                    $dadosAtualizacao = [
                        'nome' => $dados['nome'] ?? '',
                        'email' => $dados['email'] ?? '',
                        'telefone' => $dados['telefone'] ?? '',
                        'documento' => $dados['documento'] ?? '',
                        'cep' => $dados['cep'] ?? '',
                        'endereco' => $dados['endereco'] ?? '',
                        'numero' => $dados['numero'] ?? '',
                        'complemento' => $dados['complemento'] ?? '',
                        'bairro' => $dados['bairro'] ?? '',
                        'cidade' => $dados['cidade'] ?? '',
                        'estado' => $dados['estado'] ?? '',
                        'notificacoes_email' => isset($dados['notificacoes_email']) ? 1 : 0,
                        'notificacoes_sms' => isset($dados['notificacoes_sms']) ? 1 : 0,
                        'idioma' => $dados['idioma'] ?? 'pt-BR',
                        'perfil' => 'cliente',
                        'status' => 'ativo',
                        'creditos_disponiveis' => 0
                    ];
                    
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
                    
                    // Registrar log
                    $this->authService->registrarLogAuditoria(
                        $usuarioId,
                        'atualizar_perfil',
                        'usuarios',
                        $usuarioId,
                        null,
                        $dadosAtualizacao
                    );
                    
                    $_SESSION['message'] = 'Dados atualizados com sucesso!';
                    $_SESSION['message_type'] = 'success';
                    
                } catch (\Exception $e) {
                    $_SESSION['message'] = 'Erro ao atualizar dados: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'danger';
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
