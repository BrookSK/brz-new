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

    /** @var array Diagnóstico do último funil de filtragem de pedidos. */
    private array $lastDiag = ['caixa_fechada' => 0, 'internacionais' => 0, 'ja_com_etiqueta' => 0, 'disponiveis' => 0];

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
                is_test TINYINT(1) NOT NULL DEFAULT 0,
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

        // Migração leve: garantir coluna is_test em tabelas já existentes.
        try {
            $cols = $this->getTableColumns('shippo_etiquetas');
            if (!empty($cols) && !in_array('is_test', $cols, true)) {
                $this->connection->exec("ALTER TABLE shippo_etiquetas ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
            }
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
        $colPais = $this->pickColumn($colsP, ['pais_destino', 'pais', 'pais_entrega', 'country', 'country_code', 'dest_country', 'endereco_pais']);

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

        // ─── Filtro: MOSTRAR APENAS pedidos com destino INTERNACIONAL confirmado ──
        // Brasil é o país PADRÃO do sistema. Portanto, um pedido só é internacional
        // quando existe uma indicação EXPLÍCITA de país estrangeiro (diferente de
        // Brasil e não vazio). Pedidos sem país definido são tratados como nacionais
        // e NÃO aparecem. Fontes de país consideradas (basta UMA ser internacional):
        //   - coluna pais_entrega da tabela pedidos
        //   - país do endereço vinculado (endereco_entrega_id -> enderecos.pais)
        //   - país de QUALQUER endereço do usuário (enderecos por usuario_id)
        $paisFilter = '';
        $BR_VALUES = "('BR','BRA','BRAZIL','BRASIL')";

        // Uma fonte é "internacional" quando: não está vazia E não é Brasil.
        $internacionalConds = [];

        // 1) Coluna(s) de país da própria tabela pedidos.
        foreach (['pais_entrega', 'country_entrega', 'shipping_country', 'pais_destino', 'dest_country', 'endereco_pais', 'pais', 'country', 'country_code'] as $cp) {
            if (in_array($cp, $colsP, true)) {
                $internacionalConds[] = "(TRIM(COALESCE(p." . $cp . ",'')) <> '' AND UPPER(TRIM(p." . $cp . ")) NOT IN " . $BR_VALUES . ")";
            }
        }

        // 2/3) País do endereço vinculado e de qualquer endereço do usuário.
        if ($this->tableExists('enderecos')) {
            $colsEnd = $this->getTableColumns('enderecos');
            $colEndPais = $this->pickColumn($colsEnd, ['pais', 'country', 'country_code', 'pais_code']);
            if ($colEndPais !== '') {
                // Endereço vinculado ao pedido é internacional
                if (in_array('endereco_entrega_id', $colsP, true) && in_array('id', $colsEnd, true)) {
                    $internacionalConds[] = "EXISTS (
                        SELECT 1 FROM enderecos e_vinc
                        WHERE e_vinc.id = p.endereco_entrega_id
                          AND TRIM(COALESCE(e_vinc." . $colEndPais . ",'')) <> ''
                          AND UPPER(TRIM(e_vinc." . $colEndPais . ")) NOT IN " . $BR_VALUES . "
                    )";
                }
                // Algum endereço do usuário é internacional
                if (in_array('usuario_id', $colsEnd, true)) {
                    $internacionalConds[] = "EXISTS (
                        SELECT 1 FROM enderecos e_user
                        WHERE e_user.usuario_id = p.usuario_id
                          AND TRIM(COALESCE(e_user." . $colEndPais . ",'')) <> ''
                          AND UPPER(TRIM(e_user." . $colEndPais . ")) NOT IN " . $BR_VALUES . "
                    )";
                }
            }
        }

        // O pedido aparece somente se PELO MENOS UMA fonte for internacional.
        if (!empty($internacionalConds)) {
            $paisFilter .= " AND ( " . implode(' OR ', $internacionalConds) . " )";
        } else {
            // Sem nenhuma fonte de país no schema: não há como confirmar internacional.
            // Por segurança (Brasil é o padrão), não retornar nada.
            $paisFilter .= " AND 1 = 0";
        }

        // Excluir pedidos removidos (soft-delete) e arquivados, quando essas colunas existirem.
        $statusExtraFilter = '';
        if (in_array('deleted_at', $colsP, true)) {
            $statusExtraFilter .= " AND p.deleted_at IS NULL";
        }
        if (in_array('arquivado', $colsP, true)) {
            $statusExtraFilter .= " AND COALESCE(p.arquivado, 0) = 0";
        }

        // Critério central de "caixa fechada": status produto_consolidado/consolidado.
        $caixaFechadaWhere = "LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado')";
        // Base = caixa fechada + exclusão de removidos/arquivados.
        $baseWhere = $caixaFechadaWhere . $statusExtraFilter;

        // ─── Filtro: EXIGIR peso e medidas preenchidos ───────────────────────
        // Regra: só aparece o pedido pronto para etiqueta (peso > 0 e as três
        // dimensões > 0). Se alguma coluna não existir no schema, essa condição
        // é omitida (não há como validar aquilo que não existe).
        $pesoMedidasFilter = '';
        if (in_array('peso_total', $colsP, true)) {
            $pesoMedidasFilter .= " AND COALESCE(p.peso_total, 0) > 0";
        }
        if (in_array('altura', $colsP, true)) {
            $pesoMedidasFilter .= " AND COALESCE(p.altura, 0) > 0";
        }
        if (in_array('largura', $colsP, true)) {
            $pesoMedidasFilter .= " AND COALESCE(p.largura, 0) > 0";
        }
        if (in_array('comprimento', $colsP, true)) {
            $pesoMedidasFilter .= " AND COALESCE(p.comprimento, 0) > 0";
        }

        // Observação: NÃO excluímos pedidos que já possuem etiqueta. A regra do
        // negócio pede listar TODOS os pedidos internacionais em Caixa Fechada
        // com peso e medidas preenchidos, prontos para gerar (ou regerar) etiqueta.
        $sql = "
            SELECT p.id AS pedido_id, {$colUserNome} AS cliente_nome, p.usuario_id, p.created_at
                   {$extraSelect},
                   {$colUserEmail} AS cliente_email, {$colUserTel} AS cliente_telefone
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE {$baseWhere}
              {$paisFilter}
              {$pesoMedidasFilter}
            ORDER BY p.created_at DESC
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

            // Diagnóstico do funil (para explicar lista vazia sem acesso ao banco).
            $this->lastDiag = ['caixa_fechada' => 0, 'internacionais' => 0, 'ja_com_etiqueta' => 0, 'disponiveis' => count($rows)];
            try {
                // 1) Total em caixa fechada (sem exigir internacional nem ausência de etiqueta).
                $stCf = $this->connection->query("SELECT COUNT(*) FROM pedidos p WHERE {$caixaFechadaWhere}");
                $this->lastDiag['caixa_fechada'] = (int) ($stCf ? $stCf->fetchColumn() : 0);
            } catch (\Exception $e) {}
            try {
                // 2) Caixa fechada + internacional (sem exigir peso/medidas).
                $stIntl = $this->connection->prepare("SELECT COUNT(*) FROM pedidos p WHERE {$baseWhere} {$paisFilter}");
                $stIntl->execute();
                $this->lastDiag['internacionais'] = (int) $stIntl->fetchColumn();
            } catch (\Exception $e) {}
            // 3) Internacionais em caixa fechada que ainda NÃO têm peso/medidas completos
            //    (ficam de fora da lista até serem preenchidos).
            $this->lastDiag['sem_peso_medidas'] = max(0, $this->lastDiag['internacionais'] - $this->lastDiag['disponiveis']);

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

        // Colunas de endereço/país que possam existir DIRETAMENTE no pedido (schemas variados).
        $colEndereco = $this->pickColumn($colsP, ['endereco_entrega', 'endereco', 'address', 'street1']);
        $colCidade = $this->pickColumn($colsP, ['cidade_entrega', 'cidade', 'city']);
        $colEstado = $this->pickColumn($colsP, ['estado_entrega', 'estado', 'state']);
        $colCep = $this->pickColumn($colsP, ['cep_entrega', 'cep', 'zip', 'postal_code']);
        $colPais = $this->pickColumn($colsP, ['pais_entrega', 'pais_destino', 'pais', 'country', 'country_code', 'dest_country', 'endereco_pais']);
        $colComplemento = $this->pickColumn($colsP, ['complemento_entrega', 'complemento', 'address2', 'street2']);
        $colNumero = $this->pickColumn($colsP, ['numero_entrega', 'numero', 'number']);

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

            // 1) Tentar endereço direto no pedido (quando o schema tiver essas colunas).
            $endereco = $colEndereco !== '' ? (string) ($row[$colEndereco] ?? '') : '';
            $cidade   = $colCidade   !== '' ? (string) ($row[$colCidade] ?? '')   : '';
            $estado   = $colEstado   !== '' ? (string) ($row[$colEstado] ?? '')   : '';
            $cep      = $colCep      !== '' ? (string) ($row[$colCep] ?? '')      : '';
            $pais     = $colPais     !== '' ? (string) ($row[$colPais] ?? '')     : '';
            $complemento = $colComplemento !== '' ? (string) ($row[$colComplemento] ?? '') : '';
            $numero   = $colNumero   !== '' ? (string) ($row[$colNumero] ?? '')   : '';

            // 2) Se faltarem dados de endereço, buscar na tabela enderecos.
            //    Prioridade: endereço vinculado ao pedido (endereco_entrega_id);
            //    senão, endereço internacional do usuário; senão, endereço mais recente.
            $precisaCompletar = ($endereco === '' || $cidade === '' || $cep === '' || $pais === '');
            if ($precisaCompletar && $this->tableExists('enderecos')) {
                $rowEnd = null;

                $endId = (int) ($row['endereco_entrega_id'] ?? 0);
                if ($endId > 0) {
                    $stE = $this->connection->prepare('SELECT * FROM enderecos WHERE id = ? LIMIT 1');
                    $stE->execute([$endId]);
                    $rowEnd = $stE->fetch(\PDO::FETCH_ASSOC) ?: null;
                }

                // Fallback: endereço INTERNACIONAL do usuário (não Brasil), o mais recente.
                if (!$rowEnd) {
                    $uid = (int) ($row['usuario_id'] ?? 0);
                    if ($uid > 0) {
                        $stE = $this->connection->prepare(
                            "SELECT * FROM enderecos
                             WHERE usuario_id = ?
                               AND TRIM(COALESCE(pais,'')) <> ''
                               AND UPPER(TRIM(pais)) NOT IN ('BR','BRA','BRAZIL','BRASIL')
                             ORDER BY principal DESC, id DESC LIMIT 1"
                        );
                        $stE->execute([$uid]);
                        $rowEnd = $stE->fetch(\PDO::FETCH_ASSOC) ?: null;
                    }
                }

                if (is_array($rowEnd) && !empty($rowEnd)) {
                    if ($endereco === '') $endereco = (string) ($rowEnd['endereco'] ?? ($rowEnd['logradouro'] ?? ''));
                    if ($cidade === '')   $cidade   = (string) ($rowEnd['cidade'] ?? '');
                    if ($estado === '')   $estado   = (string) ($rowEnd['estado'] ?? '');
                    if ($cep === '')      $cep      = (string) ($rowEnd['cep'] ?? '');
                    if ($pais === '')     $pais     = (string) ($rowEnd['pais'] ?? '');
                    if ($complemento === '') $complemento = (string) ($rowEnd['complemento'] ?? '');
                    if ($numero === '')   $numero   = (string) ($rowEnd['numero'] ?? '');
                }
            }

            // Normalizar campos de endereço para uso no addressTo do Shippo.
            // Concatena número ao logradouro quando existir (Shippo espera street1 completo).
            $street1 = trim($endereco . ($numero !== '' ? (', ' . $numero) : ''));
            $row['_endereco'] = $street1;
            $row['_cidade'] = $cidade;
            $row['_estado'] = $estado;
            $row['_cep'] = $cep;
            $row['_pais'] = $pais !== '' ? $pais : 'US';
            $row['_complemento'] = $complemento;

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
            'diag' => $this->lastDiag,
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
            $this->json(['success' => false, 'error' => __('admin.shippo.invalid_order_id', 'ID do pedido inválido.')], 400);
            return;
        }

        if (!$this->svc->isConfigured()) {
            $this->json(['success' => false, 'error' => __('admin.shippo.not_configured_add_token', 'Shippo não configurado. Adicione o token em Configurações.')], 400);
            return;
        }

        $pedido = $this->getPedidoCompleto($id);
        if (!$pedido) {
            $this->json(['success' => false, 'error' => __('admin.shippo.order_not_found', 'Pedido não encontrado.')], 404);
            return;
        }

        // País destino não pode ser Brasil
        $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));
        if (in_array($pais, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true)) {
            $this->json(['success' => false, 'error' => __('admin.shippo.no_brazil_shipping', 'Shippo não atende envios para o Brasil. Use Correio Internacional.')], 400);
            return;
        }

        // Validar endereço mínimo exigido pela Shippo (evita erro genérico "incomplete address").
        $faltando = [];
        if (trim((string) ($pedido['_endereco'] ?? '')) === '') $faltando[] = __('admin.shippo.field_address_street', 'endereço/rua');
        if (trim((string) ($pedido['_cidade'] ?? '')) === '')   $faltando[] = __('admin.shippo.field_city', 'cidade');
        if (trim((string) ($pedido['_cep'] ?? '')) === '')      $faltando[] = __('admin.shippo.field_zip', 'CEP/ZIP');
        if ($pais === '')                                        $faltando[] = __('admin.shippo.field_country', 'país');
        if (!empty($faltando)) {
            $this->json([
                'success' => false,
                'error' => __('admin.shippo.incomplete_address', 'Endereço incompleto para envio internacional. Preencha: {fields}. Edite o endereço do pedido e tente novamente.', ['fields' => implode(', ', $faltando)]),
            ], 400);
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
            $this->json(['success' => false, 'error' => $result['error'] ?? __('admin.shippo.create_shipment_failed', 'Falha ao criar shipment.')], 400);
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
            $this->json(['success' => false, 'error' => __('admin.shippo.invalid_order_or_rate', 'Pedido ou rate inválido.')], 400);
            return;
        }

        // Comprar etiqueta
        $result = $this->svc->purchaseLabel($rateId, 'PDF_4x6');

        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? __('admin.shippo.purchase_label_failed', 'Falha ao comprar etiqueta.')], 400);
            return;
        }

        // Salvar no banco
        $this->ensureShippoEtiquetasTable();
        try {
            // Deletar anterior se existir
            $stDel = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
            $stDel->execute([$id]);

            $stIns = $this->connection->prepare("
                INSERT INTO shippo_etiquetas (pedido_id, shipment_id, transaction_id, rate_id, tracking_number, tracking_url, label_url, carrier, service_level, rate_amount, rate_currency, status, is_test, last_response_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'gerada', ?, ?, NOW())
            ");

            $isTest = !empty($result['data']['test']) ? 1 : 0;
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
                $isTest,
                json_encode($result['data'] ?? []),
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => __('admin.shippo.label_saved_failed', 'Etiqueta gerada mas falhou ao salvar: ') . $e->getMessage()], 500);
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
            $this->json(['success' => false, 'error' => __('admin.shippo.invalid_id', 'ID inválido.')], 400);
            return;
        }

        // Deletar etiqueta existente
        $this->ensureShippoEtiquetasTable();
        try {
            $st = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
            $st->execute([$id]);
        } catch (\Exception $e) {}

        $this->json(['success' => true, 'message' => __('admin.shippo.label_removed_generate_new', 'Etiqueta removida. Gere uma nova.')]);
    }

    /**
     * Gerar etiquetas em massa.
     */
    public function gerarEtiquetasMassa(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        // Cada pedido faz chamadas de rede à API Shippo (createShipment + purchaseLabel),
        // que podem levar vários segundos. Ao processar múltiplos pedidos em série, o tempo
        // padrão de execução (30s) pode ser estourado, matando o script no meio e devolvendo
        // HTML de erro no lugar do JSON (quebra o r.json() no front). Elevamos o limite.
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        // Buffer de saída: garante que nenhum warning/notice do PHP contamine a resposta JSON.
        // Descartamos qualquer saída acidental logo antes de emitir o JSON final.
        if (function_exists('ob_get_level') && ob_get_level() === 0) {
            ob_start();
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $pedidoIds = $body['pedido_ids'] ?? [];

        if (!is_array($pedidoIds) || empty($pedidoIds)) {
            $this->json(['success' => false, 'error' => __('admin.shippo.no_order_selected', 'Nenhum pedido selecionado.')], 400);
            return;
        }

        if (!$this->svc->isConfigured()) {
            $this->json(['success' => false, 'error' => __('admin.shippo.not_configured', 'Shippo não configurado.')], 400);
            return;
        }

        $results = [];
        foreach ($pedidoIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;

            $pedido = $this->getPedidoCompleto($pid);
            if (!$pedido) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.order_not_found', 'Pedido não encontrado.')];
                continue;
            }

            $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));
            if (in_array($pais, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true)) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.brazil_not_allowed_shippo', 'Destino Brasil não permitido na Shippo.')];
                continue;
            }

            // Validar endereço mínimo exigido pela Shippo (evita erro genérico "incomplete address").
            $faltando = [];
            if (trim((string) ($pedido['_endereco'] ?? '')) === '') $faltando[] = __('admin.shippo.field_address_street', 'endereço/rua');
            if (trim((string) ($pedido['_cidade'] ?? '')) === '')   $faltando[] = __('admin.shippo.field_city', 'cidade');
            if (trim((string) ($pedido['_cep'] ?? '')) === '')      $faltando[] = __('admin.shippo.field_zip', 'CEP/ZIP');
            if ($pais === '')                                        $faltando[] = __('admin.shippo.field_country', 'país');
            if (!empty($faltando)) {
                $results[] = [
                    'pedido_id' => $pid,
                    'success' => false,
                    'error' => __('admin.shippo.incomplete_address', 'Endereço incompleto para envio internacional. Preencha: {fields}. Edite o endereço do pedido e tente novamente.', ['fields' => implode(', ', $faltando)]),
                ];
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

            // Verificar modo de geração configurado
            $cfg = $this->svc->getShippoConfig();
            $massaMode = (string) ($cfg['shippo_massa_mode'] ?? 'cheapest');
            $carrierAccount = (string) ($cfg['shippo_carrier_account'] ?? '');
            $servicelevelToken = (string) ($cfg['shippo_servicelevel_token'] ?? '');
            $labelFileType = (string) ($cfg['shippo_label_file_type'] ?? 'PDF_4x6');

            if ($massaMode === 'single_call' && $carrierAccount !== '' && $servicelevelToken !== '') {
                // Fluxo 2 etapas com carrier/service pré-definido: cria shipment (PURCHASE) → filtra rate pelo service level → compra
                $shipResult = $this->svc->createShipment($addressFrom, $addressTo, $parcel, $customs);
                if (!$shipResult['success']) {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => $shipResult['error'] ?? __('admin.shippo.shipment_failed', 'Falha no shipment.')];
                    continue;
                }

                $rates = $shipResult['rates'] ?? [];
                // Filtrar pelo service level configurado
                $matchedRate = null;
                foreach ($rates as $rate) {
                    $rateService = $rate['servicelevel']['token'] ?? ($rate['servicelevel_token'] ?? '');
                    $rateCarrier = $rate['carrier_account'] ?? '';
                    if (strtolower($rateService) === strtolower($servicelevelToken)) {
                        $matchedRate = $rate;
                        break;
                    }
                }
                // Se não encontrou pelo service level exato, tentar pelo carrier account
                if (!$matchedRate) {
                    foreach ($rates as $rate) {
                        if (($rate['carrier_account'] ?? '') === $carrierAccount) {
                            $matchedRate = $rate;
                            break;
                        }
                    }
                }
                // Fallback: pegar o primeiro disponível
                if (!$matchedRate && !empty($rates)) {
                    $matchedRate = $rates[0];
                }

                if (!$matchedRate) {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.no_rate_for_carrier', 'Nenhuma rate disponível para o carrier/serviço configurado.')];
                    continue;
                }

                $rateId = $matchedRate['object_id'] ?? '';
                if ($rateId === '') {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.rate_no_id', 'Rate sem ID.')];
                    continue;
                }

                $labelResult = $this->svc->purchaseLabel($rateId, $labelFileType ?: 'PDF_4x6');
                if (!$labelResult['success']) {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => $labelResult['error'] ?? __('admin.shippo.label_generation_failed', 'Falha ao gerar etiqueta.')];
                    continue;
                }

                // Salvar
                $this->ensureShippoEtiquetasTable();
                try {
                    $stDel = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
                    $stDel->execute([$pid]);

                    $stIns = $this->connection->prepare("
                        INSERT INTO shippo_etiquetas (pedido_id, shipment_id, transaction_id, rate_id, tracking_number, tracking_url, label_url, carrier, service_level, rate_amount, rate_currency, status, is_test, last_response_json, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'gerada', ?, ?, NOW())
                    ");
                    $stIns->execute([
                        $pid,
                        $shipResult['shipment_id'] ?? '',
                        $labelResult['transaction_id'] ?? '',
                        $rateId,
                        $labelResult['tracking_number'] ?? '',
                        $labelResult['tracking_url'] ?? '',
                        $labelResult['label_url'] ?? '',
                        (string) ($matchedRate['provider'] ?? ''),
                        (string) ($matchedRate['servicelevel']['name'] ?? ($matchedRate['servicelevel_name'] ?? '')),
                        (float) ($matchedRate['amount'] ?? 0),
                        (string) ($matchedRate['currency'] ?? 'USD'),
                        !empty($labelResult['data']['test']) ? 1 : 0,
                        json_encode($labelResult['data'] ?? []),
                    ]);

                    $results[] = [
                        'pedido_id' => $pid,
                        'success' => true,
                        'tracking_number' => $labelResult['tracking_number'] ?? '',
                        'label_url' => $labelResult['label_url'] ?? '',
                    ];
                } catch (\Exception $e) {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.save_failed', 'Falha ao salvar: ') . $e->getMessage()];
                }
            } else {
                // Modo cotação: cria shipment, pega rates, escolhe o mais barato
                $shipResult = $this->svc->createShipment($addressFrom, $addressTo, $parcel, $customs);
                if (!$shipResult['success']) {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => $shipResult['error'] ?? __('admin.shippo.shipment_failed', 'Falha no shipment.')];
                    continue;
                }

                $rates = $shipResult['rates'] ?? [];
                if (empty($rates)) {
                    $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.no_rate_available', 'Nenhuma rate disponível.')];
                    continue;
                }

                usort($rates, function($a, $b) {
                    return ((float)($a['amount'] ?? 999)) <=> ((float)($b['amount'] ?? 999));
                });
            $cheapestRate = $rates[0];
            $rateId = $cheapestRate['object_id'] ?? '';

            if ($rateId === '') {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.rate_no_id', 'Rate sem ID.')];
                continue;
            }

            // Comprar etiqueta
            $labelResult = $this->svc->purchaseLabel($rateId, $labelFileType ?: 'PDF_4x6');
            if (!$labelResult['success']) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => $labelResult['error'] ?? __('admin.shippo.label_generation_failed', 'Falha ao gerar etiqueta.')];
                continue;
            }

            // Salvar
            $this->ensureShippoEtiquetasTable();
            try {
                $stDel = $this->connection->prepare("DELETE FROM shippo_etiquetas WHERE pedido_id = ?");
                $stDel->execute([$pid]);

                $stIns = $this->connection->prepare("
                    INSERT INTO shippo_etiquetas (pedido_id, shipment_id, transaction_id, rate_id, tracking_number, tracking_url, label_url, carrier, service_level, rate_amount, rate_currency, status, is_test, last_response_json, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'gerada', ?, ?, NOW())
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
                    !empty($labelResult['data']['test']) ? 1 : 0,
                    json_encode($labelResult['data'] ?? []),
                ]);

                $results[] = [
                    'pedido_id' => $pid,
                    'success' => true,
                    'tracking_number' => $labelResult['tracking_number'] ?? '',
                    'label_url' => $labelResult['label_url'] ?? '',
                ];
            } catch (\Exception $e) {
                $results[] = ['pedido_id' => $pid, 'success' => false, 'error' => __('admin.shippo.save_failed', 'Falha ao salvar: ') . $e->getMessage()];
            }
            } // fim else (modo cotação)
        }

        // Descarta qualquer saída acidental (warnings/notices) acumulada no buffer antes do JSON.
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_clean();
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
            $this->json(['success' => false, 'error' => __('admin.shippo.invalid_id', 'ID inválido.')], 400);
            return;
        }

        // Chamar o mesmo fluxo de gerarEtiqueta que retorna rates
        $pedido = $this->getPedidoCompleto($id);
        if (!$pedido) {
            $this->json(['success' => false, 'error' => __('admin.shippo.order_not_found', 'Pedido não encontrado.')], 404);
            return;
        }

        $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));
        if (in_array($pais, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true)) {
            $this->json(['success' => false, 'error' => __('admin.shippo.brazil_not_allowed', 'Destino Brasil não permitido.')], 400);
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
            $this->json(['success' => false, 'error' => $result['error'] ?? __('admin.shippo.failure', 'Falha.')], 400);
            return;
        }

        $this->json([
            'success' => true,
            'shipment_id' => $result['shipment_id'] ?? '',
            'rates' => $result['rates'] ?? [],
        ]);
    }
}
