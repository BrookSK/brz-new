<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\CorreiosPacketService;
use App\Models\PedidoEcommerce;
use Config\Database;

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

    private function onlyDigits(string $v): string {
        $v = (string) $v;
        $v = preg_replace('/\D+/', '', $v);
        return (string) $v;
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
            $this->json([
                'success' => false,
                'error' => (string) ($resp['error'] ?? 'Falha ao gerar etiqueta.'),
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
}
