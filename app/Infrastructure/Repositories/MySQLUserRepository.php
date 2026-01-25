<?php
/**
 * @file MySQLUserRepository.php
 * @responsibilidade Implementação MySQL do Repository de Usuários
 * @descrição Persistência de usuários no banco MySQL com todas as operações CRUD
 * @conexão Implementa UserRepositoryInterface, usado por AuthService
 */

namespace App\Infrastructure\Repositories;

use App\Core\Repositories\UserRepositoryInterface;
use App\Core\Domain\User;
use App\Core\ValueObjects\Email;
use App\Core\ValueObjects\Phone;
use App\Shared\Constants\UserRoles;
use PDO;
use PDOException;

class MySQLUserRepository implements UserRepositoryInterface {
    private PDO $connection;

    public function __construct(PDO $connection) {
        $this->connection = $connection;
    }

    public function save(User $user): User {
        try {
            $this->connection->beginTransaction();

            $data = $user->toArray();

            if ($user->getId() === null) {
                // Insert
                $sql = "
                    INSERT INTO usuarios (
                        name, email, password, phone, cpf, birth_date,
                        address, number, neighborhood, city, state, zip_code,
                        role, active, created_at, updated_at
                    ) VALUES (
                        :name, :email, :password, :phone, :cpf, :birth_date,
                        :address, :number, :neighborhood, :city, :state, :zip_code,
                        :role, :active, :created_at, :updated_at
                    )
                ";

                $stmt = $this->connection->prepare($sql);
                $this->bindUserParameters($stmt, $data);
                $stmt->execute();

                $user->setId($this->connection->lastInsertId());
            } else {
                // Update
                $sql = "
                    UPDATE usuarios SET
                        name = :name,
                        email = :email,
                        password = :password,
                        phone = :phone,
                        cpf = :cpf,
                        birth_date = :birth_date,
                        address = :address,
                        number = :number,
                        neighborhood = :neighborhood,
                        city = :city,
                        state = :state,
                        zip_code = :zip_code,
                        role = :role,
                        active = :active,
                        updated_at = :updated_at
                    WHERE id = :id
                ";

                $stmt = $this->connection->prepare($sql);
                $data['id'] = $user->getId();
                $this->bindUserParameters($stmt, $data);
                $stmt->execute();
            }

            $this->connection->commit();
            return $user;

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new \RuntimeException("Erro ao salvar usuário: " . $e->getMessage());
        }
    }

    public function findById(int $id): ?User {
        $sql = "SELECT * FROM usuarios WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->hydrateUser($data) : null;
    }

