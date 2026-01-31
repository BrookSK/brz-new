<?php
namespace App\Models;

class AssessoriaOrcamento extends Model {
    protected $table = 'assessoria_orcamentos';

    public function findByToken(string $token): ?array {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE public_token = :t LIMIT 1");
        $stmt->bindValue(':t', $token);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    public function findByJobId(string $jobId): ?array {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE job_id = :j ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(':j', $jobId);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    public function getByUsuarioId(int $usuarioId, int $limit = 20): array {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE usuario_id = :u ORDER BY id DESC LIMIT {$limit}");
        $stmt->bindValue(':u', $usuarioId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function touchUpdatedAt(int $id): void {
        try {
            $stmt = $this->getConnection()->prepare("UPDATE {$this->table} SET updated_at = NOW() WHERE id = :id");
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Exception $e) {
        }
    }
}
