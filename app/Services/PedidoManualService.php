<?php
namespace App\Services;

class PedidoManualService {
    private \PDO $db;

    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Config\Database::getConnection();
    }

    private function garantirCarteiraUsuario(int $usuarioId): void {
        if ($usuarioId <= 0) {
            return;
        }

        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `carteiras` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `saldo_usd` decimal(10,2) DEFAULT 0.00,
                    `saldo_brl` decimal(10,2) DEFAULT 0.00,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_usuario_id` (`usuario_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
        }

        try {
            $stmt = $this->db->prepare('INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)');
            $stmt->execute([(int) $usuarioId]);
        } catch (\Exception $e) {
        }
    }

    private function garantirTabelaTransacoesCarteira(): void {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `transacoes_carteira` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `tipo` enum('credito','debito','conversao') NOT NULL,
                    `valor_usd` decimal(10,2) DEFAULT 0.00,
                    `valor_brl` decimal(10,2) DEFAULT 0.00,
                    `taxa_conversao` decimal(10,6) DEFAULT 1.000000,
                    `descricao` text,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_usuario_id` (`usuario_id`),
                    KEY `idx_tipo` (`tipo`),
                    KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
        }
    }

    private function debitarCarteiraParaPedidoManual(int $usuarioId, int $pedidoId, float $valor, string $moeda): void {
        $usuarioId = (int) $usuarioId;
        $pedidoId = (int) $pedidoId;
        $valor = (float) $valor;
        $moeda = strtoupper(trim((string) $moeda));

        if ($usuarioId <= 0) {
            throw new \Exception('Usuário inválido para pagamento via carteira');
        }
        if ($pedidoId <= 0) {
            throw new \Exception('Pedido inválido para pagamento via carteira');
        }
        if ($valor <= 0) {
            throw new \Exception('Valor inválido para pagamento via carteira');
        }
        if (!in_array($moeda, ['BRL', 'USD'], true)) {
            throw new \Exception('Moeda inválida para carteira');
        }

        $this->garantirCarteiraUsuario($usuarioId);
        $this->garantirTabelaTransacoesCarteira();

        $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
        $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';

        $stmt = $this->db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? FOR UPDATE');
        $stmt->execute([$usuarioId]);
        $carteira = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $saldoAtual = (float) ($carteira[$saldoCol] ?? 0);
        if ($saldoAtual + 0.00001 < $valor) {
            throw new \Exception('Saldo insuficiente na carteira');
        }

        $stmtUpd = $this->db->prepare('UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' - :valor, updated_at = NOW() WHERE usuario_id = :uid');
        $stmtUpd->execute([':valor' => $valor, ':uid' => $usuarioId]);

        try {
            $stmtTx = $this->db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'debito\', :valor, :desc, NOW())');
            $stmtTx->execute([
                ':uid' => $usuarioId,
                ':valor' => $valor,
                ':desc' => 'Pagamento do Pedido #' . $pedidoId,
            ]);
        } catch (\Exception $e) {
        }
    }

    private function criarEnderecoEntregaParaCliente(int $clienteId, ?array $enderecoEntrega): int {
        if ($clienteId <= 0) {
            return 0;
        }
        if (!$this->tableExists('enderecos')) {
            return 0;
        }
        if (!is_array($enderecoEntrega)) {
            return 0;
        }

        $cep = trim((string) ($enderecoEntrega['cep'] ?? ''));
        $endereco = trim((string) ($enderecoEntrega['endereco'] ?? ''));
        $numero = trim((string) ($enderecoEntrega['numero'] ?? ''));
        $bairro = trim((string) ($enderecoEntrega['bairro'] ?? ''));
        $cidade = trim((string) ($enderecoEntrega['cidade'] ?? ''));
        $estado = trim((string) ($enderecoEntrega['estado'] ?? ''));
        $complemento = trim((string) ($enderecoEntrega['complemento'] ?? ''));

        if ($cep === '' || $endereco === '' || $numero === '' || $bairro === '' || $cidade === '' || $estado === '') {
            return 0;
        }

        try {
            $colsEnd = $this->getCols('enderecos');
            if (empty($colsEnd) || !in_array('id', $colsEnd, true) || !in_array('usuario_id', $colsEnd, true)) {
                return 0;
            }

            $map = [
                'cep' => ['cep'],
                'endereco' => ['endereco', 'logradouro'],
                'numero' => ['numero'],
                'complemento' => ['complemento'],
                'bairro' => ['bairro'],
                'cidade' => ['cidade'],
                'estado' => ['estado', 'uf'],
            ];

            $cols = ['usuario_id'];
            $vals = [':usuario_id'];
            $params = [':usuario_id' => $clienteId];

            $resolved = [];
            foreach ($map as $key => $cands) {
                $col = $this->pickFirstExistingColumn($colsEnd, $cands);
                if ($col !== '') {
                    $resolved[$key] = $col;
                }
            }

            $valueByKey = [
                'cep' => $cep,
                'endereco' => $endereco,
                'numero' => $numero,
                'complemento' => $complemento,
                'bairro' => $bairro,
                'cidade' => $cidade,
                'estado' => $estado,
            ];

            foreach ($resolved as $key => $col) {
                $cols[] = $col;
                $vals[] = ':' . $key;
                $params[':' . $key] = $valueByKey[$key] ?? '';
            }

            if (in_array('principal', $colsEnd, true)) {
                $cols[] = 'principal';
                $vals[] = ':principal';
                $params[':principal'] = 1;
            }

            $sql = 'INSERT INTO enderecos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $newId = (int) $this->db->lastInsertId();
            return $newId > 0 ? $newId : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function criarPendenciaComprovantePedido(int $pedidoId, string $metodo, ?int $usuarioId = null): void {
        if ($pedidoId <= 0) {
            return;
        }
        $metodo = trim((string) $metodo);
        if ($metodo === '') {
            return;
        }
        if (!$this->tableExists('pedidos_pagamento_documentos')) {
            return;
        }

        try {
            $stmt = $this->db->prepare('SELECT id FROM pedidos_pagamento_documentos WHERE pedido_id = :pedido_id AND metodo = :metodo LIMIT 1');
            $stmt->execute([':pedido_id' => $pedidoId, ':metodo' => $metodo]);
            $exists = (int) ($stmt->fetchColumn() ?: 0);
            if ($exists > 0) {
                return;
            }
        } catch (
            \Exception $e
        ) {
            // ignore
        }

        $cols = ['pedido_id', 'metodo', 'status'];
        $vals = [':pedido_id', ':metodo', ':status'];
        $params = [':pedido_id' => $pedidoId, ':metodo' => $metodo, ':status' => 'pendente_upload'];

        try {
            $colsT = $this->getCols('pedidos_pagamento_documentos');
            if (!empty($colsT) && $usuarioId !== null && $usuarioId > 0 && in_array('usuario_id', $colsT, true)) {
                $cols[] = 'usuario_id';
                $vals[] = ':usuario_id';
                $params[':usuario_id'] = (int) $usuarioId;
            }
        } catch (\Exception $e) {
        }

        try {
            $sql = 'INSERT INTO pedidos_pagamento_documentos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $stmtIns = $this->db->prepare($sql);
            $stmtIns->execute($params);
        } catch (\Exception $e) {
            // ignore
        }
    }

    public function gerarLinkPagamentoPedidoManual(int $pedidoId, string $billingType = 'BOLETO'): array {
        if ($pedidoId <= 0) {
            throw new \Exception('Pedido inválido');
        }

        $colsPedidos = $this->getCols('pedidos');
        if (empty($colsPedidos)) {
            throw new \Exception('Tabela pedidos não encontrada');
        }

        $colMoeda = in_array('moeda', $colsPedidos, true) ? 'moeda' : (in_array('currency', $colsPedidos, true) ? 'currency' : '');
        $stmt = $this->db->prepare('SELECT id' . ($colMoeda !== '' ? (', ' . $colMoeda . ' AS moeda') : '') . ' FROM pedidos WHERE id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        if (empty($pedido)) {
            throw new \Exception('Pedido não encontrado');
        }

        $moeda = strtoupper((string) ($pedido['moeda'] ?? 'BRL'));
        if ($moeda === '') {
            $moeda = 'BRL';
        }

        if ($moeda === 'BRL') {
            return $this->gerarLinkPagamentoAppMaxPedidoManual($pedidoId, $billingType);
        }

        return $this->gerarLinkPagamentoStripePedidoManual($pedidoId);
    }

    private function gerarLinkPagamentoStripePedidoManual(int $pedidoId): array {
        if ($pedidoId <= 0) {
            return ['success' => false, 'error' => 'Pedido inválido'];
        }

        $colsPedidos = $this->getCols('pedidos');
        if (empty($colsPedidos)) {
            return ['success' => false, 'error' => 'Tabela pedidos não encontrada'];
        }

        $colMoeda = in_array('moeda', $colsPedidos, true) ? 'moeda' : (in_array('currency', $colsPedidos, true) ? 'currency' : '');
        $colTotal = $this->pickFirstExistingColumn($colsPedidos, ['total', 'valor_total', 'amount', 'valor']);
        $colUsuarioId = $this->pickFirstExistingColumn($colsPedidos, ['usuario_id', 'user_id', 'cliente_id']);
        $colNumeroPedido = $this->pickFirstExistingColumn($colsPedidos, ['numero_pedido', 'codigo', 'order_number']);

        $select = ['id'];
        if ($colMoeda !== '') $select[] = $colMoeda . ' AS moeda';
        if ($colTotal !== '') $select[] = $colTotal . ' AS total';
        if ($colUsuarioId !== '') $select[] = $colUsuarioId . ' AS usuario_id';
        if ($colNumeroPedido !== '') $select[] = $colNumeroPedido . ' AS numero_pedido';

        $stmt = $this->db->prepare('SELECT ' . implode(', ', $select) . ' FROM pedidos WHERE id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        if (empty($pedido)) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }

        $moeda = strtoupper(trim((string) ($pedido['moeda'] ?? 'USD')));
        if ($moeda === '') $moeda = 'USD';
        if ($moeda === 'BRL') {
            return ['success' => false, 'error' => 'Este pedido não é USD/Stripe'];
        }

        $totalUsd = (float) ($pedido['total'] ?? 0);
        if ($totalUsd <= 0) {
            return ['success' => false, 'error' => 'Total inválido para cobrança'];
        }

        $email = '';
        try {
            $uid = (int) ($pedido['usuario_id'] ?? 0);
            if ($uid > 0 && $this->tableExists('usuarios')) {
                $stmtU = $this->db->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
                $stmtU->execute([$uid]);
                $email = (string) ($stmtU->fetchColumn() ?: '');
            }
        } catch (\Exception $e) {
            $email = '';
        }

        $descricao = 'Pedido #' . (string) (($pedido['numero_pedido'] ?? '') !== '' ? $pedido['numero_pedido'] : $pedidoId);

        $paySvc = new PaymentService();
        $session = $paySvc->createStripeCheckoutSession($pedidoId, $totalUsd, $descricao, ['email' => $email]);
        if (empty($session['success'])) {
            return ['success' => false, 'error' => (string) ($session['error'] ?? 'Falha ao criar link Stripe')];
        }

        $payResult = [
            'billingType' => 'CREDIT_CARD',
            'payment_id' => (string) ($session['payment_intent_id'] ?? ''),
            'invoiceUrl' => (string) ($session['url'] ?? ''),
        ];

        // Se não veio payment_intent, usar session_id como fallback (ao menos para exibição do link)
        if ($payResult['payment_id'] === '') {
            $payResult['payment_id'] = (string) ($session['session_id'] ?? '');
        }

        $this->persistirPagamentoNoPedido($pedidoId, $payResult, 'stripe');

        return [
            'success' => true,
            'pedido_id' => $pedidoId,
            'payment_id' => $payResult['payment_id'],
            'invoiceUrl' => (string) ($session['url'] ?? ''),
            'billingType' => 'CREDIT_CARD',
            'status' => 'pending',
        ];
    }

    private function pickFirstExistingColumn(array $cols, array $candidates): string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return '';
    }

    private function getConfigKeyValue(string $key, $default = null) {
        $db = $this->db;

        // Formato categoria/chave/valor ou chave/valor
        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        // Formato categoria/chave/valor: se a chave vier no formato categoria_chave,
        // tenta buscar em (categoria, chave) quando a chave completa não existir.
        try {
            if (strpos($key, '_') !== false) {
                [$categoria, $chave] = explode('_', $key, 2);
                $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($cols) && in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                    $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                    $stmt->execute([(string) $categoria, (string) $chave]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        // Formato single row (coluna direta)
        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (is_array($cols) && in_array($key, $cols, true)) {
                $stmt = $db->query('SELECT ' . $key . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && array_key_exists('valor', $row)) {
                    return $row['valor'];
                }
            }
        } catch (\Exception $e) {
        }

        // Fallback em tabela configuracoes (chave/valor)
        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    public function getAliquota(string $key): float {
        $key = trim($key);
        if ($key === '') return 0.0;
        $v = $this->getConfigKeyValue($key, null);
        if ($v === null) {
            return 0.0;
        }
        return (float) str_replace(',', '.', (string) $v);
    }

    public function getTaxaServicoPorKgBRL(): float {
        // Preferência: chaves de entrega (Checkout) e depois chaves genéricas
        $vBRL = $this->getConfigKeyValue('entrega_taxa_servico_kg', null);
        if ($vBRL !== null && $vBRL !== '') {
            return (float) str_replace(',', '.', (string) $vBRL);
        }

        $vBRL = $this->getConfigKeyValue('taxa_servico_kg', null);
        if ($vBRL !== null && $vBRL !== '') {
            return (float) str_replace(',', '.', (string) $vBRL);
        }

        $vUSD = $this->getConfigKeyValue('entrega_taxa_servico_usd_por_kg', null);
        if ($vUSD === null || $vUSD === '') {
            $vUSD = $this->getConfigKeyValue('taxa_servico_usd_por_kg', null);
        }
        $usd = ($vUSD !== null && $vUSD !== '') ? (float) str_replace(',', '.', (string) $vUSD) : 39.0;

        $taxa = $this->getTaxaConversaoUSDBRL();

        return $usd * $taxa;
    }

    public function getTaxaServicoPorKgUSD(): float {
        $vUSD = $this->getConfigKeyValue('entrega_taxa_servico_usd_por_kg', null);
        if ($vUSD === null || $vUSD === '') {
            $vUSD = $this->getConfigKeyValue('taxa_servico_usd_por_kg', null);
        }
        $usd = ($vUSD !== null && $vUSD !== '') ? (float) str_replace(',', '.', (string) $vUSD) : 39.0;
        return $usd;
    }

    public function getPixDescontoTaxaServicoPercent(): float {
        $v = $this->getConfigKeyValue('pagamentos_pix_desconto_taxa_servico_percent', null);
        if ($v === null || $v === '') {
            return 0.0;
        }
        $p = (float) str_replace(',', '.', (string) $v);
        if ($p < 0) $p = 0.0;
        if ($p > 100) $p = 100.0;
        return $p;
    }

    private function calcularImpostosPadrao(float $valorProdutos, float $valorFrete): float {
        $icms = $this->getAliquota('icms_aliquota');
        $ipi = $this->getAliquota('ipi_aliquota');

        $base = $valorProdutos + $valorFrete;
        $valorICMS = ($icms > 0) ? ($base * ($icms / 100)) : 0.0;
        $valorIPI = ($ipi > 0) ? ($base * ($ipi / 100)) : 0.0;

        return $valorICMS + $valorIPI;
    }

    public function getTaxaConversaoUSDBRL(): float {
        $taxa = 5.5;

        $cfg = $this->getConfigKeyValue('sistema_usd_brl_rate', null);
        if ($cfg !== null && $cfg !== '') {
            $v = (float) str_replace(',', '.', (string) $cfg);
            if ($v > 0) {
                return $v;
            }
        }

        try {
            $stmt = $this->db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            if (is_array($row) && isset($row['taxa_conversao'])) {
                $v = (float) $row['taxa_conversao'];
                if ($v > 0) {
                    $taxa = $v;
                }
            }
        } catch (\Exception $e) {
        }

        return $taxa;
    }

    private function calcularResumoPadrao(string $moeda, array $itens, float $valorFrete = 0.0): array {
        $moeda = strtoupper(trim($moeda));
        if ($moeda === '') {
            $moeda = 'BRL';
        }

        $subtotal = 0.0;
        $pesoTotal = 0.0;

        $colsProdutos = $this->getCols('produtos');
        $prodPesoCol = $this->pickFirstExistingColumn($colsProdutos, ['peso', 'weight']);
        $prodPriceCol = $this->pickFirstExistingColumn($colsProdutos, ['price', 'valor', 'preco']);

        $select = ['id'];
        if ($prodPesoCol !== '') $select[] = $prodPesoCol . ' AS peso';
        if ($prodPriceCol !== '') $select[] = $prodPriceCol . ' AS price';
        $stmtProd = $this->db->prepare('SELECT ' . implode(', ', $select) . ' FROM produtos WHERE id = ? LIMIT 1');

        foreach ($itens as $it) {
            $produtoId = (int) ($it['produto_id'] ?? 0);
            $qtd = (int) ($it['quantidade'] ?? 0);
            if ($produtoId <= 0 || $qtd <= 0) continue;

            $valorUnit = isset($it['valor_unitario']) ? (float) $it['valor_unitario'] : 0.0;
            $peso = 0.0;

            try {
                $stmtProd->execute([$produtoId]);
                $p = $stmtProd->fetch(\PDO::FETCH_ASSOC) ?: [];
                if ($valorUnit <= 0 && isset($p['price'])) {
                    $valorUnit = (float) $p['price'];
                }
                if (isset($p['peso'])) {
                    $peso = (float) $p['peso'];
                }
            } catch (\Exception $e) {
            }

            $subtotal += ($valorUnit * $qtd);
            $pesoTotal += ($peso * $qtd);
        }

        $pesoParaTaxa = ceil($pesoTotal);
        $taxaServico = 0.0;
        if ($moeda === 'BRL') {
            $taxaServico = $pesoParaTaxa * $this->getTaxaServicoPorKgBRL();
        } else {
            // Para moedas diferentes, usa taxa USD e não converte (mantém compatibilidade)
            $vUSD = $this->getConfigKeyValue('entrega_taxa_servico_usd_por_kg', null);
            if ($vUSD === null || $vUSD === '') {
                $vUSD = $this->getConfigKeyValue('taxa_servico_usd_por_kg', null);
            }
            $usd = ($vUSD !== null && $vUSD !== '') ? (float) str_replace(',', '.', (string) $vUSD) : 39.0;
            $taxaServico = $pesoParaTaxa * $usd;
        }

        $impostos = $this->calcularImpostosPadrao($subtotal, $valorFrete);
        $total = $subtotal + $valorFrete + $taxaServico + $impostos;

        return [
            'subtotal_produtos' => round($subtotal, 2),
            'peso_total' => round($pesoTotal, 3),
            'taxa_servico' => round($taxaServico, 2),
            'valor_impostos' => round($impostos, 2),
            'valor_frete' => round($valorFrete, 2),
            'valor_total' => round($total, 2),
        ];
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getCols(string $table): array {
        try {
            $stmt = $this->db->query('DESCRIBE ' . $table);
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getItensTable(): string {
        if ($this->tableExists('pedido_itens')) return 'pedido_itens';
        if ($this->tableExists('pedido_items')) return 'pedido_items';
        return 'pedido_itens';
    }

    public function gerarLinkPagamentoAppMaxPedidoManual(int $pedidoId, string $billingType = 'BOLETO'): array {
        return $this->gerarLinkPagamentoAsaasPedidoManual($pedidoId, $billingType);
    }

    private function getConfig(string $categoria, string $chave, $default = null) {
        $db = $this->db;

        try {
            $stmtCols = $db->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols) && !empty($cols)) {
                $colName = null;
                if ($categoria === 'pagamentos') {
                    $direct = [
                        'asaas_api_key',
                        'asaas_ambiente',
                        'asaas_enabled',
                        'webhook_link_pagamento_pedido_manual_url',
                    ];
                    if (in_array($chave, $direct, true) && in_array($chave, $cols, true)) {
                        $colName = $chave;
                    }
                }

                if (!empty($colName)) {
                    $stmt = $db->query('SELECT ' . $colName . ' AS valor FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            $stmt->execute([$categoria, $chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        try {
            $key = $categoria . '_' . $chave;
            $stmt = $db->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    public function criarPedidoManual(int $clienteId, string $moeda, array $itens, array $resumo = [], ?int $adminCriadorId = null, ?string $formaPagamento = null, ?array $enderecoEntrega = null, ?string $tipoCompra = null): int {
        if ($clienteId <= 0) {
            throw new \Exception('Cliente inválido');
        }
        if (empty($itens)) {
            throw new \Exception('Adicione ao menos 1 produto');
        }

        $moeda = strtoupper(trim($moeda));
        if (!in_array($moeda, ['BRL', 'USD', 'EUR'], true)) {
            $moeda = 'BRL';
        }

        if ($formaPagamento !== null) {
            $fpNorm = trim((string) $formaPagamento);
            if ($fpNorm !== '' && $fpNorm !== 'pagdev' && $fpNorm !== 'carteira') {
                throw new \Exception('Forma de pagamento inválida');
            }
        }

        if ($tipoCompra !== null) {
            $tc = strtolower(trim((string) $tipoCompra));
            if ($tc === '') {
                $tipoCompra = null;
            } elseif (!in_array($tc, ['online', 'offline'], true)) {
                throw new \Exception('Tipo de compra inválido');
            } else {
                $tipoCompra = $tc;
            }
        }

        $colsPedidos = $this->getCols('pedidos');
        if (empty($colsPedidos)) {
            throw new \Exception('Tabela pedidos não encontrada');
        }

        $usuarioCol = in_array('usuario_id', $colsPedidos, true) ? 'usuario_id' : (in_array('user_id', $colsPedidos, true) ? 'user_id' : '');
        if ($usuarioCol === '') {
            throw new \Exception('Tabela pedidos sem coluna de usuário');
        }

        $colMoeda = in_array('moeda', $colsPedidos, true) ? 'moeda' : (in_array('currency', $colsPedidos, true) ? 'currency' : '');
        $colTotal = in_array('total', $colsPedidos, true) ? 'total' : (in_array('valor_total', $colsPedidos, true) ? 'valor_total' : (in_array('valor_total_brl', $colsPedidos, true) ? 'valor_total_brl' : ''));
        if ($colTotal === '') {
            throw new \Exception('Tabela pedidos sem coluna de total');
        }

        $codigoCol = in_array('codigo_pedido', $colsPedidos, true) ? 'codigo_pedido' : (in_array('numero_pedido', $colsPedidos, true) ? 'numero_pedido' : '');
        $statusCol = in_array('status', $colsPedidos, true) ? 'status' : '';
        $obsCol = in_array('observacoes', $colsPedidos, true) ? 'observacoes' : (in_array('observacao', $colsPedidos, true) ? 'observacao' : '');

        // Se resumo não veio do front (ou veio zerado), recalcular com a cobrança padrão do sistema
        $resumoTotal = isset($resumo['valor_total']) ? (float) $resumo['valor_total'] : 0.0;
        if ($resumoTotal <= 0) {
            $valorFrete = isset($resumo['valor_frete']) ? (float) $resumo['valor_frete'] : 0.0;
            $resumo = $this->calcularResumoPadrao($moeda, $itens, $valorFrete);
        }

        $subtotalItens = 0.0;
        foreach ($itens as $it) {
            $q = (int) ($it['quantidade'] ?? 0);
            $vu = (float) ($it['valor_unitario'] ?? 0);
            if ($q <= 0 || $vu < 0) {
                throw new \Exception('Item inválido');
            }
            $subtotalItens += ($q * $vu);
        }
        $subtotalItens = round($subtotalItens, 2);

        // Se veio total calculado (com taxas), usar. Senão, usar subtotal como base.
        $valorTotalCalc = isset($resumo['valor_total']) ? (float) $resumo['valor_total'] : 0.0;
        $total = ($valorTotalCalc > 0) ? round($valorTotalCalc, 2) : $subtotalItens;

        // Endereço: em pedidos manuais, preferir o endereço principal do cliente quando existir.
        // Se não houver endereço cadastrado, permitir que o admin informe os dados de entrega para criar o endereço.
        $enderecoEntregaId = 0;
        if (in_array('endereco_entrega_id', $colsPedidos, true) && $this->tableExists('enderecos')) {
            try {
                $colsEnd = $this->getCols('enderecos');
                if (!empty($colsEnd) && in_array('usuario_id', $colsEnd, true) && in_array('id', $colsEnd, true)) {
                    $orderBy = 'id DESC';
                    if (in_array('principal', $colsEnd, true)) {
                        $orderBy = 'principal DESC, id DESC';
                    }
                    $stE = $this->db->prepare('SELECT id FROM enderecos WHERE usuario_id = ? ORDER BY ' . $orderBy . ' LIMIT 1');
                    $stE->execute([(int) $clienteId]);
                    $enderecoEntregaId = (int) ($stE->fetchColumn() ?: 0);
                }
            } catch (\Exception $e) {
                $enderecoEntregaId = 0;
            }

            if ($enderecoEntregaId <= 0) {
                $enderecoEntregaId = $this->criarEnderecoEntregaParaCliente($clienteId, $enderecoEntrega);
                if ($enderecoEntregaId <= 0) {
                    throw new \Exception('Cliente sem endereço de entrega cadastrado. Informe o endereço de entrega para criar o pedido manual.');
                }
            }
        }

        $cols = [$usuarioCol, $colTotal];
        $vals = [':usuario_id', ':total'];
        $params = [':usuario_id' => $clienteId, ':total' => $total];

        if ($colMoeda !== '') {
            $cols[] = $colMoeda;
            $vals[] = ':moeda';
            $params[':moeda'] = $moeda;
        }

        if ($formaPagamento !== null) {
            $fp = trim((string) $formaPagamento);
            if ($fp !== '' && in_array('forma_pagamento', $colsPedidos, true)) {
                $cols[] = 'forma_pagamento';
                $vals[] = ':forma_pagamento';
                $params[':forma_pagamento'] = $fp;
            }
        }

        if ($enderecoEntregaId > 0 && in_array('endereco_entrega_id', $colsPedidos, true)) {
            $cols[] = 'endereco_entrega_id';
            $vals[] = ':endereco_entrega_id';
            $params[':endereco_entrega_id'] = $enderecoEntregaId;
        }

        // Persistir resumo quando colunas existirem
        $mapResumo = [
            'subtotal_produtos' => ['subtotal_produtos', 'subtotal'],
            'peso_total' => ['peso_total'],
            'taxa_servico' => ['taxa_servico', 'servicos'],
            'valor_impostos' => ['valor_impostos', 'impostos'],
            'valor_frete' => ['valor_frete', 'frete'],
            'valor_total' => ['valor_total', 'total'],
        ];
        foreach ($mapResumo as $k => $cands) {
            if (!array_key_exists($k, $resumo)) {
                continue;
            }
            $col = '';
            foreach ($cands as $c) {
                if (in_array($c, $colsPedidos, true)) {
                    $col = $c;
                    break;
                }
            }
            if ($col !== '' && !in_array($col, $cols, true)) {
                $cols[] = $col;
                $vals[] = ':' . $k;
                $params[':' . $k] = (float) $resumo[$k];
            }
        }

        if ($statusCol !== '') {
            $cols[] = $statusCol;
            $vals[] = ':status';
            $params[':status'] = 'pendente';
        }

        if ($obsCol !== '') {
            $cols[] = $obsCol;
            $vals[] = ':obs';
            $params[':obs'] = 'Pedido manual';
        }

        if ($codigoCol !== '') {
            $cols[] = $codigoCol;
            $vals[] = ':codigo';
            $params[':codigo'] = 'MAN-' . date('Ymd-His');
        }

        if ($tipoCompra !== null && in_array('tipo_compra', $colsPedidos, true)) {
            $cols[] = 'tipo_compra';
            $vals[] = ':tipo_compra';
            $params[':tipo_compra'] = $tipoCompra;
        }

        // Origem do pedido / admin criador (quando colunas existirem)
        if (in_array('origem_pedido', $colsPedidos, true)) {
            $cols[] = 'origem_pedido';
            $vals[] = ':origem_pedido';
            $params[':origem_pedido'] = 'manual';
        }
        if ($adminCriadorId !== null && $adminCriadorId > 0 && in_array('admin_criador_id', $colsPedidos, true)) {
            $cols[] = 'admin_criador_id';
            $vals[] = ':admin_criador_id';
            $params[':admin_criador_id'] = (int) $adminCriadorId;
        }

        $this->db->beginTransaction();
        try {
            $sql = 'INSERT INTO pedidos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $pedidoId = (int) $this->db->lastInsertId();

            if ($pedidoId <= 0) {
                throw new \Exception('Falha ao criar pedido');
            }

            $this->salvarItensPedido($pedidoId, $itens, $moeda);

            if ($formaPagamento !== null) {
                $fp = strtolower(trim((string) $formaPagamento));
                if ($fp === 'carteira') {
                    $this->debitarCarteiraParaPedidoManual($clienteId, $pedidoId, (float) $total, (string) $moeda);

                    $colsPedidosUpd = $this->getCols('pedidos');
                    $set = [];
                    $upd = [':id' => (int) $pedidoId];

                    if (is_array($colsPedidosUpd) && in_array('payment_gateway', $colsPedidosUpd, true)) {
                        $set[] = 'payment_gateway = :pg';
                        $upd[':pg'] = 'carteira';
                    }
                    if (is_array($colsPedidosUpd) && in_array('payment_id', $colsPedidosUpd, true)) {
                        $set[] = 'payment_id = :pid';
                        $upd[':pid'] = 'WALLET_' . (int) $pedidoId;
                    }
                    if (is_array($colsPedidosUpd) && in_array('payment_status', $colsPedidosUpd, true)) {
                        $set[] = 'payment_status = :pst';
                        $upd[':pst'] = 'PAID';
                    }
                    if (is_array($colsPedidosUpd) && in_array('forma_pagamento', $colsPedidosUpd, true)) {
                        $set[] = 'forma_pagamento = :fp';
                        $upd[':fp'] = 'carteira';
                    }
                    if (is_array($colsPedidosUpd) && in_array('status', $colsPedidosUpd, true)) {
                        $set[] = 'status = :st';
                        $upd[':st'] = 'processando';
                    }

                    foreach (['paid_at', 'data_pagamento', 'data_confirmacao', 'updated_at'] as $c) {
                        if (is_array($colsPedidosUpd) && in_array($c, $colsPedidosUpd, true)) {
                            $set[] = $c . ' = :paid_at';
                            $upd[':paid_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }

                    if (!empty($set)) {
                        $st = $this->db->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                        $st->execute($upd);
                    }
                }
            }

            // Se for pagamento offline (PagDev), cria pendência de comprovante quando a tabela existir
            if ($formaPagamento !== null) {
                $fp = strtolower(trim((string) $formaPagamento));
                if ($fp === 'pagdev') {
                    $this->criarPendenciaComprovantePedido($pedidoId, $fp, $adminCriadorId);
                }
            }

            $this->db->commit();
            return $pedidoId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function salvarItensPedido(int $pedidoId, array $itens, string $moeda): void {
        $table = $this->getItensTable();
        $colsItens = $this->getCols($table);
        if (empty($colsItens)) {
            throw new \Exception('Tabela de itens do pedido não encontrada');
        }

        $colsProdutos = $this->getCols('produtos');
        $prodNameCol = $this->pickFirstExistingColumn($colsProdutos, ['name', 'nome', 'titulo', 'descricao']);
        $prodSkuCol = $this->pickFirstExistingColumn($colsProdutos, ['sku', 'codigo', 'codigo_sku']);
        $prodPriceCol = $this->pickFirstExistingColumn($colsProdutos, ['price', 'valor', 'preco']);

        $select = ['id'];
        if ($prodNameCol !== '') $select[] = $prodNameCol . ' AS name';
        if ($prodSkuCol !== '') $select[] = $prodSkuCol . ' AS sku';
        if ($prodPriceCol !== '') $select[] = $prodPriceCol . ' AS price';

        $produtoStmt = $this->db->prepare('SELECT ' . implode(', ', $select) . ' FROM produtos WHERE id = ? LIMIT 1');

        foreach ($itens as $it) {
            $produtoId = (int) ($it['produto_id'] ?? 0);
            $qtd = (int) ($it['quantidade'] ?? 0);
            if ($produtoId <= 0 || $qtd <= 0) {
                continue;
            }

            $produtoStmt->execute([$produtoId]);
            $prod = $produtoStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (empty($prod)) {
                throw new \Exception('Produto não encontrado: ' . $produtoId);
            }

            $nome = (string) ($prod['name'] ?? '');
            $sku = (string) ($prod['sku'] ?? '');

            $valorUnit = 0.0;
            if (isset($it['valor_unitario'])) {
                $valorUnit = (float) $it['valor_unitario'];
            } elseif (isset($prod['price'])) {
                $valorUnit = (float) $prod['price'];
            }

            $subtotal = round($valorUnit * $qtd, 2);

            $colPedido = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : '';
            $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
            if ($colPedido === '' || $colProduto === '') {
                throw new \Exception('Tabela de itens incompatível (sem pedido_id/produto_id)');
            }

            $cols = [$colPedido, $colProduto];
            $vals = [':pedido_id', ':produto_id'];
            $params = [':pedido_id' => $pedidoId, ':produto_id' => $produtoId];

            $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
            if ($colQtd !== '') {
                $cols[] = $colQtd;
                $vals[] = ':qtd';
                $params[':qtd'] = $qtd;
            }

            $colNome = in_array('nome_produto', $colsItens, true) ? 'nome_produto' : (in_array('produto_nome', $colsItens, true) ? 'produto_nome' : (in_array('nome', $colsItens, true) ? 'nome' : ''));
            if ($colNome !== '') {
                $cols[] = $colNome;
                $vals[] = ':nome';
                $params[':nome'] = $nome;
            }

            $colSku = in_array('sku', $colsItens, true) ? 'sku' : '';
            if ($colSku !== '') {
                $cols[] = $colSku;
                $vals[] = ':sku';
                $params[':sku'] = $sku;
            }

            $colUnit = in_array('preco_unitario', $colsItens, true) ? 'preco_unitario' : (in_array('valor_unitario', $colsItens, true) ? 'valor_unitario' : (in_array('price', $colsItens, true) ? 'price' : (in_array('preco', $colsItens, true) ? 'preco' : '')));
            if ($colUnit !== '') {
                $cols[] = $colUnit;
                $vals[] = ':vu';
                $params[':vu'] = $valorUnit;
            }

            $colSub = in_array('subtotal', $colsItens, true) ? 'subtotal' : '';
            if ($colSub !== '') {
                $cols[] = $colSub;
                $vals[] = ':sub';
                $params[':sub'] = $subtotal;
            }

            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    public function gerarLinkPagamentoAsaasPedidoManual(int $pedidoId, string $billingType = 'BOLETO'): array {
        if ($pedidoId <= 0) {
            throw new \Exception('Pedido inválido');
        }

        $billingType = strtoupper(trim($billingType));
        if (!in_array($billingType, ['BOLETO', 'PIX'], true)) {
            $billingType = 'BOLETO';
        }

        $colsPedidos = $this->getCols('pedidos');
        if (empty($colsPedidos)) {
            throw new \Exception('Tabela pedidos não encontrada');
        }

        $colTotal = in_array('total', $colsPedidos, true) ? 'total' : (in_array('valor_total', $colsPedidos, true) ? 'valor_total' : (in_array('valor_total_brl', $colsPedidos, true) ? 'valor_total_brl' : ''));
        $colMoeda = in_array('moeda', $colsPedidos, true) ? 'moeda' : (in_array('currency', $colsPedidos, true) ? 'currency' : '');
        $usuarioCol = in_array('usuario_id', $colsPedidos, true) ? 'usuario_id' : (in_array('user_id', $colsPedidos, true) ? 'user_id' : '');
        $codigoCol = in_array('codigo_pedido', $colsPedidos, true) ? 'codigo_pedido' : (in_array('numero_pedido', $colsPedidos, true) ? 'numero_pedido' : '');

        $selectCols = ['id'];
        if ($usuarioCol !== '') $selectCols[] = $usuarioCol . ' AS usuario_id';
        if ($colTotal !== '') $selectCols[] = $colTotal . ' AS total';
        if ($colMoeda !== '') $selectCols[] = $colMoeda . ' AS moeda';
        if ($codigoCol !== '') $selectCols[] = $codigoCol . ' AS codigo_pedido';

        $stmt = $this->db->prepare('SELECT ' . implode(', ', $selectCols) . ' FROM pedidos WHERE id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        if (empty($pedido)) {
            throw new \Exception('Pedido não encontrado');
        }

        $clienteId = (int) ($pedido['usuario_id'] ?? 0);
        if ($clienteId <= 0) {
            throw new \Exception('Pedido sem cliente vinculado');
        }

        $colsUsuarios = $this->getCols('usuarios');
        $uNomeCol = $this->pickFirstExistingColumn($colsUsuarios, ['nome', 'name']);
        $uEmailCol = $this->pickFirstExistingColumn($colsUsuarios, ['email']);
        $uTelefoneCol = $this->pickFirstExistingColumn($colsUsuarios, ['telefone', 'phone', 'celular']);
        $uDocumentoCol = $this->pickFirstExistingColumn($colsUsuarios, ['documento', 'cpf', 'cnpj']);

        $uSelect = ['id'];
        if ($uNomeCol !== '') $uSelect[] = $uNomeCol . ' AS nome';
        if ($uEmailCol !== '') $uSelect[] = $uEmailCol . ' AS email';
        if ($uTelefoneCol !== '') $uSelect[] = $uTelefoneCol . ' AS telefone';
        if ($uDocumentoCol !== '') $uSelect[] = $uDocumentoCol . ' AS documento';

        $stU = $this->db->prepare('SELECT ' . implode(', ', $uSelect) . ' FROM usuarios WHERE id = ? LIMIT 1');
        $stU->execute([$clienteId]);
        $user = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];
        if (empty($user)) {
            throw new \Exception('Cliente não encontrado');
        }

        $nome = (string) ($user['nome'] ?? '');
        $email = (string) ($user['email'] ?? '');
        $telefone = (string) ($user['telefone'] ?? '');
        $documento = (string) ($user['documento'] ?? '');

        $total = (float) ($pedido['total'] ?? 0);
        $moeda = strtoupper((string) ($pedido['moeda'] ?? 'BRL'));
        if ($moeda === '') {
            $moeda = 'BRL';
        }

        if ($moeda !== 'BRL') {
            throw new \Exception('Pedido manual para AppMax deve estar em BRL');
        }

        $codigoPedido = (string) ($pedido['codigo_pedido'] ?? $pedidoId);
        if ($codigoPedido === '') {
            $codigoPedido = (string) $pedidoId;
        }

        $descricao = 'Pedido manual #' . $codigoPedido;

        // PIX: aplicar desconto configurável na taxa de serviço (atualiza o pedido antes de gerar cobrança)
        if ($billingType === 'PIX') {
            try {
                $pct = (float) $this->getPixDescontoTaxaServicoPercent();
                if ($pct > 0) {
                    $stCols = $this->db->query('DESCRIBE pedidos');
                    $colsAll = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

                    $colSvc = '';
                    foreach (['taxa_servico', 'servicos'] as $c) {
                        if (is_array($colsAll) && in_array($c, $colsAll, true)) {
                            $colSvc = $c;
                            break;
                        }
                    }

                    if ($colSvc !== '') {
                        $colSub = in_array('subtotal', $colsAll, true) ? 'subtotal' : (in_array('subtotal_produtos', $colsAll, true) ? 'subtotal_produtos' : '');
                        $colImp = in_array('impostos', $colsAll, true) ? 'impostos' : (in_array('valor_impostos', $colsAll, true) ? 'valor_impostos' : '');
                        $colFre = in_array('frete', $colsAll, true) ? 'frete' : (in_array('valor_frete', $colsAll, true) ? 'valor_frete' : '');
                        $colTot = in_array('total', $colsAll, true) ? 'total' : (in_array('valor_total', $colsAll, true) ? 'valor_total' : (in_array('valor_total_brl', $colsAll, true) ? 'valor_total_brl' : ''));
                        $colDesc = in_array('desconto', $colsAll, true) ? 'desconto' : '';

                        $sel = ['id'];
                        $sel[] = $colSvc . ' AS taxa_servico';
                        if ($colSub !== '') $sel[] = $colSub . ' AS subtotal';
                        if ($colImp !== '') $sel[] = $colImp . ' AS impostos';
                        if ($colFre !== '') $sel[] = $colFre . ' AS frete';
                        if ($colTot !== '') $sel[] = $colTot . ' AS total';
                        if ($colDesc !== '') $sel[] = $colDesc . ' AS desconto';
                        $stP = $this->db->prepare('SELECT ' . implode(', ', $sel) . ' FROM pedidos WHERE id = ? LIMIT 1');
                        $stP->execute([(int) $pedidoId]);
                        $rowP = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];

                        $svc = (float) ($rowP['taxa_servico'] ?? 0);
                        $svcNew = max(0.0, $svc * (1.0 - ($pct / 100.0)));

                        if ($svcNew !== $svc) {
                            $subtotalCalc = (float) ($rowP['subtotal'] ?? 0);
                            $impostosCalc = (float) ($rowP['impostos'] ?? 0);
                            $freteCalc = (float) ($rowP['frete'] ?? 0);
                            $descontoCalc = (float) ($rowP['desconto'] ?? 0);
                            $totalNew = $subtotalCalc + $svcNew + $impostosCalc + $freteCalc - $descontoCalc;

                            $set = [$colSvc . ' = :svc'];
                            $params = [':svc' => $svcNew, ':id' => (int) $pedidoId];
                            if ($colTot !== '') {
                                $set[] = $colTot . ' = :tot';
                                $params[':tot'] = $totalNew;
                            }
                            $sqlUp = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                            $this->db->prepare($sqlUp)->execute($params);

                            $total = $colTot !== '' ? $totalNew : $total;
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }

        $pg = new PaymentService();

        $totalCents = (int) round(((float) $total) * 100);
        $shippingValueCents = 0;
        $productsValueCents = $totalCents;

        $products = [
            [
                'sku' => 'PEDIDO_MANUAL_' . (string) $pedidoId,
                'name' => $descricao,
                'quantity' => 1,
                'unit_value' => $productsValueCents,
                'type' => 'service',
                'freight_type' => 'normal',
            ]
        ];

        $result = $pg->processarPagamento([
            'billingType' => $billingType,
            'customer_name' => $nome,
            'customer_email' => $email,
            'customer_phone' => $telefone,
            'customer_document' => $documento,
            'externalReference' => (string) $pedidoId,
            'products' => $products,
            'products_value_cents' => $productsValueCents,
            'shipping_value_cents' => $shippingValueCents,
            'discount_value_cents' => 0,
        ], $total, 'BRL', $descricao);

        $paymentId = (string) ($result['payment_id'] ?? '');
        $invoiceUrl = (string) ($result['invoiceUrl'] ?? '');
        $statusGateway = (string) ($result['status'] ?? '');
        $pix = (isset($result['pix']) && is_array($result['pix'])) ? $result['pix'] : null;
        $bankSlipUrl = (string) ($result['bankSlipUrl'] ?? '');
        $digitableLine = (string) ($result['digitableLine'] ?? '');

        if ($paymentId === '') {
            throw new \Exception('AppMax: payment_id não retornado');
        }

        $this->persistirPagamentoNoPedido($pedidoId, $result, 'appmax');

        $itens = $this->obterItensPedidoManualParaWebhook($pedidoId, $moeda);

        $this->enviarWebhookLinkPagamentoPedidoManual([
            'evento' => 'pedido_manual_link_pagamento_gerado',
            'timestamp' => date('c'),
            'pedido' => [
                'id' => (int) $pedidoId,
                'codigo' => $codigoPedido,
                'moeda' => $moeda,
                'total' => (float) $total,
            ],
            'cliente' => [
                'id' => (int) $clienteId,
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'documento' => $documento,
            ],
            'itens' => $itens,
            'pagamento' => [
                'gateway' => 'appmax',
                'payment_id' => $paymentId,
                'invoice_url' => $invoiceUrl,
                'billing_type' => $billingType,
                'status' => $statusGateway,
            ],
        ]);

        return [
            'success' => true,
            'pedido_id' => $pedidoId,
            'payment_id' => $paymentId,
            'invoiceUrl' => $invoiceUrl,
            'pix' => $pix,
            'bankSlipUrl' => $bankSlipUrl,
            'digitableLine' => $digitableLine,
            'billingType' => $billingType,
            'status' => $statusGateway,
        ];
    }

    private function persistirPagamentoNoPedido(int $pedidoId, array $payResult, string $gateway = 'asaas'): void {
        $colsPedidos = $this->getCols('pedidos');
        if (empty($colsPedidos)) {
            return;
        }

        $set = [];
        $params = [':id' => $pedidoId];

        if (in_array('payment_gateway', $colsPedidos, true)) {
            $set[] = 'payment_gateway = :pg';
            $params[':pg'] = $gateway;
        }
        if (in_array('forma_pagamento', $colsPedidos, true)) {
            $bt = (string) ($payResult['billingType'] ?? ($payResult['billing_type'] ?? ''));
            $bt = strtoupper(trim($bt));
            if ($bt !== '') {
                $set[] = 'forma_pagamento = :fp';
                $params[':fp'] = $bt;
            }
        }
        if (in_array('payment_id', $colsPedidos, true)) {
            $set[] = 'payment_id = :pid';
            $params[':pid'] = (string) ($payResult['payment_id'] ?? '');
        }
        if (in_array('payment_invoice_url', $colsPedidos, true)) {
            $inv = (string) ($payResult['invoiceUrl'] ?? ($payResult['invoice_url'] ?? ''));
            if ($inv !== '') {
                $set[] = 'payment_invoice_url = :inv';
                $params[':inv'] = $inv;
            }
        }
        if (in_array('payment_bank_slip_url', $colsPedidos, true)) {
            $bol = (string) ($payResult['bankSlipUrl'] ?? ($payResult['bank_slip_url'] ?? ''));
            if ($bol !== '') {
                $set[] = 'payment_bank_slip_url = :bol';
                $params[':bol'] = $bol;
            }
        }
        if (in_array('payment_digitable_line', $colsPedidos, true)) {
            $dig = (string) ($payResult['digitableLine'] ?? ($payResult['digitable_line'] ?? ($payResult['linha_digitavel'] ?? '')));
            if ($dig !== '') {
                $set[] = 'payment_digitable_line = :dig';
                $params[':dig'] = $dig;
            }
        }
        if (in_array('payment_pix_payload', $colsPedidos, true) || in_array('payment_pix_encoded_image', $colsPedidos, true)) {
            $pix = null;
            if (isset($payResult['pix']) && is_array($payResult['pix'])) {
                $pix = $payResult['pix'];
            }
            $pixPayload = '';
            $pixImg = '';
            if (is_array($pix)) {
                $pixPayload = (string) ($pix['payload'] ?? ($pix['pix_payload'] ?? ''));
                $pixImg = (string) ($pix['encodedImage'] ?? ($pix['encoded_image'] ?? ($pix['qrCode'] ?? ($pix['qr_code'] ?? ''))));
            }
            if ($pixPayload === '') {
                $pixPayload = (string) ($payResult['pixPayload'] ?? ($payResult['pix_payload'] ?? ''));
            }
            if ($pixImg === '') {
                $pixImg = (string) ($payResult['pixImg'] ?? ($payResult['pix_encoded_image'] ?? ''));
            }

            if ($pixPayload !== '' && in_array('payment_pix_payload', $colsPedidos, true)) {
                $set[] = 'payment_pix_payload = :pix_payload';
                $params[':pix_payload'] = $pixPayload;
            }
            if ($pixImg !== '' && in_array('payment_pix_encoded_image', $colsPedidos, true)) {
                $set[] = 'payment_pix_encoded_image = :pix_img';
                $params[':pix_img'] = $pixImg;
            }
        }
        if (in_array('payment_status', $colsPedidos, true)) {
            $set[] = 'payment_status = :pst';
            $params[':pst'] = 'pending';
        }
        if (in_array('status', $colsPedidos, true)) {
            $set[] = 'status = :st';
            $params[':st'] = 'pendente';
        }

        if (!empty($set)) {
            $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    private function enviarWebhookLinkPagamentoPedidoManual(array $payload): void {
        $url = (string) $this->getConfig('pagamentos', 'webhook_link_pagamento_pedido_manual_url', '');
        if ($url === '') {
            return;
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: brz-new/1.0',
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!empty($err)) {
                error_log('[WEBHOOK][PEDIDO_MANUAL] Erro cURL: ' . $err);
                return;
            }
            if ($code < 200 || $code >= 300) {
                error_log('[WEBHOOK][PEDIDO_MANUAL] HTTP ' . $code . ': ' . (string) $resp);
                return;
            }
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 15,
            ]
        ]);
        $resp = @file_get_contents($url, false, $context);
        if ($resp === false) {
            error_log('[WEBHOOK][PEDIDO_MANUAL] Falha ao enviar');
        }
    }

    private function obterItensPedidoManualParaWebhook(int $pedidoId, string $moeda): array {
        $table = $this->getItensTable();
        $colsItens = $this->getCols($table);
        if (empty($colsItens)) {
            return [];
        }

        $colPedido = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : '';
        if ($colPedido === '') {
            return [];
        }

        $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
        $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
        $colUnit = in_array('valor_unitario', $colsItens, true) ? 'valor_unitario' : (in_array('price', $colsItens, true) ? 'price' : (in_array('preco', $colsItens, true) ? 'preco' : ''));
        $colSub = in_array('subtotal', $colsItens, true) ? 'subtotal' : '';
        $colNomeItem = in_array('nome_produto', $colsItens, true) ? 'nome_produto' : (in_array('produto_nome', $colsItens, true) ? 'produto_nome' : (in_array('nome', $colsItens, true) ? 'nome' : ''));
        $colSkuItem = in_array('sku', $colsItens, true) ? 'sku' : '';

        $select = [];
        if ($colProduto !== '') $select[] = $colProduto . ' AS produto_id';
        if ($colQtd !== '') $select[] = $colQtd . ' AS quantidade';
        if ($colUnit !== '') $select[] = $colUnit . ' AS valor_unitario';
        if ($colSub !== '') $select[] = $colSub . ' AS subtotal';
        if ($colNomeItem !== '') $select[] = $colNomeItem . ' AS nome';
        if ($colSkuItem !== '') $select[] = $colSkuItem . ' AS sku';
        if (empty($select)) {
            $select[] = 'id';
        }

        $stmt = $this->db->prepare('SELECT ' . implode(', ', $select) . ' FROM ' . $table . ' WHERE ' . $colPedido . ' = ? ORDER BY id ASC');
        $stmt->execute([$pedidoId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Enriquecer com dados do produto se faltar nome/sku
        $colsProdutos = $this->getCols('produtos');
        $pNomeCol = $this->pickFirstExistingColumn($colsProdutos, ['name', 'nome', 'titulo', 'descricao']);
        $pSkuCol = $this->pickFirstExistingColumn($colsProdutos, ['sku', 'codigo', 'codigo_sku']);

        $prodStmt = null;
        if (!empty($colsProdutos) && ($pNomeCol !== '' || $pSkuCol !== '')) {
            $pSel = ['id'];
            if ($pNomeCol !== '') $pSel[] = $pNomeCol . ' AS nome';
            if ($pSkuCol !== '') $pSel[] = $pSkuCol . ' AS sku';
            $prodStmt = $this->db->prepare('SELECT ' . implode(', ', $pSel) . ' FROM produtos WHERE id = ? LIMIT 1');
        }

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['produto_id'] ?? 0);
            $nome = (string) ($r['nome'] ?? '');
            $sku = (string) ($r['sku'] ?? '');

            if ($prodStmt && $pid > 0 && ($nome === '' || $sku === '')) {
                try {
                    $prodStmt->execute([$pid]);
                    $p = $prodStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                    if ($nome === '' && isset($p['nome'])) $nome = (string) $p['nome'];
                    if ($sku === '' && isset($p['sku'])) $sku = (string) $p['sku'];
                } catch (\Exception $e) {
                    // ignore
                }
            }

            $qtd = (int) ($r['quantidade'] ?? 0);
            $unit = (float) ($r['valor_unitario'] ?? 0);
            $sub = isset($r['subtotal']) ? (float) $r['subtotal'] : (float) ($qtd * $unit);

            $out[] = [
                'produto_id' => $pid,
                'nome' => $nome,
                'sku' => $sku,
                'quantidade' => $qtd,
                'valor_unitario' => $unit,
                'subtotal' => $sub,
                'moeda' => $moeda,
            ];
        }

        return $out;
    }
}
