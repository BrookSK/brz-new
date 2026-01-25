<?php
/**
 * @file AuthService.php
 * @responsibilidade Serviço de Autenticação e Gestão de Usuários
 * @descrição Centraliza toda a lógica de negócio relacionada a usuários e autenticação
 * @conexão Usado por Controllers, UseCases e Middleware
 */

namespace App\Core\Services;

use App\Core\Repositories\UserRepositoryInterface;
use App\Core\Domain\User;
use App\Core\ValueObjects\Email;
use App\Core\ValueObjects\Phone;
use App\Shared\Constants\UserRoles;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Exceptions\AuthenticationException;

class AuthService {
    private UserRepositoryInterface $userRepository;
    private string $sessionKey = 'user_id';
    private int $maxLoginAttempts = 5;
    private int $lockoutTime = 900; // 15 minutos

    public function __construct(UserRepositoryInterface $userRepository) {
        $this->userRepository = $userRepository;
    }

    /**
     * Registra um novo usuário
     */
    public function register(array $userData): User {
        $this->validateRegistrationData($userData);

        // Verificar se email já existe
        $email = new Email($userData['email']);
        if ($this->userRepository->emailExists($email)) {
            throw new ValidationException('Email já está em uso');
        }

        // Verificar CPF se fornecido
        if (!empty($userData['cpf'])) {
            if ($this->userRepository->cpfExists($userData['cpf'])) {
                throw new ValidationException('CPF já está em uso');
            }
        }

        // Criar usuário
        $user = new User(
            $userData['name'],
            $email,
            $userData['password'],
            $userData['role'] ?? UserRoles::CLIENT
        );

        // Dados opcionais
        if (!empty($userData['phone'])) {
            $user->setPhone(new Phone($userData['phone']));
        }

        if (!empty($userData['cpf'])) {
            $user->setCpf($userData['cpf']);
        }

        if (!empty($userData['birth_date'])) {
            $user->setBirthDate($userData['birth_date']);
        }

        // Endereço
        $addressFields = ['address', 'number', 'neighborhood', 'city', 'state', 'zip_code'];
        foreach ($addressFields as $field) {
            if (!empty($userData[$field])) {
                $method = 'set' . ucfirst($field);
                $user->$method($userData[$field]);
            }
        }

        return $this->userRepository->save($user);
    }

    /**
     * Realiza login do usuário
     */
    public function login(string $email, string $password, bool $remember = false): User {
        $this->checkRateLimit($email);

        $user = $this->userRepository->findByEmail(new Email($email));
        
        if (!$user) {
            $this->incrementLoginAttempts($email);
            throw new AuthenticationException('Credenciais inválidas');
        }

        if (!$user->isActive()) {
            throw new AuthenticationException('Usuário inativo');
        }

        if (!$user->verifyPassword($password)) {
            $this->incrementLoginAttempts($email);
            throw new AuthenticationException('Credenciais inválidas');
        }

        // Login bem-sucedido
        $this->clearLoginAttempts($email);
        $this->createSession($user, $remember);
        $this->userRepository->updateLastLogin($user->getId());

        return $user;
    }

    /**
     * Realiza logout do usuário
     */
    public function logout(): void {
        $this->destroySession();
    }

    /**
     * Obtém usuário autenticado atual
     */
    public function getCurrentUser(): ?User {
        $userId = $this->getUserIdFromSession();
        
        if (!$userId) {
            return null;
        }

        return $this->userRepository->findById($userId);
    }

    /**
     * Verifica se usuário está autenticado
     */
    public function isAuthenticated(): bool {
        return $this->getCurrentUser() !== null;
    }

    /**
     * Verifica se usuário tem determinada permissão
     */
    public function hasPermission(string $permission): bool {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }

