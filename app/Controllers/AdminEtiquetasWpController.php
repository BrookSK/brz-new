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
    private ?string $ultimoErroSalvarEtiqueta = null;
    private ?string $ultimoSqlSalvarEtiqueta = null;
    private ?int $ultimoRowCountSalvar = null;
    private ?string $ultimoLastInsertId = null;

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

    /**
     * Consolidar itens por NCM quando há mais de $maxItens.
     * Agrupa itens com mesmo NCM somando quantidades e valores.
     * Mantém a descrição do item de maior valor como representante do grupo.
     * Se após consolidação ainda exceder o limite, agrupa os menores num genérico.
     */
    private function consolidarItensPorNcm(array $itemsIn, int $maxItens = 20): array
    {
        // Agrupar por NCM
        $grupos = [];
        foreach ($itemsIn as $item) {
            $ncm = trim((string) ($item['ncm'] ?? ''));
            if ($ncm === '') $ncm = '_sem_ncm';
            if (!isset($grupos[$ncm])) {
                $grupos[$ncm] = [];
            }
            $grupos[$ncm][] = $item;
        }

        // Se o número de NCMs distintos já cabe no limite, consolidar cada grupo num único item
        $resultado = [];
        foreach ($grupos as $ncm => $itens) {
            if (count($itens) === 1) {
                $resultado[] = $itens[0];
                continue;
            }

            // Consolidar: somar quantidades e valor total, usar descrição do item mais caro
            $totalQtd = 0;
            $totalValor = 0.0;
            $maiorValor = 0.0;
            $descMaior = '';
            $pesoTotal = 0.0;
            $temBateria = 'N';
            $temPerfume = 'N';
            $valorJaUsd = false;

            foreach ($itens as $it) {
                $qtd = (int) ($it['quantidade'] ?? 1);
                $val = (float) ($it['preco_unitario'] ?? ($it['declaration_value'] ?? 0));
                $valTotal = $val * $qtd;
                $totalQtd += $qtd;
                $totalValor += $valTotal;
                $pesoTotal += (float) ($it['peso_kg'] ?? 0) * $qtd;
                if ($valTotal > $maiorValor) {
                    $maiorValor = $valTotal;
                    $descMaior = trim((string) ($it['nome_produto'] ?? ($it['nome'] ?? 'Item')));
                }
                if (($it['tem_bateria'] ?? 'N') === 'S') $temBateria = 'S';
                if (($it['tem_perfume'] ?? 'N') === 'S') $temPerfume = 'S';
                if (!empty($it['_valor_ja_usd'])) $valorJaUsd = true;
            }

            // Valor médio por unidade (para a declaração aduaneira)
            $valorUnit = $totalQtd > 0 ? round($totalValor / $totalQtd, 2) : 0;

            $resultado[] = [
                'nome_produto' => $descMaior . (count($itens) > 1 ? ' (+' . (count($itens) - 1) . ')' : ''),
                'nome' => $descMaior,
                'ncm' => $ncm === '_sem_ncm' ? '' : $ncm,
                'preco_unitario' => $valorUnit,
                'declaration_value' => $valorUnit,
                'quantidade' => $totalQtd,
                'peso_kg' => $pesoTotal > 0 ? round($pesoTotal / $totalQtd, 3) : 0,
                'tem_bateria' => $temBateria,
                'tem_perfume' => $temPerfume,
                '_valor_ja_usd' => $valorJaUsd,
            ];
        }

        // Se ainda excede o limite após consolidação por NCM, agrupar os de menor valor
        if (count($resultado) > $maxItens) {
            // Ordenar por valor total (desc) — manter os maiores individuais
            usort($resultado, function($a, $b) {
                $va = (float) ($a['preco_unitario'] ?? 0) * (int) ($a['quantidade'] ?? 1);
                $vb = (float) ($b['preco_unitario'] ?? 0) * (int) ($b['quantidade'] ?? 1);
                return $vb <=> $va;
            });

            // Manter os top ($maxItens - 1) e agrupar o resto num item genérico
            $top = array_slice($resultado, 0, $maxItens - 1);
            $rest = array_slice($resultado, $maxItens - 1);

            $restQtd = 0;
            $restValor = 0.0;
            $restNcm = '';
            foreach ($rest as $r) {
                $q = (int) ($r['quantidade'] ?? 1);
                $v = (float) ($r['preco_unitario'] ?? 0);
                $restQtd += $q;
                $restValor += $v * $q;
                if ($restNcm === '' && !empty($r['ncm'])) $restNcm = $r['ncm'];
            }

            $top[] = [
                'nome_produto' => 'Outros itens (' . count($rest) . ' grupos)',
                'nome' => 'Outros itens',
                'ncm' => $restNcm,
                'preco_unitario' => $restQtd > 0 ? round($restValor / $restQtd, 2) : 0,
                'declaration_value' => $restQtd > 0 ? round($restValor / $restQtd, 2) : 0,
                'quantidade' => $restQtd,
                'peso_kg' => 0,
                'tem_bateria' => 'N',
                'tem_perfume' => 'N',
                '_valor_ja_usd' => true,
            ];

            $resultado = $top;
        }

        return $resultado;
    }

    private function getUsdToBrlRate(): float
    {
        try {
            return \App\Core\ExchangeRate::getUsdToBrl();
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
            'message' => $allOk ? __('admin.labels_wp.all_tests_passed', 'Todos os testes passaram!') : __('admin.labels_wp.some_tests_failed', 'Alguns testes falharam.'),
            'ambiente' => $ambiente ?? 'DESCONHECIDO',
            'results' => $results,
        ]);
    }

    // =========================================================
    // SALDO FINANCEIRO (CORREIOS)
    // =========================================================

    /**
     * Consultar saldo financeiro direto da API dos Correios (mesmo do painel antigo).
     * GET /admin/etiquetas-wp/saldo
     */
    public function saldo(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $svc = new \App\Services\CorreiosPacketService();
        $r = $svc->getBalance();

        if (empty($r['success'])) {
            $this->json([
                'success' => false,
                'error' => (string) ($r['error'] ?? __('admin.labels_wp.balance_query_failed', 'Falha ao consultar saldo.')),
            ], 400);
            return;
        }

        $this->json([
            'success' => true,
            'currentBalance' => $r['currentBalance'] ?? null,
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
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_order_id', 'pedido_id inválido')], 400);
            return;
        }

        $pedidoModel = new PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($pedidoId);
        if (!is_array($pedido) || empty($pedido['id'])) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.order_not_found', 'Pedido não encontrado')], 404);
            return;
        }

        $status = strtolower(trim((string) ($pedido['status'] ?? '')));
        if (!in_array($status, ['produto_consolidado', 'consolidado'], true)) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.order_not_closed_box', 'Pedido não está em Caixa Fechada (status: {status})', ['status' => $status])], 400);
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

            // Salvar no banco local também (retorna false se falhar ao persistir).
            $salvouLocal = $this->salvarEtiquetaLocal($pedidoId, $packageData['customerControlCode'], $tracking, $resp);

            // Fallback: se não gravou local, buscar do WP e regravar (garante rastreio na conta/admin).
            if ($this->pedidoSemTrackingLocal($pedidoId)) {
                $rSync = $this->sincronizarPedidoDoWp($pedidoId);
                if (!empty($rSync['tracking_number'])) {
                    $tracking = $rSync['tracking_number'];
                    $salvouLocal = true;
                }
            }

            // Atualizar status do pedido
            try {
                $pedidoModel->atualizarStatus($pedidoId, 'etiqueta_gerada', __('admin.labels_wp.status_label_via_wp', 'Etiqueta via WordPress - Rastreio: ') . $tracking, $_SESSION['usuario_id'] ?? null);
            } catch (\Exception $e) {}

            // Notificar o cliente automaticamente (e-mail + WhatsApp) com o rastreio. Best-effort.
            $notif = ['email_enviado' => false, 'whatsapp_enviado' => false];
            try {
                $notif = (new \App\Services\NotificationService())->notificarEventoPedido('correios_packet_label_created', $pedidoId, [
                    'tracking_number' => $tracking,
                ]);
            } catch (\Throwable $e) {
                error_log('[ETIQUETAS_WP][NOTIF] Falha ao notificar pedido #' . $pedidoId . ': ' . $e->getMessage());
            }

            $this->json([
                'success' => true,
                'pedido_id' => $pedidoId,
                'tracking_number' => $tracking,
                'wp_post_id' => $resp['wp_post_id'] ?? null,
                'salvo_local' => $salvouLocal,
                'notificado_email' => !empty($notif['email_enviado']),
                'notificado_whatsapp' => !empty($notif['whatsapp_enviado']),
                'aviso_local' => $salvouLocal ? null : __('admin.labels_wp.label_not_saved_local', 'Atenção: etiqueta gerada no Correios, mas não foi possível salvar o rastreio no banco local. Verifique a tabela correios_packet_etiquetas.'),
            ]);
        } else {
            $this->json([
                'success' => false,
                'error' => $resp['error'] ?? __('admin.labels_wp.wp_unknown_error', 'Erro desconhecido do WordPress'),
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
            echo json_encode(['success' => false, 'error' => __('admin.labels_wp.no_order_selected', 'Nenhum pedido selecionado')]);
            exit;
        }

        $ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => __('admin.labels_wp.invalid_ids', 'IDs inválidos')]);
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
                        $result['error'] = __('admin.labels_wp.already_has_label', 'Já possui etiqueta');
                        $results[] = $result;
                        continue;
                    }
                }

                $pedido = $pedidoModel->getComDetalhes($pid);
                if (!is_array($pedido) || empty($pedido['id'])) {
                    $result['error'] = __('admin.labels_wp.order_not_found', 'Pedido não encontrado');
                    $results[] = $result;
                    continue;
                }

                $status = strtolower(trim((string) ($pedido['status'] ?? '')));
                if (!in_array($status, ['produto_consolidado', 'consolidado'], true)) {
                    $result['error'] = __('admin.labels_wp.not_closed_box', 'Não está em Caixa Fechada');
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

                    // Garantir que o rastreio ficou salvo localmente. Se por algum motivo não
                    // gravou (tracking vazio na resposta, etc.), buscar do WP e regravar — assim
                    // o rastreio SEMPRE aparece na conta/admin sem depender de sincronização manual.
                    if ($this->pedidoSemTrackingLocal($pid)) {
                        $rSync = $this->sincronizarPedidoDoWp($pid);
                        if (!empty($rSync['tracking_number'])) {
                            $tracking = $rSync['tracking_number'];
                        }
                    }

                    try { $pedidoModel->atualizarStatus($pid, 'etiqueta_gerada', __('admin.labels_wp.status_label_via_wp_bulk', 'Etiqueta via WP em massa - Rastreio: ') . $tracking, $_SESSION['usuario_id'] ?? null); } catch (\Exception $e) {}

                    // Notificar o cliente automaticamente (e-mail + WhatsApp). Best-effort.
                    try {
                        (new \App\Services\NotificationService())->notificarEventoPedido('correios_packet_label_created', $pid, ['tracking_number' => $tracking]);
                    } catch (\Throwable $e) {
                        error_log('[ETIQUETAS_WP][NOTIF] Falha ao notificar pedido #' . $pid . ': ' . $e->getMessage());
                    }

                    $result['success'] = true;
                    $result['tracking_number'] = $tracking;
                } else {
                    $result['error'] = $resp['error'] ?? __('admin.labels_wp.wp_error', 'Erro WordPress');
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
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_dispatch_number', 'dispatchNumber inválido')], 400);
            return;
        }
        if (empty($data['trackingCodes'])) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.select_one_package', 'Selecione pelo menos 1 pacote')], 400);
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
            $this->json(['success' => false, 'error' => __('admin.labels_wp.select_one_container', 'Selecione pelo menos 1 container')], 400);
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
                $this->json(['success' => false, 'error' => __('admin.labels_wp.required_field', 'Campo obrigatório: {field}', ['field' => $field])], 400);
                return;
            }
        }

        $resp = $this->wp->createDeparture($body);

        // Após confirmar o embarque com sucesso, notificar os clientes dos pedidos embarcados
        // (e-mail + WhatsApp via NotificationService) com o código de rastreio. Best-effort:
        // qualquer falha aqui NÃO deve derrubar a resposta de sucesso do embarque.
        if (!empty($resp['success'])) {
            try {
                $billIds = array_values(array_filter(array_map('intval', (array) ($body['billIds'] ?? [])), fn($v) => $v > 0));
                $pares = $this->resolverPedidosTrackingDoEmbarque($billIds);
                $embarqueInfo = [
                    'flight_number' => (string) ($body['flightNumber'] ?? ''),
                    'airline_code' => (string) ($body['airlineCode'] ?? ''),
                    'departure_date' => (string) ($body['departureDate'] ?? ''),
                    'departure_airport' => (string) ($body['departureAirportCode'] ?? ''),
                    'arrival_date' => (string) ($body['arrivalDate'] ?? ''),
                    'arrival_airport' => (string) ($body['arrivalAirportCode'] ?? ''),
                ];
                $resumoNotif = $this->notificarPedidosEmbarque($pares, $embarqueInfo);
                $resp['notificacoes'] = $resumoNotif;
            } catch (\Throwable $e) {
                error_log('[EMBARQUE][NOTIF] Falha ao notificar clientes do embarque: ' . $e->getMessage());
                $resp['notificacoes'] = ['enviadas' => 0, 'erro' => $e->getMessage()];
            }
        }

        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    /**
     * Resolve os pares (pedido_id, tracking_number) de um embarque a partir das faturas (billIds).
     *
     * Como a hierarquia fatura->container->pacote vive no WordPress e o vínculo local é por
     * tracking_number, a estratégia é: (1) obter as faturas do WP e seus containers;
     * (2) obter os containers do WP e seus tracking codes; (3) cruzar os trackings com a
     * tabela local correios_packet_etiquetas para descobrir o pedido_id de cada rastreio.
     *
     * É tolerante a variações de nomes de campos no retorno do WP e nunca lança exceção.
     *
     * @return array<int, array{pedido_id:int, tracking_number:string}>
     */
    private function resolverPedidosTrackingDoEmbarque(array $billIds): array
    {
        $billIds = array_values(array_filter(array_map('intval', $billIds), fn($v) => $v > 0));
        if (empty($billIds)) {
            return [];
        }

        // 1) Coletar todos os tracking codes das faturas selecionadas (via WP).
        $trackings = $this->coletarTrackingsDasFaturas($billIds);
        if (empty($trackings)) {
            error_log('[EMBARQUE][NOTIF] Nenhum tracking resolvido para billIds=' . json_encode($billIds));
            return [];
        }

        // 2) Cruzar trackings com a tabela local para obter pedido_id.
        $pares = [];
        if ($this->tableExists('correios_packet_etiquetas')) {
            try {
                $in = implode(',', array_fill(0, count($trackings), '?'));
                $st = $this->connection->prepare("SELECT pedido_id, tracking_number FROM correios_packet_etiquetas WHERE tracking_number IN ({$in})");
                $st->execute(array_values($trackings));
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $pid = (int) ($row['pedido_id'] ?? 0);
                    $trk = trim((string) ($row['tracking_number'] ?? ''));
                    if ($pid > 0 && $trk !== '') {
                        $pares[$pid] = ['pedido_id' => $pid, 'tracking_number' => $trk];
                    }
                }
            } catch (\Exception $e) {
                error_log('[EMBARQUE][NOTIF] Erro ao cruzar trackings com etiquetas: ' . $e->getMessage());
            }
        }

        return array_values($pares);
    }

    /**
     * Coleta os tracking codes de um conjunto de faturas do WordPress, descendo por containers.
     * Tolerante a diferentes formatos de retorno do WP.
     *
     * @return string[] lista única de tracking codes
     */
    private function coletarTrackingsDasFaturas(array $billIds): array
    {
        $billIdsSet = array_flip(array_map('intval', $billIds));
        $containerIds = [];      // wp_post_id de containers (quando a fatura referenciar assim)
        $dispatchNumbers = [];   // números de remessa (formato observado no retorno do WP: fatura.dispatch_numbers)
        $trackings = [];

        // Buscar as faturas do WP e extrair a ligação com containers de cada fatura selecionada.
        try {
            $respBills = $this->wp->listBills(['per_page' => 500]);
            $bills = $this->extrairLista($respBills);
            foreach ($bills as $bill) {
                $bwid = (int) ($bill['wp_post_id'] ?? ($bill['id'] ?? 0));
                if ($bwid <= 0 || !isset($billIdsSet[$bwid])) {
                    continue;
                }
                // Formato principal observado: fatura.dispatch_numbers (liga por dispatch_number do container).
                if (isset($bill['dispatch_numbers']) && is_array($bill['dispatch_numbers'])) {
                    foreach ($bill['dispatch_numbers'] as $dn) {
                        $dn = (string) $dn;
                        if ($dn !== '') $dispatchNumbers[$dn] = $dn;
                    }
                }
                // Fallbacks: alguns formatos referenciam containers por wp_post_id.
                foreach ($this->extrairIds($bill, ['containerIds', 'container_ids', 'containers']) as $cid) {
                    $containerIds[$cid] = $cid;
                }
                // Alguns retornos já trazem os trackings direto na fatura.
                foreach ($this->extrairTrackings($bill) as $t) {
                    $trackings[$t] = $t;
                }
            }
        } catch (\Throwable $e) {
            error_log('[EMBARQUE][NOTIF] Erro ao listar faturas do WP: ' . $e->getMessage());
        }

        // Buscar os containers do WP e coletar tracking codes dos containers vinculados
        // (por dispatch_number — ligação principal — ou por wp_post_id — fallback).
        if (!empty($dispatchNumbers) || !empty($containerIds)) {
            try {
                $respContainers = $this->wp->listContainers(['per_page' => 500]);
                $containers = $this->extrairLista($respContainers);
                foreach ($containers as $container) {
                    $cwid = (int) ($container['wp_post_id'] ?? ($container['id'] ?? 0));
                    $dn = (string) ($container['dispatch_number'] ?? '');
                    $vinculado = ($dn !== '' && isset($dispatchNumbers[$dn])) || ($cwid > 0 && isset($containerIds[$cwid]));
                    if (!$vinculado) {
                        continue;
                    }
                    foreach ($this->extrairTrackings($container) as $t) {
                        $trackings[$t] = $t;
                    }
                }
            } catch (\Throwable $e) {
                error_log('[EMBARQUE][NOTIF] Erro ao listar containers do WP: ' . $e->getMessage());
            }
        }

        return array_values($trackings);
    }

    /**
     * Extrai a lista de itens de uma resposta do WP (chave 'data' comum, ou o próprio array).
     */
    private function extrairLista($resp): array
    {
        if (!is_array($resp)) {
            return [];
        }
        if (isset($resp['data']) && is_array($resp['data'])) {
            return $resp['data'];
        }
        // Alguns endpoints podem retornar a lista na raiz.
        $isList = array_keys($resp) === range(0, count($resp) - 1);
        return $isList ? $resp : [];
    }

    /**
     * Extrai IDs (inteiros) de um item, tentando várias chaves candidatas.
     * Suporta tanto array de inteiros quanto array de objetos com id/wp_post_id.
     *
     * @return int[]
     */
    private function extrairIds(array $item, array $candidateKeys): array
    {
        $out = [];
        foreach ($candidateKeys as $key) {
            if (!isset($item[$key]) || !is_array($item[$key])) {
                continue;
            }
            foreach ($item[$key] as $v) {
                if (is_array($v)) {
                    $id = (int) ($v['wp_post_id'] ?? ($v['id'] ?? 0));
                } else {
                    $id = (int) $v;
                }
                if ($id > 0) {
                    $out[$id] = $id;
                }
            }
        }
        return array_values($out);
    }

    /**
     * Extrai tracking codes de um item, tolerando várias chaves e formatos (array ou JSON string).
     *
     * @return string[]
     */
    private function extrairTrackings(array $item): array
    {
        $out = [];
        $candidateKeys = ['trackingCodes', 'tracking_codes', 'trackingNumbers', 'tracking_numbers', 'tracking_numbers_json', 'trackings', 'tracking_code'];
        foreach ($candidateKeys as $key) {
            if (!isset($item[$key])) {
                continue;
            }
            $val = $item[$key];
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    $val = $decoded;
                } else {
                    $val = [$val];
                }
            }
            if (!is_array($val)) {
                continue;
            }
            foreach ($val as $t) {
                if (is_array($t)) {
                    $t = (string) ($t['tracking_code'] ?? ($t['tracking_number'] ?? ($t['code'] ?? '')));
                }
                $t = trim((string) $t);
                if ($t !== '') {
                    $out[$t] = $t;
                }
            }
        }
        return array_values($out);
    }

    /**
     * Dispara as notificações (e-mail + WhatsApp) para cada pedido embarcado, via NotificationService.
     * Evento: correios_packet_shipment_departed. Retorna um resumo do que foi processado.
     *
     * @param array<int, array{pedido_id:int, tracking_number:string}> $pares
     * @param array<string,string> $embarqueInfo
     */
    private function notificarPedidosEmbarque(array $pares, array $embarqueInfo): array
    {
        $enviadas = 0;
        $falhas = 0;
        $pedidos = [];

        if (empty($pares)) {
            return ['enviadas' => 0, 'falhas' => 0, 'pedidos' => [], 'aviso' => 'Nenhum pedido resolvido para o embarque'];
        }

        $notif = new \App\Services\NotificationService();
        foreach ($pares as $par) {
            $pedidoId = (int) ($par['pedido_id'] ?? 0);
            $tracking = trim((string) ($par['tracking_number'] ?? ''));
            if ($pedidoId <= 0) {
                continue;
            }
            try {
                $notif->notificarEventoPedido('correios_packet_shipment_departed', $pedidoId, array_merge($embarqueInfo, [
                    'tracking_number' => $tracking,
                ]));
                $enviadas++;
                $pedidos[] = $pedidoId;
            } catch (\Throwable $e) {
                $falhas++;
                error_log('[EMBARQUE][NOTIF] Falha ao notificar pedido #' . $pedidoId . ': ' . $e->getMessage());
            }
        }

        return ['enviadas' => $enviadas, 'falhas' => $falhas, 'pedidos' => $pedidos];
    }

    /**
     * Reparo TEMPORÁRIO da tabela correios_packet_etiquetas, que ficou corrompida:
     * - Linha com id=0 (AUTO_INCREMENT quebrado) fazia todo INSERT virar UPDATE dessa linha.
     * - Índice de pedido_id não era UNIQUE (permitia duplicatas e quebrava o ON DUPLICATE KEY).
     * GET /admin/etiquetas-wp/reparar-tabela-etiquetas
     * Remover após uso.
     */
    public function repararTabelaEtiquetas(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        header('Content-Type: application/json; charset=utf-8');

        $passos = [];
        try {
            // 1) Remover a linha corrompida id=0 (guardando o que era, para log).
            try {
                $st = $this->connection->query('SELECT id, pedido_id, tracking_number, wp_post_id FROM correios_packet_etiquetas WHERE id = 0');
                $linhaZero = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
                $passos['linha_id_zero_antes'] = $linhaZero;
                if (!empty($linhaZero)) {
                    $this->connection->exec('DELETE FROM correios_packet_etiquetas WHERE id = 0');
                    $passos['linha_id_zero_removida'] = true;
                }
            } catch (\Throwable $e) {
                $passos['erro_remover_id_zero'] = $e->getMessage();
            }

            // 2) Remover duplicatas de pedido_id (mantém a de maior id/mais recente).
            try {
                $this->connection->exec(
                    'DELETE t1 FROM correios_packet_etiquetas t1
                     INNER JOIN correios_packet_etiquetas t2
                       ON t1.pedido_id = t2.pedido_id AND t1.id < t2.id'
                );
                $passos['duplicatas_removidas'] = true;
            } catch (\Throwable $e) {
                $passos['erro_remover_duplicatas'] = $e->getMessage();
            }

            // 3) Tornar pedido_id realmente UNIQUE (dropar índice não-único e recriar UNIQUE).
            try {
                // Descobrir o nome do índice atual de pedido_id.
                $idx = $this->connection->query('SHOW INDEX FROM correios_packet_etiquetas')->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($idx as $i) {
                    if ($i['Column_name'] === 'pedido_id' && strtoupper((string) $i['Key_name']) !== 'PRIMARY') {
                        try { $this->connection->exec('ALTER TABLE correios_packet_etiquetas DROP INDEX `' . $i['Key_name'] . '`'); } catch (\Throwable $e) {}
                    }
                }
                $this->connection->exec('ALTER TABLE correios_packet_etiquetas ADD UNIQUE KEY uniq_pedido_id (pedido_id)');
                $passos['unique_pedido_id_criado'] = true;
            } catch (\Throwable $e) {
                $passos['erro_unique_pedido_id'] = $e->getMessage();
            }

            // 4) CAUSA RAIZ: a coluna id perdeu o AUTO_INCREMENT (todo INSERT gravava id=0 e
            //    colidia na PRIMARY KEY, virando UPDATE). Primeiro corrigir qualquer linha id=0
            //    existente para um id válido, depois redefinir a coluna como AUTO_INCREMENT.
            try {
                // Se ainda houver linha id=0, dar a ela um id novo acima do máximo.
                $temZero = (int) $this->connection->query('SELECT COUNT(*) FROM correios_packet_etiquetas WHERE id = 0')->fetchColumn();
                if ($temZero > 0) {
                    $novoId = ((int) $this->connection->query('SELECT COALESCE(MAX(id),0) FROM correios_packet_etiquetas')->fetchColumn()) + 1;
                    $stUp = $this->connection->prepare('UPDATE correios_packet_etiquetas SET id = ? WHERE id = 0 LIMIT 1');
                    $stUp->execute([$novoId]);
                    $passos['linha_id_zero_reindexada_para'] = $novoId;
                }
                // Redefinir a coluna id como AUTO_INCREMENT (recupera a propriedade perdida).
                $this->connection->exec('ALTER TABLE correios_packet_etiquetas MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT');
                $passos['coluna_id_auto_increment_restaurada'] = true;

                $maxId = (int) $this->connection->query('SELECT COALESCE(MAX(id),0) FROM correios_packet_etiquetas')->fetchColumn();
                $this->connection->exec('ALTER TABLE correios_packet_etiquetas AUTO_INCREMENT = ' . ($maxId + 1));
                $passos['auto_increment_ajustado_para'] = $maxId + 1;
            } catch (\Throwable $e) {
                $passos['erro_auto_increment'] = $e->getMessage();
            }

            $passos['total_linhas_apos'] = (int) $this->connection->query('SELECT COUNT(*) FROM correios_packet_etiquetas')->fetchColumn();
            $this->json(['success' => true, 'passos' => $passos]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'passos' => $passos], 500);
        }
    }

    /**
     * Diagnóstico temporário: mostra o que está gravado para um pedido em relação ao rastreio.
     * GET /admin/etiquetas-wp/diagnostico-rastreio?pedido_id=758
     * Remover após depuração.
     */

    /**
     * Teste ISOLADO de e-mail: chama EmailService::send() direto, sem dedupe e sem passar pelo
     * fluxo de eventos. Serve para saber se o EmailService/SMTP realmente entrega.
     * GET /admin/etiquetas-wp/testar-email-direto?to=alguem@exemplo.com
     * Remover após depuração.
     */
    public function testarEmailDireto(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $to = trim((string) $request->getParam('to', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Informe ?to=email@valido']);
            return;
        }

        $assunto = 'Teste direto EmailService - ' . date('H:i:s');
        $html = '<p>Teste direto do EmailService em ' . date('d/m/Y H:i:s') . '. Se você recebeu isto, o EmailService/SMTP entrega normalmente.</p>';

        try {
            // dedupeKey vazio = sem deduplicação (sempre tenta enviar).
            (new \App\Services\EmailService())->send($to, $assunto, $html, '', ['evento' => 'teste_direto']);
            $this->json(['success' => true, 'enviado_para' => $to, 'obs' => 'send() nao lancou excecao (SMTP aceitou). Verifique a caixa.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'erro' => $e->getMessage()]);
        }
    }

    public function diagnosticoRastreio(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=utf-8');

        $pedidoId = (int) $request->getParam('pedido_id', 0);
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => 'Informe ?pedido_id=']);
            return;
        }

        $out = ['pedido_id' => $pedidoId];

        // Coluna tracking_code DIRETO na tabela pedidos (tem PRIORIDADE no getComDetalhes).
        try {
            $colsP = [];
            try { $stcp = $this->connection->query('DESCRIBE pedidos'); $colsP = $stcp ? $stcp->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Throwable $e) {}
            $trackCols = array_values(array_filter(['tracking_code','codigo_rastreio','rastreamento','tracking','tracking_source'], fn($c) => in_array($c, $colsP, true)));
            if (!empty($trackCols)) {
                $sel = implode(', ', $trackCols);
                $stP = $this->connection->prepare("SELECT {$sel} FROM pedidos WHERE id = ? LIMIT 1");
                $stP->execute([$pedidoId]);
                $out['pedidos_colunas_tracking'] = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];
            } else {
                $out['pedidos_colunas_tracking'] = 'nenhuma coluna de tracking na tabela pedidos';
            }
        } catch (\Throwable $e) {
            $out['pedidos_colunas_tracking_erro'] = $e->getMessage();
        }

        // Linha em correios_packet_etiquetas
        try {
            $st = $this->connection->prepare('SELECT id, pedido_id, customer_control_code, tracking_number, status, wp_post_id, created_at FROM correios_packet_etiquetas WHERE pedido_id = ? ORDER BY id DESC');
            $st->execute([$pedidoId]);
            $out['correios_packet_etiquetas'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $out['correios_packet_etiquetas_erro'] = $e->getMessage();
        }

        // shippo_etiquetas
        try {
            if ($this->tableExists('shippo_etiquetas')) {
                $st = $this->connection->prepare('SELECT id, pedido_id, tracking_number, status FROM shippo_etiquetas WHERE pedido_id = ? ORDER BY id DESC');
                $st->execute([$pedidoId]);
                $out['shippo_etiquetas'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable $e) {
            $out['shippo_etiquetas_erro'] = $e->getMessage();
        }

        // O que o getComDetalhes resolve como tracking
        $codigoPedido = '';
        try {
            $pm = new PedidoEcommerce();
            $ped = $pm->getComDetalhes($pedidoId);
            $codigoPedido = (string) ($ped['codigo_pedido'] ?? ($ped['numero_pedido'] ?? ''));
            $out['getComDetalhes_tracking'] = [
                'tracking_code' => $ped['tracking_code'] ?? null,
                'tracking_source' => $ped['tracking_source'] ?? null,
                'tracking_label_url' => $ped['tracking_label_url'] ?? null,
                'status' => $ped['status'] ?? null,
                'codigo_pedido' => $ped['codigo_pedido'] ?? null,
                'numero_pedido' => $ped['numero_pedido'] ?? null,
                'cliente_email' => $ped['cliente_email'] ?? ($ped['email'] ?? null),
                'cliente_telefone' => $ped['cliente_telefone'] ?? ($ped['telefone'] ?? null),
            ];
        } catch (\Throwable $e) {
            $out['getComDetalhes_erro'] = $e->getMessage();
        }

        // O que o WordPress retorna ao buscar por este pedido (revela order_id / pedido_id_local / tracking).
        $termos = array_values(array_filter([
            $codigoPedido,
            'PED-' . str_pad((string) $pedidoId, 6, '0', STR_PAD_LEFT),
            (string) $pedidoId,
        ], fn($v) => trim((string) $v) !== ''));
        $out['wp_termos_busca'] = $termos;
        $out['wp_pacotes'] = [];
        foreach ($termos as $termo) {
            try {
                $resp = $this->wp->listPackages(['search' => $termo, 'per_page' => 20]);
                $lista = (is_array($resp) && isset($resp['data']) && is_array($resp['data'])) ? $resp['data'] : [];
                foreach ($lista as $pkg) {
                    $out['wp_pacotes'][] = [
                        'termo' => $termo,
                        'wp_post_id' => $pkg['wp_post_id'] ?? null,
                        'order_id' => $pkg['order_id'] ?? null,
                        'pedido_id_local' => $pkg['pedido_id_local'] ?? null,
                        'tracking_code' => $pkg['tracking_code'] ?? null,
                        'recipient_name' => $pkg['recipient_name'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                $out['wp_pacotes_erro'][] = $termo . ': ' . $e->getMessage();
            }
        }

        // Filtro EXATO por pedido_id_local (só funciona se o snippet do WP já foi atualizado).
        try {
            $respExato = $this->wp->listPackagesByPedidoLocal($pedidoId);
            $listaExato = (is_array($respExato) && isset($respExato['data']) && is_array($respExato['data'])) ? $respExato['data'] : [];
            $out['wp_filtro_exato_pedido_id_local'] = array_map(function ($pkg) {
                return [
                    'wp_post_id' => $pkg['wp_post_id'] ?? null,
                    'order_id' => $pkg['order_id'] ?? null,
                    'pedido_id_local' => $pkg['pedido_id_local'] ?? null,
                    'tracking_code' => $pkg['tracking_code'] ?? null,
                ];
            }, $listaExato);
        } catch (\Throwable $e) {
            $out['wp_filtro_exato_erro'] = $e->getMessage();
        }

        // TESTE DE GRAVAÇÃO: tentar sincronizar do WP e reportar se salvou + erro exato do INSERT.
        try {
            $rSync = $this->sincronizarPedidoDoWp($pedidoId);
            $out['sincronizar_resultado'] = $rSync;
            $out['salvar_erro'] = $this->ultimoErroSalvarEtiqueta;
            $out['salvar_sql'] = $this->ultimoSqlSalvarEtiqueta;
            $out['salvar_rowcount'] = $this->ultimoRowCountSalvar;
            $out['salvar_last_insert_id'] = $this->ultimoLastInsertId;

            // Diagnóstico de infra: banco atual, se a tabela é VIEW e se há triggers.
            try {
                $out['db_atual'] = (string) $this->connection->query('SELECT DATABASE()')->fetchColumn();
            } catch (\Throwable $e) {}
            try {
                $stT = $this->connection->query("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'correios_packet_etiquetas'");
                $out['tabela_tipo'] = $stT ? (string) $stT->fetchColumn() : null;
            } catch (\Throwable $e) {}
            try {
                $stTr = $this->connection->query("SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'correios_packet_etiquetas'");
                $out['tabela_triggers'] = $stTr ? ($stTr->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            } catch (\Throwable $e) {}
            try {
                $out['total_linhas_tabela'] = (int) $this->connection->query('SELECT COUNT(*) FROM correios_packet_etiquetas')->fetchColumn();
            } catch (\Throwable $e) {}
            // Índices (revela UNIQUE que causa colisão no ON DUPLICATE KEY UPDATE).
            try {
                $stIdx = $this->connection->query('SHOW INDEX FROM correios_packet_etiquetas');
                $out['tabela_indices'] = $stIdx ? ($stIdx->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            } catch (\Throwable $e) {}
            // As linhas que realmente existem na tabela.
            try {
                $stAll = $this->connection->query('SELECT id, pedido_id, tracking_number, wp_post_id, customer_control_code FROM correios_packet_etiquetas ORDER BY id DESC LIMIT 20');
                $out['tabela_linhas_existentes'] = $stAll ? ($stAll->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            } catch (\Throwable $e) {}

            // Reler a tabela local após a tentativa
            $st = $this->connection->prepare('SELECT id, pedido_id, tracking_number, wp_post_id FROM correios_packet_etiquetas WHERE pedido_id = ? ORDER BY id DESC');
            $st->execute([$pedidoId]);
            $out['correios_packet_etiquetas_apos_sync'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $out['sincronizar_erro'] = $e->getMessage();
        }

        // Últimos pacotes do WP (sem filtro) para ver se o pacote deste pedido existe com outro código.
        try {
            $respUlt = $this->wp->listPackages(['per_page' => 15]);
            $listaUlt = (is_array($respUlt) && isset($respUlt['data']) && is_array($respUlt['data'])) ? $respUlt['data'] : [];
            $out['wp_ultimos_pacotes'] = array_map(function ($pkg) {
                return [
                    'wp_post_id' => $pkg['wp_post_id'] ?? null,
                    'order_id' => $pkg['order_id'] ?? null,
                    'pedido_id_local' => $pkg['pedido_id_local'] ?? null,
                    'tracking_code' => $pkg['tracking_code'] ?? null,
                    'recipient_name' => $pkg['recipient_name'] ?? null,
                    'created_at' => $pkg['created_at'] ?? null,
                ];
            }, $listaUlt);
        } catch (\Throwable $e) {
            $out['wp_ultimos_erro'] = $e->getMessage();
        }

        $this->json(['success' => true, 'diagnostico' => $out]);
    }

    /**
     * Recupera/sincroniza a etiqueta de um pedido a partir do WordPress quando a linha local
     * em correios_packet_etiquetas está ausente (ex.: falha antiga ao salvar, apesar do status
     * já estar 'etiqueta_gerada'). Busca o pacote no WP pelo código do pedido e grava localmente.
     * POST /admin/etiquetas-wp/sincronizar-rastreio
     * Body JSON: { pedido_id: int }
     */
    public function sincronizarRastreio(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) { $body = []; }
        $pedidoId = (int) ($body['pedido_id'] ?? $request->getParam('pedido_id', 0));
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_order_id', 'pedido_id inválido')], 400);
            return;
        }

        // Já existe linha local com tracking? Então nada a fazer.
        try {
            $st = $this->connection->prepare("SELECT tracking_number FROM correios_packet_etiquetas WHERE pedido_id = ? AND tracking_number IS NOT NULL AND tracking_number <> '' ORDER BY id DESC LIMIT 1");
            $st->execute([$pedidoId]);
            $ja = trim((string) ($st->fetchColumn() ?: ''));
            if ($ja !== '') {
                $this->json(['success' => true, 'tracking_number' => $ja, 'ja_existia' => true]);
                return;
            }
        } catch (\Exception $e) {
        }

        // Buscar no WordPress e salvar localmente (núcleo reutilizável).
        $r = $this->sincronizarPedidoDoWp($pedidoId);
        $tracking = (string) ($r['tracking_number'] ?? '');

        if ($tracking === '') {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.wp_package_not_found', 'Não encontrei o pacote correspondente no WordPress. Verifique se a etiqueta foi realmente gerada.')], 404);
            return;
        }

        $this->json([
            'success' => true,
            'pedido_id' => $pedidoId,
            'tracking_number' => $tracking,
            'wp_post_id' => $r['wp_post_id'] ?? null,
        ]);
    }

    /**
     * Sincroniza de uma vez TODOS os pedidos que estão com status 'etiqueta_gerada' mas sem
     * rastreio salvo localmente. Busca cada um no WordPress e grava. Rode uma única vez após
     * subir para produção para resolver o backlog. Idempotente (pula quem já tem tracking).
     * GET /admin/etiquetas-wp/sincronizar-todos
     */
    public function sincronizarTodosRastreios(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=utf-8');

        // Buscar pedidos com etiqueta gerada porém sem linha local com tracking.
        $pedidoIds = [];
        try {
            $sql = "SELECT p.id FROM pedidos p
                    LEFT JOIN correios_packet_etiquetas cpe
                      ON cpe.pedido_id = p.id AND cpe.tracking_number IS NOT NULL AND cpe.tracking_number <> ''
                    WHERE LOWER(COALESCE(p.status,'')) IN ('etiqueta_gerada','em_transporte','aguardando_liberacao_aduaneira','enviado_ao_destinatario','entregue')
                      AND cpe.id IS NULL
                    ORDER BY p.id DESC
                    LIMIT 1000";
            $st = $this->connection->query($sql);
            $pedidoIds = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao listar pedidos: ' . $e->getMessage()], 500);
            return;
        }

        $pedidoIds = array_values(array_filter(array_map('intval', $pedidoIds), fn($v) => $v > 0));

        $sincronizados = 0;
        $naoEncontrados = 0;
        $detalhes = [];
        foreach ($pedidoIds as $pid) {
            $r = $this->sincronizarPedidoDoWp($pid);
            if (!empty($r['tracking_number'])) {
                $sincronizados++;
                $detalhes[] = ['pedido_id' => $pid, 'tracking_number' => $r['tracking_number']];
            } else {
                $naoEncontrados++;
            }
        }

        $this->json([
            'success' => true,
            'total_verificados' => count($pedidoIds),
            'sincronizados' => $sincronizados,
            'nao_encontrados_no_wp' => $naoEncontrados,
            'detalhes' => $detalhes,
        ]);
    }

    /**
     * Retorna true se o pedido NÃO tem rastreio salvo localmente em correios_packet_etiquetas.
     */
    private function pedidoSemTrackingLocal(int $pedidoId): bool
    {
        if ($pedidoId <= 0 || !$this->tableExists('correios_packet_etiquetas')) {
            return true;
        }
        try {
            $st = $this->connection->prepare("SELECT tracking_number FROM correios_packet_etiquetas WHERE pedido_id = ? AND tracking_number IS NOT NULL AND tracking_number <> '' ORDER BY id DESC LIMIT 1");
            $st->execute([$pedidoId]);
            return trim((string) ($st->fetchColumn() ?: '')) === '';
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Núcleo reutilizável: busca a etiqueta de um pedido no WordPress e grava localmente.
     * Retorna ['tracking_number' => string|'', 'wp_post_id' => mixed].
     */
    private function sincronizarPedidoDoWp(int $pedidoId): array
    {
        $vazio = ['tracking_number' => '', 'wp_post_id' => null];
        if ($pedidoId <= 0) return $vazio;

        $pedidoModel = new PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes($pedidoId);
        if (!is_array($pedido) || empty($pedido['id'])) return $vazio;

        $codigo = trim((string) ($pedido['codigo_pedido'] ?? ($pedido['numero_pedido'] ?? '')));

        // Coletar candidatos de pacotes do WP por vínculo EXATO (nunca busca parcial/fuzzy,
        // que casava com pedidos de outros clientes — ex.: "761" batendo em "67617").
        $candidatos = [];

        // 1) Vínculo exato por pedido_id_local (meta gravada na geração). É o mais confiável.
        try {
            $respPid = $this->wp->listPackagesByPedidoLocal($pedidoId);
            $listaPid = (is_array($respPid) && isset($respPid['data']) && is_array($respPid['data'])) ? $respPid['data'] : [];
            foreach ($listaPid as $pkg) {
                if ((int) ($pkg['pedido_id_local'] ?? 0) === $pedidoId) {
                    $candidatos[] = $pkg;
                }
            }
        } catch (\Throwable $e) {
        }

        // 2) Fallback: busca pelo código do pedido, mas só aceitando match EXATO de order_id.
        if (empty($candidatos) && $codigo !== '') {
            try {
                $resp = $this->wp->listPackages(['search' => $codigo, 'per_page' => 50]);
                $lista = (is_array($resp) && isset($resp['data']) && is_array($resp['data'])) ? $resp['data'] : [];
                foreach ($lista as $pkg) {
                    if (trim((string) ($pkg['order_id'] ?? '')) === $codigo) {
                        $candidatos[] = $pkg;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // Escolher o candidato com tracking preenchido (o mais recente por wp_post_id).
        $escolhido = null;
        foreach ($candidatos as $pkg) {
            if (trim((string) ($pkg['tracking_code'] ?? '')) === '') continue;
            if ($escolhido === null || (int) ($pkg['wp_post_id'] ?? 0) > (int) ($escolhido['wp_post_id'] ?? 0)) {
                $escolhido = $pkg;
            }
        }

        if ($escolhido !== null) {
            $trk = trim((string) ($escolhido['tracking_code'] ?? ''));
            $this->salvarEtiquetaLocal($pedidoId, $codigo !== '' ? $codigo : (string) $pedidoId, $trk, [
                'tracking_number' => $trk,
                'wp_post_id' => $escolhido['wp_post_id'] ?? null,
                'origem' => 'sincronizacao',
            ]);
            return ['tracking_number' => $trk, 'wp_post_id' => $escolhido['wp_post_id'] ?? null];
        }

        return $vazio;
    }

    /**
     * Notifica os clientes dos pacotes selecionados na tela de etiquetas (e-mail + WhatsApp)
     * com o código de rastreio da etiqueta gerada. Não depende de container/fatura/embarque.
     * POST /admin/etiquetas-wp/notificar-selecionados
     * Body JSON: { pedido_ids: [int, ...] }
     */
    public function notificarSelecionados(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = [];
        }
        $pedidoIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($body['pedido_ids'] ?? [])),
            fn($v) => $v > 0
        )));

        if (empty($pedidoIds)) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.no_order_selected', 'Nenhum pedido selecionado')], 400);
            return;
        }

        $enviadas = 0;
        $falhas = 0;
        $detalhes = [];
        $notif = new \App\Services\NotificationService();

        foreach ($pedidoIds as $pid) {
            // Buscar o rastreio da etiqueta PACKET do pedido para incluir na notificação.
            $tracking = '';
            if ($this->tableExists('correios_packet_etiquetas')) {
                try {
                    $st = $this->connection->prepare('SELECT tracking_number FROM correios_packet_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                    $st->execute([$pid]);
                    $tracking = trim((string) ($st->fetchColumn() ?: ''));
                } catch (\Exception $e) {
                }
            }

            try {
                $r = $notif->notificarEventoPedido('correios_packet_label_created', $pid, [
                    'tracking_number' => $tracking,
                ]);
                $okEmail = !empty($r['email_enviado']);
                $okWhats = !empty($r['whatsapp_enviado']);
                if ($okEmail || $okWhats) {
                    $enviadas++;
                    $detalhes[] = [
                        'pedido_id' => $pid,
                        'success' => true,
                        'tracking_number' => $tracking,
                        'email' => $okEmail,
                        'whatsapp' => $okWhats,
                    ];
                } else {
                    // Nenhum canal enviou de fato — reportar como falha real (não sucesso silencioso).
                    $falhas++;
                    $detalhes[] = [
                        'pedido_id' => $pid,
                        'success' => false,
                        'error' => (string) ($r['email_erro'] ?? $r['whatsapp_erro'] ?? 'Nenhum canal enviou'),
                    ];
                }
            } catch (\Throwable $e) {
                $falhas++;
                $detalhes[] = ['pedido_id' => $pid, 'success' => false, 'error' => $e->getMessage()];
                error_log('[ETIQUETAS_WP][NOTIF] Falha ao notificar pedido #' . $pid . ': ' . $e->getMessage());
            }
        }

        $this->json([
            'success' => $enviadas > 0,
            'enviadas' => $enviadas,
            'falhas' => $falhas,
            'detalhes' => $detalhes,
        ]);
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
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_wp_post_id', 'wp_post_id inválido')], 400);
            return;
        }

        $resp = $this->wp->deleteContainer($wpPostId);
        
        // Debug: logar a resposta completa do WordPress
        error_log('[BRZ-DELETE-CONTAINER] wp_post_id=' . $wpPostId . ' | resp=' . json_encode($resp));
        
        // Garantir que sempre tem a chave 'error' se não teve sucesso
        if (empty($resp['success']) && empty($resp['error'])) {
            $resp['error'] = $resp['message'] ?? $resp['raw'] ?? __('admin.labels_wp.delete_container_unknown_error', 'Erro desconhecido ao deletar container');
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
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_wp_post_id', 'wp_post_id inválido')], 400);
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
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_wp_post_id', 'wp_post_id inválido')], 400);
            return;
        }

        $resp = $this->wp->deleteDeparture($wpPostId);
        $this->json($resp, !empty($resp['success']) ? 200 : 400);
    }

    // =========================================================
    // REGERAR ETIQUETA
    // =========================================================

    /**
     * Regerar etiqueta: deleta a existente e gera nova com dados atuais do pedido.
     * POST /admin/etiquetas-wp/regerar-etiqueta
     * Body JSON: { pedido_id: int }
     */
    public function regerarEtiqueta(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $body = json_decode(file_get_contents('php://input'), true);
        $pedidoId = (int) ($body['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_order_id', 'pedido_id inválido')], 400);
            return;
        }

        // Marcar etiqueta antiga como cancelada no WordPress (via fix-meta)
        try {
            if ($this->tableExists('correios_packet_etiquetas')) {
                $stWp = $this->connection->prepare('SELECT wp_post_id FROM correios_packet_etiquetas WHERE pedido_id = ? LIMIT 1');
                $stWp->execute([$pedidoId]);
                $wpPostId = (int) ($stWp->fetchColumn() ?: 0);
                if ($wpPostId > 0) {
                    $this->wp->fixPackageMeta($wpPostId, ['packageStatus' => 'cancelado']);
                }
            }
        } catch (\Exception $e) {
            // Não bloquear se falhar a marcação no WP
            error_log('[BRZ-REGERAR-WP] Erro ao marcar etiqueta como cancelada no WP (pedido #' . $pedidoId . '): ' . $e->getMessage());
        }

        // Deletar etiqueta local existente
        try {
            if ($this->tableExists('correios_packet_etiquetas')) {
                $stDel = $this->connection->prepare('DELETE FROM correios_packet_etiquetas WHERE pedido_id = ?');
                $stDel->execute([$pedidoId]);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.delete_previous_label_error', 'Erro ao deletar etiqueta anterior: ') . $e->getMessage()], 500);
            return;
        }

        // Reverter status do pedido para permitir nova geração
        try {
            $pedidoModel = new PedidoEcommerce();
            $pedidoModel->atualizarStatus($pedidoId, 'produto_consolidado', __('admin.labels_wp.status_label_deleted_regen', 'Etiqueta deletada para regeração (via WP)'), $_SESSION['usuario_id'] ?? null);
        } catch (\Exception $e) {
            // Não bloquear se falhar a reversão de status
            error_log('[BRZ-REGERAR-WP] Erro ao reverter status pedido #' . $pedidoId . ': ' . $e->getMessage());
        }

        // Agora gerar nova etiqueta usando o mesmo fluxo
        $this->gerarEtiqueta($request);
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
            echo __('admin.labels_wp.invalid_id', 'ID inválido.');
            return;
        }

        // Antes de gerar o PDF, enviar dados de fix para o WP (pedido_id_local + items)
        try {
            // Buscar pedido_id_local a partir do wp_post_id no banco local
            $st = $this->connection->prepare(
                "SELECT pedido_id FROM correios_packet_etiquetas WHERE wp_post_id = ? LIMIT 1"
            );
            $st->execute([$wpPostId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            error_log('[BRZ-PDF-FIX] wp_post_id=' . $wpPostId . ' | etiqueta_row=' . json_encode($row));
            if ($row && !empty($row['pedido_id'])) {
                $pedidoId = (int) $row['pedido_id'];
                // IMPORTANTE: NÃO reenviar 'pedidoIdLocal' aqui. O pacote já nasce no WordPress
                // com o vínculo correto (_pedido_id_local / _package_order_id) na criação.
                // Reescrevê-lo a cada download de PDF corrompia o vínculo quando o SELECT por
                // wp_post_id (que não é único na tabela local) resolvia o pedido errado — foi
                // exatamente o que fez o pacote do 747 virar 738 após gerar o PDF.
                // O fix-meta do PDF deve corrigir SOMENTE os itens (descrição/NCM/valor/peso).
                $fixData = [];

                // Buscar itens do pedido para enviar ao WP
                try {
                    // Detectar tabela de itens (pedido_itens ou pedido_items)
                    $itensTable = null;
                    foreach (['pedido_itens', 'pedido_items'] as $t) {
                        try {
                            $stCheck = $this->connection->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
                            $stCheck->execute([$t]);
                            if ($stCheck->fetchColumn()) { $itensTable = $t; break; }
                        } catch (\Exception $e) {}
                    }
                    error_log('[BRZ-PDF-FIX] pedido_id=' . $pedidoId . ' | itensTable=' . ($itensTable ?? 'NULL'));
                    if ($itensTable) {
                        $cols = [];
                        try { $stC = $this->connection->query("DESCRIBE {$itensTable}"); $cols = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                        $nomeCol = in_array('nome_produto', $cols) ? 'nome_produto' : (in_array('nome', $cols) ? 'nome' : 'nome_produto');
                        $precoCol = in_array('preco_unitario', $cols) ? 'preco_unitario' : (in_array('valor_unitario', $cols) ? 'valor_unitario' : 'preco_unitario');
                        $qtdCol = in_array('quantidade', $cols) ? 'quantidade' : 'quantidade';
                        $hasProdutoId = in_array('produto_id', $cols);
                        $hasNcmCol = in_array('ncm', $cols);
                        $hasProdutoNcmCol = in_array('produto_ncm', $cols);
                        $hasTipoItem = in_array('tipo_item', $cols);
                        $hasDeclarationValue = in_array('declaration_value', $cols);

                        // Buscar itens com NCM da própria tabela (prioridade) e da tabela produtos (fallback)
                        $selectCols = "i.{$nomeCol}, i.{$precoCol}, i.{$qtdCol}";
                        if ($hasProdutoId) $selectCols .= ", i.produto_id";
                        if ($hasNcmCol) $selectCols .= ", i.ncm AS item_ncm";
                        if ($hasProdutoNcmCol) $selectCols .= ", i.produto_ncm";
                        if ($hasTipoItem) $selectCols .= ", i.tipo_item";
                        if ($hasDeclarationValue) $selectCols .= ", i.declaration_value";
                        if (in_array('peso_kg', $cols)) $selectCols .= ", i.peso_kg";
                        if (in_array('peso_manual', $cols)) $selectCols .= ", i.peso_manual";
                        if (in_array('pacote_id', $cols)) $selectCols .= ", i.pacote_id";

                        if ($hasProdutoId) {
                            $sql = "SELECT {$selectCols}, p.ncm AS produto_ncm_join
                                    FROM {$itensTable} i 
                                    LEFT JOIN produtos p ON p.id = i.produto_id 
                                    WHERE i.pedido_id = ?";
                        } else {
                            $sql = "SELECT {$selectCols} FROM {$itensTable} i WHERE i.pedido_id = ?";
                        }

                        $stItems = $this->connection->prepare($sql);
                        $stItems->execute([$pedidoId]);
                        $itemsRows = $stItems->fetchAll(\PDO::FETCH_ASSOC);
                        error_log('[BRZ-PDF-FIX] pedido_id=' . $pedidoId . ' | items_found=' . count($itemsRows) . ' | sample=' . json_encode($itemsRows[0] ?? []));
                        if (!empty($itemsRows)) {
                            // Verificar moeda do pedido para conversão BRL→USD
                            $brlToUsdRate = 1.0;
                            $moedaPedido = 'USD';
                            try {
                                $stMoeda = $this->connection->prepare("SELECT moeda FROM pedidos WHERE id = ? LIMIT 1");
                                $stMoeda->execute([$pedidoId]);
                                $moedaPedido = strtoupper(trim((string) ($stMoeda->fetchColumn() ?: 'USD')));
                                if ($moedaPedido === 'BRL') {
                                    $usdRate = $this->getUsdToBrlRate();
                                    $brlToUsdRate = ($usdRate > 0.000001) ? (1.0 / $usdRate) : (1.0 / \App\Core\ExchangeRate::getUsdToBrl());
                                }
                            } catch (\Exception $e) {}

                            $items = [];

                            // Determinar se os valores já estão em USD:
                            // Se o pedido tem itens de pacote_redirecionamento, todos os preco_unitario estão em USD
                            $todosUsd = false;
                            if ($hasTipoItem) {
                                foreach ($itemsRows as $checkRow) {
                                    if (($checkRow['tipo_item'] ?? '') === 'pacote_redirecionamento' || (int) ($checkRow['produto_id'] ?? 0) >= 999990) {
                                        $todosUsd = true;
                                        break;
                                    }
                                }
                            }

                            foreach ($itemsRows as $it) {
                                // NCM: prioridade item_ncm > produto_ncm > produto_ncm_join
                                $ncmRaw = (string) ($it['item_ncm'] ?? ($it['produto_ncm'] ?? ($it['produto_ncm_join'] ?? '')));
                                $ncmDigits = preg_replace('/\D/', '', $ncmRaw);
                                $hs = strlen($ncmDigits) >= 8 ? substr($ncmDigits, 0, 8) : (strlen($ncmDigits) >= 6 ? substr($ncmDigits, 0, 6) : $ncmDigits);

                                $isPacoteItem = (($it['tipo_item'] ?? 'produto') === 'pacote_redirecionamento')
                                    || ((int) ($it['produto_id'] ?? 0) >= 999990);

                                $val = (float) ($it[$precoCol] ?? 0);
                                // Se tem declaration_value preenchido para pacotes, usar ele
                                if ($isPacoteItem && !empty($it['declaration_value']) && (float) $it['declaration_value'] > 0) {
                                    $val = (float) $it['declaration_value'];
                                }

                                // Converter BRL→USD somente se pedido é BRL E valores estão em BRL
                                // (pedidos mistos com pacotes salvam tudo em USD)
                                if (!$todosUsd && $moedaPedido === 'BRL' && $val > 0) {
                                    $val = $val * $brlToUsdRate;
                                }

                                if ($val < 0.01) $val = 0.01;

                                // Descrição: usar nome_produto, com fallback para pacotes_recebidos
                                $descFix = trim((string) ($it[$nomeCol] ?? ''));
                                if ($descFix === '' || $descFix === 'Item' || strpos($descFix, 'Produto #') === 0) {
                                    // Tentar buscar nome do pacote_recebido via pacote_id
                                    $pacoteIdFix = (int) ($it['pacote_id'] ?? 0);
                                    if ($isPacoteItem && $pacoteIdFix > 0) {
                                        try {
                                            $stPacNome = $this->connection->prepare(
                                                "SELECT nome FROM pacotes_recebidos WHERE id = ? LIMIT 1"
                                            );
                                            $stPacNome->execute([$pacoteIdFix]);
                                            $nomePacFix = trim((string) ($stPacNome->fetchColumn() ?: ''));
                                            if ($nomePacFix !== '') $descFix = $nomePacFix;
                                        } catch (\Throwable $e) {}
                                    }
                                    if ($descFix === '' || $descFix === 'Item' || strpos($descFix, 'Produto #') === 0) {
                                        $descFix = 'Item';
                                    }
                                }

                                // Peso individual do item
                                $pesoFixItem = 0;
                                if (in_array('peso_kg', $cols)) {
                                    $pesoFixItem = (float) ($it['peso_kg'] ?? 0);
                                }
                                if ($pesoFixItem <= 0 && in_array('peso_manual', $cols)) {
                                    $pesoFixItem = (float) ($it['peso_manual'] ?? 0);
                                }
                                // Fallback: buscar peso do pacote_recebido
                                if ($pesoFixItem <= 0 && $isPacoteItem) {
                                    $pacoteIdPeso = (int) ($it['pacote_id'] ?? 0);
                                    if ($pacoteIdPeso > 0) {
                                        try {
                                            $stPeso = $this->connection->prepare('SELECT peso_kg FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                                            $stPeso->execute([$pacoteIdPeso]);
                                            $pesoPac = (float) ($stPeso->fetchColumn() ?: 0);
                                            if ($pesoPac > 0) $pesoFixItem = $pesoPac;
                                        } catch (\Throwable $e) {}
                                    }
                                }

                                $items[] = [
                                    'hsCode' => $hs,
                                    'description' => $descFix,
                                    'quantity' => (int) ($it[$qtdCol] ?? 1),
                                    'value' => (float) number_format($val, 2, '.', ''),
                                    'weight' => (float) $pesoFixItem,
                                ];
                            }
                            $fixData['items'] = $items;
                            error_log('[BRZ-PDF-FIX] items_to_send=' . json_encode($items));
                        }
                    }
                } catch (\Exception $e) {
                    error_log('[BRZ-PDF-FIX] ERRO itens: ' . $e->getMessage());
                }

                // Só chamar o fix-meta se houver itens para corrigir (não reescrevemos mais o vínculo).
                if (!empty($fixData['items'])) {
                    error_log('[BRZ-PDF-FIX] Chamando fixPackageMeta | fixData=' . json_encode($fixData));
                    $fixResp = $this->wp->fixPackageMeta($wpPostId, $fixData);
                    error_log('[BRZ-PDF-FIX] fixPackageMeta resp=' . json_encode($fixResp));
                }
            }
        } catch (\Exception $e) {
            error_log('[BRZ-PDF-FIX] ERRO geral: ' . $e->getMessage());
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
            echo __('admin.labels_wp.invalid_id', 'ID inválido.');
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
            echo __('admin.labels_wp.invalid_id', 'ID inválido.');
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
        if ($request->getParam('page')) $params['page'] = (int) $request->getParam('page');
        if ($request->getParam('search')) $params['search'] = trim((string) $request->getParam('search'));

        $resp = $this->wp->listPackages($params);

        // Enriquecer com pedido_id local para exibição formatada
        if (!empty($resp['success']) && !empty($resp['data']) && is_array($resp['data'])) {
            try {
                // Primeiro: buscar por tracking_number na tabela de etiquetas
                $trackings = array_filter(array_map(function($p) { return $p['tracking_code'] ?? ''; }, $resp['data']));
                $mapByTracking = [];
                if (!empty($trackings)) {
                    $in = implode(',', array_fill(0, count($trackings), '?'));
                    $st = $this->connection->prepare("SELECT tracking_number, pedido_id FROM correios_packet_etiquetas WHERE tracking_number IN ({$in})");
                    $st->execute(array_values($trackings));
                    foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $mapByTracking[$row['tracking_number']] = (int) $row['pedido_id'];
                    }
                }

                // Segundo: para os que não encontrou, buscar pelo order_id como numero_pedido na tabela pedidos
                $orderIds = [];
                foreach ($resp['data'] as $pkg) {
                    $tc = $pkg['tracking_code'] ?? '';
                    if (!isset($mapByTracking[$tc]) && !empty($pkg['order_id'])) {
                        $orderIds[] = $pkg['order_id'];
                    }
                }
                $mapByOrderId = [];
                if (!empty($orderIds)) {
                    $colsP = [];
                    try { $stC = $this->connection->query('DESCRIBE pedidos'); $colsP = $stC ? $stC->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                    $hasNumeroPedido = in_array('numero_pedido', $colsP, true);
                    $hasCodigoPedido = in_array('codigo_pedido', $colsP, true);
                    if ($hasNumeroPedido || $hasCodigoPedido) {
                        $col = $hasNumeroPedido ? 'numero_pedido' : 'codigo_pedido';
                        $in2 = implode(',', array_fill(0, count($orderIds), '?'));
                        $st2 = $this->connection->prepare("SELECT id, {$col} FROM pedidos WHERE {$col} IN ({$in2})");
                        $st2->execute(array_values($orderIds));
                        foreach ($st2->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                            $mapByOrderId[$row[$col]] = (int) $row['id'];
                        }
                    }
                }

                foreach ($resp['data'] as &$pkg) {
                    $tc = $pkg['tracking_code'] ?? '';
                    $oid = $pkg['order_id'] ?? '';
                    // Prioridade 1: pedido_id_local que o próprio WP guarda (ID exato, sem ambiguidade).
                    $pidWp = (int) ($pkg['pedido_id_local'] ?? 0);
                    if ($pidWp > 0) {
                        $pkg['pedido_id_local'] = $pidWp;
                    } elseif (isset($mapByTracking[$tc])) {
                        $pkg['pedido_id_local'] = $mapByTracking[$tc];
                    } elseif (isset($mapByOrderId[$oid])) {
                        $pkg['pedido_id_local'] = $mapByOrderId[$oid];
                    }
                }
                unset($pkg);

                // Enriquecer com nome do cliente a partir do banco local quando não veio da API
                $pedidoIds = array_filter(array_map(function($p) { return $p['pedido_id_local'] ?? null; }, $resp['data']));
                if (!empty($pedidoIds)) {
                    $in3 = implode(',', array_fill(0, count($pedidoIds), '?'));
                    try {
                        $st3 = $this->connection->prepare("SELECT p.id, p.cliente_nome FROM pedidos p WHERE p.id IN ({$in3})");
                        $st3->execute(array_values($pedidoIds));
                        $mapNomes = [];
                        foreach ($st3->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                            $mapNomes[(int) $row['id']] = $row['cliente_nome'] ?? '';
                        }
                        foreach ($resp['data'] as &$pkg) {
                            $pid = $pkg['pedido_id_local'] ?? null;
                            if ($pid && isset($mapNomes[$pid]) && empty($pkg['recipient_name'])) {
                                $pkg['recipient_name'] = $mapNomes[$pid];
                            }
                        }
                        unset($pkg);
                    } catch (\Exception $e) {}
                }
            } catch (\Exception $e) {}
        }

        // Enriquecer com informação de mala
        if (!empty($resp['success']) && !empty($resp['data']) && is_array($resp['data'])) {
            try {
                $this->ensureMalasTable();
                $trackingsAll = array_filter(array_map(function($p) { return $p['tracking_code'] ?? ''; }, $resp['data']));
                if (!empty($trackingsAll)) {
                    $in = implode(',', array_fill(0, count($trackingsAll), '?'));
                    $stMala = $this->connection->prepare("SELECT mp.tracking_code, m.id AS mala_id, m.nome AS mala_nome FROM etiquetas_mala_pacotes mp INNER JOIN etiquetas_malas m ON m.id = mp.mala_id WHERE mp.tracking_code IN ({$in})");
                    $stMala->execute(array_values($trackingsAll));
                    $mapMala = [];
                    foreach ($stMala->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $mapMala[$row['tracking_code']] = ['id' => (int) $row['mala_id'], 'nome' => $row['mala_nome']];
                    }
                    foreach ($resp['data'] as &$pkg) {
                        $tc = $pkg['tracking_code'] ?? '';
                        if (isset($mapMala[$tc])) {
                            $pkg['mala_id'] = $mapMala[$tc]['id'];
                            $pkg['mala_nome'] = $mapMala[$tc]['nome'];
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
            return ['_error' => __('admin.labels_wp.invalid_zip', 'CEP inválido (deve ter 8 dígitos)')];
        }
        $phoneDigits = $destinatario['recipientPhoneNumber'];
        if ($phoneDigits === '' || !in_array(strlen($phoneDigits), [10, 11], true)) {
            return ['_error' => __('admin.labels_wp.invalid_phone', 'Telefone inválido (10 ou 11 dígitos)')];
        }
        $destEmail = $destinatario['recipientEmail'];
        if ($destEmail === '' || filter_var($destEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['_error' => __('admin.labels_wp.invalid_email', 'E-mail inválido')];
        }
        $docType = $destinatario['recipientDocumentType'];
        $docNum = $destinatario['recipientDocumentNumber'];
        if ($docType === 'CPF' && strlen($docNum) !== 11) {
            return ['_error' => __('admin.labels_wp.invalid_cpf', 'CPF inválido (11 dígitos)')];
        }
        if ($docType === 'CNPJ' && strlen($docNum) !== 14) {
            return ['_error' => __('admin.labels_wp.invalid_cnpj', 'CNPJ inválido (14 dígitos)')];
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

        if ($totalWeight <= 0) return ['_error' => __('admin.labels_wp.weight_missing', 'Peso não informado')];
        if ($totalWeight > 30000) return ['_error' => __('admin.labels_wp.weight_exceeds', 'Peso excede 30kg')];
        if ($packagingLength < 16 || $packagingLength > 100) return ['_error' => __('admin.labels_wp.invalid_length', 'Comprimento inválido (16-100cm)')];
        if ($packagingWidth < 11 || $packagingWidth > 100) return ['_error' => __('admin.labels_wp.invalid_width', 'Largura inválida (11-100cm)')];
        if ($packagingHeight < 2 || $packagingHeight > 100) return ['_error' => __('admin.labels_wp.invalid_height', 'Altura inválida (2-100cm)')];
        if (($packagingLength + $packagingWidth + $packagingHeight) > 200) return ['_error' => __('admin.labels_wp.dimensions_sum_exceeds', 'Soma dimensões > 200cm')];

        // Itens
        // Se o pedido tem invoice confirmado, usar dados do invoice (nome_produto, ncm, declaration_value)
        $itemsIn = isset($pedido['items']) && is_array($pedido['items']) ? $pedido['items'] : [];

        // Verificar se os itens vieram da tabela correta (com tipo_item)
        // Se não, buscar diretamente de pedido_itens que tem as colunas de pacote
        $pedidoIdLocal = (int) ($pedido['id'] ?? 0);
        $precisaBuscarDireto = false;
        if (!empty($itemsIn)) {
            // Se algum item tem produto_id >= 999990 mas NÃO tem tipo_item, os dados vieram de pedido_items (sem as colunas)
            foreach ($itemsIn as $checkIt) {
                if ((int) ($checkIt['produto_id'] ?? 0) >= 999990 && empty($checkIt['tipo_item'])) {
                    $precisaBuscarDireto = true;
                    break;
                }
            }
        }

        if ($precisaBuscarDireto && $pedidoIdLocal > 0) {
            try {
                $dbDireto = \Config\Database::getConnection();
                $stDireto = $dbDireto->prepare(
                    "SELECT * FROM pedido_itens WHERE pedido_id = ? ORDER BY id ASC"
                );
                $stDireto->execute([$pedidoIdLocal]);
                $itensDireto = $stDireto->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                if (!empty($itensDireto)) {
                    $itemsIn = [];
                    foreach ($itensDireto as $itD) {
                        $nomeProd = trim((string) ($itD['nome_produto'] ?? ($itD['nome_item'] ?? '')));
                        // Fallback: buscar nome do pacote_recebido quando nome está vazio ou genérico
                        $pidItem = (int) ($itD['produto_id'] ?? 0);
                        $pacoteIdItem = (int) ($itD['pacote_id'] ?? 0);
                        if (($nomeProd === '' || $nomeProd === 'Item' || strpos($nomeProd, 'Produto #') === 0) && $pacoteIdItem > 0) {
                            try {
                                $stNomePac = $dbDireto->prepare('SELECT nome FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                                $stNomePac->execute([$pacoteIdItem]);
                                $nomePac = trim((string) ($stNomePac->fetchColumn() ?: ''));
                                if ($nomePac !== '') $nomeProd = $nomePac;
                            } catch (\Throwable $e) {}
                        }
                        // Peso: fallback para pacotes_recebidos.peso_kg
                        $pesoKgItem = (float) ($itD['peso_manual'] ?? ($itD['peso_kg'] ?? 0));
                        if ($pesoKgItem <= 0 && $pacoteIdItem > 0) {
                            try {
                                $stPesoDir = $dbDireto->prepare('SELECT peso_kg FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                                $stPesoDir->execute([$pacoteIdItem]);
                                $pesoDirFb = (float) ($stPesoDir->fetchColumn() ?: 0);
                                if ($pesoDirFb > 0) $pesoKgItem = $pesoDirFb;
                            } catch (\Throwable $e) {}
                        }
                        $itemsIn[] = [
                            'produto_id' => $pidItem,
                            'nome_produto' => $nomeProd,
                            'nome' => $nomeProd,
                            'ncm' => $itD['ncm'] ?? ($itD['produto_ncm'] ?? ''),
                            'produto_ncm' => $itD['produto_ncm'] ?? ($itD['ncm'] ?? ''),
                            'preco_unitario' => (float) ($itD['preco_unitario'] ?? 0),
                            'declaration_value' => (float) ($itD['declaration_value'] ?? 0),
                            'quantidade' => (int) ($itD['quantidade'] ?? 1),
                            'peso_kg' => $pesoKgItem,
                            'tipo_item' => $itD['tipo_item'] ?? 'produto',
                            'pacote_id' => $itD['pacote_id'] ?? null,
                            '_valor_ja_usd' => (($itD['tipo_item'] ?? '') === 'pacote_redirecionamento'),
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }

        try {
            $dbInv = \Config\Database::getConnection();
            $stInv = $dbInv->prepare(
                "SELECT pi.status FROM pedido_invoices pi WHERE pi.pedido_id = ? AND pi.status = 'confirmado' ORDER BY pi.id DESC LIMIT 1"
            );
            $stInv->execute([(int) ($pedido['id'] ?? 0)]);
            $invoiceConfirmado = $stInv->fetch(\PDO::FETCH_ASSOC);

            if ($invoiceConfirmado) {
                // Buscar itens do invoice confirmado
                $stItems = $dbInv->prepare(
                    "SELECT pii.* FROM pedido_invoice_items pii 
                     INNER JOIN pedido_invoices pi ON pi.id = pii.invoice_id 
                     WHERE pi.pedido_id = ? AND pi.status = 'confirmado' 
                     ORDER BY pii.id ASC"
                );
                $stItems->execute([(int) ($pedido['id'] ?? 0)]);
                $invoiceItems = $stItems->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                if (!empty($invoiceItems)) {
                    $itemsIn = [];
                    foreach ($invoiceItems as $invItem) {
                        $itemsIn[] = [
                            'nome_produto' => $invItem['nome_produto'] ?? 'Produto',
                            'nome' => $invItem['nome_produto'] ?? 'Produto',
                            'ncm' => $invItem['ncm'] ?? '',
                            'preco_unitario' => (float) ($invItem['declaration_value'] ?? 0),
                            'quantidade' => (int) ($invItem['quantidade'] ?? 1),
                            'peso_kg' => (float) ($invItem['peso_kg'] ?? 0),
                            'tem_bateria' => $invItem['tem_bateria'] ?? 'N',
                            'tem_perfume' => $invItem['tem_perfume'] ?? 'N',
                            '_valor_ja_usd' => true, // Flag: não converter BRL→USD
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Se falhar, usa itens normais do pedido
        }
        if (empty($itemsIn)) return ['_error' => __('admin.labels_wp.no_items', 'Sem itens')];

        // Não limitar quantidade de itens (antigo - if (count($itemsIn) > 20) return ['_error' => 'Mais de 20 itens'];) — o WordPress (plugin) cuida de separar
        // em 3 itens principais + folha suplementar internamente

        $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        $brlToUsdRate = 1.0;
        if ($moedaPedido === 'BRL') {
            $usdRate = $this->getUsdToBrlRate();
            $brlToUsdRate = ($usdRate > 0.000001) ? (1.0 / $usdRate) : (1.0 / \App\Core\ExchangeRate::getUsdToBrl());
        }

        $items = [];
        foreach ($itemsIn as $idx => $it) {
            if (!is_array($it)) continue;
            $qtd = (int) ($it['quantidade'] ?? 0);
            if ($qtd <= 0) return ['_error' => __('admin.labels_wp.item_invalid_qty', 'Item #{n} qtd inválida', ['n' => ($idx+1)])];
            $desc = trim((string) ($it['nome_produto'] ?? ($it['nome'] ?? 'Item')));
            if ($desc === '' || $desc === 'Item' || strpos($desc, 'Produto #') === 0) {
                // Fallback: buscar nome do pacote_recebido
                $pacIdFallback = (int) ($it['pacote_id'] ?? 0);
                if ($pacIdFallback > 0) {
                    try {
                        $dbFb = \Config\Database::getConnection();
                        $stFb = $dbFb->prepare('SELECT nome FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                        $stFb->execute([$pacIdFallback]);
                        $nomeFb = trim((string) ($stFb->fetchColumn() ?: ''));
                        if ($nomeFb !== '') $desc = $nomeFb;
                    } catch (\Throwable $e) {}
                }
                if ($desc === '' || $desc === 'Item' || strpos($desc, 'Produto #') === 0) {
                    $desc = 'Item ' . ($idx+1);
                }
            }

            // NCM: tentar múltiplas fontes (ncm, produto_ncm, ncm_code)
            $ncmRaw = (string) ($it['ncm'] ?? ($it['produto_ncm'] ?? ($it['ncm_code'] ?? '')));
            $ncmDigits = $this->onlyDigits($ncmRaw);
            if ($ncmDigits === '' || strlen($ncmDigits) < 6) return ['_error' => __('admin.labels_wp.item_no_ncm', 'Item #{n} sem NCM ({desc})', ['n' => ($idx+1), 'desc' => $desc])];
            $hs = strlen($ncmDigits) >= 8 ? substr($ncmDigits, 0, 8) : substr($ncmDigits, 0, 6);

            // Valor: para itens de pacote (produto_id >= 999990), o valor SEMPRE está em USD
            // Não importa se o pedido é BRL — o declaration_value/preco_unitario de pacotes é USD
            $isPacote = ((int) ($it['produto_id'] ?? 0) >= 999990)
                || (($it['tipo_item'] ?? 'produto') === 'pacote_redirecionamento')
                || !empty($it['_valor_ja_usd']);

            $val = (float) ($it['preco_unitario'] ?? 0);
            if ($val <= 0 && !empty($it['declaration_value'])) {
                $val = (float) $it['declaration_value'];
            }

            // Só converter BRL→USD se NÃO for pacote e NÃO tiver flag _valor_ja_usd
            if (!$isPacote && $moedaPedido === 'BRL' && $val > 0) {
                $val = $val * $brlToUsdRate;
            }

            if ($val < 0.01) $val = 0.01;
            $pesoItem = (float) ($it['peso_kg'] ?? 0);
            // Fallback: buscar peso do pacote_recebido quando peso_kg está zero
            if ($pesoItem <= 0 && $isPacote) {
                $pacIdPeso = (int) ($it['pacote_id'] ?? 0);
                if ($pacIdPeso > 0) {
                    try {
                        $dbPeso = \Config\Database::getConnection();
                        $stPesoFb = $dbPeso->prepare('SELECT peso_kg FROM pacotes_recebidos WHERE id = ? LIMIT 1');
                        $stPesoFb->execute([$pacIdPeso]);
                        $pesoFb = (float) ($stPesoFb->fetchColumn() ?: 0);
                        if ($pesoFb > 0) $pesoItem = $pesoFb;
                    } catch (\Throwable $e) {}
                }
            }
            $items[] = ['hsCode' => $hs, 'description' => substr($desc, 0, 500), 'quantity' => $qtd, 'value' => (float) number_format($val, 2, '.', ''), 'weight' => (float) number_format($pesoItem, 4, '.', '')];
        }
        if (empty($items)) return ['_error' => __('admin.labels_wp.no_valid_items', 'Sem itens válidos')];

        $freightPaidValue = (float) ($overrides['freightPaidValue'] ?? 0.01);
        if ($freightPaidValue < 0.01) $freightPaidValue = 0.01;

        $pid = (int) ($pedido['id'] ?? 0);
        $customerControlCode = (string) ($pedido['codigo_pedido'] ?? ('PED-' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT)));
        if (trim($customerControlCode) === '') $customerControlCode = 'PED-' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT);

        $payload = array_merge($destinatario, [
            'customerControlCode' => substr($customerControlCode, 0, 100),
            'pedidoIdLocal' => $pid,
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

    private function salvarEtiquetaLocal(int $pedidoId, string $controlCode, string $tracking, array $resp): bool
    {
        // Fallback robusto: se o tracking veio vazio, tentar extrair de formatos alternativos
        // que o WordPress/Correios possam retornar (aninhado, camelCase, etc.).
        $tracking = trim((string) $tracking);
        if ($tracking === '') {
            $candidatos = [
                $resp['tracking_number'] ?? null,
                $resp['trackingNumber'] ?? null,
                $resp['tracking_code'] ?? null,
                $resp['data']['tracking_number'] ?? null,
                $resp['data']['trackingNumber'] ?? null,
                $resp['raw'][0]['trackingNumber'] ?? null,
            ];
            foreach ($candidatos as $c) {
                $c = trim((string) $c);
                if ($c !== '') { $tracking = $c; break; }
            }
        }
        if ($tracking === '') {
            error_log('[ETIQUETAS_WP] ATENÇÃO: etiqueta do pedido #' . $pedidoId . ' salva SEM tracking_number. Resp: ' . json_encode($resp));
        }

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

            // Descobrir as colunas realmente existentes na tabela e montar o INSERT
            // apenas com o que existe. Sem isso, se a coluna wp_post_id (ou outra) não
            // existir e o ALTER falhar (ex.: permissão), o INSERT quebrava e a etiqueta
            // NÃO era salva — apesar de o status do pedido já ter mudado (bug do #758).
            $cols = [];
            try {
                $st = $this->connection->query('DESCRIBE correios_packet_etiquetas');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            // Tentar adicionar wp_post_id se faltar (best-effort).
            if (!empty($cols) && !in_array('wp_post_id', $cols, true)) {
                try {
                    $this->connection->exec('ALTER TABLE correios_packet_etiquetas ADD COLUMN wp_post_id INT NULL DEFAULT NULL');
                    $cols[] = 'wp_post_id';
                } catch (\Exception $e) {
                    // Segue sem a coluna — o INSERT abaixo simplesmente não a inclui.
                }
            }

            // Montar dinamicamente colunas/valores conforme o schema real.
            $dados = [
                'pedido_id' => $pedidoId,
                'customer_control_code' => $controlCode,
                'tracking_number' => $tracking,
                'status' => 'gerada',
                'wp_post_id' => $resp['wp_post_id'] ?? null,
                'last_response_json' => json_encode($resp),
            ];
            $insertCols = [];
            $placeholders = [];
            $valores = [];
            $updateParts = [];
            foreach ($dados as $col => $val) {
                // pedido_id sempre entra; as demais só se a coluna existir no schema.
                if ($col !== 'pedido_id' && !empty($cols) && !in_array($col, $cols, true)) {
                    continue;
                }
                $insertCols[] = $col;
                $placeholders[] = '?';
                $valores[] = $val;
                if ($col !== 'pedido_id') {
                    $updateParts[] = $col . ' = VALUES(' . $col . ')';
                }
            }
            // updated_at, se existir
            $hasUpdatedAt = empty($cols) || in_array('updated_at', $cols, true);
            $hasCreatedAt = empty($cols) || in_array('created_at', $cols, true);

            $sqlCols = implode(', ', $insertCols);
            $sqlVals = implode(', ', $placeholders);
            if ($hasCreatedAt) { $sqlCols .= ', created_at'; $sqlVals .= ', NOW()'; }
            if ($hasUpdatedAt) { $sqlCols .= ', updated_at'; $sqlVals .= ', NOW()'; }
            if ($hasUpdatedAt) { $updateParts[] = 'updated_at = NOW()'; }

            $sqlIns = 'INSERT INTO correios_packet_etiquetas (' . $sqlCols . ') VALUES (' . $sqlVals . ')';
            if (!empty($updateParts)) {
                $sqlIns .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
            }

            $this->ultimoSqlSalvarEtiqueta = $sqlIns;
            $stIns = $this->connection->prepare($sqlIns);
            $stIns->execute($valores);
            $this->ultimoRowCountSalvar = $stIns->rowCount();
            $this->ultimoLastInsertId = (string) $this->connection->lastInsertId();
            $this->ultimoErroSalvarEtiqueta = null;
            return true;
        } catch (\Exception $e) {
            $this->ultimoErroSalvarEtiqueta = $e->getMessage();
            error_log('[ETIQUETAS_WP] Erro ao salvar etiqueta local (pedido #' . $pedidoId . '): ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // MALAS (Agrupamento de pacotes para seleção rápida)
    // =========================================================

    private function ensureMalasTable(): void {
        try {
            $this->connection->query("SELECT 1 FROM etiquetas_malas LIMIT 1");
        } catch (\Exception $e) {
            try {
                $this->connection->exec("CREATE TABLE IF NOT EXISTS etiquetas_malas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(100) NOT NULL,
                    descricao VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_nome (nome)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (\Exception $ex) {}
        }

        // Tabela de vínculo pacote -> mala
        try {
            $this->connection->query("SELECT 1 FROM etiquetas_mala_pacotes LIMIT 1");
        } catch (\Exception $e) {
            try {
                $this->connection->exec("CREATE TABLE IF NOT EXISTS etiquetas_mala_pacotes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    mala_id INT NOT NULL,
                    tracking_code VARCHAR(120) NOT NULL,
                    pedido_id INT NULL,
                    peso_gramas INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_mala_id (mala_id),
                    KEY idx_tracking (tracking_code),
                    UNIQUE KEY uniq_tracking (tracking_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (\Exception $ex) {}
        }
    }

    /**
     * Listar malas com contagem de pacotes e peso total.
     * GET /admin/etiquetas-wp/listar-malas
     */
    public function listarMalas(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureMalasTable();

        try {
            $st = $this->connection->prepare("
                SELECT m.*, 
                       COUNT(mp.id) AS pacotes_count
                FROM etiquetas_malas m
                LEFT JOIN etiquetas_mala_pacotes mp ON mp.mala_id = m.id
                GROUP BY m.id
                ORDER BY m.created_at DESC
            ");
            $st->execute();
            $malas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Buscar pacotes de cada mala e calcular peso real a partir dos pedidos
            foreach ($malas as &$mala) {
                $stP = $this->connection->prepare("SELECT mp.tracking_code, mp.pedido_id, p.peso_total FROM etiquetas_mala_pacotes mp LEFT JOIN pedidos p ON p.id = mp.pedido_id WHERE mp.mala_id = ? ORDER BY mp.created_at ASC");
                $stP->execute([(int) $mala['id']]);
                $pacotes = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                $pesoTotalKg = 0;
                foreach ($pacotes as &$pac) {
                    $pesoKg = (float) ($pac['peso_total'] ?? 0);
                    // Se peso > 500, está em gramas — converter para kg
                    if ($pesoKg > 500) $pesoKg = $pesoKg / 1000;
                    $pac['peso_kg'] = $pesoKg;
                    $pesoTotalKg += $pesoKg;
                }
                unset($pac);

                $mala['pacotes'] = $pacotes;
                $mala['peso_total_kg'] = round($pesoTotalKg, 2);
            }
            unset($mala);

            $this->json(['success' => true, 'data' => $malas]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Criar uma nova mala.
     * POST /admin/etiquetas-wp/criar-mala
     * Body: { nome: string, descricao?: string }
     */
    public function criarMala(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureMalasTable();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $nome = trim((string) ($body['nome'] ?? ''));
        $descricao = trim((string) ($body['descricao'] ?? ''));

        if ($nome === '') {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.bag_name_required', 'Nome da mala é obrigatório.')], 400);
            return;
        }

        try {
            $st = $this->connection->prepare("INSERT INTO etiquetas_malas (nome, descricao) VALUES (?, ?)");
            $st->execute([$nome, $descricao]);
            $id = (int) $this->connection->lastInsertId();
            $this->json(['success' => true, 'id' => $id, 'nome' => $nome]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Deletar uma mala (e desvincular pacotes).
     * POST /admin/etiquetas-wp/deletar-mala
     * Body: { mala_id: int }
     */
    public function deletarMala(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureMalasTable();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $malaId = (int) ($body['mala_id'] ?? 0);

        if ($malaId <= 0) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_bag_id', 'mala_id inválido.')], 400);
            return;
        }

        try {
            $this->connection->prepare("DELETE FROM etiquetas_mala_pacotes WHERE mala_id = ?")->execute([$malaId]);
            $this->connection->prepare("DELETE FROM etiquetas_malas WHERE id = ?")->execute([$malaId]);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Atribuir pacotes (tracking codes) a uma mala.
     * POST /admin/etiquetas-wp/atribuir-mala
     * Body: { mala_id: int, tracking_codes: [string], pedido_ids?: [int] }
     */
    public function atribuirMala(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureMalasTable();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $malaId = (int) ($body['mala_id'] ?? 0);
        $trackingCodes = $body['tracking_codes'] ?? [];
        $pedidoIds = $body['pedido_ids'] ?? [];

        if ($malaId <= 0) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.invalid_bag_id', 'mala_id inválido.')], 400);
            return;
        }
        if (!is_array($trackingCodes) || empty($trackingCodes)) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.no_tracking_code_provided', 'Nenhum tracking code informado.')], 400);
            return;
        }

        try {
            // ON DUPLICATE KEY UPDATE permite MOVER uma etiqueta que já esteja em outra mala
            // (o UNIQUE em tracking_code garante que cada etiqueta fique em apenas uma mala).
            $stIns = $this->connection->prepare("INSERT INTO etiquetas_mala_pacotes (mala_id, tracking_code, pedido_id, peso_gramas) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE mala_id = VALUES(mala_id), pedido_id = VALUES(pedido_id), peso_gramas = VALUES(peso_gramas)");

            $added = 0;
            foreach ($trackingCodes as $idx => $tc) {
                $tc = trim((string) $tc);
                if ($tc === '') continue;
                $pid = isset($pedidoIds[$idx]) ? (int) $pedidoIds[$idx] : null;

                // Buscar peso do pacote
                $peso = null;
                if ($pid && $pid > 0) {
                    try {
                        $stPeso = $this->connection->prepare("SELECT peso_total FROM pedidos WHERE id = ? LIMIT 1");
                        $stPeso->execute([$pid]);
                        $pesoRaw = (float) ($stPeso->fetchColumn() ?: 0);
                        if ($pesoRaw > 0) {
                            // Se peso > 1000, provavelmente já está em gramas
                            // Se peso < 1000, provavelmente está em kg
                            if ($pesoRaw > 500) {
                                $peso = (int) round($pesoRaw); // Já está em gramas
                            } else {
                                $peso = (int) round($pesoRaw * 1000); // Converter kg → gramas
                            }
                        }
                    } catch (\Exception $e) {}
                }

                $stIns->execute([$malaId, $tc, $pid, $peso]);
                if ($stIns->rowCount() > 0) $added++;
            }

            $this->json(['success' => true, 'added' => $added]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remover pacotes de uma mala.
     * POST /admin/etiquetas-wp/remover-da-mala
     * Body: { tracking_codes: [string] }
     */
    public function removerDaMala(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureMalasTable();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $trackingCodes = $body['tracking_codes'] ?? [];

        if (!is_array($trackingCodes) || empty($trackingCodes)) {
            $this->json(['success' => false, 'error' => __('admin.labels_wp.no_tracking_code', 'Nenhum tracking code.')], 400);
            return;
        }

        try {
            $in = implode(',', array_fill(0, count($trackingCodes), '?'));
            $this->connection->prepare("DELETE FROM etiquetas_mala_pacotes WHERE tracking_code IN ({$in})")->execute(array_values($trackingCodes));
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
