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
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'];
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
            'perfil' => $_SESSION['usuario_perfil']
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
            header('HTTP/1.0 403 Forbidden');
            echo 'Acesso negado';
            exit;
        }
    }
    
    public function requerPermissao($acao) {
        $this->requerAutenticacao();
        
        if (!$this->temPermissao($acao)) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Acesso negado';
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
        $stmt = $this->usuarioModel->connection->prepare("
            INSERT INTO auditoria_logs (usuario_id, acao, tabela, registro_id, valores_antigos, valores_novos, ip, user_agent) 
            VALUES (:usuario_id, :acao, :tabela, :registro_id, :valores_antigos, :valores_novos, :ip, :user_agent)
        ");
        
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':tabela', $tabela);
        $stmt->bindParam(':registro_id', $registroId);
        $stmt->bindParam(':valores_antigos', $valoresAntigos ? json_encode($valoresAntigos) : null);
        $stmt->bindParam(':valores_novos', $valoresNovos ? json_encode($valoresNovos) : null);
        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? null);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        
        $stmt->execute();
    }
}
