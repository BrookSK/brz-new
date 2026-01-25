<?php
namespace App\Models;

class Usuario extends Model {
    protected $table = 'usuarios';

    public function __construct() {
        parent::__construct();
    }
    
    public function find($id) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function getAll() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
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
        
        if ($usuario && ($senha === $usuario['senha'] || password_verify($senha, $usuario['senha']))) {
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
        // Não fazer hash da senha - armazenar em texto plano
        return parent::create($data);
    }

    public function updatePassword($id, $novaSenha) {
        // Armazenar senha em texto plano
        $stmt = $this->getConnection()->prepare("UPDATE {$this->table} SET senha = :senha WHERE id = :id");
        $stmt->bindParam(':senha', $novaSenha);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function update($id, $data) {
        $stmt = $this->getConnection()->prepare("
            UPDATE {$this->table} 
            SET nome = :nome, 
                email = :email, 
                documento = :documento, 
                telefone = :telefone, 
                endereco = :endereco, 
                numero = :numero,
                complemento = :complemento,
                bairro = :bairro,
                cidade = :cidade, 
                estado = :estado, 
                cep = :cep, 
                perfil = :perfil, 
                status = :status, 
                creditos_disponiveis = :creditos_disponiveis,
                notificacoes_email = :notificacoes_email,
                notificacoes_sms = :notificacoes_sms,
                idioma = :idioma,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':documento', $data['documento']);
        $stmt->bindParam(':telefone', $data['telefone']);
        $stmt->bindParam(':endereco', $data['endereco']);
        $stmt->bindParam(':numero', $data['numero']);
        $stmt->bindParam(':complemento', $data['complemento']);
        $stmt->bindParam(':bairro', $data['bairro']);
        $stmt->bindParam(':cidade', $data['cidade']);
        $stmt->bindParam(':estado', $data['estado']);
        $stmt->bindParam(':cep', $data['cep']);
        $stmt->bindParam(':perfil', $data['perfil']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':creditos_disponiveis', $data['creditos_disponiveis']);
        $stmt->bindParam(':notificacoes_email', $data['notificacoes_email']);
        $stmt->bindParam(':notificacoes_sms', $data['notificacoes_sms']);
        $stmt->bindParam(':idioma', $data['idioma']);
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function getUsuariosComFiltros($busca = '', $status = '', $perfil = '', $limite = 20, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($busca) {
            $sql .= " AND (nome LIKE :busca OR email LIKE :busca OR documento LIKE :busca)";
            $params['busca'] = "%{$busca}%";
        }
        
        if ($status) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        if ($perfil) {
            $sql .= " AND perfil = :perfil";
            $params['perfil'] = $perfil;
        }
        
        $sql .= " ORDER BY nome ASC LIMIT :limite OFFSET :offset";
        $params['limite'] = $limite;
        $params['offset'] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTotalUsuarios($busca = '', $status = '', $perfil = '') {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($busca) {
            $sql .= " AND (nome LIKE :busca OR email LIKE :busca OR documento LIKE :busca)";
            $params['busca'] = "%{$busca}%";
        }
        
        if ($status) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        if ($perfil) {
            $sql .= " AND perfil = :perfil";
            $params['perfil'] = $perfil;
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }
    
    public function getEstatisticas() {
        $stmt = $this->getConnection()->prepare("
            SELECT 
                COUNT(*) as total_usuarios,
                COUNT(CASE WHEN status = 'ativo' THEN 1 END) as usuarios_ativos,
                COUNT(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE) THEN 1 END) as usuarios_mes
            FROM {$this->table}
        ");
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
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
