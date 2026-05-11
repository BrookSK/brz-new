<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminRedirecionamentoController extends Controller {

    /**
     * Verifica se a tabela tem todas as colunas obrigatórias.
     * Se não tiver, dropa e recria com o DDL correto.
     * Se tiver, adiciona apenas as colunas faltantes (best-effort).
     */
    private function garantirSchema(\PDO $db, string $tabela, array $colsObrigatorias, string $ddl): void {
        try {
            $st = $db->prepare("SHOW TABLES LIKE ?");
            $st->execute([$tabela]);
            $existe = (bool) $st->fetchColumn();

            if (!$existe) return; // CREATE TABLE IF NOT EXISTS já criou

            $existentes = $db->query("DESCRIBE `$tabela`")->fetchAll(\PDO::FETCH_COLUMN);
            $faltam = array_diff($colsObrigatorias, $existentes);

            if (!empty($faltam)) {
                // Schema incompatível — dropar e recriar
                $db->exec("DROP TABLE `$tabela`");
                $db->exec($ddl);
            }
        } catch (\Exception $e) {
            error_log("[Redirecionamento] garantirSchema($tabela): " . $e->getMessage());
        }
    }

    /** Adiciona colunas faltantes em uma tabela existente (best-effort) */

    /**
     * Retorna o redirecionador vinculado ao usuário logado.
     * Tenta por usuario_id primeiro, depois por email como fallback.
     * Se perfil não for 'redirecionador', retorna null.
     */
    private function getRedirecionadorFixo(): ?array {
        $perfil = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
        if ($perfil !== 'redirecionador') return null;

        $db  = $this->pdo();
        $uid = (int)($_SESSION['usuario_id'] ?? 0);

        // 1) por usuario_id
        if ($uid > 0) {
            $st = $db->prepare("SELECT id, nome FROM redirecionadores WHERE usuario_id=? AND status='ativo' LIMIT 1");
            $st->execute([$uid]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row) return $row;
        }

        // 2) fallback por email do usuário logado
        $email = trim((string)($_SESSION['usuario_email'] ?? ''));
        if ($email !== '') {
            $st = $db->prepare("SELECT id, nome FROM redirecionadores WHERE email=? AND status='ativo' LIMIT 1");
            $st->execute([$email]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                // Aproveita para vincular usuario_id para próximas vezes
                if ($uid > 0) {
                    try { $db->prepare("UPDATE redirecionadores SET usuario_id=? WHERE id=?")->execute([$uid, $row['id']]); } catch (\Exception $e) {}
                }
                return $row;
            }
        }

        // 3) Se não achou vínculo, retorna um placeholder para esconder o campo
        //    (o redirecionador existe mas ainda não foi vinculado — não deve ver o select)
        return ['id' => 0, 'nome' => $_SESSION['usuario_nome'] ?? 'Você'];
    }
    private function migrarColunas(\PDO $db, string $tabela, array $colunas): void {
        try {
            $existentes = $db->query("DESCRIBE `$tabela`")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($colunas as $col => $def) {
                if (!in_array($col, $existentes, true)) {
                    try {
                        $db->exec("ALTER TABLE `$tabela` ADD COLUMN `$col` $def");
                    } catch (\Exception $e) {
                        error_log("[Redirecionamento] ALTER $tabela ADD $col: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("[Redirecionamento] migrarColunas($tabela): " . $e->getMessage());
        }
    }

    private function auth(): void {
        (new AuthService())->requerPerfis(['admin', 'suporte', 'redirecionador']);
    }

    private function adminOnly(): void {
        (new AuthService())->requerPerfis(['admin', 'suporte']);
    }

    private function pdo(): \PDO {
        return \Config\Database::getConnection();
    }

    /** Cria todas as tabelas do módulo se não existirem */
    private function migrar(): void {
        $db = $this->pdo();
        try {
        $db->exec("CREATE TABLE IF NOT EXISTS redirecionadores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT DEFAULT NULL,
            nome VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            telefone VARCHAR(30) DEFAULT NULL,
            suite VARCHAR(50) DEFAULT NULL,
            conta_bancaria TEXT DEFAULT NULL,
            status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            redirecionador_id INT NOT NULL,
            nome VARCHAR(200) NOT NULL,
            cpf VARCHAR(20) DEFAULT NULL,
            email VARCHAR(200) DEFAULT NULL,
            telefone VARCHAR(30) DEFAULT NULL,
            data_nascimento DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_red_id (redirecionador_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_enderecos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT NOT NULL,
            logradouro VARCHAR(255) NOT NULL,
            numero VARCHAR(20) DEFAULT NULL,
            complemento VARCHAR(100) DEFAULT NULL,
            bairro VARCHAR(100) DEFAULT NULL,
            cidade VARCHAR(100) NOT NULL,
            estado VARCHAR(2) NOT NULL,
            cep VARCHAR(10) NOT NULL,
            principal TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cli_id (cliente_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_tabela_pesos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            peso_ate_kg DECIMAL(6,3) NOT NULL,
            valor_usd DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_peso (peso_ate_kg)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Migração defensiva: garantir colunas corretas na tabela de pesos
        // (pode existir com schema antigo — dropar e recriar se necessário)
        try {
            $existingCols = $db->query("DESCRIBE redirecionamento_tabela_pesos")->fetchAll(\PDO::FETCH_COLUMN);
            $hasPesoAte  = in_array('peso_ate_kg', $existingCols, true);
            $hasValorUsd = in_array('valor_usd',   $existingCols, true);
            if (!$hasPesoAte || !$hasValorUsd) {
                $hasPesoMax = in_array('peso_max_kg', $existingCols, true);
                $hasPesoMin = in_array('peso_min_kg', $existingCols, true);
                if (!$hasPesoAte) {
                    if ($hasPesoMax) {
                        $db->exec("ALTER TABLE redirecionamento_tabela_pesos CHANGE peso_max_kg peso_ate_kg DECIMAL(6,3) NOT NULL");
                    } else {
                        // Schema incompatível — dropar e recriar limpo
                        $db->exec("DROP TABLE redirecionamento_tabela_pesos");
                        $db->exec("CREATE TABLE redirecionamento_tabela_pesos (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            peso_ate_kg DECIMAL(6,3) NOT NULL,
                            valor_usd DECIMAL(10,2) NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY uk_peso (peso_ate_kg)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    }
                }
                if ($hasPesoMin) {
                    try { $db->exec("ALTER TABLE redirecionamento_tabela_pesos DROP COLUMN peso_min_kg"); } catch (\Exception $e) {}
                }
                if (!$hasValorUsd) {
                    $db->exec("ALTER TABLE redirecionamento_tabela_pesos ADD COLUMN valor_usd DECIMAL(10,2) NOT NULL DEFAULT 0");
                }
                try { $db->exec("ALTER TABLE redirecionamento_tabela_pesos DROP INDEX uk_peso"); } catch (\Exception $e) {}
                try { $db->exec("ALTER TABLE redirecionamento_tabela_pesos ADD UNIQUE KEY uk_peso (peso_ate_kg)"); } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {
            error_log('[Redirecionamento] migrar tabela_pesos: ' . $e->getMessage());
        }

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_envios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            redirecionador_id INT NOT NULL,
            cliente_id INT DEFAULT NULL,
            id_pedido_cliente VARCHAR(100) DEFAULT NULL,
            destinatario_nome VARCHAR(200) DEFAULT NULL,
            destinatario_cpf VARCHAR(20) DEFAULT NULL,
            destinatario_email VARCHAR(200) DEFAULT NULL,
            destinatario_telefone VARCHAR(30) DEFAULT NULL,
            destinatario_data_nascimento DATE DEFAULT NULL,
            dest_logradouro VARCHAR(255) DEFAULT NULL,
            dest_numero VARCHAR(20) DEFAULT NULL,
            dest_complemento VARCHAR(100) DEFAULT NULL,
            dest_bairro VARCHAR(100) DEFAULT NULL,
            dest_cidade VARCHAR(100) DEFAULT NULL,
            dest_estado VARCHAR(2) DEFAULT NULL,
            dest_cep VARCHAR(10) DEFAULT NULL,
            moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
            valor_frete_usd DECIMAL(10,2) DEFAULT NULL,
            peso_kg DECIMAL(6,3) DEFAULT NULL,
            largura_cm DECIMAL(6,2) DEFAULT NULL,
            altura_cm DECIMAL(6,2) DEFAULT NULL,
            comprimento_cm DECIMAL(6,2) DEFAULT NULL,
            peso_real_kg DECIMAL(6,3) DEFAULT NULL,
            largura_real_cm DECIMAL(6,2) DEFAULT NULL,
            altura_real_cm DECIMAL(6,2) DEFAULT NULL,
            comprimento_real_cm DECIMAL(6,2) DEFAULT NULL,
            valor_cobrado_usd DECIMAL(10,2) DEFAULT NULL,
            valor_correto_usd DECIMAL(10,2) DEFAULT NULL,
            status ENUM('rascunho','aguardando_pagamento','pago','etiqueta_gerada','coletado','entregue','divergencia','cancelado') NOT NULL DEFAULT 'rascunho',
            status_pagamento ENUM('pendente','pago','falhou','reembolsado') NOT NULL DEFAULT 'pendente',
            stripe_payment_intent VARCHAR(255) DEFAULT NULL,
            tracking_code VARCHAR(100) DEFAULT NULL,
            etiqueta_url TEXT DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_red_id (redirecionador_id),
            INDEX idx_status (status),
            INDEX idx_status_pag (status_pagamento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_produtos_envio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            envio_id INT NOT NULL,
            ncm VARCHAR(20) DEFAULT NULL,
            descricao VARCHAR(255) NOT NULL,
            preco_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            peso_kg DECIMAL(6,3) NOT NULL DEFAULT 0.000,
            quantidade INT NOT NULL DEFAULT 1,
            INDEX idx_envio_id (envio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_pagamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            envio_id INT NOT NULL,
            tipo ENUM('envio','diferenca','reembolso') NOT NULL DEFAULT 'envio',
            valor_usd DECIMAL(10,2) NOT NULL,
            stripe_payment_intent VARCHAR(255) DEFAULT NULL,
            stripe_client_secret VARCHAR(500) DEFAULT NULL,
            status ENUM('pendente','pago','falhou','reembolsado') NOT NULL DEFAULT 'pendente',
            comprovante_url TEXT DEFAULT NULL,
            pago_em TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_envio_id (envio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS redirecionamento_coletas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            envio_id INT NOT NULL,
            redirecionador_id INT NOT NULL,
            data_agendada DATE NOT NULL,
            horario TIME NOT NULL,
            status ENUM('agendado','confirmado','coletado','cancelado') NOT NULL DEFAULT 'agendado',
            observacoes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_envio_id (envio_id),
            INDEX idx_data (data_agendada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Migração defensiva: dropar e recriar tabelas com schema incompatível ──
        $schemas = [
            'redirecionamento_coletas' => [
                'colunas_obrigatorias' => ['envio_id', 'redirecionador_id', 'data_agendada', 'horario'],
                'ddl' => "CREATE TABLE redirecionamento_coletas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    envio_id INT NOT NULL,
                    redirecionador_id INT NOT NULL,
                    data_agendada DATE NOT NULL,
                    horario TIME NOT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'agendado',
                    observacoes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_envio_id (envio_id),
                    INDEX idx_data (data_agendada)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            'redirecionamento_envios' => [
                'colunas_obrigatorias' => ['redirecionador_id', 'status_pagamento', 'valor_cobrado_usd'],
                'ddl' => "CREATE TABLE redirecionamento_envios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    redirecionador_id INT NOT NULL,
                    cliente_id INT DEFAULT NULL,
                    id_pedido_cliente VARCHAR(100) DEFAULT NULL,
                    destinatario_nome VARCHAR(200) DEFAULT NULL,
                    destinatario_cpf VARCHAR(20) DEFAULT NULL,
                    destinatario_email VARCHAR(200) DEFAULT NULL,
                    destinatario_telefone VARCHAR(30) DEFAULT NULL,
                    destinatario_data_nascimento DATE DEFAULT NULL,
                    dest_logradouro VARCHAR(255) DEFAULT NULL,
                    dest_numero VARCHAR(20) DEFAULT NULL,
                    dest_complemento VARCHAR(100) DEFAULT NULL,
                    dest_bairro VARCHAR(100) DEFAULT NULL,
                    dest_cidade VARCHAR(100) DEFAULT NULL,
                    dest_estado VARCHAR(2) DEFAULT NULL,
                    dest_cep VARCHAR(10) DEFAULT NULL,
                    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
                    valor_frete_usd DECIMAL(10,2) DEFAULT NULL,
                    peso_kg DECIMAL(6,3) DEFAULT NULL,
                    largura_cm DECIMAL(6,2) DEFAULT NULL,
                    altura_cm DECIMAL(6,2) DEFAULT NULL,
                    comprimento_cm DECIMAL(6,2) DEFAULT NULL,
                    peso_real_kg DECIMAL(6,3) DEFAULT NULL,
                    largura_real_cm DECIMAL(6,2) DEFAULT NULL,
                    altura_real_cm DECIMAL(6,2) DEFAULT NULL,
                    comprimento_real_cm DECIMAL(6,2) DEFAULT NULL,
                    valor_cobrado_usd DECIMAL(10,2) DEFAULT NULL,
                    valor_correto_usd DECIMAL(10,2) DEFAULT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'rascunho',
                    status_pagamento VARCHAR(30) NOT NULL DEFAULT 'pendente',
                    stripe_payment_intent VARCHAR(255) DEFAULT NULL,
                    tracking_code VARCHAR(100) DEFAULT NULL,
                    etiqueta_url TEXT DEFAULT NULL,
                    observacoes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_red_id (redirecionador_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            'redirecionamento_pagamentos' => [
                'colunas_obrigatorias' => ['envio_id', 'tipo', 'valor_usd', 'status'],
                'ddl' => "CREATE TABLE redirecionamento_pagamentos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    envio_id INT NOT NULL,
                    tipo VARCHAR(20) NOT NULL DEFAULT 'envio',
                    valor_usd DECIMAL(10,2) NOT NULL,
                    stripe_payment_intent VARCHAR(255) DEFAULT NULL,
                    stripe_client_secret VARCHAR(500) DEFAULT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'pendente',
                    comprovante_url TEXT DEFAULT NULL,
                    pago_em TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_envio_id (envio_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            'redirecionamento_produtos_envio' => [
                'colunas_obrigatorias' => ['envio_id', 'descricao'],
                'ddl' => "CREATE TABLE redirecionamento_produtos_envio (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    envio_id INT NOT NULL,
                    ncm VARCHAR(20) DEFAULT NULL,
                    descricao VARCHAR(255) NOT NULL,
                    preco_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    peso_kg DECIMAL(6,3) NOT NULL DEFAULT 0.000,
                    quantidade INT NOT NULL DEFAULT 1,
                    INDEX idx_envio_id (envio_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            'redirecionamento_clientes' => [
                'colunas_obrigatorias' => ['redirecionador_id', 'nome'],
                'ddl' => "CREATE TABLE redirecionamento_clientes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    redirecionador_id INT NOT NULL,
                    nome VARCHAR(200) NOT NULL,
                    cpf VARCHAR(20) DEFAULT NULL,
                    email VARCHAR(200) DEFAULT NULL,
                    telefone VARCHAR(30) DEFAULT NULL,
                    data_nascimento DATE DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_red_id (redirecionador_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            'redirecionamento_enderecos' => [
                'colunas_obrigatorias' => ['cliente_id', 'logradouro', 'cidade'],
                'ddl' => "CREATE TABLE redirecionamento_enderecos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cliente_id INT NOT NULL,
                    logradouro VARCHAR(255) NOT NULL,
                    numero VARCHAR(20) DEFAULT NULL,
                    complemento VARCHAR(100) DEFAULT NULL,
                    bairro VARCHAR(100) DEFAULT NULL,
                    cidade VARCHAR(100) NOT NULL,
                    estado VARCHAR(2) NOT NULL,
                    cep VARCHAR(10) NOT NULL,
                    principal TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_cli_id (cliente_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
            'redirecionadores' => [
                'colunas_obrigatorias' => ['nome', 'email', 'status'],
                'ddl' => "CREATE TABLE redirecionadores (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT DEFAULT NULL,
                    nome VARCHAR(200) NOT NULL,
                    email VARCHAR(200) NOT NULL,
                    telefone VARCHAR(30) DEFAULT NULL,
                    suite VARCHAR(50) DEFAULT NULL,
                    conta_bancaria TEXT DEFAULT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'ativo',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ],
        ];

        foreach ($schemas as $tabela => $cfg) {
            $this->garantirSchema($db, $tabela, $cfg['colunas_obrigatorias'], $cfg['ddl']);
        }

        // Garantir colunas adicionais que podem faltar em tabelas já existentes
        $this->migrarColunas($db, 'redirecionadores', [
            'usuario_id' => 'INT DEFAULT NULL',
            'suite'      => 'VARCHAR(50) DEFAULT NULL',
        ]);

        $this->migrarColunas($db, 'redirecionamento_envios', [
            'etiqueta_provedor' => "VARCHAR(30) DEFAULT NULL",
            'wexpress_shipping_id' => "VARCHAR(100) DEFAULT NULL",
            'wexpress_tracking_number' => "VARCHAR(100) DEFAULT NULL",
            'courier_tracking_number' => "VARCHAR(100) DEFAULT NULL",
            'wexpress_status' => "VARCHAR(50) DEFAULT NULL",
            'wexpress_label_url' => "TEXT DEFAULT NULL",
            'etiqueta_request_json' => "LONGTEXT DEFAULT NULL",
            'etiqueta_response_json' => "LONGTEXT DEFAULT NULL",
            'etiqueta_gerada_em' => "DATETIME DEFAULT NULL",
            'etiqueta_gerada_por' => "INT DEFAULT NULL",
        ]);

        // Seed da tabela de pesos se vazia
        try {
            $count = (int) $db->query("SELECT COUNT(*) FROM redirecionamento_tabela_pesos")->fetchColumn();
        } catch (\Exception $e) { $count = 0; }
        if ($count === 0) {
            $pesos = [
                [0.5,10.76],[1.0,15.33],[1.5,19.94],[2.0,24.55],[2.5,29.16],[3.0,33.74],
                [3.5,38.02],[4.0,42.59],[4.5,47.15],[5.0,51.68],[5.5,56.24],[6.0,60.81],
                [6.5,65.37],[7.0,69.93],[7.5,74.49],[8.0,79.05],[8.5,83.62],[9.0,88.18],
                [9.5,92.75],[10.0,97.31],[10.5,101.88],[11.0,106.44],[11.5,111.00],[12.0,115.57],
                [12.5,120.13],[13.0,124.69],[13.5,129.25],[14.0,133.81],[14.5,138.38],[15.0,142.94],
                [15.5,147.51],[16.0,152.07],[16.5,156.63],[17.0,161.20],[17.5,165.76],[18.0,170.33],
                [18.5,174.89],[19.0,179.46],[19.5,184.01],[20.0,188.57],[20.5,193.14],[21.0,197.70],
                [21.5,202.27],[22.0,206.83],[22.5,211.39],[23.0,215.96],[23.5,220.52],[24.0,225.09],
                [24.5,229.65],[25.0,234.21],[25.5,238.78],[26.0,243.33],[26.5,247.90],[27.0,252.46],
                [27.5,257.02],[28.0,261.59],[28.5,266.15],[29.0,270.72],[29.5,275.28],[30.0,279.84],
            ];
            $st = $db->prepare("INSERT IGNORE INTO redirecionamento_tabela_pesos (peso_ate_kg, valor_usd) VALUES (?,?)");
            foreach ($pesos as [$p, $v]) { $st->execute([$p, $v]); }
        }
        } catch (\Exception $e) {
            error_log('[Redirecionamento] migrar() error: ' . $e->getMessage());
        }
    }

    /** Calcula valor USD para um peso baseado na tabela */
    private function calcularValor(float $pesoKg): ?array {
        try {
            $st = $this->pdo()->prepare(
                "SELECT peso_ate_kg, valor_usd FROM redirecionamento_tabela_pesos WHERE peso_ate_kg >= ? ORDER BY peso_ate_kg ASC LIMIT 1"
            );
            $st->execute([$pesoKg]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return ['faixa' => (float)$row['peso_ate_kg'], 'valor_usd' => (float)$row['valor_usd']];
            }
            // Acima do máximo: usa a última faixa
            $last = $this->pdo()->query("SELECT peso_ate_kg, valor_usd FROM redirecionamento_tabela_pesos ORDER BY peso_ate_kg DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            return $last ? ['faixa' => (float)$last['peso_ate_kg'], 'valor_usd' => (float)$last['valor_usd']] : null;
        } catch (\Exception $e) { return null; }
    }

    private function getStripeKeys(): array {
        try {
            $db = $this->pdo();
            $tables = ['configuracoes_sistema','configuracoes','settings','config'];
            foreach ($tables as $t) {
                $st = $db->prepare("SHOW TABLES LIKE ?"); $st->execute([$t]);
                if (!$st->fetchColumn()) continue;
                $cols = $db->query("DESCRIBE $t")->fetchAll(\PDO::FETCH_COLUMN);
                $keyCol = in_array('chave',$cols,true)?'chave':(in_array('key',$cols,true)?'key':'');
                $valCol = in_array('valor',$cols,true)?'valor':(in_array('value',$cols,true)?'value':'');
                if (!$keyCol || !$valCol) continue;

                // Buscar com e sem prefixo de categoria
                $keysToSearch = ['stripe_secret_key','stripe_public_key','stripe_publishable_key','pagamentos_stripe_secret_key','pagamentos_stripe_publishable_key','stripe_api_key'];
                $placeholders = implode(',', array_fill(0, count($keysToSearch), '?'));

                // Formato 1: chave simples (sem categoria)
                $st2 = $db->prepare("SELECT $keyCol, $valCol FROM $t WHERE $keyCol IN ($placeholders)");
                $st2->execute($keysToSearch);
                $rows = $st2->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

                // Formato 2: com coluna categoria
                if (in_array('categoria', $cols, true)) {
                    $st3 = $db->prepare("SELECT $keyCol, $valCol FROM $t WHERE categoria = 'pagamentos' AND $keyCol IN ('stripe_secret_key','stripe_publishable_key','stripe_public_key','stripe_api_key')");
                    $st3->execute();
                    $rowsCat = $st3->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
                    $rows = array_merge($rowsCat, $rows); // prioridade para formato sem prefixo
                }

                if (!empty($rows)) {
                    $secret = $rows['stripe_secret_key'] ?? ($rows['pagamentos_stripe_secret_key'] ?? ($rows['stripe_api_key'] ?? ''));
                    $public = $rows['stripe_public_key'] ?? ($rows['stripe_publishable_key'] ?? ($rows['pagamentos_stripe_publishable_key'] ?? ''));
                    if ($secret !== '' || $public !== '') {
                        return ['secret' => $secret, 'public' => $public];
                    }
                }
            }
        } catch (\Exception $e) {}
        return ['secret'=>'','public'=>''];
    }

    private function getEmailsColetaConfig(\PDO $db): array {
        try {
            $st = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'redirecionamento_emails_coleta' LIMIT 1");
            $st->execute();
            $val = trim((string) ($st->fetchColumn() ?: ''));
            if ($val === '') return [];
            $emails = array_filter(array_map('trim', explode(',', $val)), fn($e) => $e !== '' && strpos($e, '@') !== false);
            return $emails;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getEmailsNotificacao(): array {
        try {
            $db = $this->pdo();
            $tables = ['configuracoes_sistema','configuracoes','settings','config'];
            foreach ($tables as $t) {
                $st = $db->prepare("SHOW TABLES LIKE ?"); $st->execute([$t]);
                if (!$st->fetchColumn()) continue;
                $cols = $db->query("DESCRIBE $t")->fetchAll(\PDO::FETCH_COLUMN);
                $keyCol = in_array('chave',$cols,true)?'chave':(in_array('key',$cols,true)?'key':'');
                $valCol = in_array('valor',$cols,true)?'valor':(in_array('value',$cols,true)?'value':'');
                if (!$keyCol || !$valCol) continue;
                $st2 = $db->prepare("SELECT $keyCol, $valCol FROM $t WHERE $keyCol IN ('redirecionamento_email_fabiana','redirecionamento_email_lucas','email_fabiana','email_lucas')");
                $st2->execute();
                $rows = $st2->fetchAll(\PDO::FETCH_KEY_PAIR);
                if (!empty($rows)) {
                    return [
                        'fabiana' => $rows['redirecionamento_email_fabiana'] ?? ($rows['email_fabiana'] ?? ''),
                        'lucas'   => $rows['redirecionamento_email_lucas']   ?? ($rows['email_lucas']   ?? ''),
                    ];
                }
            }
        } catch (\Exception $e) {}
        return ['fabiana'=>'','lucas'=>''];
    }

    private function enviarEmailNotificacao(string $para, string $assunto, string $corpo): void {
        if (empty($para) || !filter_var($para, FILTER_VALIDATE_EMAIL)) return;
        try {
            $html = $this->montarEmailHtml($assunto, $corpo);
            $emailService = new \App\Services\EmailService();
            $emailService->send($para, $assunto, $html);
        } catch (\Exception $e) {
            error_log('[REDIR_EMAIL] Erro ao enviar para ' . $para . ': ' . $e->getMessage());
        }
    }

    private function montarEmailHtml(string $titulo, string $conteudo): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 20px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)">
    <tr><td style="background:#0b1f3a;padding:24px 32px;text-align:center">
        <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600">🚚 Braziliana Redirecionamento</h1>
    </td></tr>
    <tr><td style="padding:32px">
        <h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;font-weight:600">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h2>
        <div style="color:#4a4a4a;font-size:15px;line-height:1.6">' . $conteudo . '</div>
    </td></tr>
    <tr><td style="padding:0 32px 24px">
        <a href="https://brazilianashop.com.br/admin/redirecionamento/coletas" style="display:inline-block;background:#0b1f3a;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;font-weight:500">Acessar painel</a>
    </td></tr>
    <tr><td style="background:#f8fafc;padding:16px 32px;border-top:1px solid #e5e7eb">
        <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center">Braziliana Shop — Serviço de Redirecionamento de Pacotes</p>
    </td></tr>
</table>
</td></tr>
</table>
</body></html>';
    }

    // ─── DASHBOARD ────────────────────────────────────────────────────────────

    public function dashboard(Request $request) {
        $this->auth();
        $this->migrar();
        $db = $this->pdo();
        $kpis = ['total_envios'=>0,'pendentes_pagamento'=>0,'aguardando_coleta'=>0,'divergencias_peso'=>0,'valores_a_receber'=>0.0,'valores_a_devolver'=>0.0];
        try {
            $kpis['total_envios'] = (int)$db->query("SELECT COUNT(*) FROM redirecionamento_envios")->fetchColumn();
            $kpis['pendentes_pagamento'] = (int)$db->query("SELECT COUNT(*) FROM redirecionamento_envios WHERE status_pagamento='pendente' AND status NOT IN ('rascunho','cancelado')")->fetchColumn();
            $kpis['aguardando_coleta'] = (int)$db->query("SELECT COUNT(*) FROM redirecionamento_envios WHERE status='pago'")->fetchColumn();
            $kpis['divergencias_peso'] = (int)$db->query("SELECT COUNT(*) FROM redirecionamento_envios WHERE status='divergencia'")->fetchColumn();
            $kpis['valores_a_receber'] = (float)($db->query("SELECT COALESCE(SUM(valor_usd),0) FROM redirecionamento_pagamentos WHERE tipo='diferenca' AND status='pendente'")->fetchColumn() ?: 0);
            $kpis['valores_a_devolver'] = (float)($db->query("SELECT COALESCE(SUM(ABS(valor_usd)),0) FROM redirecionamento_pagamentos WHERE tipo='reembolso' AND status='pendente'")->fetchColumn() ?: 0);
        } catch (\Exception $e) {}
        $this->view('admin/redirecionamento/dashboard', ['kpis' => $kpis]);
    }

    // ─── REDIRECIONADORES ─────────────────────────────────────────────────────

    public function redirecionadores(Request $request) {
        $this->adminOnly();
        $this->migrar();
        $db = $this->pdo();
        $busca = trim((string)$request->getParam('busca',''));
        $sql = "SELECT * FROM redirecionadores WHERE 1=1";
        $params = [];
        if ($busca !== '') { $sql .= " AND (nome LIKE ? OR email LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; }
        $sql .= " ORDER BY nome ASC";
        $st = $db->prepare($sql); $st->execute($params);
        $redirecionadores = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $this->view('admin/redirecionamento/redirecionadores', ['redirecionadores'=>$redirecionadores,'busca'=>$busca]);
    }

    public function redirecionadorNovo(Request $request) {
        $this->adminOnly(); $this->migrar();
        $this->view('admin/redirecionamento/redirecionador-form', ['redirecionador'=>null,'modo'=>'novo']);
    }

    public function redirecionadorSalvar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $db = $this->pdo();
        $nome  = trim((string)$request->getParam('nome',''));
        $email = trim((string)$request->getParam('email',''));
        $tel   = trim((string)$request->getParam('telefone',''));
        $suite = trim((string)$request->getParam('suite',''));
        $status= in_array($request->getParam('status','ativo'),['ativo','bloqueado'],'ativo') ? $request->getParam('status') : 'ativo';
        if ($nome===''||$email==='') { $_SESSION['message']='Nome e e-mail são obrigatórios.'; $_SESSION['message_type']='danger'; $this->redirect('/admin/redirecionamento/redirecionadores/novo'); return; }
        if ($suite==='') { $suite = 'BR-'.strtoupper(substr(md5($email.time()),0,6)); }
        $db->prepare("INSERT INTO redirecionadores (nome,email,telefone,suite,status) VALUES (?,?,?,?,?)")->execute([$nome,$email,$tel,$suite,$status]);
        $_SESSION['message']='Redirecionador criado com sucesso.'; $_SESSION['message_type']='success';
        $this->redirect('/admin/redirecionamento/redirecionadores');
    }

    public function redirecionadorEditar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id = (int)$request->getParam('id',0);
        $r = $this->pdo()->prepare("SELECT * FROM redirecionadores WHERE id=?"); $r->execute([$id]);
        $redirecionador = $r->fetch(\PDO::FETCH_ASSOC);
        if (!$redirecionador) { $this->redirect('/admin/redirecionamento/redirecionadores'); return; }
        $this->view('admin/redirecionamento/redirecionador-form', ['redirecionador'=>$redirecionador,'modo'=>'editar']);
    }

    public function redirecionadorAtualizar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id    = (int)$request->getParam('id',0);
        $nome  = trim((string)$request->getParam('nome',''));
        $email = trim((string)$request->getParam('email',''));
        $tel   = trim((string)$request->getParam('telefone',''));
        $suite = trim((string)$request->getParam('suite',''));
        $status= in_array($request->getParam('status','ativo'),['ativo','bloqueado']) ? $request->getParam('status') : 'ativo';
        $this->pdo()->prepare("UPDATE redirecionadores SET nome=?,email=?,telefone=?,suite=?,status=? WHERE id=?")->execute([$nome,$email,$tel,$suite,$status,$id]);
        $_SESSION['message']='Redirecionador atualizado.'; $_SESSION['message_type']='success';
        $this->redirect('/admin/redirecionamento/redirecionadores');
    }

    // ─── CLIENTES ─────────────────────────────────────────────────────────────

    public function clientes(Request $request) {
        $this->auth(); $this->migrar();
        $db = $this->pdo();
        $redirecionadorFixo = $this->getRedirecionadorFixo();
        $params = [];
        $busca = trim((string)$request->getParam('busca',''));
        $sql = "SELECT c.*, r.nome AS redirecionador_nome,
                    (SELECT COUNT(*) FROM redirecionamento_enderecos e WHERE e.cliente_id=c.id) AS enderecos_count
                FROM redirecionamento_clientes c
                LEFT JOIN redirecionadores r ON r.id=c.redirecionador_id
                WHERE 1=1";
        if ($redirecionadorFixo && $redirecionadorFixo['id'] > 0) {
            $sql .= " AND c.redirecionador_id=?";
            $params[] = $redirecionadorFixo['id'];
        }
        if ($busca!=='') { $sql.=" AND (c.nome LIKE ? OR c.cpf LIKE ? OR c.email LIKE ?)"; $params[]="%$busca%"; $params[]="%$busca%"; $params[]="%$busca%"; }
        $sql.=" ORDER BY c.nome ASC";
        $st=$db->prepare($sql); $st->execute($params);
        $clientes=$st->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $reds=$db->query("SELECT id,nome FROM redirecionadores WHERE status='ativo' ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $this->view('admin/redirecionamento/clientes',['clientes'=>$clientes,'busca'=>$busca,'redirecionadores'=>$reds,'redirecionadorFixo'=>$redirecionadorFixo]);
    }

    public function clienteSalvar(Request $request) {
        $this->auth(); $this->migrar();
        $db = $this->pdo();
        $redId = (int)$request->getParam('redirecionador_id',0);
        $nome  = trim((string)$request->getParam('nome',''));
        $cpf   = trim((string)$request->getParam('cpf',''));
        $email = trim((string)$request->getParam('email',''));
        $tel   = trim((string)$request->getParam('telefone',''));
        $nasc  = trim((string)$request->getParam('data_nascimento','')) ?: null;
        if ($nome==='') { $this->json(['ok'=>false,'msg'=>'Nome obrigatório.']); return; }
        $db->prepare("INSERT INTO redirecionamento_clientes (redirecionador_id,nome,cpf,email,telefone,data_nascimento) VALUES (?,?,?,?,?,?)")->execute([$redId,$nome,$cpf,$email,$tel,$nasc]);
        $clienteId = (int)$db->lastInsertId();
        // Endereço
        $logr = trim((string)$request->getParam('logradouro',''));
        $cid  = trim((string)$request->getParam('cidade',''));
        $est  = trim((string)$request->getParam('estado',''));
        $cep  = trim((string)$request->getParam('cep',''));
        if ($logr!==''&&$cid!==''&&$est!=='') {
            $db->prepare("INSERT INTO redirecionamento_enderecos (cliente_id,logradouro,numero,complemento,bairro,cidade,estado,cep,principal) VALUES (?,?,?,?,?,?,?,?,1)")
               ->execute([$clienteId,$logr,$request->getParam('numero',''),$request->getParam('complemento',''),$request->getParam('bairro',''),$cid,$est,$cep]);
        }
        $this->json(['ok'=>true,'id'=>$clienteId,'nome'=>$nome]);
    }

    public function clienteGet(Request $request) {
        $this->auth(); $this->migrar();
        $id = (int)$request->getParam('id',0);
        $st = $this->pdo()->prepare("SELECT c.*, e.logradouro,e.numero,e.complemento,e.bairro,e.cidade,e.estado,e.cep FROM redirecionamento_clientes c LEFT JOIN redirecionamento_enderecos e ON e.cliente_id=c.id AND e.principal=1 WHERE c.id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $this->json($row ?: []);
    }

    public function clienteAtualizar(Request $request) {
        $this->auth(); $this->migrar();
        $id = (int)$request->getParam('id',0);
        if ($id <= 0) { $this->json(['ok'=>false,'msg'=>'ID inválido']); return; }
        $nome = trim((string)$request->getParam('nome',''));
        $cpf = trim((string)$request->getParam('cpf',''));
        $email = trim((string)$request->getParam('email',''));
        $tel = trim((string)$request->getParam('telefone',''));
        $nasc = trim((string)$request->getParam('data_nascimento','')) ?: null;
        if ($nome === '') { $this->json(['ok'=>false,'msg'=>'Nome obrigatório']); return; }
        $this->pdo()->prepare("UPDATE redirecionamento_clientes SET nome=?,cpf=?,email=?,telefone=?,data_nascimento=? WHERE id=?")
            ->execute([$nome,$cpf,$email,$tel,$nasc,$id]);
        $this->json(['ok'=>true]);
    }

    public function clienteExcluir(Request $request) {
        $this->auth(); $this->migrar();
        $id = (int)$request->getParam('id',0);
        if ($id <= 0) { $this->json(['ok'=>false,'msg'=>'ID inválido']); return; }
        $db = $this->pdo();
        // Verificar se tem envios vinculados
        $st = $db->prepare("SELECT COUNT(*) FROM redirecionamento_envios WHERE cliente_id=?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $this->json(['ok'=>false,'msg'=>'Não é possível excluir: cliente tem envios vinculados.']);
            return;
        }
        $db->prepare("DELETE FROM redirecionamento_enderecos WHERE cliente_id=?")->execute([$id]);
        $db->prepare("DELETE FROM redirecionamento_clientes WHERE id=?")->execute([$id]);
        $this->json(['ok'=>true]);
    }

    public function clientesLista(Request $request) {
        $this->auth();
        $redId = (int)$request->getParam('redirecionador_id', 0);

        // Se redirecionador_id=0 e o usuário é redirecionador, tentar resolver
        if (!$redId) {
            $redFixo = $this->getRedirecionadorFixo();
            if ($redFixo && (int)($redFixo['id'] ?? 0) > 0) {
                $redId = (int)$redFixo['id'];
            }
        }

        if (!$redId) { $this->json(['ok'=>false,'clientes'=>[]]); return; }
        $st = $this->pdo()->prepare("SELECT id, nome FROM redirecionamento_clientes WHERE redirecionador_id=? ORDER BY nome ASC");
        $st->execute([$redId]);
        $this->json(['ok'=>true,'clientes'=>$st->fetchAll(\PDO::FETCH_ASSOC)?:[]]);
    }

    // ─── ENVIOS ───────────────────────────────────────────────────────────────

    public function envios(Request $request) {
        $this->auth(); $this->migrar();
        $db = $this->pdo();
        $filtroStatus = trim((string)$request->getParam('status',''));
        $filtroRed    = (int)$request->getParam('redirecionador_id',0);
        $filtroData   = trim((string)$request->getParam('data',''));
        $sql = "SELECT e.*,
                    r.nome AS redirecionador_nome,
                    c.nome AS cliente_nome
                FROM redirecionamento_envios e
                LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id
                LEFT JOIN redirecionamento_clientes c ON c.id=e.cliente_id
                WHERE 1=1";
        $params=[];
        if ($filtroStatus!=='') { $sql.=" AND e.status=?"; $params[]=$filtroStatus; }
        if ($filtroRed>0)        { $sql.=" AND e.redirecionador_id=?"; $params[]=$filtroRed; }
        if ($filtroData!=='')    { $sql.=" AND DATE(e.created_at)=?"; $params[]=$filtroData; }
        $sql.=" ORDER BY e.id DESC";
        $st=$db->prepare($sql); $st->execute($params);
        $envios=$st->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $reds=$db->query("SELECT id,nome FROM redirecionadores ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $this->view('admin/redirecionamento/envios',['envios'=>$envios,'redirecionadores'=>$reds,'filtroStatus'=>$filtroStatus,'filtroRed'=>$filtroRed,'filtroData'=>$filtroData]);
    }

    public function envioNovo(Request $request) {
        $this->auth(); $this->migrar();
        $db = $this->pdo();
        $redirecionadorFixo = $this->getRedirecionadorFixo();
        $reds=$db->query("SELECT id,nome FROM redirecionadores WHERE status='ativo' ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $tabela=$db->query("SELECT peso_ate_kg,valor_usd FROM redirecionamento_tabela_pesos ORDER BY peso_ate_kg ASC")->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $stripeKeys = $this->getStripeKeys();
        $this->view('admin/redirecionamento/envio-novo',['redirecionadores'=>$reds,'tabela'=>$tabela,'stripePublicKey'=>$stripeKeys['public'],'redirecionadorFixo'=>$redirecionadorFixo]);
    }

    public function envioSalvar(Request $request) {
        $this->auth(); $this->migrar();
        $db = $this->pdo();
        $redId   = (int)$request->getParam('redirecionador_id',0);
        $clienteId=(int)$request->getParam('cliente_id',0);
        $idPedCli= trim((string)$request->getParam('id_pedido_cliente',''));
        $pesoKg  = (float)str_replace(',','.',$request->getParam('peso_kg','0'));
        $largura = (float)str_replace(',','.',$request->getParam('largura_cm','0'));
        $altura  = (float)str_replace(',','.',$request->getParam('altura_cm','0'));
        $compr   = (float)str_replace(',','.',$request->getParam('comprimento_cm','0'));
        $valFrete= (float)str_replace(',','.',$request->getParam('valor_frete_usd','0'));

        $calc = $this->calcularValor($pesoKg);
        $valorCobrado = $calc ? $calc['valor_usd'] : $valFrete;

        $data = [
            'redirecionador_id'=>$redId,'cliente_id'=>$clienteId>0?$clienteId:null,
            'id_pedido_cliente'=>$idPedCli,
            'destinatario_nome'=>trim((string)$request->getParam('destinatario_nome','')),
            'destinatario_cpf'=>trim((string)$request->getParam('destinatario_cpf','')),
            'destinatario_email'=>trim((string)$request->getParam('destinatario_email','')),
            'destinatario_telefone'=>trim((string)$request->getParam('destinatario_telefone','')),
            'destinatario_data_nascimento'=>trim((string)$request->getParam('destinatario_data_nascimento',''))?:null,
            'dest_logradouro'=>trim((string)$request->getParam('dest_logradouro','')),
            'dest_numero'=>trim((string)$request->getParam('dest_numero','')),
            'dest_complemento'=>trim((string)$request->getParam('dest_complemento','')),
            'dest_bairro'=>trim((string)$request->getParam('dest_bairro','')),
            'dest_cidade'=>trim((string)$request->getParam('dest_cidade','')),
            'dest_estado'=>trim((string)$request->getParam('dest_estado','')),
            'dest_cep'=>trim((string)$request->getParam('dest_cep','')),
            'moeda'=>'USD','valor_frete_usd'=>$valFrete,
            'peso_kg'=>$pesoKg,'largura_cm'=>$largura,'altura_cm'=>$altura,'comprimento_cm'=>$compr,
            'valor_cobrado_usd'=>$valorCobrado,'status'=>'aguardando_pagamento','status_pagamento'=>'pendente',
        ];
        $cols=implode(',',array_keys($data));
        $phs=':'.implode(',:',$keys=array_keys($data));
        $st=$db->prepare("INSERT INTO redirecionamento_envios ($cols) VALUES ($phs)");
        foreach ($data as $k=>$v) $st->bindValue(":$k",$v);
        $st->execute();
        $envioId=(int)$db->lastInsertId();

        // Produtos
        $produtos = $request->getParam('produtos',[]);
        if (is_array($produtos)) {
            $stP=$db->prepare("INSERT INTO redirecionamento_produtos_envio (envio_id,ncm,descricao,preco_usd,peso_kg,quantidade) VALUES (?,?,?,?,?,?)");
            foreach ($produtos as $p) {
                $stP->execute([$envioId,$p['ncm']??'',$p['descricao']??'',(float)($p['preco_usd']??0),(float)($p['peso_kg']??0),(int)($p['quantidade']??1)]);
            }
        }

        $this->json(['ok'=>true,'envio_id'=>$envioId,'valor_usd'=>$valorCobrado]);
    }

    public function envioDetalhe(Request $request) {
        $this->auth(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $db=$this->pdo();
        $st=$db->prepare("SELECT e.*,r.nome AS redirecionador_nome,c.nome AS cliente_nome FROM redirecionamento_envios e LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id LEFT JOIN redirecionamento_clientes c ON c.id=e.cliente_id WHERE e.id=? LIMIT 1");
        $st->execute([$id]);
        $envio=$st->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) { $this->redirect('/admin/redirecionamento/envios'); return; }
        $stP=$db->prepare("SELECT * FROM redirecionamento_produtos_envio WHERE envio_id=?"); $stP->execute([$id]);
        $produtos=$stP->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $stPag=$db->prepare("SELECT * FROM redirecionamento_pagamentos WHERE envio_id=? ORDER BY id DESC"); $stPag->execute([$id]);
        $pagamentos=$stPag->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $stripeKeys=$this->getStripeKeys();
        $this->view('admin/redirecionamento/envio-detalhe',['envio'=>$envio,'produtos'=>$produtos,'pagamentos'=>$pagamentos,'stripePublicKey'=>$stripeKeys['public']]);
    }

    public function envioAtualizarPeso(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $pesoReal=(float)str_replace(',','.',$request->getParam('peso_real_kg','0'));
        $largReal=(float)str_replace(',','.',$request->getParam('largura_real_cm','0'));
        $altReal =(float)str_replace(',','.',$request->getParam('altura_real_cm','0'));
        $compReal=(float)str_replace(',','.',$request->getParam('comprimento_real_cm','0'));
        $db=$this->pdo();
        $st=$db->prepare("SELECT valor_cobrado_usd FROM redirecionamento_envios WHERE id=? LIMIT 1"); $st->execute([$id]);
        $envio=$st->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) { $this->json(['ok'=>false,'msg'=>'Envio não encontrado']); return; }
        $calc=$this->calcularValor($pesoReal);
        $valorCorreto=$calc?$calc['valor_usd']:(float)$envio['valor_cobrado_usd'];
        $diferenca=round($valorCorreto-(float)$envio['valor_cobrado_usd'],2);
        $novoStatus=abs($diferenca)>0.01?'divergencia':'pago';
        $db->prepare("UPDATE redirecionamento_envios SET peso_real_kg=?,largura_real_cm=?,altura_real_cm=?,comprimento_real_cm=?,valor_correto_usd=?,status=? WHERE id=?")
           ->execute([$pesoReal,$largReal,$altReal,$compReal,$valorCorreto,$novoStatus,$id]);
        if (abs($diferenca)>0.01) {
            $tipo=$diferenca>0?'diferenca':'reembolso';
            $db->prepare("INSERT INTO redirecionamento_pagamentos (envio_id,tipo,valor_usd,status) VALUES (?,?,?,?)")->execute([$id,$tipo,abs($diferenca),'pendente']);
        }
        $this->json(['ok'=>true,'valor_correto'=>$valorCorreto,'diferenca'=>$diferenca,'status'=>$novoStatus]);
    }

    public function envioMarcarColetado(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $this->pdo()->prepare("UPDATE redirecionamento_envios SET status='coletado' WHERE id=?")->execute([$id]);
        $this->json(['ok'=>true]);
    }

    public function envioMarcarPago(Request $request) {
        $this->adminOnly(); $this->migrar();
        $envioId = (int) $request->getParam('envio_id', 0);
        if ($envioId <= 0) { $this->json(['ok'=>false,'msg'=>'Envio inválido']); return; }
        $db = $this->pdo();
        $db->prepare("UPDATE redirecionamento_envios SET status_pagamento='pago', status='pago' WHERE id=?")->execute([$envioId]);
        $db->prepare("INSERT INTO redirecionamento_pagamentos (envio_id, tipo, valor_usd, status, pago_em) SELECT id, 'envio', valor_cobrado_usd, 'pago', NOW() FROM redirecionamento_envios WHERE id=?")
            ->execute([$envioId]);
        $this->json(['ok'=>true]);
    }

    public function envioMarcarEntregue(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $this->pdo()->prepare("UPDATE redirecionamento_envios SET status='entregue' WHERE id=?")->execute([$id]);
        $this->json(['ok'=>true]);
    }

    public function envioSalvarTracking(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $tracking=trim((string)$request->getParam('tracking_code',''));
        $etiqueta='';

        // Upload de etiqueta (arquivo)
        if (!empty($_FILES['etiqueta_file']['tmp_name']) && $_FILES['etiqueta_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/etiquetas/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['etiqueta_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
                $this->json(['ok'=>false,'msg'=>'Formato inválido. Use PDF, JPG ou PNG.']); return;
            }
            $filename = 'etiqueta_' . $id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['etiqueta_file']['tmp_name'], $uploadDir . $filename);
            $etiqueta = '/uploads/etiquetas/' . $filename;
        }

        $set = "tracking_code=?, status='etiqueta_gerada'";
        $params = [$tracking];
        if ($etiqueta !== '') {
            $set = "tracking_code=?, etiqueta_url=?, status='etiqueta_gerada'";
            $params = [$tracking, $etiqueta];
        }
        $params[] = $id;
        $this->pdo()->prepare("UPDATE redirecionamento_envios SET $set WHERE id=?")->execute($params);

        // Notificar por e-mail
        $stE=$this->pdo()->prepare("SELECT e.*,r.nome AS red_nome,r.email AS red_email FROM redirecionamento_envios e LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id WHERE e.id=? LIMIT 1");
        $stE->execute([$id]); $envio=$stE->fetch(\PDO::FETCH_ASSOC);
        if ($envio) {
            $emails=$this->getEmailsNotificacao();
            $assunto="Etiqueta gerada - Pedido #{$id} ({$envio['red_nome']})";
            $corpo="<p>Etiqueta gerada para o pedido #{$id} do redirecionador <b>{$envio['red_nome']}</b>.<br>Código de rastreio: <b>$tracking</b><br>Já pode combinar a coleta.</p>";
            $this->enviarEmailNotificacao($emails['fabiana'],$assunto,$corpo);
            $this->enviarEmailNotificacao($emails['lucas'],$assunto,$corpo);
        }
        $this->json(['ok'=>true]);
    }

    // ─── PAGAMENTOS / STRIPE ──────────────────────────────────────────────────

    public function criarIntentPagamento(Request $request) {
        $this->auth(); $this->migrar();
        $envioId=(int)$request->getParam('envio_id',0);
        $db=$this->pdo();
        $st=$db->prepare("SELECT * FROM redirecionamento_envios WHERE id=? LIMIT 1"); $st->execute([$envioId]);
        $envio=$st->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) { $this->json(['ok'=>false,'msg'=>'Envio não encontrado']); return; }
        $valorUsd=(float)($envio['valor_cobrado_usd']??0);
        if ($valorUsd<=0) { $this->json(['ok'=>false,'msg'=>'Valor inválido']); return; }
        $keys=$this->getStripeKeys();
        if (empty($keys['secret'])) { $this->json(['ok'=>false,'msg'=>'Stripe não configurado']); return; }
        try {
            $ch=curl_init('https://api.stripe.com/v1/payment_intents');
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_POST=>true,
                CURLOPT_USERPWD=>$keys['secret'].':',
                CURLOPT_POSTFIELDS=>http_build_query([
                    'amount'=>(int)round($valorUsd*100),
                    'currency'=>'usd',
                    'metadata[envio_id]'=>$envioId,
                    'description'=>"Redirecionamento envio #$envioId",
                ]),
            ]);
            $resp=curl_exec($ch); curl_close($ch);
            $data=json_decode($resp,true);
            if (!empty($data['client_secret'])) {
                $db->prepare("UPDATE redirecionamento_envios SET stripe_payment_intent=? WHERE id=?")->execute([$data['id'],$envioId]);
                $db->prepare("INSERT INTO redirecionamento_pagamentos (envio_id,tipo,valor_usd,stripe_payment_intent,stripe_client_secret,status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE stripe_client_secret=VALUES(stripe_client_secret)")
                   ->execute([$envioId,'envio',$valorUsd,$data['id'],$data['client_secret'],'pendente']);
                $this->json(['ok'=>true,'client_secret'=>$data['client_secret'],'payment_intent_id'=>$data['id']]);
            } else {
                $this->json(['ok'=>false,'msg'=>$data['error']['message']??'Erro Stripe']);
            }
        } catch (\Exception $e) { $this->json(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    public function confirmarPagamento(Request $request) {
        $this->auth(); $this->migrar();
        $envioId=(int)$request->getParam('envio_id',0);
        $piId=trim((string)$request->getParam('payment_intent_id',''));
        $db=$this->pdo();
        $keys=$this->getStripeKeys();
        $status='pago';
        if (!empty($keys['secret'])&&!empty($piId)) {
            $ch=curl_init("https://api.stripe.com/v1/payment_intents/$piId");
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>$keys['secret'].':']);
            $resp=curl_exec($ch); curl_close($ch);
            $data=json_decode($resp,true);
            if (($data['status']??'')!=='succeeded') $status='pendente';
        }
        if ($status==='pago') {
            $db->prepare("UPDATE redirecionamento_envios SET status_pagamento='pago',status='pago' WHERE id=?")->execute([$envioId]);
            $db->prepare("UPDATE redirecionamento_pagamentos SET status='pago',pago_em=NOW() WHERE envio_id=? AND tipo='envio'")->execute([$envioId]);
            // Notificar admin
            $stE=$db->prepare("SELECT e.*,r.nome AS red_nome FROM redirecionamento_envios e LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id WHERE e.id=? LIMIT 1");
            $stE->execute([$envioId]); $envio=$stE->fetch(\PDO::FETCH_ASSOC);
            if ($envio) {
                $emails=$this->getEmailsNotificacao();
                $assunto="Novo pedido pago - #{$envioId} ({$envio['red_nome']})";
                $corpo="<p>O redirecionador <b>{$envio['red_nome']}</b> realizou o pagamento do pedido #{$envioId}. Já pode buscar a caixa.</p>";
                $this->enviarEmailNotificacao($emails['fabiana'],$assunto,$corpo);
                $this->enviarEmailNotificacao($emails['lucas'],$assunto,$corpo);
            }
        }
        $this->json(['ok'=>$status==='pago','status'=>$status]);
    }

    public function uploadComprovante(Request $request) {
        $this->auth(); $this->migrar();
        $envioId=(int)$request->getParam('envio_id',0);
        $tipo=in_array($request->getParam('tipo','envio'),['envio','diferenca','reembolso'])?$request->getParam('tipo'):'envio';
        if (empty($_FILES['comprovante']['tmp_name'])) { $this->json(['ok'=>false,'msg'=>'Arquivo não enviado']); return; }
        $ext=strtolower(pathinfo($_FILES['comprovante']['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','pdf'])) { $this->json(['ok'=>false,'msg'=>'Formato inválido']); return; }
        $dir=__DIR__.'/../../public/uploads/comprovantes/';
        if (!is_dir($dir)) mkdir($dir,0755,true);
        $fname='comp_'.$envioId.'_'.$tipo.'_'.time().'.'.$ext;
        move_uploaded_file($_FILES['comprovante']['tmp_name'],$dir.$fname);
        $url='/uploads/comprovantes/'.$fname;
        $this->pdo()->prepare("UPDATE redirecionamento_pagamentos SET comprovante_url=? WHERE envio_id=? AND tipo=?")->execute([$url,$envioId,$tipo]);
        $this->json(['ok'=>true,'url'=>$url]);
    }

    // ─── DIVERGÊNCIAS ─────────────────────────────────────────────────────────

    public function divergencias(Request $request) {
        $this->auth(); $this->migrar();
        $db=$this->pdo();
        $st=$db->query("SELECT e.id AS envio_id,e.valor_cobrado_usd,e.valor_correto_usd,
                            (e.valor_correto_usd - e.valor_cobrado_usd) AS diferenca,
                            e.status,r.nome AS redirecionador_nome,
                            p.status AS status_pag, p.comprovante_url, p.id AS pag_id
                        FROM redirecionamento_envios e
                        LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id
                        LEFT JOIN redirecionamento_pagamentos p ON p.envio_id=e.id AND p.tipo IN ('diferenca','reembolso')
                        WHERE e.status='divergencia'
                        ORDER BY e.id DESC");
        $divergencias=$st?$st->fetchAll(\PDO::FETCH_ASSOC):[];
        $this->view('admin/redirecionamento/divergencias',['divergencias'=>$divergencias]);
    }

    public function divergenciaGerarLink(Request $request) {
        $this->adminOnly(); $this->migrar();
        $pagId=(int)$request->getParam('pag_id',0);
        $db=$this->pdo();
        $st=$db->prepare("SELECT * FROM redirecionamento_pagamentos WHERE id=? LIMIT 1"); $st->execute([$pagId]);
        $pag=$st->fetch(\PDO::FETCH_ASSOC);
        if (!$pag) { $this->json(['ok'=>false,'msg'=>'Pagamento não encontrado']); return; }
        $keys=$this->getStripeKeys();
        if (empty($keys['secret'])) { $this->json(['ok'=>false,'msg'=>'Stripe não configurado']); return; }
        try {
            $ch=curl_init('https://api.stripe.com/v1/payment_intents');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$keys['secret'].':',
                CURLOPT_POSTFIELDS=>http_build_query(['amount'=>(int)round((float)$pag['valor_usd']*100),'currency'=>'usd','description'=>"Diferença envio #{$pag['envio_id']}"])]);
            $resp=curl_exec($ch); curl_close($ch);
            $data=json_decode($resp,true);
            if (!empty($data['client_secret'])) {
                $db->prepare("UPDATE redirecionamento_pagamentos SET stripe_payment_intent=?,stripe_client_secret=? WHERE id=?")->execute([$data['id'],$data['client_secret'],$pagId]);
                $this->json(['ok'=>true,'client_secret'=>$data['client_secret']]);
            } else { $this->json(['ok'=>false,'msg'=>$data['error']['message']??'Erro Stripe']); }
        } catch (\Exception $e) { $this->json(['ok'=>false,'msg'=>$e->getMessage()]); }
    }

    /**
     * Redirecionador paga diferença via Stripe Checkout Session
     */
    public function divergenciaPagar(Request $request) {
        $this->auth(); $this->migrar();
        $pagId = (int) $request->getParam('pag_id', 0);
        $db = $this->pdo();
        $st = $db->prepare("SELECT p.*, e.id AS envio_id FROM redirecionamento_pagamentos p LEFT JOIN redirecionamento_envios e ON e.id = p.envio_id WHERE p.id = ? LIMIT 1");
        $st->execute([$pagId]);
        $pag = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$pag) { $this->json(['ok' => false, 'msg' => 'Pagamento não encontrado']); return; }
        if (strtolower($pag['status'] ?? '') === 'pago') { $this->json(['ok' => false, 'msg' => 'Já foi pago']); return; }

        $keys = $this->getStripeKeys();
        if (empty($keys['secret'])) { $this->json(['ok' => false, 'msg' => 'Stripe não configurado']); return; }

        $valorCentavos = (int) round((float) $pag['valor_usd'] * 100);
        $envioId = (int) ($pag['envio_id'] ?? 0);

        try {
            // Criar Stripe Checkout Session
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_USERPWD => $keys['secret'] . ':',
                CURLOPT_POSTFIELDS => http_build_query([
                    'mode' => 'payment',
                    'payment_method_types[0]' => 'card',
                    'line_items[0][price_data][currency]' => 'usd',
                    'line_items[0][price_data][product_data][name]' => "Diferença de peso - Envio #{$envioId}",
                    'line_items[0][price_data][unit_amount]' => $valorCentavos,
                    'line_items[0][quantity]' => 1,
                    'success_url' => "https://brazilianashop.com.br/admin/redirecionamento/divergencias?pago=1&pag_id={$pagId}",
                    'cancel_url' => "https://brazilianashop.com.br/admin/redirecionamento/divergencias",
                    'metadata[pag_id]' => $pagId,
                    'metadata[envio_id]' => $envioId,
                    'metadata[tipo]' => 'redirecionamento_diferenca',
                ]),
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($resp, true);

            if (!empty($data['url'])) {
                $db->prepare("UPDATE redirecionamento_pagamentos SET stripe_payment_intent = ? WHERE id = ?")
                    ->execute([$data['id'] ?? '', $pagId]);
                $this->json(['ok' => true, 'checkout_url' => $data['url']]);
            } else {
                $this->json(['ok' => false, 'msg' => $data['error']['message'] ?? 'Erro ao criar checkout Stripe']);
            }
        } catch (\Exception $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function divergenciaMarcarPago(Request $request) {
        $this->adminOnly(); $this->migrar();
        $pagId=(int)$request->getParam('pag_id',0);
        $db=$this->pdo();
        $db->prepare("UPDATE redirecionamento_pagamentos SET status='pago',pago_em=NOW() WHERE id=?")->execute([$pagId]);
        $st=$db->prepare("SELECT envio_id FROM redirecionamento_pagamentos WHERE id=? LIMIT 1"); $st->execute([$pagId]);
        $row=$st->fetch(\PDO::FETCH_ASSOC);
        if ($row) $db->prepare("UPDATE redirecionamento_envios SET status='pago' WHERE id=? AND status='divergencia'")->execute([$row['envio_id']]);
        $this->json(['ok'=>true]);
    }

    // ─── TABELA DE PESOS ──────────────────────────────────────────────────────

    public function tabelaPesos(Request $request) {
        $this->auth(); $this->migrar();
        $db = $this->pdo();
        $tabela=$db->query("SELECT * FROM redirecionamento_tabela_pesos ORDER BY peso_ate_kg ASC")->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $provedorEtiqueta = 'wexpress';
        $emailsColeta = '';
        try {
            $st = $db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('redirecionamento_provedor_etiqueta','redirecionamento_emails_coleta')");
            $st->execute();
            $cfgs = $st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
            $provedorEtiqueta = trim((string) ($cfgs['redirecionamento_provedor_etiqueta'] ?? 'wexpress'));
            $emailsColeta = trim((string) ($cfgs['redirecionamento_emails_coleta'] ?? ''));
        } catch (\Exception $e) {}
        $this->view('admin/redirecionamento/tabela-pesos',['tabela'=>$tabela,'provedorEtiqueta'=>$provedorEtiqueta,'emailsColeta'=>$emailsColeta]);
    }

    public function tabelaPesosSalvar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $peso=(float)str_replace(',','.',$request->getParam('peso_ate_kg','0'));
        $valor=(float)str_replace(',','.',$request->getParam('valor_usd','0'));
        if ($peso<=0||$valor<=0) { $this->json(['ok'=>false,'msg'=>'Valores inválidos']); return; }
        $this->pdo()->prepare("INSERT INTO redirecionamento_tabela_pesos (peso_ate_kg,valor_usd) VALUES (?,?) ON DUPLICATE KEY UPDATE valor_usd=VALUES(valor_usd)")->execute([$peso,$valor]);
        $this->json(['ok'=>true]);
    }

    public function configuracaoSalvar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $chave = trim((string) $request->getParam('chave', ''));
        $valor = trim((string) $request->getParam('valor', ''));
        $chavesPermitidas = ['redirecionamento_provedor_etiqueta', 'redirecionamento_emails_coleta'];
        if (!in_array($chave, $chavesPermitidas, true)) { $this->json(['ok'=>false,'msg'=>'Chave não permitida']); return; }
        $db = $this->pdo();
        $db->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
            ->execute([$chave, $valor]);
        $this->json(['ok'=>true]);
    }

    public function tabelaPesosExcluir(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $this->pdo()->prepare("DELETE FROM redirecionamento_tabela_pesos WHERE id=?")->execute([$id]);
        $this->json(['ok'=>true]);
    }

    public function calcularSimulador(Request $request) {
        $this->auth(); $this->migrar();
        $peso=(float)str_replace(',','.',$request->getParam('peso','0'));
        $calc=$this->calcularValor($peso);
        $this->json($calc?['ok'=>true,'faixa'=>$calc['faixa'],'valor_usd'=>$calc['valor_usd']]:['ok'=>false,'msg'=>'Fora da tabela']);
    }

    // ─── PAGAMENTOS (listagem) ────────────────────────────────────────────────

    public function pagamentos(Request $request) {
        $this->auth(); $this->migrar();
        $db=$this->pdo();
        $st=$db->query("SELECT p.*,r.nome AS redirecionador_nome FROM redirecionamento_pagamentos p LEFT JOIN redirecionamento_envios e ON e.id=p.envio_id LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id ORDER BY p.id DESC");
        $pagamentos=$st?$st->fetchAll(\PDO::FETCH_ASSOC):[];
        $this->view('admin/redirecionamento/pagamentos',['pagamentos'=>$pagamentos]);
    }

    // ─── COMPROVANTES ─────────────────────────────────────────────────────────

    public function comprovantes(Request $request) {
        $this->auth(); $this->migrar();
        $db=$this->pdo();
        $st=$db->query("SELECT p.*,r.nome AS redirecionador_nome FROM redirecionamento_pagamentos p LEFT JOIN redirecionamento_envios e ON e.id=p.envio_id LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id WHERE p.comprovante_url IS NOT NULL ORDER BY p.id DESC");
        $comprovantes=$st?$st->fetchAll(\PDO::FETCH_ASSOC):[];
        $this->view('admin/redirecionamento/comprovantes',['comprovantes'=>$comprovantes]);
    }

    // ─── COLETAS ──────────────────────────────────────────────────────────────

    public function coletas(Request $request) {
        $this->auth(); $this->migrar();
        $db=$this->pdo();
        $perfil = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
        $st=$db->query("SELECT c.*,r.nome AS redirecionador_nome,e.id_pedido_cliente FROM redirecionamento_coletas c LEFT JOIN redirecionadores r ON r.id=c.redirecionador_id LEFT JOIN redirecionamento_envios e ON e.id=c.envio_id ORDER BY c.data_agendada ASC,c.horario ASC");
        $coletas=$st?$st->fetchAll(\PDO::FETCH_ASSOC):[];

        // Envios disponíveis para agendar (sem coleta pendente/confirmada)
        $sqlEnvios = "SELECT e.id, e.id_pedido_cliente, r.nome AS redirecionador_nome
            FROM redirecionamento_envios e
            LEFT JOIN redirecionadores r ON r.id=e.redirecionador_id
            WHERE e.id NOT IN (
                SELECT envio_id FROM redirecionamento_coletas WHERE status IN ('agendado','confirmado')
            )";
        $paramsEnvios = [];
        if ($perfil === 'redirecionador') {
            $uid = (int)($_SESSION['usuario_id'] ?? 0);
            $stR = $db->prepare("SELECT id FROM redirecionadores WHERE usuario_id=? LIMIT 1");
            $stR->execute([$uid]);
            $redId = (int)($stR->fetchColumn() ?: 0);
            if ($redId) { $sqlEnvios .= " AND e.redirecionador_id=?"; $paramsEnvios[] = $redId; }
        }
        $sqlEnvios .= " ORDER BY e.id DESC LIMIT 200";
        $stE = $db->prepare($sqlEnvios); $stE->execute($paramsEnvios);
        $enviosDisponiveis = $stE->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->view('admin/redirecionamento/coletas',['coletas'=>$coletas,'enviosDisponiveis'=>$enviosDisponiveis]);
    }

    public function coletaAgendar(Request $request) {
        $this->auth(); $this->migrar();
        $envioId=(int)$request->getParam('envio_id',0);
        $data=trim((string)$request->getParam('data_agendada',''));
        $hora=trim((string)$request->getParam('horario',''));
        $obs =trim((string)$request->getParam('observacoes',''));
        if (!$envioId||!$data||!$hora) { $this->json(['ok'=>false,'msg'=>'Dados incompletos']); return; }
        $db=$this->pdo();
        $stE=$db->prepare("SELECT redirecionador_id FROM redirecionamento_envios WHERE id=? LIMIT 1"); $stE->execute([$envioId]);
        $envio=$stE->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) { $this->json(['ok'=>false,'msg'=>'Envio não encontrado']); return; }
        $db->prepare("INSERT INTO redirecionamento_coletas (envio_id,redirecionador_id,data_agendada,horario,observacoes) VALUES (?,?,?,?,?)")->execute([$envioId,$envio['redirecionador_id'],$data,$hora,$obs]);

        // Notificar emails configurados (admin)
        $stR=$db->prepare("SELECT r.nome,r.email FROM redirecionadores r WHERE r.id=? LIMIT 1"); $stR->execute([$envio['redirecionador_id']]);
        $red=$stR->fetch(\PDO::FETCH_ASSOC);
        $assunto="📅 Nova coleta agendada - Envio #{$envioId}";
        $corpo='<table style="width:100%;border-collapse:collapse;margin:8px 0">
            <tr><td style="padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px">
                <strong style="color:#166534">✓ Coleta agendada com sucesso</strong>
            </td></tr>
        </table>
        <table style="width:100%;border-collapse:collapse;margin:16px 0">
            <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Redirecionador</td><td style="padding:6px 0;font-weight:600">'.htmlspecialchars($red['nome']??'').'</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Envio</td><td style="padding:6px 0;font-weight:600">#'.$envioId.'</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Data</td><td style="padding:6px 0;font-weight:600">'.date('d/m/Y', strtotime($data)).'</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Horário</td><td style="padding:6px 0;font-weight:600">'.$hora.'</td></tr>
        </table>
        <p style="color:#6b7280;font-size:13px;margin-top:16px">Acesse o painel para confirmar ou reagendar.</p>';

        $emailsConfig = $this->getEmailsColetaConfig($db);
        foreach ($emailsConfig as $email) {
            $this->enviarEmailNotificacao($email, $assunto, $corpo);
        }
        // Fallback: notificar emails padrão também
        $emails=$this->getEmailsNotificacao();
        $this->enviarEmailNotificacao($emails['fabiana'],$assunto,$corpo);

        $this->json(['ok'=>true]);
    }

    public function coletaConfirmar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $this->pdo()->prepare("UPDATE redirecionamento_coletas SET status='confirmado' WHERE id=?")->execute([$id]);
        $this->json(['ok'=>true]);
    }

    public function coletaMarcarColetado(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $db=$this->pdo();
        $db->prepare("UPDATE redirecionamento_coletas SET status='coletado' WHERE id=?")->execute([$id]);
        $st=$db->prepare("SELECT envio_id FROM redirecionamento_coletas WHERE id=? LIMIT 1"); $st->execute([$id]);
        $row=$st->fetch(\PDO::FETCH_ASSOC);
        if ($row) $db->prepare("UPDATE redirecionamento_envios SET status='coletado' WHERE id=?")->execute([$row['envio_id']]);
        $this->json(['ok'=>true]);
    }

    public function coletaCancelar(Request $request) {
        $this->auth(); $this->migrar();
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) { $this->json(['ok'=>false,'msg'=>'ID inválido']); return; }
        $db = $this->pdo();

        // Verificar se a coleta pertence ao redirecionador logado (segurança)
        $redFixo = $this->getRedirecionadorFixo();
        if ($redFixo && (int)($redFixo['id'] ?? 0) > 0) {
            $st = $db->prepare("SELECT id FROM redirecionamento_coletas WHERE id = ? AND redirecionador_id = ? AND status = 'agendado' LIMIT 1");
            $st->execute([$id, (int)$redFixo['id']]);
            if (!$st->fetchColumn()) { $this->json(['ok'=>false,'msg'=>'Coleta não encontrada ou não pode ser cancelada']); return; }
        }

        $db->prepare("UPDATE redirecionamento_coletas SET status = 'cancelado' WHERE id = ? AND status = 'agendado'")->execute([$id]);

        // Notificar admin
        try {
            $st = $db->prepare("SELECT c.envio_id, r.nome AS red_nome FROM redirecionamento_coletas c LEFT JOIN redirecionadores r ON r.id = c.redirecionador_id WHERE c.id = ? LIMIT 1");
            $st->execute([$id]);
            $coleta = $st->fetch(\PDO::FETCH_ASSOC);
            if ($coleta) {
                $emailsConfig = $this->getEmailsColetaConfig($db);
                $assunto = "❌ Coleta cancelada - Envio #{$coleta['envio_id']}";
                $corpo = '<table style="width:100%;border-collapse:collapse;margin:8px 0">
                    <tr><td style="padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px">
                        <strong style="color:#991b1b">✗ Coleta cancelada pelo redirecionador</strong>
                    </td></tr>
                </table>
                <table style="width:100%;border-collapse:collapse;margin:16px 0">
                    <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Redirecionador</td><td style="padding:6px 0;font-weight:600">' . htmlspecialchars($coleta['red_nome'] ?? '') . '</td></tr>
                    <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Envio</td><td style="padding:6px 0;font-weight:600">#' . $coleta['envio_id'] . '</td></tr>
                </table>
                <p style="color:#6b7280;font-size:13px;margin-top:16px">O redirecionador cancelou esta coleta. Ele pode reagendar uma nova data.</p>';
                foreach ($emailsConfig as $email) {
                    $this->enviarEmailNotificacao($email, $assunto, $corpo);
                }
                $emails = $this->getEmailsNotificacao();
                $this->enviarEmailNotificacao($emails['fabiana'], $assunto, $corpo);
            }
        } catch (\Exception $e) {}

        $this->json(['ok'=>true]);
    }

    public function coletaReagendar(Request $request) {
        $this->adminOnly(); $this->migrar();
        $id=(int)$request->getParam('id',0);
        $data=trim((string)$request->getParam('data_agendada',''));
        $hora=trim((string)$request->getParam('horario',''));
        if (!$id || !$data || !$hora) { $this->json(['ok'=>false,'msg'=>'Dados incompletos']); return; }
        $db = $this->pdo();
        $db->prepare("UPDATE redirecionamento_coletas SET data_agendada=?,horario=?,status='agendado' WHERE id=?")->execute([$data,$hora,$id]);

        // Notificar redirecionador sobre reagendamento
        try {
            $st = $db->prepare("SELECT c.envio_id, c.redirecionador_id, r.nome AS red_nome, r.email AS red_email
                FROM redirecionamento_coletas c
                LEFT JOIN redirecionadores r ON r.id = c.redirecionador_id
                WHERE c.id = ? LIMIT 1");
            $st->execute([$id]);
            $coleta = $st->fetch(\PDO::FETCH_ASSOC);
            if ($coleta && !empty($coleta['red_email'])) {
                $assunto = "🔄 Coleta reagendada - Envio #{$coleta['envio_id']}";
                $corpo = '<table style="width:100%;border-collapse:collapse;margin:8px 0">
                    <tr><td style="padding:8px 12px;background:#fef3c7;border:1px solid #fde68a;border-radius:6px">
                        <strong style="color:#92400e">⚠️ Sua coleta foi reagendada</strong>
                    </td></tr>
                </table>
                <table style="width:100%;border-collapse:collapse;margin:16px 0">
                    <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Envio</td><td style="padding:6px 0;font-weight:600">#' . $coleta['envio_id'] . '</td></tr>
                    <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Nova data</td><td style="padding:6px 0;font-weight:600">' . date('d/m/Y', strtotime($data)) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#6b7280;font-size:13px">Novo horário</td><td style="padding:6px 0;font-weight:600">' . $hora . '</td></tr>
                </table>
                <p style="color:#6b7280;font-size:13px;margin-top:16px">Tenha o pacote pronto na nova data e horário.</p>';
                $this->enviarEmailNotificacao($coleta['red_email'], $assunto, $corpo);
            }
        } catch (\Exception $e) {
            error_log('[REDIR] Erro email reagendar: ' . $e->getMessage());
        }

        $this->json(['ok'=>true]);
    }

    // ─── ETIQUETAS ────────────────────────────────────────────────────────────

    /**
     * Página de ajuda/tutorial para redirecionadores
     */
    public function ajuda(Request $request) {
        $this->auth();
        $this->view('admin/redirecionamento/ajuda');
    }

    /**
     * Gera etiqueta para um envio (W Express ou Correios, conforme config)
     */
    public function gerarEtiqueta(Request $request) {
        $this->auth(); $this->migrar();
        $envioId = (int) $request->getParam('envio_id', 0);
        if ($envioId <= 0) { $this->json(['ok' => false, 'msg' => 'Envio inválido']); return; }

        $db = $this->pdo();

        // Buscar envio
        $st = $db->prepare("SELECT e.*, c.nome AS cliente_nome, c.cpf AS cliente_cpf, c.email AS cliente_email, c.telefone AS cliente_telefone
            FROM redirecionamento_envios e
            LEFT JOIN redirecionamento_clientes c ON c.id = e.cliente_id
            WHERE e.id = ? LIMIT 1");
        $st->execute([$envioId]);
        $envio = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) { $this->json(['ok' => false, 'msg' => 'Envio não encontrado']); return; }

        // Verificar se pagamento foi feito
        if (strtolower(trim($envio['status_pagamento'] ?? '')) !== 'pago') {
            $this->json(['ok' => false, 'msg' => 'Pagamento ainda não confirmado. Pague antes de gerar a etiqueta.']); return;
        }

        // Verificar se já tem etiqueta — permitir regerar
        $reGerar = false;
        if (!empty($envio['wexpress_shipping_id']) || !empty($envio['tracking_code'])) {
            $reGerar = true;
        }

        // Buscar produtos do envio
        $stP = $db->prepare("SELECT * FROM redirecionamento_produtos_envio WHERE envio_id = ?");
        $stP->execute([$envioId]);
        $produtos = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Determinar provedor
        $provedor = 'wexpress';
        try {
            $stCfg = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'redirecionamento_provedor_etiqueta' LIMIT 1");
            $stCfg->execute();
            $cfgVal = strtolower(trim((string) ($stCfg->fetchColumn() ?: 'wexpress')));
            if (in_array($cfgVal, ['wexpress', 'correios'], true)) $provedor = $cfgVal;
        } catch (\Exception $e) {}

        if ($provedor === 'wexpress') {
            $result = $this->gerarEtiquetaWExpress($db, $envio, $produtos, $envioId);
        } else {
            $result = $this->gerarEtiquetaCorreios($db, $envio, $produtos, $envioId);
        }

        $this->json($result);
    }

    private function gerarEtiquetaWExpress(\PDO $db, array $envio, array $produtos, int $envioId): array {
        $svc = new \App\Services\WExpressService();
        $sender = $svc->getSender();
        if (!is_array($sender) || empty($sender)) {
            return ['ok' => false, 'msg' => 'W-Express: Sender não configurado. Peça ao admin para configurar em Configurações > Entrega.'];
        }

        // Montar dados do destinatário
        $nome = trim((string) ($envio['destinatario_nome'] ?? ''));
        $partes = preg_split('/\s+/', $nome) ?: [];
        $firstName = $partes[0] ?? $nome;
        $lastName = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';

        $doc = trim((string) ($envio['destinatario_cpf'] ?? ($envio['cliente_cpf'] ?? '')));
        $docDigits = preg_replace('/\D+/', '', $doc);
        $taxType = strlen($docDigits) > 11 ? 'CNPJ' : 'CPF';
        $recipientType = ($taxType === 'CPF') ? 'individual' : 'business';

        // Montar itens
        $items = [];
        $declaredValue = 0.0;
        foreach ($produtos as $p) {
            $qtd = (int) ($p['quantidade'] ?? 1);
            if ($qtd <= 0) $qtd = 1;
            $unitValue = (float) ($p['preco_usd'] ?? 0);
            if ($unitValue <= 0) $unitValue = 1.0;

            $ncm = preg_replace('/\D+/', '', (string) ($p['ncm'] ?? ''));
            if ($ncm === '') {
                return ['ok' => false, 'msg' => 'Produto "' . ($p['descricao'] ?? '') . '" não tem NCM cadastrado. Adicione o NCM antes de gerar a etiqueta.'];
            }

            $items[] = [
                'description' => (string) ($p['descricao'] ?? 'item'),
                'quantity' => $qtd,
                'unit_value' => round($unitValue, 2),
                'tariff_code' => (int) $ncm,
            ];
            $declaredValue += ($unitValue * $qtd);
        }

        if (empty($items)) {
            return ['ok' => false, 'msg' => 'Nenhum produto no envio. Adicione produtos antes de gerar a etiqueta.'];
        }

        $pesoKg = (float) ($envio['peso_kg'] ?? 0);
        if ($pesoKg <= 0) $pesoKg = 1.0;

        $packages = [[
            'weight' => round($pesoKg * 1000, 2), // gramas
            'width'  => (float) ($envio['largura_cm'] ?? 10),
            'length' => (float) ($envio['comprimento_cm'] ?? 15),
            'height' => (float) ($envio['altura_cm'] ?? 10),
        ]];

        $cep = preg_replace('/\D+/', '', (string) ($envio['dest_cep'] ?? ''));
        $addr1 = trim((string) ($envio['dest_logradouro'] ?? ''));
        $numero = trim((string) ($envio['dest_numero'] ?? ''));
        $compl = trim((string) ($envio['dest_complemento'] ?? ''));
        $bairro = trim((string) ($envio['dest_bairro'] ?? ''));
        $cidade = trim((string) ($envio['dest_cidade'] ?? ''));
        $estado = trim((string) ($envio['dest_estado'] ?? ''));

        $addr2Parts = [];
        if ($compl !== '') $addr2Parts[] = $compl;
        if ($bairro !== '') $addr2Parts[] = $bairro;

        $externalId = 'REDIR-' . $envioId . '-' . date('YmdHis');
        $freteDeclarado = (float) ($envio['valor_frete_usd'] ?? 0);
        if ($freteDeclarado <= 0) {
            $freteDeclarado = round(max(0.01, $pesoKg * 1.80), 2);
        }

        $payload = [
            'shipment_purpose' => 'personal',
            'external_shipping_id' => $externalId,
            'external_shipping_reference' => 'redirecionamento-' . $envioId,
            'service_code' => $svc->getServiceCode(),
            'incoterms' => 'DDU',
            'dimensions_unit' => 'cm',
            'weight_unit' => 'g',
            'currency' => 'USD',
            'declared_value' => round($declaredValue, 2),
            'freight_value' => $freteDeclarado,
            'insurance_value' => 0,
            'invoice_number' => (string) $envioId,
            'packages' => $packages,
            'sender' => $sender,
            'recipient' => [
                'type' => $recipientType,
                'business_name' => $recipientType === 'business' ? $nome : ' ',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'tax_id_type' => $taxType,
                'tax_id' => $docDigits,
                'email' => trim((string) ($envio['destinatario_email'] ?? ($envio['cliente_email'] ?? ''))),
                'phone' => trim((string) ($envio['destinatario_telefone'] ?? ($envio['cliente_telefone'] ?? ''))),
                'address' => [
                    'address_number' => $numero,
                    'address_line_1' => $addr1,
                    'address_line_2' => implode(', ', $addr2Parts),
                    'postal_code' => $cep,
                    'city' => $cidade,
                    'state' => $estado,
                    'country' => 'BR',
                ],
            ],
            'items' => $items,
        ];

        try {
            $resp = $svc->createShipping($payload);
            $httpCode = $svc->getLastHttpCode();
        } catch (\Exception $e) {
            $db->prepare("UPDATE redirecionamento_envios SET etiqueta_provedor='wexpress', etiqueta_request_json=?, etiqueta_response_json=? WHERE id=?")
                ->execute([json_encode($payload), json_encode(['error' => $e->getMessage()]), $envioId]);
            return ['ok' => false, 'msg' => 'Erro W-Express: ' . $e->getMessage()];
        }

        $wxStatus = is_array($resp) ? (string) ($resp['shipping_status'] ?? '') : '';
        $wxShipId = is_array($resp) ? (string) ($resp['shipping_id'] ?? '') : '';
        $wxTrack = is_array($resp) ? (string) ($resp['wexpress_tracking_number'] ?? '') : '';
        $wxCourier = is_array($resp) ? (string) ($resp['courier_tracking_number'] ?? '') : '';
        $labelUrl = $wxShipId !== '' ? 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId) : '';

        $trackingFinal = $wxCourier !== '' ? $wxCourier : $wxTrack;
        $etiquetaGerada = ($wxStatus === 'LABEL_CREATED');

        $db->prepare("UPDATE redirecionamento_envios SET
            etiqueta_provedor = 'wexpress',
            wexpress_shipping_id = ?,
            wexpress_tracking_number = ?,
            courier_tracking_number = ?,
            wexpress_status = ?,
            wexpress_label_url = ?,
            tracking_code = ?,
            etiqueta_url = ?,
            etiqueta_request_json = ?,
            etiqueta_response_json = ?,
            etiqueta_gerada_em = NOW(),
            etiqueta_gerada_por = ?,
            status = ?
            WHERE id = ?")
            ->execute([
                $wxShipId ?: null,
                $wxTrack ?: null,
                $wxCourier ?: null,
                $wxStatus ?: null,
                $labelUrl ?: null,
                $trackingFinal ?: null,
                $labelUrl ?: null,
                json_encode($payload),
                json_encode($resp),
                (int) ($_SESSION['usuario_id'] ?? 0),
                $etiquetaGerada ? 'etiqueta_gerada' : ($envio['status'] ?? 'pago'),
                $envioId,
            ]);

        if (!$etiquetaGerada) {
            return ['ok' => false, 'msg' => 'W-Express retornou status: ' . ($wxStatus ?: 'desconhecido') . '. Verifique os dados e tente novamente.'];
        }

        return [
            'ok' => true,
            'tracking' => $trackingFinal,
            'label_url' => $labelUrl,
            'shipping_id' => $wxShipId,
            'msg' => 'Etiqueta gerada com sucesso!',
        ];
    }

    private function gerarEtiquetaCorreios(\PDO $db, array $envio, array $produtos, int $envioId): array {
        // Buscar configuração dos Correios
        try {
            $ambiente = 'homologacao';
            $token = '';
            try {
                $stCfg = $db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('correios_prepostagem_token','sigep_ambiente') LIMIT 10");
                $stCfg->execute();
                $cfgs = $stCfg->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
                $token = (string) ($cfgs['correios_prepostagem_token'] ?? '');
                $ambiente = (string) ($cfgs['sigep_ambiente'] ?? 'homologacao');
            } catch (\Exception $e) {}

            if ($token === '') {
                return ['ok' => false, 'msg' => 'Correios Pré-Postagem não configurado (token ausente).'];
            }

            $baseUrl = ($ambiente === 'producao' || $ambiente === 'production')
                ? 'https://api.correios.com.br/prepostagem'
                : 'https://apihom.correios.com.br/prepostagem';

            $svc = new \App\Services\CorreiosPrepostagemService($baseUrl, $token);
        } catch (\Exception $e) {
            return ['ok' => false, 'msg' => 'Correios não configurado: ' . $e->getMessage()];
        }

        // Montar dados para Correios (simplificado — reutiliza a mesma lógica do admin)
        $nome = trim((string) ($envio['destinatario_nome'] ?? ''));
        $cep = preg_replace('/\D+/', '', (string) ($envio['dest_cep'] ?? ''));
        $endereco = trim((string) ($envio['dest_logradouro'] ?? ''));
        $numero = trim((string) ($envio['dest_numero'] ?? ''));
        $complemento = trim((string) ($envio['dest_complemento'] ?? ''));
        $bairro = trim((string) ($envio['dest_bairro'] ?? ''));
        $cidade = trim((string) ($envio['dest_cidade'] ?? ''));
        $estado = trim((string) ($envio['dest_estado'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string) ($envio['destinatario_cpf'] ?? ($envio['cliente_cpf'] ?? '')));

        $pesoGramas = (int) (((float) ($envio['peso_kg'] ?? 1)) * 1000);
        if ($pesoGramas <= 0) $pesoGramas = 1000;

        try {
            $result = $svc->criarPrePostagem([
                'destinatario' => [
                    'nome' => $nome,
                    'cpf' => $cpf,
                    'endereco' => $endereco,
                    'numero' => $numero,
                    'complemento' => $complemento,
                    'bairro' => $bairro,
                    'cidade' => $cidade,
                    'uf' => $estado,
                    'cep' => $cep,
                ],
                'peso' => $pesoGramas,
                'largura' => (int) (((float) ($envio['largura_cm'] ?? 10)) * 10), // mm
                'altura' => (int) (((float) ($envio['altura_cm'] ?? 10)) * 10),
                'comprimento' => (int) (((float) ($envio['comprimento_cm'] ?? 15)) * 10),
            ]);
        } catch (\Exception $e) {
            $db->prepare("UPDATE redirecionamento_envios SET etiqueta_provedor='correios', etiqueta_response_json=? WHERE id=?")
                ->execute([json_encode(['error' => $e->getMessage()]), $envioId]);
            return ['ok' => false, 'msg' => 'Erro Correios: ' . $e->getMessage()];
        }

        $tracking = (string) ($result['codigo_rastreio'] ?? ($result['tracking'] ?? ''));
        $labelUrl = (string) ($result['label_url'] ?? ($result['etiqueta_url'] ?? ''));

        if ($tracking === '') {
            return ['ok' => false, 'msg' => 'Correios não retornou código de rastreio.'];
        }

        $db->prepare("UPDATE redirecionamento_envios SET
            etiqueta_provedor = 'correios',
            tracking_code = ?,
            etiqueta_url = ?,
            etiqueta_request_json = ?,
            etiqueta_response_json = ?,
            etiqueta_gerada_em = NOW(),
            etiqueta_gerada_por = ?,
            status = 'etiqueta_gerada'
            WHERE id = ?")
            ->execute([
                $tracking,
                $labelUrl ?: null,
                json_encode($result['request'] ?? []),
                json_encode($result),
                (int) ($_SESSION['usuario_id'] ?? 0),
                $envioId,
            ]);

        return [
            'ok' => true,
            'tracking' => $tracking,
            'label_url' => $labelUrl,
            'msg' => 'Etiqueta gerada com sucesso!',
        ];
    }

    /**
     * Baixar/visualizar etiqueta de um envio
     */
    public function baixarEtiqueta(Request $request) {
        $this->auth(); $this->migrar();
        $envioId = (int) $request->getParam('envio_id', 0);
        $db = $this->pdo();
        $st = $db->prepare("SELECT etiqueta_provedor, wexpress_shipping_id, wexpress_label_url, etiqueta_url, tracking_code FROM redirecionamento_envios WHERE id = ? LIMIT 1");
        $st->execute([$envioId]);
        $envio = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) { $this->json(['ok' => false, 'msg' => 'Envio não encontrado']); return; }

        $url = trim((string) ($envio['wexpress_label_url'] ?? ($envio['etiqueta_url'] ?? '')));
        if ($url === '') {
            $this->json(['ok' => false, 'msg' => 'Etiqueta não disponível. Gere a etiqueta primeiro.']); return;
        }

        $this->json(['ok' => true, 'url' => $url, 'provedor' => $envio['etiqueta_provedor'] ?? '']);
    }
}
