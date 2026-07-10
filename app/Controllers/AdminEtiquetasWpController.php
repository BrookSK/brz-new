<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\WordPressEtiquetasService;
use App\Models\PedidoEcommerce;
use Config\Database;

/**
 * Controller para integração de etiquetas via WordPress.
 * 
 * Faz requisições para o WordPress Etiquetas (etiquetas.brazilianashop.com.br)
 * para criar pacotes, containers, faturas e embarques.
 * 
 * O WordPress mantém o registro e gera os PDFs.
 */
class AdminEtiquetasWpController extends Controller
{
    private WordPressEtiquetasService $wp;
    private \PDO $connection;

    public function __construct()
    {
        $this->wp = new WordPressEtiquetasService();
        $this->connection = Database::getConnection();
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function onlyDigits(string $v): string
    {
        return (string) preg_replace('/\D+/', '', $v);
    }

    private function getUsdToBrlRate(): float
    {
        try {
            $st = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['sistema_usd_brl_rate']);
            $v = (float) str_replace(',', '.', (string) ($st->fetchColumn() ?: '0'));
            if ($v > 1.01) return $v;
        } catch (\Exception $e) {}
        return 5.85;
    }

    private function pickFirstNonEmpty(array $row, array $keys): string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row)) {
                $v = trim((string) ($row[$k] ?? ''));
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    private function buildRecipientFromPedido(array $pedido): array
    {
        $destNome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? ''));
        $destEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ''));
        $destTel = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ''));

        $destDoc = $this->pickFirstNonEmpty($pedido, ['cliente_cpf_cnpj', 'cpf_cnpj', 'cpfCnpj', 'cpf', 'cnpj', 'documento', 'document']);
        if ($destDoc === '' && isset($pedido['cliente']) && is_array($pedido['cliente'])) {
            $destDoc = $this->pickFirstNonEmpty((array) $pedido['cliente'], ['cpf_cnpj', 'cpfCnpj', 'cpf', 'cnpj', 'documento', 'document']);
        }
        $destDocDigits = $this->onlyDigits($destDoc);

        $docType = 'CPF';
        if (strlen($destDocDigits) === 14) {
            $docType = 'CNPJ';
        }
        if ($docType === 'CPF' && $destDocDigits !== '' && strlen($destDocDigits) < 11) {
            $destDocDigits = str_pad($destDocDigits, 11, '0', STR_PAD_LEFT);
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
            'recipientAddressNumber' => substr(trim($numero), 0, 10),
            'recipientAddressComplement' => substr(trim($complemento), 0, 50),
            'recipientCityName' => substr(trim($cidade), 0, 100),
            'recipientState' => substr(strtoupper(trim($uf)), 0, 2),
            'recipientZipCode' => substr($cep, 0, 8),
            'recipientEmail' => substr(trim($destEmail), 0, 50),
            'recipientPhoneNumber' => $telDigits,
        ];
    }

    // =========================================================
    // PÁGINA PRINCIPAL - TESTE DE INTEGRAÇÃO + FLUXO
    // =========================================================

    public function index(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->view('admin/etiquetas-wp/index', [
            'sidebarActive' => 'correios-mundial',
        ]);
    }

    // =========================================================
    // TESTE DE CONEXÃO
    // =========================================================

    public function testarConexao(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $results = [];

        // Teste 1: Saldo
        $t1Start = microtime(true);
        $balance = $this->wp->getBalance();
        $t1Time = round((microtime(true) - $t1Start) * 1000);
        $results['balance'] = [
            'success' => !empty($balance['success']),
            'data' => $balance,
            'time_ms' => $t1Time,
        ];

        // Detectar ambiente a partir do retorno do WordPress
        $ambiente = 'DESCONHECIDO';
        if (!empty($balance['ambiente'])) {
            $ambiente = (string) $balance['ambiente'];
        }

        // Teste 2: Listar pacotes
        $t2Start = microtime(true);
        $packages = $this->wp->listPackages(['per_page' => 1]);
        $t2Time = round((microtime(true) - $t2Start) * 1000);
        $results['list_packages'] = [
            'success' => !empty($packages['success']),
            'total' => $packages['total'] ?? 0,
            'time_ms' => $t2Time,
        ];

        // Teste 3: Listar containers
        $t3Start = microtime(true);
        $containers = $this->wp->listContainers(['per_page' => 1]);
        $t3Time = round((microtime(true) - $t3Start) * 1000);
        $results['list_containers'] = [
            'success' => !empty($containers['success']),
            'total' => $containers['total'] ?? 0,
            'time_ms' => $t3Time,
        ];

        // Teste 4: Listar faturas
        $t4Start = microtime(true);
        $bills = $this->wp->listBills(['per_page' => 1]);
        $t4Time = round((microtime(true) - $t4Start) * 1000);
        $results['list_bills'] = [
            'success' => !empty($bills['success']),
            'total' => $bills['total'] ?? 0,
            'time_ms' => $t4Time,
        ];

        // Teste 5: Listar embarques
        $t5Start = microtime(true);
        $departures = $this->wp->listDepartures(['per_page' => 1]);
        $t5Time = round((microtime(true) - $t5Start) * 1000);
        $results['list_departures'] = [
            'success' => !empty($departures['success']),
            'total' => $departures['total'] ?? 0,
            'time_ms' => $t5Time,
        ];

        $allOk = $results['balance']['success'] 
            && $results['list_packages']['success'] 
            && $results['list_containers']['success']
            && $results['list_bills']['success']
            && $results['list_departures']['success'];

        $this->json([
            'success' => $allOk,
            'message' => $allOk ? 'Todos os testes passaram!' : 'Alguns testes falharam.',
            'ambiente' => $ambiente ?? 'DESCONHECIDO',
            'results' => $results,
        ]);
    }

    // =========================================================
    // GERAR ETIQUETAS VIA WORDPRESS
    // =========================================================

    /**
     * Gerar etiqueta individual via WordPress.
     * POST /admin/etiquetas-wp/gerar-etiqueta
     * Body JSON: { pedido_id: int }
     */
    public function gerarEtiqueta(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $body = json_decode(file_get_contents('php://input'), true);
        $pedidoId = (int) ($body['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => 'pedido_id inválido'], 400);
            return;
        }

        $pedidoModel = new PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($pedidoId);
        if (!is_array($pedido) || empty($pedido['id'])) {
            $this->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
            return;
        }

        $status = strtolower(trim((string) ($pedido['status'] ?? '')));
        if (!in_array($status, ['produto_consolidado', 'consolidado'], true)) {
            $this->json(['success' => false, 'error' => 'Pedido não está em Caixa Fechada (status: ' . $status . ')'], 400);
            return;
        }

        // Montar dados do pacote
        $packageData = $this->buildPackagePayload($pedido, $body);
        if (isset($packageData['_error'])) {
            $this->json(['success' => false, 'error' => $packageData['_error']], 400);
            return;
        }

        // Chamar WordPress
        $resp = $this->wp->createPackage($packageData);

        if (!empty($resp['success'])) {
            $tracking = $resp['tracking_number'] ?? '';

            // Salvar no banco local também
            $this->salvarEtiquetaLocal($pedidoId, $packageData['customerControlCode'], $tracking, $resp);

            // Atualizar status do pedido
            try {
                $pedidoModel->atualizarStatus($pedidoId, 'etiqueta_gerada', 'Etiqueta via WordPress - Rastreio: ' . $tracking, $_SESSION['usuario_id'] ?? null);
            } catch (\Exception $e) {}

            $this->json([
                'success' => true,
                'pedido_id' => $pedidoId,
                'tracking_number' => $tracking,
                'wp_post_id' => $resp['wp_post_id'] ?? null,
            ]);
        } else {
            $this->json([
                'success' => false,
                'error' => $resp['error'] ?? 'Erro desconhecido do WordPress',
                'pedido_id' => $pedidoId,
            ], 400);
        }
    }

    /**
     * Gerar etiquetas em massa via WordPress.
     * POST /admin/etiquetas-wp/gerar-etiquetas-massa
     * Body JSON: { ids: [int, ...] }
     */
    public function gerarEtiquetasMassa(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode(file_get_contents('php://input'), true);
        $ids = $body['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'Nenhum pedido selecionado']);
            exit;
        }

        $ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'IDs inválidos']);
            exit;
        }

        $pedidoModel = new PedidoEcommerce();
        $results = [];

        foreach ($ids as $pid) {
            $result = ['pedido_id' => $pid, 'success' => false, 'error' => '', 'tracking_number' => ''];

            try {
                // Verificar se já tem etiqueta
                if ($this->tableExists('correios_packet_etiquetas')) {
                    $stCheck = $this->connection->prepare('SELECT id FROM correios_packet_etiquetas WHERE pedido_id = ? LIMIT 1');
                    $stCheck->execute([$pid]);
                    if ((int) ($stCheck->fetchColumn() ?: 0) > 0) {
                        $result['error'] = 'Já possui etiqueta';
                        $results[] = $result;
                        continue;
                    }
                }

                $pedido = $pedidoModel->getComDetalhes($pid);
                if (!is_array($pedido) || empty($pedido['id'])) {
                    $result['error'] = 'Pedido não encontrado';
                    $results[] = $result;
                    continue;
                }

                $status = strtolower(trim((string) ($pedido['status'] ?? '')));
                if (!in_array($status, ['produto_consolidado', 'consolidado'], true)) {
                    $result['error'] = 'Não está em Caixa Fechada';
                    $results[] = $result;
                    continue;
                }

                $packageData = $this->buildPackagePayload($pedido);
                if (isset($packageData['_error'])) {
                    $result['error'] = $packageData['_error'];
                    $results[] = $result;
                    continue;
                }

                $resp = $this->wp->createPackage($packageData);

                if (!empty($resp['success'])) {
                    $tracking = $resp['tracking_number'] ?? '';
                    $this->salvarEtiquetaLocal($pid, $packageData['customerControlCode'], $tracking, $resp);

                    try { $pedidoModel->atualizarStatus($pid, 'etiqueta_gerada', 'Etiqueta via WP em massa - Rastreio: ' . $tracking, $_SESSION['usuario_id'] ?? null); } catch (\Exception $e) {}

                    $result['success'] = true;
                    $result['tracking_number'] = $tracking;
                } else {
                    $result['error'] = $resp['error'] ?? 'Erro WordPress';
                }
            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }

            $results[] = $result;
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        echo json_encode(['success' => true, 'results' => $results, 'generated' => $successCount, 'failed' => $failCount]);
        exit;
    }

    // =========================================================
    // CONTAINERS VIA WORDPRESS
    // =========================================================

    /**
     * Criar container via WordPress.
     * POST /admin/etiquetas-wp/criar-container
     */
    public function criarContainer(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $body = json_decode(file_get_contents('php://input'), true);

        $data = [
            'dispatchNumber' => (int) ($body['dispatchNumber'] ?? 0),
            'trackingCodes' => $body['trackingCodes'] ?? [],
            'originCountry' => $body['originCountry'] ?? 'US',
            'originOperatorName' => $body['originOperatorName'] ?? 'USPS',
            'destinationOperatorName' => $body['destinationOperatorName'] ?? 'CWBA',
            'postalCategoryCode' => $body['postalCategoryCode'] ?? 'A',
            'serviceSubclassCode' => $body['serviceSubclassCode'] ?? 'NX',
            'unitType' => $body['unitType'] ?? '2',
            'awb' => $body['awb'] ?? '',
            'triageGroup' => $body['triageGroup'] ?? '1',
        ];

        if ($data['dispatchNumber'] <= 0) {
            $this->json(['success' => false, 'error' => 'dispatchNumber inválido'], 400);
            return;
        }
        if (empty($data['trackingCodes'])) {
            $this->json(['success' => false, 'error' => 'Selecione pelo menos 1 pacote'], 400);
            return;
        }

        $resp = $this->wp->createContainer($data);
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    // =========================================================
    // FATURAS VIA WORDPRESS
    // =========================================================

    /**
     * Criar fatura via WordPress.
     * POST /admin/etiquetas-wp/criar-fatura
     */
    public function criarFatura(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $body = json_decode(file_get_contents('php://input'), true);
        $containerIds = $body['containerIds'] ?? [];

        if (!is_array($containerIds) || empty($containerIds)) {
            $this->json(['success' => false, 'error' => 'Selecione pelo menos 1 container'], 400);
            return;
        }

        $resp = $this->wp->createBill(['containerIds' => $containerIds]);
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    // =========================================================
    // EMBARQUES VIA WORDPRESS
    // =========================================================

    /**
     * Criar embarque via WordPress.
     * POST /admin/etiquetas-wp/criar-embarque
     */
    public function criarEmbarque(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $body = json_decode(file_get_contents('php://input'), true);

        $required = ['billIds', 'flightNumber', 'airlineCode', 'departureDate', 'departureAirportCode', 'arrivalDate', 'arrivalAirportCode'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                $this->json(['success' => false, 'error' => "Campo obrigatório: {$field}"], 400);
                return;
            }
        }

        $resp = $this->wp->createDeparture($body);
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    // =========================================================
    // DELETAR/DESVINCULAR
    // =========================================================

    public function deletarContainer(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $body = json_decode(file_get_contents('php://input'), true);
        $wpPostId = (int) ($body['wp_post_id'] ?? 0);
        if ($wpPostId <= 0) {
            $this->json(['success' => false, 'error' => 'wp_post_id inválido'], 400);
            return;
        }

        $resp = $this->wp->deleteContainer($wpPostId);
        
        // Debug: logar a resposta completa do WordPress
        error_log('[BRZ-DELETE-CONTAINER] wp_post_id=' . $wpPostId . ' | resp=' . json_encode($resp));
        
        // Garantir que sempre tem a chave 'error' se não teve sucesso
        if (empty($resp['success']) && empty($resp['error'])) {
            $resp['error'] = $resp['message'] ?? $resp['raw'] ?? 'Erro desconhecido ao deletar container';
        }
        
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    public function deletarFatura(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $body = json_decode(file_get_contents('php://input'), true);
        $wpPostId = (int) ($body['wp_post_id'] ?? 0);
        if ($wpPostId <= 0) {
            $this->json(['success' => false, 'error' => 'wp_post_id inválido'], 400);
            return;
        }

        $resp = $this->wp->deleteBill($wpPostId);
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    public function deletarEmbarque(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $body = json_decode(file_get_contents('php://input'), true);
        $wpPostId = (int) ($body['wp_post_id'] ?? 0);
        if ($wpPostId <= 0) {
            $this->json(['success' => false, 'error' => 'wp_post_id inválido'], 400);
            return;
        }

        $resp = $this->wp->deleteDeparture($wpPostId);
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    // =========================================================
    // DOWNLOAD DE PDFs
    // =========================================================

    /**
     * Download do PDF da etiqueta de um pacote.
     * GET /admin/etiquetas-wp/pdf/pacote/{id}
     */
    public function pdfPacote(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $wpPostId = (int) $request->getParam('id');
        if ($wpPostId <= 0) {
            http_response_code(400);
            echo 'ID inválido.';
            return;
        }

        $result = $this->wp->downloadPackagePdf($wpPostId);
        $this->servePdf($result, 'etiqueta_' . $wpPostId . '.pdf');
    }

    /**
     * Download do PDF do container.
     * GET /admin/etiquetas-wp/pdf/container/{id}
     */
    public function pdfContainer(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $wpPostId = (int) $request->getParam('id');
        if ($wpPostId <= 0) {
            http_response_code(400);
            echo 'ID inválido.';
            return;
        }

        $result = $this->wp->downloadContainerPdf($wpPostId);
        $this->servePdf($result, 'container_' . $wpPostId . '.pdf');
    }

    /**
     * Download do PDF da fatura.
     * GET /admin/etiquetas-wp/pdf/fatura/{id}
     */
    public function pdfFatura(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $wpPostId = (int) $request->getParam('id');
        if ($wpPostId <= 0) {
            http_response_code(400);
            echo 'ID inválido.';
            return;
        }

        $result = $this->wp->downloadBillPdf($wpPostId);
        $this->servePdf($result, 'fatura_' . $wpPostId . '.pdf');
    }

    /**
     * Serve o PDF para download ou mostra erro.
     */
    private function servePdf($result, string $filename): void
    {
        if (is_string($result)) {
            // É o PDF binário
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($result));
            echo $result;
            exit;
        }

        // É um array de erro
        http_response_code(is_array($result) ? ((int) ($result['http_code'] ?? 500)) : 500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }

    // =========================================================
    // LISTAR DADOS DO WORDPRESS
    // =========================================================

    public function listarPacotes(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $params = [];
        if ($request->getParam('without_container')) $params['without_container'] = '1';
        if ($request->getParam('per_page')) $params['per_page'] = (int) $request->getParam('per_page');

        $resp = $this->wp->listPackages($params);

        // Enriquecer com pedido_id local para exibição formatada
        if (!empty($resp['success']) && !empty($resp['data']) && is_array($resp['data'])) {
            try {
                $trackings = array_filter(array_map(function($p) { return $p['tracking_code'] ?? ''; }, $resp['data']));
                if (!empty($trackings)) {
                    $in = implode(',', array_fill(0, count($trackings), '?'));
                    $st = $this->connection->prepare("SELECT tracking_number, pedido_id FROM correios_packet_etiquetas WHERE tracking_number IN ({$in})");
                    $st->execute(array_values($trackings));
                    $map = [];
                    foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $map[$row['tracking_number']] = (int) $row['pedido_id'];
                    }
                    foreach ($resp['data'] as &$pkg) {
                        $tc = $pkg['tracking_code'] ?? '';
                        if (isset($map[$tc])) {
                            $pkg['pedido_id_local'] = $map[$tc];
                        }
                    }
                    unset($pkg);
                }
            } catch (\Exception $e) {}
        }

        $this->json($resp);
    }

    public function listarContainers(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $params = [];
        if ($request->getParam('without_bill')) $params['without_bill'] = '1';

        $resp = $this->wp->listContainers($params);
        $this->json($resp);
    }

    public function listarFaturas(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $params = [];
        if ($request->getParam('without_departure')) $params['without_departure'] = '1';
        if ($request->getParam('per_page')) $params['per_page'] = (int) $request->getParam('per_page');

        $resp = $this->wp->listBills($params);
        $this->json($resp);
    }

    public function listarEmbarques(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $resp = $this->wp->listDepartures();
        $this->json($resp);
    }

    // =========================================================
    // HELPERS INTERNOS
    // =========================================================

    private function buildPackagePayload(array $pedido, array $overrides = []): array
    {
        $destinatario = $this->buildRecipientFromPedido($pedido);

        // Validações do destinatário
        $zipDigits = $destinatario['recipientZipCode'];
        if (strlen($zipDigits) !== 8) {
            return ['_error' => 'CEP inválido (deve ter 8 dígitos)'];
        }
        $phoneDigits = $destinatario['recipientPhoneNumber'];
        if ($phoneDigits === '' || !in_array(strlen($phoneDigits), [10, 11], true)) {
            return ['_error' => 'Telefone inválido (10 ou 11 dígitos)'];
        }
        $destEmail = $destinatario['recipientEmail'];
        if ($destEmail === '' || filter_var($destEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['_error' => 'E-mail inválido'];
        }
        $docType = $destinatario['recipientDocumentType'];
        $docNum = $destinatario['recipientDocumentNumber'];
        if ($docType === 'CPF' && strlen($docNum) !== 11) {
            return ['_error' => 'CPF inválido (11 dígitos)'];
        }
        if ($docType === 'CNPJ' && strlen($docNum) !== 14) {
            return ['_error' => 'CNPJ inválido (14 dígitos)'];
        }

        // Peso e dimensões
        $pesoKg = isset($pedido['peso_total']) ? (float) $pedido['peso_total'] : 0.0;
        $alturaCm = isset($pedido['altura']) ? (float) $pedido['altura'] : 0.0;
        $larguraCm = isset($pedido['largura']) ? (float) $pedido['largura'] : 0.0;
        $comprimentoCm = isset($pedido['comprimento']) ? (float) $pedido['comprimento'] : 0.0;

        $totalWeight = (int) max(0, round($pesoKg * 1000));
        $packagingLength = $comprimentoCm > 0 ? $comprimentoCm : 16;
        $packagingWidth = $larguraCm > 0 ? $larguraCm : 11;
        $packagingHeight = $alturaCm > 0 ? $alturaCm : 2;

        // Override por formulário (se veio do body)
        if (!empty($overrides['totalWeight'])) $totalWeight = (int) $overrides['totalWeight'];
        if (!empty($overrides['packagingLength'])) $packagingLength = (float) $overrides['packagingLength'];
        if (!empty($overrides['packagingWidth'])) $packagingWidth = (float) $overrides['packagingWidth'];
        if (!empty($overrides['packagingHeight'])) $packagingHeight = (float) $overrides['packagingHeight'];

        if ($totalWeight <= 0) return ['_error' => 'Peso não informado'];
        if ($totalWeight > 30000) return ['_error' => 'Peso excede 30kg'];
        if ($packagingLength < 16 || $packagingLength > 100) return ['_error' => 'Comprimento inválido (16-100cm)'];
        if ($packagingWidth < 11 || $packagingWidth > 100) return ['_error' => 'Largura inválida (11-100cm)'];
        if ($packagingHeight < 2 || $packagingHeight > 100) return ['_error' => 'Altura inválida (2-100cm)'];
        if (($packagingLength + $packagingWidth + $packagingHeight) > 200) return ['_error' => 'Soma dimensões > 200cm'];

        // Itens
        $itemsIn = isset($pedido['items']) && is_array($pedido['items']) ? $pedido['items'] : [];
        if (empty($itemsIn)) return ['_error' => 'Sem itens'];
        if (count($itemsIn) > 20) return ['_error' => 'Mais de 20 itens'];

        $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        $brlToUsdRate = 1.0;
        if ($moedaPedido === 'BRL') {
            $usdRate = $this->getUsdToBrlRate();
            $brlToUsdRate = ($usdRate > 0.000001) ? (1.0 / $usdRate) : (1.0 / 5.85);
        }

        $items = [];
        foreach ($itemsIn as $idx => $it) {
            if (!is_array($it)) continue;
            $qtd = (int) ($it['quantidade'] ?? 0);
            if ($qtd <= 0) return ['_error' => 'Item #' . ($idx+1) . ' qtd inválida'];
            $desc = trim((string) ($it['nome_produto'] ?? ($it['nome'] ?? 'Item')));
            if ($desc === '') $desc = 'Item ' . ($idx+1);
            $ncmDigits = $this->onlyDigits((string) ($it['ncm'] ?? ''));
            if ($ncmDigits === '' || strlen($ncmDigits) < 6) return ['_error' => 'Item #' . ($idx+1) . ' sem NCM'];
            $hs = strlen($ncmDigits) >= 8 ? substr($ncmDigits, 0, 8) : substr($ncmDigits, 0, 6);
            $val = (float) ($it['preco_unitario'] ?? 0);
            if ($moedaPedido === 'BRL' && $val > 0) $val = $val * $brlToUsdRate;
            if ($val < 0.01) $val = 0.01;
            $items[] = ['hsCode' => $hs, 'description' => substr($desc, 0, 500), 'quantity' => $qtd, 'value' => (float) number_format($val, 2, '.', '')];
        }
        if (empty($items)) return ['_error' => 'Sem itens válidos'];

        $freightPaidValue = (float) ($overrides['freightPaidValue'] ?? 0.01);
        if ($freightPaidValue < 0.01) $freightPaidValue = 0.01;

        $pid = (int) ($pedido['id'] ?? 0);
        $customerControlCode = (string) ($pedido['codigo_pedido'] ?? ('PED-' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT)));
        if (trim($customerControlCode) === '') $customerControlCode = 'PED-' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT);

        $payload = array_merge($destinatario, [
            'customerControlCode' => substr($customerControlCode, 0, 100),
            'totalWeight' => $totalWeight,
            'packagingLength' => (float) number_format($packagingLength, 2, '.', ''),
            'packagingWidth' => (float) number_format($packagingWidth, 2, '.', ''),
            'packagingHeight' => (float) number_format($packagingHeight, 2, '.', ''),
            'distributionModality' => (int) ($overrides['distributionModality'] ?? 33162),
            'taxPaymentMethod' => $overrides['taxPaymentMethod'] ?? 'DDU',
            'currency' => 'USD',
            'nonNationalizationInstruction' => 'RETURNTOORIGIN',
            'freightPaidValue' => (float) number_format($freightPaidValue, 2, '.', ''),
            'items' => $items,
        ]);

        if (!empty($overrides['insurancePaidValue'])) {
            $payload['insurancePaidValue'] = (float) number_format((float) $overrides['insurancePaidValue'], 2, '.', '');
        }

        return $payload;
    }

    private function salvarEtiquetaLocal(int $pedidoId, string $controlCode, string $tracking, array $resp): void
    {
        try {
            // Garantir que tabela existe
            if (!$this->tableExists('correios_packet_etiquetas')) {
                $sql = "CREATE TABLE IF NOT EXISTS correios_packet_etiquetas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    pedido_id INT NOT NULL,
                    customer_control_code VARCHAR(120) NULL,
                    tracking_number VARCHAR(120) NULL,
                    status VARCHAR(30) DEFAULT 'gerada',
                    wp_post_id INT NULL,
                    last_request_json LONGTEXT NULL,
                    last_response_json LONGTEXT NULL,
                    last_http_code INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY uniq_pedido (pedido_id),
                    KEY idx_tracking (tracking_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $this->connection->exec($sql);
            }

            // Garantir coluna wp_post_id existe
            try {
                $cols = [];
                $st = $this->connection->query('DESCRIBE correios_packet_etiquetas');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (!in_array('wp_post_id', $cols, true)) {
                    $this->connection->exec('ALTER TABLE correios_packet_etiquetas ADD COLUMN wp_post_id INT NULL DEFAULT NULL');
                }
            } catch (\Exception $e) {}

            $stIns = $this->connection->prepare('INSERT INTO correios_packet_etiquetas (pedido_id, customer_control_code, tracking_number, status, wp_post_id, last_response_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE tracking_number = VALUES(tracking_number), status = VALUES(status), wp_post_id = VALUES(wp_post_id), last_response_json = VALUES(last_response_json), updated_at = NOW()');
            $stIns->execute([
                $pedidoId,
                $controlCode,
                $tracking,
                'gerada',
                $resp['wp_post_id'] ?? null,
                json_encode($resp),
            ]);
        } catch (\Exception $e) {
            error_log('[ETIQUETAS_WP] Erro ao salvar etiqueta local: ' . $e->getMessage());
        }
    }
}
