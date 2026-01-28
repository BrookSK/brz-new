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
                $insert[$col] = $dataNormalized[$key];
            }
        }

        if (!empty($cols) && in_array('created_at', $cols, true)) {
            $insert['created_at'] = date('Y-m-d H:i:s');
        }
        if (empty($insert)) {
            return false;
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
        $sql = "UPDATE {$this->table} SET 
                tipo = :tipo, 
                cep = :cep, 
                logradouro = :logradouro, 
                numero = :numero, 
                complemento = :complemento, 
                bairro = :bairro, 
                cidade = :cidade, 
                estado = :estado, 
                updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->connection->prepare($sql);
        
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':cep', $data['cep']);
        $stmt->bindParam(':logradouro', $data['logradouro']);
        $stmt->bindParam(':numero', $data['numero']);
        $stmt->bindParam(':complemento', $data['complemento']);
        $stmt->bindParam(':bairro', $data['bairro']);
        $stmt->bindParam(':cidade', $data['cidade']);
        $stmt->bindParam(':estado', $data['estado']);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
}
