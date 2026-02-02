<?php
namespace App\Services;

class PedidoManualService {
    private \PDO $db;

    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Config\Database::getConnection();
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
        // Preferência: chave taxa_servico_usd_por_kg * taxa de conversão, ou taxa_servico_kg diretamente
        $vBRL = $this->getConfigKeyValue('taxa_servico_kg', null);
        if ($vBRL !== null && $vBRL !== '') {
            return (float) str_replace(',', '.', (string) $vBRL);
        }

        $vUSD = $this->getConfigKeyValue('taxa_servico_usd_por_kg', null);
        $usd = ($vUSD !== null && $vUSD !== '') ? (float) str_replace(',', '.', (string) $vUSD) : 39.0;

        $taxa = 5.5;
        try {
            $stmt = $this->db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            if (is_array($row) && isset($row['taxa_conversao'])) {
                $taxa = (float) $row['taxa_conversao'];
            }
        } catch (\Exception $e) {
        }

        return $usd * $taxa;
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

    public function criarPedidoManual(int $clienteId, string $moeda, array $itens, array $resumo = []): int {
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

        $total = 0.0;
        foreach ($itens as $it) {
            $q = (int) ($it['quantidade'] ?? 0);
            $vu = (float) ($it['valor_unitario'] ?? 0);
            if ($q <= 0 || $vu < 0) {
                throw new \Exception('Item inválido');
            }
            $total += ($q * $vu);
        }
        $total = round($total, 2);

        // Se o front enviou um total calculado (com taxas), usar quando for maior ou igual ao subtotal
        $valorTotalCalc = isset($resumo['valor_total']) ? (float) $resumo['valor_total'] : 0.0;
        if ($valorTotalCalc > 0 && $valorTotalCalc >= $total) {
            $total = round($valorTotalCalc, 2);
        }

        $cols = [$usuarioCol, $colTotal];
        $vals = [':usuario_id', ':total'];
        $params = [':usuario_id' => $clienteId, ':total' => $total];

        if ($colMoeda !== '') {
            $cols[] = $colMoeda;
            $vals[] = ':moeda';
            $params[':moeda'] = $moeda;
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
            if ($col !== '') {
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

            $colUnit = in_array('valor_unitario', $colsItens, true) ? 'valor_unitario' : (in_array('price', $colsItens, true) ? 'price' : (in_array('preco', $colsItens, true) ? 'preco' : ''));
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
            throw new \Exception('Pedido manual para Asaas deve estar em BRL');
        }

        $codigoPedido = (string) ($pedido['codigo_pedido'] ?? $pedidoId);
        if ($codigoPedido === '') {
            $codigoPedido = (string) $pedidoId;
        }

        $descricao = 'Pedido manual #' . $codigoPedido;

        $pg = new PaymentService();
        $result = $pg->processarPagamento([
            'billingType' => $billingType,
            'customer_name' => $nome,
            'customer_email' => $email,
            'customer_phone' => $telefone,
            'customer_document' => $documento,
            'externalReference' => (string) $pedidoId,
        ], $total, 'BRL', $descricao);

        $paymentId = (string) ($result['payment_id'] ?? '');
        $invoiceUrl = (string) ($result['invoiceUrl'] ?? '');
        $statusGateway = (string) ($result['status'] ?? '');

        if ($paymentId === '') {
            throw new \Exception('Asaas: payment_id não retornado');
        }

        $this->persistirPagamentoNoPedido($pedidoId, $result);

        $this->enviarWebhookLinkPagamentoPedidoManual([
            'pedido_id' => (string) $pedidoId,
            'codigo_pedido' => $codigoPedido,
            'cliente_id' => (string) $clienteId,
            'cliente_nome' => $nome,
            'total' => (string) $total,
            'moeda' => $moeda,
            'payment_id' => $paymentId,
            'invoiceUrl' => $invoiceUrl,
            'billingType' => $billingType,
            'status' => $statusGateway,
        ]);

        return [
            'success' => true,
            'pedido_id' => $pedidoId,
            'payment_id' => $paymentId,
            'invoiceUrl' => $invoiceUrl,
            'billingType' => $billingType,
            'status' => $statusGateway,
        ];
    }

    private function persistirPagamentoNoPedido(int $pedidoId, array $payResult): void {
        $colsPedidos = $this->getCols('pedidos');
        if (empty($colsPedidos)) {
            return;
        }

        $set = [];
        $params = [':id' => $pedidoId];

        if (in_array('payment_gateway', $colsPedidos, true)) {
            $set[] = 'payment_gateway = :pg';
            $params[':pg'] = 'asaas';
        }
        if (in_array('payment_id', $colsPedidos, true)) {
            $set[] = 'payment_id = :pid';
            $params[':pid'] = (string) ($payResult['payment_id'] ?? '');
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
}
