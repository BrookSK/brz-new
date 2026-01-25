<?php
/**
 * @file UserRoles.php
 * @responsibilidade Constantes para roles de usuário
 * @descrição Define os papéis/permissões disponíveis no sistema
 * @conexão Usado por User, AuthService e validações de permissão
 */

namespace App\Shared\Constants;

class UserRoles {
    public const ADMIN = 'admin';
    public const CLIENT = 'client';
    public const OPERATOR = 'operator';

    /**
     * Retorna todos os roles disponíveis
     */
    public static function getAll(): array {
        return [
            self::ADMIN,
            self::CLIENT,
            self::OPERATOR
        ];
    }

    /**
     * Retorna a descrição de um role
     */
    public static function getDescription(string $role): string {
        $descriptions = [
            self::ADMIN => 'Administrador',
            self::CLIENT => 'Cliente',
            self::OPERATOR => 'Operador'
        ];

        return $descriptions[$role] ?? 'Desconhecido';
    }

    /**
     * Retorna o nível de hierarquia do role
     */
    public static function getLevel(string $role): int {
        $levels = [
            self::ADMIN => 100,
            self::OPERATOR => 50,
            self::CLIENT => 10
        ];

        return $levels[$role] ?? 0;
    }

    /**
     * Verifica se um role pode acessar determinado nível
     */
    public static function canAccess(string $userRole, int $requiredLevel): bool {
        return self::getLevel($userRole) >= $requiredLevel;
    }

    /**
     * Verifica se um role pode gerenciar outro
     */
    public static function canManage(string $managerRole, string $targetRole): bool {
        return self::getLevel($managerRole) > self::getLevel($targetRole);
    }

    /**
     * Retorna os permissões por role
     */
    public static function getPermissions(string $role): array {
        $permissions = [
            self::ADMIN => [
                'users.create',
                'users.read',
                'users.update',
                'users.delete',
                'products.create',
                'products.read',
                'products.update',
                'products.delete',
                'orders.create',
                'orders.read',
                'orders.update',
                'orders.delete',
                'payments.read',
                'payments.confirm',
                'configurations.update',
                'reports.read',
                'system.admin'
            ],
            self::OPERATOR => [
                'products.create',
                'products.read',
                'products.update',
                'orders.create',
                'orders.read',
                'orders.update',
                'payments.read',
                'payments.confirm'
            ],
            self::CLIENT => [
                'products.read',
                'orders.create',
                'orders.read',
                'orders.update', // apenas cancelar próprio pedido
                'profile.read',
                'profile.update'
            ]
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Verifica se um role tem determinada permissão
     */
    public static function hasPermission(string $role, string $permission): bool {
        return in_array($permission, self::getPermissions($role));
    }

    /**
     * Retorna os roles disponíveis para seleção em formulários
     */
    public static function getForSelect(): array {
        return [
            self::CLIENT => self::getDescription(self::CLIENT),
            self::OPERATOR => self::getDescription(self::OPERATOR),
            self::ADMIN => self::getDescription(self::ADMIN)
        ];
    }

    /**
     * Valida se um role é válido
     */
    public static function isValid(string $role): bool {
        return in_array($role, self::getAll());
    }
}