        return UserRoles::hasPermission($user->getRole(), $permission);
    }

    /**
     * Verifica se usuário tem determinado role
     */
    public function hasRole(string $role): bool {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }

        return $user->getRole() === $role;
    }

    /**
     * Verifica se usuário pode acessar determinado nível
     */
    public function canAccess(int $requiredLevel): bool {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }

        return UserRoles::canAccess($user->getRole(), $requiredLevel);
    }

    /**
     * Atualiza perfil do usuário
     */
    public function updateProfile(int $userId, array $userData): User {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new NotFoundException('Usuário não encontrado');
        }

        $this->validateUpdateData($userData);

        // Atualizar campos permitidos
        if (!empty($userData['name'])) {
            $user->setName($userData['name']);
        }

        if (!empty($userData['phone'])) {
            $user->setPhone(new Phone($userData['phone']));
        }

        if (!empty($userData['cpf'])) {
            // Verificar se CPF já existe de outro usuário
            if ($this->userRepository->cpfExists($userData['cpf'], $userId)) {
                throw new ValidationException('CPF já está em uso');
            }
            $user->setCpf($userData['cpf']);
        }

        if (!empty($userData['birth_date'])) {
            $user->setBirthDate($userData['birth_date']);
        }

        // Endereço
        $addressFields = ['address', 'number', 'neighborhood', 'city', 'state', 'zip_code'];
        foreach ($addressFields as $field) {
            if (isset($userData[$field])) {
                $method = 'set' . ucfirst($field);
                $user->$method($userData[$field]);
            }
        }

        return $this->userRepository->save($user);
    }

    /**
     * Altera senha do usuário
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new NotFoundException('Usuário não encontrado');
        }

        if (!$user->verifyPassword($currentPassword)) {
            throw new AuthenticationException('Senha atual incorreta');
        }

        $user->setPassword($newPassword);
        $this->userRepository->save($user);

        return true;
    }

    /**
     * Solicita recuperação de senha
     */
    public function requestPasswordReset(string $email): string {
        $user = $this->userRepository->findByEmail(new Email($email));
        
        if (!$user) {
            // Por segurança, não informamos se email existe
            return '';
        }

        $token = $this->generateResetToken();
        $this->storeResetToken($user->getId(), $token);

        // Aqui seria enviado o email com o token
        // Por ora, apenas retornamos o token para debug
        return $token;
    }

    /**
     * Redefine senha com token
     */
    public function resetPassword(string $token, string $newPassword): bool {
        $userId = $this->validateResetToken($token);
        
        if (!$userId) {
            throw new AuthenticationException('Token inválido ou expirado');
        }

        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new NotFoundException('Usuário não encontrado');
        }

        $user->setPassword($newPassword);
        $this->userRepository->save($user);
        $this->removeResetToken($token);

        return true;
    }

    /**
     * Ativa usuário
     */
    public function activateUser(int $userId): bool {
        return $this->userRepository->setActive($userId, true);
    }

    /**
     * Desativa usuário
     */
    public function deactivateUser(int $userId): bool {
        return $this->userRepository->setActive($userId, false);
    }

    /**
     * Lista usuários com paginação
     */
    public function listUsers(array $filters = [], int $page = 1, int $limit = 10): array {
        $offset = ($page - 1) * $limit;
        $users = $this->userRepository->findAll($filters, $limit, $offset);
        $total = $this->userRepository->count($filters);

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    // Métodos privados
    private function validateRegistrationData(array $userData): void {
        $required = ['name', 'email', 'password'];
        
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new ValidationException("Campo {$field} é obrigatório");
            }
        }

        if (strlen($userData['password']) < 8) {
            throw new ValidationException('Senha deve ter pelo menos 8 caracteres');
        }

        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Email inválido');
        }
    }

    private function validateUpdateData(array $userData): void {
        if (isset($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Email inválido');
        }
    }

    private function createSession(User $user, bool $remember = false): void {
        session_regenerate_id(true);
        $_SESSION[$this->sessionKey] = $user->getId();
        
        if ($remember) {
            // Implementar remember me com cookies seguros
            $token = $this->generateRememberToken();
            $this->storeRememberToken($user->getId(), $token);
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }
    }

    private function destroySession(): void {
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    private function getUserIdFromSession(): ?int {
        return $_SESSION[$this->sessionKey] ?? null;
    }

    private function generateResetToken(): string {
        return bin2hex(random_bytes(32));
    }

    private function generateRememberToken(): string {
        return bin2hex(random_bytes(32));
    }

    private function storeResetToken(int $userId, string $token): void {
        // Implementar armazenamento do token no banco
        // Tabela: password_resets (user_id, token, expires_at)
    }

    private function validateResetToken(string $token): ?int {
        // Implementar validação do token no banco
        return null; // Retornar user_id se válido
    }

    private function removeResetToken(string $token): void {
        // Remover token do banco após uso
    }

    private function storeRememberToken(int $userId, string $token): void {
        // Implementar armazenamento do remember token
    }

    private function checkRateLimit(string $email): void {
        // Implementar rate limiting para login
        // Usar Redis ou tabela de tentativas
    }

    private function incrementLoginAttempts(string $email): void {
        // Implementar contador de tentativas
    }

    private function clearLoginAttempts(string $email): void {
        // Limpar tentativas após login bem-sucedido
    }
}
