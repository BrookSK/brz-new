<?php
/**
 * @file AuthController.php
 * @responsibilidade Controller para rotas de autenticação
 * @descrição Gerencia requisições HTTP relacionadas a usuários e autenticação
 * @conexão Define rotas /register, /login, /logout, /profile, etc.
 */

namespace App\Presentation\Controllers;

use App\Application\UseCases\Auth\RegisterUser;
use App\Application\UseCases\Auth\LoginUser;
use App\Application\UseCases\Auth\LogoutUser;
use App\Application\DTOs\UserDTO;
use App\Core\Services\AuthService;
use App\Presentation\Middleware\AuthMiddleware;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Exceptions\AuthenticationException;

class AuthController extends Controller {
    private AuthService $authService;
    private RegisterUser $registerUser;
    private LoginUser $loginUser;
    private LogoutUser $logoutUser;

    public function __construct(
        AuthService $authService,
        RegisterUser $registerUser,
        LoginUser $loginUser,
        LogoutUser $logoutUser
    ) {
        $this->authService = $authService;
        $this->registerUser = $registerUser;
        $this->loginUser = $loginUser;
        $this->logoutUser = $logoutUser;
    }

    /**
     * Exibe página de registro
     */
    public function showRegister() {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth.register', [
            'title' => 'Criar Conta - Braziliana Shop',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Processa registro de novo usuário
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/register');
            return;
        }

        try {
            // Validar CSRF token
            if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
                throw new ValidationException('Token de segurança inválido');
            }

            // Criar DTO a partir da requisição
            $userDTO = UserDTO::fromRequest($_POST);

