<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\CorreiosPacketService;
use App\Models\PedidoEcommerce;
use Config\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

class AdminCorreiosMundialController extends Controller {
    private CorreiosPacketService $svc;
    private $connection;

    public function __construct() {
        $this->svc = new CorreiosPacketService();
        $this->connection = Database::getConnection();
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function ensurePacketEtiquetasTable(): void {
        try {
            if ($this->tableExists('correios_packet_etiquetas')) {
                return;
            }
            $sql = "CREATE TABLE IF NOT EXISTS correios_packet_etiquetas (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  pedido_id INT NOT NULL,\n"
                . "  customer_control_code VARCHAR(120) NULL,\n"
                . "  tracking_number VARCHAR(120) NULL,\n"
                . "  status VARCHAR(30) DEFAULT 'gerada',\n"
                . "  last_request_json LONGTEXT NULL,\n"
                . "  last_response_json LONGTEXT NULL,\n"
                . "  last_http_code INT NULL,\n"
                . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
                . "  updated_at TIMESTAMP NULL DEFAULT NULL,\n"
                . "  UNIQUE KEY uniq_correios_packet_etiquetas_pedido_id (pedido_id),\n"
                . "  KEY idx_correios_packet_etiquetas_tracking_number (tracking_number)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->connection->exec($sql);
        } catch (\Exception $e) {
        }
    }

    private function ensurePacketBillsTable(): void {
        try {
            if ($this->tableExists('correios_packet_bills')) {
                return;
            }

            $sql = "CREATE TABLE IF NOT EXISTS correios_packet_bills (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  status VARCHAR(30) DEFAULT 'pending',\n"
                . "  request_id VARCHAR(120) NULL,\n"
                . "  cn38_code VARCHAR(120) NULL,\n"
                . "  containers_json LONGTEXT NULL,\n"
                . "  tracking_count INT NULL,\n"
                . "  last_request_json LONGTEXT NULL,\n"
                . "  last_response_json LONGTEXT NULL,\n"
                . "  last_http_code INT NULL,\n"
                . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
                . "  updated_at TIMESTAMP NULL DEFAULT NULL,\n"
                . "  KEY idx_correios_packet_bills_status (status),\n"
                . "  KEY idx_correios_packet_bills_request_id (request_id),\n"
                . "  KEY idx_correios_packet_bills_cn38_code (cn38_code)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->connection->exec($sql);
        } catch (\Exception $e) {
        }
    }

    private function ensureContainersHasBillIdColumn(): void {
        try {
            $this->ensurePacketContainersTable();
            if (!$this->tableExists('correios_packet_containers')) {
                return;
            }

            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'correios_packet_containers' AND COLUMN_NAME = 'bill_id'");
            $stmt->execute();
            $exists = ((int) $stmt->fetchColumn()) > 0;
            if ($exists) {
                return;
            }

            $this->connection->exec("ALTER TABLE correios_packet_containers ADD COLUMN bill_id INT NULL DEFAULT NULL");
            $this->connection->exec("ALTER TABLE correios_packet_containers ADD KEY idx_correios_packet_containers_bill_id (bill_id)");
        } catch (\Exception $e) {
        }
    }

    private function ensurePacketContainersTable(): void {
        try {
            if ($this->tableExists('correios_packet_containers')) {
                return;
            }

            $sql = "CREATE TABLE IF NOT EXISTS correios_packet_containers (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  dispatch_number INT NOT NULL,\n"
                . "  origin_country VARCHAR(2) NOT NULL DEFAULT 'US',\n"
                . "  origin_operator_name VARCHAR(4) NOT NULL,\n"
                . "  destination_operator_name VARCHAR(4) NOT NULL,\n"
                . "  postal_category_code VARCHAR(1) NOT NULL DEFAULT 'A',\n"
                . "  service_subclass_code VARCHAR(2) NOT NULL DEFAULT 'NX',\n"
                . "  unit_type VARCHAR(1) NOT NULL DEFAULT '2',\n"
                . "  awb VARCHAR(50) NULL,\n"
                . "  triage_group VARCHAR(3) NULL,\n"
                . "  tracking_numbers_json LONGTEXT NULL,\n"
                . "  unit_code VARCHAR(120) NULL,\n"
                . "  status VARCHAR(30) DEFAULT 'created',\n"
                . "  last_request_json LONGTEXT NULL,\n"
                . "  last_response_json LONGTEXT NULL,\n"
                . "  last_http_code INT NULL,\n"
                . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
                . "  updated_at TIMESTAMP NULL DEFAULT NULL,\n"
                . "  UNIQUE KEY uniq_correios_packet_containers_dispatch_number (dispatch_number),\n"
                . "  KEY idx_correios_packet_containers_unit_code (unit_code)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->connection->exec($sql);
        } catch (\Exception $e) {
        }
    }

    private function ensureEtiquetasHasContainerIdColumn(): void {
        try {
            $this->ensurePacketEtiquetasTable();
            if (!$this->tableExists('correios_packet_etiquetas')) {
                return;
            }
            $cols = [];
            $st = $this->connection->query('DESCRIBE correios_packet_etiquetas');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (is_array($cols) && in_array('container_id', $cols, true)) {
                return;
            }
            $this->connection->exec('ALTER TABLE correios_packet_etiquetas ADD COLUMN container_id INT NULL DEFAULT NULL, ADD KEY idx_correios_packet_etiquetas_container_id (container_id)');
        } catch (\Exception $e) {
        }
    }

    private function parseBulkTokens(string $raw): array {
        $raw = trim((string) $raw);
        if ($raw === '') return [];
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $parts = preg_split('/[\n\t\s,;]+/', $raw);
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p === '') continue;
            $out[] = $p;
        }
        return array_values(array_unique($out));
    }

    private function getPacketGroups(): array {
        return [
            "packet_express" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional do Rio de Janeiro – SE/RJ",
                "location" => "Ponta do Galeão, s/n, 2º andar – TECA Correios",
                "city" => "Galeão",
                "zipcode" => "21941-974",
                "region" => "Ilha do Governador, Rio de Janeiro/RJ",
                "cnpj" => "34.028.316/7189-93"
            ],
            "packet_standard_grupo_1" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional de São Paulo – SE/SPM",
                "location" => "Rua Mergenthaler, 568, bloco III, 5º andar, Vila Leopoldina",
                "zipcode" => "05311-900",
                "region" => "São Paulo/SP",
                "cnpj" => "34.028.316/7105-85"
            ],
            "packet_standard_grupo_2" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional em Valinhos - SE/SPI",
                "location" => "Rua Clark, 3401, Macuco",
                "zipcode" => "13279-400",
                "region" => "Valinhos/SP",
                "cnpj" => "34.028.316/9395-74"
            ],
            "packet_standard_grupo_3" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional do Rio de Janeiro – SE/RJ",
                "location" => "Ponta do Galeão, s/n, 2º andar – TECA Correios",
                "city" => "Galeão",
                "zipcode" => "21941-974",
                "region" => "Ilha do Governador, Rio de Janeiro/RJ",
                "cnpj" => "34.028.316/7189-93"
            ],
            "packet_standard_grupo_4" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional de Curitiba - SE/PR",
                "location" => "Rua Salgado Filho, 476, Jardim Amélia",
                "zipcode" => "83330-972",
                "region" => "Pinhais/PR",
                "cnpj" => "34.028.316/9148-22"
            ],
            "packet_standard_grupo_5" => [
                "company" => "Empresa Brasileira de Correios e Telégrafos",
                "center" => "Centro Internacional de Curitiba - SE/PR",
                "location" => "Rua Salgado Filho, 476, Jardim Amélia",
                "zipcode" => "83330-972",
                "region" => "Pinhais/PR",
                "cnpj" => "34.028.316/9148-22"
            ]
        ];
    }

    private function onlyDigits(string $v): string {
        $v = (string) $v;
        $v = preg_replace('/\D+/', '', $v);
        return (string) $v;
    }

    private function packetFriendlyError(string $msg, $raw = null): string {
        $m = trim((string) $msg);
        $mUpper = strtoupper($m);

        // Códigos conhecidos
        if (strpos($mUpper, 'SUA-121') !== false) {
            return 'CPF do destinatário inválido. Confira se o CPF está correto e regular na Receita Federal.';
        }

        // Mensagens conhecidas (inglês)
        $mLower = strtolower($m);
        if (strpos($mLower, 'cpf') !== false && strpos($mLower, 'regular') !== false) {
            return 'CPF do destinatário não está regular na Receita Federal. O pedido não pode gerar etiqueta até regularizar.';
        }
        if (strpos($mLower, 'cpf number is invalid') !== false) {
            return 'CPF do destinatário inválido. Confira o número informado.';
        }
        if (strpos($mLower, 'cnpj') !== false && strpos($mLower, 'invalid') !== false) {
            return 'CNPJ do destinatário inválido. Confira o número informado.';
        }
        if (strpos($mLower, 'zip') !== false && (strpos($mLower, 'invalid') !== false || strpos($mLower, 'must') !== false)) {
            return 'CEP do destinatário inválido. Informe um CEP válido com 8 dígitos.';
        }
        if (strpos($mLower, 'phone') !== false && (strpos($mLower, 'invalid') !== false || strpos($mLower, 'digits') !== false)) {
            return 'Telefone do destinatário inválido. Informe DDD + número (10 ou 11 dígitos), sem +55.';
        }
        if (strpos($mLower, 'hs') !== false && (strpos($mLower, 'not') !== false || strpos($mLower, 'allowed') !== false)) {
            return 'Não foi possível gerar o rastreio porque o NCM (HS Code) de algum item não é permitido no Brasil. Revise o NCM do produto.';
        }

        // Tenta extrair mensagens em listas/estruturas comuns
        if (is_array($raw)) {
            foreach (['message', 'error', 'mensagem', 'erro'] as $k) {
                if (!empty($raw[$k]) && is_string($raw[$k])) {
                    $candidate = trim((string) $raw[$k]);
                    if ($candidate !== '' && $candidate !== $m) {
                        $m = $candidate;
                        $mLower = strtolower($m);
                    }
                }
            }

            if (!empty($raw['errors']) && is_array($raw['errors'])) {
                $first = $raw['errors'][0] ?? null;
                if (is_string($first) && trim($first) !== '') {
                    $m = trim($first);
                }
                if (is_array($first)) {
                    $cand = (string) ($first['message'] ?? ($first['error'] ?? ($first['mensagem'] ?? '')));
                    if (trim($cand) !== '') {
                        $m = trim($cand);
                    }
                }
            }
        }

        // Fallback genérico em PT
        if ($m === '') {
            return 'Não foi possível gerar a etiqueta. Verifique os dados do destinatário e dos produtos e tente novamente.';
        }

        return 'Não foi possível gerar a etiqueta: ' . $m;
    }

    private function pickFirstNonEmpty(array $row, array $keys): string {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row)) {
                $v = trim((string) ($row[$k] ?? ''));
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    private function buildRecipientFromPedido(array $pedido): array {
        $destNome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? ''));
        $destEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ''));
        $destTel = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ''));

        $destDoc = $this->pickFirstNonEmpty($pedido, ['cliente_cpf_cnpj', 'cpf_cnpj', 'cpfCnpj', 'cpf', 'cnpj', 'documento', 'document']);
        if ($destDoc === '' && isset($pedido['cliente']) && is_array($pedido['cliente'])) {
            $destDoc = $this->pickFirstNonEmpty((array) $pedido['cliente'], ['cpf_cnpj', 'cpfCnpj', 'cpf', 'cnpj', 'documento', 'document']);
        }
        if ($destDoc === '') {
            $uid = (int) ($pedido['usuario_id'] ?? 0);
            if ($uid > 0 && $this->tableExists('usuarios')) {
                try {
                    $colsU = [];
                    try {
                        $stCols = $this->connection->query('DESCRIBE usuarios');
                        $colsU = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsU = [];
                    }

                    $docCol = null;
                    foreach (['cpf_cnpj', 'cpfCnpj', 'documento', 'document', 'cpf', 'cnpj'] as $c) {
                        if (in_array($c, $colsU, true)) {
                            $docCol = $c;
                            break;
                        }
                    }

                    if ($docCol) {
                        $stU = $this->connection->prepare('SELECT ' . $docCol . ' AS documento FROM usuarios WHERE id = ? LIMIT 1');
                        $stU->execute([$uid]);
                        $rowU = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $destDoc = (string) ($rowU['documento'] ?? '');
                    }
                } catch (\Exception $e) {
                }
            }
        }
        $destDocDigits = $this->onlyDigits($destDoc);

        $docType = 'CPF';
        if (strlen($destDocDigits) === 14) {
            $docType = 'CNPJ';
        }

        // Em alguns bancos o CPF/CNPJ pode vir como número e perder zeros à esquerda.
        // Para reduzir falso-positivo de documento inválido, completar com zeros quando fizer sentido.
        if ($docType === 'CPF' && $destDocDigits !== '' && strlen($destDocDigits) < 11) {
            $destDocDigits = str_pad($destDocDigits, 11, '0', STR_PAD_LEFT);
        }
        if ($docType === 'CNPJ' && $destDocDigits !== '' && strlen($destDocDigits) < 14) {
            $destDocDigits = str_pad($destDocDigits, 14, '0', STR_PAD_LEFT);
        }

        $cep = $this->onlyDigits((string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? '')));
        $logradouro = (string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''));
        $numero = (string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''));
        $complemento = (string) ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''));
        $cidade = (string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ''));
        $uf = (string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? ''));

        $telDigits = $this->onlyDigits($destTel);
        if (strlen($telDigits) >= 12 && strpos($telDigits, '55') === 0) {
            $telDigits = substr($telDigits, 2);
        }
        if (strlen($telDigits) > 11) {
            $telDigits = substr($telDigits, -11);
        }

        return [
            'recipientName' => substr(trim($destNome), 0, 70),
            'recipientDocumentType' => $docType,
            'recipientDocumentNumber' => substr($destDocDigits, 0, 14),
            'recipientAddress' => substr(trim($logradouro), 0, 170),
            'recipientAddressNumber' => substr(trim((string) $numero), 0, 10),
            'recipientAddressComplement' => substr(trim((string) $complemento), 0, 50),
            'recipientCityName' => substr(trim((string) $cidade), 0, 100),
            'recipientState' => substr(strtoupper(trim((string) $uf)), 0, 2),
            'recipientZipCode' => substr($cep, 0, 8),
            'recipientEmail' => substr(trim((string) $destEmail), 0, 50),
            'recipientPhoneNumber' => $telDigits,
        ];
    }

    private function buildSenderFromConfig(): array {
        // Reutiliza ShipStation from_address_json como remetente (EUA).
        $fromJson = '';
        try {
            $st = $this->connection->query("SELECT 1");
        } catch (\Exception $e) {
        }

        try {
            // best-effort pegar do configuracoes_sistema via chave/valor
            if ($this->tableExists('configuracoes_sistema')) {
                $cols = [];
                try {
                    $stCols = $this->connection->query('DESCRIBE configuracoes_sistema');
                    $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $cols = [];
                }
                if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                    $st = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                    $st->execute(['entrega_shipstation_from_address_json']);
                    $fromJson = (string) ($st->fetchColumn() ?: '');
                }
            }
        } catch (\Exception $e) {
            $fromJson = '';
        }

        $from = $fromJson !== '' ? json_decode($fromJson, true) : null;
        if (!is_array($from)) {
            $from = [];
        }

        $name = trim((string) ($from['company_name'] ?? ($from['name'] ?? '')));
        if ($name === '') {
            $name = 'Braziliana LLC';
        }

        $addr1 = trim((string) ($from['address_line1'] ?? ''));
        $addr2 = trim((string) ($from['address_line2'] ?? ''));
        $city = trim((string) ($from['city_locality'] ?? ''));
        $state = strtoupper(trim((string) ($from['state_province'] ?? '')));
        $zip = $this->onlyDigits((string) ($from['postal_code'] ?? ''));
        $email = trim((string) ($from['email'] ?? ''));

        $address = $addr1;
        $number = '';
        $complement = $addr2;

        if ($address === '') {
            $address = 'Address';
        }
        if ($number === '') {
            $number = '0';
        }
        if ($city === '') {
            $city = 'City';
        }
        if ($zip === '') {
            $zip = '00000';
        }
        if ($state === '') {
            $state = 'NC';
        }

        return [
            'senderName' => substr($name, 0, 70),
            'senderAddress' => substr($address, 0, 140),
            'senderAddressNumber' => substr($number, 0, 6),
            'senderAddressComplement' => substr($complement, 0, 50),
            'senderZipCode' => substr($zip, 0, 20),
            'senderCityName' => substr($city, 0, 50),
            'senderState' => substr($state, 0, 2),
            'senderCountryCode' => 'US',
            'senderEmail' => substr($email, 0, 50),
            'senderWebsite' => 'brazilianashop.com',
        ];
    }

    private function getPedidosCaixaFechadaSemEtiqueta(): array {
        if (!$this->tableExists('pedidos')) {
            return [];
        }
        $this->ensurePacketEtiquetasTable();

        $sql = "
            SELECT p.id AS pedido_id, u.nome AS cliente_nome, p.usuario_id, p.created_at
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN correios_packet_etiquetas cpe ON cpe.pedido_id = p.id
            WHERE LOWER(COALESCE(p.status,'')) IN ('produto_consolidado','consolidado')
              AND cpe.id IS NULL
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
            return [];
        }
    }

    private function getEtiquetasGeradas(): array {
        $this->ensurePacketEtiquetasTable();
        if (!$this->tableExists('correios_packet_etiquetas')) {
            return [];
        }
        try {
            $st = $this->connection->prepare("
                SELECT cpe.*, p.usuario_id, u.nome as cliente_nome
                FROM correios_packet_etiquetas cpe
                LEFT JOIN pedidos p ON p.id = cpe.pedido_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                ORDER BY cpe.created_at DESC
                LIMIT 100
            ");
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $pedidos = $this->getPedidosCaixaFechadaSemEtiqueta();
        $etiquetas = $this->getEtiquetasGeradas();

        $this->view('admin/correios-mundial', [
            'pedidos' => $pedidos,
            'etiquetas' => $etiquetas,
        ]);
    }

    public function balance(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $r = $this->svc->getBalance();
        if (empty($r['success'])) {
            $this->json([
                'success' => false,
                'error' => (string) ($r['error'] ?? 'Falha ao consultar saldo.'),
                'http_code' => $r['http_code'] ?? null,
                'request_url' => $r['request_url'] ?? null,
            ], 400);
            return;
        }

        $this->json([
            'success' => true,
            'currentBalance' => $r['currentBalance'] ?? null,
            'http_code' => $r['http_code'] ?? null,
            'request_url' => $r['request_url'] ?? null,
        ]);
    }

    public function pedido(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            header('Location: /admin/correios-mundial');
            exit;
        }

        $this->ensurePacketEtiquetasTable();
        $existingEtiqueta = null;
        try {
            if ($this->tableExists('correios_packet_etiquetas')) {
                $st = $this->connection->prepare('SELECT * FROM correios_packet_etiquetas WHERE pedido_id = ? LIMIT 1');
                $st->execute([$id]);
                $existingEtiqueta = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Exception $e) {
            $existingEtiqueta = null;
        }

        $pedidoModel = new PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($id);
        if (!is_array($pedido) || empty($pedido['id'])) {
            header('Location: /admin/correios-mundial');
            exit;
        }

        $destinatario = $this->buildRecipientFromPedido($pedido);
        $sender = $this->buildSenderFromConfig();

        // Validações do destinatário (guia v2.8)
        $pageError = '';
        $zipDigits = $this->onlyDigits((string) ($destinatario['recipientZipCode'] ?? ''));
        if (strlen($zipDigits) !== 8) {
            $pageError = 'Dados do destinatário incompletos: CEP inválido (deve conter 8 dígitos). Atualize o endereço do cliente/pedido e tente novamente.';
        }
        $destinatario['recipientZipCode'] = $zipDigits;

        $phoneDigits = $this->onlyDigits((string) ($destinatario['recipientPhoneNumber'] ?? ''));
        if (strlen($phoneDigits) === 0) {
            if ($pageError === '') {
                $pageError = 'Dados do destinatário incompletos: telefone é obrigatório (10 ou 11 dígitos, sem +55). Atualize o cadastro e tente novamente.';
            }
        }
        if ($pageError === '' && !in_array(strlen($phoneDigits), [10, 11], true)) {
            $pageError = 'Dados do destinatário incompletos: telefone inválido (deve conter 10 ou 11 dígitos, sem +55). Atualize o cadastro e tente novamente.';
        }
        $destinatario['recipientPhoneNumber'] = $phoneDigits;

        $destEmail = trim((string) ($destinatario['recipientEmail'] ?? ''));
        if ($destEmail === '') {
            if ($pageError === '') {
                $pageError = 'Dados do destinatário incompletos: e-mail é obrigatório. Atualize o cadastro e tente novamente.';
            }
        }
        if ($pageError === '' && filter_var($destEmail, FILTER_VALIDATE_EMAIL) === false) {
            $pageError = 'Dados do destinatário incompletos: e-mail inválido. Atualize o cadastro e tente novamente.';
        }

        $docType = strtoupper(trim((string) ($destinatario['recipientDocumentType'] ?? '')));
        $docNum = $this->onlyDigits((string) ($destinatario['recipientDocumentNumber'] ?? ''));
        if ($pageError === '' && $docType === 'CPF' && strlen($docNum) !== 11) {
            $pageError = 'Dados do destinatário incompletos: CPF inválido (deve conter 11 dígitos). Atualize o CPF do cliente e tente novamente.';
        }
        if ($pageError === '' && $docType === 'CNPJ' && strlen($docNum) !== 14) {
            $pageError = 'Dados do destinatário incompletos: CNPJ inválido (deve conter 14 dígitos). Atualize o CNPJ do cliente e tente novamente.';
        }
        if ($pageError === '' && !in_array($docType, ['CPF', 'CNPJ', 'PASSPORT'], true)) {
            $pageError = 'Dados do destinatário incompletos: tipo de documento inválido (CPF/CNPJ/PASSPORT).';
        }
        $destinatario['recipientDocumentType'] = $docType;
        $destinatario['recipientDocumentNumber'] = $docNum;

        $items = isset($pedido['items']) && is_array($pedido['items']) ? $pedido['items'] : [];

        $pesoKg = 0.0;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $q = (int) ($it['quantidade'] ?? 0);
            if ($q <= 0) $q = 1;
            $w = 0.0;
            if (isset($it['peso_kg']) && is_numeric($it['peso_kg'])) {
                $w = (float) $it['peso_kg'];
            }
            if ($w > 0) {
                $pesoKg += ($w * $q);
            }
        }
        if ($pesoKg <= 0) {
            $pesoKg = 0.2;
        }
        $pesoGramas = (int) max(1, round($pesoKg * 1000));

        $defaults = [
            'totalWeight' => $pesoGramas,
            'packagingLength' => 16,
            'packagingWidth' => 11,
            'packagingHeight' => 2,
        ];

        $this->view('admin/correios-mundial-pedido', [
            'pedido' => $pedido,
            'destinatario' => $destinatario,
            'sender' => $sender,
            'items' => $items,
            'defaults' => $defaults,
            'existingEtiqueta' => $existingEtiqueta,
            'pageError' => $pageError,
        ]);
    }

    public function gerarEtiqueta(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'Pedido inválido'], 400);
            return;
        }

        $this->ensurePacketEtiquetasTable();
        if (!$this->tableExists('correios_packet_etiquetas')) {
            $this->json(['success' => false, 'error' => 'Tabela correios_packet_etiquetas não encontrada.'], 500);
            return;
        }

        try {
            $stCheck = $this->connection->prepare('SELECT id FROM correios_packet_etiquetas WHERE pedido_id = ? LIMIT 1');
            $stCheck->execute([$id]);
            $exists = (int) ($stCheck->fetchColumn() ?: 0);
            if ($exists > 0) {
                $this->json(['success' => false, 'error' => 'Já existe etiqueta PACKET para este pedido'], 400);
                return;
            }
        } catch (\Exception $e) {
        }

        $pedidoModel = new PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($id);
        if (!is_array($pedido) || empty($pedido['id'])) {
            $this->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
            return;
        }

        $status = strtolower(trim((string) ($pedido['status'] ?? '')));
        if (!in_array($status, ['produto_consolidado', 'consolidado'], true)) {
            $this->json(['success' => false, 'error' => 'Pedido não está em Caixa Fechada'], 400);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) $data = [];

        $totalWeight = (int) ($data['totalWeight'] ?? 0);
        $packagingLength = (float) str_replace(',', '.', (string) ($data['packagingLength'] ?? '0'));
        $packagingWidth = (float) str_replace(',', '.', (string) ($data['packagingWidth'] ?? '0'));
        $packagingHeight = (float) str_replace(',', '.', (string) ($data['packagingHeight'] ?? '0'));
        $freightPaidValue = (float) str_replace(',', '.', (string) ($data['freightPaidValue'] ?? '0.01'));
        $insurancePaidValueRaw = trim((string) ($data['insurancePaidValue'] ?? ''));
        $insurancePaidValue = null;
        if ($insurancePaidValueRaw !== '') {
            $insurancePaidValue = (float) str_replace(',', '.', $insurancePaidValueRaw);
        }

        // Validações mínimas (regras doc)
        if ($totalWeight < 1 || $totalWeight > 30000) {
            $this->json(['success' => false, 'error' => 'Peso total inválido (1 a 30000g)'], 400);
            return;
        }
        if ($packagingLength < 16 || $packagingLength > 100) {
            $this->json(['success' => false, 'error' => 'Comprimento inválido (mín 16cm, máx 100cm)'], 400);
            return;
        }
        if ($packagingWidth < 11 || $packagingWidth > 100) {
            $this->json(['success' => false, 'error' => 'Largura inválida (mín 11cm, máx 100cm)'], 400);
            return;
        }
        if ($packagingHeight < 2 || $packagingHeight > 100) {
            $this->json(['success' => false, 'error' => 'Altura inválida (mín 2cm, máx 100cm)'], 400);
            return;
        }
        if (($packagingLength + $packagingWidth + $packagingHeight) > 200.0001) {
            $this->json(['success' => false, 'error' => 'Soma das dimensões (C+L+A) não pode ultrapassar 200cm'], 400);
            return;
        }
        if ($freightPaidValue < 0.01) {
            $this->json(['success' => false, 'error' => 'Frete deve ser no mínimo 0.01'], 400);
            return;
        }
        if ($insurancePaidValue !== null && $insurancePaidValue < 0.01) {
            $this->json(['success' => false, 'error' => 'Seguro deve ser no mínimo 0.01 (ou deixe vazio)'], 400);
            return;
        }

        $destinatario = $this->buildRecipientFromPedido($pedido);
        $sender = $this->buildSenderFromConfig();

        // Itens
        $itemsIn = isset($pedido['items']) && is_array($pedido['items']) ? $pedido['items'] : [];
        if (empty($itemsIn)) {
            $this->json(['success' => false, 'error' => 'Pedido sem itens'], 400);
            return;
        }

        if (count($itemsIn) > 20) {
            $this->json(['success' => false, 'error' => 'Pedido possui mais de 20 itens (limite da API PACKET)'], 400);
            return;
        }

        $items = [];
        $sumItems = 0.0;
        $idx = 0;
        foreach ($itemsIn as $it) {
            if (!is_array($it)) continue;
            $idx++;
            $qtd = (int) ($it['quantidade'] ?? 0);
            if ($qtd <= 0) {
                $this->json(['success' => false, 'error' => 'Item #' . $idx . ' com quantidade inválida'], 400);
                return;
            }
            $desc = trim((string) ($it['nome_produto'] ?? ($it['nome'] ?? 'Item')));
            if ($desc === '') $desc = 'Item ' . $idx;

            $ncmDigits = $this->onlyDigits((string) ($it['ncm'] ?? ''));
            if ($ncmDigits === '' || strlen($ncmDigits) < 6) {
                $this->json(['success' => false, 'error' => 'Item #' . $idx . ' sem NCM'], 400);
                return;
            }
            $hs = strlen($ncmDigits) >= 8 ? substr($ncmDigits, 0, 8) : substr($ncmDigits, 0, 6);

            $val = (float) ($it['preco_unitario'] ?? 0);
            if ($val < 0.01) {
                $this->json(['success' => false, 'error' => 'Item #' . $idx . ' com valor inválido'], 400);
                return;
            }
            $sumItems += ($val * $qtd);

            $items[] = [
                'hsCode' => $hs,
                'description' => substr($desc, 0, 500),
                'quantity' => $qtd,
                'value' => (float) number_format($val, 2, '.', ''),
            ];
        }
        if (empty($items)) {
            $this->json(['success' => false, 'error' => 'Pedido sem itens válidos'], 400);
            return;
        }

        $sumAduaneiro = $freightPaidValue + ($insurancePaidValue ?? 0.0) + $sumItems;
        if ($sumAduaneiro > 3000.0 + 0.0001) {
            $this->json(['success' => false, 'error' => 'Soma de valores (frete+seguro+itens) não pode ultrapassar 3000.00 USD'], 400);
            return;
        }

        $customerControlCode = (string) ($pedido['codigo_pedido'] ?? ('PED-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT)));
        $customerControlCode = trim($customerControlCode);
        if ($customerControlCode === '') {
            $customerControlCode = 'PED-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        }

        $package = array_merge([
            'customerControlCode' => substr($customerControlCode, 0, 100),
            'totalWeight' => $totalWeight,
            'packagingLength' => (float) number_format($packagingLength, 2, '.', ''),
            'packagingWidth' => (float) number_format($packagingWidth, 2, '.', ''),
            'packagingHeight' => (float) number_format($packagingHeight, 2, '.', ''),
            'distributionModality' => 33162,
            'taxPaymentMethod' => 'DDU',
            'currency' => 'USD',
            'nonNationalizationInstruction' => 'RETURNTOORIGIN',
            'packageRfidCode' => '',
            'freightPaidValue' => (float) number_format($freightPaidValue, 2, '.', ''),
        ], $sender, $destinatario);

        if ($insurancePaidValue !== null) {
            $package['insurancePaidValue'] = (float) number_format($insurancePaidValue, 2, '.', '');
        }
        $package['items'] = $items;

        $resp = $this->svc->createPackages([$package]);
        if (empty($resp['success'])) {
            $friendly = $this->packetFriendlyError((string) ($resp['error'] ?? ''), $resp['raw'] ?? null);
            $this->json([
                'success' => false,
                'error' => $friendly,
                'http_code' => $resp['http_code'] ?? null,
            ], 400);
            return;
        }

        $rawResp = $resp['raw'] ?? null;
        $tracking = '';
        if (is_array($rawResp) && isset($rawResp['packageResponseList']) && is_array($rawResp['packageResponseList']) && !empty($rawResp['packageResponseList'][0])) {
            $first = $rawResp['packageResponseList'][0];
            if (is_array($first) && !empty($first['trackingNumber'])) {
                $tracking = (string) $first['trackingNumber'];
            }
        }
        if (trim($tracking) === '') {
            $this->json(['success' => false, 'error' => 'Resposta da API sem trackingNumber', 'raw' => $rawResp], 500);
            return;
        }

        try {
            $stIns = $this->connection->prepare('INSERT INTO correios_packet_etiquetas (pedido_id, customer_control_code, tracking_number, status, last_request_json, last_response_json, last_http_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stIns->execute([
                $id,
                $customerControlCode,
                $tracking,
                'gerada',
                json_encode(['packageList' => [$package]]),
                json_encode($rawResp),
                (int) ($resp['http_code'] ?? 200),
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Falha ao salvar etiqueta: ' . $e->getMessage()], 500);
            return;
        }

        try {
            $pedidoModel->atualizarStatus((int) $id, 'etiqueta_gerada', 'Etiqueta PACKET gerada - Rastreio: ' . $tracking, $_SESSION['usuario_id'] ?? null);
        } catch (\Exception $e) {
        }

        try {
            $notif = new \App\Services\NotificationService();
            $notif->notificarEventoPedido('correios_packet_label_created', (int) $id, [
                'tracking_number' => $tracking,
                'customer_control_code' => $customerControlCode,
            ]);
        } catch (\Exception $e) {
        }

        $this->json([
            'success' => true,
            'pedido_id' => $id,
            'tracking_number' => $tracking,
        ]);
    }

    private function cmFmtUsd($v): string {
        if (!is_numeric($v)) return '';
        return '$ ' . number_format((float) $v, 2, '.', ',');
    }

    private function cmFmtUsdNoSymbol($v): string {
        if (!is_numeric($v)) return '';
        return number_format((float) $v, 2, '.', ',');
    }

    private function code128Svg(string $text, int $barHeight = 55, int $module = 1): string {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        // Code128 patterns (modules) for values 0..106
        $patterns = [
            0=>'212222',1=>'222122',2=>'222221',3=>'121223',4=>'121322',5=>'131222',6=>'122213',7=>'122312',8=>'132212',9=>'221213',
            10=>'221312',11=>'231212',12=>'112232',13=>'122132',14=>'122231',15=>'113222',16=>'123122',17=>'123221',18=>'223211',19=>'221132',
            20=>'221231',21=>'213212',22=>'223112',23=>'312131',24=>'311222',25=>'321122',26=>'321221',27=>'312212',28=>'322112',29=>'322211',
            30=>'212123',31=>'212321',32=>'232121',33=>'111323',34=>'131123',35=>'131321',36=>'112313',37=>'132113',38=>'132311',39=>'211313',
            40=>'231113',41=>'231311',42=>'112133',43=>'112331',44=>'132131',45=>'113123',46=>'113321',47=>'133121',48=>'313121',49=>'211331',
            50=>'231131',51=>'213113',52=>'213311',53=>'213131',54=>'311123',55=>'311321',56=>'331121',57=>'312113',58=>'312311',59=>'332111',
            60=>'314111',61=>'221411',62=>'431111',63=>'111224',64=>'111422',65=>'121124',66=>'121421',67=>'141122',68=>'141221',69=>'112214',
            70=>'112412',71=>'122114',72=>'122411',73=>'142112',74=>'142211',75=>'241211',76=>'221114',77=>'413111',78=>'241112',79=>'134111',
            80=>'111242',81=>'121142',82=>'121241',83=>'114212',84=>'124112',85=>'124211',86=>'411212',87=>'421112',88=>'421211',89=>'212141',
            90=>'214121',91=>'412121',92=>'111143',93=>'111341',94=>'131141',95=>'114113',96=>'114311',97=>'411113',98=>'411311',99=>'113141',
            100=>'114131',101=>'311141',102=>'411131',103=>'211412',104=>'211214',105=>'211232',106=>'2331112'
        ];

        // Code Set B
        $codes = [];
        $start = 104; // Start B
        $codes[] = $start;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($text[$i]);
            if ($o < 32 || $o > 126) {
                $o = 32;
            }
            $codes[] = $o - 32;
        }

        $checksum = $start;
        for ($i = 1; $i < count($codes); $i++) {
            $checksum += $codes[$i] * $i;
        }
        $checksum = $checksum % 103;
        $codes[] = $checksum;
        $codes[] = 106; // Stop

        $x = 0;
        $bars = [];
        foreach ($codes as $c) {
            $p = $patterns[$c] ?? '';
            if ($p === '') continue;
            $isBar = true;
            foreach (str_split($p) as $d) {
                $w = ((int) $d) * $module;
                if ($isBar) {
                    $bars[] = [$x, $w];
                }
                $x += $w;
                $isBar = !$isBar;
            }
        }
        $wTotal = max(1, $x);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $wTotal . '" height="' . (int) $barHeight . '" viewBox="0 0 ' . $wTotal . ' ' . (int) $barHeight . '">';
        $svg .= '<rect width="100%" height="100%" fill="#fff" />';
        foreach ($bars as $b) {
            $svg .= '<rect x="' . (int) $b[0] . '" y="0" width="' . (int) $b[1] . '" height="' . (int) $barHeight . '" fill="#000" />';
        }
        $svg .= '</svg>';
        return $svg;
    }

    private function svgToDataUri(string $svg): string {
        $svg = trim((string) $svg);
        if ($svg === '') return '';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function imageFileToDataUri(string $path): string {
        $path = (string) $path;
        if ($path === '' || !is_file($path)) return '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
        if ($ext === 'gif') $mime = 'image/gif';
        if ($ext === 'svg') $mime = 'image/svg+xml';
        $bin = @file_get_contents($path);
        if ($bin === false || $bin === null) return '';
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    private function getAvailablePackagesForContainer(): array {
        $this->ensurePacketEtiquetasTable();
        $this->ensureEtiquetasHasContainerIdColumn();
        if (!$this->tableExists('correios_packet_etiquetas')) return [];
        try {
            $st = $this->connection->prepare('SELECT pedido_id, tracking_number FROM correios_packet_etiquetas WHERE tracking_number IS NOT NULL AND tracking_number <> \'\' AND (container_id IS NULL OR container_id = 0) ORDER BY created_at DESC LIMIT 500');
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getNextDispatchNumber(): int {
        $this->ensurePacketContainersTable();
        if (!$this->tableExists('correios_packet_containers')) return 1;
        try {
            $st = $this->connection->query('SELECT MAX(dispatch_number) FROM correios_packet_containers');
            $m = (int) ($st ? ($st->fetchColumn() ?: 0) : 0);
            return max(1, $m + 1);
        } catch (\Exception $e) {
            return 1;
        }
    }

    public function containers(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $this->ensurePacketContainersTable();
        $containers = [];
        if ($this->tableExists('correios_packet_containers')) {
            try {
                $st = $this->connection->prepare('SELECT * FROM correios_packet_containers ORDER BY created_at DESC LIMIT 100');
                $st->execute();
                $containers = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $containers = [];
            }
        }

        foreach ($containers as &$c) {
            $tn = [];
            $raw = (string) ($c['tracking_numbers_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $tn = $decoded;
            }
            $c['packages_count'] = is_array($tn) ? count($tn) : 0;
        }
        unset($c);

        $this->view('admin/correios-mundial-containers', [
            'containers' => $containers,
            'flashError' => (string) ($request->getParam('error') ?? ''),
            'flashSuccess' => (string) ($request->getParam('success') ?? ''),
        ]);
    }

    public function faturas(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $this->ensurePacketBillsTable();
        $bills = [];
        if ($this->tableExists('correios_packet_bills')) {
            try {
                $st = $this->connection->prepare('SELECT * FROM correios_packet_bills ORDER BY created_at DESC LIMIT 100');
                $st->execute();
                $bills = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $bills = [];
            }
        }

        $this->view('admin/correios-mundial-faturas', [
            'bills' => $bills,
            'flashError' => (string) ($request->getParam('error') ?? ''),
            'flashSuccess' => (string) ($request->getParam('success') ?? ''),
        ]);
    }

    public function faturaNova(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $this->ensurePacketContainersTable();
        $this->ensurePacketBillsTable();
        $this->ensureContainersHasBillIdColumn();

        $balance = $this->svc->getBalance();

        $containers = [];
        if ($this->tableExists('correios_packet_containers')) {
            try {
                $st = $this->connection->prepare("SELECT id, dispatch_number, unit_code, tracking_numbers_json, created_at FROM correios_packet_containers WHERE (bill_id IS NULL OR bill_id = 0) AND status = 'created' AND unit_code IS NOT NULL AND unit_code <> '' ORDER BY created_at DESC LIMIT 200");
                $st->execute();
                $containers = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $containers = [];
            }
        }

        foreach ($containers as &$c) {
            $tn = [];
            $raw = (string) ($c['tracking_numbers_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $tn = $decoded;
            }
            $c['tracking_count'] = is_array($tn) ? count($tn) : 0;
        }
        unset($c);

        $this->view('admin/correios-mundial-fatura-nova', [
            'containers' => $containers,
            'balance' => $balance,
            'flashError' => (string) ($request->getParam('error') ?? ''),
            'flashSuccess' => (string) ($request->getParam('success') ?? ''),
        ]);
    }

    public function faturaCriar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $this->ensurePacketContainersTable();
        $this->ensurePacketBillsTable();
        $this->ensureContainersHasBillIdColumn();

        if (!$this->tableExists('correios_packet_bills')) {
            header('Location: /admin/correios-mundial/faturas?error=' . rawurlencode('Tabela correios_packet_bills não encontrada.'));
            exit;
        }

        $containerIds = $request->getParam('containerIds');
        if (!is_array($containerIds)) {
            $containerIds = [];
        }
        $containerIds = array_values(array_unique(array_filter(array_map(function ($v) {
            return (int) $v;
        }, $containerIds), fn($v) => $v > 0)));

        if (empty($containerIds)) {
            header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Selecione pelo menos 1 container.'));
            exit;
        }

        $balance = $this->svc->getBalance();
        if (empty($balance['success'])) {
            header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode((string) ($balance['error'] ?? 'Falha ao consultar saldo.')));
            exit;
        }
        $currentBalance = (int) ($balance['currentBalance'] ?? 0);
        if ($currentBalance <= 0) {
            header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Saldo insuficiente para faturamento.'));
            exit;
        }

        $unitList = [];
        $totalTrackings = 0;
        $containerRows = [];

        try {
            $in = implode(',', array_fill(0, count($containerIds), '?'));
            $st = $this->connection->prepare('SELECT id, unit_code, tracking_numbers_json, bill_id, status FROM correios_packet_containers WHERE id IN (' . $in . ')');
            $st->execute($containerIds);
            $containerRows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $containerRows = [];
        }

        if (empty($containerRows)) {
            header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Containers não encontrados.'));
            exit;
        }

        foreach ($containerRows as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $uc = trim((string) ($row['unit_code'] ?? ''));
            $billId = (int) ($row['bill_id'] ?? 0);
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($cid <= 0 || $uc === '') {
                header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Container inválido/sem unitCode.'));
                exit;
            }
            if ($billId > 0) {
                header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Container já está vinculado a uma fatura.'));
                exit;
            }
            if ($status !== 'created') {
                header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Apenas containers com status created podem ser faturados.'));
                exit;
            }

            $tn = [];
            $raw = (string) ($row['tracking_numbers_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $tn = $decoded;
            }
            $tn = array_values(array_filter(array_map(function ($v) {
                $v = strtoupper(trim((string) $v));
                return $v !== '' ? $v : null;
            }, is_array($tn) ? $tn : [])));

            if (empty($tn)) {
                header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Container sem trackingNumbers.'));
                exit;
            }
            if (count($tn) > 1000) {
                header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Limite de 1000 pacotes por mala/unitCode.'));
                exit;
            }

            $totalTrackings += count($tn);
            if ($totalTrackings > 5000) {
                header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Limite de 5000 códigos de rastreio por fatura excedido.'));
                exit;
            }

            $unitList[] = [
                'unitCode' => $uc,
                'trackingNumbers' => $tn,
            ];
        }

        $billId = 0;
        try {
            $stIns = $this->connection->prepare('INSERT INTO correios_packet_bills (status, containers_json, tracking_count, last_request_json, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
            $stIns->execute([
                'pending',
                json_encode($containerIds),
                $totalTrackings,
                json_encode(['unitList' => $unitList]),
            ]);
            $billId = (int) $this->connection->lastInsertId();
        } catch (\Exception $e) {
            header('Location: /admin/correios-mundial/faturas/nova?error=' . rawurlencode('Falha ao salvar fatura no banco.'));
            exit;
        }

        try {
            $in = implode(',', array_fill(0, count($containerIds), '?'));
            $sql = 'UPDATE correios_packet_containers SET bill_id = ? WHERE id IN (' . $in . ') AND (bill_id IS NULL OR bill_id = 0)';
            $params = array_merge([$billId], $containerIds);
            $stUp = $this->connection->prepare($sql);
            $stUp->execute($params);
        } catch (\Exception $e) {
        }

        $api = $this->svc->createCn38RequestUnits($unitList);
        if (empty($api['success'])) {
            try {
                $stUp = $this->connection->prepare('UPDATE correios_packet_bills SET status = ?, last_response_json = ?, last_http_code = ?, updated_at = NOW() WHERE id = ?');
                $stUp->execute([
                    'error',
                    json_encode($api['raw'] ?? null),
                    $api['http_code'] ?? null,
                    $billId,
                ]);
            } catch (\Exception $e) {
            }

            header('Location: /admin/correios-mundial/faturas?error=' . rawurlencode((string) ($api['error'] ?? 'Falha ao iniciar faturamento.')));
            exit;
        }

        $rawResp = $api['raw'] ?? [];
        $requestId = (string) ($rawResp['requestId'] ?? '');
        $requestStatus = (string) ($rawResp['requestStatus'] ?? '');
        if ($requestId === '') {
            header('Location: /admin/correios-mundial/faturas?error=' . rawurlencode('Correios não retornou requestId.'));
            exit;
        }

        try {
            $stUp = $this->connection->prepare('UPDATE correios_packet_bills SET status = ?, request_id = ?, last_response_json = ?, last_http_code = ?, updated_at = NOW() WHERE id = ?');
            $stUp->execute([
                $requestStatus !== '' ? strtolower($requestStatus) : 'pending',
                $requestId,
                json_encode($api['raw'] ?? null),
                $api['http_code'] ?? null,
                $billId,
            ]);
        } catch (\Exception $e) {
        }

        $status = '';
        $final = null;
        $started = time();
        while (true) {
            $stResp = $this->svc->getCn38RequestUnitsStatus($requestId);
            if (!empty($stResp['success']) && is_array($stResp['raw'] ?? null)) {
                $final = $stResp;
                $status = (string) (($stResp['raw']['requestStatus'] ?? '') !== '' ? $stResp['raw']['requestStatus'] : ($stResp['raw']['status'] ?? ''));
                if (!in_array(strtolower($status), ['pending', 'processing'], true)) {
                    break;
                }
            }

            if (time() - $started > 120) {
                break;
            }
            sleep(2);
        }

        if (is_array($final) && !empty($final['success'])) {
            $rawFinal = $final['raw'] ?? [];
            $statusFinal = strtolower(trim((string) ($rawFinal['requestStatus'] ?? $status)));
            $cn38 = (string) ($rawFinal['cn38Code'] ?? '');
            $errMsg = (string) ($rawFinal['errorMessage'] ?? '');

            try {
                $stUp = $this->connection->prepare('UPDATE correios_packet_bills SET status = ?, cn38_code = ?, last_response_json = ?, last_http_code = ?, updated_at = NOW() WHERE id = ?');
                $stUp->execute([
                    $statusFinal !== '' ? $statusFinal : 'processing',
                    $cn38 !== '' ? $cn38 : null,
                    json_encode($rawFinal),
                    $final['http_code'] ?? null,
                    $billId,
                ]);
            } catch (\Exception $e) {
            }

            if ($statusFinal === 'error') {
                header('Location: /admin/correios-mundial/faturas?error=' . rawurlencode($errMsg !== '' ? $errMsg : 'Erro ao processar fatura.'));
                exit;
            }
            if ($statusFinal === 'success' && $cn38 !== '') {
                header('Location: /admin/correios-mundial/faturas?success=' . rawurlencode('Fatura gerada: ' . $cn38));
                exit;
            }
        }

        header('Location: /admin/correios-mundial/faturas?success=' . rawurlencode('Solicitação criada. Aguarde e atualize o status.'));
        exit;
    }

    public function faturaPdf(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            http_response_code(400);
            echo 'ID inválido.';
            return;
        }

        $this->ensurePacketBillsTable();
        $this->ensurePacketContainersTable();
        if (!$this->tableExists('correios_packet_bills')) {
            http_response_code(500);
            echo 'Tabela correios_packet_bills não encontrada.';
            return;
        }

        $bill = null;
        try {
            $st = $this->connection->prepare('SELECT * FROM correios_packet_bills WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $bill = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $bill = null;
        }
        if (!is_array($bill)) {
            http_response_code(404);
            echo 'Fatura não encontrada.';
            return;
        }

        $cn38Code = (string) ($bill['cn38_code'] ?? '');
        if ($cn38Code === '') {
            http_response_code(400);
            echo 'cn38Code não encontrado.';
            return;
        }

        $containersIds = [];
        $rawC = (string) ($bill['containers_json'] ?? '');
        if ($rawC !== '') {
            $decoded = json_decode($rawC, true);
            if (is_array($decoded)) {
                $containersIds = array_values(array_unique(array_filter(array_map('intval', $decoded), fn($v) => $v > 0)));
            }
        }
        if (empty($containersIds)) {
            http_response_code(400);
            echo 'Containers não encontrados.';
            return;
        }

        $containers = [];
        try {
            $in = implode(',', array_fill(0, count($containersIds), '?'));
            $st = $this->connection->prepare('SELECT * FROM correios_packet_containers WHERE id IN (' . $in . ')');
            $st->execute($containersIds);
            $containers = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $containers = [];
        }

        if (empty($containers)) {
            http_response_code(400);
            echo 'Containers inválidos.';
            return;
        }

        $c0 = $containers[0];
        $originOperatorName = (string) ($c0['origin_operator_name'] ?? '');
        $destinationOperatorName = (string) ($c0['destination_operator_name'] ?? '');
        $serviceSubclassCode = (string) ($c0['service_subclass_code'] ?? 'NX');
        $subclassDescription = ($serviceSubclassCode === 'IX') ? 'PACKET EXPRESS' : 'PACKET STANDARD';

        $totalContainers = count($containers);
        $totalTrackings = 0;
        foreach ($containers as $c) {
            $tn = [];
            $raw = (string) ($c['tracking_numbers_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $tn = $decoded;
            }
            $totalTrackings += is_array($tn) ? count($tn) : 0;
        }

        $pluginAssets = dirname(__DIR__) . '/Plugins/woocommerce-package-redirect/assets/images/';
        $logoTransAmerica = $this->imageFileToDataUri($pluginAssets . 'logo-transamerica.png');

        $html = '<!DOCTYPE html><html><head>'
            . '<meta charset="UTF-8">'
            . '<style>'
            . '@page { margin: 0px; }'
            . 'body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 0; }'
            . '.sheet { width: 220mm; height: 110mm; padding: 4mm; box-sizing: border-box; overflow: hidden; }'
            . 'table { width: 100%; border-collapse: collapse; margin: 0; }'
            . 'td { padding: 5px; border: 1px solid #000; vertical-align: top; }'
            . 'p { margin: 0; }'
            . '.bold { font-weight: bold; }'
            . '.en { font-size: 7pt; color: #555; }'
            . '.logo { width: 20mm; }'
            . '.logo img { width: 100%; }'
            . '</style>'
            . '</head><body><div class="sheet">';

        $html .= '<table>'
            . '<tr>'
            . '<td style="width: 30mm;">' . ($logoTransAmerica !== '' ? '<div class="logo"><img src="' . $logoTransAmerica . '" alt=" "></div>' : '') . '</td>'
            . '<td><p class="bold">FATURA DE ENTREGA<br><span class="en">(Delivery Bill)</span></p></td>'
            . '<td style="width: 30mm;"><p class="bold">1 de 1</p></td>'
            . '</tr>'
            . '</table>';

        $html .= '<table>'
            . '<tr>'
            . '<td colspan="2"><p class="bold">OPERADOR DE ORIGEM<br><span class="en">(Office of Origin)</span></p><p>' . htmlspecialchars($originOperatorName) . '</p></td>'
            . '<td><p class="bold">CÓDIGO CN38<br><span class="en">(CN38 Code)</span></p><p>' . htmlspecialchars($cn38Code) . '</p></td>'
            . '</tr>'
            . '<tr>'
            . '<td colspan="2"><p class="bold">OPERADOR DE DESTINO<br><span class="en">(Office of Destination)</span></p><p>' . htmlspecialchars($destinationOperatorName) . '</p></td>'
            . '<td><p class="bold">SERVIÇO<br><span class="en">(Service)</span></p><p>' . htmlspecialchars($subclassDescription) . '</p></td>'
            . '</tr>'
            . '<tr>'
            . '<td><p class="bold">QTD. MALAS<br><span class="en">(Units)</span></p><p>' . (int) $totalContainers . '</p></td>'
            . '<td><p class="bold">QTD. ITENS<br><span class="en">(Items)</span></p><p>' . (int) $totalTrackings . '</p></td>'
            . '<td><p class="bold">DATA<br><span class="en">(Date)</span></p><p>' . date('d/m/Y') . '</p></td>'
            . '</tr>'
            . '</table>';

        $html .= '<table>'
            . '<tr>'
            . '<td class="bold">Unit Code</td>'
            . '<td class="bold">Remessa</td>'
            . '<td class="bold">Qtd pacotes</td>'
            . '</tr>';
        foreach ($containers as $c) {
            $uc = (string) ($c['unit_code'] ?? '');
            $dn = (string) ($c['dispatch_number'] ?? '');
            $tn = [];
            $raw = (string) ($c['tracking_numbers_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $tn = $decoded;
            }
            $html .= '<tr>'
                . '<td>' . htmlspecialchars($uc) . '</td>'
                . '<td>' . htmlspecialchars($dn) . '</td>'
                . '<td>' . (int) (is_array($tn) ? count($tn) : 0) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        $html .= '</div></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 623.6, 311.8]);
        $dompdf->render();

        $safe = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $cn38Code);
        if ($safe === '') $safe = 'cn38';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="fatura_cn38_' . $safe . '.pdf"');
        echo $dompdf->output();
    }

    public function containerCancelar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('ID inválido.'));
            exit;
        }

        $this->ensurePacketContainersTable();
        if (!$this->tableExists('correios_packet_containers')) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('Tabela correios_packet_containers não encontrada.'));
            exit;
        }

        $row = null;
        try {
            $st = $this->connection->prepare('SELECT * FROM correios_packet_containers WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $row = null;
        }
        if (!is_array($row)) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('Container não encontrado.'));
            exit;
        }

        $unitCode = trim((string) ($row['unit_code'] ?? ''));

        $dispatchNumber = trim((string) ($row['dispatch_number'] ?? ''));
        if ($dispatchNumber === '') {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('dispatchNumber não encontrado no container.'));
            exit;
        }

        // Primeiro: cancelar pelo unitCode (mais confiável). Depois, fallback para dispatchNumber.
        $api = null;
        $attemptLog = [];
        $ambientesTry = ['homologacao', 'producao'];
        if ($unitCode !== '') {
            foreach ($ambientesTry as $ambTry) {
                $apiU = $this->svc->cancelUnit($unitCode, $ambTry);
                $attemptLog[] = [
                    'amb' => $ambTry,
                    'dn' => 'unit:' . $unitCode,
                    'http' => $apiU['http_code'] ?? null,
                    'err' => (string) ($apiU['error'] ?? ''),
                    'url' => (string) ($apiU['request_url'] ?? ''),
                ];
                if (!empty($apiU['success'])) {
                    $api = $apiU;
                    break;
                }
            }
        }

        if (empty($api) || empty($api['success'])) {
            $api = $this->svc->cancelDispatch($dispatchNumber);
        }
        if (empty($api['success'])) {
            $rawErr = (string) ($api['error'] ?? '');
            // fallback: containers antigos podem ter dispatch_number errado (retry NEG-118)
            if (stripos($rawErr, 'NEG-124') !== false || stripos($rawErr, 'dispatch numbers not found') !== false) {
                $candidateNumbers = [];
                $candidateNumbers[] = $dispatchNumber;

                $attemptLog[] = [
                    'amb' => 'config',
                    'dn' => $dispatchNumber,
                    'http' => $api['http_code'] ?? null,
                    'err' => (string) ($api['error'] ?? ''),
                    'url' => (string) ($api['request_url'] ?? ''),
                ];

                $lr = (string) ($row['last_request_json'] ?? '');
                if ($lr !== '') {
                    $jr = json_decode($lr, true);
                    $dn2 = '';
                    if (is_array($jr) && isset($jr['dispatchNumber'])) {
                        $dn2 = trim((string) $jr['dispatchNumber']);
                    }
                    if ($dn2 !== '') {
                        $candidateNumbers[] = $dn2;
                    }
                }

                // tentativas adicionais: quando houve retry, o dispatch pode ter sido incrementado
                if (ctype_digit((string) $dispatchNumber)) {
                    $base = (int) $dispatchNumber;
                    for ($i = 1; $i <= 15; $i++) {
                        $candidateNumbers[] = (string) ($base + $i);
                    }
                }

                $candidateNumbers = array_values(array_unique(array_filter(array_map('strval', $candidateNumbers))));

                // fallback adicional: tentar nos dois ambientes (homologação/produção)
                foreach ($ambientesTry as $ambTry) {
                    foreach ($candidateNumbers as $dnTry) {
                        if ($dnTry === '') continue;
                        $api2 = $this->svc->cancelDispatch($dnTry, $ambTry);
                        $attemptLog[] = [
                            'amb' => $ambTry,
                            'dn' => $dnTry,
                            'http' => $api2['http_code'] ?? null,
                            'err' => (string) ($api2['error'] ?? ''),
                            'url' => (string) ($api2['request_url'] ?? ''),
                        ];
                        if (!empty($api2['success'])) {
                            $dispatchNumber = $dnTry;
                            $api = $api2;
                            break 2;
                        }
                    }
                }

                // se falhou, sobrescreve erro com resumo das últimas tentativas
                if (empty($api['success']) && !empty($attemptLog)) {
                    $tail = array_slice($attemptLog, -6);
                    $parts = [];
                    foreach ($tail as $a) {
                        $parts[] = ($a['amb'] ?? '-') . ':' . ($a['dn'] ?? '-') . ' HTTP ' . (($a['http'] ?? '') !== null ? (string) $a['http'] : '-') . ' ' . ($a['err'] ?? '');
                    }
                    $api['error'] = trim((string) ($api['error'] ?? '')) . ' | tentativas: ' . implode(' || ', $parts);
                }
            }

            if (empty($api['success'])) {
                $err = $this->packetFriendlyError((string) ($api['error'] ?? 'Falha ao cancelar despacho.'), $api['raw'] ?? null);
                header('Location: /admin/correios-mundial/containers?error=' . rawurlencode($err));
                exit;
            }
        }

        try {
            $stUp = $this->connection->prepare('UPDATE correios_packet_containers SET dispatch_number = ?, status = ?, last_response_json = ?, last_http_code = ?, updated_at = NOW() WHERE id = ?');
            $stUp->execute([
                $dispatchNumber,
                'cancelled',
                json_encode($api['raw'] ?? null),
                $api['http_code'] ?? null,
                $id,
            ]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/correios-mundial/containers?success=' . rawurlencode('Despacho cancelado. Agora você pode deletar o container para liberar os pacotes.'));
        exit;
    }

    public function containerDeletar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('ID inválido.'));
            exit;
        }

        $this->ensurePacketContainersTable();
        if (!$this->tableExists('correios_packet_containers')) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('Tabela correios_packet_containers não encontrada.'));
            exit;
        }

        $row = null;
        try {
            $st = $this->connection->prepare('SELECT id, status, unit_code FROM correios_packet_containers WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $row = null;
        }
        if (!is_array($row)) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('Container não encontrado.'));
            exit;
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status !== 'cancelled') {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('Para deletar, primeiro cancele o despacho deste container.'));
            exit;
        }

        // libera pacotes
        if ($this->tableExists('correios_packet_etiquetas')) {
            try {
                $stRel = $this->connection->prepare('UPDATE correios_packet_etiquetas SET container_id = NULL WHERE container_id = ?');
                $stRel->execute([$id]);
            } catch (\Exception $e) {
            }
        }

        try {
            $stDel = $this->connection->prepare('DELETE FROM correios_packet_containers WHERE id = ? LIMIT 1');
            $stDel->execute([$id]);
        } catch (\Exception $e) {
            header('Location: /admin/correios-mundial/containers?error=' . rawurlencode('Falha ao deletar container.'));
            exit;
        }

        $unitCode = (string) ($row['unit_code'] ?? '');
        $msg = $unitCode !== '' ? ('Container deletado: ' . $unitCode) : 'Container deletado.';
        header('Location: /admin/correios-mundial/containers?success=' . rawurlencode($msg));
        exit;
    }

    public function containerNovo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $bulk = (string) ($request->getParam('bulk') ?? '');
        $preselected = [];
        $bulkResult = null;

        $available = $this->getAvailablePackagesForContainer();
        $availableSet = [];
        foreach ($available as $p) {
            $trk = (string) ($p['tracking_number'] ?? '');
            if ($trk !== '') $availableSet[$trk] = true;
        }

        if (trim($bulk) !== '') {
            $tokens = $this->parseBulkTokens($bulk);
            $found = [];
            $notFound = [];
            $alreadyUsed = [];

            foreach ($tokens as $t) {
                $isNumeric = ctype_digit($t);
                $tracking = '';

                if ($isNumeric) {
                    $pid = (int) $t;
                    if ($pid > 0 && $this->tableExists('correios_packet_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT tracking_number, container_id FROM correios_packet_etiquetas WHERE pedido_id = ? LIMIT 1');
                            $st->execute([$pid]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
                            if (is_array($row)) {
                                $tracking = (string) ($row['tracking_number'] ?? '');
                                $cid = (int) ($row['container_id'] ?? 0);
                                if ($tracking !== '' && $cid > 0) {
                                    $alreadyUsed[] = $t . ' -> ' . $tracking;
                                    $tracking = '';
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }
                } else {
                    $tracking = strtoupper(trim($t));
                    if ($tracking !== '' && $this->tableExists('correios_packet_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT container_id FROM correios_packet_etiquetas WHERE tracking_number = ? LIMIT 1');
                            $st->execute([$tracking]);
                            $cid = (int) ($st->fetchColumn() ?: 0);
                            if ($cid > 0) {
                                $alreadyUsed[] = $tracking;
                                $tracking = '';
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($tracking !== '' && isset($availableSet[$tracking])) {
                    $found[] = $tracking;
                    $preselected[] = $tracking;
                } else {
                    if ($tracking === '' && $isNumeric) {
                        // já foi para alreadyUsed ou não resolveu
                        if (!in_array($t, $alreadyUsed, true)) {
                            $notFound[] = $t;
                        }
                    } elseif ($tracking !== '') {
                        $notFound[] = $tracking;
                    }
                }
            }

            $preselected = array_values(array_unique($preselected));
            $bulkResult = [
                'found' => array_values(array_unique($found)),
                'not_found' => array_values(array_unique($notFound)),
                'already_used' => array_values(array_unique($alreadyUsed)),
            ];
        }

        $defaults = [
            'dispatchNumber' => $this->getNextDispatchNumber(),
            'originOperatorName' => 'BRAZ',
            'destinationOperatorName' => 'SAOD',
            'postalCategoryCode' => 'A',
            'serviceSubclassCode' => 'NX',
            'unitType' => '2',
            'awb' => '',
            'triageGroup' => '',
            'bulk' => $bulk,
        ];

        $this->view('admin/correios-mundial-container-novo', [
            'defaults' => $defaults,
            'availablePackages' => $available,
            'preselected' => $preselected,
            'bulkResult' => $bulkResult,
            'flashError' => (string) ($request->getParam('error') ?? ''),
            'flashSuccess' => (string) ($request->getParam('success') ?? ''),
        ]);
    }

    public function containerCriar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $this->ensurePacketContainersTable();
        $this->ensureEtiquetasHasContainerIdColumn();

        $dispatchNumber = (int) ($request->getParam('dispatchNumber') ?? 0);
        $originCountry = strtoupper(trim((string) ($request->getParam('originCountry') ?? 'US')));
        $originOperatorName = strtoupper(substr(trim((string) ($request->getParam('originOperatorName') ?? 'BRAS')), 0, 4));
        $destinationOperatorName = strtoupper(substr(trim((string) ($request->getParam('destinationOperatorName') ?? 'SAOD')), 0, 4));
        $postalCategoryCode = strtoupper(substr(trim((string) ($request->getParam('postalCategoryCode') ?? 'A')), 0, 1));
        $serviceSubclassCode = strtoupper(substr(trim((string) ($request->getParam('serviceSubclassCode') ?? 'NX')), 0, 2));
        $unitType = (string) ($request->getParam('unitType') ?? '2');
        $awb = trim((string) ($request->getParam('awb') ?? ''));
        $triageGroup = trim((string) ($request->getParam('triageGroup') ?? '1'));
        $bulk = (string) ($request->getParam('bulk') ?? '');

        $trackingNumbers = $request->getParam('trackingNumbers');
        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [];
        }
        $trackingNumbers = array_values(array_filter(array_map(function ($v) {
            $v = strtoupper(trim((string) $v));
            return $v !== '' ? $v : null;
        }, $trackingNumbers)));

        if (empty($trackingNumbers) && trim($bulk) !== '') {
            // tenta resolver a partir do bulk
            $tokens = $this->parseBulkTokens($bulk);
            $resolved = [];
            foreach ($tokens as $t) {
                if (ctype_digit($t)) {
                    $pid = (int) $t;
                    if ($pid > 0 && $this->tableExists('correios_packet_etiquetas')) {
                        try {
                            $st = $this->connection->prepare('SELECT tracking_number, container_id FROM correios_packet_etiquetas WHERE pedido_id = ? LIMIT 1');
                            $st->execute([$pid]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
                            if (is_array($row)) {
                                $trk = strtoupper(trim((string) ($row['tracking_number'] ?? '')));
                                $cid = (int) ($row['container_id'] ?? 0);
                                if ($trk !== '' && $cid <= 0) {
                                    $resolved[] = $trk;
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }
                } else {
                    $resolved[] = strtoupper(trim($t));
                }
            }
            $trackingNumbers = array_values(array_unique(array_filter($resolved)));
        }

        if ($dispatchNumber <= 0) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('dispatchNumber inválido.'));
            exit;
        }
        if ($originCountry !== 'US') {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('originCountry deve ser US.'));
            exit;
        }
        if ($originOperatorName === '' || strlen($originOperatorName) !== 4) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('originOperatorName deve ter 4 letras.'));
            exit;
        }
        if ($destinationOperatorName === '' || strlen($destinationOperatorName) !== 4) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('destinationOperatorName deve ter 4 letras.'));
            exit;
        }
        if ($postalCategoryCode === '') {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('postalCategoryCode inválido.'));
            exit;
        }
        if (!in_array($serviceSubclassCode, ['NX', 'IX'], true)) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('serviceSubclassCode deve ser NX ou IX.'));
            exit;
        }
        if (!in_array($unitType, ['1', '2'], true)) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('unitType inválido (1 ou 2).'));
            exit;
        }
        if (count($trackingNumbers) <= 0) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('Selecione pelo menos 1 pacote.'));
            exit;
        }
        if (count($trackingNumbers) > 500) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('Limite de 500 pacotes por container.'));
            exit;
        }

        // valida se todos existem e não estão usados
        $invalid = [];
        $already = [];
        if ($this->tableExists('correios_packet_etiquetas')) {
            foreach ($trackingNumbers as $t) {
                try {
                    $st = $this->connection->prepare('SELECT container_id FROM correios_packet_etiquetas WHERE tracking_number = ? LIMIT 1');
                    $st->execute([$t]);
                    $cid = $st->fetchColumn();
                    if ($cid === false) {
                        $invalid[] = $t;
                    } else {
                        if ((int) $cid > 0) $already[] = $t;
                    }
                } catch (\Exception $e) {
                    $invalid[] = $t;
                }
            }
        }
        if (!empty($invalid)) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('Tracking não encontrado: ' . implode(', ', array_slice($invalid, 0, 20))));
            exit;
        }
        if (!empty($already)) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('Já usado em outro container: ' . implode(', ', array_slice($already, 0, 20))));
            exit;
        }

        $payload = [
            'dispatchNumber' => $dispatchNumber,
            'originCountry' => $originCountry,
            'originOperatorName' => $originOperatorName,
            'destinationOperatorName' => $destinationOperatorName,
            'postalCategoryCode' => $postalCategoryCode,
            'serviceSubclassCode' => $serviceSubclassCode,
            'triageGroup' => $triageGroup,
            'unitList' => [
                [
                    'sequence' => 1,
                    'unitType' => $unitType,
                    'trackingNumbers' => $trackingNumbers,
                    'awbNumber' => $awb,
                ]
            ]
        ];

        $apiResp = null;
        $attempts = 0;
        $maxAttempts = 15;
        while ($attempts < $maxAttempts) {
            $attempts++;
            $apiResp = $this->svc->createUnits($payload);
            if (!empty($apiResp['success'])) {
                break;
            }

            $rawErr = (string) ($apiResp['error'] ?? '');
            if (stripos($rawErr, 'NEG-118') !== false || stripos($rawErr, 'duplicated dispatch number') !== false) {
                $payload['dispatchNumber'] = (int) ($payload['dispatchNumber'] ?? 0) + 1;
                continue;
            }

            break;
        }

        if (empty($apiResp) || empty($apiResp['success'])) {
            $err = $this->packetFriendlyError((string) (($apiResp['error'] ?? '') !== '' ? $apiResp['error'] : 'Falha ao criar unitizador.'), $apiResp['raw'] ?? null);
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode($err) . '&bulk=' . rawurlencode($bulk));
            exit;
        }

        // se houve retry por NEG-118, o dispatchNumber real pode ter sido incrementado
        $dispatchNumber = (int) ($payload['dispatchNumber'] ?? $dispatchNumber);

        $unitCode = '';
        $raw = $apiResp['raw'] ?? null;
        if (is_array($raw)) {
            $list = $raw['unitResponseList'] ?? null;
            if (is_array($list) && isset($list[0]) && is_array($list[0])) {
                $unitCode = (string) ($list[0]['unitCode'] ?? '');
            }
        }
        if ($unitCode === '') {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('Correios não retornou unitCode.') . '&bulk=' . rawurlencode($bulk));
            exit;
        }

        $containerId = 0;
        try {
            $stIns = $this->connection->prepare('INSERT INTO correios_packet_containers (dispatch_number, origin_country, origin_operator_name, destination_operator_name, postal_category_code, service_subclass_code, unit_type, awb, triage_group, tracking_numbers_json, unit_code, status, last_request_json, last_response_json, last_http_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stIns->execute([
                $dispatchNumber,
                'US',
                $originOperatorName,
                $destinationOperatorName,
                $postalCategoryCode,
                $serviceSubclassCode,
                $unitType,
                $awb !== '' ? $awb : null,
                $triageGroup !== '' ? $triageGroup : null,
                json_encode(array_values($trackingNumbers)),
                $unitCode,
                'created',
                json_encode($payload),
                json_encode($apiResp['raw'] ?? null),
                $apiResp['http_code'] ?? null,
            ]);
            $containerId = (int) $this->connection->lastInsertId();
        } catch (\Exception $e) {
            header('Location: /admin/correios-mundial/containers/novo?error=' . rawurlencode('Falha ao salvar container no banco.') . '&bulk=' . rawurlencode($bulk));
            exit;
        }

        // marca pacotes como usados
        if ($containerId > 0 && $this->tableExists('correios_packet_etiquetas')) {
            try {
                $in = implode(',', array_fill(0, count($trackingNumbers), '?'));
                $sql = 'UPDATE correios_packet_etiquetas SET container_id = ? WHERE tracking_number IN (' . $in . ')';
                $params = array_merge([$containerId], array_values($trackingNumbers));
                $stUp = $this->connection->prepare($sql);
                $stUp->execute($params);
            } catch (\Exception $e) {
            }
        }

        header('Location: /admin/correios-mundial/containers?success=' . rawurlencode('Container criado: ' . $unitCode));
        exit;
    }

    public function containerPdf(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            http_response_code(400);
            echo 'ID inválido.';
            return;
        }

        $this->ensurePacketContainersTable();
        if (!$this->tableExists('correios_packet_containers')) {
            http_response_code(500);
            echo 'Tabela correios_packet_containers não encontrada.';
            return;
        }

        $row = null;
        try {
            $st = $this->connection->prepare('SELECT * FROM correios_packet_containers WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $row = null;
        }
        if (!is_array($row)) {
            http_response_code(404);
            echo 'Container não encontrado.';
            return;
        }

        $unitCode = (string) ($row['unit_code'] ?? '');
        if ($unitCode === '') {
            http_response_code(400);
            echo 'unitCode não encontrado.';
            return;
        }

        $dispatchNumber = (string) ($row['dispatch_number'] ?? '');
        $serviceSubclassCode = (string) ($row['service_subclass_code'] ?? 'NX');
        $triageGroup = (string) ($row['triage_group'] ?? '1');
        $awb = (string) ($row['awb'] ?? '');
        $trackingNumbers = [];
        $rawTn = (string) ($row['tracking_numbers_json'] ?? '');
        if ($rawTn !== '') {
            $decoded = json_decode($rawTn, true);
            if (is_array($decoded)) $trackingNumbers = $decoded;
        }

        $subclassDescription = ($serviceSubclassCode === 'IX') ? 'PACKET EXPRESS' : 'PACKET STANDARD';
        $subclassImage = ($serviceSubclassCode === 'IX') ? 'packet-express.png' : 'packet-standard.png';

        $pluginAssets = dirname(__DIR__) . '/Plugins/woocommerce-package-redirect/assets/images/';
        $logoTransAmerica = $this->imageFileToDataUri($pluginAssets . 'logo-transamerica.png');
        $logoCorreios = $this->imageFileToDataUri($pluginAssets . 'logo-correios.png');
        $logoPacket = $this->imageFileToDataUri($pluginAssets . $subclassImage);

        $barcodeSvg = $this->code128Svg($unitCode, 55, 1);
        $barcodeImg = $this->svgToDataUri($barcodeSvg);

        $groups = $this->getPacketGroups();
        $selectedGroup = $groups['packet_standard_grupo_' . $triageGroup] ?? ($groups['packet_standard_grupo_1'] ?? []);

        $totalWeightKg = 0.0;
        if (!empty($trackingNumbers) && $this->tableExists('correios_packet_etiquetas')) {
            try {
                $in = implode(',', array_fill(0, count($trackingNumbers), '?'));
                $stW = $this->connection->prepare('SELECT last_request_json FROM correios_packet_etiquetas WHERE tracking_number IN (' . $in . ')');
                $stW->execute(array_values($trackingNumbers));
                $rows = $stW->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $lr = (string) ($r['last_request_json'] ?? '');
                    if ($lr === '') continue;
                    $jr = json_decode($lr, true);
                    if (is_array($jr) && isset($jr['packageList'][0]['totalWeight'])) {
                        $g = (float) ($jr['packageList'][0]['totalWeight'] ?? 0);
                        if ($g > 0) $totalWeightKg += ($g / 1000.0);
                    }
                }
            } catch (\Exception $e) {
            }
        }

        $html = '<!DOCTYPE html><html><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Etiqueta (Unitizador) - ' . htmlspecialchars($unitCode) . '</title>'
            . '<style>'
            . '@page { margin: 0px; }'
            . 'body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 0; }'
            . '.sheet { width: 220mm; height: 110mm; padding: 4mm; box-sizing: border-box; overflow: hidden; }'
            . 'table { width: 100%; border-collapse: collapse; margin: 0; page-break-inside: avoid; }'
            . 'tr, td { page-break-inside: avoid; }'
            . 'td { padding: 5px; border: 1px solid #000; vertical-align: top; }'
            . 'p { margin: 0; }'
            . '.bold { font-weight: bold; }'
            . '.en { font-size: 7pt; color: #555; }'
            . '.header { margin-bottom: 15px; text-align: center; }'
            . '.logo-container { width: 20mm; }'
            . '.logo-container img { width: 100%; }'
            . '.header2 { position: relative; }'
            . '.header2 .right { position: absolute; right: 10px; top: 10px; }'
            . '.header2 .logo-container { width: 20mm; height: 20mm; display: inline-block; vertical-align: middle; }'
            . '.header2 .subclass-description { font-size: 15pt; display: inline-block; vertical-align: middle; }'
            . '.barcode { text-align: center; font-size: 12px; padding: 5px 0; vertical-align: middle; }'
            . '.barcode img { width: 120mm; height: 18mm; margin-bottom: 10px; }'
            . '.group { position: relative; line-height: 16px; margin: 0; padding: 10px; }'
            . '.group .right { position: absolute; right: -4px; top: 42px; }'
            . '.group .right p { padding: 15px 60px; font-size: 20pt; color: white; background-color: black; }'
            . '</style>'
            . '</head><body>';

        $html .= '<div class="sheet">';

        $html .= '<table>'
            . '<tr>'
            . '<td style="width: 30mm;">'
            . '<div class="logo-container">'
            . ($logoTransAmerica !== '' ? '<img src="' . $logoTransAmerica . '" alt="Logo">' : '')
            . '</div>'
            . '</td>'
            . '<td colspan="2" class="header2">'
            . '<div class="left"><div class="logo-container">' . ($logoCorreios !== '' ? '<img src="' . $logoCorreios . '" alt=" ">' : '') . '</div></div>'
            . '<div class="right"><div class="logo-container">' . ($logoPacket !== '' ? '<img src="' . $logoPacket . '" alt=" ">' : '') . '</div>'
            . '<span class="subclass-description bold">' . htmlspecialchars($subclassDescription) . '</span></div>'
            . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td><p class="bold">N° do Despacho<br><span class="en">(Dispatch N°)</span></p><p>' . htmlspecialchars($dispatchNumber) . '</p></td>'
            . '<td colspan="2" class="bold">'
            . '<div class="group"><div class="left">'
            . '<p>' . htmlspecialchars((string) ($selectedGroup['company'] ?? '')) . '</p>'
            . '<p>' . htmlspecialchars((string) ($selectedGroup['center'] ?? '')) . '</p>'
            . '<p>' . htmlspecialchars((string) ($selectedGroup['location'] ?? '')) . '</p>'
            . '<p>CEP: ' . htmlspecialchars((string) ($selectedGroup['zipcode'] ?? '')) . ' - CNPJ: ' . htmlspecialchars((string) ($selectedGroup['cnpj'] ?? '')) . '</p>'
            . '</div><div class="right"><p>' . htmlspecialchars($triageGroup) . '</p></div></div>'
            . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td><p class="bold">N° Serial da Mala <span class="en">(Receptacle Serial Number)</span></p><p>' . htmlspecialchars($dispatchNumber) . '</p></td>'
            . '<td><p class="bold">N° Voo <span class="en">(Flight Number)</span></p><p>-</p></td>'
            . '<td><p class="bold">N° AWB <span class="en">(AWB#)</span></p><p>' . htmlspecialchars($awb) . '</p></td>'
            . '</tr>'
            . '<tr>'
            . '<td><p class="bold">Data do Despacho<br><span class="en">(Date)</span></p><p>' . date('d/m/Y') . '</p></td>'
            . '<td><p class="bold">Aeroporto de Origem <span class="en">(Airport of Departure)</span></p><p>-</p></td>'
            . '<td><p class="bold">Aeroporto de Destino <span class="en">(Airport of Offloading)</span></p><p>-</p></td>'
            . '</tr>'
            . '<tr>'
            . '<td><p class="bold">Quantidade de Itens<br><span class="en">(Quantity)</span></p><p>' . (int) count($trackingNumbers) . '</p></td>'
            . '<td rowspan="3" colspan="2" class="barcode"><div>'
            . ($barcodeImg !== '' ? '<img src="' . $barcodeImg . '" alt=" ">' : '')
            . '<p class="bold">' . htmlspecialchars($unitCode) . '</p>'
            . '</div></td>'
            . '</tr>'
            . '<tr><td><p class="bold">Peso Kg<br><span class="en">(Weight Kg)</span></p><p>' . htmlspecialchars(number_format($totalWeightKg, 2, ',', '.')) . '</p></td></tr>'
            . '<tr><td><p class="bold">Service</p><p>DDU</p></td></tr>'
            . '</table>';

        $html .= '</div></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 623.6, 311.8]);
        $dompdf->render();

        $safe = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $unitCode);
        if ($safe === '') $safe = 'unitizador';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="etiqueta_unitizador_' . $safe . '.pdf"');
        echo $dompdf->output();
    }

    public function etiquetaPdf(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $tracking = (string) $request->getParam('tracking');
        $tracking = trim($tracking);
        if ($tracking === '') {
            http_response_code(400);
            echo 'Tracking inválido.';
            return;
        }

        $this->ensurePacketEtiquetasTable();
        if (!$this->tableExists('correios_packet_etiquetas')) {
            http_response_code(500);
            echo 'Tabela correios_packet_etiquetas não encontrada.';
            return;
        }

        $etiqueta = null;
        try {
            $st = $this->connection->prepare('SELECT * FROM correios_packet_etiquetas WHERE tracking_number = ? LIMIT 1');
            $st->execute([$tracking]);
            $etiqueta = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $etiqueta = null;
        }

        if (!$etiqueta || empty($etiqueta['pedido_id'])) {
            http_response_code(404);
            echo 'Etiqueta não encontrada.';
            return;
        }

        $pedidoId = (int) ($etiqueta['pedido_id'] ?? 0);
        $pedidoModel = new PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($pedidoId);
        if (!is_array($pedido) || empty($pedido['id'])) {
            http_response_code(404);
            echo 'Pedido não encontrado.';
            return;
        }

        $destinatario = $this->buildRecipientFromPedido($pedido);
        $sender = $this->buildSenderFromConfig();

        $req = [];
        $reqJson = (string) ($etiqueta['last_request_json'] ?? '');
        if ($reqJson !== '') {
            $tmp = json_decode($reqJson, true);
            if (is_array($tmp)) $req = $tmp;
        }

        $pkg = [];
        if (!empty($req['packageList']) && is_array($req['packageList']) && !empty($req['packageList'][0]) && is_array($req['packageList'][0])) {
            $pkg = $req['packageList'][0];
        }

        $items = [];
        if (!empty($pkg['items']) && is_array($pkg['items'])) {
            $items = $pkg['items'];
        }

        $customerControlCode = (string) ($etiqueta['customer_control_code'] ?? ($pkg['customerControlCode'] ?? ''));

        $totalWeight = isset($pkg['totalWeight']) ? (int) $pkg['totalWeight'] : null;
        $packagingLength = isset($pkg['packagingLength']) ? (float) $pkg['packagingLength'] : null;
        $packagingWidth = isset($pkg['packagingWidth']) ? (float) $pkg['packagingWidth'] : null;
        $packagingHeight = isset($pkg['packagingHeight']) ? (float) $pkg['packagingHeight'] : null;
        $freightPaidValue = $pkg['freightPaidValue'] ?? null;
        $insurancePaidValue = $pkg['insurancePaidValue'] ?? null;

        $recipientZipDigits = preg_replace('/\D+/', '', (string) ($destinatario['recipientZipCode'] ?? ''));
        $recipientZipPretty = $recipientZipDigits;
        if (strlen($recipientZipDigits) === 8) {
            $recipientZipPretty = substr($recipientZipDigits, 0, 5) . '-' . substr($recipientZipDigits, 5, 3);
        }

        $safeTracking = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $tracking);
        if ($safeTracking === '') $safeTracking = 'tracking';

        $barTrackingSvg = $this->code128Svg($tracking, 60, 1);
        $barZipSvg = $recipientZipPretty !== '' ? $this->code128Svg(preg_replace('/\D+/', '', $recipientZipPretty), 60, 1) : '';

        $barTrackingImg = $this->svgToDataUri($barTrackingSvg);
        $barZipImg = $this->svgToDataUri($barZipSvg);

        $senderName = (string) ($sender['senderName'] ?? '');
        $senderAddr = trim((string) ($sender['senderAddress'] ?? '') . ' ' . (string) ($sender['senderAddressNumber'] ?? ''));
        $senderComp = trim((string) ($sender['senderAddressComplement'] ?? ''));
        $senderCity = (string) ($sender['senderCityName'] ?? '');
        $senderState = (string) ($sender['senderState'] ?? '');
        $senderZip = (string) ($sender['senderZipCode'] ?? '');
        $senderEmail = (string) ($sender['senderEmail'] ?? '');
        $senderWeb = (string) ($sender['senderWebsite'] ?? '');

        $destName = (string) ($destinatario['recipientName'] ?? '');
        $destAddr = trim((string) ($destinatario['recipientAddress'] ?? '') . ', ' . (string) ($destinatario['recipientAddressNumber'] ?? ''));
        $destComp = trim((string) ($destinatario['recipientAddressComplement'] ?? ''));
        $destCity = (string) ($destinatario['recipientCityName'] ?? '');
        $destState = (string) ($destinatario['recipientState'] ?? '');

        $orderRef = $customerControlCode !== '' ? $customerControlCode : ('PED-' . str_pad((string) $pedidoId, 6, '0', STR_PAD_LEFT));

        $distributionModality = (string) ($pkg['distributionModality'] ?? '33162');
        $modalityDescription = ($distributionModality === '33170') ? 'PACKET EXPRESS' : 'PACKET STANDARD';

        $pluginAssets = dirname(__DIR__) . '/Plugins/woocommerce-package-redirect/assets/images/';
        $logoTransAmerica = $this->imageFileToDataUri($pluginAssets . 'logo-transamerica.png');
        $logoCorreios = $this->imageFileToDataUri($pluginAssets . 'logo-correios.png');
        $logoPacket = $this->imageFileToDataUri($pluginAssets . (($distributionModality === '33170') ? 'packet-express.png' : 'packet-standard.png'));

        $senderContract = '';
        try {
            $db = Database::getConnection();
            $tableInfo = null;
            try {
                $st = $db->query('DESCRIBE configuracoes_sistema');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (is_array($cols) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                    $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                    $stmt->execute(['entrega_correios_packet_contrato']);
                    $senderContract = (string) ($stmt->fetchColumn() ?: '');
                }
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
        }

        $returnCompanyName = 'Braziliana';
        $returnStreet = 'Rua Votuporanga - 2276 / Eldorado';
        $returnZipCityUf = '15043-040 - São José do Rio Preto/SP';

        $totalWeightKg = 0.0;
        $pedidoItems = isset($pedido['items']) && is_array($pedido['items']) ? $pedido['items'] : [];
        foreach ($pedidoItems as $pi) {
            if (!is_array($pi)) continue;
            $q = (int) ($pi['quantidade'] ?? 0);
            if ($q <= 0) $q = 1;
            $w = 0.0;
            if (isset($pi['peso_kg']) && is_numeric($pi['peso_kg'])) {
                $w = (float) $pi['peso_kg'];
            }
            if ($w > 0) {
                $totalWeightKg += ($w * $q);
            }
        }
        $totalWeightKg = round($totalWeightKg, 2);

        $sumItems = 0.0;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $q = (int) ($it['quantity'] ?? 0);
            if ($q <= 0) $q = 1;
            $valUnit = (float) ($it['value'] ?? 0);
            $sumItems += ($valUnit * $q);
        }
        $sumItems = round($sumItems, 2);
        $freight = is_numeric($freightPaidValue) ? (float) $freightPaidValue : 0.0;
        $insurance = is_numeric($insurancePaidValue) ? (float) $insurancePaidValue : 0.0;
        $totalUsd = round($sumItems + $freight + $insurance, 2);

        // Reutiliza template do plugin (mesmo layout do print)
        $itemsAll = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $itemsAll[] = [
                'hsCode' => (string) ($it['hsCode'] ?? ''),
                'description' => (string) ($it['description'] ?? ''),
                'quantity' => (int) ($it['quantity'] ?? 0),
                'value' => (float) ($it['value'] ?? 0),
            ];
        }
        $itemsMain = array_slice($itemsAll, 0, 3);
        $itemsSupplementary = array_slice($itemsAll, 3);
        $itemWeight = (count($itemsAll) > 0) ? ($totalWeightKg / count($itemsAll)) : 0.0;

        $html = '<!DOCTYPE html><html lang="pt-BR"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Etiqueta - ' . htmlspecialchars($tracking) . '</title>'
            . '<style>'
            . '@page{margin:0;width:100mm;height:150mm;}'
            . 'body{font-family:Arial,sans-serif;}'
            . '.page{page-break-after:always;}'
            . '.page:last-child{page-break-after:avoid;}'
            . 'body p{font-size:8pt;margin:0;}'
            . '.header{margin:2.5mm;position:relative;}'
            . '.header p,.header strong{margin-top:10px;font-size:10pt;line-height:2px;}'
            . '.header .logo-container{width:20mm;height:20mm;}'
            . '.header .logo-container img{vertical-align:middle;width:100%;}'
            . '.header .left .logo-container img{margin-top:40%;}'
            . '.header .right{position:absolute;top:0;right:0;float:right;display:block;}'
            . '.header .right .logo-container{margin-left:auto;}'
            . '.header .right .logo-container img{vertical-align:middle;width:auto;height:100%;}'
            . '.header .correios-service-info{text-align:right;}'
            . '.tracking-number{position:relative;text-align:center;height:18mm;width:80mm;margin-bottom:6mm;margin-left:5mm;padding:0;}'
            . '.tracking-number p{font-size:15pt;}'
            . '.tracking-number .bar-code-container{height:100%;width:100%;}'
            . '.tracking-number .bar-code-container img{width:100%;height:100%;}'
            . '.tracking-number .service-class{position:absolute;font-size:20pt;top:9mm;left:85mm;}'
            . '.recipient{width:100%;}'
            . '.recipient p,.instructions p,.customs-declaration p{font-size:8pt;line-height:10pt;padding:0;}'
            . '.recipient .recipient-sign{margin:0 2.5mm;margin-bottom:2mm;position:relative;height:24px;width:100%;}'
            . '.recipient .recipient-sign .document{position:absolute;right:30mm;bottom:-5px;background-color:white;padding:0 1px 0.5px 1px;}'
            . '.recipient .signature-container{position:relative;width:95mm;}'
            . '.recipient .signature-container .line{position:absolute;width:80mm;left:55px;border-bottom:solid 1px black;}'
            . '.recipient .recipient-data{width:99.5%;padding:0;border:solid 1px black;height:26mm;position:relative;}'
            . '.recipient .recipient-data p{padding-left:2.5mm;font-size:8.5pt;}'
            . '.recipient .recipient-data .left{width:48mm;}'
            . '.recipient .recipient-data .section-title{color:white;background-color:black;padding:3px 2.5mm 3px 2.5mm;display:inline-block;margin-bottom:1.5mm;}'
            . '.recipient .recipient-data .right{position:absolute;top:2mm;right:0.4mm;margin-right:5mm;height:18mm;width:40mm;}'
            . '.recipient .recipient-data .bar-code-container{height:100%;width:100%;margin-bottom:1.5mm;}'
            . '.recipient .recipient-data .bar-code-container img{width:100%;height:100%;}'
            . '.recipient .recipient-data .right div{height:100%;width:100%;}'
            . '.recipient .recipient-data .right p{text-align:center;}'
            . '.instructions{margin:0 2.5mm;position:relative;height:26mm;margin-bottom:10px;}'
            . '.instructions .non-nationalization-policy{position:relative;width:100%;height:10pt;}'
            . '.instructions .non-nationalization-policy .square{position:absolute;left:0;top:1pt;height:6pt;width:8pt;border:solid 1px black;font-size:5pt;text-align:center;}'
            . '.instructions .non-nationalization-policy p{position:absolute;left:4mm;top:0;}'
            . '.instructions .return-section{width:60mm;line-height:0;}'
            . '.instructions .return-section .note{font-size:6pt;line-height:6px;}'
            . '.instructions .return-section .title{text-align:center;display:inline-block;width:100%;margin:0;padding:0;}'
            . '.instructions .return-section .title .line{display:inline-block;vertical-align:middle;width:30%;height:1px;background-color:black;margin-top:4px;}'
            . '.instructions .return-section p{line-height:12px;}'
            . '.instructions .return-section .title p{display:inline-block;vertical-align:middle;margin:4px 4px 0 4px;}'
            . '.instructions .right{position:absolute;top:5mm;right:2.5mm;display:block;}'
            . '.instructions .right p{font-size:10pt;}'
            . '.instructions .complaints p{line-height:9px;font-size:6pt;}'
            . '.customs-declaration{margin:1mm 2.5mm 2.5mm 2.5mm;}'
            . '.customs-declaration table{width:100%;border-collapse:collapse;}'
            . '.customs-declaration th,.customs-declaration td{border:1px solid #000;font-size:6pt;font-weight:normal;padding:0;text-align:left;word-wrap:break-word;word-break:break-word;}'
            . '.customs-declaration .table-header,.customs-declaration .sh{white-space:nowrap;}'
            . '.suplementary.customs-declaration{margin:0;width:100%;height:100%;}'
            . '.suplementary.customs-declaration th,.suplementary.customs-declaration td{padding:2;font-size:6pt;text-align:center;vertical-align:middle;}'
            . '.to-sender{padding:2.5mm;}'
            . '.to-sender th,.to-sender td{font-size:10pt;padding:5px 40px 5px 5px;}'
            . '</style>'
            . '</head><body>';

        $html .= '<div class="page">'
            . '<div class="header">'
            . '<div class="left">'
            . '<div class="logo-container">'
            . ($logoTransAmerica !== '' ? '<img src="' . $logoTransAmerica . '" alt=" ">' : '')
            . '</div>'
            . '<div class="order-info">'
            . '<p>Order #: ' . htmlspecialchars((string) $pedidoId) . '</p>'
            . '<p>' . htmlspecialchars((string) ($pkg['taxPaymentMethod'] ?? 'DDU')) . '</p>'
            . '</div>'
            . '<div class="logo-container" style="display:inline-block;position:absolute;top:-7.5mm;left:25mm;">'
            . ($logoCorreios !== '' ? '<img src="' . $logoCorreios . '" alt=" ">' : '')
            . '</div>'
            . '</div>'
            . '<div class="right">'
            . '<div class="logo-container">'
            . ($logoPacket !== '' ? '<img src="' . $logoPacket . '" alt=" ">' : '')
            . '</div>'
            . '<div class="correios-service-info">'
            . '<p><strong>' . htmlspecialchars($modalityDescription) . '</strong></p>'
            . '<p>Contrato <strong>' . htmlspecialchars($senderContract) . '</strong></p>'
            . '</div>'
            . '</div>'
            . '</div>';

        $html .= '<div class="tracking-number">'
            . '<p><strong>' . htmlspecialchars($tracking) . '</strong></p>'
            . '<div class="bar-code-container">'
            . ($barTrackingImg !== '' ? '<img src="' . $barTrackingImg . '" alt=" ">' : '')
            . '</div>'
            . '<p class="service-class"><strong>US</strong></p>'
            . '</div>';

        $html .= '<div class="recipient">'
            . '<div class="recipient-sign">'
            . '<div class="signature-container"><p>Recebedor:</p><div class="line"></div></div>'
            . '<div class="signature-container"><p>Assinatura:</p><div class="line"></div></div>'
            . '<p class="document">Documento:</p>'
            . '</div>'
            . '<div class="recipient-data">'
            . '<div class="left">'
            . '<p class="section-title"><strong>DESTINATÁRIO</strong></p>'
            . '<p>' . htmlspecialchars($destName) . '</p>'
            . '<p>' . htmlspecialchars($destAddr) . '</p>'
            . '<p>' . htmlspecialchars($destComp) . '</p>'
            . '<p>' . htmlspecialchars($destCity) . '/' . htmlspecialchars($destState) . '</p>'
            . '</div>'
            . '<div class="right">'
            . '<div class="bar-code-container">'
            . ($barZipImg !== '' ? '<img src="' . $barZipImg . '" alt=" ">' : '')
            . '</div>'
            . '<p style="font-size:15pt;"><strong>' . htmlspecialchars($recipientZipPretty) . '</strong></p>'
            . '</div>'
            . '</div>'
            . '</div>';

        $nonNat = (string) ($pkg['nonNationalizationInstruction'] ?? 'RETURNTOORIGIN');
        $html .= '<div class="instructions">'
            . '<div class="left">'
            . '<p><strong>Instrução do Remetente no caso de não nacionalização:</strong></p>'
            . '<div class="non-nationalization-policy">'
            . '<div class="square">' . ($nonNat === 'RETURNTOORIGIN' ? 'X' : '') . '</div>'
            . '<p>Retorno à origem</p>'
            . '</div>'
            . '<div class="complaints">'
            . '<p>Dúvidas e reclamações:</p>'
            . '<p>' . htmlspecialchars($senderEmail) . ' / ' . htmlspecialchars($senderWeb) . '</p>'
            . '</div>'
            . '<div class="return-section">'
            . '<div class="title"><div class="line"></div><p><strong>DEVOLUÇÃO:</strong></p><div class="line"></div></div>'
            . '<p class="note">(Em caso de não entrega ao remetente, entregar para:)</p>'
            . '<p>' . htmlspecialchars($returnCompanyName) . '</p>'
            . '<p>' . htmlspecialchars($returnStreet) . '</p>'
            . '<p>' . htmlspecialchars($returnZipCityUf) . '</p>'
            . '</div>'
            . '</div>'
            . '<div class="right">'
            . '<p><strong>Remetente:</strong></p>'
            . '<p>' . htmlspecialchars($senderName) . '</p>'
            . '<p>' . htmlspecialchars($senderAddr) . '</p>'
            . '<p>' . htmlspecialchars($senderCity) . '</p>'
            . '<p>' . htmlspecialchars((string) ($sender['senderCountryCode'] ?? 'US')) . '</p>'
            . '<p><strong>Braziliana</strong></p>'
            . '</div>'
            . '</div>';

        $html .= '<div class="customs-declaration"><table>'
            . '<tr><th colspan="3"><strong>Declaração para Alfândega</strong></th><th colspan="3">Pode ser aberto Ex Officio 1/1</th></tr>'
            . '<thead><tr>'
            . '<th class="table-header sh">Cod SH</th>'
            . '<th class="table-header">Qtde</th>'
            . '<th class="table-header">Descrição do Conteúdo</th>'
            . '<th class="table-header">Peso KG</th>'
            . '<th class="table-header">Unit USD</th>'
            . '<th class="table-header">Valor USD</th>'
            . '</tr></thead><tbody>';

        $totalItemsValue = 0.0;
        foreach ($itemsMain as $item) {
            $q = (int) ($item['quantity'] ?? 0);
            if ($q <= 0) $q = 1;
            $v = (float) ($item['value'] ?? 0);
            $itemTotal = $v * $q;
            $totalItemsValue += $itemTotal;
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string) $item['hsCode']) . '</td>'
                . '<td>' . (int) $q . '</td>'
                . '<td>' . htmlspecialchars((string) $item['description']) . '</td>'
                . '<td>' . htmlspecialchars(number_format($itemWeight / $q, 2, ',', '.')) . '</td>'
                . '<td>' . htmlspecialchars(number_format($v, 2, ',', '.')) . '</td>'
                . '<td>' . htmlspecialchars(number_format($itemTotal, 2, ',', '.')) . '</td>'
                . '</tr>';
        }
        $html .= '<tr><th colspan="5">Frete USD:</th><td>' . htmlspecialchars(number_format($freight, 2, ',', '.')) . '</td></tr>';
        $html .= '<tr><th colspan="5">Seguro USD:</th><td>' . htmlspecialchars(number_format($insurance, 2, ',', '.')) . '</td></tr>';
        $totalUsd2 = $totalItemsValue + $freight + $insurance;
        $html .= '<tr><th colspan="5">Total USD (Mercadorias + Frete + Seguro):</th><td>' . htmlspecialchars(number_format($totalUsd2, 2, ',', '.')) . '</td></tr>';
        $html .= '</tbody></table></div>';
        $html .= '</div>';

        $html .= '<div class="page"><div class="to-sender">'
            . '<table border="1" style="width:100%;border-collapse:collapse;text-align:left;">'
            . '<thead><tr><th colspan="5">AO REMETENTE</th></tr></thead><tbody>'
            . '<tr><td colspan="2"></td><td colspan="3">MUDOU-SE</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">ENDEREÇO INSUFICIENTE</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">NÃO EXISTE O Nº INDICADO</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">FALECIDO</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">DESCONHECIDO</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">RECUSADO</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">AUSENTE</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">NÃO PROCURADO</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">OUTROS:</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">INFORMAÇÃO PRESTADA PELO<br>PORTEIRO OU SÍNDICO</td></tr>'
            . '<tr><td colspan="2"></td><td colspan="3">REINTEGRADO AO SERVIÇO POSTAL<br>EM ____/____/________</td></tr>'
            . '<tr><td colspan="5">DATA:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;RUBRICA:</td></tr>'
            . '</tbody></table>'
            . '</div></div>';

        $html .= '<div class="page"><div class="suplementary customs-declaration">'
            . '<table>'
            . '<tr><th colspan="5"><strong>SUPPLEMENTARY</strong></th><th>' . htmlspecialchars($tracking) . '</th></tr>'
            . '<tr><th colspan="6"><strong>CUSTOMS DECLARATION</strong></th></tr>'
            . '<tr>'
            . '<th>COD.SH</th><th>QUANTITY</th><th>DESCRIPTION</th><th>WEIGHT (KG)</th><th>UNI (US)</th><th>VALUE (US)</th>'
            . '</tr>';

        $total3W = 0.0;
        $total3V = 0.0;
        foreach ($itemsSupplementary as $item) {
            $q = (int) ($item['quantity'] ?? 0);
            if ($q <= 0) $q = 1;
            $v = (float) ($item['value'] ?? 0);
            $line = $v * $q;
            $total3W += $itemWeight;
            $total3V += $line;
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string) $item['hsCode']) . '</td>'
                . '<td>' . (int) $q . '</td>'
                . '<td>' . htmlspecialchars((string) $item['description']) . '</td>'
                . '<td>' . htmlspecialchars(number_format($itemWeight / $q, 2, ',', '.')) . '</td>'
                . '<td>' . htmlspecialchars(number_format($v, 2, ',', '.')) . '</td>'
                . '<td>' . htmlspecialchars(number_format($line, 2, ',', '.')) . '</td>'
                . '</tr>';
        }
        $html .= '<tr><th colspan="3">TOTAL</th><th>' . htmlspecialchars(number_format($totalWeightKg, 2, ',', '.')) . '</th><th></th><th>' . htmlspecialchars(number_format($total3V, 2, ',', '.')) . '</th></tr>';
        $html .= '</table></div></div>';

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        // Mesmo tamanho do template do plugin: 100mm x 150mm
        $dompdf->setPaper([0, 0, 283.46, 425.20]);
        $dompdf->render();

        $filename = 'etiqueta_PACKET_' . $safeTracking . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
    }
}
