<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Models\Usuario;

class AuthController extends Controller {
    private $authService;
    private $usuarioModel;
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->usuarioModel = new Usuario();
    }
    
    public function login(Request $request) {
        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($usuario['perfil'] === 'admin') {
                $this->redirect('/admin/dashboard');
            } else {
                $this->redirect('/minha-conta');
            }
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $email = $request->getParam('email');
            $senha = $request->getParam('senha');
            $isAdmin = $request->getParam('admin_login') === '1';
            
            try {
                $usuario = $this->authService->autenticar($email, $senha);
                
                if ($usuario) {
                    // Verificar se é login admin
                    if ($isAdmin && $usuario['perfil'] !== 'admin') {
                        $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                        $_SESSION['message_type'] = 'danger';
                        $this->redirect('/login');
                        return;
                    }
                    
                    // Redirecionar baseado no perfil
                    if ($isAdmin || $usuario['perfil'] === 'admin') {
                        $_SESSION['message'] = 'Bem-vindo, ' . $usuario['nome'] . '!';
                        $_SESSION['message_type'] = 'success';
                        $this->redirect('/admin/dashboard');
                    } else {
                        $_SESSION['message'] = 'Bem-vendo de volta, ' . $usuario['nome'] . '!';
                        $_SESSION['message_type'] = 'success';
                        $this->redirect('/minha-conta');
                    }
                    return;
                } else {
                    $_SESSION['message'] = 'E-mail ou senha incorretos';
                    $_SESSION['message_type'] = 'danger';
                }
            } catch (\Exception $e) {
                $_SESSION['message'] = 'Erro ao fazer login: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
            }
            
            $this->redirect('/login');
            return;
        }
        
        $this->view('auth/login');
    }
    
    public function loginAdmin(Request $request) {
        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($usuario['perfil'] === 'admin') {
                $this->redirect('/admin/dashboard');
            } else {
                $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/loginadmin');
            }
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $email = $request->getParam('email');
            $senha = $request->getParam('senha');
            
            try {
                $usuario = $this->authService->autenticar($email, $senha);
                
                if ($usuario) {
                    // Verificar se é admin
                    if ($usuario['perfil'] !== 'admin') {
                        $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                        $_SESSION['message_type'] = 'danger';
                        $this->redirect('/loginadmin');
                        return;
                    }
                    
                    $_SESSION['message'] = 'Bem-vindo, ' . $usuario['nome'] . '! Acesso administrativo.';
                    $_SESSION['message_type'] = 'success';
                    $this->redirect('/admin/dashboard');
                    return;
                } else {
                    $_SESSION['message'] = 'E-mail ou senha incorretos';
                    $_SESSION['message_type'] = 'danger';
                }
            } catch (\Exception $e) {
                $_SESSION['message'] = 'Erro ao fazer login: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
            }
            
            $this->redirect('/loginadmin');
            return;
        }
        
        $this->view('auth/loginadmin');
    }
    
    public function register(Request $request) {
        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($usuario['perfil'] === 'admin') {
                $this->redirect('/admin/dashboard');
            } else {
                $this->redirect('/minha-conta');
            }
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $dados = $request->getParams();
            
            // Validar dados
            $erros = $this->validarRegistro($dados);
            
            if (empty($erros)) {
                try {
                    // Verificar se e-mail já existe
                    if ($this->usuarioModel->findByEmail($dados['email'])) {
                        $erros[] = 'E-mail já cadastrado';
                    }
                    
                    // Verificar se documento já existe
                    if ($this->usuarioModel->findByDocumento($dados['documento'])) {
                        $erros[] = 'Documento já cadastrado';
                    }
                    
                    if (empty($erros)) {
                        $usuarioId = $this->usuarioModel->create([
                            'nome' => $dados['nome'],
                            'email' => $dados['email'],
                            'senha' => $dados['senha'],
                            'telefone' => $dados['telefone'],
                            'documento' => $dados['documento'],
                            'perfil' => 'cliente'
                        ]);
                        
                        if ($usuarioId) {
                            // Fazer login automático
                            $usuario = $this->authService->login($dados['email'], $dados['senha']);
                            $this->redirect('/minha-conta');
                        }
                    }
                } catch (\Exception $e) {
                    $erros[] = 'Erro ao criar conta: ' . $e->getMessage();
                }
            }
            
            $this->view('auth/register', [
                'erros' => $erros,
                'dados' => $dados
            ]);
        } else {
            $this->view('auth/register');
        }
    }
    
    private function validarRegistro($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) {
            $erros[] = 'Nome é obrigatório';
        }
        
        if (empty($dados['email'])) {
            $erros[] = 'E-mail é obrigatório';
        } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        if (empty($dados['senha'])) {
            $erros[] = 'Senha é obrigatória';
        } elseif (strlen($dados['senha']) < 6) {
            $erros[] = 'Senha deve ter pelo menos 6 caracteres';
        }
        
        if (empty($dados['senha_confirmacao'])) {
            $erros[] = 'Confirmação de senha é obrigatória';
        } elseif ($dados['senha'] !== $dados['senha_confirmacao']) {
            $erros[] = 'Senhas não conferem';
        }
        
        if (empty($dados['documento'])) {
            $erros[] = 'Documento é obrigatório';
        }
        
        if (empty($dados['telefone'])) {
            $erros[] = 'Telefone é obrigatório';
        }
        
        return $erros;
    }
    
    public function perfil(Request $request) {
        $this->authService->requerAutenticacao();
        
        $usuario = $this->authService->getUsuarioLogado();
        $usuarioCompleto = $this->usuarioModel->find($usuario['id']);
        
        if ($request->getMethod() === 'POST') {
            $dados = $request->getParams();
            
            // Validar dados
            $erros = $this->validarPerfil($dados);
            
            if (empty($erros)) {
                try {
                    $updateData = [
                        'nome' => $dados['nome'],
                        'telefone' => $dados['telefone']
                    ];
                    
                    if (!empty($dados['senha_atual'])) {
                        if (!$this->usuarioModel->authenticate($usuarioCompleto['email'], $dados['senha_atual'])) {
                            $erros[] = 'Senha atual incorreta';
                        } elseif (!empty($dados['nova_senha'])) {
                            if (strlen($dados['nova_senha']) < 6) {
                                $erros[] = 'Nova senha deve ter pelo menos 6 caracteres';
                            } elseif ($dados['nova_senha'] !== $dados['nova_senha_confirmacao']) {
                                $erros[] = 'Novas senhas não conferem';
                            } else {
                                $this->usuarioModel->updatePassword($usuario['id'], $dados['nova_senha']);
                            }
                        }
                    }
                    
                    if (empty($erros)) {
                        $this->usuarioModel->update($usuario['id'], $updateData);
                        
                        // Atualizar sessão
                        $_SESSION['usuario_nome'] = $dados['nome'];
                        
                        $this->view('auth/perfil', [
                            'success' => 'Perfil atualizado com sucesso',
                            'usuario' => $this->usuarioModel->find($usuario['id'])
                        ]);
                        return;
                    }
                } catch (\Exception $e) {
                    $erros[] = 'Erro ao atualizar perfil: ' . $e->getMessage();
                }
            }
            
            $this->view('auth/perfil', [
                'erros' => $erros,
                'usuario' => array_merge($usuarioCompleto, $dados)
            ]);
        } else {
            $this->view('auth/perfil', [
                'usuario' => $usuarioCompleto
            ]);
        }
    }
    
    private function validarPerfil($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) {
            $erros[] = 'Nome é obrigatório';
        }
        
        if (empty($dados['telefone'])) {
            $erros[] = 'Telefone é obrigatório';
        }
        
        return $erros;
    }
}
