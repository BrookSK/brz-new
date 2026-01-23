<?php
namespace App\Models;

class Usuario extends Model {
    protected $table = 'usuarios';

    public function findByEmail($email) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findByDocumento($documento) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE documento = :documento");
        $stmt->bindParam(':documento', $documento);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function authenticate($email, $senha) {
        $usuario = $this->findByEmail($email);
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Atualizar último login
            $stmt = $this->connection->prepare("UPDATE {$this->table} SET ultimo_login = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $usuario['id']);
            $stmt->execute();
            
            unset($usuario['senha']); // Remover senha do retorno
            return $usuario;
        }
        
        return false;
    }

    public function create($data) {
        if (isset($data['senha'])) {
            $data['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        return parent::create($data);
    }

    public function updatePassword($id, $novaSenha) {
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $this->connection->prepare("UPDATE {$this->table} SET senha = :senha WHERE id = :id");
        $stmt->bindParam(':senha', $senhaHash);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function hasPermission($usuarioId, $acao) {
        $stmt = $this->connection->prepare("SELECT perfil FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $usuarioId);
        $stmt->execute();
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$usuario) return false;
        
        $permissoes = [
            'admin' => ['create', 'read', 'update', 'delete', 'admin'],
            'suporte' => ['read', 'support'],
            'vendedor' => ['read', 'sales'],
            'cliente' => ['create_order', 'read_own']
        ];
        
        return in_array($acao, $permissoes[$usuario['perfil']] ?? []);
    }

    public function getEnderecos($usuarioId) {
        $stmt = $this->connection->prepare("SELECT * FROM enderecos WHERE usuario_id = :id ORDER BY principal DESC, created_at DESC");
        $stmt->bindParam(':id', $usuarioId);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getPedidos($usuarioId, $limit = 50, $offset = 0) {
        $stmt = $this->connection->prepare("
            SELECT p.*, 
                   e_entrega.cep as cep_entrega, e_entrega.cidade as cidade_entrega,
                   e_cobranca.cep as cep_cobranca
            FROM pedidos p
            LEFT JOIN enderecos e_entrega ON p.endereco_entrega_id = e_entrega.id
            LEFT JOIN enderecos e_cobranca ON p.endereco_cobranca_id = e_cobranca.id
            WHERE p.usuario_id = :id 
            ORDER BY p.created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindParam(':id', $usuarioId);
        $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
