<?php
namespace App\Models;

class Usuario extends Model {
    protected $table = 'usuarios';

    private static ?array $cachedUserColumns = null;

    private function ensureRememberTokensTable(): void {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute(['remember_tokens']);
            $exists = (bool) $stmt->fetchColumn();
            if ($exists) {
                return;
            }

            $this->connection->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_token_hash (token_hash),
                INDEX idx_usuario_id (usuario_id),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    public function isPasswordResetTokenValid(string $token): bool {
        $uid = $this->getUserIdByResetToken($token);
        return !empty($uid) && (int) $uid > 0;
    }

    public function setRememberToken(int $usuarioId, string $token, int $ttlSeconds): bool {
        $usuarioId = (int) $usuarioId;
        $token = trim((string) $token);
        if ($usuarioId <= 0 || $token === '' || $ttlSeconds <= 0) {
            return false;
        }

        $this->ensureRememberTokensTable();

        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (int) $ttlSeconds);
        $createdAt = date('Y-m-d H:i:s');

        try {
            $stDel = $this->connection->prepare('DELETE FROM remember_tokens WHERE usuario_id = ?');
            $stDel->execute([$usuarioId]);
        } catch (\Exception $e) {
        }

        try {
            $st = $this->connection->prepare('INSERT INTO remember_tokens (usuario_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
            $st->execute([$usuarioId, $hash, $expiresAt, $createdAt]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clearRememberTokens(int $usuarioId): void {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return;
        }
        $this->ensureRememberTokensTable();
        try {
            $st = $this->connection->prepare('DELETE FROM remember_tokens WHERE usuario_id = ?');
            $st->execute([$usuarioId]);
        } catch (\Exception $e) {
        }
    }

    public function findByRememberToken(string $token): ?array {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }
        $this->ensureRememberTokensTable();
        $hash = hash('sha256', $token);
        try {
            $st = $this->connection->prepare('SELECT usuario_id FROM remember_tokens WHERE token_hash = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
            $st->execute([$hash]);
            $uid = $st->fetchColumn();
            if ($uid === false || $uid === null) {
                return null;
            }
            $uid = (int) $uid;
            if ($uid <= 0) {
                return null;
            }
            $u = $this->find($uid);
            return is_array($u) ? $u : null;
        } catch (\Exception $e) {
            return null;
        }
    }

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
        $orderCol = 'id';
        try {
            $stmtCols = $this->getConnection()->query("DESCRIBE {$this->table}");
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (is_array($cols)) {
                if (in_array('nome', $cols, true)) {
                    $orderCol = 'nome';
                } elseif (in_array('name', $cols, true)) {
                    $orderCol = 'name';
                }
            }
        } catch (\Exception $e) {
        }

        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} ORDER BY {$orderCol} ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function findByEmail($email) {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }

        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE LOWER(TRIM(email)) = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findByDocumento($documento) {
        $documento = preg_replace('/\D+/', '', (string) $documento);
        if ($documento === '') {
            return false;
        }

        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE documento = :documento");
        $stmt->bindParam(':documento', $documento);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function ensurePasswordResetsTable(): void {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute(['password_resets']);
            $exists = (bool) $stmt->fetchColumn();
            if ($exists) {
                return;
            }

            $this->connection->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_token_hash (token_hash),
                INDEX idx_usuario_id (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    public function requestPasswordResetToken(string $email): string {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        $user = $this->findByEmail($email);
        if (!$user || empty($user['id'])) {
            return '';
        }

        $this->ensurePasswordResetsTable();

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        try {
            $stDel = $this->connection->prepare('DELETE FROM password_resets WHERE usuario_id = ?');
            $stDel->execute([(int) $user['id']]);
        } catch (\Exception $e) {
        }

        try {
            $st = $this->connection->prepare("INSERT INTO password_resets (usuario_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())");
            $st->execute([(int) $user['id'], $hash]);
        } catch (\Exception $e) {
            return '';
        }

        return $token;
    }

    private function getUserIdByResetToken(string $token): ?int {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        $this->ensurePasswordResetsTable();

        $hash = hash('sha256', $token);
        try {
            $st = $this->connection->prepare('SELECT usuario_id FROM password_resets WHERE token_hash = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
            $st->execute([$hash]);
            $uid = $st->fetchColumn();
            if ($uid === false || $uid === null) {
                return null;
            }
            $uid = (int) $uid;
            return $uid > 0 ? $uid : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function consumeResetToken(string $token): void {
        $token = trim((string) $token);
        if ($token === '') {
            return;
        }

        $hash = hash('sha256', $token);
        try {
            $st = $this->connection->prepare('DELETE FROM password_resets WHERE token_hash = ?');
            $st->execute([$hash]);
        } catch (\Exception $e) {
        }
    }

    public function resetPasswordByToken(string $token, string $newPassword): bool {
        $uid = $this->getUserIdByResetToken($token);
        if (!$uid) {
            return false;
        }

        if (trim($newPassword) === '' || strlen((string) $newPassword) < 6) {
            return false;
        }

        $ok = (bool) $this->updatePassword((int) $uid, $newPassword);
        if ($ok) {
            $this->consumeResetToken($token);
        }
        return $ok;
    }

    public function authenticate($email, $senha) {
        $usuario = $this->findByEmail($email);

        $hash = null;
        $senhaCol = null;
        if (is_array($usuario)) {
            $s1 = isset($usuario['senha']) ? trim((string) $usuario['senha']) : null;
            $s2 = isset($usuario['password']) ? trim((string) $usuario['password']) : null;
            if ($s1 !== null && $s1 !== '') {
                $hash = $s1;
                $senhaCol = 'senha';
            } elseif ($s2 !== null && $s2 !== '') {
                $hash = $s2;
                $senhaCol = 'password';
            } elseif (array_key_exists('senha', $usuario)) {
                // fallback quando existe a coluna mas está vazia
                $hash = is_string($usuario['senha'] ?? null) ? (string) $usuario['senha'] : null;
                $senhaCol = 'senha';
            } elseif (array_key_exists('password', $usuario)) {
                $hash = is_string($usuario['password'] ?? null) ? (string) $usuario['password'] : null;
                $senhaCol = 'password';
            }
        }
        $ok = false;
        if ($usuario && is_string($hash) && $hash !== '') {
            $hashToVerify = trim($hash, " \t\n\r\0\x0B\"'");
            $isWpPrefixed = false;
            if (str_starts_with($hashToVerify, '$wp$')) {
                $hashToVerify = '$' . ltrim(substr($hashToVerify, 4), '$');
                $isWpPrefixed = true;
            }

            // Compatibilidade: aceita senha em texto plano antiga
            if (hash_equals($hash, (string) $senha)) {
                $ok = true;
                // Migrar para hash
                $newHash = password_hash((string) $senha, PASSWORD_DEFAULT);
                $col = $senhaCol ?: (array_key_exists('senha', $usuario) ? 'senha' : 'password');
                try {
                    $stmtHash = $this->connection->prepare("UPDATE {$this->table} SET {$col} = :hash WHERE id = :id");
                    $stmtHash->bindParam(':hash', $newHash);
                    $stmtHash->bindParam(':id', $usuario['id']);
                    $stmtHash->execute();
                    $hash = $newHash;
                } catch (\Exception $e) {
                }
            } elseif (password_verify((string) $senha, $hashToVerify)) {
                $ok = true;
                // Rehash quando necessário
                if ($isWpPrefixed || password_needs_rehash($hashToVerify, PASSWORD_DEFAULT)) {
                    $newHash = password_hash((string) $senha, PASSWORD_DEFAULT);
                    $col = $senhaCol ?: (array_key_exists('senha', $usuario) ? 'senha' : 'password');
                    try {
                        $stmtHash = $this->connection->prepare("UPDATE {$this->table} SET {$col} = :hash WHERE id = :id");
                        $stmtHash->bindParam(':hash', $newHash);
                        $stmtHash->bindParam(':id', $usuario['id']);
                        $stmtHash->execute();
                        $hash = $newHash;
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        if ($usuario && $ok) {
            // Atualizar último login
            $stmt = $this->connection->prepare("UPDATE {$this->table} SET ultimo_login = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $usuario['id']);
            $stmt->execute();
            
            unset($usuario['password'], $usuario['senha']);
            
            // Adicionar campo 'perfil' para compatibilidade
            $perfil = '';
            if (array_key_exists('perfil', $usuario) && $usuario['perfil'] !== null && trim((string) $usuario['perfil']) !== '') {
                $perfil = (string) $usuario['perfil'];
            } elseif (array_key_exists('role', $usuario) && $usuario['role'] !== null && trim((string) $usuario['role']) !== '') {
                $perfil = (string) $usuario['role'];
            } else {
                $perfil = 'cliente';
            }
            $usuario['perfil'] = $perfil;
            $usuario['nome'] = $usuario['name'] ?? $usuario['nome'] ?? '';
            
            return $usuario;
        }
        
        return false;
    }

    public function create($data) {
        if (isset($data['senha']) && $data['senha'] !== '' && !is_null($data['senha'])) {
            $info = password_get_info((string) $data['senha']);
            if (($info['algo'] ?? 0) === 0) {
                $data['senha'] = password_hash((string) $data['senha'], PASSWORD_DEFAULT);
            }
        }
        if (isset($data['password']) && $data['password'] !== '' && !is_null($data['password'])) {
            $info = password_get_info((string) $data['password']);
            if (($info['algo'] ?? 0) === 0) {
                $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            }
        }

        if (array_key_exists('documento', $data)) {
            $doc = preg_replace('/\D+/', '', (string) $data['documento']);
            $data['documento'] = $doc === '' ? null : $doc;
        }

        $id = parent::create($data);

        try {
            if (empty($data['suite'])) {
                $stmtCols = $this->getConnection()->query("DESCRIBE {$this->table}");
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($cols) && in_array('suite', $cols, true)) {
                    $stmt = $this->getConnection()->prepare("UPDATE {$this->table} SET `suite` = :sw WHERE id = :id AND (`suite` IS NULL OR `suite` = 0)");
                    $stmt->bindValue(':sw', (int) $id, \PDO::PARAM_INT);
                    $stmt->bindValue(':id', (int) $id, \PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        } catch (\Exception $e) {
        }

        return $id;
    }

    public function updatePassword($id, $novaSenha) {
        $hash = password_hash((string) $novaSenha, PASSWORD_DEFAULT);

        // Detectar coluna de senha existente
        $colunaSenha = 'senha';
        try {
            $stmtCols = $this->getConnection()->query("DESCRIBE {$this->table}");
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && in_array('senha', $cols, true)) {
                $colunaSenha = 'senha';
            } elseif (is_array($cols) && in_array('password', $cols, true)) {
                $colunaSenha = 'password';
            }
        } catch (\Exception $e) {
        }

        $stmt = $this->getConnection()->prepare("UPDATE {$this->table} SET {$colunaSenha} = :senha WHERE id = :id");
        $stmt->bindParam(':senha', $hash);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function update($id, $data) {
        try {
            // Obter colunas existentes na tabela
            $stmt = $this->getConnection()->query("DESCRIBE {$this->table}");
            $colunas = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Construir SQL dinâmico apenas com colunas existentes
            $setParts = [];
            $params = [];
            
            $mapeamentoColunas = [
                'nome' => 'nome',
                'email' => 'email',
                'documento' => 'documento',
                'telefone' => 'telefone',
                'avatar' => 'avatar',
                'foto' => 'foto',
                'foto_perfil' => 'foto_perfil',
                'imagem_perfil' => 'imagem_perfil',
                'endereco' => 'endereco',
                'numero' => 'numero',
                'complemento' => 'complemento',
                'bairro' => 'bairro',
                'cidade' => 'cidade',
                'estado' => 'estado',
                'cep' => 'cep',
                'data_nascimento' => 'data_nascimento',
                'pais_residencia' => 'pais_residencia',
                'termos_aceitos_em' => 'termos_aceitos_em',
                'termos_aceitos_ip' => 'termos_aceitos_ip',
                'termos_versao' => 'termos_versao',
                'precisa_recadastro' => 'precisa_recadastro',
                'wp_origem' => 'wp_origem',
                'wp_user_id' => 'wp_user_id',
                'perfil' => 'perfil',
                'status' => 'status',
                'creditos_disponiveis' => 'creditos_disponiveis',
                'notificacoes_email' => 'notificacoes_email',
                'notificacoes_sms' => 'notificacoes_sms',
                'idioma' => 'idioma'
            ];
            
            foreach ($mapeamentoColunas as $campoForm => $colunaBanco) {
                if (in_array($colunaBanco, $colunas) && array_key_exists($colunaBanco, $data)) {
                    $setParts[] = "{$colunaBanco} = :{$colunaBanco}";
                    $params[$colunaBanco] = $data[$colunaBanco];
                }
            }
            
            // Adicionar updated_at se existir
            if (in_array('updated_at', $colunas)) {
                $setParts[] = "updated_at = NOW()";
            }
            
            if (empty($setParts)) {
                throw new \Exception('Nenhuma coluna válida encontrada para atualização');
            }
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id = :id";
            $params['id'] = $id;
            
            $stmt = $this->getConnection()->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            return $stmt->execute();
            
        } catch (\Exception $e) {
            error_log('Erro no update de usuário: ' . $e->getMessage());
            throw $e;
        }
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
        $orderBy = 'created_at DESC';
        try {
            $stmtCols = $this->connection->query("DESCRIBE enderecos");
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && in_array('principal', $cols, true)) {
                $orderBy = 'principal DESC, created_at DESC';
            }
        } catch (\Exception $e) {
        }

        $stmt = $this->connection->prepare("SELECT * FROM enderecos WHERE usuario_id = :id ORDER BY {$orderBy}");
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

    private function getUserColumns(): array {
        if (self::$cachedUserColumns !== null) {
            return self::$cachedUserColumns;
        }

        try {
            $stmt = $this->getConnection()->query('DESCRIBE usuarios');
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            self::$cachedUserColumns = is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            self::$cachedUserColumns = [];
        }

        return self::$cachedUserColumns;
    }

    public function getMissingRequiredFields(array $usuario): array {
        $cols = $this->getUserColumns();

        $required = [
            'nome' => ['nome', 'name'],
            'email' => ['email'],
            'telefone' => ['telefone', 'phone'],
            'cep' => ['cep', 'zip_code'],
            'endereco' => ['endereco', 'address'],
            'numero' => ['numero', 'number'],
            'bairro' => ['bairro', 'neighborhood'],
            'cidade' => ['cidade', 'city'],
            'estado' => ['estado', 'state'],
            'data_nascimento' => ['data_nascimento', 'birth_date'],
            'pais_residencia' => ['pais_residencia'],
        ];

        $missing = [];
        foreach ($required as $label => $candidates) {
            $col = null;
            foreach ($candidates as $c) {
                if (in_array($c, $cols, true)) {
                    $col = $c;
                    break;
                }
            }
            if ($col === null) {
                continue;
            }

            $val = $usuario[$col] ?? '';
            if (is_string($val)) {
                $val = trim($val);
            }
            if ($val === '' || $val === null) {
                $missing[] = $label;
            }
        }

        $pais = '';
        if (in_array('pais_residencia', $cols, true)) {
            $pais = strtoupper(trim((string) ($usuario['pais_residencia'] ?? '')));
        }
        if ($pais === 'BR' || $pais === '') {
            $doc = '';
            foreach (['documento', 'cpf_cnpj', 'cpf'] as $c) {
                if (in_array($c, $cols, true) && !empty($usuario[$c])) {
                    $doc = preg_replace('/\D+/', '', (string) $usuario[$c]);
                    break;
                }
            }
            if ($doc === '' || strlen($doc) < 11) {
                if (!in_array('documento', $missing, true)) {
                    $missing[] = 'documento';
                }
            }
        }

        return $missing;
    }

    public function hasAcceptedTerms(array $usuario): bool {
        $cols = $this->getUserColumns();
        if (in_array('termos_aceitos_em', $cols, true)) {
            $v = (string) ($usuario['termos_aceitos_em'] ?? '');
            if (trim($v) !== '' && $v !== '0000-00-00 00:00:00') {
                return true;
            }
        }

        foreach (['termos_aceitos', 'aceitou_termos', 'aceite_termos', 'termos'] as $c) {
            if (in_array($c, $cols, true)) {
                $v = $usuario[$c] ?? null;
                if ($v === 1 || $v === '1' || $v === true || $v === 'true' || $v === 'on') {
                    return true;
                }
            }
        }

        return false;
    }

    public function isProfileComplete(array $usuario): bool {
        return empty($this->getMissingRequiredFields($usuario));
    }
}
