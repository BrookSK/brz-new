<?php
namespace App\Models;

class StreamingUsage extends Model {
    protected $table = 'streaming_usage';

    /**
     * Retorna uso do mês atual
     */
    public function getCurrentMonth(): array {
        $yearMonth = date('Y-m');
        $stmt = $this->connection->prepare(
            "SELECT `year_month`, `minutes_streamed` FROM {$this->table} WHERE `year_month` = :ym"
        );
        $stmt->bindValue(':ym', $yearMonth);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return ['year_month' => $yearMonth, 'minutes_streamed' => 0];
        }
        return $row;
    }

    /**
     * Adiciona minutos ao mês atual
     */
    public function addMinutes(int $minutes): bool {
        $yearMonth = date('Y-m');
        $stmt = $this->connection->prepare(
            "INSERT INTO {$this->table} (`year_month`, `minutes_streamed`) 
             VALUES (:ym, :min)
             ON DUPLICATE KEY UPDATE `minutes_streamed` = `minutes_streamed` + :min2"
        );
        $stmt->bindValue(':ym', $yearMonth);
        $stmt->bindValue(':min', $minutes, \PDO::PARAM_INT);
        $stmt->bindValue(':min2', $minutes, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Verifica se a cota foi excedida
     */
    public function isQuotaExceeded(int $minutosInclusos): bool {
        $usage = $this->getCurrentMonth();
        return $usage['minutes_streamed'] >= $minutosInclusos;
    }
}
