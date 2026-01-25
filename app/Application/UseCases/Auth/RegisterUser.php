<?php
/**
 * @file RegisterUser.php
 * @responsibilidade Use Case para registro de novos usuários
 * @descrição Implementa o fluxo completo de registro de usuário
 * @conexão Usado por AuthController, depende de AuthService
 */

namespace App\Application\UseCases\Auth;

use App\Core\Services\AuthService;
use App\Application\DTOs\UserDTO;
use App\Shared\Exceptions\ValidationException;

class RegisterUser {
    private AuthService $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    /**
     * Executa o registro de um novo usuário
     */
    public function execute(UserDTO $userDTO): array {
        try {
            // Validar dados do DTO
            $this->validateUserDTO($userDTO);

            // Preparar dados para o serviço
            $userData = [
                'name' => $userDTO->getName(),
                'email' => $userDTO->getEmail(),
                'password' => $userDTO->getPassword(),
                'role' => $userDTO->getRole() ?? 'client',
                'phone' => $userDTO->getPhone(),
                'cpf' => $userDTO->getCpf(),
                'birth_date' => $userDTO->getBirthDate(),
                'address' => $userDTO->getAddress(),
                'number' => $userDTO->getNumber(),
                'neighborhood' => $userDTO->getNeighborhood(),
                'city' => $userDTO->getCity(),
                'state' => $userDTO->getState(),
                'zip_code' => $userDTO->getZipCode()
            ];

            // Registrar usuário através do serviço
            $user = $this->authService->register($userData);

            // Retornar resposta formatada
            return [
                'success' => true,
                'message' => 'Usuário registrado com sucesso',
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail()->getValue(),
                    'role' => $user->getRole(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ];

        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->getErrors()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao registrar usuário: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valida os dados do DTO
     */
    private function validateUserDTO(UserDTO $userDTO): void {
        $errors = [];

        // Validação do nome
        if (empty($userDTO->getName())) {
            $errors['name'] = 'Nome é obrigatório';
        } elseif (strlen($userDTO->getName()) < 3) {
            $errors['name'] = 'Nome deve ter pelo menos 3 caracteres';
        } elseif (strlen($userDTO->getName()) > 100) {
            $errors['name'] = 'Nome deve ter no máximo 100 caracteres';
        }

        // Validação do email
        if (empty($userDTO->getEmail())) {
            $errors['email'] = 'Email é obrigatório';
        } elseif (!filter_var($userDTO->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        }

        // Validação da senha
        if (empty($userDTO->getPassword())) {
            $errors['password'] = 'Senha é obrigatória';
        } elseif (strlen($userDTO->getPassword()) < 8) {
            $errors['password'] = 'Senha deve ter pelo menos 8 caracteres';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $userDTO->getPassword())) {
            $errors['password'] = 'Senha deve conter pelo menos uma letra maiúscula, uma minúscula e um número';
        }

        // Validação do telefone (se fornecido)
        if (!empty($userDTO->getPhone())) {
            $phone = preg_replace('/[^0-9]/', '', $userDTO->getPhone());
            if (strlen($phone) < 10 || strlen($phone) > 13) {
                $errors['phone'] = 'Telefone inválido';
            }
        }

        // Validação do CPF (se fornecido)
        if (!empty($userDTO->getCpf())) {
            $cpf = preg_replace('/[^0-9]/', '', $userDTO->getCpf());
            if (strlen($cpf) !== 11) {
                $errors['cpf'] = 'CPF deve ter 11 dígitos';
            }
        }

        // Validação da data de nascimento (se fornecida)
        if (!empty($userDTO->getBirthDate())) {
            $birthDate = \DateTime::createFromFormat('Y-m-d', $userDTO->getBirthDate());
            if (!$birthDate) {
                $errors['birth_date'] = 'Data de nascimento inválida';
            } else {
                $now = new \DateTime();
                $age = $now->diff($birthDate)->y;
                if ($age < 13 || $age > 120) {
                    $errors['birth_date'] = 'Idade deve estar entre 13 e 120 anos';
                }
            }
        }

        // Validação do CEP (se fornecido)
        if (!empty($userDTO->getZipCode())) {
            $zipCode = preg_replace('/[^0-9]/', '', $userDTO->getZipCode());
            if (strlen($zipCode) !== 8) {
                $errors['zip_code'] = 'CEP deve ter 8 dígitos';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Dados inválidos', $errors);
        }
    }
}
