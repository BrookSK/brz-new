<?php
namespace App\Services;

use App\Models\Carne;
use App\Services\PaymentService;
use Config\Database;

class CarneService {
    private $carneModel;
    private $db;

    public function __construct() {
        $this->carneModel = new Carne();
        $this->db = Database::getConnection();
    }

    /**
     * Calcula parcelas para exibição no checkout
     */
    public function calcularParcelas($totalProdutos, $totalTaxas, $maxParcelas = 12) {
        $total = $totalProdutos + $totalTaxas;
        $opcoes = [];

        // Mínimo por boleto/PIX: R$ 20,00 (~USD 3.40)
        // Câmbio Real com take_rates=1 precisa de margem acima de USD 1.00 líquido
        $minimoBoleto = 20.00;

        for ($i = 1; $i <= $maxParcelas; $i++) {
            $vProd = round($totalProdutos / $i, 2);
            $vTaxa = round($totalTaxas / $i, 2);
            $vTotal = round($total / $i, 2);

            // Só incluir se ambos os boletos atendem o mínimo
            // (ou se o valor é 0, ex: sem taxas ou sem produtos)
            $prodOk = ($vProd <= 0 || $vProd >= $minimoBoleto);
            $taxaOk = ($vTaxa <= 0 || $vTaxa >= $minimoBoleto);

            if ($prodOk && $taxaOk) {
                $opcoes[] = [
                    'parcelas' => $i,
                    'valor_parcela_produtos' => $vProd,
                    'valor_parcela_taxas' => $vTaxa,
                    'valor_parcela_total' => $vTotal,
                    'total' => $total
                ];
            }
        }

        // Garantir que pelo menos 1x esteja disponível
        if (empty($opcoes)) {
            $opcoes[] = [
                'parcelas' => 1,
                'valor_parcela_produtos' => $totalProdutos,
                'valor_parcela_taxas' => $totalTaxas,
                'valor_parcela_total' => $total,
                'total' => $total
            ];
        }

        return $opcoes;
    }

    /**
     * Cria carnê a partir do checkout
     */
    public function criarCarne($pedidoId, $clienteId, $totalProdutos, $totalTaxas, $qtdParcelas, $dadosCliente = []) {
        $dados = [
            'pedido_id' => $pedidoId,
            'cliente_id' => $clienteId,
            'total_produtos' => $totalProdutos,
            'total_taxas' => $totalTaxas,
            'total_geral' => round($totalProdutos + $totalTaxas, 2),
            'quantidade_parcelas' => $qtdParcelas,
            'status' => 'aguardando_primeira_parcela',
            'termos_aceitos' => 1,
            'termos_aceitos_em' => date('Y-m-d H:i:s')
        ];

        $carneId = $this->carneModel->criarComParcelas($dados, $qtdParcelas);

        // Gerar boletos da primeira parcela automaticamente
        try {
            $parcelas = $this->carneModel->getParcelas($carneId);
            if (!empty($parcelas[0])) {
                $this->gerarBoletosParcela($parcelas[0], $pedidoId, $dadosCliente);
            }
        } catch (\Exception $e) {
            // Log do erro mas não falha a criação do carnê
            error_log('[CARNE] Erro ao gerar boletos da 1ª parcela: ' . $e->getMessage());
        }

        $this->dispararNotificacao($carneId, null, 'carne_criado');
        return $carneId;
    }

    /**
     * Gera os dois boletos de uma parcela (Câmbio Real + Appmax)
     */
    public function gerarBoletosParcela($parcela, $pedidoId, $dadosCliente = []) {
        $parcelaId = $parcela['id'];
        $clientData = $this->buildClientData($dadosCliente, $parcela['carne_id'] ?? 0);
        $descBase = "Carnê Braziliana - Pedido #{$pedidoId} - Parcela {$parcela['numero_parcela']}";

        // Primeira parcela: PIX. Demais: Boleto.
        $isPrimeira = ((int) ($parcela['numero_parcela'] ?? 0) === 1);

        if ($isPrimeira) {
            $this->gerarPixParcela($parcela, $pedidoId, $clientData, $descBase);
        } else {
            $this->gerarBoletoParcela($parcela, $pedidoId, $clientData, $descBase);
        }
    }

