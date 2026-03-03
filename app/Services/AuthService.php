<?php
namespace App\Services;

use App\Models\Usuario;
use App\Models\Carrinho;

class AuthService {
    private $usuarioModel;
    private $carrinhoModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
        $this->carrinhoModel = new Carrinho();
    }

    private function mergeSessionCartToUser(int $usuarioId): void {
        if ($usuarioId <= 0) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessCart = $_SESSION['carrinho'] ?? [];
        if (!is_array($sessCart) || empty($sessCart)) {
            return;
        }

        try {
            $cart = $this->carrinhoModel->getOrCreateCarrinho($usuarioId, null, 'BRL');
            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
            if ($cartId <= 0) {
                return;
            }

            foreach ($sessCart as $k => $it) {
                if (!is_array($it)) continue;
                $pid = (int) ($it['produto_id'] ?? 0);
                if ($pid <= 0) continue;
                $qtd = (int) ($it['quantidade'] ?? 1);
                if ($qtd < 1) $qtd = 1;

                $pvId = null;
                if (isset($it['produto_variacao_id']) && $it['produto_variacao_id'] !== '' && $it['produto_variacao_id'] !== null) {
                    $tmp = (int) $it['produto_variacao_id'];
                    if ($tmp > 0) $pvId = $tmp;
                } else {
                    // tentativa de extrair da key "produtoId:variacaoId"
                    if (is_string($k) && strpos($k, ':') !== false) {
                        $parts = explode(':', $k);
                        if (count($parts) >= 2) {
                            $tmp = (int) ($parts[1] ?? 0);
                            if ($tmp > 0) $pvId = $tmp;
                        }
                    }
                }

                $varDesc = isset($it['variacao_descricao']) ? (string) $it['variacao_descricao'] : null;
                $this->carrinhoModel->adicionarItem($cartId, $pid, $qtd, $pvId, $varDesc);
            }

            unset($_SESSION['carrinho']);

            if (isset($_COOKIE['guest_cart'])) {
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                if (PHP_VERSION_ID >= 70300) {
                    setcookie('guest_cart', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'secure' => $secure,
                        'httponly' => false,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie('guest_cart', '', time() - 3600, '/; samesite=Lax', '', $secure, false);
                }
            }
        } catch (\Exception $e) {
            // se falhar, mantém sessão como fallback
        }
    }
    
    public function login($email, $senha) {
        $usuario = $this->usuarioModel->authenticate($email, $senha);
        
        if ($usuario) {
            $this->criarSessao($usuario);
            return $usuario;
        }
        
        return false;
    }
    
    public function autenticar($email, $senha) {
        $usuario = $this->usuarioModel->authenticate($email, $senha);
        
        if ($usuario) {
            $this->criarSessao($usuario);
            return $usuario;
        }
        
        return false;
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Ao deslogar de um usuário, o carrinho não deve “vazar” para o modo visitante.
        // Mantemos o carrinho no banco (para restaurar quando logar novamente) e limpamos sessão/cookie do visitante.
        try {
            $uidCart = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($uidCart > 0) {
                // Se por algum motivo houver itens no carrinho da sessão, persiste no carrinho do usuário (DB)
                // antes de limpar a sessão.
                if (!empty($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
                    $this->mergeSessionCartToUser($uidCart);
                }

                unset($_SESSION['carrinho']);
                if (isset($_COOKIE['guest_cart'])) {
                    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie('guest_cart', '', [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'secure' => $secure,
                            'httponly' => false,
                            'samesite' => 'Lax',
                        ]);
                    } else {
                        setcookie('guest_cart', '', time() - 3600, '/; samesite=Lax', '', $secure, false);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        try {
            if ($uid > 0) {
                $this->usuarioModel->clearRememberTokens($uid);
            }
        } catch (\Exception $e) {
        }

        session_destroy();

        if (isset($_COOKIE['remember_token'])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if (PHP_VERSION_ID >= 70300) {
                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('remember_token', '', time() - 3600, '/; samesite=Lax', '', $secure, true);
            }
        }
    }
    
    public function criarSessao($usuario) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'] ?? ($usuario['name'] ?? '');
        $_SESSION['usuario_email'] = $usuario['email'];
        $docSess = '';
        if (isset($usuario['documento'])) {
            $docSess = preg_replace('/\D+/', '', (string) $usuario['documento']);
        } elseif (isset($usuario['cpf'])) {
            $docSess = preg_replace('/\D+/', '', (string) $usuario['cpf']);
        }
        $_SESSION['usuario_documento'] = $docSess;
        $perfil = (string) ($usuario['perfil'] ?? '');
        $role = (string) ($usuario['role'] ?? '');
        $_SESSION['usuario_perfil'] = $perfil !== '' ? $perfil : $role;
        $_SESSION['usuario_role'] = $role !== '' ? $role : $perfil;
        $avatarCandidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto', 'avatar_url', 'avatarUrl', 'profile_image', 'profileImage', 'foto_url'];
        $avatarUrl = null;
        foreach ($avatarCandidates as $c) {
            if (!empty($usuario[$c]) && is_string($usuario[$c])) {
                $avatarUrl = $usuario[$c];
                break;
            }
        }
        $_SESSION['usuario_avatar'] = $avatarUrl;
        $_SESSION['logado'] = true;
        $_SESSION['ultimo_acesso'] = time();

        try {
            $uid = (int) ($usuario['id'] ?? 0);
            if ($uid > 0) {
                $token = bin2hex(random_bytes(32));
                $ttl = 60 * 60 * 24 * 7;
                $ok = $this->usuarioModel->setRememberToken($uid, $token, $ttl);
                if ($ok) {
                    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie('remember_token', $token, [
                            'expires' => time() + $ttl,
                            'path' => '/',
                            'secure' => $secure,
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    } else {
                        setcookie('remember_token', $token, time() + $ttl, '/; samesite=Lax', '', $secure, true);
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        
        // Gerar CSRF token
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->mergeSessionCartToUser((int) ($usuario['id'] ?? 0));
    }
    
    public function estaLogado() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['logado']) && $_SESSION['logado'] === true;
    }
    
    public function getUsuarioLogado() {
        if (!$this->estaLogado()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'],
            'email' => $_SESSION['usuario_email'],
            'documento' => $_SESSION['usuario_documento'] ?? '',
            'perfil' => $_SESSION['usuario_perfil'],
            'role' => $_SESSION['usuario_role'] ?? $_SESSION['usuario_perfil'],
            'avatar' => $_SESSION['usuario_avatar'] ?? null
        ];
    }
    
    public function temPermissao($acao) {
        $usuario = $this->getUsuarioLogado();
        
        if (!$usuario) {
            return false;
        }
        
        return $this->usuarioModel->hasPermission($usuario['id'], $acao);
    }
    
    public function requerAutenticacao() {
        if (!$this->estaLogado()) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $isAdminUri = (stripos($uri, '/admin') === 0);
            $isLoginUri = (stripos($uri, '/login') === 0 || stripos($uri, '/loginadmin') === 0);

            if ($uri !== '' && $uri[0] === '/' && !$isLoginUri) {
                $_SESSION['redirect_after_login'] = $uri;
            }

            $redirectParam = '';
            if (isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
                $redirectParam = rawurlencode($_SESSION['redirect_after_login']);
            }

            if ($isAdminUri) {
                header('Location: /loginadmin' . ($redirectParam !== '' ? ('?redirect=' . $redirectParam) : ''));
            } else {
                header('Location: /login' . ($redirectParam !== '' ? ('?redirect=' . $redirectParam) : ''));
            }
            exit;
        }
    }
    
    public function requerPerfil($perfil) {
        $this->requerAutenticacao();
        
        $usuario = $this->getUsuarioLogado();

        $perfilAtual = strtolower(trim((string) ($usuario['perfil'] ?? '')));
        $roleAtual = strtolower(trim((string) ($usuario['role'] ?? '')));
        $perfilNeed = strtolower(trim((string) $perfil));
        if ($perfilNeed === '' || ($perfilAtual !== $perfilNeed && $roleAtual !== $perfilNeed)) {
            $_SESSION['message'] = 'Acesso negado. Permissão de ' . $perfil . ' necessária.';
            $_SESSION['message_type'] = 'danger';
            // Evitar loop (/admin/dashboard -> /admin -> /admin/dashboard ...)
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $isAdminUri = (stripos($uri, '/admin') === 0);
            $target = $isAdminUri ? '/' : '/login';
            header('Location: ' . $target);
            exit;
        }
    }

    public function requerPerfis(array $perfis) {
        $this->requerAutenticacao();

        $usuario = $this->getUsuarioLogado();
        $perfilAtual = strtolower(trim((string) ($usuario['perfil'] ?? '')));
        $roleAtual = strtolower(trim((string) ($usuario['role'] ?? '')));
        $perfisNorm = array_values(array_filter(array_map(function ($p) {
            return strtolower(trim((string) $p));
        }, $perfis), function ($p) {
            return $p !== '';
        }));

        if (empty($perfisNorm)) {
            return;
        }

        if (!in_array($perfilAtual, $perfisNorm, true) && !in_array($roleAtual, $perfisNorm, true)) {
            $_SESSION['message'] = 'Acesso negado. Permissão insuficiente.';
            $_SESSION['message_type'] = 'danger';
            // Evitar loop (/admin/dashboard -> /admin -> /admin/dashboard ...)
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $isAdminUri = (stripos($uri, '/admin') === 0);
            $target = $isAdminUri ? '/' : '/login';
            header('Location: ' . $target);
            exit;
        }
    }

    public function podeAcessarPainelAdmin(): bool {
        $usuario = $this->getUsuarioLogado();
        if (!$usuario) {
            return false;
        }
        $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
        $role = strtolower(trim((string) ($usuario['role'] ?? '')));
        if ($perfil === '' && $role === '') {
            return false;
        }
        return in_array($perfil, ['admin', 'vendedor', 'suporte', 'redirecionador', 'conferente'], true)
            || in_array($role, ['admin', 'vendedor', 'suporte', 'redirecionador', 'conferente'], true);
    }
    
    public function requerPermissao($acao) {
        $this->requerAutenticacao();
        
        if (!$this->temPermissao($acao)) {
            $_SESSION['message'] = 'Acesso negado. Permissão insuficiente.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /login');
            exit;
        }
    }
    
    public function validarCSRF($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function getCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public function registrarLogAuditoria($usuarioId, $acao, $tabela = null, $registroId = null, $valoresAntigos = null, $valoresNovos = null) {
        try {
            $connection = $this->usuarioModel->getConnection();
            $stmt = $connection->prepare("
                INSERT INTO auditoria_logs (usuario_id, acao, tabela, registro_id, valores_antigos, valores_novos, ip, user_agent) 
                VALUES (:usuario_id, :acao, :tabela, :registro_id, :valores_antigos, :valores_novos, :ip, :user_agent)
            ");
            
            // Usar bindValue em vez de bindParam para evitar problemas com nulos
            $stmt->bindValue(':usuario_id', $usuarioId);
            $stmt->bindValue(':acao', $acao);
            $stmt->bindValue(':tabela', $tabela);
            $stmt->bindValue(':registro_id', $registroId);
            $stmt->bindValue(':valores_antigos', $valoresAntigos ? json_encode($valoresAntigos) : null);
            $stmt->bindValue(':valores_novos', $valoresNovos ? json_encode($valoresNovos) : null);
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':user_agent', $userAgent);
            
            $stmt->execute();
            
        } catch (\Exception $e) {
            // Se não conseguir registrar log, apenas registrar no error_log
            error_log('Erro ao registrar log de auditoria: ' . $e->getMessage());
            error_log("Dados do log: usuario_id={$usuarioId}, acao={$acao}, tabela={$tabela}");
        }
    }
}
