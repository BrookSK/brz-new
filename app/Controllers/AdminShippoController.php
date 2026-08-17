<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\ShippoService;
use Config\Database;

/**
 * Controller para gerenciamento de etiquetas Shippo.
 * Envios internacionais para o mundo todo, exceto Brasil.
 */
class AdminShippoController extends Controller {
    private ShippoService $svc;
    private $connection;

    public function __construct() {
        $this->svc = new ShippoService();
        $this->connection = Database::getConnection();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableColumns(string $table): array {
        try {
            $stmt = $this->connection->query('DESCRIBE ' . $table);
            $cols = $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $cols, array $candidates): string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return '';
    }

    // ─── Tabela de Etiquetas Shippo ──────────────────────────────────────────────

    private function ensureShippoEtiquetasTable(): void {
        try {
            if ($this->tableExists('shippo_etiquetas')) {
                return;
            }
            $sql = "CREATE TABLE IF NOT EXISTS shippo_etiquetas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                shipment_id VARCHAR(200) NULL,
                transaction_id VARCHAR(200) NULL,
                rate_id VARCHAR(200) NULL,
                tracking_number VARCHAR(200) NULL,
                tracking_url VARCHAR(500) NULL,
                label_url VARCHAR(500) NULL,
                carrier VARCHAR(100) NULL,
                service_level VARCHAR(100) NULL,
                rate_amount DECIMAL(10,2) NULL,
                rate_currency VARCHAR(10) DEFAULT 'USD',
                status VARCHAR(30) DEFAULT 'gerada',
                last_request_json LONGTEXT NULL,
                last_response_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uniq_shippo_etiquetas_pedido_id (pedido_id),
                KEY idx_shippo_etiquetas_tracking_number (tracking_number),
                KEY idx_shippo_etiquetas_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->connection->exec($sql);
        } catch (\Exception $e) {
        }
    }

    // ─── Busca de pedidos ────────────────────────────────────────────────────────

    /**
     * Busca pedidos consolidados SEM etiqueta Shippo, excluindo destinos para o Brasil.
     * Apenas pedidos cujo país de destino NÃO seja BR.
     */
    private function getPedidosSemEtiqueta(): array {
        if (!$this->tableExists('pedidos')) {
            return [];
        }
        $this->ensureShippoEtiquetasTable();

        $colsP = $this->getTableColumns('pedidos');
        $colsU = $this->getTableColumns('usuarios');

        // Identificar coluna de país de destino
        $colPais = $this->pickColumn($colsP, ['pais_destino', 'pais', 'country', 'country_code', 'dest_country']);

        // Construir SELECT com colunas que existem
        $extraSelect = '';
        if (in_array('peso_total', $colsP, true)) $extraSelect .= ', p.peso_total';
        else $extraSelect .= ', NULL AS peso_total';
        if (in_array('altura', $colsP, true)) $extraSelect .= ', p.altura';
        else $extraSelect .= ', NULL AS altura';
        if (in_array('largura', $colsP, true)) $extraSelect .= ', p.largura';
        else $extraSelect .= ', NULL AS largura';
        if (in_array('comprimento', $colsP, true)) $extraSelect .= ', p.comprimento';
        else $extraSelect .= ', NULL AS comprimento';

        // País de destino
        if ($colPais !== '') {
            $extraSelect .= ', p.' . $colPais . ' AS pais_destino';
        } else {
            $extraSelect .= ", 'US' AS pais_destino";
        }

        $colUserNome = in_array('nome', $colsU, true) ? 'u.nome' : (in_array('name', $colsU, true) ? 'u.name' : 'NULL');
        $colUserEmail = in_array('email', $colsU, true) ? 'u.email' : 'NULL';
        $colUserTel = in_array('telefone', $colsU, true) ? 'u.telefone' : (in_array('phone', $colsU, true) ? 'u.phone' : 'NULL');

        // Filtro para excluir Brasil
        $paisFilter = '';
        if ($colPais !== '') {
            $paisFilter = " AND UPPER(COALESCE(p." . $colPais . ",'')) NOT IN ('BR','BRA','BRAZIL','BRASIL')";
        }

        $sql = "
            SELECT p.id AS pedido_id, {$colUserNome} AS cliente_nome, p.usuario_id, p.created_at
                   {$extraSelect},
                   {$colUserEmail} AS cliente_email, {$colUserTel} AS cliente_telefone
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN shippo_etiquetas se ON se.pedido_id = p.id
            WHERE LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado')
              AND se.id IS NULL
              {$paisFilter}
            ORDER BY p.created_at ASC
            LIMIT 200
        ";
        try {
            $st = $this->connection->prepare($sql);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) {
                $r['pedido_id'] = (int) ($r['pedido_id'] ?? 0);
                $r['id'] = (int) ($r['pedido_id'] ?? 0);
            }
            unset($r);
            return $rows;
        } catch (\Exception $e) {
            error_log('[SHIPPO] getPedidosSemEtiqueta error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca etiquetas Shippo já geradas.
     */
    private function getEtiquetasGeradas(): array {
        $this->ensureShippoEtiquetasTable();
        if (!$this->tableExists('shippo_etiquetas')) {
            return [];
        }
        try {
            $sql = "
                SELECT se.*, u.nome AS cliente_nome
                FROM shippo_etiquetas se
                LEFT JOIN pedidos p ON p.id = se.pedido_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                ORDER BY se.created_at DESC
                LIMIT 500
            ";
            $st = $this->connection->prepare($sql);
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Busca dados completos de um pedido por ID.
     */
    private function getPedidoCompleto(int $pedidoId): ?array {
        if (!$this->tableExists('pedidos')) {
            return null;
        }
        $colsP = $this->getTableColumns('pedidos');
        $colsU = $this->getTableColumns('usuarios');

        $colUserNome = in_array('nome', $colsU, true) ? 'u.nome' : (in_array('name', $colsU, true) ? 'u.name' : 'NULL');
        $colUserEmail = in_array('email', $colsU, true) ? 'u.email' : 'NULL';
        $colUserTel = in_array('telefone', $colsU, true) ? 'u.telefone' : (in_array('phone', $colsU, true) ? 'u.phone' : 'NULL');

        // Endereço de destino
        $colEndereco = $this->pickColumn($colsP, ['endereco_entrega', 'endereco', 'address', 'street1']);
        $colCidade = $this->pickColumn($colsP, ['cidade_entrega', 'cidade', 'city']);
        $colEstado = $this->pickColumn($colsP, ['estado_entrega', 'estado', 'state']);
        $colCep = $this->pickColumn($colsP, ['cep_entrega', 'cep', 'zip', 'postal_code']);
        $colPais = $this->pickColumn($colsP, ['pais_destino', 'pais', 'country', 'country_code', 'dest_country']);
        $colComplemento = $this->pickColumn($colsP, ['complemento_entrega', 'complemento', 'address2', 'street2']);

        $selects = ['p.*', "{$colUserNome} AS cliente_nome", "{$colUserEmail} AS cliente_email", "{$colUserTel} AS cliente_telefone"];

        $sql = "SELECT " . implode(', ', $selects) . "
                FROM pedidos p
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.id = ?
                LIMIT 1";
        try {
            $st = $this->connection->prepare($sql);
            $st->execute([$pedidoId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$row) return null;

            // Normalizar campos de endereço
            $row['_endereco'] = $row[$colEndereco] ?? '';
            $row['_cidade'] = $row[$colCidade] ?? '';
            $row['_estado'] = $row[$colEstado] ?? '';
            $row['_cep'] = $row[$colCep] ?? '';
            $row['_pais'] = $row[$colPais] ?? 'US';
            $row['_complemento'] = $colComplemento !== '' ? ($row[$colComplemento] ?? '') : '';

            return $row;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Busca itens de um pedido para declaração aduaneira.
     */
    private function getItensPedido(int $pedidoId): array {
        $tableCandidates = ['pedido_itens', 'pedido_produtos', 'order_items', 'itens_pedido'];
        $table = '';
        foreach ($tableCandidates as $t) {
            if ($this->tableExists($t)) {
                $table = $t;
                break;
            }
        }
        if ($table === '') {
            return [];
        }

        $cols = $this->getTableColumns($table);
        $colPedidoId = $this->pickColumn($cols, ['pedido_id', 'order_id']);
        if ($colPedidoId === '') return [];

        $colNome = $this->pickColumn($cols, ['nome', 'name', 'descricao', 'description', 'produto_nome']);
        $colQtd = $this->pickColumn($cols, ['quantidade', 'qty', 'quantity']);
        $colPeso = $this->pickColumn($cols, ['peso', 'weight', 'peso_unitario']);
        $colValor = $this->pickColumn($cols, ['valor_unitario', 'preco', 'price', 'valor', 'amount']);
        $colHsCode = $this->pickColumn($cols, ['hs_code', 'ncm', 'tariff_number']);

        try {
            $sql = "SELECT * FROM {$table} WHERE {$colPedidoId} = ?";
            $st = $this->connection->prepare($sql);
            $st->execute([$pedidoId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'description' => $colNome !== '' ? (string) ($r[$colNome] ?? 'Merchandise') : 'Merchandise',
                    'quantity' => $colQtd !== '' ? (int) ($r[$colQtd] ?? 1) : 1,
                    'net_weight' => $colPeso !== '' ? (string) ($r[$colPeso] ?? '0.1') : '0.1',
                    'mass_unit' => 'kg',
                    'value_amount' => $colValor !== '' ? (string) ($r[$colValor] ?? '10.00') : '10.00',
                    'value_currency' => 'USD',
                    'origin_country' => 'US',
                    'tariff_number' => $colHsCode !== '' ? (string) ($r[$colHsCode] ?? '') : '',
                ];
            }
            return $items;
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Actions ─────────────────────────────────────────────────────────────────

    /**
     * Página principal - listagem de pedidos e etiquetas.
     */
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $pedidos = $this->getPedidosSemEtiqueta();
        $etiquetas = $this->getEtiquetasGeradas();

        $this->view('admin/shippo', [
            'pedidos' => $pedidos,
            'etiquetas' => $etiquetas,
            'sidebarActive' => 'shippo',
        ]);
    }

    /**
     * Detalhes de um pedido - com formulário para gerar etiqueta.
     */
    public function pedido(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $id = (int) $request->param('id');
        if ($id <= 0) {
            $this->redirect('/admin/shippo');
            return;
        }

        $pedido = $this->getPedidoCompleto($id);
        if (!$pedido) {
            $this->redirect('/admin/shippo');
            return;
        }

        $itens = $this->getItensPedido($id);

        // Verificar se já tem etiqueta
        $this->ensureShippoEtiquetasTable();
        $etiqueta = null;
        try {
            $st = $this->connection->prepare("SELECT * FROM shippo_etiquetas WHERE pedido_id = ? LIMIT 1");
            $st->execute([$id]);
            $etiqueta = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {}

        $this->view('admin/shippo-pedido', [
            'pedido' => $pedido,
            'itens' => $itens,
            'etiqueta' => $etiqueta,
            'sidebarActive' => 'shippo',
        ]);
    }

    /**
     * Gerar etiqueta para um pedido (POST).
     * Fluxo: cria shipment -> exibe rates -> usuário escolhe -> purchaseLabel.
     */
    public function gerarEtiqueta(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $id = (int) $request->param('id');
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID do pedido inválido.'], 400);
            return;
        }

        if (!$this->svc->isConfigured()) {
            $this->json(['success' => false, 'error' => 'Shippo não configurado. Adicione o token em Configurações.'], 400);
            return;
        }

        $pedido = $this->getPedidoCompleto($id);
        if (!$pedido) {
            $this->json(['success' => false, 'error' => 'Pedido não encontrado.'], 404);
            return;
        }

        // País destino não pode ser Brasil
        $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));
        if (in_array($pais, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true)) {
            $this->json(['success' => false, 'error' => 'Shippo não atende envios para o Brasil. Use Correio Internacional.'], 400);
            return;
        }

        // Montar endereço de origem
        $addressFrom = $this->svc->getDefaultAddressFrom();

        // Montar endereço de destino
        $addressTo = [
            'name' => (string) ($pedido['cliente_nome'] ?? ''),
            'street1' => (string) ($pedido['_endereco'] ?? ''),
            'street2' => (string) ($pedido['_complemento'] ?? ''),
            'city' => (string) ($pedido['_cidade'] ?? ''),
            'state' => (string) ($pedido['_estado'] ?? ''),
            'zip' => (string) ($pedido['_cep'] ?? ''),
            'country' => $pais ?: 'US',
            'phone' => (string) ($pedido['cliente_telefone'] ?? ''),
            'email' => (string) ($pedido['cliente_email'] ?? ''),
        ];

        // Montar parcel (dimensões e peso)
        $peso = (float) ($pedido['peso_total'] ?? 0);
        $altura = (float) ($pedido['altura'] ?? 0);
        $largura = (float) ($pedido['largura'] ?? 0);
        $comprimento = (float) ($pedido['comprimento'] ?? 0);

        if ($peso <= 0) $peso = 0.5;
        if ($altura <= 0) $altura = 10;
        if ($largura <= 0) $largura = 10;
        if ($comprimento <= 0) $comprimento = 10;

        $parcel = [
            'length' => (string) $comprimento,
            'width' => (string) $largura,
            'height' => (string) $altura,
            'distance_unit' => 'cm',
            'weight' => (string) $peso,
            'mass_unit' => 'kg',
        ];

        // Declaração aduaneira (envio internacional)
        $itens = $this->getItensPedido($id);
        $customsDeclaration = [];
        if (!empty($itens)) {
            $customsDeclaration = $this->svc->buildCustomsDeclaration($itens);
        } else {
            $customsDeclaration = $this->svc->buildCustomsDeclaration([
                ['description' => 'Merchandise', 'quantity' => 1, 'net_weight' => (string) $peso, 'value_amount' => '50.00']
            ]);
        }

        // Criar shipment para obter rates
        $result = $this->svc->createShipment($addressFrom, $addressTo, $parcel, $customsDeclaration);

        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Falha ao criar shipment.'], 400);
            return;
        }

        // Salvar shipment_id e retornar rates para o frontend
        $rates = $result['rates'] ?? [];
        $shipmentId = $result['shipment_id'] ?? '';

        $this->json([
            'success' => true,
            'shipment_id' => $shipmentId,
            'rates' => $rates,
            'pedido_id' => $id,
        ]);
    }

    /**
     * Confirma a compra da etiqueta com o rate escolhido (POST).
     */
    public function confirmarEtiqueta(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $id = (int) $request->param('id');
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $rateId = (string) ($body['rate_id'] ?? '');

        if ($id <= 0 || $rateId === '') {
            $this->json(['success' => false, 'error' => 'Pedido ou rate inválido.'], 400);
            return;
        }

        // Comprar etiqueta
        $result = $this->svc->purchaseLabel($rateId, 'PDF_4x6');

        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Falha ao comprar etiqueta.'], 400);
            return;
        }

        // Salvar no banco
        $this->ensureShippoEtiquetasTable();
        try {
            // Deletar anterior se existir
            $stDel = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
            $stDel->execute([$id]);

            $stIns = $this->connection->prepare("
                INSERT INTO shippo_etiquetas (pedido_id, shipment_id, transaction_id, rate_id, tracking_number, tracking_url, label_url, carrier, service_level, rate_amount, rate_currency, status, last_response_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'gerada', ?, NOW())
            ");

            $rateData = $result['rate'] ?? [];
            $carrier = '';
            $serviceLevel = '';
            $rateAmount = 0;
            $rateCurrency = 'USD';

            if (is_array($rateData)) {
                $carrier = (string) ($rateData['provider'] ?? '');
                $serviceLevel = (string) ($rateData['servicelevel_name'] ?? ($rateData['servicelevel']['name'] ?? ''));
                $rateAmount = (float) ($rateData['amount'] ?? 0);
                $rateCurrency = (string) ($rateData['currency'] ?? 'USD');
            }

            $stIns->execute([
                $id,
                (string) ($body['shipment_id'] ?? ''),
                $result['transaction_id'] ?? '',
                $rateId,
                $result['tracking_number'] ?? '',
                $result['tracking_url'] ?? '',
                $result['label_url'] ?? '',
                $carrier,
                $serviceLevel,
                $rateAmount,
                $rateCurrency,
                json_encode($result['data'] ?? []),
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Etiqueta gerada mas falhou ao salvar: ' . $e->getMessage()], 500);
            return;
        }

        $this->json([
            'success' => true,
            'tracking_number' => $result['tracking_number'] ?? '',
            'label_url' => $result['label_url'] ?? '',
            'tracking_url' => $result['tracking_url'] ?? '',
        ]);
    }

    /**
     * Regerar etiqueta (deletar e gerar novamente).
     */
    public function regerarEtiqueta(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $id = (int) $request->param('id');
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        // Deletar etiqueta existente
        $this->ensureShippoEtiquetasTable();
        try {
            $st = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
            $st->execute([$id]);
        } catch (\Exception $e) {}

        $this->json(['success' => true, 'message' => 'Etiqueta removida. Gere uma nova.']);
    }

    /**
     * Gerar etiquetas em massa.
     */
    public function gerarEtiquetasMassa(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $pedidoIds = $body['pedido_ids'] ?? [];

        if (!is_array($pedidoIds) || empty($pedidoIds)) {
            $this->json(['success' => false, 'error' => 'Nenhum pedido selecionado.'], 400);
            return;
        }

        if (!$this->svc->isConfigured()) {
            $this->json(['success' => false, 'error' => 'Shippo não configurado.'], 400);
            return;
        }

        $results = [];
        foreach ($pedidoIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;

            $pedido = $this->getPedidoCompleto($pid);
            if (!$pedido) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => 'Pedido não encontrado.'];
                continue;
            }

            $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));
            if (in_array($pais, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true)) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => 'Destino Brasil não permitido na Shippo.'];
                continue;
            }

            $addressFrom = $this->svc->getDefaultAddressFrom();
            $addressTo = [
                'name' => (string) ($pedido['cliente_nome'] ?? ''),
                'street1' => (string) ($pedido['_endereco'] ?? ''),
                'street2' => (string) ($pedido['_complemento'] ?? ''),
                'city' => (string) ($pedido['_cidade'] ?? ''),
                'state' => (string) ($pedido['_estado'] ?? ''),
                'zip' => (string) ($pedido['_cep'] ?? ''),
                'country' => $pais ?: 'US',
                'phone' => (string) ($pedido['cliente_telefone'] ?? ''),
                'email' => (string) ($pedido['cliente_email'] ?? ''),
            ];

            $peso = (float) ($pedido['peso_total'] ?? 0.5);
            $altura = (float) ($pedido['altura'] ?? 10);
            $largura = (float) ($pedido['largura'] ?? 10);
            $comprimento = (float) ($pedido['comprimento'] ?? 10);
            if ($peso <= 0) $peso = 0.5;
            if ($altura <= 0) $altura = 10;
            if ($largura <= 0) $largura = 10;
            if ($comprimento <= 0) $comprimento = 10;

            $parcel = [
                'length' => (string) $comprimento,
                'width' => (string) $largura,
                'height' => (string) $altura,
                'distance_unit' => 'cm',
                'weight' => (string) $peso,
                'mass_unit' => 'kg',
            ];

            $itens = $this->getItensPedido($pid);
            $customs = !empty($itens)
                ? $this->svc->buildCustomsDeclaration($itens)
                : $this->svc->buildCustomsDeclaration([['description' => 'Merchandise', 'quantity' => 1, 'net_weight' => (string) $peso, 'value_amount' => '50.00']]);

            $shipResult = $this->svc->createShipment($addressFrom, $addressTo, $parcel, $customs);
            if (!$shipResult['success']) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => $shipResult['error'] ?? 'Falha no shipment.'];
                continue;
            }

            // Pegar a rate mais barata
            $rates = $shipResult['rates'] ?? [];
            if (empty($rates)) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => 'Nenhuma rate disponível.'];
                continue;
            }

