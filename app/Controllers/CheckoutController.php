<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;
use App\Services\CpfValidator;
use App\Models\Carrinho;
use App\Models\Produto;
use App\Models\Usuario;
use App\Models\Endereco;
use App\Models\PedidoEcommerce;
use App\Models\AssessoriaOrcamento;

// Garantir que as classes sejam carregadas
require_once __DIR__ . '/../Models/Model.php';
require_once __DIR__ . '/../Models/Endereco.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Carrinho.php';
require_once __DIR__ . '/../Models/Produto.php';
require_once __DIR__ . '/../Models/PedidoEcommerce.php';
require_once __DIR__ . '/../Models/AssessoriaOrcamento.php';

class CheckoutController extends Controller {
    private $authService;
    private $paymentService;
    private $carrinhoModel;
    private $usuarioModel;
    private $enderecoModel;
    private $pedidoModel;

    private function gerarCobrancaAppmaxTaxaServicoSplit(int $pedidoId, string $billingType, float $valor, array $usuario, string $descricao, string $componente = 'taxa_servico'): array {
        $billingType = strtoupper(trim($billingType));
        if (!in_array($billingType, ['PIX', 'BOLETO'], true)) {
            $billingType = 'BOLETO';
        }
        $valor = (float) $valor;
        if ($valor <= 0) {
            return ['success' => true, 'skipped' => true];
        }

        $nome = (string) ($usuario['nome'] ?? 'Cliente');
        $email = (string) ($usuario['email'] ?? '');
        $telefone = (string) ($usuario['telefone'] ?? '');
        $documento = (string) ($usuario['documento'] ?? '');

        $productsValueCents = (int) round($valor * 100);
        $products = [
            [
                'sku' => 'TAXA_SERVICO_' . (string) $pedidoId,
                'name' => $descricao,
                'quantity' => 1,
                'unit_value' => $productsValueCents,
                'type' => 'service',
                'freight_type' => 'normal',
            ]
        ];

        $result = $this->paymentService->processarPagamento([
            'billingType' => $billingType,
            'force_gateway' => 'appmax',
            'customer_name' => $nome,
            'customer_email' => $email,
            'customer_phone' => $telefone,
            'customer_document' => $documento,
            'externalReference' => (string) $pedidoId,
            'products' => $products,
            'products_value_cents' => $productsValueCents,
            'shipping_value_cents' => 0,
            'discount_value_cents' => 0,
        ], $valor, 'BRL', $descricao);

        if (empty($result['success'])) {
            return $result;
        }

        $paymentId = (string) ($result['payment_id'] ?? '');
        if ($paymentId === '') {
            return ['success' => false, 'error' => 'AppMax: payment_id não retornado'];
        }

        $pix = (isset($result['pix']) && is_array($result['pix'])) ? $result['pix'] : null;
        $invoiceUrl = (string) ($result['invoiceUrl'] ?? '');
        $bankSlipUrl = (string) ($result['bankSlipUrl'] ?? '');
        $digitableLine = (string) ($result['digitableLine'] ?? '');

        $this->paymentService->registrarPedidoPagamentoSplit([
            'pedido_id' => $pedidoId,
            'componente' => strtolower(trim((string) $componente)) !== '' ? strtolower(trim((string) $componente)) : 'taxa_servico',
            'gateway' => 'appmax',
            'metodo' => strtolower($billingType),
            'moeda' => 'BRL',
            'valor' => $valor,
            'payment_id' => $paymentId,
            'status' => 'pending',
            'invoice_url' => $invoiceUrl,
            'bank_slip_url' => $bankSlipUrl,
            'digitable_line' => $digitableLine,
            'pix_encoded_image' => is_array($pix) ? (string) ($pix['encodedImage'] ?? '') : '',
            'pix_payload' => is_array($pix) ? (string) ($pix['payload'] ?? '') : '',
        ]);

        return $result;
    }

