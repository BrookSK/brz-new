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

        // Mínimo por boleto: R$ 20,00
        $minimoBoleto = 20.00;

        // Carnê começa em 2x (1x não é carnê)
        for ($i = 2; $i <= $maxParcelas; $i++) {
            $vProd = round($totalProdutos / $i, 2);
            $vTaxa = round($totalTaxas / $i, 2);
            $vTotal = round($total / $i, 2);

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

        return $opcoes;
    }

    /**
     * Calcula o valor mínimo necessário para ter pelo menos 2x no carnê
     * Leva em conta o mínimo por boleto (R$20) para produtos e taxas separadamente
     */
    public function calcularMinimoParcelamento($totalProdutos, $totalTaxas): float {
        $minimoBoleto = 20.00;
        // Para 2x: cada boleto precisa de >= R$20
        // mínimo_produtos = 2 * 20 = 40 (se produtos > 0)
        // mínimo_taxas    = 2 * 20 = 40 (se taxas > 0)
        $minProd = ($totalProdutos > 0) ? ($minimoBoleto * 2) : 0.0;
        $minTaxa = ($totalTaxas > 0)   ? ($minimoBoleto * 2) : 0.0;
        return $minProd + $minTaxa;
    }

    /**
     * Cria carnê a partir do checkout
     */
    public function criarCarne($pedidoId, $clienteId, $totalProdutos, $totalTaxas, $qtdParcelas, $dadosCliente = []) {
        $this->carneModel->registrarLog(null, (int) $pedidoId, null, 'carne_criado', "Iniciando criação de carnê para pedido #{$pedidoId}", json_encode(['cliente_id' => $clienteId, 'total_produtos' => $totalProdutos, 'total_taxas' => $totalTaxas, 'parcelas' => $qtdParcelas]));

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
            $this->carneModel->registrarLog($carneId, (int) $pedidoId, null, 'carne_erro', "Erro ao gerar boletos da 1ª parcela", $e->getMessage());
        }

        $this->carneModel->registrarLog($carneId, (int) $pedidoId, null, 'carne_criado', "Carnê #{$carneId} criado com sucesso para pedido #{$pedidoId}", json_encode(['carne_id' => $carneId, 'parcelas' => $qtdParcelas, 'total' => round($totalProdutos + $totalTaxas, 2)]));
        $this->dispararNotificacao($carneId, null, 'carne_criado');
        return $carneId;
    }

    /**
     * Gera os dois boletos de uma parcela (Câmbio Real + Câmbio Real Taxas)
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
     * Gera PIX para uma parcela (Câmbio Real + Câmbio Real Taxas)
     */
    public function gerarPixParcela($parcela, $pedidoId, $clientData, $descBase) {
        $paymentService = new PaymentService();
        $parcelaId = $parcela['id'];
        $numeroParcela = (int) ($parcela['numero_parcela'] ?? 1);

        // Determinar expiração: 1ª parcela = 0 dias (expira hoje), demais = 0 dias (expira no dia do vencimento)
        $dueDateDays = 0; // Expira no mesmo dia (23:59)

        // Marcar como PIX
        $this->carneModel->atualizarParcela($parcelaId, ['metodo_pagamento' => 'pix']);

        // Calcular data de expiração para salvar na parcela
        if ($numeroParcela === 1) {
            // 1ª parcela: 30 minutos
            $minutosExpiracao = 30;
            try {
                $db = \Config\Database::getConnection();
                $stCfg = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_primeira_parcela_minutos' LIMIT 1");
                $stCfg->execute();
                $cfgMin = (int) ($stCfg->fetchColumn() ?: 30);
                if ($cfgMin > 0) $minutosExpiracao = $cfgMin;
            } catch (\Exception $e) {}
            $expiraEm = date('Y-m-d H:i:s', strtotime("+{$minutosExpiracao} minutes"));

            // Salvar no carnê para o cron verificar
            try {
                $db = \Config\Database::getConnection();
                $db->prepare("UPDATE carnes SET primeira_parcela_expira_em = ? WHERE id = ? AND primeira_parcela_expira_em IS NULL")
                    ->execute([$expiraEm, $parcela['carne_id']]);
            } catch (\Exception $e) {}
        } else {
            // Demais parcelas: expira às 23:59 do dia do vencimento
            $vencimento = $parcela['vencimento'] ?? date('Y-m-d');
            $expiraEm = $vencimento . ' 23:59:59';
        }

        // Salvar expiração na parcela
        $this->carneModel->atualizarParcela($parcelaId, ['expira_em' => $expiraEm]);

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
                        $clientData,
                        null,
                        null,
                        $dueDateDays
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
                        'pix_produtos_expiracao' => $expiraEm,
                    ]);

                    $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'pix_gerado', "PIX Produtos gerado para parcela #{$parcelaId}", json_encode(['payment_id' => $crResult['payment_id'] ?? '', 'valor_brl' => $valorBrl]));
                    error_log('[CARNE] PIX CR: id=' . ($crResult['payment_id'] ?? '') . ' payload_len=' . strlen($pixPayload) . ' qr_len=' . strlen($pixQrcode));
                } else {
                    $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'pix_erro', "Erro ao gerar PIX Produtos para parcela #{$parcelaId}", $crResult['error'] ?? 'desconhecido');
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

        // 2. PIX Taxas via Câmbio Real Taxas
        if ($parcela['valor_taxas'] > 0) {
            try {
                $result = $paymentService->createCambioRealTaxasPixPayment(
                    (int) $pedidoId,
                    (float) $parcela['valor_taxas'],
                    $descBase . ' - Taxas',
                    $clientData,
                    $dueDateDays
                );

                if (!empty($result['success'])) {
                    $pix = $result['pix'] ?? [];
                    $pixTaxasQrcode  = (string) ($pix['encodedImage'] ?? '');
                    $pixTaxasPayload = (string) ($pix['payload'] ?? '');

                    // Normalizar QR code: remover prefixo data:image se presente
                    // A view detecta SVG vs PNG via base64_decode dos primeiros bytes
                    if ($pixTaxasQrcode !== '') {
                        $pixTaxasQrcode = preg_replace('#^data:image/[^;]+;base64,#', '', trim($pixTaxasQrcode));
                    }

                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_taxas_url'        => $result['invoice_url'] ?? '',
                        'boleto_taxas_id_externo' => $result['payment_id'] ?? '',
                        'pix_taxas_qrcode'        => $pixTaxasQrcode,
                        'pix_taxas_payload'       => $pixTaxasPayload,
                        'pix_taxas_expiracao'     => $expiraEm,
                    ]);
                    $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'pix_gerado', "PIX Taxas gerado para parcela #{$parcelaId}", json_encode(['payment_id' => $result['payment_id'] ?? '', 'valor' => $parcela['valor_taxas']]));
                    error_log('[CARNE] PIX Câmbio Real Taxas gerado: id=' . ($result['payment_id'] ?? ''));
                } else {
                    $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'pix_erro', "Erro ao gerar PIX Taxas para parcela #{$parcelaId}", $result['error'] ?? 'desconhecido');
                    error_log('[CARNE] Erro PIX Câmbio Real Taxas: ' . ($result['error'] ?? 'desconhecido'));
                }
            } catch (\Exception $e) {
                error_log('[CARNE] Exception PIX Câmbio Real Taxas: ' . $e->getMessage());
            }
        }
    }
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
                        // Fallback: construir URL a partir do payment_id
                        if (empty($crUrl) && !empty($crResult['payment_id'])) {
                            $crUrl = 'https://payment.cambioreal.com/request/' . $crResult['payment_id'];
                        }
                        $this->carneModel->atualizarParcela($parcelaId, [
                            'boleto_produtos_url' => $crUrl,
                            'boleto_produtos_codigo' => $crResult['digitable_line'] ?? '',
                            'boleto_produtos_id_externo' => $crResult['payment_id'] ?? '',
                        ]);
                        $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'boleto_gerado', "Boleto Produtos gerado para parcela #{$parcelaId}", json_encode(['payment_id' => $crResult['payment_id'] ?? '', 'valor' => $parcela['valor_produtos']]));
                    } else {
                        $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'boleto_erro', "Erro ao gerar Boleto Produtos para parcela #{$parcelaId}", $crResult['error'] ?? '');
                        error_log('[CARNE] Erro CR boleto: ' . ($crResult['error'] ?? ''));
                    }
                } catch (\Exception $e) {
                    error_log('[CARNE] Exception CR boleto: ' . $e->getMessage());
                }
            }
        }

        // 2. Boleto Taxas via Câmbio Real Taxas
        if ($parcela['valor_taxas'] > 0) {
            try {
                $result = $paymentService->createCambioRealTaxasBoletoPayment(
                    (int) $pedidoId,
                    (float) $parcela['valor_taxas'],
                    $descBase . ' - Taxas',
                    $clientData
                );
                if (!empty($result['success'])) {
                    $taxaUrl = $result['bank_slip_url'] ?? ($result['invoice_url'] ?? '');
                    // Fallback: construir URL a partir do payment_id
                    if (empty($taxaUrl) && !empty($result['payment_id'])) {
                        $taxaUrl = 'https://payment.cambioreal.com/request/' . $result['payment_id'];
                    }
                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_taxas_url'        => $taxaUrl,
                        'boleto_taxas_codigo'     => $result['digitable_line'] ?? '',
                        'boleto_taxas_id_externo' => $result['payment_id'] ?? '',
                    ]);
                    $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'boleto_gerado', "Boleto Taxas gerado para parcela #{$parcelaId}", json_encode(['payment_id' => $result['payment_id'] ?? '', 'valor' => $parcela['valor_taxas']]));
                    error_log('[CARNE] Boleto Câmbio Real Taxas gerado: id=' . ($result['payment_id'] ?? ''));
                } else {
                    $this->carneModel->registrarLog($parcela['carne_id'] ?? null, (int) $pedidoId, $parcelaId, 'boleto_erro', "Erro ao gerar Boleto Taxas para parcela #{$parcelaId}", $result['error'] ?? 'desconhecido');
                    error_log('[CARNE] Erro boleto Câmbio Real Taxas: ' . ($result['error'] ?? 'desconhecido'));
                }
            } catch (\Exception $e) {
                error_log('[CARNE] Exception Câmbio Real Taxas boleto: ' . $e->getMessage());
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
                // Buscar dados do cliente diretamente (sem depender da tabela enderecos)
                $stmt = $this->db->prepare("
                    SELECT u.*
                    FROM carnes c JOIN usuarios u ON c.cliente_id = u.id
                    WHERE c.id = :cid LIMIT 1
                ");
                $stmt->execute([':cid' => $carneId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $clientData = [
                        'name' => (string) ($row['nome'] ?? ($row['name'] ?? '')),
                        'email' => (string) ($row['email'] ?? ''),
                        'document' => (string) ($row['documento'] ?? ($row['cpf'] ?? '')),
                        'birth_date' => (string) ($row['data_nascimento'] ?? ($row['birth_date'] ?? '1990-01-01')),
                        'phone' => (string) ($row['telefone'] ?? ($row['celular'] ?? ($row['phone'] ?? ''))),
                        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                        'address' => [
                            'state' => (string) ($row['estado'] ?? ($row['state'] ?? 'SP')),
                            'city' => (string) ($row['cidade'] ?? ($row['city'] ?? 'São Paulo')),
                            'zip_code' => (string) ($row['cep'] ?? ($row['zip_code'] ?? '01001-000')),
                            'district' => (string) ($row['bairro'] ?? ($row['district'] ?? 'Centro')),
                            'street' => (string) ($row['endereco'] ?? ($row['street'] ?? 'Rua Exemplo')),
                            'number' => (string) ($row['numero'] ?? ($row['number'] ?? '1')),
                        ],
                    ];
                    // Tentar buscar endereço da tabela enderecos (se existir)
                    try {
                        $stEnd = $this->db->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC LIMIT 1");
                        $stEnd->execute([(int) $row['id']]);
                        $end = $stEnd->fetch(\PDO::FETCH_ASSOC);
                        if ($end && !empty($end['cep'])) {
                            $clientData['address'] = [
                                'state' => (string) ($end['estado'] ?? ($end['state'] ?? $clientData['address']['state'])),
                                'city' => (string) ($end['cidade'] ?? ($end['city'] ?? $clientData['address']['city'])),
                                'zip_code' => (string) ($end['cep'] ?? ($end['zip_code'] ?? $clientData['address']['zip_code'])),
                                'district' => (string) ($end['bairro'] ?? ($end['district'] ?? $clientData['address']['district'])),
                                'street' => (string) ($end['endereco'] ?? ($end['street'] ?? $clientData['address']['street'])),
                                'number' => (string) ($end['numero'] ?? ($end['number'] ?? $clientData['address']['number'])),
                            ];
                        }
                    } catch (\Exception $e) {
                        // Tabela enderecos pode não existir — usar dados do usuario
                    }
                }
            } catch (\Exception $e) {
                error_log('[CARNE] buildClientData erro: ' . $e->getMessage());
            }
            // Fallback final se ainda vazio
            if (empty($clientData['name'])) $clientData['name'] = 'Cliente';
            if (empty($clientData['address']['street'])) {
                $clientData['address'] = ['state'=>'SP','city'=>'São Paulo','zip_code'=>'01001-000','district'=>'Centro','street'=>'Rua Exemplo','number'=>'1'];
            }
            if (empty($clientData['birth_date'])) $clientData['birth_date'] = '1990-01-01';
        }
        return $clientData;
    }

    /**
     * Processa pagamento de boleto via webhook
     */
    public function processarPagamentoBoleto($idExterno, $tipo) {
        // tipo: 'produtos' (Câmbio Real) ou 'taxas' (Câmbio Real Taxas)
        $campo = "boleto_{$tipo}_id_externo";
        error_log('[CARNE processarPagamentoBoleto] campo=' . $campo . ' idExterno=' . $idExterno);
        $this->carneModel->registrarLog(null, null, null, 'webhook_recebido', "Webhook recebido para {$campo}={$idExterno}", json_encode(['campo' => $campo, 'id_externo' => $idExterno, 'tipo' => $tipo]));

        // Verificar status real no Câmbio Real antes de marcar como pago
        try {
            $paymentService = new \App\Services\PaymentService();
            $crStatus = $paymentService->checkCambioRealPaymentStatus($idExterno);
            if (empty($crStatus['paid'])) {
                $realStatus = $crStatus['status'] ?? 'unknown';
                error_log('[CARNE processarPagamentoBoleto] Pagamento NÃO confirmado no CR. Status real: ' . $realStatus . ' idExterno=' . $idExterno);
                $this->carneModel->registrarLog(null, null, null, 'pagamento_nao_confirmado', "Webhook recebido mas pagamento não confirmado no Câmbio Real. Status: {$realStatus}", json_encode(['campo' => $campo, 'id_externo' => $idExterno, 'status_real' => $realStatus]));
                return false;
            }
        } catch (\Exception $e) {
            // Se não conseguir verificar, continuar com o fluxo normal (fallback)
            error_log('[CARNE processarPagamentoBoleto] Erro ao verificar CR: ' . $e->getMessage() . ' - continuando...');
        }

        $stmt = $this->db->prepare("SELECT * FROM carne_parcelas WHERE {$campo} = :ext");
        $stmt->execute([':ext' => $idExterno]);
        $parcela = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$parcela) {
            error_log('[CARNE processarPagamentoBoleto] Parcela NÃO encontrada para ' . $campo . '=' . $idExterno);
            $this->carneModel->registrarLog(null, null, null, 'pagamento_nao_encontrado', "Parcela não encontrada para {$campo}={$idExterno}", json_encode(['campo' => $campo, 'id_externo' => $idExterno]));
            return false;
        }

        // Verificar se a parcela está cancelada ou se o pedido está cancelado/lixeira
        $parcelaStatus = strtolower(trim((string) ($parcela['status'] ?? '')));
        if ($parcelaStatus === 'cancelada') {
            error_log('[CARNE processarPagamentoBoleto] Parcela #' . $parcela['id'] . ' está cancelada, ignorando webhook');
            return false;
        }

        // Verificar se a parcela expirou (pagamento fora do prazo)
        $expiraEm = trim((string) ($parcela['expira_em'] ?? ''));
        if ($expiraEm !== '' && strtotime($expiraEm) < time()) {
            // Verificar se tem juros (parcela em atraso mas dentro da tolerância)
            $diasAtraso = (int) ($parcela['dias_atraso'] ?? 0);
            if ($diasAtraso > 0 && $parcelaStatus === 'em_atraso') {
                // Parcela em atraso com juros — aceitar pagamento se o valor bater (com juros)
                error_log('[CARNE processarPagamentoBoleto] Parcela #' . $parcela['id'] . ' em atraso (' . $diasAtraso . ' dias), aceitando pagamento com juros');
            } else {
                error_log('[CARNE processarPagamentoBoleto] Parcela #' . $parcela['id'] . ' expirou em ' . $expiraEm . ', ignorando pagamento tardio');
                $this->carneModel->registrarLog($parcela['carne_id'] ?? null, null, $parcela['id'] ?? null, 'pagamento_expirado', "Pagamento ignorado - parcela expirada em {$expiraEm}", json_encode(['parcela_id' => $parcela['id'], 'expira_em' => $expiraEm]));
                return false;
            }
        }
        try {
            $stCarne = $this->db->prepare("SELECT c.pedido_id FROM carnes c WHERE c.id = ? LIMIT 1");
            $stCarne->execute([$parcela['carne_id']]);
            $carneRow = $stCarne->fetch(\PDO::FETCH_ASSOC);
            if ($carneRow && !empty($carneRow['pedido_id'])) {
                $stPed = $this->db->prepare("SELECT status, deleted_at FROM pedidos WHERE id = ? LIMIT 1");
                $stPed->execute([$carneRow['pedido_id']]);
                $pedRow = $stPed->fetch(\PDO::FETCH_ASSOC);
                if ($pedRow) {
                    $pedStatus = strtolower(trim((string) ($pedRow['status'] ?? '')));
                    if ($pedStatus === 'cancelado' || !empty($pedRow['deleted_at'])) {
                        error_log('[CARNE processarPagamentoBoleto] Pedido #' . $carneRow['pedido_id'] . ' está cancelado/lixeira, ignorando webhook para parcela #' . $parcela['id']);
                        $this->carneModel->registrarLog($parcela['carne_id'] ?? null, null, $parcela['id'] ?? null, 'pagamento_ignorado', "Pagamento ignorado - pedido cancelado/lixeira", json_encode(['parcela_id' => $parcela['id'], 'pedido_id' => $carneRow['pedido_id']]));
                        return false;
                    }
                }
            }
        } catch (\Exception $e) {}


        error_log('[CARNE processarPagamentoBoleto] Parcela encontrada: id=' . $parcela['id'] . ' carne_id=' . $parcela['carne_id']);
        $this->carneModel->registrarLog($parcela['carne_id'] ?? null, null, $parcela['id'] ?? null, 'pagamento_confirmado', "Pagamento confirmado para parcela #{$parcela['id']} ({$tipo})", json_encode(['parcela_id' => $parcela['id'], 'carne_id' => $parcela['carne_id'], 'tipo' => $tipo, 'id_externo' => $idExterno]));
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

        // 6b. CANCELAMENTO POR EXPIRAÇÃO DA 1ª PARCELA (30 minutos)
        try {
            $agora = date('Y-m-d H:i:s');
            $stPrimeira = $this->db->prepare("
                SELECT c.id, c.pedido_id FROM carnes c
                WHERE c.status = 'ativo'
                AND c.primeira_parcela_expira_em IS NOT NULL
                AND c.primeira_parcela_expira_em < :agora
                AND NOT EXISTS (
                    SELECT 1 FROM carne_parcelas cp 
                    WHERE cp.carne_id = c.id AND cp.numero_parcela = 1 AND cp.status = 'paga'
                )
            ");
            $stPrimeira->execute([':agora' => $agora]);
            $carnesExpirados = $stPrimeira->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($carnesExpirados as $ce) {
                $cid = (int) $ce['id'];
                $pidCarne = (int) $ce['pedido_id'];

                // Cancelar carnê
                $this->carneModel->update($cid, [
                    'status' => 'cancelado',
                    'cancelado_em' => $agora,
                    'motivo_cancelamento' => 'Primeira parcela não paga dentro do prazo'
                ]);

                // Cancelar parcelas pendentes
                $this->db->prepare("UPDATE carne_parcelas SET status = 'cancelada' WHERE carne_id = ? AND status NOT IN ('paga')")
                    ->execute([$cid]);

                // Cancelar pedido
                if ($pidCarne > 0) {
                    try {
                        $this->db->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = ? AND status NOT IN ('cancelado')")
                            ->execute([$pidCarne]);
                    } catch (\Exception $e) {}
                }

                $this->carneModel->registrarHistorico($cid, null, 'carne_cancelado',
                    'Carnê cancelado: primeira parcela não paga dentro do prazo de 30 minutos.');
                $this->dispararNotificacao($cid, null, 'carne_cancelado');
                $resultados['cancelados']++;
                error_log("[CARNE CRON] Carnê #{$cid} cancelado: 1ª parcela expirou (pedido #{$pidCarne})");
            }
        } catch (\Exception $e) {
            error_log('[CARNE CRON] Erro verificação 1ª parcela: ' . $e->getMessage());
        }

        // 6c. JUROS E CANCELAMENTO POR ATRASO (parcelas seguintes)
        // Regra: venceu → corre juros diário por 7 dias → cancela
        try {
            $diasTolerancia = 7;
            $jurosDiario = 0.033; // 0.033% ao dia
            try {
                $stCfg = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('carne_dias_tolerancia_atraso','carne_juros_diario_percent')");
                $stCfg->execute();
                $cfgs = $stCfg->fetchAll(\PDO::FETCH_KEY_PAIR);
                $diasTolerancia = (int) ($cfgs['carne_dias_tolerancia_atraso'] ?? 7);
                $jurosDiario = (float) ($cfgs['carne_juros_diario_percent'] ?? 0.033);
                if ($diasTolerancia < 1) $diasTolerancia = 7;
                if ($jurosDiario <= 0) $jurosDiario = 0.033;
            } catch (\Exception $e) {}

            // Buscar parcelas vencidas (não pagas, não canceladas, vencimento já passou)
            $stVencidas = $this->db->prepare("
                SELECT cp.*, c.pedido_id, c.id AS carne_id_ref
                FROM carne_parcelas cp
                JOIN carnes c ON cp.carne_id = c.id
                WHERE cp.status IN ('aguardando_pagamento', 'vencida', 'em_atraso')
                AND cp.numero_parcela > 1
                AND cp.vencimento < :hoje
                AND c.status NOT IN ('cancelado', 'quitado', 'encerrado', 'liberado_envio')
            ");
            $stVencidas->execute([':hoje' => $hoje]);
            $parcelasVencidas = $stVencidas->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($parcelasVencidas as $pv) {
                $pvId = (int) $pv['id'];
                $vencimento = $pv['vencimento'];
                $diasAtraso = (int) ((strtotime($hoje) - strtotime($vencimento)) / 86400);
                if ($diasAtraso < 1) $diasAtraso = 1;

                // Calcular juros
                $valorProdOriginal = (float) ($pv['valor_produtos_original'] ?: $pv['valor_produtos']);
                $valorTaxasOriginal = (float) ($pv['valor_taxas_original'] ?: $pv['valor_taxas']);
                $valorBase = $valorProdOriginal + $valorTaxasOriginal;
                $jurosAcumulado = round($valorBase * ($jurosDiario / 100) * $diasAtraso, 2);

                // Atualizar parcela com juros e dias de atraso
                $updateData = [
                    'dias_atraso' => $diasAtraso,
                    'valor_juros' => $jurosAcumulado,
                    'status' => $diasAtraso > $diasTolerancia ? 'cancelada' : 'em_atraso',
                ];

                // Salvar valores originais se ainda não salvos
                if (empty($pv['valor_produtos_original'])) {
                    $updateData['valor_produtos_original'] = $valorProdOriginal;
                }
                if (empty($pv['valor_taxas_original'])) {
                    $updateData['valor_taxas_original'] = $valorTaxasOriginal;
                }

                $this->carneModel->atualizarParcela($pvId, $updateData);

                // Notificar cliente no 1º dia de atraso e no 5º dia (último aviso)
                if ($diasAtraso === 1 || $diasAtraso === 5) {
                    $this->dispararNotificacao((int) $pv['carne_id_ref'], $pvId, 'parcela_em_atraso_juros');
                }

                // Se passou da tolerância, cancelar o carnê inteiro
                if ($diasAtraso > $diasTolerancia) {
                    $cid = (int) $pv['carne_id_ref'];
                    $pidCarne = (int) $pv['pedido_id'];

                    // Verificar se já não foi cancelado
                    $stStatus = $this->db->prepare("SELECT status FROM carnes WHERE id = ? LIMIT 1");
                    $stStatus->execute([$cid]);
                    $statusAtual = (string) $stStatus->fetchColumn();

                    if ($statusAtual !== 'cancelado') {
                        $this->carneModel->update($cid, [
                            'status' => 'cancelado',
                            'cancelado_em' => date('Y-m-d H:i:s'),
                            'motivo_cancelamento' => "Cancelado por atraso de {$diasAtraso} dias na parcela #{$pv['numero_parcela']}"
                        ]);

                        // Cancelar demais parcelas pendentes
                        $this->db->prepare("UPDATE carne_parcelas SET status = 'cancelada' WHERE carne_id = ? AND status NOT IN ('paga','cancelada')")
                            ->execute([$cid]);

                        // Cancelar pedido
                        if ($pidCarne > 0) {
                            try {
                                $this->db->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = ? AND status NOT IN ('cancelado')")
                                    ->execute([$pidCarne]);
                            } catch (\Exception $e) {}
                        }

                        $this->carneModel->registrarHistorico($cid, $pvId, 'carne_cancelado',
                            "Carnê cancelado: parcela #{$pv['numero_parcela']} com {$diasAtraso} dias de atraso (limite: {$diasTolerancia} dias).");
                        $this->dispararNotificacao($cid, null, 'carne_cancelado');
                        $resultados['cancelados']++;
                        error_log("[CARNE CRON] Carnê #{$cid} cancelado: atraso de {$diasAtraso} dias na parcela #{$pv['numero_parcela']}");
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('[CARNE CRON] Erro juros/cancelamento: ' . $e->getMessage());
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

        // Registrar na carne_notificacoes (manter compatibilidade)
        $stmt = $this->db->prepare("
            INSERT INTO carne_notificacoes (carne_id, parcela_id, evento, canal, destinatario, payload, status)
            VALUES (:cid, :pid, :ev, 'email', :dest, :pay, 'pendente')
        ");
        $stmt->execute([
            ':cid' => $carneId, ':pid' => $parcelaId, ':ev' => $evento,
            ':dest' => $email, ':pay' => json_encode($payload)
        ]);

        // Enviar email de verdade via EmailService
        $this->enviarEmailNotificacaoCarne($carneId, $parcelaId, $evento, $email, $payload);
    }

    /**
     * Envia email real de notificação do carnê e registra no email_logs
     */
    private function enviarEmailNotificacaoCarne($carneId, $parcelaId, $evento, $email, $payload) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;

        $carne = is_array($payload) ? $payload : [];
        $clienteNome = $carne['cliente'] ?? 'Cliente';
        $pedidoId = (int) ($carne['pedido_id'] ?? 0);
        $parcela = $carne['parcela'] ?? null;

        // Montar dados do template conforme o evento
        $baseUrl = rtrim(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br'), '/');
        $urlMeuCarne = $baseUrl . '/meu-carne/' . $carneId;

        $templateData = $this->montarDadosTemplateEvento($evento, $carneId, $pedidoId, $clienteNome, $parcela, $urlMeuCarne);
        if (empty($templateData)) return;

        $assunto = $templateData['assunto'];
        $htmlEmail = null;

        // Tentar usar template customizado da tabela email_templates (salvo via Configurações > Criar E-mail)
        try {
            $emailService = new \App\Services\EmailService();
            $templateNome = (strpos($evento, 'carne_') === 0) ? $evento : 'carne_' . $evento;
            $tplDb = $emailService->getTemplate($templateNome);
            if (!empty($tplDb['corpo_html'])) {
                // Renderizar variáveis no template do banco
                $vars = [
                    'cliente_nome' => $clienteNome,
                    'cliente_email' => $email,
                    'carne_id' => $carneId,
                    'pedido_id' => $pedidoId,
                    'url_meu_carne' => $urlMeuCarne,
                    'total_geral' => $carne['total_geral'] ?? '',
                    'quantidade_parcelas' => $carne['quantidade_parcelas'] ?? '',
                ];
                if ($parcela && is_array($parcela)) {
                    $vars['numero_parcela'] = $parcela['numero_parcela'] ?? '';
                    $vars['total_parcelas'] = $carne['quantidade_parcelas'] ?? '';
                    $vars['valor_parcela'] = isset($parcela['valor_total']) ? 'R$ ' . number_format((float) $parcela['valor_total'], 2, ',', '.') : '';
                    $vars['valor_produtos'] = isset($parcela['valor_produtos']) ? 'R$ ' . number_format((float) $parcela['valor_produtos'], 2, ',', '.') : '';
                    $vars['valor_taxas'] = isset($parcela['valor_taxas']) ? 'R$ ' . number_format((float) $parcela['valor_taxas'], 2, ',', '.') : '';
                    $vars['vencimento'] = !empty($parcela['vencimento']) ? date('d/m/Y', strtotime($parcela['vencimento'])) : '';
                    $vars['status_parcela'] = $parcela['status'] ?? '';
                }
                $htmlEmail = $emailService->renderTemplate($tplDb['corpo_html'], $vars);
                if (!empty($tplDb['assunto'])) {
                    $assunto = $emailService->renderTemplate($tplDb['assunto'], $vars);
                }
            }
        } catch (\Exception $e) {
            // Fallback para template PHP padrão
        }

        // Se não tem template customizado, usar o template PHP padrão
        if (empty($htmlEmail)) {
            $titulo = $templateData['titulo'];
            $mensagem = $templateData['mensagem'];
            $detalhes = $templateData['detalhes'] ?? [];
            $alerta = $templateData['alerta'] ?? null;
            $alertaMensagem = $templateData['alertaMensagem'] ?? '';
            $ctaTexto = $templateData['ctaTexto'] ?? 'Ver meu carnê';

            ob_start();
            include __DIR__ . '/../Views/emails/carne-notificacao.php';
            $htmlEmail = ob_get_clean();
        }

        $status = 'enviado';
        $erroMsg = null;

        try {
            $emailService = new \App\Services\EmailService();
            $emailService->send($email, $assunto, $htmlEmail);
        } catch (\Exception $e) {
            $status = 'erro';
            $erroMsg = $e->getMessage();
            error_log('[CARNE EMAIL] Erro ao enviar email ' . $evento . ' para ' . $email . ': ' . $e->getMessage());
        }

        // Atualizar status na carne_notificacoes
        try {
            $this->db->prepare("
                UPDATE carne_notificacoes SET status = :st, erro_mensagem = :err
                WHERE carne_id = :cid AND canal = 'email' AND evento = :ev
                ORDER BY id DESC LIMIT 1
            ")->execute([':st' => $status, ':err' => $erroMsg, ':cid' => $carneId, ':ev' => $evento]);
        } catch (\Exception $e) {}

        // Registrar no email_logs
        try {
            $corpoResumo = mb_substr(strip_tags($templateData['mensagem'] ?? ''), 0, 200);
            $this->db->prepare("
                INSERT INTO email_logs (tipo, destinatario_email, destinatario_nome, assunto, corpo_resumo, status, erro_mensagem, carne_id, parcela_id, pedido_id, created_at)
                VALUES (:tipo, :email, :nome, :assunto, :corpo, :status, :erro, :carne_id, :parcela_id, :pedido_id, NOW())
            ")->execute([
                ':tipo' => (strpos($evento, 'carne_') === 0) ? $evento : 'carne_' . $evento,
                ':email' => $email,
                ':nome' => $clienteNome,
                ':assunto' => $assunto,
                ':corpo' => $corpoResumo,
                ':status' => $status,
                ':erro' => $erroMsg,
                ':carne_id' => $carneId,
                ':parcela_id' => $parcelaId,
                ':pedido_id' => $pedidoId ?: null,
            ]);
        } catch (\Exception $e) {
            error_log('[EMAIL_LOG] Erro ao registrar: ' . $e->getMessage());
        }
    }

    /**
     * Monta os dados do template de email conforme o tipo de evento
     */
    private function montarDadosTemplateEvento($evento, $carneId, $pedidoId, $clienteNome, $parcela, $urlMeuCarne) {
        $detalhes = ['Carnê' => "#$carneId", 'Pedido' => "#$pedidoId"];

        if ($parcela && is_array($parcela)) {
            $numParcela = $parcela['numero_parcela'] ?? '';
            $vencimento = !empty($parcela['vencimento']) ? date('d/m/Y', strtotime($parcela['vencimento'])) : '';
            $valorTotal = isset($parcela['valor_total']) ? 'R$ ' . number_format((float) $parcela['valor_total'], 2, ',', '.') : '';
            if ($numParcela) $detalhes['Parcela'] = $numParcela;
            if ($vencimento) $detalhes['Vencimento'] = $vencimento;
            if ($valorTotal) $detalhes['Valor'] = $valorTotal;
        }

        switch ($evento) {
            case 'carne_criado':
                return [
                    'assunto' => "Seu carnê #{$carneId} foi criado - Braziliana",
                    'titulo' => 'Carnê Criado',
                    'mensagem' => 'Seu carnê foi criado com sucesso! Acesse o link abaixo para visualizar suas parcelas e realizar os pagamentos.',
                    'detalhes' => $detalhes,
                    'alerta' => 'success',
                    'alertaMensagem' => '<strong>✓ Tudo certo!</strong> Seu carnê está ativo e pronto para pagamento.',
                    'ctaTexto' => 'Ver meu carnê e pagar',
                ];

            case 'pagamento_confirmado':
                return [
                    'assunto' => "Pagamento confirmado - Carnê #{$carneId}",
                    'titulo' => 'Pagamento Confirmado',
                    'mensagem' => 'Recebemos a confirmação do pagamento da sua parcela. Obrigado!',
                    'detalhes' => $detalhes,
                    'alerta' => 'success',
                    'alertaMensagem' => '<strong>✓ Pagamento recebido!</strong> Sua parcela foi registrada como paga.',
                    'ctaTexto' => 'Ver meu carnê',
                ];

            case 'parcela_proxima_vencimento':
                return [
                    'assunto' => "Lembrete: parcela próxima do vencimento - Carnê #{$carneId}",
                    'titulo' => 'Lembrete de Vencimento',
                    'mensagem' => 'Sua parcela está próxima do vencimento. Realize o pagamento para manter seu carnê em dia.',
                    'detalhes' => $detalhes,
                    'alerta' => 'warning',
                    'alertaMensagem' => '<strong>⏰ Atenção:</strong> Sua parcela vence em breve. Evite atrasos!',
                    'ctaTexto' => 'Pagar agora',
                ];

            case 'cobranca':
                $statusParcela = $parcela['status'] ?? 'pendente';
                $alertaMsg = ($statusParcela === 'em_atraso' || $statusParcela === 'vencida')
                    ? '<strong>⚠️ Atenção:</strong> Esta parcela está <strong>' . ($statusParcela === 'em_atraso' ? 'em atraso' : 'vencida') . '</strong>. Regularize o pagamento para evitar o cancelamento do seu carnê.'
                    : '<strong>⏰ Lembrete:</strong> Realize o pagamento da sua parcela.';
                return [
                    'assunto' => ($statusParcela === 'em_atraso' || $statusParcela === 'vencida')
                        ? "⚠️ Parcela em atraso - Carnê #{$carneId}"
                        : "Cobrança - Carnê #{$carneId}",
                    'titulo' => 'Cobrança de Parcela',
                    'mensagem' => 'Estamos entrando em contato para lembrar sobre o pagamento da parcela do seu carnê.',
                    'detalhes' => $detalhes,
                    'alerta' => ($statusParcela === 'em_atraso' || $statusParcela === 'vencida') ? 'danger' : 'warning',
                    'alertaMensagem' => $alertaMsg,
                    'ctaTexto' => 'Ver meu carnê e pagar',
                ];

            case 'carne_quitado':
                return [
                    'assunto' => "Parabéns! Carnê #{$carneId} quitado",
                    'titulo' => 'Carnê Quitado',
                    'mensagem' => 'Parabéns! Todas as parcelas do seu carnê foram pagas. Seu produto será preparado para envio.',
                    'detalhes' => $detalhes,
                    'alerta' => 'success',
                    'alertaMensagem' => '<strong>🎉 Parabéns!</strong> Seu carnê está totalmente quitado!',
                    'ctaTexto' => 'Ver meu carnê',
                ];

            case 'envio_liberado':
                return [
                    'assunto' => "Envio liberado - Carnê #{$carneId}",
                    'titulo' => 'Envio Liberado',
                    'mensagem' => 'O envio do seu produto foi liberado! Em breve você receberá as informações de rastreamento.',
                    'detalhes' => $detalhes,
                    'alerta' => 'success',
                    'alertaMensagem' => '<strong>🚚 Boa notícia!</strong> Seu produto está sendo preparado para envio.',
                    'ctaTexto' => 'Ver meu carnê',
                ];

            case 'aviso_cancelamento':
                return [
                    'assunto' => "⚠️ URGENTE: Aviso de cancelamento - Carnê #{$carneId}",
                    'titulo' => 'Aviso de Cancelamento',
                    'mensagem' => 'Seu carnê possui parcelas em atraso há muito tempo. Se o pagamento não for regularizado nos próximos dias, o carnê será cancelado automaticamente.',
                    'detalhes' => $detalhes,
                    'alerta' => 'danger',
                    'alertaMensagem' => '<strong>🚨 URGENTE:</strong> Seu carnê será cancelado se o pagamento não for regularizado. Entre em contato conosco se precisar de ajuda.',
                    'ctaTexto' => 'Regularizar agora',
                ];

            case 'carne_cancelado':
                return [
                    'assunto' => "Carnê #{$carneId} cancelado por inadimplência",
                    'titulo' => 'Carnê Cancelado',
                    'mensagem' => 'Infelizmente seu carnê foi cancelado devido à inadimplência. Entre em contato com nosso suporte se desejar mais informações.',
                    'detalhes' => $detalhes,
                    'alerta' => 'danger',
                    'alertaMensagem' => '<strong>❌ Cancelado:</strong> Seu carnê foi cancelado por falta de pagamento.',
                    'ctaTexto' => 'Entrar em contato',
                ];

            case 'parcela_em_atraso_juros':
                $diasAtraso = (int) ($parcela['dias_atraso'] ?? 0);
                $valorJuros = isset($parcela['valor_juros']) ? 'R$ ' . number_format((float) $parcela['valor_juros'], 2, ',', '.') : '';
                if ($valorJuros) $detalhes['Juros acumulados'] = $valorJuros;
                if ($diasAtraso) $detalhes['Dias em atraso'] = $diasAtraso;
                $detalhes['Prazo máximo'] = '7 dias após vencimento';
                return [
                    'assunto' => "⚠️ Parcela em atraso com juros - Carnê #{$carneId}",
                    'titulo' => 'Parcela em Atraso',
                    'mensagem' => "Sua parcela está em atraso e estão sendo cobrados juros diários. Se o pagamento não for realizado em até 7 dias após o vencimento, o carnê será cancelado automaticamente.",
                    'detalhes' => $detalhes,
                    'alerta' => 'danger',
                    'alertaMensagem' => "<strong>⚠️ Atenção:</strong> Sua parcela está em atraso há {$diasAtraso} dia(s). Juros estão sendo aplicados. Pague o quanto antes para evitar o cancelamento.",
                    'ctaTexto' => 'Pagar agora',
                ];

            default:
                return [
                    'assunto' => "Atualização do Carnê #{$carneId} - Braziliana",
                    'titulo' => 'Atualização do Carnê',
                    'mensagem' => 'Houve uma atualização no seu carnê. Acesse o link abaixo para mais detalhes.',
                    'detalhes' => $detalhes,
                    'alerta' => null,
                    'alertaMensagem' => '',
                    'ctaTexto' => 'Ver meu carnê',
                ];
        }
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
     * Verifica se carnê está ativo nas configurações (sem checar moeda/país — isso é feito pelo JS)
     */
    public function isCarneAtivo(): bool {
        try {
            $stmt = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('carne_ativo', 'carne_somente_admin')");
            $stmt->execute();
            $configs = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $ativo = (string) ($configs['carne_ativo'] ?? '0');
            $somenteAdmin = (string) ($configs['carne_somente_admin'] ?? '0');

            // Modo teste: só admin vê — retorna true para que o JS possa checar o perfil se necessário
            // mas aqui retornamos true mesmo assim (o JS filtra por isBRL+isBR)
            if ($somenteAdmin === '1') {
                $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
                if ($perfil === '') $perfil = strtolower(trim((string) ($_SESSION['usuario_role'] ?? '')));
                if ($perfil === 'administrator' || $perfil === 'administrador') $perfil = 'admin';
                return ($perfil === 'admin');
            }

            return ($ativo === '1');
        } catch (\Exception $e) {
            return false;
        }
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
            if ($perfil === '') $perfil = strtolower(trim((string) ($_SESSION['usuario_role'] ?? '')));
            if ($perfil === 'administrator' || $perfil === 'administrador') $perfil = 'admin';
            $isAdmin = ($perfil === 'admin');

            // Modo teste ligado: só admin vê (independente do toggle principal)
            if ($somenteAdmin === '1') {
                return $isAdmin;
            }

            // Modo normal: carne_ativo=1 significa disponível para TODOS (incluindo admin)
            return ($ativo === '1');
        } catch (\Exception $e) {
            return false;
        }
    }
}
