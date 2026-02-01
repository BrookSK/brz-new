<?php
namespace App\Services;

use App\Models\Usuario;

class AuthService {
    private $usuarioModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
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
            header('Location: /login');
            exit;
        }
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
