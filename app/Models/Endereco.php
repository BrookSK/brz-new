<?php
namespace App\Models;

class Endereco extends Model {
    protected $table = 'enderecos';
    
    public function __construct() {
        parent::__construct();
    }
    
    public function create($data) {
        $cols = [];
        try {
            $stmtCols = $this->connection->query("DESCRIBE {$this->table}");
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
        }

        $dataNormalized = $data;
        if (!isset($dataNormalized['logradouro']) && isset($dataNormalized['endereco'])) {
            $dataNormalized['logradouro'] = $dataNormalized['endereco'];
        }
        if (!isset($dataNormalized['endereco']) && isset($dataNormalized['logradouro'])) {
            $dataNormalized['endereco'] = $dataNormalized['logradouro'];
        }

        $allowedMap = [
            'usuario_id' => 'usuario_id',
            'tipo' => 'tipo',
            'cep' => 'cep',
            'logradouro' => 'logradouro',
            'endereco' => 'endereco',
            'numero' => 'numero',
            'complemento' => 'complemento',
            'bairro' => 'bairro',
            'cidade' => 'cidade',
            'estado' => 'estado',
            'pais' => 'pais',
            'principal' => 'principal',
        ];

        $insert = [];
        foreach ($allowedMap as $key => $col) {
            if (isset($dataNormalized[$key]) && (empty($cols) || in_array($col, $cols, true))) {
                $val = $dataNormalized[$key];
                // Normalizar estado para UF de 2 letras
                if ($col === 'estado') {
                    $val = \App\Models\Usuario::normalizeEstado((string) $val);
                }
                $insert[$col] = $val;
            }
        }

        if (!empty($cols) && in_array('created_at', $cols, true)) {
            $insert['created_at'] = date('Y-m-d H:i:s');
        }
        if (empty($insert)) {
            return false;
        }

        // Validar cidade: mínimo 3 caracteres
        if (isset($insert['cidade']) && $insert['cidade'] !== '' && mb_strlen(trim((string) $insert['cidade'])) < 3) {
            throw new \Exception('Cidade deve ter no mínimo 3 caracteres');
        }

        $columns = implode(', ', array_keys($insert));
        $placeholders = ':' . implode(', :', array_keys($insert));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->connection->prepare($sql);

        foreach ($insert as $k => $v) {
            $stmt->bindValue(":" . $k, $v);
        }

        return $stmt->execute();
    }
    
    public function findByUsuario($usuarioId) {
        $sql = "SELECT * FROM {$this->table} WHERE usuario_id = :usuario_id ORDER BY created_at DESC";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        // Normalizar estado para UF de 2 letras
        if (isset($data['estado'])) {
            $data['estado'] = \App\Models\Usuario::normalizeEstado((string) $data['estado']);
        }

        // Validar cidade: mínimo 3 caracteres
        if (isset($data['cidade']) && $data['cidade'] !== '' && mb_strlen(trim((string) $data['cidade'])) < 3) {
            throw new \Exception('Cidade deve ter no mínimo 3 caracteres');
        }

        // Descobrir colunas reais da tabela para montar o UPDATE dinamicamente.
        // Schemas variam: algumas bases usam 'endereco', outras 'logradouro';
        // 'updated_at' pode não existir. Montar apenas com o que existe evita o
        // erro "Unknown column".
        $cols = [];
        try {
            $stmtCols = $this->connection->query("DESCRIBE {$this->table}");
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        // Espelhar endereco <-> logradouro (nem toda base tem as duas colunas).
        $dataNormalized = $data;
        if (!isset($dataNormalized['logradouro']) && isset($dataNormalized['endereco'])) {
            $dataNormalized['logradouro'] = $dataNormalized['endereco'];
        }
        if (!isset($dataNormalized['endereco']) && isset($dataNormalized['logradouro'])) {
            $dataNormalized['endereco'] = $dataNormalized['logradouro'];
        }

        $allowed = ['tipo', 'cep', 'logradouro', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'pais'];

        $setParts = [];
        $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $dataNormalized)) {
                continue;
            }
            // Só incluir a coluna se ela existir no schema (ou se DESCRIBE falhou).
            if (!empty($cols) && !in_array($col, $cols, true)) {
                continue;
            }
            $setParts[] = "{$col} = :{$col}";
            $params[":{$col}"] = $dataNormalized[$col];
        }

        if (empty($setParts)) {
            return false;
        }

        if (empty($cols) || in_array('updated_at', $cols, true)) {
            $setParts[] = 'updated_at = NOW()';
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->connection->prepare($sql);

        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
}