            // Executar registro
            $result = $this->registerUser->execute($userDTO);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $this->redirect('/login');
            } else {
                $_SESSION['errors'] = $result['errors'] ?? [$result['message']];
                $_SESSION['old'] = $_POST;
                $this->redirect('/register');
            }

        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->getErrors() ?: [$e->getMessage()];
            $_SESSION['old'] = $_POST;
            $this->redirect('/register');
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao registrar usuário. Tente novamente.'];
            $_SESSION['old'] = $_POST;
            $this->redirect('/register');
        }
    }

    /**
     * Exibe página de login
     */
    public function showLogin() {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth.login', [
            'title' => 'Entrar - Braziliana Shop',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Processa login do usuário
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        try {
            // Validar CSRF token
            if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
                throw new ValidationException('Token de segurança inválido');
            }

            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);

            // Executar login
            $result = $this->loginUser->execute($email, $password, $remember);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                
                // Redirecionar para a página pretendida ou dashboard
                $redirect = $_SESSION['intended'] ?? '/dashboard';
                unset($_SESSION['intended']);
                $this->redirect($redirect);
            } else {
                $_SESSION['errors'] = [$result['message']];
                $_SESSION['old'] = $_POST;
                $this->redirect('/login');
            }

        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->getErrors() ?: [$e->getMessage()];
            $_SESSION['old'] = $_POST;
            $this->redirect('/login');
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao fazer login. Tente novamente.'];
            $_SESSION['old'] = $_POST;
            $this->redirect('/login');
        }
    }

    /**
     * Processa logout do usuário
     */
    public function logout() {
        try {
            $this->logoutUser->execute();
            $_SESSION['success'] = 'Logout realizado com sucesso';
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao fazer logout'];
        }

        $this->redirect('/login');
    }

    /**
     * Exibe perfil do usuário
     */
    public function showProfile() {
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'];
            $this->redirect('/login');
            return;
        }

        $this->view('auth.profile', [
            'title' => 'Meu Perfil - Braziliana Shop',
            'user' => $user->toSafeArray(),
            'success' => $_SESSION['success'] ?? '',
            'errors' => $_SESSION['errors'] ?? []
        ]);

        unset($_SESSION['success'], $_SESSION['errors']);
    }

    /**
     * Atualiza perfil do usuário
     */
    public function updateProfile() {
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/profile');
            return;
        }

        try {
            // Validar CSRF token
            if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
                throw new ValidationException('Token de segurança inválido');
            }

            // Atualizar perfil
            $updatedUser = $this->authService->updateProfile($user->getId(), $_POST);
            
            $_SESSION['success'] = 'Perfil atualizado com sucesso';
            $this->redirect('/profile');

        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->getErrors() ?: [$e->getMessage()];
            $_SESSION['old'] = $_POST;
            $this->redirect('/profile');
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao atualizar perfil. Tente novamente.'];
            $_SESSION['old'] = $_POST;
            $this->redirect('/profile');
        }
    }

    /**
     * Exibe página de alteração de senha
     */
    public function showChangePassword() {
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $this->view('auth.change-password', [
            'title' => 'Alterar Senha - Braziliana Shop',
            'errors' => $_SESSION['errors'] ?? []
        ]);

        unset($_SESSION['errors']);
    }

    /**
     * Processa alteração de senha
     */
    public function changePassword() {
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/change-password');
            return;
        }

        try {
            // Validar CSRF token
            if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
                throw new ValidationException('Token de segurança inválido');
            }

            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validações
            if (empty($currentPassword)) {
                throw new ValidationException('Senha atual é obrigatória');
            }

            if (empty($newPassword)) {
                throw new ValidationException('Nova senha é obrigatória');
            }

            if ($newPassword !== $confirmPassword) {
                throw new ValidationException('Confirmação de senha não coincide');
            }

            if (strlen($newPassword) < 8) {
                throw new ValidationException('Nova senha deve ter pelo menos 8 caracteres');
            }

            // Alterar senha
            $this->authService->changePassword($user->getId(), $currentPassword, $newPassword);
            
            $_SESSION['success'] = 'Senha alterada com sucesso';
            $this->redirect('/profile');

        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->getErrors() ?: [$e->getMessage()];
            $_SESSION['old'] = $_POST;
            $this->redirect('/change-password');
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao alterar senha. Tente novamente.'];
            $_SESSION['old'] = $_POST;
            $this->redirect('/change-password');
        }
    }

    /**
     * Exibe página de recuperação de senha
     */
    public function showForgotPassword() {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth.forgot-password', [
            'title' => 'Recuperar Senha - Braziliana Shop',
            'errors' => $_SESSION['errors'] ?? [],
            'success' => $_SESSION['success'] ?? ''
        ]);

        unset($_SESSION['errors'], $_SESSION['success']);
    }

    /**
     * Processa solicitação de recuperação de senha
     */
    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/forgot-password');
            return;
        }

        try {
            // Validar CSRF token
            if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
                throw new ValidationException('Token de segurança inválido');
            }

            $email = $_POST['email'] ?? '';

            if (empty($email)) {
                throw new ValidationException('Email é obrigatório');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Email inválido');
            }

            // Solicitar recuperação
            $token = $this->authService->requestPasswordReset($email);
            
            $_SESSION['success'] = 'Se o email estiver cadastrado, você receberá instruções para recuperar sua senha';
            $this->redirect('/login');

        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->getErrors() ?: [$e->getMessage()];
            $_SESSION['old'] = $_POST;
            $this->redirect('/forgot-password');
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao processar solicitação. Tente novamente.'];
            $_SESSION['old'] = $_POST;
            $this->redirect('/forgot-password');
        }
    }

    /**
     * Exibe página de redefinição de senha
     */
    public function showResetPassword($token) {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth.reset-password', [
            'title' => 'Redefinir Senha - Braziliana Shop',
            'token' => $token,
            'errors' => $_SESSION['errors'] ?? []
        ]);

        unset($_SESSION['errors']);
    }

    /**
     * Processa redefinição de senha
     */
    public function resetPassword($token) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        try {
            // Validar CSRF token
            if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
                throw new ValidationException('Token de segurança inválido');
            }

            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validações
            if (empty($newPassword)) {
                throw new ValidationException('Nova senha é obrigatória');
            }

            if ($newPassword !== $confirmPassword) {
                throw new ValidationException('Confirmação de senha não coincide');
            }

            if (strlen($newPassword) < 8) {
                throw new ValidationException('Nova senha deve ter pelo menos 8 caracteres');
            }

            // Redefinir senha
            $this->authService->resetPassword($token, $newPassword);
            
            $_SESSION['success'] = 'Senha redefinida com sucesso';
            $this->redirect('/login');

        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->getErrors() ?: [$e->getMessage()];
            $_SESSION['old'] = $_POST;
            $this->redirect("/reset-password/{$token}");
        } catch (\Exception $e) {
            $_SESSION['errors'] = ['Erro ao redefinir senha. Tente novamente.'];
            $_SESSION['old'] = $_POST;
            $this->redirect("/reset-password/{$token}");
        }
    }

    /**
     * Gera e retorna CSRF token
     */
    private function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida CSRF token
     */
    private function validateCSRF(string $token): bool {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    /**
     * Retorna CSRF token para uso em views
     */
    public function getCSRFToken(): string {
        return $this->generateCSRFToken();
    }
}