            usort($rates, function($a, $b) {
                return ((float)($a['amount'] ?? 999)) <=> ((float)($b['amount'] ?? 999));
            });
            $cheapestRate = $rates[0];
            $rateId = $cheapestRate['object_id'] ?? '';

            if ($rateId === '') {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => 'Rate sem ID.'];
                continue;
            }

            // Comprar etiqueta
            $labelResult = $this->svc->purchaseLabel($rateId, 'PDF_4x6');
            if (!$labelResult['success']) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => $labelResult['error'] ?? 'Falha ao gerar etiqueta.'];
                continue;
            }

            // Salvar
            $this->ensureShippoEtiquetasTable();
            try {
                $stDel = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
                $stDel->execute([$pid]);

                $stIns = $this->connection->prepare("
                    INSERT INTO shippo_etiquetas (pedido_id, shipment_id, transaction_id, rate_id, tracking_number, tracking_url, label_url, carrier, service_level, rate_amount, rate_currency, status, last_response_json, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'gerada', ?, NOW())
                ");
                $stIns->execute([
                    $pid,
                    $shipResult['shipment_id'] ?? '',
                    $labelResult['transaction_id'] ?? '',
                    $rateId,
                    $labelResult['tracking_number'] ?? '',
                    $labelResult['tracking_url'] ?? '',
                    $labelResult['label_url'] ?? '',
                    (string) ($cheapestRate['provider'] ?? ''),
                    (string) ($cheapestRate['servicelevel']['name'] ?? ($cheapestRate['servicelevel_name'] ?? '')),
                    (float) ($cheapestRate['amount'] ?? 0),
                    (string) ($cheapestRate['currency'] ?? 'USD'),
                    json_encode($labelResult['data'] ?? []),
                ]);

                $results[] = [
                    'pedido_id' => $pid,
                    'success' => true,
                    'tracking_number' => $labelResult['tracking_number'] ?? '',
                    'label_url' => $labelResult['label_url'] ?? '',
                ];
            } catch (\Exception $e) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => 'Falha ao salvar: ' . $e->getMessage()];
            }
        }

        $this->json(['success' => true, 'results' => $results]);
    }

    /**
     * Buscar rates para um pedido (sem comprar). Usado via AJAX.
     */
    public function rates(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $id = (int) $request->param('id');
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        // Chamar o mesmo fluxo de gerarEtiqueta que retorna rates
        $pedido = $this->getPedidoCompleto($id);
        if (!$pedido) {
            $this->json(['success' => false, 'error' => 'Pedido não encontrado.'], 404);
            return;
        }

        $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));
        if (in_array($pais, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true)) {
            $this->json(['success' => false, 'error' => 'Destino Brasil não permitido.'], 400);
            return;
        }

        $addressFrom = $this->svc->getDefaultAddressFrom();
        $addressTo = [
            'name' => (string) ($pedido['cliente_nome'] ?? ''),
            'street1' => (string) ($pedido['_endereco'] ?? ''),
            'street2' => (string) ($pedido['_complemento'] ?? ''),
            'city' => (string) ($pedido['_cidade'] ?? ''),
            'state' => (string) ($pedido['_estado'] ?? ''),
            'zip' => (string) ($pedido['_cep'] ?? ''),
            'country' => $pais ?: 'US',
            'phone' => (string) ($pedido['cliente_telefone'] ?? ''),
            'email' => (string) ($pedido['cliente_email'] ?? ''),
        ];

        $peso = (float) ($pedido['peso_total'] ?? 0.5);
        $altura = (float) ($pedido['altura'] ?? 10);
        $largura = (float) ($pedido['largura'] ?? 10);
        $comprimento = (float) ($pedido['comprimento'] ?? 10);
        if ($peso <= 0) $peso = 0.5;
        if ($altura <= 0) $altura = 10;
        if ($largura <= 0) $largura = 10;
        if ($comprimento <= 0) $comprimento = 10;

        $parcel = [
            'length' => (string) $comprimento,
            'width' => (string) $largura,
            'height' => (string) $altura,
            'distance_unit' => 'cm',
            'weight' => (string) $peso,
            'mass_unit' => 'kg',
        ];

        $itens = $this->getItensPedido($id);
        $customs = !empty($itens)
            ? $this->svc->buildCustomsDeclaration($itens)
            : $this->svc->buildCustomsDeclaration([['description' => 'Merchandise', 'quantity' => 1, 'net_weight' => (string) $peso, 'value_amount' => '50.00']]);

        $result = $this->svc->createShipment($addressFrom, $addressTo, $parcel, $customs);

        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Falha.'], 400);
            return;
        }

        $this->json([
            'success' => true,
            'shipment_id' => $result['shipment_id'] ?? '',
            'rates' => $result['rates'] ?? [],
        ]);
    }
}
