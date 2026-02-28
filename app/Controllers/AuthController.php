<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\CpfValidator;
use App\Services\WordPressDbService;
use App\Models\Usuario;

class AuthController extends Controller {
    private $authService;
    private $usuarioModel;
    private $wpDbService;
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->usuarioModel = new Usuario();
        $this->wpDbService = new WordPressDbService();
    }

    private function tryWpRecadastroFlow(string $email, bool $isAjax = false): bool {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $local = $this->usuarioModel->findByEmail($email);
            if ($local) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        $wpUser = null;
        try {
            $wpUser = $this->wpDbService->findUserByEmailPriority($email, ['br', 'us']);
        } catch (\Exception $e) {
            $wpUser = null;
        }

        if (!is_array($wpUser) || empty($wpUser['id'])) {
            return false;
        }

        $cols = [];
        try {
            $stmtCols = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $nome = trim((string) ($wpUser['display_name'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($wpUser['login'] ?? ''));
        }
        if ($nome === '') {
            $nome = 'Cliente';
        }

        $data = [
            'nome' => $nome,
            'email' => $email,
            'perfil' => 'cliente',
            'senha' => bin2hex(random_bytes(16)),
        ];

        if (is_array($cols) && in_array('precisa_recadastro', $cols, true)) {
            $data['precisa_recadastro'] = 1;
        }
        if (is_array($cols) && in_array('wp_origem', $cols, true)) {
            $data['wp_origem'] = (string) ($wpUser['source'] ?? 'br');
        }
        if (is_array($cols) && in_array('wp_user_id', $cols, true)) {
            $data['wp_user_id'] = (int) ($wpUser['id'] ?? 0);
        }

        $userId = 0;
        try {
            $userId = (int) $this->usuarioModel->create($data);
        } catch (\Exception $e) {
            $userId = 0;
        }

        if ($userId <= 0) {
            return false;
        }

        $token = '';
        try {
            $token = $this->usuarioModel->requestPasswordResetToken($email);
        } catch (\Exception $e) {
            $token = '';
        }
        if ($token === '') {
            return false;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $base = $host !== '' ? ($scheme . '://' . $host) : '';
        $link = ($base !== '' ? $base : '') . '/redefinir-senha/' . rawurlencode($token);

        $subject = 'Atualização cadastral - Braziliana';
        $html = 'Olá,<br><br>'
            . 'Como este é um site novo, precisamos que você atualize seu cadastro para continuar usando sua conta.<br><br>'
            . 'Clique no link abaixo para definir uma nova senha e acessar o sistema:<br><br>'
            . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a><br><br>'
            . 'Este link expira em 1 hora.';

        $fromEmail = 'noreply@brazilianashop.com.br';
        $fromName = 'Braziliana';
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        @mail($email, $subject, $html, implode("\r\n", $headers));

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = 'Encontramos seu cadastro no site antigo. Enviamos um e-mail para você definir uma nova senha e atualizar seus dados.';
        $_SESSION['message_type'] = 'warning';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $_SESSION['message'], 'action' => 'password_reset_sent']);
            return true;
        }

        $this->redirect('/login');
        return true;
    }

    private function normalizeInternalRedirect(string $candidate): string {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }
        if ($candidate[0] !== '/') {
            return '';
        }
        if (strpos($candidate, '//') === 0) {
            return '';
        }
        if (stripos($candidate, 'http://') === 0 || stripos($candidate, 'https://') === 0) {
            return '';
        }
        return $candidate;
    }
    
    public function login(Request $request) {
        $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
        if ($redirectTo === '') {
            try {
                $redirectTo = (string) ($_GET['redirect'] ?? '');
            } catch (\Exception $e) {
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($redirectTo === '' && isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
            $redirectTo = (string) $_SESSION['redirect_after_login'];
        }
        $redirectTo = $this->normalizeInternalRedirect($redirectTo);

        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
            if ($perfil === 'representante') {
                $this->redirect('/meu-painel');
            }
            if ($this->authService->podeAcessarPainelAdmin()) {
                $this->redirect('/admin/dashboard');
            }
            if (isset($_SESSION['redirect_after_login'])) {
                unset($_SESSION['redirect_after_login']);
            }
            $this->redirect($redirectTo !== '' ? $redirectTo : '/minha-conta');
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
            if ($redirectTo === '' && isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
                $redirectTo = (string) $_SESSION['redirect_after_login'];
            }
            $redirectTo = $this->normalizeInternalRedirect((string) $redirectTo);
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            try {
                $usuario = $this->authService->autenticar($email, $senha);
                
                if ($usuario) {
                    // Verificar se é login admin
                    if ($isAdmin && !$this->authService->podeAcessarPainelAdmin()) {
                        $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                        $_SESSION['message_type'] = 'danger';
                        $this->redirect('/login');
                        return;
                    }
                    
                    // Redirecionar baseado no perfil
                    if ($isAdmin || $this->authService->podeAcessarPainelAdmin()) {
                        $_SESSION['message'] = 'Bem-vindo, ' . $usuario['nome'] . '!';
                        $_SESSION['message_type'] = 'success';
                        $adminTarget = '/admin/dashboard';
                        if (isset($_SESSION['redirect_after_login'])) {
                            unset($_SESSION['redirect_after_login']);
                        }
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'redirect' => $adminTarget]);
                            return;
                        }
                        $this->redirect($adminTarget);
                    } else {
                        $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
                        if ($perfil === 'representante') {
                            if ($isAjax) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['success' => true, 'redirect' => '/meu-painel']);
                                return;
                            }
                            $this->redirect('/meu-painel');
                        }
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
                        if (isset($_SESSION['redirect_after_login'])) {
                            unset($_SESSION['redirect_after_login']);
                        }
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'redirect' => $target]);
                            return;
                        }
                        $this->redirect($target);
                    }
                    return;
                } else {
                    if ($this->tryWpRecadastroFlow((string) $email, $isAjax)) {
                        return;
                    }
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

        $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
        if ($redirectTo === '') {
            try {
                $redirectTo = (string) ($_GET['redirect'] ?? '');
            } catch (\Exception $e) {
            }
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($redirectTo === '' && isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
            $redirectTo = (string) $_SESSION['redirect_after_login'];
        }
        $redirectTo = $this->normalizeInternalRedirect($redirectTo);

        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($this->authService->podeAcessarPainelAdmin()) {
                if (isset($_SESSION['redirect_after_login'])) {
                    unset($_SESSION['redirect_after_login']);
                }
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
                    if (!$this->authService->podeAcessarPainelAdmin()) {
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

                    $adminTarget = $redirectTo !== '' ? $redirectTo : '/admin/dashboard';
                    if (isset($_SESSION['redirect_after_login'])) {
                        unset($_SESSION['redirect_after_login']);
                    }

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'redirect' => $adminTarget]);
                        return;
                    }

                    $this->redirect($adminTarget);
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

    public function recuperarSenha(Request $request) {
        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if (($usuario['perfil'] ?? '') === 'admin') {
                $this->redirect('/admin/dashboard');
            }
            $this->redirect('/minha-conta');
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($request->getMethod() === 'POST') {
            $email = trim((string) ($request->getParam('email') ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['message'] = 'Informe um e-mail válido.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/recuperar-senha');
                return;
            }

            $token = '';
            try {
                $token = $this->usuarioModel->requestPasswordResetToken($email);
            } catch (\Exception $e) {
                $token = '';
            }

            if ($token !== '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
                $base = $host !== '' ? ($scheme . '://' . $host) : '';
                $link = ($base !== '' ? $base : '') . '/redefinir-senha/' . rawurlencode($token);

                $subject = 'Recuperação de senha - Braziliana';
                $html = 'Olá,<br><br>'
                    . 'Recebemos uma solicitação para redefinir sua senha. Clique no link abaixo para criar uma nova senha:<br><br>'
                    . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a><br><br>'
                    . 'Se você não solicitou isso, ignore este e-mail.<br><br>'
                    . 'Este link expira em 1 hora.';

                $fromEmail = 'noreply@brazilianashop.com.br';
                $fromName = 'Braziliana';
                $headers = [];
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
                $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
                @mail($email, $subject, $html, implode("\r\n", $headers));
            }

            // Por segurança, mesma mensagem para email existente ou não
            $_SESSION['message'] = 'Se o e-mail estiver cadastrado, você receberá instruções para recuperar sua senha.';
            $_SESSION['message_type'] = 'success';
            $this->redirect('/login');
            return;
        }

        $this->view('auth/recuperar-senha');
    }

    public function redefinirSenha(Request $request) {
        $token = (string) ($request->getParam('token') ?? '');
        $token = trim($token);
        if ($token === '') {
            $_SESSION['message'] = 'Link inválido.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/recuperar-senha');
            return;
        }

        if ($request->getMethod() === 'POST') {
            $senha = (string) ($request->getParam('senha') ?? '');
            $confirm = (string) ($request->getParam('senha_confirmacao') ?? '');
            if (trim($senha) === '' || strlen($senha) < 6) {
                $_SESSION['message'] = 'A nova senha deve ter pelo menos 6 caracteres.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/redefinir-senha/' . rawurlencode($token));
                return;
            }
            if ($senha !== $confirm) {
                $_SESSION['message'] = 'As senhas não conferem.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/redefinir-senha/' . rawurlencode($token));
                return;
            }

            $ok = false;
            try {
                $ok = $this->usuarioModel->resetPasswordByToken($token, $senha);
            } catch (\Exception $e) {
                $ok = false;
            }

            if ($ok) {
                $_SESSION['message'] = 'Senha redefinida com sucesso. Você já pode fazer login.';
                $_SESSION['message_type'] = 'success';
                $this->redirect('/login');
                return;
            }

            $_SESSION['message'] = 'Link inválido ou expirado. Solicite a recuperação novamente.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/recuperar-senha');
            return;
        }

        $this->view('auth/redefinir-senha', ['token' => $token]);
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
                    
                    // Verificar se CPF já existe (somente se informado)
                    $docCheck = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
                    if ($docCheck !== '' && $this->usuarioModel->findByDocumento($docCheck)) {
                        $erros[] = 'CPF já cadastrado';
                    }
                    
                    if (empty($erros)) {
                        $docToSave = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
                        $usuarioId = $this->usuarioModel->create([
                            'nome' => $dados['nome'],
                            'email' => $dados['email'],
                            'senha' => $dados['senha'],
                            'telefone' => $dados['telefone'],
                            'documento' => $docToSave,
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
                    $msg = (string) $e->getMessage();
                    if (stripos($msg, 'Duplicate entry') !== false && stripos($msg, 'documento') !== false) {
                        $erros[] = 'CPF já cadastrado';
                    } elseif (stripos($msg, 'Duplicate entry') !== false && stripos($msg, 'email') !== false) {
                        $erros[] = 'E-mail já cadastrado';
                    } else {
                        $erros[] = 'Erro ao criar conta: ' . $msg;
                    }
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
        } else {
            $nome = trim((string) $dados['nome']);
            $parts = preg_split('/\s+/', $nome) ?: [];
            $parts = array_values(array_filter($parts, static fn($p) => is_string($p) && mb_strlen(trim($p)) >= 2));
            if (count($parts) < 2) {
                $erros[] = 'Informe nome e sobrenome';
            }
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
        
        $pais = strtoupper(trim((string) ($dados['pais_residencia'] ?? 'BR')));
        if ($pais === '') {
            $pais = 'BR';
        }

        // CPF: obrigatório somente para residentes no Brasil
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
        
        if (empty($dados['telefone'])) {
            $erros[] = 'Telefone é obrigatório';
        }

        if (empty($dados['data_nascimento'])) {
            $erros[] = 'Data de nascimento é obrigatória';
        } else {
            $rawBirth = trim((string) ($dados['data_nascimento'] ?? ''));
            $birth = \DateTime::createFromFormat('Y-m-d', $rawBirth);
            if (!$birth) {
                $birth = \DateTime::createFromFormat('d/m/Y', $rawBirth);
            }
            if (!$birth) {
                $erros[] = 'Data de nascimento inválida';
            } else {
                $birth->setTime(0, 0, 0);
                $today = new \DateTime('today');
                if ($birth > $today) {
                    $erros[] = 'Data de nascimento não pode ser no futuro';
                }
            }
        }

        if (empty($dados['pais_residencia'])) {
            $erros[] = 'País de residência é obrigatório';
        }

        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if ($pais === 'BR' && empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if ($pais === 'BR' && empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';
        if (empty($dados['estado'])) $erros[] = 'Estado é obrigatório';

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