    private function pedidoJaTemSplitPagamentos(int $pedidoId): bool {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) {
            return false;
        }
        try {
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT 1 FROM pedido_pagamentos WHERE pedido_id = ? LIMIT 1');
            $st->execute([$pedidoId]);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function normalizeTelefoneFromCheckout(array $dados): string {
        $tel = (string) ($dados['telefone'] ?? '');
        $tel = trim($tel);
        if ($tel !== '') {
            return $tel;
        }

        $ddi = trim((string) ($dados['telefone_ddi'] ?? ''));
        $numero = trim((string) ($dados['telefone_numero'] ?? ''));
        $ddi = preg_replace('/\D+/', '', $ddi);
        $numero = preg_replace('/\D+/', '', $numero);

        if ($ddi === '' && $numero === '') {
            return '';
        }

        if ($ddi === '0') {
            $ddiOutro = trim((string) ($dados['telefone_ddi_outro'] ?? ''));
            $ddiOutro = preg_replace('/\D+/', '', $ddiOutro);
            if ($ddiOutro !== '') {
                $ddi = $ddiOutro;
            }
        }

        if ($ddi !== '' && $numero !== '') {
            return '+' . $ddi . $numero;
        }

        return $numero;
    }

    private function validarDisponibilidadeCarrinhoNoBanco(array $carrinho): array {
        $db = \Config\Database::getConnection();

        $dbName = null;
        try {
            $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
        } catch (\Throwable $e) {
            $dbName = null;
        }

        $produtoCols = [];
        try {
            $st = $db->query('DESCRIBE produtos');
            $produtoCols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable $e) {
            $produtoCols = [];
        }

        $produtoColAtivo = null;
        foreach (['active', 'ativo'] as $c) {
            if (is_array($produtoCols) && in_array($c, $produtoCols, true)) {
                $produtoColAtivo = $c;
                break;
            }
        }

        $produtoColStatus = null;
        if (is_array($produtoCols) && in_array('status', $produtoCols, true)) {
            $produtoColStatus = 'status';
        }

        $produtoColStock = null;
        foreach (['stock', 'estoque', 'stock_quantity', 'quantidade', 'quantity', 'qtd', 'qty', 'estoque_atual'] as $c) {
            if (is_array($produtoCols) && in_array($c, $produtoCols, true)) {
                $produtoColStock = $c;
                break;
            }
        }

        $produtoColControla = null;
        foreach (['controla_estoque', 'manage_stock'] as $c) {
            if (is_array($produtoCols) && in_array($c, $produtoCols, true)) {
                $produtoColControla = $c;
                break;
            }
        }

        $produtoPkCol = 'id';
        if (is_array($produtoCols) && !in_array('id', $produtoCols, true) && in_array('produto_id', $produtoCols, true)) {
            $produtoPkCol = 'produto_id';
        }

        $produtoLookupCols = [];
        foreach (['id', 'produto_id', 'product_id', 'wp_product_id', 'woocommerce_product_id'] as $c) {
            if (is_array($produtoCols) && in_array($c, $produtoCols, true)) {
                $produtoLookupCols[] = $c;
            }
        }
        if (empty($produtoLookupCols)) {
            $produtoLookupCols = [$produtoPkCol];
        }

        $variacoesTable = null;
        $hasProdutoVariacoes = false;
        foreach (['produto_variacoes', 'produto_variations', 'product_variacoes', 'product_variations', 'variacoes_produto', 'variations'] as $t) {
            try {
                $st = $db->query("SHOW TABLES LIKE " . $db->quote($t));
                $exists = (bool) ($st && $st->fetch());
                if ($exists) {
                    $variacoesTable = $t;
                    $hasProdutoVariacoes = true;
                    break;
                }
            } catch (\Throwable $e) {
            }
        }

        $variacaoCols = [];
        if ($hasProdutoVariacoes && $variacoesTable) {
            try {
                $st = $db->query('DESCRIBE ' . $variacoesTable);
                $variacaoCols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $variacaoCols = [];
            }
        }

        $variacaoColAtivo = null;
        foreach (['active', 'ativo'] as $c) {
            if (is_array($variacaoCols) && in_array($c, $variacaoCols, true)) {
                $variacaoColAtivo = $c;
                break;
            }
        }

        $variacaoColStatus = null;
        if (is_array($variacaoCols) && in_array('status', $variacaoCols, true)) {
            $variacaoColStatus = 'status';
        }

        $variacaoColStock = null;
        foreach (['stock', 'estoque', 'stock_quantity', 'quantidade', 'quantity', 'qtd', 'qty', 'estoque_atual'] as $c) {
            if (is_array($variacaoCols) && in_array($c, $variacaoCols, true)) {
                $variacaoColStock = $c;
                break;
            }
        }

        $variacaoColControla = null;
        foreach (['controla_estoque', 'manage_stock'] as $c) {
            if (is_array($variacaoCols) && in_array($c, $variacaoCols, true)) {
                $variacaoColControla = $c;
                break;
            }
        }

        $variacaoPkCol = 'id';
        if (is_array($variacaoCols) && !in_array('id', $variacaoCols, true) && in_array('variacao_id', $variacaoCols, true)) {
            $variacaoPkCol = 'variacao_id';
        }

        $variacaoProdutoFkCol = null;
        foreach (['produto_id', 'product_id'] as $c) {
            if (is_array($variacaoCols) && in_array($c, $variacaoCols, true)) {
                $variacaoProdutoFkCol = $c;
                break;
            }
        }

        $erros = [];

        $stProduto = null;
        try {
            $select = ['`' . $produtoPkCol . '` AS id'];
            if (is_array($produtoCols) && in_array('nome', $produtoCols, true)) {
                $select[] = 'nome';
            }
            if (is_array($produtoCols) && in_array('name', $produtoCols, true)) {
                $select[] = 'name';
            }
            if (is_array($produtoCols) && in_array('sku', $produtoCols, true)) {
                $select[] = 'sku';
            }
            if (!empty($produtoColAtivo)) $select[] = $produtoColAtivo;
            if (!empty($produtoColStatus)) $select[] = $produtoColStatus;
            if (!empty($produtoColStock)) $select[] = $produtoColStock;
            if (!empty($produtoColControla)) $select[] = $produtoColControla;
            $select = array_values(array_unique($select));

            $whereParts = [];
            foreach ($produtoLookupCols as $c) {
                if (preg_match('/^[a-zA-Z0-9_]+$/', (string) $c)) {
                    $whereParts[] = '`' . $c . '` = ?';
                }
            }
            if (empty($whereParts)) {
                $whereParts[] = '`' . $produtoPkCol . '` = ?';
            }

            $stProduto = $db->prepare('SELECT ' . implode(', ', $select) . ' FROM produtos WHERE (' . implode(' OR ', $whereParts) . ') LIMIT 1');
        } catch (\Throwable $e) {
            $stProduto = null;
        }

        $stVariacao = null;
        if ($hasProdutoVariacoes && $variacoesTable) {
            try {
                $select = ['`' . $variacaoPkCol . '` AS id'];
                if (!empty($variacaoProdutoFkCol)) {
                    $select[] = '`' . $variacaoProdutoFkCol . '` AS produto_id';
                }
                if (!empty($variacaoColAtivo)) $select[] = $variacaoColAtivo;
                if (!empty($variacaoColStatus)) $select[] = $variacaoColStatus;
                if (!empty($variacaoColStock)) $select[] = $variacaoColStock;
                if (!empty($variacaoColControla)) $select[] = $variacaoColControla;
                $select = array_values(array_unique($select));
                $stVariacao = $db->prepare('SELECT ' . implode(', ', $select) . ' FROM ' . $variacoesTable . ' WHERE `' . $variacaoPkCol . '` = ? LIMIT 1');
            } catch (\Throwable $e) {
                $stVariacao = null;
            }
        }

        foreach ($carrinho as $cartKey => $item) {
            $produtoId = (int) ($item['produto_id'] ?? ($item['id'] ?? 0));
            if ($produtoId <= 0 && (is_int($cartKey) || (is_string($cartKey) && ctype_digit($cartKey)))) {
                $produtoId = (int) $cartKey;
            }
            if ($produtoId <= 0) {
                continue;
            }
            $qtd = (int) ($item['quantidade'] ?? 1);
            if ($qtd < 1) $qtd = 1;

            $produtoVariacaoId = null;
            foreach (['produto_variacao_id', 'variacao_id', 'variation_id', 'produto_variacao', 'id_variacao'] as $varKey) {
                if (isset($item[$varKey]) && $item[$varKey] !== '' && $item[$varKey] !== null) {
                    $pv = (int) $item[$varKey];
                    if ($pv > 0) {
                        $produtoVariacaoId = $pv;
                        break;
                    }
                }
            }

            $produtoRow = null;
            if ($stProduto) {
                try {
                    $params = array_fill(0, count($produtoLookupCols), $produtoId);
                    if (empty($params)) {
                        $params = [$produtoId];
                    }
                    $stProduto->execute($params);
                    $produtoRow = $stProduto->fetch(\PDO::FETCH_ASSOC) ?: null;
                } catch (\Throwable $e) {
                    $produtoRow = null;
                }
            }

            // Fallback: alguns carrinhos armazenam o ID da variação no campo produto_id.
            // Se o produto não existir, tentar tratar o produto_id como variação.
            if ((!$produtoRow || empty($produtoRow['id'])) && $produtoVariacaoId === null && $hasProdutoVariacoes && $stVariacao) {
                try {
                    $stVariacao->execute([$produtoId]);
                    $varAsProduct = $stVariacao->fetch(\PDO::FETCH_ASSOC) ?: null;
                    if (is_array($varAsProduct) && !empty($varAsProduct['id']) && !empty($varAsProduct['produto_id'])) {
                        $produtoVariacaoId = (int) $varAsProduct['id'];
                        $produtoId = (int) $varAsProduct['produto_id'];

                        if ($stProduto) {
                            try {
                                $stProduto->execute([$produtoId]);
                                $produtoRow = $stProduto->fetch(\PDO::FETCH_ASSOC) ?: null;
                            } catch (\Throwable $e) {
                                $produtoRow = null;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                }
            }

            if (!$produtoRow || empty($produtoRow['id'])) {
                try {
                    $this->debugLog('[CHECKOUT] Produto não encontrado no banco. produto_id=' . $produtoId . ' lookup_cols=' . json_encode($produtoLookupCols));
                } catch (\Throwable $e) {
                }
                $erros[] = [
                    'produto_id' => $produtoId,
                    'produto_variacao_id' => $produtoVariacaoId,
                    'lookup_cols' => $produtoLookupCols,
                    'db' => ($dbName !== false ? $dbName : null),
                    'motivo' => 'Produto não encontrado',
                    'quantidade_solicitada' => $qtd,
                ];
                continue;
            }

            $nomeProduto = (string) ($item['nome'] ?? ($item['name'] ?? ($produtoRow['nome'] ?? ($produtoRow['name'] ?? ''))));
            if (trim($nomeProduto) === '') {
                $nomeProduto = 'Produto #' . $produtoId;
            }

            if (!empty($produtoColAtivo)) {
                $rawAtivo = $produtoRow[$produtoColAtivo] ?? 0;
                $ativoOk = false;
                if (is_numeric($rawAtivo)) {
                    $ativoOk = ((int) $rawAtivo) === 1;
                } else {
                    $s = strtolower(trim((string) $rawAtivo));
                    $ativoOk = in_array($s, ['1', 'true', 'yes', 'sim', 'ativo', 'active'], true);
                }
                if (!$ativoOk) {
                    $erros[] = [
                        'produto_id' => $produtoId,
                        'produto_variacao_id' => $produtoVariacaoId,
                        'nome' => $nomeProduto,
                        'motivo' => 'Produto inativo',
                        'quantidade_solicitada' => $qtd,
                    ];
                    continue;
                }
            }

            if (!empty($produtoColStatus)) {
                $st = strtolower(trim((string) ($produtoRow[$produtoColStatus] ?? '')));
                $statusOk = true;
                if ($st !== '') {
                    if (is_numeric($st)) {
                        $statusOk = ((int) $st) === 1;
                    } else {
                        $statusOk = in_array($st, ['published', 'ativo', 'active', 'enabled', 'instock', 'in_stock', 'available', 'disponivel'], true);
                    }
                }
                if (!$statusOk) {
                    $erros[] = [
                        'produto_id' => $produtoId,
                        'produto_variacao_id' => $produtoVariacaoId,
                        'nome' => $nomeProduto,
                        'motivo' => 'Produto indisponível',
                        'status' => $st,
                        'quantidade_solicitada' => $qtd,
                    ];
                    continue;
                }
            }

            if ($produtoVariacaoId !== null) {
                if (!$hasProdutoVariacoes || !$stVariacao) {
                    $erros[] = [
                        'produto_id' => $produtoId,
                        'produto_variacao_id' => $produtoVariacaoId,
                        'nome' => $nomeProduto,
                        'motivo' => 'Variação não disponível',
                        'quantidade_solicitada' => $qtd,
                    ];
                    continue;
                }

                $varRow = null;
                try {
                    $stVariacao->execute([(int) $produtoVariacaoId]);
                    $varRow = $stVariacao->fetch(\PDO::FETCH_ASSOC) ?: null;
                } catch (\Throwable $e) {
                    $varRow = null;
                }

                if (!$varRow || empty($varRow['id'])) {
                    $erros[] = [
                        'produto_id' => $produtoId,
                        'produto_variacao_id' => $produtoVariacaoId,
                        'nome' => $nomeProduto,
                        'motivo' => 'Variação não encontrada',
                        'quantidade_solicitada' => $qtd,
                    ];
                    continue;
                }

                if (isset($varRow['produto_id']) && (int) $varRow['produto_id'] !== $produtoId) {
                    $erros[] = [
                        'produto_id' => $produtoId,
                        'produto_variacao_id' => $produtoVariacaoId,
                        'nome' => $nomeProduto,
                        'motivo' => 'Variação inválida para este produto',
                        'quantidade_solicitada' => $qtd,
                    ];
                    continue;
                }

                if (!empty($variacaoColAtivo)) {
                    $rawAtivoV = $varRow[$variacaoColAtivo] ?? 0;
                    $ativoVOk = false;
                    if (is_numeric($rawAtivoV)) {
                        $ativoVOk = ((int) $rawAtivoV) === 1;
                    } else {
                        $s = strtolower(trim((string) $rawAtivoV));
                        $ativoVOk = in_array($s, ['1', 'true', 'yes', 'sim', 'ativo', 'active'], true);
                    }
                    if (!$ativoVOk) {
                        $erros[] = [
                            'produto_id' => $produtoId,
                            'produto_variacao_id' => $produtoVariacaoId,
                            'nome' => $nomeProduto,
                            'motivo' => 'Variação inativa',
                            'quantidade_solicitada' => $qtd,
                        ];
                        continue;
                    }
                }

                if (!empty($variacaoColStatus)) {
                    $stV = strtolower(trim((string) ($varRow[$variacaoColStatus] ?? '')));
                    $statusVOk = true;
                    if ($stV !== '') {
                        if (is_numeric($stV)) {
                            $statusVOk = ((int) $stV) === 1;
                        } else {
                            $statusVOk = in_array($stV, ['published', 'ativo', 'active', 'enabled', 'instock', 'in_stock', 'available', 'disponivel'], true);
                        }
                    }
                    if (!$statusVOk) {
                        $erros[] = [
                            'produto_id' => $produtoId,
                            'produto_variacao_id' => $produtoVariacaoId,
                            'nome' => $nomeProduto,
                            'motivo' => 'Variação indisponível',
                            'status' => $stV,
                            'quantidade_solicitada' => $qtd,
                        ];
                        continue;
                    }
                }

                if (!empty($variacaoColStock)) {
                    $controlaV = true;
                    if (!empty($variacaoColControla) && array_key_exists($variacaoColControla, $varRow)) {
                        $raw = $varRow[$variacaoColControla];
                        $controlaV = !empty($raw) && (string) $raw !== '0' && strtolower((string) $raw) !== 'false';
                    }
                    if ($controlaV) {
                        $stockV = (int) ($varRow[$variacaoColStock] ?? 0);
                        if ($stockV < $qtd) {
                            $erros[] = [
                                'produto_id' => $produtoId,
                                'produto_variacao_id' => $produtoVariacaoId,
                                'nome' => $nomeProduto,
                                'motivo' => 'Estoque insuficiente (variação)',
                                'estoque_disponivel' => $stockV,
                                'quantidade_solicitada' => $qtd,
                            ];
                        }
                    }
                }
            } else {
                if (!empty($produtoColStock) && isset($produtoRow[$produtoColStock])) {
                    $controla = true;
                    if (!empty($produtoColControla) && array_key_exists($produtoColControla, $produtoRow)) {
                        $raw = $produtoRow[$produtoColControla];
                        $controla = !empty($raw) && (string) $raw !== '0' && strtolower((string) $raw) !== 'false';
                    }
                    if ($controla) {
                        $stock = (int) $produtoRow[$produtoColStock];
                        if ($stock < $qtd) {
                            $erros[] = [
                                'produto_id' => $produtoId,
                                'produto_variacao_id' => null,
                                'nome' => $nomeProduto,
                                'motivo' => 'Estoque insuficiente',
                                'estoque_disponivel' => $stock,
                                'quantidade_solicitada' => $qtd,
                            ];
                        }
                    }
                }
            }
        }

        return $erros;
    }

    private function debugLog(string $message): void {
        $enabled = false;
        if (isset($_ENV['APP_DEBUG'])) {
            $enabled = ($_ENV['APP_DEBUG'] === '1' || strtolower((string) $_ENV['APP_DEBUG']) === 'true');
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $enabled = ($_SERVER['APP_DEBUG'] === '1' || strtolower((string) $_SERVER['APP_DEBUG']) === 'true');
        }

        if ($enabled) {
            error_log($message);
        }
    }

    private function formatarErroParaUsuario(string $mensagem): string {
        $m = trim($mensagem);

        if (stripos($m, 'Erro Asaas HTTP') !== false) {
            $jsonPos = strpos($m, '{');
            if ($jsonPos !== false) {
                $jsonStr = substr($m, $jsonPos);
                $decoded = json_decode($jsonStr, true);
                if (is_array($decoded) && !empty($decoded['errors']) && is_array($decoded['errors'])) {
                    $first = $decoded['errors'][0] ?? null;
                    if (is_array($first)) {
                        $desc = (string) ($first['description'] ?? '');
                        if ($desc !== '') {
                            return $desc;
                        }
                    }
                }
            }
        }

        $prefixes = [
            'Erro ao processar pedido:',
            'Erro ao processar pagamento:',
        ];
        foreach ($prefixes as $p) {
            if (stripos($m, $p) === 0) {
                $m = trim(substr($m, strlen($p)));
            }
        }

        return $m !== '' ? $m : 'Não foi possível processar o pagamento. Tente novamente.';
    }

    private function getConfigValue(string $chave, $default = null) {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }
        return $default;
    }

    private function getTaxaServicoPorKg(): float {
        $v = $this->getConfigValue('taxa_servico_usd_por_kg', null);
        if ($v === null || $v === '') {
            $v = $this->getConfigValue('entrega_taxa_servico_kg', null);
        }
        if ($v === null || $v === '') {
            $v = '39';
        }
        return floatval($v);
    }

    private function getPixDescontoTaxaServicoPercent(): float {
        $v = $this->getConfigValue('pagamentos_pix_desconto_taxa_servico_percent', null);
        if ($v === null || $v === '') {
            return 0.0;
        }
        $p = (float) str_replace(',', '.', (string) $v);
        if ($p < 0) $p = 0.0;
        if ($p > 100) $p = 100.0;
        return $p;
    }

    private function calcularFrete(float $subtotal, float $pesoTotal, string $moeda = 'USD'): float {
        $calcularAutomatico = $this->getConfigValue('entrega_calcular_automatico', '1');
        $calcularAutomatico = ($calcularAutomatico === '1' || strtolower((string) $calcularAutomatico) === 'true');
        if (!$calcularAutomatico) {
            return 0.0;
        }

        $freteGratisAcima = floatval($this->getConfigValue('entrega_frete_gratis_acima', '0'));
        if ($freteGratisAcima <= 0 || $subtotal >= $freteGratisAcima) {
            return 0.0;
        }

        $fretePorKg = floatval($this->getConfigValue('entrega_frete_padrao', '15'));
        if ($fretePorKg <= 0) {
            return 0.0;
        }

        $pesoArredondado = ceil($pesoTotal);
        return $fretePorKg * $pesoArredondado;
    }

    private function normalizeMissingForSelectedAddress(array $missing, ?array $selectedAddress): array {
        if (empty($missing)) {
            return $missing;
        }
        if (!is_array($selectedAddress) || empty($selectedAddress)) {
            return $missing;
        }

        $pais = strtoupper(trim((string) ($selectedAddress['pais'] ?? 'BR')));
        if ($pais === '') {
            $pais = 'BR';
        }

        $addrFields = ['cep', 'endereco', 'cidade'];
        if ($pais === 'BR') {
            $addrFields[] = 'numero';
            $addrFields[] = 'bairro';
        }
        if (in_array($pais, ['BR', 'US', 'CA'], true)) {
            $addrFields[] = 'estado';
        }

        // Remover do array de pendências apenas os campos de endereço que já foram preenchidos
        // no endereço selecionado/preenchido no checkout.
        $filled = [];
        foreach ($addrFields as $f) {
            $v = trim((string) ($selectedAddress[$f] ?? ''));
            if ($v !== '') {
                $filled[] = $f;
            }
        }
        if (empty($filled)) {
            return $missing;
        }

        return array_values(array_filter($missing, function ($it) use ($filled) {
            return !in_array((string) $it, $filled, true);
        }));
    }

    private function normalizeMissingForCountry(array $missing, string $paisEntrega): array {
        if (empty($missing)) {
            return $missing;
        }

        $paisEntrega = strtoupper(trim((string) $paisEntrega));
        if ($paisEntrega === '') {
            $paisEntrega = 'BR';
        }

        $remove = [];
        if ($paisEntrega !== 'BR') {
            $remove[] = 'numero';
            $remove[] = 'bairro';
        }
        if (!in_array($paisEntrega, ['BR', 'US', 'CA'], true)) {
            $remove[] = 'estado';
        }

        if (empty($remove)) {
            return $missing;
        }

        return array_values(array_filter($missing, static function ($it) use ($remove) {
            return !in_array((string) $it, $remove, true);
        }));
    }

    private function tableExists(string $table): bool {
        try {
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function pickPedidoItensTable(\PDO $db, int $pedidoId = 0): string {
        $temPedidoItens = false;
        $temPedidoItems = false;
        try {
            $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['pedido_itens']);
            $temPedidoItens = (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            $temPedidoItens = false;
        }
        try {
            $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['pedido_items']);
            $temPedidoItems = (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            $temPedidoItems = false;
        }

        if ($temPedidoItens && !$temPedidoItems) return 'pedido_itens';
        if ($temPedidoItems && !$temPedidoItens) return 'pedido_items';
        if (!$temPedidoItens && !$temPedidoItems) return 'pedido_itens';

        if ($pedidoId > 0) {
            $c1 = 0;
            $c2 = 0;
            try {
                $st = $db->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = ?');
                $st->execute([$pedidoId]);
                $c1 = (int) ($st->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                $c1 = 0;
            }
            try {
                $st = $db->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = ?');
                $st->execute([$pedidoId]);
                $c2 = (int) ($st->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                $c2 = 0;
            }
            return ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
        }

        return 'pedido_itens';
    }

    private function garantirCarteiraUsuario(\PDO $db, int $usuarioId): void {
        if ($usuarioId <= 0) {
            return;
        }

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `carteiras` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id` int(11) NOT NULL,
                    `saldo_usd` decimal(10,2) DEFAULT 0.00,
                    `saldo_brl` decimal(10,2) DEFAULT 0.00,
                    `saldo_usd_bloqueado` decimal(10,2) DEFAULT 0.00,
                    `saldo_brl_bloqueado` decimal(10,2) DEFAULT 0.00,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_usuario_id` (`usuario_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
        }

        try {
            $cols = [];
            try {
                $st = $db->query('DESCRIBE carteiras');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }
            $toAdd = [
                'saldo_usd_bloqueado' => "ALTER TABLE carteiras ADD COLUMN saldo_usd_bloqueado decimal(10,2) DEFAULT 0.00",
                'saldo_brl_bloqueado' => "ALTER TABLE carteiras ADD COLUMN saldo_brl_bloqueado decimal(10,2) DEFAULT 0.00",
            ];
            foreach ($toAdd as $c => $sql) {
                if (!is_array($cols) || !in_array($c, $cols, true)) {
                    try { $db->exec($sql); } catch (\Exception $e) {}
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $stmt = $db->prepare('INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)');
            $stmt->execute([(int) $usuarioId]);
        } catch (\Exception $e) {
        }
    }

    private function garantirTabelaTransacoesCarteira(\PDO $db): void {
        try {
            $db->exec("
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

    private function debitarCarteiraParaPedido(int $usuarioId, int $pedidoId, float $valor, string $moeda): array {
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

        $db = \Config\Database::getConnection();

        $this->garantirCarteiraUsuario($db, $usuarioId);
        $this->garantirTabelaTransacoesCarteira($db);

        $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
        $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';

        $db->beginTransaction();
        try {
            // Desbloquear recargas do checkout rápido que já passaram da carência
            try {
                $stmtUnlock = $db->prepare("SELECT id, moeda, valor
                    FROM carteira_recargas
                    WHERE usuario_id = :uid
                      AND origem = 'clube_quick_checkout'
                      AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')
                      AND unlocked_at IS NULL
                      AND locked_until IS NOT NULL
                      AND locked_until <= NOW()");
                $stmtUnlock->execute([':uid' => $usuarioId]);
                $unlockRows = $stmtUnlock->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($unlockRows as $ur) {
                    $rid = (int) ($ur['id'] ?? 0);
                    if ($rid <= 0) continue;
                    $m = strtoupper(trim((string) ($ur['moeda'] ?? 'USD')));
                    $v = (float) ($ur['valor'] ?? 0);
                    if ($v <= 0) continue;
                    $saldoBloqColTmp = ($m === 'BRL') ? 'saldo_brl_bloqueado' : 'saldo_usd_bloqueado';
                    $stmtDec = $db->prepare('UPDATE carteiras SET ' . $saldoBloqColTmp . ' = GREATEST(' . $saldoBloqColTmp . ' - :v, 0), updated_at = NOW() WHERE usuario_id = :uid');
                    $stmtDec->execute([':v' => $v, ':uid' => $usuarioId]);
                    $stmtMark = $db->prepare('UPDATE carteira_recargas SET unlocked_at = NOW(), updated_at = NOW() WHERE id = :id AND unlocked_at IS NULL');
                    $stmtMark->execute([':id' => $rid]);
                }
            } catch (\Exception $e) {
            }

            $stmt = $db->prepare('SELECT saldo_usd, saldo_brl, saldo_usd_bloqueado, saldo_brl_bloqueado FROM carteiras WHERE usuario_id = ? FOR UPDATE');
            $stmt->execute([$usuarioId]);
            $carteira = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $saldoAtual = (float) ($carteira[$saldoCol] ?? 0);
            $saldoBloqueado = (float) ($carteira[($moeda === 'BRL') ? 'saldo_brl_bloqueado' : 'saldo_usd_bloqueado'] ?? 0);
            if ($saldoBloqueado < 0) $saldoBloqueado = 0.0;
            $saldoDisponivel = $saldoAtual - $saldoBloqueado;
            if ($saldoDisponivel < 0) $saldoDisponivel = 0.0;
            if ($saldoDisponivel + 0.00001 < $valor) {
                throw new \Exception('Saldo insuficiente na carteira');
            }

            $stmtUpd = $db->prepare('UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' - :valor, updated_at = NOW() WHERE usuario_id = :uid');
            $stmtUpd->execute([':valor' => $valor, ':uid' => $usuarioId]);

            try {
                $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (usuario_id, tipo, ' . $valorCol . ', descricao, created_at) VALUES (:uid, \'debito\', :valor, :desc, NOW())');
                $stmtTx->execute([
                    ':uid' => $usuarioId,
                    ':valor' => $valor,
                    ':desc' => 'Pagamento do Pedido #' . $pedidoId,
                ]);
            } catch (\Exception $e) {
            }

            $db->commit();

            return [
                'success' => true,
                'status' => 'PAID',
                'paid_at' => date('Y-m-d H:i:s'),
                'billingType' => 'WALLET',
                'payment_id' => 'WALLET_' . $pedidoId,
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function getIdempotencySignature(array $dados, array $carrinho, array $usuario, float $total, string $moeda): string {
        $uid = (int) ($usuario['id'] ?? 0);
        $email = strtolower(trim((string) ($usuario['email'] ?? ($dados['email'] ?? ''))));
        $items = [];
        foreach ($carrinho as $it) {
            $produtoId = (int) ($it['produto_id'] ?? ($it['id'] ?? 0));
            $qtd = (int) ($it['quantidade'] ?? ($it['qty'] ?? 1));
            if ($produtoId <= 0 || $qtd <= 0) continue;
            $vid = (int) ($it['produto_variacao_id'] ?? 0);
            $vu = (float) ($it['preco_unitario'] ?? ($it['price'] ?? ($it['preco'] ?? 0)));
            $items[] = [$produtoId, $vid, $qtd, round($vu, 2)];
        }
        sort($items);
        $payload = json_encode([
            'uid' => $uid,
            'email' => $email,
            'moeda' => strtoupper(trim($moeda)),
            'total' => round($total, 2),
            'items' => $items,
        ]);
        return sha1((string) $payload);
    }

    private function findExistingPedidoByIdempotency(int $usuarioId, string $moeda, float $total, string $idemHash): ?int {
        try {
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE pedidos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $colUser = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
            $colMoeda = in_array('moeda', $cols, true) ? 'moeda' : (in_array('currency', $cols, true) ? 'currency' : '');
            $colTotal = in_array('total', $cols, true) ? 'total' : (in_array('valor_total', $cols, true) ? 'valor_total' : '');
            $colObs = in_array('observacoes', $cols, true) ? 'observacoes' : (in_array('observacao', $cols, true) ? 'observacao' : '');
            $colCreated = in_array('created_at', $cols, true) ? 'created_at' : (in_array('data_criacao', $cols, true) ? 'data_criacao' : '');

            if ($colUser === '' || $colTotal === '') {
                return null;
            }

            // Sem coluna de observações não dá para garantir que é a mesma tentativa (risco de reaproveitar pedido errado)
            if ($colObs === '') {
                return null;
            }

            $where = [];
            $params = [];

            $where[] = $colUser . ' = :uid';
            $params[':uid'] = $usuarioId;

            if ($colMoeda !== '') {
                $where[] = $colMoeda . ' = :moeda';
                $params[':moeda'] = strtoupper(trim($moeda));
            }

            // total aproximado (evita problemas de float)
            $where[] = 'ABS(' . $colTotal . ' - :total) < 0.01';
            $params[':total'] = round($total, 2);

            if ($colCreated !== '') {
                $where[] = $colCreated . " >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
            }

            $where[] = $colObs . ' LIKE :idem';
            $params[':idem'] = '%[IDEMPOTENCY:' . $idemHash . ']%';

            $sql = 'SELECT id FROM pedidos WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 1';
            $st = $db->prepare($sql);
            $st->execute($params);
            $id = (int) ($st->fetchColumn() ?: 0);
            return $id > 0 ? $id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function pedidoJaTemItens(int $pedidoId): bool {
        try {
            $db = \Config\Database::getConnection();
            foreach (['pedido_itens', 'pedido_items'] as $t) {
                try {
                    $st = $db->prepare('SELECT COUNT(*) FROM ' . $t . ' WHERE pedido_id = ?');
                    $st->execute([(int) $pedidoId]);
                    $cnt = (int) ($st->fetchColumn() ?: 0);
                    if ($cnt > 0) return true;
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
        }
        return false;
    }

    private function atualizarPagamentoNoPedido(int $pedidoId, array $paymentResult, string $gateway): void {
        try {
            $db = \Config\Database::getConnection();

            $colsP = [];
            try {
                $stmtColsP = $db->query('DESCRIBE pedidos');
                $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
            }

            if (!is_array($colsP) || empty($colsP)) {
                return;
            }

            $set = [];
            $params = ['id' => $pedidoId];

            if (in_array('payment_gateway', $colsP, true)) {
                $set[] = 'payment_gateway = :payment_gateway';
                $params['payment_gateway'] = $gateway;
            }

            if (!empty($paymentResult['payment_id']) && in_array('payment_id', $colsP, true)) {
                $set[] = 'payment_id = :payment_id';
                $params['payment_id'] = $paymentResult['payment_id'];
            }

            if (!empty($paymentResult['status']) && in_array('payment_status', $colsP, true)) {
                $set[] = 'payment_status = :payment_status';
                $params['payment_status'] = $paymentResult['status'];
            }

            if (!empty($paymentResult['paid_at']) && in_array('pago_em', $colsP, true)) {
                $set[] = 'pago_em = :pago_em';
                $params['pago_em'] = $paymentResult['paid_at'];
            }

            // Persistir detalhes auxiliares (quando existirem colunas no schema)
            if (!empty($paymentResult['invoiceUrl'])) {
                foreach (['payment_invoice_url', 'invoice_url', 'invoiceUrl'] as $c) {
                    if (in_array($c, $colsP, true)) {
                        $set[] = $c . ' = :invoice_url';
                        $params['invoice_url'] = (string) $paymentResult['invoiceUrl'];
                        break;
                    }
                }
            }
            if (!empty($paymentResult['bankSlipUrl'])) {
                foreach (['payment_bank_slip_url', 'bank_slip_url', 'bankSlipUrl', 'boleto_url'] as $c) {
                    if (in_array($c, $colsP, true)) {
                        $set[] = $c . ' = :boleto_url';
                        $params['boleto_url'] = (string) $paymentResult['bankSlipUrl'];
                        break;
                    }
                }
            }
            if (!empty($paymentResult['digitableLine'])) {
                foreach (['payment_digitable_line', 'digitable_line', 'digitableLine', 'linha_digitavel'] as $c) {
                    if (in_array($c, $colsP, true)) {
                        $set[] = $c . ' = :digitable_line';
                        $params['digitable_line'] = (string) $paymentResult['digitableLine'];
                        break;
                    }
                }
            }

            if (!empty($paymentResult['pix']) && is_array($paymentResult['pix'])) {
                $pixImg = (string) ($paymentResult['pix']['encodedImage'] ?? '');
                $pixPayload = (string) ($paymentResult['pix']['payload'] ?? '');
                if ($pixImg !== '') {
                    foreach (['payment_pix_encoded_image', 'pix_encoded_image', 'pix_qr_base64', 'pix_qr'] as $c) {
                        if (in_array($c, $colsP, true)) {
                            $set[] = $c . ' = :pix_encoded_image';
                            $params['pix_encoded_image'] = $pixImg;
                            break;
                        }
                    }
                }
                if ($pixPayload !== '') {
                    foreach (['payment_pix_payload', 'pix_payload', 'pix_emv', 'pix_copy_paste'] as $c) {
                        if (in_array($c, $colsP, true)) {
                            $set[] = $c . ' = :pix_payload';
                            $params['pix_payload'] = $pixPayload;
                            break;
                        }
                    }
                }
            }

            // Se o pagamento já veio confirmado/aprovado, atualizar o status do pedido
            $statusPago = false;
            $st = strtoupper((string) ($paymentResult['status'] ?? ''));
            if (in_array($st, ['CONFIRMED', 'RECEIVED', 'APPROVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                $statusPago = true;
            }

            if ($statusPago && in_array('status', $colsP, true)) {
                $set[] = 'status = :status';
                $params['status'] = 'pago';
            }

            if ($statusPago && in_array('pago_em', $colsP, true) && !array_key_exists('pago_em', $params)) {
                $set[] = 'pago_em = :pago_em';
                $params['pago_em'] = date('Y-m-d H:i:s');
            }

            if (empty($set)) {
                return;
            }

            $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $e) {
        }
    }

    private function processarPagamentoPedido(int $pedidoId, array $dados, array $usuario, array $pedidoRow): array {
        $forma = (string) ($dados['forma_pagamento'] ?? '');

        $pickNonEmpty = static function(...$vals): string {
            foreach ($vals as $v) {
                $s = trim((string) ($v ?? ''));
                if ($s !== '') return $s;
            }
            return '';
        };

        $nomeEfetivo = $pickNonEmpty(
            $dados['nome'] ?? '',
            $pedidoRow['cliente_nome'] ?? '',
            $pedidoRow['customer_name'] ?? '',
            $usuario['nome'] ?? '',
            'Cliente'
        );
        $emailEfetivo = $pickNonEmpty(
            $dados['email'] ?? '',
            $pedidoRow['cliente_email'] ?? '',
            $pedidoRow['email'] ?? '',
            $pedidoRow['customer_email'] ?? '',
            $usuario['email'] ?? ''
        );
        $docEfetivo = $pickNonEmpty(
            $dados['documento'] ?? '',
            $pedidoRow['cliente_documento'] ?? '',
            $pedidoRow['documento'] ?? '',
            $usuario['documento'] ?? ''
        );
        $telEfetivo = $pickNonEmpty(
            $dados['telefone'] ?? '',
            $pedidoRow['cliente_telefone'] ?? '',
            $usuario['telefone'] ?? '',
            $usuario['celular'] ?? ''
        );

        $billingType = 'CREDIT_CARD';
        if ($forma === 'pix') {
            $billingType = 'PIX';
        } elseif ($forma === 'boleto') {
            $billingType = 'BOLETO';
        }

        $valor = (float) ($pedidoRow['total'] ?? 0);
        $moeda = (string) ($pedidoRow['moeda'] ?? 'BRL');
        $taxaConversao = (float) ($pedidoRow['taxa_conversao'] ?? 1.0);
        if ($taxaConversao <= 0) {
            $taxaConversao = 1.0;
        }

        // Para BRL, preferir valores normalizados (já em BRL) vindos do model (evita cobrar USD quando moeda=BRL)
        $pedidoNorm = [];
        $usingNorm = false;
        if (strtoupper(trim((string) $moeda)) === 'BRL') {
            try {
                $pedidoNorm = $this->pedidoModel->getComDetalhes((int) $pedidoId);
                if (is_array($pedidoNorm) && !empty($pedidoNorm)) {
                    if (strtoupper(trim((string) ($pedidoNorm['moeda'] ?? 'BRL'))) === 'BRL') {
                        $vNorm = (float) ($pedidoNorm['total'] ?? 0);
                        if ($vNorm > 0) {
                            $valor = $vNorm;
                            $usingNorm = true;
                        }
                        $txNorm = (float) ($pedidoNorm['taxa_conversao'] ?? 0);
                        if ($txNorm > 1.01) {
                            $taxaConversao = $txNorm;
                        }
                    }
                }
            } catch (\Exception $e) {
                $pedidoNorm = [];
                $usingNorm = false;
            }
        }

        // Se já temos valores normalizados, não usar heurísticas nem recarregar colunas do pedido
        $pedidoFull = [];
        if (!$usingNorm) {
            // Recarregar pedido completo (quando possível) para ter base consistente de total/frete/serviços/impostos
            try {
                $dbP = \Config\Database::getConnection();
                $stCols = $dbP->query('DESCRIBE pedidos');
                $colsPed = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($colsPed) && !empty($colsPed)) {
                    $select = ['id'];
                    foreach (['subtotal', 'subtotal_produtos', 'servicos', 'taxa_servico', 'impostos', 'valor_impostos', 'frete', 'valor_frete', 'desconto', 'total', 'valor_total', 'taxa_conversao', 'moeda', 'currency'] as $c) {
                        if (in_array($c, $colsPed, true)) {
                            $select[] = $c;
                        }
                    }
                    $stP = $dbP->prepare('SELECT ' . implode(', ', array_unique($select)) . ' FROM pedidos WHERE id = ? LIMIT 1');
                    $stP->execute([$pedidoId]);
                    $pedidoFull = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];
                }
            } catch (\Exception $e) {
                $pedidoFull = [];
            }

            // Preferir moeda do pedido completo se existir
            if (!empty($pedidoFull)) {
                $moeda = (string) ($pedidoFull['moeda'] ?? ($pedidoFull['currency'] ?? $moeda));
                if (isset($pedidoFull['taxa_conversao']) && (float) $pedidoFull['taxa_conversao'] > 0) {
                    $taxaConversao = (float) $pedidoFull['taxa_conversao'];
                }
            }
        }

        // Se o pedido está como BRL mas o total parece estar em USD, converter antes de cobrar
        $deveConverterParaBRL = false;
        if ($usingNorm) {
            $deveConverterParaBRL = false;
        }
        if (strtoupper(trim((string) $moeda)) === 'BRL') {
            if ($usingNorm) {
                // já normalizado em BRL via model
            } else {
            if ($taxaConversao <= 1.01) {
                try {
                    $dbTx = \Config\Database::getConnection();
                    $stTx = $dbTx->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                    $stTx->execute();
                    $v = (string) ($stTx->fetchColumn() ?: '0');
                    $tx = (float) str_replace(',', '.', $v);
                    if ($tx > 1.01) {
                        $taxaConversao = $tx;
                    }
                } catch (\Exception $e) {
                }
            }

            // Fonte canônica: soma das colunas (quando existirem)
            $sumCols = null;
            if (!empty($pedidoFull)) {
                $sub = (float) ($pedidoFull['subtotal'] ?? ($pedidoFull['subtotal_produtos'] ?? 0));
                $svc = (float) ($pedidoFull['servicos'] ?? ($pedidoFull['taxa_servico'] ?? 0));
                $imp = (float) ($pedidoFull['impostos'] ?? ($pedidoFull['valor_impostos'] ?? 0));
                $fre = (float) ($pedidoFull['frete'] ?? ($pedidoFull['valor_frete'] ?? 0));
                $des = (float) ($pedidoFull['desconto'] ?? 0);
                $sumCols = ($sub + $svc + $imp + $fre - $des);
                if ($sumCols <= 0) {
                    $sumCols = null;
                }
            }

            // Se os valores do pedido no banco estão em USD mas a moeda é BRL, converter com taxa_conversao
            if ($taxaConversao > 1.01) {
                if ($sumCols !== null && $sumCols > 0 && $sumCols <= 2000) {
                    $sumCols = $sumCols * $taxaConversao;
                    $deveConverterParaBRL = true;
                }
                if ($valor > 0 && $valor <= 2000) {
                    $deveConverterParaBRL = true;
                }
            }

            // Se a soma já está em BRL (ex: 754.65) ela deve ser o valor da cobrança
            if ($sumCols !== null && $sumCols > 0) {
                // Se o total salvo é muito menor (ex: 129), usar a soma
                if ($valor <= 0 || $sumCols > ($valor * 1.2)) {
                    $valor = $sumCols;
                }
            }

            // heurística: total "baixo" com taxa>1 normalmente significa que total está em USD
            if ($taxaConversao > 1.01 && $valor > 0 && $valor <= 2000) {
                // Se já ajustamos para a soma, não converter novamente.
                if ($sumCols === null) {
                    $deveConverterParaBRL = true;
                }
            }
            }
        }

        if ($deveConverterParaBRL) {
            $valor = $valor * $taxaConversao;
        }
        $descricao = 'Pedido #' . (string) ($pedidoRow['numero_pedido'] ?? $pedidoId);

        $payload = [
            'billingType' => $billingType,
            'externalReference' => (string) $pedidoId,
            'customer_name' => (string) $nomeEfetivo,
            'customer_email' => (string) $emailEfetivo,
            'customer_document' => (string) $docEfetivo,
            'customer_phone' => (string) $telEfetivo,
            'customer_zipcode' => (string) ($dados['cep'] ?? ''),
            'customer_address' => (string) ($dados['endereco'] ?? ''),
            'customer_address_number' => (string) ($dados['numero'] ?? ''),
            'customer_address_complement' => (string) ($dados['complemento'] ?? ''),
            'customer_province' => (string) ($dados['bairro'] ?? ''),
            'customer_city' => (string) ($dados['cidade'] ?? ''),
            'customer_state' => (string) ($dados['estado'] ?? ''),
        ];

        // AppMax exige o order_id/customer_id, mas aqui criamos os dois com base em:
        // - products (itens) em centavos
        // - products_value/shipping_value/discount_value em centavos
        if (strtoupper(trim((string) $moeda)) === 'BRL') {
            try {
                $db = \Config\Database::getConnection();

                $products = [];
                $productsValueCents = 0;
                $shippingValueCents = 0;
                $discountValueCents = 0;

                // Preferir itens/frete normalizados do PedidoEcommerce (já em BRL)
                if (is_array($pedidoNorm) && !empty($pedidoNorm)) {
                    $freteNorm = (float) ($pedidoNorm['frete'] ?? 0);
                    $shippingValueCents = (int) round($freteNorm * 100);

                    $itemsNorm = (array) ($pedidoNorm['items'] ?? []);
                    foreach ($itemsNorm as $it) {
                        if (!is_array($it)) continue;
                        $qtd = (int) ($it['quantidade'] ?? 1);
                        if ($qtd <= 0) $qtd = 1;

                        $preco = (float) ($it['preco_unitario'] ?? 0);
                        $unitValueCents = (int) round($preco * 100);
                        if ($unitValueCents <= 0) continue;
                        $productsValueCents += ($unitValueCents * $qtd);

                        $sku = (string) ($it['sku'] ?? ($it['nome_produto_sku'] ?? ($it['referencia'] ?? '')));
                        if ($sku === '') {
                            $pid = (int) ($it['produto_id'] ?? 0);
                            $sku = $pid > 0 ? ('PROD_' . $pid) : ('ITEM_' . uniqid());
                        }
                        $name = (string) ($it['nome'] ?? ($it['nome_produto'] ?? 'Item do Pedido'));
                        $products[] = [
                            'sku' => $sku,
                            'name' => $name,
                            'quantity' => $qtd,
                            'unit_value' => $unitValueCents,
                            'type' => 'physical',
                        ];
                    }
                }

                // Frete do pedido (se existir coluna)
                try {
                    $stmtColsP = $db->query('DESCRIBE pedidos');
                    $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
                    $freteCol = null;
                    foreach (['frete', 'valor_frete', 'shipping'] as $c) {
                        if (is_array($colsP) && in_array($c, $colsP, true)) {
                            $freteCol = $c;
                            break;
                        }
                    }
                    if ($freteCol) {
                        $stmtF = $db->prepare('SELECT ' . $freteCol . ' AS frete FROM pedidos WHERE id = ? LIMIT 1');
                        $stmtF->execute([$pedidoId]);
                        $rowF = $stmtF->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $freteValor = (float) ($rowF['frete'] ?? 0);
                        if ($deveConverterParaBRL) {
                            $freteValor = $freteValor * $taxaConversao;
                        }
                        if ($shippingValueCents <= 0) {
                            $shippingValueCents = (int) round($freteValor * 100);
                        }
                    }
                } catch (\Exception $e) {
                }

                // Itens do pedido (tolerante a schema)
                try {
                    if (!empty($products)) {
                        // já carregado via PedidoEcommerce
                        throw new \Exception('skip');
                    }
                    $itensTable = $this->pickPedidoItensTable($db, $pedidoId);
                    $stmtColsI = $db->query('DESCRIBE ' . $itensTable);
                    $colsI = $stmtColsI->fetchAll(\PDO::FETCH_COLUMN);

                    $colPedidoId = in_array('pedido_id', $colsI, true) ? 'pedido_id' : '';
                    if ($colPedidoId !== '') {
                        $colQtd = in_array('quantidade', $colsI, true) ? 'quantidade' : (in_array('qty', $colsI, true) ? 'qty' : '');
                        $colPreco = in_array('preco_unitario', $colsI, true) ? 'preco_unitario' : (in_array('valor_unitario', $colsI, true) ? 'valor_unitario' : (in_array('price', $colsI, true) ? 'price' : ''));
                        $colNome = in_array('nome_produto', $colsI, true) ? 'nome_produto' : (in_array('produto_nome', $colsI, true) ? 'produto_nome' : (in_array('nome', $colsI, true) ? 'nome' : ''));
                        $colSku = in_array('nome_produto_sku', $colsI, true) ? 'nome_produto_sku' : (in_array('sku', $colsI, true) ? 'sku' : '');
                        $colProdutoId = in_array('produto_id', $colsI, true) ? 'produto_id' : '';

                        $select = ['id'];
                        if ($colProdutoId !== '') $select[] = $colProdutoId . ' AS produto_id';
                        if ($colQtd !== '') $select[] = $colQtd . ' AS quantidade';
                        if ($colPreco !== '') $select[] = $colPreco . ' AS preco_unitario';
                        if ($colNome !== '') $select[] = $colNome . ' AS nome_produto';
                        if ($colSku !== '') $select[] = $colSku . ' AS sku';

                        $stmtItens = $db->prepare('SELECT ' . implode(', ', $select) . ' FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' = ?');
                        $stmtItens->execute([$pedidoId]);
                        $rowsItens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        foreach ($rowsItens as $it) {
                            $qtd = (int) ($it['quantidade'] ?? 1);
                            if ($qtd <= 0) $qtd = 1;
                            $preco = (float) ($it['preco_unitario'] ?? 0);
                            if ($deveConverterParaBRL) {
                                $preco = $preco * $taxaConversao;
                            }
                            $unitValueCents = (int) round($preco * 100);
                            if ($unitValueCents <= 0) {
                                continue;
                            }
                            $productsValueCents += ($unitValueCents * $qtd);

                            $sku = (string) ($it['sku'] ?? '');
                            if ($sku === '') {
                                $pid = (int) ($it['produto_id'] ?? 0);
                                $sku = $pid > 0 ? ('PROD_' . $pid) : ('ITEM_' . (int) ($it['id'] ?? 0));
                            }

                            $name = (string) ($it['nome_produto'] ?? '');
                            if ($name === '') {
                                $name = 'Item do Pedido';
                            }

                            $products[] = [
                                'sku' => $sku,
                                'name' => $name,
                                'quantity' => $qtd,
                                'unit_value' => $unitValueCents,
                                'type' => 'physical',
                            ];
                        }
                    }
                } catch (\Exception $e) {
                }

                // Forçar cobrança exatamente pelo TOTAL exibido ao usuário (BRL)
                // (elimina divergência USD/BRL e diferenças entre itens/frete/tabelas)
                if ($valor > 0) {
                    $totalCents = (int) round(((float) $valor) * 100);
                    $products = [
                        [
                            'sku' => 'PEDIDO_' . (string) $pedidoId,
                            'name' => $descricao,
                            'quantity' => 1,
                            'unit_value' => $totalCents,
                            'type' => 'service',
                        ]
                    ];
                    $productsValueCents = $totalCents;
                    $shippingValueCents = 0;
                    $discountValueCents = 0;
                }

                if (!empty($products)) {
                    $payload['products'] = $products;
                    $payload['products_value_cents'] = $productsValueCents;
                    $payload['shipping_value_cents'] = $shippingValueCents;
                    $payload['discount_value_cents'] = $discountValueCents;
                }
            } catch (\Exception $e) {
            }
        }

        if ($billingType === 'CREDIT_CARD') {
            $payload['card_holder_name'] = (string) ($dados['card_holder_name'] ?? '');
            $payload['card_number'] = (string) ($dados['card_number'] ?? '');
            $payload['card_expiry_month'] = (string) ($dados['card_expiry_month'] ?? '');
            $payload['card_expiry_year'] = (string) ($dados['card_expiry_year'] ?? '');
            $payload['card_cvv'] = (string) ($dados['card_cvv'] ?? '');
        }

        $errosPagamento = $this->paymentService->validarDadosPagamento($payload);
        if (!empty($errosPagamento)) {
            throw new \Exception(implode(', ', $errosPagamento));
        }

        $result = $this->paymentService->processarPagamento($payload, $valor, $moeda, $descricao);
        if (empty($result['success'])) {
            throw new \Exception('Falha ao processar pagamento');
        }

        return $result;
    }

    private function registrarPagamentoPedido($pedidoId, $dados) {
        try {
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE pagamentos');
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                return;
            }

            if (!is_array($cols) || empty($cols)) {
                return;
            }

            $stmtPedido = $db->prepare('SELECT total, moeda, forma_pagamento FROM pedidos WHERE id = ? LIMIT 1');
            $stmtPedido->execute([$pedidoId]);
            $pedidoRow = $stmtPedido->fetch(\PDO::FETCH_ASSOC);

            $metodo = $dados['forma_pagamento'] ?? ($pedidoRow['forma_pagamento'] ?? null);
            $statusInicial = 'pendente';

            $insert = [];
            if (in_array('pedido_id', $cols, true)) {
                $insert['pedido_id'] = $pedidoId;
            }

            foreach (['metodo', 'forma_pagamento', 'payment_method', 'tipo'] as $c) {
                if (!empty($metodo) && in_array($c, $cols, true)) {
                    $insert[$c] = $metodo;
                    break;
                }
            }

            foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                if (in_array($c, $cols, true)) {
                    $insert[$c] = $statusInicial;
                    break;
                }
            }

            foreach (['gateway', 'provedor', 'provider'] as $c) {
                if (in_array($c, $cols, true)) {
                    $insert[$c] = ($pedidoRow['moeda'] ?? null) === 'BRL' ? 'appmax' : 'stripe';
                    break;
                }
            }

            if (in_array('valor', $cols, true) && isset($pedidoRow['total'])) {
                $insert['valor'] = $pedidoRow['total'];
            }
            if (in_array('valor_total', $cols, true) && isset($pedidoRow['total'])) {
                $insert['valor_total'] = $pedidoRow['total'];
            }

            if (empty($insert) || !isset($insert['pedido_id'])) {
                return;
            }

            // Se já existir, atualizar; senão, inserir
            $existe = false;
            if (in_array('pedido_id', $cols, true)) {
                try {
                    $stmtExiste = $db->prepare('SELECT 1 FROM pagamentos WHERE pedido_id = ? LIMIT 1');
                    $stmtExiste->execute([$pedidoId]);
                    $existe = (bool) $stmtExiste->fetchColumn();
                } catch (\Exception $e) {
                }
            }

            if ($existe) {
                $setParts = [];
                $params = [];
                foreach ($insert as $k => $v) {
                    if ($k === 'pedido_id') {
                        continue;
                    }
                    $setParts[] = "{$k} = :{$k}";
                    $params[$k] = $v;
                }
                if (!empty($setParts)) {
                    $params['pedido_id'] = $pedidoId;
                    $sql = 'UPDATE pagamentos SET ' . implode(', ', $setParts) . ' WHERE pedido_id = :pedido_id';
                    $stmtUpd = $db->prepare($sql);
                    $stmtUpd->execute($params);
                }
            } else {
                $columns = implode(', ', array_keys($insert));
                $placeholders = ':' . implode(', :', array_keys($insert));
                $sql = "INSERT INTO pagamentos ({$columns}) VALUES ({$placeholders})";
                $stmtIns = $db->prepare($sql);
                foreach ($insert as $k => $v) {
                    $stmtIns->bindValue(':' . $k, $v);
                }
                $stmtIns->execute();
            }
        } catch (\Exception $e) {
        }
    }
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->paymentService = new PaymentService();
        $this->carrinhoModel = new Carrinho();
        $this->usuarioModel = new Usuario();
        $this->enderecoModel = new Endereco();
        $this->pedidoModel = new PedidoEcommerce();
    }

    private function requireFromCartOrRedirect(bool $asJson = false): bool {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } catch (\Throwable $e) {
        }

        $fromCartAt = (int) ($_SESSION['checkout_from_cart_at'] ?? 0);
        if ($fromCartAt <= 0 || (time() - $fromCartAt) > 900) {
            if ($asJson) {
                $this->json(['success' => false, 'error' => 'Acesso ao checkout inválido. Volte ao carrinho para continuar.'], 403);
                return false;
            }
            $this->redirect('/carrinho');
            return false;
        }

        return true;
    }

    private function getUserCartIdPreferNonEmpty(int $usuarioId): int {
        if ($usuarioId <= 0) return 0;
        try {
            $db = $this->carrinhoModel->getConnection();
            $st = $db->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? AND expira_em > NOW() ORDER BY created_at DESC LIMIT 10');
            $st->execute([$usuarioId]);
            $ids = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (empty($ids)) {
                return 0;
            }

            foreach ($ids as $cid) {
                try {
                    $stCnt = $db->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                    $stCnt->execute([$cid]);
                    $cnt = (int) ($stCnt->fetchColumn() ?: 0);
                    if ($cnt > 0) {
                        return $cid;
                    }
                } catch (\Throwable $e) {
                }
            }

            return (int) $ids[0];
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function validatePesoMaximoCarrinhoAtivoOrFail(bool $asJson = false): bool {
        // Revalidar peso ativo no backend (evita bypass via request manual)
        $usuario = $this->authService->getUsuarioLogado();
        $carrinho = $this->getCarrinhoForCheckout(is_array($usuario) ? $usuario : null);
        if (empty($carrinho)) {
            if ($asJson) {
                $this->json(['success' => false, 'error' => 'Carrinho vazio'], 400);
                return false;
            }
            $this->redirect('/carrinho');
            return false;
        }

        $pesoTotal = 0.0;
        foreach ($carrinho as $item) {
            $qtd = (int) ($item['quantidade'] ?? 0);
            if ($qtd <= 0) continue;
            $pesoUnit = (float) ($item['peso'] ?? ($item['weight'] ?? 0));
            if ($pesoUnit <= 0) {
                $pesoUnit = 0.5;
            }
            $pesoTotal += ($pesoUnit * $qtd);
        }

        if ($pesoTotal > 30.0 + 0.00001) {
            if ($asJson) {
                $this->json(['success' => false, 'error' => 'Peso máximo do carrinho é 30kg. Desative itens no carrinho para continuar.'], 400);
                return false;
            }
            $_SESSION['message'] = 'Peso máximo do carrinho é 30kg. Desative itens no carrinho para continuar.';
            $_SESSION['message_type'] = 'warning';
            $this->redirect('/carrinho');
            return false;
        }

        return true;
    }

    private function getCarrinhoForCheckout(?array $usuario): array {
        $uid = (int) (($usuario['id'] ?? 0));
        if ($uid > 0) {
            try {
                $cartId = (int) $this->getUserCartIdPreferNonEmpty($uid);
                if ($cartId <= 0) {
                    $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                    $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                }

                if ($cartId > 0) {
                    $db = $this->carrinhoModel->getConnection();
                    $cols = [];
                    try {
                        $stCols = $db->query('DESCRIBE carrinho_items');
                        $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Throwable $e) {
                        $cols = [];
                    }

                    $unitCol = (is_array($cols) && in_array('preco_unitario', $cols, true)) ? 'preco_unitario' : 'valor_unitario';
                    $varCol = (is_array($cols) && in_array('produto_variacao_id', $cols, true))
                        ? 'produto_variacao_id'
                        : ((is_array($cols) && in_array('variacao_id', $cols, true)) ? 'variacao_id' : 'produto_variacao_id');

                    $st = $db->prepare('SELECT *, ' . $unitCol . ' AS unit_price, ' . $varCol . ' AS var_id FROM carrinho_items WHERE carrinho_id = ?');
                    $st->execute([$cartId]);
                    $items = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    $out = [];
                    foreach (($items ?: []) as $it) {
                        $pid = (int) ($it['produto_id'] ?? 0);
                        if ($pid <= 0) continue;
                        $pvId = (int) ($it['var_id'] ?? ($it['produto_variacao_id'] ?? ($it['variacao_id'] ?? 0)));
                        $key = ((string) $pid) . ':' . ((string) $pvId);
                        $qtd = (int) ($it['quantidade'] ?? 1);
                        if ($qtd < 1) $qtd = 1;
                        $vu = (float) ($it['unit_price'] ?? ($it['valor_unitario'] ?? ($it['preco_unitario'] ?? 0)));
                        $sub = (float) ($it['subtotal'] ?? ($vu * $qtd));
                        $out[$key] = [
                            'produto_id' => $pid,
                            'produto_variacao_id' => ($pvId > 0 ? $pvId : null),
                            'variacao_descricao' => $it['variacao_descricao'] ?? null,
                            'nome' => $it['nome'] ?? null,
                            'price' => $vu,
                            'preco_unitario' => $vu,
                            'quantidade' => $qtd,
                            'subtotal' => $sub,
                            'peso' => 0.0,
                        ];
                    }

                    if (!empty($out)) {
                        try {
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                        } catch (\Throwable $e) {
                        }
                        $ativosMap = (isset($_SESSION['carrinho_itens_ativos']) && is_array($_SESSION['carrinho_itens_ativos'])) ? $_SESSION['carrinho_itens_ativos'] : [];
                        $out = array_filter($out, function ($v, $k) use ($ativosMap) {
                            if (is_array($ativosMap) && array_key_exists((string) $k, $ativosMap)) {
                                return (bool) $ativosMap[(string) $k];
                            }
                            return true;
                        }, ARRAY_FILTER_USE_BOTH);
                        return $out;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } catch (\Throwable $e) {
        }

        $sessCart = $_SESSION['carrinho'] ?? [];
        $ativosMap = (isset($_SESSION['carrinho_itens_ativos']) && is_array($_SESSION['carrinho_itens_ativos'])) ? $_SESSION['carrinho_itens_ativos'] : [];
        if (!empty($sessCart) && is_array($sessCart)) {
            $sessCart = array_filter($sessCart, function ($v, $k) use ($ativosMap) {
                if (is_array($ativosMap) && array_key_exists((string) $k, $ativosMap)) {
                    return (bool) $ativosMap[(string) $k];
                }
                return true;
            }, ARRAY_FILTER_USE_BOTH);
        }
        return $sessCart;
    }
    
    public function index(Request $request) {
        if (!$this->requireFromCartOrRedirect(false)) {
            return;
        }
        if (!$this->validatePesoMaximoCarrinhoAtivoOrFail(false)) {
            return;
        }

        // Obter usuário logado
        $usuario = $this->authService->getUsuarioLogado();

        // Obter carrinho (DB quando logado, sessão como fallback)
        $carrinho = $this->getCarrinhoForCheckout($usuario);
        
        // Verificar se o carrinho tem itens
        if (empty($carrinho)) {
            $this->redirect('/produtos');
            return;
        }
        
        // Obter usuário logado (já carregado acima)
        $usuarioCompletoDb = null;
        if (!empty($usuario) && !empty($usuario['id'])) {
            try {
                $usuarioCompletoDb = $this->usuarioModel->find((int) $usuario['id']);
            } catch (\Exception $e) {
                $usuarioCompletoDb = null;
            }
        }

        if (is_array($usuarioCompletoDb) && !empty($usuarioCompletoDb)) {
            $usuario = array_merge($usuarioCompletoDb, is_array($usuario) ? $usuario : []);
        }

        $usuarioCompleto = null;
        $perfilOk = true;
        $termosOk = true;
        $faltando = [];
        if (!empty($usuario) && !empty($usuario['id'])) {
            try {
                $usuarioCompleto = $this->usuarioModel->find((int) $usuario['id']);
            } catch (\Exception $e) {
                $usuarioCompleto = null;
            }
            if (is_array($usuarioCompleto) && !empty($usuarioCompleto)) {
                $termosOk = $this->usuarioModel->hasAcceptedTerms($usuarioCompleto);
                $faltando = $this->usuarioModel->getMissingRequiredFields($usuarioCompleto);
                $perfilOk = empty($faltando);
            }
        }
        
        // Obter detalhes dos produtos no carrinho
        $items = [];
        $subtotal = 0;
        $pesoTotal = 0;

        $pesoClubeTotal = 0.0;
        $subtotalClube = 0.0;
        $descontoClube = 0.0;
        $cashbackClubeEstimado = 0.0;

        // Fallback de peso via tabela produtos (quando o item do carrinho não trouxer)
        $pesoCol = null;
        $pesoCache = [];
        $stPeso = null;
        $hasClubeAtivo = false;
        $clubeCache = [];
        $stClube = null;
        try {
            $dbPeso = \Config\Database::getConnection();
            $stmtColsProd = $dbPeso->query('DESCRIBE produtos');
            $colsProd = $stmtColsProd ? ($stmtColsProd->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            foreach (['peso', 'weight', 'product_weight'] as $c) {
                if (is_array($colsProd) && in_array($c, $colsProd, true)) {
                    $pesoCol = $c;
                    break;
                }
            }
            if ($pesoCol) {
                $stPeso = $dbPeso->prepare('SELECT ' . $pesoCol . ' AS peso FROM produtos WHERE id = ? LIMIT 1');
            }

            $hasClubeAtivo = (is_array($colsProd) && in_array('clube_ativo', $colsProd, true));
            if ($hasClubeAtivo) {
                $stClube = $dbPeso->prepare('SELECT COALESCE(clube_ativo,0) AS clube_ativo FROM produtos WHERE id = ? LIMIT 1');
            }
        } catch (\Exception $e) {
            $pesoCol = null;
            $stPeso = null;
            $hasClubeAtivo = false;
            $stClube = null;
        }
        
        $produtoModel = null;
        try {
            $produtoModel = new Produto();
        } catch (\Throwable $e) {
            $produtoModel = null;
        }

        foreach ($carrinho as $produtoId => $item) {
            // Verificar diferentes campos de preço
            $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
            $quantidade = $item['quantidade'] ?? 1;

            // Peso real (kg) quando disponível; fallback para 0.5kg
            $pesoRaw = $item['peso'] ?? ($item['weight'] ?? 0);
            if (is_string($pesoRaw)) {
                $pesoRaw = str_replace(',', '.', trim($pesoRaw));
            }
            $pesoUnit = (float) $pesoRaw;

            if ($pesoUnit <= 0) {
                $pid = (int) ($item['produto_id'] ?? 0);
                if ($pid > 0) {
                    if (array_key_exists($pid, $pesoCache)) {
                        $pesoUnit = (float) $pesoCache[$pid];
                    } elseif ($stPeso) {
                        try {
                            $stPeso->execute([$pid]);
                            $pesoDb = $stPeso->fetchColumn();
                            if (is_string($pesoDb)) {
                                $pesoDb = str_replace(',', '.', trim($pesoDb));
                            }
                            $pesoUnit = (float) ($pesoDb ?: 0);
                            $pesoCache[$pid] = $pesoUnit;
                        } catch (\Exception $e) {
                            $pesoCache[$pid] = 0.0;
                        }
                    }
                }
            }
            if ($pesoUnit <= 0) {
                $pesoUnit = 0.5;
            }
            $pesoItem = $pesoUnit * (int) $quantidade;

            $isClubeAtivo = false;
            if ($hasClubeAtivo) {
                $pid = (int) ($item['produto_id'] ?? 0);
                if ($pid > 0) {
                    if (array_key_exists($pid, $clubeCache)) {
                        $isClubeAtivo = (bool) $clubeCache[$pid];
                    } elseif ($stClube) {
                        try {
                            $stClube->execute([$pid]);
                            $cv = (int) ($stClube->fetchColumn() ?: 0);
                            $isClubeAtivo = ($cv === 1);
                            $clubeCache[$pid] = $isClubeAtivo;
                        } catch (\Exception $e) {
                            $clubeCache[$pid] = false;
                        }
                    }
                }
            }
            
            $pidReal = (int) ($item['produto_id'] ?? 0);
            if ($pidReal <= 0) {
                // Fallback: quando o carrinho vier como array indexado por "produtoId:variacaoId"
                $pidReal = (int) (is_numeric($produtoId) ? $produtoId : 0);
            }

            $nomeProduto = (string) ($item['nome'] ?? ($item['name'] ?? ''));
            if ($nomeProduto === '' && $pidReal > 0 && $produtoModel) {
                try {
                    $pRow = $produtoModel->find($pidReal);
                    if (is_array($pRow) && !empty($pRow['nome'])) {
                        $nomeProduto = (string) $pRow['nome'];
                    }
                } catch (\Throwable $e) {
                }
            }
            if ($nomeProduto === '') {
                $nomeProduto = 'Produto ' . (string) $pidReal;
            }

            // Buscar detalhes do produto
            $produto = [
                'id' => $pidReal,
                'nome' => $nomeProduto,
                'preco' => $precoUnitario,
                'quantidade' => $quantidade,
                'subtotal' => $precoUnitario * $quantidade,
                'peso' => $pesoUnit,
                'foto_principal' => $item['foto_principal'] ?? null,
                'produto_variacao_id' => $item['produto_variacao_id'] ?? null,
                'variacao_descricao' => $item['variacao_descricao'] ?? null,
                'clube_ativo' => $isClubeAtivo ? 1 : 0,
            ];
            
            $items[] = $produto;
            $subtotal += $produto['subtotal'];
            $pesoTotal += $pesoItem;

            $this->debugLog('[CHECKOUT_INDEX] Item: ' . json_encode($item));
            $this->debugLog('[CHECKOUT_INDEX] Produto processado: ' . json_encode($produto));
        }

        // Se o carrinho estiver no DB, usar os totais persistidos (inclui desconto/cashback do Clube)
        $uid = (int) ($usuario['id'] ?? 0);
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId > 0) {
                    $db = $this->carrinhoModel->getConnection();
                    $cols = [];
                    try {
                        $stCols = $db->query('DESCRIBE carrinhos');
                        $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Throwable $e) {
                        $cols = [];
                    }

                    $st = $db->prepare('SELECT * FROM carrinhos WHERE id = ? LIMIT 1');
                    $st->execute([$cartId]);
                    $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

                    if (!empty($row)) {
                        $cartMoedaFromDb = strtoupper(trim((string) ($row['moeda'] ?? '')));
                        if (array_key_exists('subtotal_produtos', $row)) {
                            $subtotalFromDb = (float) ($row['subtotal_produtos'] ?? 0);
                            if ($subtotalFromDb > 0) {
                                $subtotal = $subtotalFromDb;
                            }
                        }
                        if (array_key_exists('peso_total', $row)) {
                            $pesoFromDb = (float) ($row['peso_total'] ?? 0);
                            if ($pesoFromDb > 0) {
                                $pesoTotal = $pesoFromDb;
                            }
                        }

                        if (array_key_exists('taxa_servico', $row)) $taxaServicoFromDb = (float) ($row['taxa_servico'] ?? 0);
                        if (array_key_exists('valor_impostos', $row)) $impostosFromDb = (float) ($row['valor_impostos'] ?? 0);
                        if (array_key_exists('valor_total', $row)) $totalFromDb = (float) ($row['valor_total'] ?? 0);
                        if (array_key_exists('frete_manual', $row) && $row['frete_manual'] !== null && $row['frete_manual'] !== '') {
                            $freteFromDb = (float) $row['frete_manual'];
                        }

                        if (is_array($cols) && in_array('peso_clube_total', $cols, true)) {
                            $pesoClubeTotal = (float) ($row['peso_clube_total'] ?? 0);
                        }
                        if (is_array($cols) && in_array('subtotal_clube', $cols, true)) {
                            $subtotalClube = (float) ($row['subtotal_clube'] ?? 0);
                        }
                        if (is_array($cols) && in_array('desconto_clube', $cols, true)) {
                            $descontoClube = (float) ($row['desconto_clube'] ?? 0);
                        }
                        if (is_array($cols) && in_array('cashback_clube_estimado', $cols, true)) {
                            $cashbackClubeEstimado = (float) ($row['cashback_clube_estimado'] ?? 0);
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // Calcular valores no backend sempre em USD (moeda base), para evitar mistura de moedas.
        // A conversão para BRL é feita no JS da view (assim como no carrinho).
        $frete = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
        $taxaServico = (float) $this->carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
        $impostos = (float) $this->carrinhoModel->calcularImpostos($subtotal, $frete);
        $total = $subtotal + $frete + $taxaServico + $impostos;

        // Se o DB tiver valores válidos, usar; senão manter cálculo atual
        $skipPersistedTotals = (isset($cartMoedaFromDb) && $cartMoedaFromDb === 'BRL');
        if (!$skipPersistedTotals) {
            if (isset($taxaServicoFromDb) && (float) $taxaServicoFromDb > 0) {
                $taxaServico = (float) $taxaServicoFromDb;
            }
            if (isset($impostosFromDb) && (float) $impostosFromDb > 0) {
                $impostos = (float) $impostosFromDb;
            }
        }
        // Frete 0 é valor válido (frete grátis)
        if (isset($freteFromDb) && (float) $freteFromDb >= 0) {
            $frete = (float) $freteFromDb;
        }
        if (!$skipPersistedTotals && isset($totalFromDb) && (float) $totalFromDb > 0) {
            $total = (float) $totalFromDb;
        } else {
            $total = (float) $subtotal + (float) $frete + (float) $taxaServico + (float) $impostos;
        }
        
        $enderecos = $usuario ? $this->usuarioModel->getEnderecos($usuario['id']) : [];
        $enderecoPrincipal = null;
        if (is_array($enderecos) && !empty($enderecos)) {
            foreach ($enderecos as $e) {
                if (!empty($e['principal'])) {
                    $enderecoPrincipal = $e;
                    break;
                }
            }
            if (!$enderecoPrincipal) {
                $enderecoPrincipal = $enderecos[0] ?? null;
            }
        }

        $paisEntrega = strtoupper(trim((string) ($enderecoPrincipal['pais'] ?? ($usuario['pais_residencia'] ?? 'BR'))));
        if ($paisEntrega === '') {
            $paisEntrega = 'BR';
        }

        // Fora do BR, não exigir campos específicos do Brasil.
        $faltando = $this->normalizeMissingForCountry((array) $faltando, (string) $paisEntrega);

        // Ajustar pendências do perfil com base no endereço selecionado (quando existir)
        if (!empty($usuario) && !empty($usuario['id']) && is_array($enderecoPrincipal) && !empty($enderecoPrincipal)) {
            $faltando = $this->normalizeMissingForSelectedAddress((array) $faltando, $enderecoPrincipal);
            $perfilOk = empty($faltando);
        }

        $enderecoPrefill = [
            'pais' => (string) ($enderecoPrincipal['pais'] ?? ($usuario['pais_residencia'] ?? 'BR')),
            'cep' => (string) ($enderecoPrincipal['cep'] ?? ($usuario['cep'] ?? '')),
            'endereco' => (string) ($enderecoPrincipal['endereco'] ?? ($usuario['endereco'] ?? '')),
            'numero' => (string) ($enderecoPrincipal['numero'] ?? ($usuario['numero'] ?? '')),
            'complemento' => (string) ($enderecoPrincipal['complemento'] ?? ($usuario['complemento'] ?? '')),
            'bairro' => (string) ($enderecoPrincipal['bairro'] ?? ($usuario['bairro'] ?? '')),
            'cidade' => (string) ($enderecoPrincipal['cidade'] ?? ($usuario['cidade'] ?? '')),
            'estado' => (string) ($enderecoPrincipal['estado'] ?? ($usuario['estado'] ?? '')),
        ];

        // Impostos (Remessa Conforme/ICMS/II) só devem ser exibidos/cobrados para entrega no Brasil.
        // Para outros países, a tributação local é responsabilidade do cliente.
        $cobraImpostosBR = ($paisEntrega === 'BR');
        if (!$cobraImpostosBR) {
            $impostos = 0.0;
            $total = (float) $subtotal + (float) $frete + (float) $taxaServico;
        }

        $rateBRL = 5.5;
        try {
            $r = (float) $this->carrinhoModel->getTaxaConversao('BRL');
            if ($r > 1.01) {
                $rateBRL = $r;
            }
        } catch (\Exception $e) {
        }

        $this->view('checkout/index', [
            'carrinho' => $carrinho,
            'items' => $items,
            'subtotal' => $subtotal,
            'peso_clube_total' => $pesoClubeTotal,
            'subtotal_clube' => $subtotalClube,
            'desconto_clube' => $descontoClube,
            'cashback_clube_estimado' => $cashbackClubeEstimado,
            'peso_total' => $pesoTotal,
            'usuario' => $usuario,
            'perfil_ok' => $perfilOk,
            'termos_ok' => $termosOk,
            'campos_faltando' => $faltando,
            'enderecos' => $enderecos,
            'endereco_prefill' => $enderecoPrefill,
            'moeda' => $_GET['moeda'] ?? 'BRL', // Obter moeda da URL ou padrão BRL
            'frete' => $frete,
            'taxa_servico' => $taxaServico,
            'impostos' => $impostos,
            'total' => $total,
            'pix_desconto_taxa_servico_percent' => (float) $this->getPixDescontoTaxaServicoPercent(),
            'cobra_impostos_br' => $cobraImpostosBR,
            'frete_gratis' => ($frete == 0),
            'exchange_rates' => [
                'BRL' => $rateBRL,
                'USD' => 1.0,
            ],
            'stripe_publishable_key' => $this->paymentService->getStripePublishableKey(),
            'stripe_enabled' => $this->paymentService->isStripeEnabled(),
            'entrega_fora_br' => !$cobraImpostosBR,
            'mensagem_entrega_fora_br' => 'A entrega para fora do Brasil não inclui impostos brasileiros. A tributação local é responsabilidade do cliente.',
        ]);
    }

    private function atualizarPagamentoNaTabelaPagamentos(int $pedidoId, array $paymentResult, string $gateway): void {
        try {
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE pagamentos');
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                return;
            }

            if (!is_array($cols) || empty($cols) || !in_array('pedido_id', $cols, true)) {
                return;
            }

            $updates = [];
            $params = ['pedido_id' => $pedidoId];

            foreach (['gateway', 'provedor', 'provider'] as $c) {
                if (in_array($c, $cols, true)) {
                    $updates[] = "$c = :gateway";
                    $params['gateway'] = $gateway;
                    break;
                }
            }

            $statusVal = (string) ($paymentResult['status'] ?? '');
            foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                if (!empty($statusVal) && in_array($c, $cols, true)) {
                    $updates[] = "$c = :status_pagamento";
                    $params['status_pagamento'] = $statusVal;
                    break;
                }
            }

            $txVal = (string) ($paymentResult['payment_id'] ?? '');
            foreach (['codigo_transacao', 'transaction_id', 'transacao', 'payment_id'] as $c) {
                if (!empty($txVal) && in_array($c, $cols, true)) {
                    $updates[] = "$c = :transacao";
                    $params['transacao'] = $txVal;
                    break;
                }
            }

            $dataVal = (string) ($paymentResult['paid_at'] ?? '');
            if (empty($dataVal) && (!empty($paymentResult['status']) || !empty($paymentResult['payment_id']))) {
                $dataVal = date('Y-m-d H:i:s');
            }
            foreach (['data_pagamento', 'paid_at', 'data_confirmacao', 'updated_at', 'created_at'] as $c) {
                if (!empty($dataVal) && in_array($c, $cols, true)) {
                    $updates[] = "$c = :data_pagamento";
                    $params['data_pagamento'] = $dataVal;
                    break;
                }
            }

            if (empty($updates)) {
                return;
            }

            $sql = 'UPDATE pagamentos SET ' . implode(', ', $updates) . ' WHERE pedido_id = :pedido_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $e) {
        }
    }
    
    public function processar(Request $request) {
        if (!$this->requireFromCartOrRedirect(true)) {
            return;
        }
        if (!$this->validatePesoMaximoCarrinhoAtivoOrFail(true)) {
            return;
        }

        // Obter usuário logado
        $usuario = $this->authService->getUsuarioLogado();

        // Obter carrinho (DB quando logado, sessão como fallback)
        $carrinho = $this->getCarrinhoForCheckout($usuario);
        
        if (empty($carrinho)) {
            $this->redirect('/produtos');
            return;
        }

        // Obter dados do formulário (precisamos disso para validar perfil considerando endereço selecionado)
        $dados = $request->getParams();

        // Garantir que o campo telefone (hidden) esteja preenchido mesmo quando o usuário digita no campo visível.
        try {
            $telNorm = $this->normalizeTelefoneFromCheckout(is_array($dados) ? $dados : []);
            if ($telNorm !== '') {
                $dados['telefone'] = $telNorm;
            }
        } catch (\Throwable $e) {
        }
        
        // Se está logado, exigir perfil completo + termos aceitos previamente
        if (!empty($usuario) && !empty($usuario['id'])) {
            try {
                $usuarioCompleto = $this->usuarioModel->find((int) $usuario['id']);
                if (is_array($usuarioCompleto) && !empty($usuarioCompleto)) {
                    $faltando = $this->usuarioModel->getMissingRequiredFields($usuarioCompleto);
                    $termosOk = $this->usuarioModel->hasAcceptedTerms($usuarioCompleto);

                    // Se existe endereço selecionado/preenchido no checkout e ele está completo,
                    // não bloquear o checkout por pendências de endereço no perfil do usuário.
                    if (!empty($faltando)) {
                        $selectedAddress = null;
                        $paisEntregaSel = '';

                        $enderecoSel = (int) ($dados['endereco_selecionado'] ?? 0);
                        if ($enderecoSel > 0) {
                            try {
                                $addr = $this->enderecoModel->find($enderecoSel);
                                if (is_array($addr) && !empty($addr)) {
                                    $uidAddr = (int) ($addr['usuario_id'] ?? 0);
                                    if ($uidAddr === (int) $usuario['id']) {
                                        $selectedAddress = $addr;
                                        $paisEntregaSel = (string) ($addr['pais'] ?? '');
                                    }
                                }
                            } catch (\Exception $e) {
                            }
                        }

                        if ($selectedAddress === null) {
                            $selectedAddress = [
                                'pais' => (string) ($dados['pais'] ?? ''),
                                'cep' => (string) ($dados['cep'] ?? ''),
                                'endereco' => (string) ($dados['endereco'] ?? ''),
                                'numero' => (string) ($dados['numero'] ?? ''),
                                'bairro' => (string) ($dados['bairro'] ?? ''),
                                'cidade' => (string) ($dados['cidade'] ?? ''),
                                'estado' => (string) ($dados['estado'] ?? ($dados['estado_text'] ?? '')),
                            ];
                            $paisEntregaSel = (string) ($dados['pais'] ?? '');
                        }

                        if ($paisEntregaSel === '' && is_array($selectedAddress)) {
                            $paisEntregaSel = (string) ($selectedAddress['pais'] ?? '');
                        }
                        if ($paisEntregaSel === '') {
                            $paisEntregaSel = 'BR';
                        }

                        // Fora do BR, não bloquear por numero/bairro (campos específicos do Brasil)
                        $faltando = $this->normalizeMissingForCountry((array) $faltando, $paisEntregaSel);

                        $faltando = $this->normalizeMissingForSelectedAddress((array) $faltando, $selectedAddress);
                    }

                    if (!$termosOk || !empty($faltando)) {
                        $parts = [];
                        if (!$termosOk) {
                            $parts[] = 'aceitar os termos';
                        }
                        if (!empty($faltando)) {
                            $parts[] = 'completar seus dados: ' . implode(', ', $faltando);
                        }
                        $this->json([
                            'error' => 'Para finalizar, você precisa ' . implode(' e ', $parts) . '. Atualize em /meus-dados'
                        ], 400);
                        return;
                    }
                }
            } catch (\Exception $e) {
            }
        }
        
        // Resto do processamento do pedido...
        $this->debugLog('[CHECKOUT] processar() chamado - INICIO');
        
        $dados = $request->getParams();
        $this->debugLog('[CHECKOUT] Dados recebidos: ' . json_encode($dados));
        
        // Validar consentimento legal
        if (empty($dados['consentimento_legal'])) {
            $this->debugLog('[CHECKOUT] Consentimento legal nao aceito');
            $this->json(['error' => 'É necessário aceitar os termos para continuar'], 400);
            return;
        }

        if (!empty($usuario) && !empty($usuario['id'])) {
            try {
                $colsU = [];
                $stmtColsU = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                $colsU = $stmtColsU ? $stmtColsU->fetchAll(\PDO::FETCH_COLUMN) : [];
                $upd = [];
                if (is_array($colsU) && in_array('termos_aceitos_em', $colsU, true)) {
                    $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                }
                if (is_array($colsU) && in_array('termos_aceitos_ip', $colsU, true)) {
                    $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                }
                if (is_array($colsU) && in_array('termos_versao', $colsU, true)) {
                    $upd['termos_versao'] = '1.0';
                }
                if (!empty($upd)) {
                    $this->usuarioModel->update((int) $usuario['id'], $upd);
                }
            } catch (\Exception $e) {
            }
        }
        
        // Validar dados obrigatórios
        $erros = $this->validarDadosCheckout($dados);
        if (!empty($erros)) {
            $this->debugLog('[CHECKOUT] Erros de validacao: ' . implode(', ', $erros));
            $this->json(['error' => implode(', ', $erros)], 400);
            return;
        }
        
        // Obter carrinho novamente (DB quando logado, sessão como fallback)
        $carrinho = $this->getCarrinhoForCheckout($usuario);
        $this->debugLog('[CHECKOUT] Carrinho encontrado: ' . json_encode($carrinho));
        
        if (empty($carrinho)) {
            $this->debugLog('[CHECKOUT] Carrinho vazio');
            $this->json(['error' => 'Carrinho vazio'], 400);
            return;
        }

        // Se algum item (principalmente temporário da assessoria) expirou e foi removido do banco, bloquear checkout
        try {
            $db = \Config\Database::getConnection();

            $produtoPkCandidates = ['id'];
            try {
                $stCols = $db->query('DESCRIBE produtos');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (is_array($cols) && in_array('produto_id', $cols, true) && !in_array('produto_id', $produtoPkCandidates, true)) {
                    $produtoPkCandidates[] = 'produto_id';
                }
            } catch (\Throwable $e) {
            }

            $removedExpired = false;
            foreach ($carrinho as $k => $item) {
                $pid = $item['produto_id'] ?? null;
                if (empty($pid)) {
                    continue;
                }
                try {
                    $exists = false;
                    foreach ($produtoPkCandidates as $pkCol) {
                        $stmtP = $db->prepare('SELECT 1 FROM produtos WHERE ' . $pkCol . ' = ? LIMIT 1');
                        $stmtP->execute([(int) $pid]);
                        $exists = (bool) $stmtP->fetchColumn();
                        if ($exists) {
                            break;
                        }
                    }
                    if (!$exists) {
                        unset($_SESSION['carrinho'][$k]);
                        $removedExpired = true;
                    }
                } catch (\Exception $e) {
                }
            }

            if ($removedExpired) {
                $this->json([
                    'error' => 'Alguns itens do carrinho expiraram e foram removidos. Se eram itens da Assessoria, reprocessse o orçamento para gerar novos valores e produtos.'
                ], 400);
                return;
            }
        } catch (\Exception $e) {
        }

        // Revalidar disponibilidade do carrinho no momento da finalização (status/ativo/estoque)
        try {
            $itensIndisponiveis = $this->validarDisponibilidadeCarrinhoNoBanco((array) $carrinho);
            if (!empty($itensIndisponiveis)) {
                $this->json([
                    'error' => 'Alguns produtos do seu carrinho não estão mais disponíveis. Atualize o carrinho e tente novamente.',
                    'itens_indisponiveis' => $itensIndisponiveis,
                ], 400);
                return;
            }
        } catch (\Throwable $e) {
            $this->json([
                'error' => 'Não foi possível validar a disponibilidade do carrinho. Tente novamente.',
            ], 500);
            return;
        }
        
        try {
            // Obter usuário logado
            $usuario = $this->authService->getUsuarioLogado();
            $this->debugLog('[CHECKOUT] Usuario: ' . ($usuario ? $usuario['email'] : 'Nao logado'));
            
            // Criar pedido (idempotente)
            $this->debugLog('[CHECKOUT] Chamando criarPedido()...');
            $pedidoCreateResult = $this->criarPedido($dados, $carrinho, $usuario);
            $pedidoId = is_array($pedidoCreateResult) ? (int) ($pedidoCreateResult['pedido_id'] ?? 0) : (int) $pedidoCreateResult;
            $reused = is_array($pedidoCreateResult) ? (bool) ($pedidoCreateResult['reused'] ?? false) : false;
            $idemHash = is_array($pedidoCreateResult) ? (string) ($pedidoCreateResult['idem'] ?? '') : '';
            $this->debugLog('[CHECKOUT] Pedido retornado com ID: ' . $pedidoId . ' (reused=' . ($reused ? '1' : '0') . ')' . ($idemHash !== '' ? (' idem=' . $idemHash) : ''));
            
            if ($pedidoId) {
                // Se reaproveitou pedido e ele já tem itens, não repetir inserts
                $jaTemItens = $reused ? $this->pedidoJaTemItens($pedidoId) : false;
                if (!$reused || !$jaTemItens) {
                    // Salvar itens do pedido
                    $this->debugLog('[CHECKOUT] Salvando itens do pedido...');
                    $this->salvarItensPedido($pedidoId, $carrinho);
                    $this->debugLog('[CHECKOUT] Itens do pedido salvos');

                    // Salvar dados do cliente
                    $this->debugLog('[CHECKOUT] Salvando dados do cliente...');
                    $this->salvarDadosCliente($pedidoId, $dados, $usuario);
                    $this->debugLog('[CHECKOUT] Dados do cliente salvos');
                } else {
                    $this->debugLog('[CHECKOUT] Pedido reutilizado já possui itens; pulando salvarItensPedido/salvarDadosCliente');
                }

                // Persistir forma_pagamento no pedido (alguns schemas exibem isso no admin)
                try {
                    $dbFp = \Config\Database::getConnection();
                    $colsPed = [];
                    try {
                        $stmtColsPed = $dbFp->query('DESCRIBE pedidos');
                        $colsPed = $stmtColsPed->fetchAll(\PDO::FETCH_COLUMN);
                    } catch (\Exception $e) {
                    }

                    if (is_array($colsPed) && in_array('forma_pagamento', $colsPed, true)) {
                        $forma = (string) ($dados['forma_pagamento'] ?? '');
                        if ($forma !== '') {
                            $stmtUpdFp = $dbFp->prepare('UPDATE pedidos SET forma_pagamento = ? WHERE id = ?');
                            $stmtUpdFp->execute([$forma, $pedidoId]);
                        }
                    }
                } catch (\Exception $e) {
                }

                // Registrar pagamento + notificação apenas quando não reutilizado
                if (!$reused) {
                    // Registrar pagamento (status inicial)
                    $this->registrarPagamentoPedido($pedidoId, $dados);

                    // Notificar criação do pedido
                    try {
                        $this->pedidoModel->dispararEvento('novo_pedido', (int) $pedidoId);
                    } catch (\Exception $e) {
                    }
                } else {
                    $this->debugLog('[CHECKOUT] Pedido reutilizado; pulando registrarPagamentoPedido/dispararEvento');
                }

                // Processar pagamento
                // BRL: AppMax no backend
                // USD: Stripe Elements no frontend (não coletar dados de cartão aqui)
                $pedidoRowPay = [];
                try {
                    $dbPay = \Config\Database::getConnection();
                    $stmtPedidoPay = $dbPay->prepare('SELECT id, total, moeda, taxa_conversao, numero_pedido FROM pedidos WHERE id = ? LIMIT 1');
                    $stmtPedidoPay->execute([$pedidoId]);
                    $pedidoRowPay = $stmtPedidoPay->fetch(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) {
                    $pedidoRowPay = [];
                }

                $formaSelecionada = strtolower(trim((string) ($dados['forma_pagamento'] ?? '')));

                if (!$reused && $formaSelecionada === 'carteira') {
                    $valorPedido = (float) ($pedidoRowPay['total'] ?? 0);
                    $moedaPedidoWallet = strtoupper(trim((string) ($dados['moeda'] ?? ($pedidoRowPay['moeda'] ?? 'BRL'))));
                    if (!in_array($moedaPedidoWallet, ['BRL', 'USD'], true)) {
                        $moedaPedidoWallet = strtoupper(trim((string) ($pedidoRowPay['moeda'] ?? 'BRL')));
                    }
                    if (!in_array($moedaPedidoWallet, ['BRL', 'USD'], true)) {
                        $moedaPedidoWallet = 'BRL';
                    }

                    // Garantir que o pedido reflita a moeda que será debitada na carteira
                    try {
                        $dbUpdCur = \Config\Database::getConnection();
                        $colsPedCur = [];
                        try {
                            $stColsPedCur = $dbUpdCur->query('DESCRIBE pedidos');
                            $colsPedCur = $stColsPedCur ? ($stColsPedCur->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsPedCur = [];
                        }

                        $setCur = [];
                        $pCur = [];
                        if (is_array($colsPedCur) && in_array('moeda', $colsPedCur, true)) {
                            $setCur[] = 'moeda = ?';
                            $pCur[] = $moedaPedidoWallet;
                        }
                        if (is_array($colsPedCur) && in_array('taxa_conversao', $colsPedCur, true)) {
                            // Se está pagando em USD, considerar taxa 1 como padrão; BRL também.
                            $setCur[] = 'taxa_conversao = COALESCE(taxa_conversao, 1)';
                        }
                        if (!empty($setCur)) {
                            $pCur[] = (int) $pedidoId;
                            $stUpdCur = $dbUpdCur->prepare('UPDATE pedidos SET ' . implode(', ', $setCur) . ' WHERE id = ?');
                            $stUpdCur->execute($pCur);
                        }
                    } catch (\Exception $e) {
                    }
                    $payResult = $this->debitarCarteiraParaPedido((int) ($usuario['id'] ?? 0), (int) $pedidoId, $valorPedido, $moedaPedidoWallet);
                    $gateway = 'carteira';
                    $this->atualizarPagamentoNoPedido((int) $pedidoId, $payResult, $gateway);
                    $this->atualizarPagamentoNaTabelaPagamentos((int) $pedidoId, $payResult, $gateway);

                    try {
                        if (strtoupper((string) ($payResult['status'] ?? '')) === 'PAID') {
                            $this->paymentService->creditarCashbackClubePorPedidoPago((int) $pedidoId);
                        }
                    } catch (\Exception $e) {
                    }
                }

                $moedaPedidoPay = strtoupper(trim((string) ($pedidoRowPay['moeda'] ?? 'BRL')));
                if ($moedaPedidoPay === '') {
                    $moedaPedidoPay = 'BRL';
                }
                $shouldTrySplit = ($formaSelecionada !== 'carteira' && $moedaPedidoPay === 'BRL' && in_array($formaSelecionada, ['pix', 'boleto', 'cartao_credito', 'cartao_debito'], true));
                // Se o pedido foi reutilizado, ainda assim tentar gerar split caso ainda não exista split persistido.
                if ($shouldTrySplit && $reused) {
                    $shouldTrySplit = !$this->pedidoJaTemSplitPagamentos((int) $pedidoId);
                }

                $pickNonEmpty = static function(...$vals): string {
                    foreach ($vals as $v) {
                        $s = trim((string) ($v ?? ''));
                        if ($s !== '') return $s;
                    }
                    return '';
                };

                if ($shouldTrySplit) {
                    try {
                        // Split BRL:
                        // - produto via Câmbio Real (link hospedado)
                        // - taxa de serviço + impostos via AppMax
                        if (in_array($formaSelecionada, ['pix', 'boleto', 'cartao_credito', 'cartao_debito'], true)) {
                            $pedidoNorm = [];
                            try {
                                $pedidoNorm = $this->pedidoModel->getComDetalhes((int) $pedidoId);
                            } catch (\Exception $e) {
                                $pedidoNorm = [];
                            }

                            $totalBrl = (float) (($pedidoNorm['total'] ?? null) !== null ? $pedidoNorm['total'] : ($pedidoRowPay['total'] ?? 0));
                            $taxaServico = (float) ($pedidoNorm['taxa_servico'] ?? 0);
                            if ($taxaServico < 0) $taxaServico = 0.0;
                            $valorImposto = 0.0;
                            try {
                                $colsPed = [];
                                $dbCols = \Config\Database::getConnection();
                                $stCols = $dbCols->query('DESCRIBE pedidos');
                                $colsPed = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                                $colImp = '';
                                if (is_array($colsPed)) {
                                    foreach (['valor_impostos', 'impostos'] as $c) {
                                        if (in_array($c, $colsPed, true)) {
                                            $colImp = $c;
                                            break;
                                        }
                                    }
                                }
                                if ($colImp !== '') {
                                    $stImp = $dbCols->prepare('SELECT ' . $colImp . ' AS impostos FROM pedidos WHERE id = ? LIMIT 1');
                                    $stImp->execute([(int) $pedidoId]);
                                    $rowImp = $stImp->fetch(\PDO::FETCH_ASSOC) ?: [];
                                    $valorImposto = (float) ($rowImp['impostos'] ?? 0);
                                }
                            } catch (\Exception $e) {
                                $valorImposto = 0.0;
                            }
                            if ($valorImposto < 0) $valorImposto = 0.0;
                            $valorImposto = round((float) $valorImposto, 2);

                            // Tenta obter subtotal real dos produtos (sem taxa/impostos), pois em alguns cenários
                            // o campo 'total' do pedido pode já ser o subtotal, e subtrair taxa/impostos gera valor menor.
                            $subtotalProdutos = 0.0;
                            $hasSubtotalProdutos = false;
                            try {
                                foreach (['subtotal', 'subtotal_produtos', 'valor_produtos', 'total_produtos'] as $k) {
                                    if (!$hasSubtotalProdutos && array_key_exists($k, $pedidoNorm) && $pedidoNorm[$k] !== null) {
                                        $v = (float) $pedidoNorm[$k];
                                        if ($v > 0) {
                                            $subtotalProdutos = $v;
                                            $hasSubtotalProdutos = true;
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                $hasSubtotalProdutos = false;
                            }

                            if (!$hasSubtotalProdutos) {
                                try {
                                    $dbItens = \Config\Database::getConnection();
                                    $itensTable = '';
                                    $stmtT = $dbItens->query("SHOW TABLES LIKE 'pedido_itens'");
                                    $has1 = $stmtT ? ((int) $stmtT->fetchColumn() > 0) : false;
                                    if ($has1) {
                                        $itensTable = 'pedido_itens';
                                    } else {
                                        $stmtT2 = $dbItens->query("SHOW TABLES LIKE 'pedido_items'");
                                        $has2 = $stmtT2 ? ((int) $stmtT2->fetchColumn() > 0) : false;
                                        if ($has2) {
                                            $itensTable = 'pedido_items';
                                        }
                                    }

                                    if ($itensTable !== '') {
                                        $cols = [];
                                        $stColsItens = $dbItens->query('DESCRIBE ' . $itensTable);
                                        $cols = $stColsItens ? ($stColsItens->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

                                        $colPedidoId = in_array('pedido_id', $cols, true) ? 'pedido_id' : '';
                                        $colQtd = '';
                                        foreach (['quantidade', 'qty', 'qtd'] as $c) {
                                            if ($colQtd === '' && in_array($c, $cols, true)) {
                                                $colQtd = $c;
                                            }
                                        }
                                        $colValor = '';
                                        foreach (['valor', 'preco', 'preco_unitario', 'price', 'unit_price'] as $c) {
                                            if ($colValor === '' && in_array($c, $cols, true)) {
                                                $colValor = $c;
                                            }
                                        }

                                        if ($colPedidoId !== '' && $colQtd !== '' && $colValor !== '') {
                                            $stSum = $dbItens->prepare('SELECT SUM(COALESCE(' . $colValor . ',0) * COALESCE(' . $colQtd . ',0)) AS subtotal FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' = ?');
                                            $stSum->execute([(int) $pedidoId]);
                                            $sv = $stSum->fetchColumn();
                                            $subtotalProdutos = (float) ($sv ?: 0);
                                            if ($subtotalProdutos > 0) {
                                                $hasSubtotalProdutos = true;
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    $hasSubtotalProdutos = false;
                                }
                            }

                            if ($hasSubtotalProdutos) {
                                $valorProduto = round(max(0.0, $subtotalProdutos), 2);
                            } else {
                                $valorProduto = round(max(0.0, $totalBrl - $taxaServico - $valorImposto), 2);
                            }
                            $valorTaxa = round(max(0.0, $taxaServico), 2);
                            $valorAppmax = round(max(0.0, $valorTaxa + $valorImposto), 2);

                            if ($valorProduto <= 0 && $valorAppmax <= 0) {
                                throw new \Exception('Valores inválidos para split');
                            }

                            $descricaoProduto = 'Pedido #' . (string) ($pedidoRowPay['numero_pedido'] ?? $pedidoId) . ' (produtos)';
                            $descricaoTaxa = 'Pedido #' . (string) ($pedidoRowPay['numero_pedido'] ?? $pedidoId) . ' (taxas e impostos)';
                            $payer = [];
                            $payerEmail = '';
                            try {
                                $payerEmail = $pickNonEmpty(
                                    $dados['email'] ?? '',
                                    $pedidoRowPay['cliente_email'] ?? '',
                                    $pedidoRowPay['email'] ?? '',
                                    $pedidoRowPay['customer_email'] ?? '',
                                    $usuario['email'] ?? ''
                                );
                            } catch (\Throwable $e) {
                                $payerEmail = '';
                            }
                            if ($payerEmail !== '') {
                                $payer['email'] = $payerEmail;
                            }

                            $cr = null;
                            if ($valorProduto > 0) {
                                if ($formaSelecionada === 'pix') {
                                    $tx = (float) ($pedidoRowPay['taxa_conversao'] ?? 0);
                                    if ($tx <= 1.01) {
                                        try {
                                            $dbTx = \Config\Database::getConnection();
                                            $stTx = $dbTx->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                                            $stTx->execute();
                                            $v = (string) ($stTx->fetchColumn() ?: '0');
                                            $tx2 = (float) str_replace(',', '.', $v);
                                            if ($tx2 > 1.01) {
                                                $tx = $tx2;
                                            }
                                        } catch (\Exception $e) {
                                        }
                                    }
                                    if ($tx <= 0) {
                                        $tx = 1.0;
                                    }

                                    $amountUsd = round(((float) $valorProduto) / (float) $tx, 2);
                                    if ($amountUsd <= 0) {
                                        throw new \Exception('Valor inválido para Câmbio Real (USD)');
                                    }

                                    $client = [
                                        'name' => (string) $pickNonEmpty($dados['nome'] ?? '', $pedidoRowPay['cliente_nome'] ?? '', $usuario['nome'] ?? '', 'Cliente'),
                                        'email' => (string) $pickNonEmpty($dados['email'] ?? '', $pedidoRowPay['cliente_email'] ?? '', $pedidoRowPay['email'] ?? '', $pedidoRowPay['customer_email'] ?? '', $usuario['email'] ?? ''),
                                        'document' => (string) $pickNonEmpty($dados['documento'] ?? '', $pedidoRowPay['cliente_documento'] ?? '', $pedidoRowPay['documento'] ?? '', $usuario['documento'] ?? ''),
                                        'birth_date' => (string) $pickNonEmpty($dados['data_nascimento'] ?? '', $usuario['data_nascimento'] ?? ''),
                                        'phone' => (string) $pickNonEmpty($dados['telefone'] ?? '', $pedidoRowPay['cliente_telefone'] ?? '', $usuario['telefone'] ?? '', $usuario['celular'] ?? ''),
                                        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                                        'address' => [
                                            'state' => (string) ($dados['estado'] ?? ''),
                                            'city' => (string) ($dados['cidade'] ?? ''),
                                            'zip_code' => (string) ($dados['cep'] ?? ''),
                                            'district' => (string) ($dados['bairro'] ?? ''),
                                            'street' => (string) ($dados['endereco'] ?? ''),
                                            'number' => (string) ($dados['numero'] ?? ''),
                                        ],
                                    ];

                                    $cr = $this->paymentService->createCambioRealPixPaymentProduto((int) $pedidoId, (float) $amountUsd, (float) $valorProduto, (string) $descricaoProduto, $client);
                                    if (empty($cr['success'])) {
                                        throw new \Exception((string) ($cr['error'] ?? 'Falha ao gerar PIX Câmbio Real (produto)'));
                                    }
                                } elseif ($formaSelecionada === 'boleto') {
                                    $client = [
                                        'name' => (string) ($dados['nome'] ?? ($usuario['nome'] ?? 'Cliente')),
                                        'email' => (string) ($dados['email'] ?? ($usuario['email'] ?? '')),
                                        'document' => (string) ($dados['documento'] ?? ($usuario['documento'] ?? '')),
                                        'birth_date' => (string) ($dados['data_nascimento'] ?? ($usuario['data_nascimento'] ?? '')),
                                        'phone' => (string) ($dados['telefone'] ?? ($usuario['telefone'] ?? ($usuario['celular'] ?? ''))),
                                        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                                        'address' => [
                                            'state' => (string) ($dados['estado'] ?? ''),
                                            'city' => (string) ($dados['cidade'] ?? ''),
                                            'zip_code' => (string) ($dados['cep'] ?? ''),
                                            'district' => (string) ($dados['bairro'] ?? ''),
                                            'street' => (string) ($dados['endereco'] ?? ''),
                                            'number' => (string) ($dados['numero'] ?? ''),
                                        ],
                                    ];
                                    $cr = $this->paymentService->createCambioRealDirectPaymentProdutoBoleto((int) $pedidoId, (float) $valorProduto, (string) $descricaoProduto, $client);
                                    if (empty($cr['success'])) {
                                        throw new \Exception((string) ($cr['error'] ?? 'Falha ao gerar boleto Câmbio Real (produto)'));
                                    }
                                } else {
                                    $token = (string) ($dados['cambioreal_card_token'] ?? '');
                                    $brand = (string) ($dados['cambioreal_card_brand'] ?? '');
                                    $bin = (string) ($dados['cambioreal_card_bin'] ?? '');
                                    $dfpId = (string) ($dados['cambioreal_card_dfp_id'] ?? '');
                                    $cardType = (string) ($dados['cambioreal_card_type'] ?? 'credit');

                                    $tx = (float) ($pedidoRowPay['taxa_conversao'] ?? 0);
                                    if ($tx <= 1.01) {
                                        try {
                                            $dbTx = \Config\Database::getConnection();
                                            $stTx = $dbTx->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                                            $stTx->execute();
                                            $v = (string) ($stTx->fetchColumn() ?: '0');
                                            $tx2 = (float) str_replace(',', '.', $v);
                                            if ($tx2 > 1.01) {
                                                $tx = $tx2;
                                            }
                                        } catch (\Exception $e) {
                                        }
                                    }
                                    if ($tx <= 0) {
                                        $tx = 1.0;
                                    }

                                    $amountUsd = round(((float) $valorProduto) / (float) $tx, 2);
                                    if ($amountUsd <= 0) {
                                        throw new \Exception('Valor inválido para Câmbio Real (USD)');
                                    }

                                    $client = [
                                        'name' => (string) ($dados['nome'] ?? ($usuario['nome'] ?? 'Cliente')),
                                        'email' => (string) ($dados['email'] ?? ($usuario['email'] ?? '')),
                                        'document' => (string) ($dados['documento'] ?? ($usuario['documento'] ?? '')),
                                        'birth_date' => (string) ($dados['data_nascimento'] ?? ($usuario['data_nascimento'] ?? '')),
                                        'phone' => (string) ($dados['telefone'] ?? ($usuario['telefone'] ?? ($usuario['celular'] ?? ''))),
                                        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                                        'address' => [
                                            'state' => (string) ($dados['estado'] ?? ''),
                                            'city' => (string) ($dados['cidade'] ?? ''),
                                            'zip_code' => (string) ($dados['cep'] ?? ''),
                                            'district' => (string) ($dados['bairro'] ?? ''),
                                            'street' => (string) ($dados['endereco'] ?? ''),
                                            'number' => (string) ($dados['numero'] ?? ''),
                                        ],
                                    ];

                                    $card = [
                                        'token' => trim($token),
                                        'brand' => trim($brand),
                                        'bin' => trim($bin),
                                        'dfp_id' => trim($dfpId),
                                        'holder' => (string) ($dados['card_holder_name'] ?? ''),
                                        'installments' => (function ($v) {
                                            $i = (int) $v;
                                            if ($i < 1) $i = 1;
                                            if ($i > 12) $i = 12;
                                            return $i;
                                        })($dados['installments'] ?? 1),
                                        'type' => $cardType,
                                    ];

                                    $cr = $this->paymentService->createCambioRealDirectPaymentProdutoCartao((int) $pedidoId, (float) $valorProduto, (float) $amountUsd, (string) $descricaoProduto, $client, $card);
                                    if (empty($cr['success'])) {
                                        throw new \Exception((string) ($cr['error'] ?? 'Falha ao gerar pagamento Câmbio Real (produto)'));
                                    }
                                }
                            }

                            $taxa = null;
                            if ($valorAppmax > 0) {
                                $billingType = 'BOLETO';
                                if ($formaSelecionada === 'pix') {
                                    $billingType = 'PIX';
                                } elseif (in_array($formaSelecionada, ['cartao_credito', 'cartao_debito'], true)) {
                                    $billingType = 'CREDIT_CARD';
                                }
                                $clienteSplit = [];
                                $clienteSplit['nome'] = (string) $pickNonEmpty($dados['nome'] ?? '', $pedidoRowPay['cliente_nome'] ?? '', $usuario['nome'] ?? '', 'Cliente');
                                $clienteSplit['email'] = (string) $pickNonEmpty($dados['email'] ?? '', $pedidoRowPay['cliente_email'] ?? '', $pedidoRowPay['email'] ?? '', $pedidoRowPay['customer_email'] ?? '', $usuario['email'] ?? '');
                                $clienteSplit['telefone'] = (string) $pickNonEmpty($dados['telefone'] ?? '', $pedidoRowPay['cliente_telefone'] ?? '', $usuario['telefone'] ?? '', $usuario['celular'] ?? '');
                                $clienteSplit['documento'] = (string) $pickNonEmpty($dados['documento'] ?? '', $pedidoRowPay['cliente_documento'] ?? '', $pedidoRowPay['documento'] ?? '', $usuario['documento'] ?? '');

                                if ($billingType === 'CREDIT_CARD') {
                                    $clienteSplit['card_holder_name'] = (string) ($dados['card_holder_name'] ?? '');
                                    $clienteSplit['card_number'] = (string) ($dados['card_number'] ?? '');
                                    $clienteSplit['card_expiry_month'] = (string) ($dados['card_expiry_month'] ?? '');
                                    $clienteSplit['card_expiry_year'] = (string) ($dados['card_expiry_year'] ?? '');
                                    $clienteSplit['card_cvv'] = (string) ($dados['card_cvv'] ?? '');
                                }

                                $taxa = $this->gerarCobrancaAppmaxTaxaServicoSplit((int) $pedidoId, $billingType, (float) $valorAppmax, $clienteSplit, (string) $descricaoTaxa, 'taxa_servico');
                                if (empty($taxa['success'])) {
                                    throw new \Exception((string) ($taxa['error'] ?? 'Falha ao gerar pagamento AppMax (taxa de serviço)'));
                                }

                                $appmaxPaymentId = (string) ($taxa['payment_id'] ?? '');
                                if ($appmaxPaymentId !== '' && $valorImposto > 0) {
                                    $this->paymentService->registrarPedidoPagamentoSplit([
                                        'pedido_id' => (int) $pedidoId,
                                        'componente' => 'imposto',
                                        'gateway' => 'appmax',
                                        'metodo' => strtolower((string) $billingType),
                                        'moeda' => 'BRL',
                                        'valor' => (float) $valorImposto,
                                        'payment_id' => $appmaxPaymentId,
                                        'status' => 'pending',
                                        'gateway_status' => 'SPLIT_ITEM',
                                    ]);
                                }
                            }

                            $dados['__split'] = [
                                'success' => true,
                                'split' => true,
                                'moeda' => 'BRL',
                                'produto' => $cr,
                                'taxa' => $taxa,
                                'imposto' => [
                                    'success' => true,
                                    'gateway' => 'appmax',
                                    'metodo' => $formaSelecionada,
                                    'moeda' => 'BRL',
                                    'valor' => (float) $valorImposto,
                                    'payment_id' => (string) (is_array($taxa) ? ($taxa['payment_id'] ?? '') : ''),
                                    'status' => 'pending',
                                ],
                            ];
                        } else {
                            // Fluxo legado (AppMax total) - cartao_credito permanece aqui por enquanto.
                            $payResult = $this->processarPagamentoPedido((int) $pedidoId, $dados, $usuario ?? [], $pedidoRowPay);
                            $formaSel = strtolower(trim((string) ($dados['forma_pagamento'] ?? '')));
                            // BRL: PaymentService usa AppMax por padrão (PIX/BOLETO/CC/CD)
                            $gateway = 'appmax';
                            $this->atualizarPagamentoNoPedido((int) $pedidoId, $payResult, $gateway);
                            $this->atualizarPagamentoNaTabelaPagamentos((int) $pedidoId, $payResult, $gateway);

                            // Persistir QR/payload do PIX em pedido_pagamentos para garantir exibição na conclusão,
                            // mesmo quando o schema de pedidos não possui colunas payment_pix_*.
                            if ($gateway === 'appmax' && $formaSel === 'pix') {
                                try {
                                    $pix = (isset($payResult['pix']) && is_array($payResult['pix'])) ? $payResult['pix'] : null;
                                    $this->paymentService->registrarPedidoPagamentoSplit([
                                        'pedido_id' => (int) $pedidoId,
                                        'componente' => 'pagamento',
                                        'gateway' => 'appmax',
                                        'metodo' => 'pix',
                                        'moeda' => 'BRL',
                                        'valor' => (float) ($pedidoRowPay['total'] ?? 0),
                                        'payment_id' => (string) ($payResult['payment_id'] ?? ''),
                                        'status' => (string) ($payResult['status'] ?? 'pending'),
                                        'invoice_url' => (string) ($payResult['invoiceUrl'] ?? ''),
                                        'bank_slip_url' => (string) ($payResult['bankSlipUrl'] ?? ''),
                                        'digitable_line' => (string) ($payResult['digitableLine'] ?? ''),
                                        'pix_encoded_image' => is_array($pix) ? (string) ($pix['encodedImage'] ?? '') : '',
                                        'pix_payload' => is_array($pix) ? (string) ($pix['payload'] ?? '') : '',
                                    ]);
                                } catch (\Exception $e) {
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Erro ao processar pagamento: ' . $e->getMessage());
                    }
                }

                // Se veio da Assessoria (/assessoria), vincular orçamento ao pedido (pago)
                // e colocar pedido em pendência de conferência.
                $orcId = 0;
                try {
                    $orcId = (int) ($_SESSION['checkout_assessoria_orcamento_id'] ?? 0);
                } catch (\Exception $e) {
                    $orcId = 0;
                }

                if ($orcId > 0) {
                    try {
                        $orcModel = new AssessoriaOrcamento();
                        $rowOrc = $orcModel->find($orcId);
                        if (is_array($rowOrc) && !empty($rowOrc['id'])) {
                            $orcModel->update($orcId, [
                                'status' => 'pago',
                                'pedido_id' => (int) $pedidoId,
                                'paid_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    } catch (\Exception $e) {
                    }

                    // Pedidos de Assessoria entram como pendentes de conferência (quando colunas existirem)
                    try {
                        $dbConf = \Config\Database::getConnection();
                        $colsPed = [];
                        try {
                            $stmtColsPed = $dbConf->query('DESCRIBE pedidos');
                            $colsPed = $stmtColsPed ? ($stmtColsPed->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsPed = [];
                        }

                        $set = [];
                        $params = [':id' => (int) $pedidoId];
                        if (is_array($colsPed) && in_array('origem_pedido', $colsPed, true)) {
                            $set[] = 'origem_pedido = :origem_pedido';
                            $params[':origem_pedido'] = 'redirecionamento';
                        }
                        if (is_array($colsPed) && in_array('status_conferencia', $colsPed, true)) {
                            $set[] = 'status_conferencia = :status_conferencia';
                            $params[':status_conferencia'] = 'pendente';
                        }
                        if (!empty($set)) {
                            $st = $dbConf->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                            $st->execute($params);
                        }
                    } catch (\Exception $e) {
                    }

                    try {
                        unset($_SESSION['checkout_assessoria_orcamento_id']);
                    } catch (\Exception $e) {
                    }
                }
                
                // Limpar carrinho apenas quando BRL (Asaas) for processado aqui.
                // Para USD (Stripe Elements), o carrinho é limpo após confirmação do pagamento.
                $moedaPedidoClear = strtoupper(trim((string) ($pedidoRowPay['moeda'] ?? 'BRL')));
                if ($moedaPedidoClear === '') {
                    $moedaPedidoClear = 'BRL';
                }
                $isStripeFlow = ($moedaPedidoClear !== 'BRL' && $formaSelecionada === 'cartao_credito');
                $shouldClearCartNow = (!$isStripeFlow) && (
                    $formaSelecionada === 'carteira'
                    || $moedaPedidoClear === 'BRL'
                    || in_array($formaSelecionada, ['pix', 'boleto'], true)
                );

                if ($shouldClearCartNow) {
                    // Limpar carrinho no DB (usuário logado) e na sessão (fallback)
                    try {
                        $uid = (int) ($usuario['id'] ?? 0);
                        if ($uid > 0) {
                            $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                            if ($cartId > 0) {
                                $this->carrinhoModel->limparCarrinho($cartId);
                            }
                        }
                    } catch (\Throwable $e) {
                    }

                    try {
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                    } catch (\Throwable $e) {
                    }

                    unset($_SESSION['carrinho']);
                    unset($_SESSION['carrinho_itens_ativos']);
                    $this->debugLog('[CHECKOUT] Carrinho limpo');
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Pedido criado com sucesso',
                    'pedido_id' => $pedidoId,
                    'redirect' => '/checkout/conclusao/' . $pedidoId,
                    'stripe_required' => ($formaSelecionada !== 'carteira' && strtoupper(trim((string) ($pedidoRowPay['moeda'] ?? 'BRL'))) !== 'BRL'),
                ];

                // Se geramos split, devolver detalhes para o frontend (para exibição imediata se necessário)
                if (isset($dados['__split']) && is_array($dados['__split'])) {
                    $response['split'] = true;
                    $response['split_pagamentos'] = $dados['__split'];
                }
                
                $this->debugLog('[CHECKOUT] Resposta sucesso: ' . json_encode($response));
                $this->json($response);
                // Consumir a janela de checkout (obriga passar novamente pelo carrinho em nova tentativa)
                try {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    unset($_SESSION['checkout_from_cart_at']);
                } catch (\Throwable $e) {
                }
            } else {
                $this->debugLog('[CHECKOUT] Erro ao criar pedido - ID retornado: ' . $pedidoId);
                $this->json(['error' => 'Erro ao criar pedido'], 500);
            }
        } catch (\Exception $e) {
            $this->debugLog('[CHECKOUT] Excecao: ' . $e->getMessage());
            $this->debugLog('[CHECKOUT] Stack: ' . $e->getTraceAsString());
            $msgUser = $this->formatarErroParaUsuario($e->getMessage());
            $http = (stripos($msgUser, 'Estoque insuficiente') !== false) ? 400 : 500;
            $this->json(['error' => 'Erro ao processar pedido: ' . $msgUser], $http);
        }
        
        $this->debugLog('[CHECKOUT] processar() - FIM');
    }

    public function stripePaymentIntent(Request $request) {
        if (!$this->requireFromCartOrRedirect(true)) {
            return;
        }
        if (!$this->validatePesoMaximoCarrinhoAtivoOrFail(true)) {
            return;
        }

        $pedidoId = (int) ($request->getParam('pedido_id') ?? 0);
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => 'pedido_id inválido'], 400);
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT id, total, moeda, numero_pedido, payment_gateway, payment_id FROM pedidos WHERE id = ? LIMIT 1');
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($pedido)) {
                $this->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
                return;
            }

            $moeda = strtoupper(trim((string) ($pedido['moeda'] ?? 'BRL')));
            if ($moeda === 'BRL') {
                $this->json(['success' => false, 'error' => 'Este pedido não é USD/Stripe'], 400);
                return;
            }

            $valor = (float) ($pedido['total'] ?? 0);
            $descricao = 'Pedido #' . (string) ($pedido['numero_pedido'] ?? $pedidoId);
            $customer = [
                'email' => (string) ($request->getParam('email') ?? ''),
            ];

            $pi = $this->paymentService->createStripePaymentIntent($pedidoId, $valor, $descricao, $customer);
            if (empty($pi['success'])) {
                $this->json(['success' => false, 'error' => (string) ($pi['error'] ?? 'Falha ao criar PaymentIntent')], 500);
                return;
            }

            $paymentIntentId = (string) ($pi['payment_intent_id'] ?? '');
            if ($paymentIntentId !== '') {
                $this->atualizarPagamentoNoPedido($pedidoId, ['payment_id' => $paymentIntentId, 'status' => 'pending'], 'stripe');
                $this->atualizarPagamentoNaTabelaPagamentos($pedidoId, ['payment_id' => $paymentIntentId, 'status' => 'pending'], 'stripe');
            }

            $this->json([
                'success' => true,
                'pedido_id' => $pedidoId,
                'payment_intent_id' => $paymentIntentId,
                'client_secret' => (string) ($pi['client_secret'] ?? ''),
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function stripeFinalizar(Request $request) {
        if (!$this->requireFromCartOrRedirect(true)) {
            return;
        }
        if (!$this->validatePesoMaximoCarrinhoAtivoOrFail(true)) {
            return;
        }

        $pedidoId = (int) ($request->getParam('pedido_id') ?? 0);
        $paymentIntentId = trim((string) ($request->getParam('payment_intent_id') ?? ''));
        if ($pedidoId <= 0 || $paymentIntentId === '') {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }

        try {
            $pi = $this->paymentService->retrieveStripePaymentIntent($paymentIntentId);
            $status = strtoupper((string) ($pi['status'] ?? ''));

            $internalStatus = 'pending';
            if ($status === 'SUCCEEDED') {
                $internalStatus = 'SUCCEEDED';
            } elseif (in_array($status, ['CANCELED', 'CANCELLED', 'REQUIRES_PAYMENT_METHOD'], true)) {
                $internalStatus = 'FAILED';
            }

            $this->atualizarPagamentoNoPedido($pedidoId, ['payment_id' => $paymentIntentId, 'status' => $internalStatus, 'paid_at' => ($status === 'SUCCEEDED' ? date('Y-m-d H:i:s') : null)], 'stripe');
            $this->atualizarPagamentoNaTabelaPagamentos($pedidoId, ['payment_id' => $paymentIntentId, 'status' => $internalStatus, 'paid_at' => ($status === 'SUCCEEDED' ? date('Y-m-d H:i:s') : null)], 'stripe');

            if ($status === 'SUCCEEDED') {
                try {
                    $this->paymentService->creditarCashbackClubePorPedidoPago((int) $pedidoId);
                } catch (\Exception $e) {
                }

                // Limpar carrinho no DB (usuário logado) e na sessão/cookie
                try {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                } catch (\Throwable $e) {
                }

                try {
                    $usuario = $this->authService->getUsuarioLogado();
                    $uid = (int) (($usuario['id'] ?? 0));
                    if ($uid > 0) {
                        $cartId = (int) $this->getUserCartIdPreferNonEmpty($uid);
                        if ($cartId <= 0) {
                            $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                        }
                        if ($cartId > 0) {
                            $this->carrinhoModel->limparCarrinho($cartId);
                        }
                    }
                } catch (\Throwable $e) {
                }

                unset($_SESSION['carrinho']);
                unset($_SESSION['carrinho_itens_ativos']);

                if (isset($_COOKIE['guest_cart'])) {
                    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie('guest_cart', '', [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'secure' => $secure,
                            'httponly' => false,
                            'samesite' => 'Lax',
                        ]);
                    } else {
                        setcookie('guest_cart', '', time() - 3600, '/; samesite=Lax', '', $secure, false);
                    }
                }
            }

            $this->json([
                'success' => ($status === 'SUCCEEDED'),
                'pedido_id' => $pedidoId,
                'payment_intent_id' => $paymentIntentId,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function conclusao(Request $request) {
        $pedidoId = $request->getParam('id');
        
        if (!$pedidoId) {
            $this->redirect('/produtos');
            return;
        }
        
        // Obter dados do pedido (normalizado para exibição)
        // Importante: não altera registros; converte apenas na camada de leitura quando necessário.
        $pedidoModel = new \App\Models\PedidoEcommerce();
        $pedido = $pedidoModel->getComDetalhes((int) $pedidoId);
        
        if (!$pedido) {
            $this->redirect('/produtos');
            return;
        }

        $paymentDetails = null;
        $pixQrCode = null;
        $splitPagamentos = [];

        // Se existir split no pedido_pagamentos, carregar para exibição
        try {
            $dbSplit = \Config\Database::getConnection();
            $st = $dbSplit->prepare('SELECT componente, gateway, metodo, moeda, valor, status, invoice_url, bank_slip_url, digitable_line, pix_encoded_image, pix_payload FROM pedido_pagamentos WHERE pedido_id = :p ORDER BY id ASC');
            $st->execute([':p' => (int) $pedidoId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $comp = strtolower(trim((string) ($r['componente'] ?? '')));
                if ($comp === '') continue;
                $splitPagamentos[$comp] = $r;
            }
        } catch (\Exception $e) {
            $splitPagamentos = [];
        }

        // Determinar forma/billingType (fallback para permitir renderização mesmo sem paymentDetails)
        $billingType = strtoupper((string) ($pedido['forma_pagamento'] ?? ''));
        if ($billingType === 'CARTAO_CREDITO') {
            $billingType = 'CREDIT_CARD';
        }

        // AppMax: não depende de consulta externa aqui; usar dados persistidos no pedido (quando disponíveis)
        $gatewayPedido = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
        if ($gatewayPedido === 'appmax') {

            $invoiceUrl = (string) ($pedido['payment_invoice_url'] ?? ($pedido['invoice_url'] ?? ($pedido['invoiceUrl'] ?? '')));
            $bankSlipUrl = (string) ($pedido['payment_bank_slip_url'] ?? ($pedido['bank_slip_url'] ?? ($pedido['bankSlipUrl'] ?? '')));
            $digitableLine = (string) ($pedido['payment_digitable_line'] ?? ($pedido['digitable_line'] ?? ($pedido['digitableLine'] ?? ($pedido['linha_digitavel'] ?? ''))));

            $paymentDetails = [
                'billingType' => $billingType,
                'invoiceUrl' => $invoiceUrl !== '' ? $invoiceUrl : null,
                'bankSlipUrl' => $bankSlipUrl !== '' ? $bankSlipUrl : null,
                'digitableLine' => $digitableLine !== '' ? $digitableLine : null,
                'status' => (string) ($pedido['payment_status'] ?? ''),
            ];

            if ($billingType === 'PIX') {
                $pixImg = (string) ($pedido['payment_pix_encoded_image'] ?? ($pedido['pix_encoded_image'] ?? ($pedido['pix_qr_base64'] ?? ($pedido['pix_qr'] ?? ''))));
                $pixPayload = (string) ($pedido['payment_pix_payload'] ?? ($pedido['pix_payload'] ?? ($pedido['pix_emv'] ?? ($pedido['pix_copy_paste'] ?? ''))));
                if ($pixImg !== '' || $pixPayload !== '') {
                    $pixQrCode = [
                        'encodedImage' => $pixImg !== '' ? $pixImg : null,
                        'payload' => $pixPayload !== '' ? $pixPayload : null,
                    ];
                }
            }

        } else {
            // Legado Asaas
            try {
                if (!empty($pedido['payment_gateway']) && $pedido['payment_gateway'] === 'asaas' && !empty($pedido['payment_id'])) {
                    $paymentDetails = $this->paymentService->obterPagamentoAsaas((string) $pedido['payment_id']);
                    if (strtoupper((string) ($paymentDetails['billingType'] ?? '')) === 'PIX') {
                        try {
                            $pixQrCode = $this->paymentService->obterPixQrCodeAsaas((string) $pedido['payment_id']);
                        } catch (\Exception $e) {
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // Fallback universal: em alguns schemas, o QR/payload do PIX fica apenas em pedido_pagamentos
        // (tanto no split quanto no fluxo legado). Garantir exibição no checkout/conclusao.
        if ($billingType === 'PIX' && empty($pixQrCode) && !empty($splitPagamentos)) {
            foreach ($splitPagamentos as $row) {
                if (!is_array($row)) continue;
                $img = trim((string) ($row['pix_encoded_image'] ?? ''));
                $pay = trim((string) ($row['pix_payload'] ?? ''));
                if ($img !== '' || $pay !== '') {
                    $pixQrCode = [
                        'encodedImage' => $img !== '' ? $img : null,
                        'payload' => $pay !== '' ? $pay : null,
                    ];
                    break;
                }
            }
        }

        // Se não temos paymentDetails por gateway (ex.: split), ao menos expor billingType para a view
        if (empty($paymentDetails)) {
            $paymentDetails = [
                'billingType' => $billingType,
                'status' => (string) ($pedido['payment_status'] ?? ''),
            ];
        }
        
        $this->view('checkout/conclusao', [
            'pedido' => $pedido,
            'itens' => (array) ($pedido['items'] ?? []),
            'paymentDetails' => $paymentDetails,
            'pixQrCode' => $pixQrCode,
            'splitPagamentos' => $splitPagamentos,
        ]);
    }
    
    private function salvarItensPedido($pedidoId, $carrinho) {
        $db = \Config\Database::getConnection();

        $startedTx = false;
        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTx = true;
            }
        } catch (\Throwable $e) {
            $startedTx = false;
        }

        $itensTable = $this->pickPedidoItensTable($db, (int) $pedidoId);

        // Descobrir colunas disponíveis na tabela de itens (compatibilidade entre schemas)
        $colsItens = [];
        try {
            $stmtCols = $db->query('DESCRIBE ' . $itensTable);
            $colsItens = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $colsItens = [];
        }
        
        // Detectar colunas de estoque
        $prodCols = [];
        try {
            $st = $db->query('DESCRIBE produtos');
            $prodCols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable $e) {
            $prodCols = [];
        }
        $prodStockCol = null;
        foreach (['stock', 'estoque'] as $c) {
            if (is_array($prodCols) && in_array($c, $prodCols, true)) {
                $prodStockCol = $c;
                break;
            }
        }

        $hasProdutoVariacoes = false;
        try {
            $st = $db->query("SHOW TABLES LIKE 'produto_variacoes'");
            $hasProdutoVariacoes = (bool) ($st && $st->fetch());
        } catch (\Throwable $e) {
            $hasProdutoVariacoes = false;
        }
        $varCols = [];
        if ($hasProdutoVariacoes) {
            try {
                $st = $db->query('DESCRIBE produto_variacoes');
                $varCols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $varCols = [];
            }
        }
        $varStockCol = null;
        foreach (['stock', 'estoque'] as $c) {
            if (is_array($varCols) && in_array($c, $varCols, true)) {
                $varStockCol = $c;
                break;
            }
        }

        $prodControlaCol = null;
        foreach (['controla_estoque', 'manage_stock'] as $c) {
            if (is_array($prodCols) && in_array($c, $prodCols, true)) {
                $prodControlaCol = $c;
                break;
            }
        }

        $stmtStockProduto = null;
        if (!empty($prodStockCol)) {
            $stmtStockProduto = $db->prepare('UPDATE produtos SET ' . $prodStockCol . ' = ' . $prodStockCol . ' - ? WHERE id = ? AND ' . $prodStockCol . ' >= ?');
        }
        $stmtStockVariacao = null;
        if (!empty($varStockCol)) {
            $stmtStockVariacao = $db->prepare('UPDATE produto_variacoes SET ' . $varStockCol . ' = ' . $varStockCol . ' - ? WHERE id = ? AND ' . $varStockCol . ' >= ?');
        }

        $temListaCompras = false;
        $colsLista = [];
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $st->execute(['lista_compras']);
            $temListaCompras = ((int) ($st->fetchColumn() ?: 0) > 0);
        } catch (\Throwable $e) {
            $temListaCompras = false;
        }
        if ($temListaCompras) {
            try {
                $st = $db->query('DESCRIBE lista_compras');
                $colsLista = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $colsLista = [];
            }
        }
        $listaTemPedidoId = $temListaCompras && is_array($colsLista) && in_array('pedido_id', $colsLista, true);
        $listaTemProdutoId = $temListaCompras && is_array($colsLista) && in_array('produto_id', $colsLista, true);
        $listaTemStatus = $temListaCompras && is_array($colsLista) && in_array('status', $colsLista, true);
        $listaTemTipo = $temListaCompras && is_array($colsLista) && in_array('tipo_compra', $colsLista, true);
        $listaQtdCol = '';
        if ($temListaCompras && is_array($colsLista) && in_array('quantidade_faltante', $colsLista, true)) {
            $listaQtdCol = 'quantidade_faltante';
        } elseif ($temListaCompras && is_array($colsLista) && in_array('quantidade_necessaria', $colsLista, true)) {
            $listaQtdCol = 'quantidade_necessaria';
        }

        $criouPendenciaLista = false;

        try {
            foreach ($carrinho as $item) {
                $this->debugLog('[CHECKOUT_ITENS] Item do carrinho: ' . json_encode($item));
            
            // Validar se o produto existe antes de inserir
            $produtoId = $item['produto_id'] ?? $item['id'] ?? null;
            if (empty($produtoId)) {
                $this->debugLog('[CHECKOUT_ITENS] Produto ID vazio, pulando item');
                continue;
            }
            
            $stmt = $db->prepare("SELECT id FROM produtos WHERE id = ?");
            $stmt->execute([$produtoId]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$produto) {
                $this->debugLog('[CHECKOUT_ITENS] Produto ID ' . $produtoId . ' nao encontrado, pulando item');
                continue;
            }
            
            $this->debugLog('[CHECKOUT_ITENS] Produto ID ' . $produtoId . ' validado');

            // Buscar dados do produto para persistir no pedido
            $produtoRow = null;
            try {
                $select = ['id', 'name', 'nome', 'sku', 'url_original'];
                if (!empty($prodControlaCol)) {
                    $select[] = $prodControlaCol;
                }
                $stmtP = $db->prepare('SELECT ' . implode(', ', array_values(array_unique($select))) . ' FROM produtos WHERE id = ? LIMIT 1');
                $stmtP->execute([$produtoId]);
                $produtoRow = $stmtP->fetch(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $produtoRow = null;
            }

            $nomeProduto = (string) (
                $item['nome'] ??
                $item['name'] ??
                ($item['produto_nome'] ?? null) ??
                ($produtoRow['nome'] ?? ($produtoRow['name'] ?? ''))
            );
            if (trim($nomeProduto) === '') {
                $nomeProduto = 'Produto #' . $produtoId;
            }
            $skuProduto = (string) (
                $item['sku'] ??
                ($item['referencia'] ?? null) ??
                ($produtoRow['sku'] ?? '')
            );
            $urlOriginal = (string) (
                $item['url_original'] ??
                ($item['url'] ?? null) ??
                ($produtoRow['url_original'] ?? '')
            );

            $variacaoId = null;
            $variacaoLabel = null;
            $variacaoAtributos = null;
            if (isset($item['variacao']) && is_array($item['variacao'])) {
                $variacaoId = $item['variacao']['id'] ?? null;
                $variacaoLabel = $item['variacao']['label'] ?? null;
                $variacaoAtributos = $item['variacao']['atributos'] ?? null;
            }

            $produtoVariacaoId = null;
            if (isset($item['produto_variacao_id']) && $item['produto_variacao_id'] !== '' && $item['produto_variacao_id'] !== null) {
                $pv = (int) $item['produto_variacao_id'];
                if ($pv > 0) {
                    $produtoVariacaoId = $pv;
                }
            }
            
            // Verificar diferentes campos de preço
            $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
            $quantidade = $item['quantidade'] ?? 1;
            
            $this->debugLog('[CHECKOUT_ITENS] Preco unitario: ' . $precoUnitario . ', Quantidade: ' . $quantidade);
            
            $cols = ['pedido_id', 'produto_id', 'quantidade', 'preco_unitario', 'subtotal', 'created_at'];
            $vals = [$pedidoId, $produtoId, $quantidade, $precoUnitario, $precoUnitario * $quantidade];
            $placeholders = ['?', '?', '?', '?', '?', 'NOW()'];

            // Campos de auditoria para o admin (se existirem no schema)
            if (is_array($colsItens) && in_array('nome_produto', $colsItens, true)) {
                $cols[] = 'nome_produto';
                $vals[] = $nomeProduto;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('nome_produto_sku', $colsItens, true)) {
                $cols[] = 'nome_produto_sku';
                $vals[] = $skuProduto;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('url_original', $colsItens, true)) {
                $cols[] = 'url_original';
                $vals[] = $urlOriginal;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('variacao_id', $colsItens, true)) {
                $cols[] = 'variacao_id';
                $vals[] = $variacaoId;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('variacao_label', $colsItens, true)) {
                $cols[] = 'variacao_label';
                $vals[] = $variacaoLabel;
                $placeholders[] = '?';
            }
            if (is_array($colsItens) && in_array('variacao_atributos', $colsItens, true)) {
                $cols[] = 'variacao_atributos';
                $vals[] = (is_array($variacaoAtributos) ? json_encode($variacaoAtributos) : null);
                $placeholders[] = '?';
            }

            if (is_array($colsItens) && in_array('produto_variacao_id', $colsItens, true)) {
                $cols[] = 'produto_variacao_id';
                $vals[] = $produtoVariacaoId;
                $placeholders[] = '?';
            }

                $sql = 'INSERT INTO ' . $itensTable . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $db->prepare($sql);
                $stmt->execute($vals);
            
                $this->debugLog('[CHECKOUT_ITENS] Item inserido: produto_id=' . $produtoId . ', quantidade=' . $quantidade . ', valor=' . ($precoUnitario * $quantidade));

                $controlaEstoque = !empty($prodStockCol);
                if (!empty($prodControlaCol) && is_array($produtoRow) && array_key_exists($prodControlaCol, $produtoRow)) {
                    $raw = $produtoRow[$prodControlaCol];
                    $controlaEstoque = !empty($raw) && (string) $raw !== '0' && strtolower((string) $raw) !== 'false';
                }

                if ($controlaEstoque) {
                    if ($produtoVariacaoId !== null) {
                        if (!$stmtStockVariacao) {
                            throw new \Exception('Estoque insuficiente');
                        }
                        $stmtStockVariacao->execute([(int) $quantidade, (int) $produtoVariacaoId, (int) $quantidade]);
                        if ((int) $stmtStockVariacao->rowCount() <= 0) {
                            throw new \Exception('Estoque insuficiente');
                        }
                    } else {
                        if (!$stmtStockProduto) {
                            throw new \Exception('Estoque insuficiente');
                        }
                        $stmtStockProduto->execute([(int) $quantidade, (int) $produtoId, (int) $quantidade]);
                        if ((int) $stmtStockProduto->rowCount() <= 0) {
                            throw new \Exception('Estoque insuficiente');
                        }
                    }
                } else {
                    if ($temListaCompras && $listaTemPedidoId && $listaTemProdutoId && $listaQtdCol !== '') {
                        try {
                            $faltante = (int) $quantidade;

                            // Considerar estoque cadastrado para registrar apenas o faltante (quando houver coluna)
                            if ($produtoVariacaoId !== null && !empty($varStockCol)) {
                                try {
                                    $stS = $db->prepare('SELECT ' . $varStockCol . ' FROM produto_variacoes WHERE id = ? LIMIT 1');
                                    $stS->execute([(int) $produtoVariacaoId]);
                                    $stockAtual = (int) ($stS->fetchColumn() ?: 0);
                                    $faltante = max(0, ((int) $quantidade) - $stockAtual);
                                } catch (\Exception $e) {
                                }
                            } elseif ($produtoVariacaoId === null && !empty($prodStockCol)) {
                                try {
                                    $stS = $db->prepare('SELECT ' . $prodStockCol . ' FROM produtos WHERE id = ? LIMIT 1');
                                    $stS->execute([(int) $produtoId]);
                                    $stockAtual = (int) ($stS->fetchColumn() ?: 0);
                                    $faltante = max(0, ((int) $quantidade) - $stockAtual);
                                } catch (\Exception $e) {
                                }
                            }

                            if ($faltante <= 0) {
                                continue;
                            }

                            $sqlFind = 'SELECT id, ' . $listaQtdCol . ' AS qtd FROM lista_compras WHERE pedido_id = ? AND produto_id = ?';
                            $params = [(int) $pedidoId, (int) $produtoId];
                            if ($listaTemStatus) {
                                $sqlFind .= " AND status = 'pendente'";
                            }
                            if ($listaTemTipo) {
                                $sqlFind .= ' AND (tipo_compra = ? OR tipo_compra IS NULL OR tipo_compra = \"\")';
                                $params[] = 'online';
                            }
                            $sqlFind .= ' ORDER BY id DESC LIMIT 1';
                            $stFind = $db->prepare($sqlFind);
                            $stFind->execute($params);
                            $ex = $stFind->fetch(\PDO::FETCH_ASSOC);
                            if (is_array($ex) && !empty($ex['id'])) {
                                $newQtd = ((int) ($ex['qtd'] ?? 0)) + (int) $faltante;
                                $stUpd = $db->prepare('UPDATE lista_compras SET ' . $listaQtdCol . ' = ? WHERE id = ?');
                                $stUpd->execute([$newQtd, (int) $ex['id']]);
                                $criouPendenciaLista = true;
                            } else {
                                $colsIns = ['produto_id', 'pedido_id', $listaQtdCol];
                                $valsIns = [':produto_id', ':pedido_id', ':q'];
                                $pIns = [':produto_id' => (int) $produtoId, ':pedido_id' => (int) $pedidoId, ':q' => (int) $faltante];
                                if ($listaTemStatus) {
                                    $colsIns[] = 'status';
                                    $valsIns[] = "'pendente'";
                                }
                                if ($listaTemTipo) {
                                    $colsIns[] = 'tipo_compra';
                                    $valsIns[] = ':tipo_compra';
                                    $pIns[':tipo_compra'] = 'online';
                                }
                                $sqlIns = 'INSERT INTO lista_compras (' . implode(',', $colsIns) . ') VALUES (' . implode(',', $valsIns) . ')';
                                $stIns = $db->prepare($sqlIns);
                                $stIns->execute($pIns);
                                $criouPendenciaLista = true;
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }
            }

            // Se gerou pendência na lista de compras, marcar pedido como pendente de conferência (quando colunas existirem)
            if ($criouPendenciaLista) {
                try {
                    $colsPed = [];
                    try {
                        $st = $db->query('DESCRIBE pedidos');
                        $colsPed = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsPed = [];
                    }

                    $set = [];
                    $params = [':id' => (int) $pedidoId];
                    if (is_array($colsPed) && in_array('status_conferencia', $colsPed, true)) {
                        $set[] = 'status_conferencia = :sc';
                        $params[':sc'] = 'pendente';
                    }
                    if (is_array($colsPed) && in_array('origem_pedido', $colsPed, true)) {
                        $set[] = 'origem_pedido = :op';
                        $params[':op'] = 'online';
                    }
                    if (!empty($set)) {
                        $st = $db->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                        $st->execute($params);
                    }
                } catch (\Exception $e) {
                }
            }

            if ($startedTx && $db->inTransaction()) {
                $db->commit();
            }
        } catch (\Exception $e) {
            if ($startedTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
    
    private function salvarDadosCliente($pedidoId, $dados, $usuario) {
        try {
            $db = \Config\Database::getConnection();

            $stmtPedido = $db->prepare('SELECT usuario_id, cliente_id FROM pedidos WHERE id = ? LIMIT 1');
            $stmtPedido->execute([$pedidoId]);
            $pedidoRow = $stmtPedido->fetch(\PDO::FETCH_ASSOC);

            $usuarioId = $pedidoRow['usuario_id'] ?? null;
            $clienteId = $pedidoRow['cliente_id'] ?? null;

            // Atualizar usuario (se existir)
            if (!empty($usuarioId)) {
                $colsU = [];
                try {
                    $stmtColsU = $db->query('DESCRIBE usuarios');
                    $colsU = $stmtColsU->fetchAll(\PDO::FETCH_COLUMN);
                } catch (\Exception $e) {
                }

                $setU = [];
                $paramsU = ['id' => $usuarioId];

                if (!empty($dados['email']) && is_array($colsU) && in_array('email', $colsU, true)) {
                    $setU[] = 'email = :email';
                    $paramsU['email'] = $dados['email'];
                }

                if (!empty($dados['nome'])) {
                    if (is_array($colsU) && in_array('nome', $colsU, true)) {
                        $setU[] = 'nome = :nome';
                        $paramsU['nome'] = $dados['nome'];
                    } elseif (is_array($colsU) && in_array('name', $colsU, true)) {
                        $setU[] = 'name = :nome';
                        $paramsU['nome'] = $dados['nome'];
                    }
                }

                if (!empty($dados['telefone'])) {
                    $telefoneCol = null;
                    foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                        if (is_array($colsU) && in_array($c, $colsU, true)) {
                            $telefoneCol = $c;
                            break;
                        }
                    }
                    if (!empty($telefoneCol)) {
                        $setU[] = "{$telefoneCol} = :telefone";
                        $paramsU['telefone'] = $dados['telefone'];
                    }
                }

                if (!empty($dados['documento']) && is_array($colsU) && in_array('documento', $colsU, true)) {
                    $setU[] = 'documento = :documento';
                    $paramsU['documento'] = $dados['documento'];
                }

                if (!empty($dados['data_nascimento']) && is_array($colsU) && in_array('data_nascimento', $colsU, true)) {
                    $setU[] = 'data_nascimento = :data_nascimento';
                    $paramsU['data_nascimento'] = $dados['data_nascimento'];
                }

                if (!empty($setU)) {
                    $sqlU = 'UPDATE usuarios SET ' . implode(', ', $setU) . ' WHERE id = :id';
                    $stmtU = $db->prepare($sqlU);
                    $stmtU->execute($paramsU);
                }
            }

            // Atualizar cliente (se existir)
            if (!empty($clienteId)) {
                $colsC = [];
                try {
                    $stmtColsC = $db->query('DESCRIBE clientes');
                    $colsC = $stmtColsC->fetchAll(\PDO::FETCH_COLUMN);
                } catch (\Exception $e) {
                }

                $setC = [];
                $paramsC = ['id' => $clienteId];

                if (!empty($dados['nome'])) {
                    if (is_array($colsC) && in_array('nome_razao_social', $colsC, true)) {
                        $setC[] = 'nome_razao_social = :nome';
                        $paramsC['nome'] = $dados['nome'];
                    } elseif (is_array($colsC) && in_array('nome', $colsC, true)) {
                        $setC[] = 'nome = :nome';
                        $paramsC['nome'] = $dados['nome'];
                    }
                }

                if (!empty($dados['email']) && is_array($colsC) && in_array('email', $colsC, true)) {
                    $setC[] = 'email = :email';
                    $paramsC['email'] = $dados['email'];
                }

                if (!empty($dados['telefone'])) {
                    $telefoneCol = null;
                    foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                        if (is_array($colsC) && in_array($c, $colsC, true)) {
                            $telefoneCol = $c;
                            break;
                        }
                    }
                    if (!empty($telefoneCol)) {
                        $setC[] = "{$telefoneCol} = :telefone";
                        $paramsC['telefone'] = $dados['telefone'];
                    }
                }

                if (!empty($dados['documento'])) {
                    if (is_array($colsC) && in_array('cpf_cnpj', $colsC, true)) {
                        $setC[] = 'cpf_cnpj = :documento';
                        $paramsC['documento'] = $dados['documento'];
                    } elseif (is_array($colsC) && in_array('documento', $colsC, true)) {
                        $setC[] = 'documento = :documento';
                        $paramsC['documento'] = $dados['documento'];
                    }
                }

                if (!empty($setC)) {
                    $sqlC = 'UPDATE clientes SET ' . implode(', ', $setC) . ' WHERE id = :id';
                    $stmtC = $db->prepare($sqlC);
                    $stmtC->execute($paramsC);
                }
            }

            return true;
            
        } catch (\Exception $e) {
            $this->debugLog('[CHECKOUT_DADOS_CLIENTE] Erro: ' . $e->getMessage());
            return false;
        }
    }
    
    private function obterPedidoCompleto($pedidoId) {
        $db = \Config\Database::getConnection();

        $enderecoCol = 'endereco';
        try {
            $stmtCols = $db->query('DESCRIBE enderecos');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols)) {
                if (in_array('endereco', $cols, true)) {
                    $enderecoCol = 'endereco';
                } elseif (in_array('logradouro', $cols, true)) {
                    $enderecoCol = 'logradouro';
                }
            }
        } catch (\Exception $e) {
        }

        $usuarioNomeCol = null;
        $usuarioTelefoneCol = null;
        try {
            $stmtColsU = $db->query('DESCRIBE usuarios');
            $colsU = $stmtColsU->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsU)) {
                if (in_array('nome', $colsU, true)) {
                    $usuarioNomeCol = 'nome';
                } elseif (in_array('name', $colsU, true)) {
                    $usuarioNomeCol = 'name';
                }

                foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                    if (in_array($c, $colsU, true)) {
                        $usuarioTelefoneCol = $c;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $clienteTemTabela = false;
        $clienteNomeCol = null;
        $clienteEmailCol = null;
        $clienteTelefoneCol = null;
        try {
            $stmtColsC = $db->query('DESCRIBE clientes');
            $colsC = $stmtColsC->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsC) && !empty($colsC)) {
                $clienteTemTabela = true;
                if (in_array('nome_razao_social', $colsC, true)) {
                    $clienteNomeCol = 'nome_razao_social';
                } elseif (in_array('nome', $colsC, true)) {
                    $clienteNomeCol = 'nome';
                }
                if (in_array('email', $colsC, true)) {
                    $clienteEmailCol = 'email';
                }
                foreach (['telefone', 'celular', 'phone', 'whatsapp'] as $c) {
                    if (in_array($c, $colsC, true)) {
                        $clienteTelefoneCol = $c;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $uNomeExpr = $usuarioNomeCol ? ("u.{$usuarioNomeCol}") : 'NULL';
        $uTelExpr = $usuarioTelefoneCol ? ("u.{$usuarioTelefoneCol}") : 'NULL';
        $cNomeExpr = ($clienteTemTabela && $clienteNomeCol) ? ("c.{$clienteNomeCol}") : 'NULL';
        $cEmailExpr = ($clienteTemTabela && $clienteEmailCol) ? ("c.{$clienteEmailCol}") : 'NULL';
        $cTelExpr = ($clienteTemTabela && $clienteTelefoneCol) ? ("c.{$clienteTelefoneCol}") : 'NULL';

        $sql = "SELECT 
                    p.*,
                    p.servicos AS taxa_servico,
                    COALESCE({$cNomeExpr}, {$uNomeExpr}, p.nome) AS cliente_nome,
                    COALESCE({$cEmailExpr}, u.email) AS cliente_email,
                    COALESCE({$cTelExpr}, {$uTelExpr}) AS cliente_telefone,
                    e_ent.cep AS cep,
                    e_ent.{$enderecoCol} AS endereco,
                    e_ent.numero AS numero,
                    e_ent.complemento AS complemento,
                    e_ent.bairro AS bairro,
                    e_ent.cidade AS cidade,
                    e_ent.estado AS estado
                FROM pedidos p
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                " . ($clienteTemTabela ? " LEFT JOIN clientes c ON p.cliente_id = c.id" : "") . "
                LEFT JOIN enderecos e_ent ON p.endereco_entrega_id = e_ent.id
                WHERE p.id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);

        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($pedido)) {
            $stPag = $pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? null);
            if (is_string($stPag)) {
                $stPag = strtoupper(trim($stPag));
            }
            if (!empty($stPag) && in_array($stPag, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                $pedido['status'] = 'pago';
            }
        }

        return $pedido;
    }
    
    private function obterItensPedido($pedidoId) {
        $db = \Config\Database::getConnection();

        $itensTable = $this->pickPedidoItensTable($db, (int) $pedidoId);

        $produtoNomeCol = null;
        try {
            $stmtCols = $db->query('DESCRIBE produtos');
            $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($cols)) {
                if (in_array('nome', $cols, true)) {
                    $produtoNomeCol = 'nome';
                } elseif (in_array('name', $cols, true)) {
                    $produtoNomeCol = 'name';
                } elseif (in_array('titulo', $cols, true)) {
                    $produtoNomeCol = 'titulo';
                }
            }
        } catch (\Exception $e) {
        }

        if (!empty($produtoNomeCol)) {
            $sql = "SELECT 
                        pi.*,
                        COALESCE(pi.nome_produto, pr.{$produtoNomeCol}) AS nome
                    FROM {$itensTable} pi
                    LEFT JOIN produtos pr ON pi.produto_id = pr.id
                    WHERE pi.pedido_id = ?";
        } else {
            $sql = "SELECT 
                        pi.*,
                        pi.nome_produto AS nome
                    FROM {$itensTable} pi
                    WHERE pi.pedido_id = ?";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$pedidoId]);

        $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (is_array($itens)) {
            // Mapear descrições de variação (quando existir produto_variacao_id)
            $colsItens = [];
            try {
                $stmtCols = $db->query('DESCRIBE ' . $itensTable);
                $colsItens = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsItens = [];
            }

            $hasProdutoVariacaoId = (is_array($colsItens) && in_array('produto_variacao_id', $colsItens, true));
            $variacaoDescById = [];
            if ($hasProdutoVariacaoId) {
                $pvIds = [];
                foreach ($itens as $it) {
                    $pvi = (int) ($it['produto_variacao_id'] ?? 0);
                    if ($pvi > 0) {
                        $pvIds[$pvi] = true;
                    }
                }
                $pvIds = array_keys($pvIds);
                if (!empty($pvIds)) {
                    try {
                        $in = implode(',', array_fill(0, count($pvIds), '?'));
                        $sqlVar = '
                            SELECT pvi.produto_variacao_id, vt.nome AS tipo_nome, vo.valor AS opcao_valor
                            FROM produto_variacao_itens pvi
                            INNER JOIN variacao_tipos vt ON vt.id = pvi.tipo_id
                            INNER JOIN variacao_opcoes vo ON vo.id = pvi.opcao_id
                            WHERE pvi.produto_variacao_id IN (' . $in . ')
                            ORDER BY pvi.produto_variacao_id ASC, vt.nome ASC, vo.valor ASC
                        ';
                        $stVar = $db->prepare($sqlVar);
                        $stVar->execute($pvIds);
                        $rows = $stVar->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        $tmpPairs = [];
                        foreach ($rows as $r) {
                            $vid = (int) ($r['produto_variacao_id'] ?? 0);
                            if ($vid <= 0) continue;
                            $tn = (string) ($r['tipo_nome'] ?? '');
                            $ov = (string) ($r['opcao_valor'] ?? '');
                            if ($tn === '' || $ov === '') continue;
                            if (!isset($tmpPairs[$vid])) $tmpPairs[$vid] = [];
                            $tmpPairs[$vid][] = $tn . '=' . $ov;
                        }
                        foreach ($tmpPairs as $vid => $parts) {
                            $variacaoDescById[(int) $vid] = implode(' / ', $parts);
                        }
                    } catch (\Exception $e) {
                        $variacaoDescById = [];
                    }
                }
            }

            foreach ($itens as &$item) {
                if (empty($item['nome'])) {
                    $produtoId = $item['produto_id'] ?? null;
                    $item['nome'] = !empty($produtoId) ? ('Produto #' . $produtoId) : 'Produto';
                }

                $pvId = (int) ($item['produto_variacao_id'] ?? 0);
                if ($pvId > 0) {
                    $desc = (string) ($variacaoDescById[$pvId] ?? '');
                    if ($desc !== '') {
                        $item['variacao_descricao'] = $desc;
                        $item['variacao_label'] = $desc;
                    }
                }
            }
        }

        return $itens;
    }
    
    private function obterCarrinho($usuario) {
        $sessionId = session_id();
        
        if ($usuario) {
            return $this->carrinhoModel->getOrCreateCarrinho($usuario['id'], null, 'USD');
        } else {
            return $this->carrinhoModel->getOrCreateCarrinho(null, $sessionId, 'USD');
        }
    }
    
    private function validarDadosCheckout($dados) {
        $erros = [];

        $paisEntrega = strtoupper(trim((string) ($dados['pais'] ?? 'BR')));
        if ($paisEntrega === '') {
            $paisEntrega = 'BR';
        }
        $pais = $paisEntrega;
        
        // Dados pessoais
        if (empty($dados['nome'])) {
            $erros[] = 'Nome é obrigatório';
        } else {
            $nome = trim((string) $dados['nome']);
            $parts = preg_split('/\s+/', $nome) ?: [];
            $parts = array_values(array_filter($parts, static fn($p) => is_string($p) && mb_strlen(trim($p)) >= 2));
            if (count($parts) < 2) {
                $erros[] = 'Informe nome e sobrenome';
            }
        }
        if (empty($dados['email'])) $erros[] = 'E-mail é obrigatório';
        $doc = CpfValidator::onlyDigits((string) ($dados['documento'] ?? ''));
        if ($pais === 'BR') {
            if ($doc === '' || strlen($doc) < 11) {
                $erros[] = 'CPF é obrigatório para residentes no Brasil';
            } elseif (strlen($doc) === 11 && !CpfValidator::isValid($doc)) {
                $erros[] = 'CPF inválido';
            }
        } else {
            if ($doc !== '' && strlen($doc) === 11 && !CpfValidator::isValid($doc)) {
                $erros[] = 'CPF inválido';
            }
        }
        if (empty($dados['telefone'])) $erros[] = 'Telefone é obrigatório';
        if (empty($dados['data_nascimento'])) {
            $erros[] = 'Data de nascimento é obrigatória';
        } else {
            $rawBirth = trim((string) ($dados['data_nascimento'] ?? ''));
            $birth = \DateTime::createFromFormat('Y-m-d', $rawBirth);
            if (!$birth) {
                $birth = \DateTime::createFromFormat('d/m/Y', $rawBirth);
            }
            if (!$birth) {
                $erros[] = 'Data de nascimento inválida';
            } else {
                $birth->setTime(0, 0, 0);
                $today = new \DateTime('today');
                if ($birth > $today) {
                    $erros[] = 'Data de nascimento não pode ser no futuro';
                }
            }
        }
        
        // Endereço
        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if ($pais === 'BR' && empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if ($pais === 'BR' && empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';
        if (in_array($pais, ['BR','US','CA'], true) && empty($dados['estado'])) $erros[] = 'Estado é obrigatório';

        // Destinatário (quando entregar para outra pessoa)
        $entregaOutro = (string) ($dados['entrega_para_outro'] ?? '0');
        $entregaOutro = ($entregaOutro === '1' || strtolower($entregaOutro) === 'true' || $entregaOutro === 'on');
        if ($entregaOutro) {
            if (empty($dados['destinatario_nome'])) {
                $erros[] = 'Nome do destinatário é obrigatório';
            }
            if (empty($dados['destinatario_telefone'])) {
                $erros[] = 'Telefone do destinatário é obrigatório';
            }
            if ($pais === 'BR') {
                $docDest = CpfValidator::onlyDigits((string) ($dados['destinatario_documento'] ?? ''));
                if ($docDest === '' || strlen($docDest) < 11) {
                    $erros[] = 'CPF do destinatário é obrigatório para entregas no Brasil';
                } elseif (strlen($docDest) === 11 && !CpfValidator::isValid($docDest)) {
                    $erros[] = 'CPF do destinatário inválido';
                }
            } else {
                $docDest = CpfValidator::onlyDigits((string) ($dados['destinatario_documento'] ?? ''));
                if ($docDest !== '' && strlen($docDest) === 11 && !CpfValidator::isValid($docDest)) {
                    $erros[] = 'CPF do destinatário inválido';
                }
            }
        }
        
        // Pagamento
        if (empty($dados['forma_pagamento'])) $erros[] = 'Método de pagamento é obrigatório';
        
        // Senha (se não estiver logado)
        if (!$this->authService->estaLogado()) {
            if (empty($dados['senha'])) $erros[] = 'Senha é obrigatória';
            if (empty($dados['senha_confirmacao'])) $erros[] = 'Confirmação de senha é obrigatória';
            if ($dados['senha'] !== $dados['senha_confirmacao']) $erros[] = 'Senhas não conferem';
        }
        
        return $erros;
    }
    
    private function criarOuAtualizarUsuario($dados, $usuario) {
        if ($usuario) {
            // Usuário já está logado, apenas retornar ID
            return $usuario['id'];
        }
        
        // Verificar se usuário já existe
        $usuarioExistente = $this->usuarioModel->findByEmail($dados['email']);
        
        if ($usuarioExistente) {
            // Verificar senha
            if ($this->usuarioModel->authenticate($dados['email'], $dados['senha'])) {
                return $usuarioExistente['id'];
            } else {
                throw new \Exception('E-mail já cadastrado com senha diferente');
            }
        }
        
        // Criar novo usuário
        $docToSave = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
        return $this->usuarioModel->create([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'senha' => $dados['senha'],
            'telefone' => $dados['telefone'],
            'documento' => $docToSave,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
            'perfil' => 'cliente'
        ]);
    }
    
    private function criarEndereco($usuarioId, $dados, $tipo) {
        $enderecoData = [
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'cep' => $dados['cep'],
            'endereco' => $dados['endereco'],
            'numero' => $dados['numero'] ?? '',
            'complemento' => $dados['complemento'] ?? '',
            'bairro' => $dados['bairro'] ?? '',
            'cidade' => $dados['cidade'],
            'estado' => $dados['estado'] ?? '',
            'pais' => (string) ($dados['pais'] ?? 'BR'),
            'principal' => false
        ];
        
        $this->enderecoModel->create($enderecoData);
        return $this->enderecoModel->getConnection()->lastInsertId();
    }
    
    private function registrarConsentimentoLegal($usuarioId, $dados) {
        // Aqui poderia ser implementado um registro mais detalhado do consentimento
        // Por enquanto, apenas registrar no log de auditoria
        $this->authService->registrarLogAuditoria(
            $usuarioId,
            'consentimento_legal_aceito',
            'usuarios',
            $usuarioId,
            null,
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'data_hora' => date('Y-m-d H:i:s'),
                'versao_termo' => '1.0',
                'idioma' => 'pt-BR'
            ]
        );
    }
    
    public function calcular(Request $request) {
        if (!$this->requireFromCartOrRedirect(true)) {
            return;
        }
        if (!$this->validatePesoMaximoCarrinhoAtivoOrFail(true)) {
            return;
        }

        $dados = $request->getParams();
        
        try {
            // Obter carrinho
            $usuario = $this->authService->getUsuarioLogado();
            $carrinho = $this->obterCarrinho($usuario);
            
            if (!$carrinho) {
                $this->json(['error' => 'Carrinho não encontrado'], 400);
            }
            
            // Atualizar frete manual se informado
            if (isset($dados['frete_manual'])) {
                $this->carrinhoModel->update($carrinho['id'], [
                    'frete_manual' => floatval($dados['frete_manual'])
                ]);
                $this->carrinhoModel->atualizarTotais($carrinho['id']);
                
                // Recarregar carrinho atualizado
                $carrinho = $this->carrinhoModel->find($carrinho['id']);
            }
            
            // Obter taxa de câmbio atual
            $taxaConversao = $this->carrinhoModel->getTaxaConversao($carrinho['moeda']);
            
            $this->json([
                'success' => true,
                'carrinho' => [
                    'subtotal_produtos' => number_format($carrinho['subtotal_produtos'], 2, ',', '.'),
                    'valor_frete' => number_format($carrinho['frete_manual'], 2, ',', '.'),
                    'taxa_servico' => number_format($carrinho['taxa_servico'], 2, ',', '.'),
                    'valor_impostos' => number_format($carrinho['valor_impostos'], 2, ',', '.'),
                    'valor_total' => number_format($carrinho['valor_total'], 2, ',', '.'),
                    'valor_total_brl' => number_format($carrinho['valor_total'] * $taxaConversao, 2, ',', '.'),
                    'peso_total' => number_format($carrinho['peso_total'], 3, ',', '.'),
                    'moeda' => $carrinho['moeda'],
                    'taxa_conversao' => $taxaConversao
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao calcular valores: ' . $e->getMessage()], 500);
        }
    }
    
    private function criarPedido($dados, $carrinho, $usuario) {
        try {
            $this->debugLog('[CRIAR_PEDIDO] Iniciando criacao do pedido');
            
            // Garantir usuário e cliente válidos - fluxo correto obrigatório
            $db = \Config\Database::getConnection();
            
            if (empty($usuario) || empty($usuario['email'])) {
                $usuario = [
                    'nome' => $dados['nome'] ?? 'Cliente',
                    'email' => $dados['email'] ?? null,
                    'documento' => $dados['documento'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'senha' => $dados['senha'] ?? null,
                ];
            }

            $usuario['documento'] = preg_replace('/\D+/', '', (string) ($usuario['documento'] ?? ''));
            if (($usuario['documento'] ?? '') === '') {
                $usuario['documento'] = null;
            }

            if (empty($usuario['email'])) {
                throw new \Exception('E-mail é obrigatório para criar pedido');
            }

            $emailInformado = $usuario['email'];
            
            // 1. Buscar/criar usuário na tabela usuarios
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$usuario['email']]);
            $existingUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Se não encontrou por e-mail, tentar por documento (CPF/CNPJ) pois pode ser UNIQUE
            if ((!$existingUser || empty($existingUser['id'])) && !empty($usuario['documento'])) {
                $stmtDoc = $db->prepare("SELECT id, email, name, password, senha, role, perfil FROM usuarios WHERE documento = ? LIMIT 1");
                $stmtDoc->execute([$usuario['documento']]);
                $existingUserByDoc = $stmtDoc->fetch(\PDO::FETCH_ASSOC);
                if ($existingUserByDoc && !empty($existingUserByDoc['id'])) {
                    // Se encontrou pelo CPF mas o e-mail informado é diferente, exigir login com o e-mail correto
                    if (!$this->authService->estaLogado() && !empty($existingUserByDoc['email']) && strcasecmp((string) $existingUserByDoc['email'], (string) $emailInformado) !== 0) {
                        throw new \Exception('Já existe uma conta com esse CPF. Faça login com o e-mail cadastrado para finalizar a compra.');
                    }
                    $existingUser = ['id' => $existingUserByDoc['id']];
                    // Preferir dados já existentes do cadastro
                    if (!empty($existingUserByDoc['email'])) {
                        $usuario['email'] = $existingUserByDoc['email'];
                    }
                    if (!empty($existingUserByDoc['name']) || !empty($existingUserByDoc['nome'])) {
                        $usuario['nome'] = $existingUserByDoc['nome'] ?? $existingUserByDoc['name'];
                    }
                }
            }
            
            if ($existingUser && !empty($existingUser['id'])) {
                $usuarioId = $existingUser['id'];
                $this->debugLog('[CRIAR_PEDIDO] Usuario encontrado: ' . $usuarioId);

                // Se não estiver logado, exigir que a senha informada seja válida
                if (!$this->authService->estaLogado()) {
                    $senhaInformada = $dados['senha'] ?? $usuario['senha'] ?? null;
                    if (empty($senhaInformada)) {
                        throw new \Exception('Senha é obrigatória para concluir com este e-mail');
                    }

                    $usuarioModelApp = new \App\Models\Usuario();
                    $authOk = $usuarioModelApp->authenticate($usuario['email'], $senhaInformada);
                    if (!$authOk) {
                        throw new \Exception('E-mail já cadastrado com senha diferente');
                    }
                }
            } else {
                $stmt = $db->prepare("INSERT INTO usuarios (name, email, password, documento, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $senhaPlano = $usuario['senha'] ?? 'temp123';
                $stmt->execute([
                    $usuario['nome'] ?? 'Cliente',
                    $usuario['email'],
                    password_hash((string) $senhaPlano, PASSWORD_DEFAULT),
                    $usuario['documento']
                ]);
                $usuarioId = $db->lastInsertId();
                $this->debugLog('[CRIAR_PEDIDO] Usuario criado: ' . $usuarioId);
            }

            // Garantir suite e aceite de termos para conta criada/associada no checkout
            try {
                $colsU = [];
                try {
                    $stmtColsU = $db->query('DESCRIBE usuarios');
                    $colsU = $stmtColsU ? ($stmtColsU->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsU = [];
                }

                if (!empty($usuarioId) && is_array($colsU) && !empty($colsU)) {
                    // suite: quando vazio, usar o próprio id
                    if (in_array('suite', $colsU, true)) {
                        try {
                            $stSuite = $db->prepare('UPDATE usuarios SET `suite` = ? WHERE id = ? AND (`suite` IS NULL OR `suite` = 0)');
                            $stSuite->execute([(int) $usuarioId, (int) $usuarioId]);
                        } catch (\Exception $e) {
                        }
                    }

                    // termos aceitos: o checkout sempre exige consentimento_legal, então persistimos no usuário
                    $hasConsent = !empty($dados['consentimento_legal']);
                    if ($hasConsent) {
                        $upd = [];
                        if (in_array('termos_aceitos_em', $colsU, true)) {
                            $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                        }
                        if (in_array('termos_aceitos_ip', $colsU, true)) {
                            $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                        }
                        if (in_array('termos_versao', $colsU, true)) {
                            $upd['termos_versao'] = '1.0';
                        }
                        if (!empty($upd)) {
                            $sets = [];
                            $params = [':id' => (int) $usuarioId];
                            foreach ($upd as $k => $v) {
                                $sets[] = $k . ' = :' . $k;
                                $params[':' . $k] = $v;
                            }
                            $sql = 'UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id';
                            $stUpd = $db->prepare($sql);
                            $stUpd->execute($params);
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            // Login automático quando não estava logado
            if (!$this->authService->estaLogado()) {
                try {
                    $stmtUser = $db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                    $stmtUser->execute([$usuarioId]);
                    $rowUser = $stmtUser->fetch(\PDO::FETCH_ASSOC);

                    if ($rowUser) {
                        $rowUser['perfil'] = $rowUser['perfil'] ?? ($rowUser['role'] ?? 'cliente');
                        $rowUser['nome'] = $rowUser['nome'] ?? ($rowUser['name'] ?? ($usuario['nome'] ?? 'Cliente'));
                        $this->authService->criarSessao($rowUser);
                    }
                } catch (\Exception $e) {
                }
            }
            
            // 2. Buscar/criar cliente na tabela clientes (foreign key obrigatória)
            $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
            $stmt->execute([$usuario['email']]);
            $existingClient = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingClient && !empty($existingClient['id'])) {
                $clienteId = $existingClient['id'];
                $this->debugLog('[CRIAR_PEDIDO] Cliente encontrado: ' . $clienteId);
            } else {
                // Usar estrutura REAL da tabela clientes
                $stmt = $db->prepare("INSERT INTO clientes (usuario_id, nome_razao_social, cpf_cnpj, telefone, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $usuarioId,
                    $usuario['nome'] ?? 'Cliente',
                    $usuario['documento'],
                    $usuario['telefone'] ?? '',
                    $usuario['email']
                ]);
                $clienteId = $db->lastInsertId();
                $this->debugLog('[CRIAR_PEDIDO] Cliente criado com estrutura real: ' . $clienteId);
            }
            
            // 3. Validar IDs antes de continuar
            if (empty($usuarioId) || empty($clienteId)) {
                throw new \Exception('Falha ao obter IDs válidos de usuário/cliente');
            }
            
            // Obter moeda selecionada pelo cliente
            // Default deve ser BRL (a loja opera em BRL por padrão; USD é opcional)
            $moedaSelecionada = strtoupper(trim((string) ($dados['moeda'] ?? 'BRL')));
            if (!in_array($moedaSelecionada, ['BRL', 'USD', 'EUR'], true)) {
                $moedaSelecionada = 'BRL';
            }

            // Endereço internacional exige pagamento em USD (gateways BR não aceitam).
            // Usamos o país do formulário (pais) como referência do endereço de entrega.
            $paisEntrega = strtoupper(trim((string) ($dados['pais'] ?? 'BR')));
            if ($paisEntrega !== '' && $paisEntrega !== 'BR') {
                $moedaSelecionada = 'USD';
            }
            $this->debugLog('[CRIAR_PEDIDO] Moeda selecionada pelo cliente: ' . $moedaSelecionada);
            
            // Calcular totais
            $subtotal = 0;
            $pesoTotal = 0;

            // Descobrir coluna de peso em produtos (peso/weight)
            $pesoCol = null;
            $pesoCache = [];
            try {
                $stmtColsProd = $db->query('DESCRIBE produtos');
                $colsProd = $stmtColsProd ? ($stmtColsProd->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                foreach (['peso', 'weight'] as $c) {
                    if (is_array($colsProd) && in_array($c, $colsProd, true)) {
                        $pesoCol = $c;
                        break;
                    }
                }
            } catch (\Exception $e) {
                $pesoCol = null;
            }

            $stPeso = null;
            if ($pesoCol) {
                try {
                    $stPeso = $db->prepare('SELECT ' . $pesoCol . ' AS peso FROM produtos WHERE id = ? LIMIT 1');
                } catch (\Exception $e) {
                    $stPeso = null;
                }
            }
            
            $this->debugLog('[CRIAR_PEDIDO] Calculando totais...');
            
            foreach ($carrinho as $item) {
                // Verificar diferentes campos de preço
                $precoUnitario = $item['preco_unitario'] ?? $item['price'] ?? $item['preco'] ?? 0;
                $quantidade = $item['quantidade'] ?? 1;
                
                $subtotal += $precoUnitario * $quantidade;

                // Peso real (kg) quando disponível
                $pesoUnit = (float) ($item['peso'] ?? ($item['weight'] ?? 0));
                if ($pesoUnit <= 0) {
                    $pid = (int) ($item['produto_id'] ?? 0);
                    if ($pid > 0) {
                        if (array_key_exists($pid, $pesoCache)) {
                            $pesoUnit = (float) $pesoCache[$pid];
                        } elseif ($stPeso) {
                            try {
                                $stPeso->execute([$pid]);
                                $pesoDb = (float) ($stPeso->fetchColumn() ?: 0);
                                if ($pesoDb > 0) {
                                    $pesoUnit = $pesoDb;
                                }
                                $pesoCache[$pid] = $pesoUnit;
                            } catch (\Exception $e) {
                                $pesoCache[$pid] = 0.0;
                            }
                        }
                    }
                }
                if ($pesoUnit <= 0) {
                    $pesoUnit = 0.5;
                }
                $pesoTotal += ($pesoUnit * (int) $quantidade);
                
                $this->debugLog('[CRIAR_PEDIDO] Item processado: ' . json_encode($item));
                $this->debugLog('[CRIAR_PEDIDO] Preco unitario: ' . $precoUnitario . ', Quantidade: ' . $quantidade . ', Peso unit: ' . $pesoUnit . ', Peso item: ' . ($pesoUnit * (int) $quantidade));
            }
            
            $this->debugLog('[CRIAR_PEDIDO] Subtotal: ' . $subtotal);
            $this->debugLog('[CRIAR_PEDIDO] Peso total: ' . $pesoTotal);
            
            // Taxas baseadas na moeda selecionada
            // Base dos cálculos é USD (produtos/carrinho normalmente estão em USD). Se BRL, converter no final.
            $taxaConversao = 1.0;
            if ($moedaSelecionada === 'BRL') {
                try {
                    $r = (float) $this->carrinhoModel->getTaxaConversao('BRL');
                    if ($r > 1.01) {
                        $taxaConversao = $r;
                    }
                } catch (\Exception $e) {
                }
            }

            // Calcular em USD (mesma regra do carrinho/checkout)
            $freteUsd = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
            $taxaServicoUsd = (float) $this->carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
            $impostosUsd = (float) $this->carrinhoModel->calcularImpostos($subtotal, $freteUsd);

            // PIX: desconto configurável na taxa de serviço
            $formaPagamentoSel = strtolower(trim((string) ($dados['forma_pagamento'] ?? '')));
            if ($formaPagamentoSel === 'pix') {
                $pct = (float) $this->getPixDescontoTaxaServicoPercent();
                if ($pct > 0) {
                    $taxaServicoUsd = max(0.0, $taxaServicoUsd * (1.0 - ($pct / 100.0)));
                }
            }

            $paisEntrega = strtoupper(trim((string) ($dados['pais'] ?? 'BR')));
            if ($paisEntrega === '') {
                $paisEntrega = 'BR';
            }
            if ($paisEntrega !== 'BR') {
                $impostosUsd = 0.0;
            }

            // Imposto de compra nos EUA (10%) embutido no subtotal dos produtos.
            if ($paisEntrega === 'US') {
                $subtotal = (float) $subtotal * 1.10;
            }

            $totalUsd = $subtotal + $taxaServicoUsd + $impostosUsd + $freteUsd;

            if ($moedaSelecionada === 'BRL' && $taxaConversao > 1.01) {
                $taxaServico = $taxaServicoUsd * $taxaConversao;
                $impostos = $impostosUsd * $taxaConversao;
                $frete = $freteUsd * $taxaConversao;
                $subtotal = $subtotal * $taxaConversao;
                $total = $totalUsd * $taxaConversao;
                $this->debugLog('[CRIAR_PEDIDO] Calculo em BRL (convertido de USD) - Taxa conversao: ' . $taxaConversao);
            } else {
                $taxaServico = $taxaServicoUsd;
                $impostos = $impostosUsd;
                $frete = $freteUsd;
                $total = $totalUsd;
                $this->debugLog('[CRIAR_PEDIDO] Calculo em USD - Taxa conversao: ' . $taxaConversao);
            }
            
            $this->debugLog('[CRIAR_PEDIDO] Taxa de servico: ' . $taxaServico);
            $this->debugLog('[CRIAR_PEDIDO] Impostos: ' . $impostos);
            $this->debugLog('[CRIAR_PEDIDO] Frete: ' . $frete . ' (' . (($frete == 0) ? 'GRATIS' : 'PAGO') . ')');
            $this->debugLog('[CRIAR_PEDIDO] Total: ' . $total);

            // Idempotência: evitar pedidos duplicados para a mesma tentativa de checkout
            $usuarioIdForIdem = (int) ($usuarioId ?? 0);
            $usuarioForIdem = is_array($usuario) ? $usuario : [];
            $usuarioForIdem['id'] = $usuarioIdForIdem;
            if (empty($usuarioForIdem['email']) && !empty($dados['email'])) {
                $usuarioForIdem['email'] = (string) $dados['email'];
            }
            $idemHash = $this->getIdempotencySignature((array) $dados, (array) $carrinho, (array) $usuarioForIdem, (float) $total, (string) $moedaSelecionada);
            $this->debugLog('[CRIAR_PEDIDO] Idempotency hash: ' . $idemHash);

            $existingPedidoId = $this->findExistingPedidoByIdempotency($usuarioIdForIdem, (string) $moedaSelecionada, (float) $total, $idemHash);
            if (!empty($existingPedidoId)) {
                $this->debugLog('[CRIAR_PEDIDO] Pedido já existe para esta assinatura. Reutilizando ID: ' . $existingPedidoId);
                return ['pedido_id' => (int) $existingPedidoId, 'reused' => true, 'idem' => $idemHash];
            }
            
            // Criar número do pedido
            $numeroPedido = 'BZS' . date('YmdHis') . rand(1000, 9999);
            $this->debugLog('[CRIAR_PEDIDO] Numero do pedido: ' . $numeroPedido);
            
            // Inserir pedido com todos os campos originais
            $db = \Config\Database::getConnection();
            $this->debugLog('[CRIAR_PEDIDO] Conexao com banco obtida');
            
            $sql = "INSERT INTO pedidos (
                usuario_id, nome, numero_pedido, cliente_id, status, 
                subtotal, servicos, impostos, frete, desconto, total, 
                moeda, taxa_conversao, endereco_entrega_id, endereco_cobranca_id, 
                observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $this->debugLog('[CRIAR_PEDIDO] SQL preparado');
            
            $params = [
                $usuarioId,
                $usuario['nome'] ?? 'Cliente', // OBRIGATÓRIO (NOT NULL)
                $numeroPedido,
                $clienteId,
                'pagamento', // ENUM válido
                $subtotal,
                $taxaServico, // MAPEIA PARA servicos
                $impostos,
                $frete,
                0, // desconto
                $total,
                $moedaSelecionada, // Usar moeda selecionada pelo cliente
                $taxaConversao, // Taxa de conversão aplicada
                null, // endereco_entrega_id
                null, // endereco_cobranca_id
                trim((string) (($dados['observacoes'] ?? '') . ' [IDEMPOTENCY:' . $idemHash . ']'))
            ];
            
            $this->debugLog('[CRIAR_PEDIDO] Parametros: ' . json_encode($params));
            
            $stmt->execute($params);
            $this->debugLog('[CRIAR_PEDIDO] Query executado com sucesso');
            
            $pedidoId = $db->lastInsertId();
            $this->debugLog('[CRIAR_PEDIDO] ID gerado: ' . $pedidoId);

            // Persistir dados do cliente/endereço no pedido quando o schema suportar
            try {
                $colsPed = [];
                try {
                    $stmtColsPed = $db->query('DESCRIBE pedidos');
                    $colsPed = $stmtColsPed ? $stmtColsPed->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsPed = [];
                }

                if (is_array($colsPed) && !empty($colsPed)) {
                    $set = [];
                    $p = [];

                    foreach ([
                        'cliente_nome' => (string) ($dados['nome'] ?? ($usuario['nome'] ?? '')),
                        'cliente_email' => (string) ($dados['email'] ?? ($usuario['email'] ?? '')),
                        'email' => (string) ($dados['email'] ?? ($usuario['email'] ?? '')),
                        'customer_email' => (string) ($dados['email'] ?? ($usuario['email'] ?? '')),
                        'cliente_telefone' => (string) ($dados['telefone'] ?? ''),
                        'cliente_documento' => (string) ($dados['documento'] ?? ''),
                        'documento' => (string) ($dados['documento'] ?? ''),
                    ] as $col => $val) {
                        if (in_array($col, $colsPed, true) && $val !== '') {
                            $set[] = $col . ' = :' . $col;
                            $p[$col] = $val;
                        }
                    }

                    foreach ([
                        'destinatario_nome' => (string) ($dados['destinatario_nome'] ?? ''),
                        'destinatario_documento' => (string) ($dados['destinatario_documento'] ?? ''),
                        'destinatario_telefone' => (string) ($dados['destinatario_telefone'] ?? ''),
                    ] as $col => $val) {
                        if (in_array($col, $colsPed, true) && trim($val) !== '') {
                            $set[] = $col . ' = :' . $col;
                            $p[$col] = $val;
                        }
                    }

                    foreach ([
                        'endereco' => (string) ($dados['endereco'] ?? ''),
                        'numero' => (string) ($dados['numero'] ?? ''),
                        'complemento' => (string) ($dados['complemento'] ?? ''),
                        'bairro' => (string) ($dados['bairro'] ?? ''),
                        'cidade' => (string) ($dados['cidade'] ?? ''),
                        'estado' => (string) ($dados['estado'] ?? ''),
                        'cep' => (string) ($dados['cep'] ?? ''),
                        'endereco_entrega' => (string) ($dados['endereco'] ?? ''),
                        'numero_entrega' => (string) ($dados['numero'] ?? ''),
                        'complemento_entrega' => (string) ($dados['complemento'] ?? ''),
                        'bairro_entrega' => (string) ($dados['bairro'] ?? ''),
                        'cidade_entrega' => (string) ($dados['cidade'] ?? ''),
                        'estado_entrega' => (string) ($dados['estado'] ?? ''),
                        'cep_entrega' => (string) ($dados['cep'] ?? ''),
                    ] as $col => $val) {
                        if (in_array($col, $colsPed, true) && $val !== '') {
                            $set[] = $col . ' = :' . $col;
                            $p[$col] = $val;
                        }
                    }

                    if (!empty($set)) {
                        $p['id'] = (int) $pedidoId;
                        $stmtUpd = $db->prepare('UPDATE pedidos SET ' . implode(', ', array_unique($set)) . ' WHERE id = :id');
                        $stmtUpd->execute($p);
                    }
                }
            } catch (\Exception $e) {
            }

            // Origem do pedido (orgânico/checkout) quando a coluna existir
            try {
                $colsPed = [];
                try {
                    $stmtColsPed = $db->query('DESCRIBE pedidos');
                    $colsPed = $stmtColsPed ? $stmtColsPed->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsPed = [];
                }

                if (is_array($colsPed) && in_array('origem_pedido', $colsPed, true)) {
                    $stmtOrigem = $db->prepare('UPDATE pedidos SET origem_pedido = ? WHERE id = ?');
                    $stmtOrigem->execute(['organico', $pedidoId]);
                }
            } catch (\Exception $e) {
            }

            // Criar endereço(s) e vincular ao pedido
            try {
                $enderecoModelApp = new \App\Models\Endereco();

                // Se o usuário selecionou um endereço já cadastrado, reutilizar (não criar novo)
                $enderecoEntregaId = 0;
                foreach (['endereco_entrega_id', 'endereco_id', 'enderecoEntregaId', 'enderecoId'] as $k) {
                    if (isset($dados[$k])) {
                        $enderecoEntregaId = (int) $dados[$k];
                        if ($enderecoEntregaId > 0) {
                            break;
                        }
                    }
                }

                $enderecoCobrancaId = 0;
                foreach (['endereco_cobranca_id', 'enderecoCobrancaId'] as $k) {
                    if (isset($dados[$k])) {
                        $enderecoCobrancaId = (int) $dados[$k];
                        if ($enderecoCobrancaId > 0) {
                            break;
                        }
                    }
                }

                if ($enderecoEntregaId > 0) {
                    try {
                        // Validar se o endereço pertence ao usuário
                        $stVal = $db->prepare('SELECT usuario_id FROM enderecos WHERE id = ? LIMIT 1');
                        $stVal->execute([(int) $enderecoEntregaId]);
                        $uidEnd = (int) ($stVal->fetchColumn() ?: 0);
                        if ($uidEnd !== (int) $usuarioId) {
                            $enderecoEntregaId = 0;
                        }
                    } catch (\Exception $e) {
                        $enderecoEntregaId = 0;
                    }
                }

                if ($enderecoCobrancaId > 0) {
                    try {
                        $stVal = $db->prepare('SELECT usuario_id FROM enderecos WHERE id = ? LIMIT 1');
                        $stVal->execute([(int) $enderecoCobrancaId]);
                        $uidEnd = (int) ($stVal->fetchColumn() ?: 0);
                        if ($uidEnd !== (int) $usuarioId) {
                            $enderecoCobrancaId = 0;
                        }
                    } catch (\Exception $e) {
                        $enderecoCobrancaId = 0;
                    }
                }

                if ($enderecoEntregaId > 0) {
                    if ($enderecoCobrancaId <= 0) {
                        $enderecoCobrancaId = $enderecoEntregaId;
                    }
                    $stmtUpd = $db->prepare('UPDATE pedidos SET endereco_entrega_id = ?, endereco_cobranca_id = ? WHERE id = ?');
                    $stmtUpd->execute([(int) $enderecoEntregaId, (int) $enderecoCobrancaId, (int) $pedidoId]);
                    return ['pedido_id' => (int) $pedidoId, 'reused' => false, 'idem' => $idemHash];
                }

                $enderecosExistentes = [];
                try {
                    $usuarioModelApp = new \App\Models\Usuario();
                    $enderecosExistentes = $usuarioModelApp->getEnderecos($usuarioId);
                } catch (\Exception $e) {
                }

                $principal = empty($enderecosExistentes) ? 1 : 0;
                $enderecoEntregaData = [
                    'usuario_id' => $usuarioId,
                    'tipo' => 'entrega',
                    'cep' => $dados['cep'],
                    'endereco' => $dados['endereco'],
                    'numero' => $dados['numero'] ?? '',
                    'complemento' => $dados['complemento'] ?? '',
                    'bairro' => $dados['bairro'] ?? '',
                    'cidade' => $dados['cidade'],
                    'estado' => $dados['estado'] ?? '',
                    'pais' => (string) (($dados['pais'] ?? '') !== '' ? $dados['pais'] : 'BR'),
                    'principal' => $principal,
                ];

                $enderecoEntregaIdNovo = null;
                if ($enderecoModelApp->create($enderecoEntregaData)) {
                    $enderecoEntregaIdNovo = $enderecoModelApp->getConnection()->lastInsertId();
                }

                // Por enquanto, usar o mesmo endereço para cobrança (pode ser separado depois)
                $enderecoCobrancaIdNovo = $enderecoEntregaIdNovo;

                if (!empty($enderecoEntregaIdNovo)) {
                    $stmtUpd = $db->prepare('UPDATE pedidos SET endereco_entrega_id = ?, endereco_cobranca_id = ? WHERE id = ?');
                    $stmtUpd->execute([(int) $enderecoEntregaIdNovo, (int) $enderecoCobrancaIdNovo, (int) $pedidoId]);
                }
            } catch (\Exception $e) {
                $this->debugLog('[CRIAR_PEDIDO] Falha ao criar/vincular endereco: ' . $e->getMessage());
            }
            
            return ['pedido_id' => (int) $pedidoId, 'reused' => false, 'idem' => $idemHash];
            
        } catch (\Exception $e) {
            $rawMsg = (string) $e->getMessage();
            $this->debugLog('[CRIAR_PEDIDO] Erro ao criar pedido: ' . $rawMsg);
            $this->debugLog('[CRIAR_PEDIDO] Stack: ' . $e->getTraceAsString());

            $friendly = 'Não foi possível finalizar o pedido. Verifique seus dados e tente novamente.';
            $m = strtolower($rawMsg);
            if (
                strpos($m, 'cpf_cnpj') !== false ||
                strpos($m, 'cpf/cnpj') !== false ||
                strpos($m, 'cpf') !== false ||
                strpos($m, 'cnpj') !== false ||
                strpos($m, 'documento') !== false
            ) {
                if (
                    strpos($m, 'cannot be null') !== false ||
                    strpos($m, 'não pode ser nulo') !== false ||
                    strpos($m, 'not null') !== false
                ) {
                    $friendly = 'Informe um CPF/CNPJ válido para continuar. Confira se os números estão corretos e se o documento é realmente seu. Se estiver correto e o erro persistir, entre em contato com o suporte para verificarmos.';
                } elseif (strpos($m, 'duplicate entry') !== false || strpos($m, 'duplic') !== false || strpos($m, 'já cadastrado') !== false) {
                    $friendly = 'Este CPF/CNPJ já está cadastrado. Confira se o CPF/CNPJ é realmente seu e se os números estão corretos. Se estiver correto e o erro persistir, entre em contato com o suporte para verificarmos.';
                } elseif (strpos($m, 'inválido') !== false || strpos($m, 'invalido') !== false || strpos($m, 'invalid') !== false) {
                    $friendly = 'CPF/CNPJ inválido. Confira se os números estão corretos e tente novamente. Se o documento estiver correto e o erro persistir, entre em contato com o suporte para verificarmos.';
                } else {
                    $friendly = 'Houve um problema com seu CPF/CNPJ. Confira se o documento é realmente seu e se os números estão corretos. Se estiver correto e o erro persistir, entre em contato com o suporte para verificarmos.';
                }
            }
            
            // Retornar JSON válido em caso de erro
            $this->json([
                'success' => false,
                'error' => $friendly,
                'code' => 500
            ], 500);
            return false;
        }
    }
}