    public function findByEmail(Email $email): ?User {
        $sql = "SELECT * FROM usuarios WHERE email = :email";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':email', $email->getValue());
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->hydrateUser($data) : null;
    }

    public function findByCpf(string $cpf): ?User {
        $sql = "SELECT * FROM usuarios WHERE cpf = :cpf";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':cpf', $cpf);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->hydrateUser($data) : null;
    }

    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM usuarios WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->hydrateUser($data);
        }

        return $users;
    }

    public function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE 1=1";
        $params = [];

        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function emailExists(Email $email, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = :email";
        $params = [':email' => $email->getValue()];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    public function cpfExists(string $cpf, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE cpf = :cpf";
        $params = [':cpf' => $cpf];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    public function setActive(int $id, bool $active): bool {
        $sql = "UPDATE usuarios SET active = :active, updated_at = NOW() WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':active', $active, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(int $id): bool {
        try {
            $this->connection->beginTransaction();

            // Soft delete - apenas desativa
            $sql = "UPDATE usuarios SET active = 0, updated_at = NOW() WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();

            $this->connection->commit();
            return $result;

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new \RuntimeException("Erro ao deletar usuário: " . $e->getMessage());
        }
    }

    public function findByRole(string $role, int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM usuarios WHERE role = :role ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':role', $role);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->hydrateUser($data);
        }

        return $users;
    }

    public function findActive(int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM usuarios WHERE active = 1 ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->hydrateUser($data);
        }

        return $users;
    }

    public function findInactive(int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM usuarios WHERE active = 0 ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->hydrateUser($data);
        }

        return $users;
    }

    public function search(string $term, int $limit = 10, int $offset = 0): array {
        $sql = "
            SELECT * FROM usuarios 
            WHERE name LIKE :term OR email LIKE :term OR cpf LIKE :term 
            ORDER BY name ASC 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':term', "%{$term}%");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->hydrateUser($data);
        }

        return $users;
    }

    public function updateLastLogin(int $id): bool {
        $sql = "UPDATE usuarios SET last_login = NOW() WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updatePassword(int $id, string $hashedPassword): bool {
        $sql = "UPDATE usuarios SET password = :password, updated_at = NOW() WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function isActive(int $id): bool {
        $sql = "SELECT active FROM usuarios WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function getStatistics(): array {
        $stats = [];

        // Total de usuários
        $stmt = $this->connection->query("SELECT COUNT(*) as total FROM usuarios");
        $stats['total'] = (int) $stmt->fetchColumn();

        // Usuários ativos
        $stmt = $this->connection->query("SELECT COUNT(*) as active FROM usuarios WHERE active = 1");
        $stats['active'] = (int) $stmt->fetchColumn();

        // Usuários por role
        $stmt = $this->connection->query("
            SELECT role, COUNT(*) as count 
            FROM usuarios 
            GROUP BY role
        ");
        $stats['by_role'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Usuários cadastrados este mês
        $stmt = $this->connection->query("
            SELECT COUNT(*) as this_month 
            FROM usuarios 
            WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stats['this_month'] = (int) $stmt->fetchColumn();

        // Usuários cadastrados hoje
        $stmt = $this->connection->query("
            SELECT COUNT(*) as today 
            FROM usuarios 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['today'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array {
        $sql = "
            SELECT * FROM usuarios 
            WHERE created_at BETWEEN :start_date AND :end_date 
            ORDER BY created_at DESC
        ";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
        $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->hydrateUser($data);
        }

        return $users;
    }

    public function export(array $filters = []): array {
        $sql = "
            SELECT 
                id, name, email, phone, cpf, birth_date,
                address, number, neighborhood, city, state, zip_code,
                role, active, created_at, updated_at
            FROM usuarios 
            WHERE 1=1
        ";
        $params = [];

        $this->applyFilters($sql, $params, $filters);
        $sql .= " ORDER BY name ASC";

        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Métodos auxiliares
    private function bindUserParameters($stmt, array $data): void {
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':password', $data['password']);
        $stmt->bindValue(':phone', $data['phone']);
        $stmt->bindValue(':cpf', $data['cpf']);
        $stmt->bindValue(':birth_date', $data['birth_date']);
        $stmt->bindValue(':address', $data['address']);
        $stmt->bindValue(':number', $data['number']);
        $stmt->bindValue(':neighborhood', $data['neighborhood']);
        $stmt->bindValue(':city', $data['city']);
        $stmt->bindValue(':state', $data['state']);
        $stmt->bindValue(':zip_code', $data['zip_code']);
        $stmt->bindValue(':role', $data['role']);
        $stmt->bindValue(':active', $data['active'], PDO::PARAM_BOOL);
        $stmt->bindValue(':created_at', $data['created_at']);
        $stmt->bindValue(':updated_at', $data['updated_at']);

        if (isset($data['id'])) {
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
        }
    }

    private function applyFilters(string &$sql, array &$params, array $filters): void {
        if (!empty($filters['name'])) {
            $sql .= " AND name LIKE :name";
            $params[':name'] = "%{$filters['name']}%";
        }

        if (!empty($filters['email'])) {
            $sql .= " AND email LIKE :email";
            $params[':email'] = "%{$filters['email']}%";
        }

        if (!empty($filters['role'])) {
            $sql .= " AND role = :role";
            $params[':role'] = $filters['role'];
        }

        if (isset($filters['active'])) {
            $sql .= " AND active = :active";
            $params[':active'] = $filters['active'];
        }

        if (!empty($filters['created_from'])) {
            $sql .= " AND created_at >= :created_from";
            $params[':created_from'] = $filters['created_from'];
        }

        if (!empty($filters['created_to'])) {
            $sql .= " AND created_at <= :created_to";
            $params[':created_to'] = $filters['created_to'];
        }
    }

    private function hydrateUser(array $data): User {
        return User::fromArray($data);
    }
}
