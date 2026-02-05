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
        session_start();
        session_destroy();
        
        // Limpar cookie de remember
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    
    public function criarSessao($usuario) {
        session_start();
        
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'] ?? ($usuario['name'] ?? '');
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'];
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
            'perfil' => $_SESSION['usuario_perfil'],
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
            header('Location: /login');
            exit;
        }
    }
    
    public function requerPerfil($perfil) {
        $this->requerAutenticacao();
        
        $usuario = $this->getUsuarioLogado();
        
        if ($usuario['perfil'] !== $perfil) {
            $_SESSION['message'] = 'Acesso negado. Permissão de ' . $perfil . ' necessária.';
            $_SESSION['message_type'] = 'danger';
            $target = $this->estaLogado() ? '/admin' : '/login';
            header('Location: ' . $target);
            exit;
        }
    }

    public function requerPerfis(array $perfis) {
        $this->requerAutenticacao();

        $usuario = $this->getUsuarioLogado();
        $perfilAtual = (string) ($usuario['perfil'] ?? '');
        $perfisNorm = array_values(array_filter(array_map(function ($p) {
            return strtolower(trim((string) $p));
        }, $perfis), function ($p) {
            return $p !== '';
        }));

        if (empty($perfisNorm)) {
            return;
        }

        if (!in_array(strtolower($perfilAtual), $perfisNorm, true)) {
            $_SESSION['message'] = 'Acesso negado. Permissão insuficiente.';
            $_SESSION['message_type'] = 'danger';
            $target = $this->estaLogado() ? '/admin' : '/login';
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
        if ($perfil === '') {
            return false;
        }
        return in_array($perfil, ['admin', 'vendedor', 'suporte', 'redirecionador'], true);
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
