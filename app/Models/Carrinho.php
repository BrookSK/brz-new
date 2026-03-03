<?php
namespace App\Models;

class Carrinho extends Model {
    protected $table = 'carrinhos';

    private function isDebugEnabled(): bool {
        $v = '';
        if (isset($_ENV['APP_DEBUG'])) {
            $v = (string) $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $v = (string) $_SERVER['APP_DEBUG'];
        }
        $v = strtolower(trim($v));
        return ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on');
    }

    private function debugLog(string $message): void {
        if (!$this->isDebugEnabled()) {
            return;
        }
        try {
            error_log($message);
        } catch (\Exception $e) {
        }
    }

    private function getPesoExpressionForProdutos(string $alias = 'p'): string {
        $cols = [];
        try {
            $stCols = $this->connection->query('DESCRIBE produtos');
            $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $candidates = [];
        foreach (['peso', 'weight', 'product_weight'] as $c) {
            if (is_array($cols) && in_array($c, $cols, true)) {
                $candidates[] = $alias . '.' . $c;
            }
        }

        if (empty($candidates)) {
            return '0';
        }

        $parts = [];
        foreach ($candidates as $c) {
            $parts[] = 'NULLIF(' . $c . ", 0)";
        }
        return 'COALESCE(' . implode(', ', $parts) . ', 0)';
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableColumns(string $table): array {
        try {
            $st = $this->connection->query('DESCRIBE ' . $table);
            return $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getConfigValue(string $key, $default = null) {
        try {
            if (!$this->tableExists('configuracoes_sistema')) {
                return $default;
            }
            $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([(string) $key]);
            $v = $stmt->fetchColumn();
            if ($v === false || $v === null) {
                return $default;
            }
            return $v;
        } catch (\Exception $e) {
            return $default;
        }
    }

    private function getClubeFaixaPercentual(float $pesoClubeTotal): float {
        $pesoClubeTotal = (float) $pesoClubeTotal;
        if ($pesoClubeTotal <= 0) {
            return 0.0;
        }
        if (!$this->tableExists('clube_descontos_faixas')) {
            return 0.0;
        }
        try {
            $sql = "SELECT percentual_desconto\n"
                . "FROM clube_descontos_faixas\n"
                . "WHERE ativo = 1\n"
                . "  AND peso_min_kg <= :peso\n"
                . "  AND (peso_max_kg <= 0 OR :peso <= peso_max_kg)\n"
                . "ORDER BY ordem ASC, peso_min_kg DESC, id ASC\n"
                . "LIMIT 1";
            $st = $this->connection->prepare($sql);
            $st->bindValue(':peso', $pesoClubeTotal);
            $st->execute();
            $pct = $st->fetchColumn();
            $pct = (float) str_replace(',', '.', (string) ($pct ?? 0));
            if ($pct < 0) $pct = 0.0;
            return $pct;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function getOrCreateCarrinho($usuarioId = null, $sessionId = null, $moeda = 'USD') {
        $where = [];
        $params = [];
        
        if ($usuarioId) {
            $where[] = "usuario_id = :usuario_id";
            $params[':usuario_id'] = $usuarioId;
        } else {
            $where[] = "session_id = :session_id";
            $params[':session_id'] = $sessionId;
        }
        
        $where[] = "expira_em > NOW()";
        $where[] = "moeda = :moeda";
        $params[':moeda'] = $moeda;
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $carrinho = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($carrinho) {
            return $carrinho;
        }
        
        // Criar novo carrinho
        $taxaConversao = $this->getTaxaConversao($moeda);
        
        $data = [
            'usuario_id' => $usuarioId,
            'session_id' => $sessionId,
            'moeda' => $moeda,
            'taxa_conversao' => $taxaConversao,
            'expira_em' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ];
        
        $this->create($data);
        return $this->connection->lastInsertId();
    }

    public function getTaxaConversao($moeda) {
        $m = strtoupper(trim((string) $moeda));
        if ($m === '') {
            $m = 'BRL';
        }

        // Preferir taxa configurada pelo admin em configuracoes_sistema
        if ($m === 'BRL') {
            try {
                foreach (['usd_brl_rate', 'sistema_usd_brl_rate'] as $k) {
                    try {
                        $st = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                        $st->execute([$k]);
                        $val = $st->fetchColumn();
                        $v = (float) str_replace(',', '.', trim((string) ($val ?? '')));
                        if ($v > 1.01) {
                            return $v;
                        }
                    } catch (\Exception $e) {
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // Fallback: tabela configuracoes_moeda
        try {
            $stmt = $this->connection->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = :moeda ORDER BY id DESC LIMIT 1");
            $stmt->bindParam(':moeda', $m);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $tx = $result ? (float) ($result['taxa_conversao'] ?? 0) : 0.0;
            if ($tx > 0) {
                return $tx;
            }
        } catch (\Exception $e) {
        }

        return 5.5;
    }

    public function adicionarItem($carrinhoId, $produtoId, $quantidade = 1, $produtoVariacaoId = null, $variacaoDescricao = null) {
        $itemsCols = $this->getTableColumns('carrinho_items');
        $unitCol = (is_array($itemsCols) && in_array('preco_unitario', $itemsCols, true)) ? 'preco_unitario' : 'valor_unitario';
        $varCol = (is_array($itemsCols) && in_array('produto_variacao_id', $itemsCols, true))
            ? 'produto_variacao_id'
            : ((is_array($itemsCols) && in_array('variacao_id', $itemsCols, true)) ? 'variacao_id' : 'produto_variacao_id');

        // Verificar se item já existe
        $stmt = $this->connection->prepare(
            "SELECT * FROM carrinho_items WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id AND COALESCE({$varCol},0) = COALESCE(:produto_variacao_id,0)"
        );
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->bindValue(':produto_variacao_id', $produtoVariacaoId);
        $stmt->execute();
        $itemExistente = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Obter dados do produto
        $produtoModel = new Produto();
        $produto = $produtoModel->find($produtoId);
        
        if (!$produto || $produto['estoque'] < $quantidade) {
            return false;
        }

        // Definir preço unitário efetivo (promo quando válida; variação override tem prioridade)
        $precoBase = (float) ($produto['valor'] ?? 0);
        if ($precoBase < 0) $precoBase = 0.0;
        $precoPromo = (float) ($produto['preco_promocao'] ?? 0);
        if ($precoPromo < 0) $precoPromo = 0.0;
        $precoUnitario = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;

        if ($produtoVariacaoId !== null && (int) $produtoVariacaoId > 0) {
            try {
                if ($this->tableExists('produto_variacoes')) {
                    $stCols = $this->connection->query('DESCRIBE produto_variacoes');
                    $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

                    $hasAtivo = is_array($cols) && in_array('ativo', $cols, true);
                    $select = 'id, produto_id, price_override, stock' . ($hasAtivo ? ', ativo' : '');
                    $sql = 'SELECT ' . $select . ' FROM produto_variacoes WHERE id = ? LIMIT 1';
                    $stVar = $this->connection->prepare($sql);
                    $stVar->execute([(int) $produtoVariacaoId]);
                    $var = $stVar->fetch(\PDO::FETCH_ASSOC) ?: null;

                    if (!$var || empty($var['id'])) {
                        return false;
                    }
                    if (isset($var['produto_id']) && (int) $var['produto_id'] !== (int) $produtoId) {
                        return false;
                    }
                    if ($hasAtivo && isset($var['ativo']) && (int) $var['ativo'] !== 1) {
                        return false;
                    }
                    $stockVar = (int) ($var['stock'] ?? 0);
                    if ($stockVar < (int) $quantidade) {
                        return false;
                    }
                    $override = $var['price_override'] ?? null;
                    if ($override !== null && $override !== '' && (float) $override > 0) {
                        $precoUnitario = (float) $override;
                    }
                }
            } catch (\Throwable $e) {
                // fallback: mantém preço do produto
            }
        }
        
        if ($itemExistente) {
            // Atualizar quantidade
            $novaQuantidade = $itemExistente['quantidade'] + $quantidade;
            $novoSubtotal = $novaQuantidade * $precoUnitario;
            
            $stmt = $this->connection->prepare("
                UPDATE carrinho_items 
                SET quantidade = :quantidade, {$unitCol} = :valor_unitario, subtotal = :subtotal 
                WHERE id = :id
            ");
            $stmt->bindParam(':quantidade', $novaQuantidade);
            $stmt->bindParam(':valor_unitario', $precoUnitario);
            $stmt->bindParam(':subtotal', $novoSubtotal);
            $stmt->bindParam(':id', $itemExistente['id']);
            $stmt->execute();
        } else {
            // Inserir novo item
            $subtotal = $quantidade * $precoUnitario;
            
            $stmt = $this->connection->prepare("
                INSERT INTO carrinho_items (carrinho_id, produto_id, {$varCol}, variacao_descricao, quantidade, {$unitCol}, subtotal) 
                VALUES (:carrinho_id, :produto_id, :produto_variacao_id, :variacao_descricao, :quantidade, :valor_unitario, :subtotal)
            ");
            $stmt->bindParam(':carrinho_id', $carrinhoId);
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->bindValue(':produto_variacao_id', $produtoVariacaoId);
            $stmt->bindValue(':variacao_descricao', $variacaoDescricao);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':valor_unitario', $precoUnitario);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->execute();
        }
        
        $this->atualizarTotais($carrinhoId);
        return true;
    }

    public function atualizarTotais($carrinhoId) {
        // Obter itens do carrinho
        $pesoExpr = $this->getPesoExpressionForProdutos('p');

        $prodCols = $this->getTableColumns('produtos');
        $hasClubeAtivo = is_array($prodCols) && in_array('clube_ativo', $prodCols, true);
        $clubeSelect = $hasClubeAtivo ? ', COALESCE(p.clube_ativo,0) AS clube_ativo' : ', 0 AS clube_ativo';

        $stmt = $this->connection->prepare("
            SELECT ci.*, {$pesoExpr} AS peso {$clubeSelect}
            FROM carrinho_items ci 
            JOIN produtos p ON ci.produto_id = p.id 
            WHERE ci.carrinho_id = :carrinho_id
        ");
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $subtotalProdutos = 0;
        $pesoTotal = 0;

        $pesoClubeTotal = 0.0;
        $subtotalClube = 0.0;
        
        foreach ($items as $item) {
            $subtotalProdutos += $item['subtotal'];
            $pesoTotal += $item['peso'] * $item['quantidade'];

            $isClube = ((int) ($item['clube_ativo'] ?? 0)) === 1;
            if ($isClube) {
                $subtotalClube += (float) ($item['subtotal'] ?? 0);
                $pesoClubeTotal += ((float) ($item['peso'] ?? 0)) * ((int) ($item['quantidade'] ?? 0));
            }
        }
        
        // Obter dados do carrinho
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $carrinhoId);
        $stmt->execute();
        $carrinho = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Calcular taxas
        $taxaServico = $this->calcularTaxaServico($pesoTotal, $carrinho['moeda'], $carrinho['taxa_conversao']);

        $pctDesconto = $this->getClubeFaixaPercentual((float) $pesoClubeTotal);
        $descontoClube = 0.0;
        if ($pctDesconto > 0 && $subtotalClube > 0) {
            $descontoClube = ((float) $subtotalClube) * ($pctDesconto / 100.0);
            if ($descontoClube < 0) {
                $descontoClube = 0.0;
            }
        }

        $subtotalLiquido = (float) $subtotalProdutos - (float) $descontoClube;
        if ($subtotalLiquido < 0) {
            $subtotalLiquido = 0.0;
        }

        $valorImpostos = $this->calcularImpostos($subtotalLiquido, $carrinho['frete_manual']);
        
        $valorTotal = $subtotalLiquido + $carrinho['frete_manual'] + $taxaServico + $valorImpostos;

        $cashbackPct = (float) str_replace(',', '.', (string) $this->getConfigValue('clube_cashback_percent', '0'));
        if ($cashbackPct < 0) $cashbackPct = 0.0;
        $cashbackBase = (float) $subtotalClube - (float) $descontoClube;
        if ($cashbackBase < 0) {
            $cashbackBase = 0.0;
        }
        $cashbackEstimado = 0.0;
        if ($cashbackPct > 0 && $cashbackBase > 0) {
            $cashbackEstimado = $cashbackBase * ($cashbackPct / 100.0);
            if ($cashbackEstimado < 0) {
                $cashbackEstimado = 0.0;
            }
        }
        
        // Atualizar carrinho
        $cartCols = $this->getTableColumns($this->table);
        $setExtra = '';
        if (is_array($cartCols) && in_array('peso_clube_total', $cartCols, true)) {
            $setExtra .= ', peso_clube_total = :peso_clube_total';
        }
        if (is_array($cartCols) && in_array('subtotal_clube', $cartCols, true)) {
            $setExtra .= ', subtotal_clube = :subtotal_clube';
        }
        if (is_array($cartCols) && in_array('desconto_clube', $cartCols, true)) {
            $setExtra .= ', desconto_clube = :desconto_clube';
        }
        if (is_array($cartCols) && in_array('cashback_clube_estimado', $cartCols, true)) {
            $setExtra .= ', cashback_clube_estimado = :cashback_clube_estimado';
        }

        $stmt = $this->connection->prepare("
            UPDATE {$this->table} 
            SET subtotal_produtos = :subtotal, 
                peso_total = :peso, 
                taxa_servico = :taxa_servico, 
                valor_impostos = :impostos, 
                valor_total = :total{$setExtra},
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->bindValue(':subtotal', (float) $subtotalProdutos);
        $stmt->bindValue(':peso', (float) $pesoTotal);
        $stmt->bindValue(':taxa_servico', (float) $taxaServico);
        $stmt->bindValue(':impostos', (float) $valorImpostos);
        $stmt->bindValue(':total', (float) $valorTotal);
        if (strpos($setExtra, ':peso_clube_total') !== false) {
            $stmt->bindValue(':peso_clube_total', (float) $pesoClubeTotal);
        }
        if (strpos($setExtra, ':subtotal_clube') !== false) {
            $stmt->bindValue(':subtotal_clube', (float) $subtotalClube);
        }
        if (strpos($setExtra, ':desconto_clube') !== false) {
            $stmt->bindValue(':desconto_clube', (float) $descontoClube);
        }
        if (strpos($setExtra, ':cashback_clube_estimado') !== false) {
            $stmt->bindValue(':cashback_clube_estimado', (float) $cashbackEstimado);
        }
        $stmt->bindValue(':id', (int) $carrinhoId);
        $stmt->execute();
    }

    public function calcularTaxaServico($pesoKg, $moeda, $taxaConversao) {
        $stmt = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'taxa_servico_usd_por_kg'");
        $stmt->execute();
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $taxaPorKg = $config ? floatval($config['valor']) : 39.0;
        $pesoArredondado = ceil((float) $pesoKg);
        $taxaUSD = $pesoArredondado * $taxaPorKg;
        
        if ($moeda === 'BRL') {
            return $taxaUSD * $taxaConversao;
        }
        
        return $taxaUSD;
    }

    public function calcularImpostos($valorProdutos, $valorFrete) {
        // Regra baseada na Receita Federal (Remessas Postal/Expressa):
        // - Valor aduaneiro ("valor da compra") = produto + frete + seguro
        // - II (Imposto de Importação):
        //   - Remessa Conforme (certificado):
        //     - até US$ 50: 20%
        //     - acima de US$ 50: 60% com desconto de US$ 20
        //   - Não certificado: 60% sem desconto
        // - ICMS: cálculo "por dentro" e incide sobre (valor aduaneiro + II)
        //   BC = (valor aduaneiro + II) / (1 - aliquota_icms)
        //   ICMS = BC * aliquota_icms

        $valorProdutos = (float) $valorProdutos;
        $valorFrete = (float) $valorFrete;

        $aliqIcms = 17.0;
        $certificado = false;
        $seguro = 0.0;
        try {
            $stmt = $this->connection->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('icms_aliquota', 'remessa_conforme_certificado', 'remessa_conforme_prc', 'seguro_valor')");
            $stmt->execute();
            $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($configs as $cfg) {
                $k = (string) ($cfg['chave'] ?? '');
                $vRaw = (string) ($cfg['valor'] ?? '');
                $vRaw = str_replace(',', '.', trim($vRaw));
                if ($k === 'icms_aliquota' && $vRaw !== '') {
                    $a = (float) $vRaw;
                    // Aceitar percentual (17) ou fração (0.17)
                    if ($a > 0 && $a <= 1.0) {
                        $a = $a * 100.0;
                    }
                    if ($a > 0 && $a < 100) {
                        $aliqIcms = $a;
                    }
                }
                if (($k === 'remessa_conforme_certificado' || $k === 'remessa_conforme_prc') && $vRaw !== '') {
                    $vv = strtolower($vRaw);
                    $certificado = ($vv === '1' || $vv === 'true' || $vv === 'yes' || $vv === 'on');
                }
                if ($k === 'seguro_valor' && $vRaw !== '') {
                    $s = (float) $vRaw;
                    if ($s > 0) {
                        $seguro = $s;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $valorAduaneiro = $valorProdutos + $valorFrete + $seguro;
        if ($valorAduaneiro < 0) {
            $valorAduaneiro = 0.0;
        }

        // II
        $ii = 0.0;
        if ($certificado) {
            if ($valorAduaneiro <= 50.0) {
                $ii = 0.20 * $valorAduaneiro;
            } else {
                $ii = (0.60 * $valorAduaneiro) - 20.0;
                if ($ii < 0) {
                    $ii = 0.0;
                }
            }
        } else {
            $ii = 0.60 * $valorAduaneiro;
        }

        $this->debugLog('[IMPOSTOS] valorProdutos=' . $valorProdutos . ' valorFrete=' . $valorFrete . ' seguro=' . $seguro . ' valorAduaneiro=' . $valorAduaneiro . ' certificado=' . ($certificado ? '1' : '0') . ' II=' . $ii);

        // ICMS "por dentro" sobre (valor aduaneiro + II)
        $baseIcms = $valorAduaneiro + $ii;
        $p = ((float) $aliqIcms) / 100.0;
        $icms = 0.0;
        if ($p > 0 && $p < 1) {
            $bc = $baseIcms / (1.0 - $p);
            $icms = $bc * $p;
        }

        $this->debugLog('[IMPOSTOS] aliqIcms=' . $aliqIcms . ' baseIcms=' . $baseIcms . ' icms=' . $icms);

        return $ii + $icms;
    }

    public function getItems($carrinhoId) {
        $pesoExpr = $this->getPesoExpressionForProdutos('p');
        $prodCols = $this->getTableColumns('produtos');
        $hasClubeAtivo = is_array($prodCols) && in_array('clube_ativo', $prodCols, true);
        $clubeSelect = $hasClubeAtivo ? ', COALESCE(p.clube_ativo,0) AS clube_ativo' : ', 0 AS clube_ativo';

        $hasMoedaProduto = is_array($prodCols) && (in_array('moeda', $prodCols, true) || in_array('currency', $prodCols, true));
        $moedaCol = '';
        if ($hasMoedaProduto) {
            $moedaCol = in_array('moeda', $prodCols, true) ? 'p.moeda' : 'p.currency';
        }
        $moedaSelect = $moedaCol !== '' ? (', ' . $moedaCol . ' as moeda_produto') : '';

        $hasSku = is_array($prodCols) && in_array('sku', $prodCols, true);
        $hasDesc = is_array($prodCols) && in_array('descricao', $prodCols, true);
        $skuSelect = $hasSku ? ', p.sku' : ', NULL AS sku';
        $descSelect = $hasDesc ? ', p.descricao' : ', NULL AS descricao';

        $stmt = $this->connection->prepare("
            SELECT ci.*, p.nome{$skuSelect}{$descSelect}, {$pesoExpr} AS peso{$moedaSelect} {$clubeSelect}
            FROM carrinho_items ci 
            LEFT JOIN produtos p ON ci.produto_id = p.id 
            WHERE ci.carrinho_id = :carrinho_id
        ");
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function limparCarrinho($carrinhoId) {
        $stmt = $this->connection->prepare("DELETE FROM carrinho_items WHERE carrinho_id = :carrinho_id");
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->execute();
        
        $this->atualizarTotais($carrinhoId);
    }

    public function removerItem($carrinhoId, $produtoId, $produtoVariacaoId = null) {
        $stmt = $this->connection->prepare("DELETE FROM carrinho_items WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id AND COALESCE(produto_variacao_id,0) = COALESCE(:produto_variacao_id,0)");
        $stmt->bindValue(':carrinho_id', (int) $carrinhoId, \PDO::PARAM_INT);
        $stmt->bindValue(':produto_id', (int) $produtoId, \PDO::PARAM_INT);
        if ($produtoVariacaoId === null || $produtoVariacaoId === '') {
            $stmt->bindValue(':produto_variacao_id', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':produto_variacao_id', (int) $produtoVariacaoId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $affected = (int) $stmt->rowCount();
        
        $this->atualizarTotais($carrinhoId);
        return $affected;
    }

    public function setQuantidadeItem($carrinhoId, $produtoId, $quantidade, $produtoVariacaoId = null) {
        $quantidade = (int) $quantidade;
        if ($quantidade < 1) {
            $quantidade = 1;
        }

        // Obter item
        $stmt = $this->connection->prepare("SELECT * FROM carrinho_items WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id AND COALESCE(produto_variacao_id,0) = COALESCE(:produto_variacao_id,0) LIMIT 1");
        $stmt->bindValue(':carrinho_id', (int) $carrinhoId, \PDO::PARAM_INT);
        $stmt->bindValue(':produto_id', (int) $produtoId, \PDO::PARAM_INT);
        if ($produtoVariacaoId === null || $produtoVariacaoId === '') {
            $stmt->bindValue(':produto_variacao_id', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':produto_variacao_id', (int) $produtoVariacaoId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $item = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$item) {
            return false;
        }

        $valorUnitario = (float) ($item['valor_unitario'] ?? 0);
        $subtotal = $quantidade * $valorUnitario;

        $stmt = $this->connection->prepare("UPDATE carrinho_items SET quantidade = :q, subtotal = :s WHERE id = :id");
        $stmt->bindValue(':q', $quantidade);
        $stmt->bindValue(':s', $subtotal);
        $stmt->bindValue(':id', (int) $item['id']);
        $stmt->execute();

        $this->atualizarTotais($carrinhoId);
        return true;
    }
}
