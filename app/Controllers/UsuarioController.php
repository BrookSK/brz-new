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
        $pedidos = $this->pedidoModel->getPedidos($usuario['id']);
        
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
        
        $enderecos = $this->usuarioModel->getEnderecos($usuario['id']);
        
        $this->view('usuario/minha-conta', [
            'usuario' => $usuario,
            'enderecos' => $enderecos
        ]);
    }

    public function meusDados(Request $request) {
        $this->authService->requerAutenticacao();
        
        if ($request->getMethod() === 'POST') {
            $dados = $request->getParams();
            
            $erros = $this->validarDadosPessoais($dados);
            
            if (empty($erros)) {
                try {
                    $this->usuarioModel->update($this->authService->getUsuarioLogado()['id'], $dados);
                    $this->authService->registrarLogAuditoria(
                        $this->authService->getUsuarioLogado()['id'],
                        'atualizar_perfil',
                        'usuarios',
                        $this->authService->getUsuarioLogado()['id'],
                        null,
                        $dados
                    );
                    
                    $_SESSION['message'] = 'Dados atualizados com sucesso!';
                    $_SESSION['message_type'] = 'success';
                    
                } catch (\Exception $e) {
                    $_SESSION['message'] = 'Erro ao atualizar dados: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'danger';
                }
                
                $this->redirect('/minha-conta');
                return;
            }
        }
        
        $this->view('usuario/meus-dados', [
            'usuario' => $this->authService->getUsuarioLogado()
        ]);
    }

    public function meusPedidos(Request $request) {
        $this->authService->requerAutenticacao();
        
        $usuario = $this->authService->getUsuarioLogado();
        $pagina = $request->getParam('pagina', 1);
        $limite = 10;
        $offset = ($pagina - 1) * $limite;
        
        $pedidos = $this->pedidoModel->getPedidos($usuario['id'], $limite, $offset);
        
        $this->view('usuario/meus-pedidos', [
            'usuario' => $usuario,
            'pedidos' => $pedidos,
            'pagina' => $pagina,
            'total' => ceil(count($pedidos) / $limite),
            'total_paginas' => ceil($this->pedidoModel->getTotalPedidos($usuario['id']) / $limite)
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
