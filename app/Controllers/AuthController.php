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
        $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
        if ($redirectTo === '') {
            try {
                $redirectTo = (string) ($_GET['redirect'] ?? '');
            } catch (\Exception $e) {
            }
        }

        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($usuario['perfil'] === 'admin') {
                $this->redirect('/admin/dashboard');
            } else {
                $this->redirect($redirectTo !== '' ? $redirectTo : '/minha-conta');
            }
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $email = $request->getParam('email');
            $senha = $request->getParam('senha');
            $isAdmin = $request->getParam('admin_login') === '1';
            $aceiteTermos = (string) ($request->getParam('consentimento_legal', '') ?? '');
            if ($redirectTo === '') {
                $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
            }
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
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
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'redirect' => '/admin/dashboard']);
                            return;
                        }
                        $this->redirect('/admin/dashboard');
                    } else {
                        $usuarioCompleto = null;
                        try {
                            $usuarioCompleto = $this->usuarioModel->find((int) ($usuario['id'] ?? 0));
                        } catch (\Exception $e) {
                            $usuarioCompleto = null;
                        }

                        if (is_array($usuarioCompleto) && !empty($usuarioCompleto)) {
                            $precisaSalvarTermos = !$this->usuarioModel->hasAcceptedTerms($usuarioCompleto);
                            if ($precisaSalvarTermos) {
                                if ($aceiteTermos === '' || $aceiteTermos === '0') {
                                    $_SESSION['message'] = 'É necessário aceitar os termos e condições para entrar.';
                                    $_SESSION['message_type'] = 'warning';
                                    if ($isAjax) {
                                        header('Content-Type: application/json; charset=utf-8');
                                        echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                                        return;
                                    }
                                    $this->redirect('/login');
                                    return;
                                }

                                $colsU = [];
                                try {
                                    $stmtColsU = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                                    $colsU = $stmtColsU ? $stmtColsU->fetchAll(\PDO::FETCH_COLUMN) : [];
                                } catch (\Exception $e) {
                                    $colsU = [];
                                }
                                $upd = [];
                                if (is_array($colsU) && in_array('termos_aceitos_em', $colsU, true)) {
                                    $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                                }
                                if (is_array($colsU) && in_array('termos_aceitos_ip', $colsU, true)) {
                                    $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                                }
                                if (is_array($colsU) && in_array('termos_versao', $colsU, true)) {
                                    $upd['termos_versao'] = '1.0';
                                }
                                if (!empty($upd)) {
                                    $this->usuarioModel->update((int) $usuarioCompleto['id'], $upd);
                                }
                            }

                            $faltando = $this->usuarioModel->getMissingRequiredFields($usuarioCompleto);
                            if (!empty($faltando)) {
                                $_SESSION['message'] = 'Complete seus dados obrigatórios para continuar: ' . implode(', ', $faltando);
                                $_SESSION['message_type'] = 'warning';
                                $target = '/meus-dados';
                            } else {
                                $target = $redirectTo !== '' ? $redirectTo : '/minha-conta';
                            }
                        } else {
                            $target = $redirectTo !== '' ? $redirectTo : '/minha-conta';
                        }

                        $_SESSION['message'] = 'Bem-vendo de volta, ' . $usuario['nome'] . '!';
                        $_SESSION['message_type'] = 'success';
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'redirect' => $target]);
                            return;
                        }
                        $this->redirect($target);
                    }
                    return;
                } else {
                    $_SESSION['message'] = 'E-mail ou senha incorretos';
                    $_SESSION['message_type'] = 'danger';
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                        return;
                    }
                }
            } catch (\Exception $e) {
                $_SESSION['message'] = 'Erro ao fazer login: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                    return;
                }
            }
            
            $this->redirect('/login');
            return;
        }
        
        $this->view('auth/login');
    }
    
    public function loginAdmin(Request $request) {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($usuario['perfil'] === 'admin') {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'redirect' => '/admin/dashboard']);
                    return;
                }

                $this->redirect('/admin/dashboard');
            } else {
                $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                $_SESSION['message_type'] = 'danger';

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                    return;
                }

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

                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                            return;
                        }

                        $this->redirect('/loginadmin');
                        return;
                    }
                    
                    $_SESSION['message'] = 'Bem-vindo, ' . $usuario['nome'] . '! Acesso administrativo.';
                    $_SESSION['message_type'] = 'success';

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'redirect' => '/admin/dashboard']);
                        return;
                    }

                    $this->redirect('/admin/dashboard');
                    return;
                } else {
                    $_SESSION['message'] = 'E-mail ou senha incorretos';
                    $_SESSION['message_type'] = 'danger';

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                        return;
                    }
                }
            } catch (\Exception $e) {
                $_SESSION['message'] = 'Erro ao fazer login: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                    return;
                }
            }
            
            $this->redirect('/loginadmin');
            return;
        }
        
        $this->view('auth/loginadmin');
    }

    public function logout(Request $request) {
        try {
            $this->authService->logout();

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['message'] = 'Logout realizado com sucesso.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['message'] = 'Erro ao fazer logout.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/login');
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
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
                            $colsU = [];
                            try {
                                $stmtColsU = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                                $colsU = $stmtColsU ? $stmtColsU->fetchAll(\PDO::FETCH_COLUMN) : [];
                            } catch (\Exception $e) {
                                $colsU = [];
                            }

                            $upd = [];
                            $map = [
                                'cep' => 'cep',
                                'endereco' => 'endereco',
                                'numero' => 'numero',
                                'complemento' => 'complemento',
                                'bairro' => 'bairro',
                                'cidade' => 'cidade',
                                'estado' => 'estado',
                                'data_nascimento' => 'data_nascimento',
                                'pais_residencia' => 'pais_residencia',
                            ];
                            foreach ($map as $k => $col) {
                                if (is_array($colsU) && in_array($col, $colsU, true) && isset($dados[$k])) {
                                    $upd[$col] = $dados[$k];
                                }
                            }

                            if (is_array($colsU) && in_array('termos_aceitos_em', $colsU, true)) {
                                $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                            }
                            if (is_array($colsU) && in_array('termos_aceitos_ip', $colsU, true)) {
                                $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                            }
                            if (is_array($colsU) && in_array('termos_versao', $colsU, true)) {
                                $upd['termos_versao'] = '1.0';
                            }

                            if (!empty($upd)) {
                                $this->usuarioModel->update((int) $usuarioId, $upd);
                            }
                        }
                        
                        if ($usuarioId) {
                            // Fazer login automático
                            $usuario = $this->authService->login($dados['email'], $dados['senha']);
                            if ($isAjax) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['success' => true, 'redirect' => '/meus-dados']);
                                return;
                            }
                            $this->redirect('/meus-dados');
                            return;
                        } else {
                            $erros[] = 'Erro ao criar conta';
                        }
                    }
                } catch (\Exception $e) {
                    $erros[] = 'Erro ao criar conta: ' . $e->getMessage();
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $erros[0] ?? 'Erro ao criar conta'
                ]);
                return;
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

        if (empty($dados['data_nascimento'])) {
            $erros[] = 'Data de nascimento é obrigatória';
        }

        $pais = strtoupper(trim((string) ($dados['pais_residencia'] ?? 'BR')));
        if ($pais === '') {
            $pais = 'BR';
        }
        if (empty($dados['pais_residencia'])) {
            $erros[] = 'País de residência é obrigatório';
        }

        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if (empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if (empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';
        if (empty($dados['estado'])) $erros[] = 'Estado é obrigatório';

        if ($pais === 'BR') {
            $doc = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
            if ($doc === '' || strlen($doc) < 11) {
                $erros[] = 'CPF é obrigatório para residentes no Brasil';
            }
        }

        if (empty($dados['termos'])) {
            $erros[] = 'Você precisa aceitar os termos';
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