    /**
     * Gera PIX para uma parcela (Câmbio Real + Appmax)
     */
    public function gerarPixParcela($parcela, $pedidoId, $clientData, $descBase) {
        $paymentService = new PaymentService();
        $parcelaId = $parcela['id'];

        // Marcar como PIX
        $this->carneModel->atualizarParcela($parcelaId, ['metodo_pagamento' => 'pix']);

        // 1. PIX Produtos via Câmbio Real
        if ($parcela['valor_produtos'] > 0) {
            try {
                // Buscar taxa de câmbio real do sistema
                $taxaConv = 5.85;
                try {
                    $db = \Config\Database::getConnection();
                    $stTx = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'usd_brl_rate' LIMIT 1");
                    $stTx->execute();
                    $v = (float) str_replace(',', '.', (string) ($stTx->fetchColumn() ?: '0'));
                    if ($v > 1.01) $taxaConv = $v;
                } catch (\Exception $e) {}

                $valorBrl = (float) $parcela['valor_produtos'];
                $valorUsd = round($valorBrl / $taxaConv, 2);

                // Câmbio Real exige mínimo USD 1.00
                if ($valorUsd < 1.00) {
                    error_log("[CARNE] PIX CR: valor USD {$valorUsd} abaixo do mínimo. Pulando.");
                } else {
                    $crResult = $paymentService->createCambioRealPixPaymentProduto(
                        (int) $pedidoId,
                        $valorUsd,
                        $valorBrl,
                        $descBase . ' - Produtos',
                        $clientData
                    );

                if (!empty($crResult['success'])) {
                    $pix = $crResult['pix'] ?? [];
                    $pixPayload = $pix['payload'] ?? '';
                    $pixQrcode = $pix['encodedImage'] ?? '';

                    // Se não veio nos campos padrão, buscar no raw
                    if ((empty($pixPayload) || empty($pixQrcode)) && !empty($crResult['raw'])) {
                        $raw = $crResult['raw'];
                        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
                        $tx = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];

                        // Payload (copia e cola)
                        if (empty($pixPayload)) {
                            foreach ([$tx['number']??'',$tx['pix_code']??'',$tx['pix_payload']??'',$tx['emv']??'',$tx['copy_paste']??'',$data['pix_code']??'',$data['number']??'',$data['emv']??''] as $c) {
                                if (!empty($c) && is_string($c) && strlen($c) > 20) { $pixPayload = trim($c); break; }
                            }
                        }

                        // QR Code image (pode ser SVG base64 ou PNG base64)
                        if (empty($pixQrcode)) {
                            foreach ([$tx['barcode']??'',$tx['qr_code']??'',$tx['qrcode']??'',$tx['qr_code_base64']??'',$data['barcode']??'',$data['qr_code']??''] as $c) {
                                if (!empty($c) && is_string($c) && strlen($c) > 50) {
                                    // Remover prefixo data:image se houver
                                    $pixQrcode = preg_replace('#^data:image/[^;]+;base64,#', '', trim($c));
                                    break;
                                }
                            }
                        }
                    }

                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_produtos_url' => $crResult['invoice_url'] ?? '',
                        'boleto_produtos_id_externo' => $crResult['payment_id'] ?? '',
                        'pix_produtos_qrcode' => $pixQrcode,
                        'pix_produtos_payload' => $pixPayload,
                        'pix_produtos_expiracao' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                    ]);

                    error_log('[CARNE] PIX CR: id=' . ($crResult['payment_id'] ?? '') . ' payload_len=' . strlen($pixPayload) . ' qr_len=' . strlen($pixQrcode));
                } else {
                    error_log('[CARNE] Erro PIX CR: ' . ($crResult['error'] ?? 'desconhecido'));
                    if (!empty($crResult['raw'])) {
                        error_log('[CARNE] PIX CR raw: ' . substr(json_encode($crResult['raw']), 0, 1000));
                    }
                }
                } // fecha else do mínimo USD
            } catch (\Exception $e) {
                error_log('[CARNE] Exception PIX CR: ' . $e->getMessage());
            }
        }

        // 2. PIX Taxas via Appmax
        if ($parcela['valor_taxas'] > 0) {
            try {
                $appmaxDados = [
                    'billingType' => 'PIX',
                    'forma_pagamento' => 'pix',
                    'customer_name' => $clientData['name'] ?? '',
                    'customer_email' => $clientData['email'] ?? '',
                    'customer_document' => $clientData['document'] ?? '',
                    'customer_phone' => $clientData['phone'] ?? '',
                    'customer_zipcode' => $clientData['address']['zip_code'] ?? '',
                    'customer_address' => $clientData['address']['street'] ?? '',
                    'customer_address_number' => $clientData['address']['number'] ?? '',
                    'customer_province' => $clientData['address']['district'] ?? '',
                    'customer_city' => $clientData['address']['city'] ?? '',
                    'customer_state' => $clientData['address']['state'] ?? '',
                    'products' => [[
                        'sku' => 'CARNE_TAXA_PIX_' . $pedidoId . '_' . $parcela['numero_parcela'],
                        'name' => $descBase . ' - Taxas',
                        'quantity' => 1,
                        'unit_value' => (int) round($parcela['valor_taxas'] * 100),
                        'type' => 'service',
                    ]],
                    'products_value_cents' => (int) round($parcela['valor_taxas'] * 100),
                    'shipping_value_cents' => 0,
                    'discount_value_cents' => 0,
                ];

                $appmaxResult = $paymentService->processarPagamento(
                    $appmaxDados,
                    (float) $parcela['valor_taxas'],
                    'BRL',
                    $descBase . ' - Taxas'
                );

                if (!empty($appmaxResult['success'])) {
                    $pix = $appmaxResult['pix'] ?? [];
                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_taxas_url' => $appmaxResult['invoiceUrl'] ?? '',
                        'boleto_taxas_id_externo' => $appmaxResult['payment_id'] ?? '',
                        'pix_taxas_qrcode' => $pix['encodedImage'] ?? '',
                        'pix_taxas_payload' => $pix['payload'] ?? '',
                        'pix_taxas_expiracao' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                    ]);
                    error_log('[CARNE] PIX Appmax gerado: id=' . ($appmaxResult['payment_id'] ?? ''));
                } else {
                    error_log('[CARNE] Erro PIX Appmax: ' . ($appmaxResult['error'] ?? 'desconhecido'));
                }
            } catch (\Exception $e) {
                error_log('[CARNE] Exception PIX Appmax: ' . $e->getMessage());
            }
        }
    }

    /**
     * Gera boletos para uma parcela (Câmbio Real + Appmax)
     */
    private function gerarBoletoParcela($parcela, $pedidoId, $clientData, $descBase) {
        $paymentService = new PaymentService();
        $parcelaId = $parcela['id'];

        $this->carneModel->atualizarParcela($parcelaId, ['metodo_pagamento' => 'boleto']);

        // 1. Boleto Produtos via Câmbio Real
        if ($parcela['valor_produtos'] > 0) {
            $minBrl = 6.0;
            if ($parcela['valor_produtos'] < $minBrl) {
                error_log("[CARNE] Valor produtos R$ {$parcela['valor_produtos']} abaixo do mínimo Câmbio Real.");
            } else {
                try {
                    $crResult = $paymentService->createCambioRealDirectPaymentProdutoBoleto(
                        (int) $pedidoId, (float) $parcela['valor_produtos'],
                        $descBase . ' - Produtos', $clientData
                    );
                    if (!empty($crResult['success'])) {
                        $crUrl = $crResult['bank_slip_url'] ?? ($crResult['invoice_url'] ?? '');
                        if (empty($crUrl) && !empty($crResult['raw'])) {
                            $raw = $crResult['raw'];
                            $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
                            $tx = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];
                            foreach ([$tx['ticket_url']??'',$data['ticket_url']??'',$data['url']??'',$raw['url']??''] as $c) {
                                if (!empty($c) && strpos($c,'http')===0) { $crUrl=$c; break; }
                            }
                        }
                        $this->carneModel->atualizarParcela($parcelaId, [
                            'boleto_produtos_url' => $crUrl,
                            'boleto_produtos_codigo' => $crResult['digitable_line'] ?? '',
                            'boleto_produtos_id_externo' => $crResult['payment_id'] ?? '',
                        ]);
                    } else {
                        error_log('[CARNE] Erro CR boleto: ' . ($crResult['error'] ?? ''));
                    }
                } catch (\Exception $e) {
                    error_log('[CARNE] Exception CR boleto: ' . $e->getMessage());
                }
            }
        }

        // 2. Boleto Taxas via Appmax
        if ($parcela['valor_taxas'] > 0) {
            try {
                $appmaxDados = [
                    'billingType' => 'BOLETO',
                    'customer_name' => $clientData['name'] ?? '',
                    'customer_email' => $clientData['email'] ?? '',
                    'customer_document' => $clientData['document'] ?? '',
                    'customer_phone' => $clientData['phone'] ?? '',
                    'customer_zipcode' => $clientData['address']['zip_code'] ?? '',
                    'customer_address' => $clientData['address']['street'] ?? '',
                    'customer_address_number' => $clientData['address']['number'] ?? '',
                    'customer_province' => $clientData['address']['district'] ?? '',
                    'customer_city' => $clientData['address']['city'] ?? '',
                    'customer_state' => $clientData['address']['state'] ?? '',
                    'products' => [[
                        'sku' => 'CARNE_TAXA_' . $pedidoId . '_' . $parcela['numero_parcela'],
                        'name' => $descBase . ' - Taxas', 'quantity' => 1,
                        'unit_value' => (int) round($parcela['valor_taxas'] * 100), 'type' => 'service',
                    ]],
                    'products_value_cents' => (int) round($parcela['valor_taxas'] * 100),
                    'shipping_value_cents' => 0, 'discount_value_cents' => 0,
                ];
                $appmaxResult = $paymentService->processarPagamento($appmaxDados, (float) $parcela['valor_taxas'], 'BRL', $descBase . ' - Taxas');
                if (!empty($appmaxResult['success'])) {
                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_taxas_url' => $appmaxResult['bankSlipUrl'] ?? ($appmaxResult['invoiceUrl'] ?? ''),
                        'boleto_taxas_codigo' => $appmaxResult['digitableLine'] ?? '',
                        'boleto_taxas_id_externo' => $appmaxResult['payment_id'] ?? '',
                    ]);
                }
            } catch (\Exception $e) {
                error_log('[CARNE] Exception Appmax boleto: ' . $e->getMessage());
            }
        }
    }

    /**
     * Monta dados do cliente para os gateways
     */
    public function buildClientData($dadosCliente, $carneId = 0) {
        $clientData = [
            'name' => $dadosCliente['nome'] ?? '', 'email' => $dadosCliente['email'] ?? '',
            'document' => $dadosCliente['documento'] ?? '', 'birth_date' => $dadosCliente['data_nascimento'] ?? '',
            'phone' => $dadosCliente['telefone'] ?? '', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'address' => [
                'state' => $dadosCliente['estado'] ?? '', 'city' => $dadosCliente['cidade'] ?? '',
                'zip_code' => $dadosCliente['cep'] ?? '', 'district' => $dadosCliente['bairro'] ?? '',
                'street' => $dadosCliente['endereco'] ?? '', 'number' => $dadosCliente['numero'] ?? '',
            ],
        ];
        if (empty($clientData['name']) && $carneId > 0) {
            try {
                $stmt = $this->db->prepare("
                    SELECT u.nome, u.email, u.documento, u.data_nascimento, u.telefone, u.celular,
                           e.estado, e.cidade, e.cep, e.bairro, e.endereco, e.numero
                    FROM carnes c JOIN usuarios u ON c.cliente_id = u.id
                    LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
                    WHERE c.id = :cid LIMIT 1
                ");
                $stmt->execute([':cid' => $carneId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $clientData = [
                        'name' => $row['nome'] ?? '', 'email' => $row['email'] ?? '',
                        'document' => $row['documento'] ?? '', 'birth_date' => $row['data_nascimento'] ?? '',
                        'phone' => $row['telefone'] ?? ($row['celular'] ?? ''), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        'address' => ['state'=>$row['estado']??'','city'=>$row['cidade']??'','zip_code'=>$row['cep']??'','district'=>$row['bairro']??'','street'=>$row['endereco']??'','number'=>$row['numero']??''],
                    ];
                }
            } catch (\Exception $e) {}
        }
        return $clientData;
    }

    /**
     * Processa pagamento de boleto via webhook
     */
    public function processarPagamentoBoleto($idExterno, $tipo) {
        // tipo: 'produtos' (Câmbio Real) ou 'taxas' (Appmax)
        $campo = "boleto_{$tipo}_id_externo";
        $stmt = $this->db->prepare("SELECT * FROM carne_parcelas WHERE {$campo} = :ext");
        $stmt->execute([':ext' => $idExterno]);
        $parcela = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$parcela) return false;

        $this->carneModel->registrarPagamentoBoleto($parcela['id'], $tipo);
        $this->dispararNotificacao($parcela['carne_id'], $parcela['id'], 'pagamento_confirmado');
        return true;
    }

    /**
     * Rotina do cron - processa parcelas
     */
    public function processarCron() {
        $hoje = date('Y-m-d');
        $resultados = ['vencidas' => 0, 'geradas' => 0, 'notificadas' => 0, 'quitados' => 0, 'avisos_cancelamento' => 0, 'cancelados' => 0];

        // 1. Marcar parcelas vencidas
        $stmt = $this->db->prepare("
            UPDATE carne_parcelas SET status = 'vencida'
            WHERE status = 'aguardando_pagamento' AND vencimento < :hoje
        ");
        $stmt->execute([':hoje' => $hoje]);
        $resultados['vencidas'] = $stmt->rowCount();

        // 2. Marcar em atraso (vencidas há mais de 7 dias)
        $stmt = $this->db->prepare("
            UPDATE carne_parcelas SET status = 'em_atraso'
            WHERE status = 'vencida' AND vencimento < DATE_SUB(:hoje, INTERVAL 7 DAY)
        ");
        $stmt->execute([':hoje' => $hoje]);

        // 3. Gerar próximas parcelas (ativar pendentes cujo vencimento se aproxima)
        $proximoVenc = date('Y-m-d', strtotime('+7 days'));
        $stmt = $this->db->prepare("
            UPDATE carne_parcelas SET status = 'aguardando_pagamento'
            WHERE status = 'pendente' AND vencimento <= :prox
        ");
        $stmt->execute([':prox' => $proximoVenc]);
        $resultados['geradas'] = $stmt->rowCount();

        // 4. Atualizar status dos carnês com atraso
        $stmt = $this->db->query("SELECT DISTINCT carne_id FROM carne_parcelas WHERE status IN ('vencida','em_atraso')");
        $carnesComAtraso = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($carnesComAtraso as $cid) {
            // Não sobrescrever se já está em aviso_cancelamento ou cancelado
            $st = $this->db->prepare("SELECT status FROM carnes WHERE id = ? LIMIT 1");
            $st->execute([$cid]);
            $statusAtual = $st->fetchColumn();
            if (!in_array($statusAtual, ['aviso_cancelamento', 'cancelado', 'quitado', 'liberado_envio', 'encerrado'])) {
                $this->carneModel->update($cid, ['status' => 'com_atraso']);
            }
        }

        // 5. Verificar carnês quitados
        $stmt = $this->db->query("
            SELECT c.id FROM carnes c
            WHERE c.status NOT IN ('quitado','encerrado','liberado_envio','cancelado')
            AND NOT EXISTS (
                SELECT 1 FROM carne_parcelas cp WHERE cp.carne_id = c.id AND cp.status != 'paga'
            )
        ");
        $carnesQuitados = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($carnesQuitados as $cid) {
            $this->carneModel->update($cid, ['status' => 'quitado', 'envio_liberado' => 1]);
            $this->carneModel->registrarHistorico($cid, null, 'carne_quitado', 'Carnê quitado via cron');
            $this->dispararNotificacao($cid, null, 'carne_quitado');
            $resultados['quitados']++;
        }

        // 6. Notificar parcelas próximas do vencimento (3 dias)
        $tresDias = date('Y-m-d', strtotime('+3 days'));
        $stmt = $this->db->prepare("
            SELECT cp.*, c.cliente_id FROM carne_parcelas cp
            JOIN carnes c ON cp.carne_id = c.id
            WHERE cp.status = 'aguardando_pagamento' AND cp.vencimento = :data
        ");
        $stmt->execute([':data' => $tresDias]);
        $proximasVencer = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($proximasVencer as $p) {
            $this->dispararNotificacao($p['carne_id'], $p['id'], 'parcela_proxima_vencimento');
            $resultados['notificadas']++;
        }

        // 7. CANCELAMENTO POR ABANDONO
        $mesesAtraso = 2;
        $diasAviso = 7;
        try {
            $st = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('carne_meses_atraso_cancelamento','carne_dias_aviso_cancelamento')");
            $st->execute();
            $cfgs = $st->fetchAll(\PDO::FETCH_KEY_PAIR);
            $mesesAtraso = (int) ($cfgs['carne_meses_atraso_cancelamento'] ?? 2);
            $diasAviso = (int) ($cfgs['carne_dias_aviso_cancelamento'] ?? 7);
            if ($mesesAtraso < 1) $mesesAtraso = 2;
            if ($diasAviso < 1) $diasAviso = 7;
        } catch (\Exception $e) {}

        // 7a. Enviar aviso de cancelamento para carnês com X meses de atraso
        $stmt = $this->db->prepare("
            SELECT c.id FROM carnes c
            WHERE c.status IN ('com_atraso','inadimplente')
            AND c.aviso_cancelamento_em IS NULL
            AND EXISTS (
                SELECT 1 FROM carne_parcelas cp 
                WHERE cp.carne_id = c.id AND cp.status IN ('vencida','em_atraso')
                AND cp.vencimento < DATE_SUB(:hoje, INTERVAL :meses MONTH)
            )
        ");
        $stmt->execute([':hoje' => $hoje, ':meses' => $mesesAtraso]);
        $carnesParaAviso = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($carnesParaAviso as $cid) {
            $this->carneModel->update($cid, [
                'status' => 'aviso_cancelamento',
                'aviso_cancelamento_em' => date('Y-m-d H:i:s')
            ]);
            $this->carneModel->registrarHistorico($cid, null, 'aviso_cancelamento',
                "Aviso de cancelamento enviado. O cliente tem {$diasAviso} dias para regularizar.");
            $this->dispararNotificacao($cid, null, 'aviso_cancelamento');
            $resultados['avisos_cancelamento']++;
        }

        // 7b. Cancelar carnês cujo aviso expirou (X dias após o aviso)
        $stmt = $this->db->prepare("
            SELECT c.id FROM carnes c
            WHERE c.status = 'aviso_cancelamento'
            AND c.aviso_cancelamento_em IS NOT NULL
            AND c.aviso_cancelamento_em < DATE_SUB(:hoje, INTERVAL :dias DAY)
        ");
        $stmt->execute([':hoje' => $hoje . ' 23:59:59', ':dias' => $diasAviso]);
        $carnesParaCancelar = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($carnesParaCancelar as $cid) {
            $this->carneModel->update($cid, [
                'status' => 'cancelado',
                'cancelado_em' => date('Y-m-d H:i:s'),
                'motivo_cancelamento' => "Cancelado automaticamente por inadimplência ({$mesesAtraso} meses sem pagamento)"
            ]);
            $this->carneModel->registrarHistorico($cid, null, 'carne_cancelado',
                "Carnê cancelado por inadimplência após aviso de {$diasAviso} dias.");
            $this->dispararNotificacao($cid, null, 'carne_cancelado');
            $resultados['cancelados']++;
        }

        return $resultados;
    }

    /**
     * Dispara notificação (email + webhook)
     */
    public function dispararNotificacao($carneId, $parcelaId, $evento) {
        $carne = $this->carneModel->getCompleto($carneId);
        if (!$carne) return;

        $parcela = $parcelaId ? $this->carneModel->getParcela($parcelaId) : null;

        $payload = [
            'evento' => $evento,
            'carne_id' => $carneId,
            'pedido_id' => $carne['pedido_id'],
            'cliente' => $carne['cliente_nome'],
            'status' => $carne['status'],
            'parcela' => $parcela
        ];

        // Webhook
        $this->enviarWebhook($carneId, $parcelaId, $evento, $payload);

        // Email (registrar para envio)
        $this->registrarNotificacaoEmail($carneId, $parcelaId, $evento, $carne['cliente_email'], $payload);
    }

    private function enviarWebhook($carneId, $parcelaId, $evento, $payload) {
        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_webhook_ativo'");
        $stmt->execute();
        $ativo = $stmt->fetchColumn();
        if (!$ativo) return;

        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_webhook_url'");
        $stmt->execute();
        $url = $stmt->fetchColumn();
        if (empty($url)) return;

        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_eventos_webhook'");
        $stmt->execute();
        $eventos = explode(',', $stmt->fetchColumn() ?: '');
        if (!in_array($evento, $eventos)) return;

        $status = 'pendente';
        $erro = null;

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $status = ($httpCode >= 200 && $httpCode < 300) ? 'enviado' : 'erro';
            if ($status === 'erro') $erro = "HTTP {$httpCode}: {$response}";
        } catch (\Exception $e) {
            $status = 'erro';
            $erro = $e->getMessage();
        }

        $stmt = $this->db->prepare("
            INSERT INTO carne_notificacoes (carne_id, parcela_id, evento, canal, payload, status, erro_mensagem)
            VALUES (:cid, :pid, :ev, 'webhook', :pay, :st, :err)
        ");
        $stmt->execute([
            ':cid' => $carneId, ':pid' => $parcelaId, ':ev' => $evento,
            ':pay' => json_encode($payload), ':st' => $status, ':err' => $erro
        ]);
    }

    private function registrarNotificacaoEmail($carneId, $parcelaId, $evento, $email, $payload) {
        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_email_ativo'");
        $stmt->execute();
        if (!$stmt->fetchColumn()) return;

        $stmt = $this->db->prepare("
            INSERT INTO carne_notificacoes (carne_id, parcela_id, evento, canal, destinatario, payload, status)
            VALUES (:cid, :pid, :ev, 'email', :dest, :pay, 'pendente')
        ");
        $stmt->execute([
            ':cid' => $carneId, ':pid' => $parcelaId, ':ev' => $evento,
            ':dest' => $email, ':pay' => json_encode($payload)
        ]);
    }

    /**
     * Gera crédito em carteira para produto indisponível
     */
    public function gerarCreditoCarteira($carneId, $valor, $observacao, $adminId) {
        $carne = $this->carneModel->find($carneId);
        if (!$carne) return false;

        // Atualizar compra interna
        $stmt = $this->db->prepare("
            UPDATE carne_compras_internas SET 
                status = 'produto_indisponivel', produto_indisponivel = 1,
                acao_indisponibilidade = 'credito_carteira', valor_credito = :val, observacoes = :obs
            WHERE carne_id = :cid
        ");
        $stmt->execute([':val' => $valor, ':obs' => $observacao, ':cid' => $carneId]);

        $this->carneModel->registrarHistorico($carneId, null, 'credito_carteira',
            "Crédito de R$ " . number_format($valor, 2, ',', '.') . " gerado na carteira", null, $adminId);

        return true;
    }

    /**
     * Verifica se carnê está disponível para o contexto do checkout
     */
    public function isCarneDisponivel($moeda = 'BRL', $paisEnvio = 'BR') {
        try {
            if ($moeda !== 'BRL' || $paisEnvio !== 'BR') return false;

            $stmt = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('carne_ativo', 'carne_somente_admin')");
            $stmt->execute();
            $configs = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $ativo = (string) ($configs['carne_ativo'] ?? '0');
            $somenteAdmin = (string) ($configs['carne_somente_admin'] ?? '0');

            $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
            if ($perfil === 'administrator' || $perfil === 'administrador') $perfil = 'admin';
            $isAdmin = ($perfil === 'admin');

            // Modo teste ligado: só admin vê (independente do toggle principal)
            if ($somenteAdmin === '1') {
                return $isAdmin;
            }

            // Modo normal: respeita o toggle principal
            return ($ativo === '1');
        } catch (\Exception $e) {
            return false;
        }
    }
}
