<?php
/**
 * @file UserRepositoryInterface.php
 * @responsibilidade Interface para Repository de Usuários
 * @descrição Define o contrato para operações com usuários no banco de dados
 * @conexão Implementada por MySQLUserRepository, usada por AuthService
 */

namespace App\Core\Repositories;

use App\Core\Domain\User;
use App\Core\ValueObjects\Email;

interface UserRepositoryInterface {
    /**
     * Salva um usuário (cria ou atualiza)
     */
    public function save(User $user): User;

    /**
     * Busca usuário por ID
     */
    public function findById(int $id): ?User;

    /**
     * Busca usuário por email
     */
    public function findByEmail(Email $email): ?User;

    /**
     * Busca usuário por CPF
     */
    public function findByCpf(string $cpf): ?User;

    /**
     * Lista usuários com paginação e filtros
     */
    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array;

    /**
     * Conta total de usuários com filtros
     */
    public function count(array $filters = []): int;

    /**
     * Verifica se email já existe
     */
    public function emailExists(Email $email, ?int $excludeId = null): bool;

    /**
     * Verifica se CPF já existe
     */
    public function cpfExists(string $cpf, ?int $excludeId = null): bool;

    /**
     * Ativa ou desativa usuário
     */
    public function setActive(int $id, bool $active): bool;

    /**
     * Remove usuário
     */
    public function delete(int $id): bool;

    /**
     * Busca usuários por role
     */
    public function findByRole(string $role, int $limit = 10, int $offset = 0): array;

    /**
     * Busca usuários ativos
     */
    public function findActive(int $limit = 10, int $offset = 0): array;

    /**
     * Busca usuários inativos
     */
    public function findInactive(int $limit = 10, int $offset = 0): array;

    /**
     * Busca usuários por nome ou email
     */
    public function search(string $term, int $limit = 10, int $offset = 0): array;

    /**
     * Atualiza último login do usuário
     */
    public function updateLastLogin(int $id): bool;

    /**
     * Atualiza senha do usuário
     */
    public function updatePassword(int $id, string $hashedPassword): bool;

    /**
     * Verifica se usuário está ativo
     */
    public function isActive(int $id): bool;

    /**
     * Obtém estatísticas de usuários
     */
    public function getStatistics(): array;

    /**
     * Busca usuários criados em um período
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array;

    /**
     * Exporta usuários para CSV/Excel
     */
    public function export(array $filters = []): array;
}
