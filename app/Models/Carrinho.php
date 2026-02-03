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
            return $alias . '.peso';
        }

        $parts = [];
        foreach ($candidates as $c) {
            $parts[] = 'NULLIF(' . $c . ", 0)";
        }
        return 'COALESCE(' . implode(', ', $parts) . ', 0)';
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
        // Verificar se item já existe
        $stmt = $this->connection->prepare("SELECT * FROM carrinho_items WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id AND COALESCE(produto_variacao_id,0) = COALESCE(:produto_variacao_id,0)");
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
        
        if ($itemExistente) {
            // Atualizar quantidade
            $novaQuantidade = $itemExistente['quantidade'] + $quantidade;
            $novoSubtotal = $novaQuantidade * $produto['valor'];
            
            $stmt = $this->connection->prepare("
                UPDATE carrinho_items 
                SET quantidade = :quantidade, subtotal = :subtotal 
                WHERE id = :id
            ");
            $stmt->bindParam(':quantidade', $novaQuantidade);
            $stmt->bindParam(':subtotal', $novoSubtotal);
            $stmt->bindParam(':id', $itemExistente['id']);
            $stmt->execute();
        } else {
            // Inserir novo item
            $subtotal = $quantidade * $produto['valor'];
            
            $stmt = $this->connection->prepare("
                INSERT INTO carrinho_items (carrinho_id, produto_id, produto_variacao_id, variacao_descricao, quantidade, valor_unitario, subtotal) 
                VALUES (:carrinho_id, :produto_id, :produto_variacao_id, :variacao_descricao, :quantidade, :valor_unitario, :subtotal)
            ");
            $stmt->bindParam(':carrinho_id', $carrinhoId);
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->bindValue(':produto_variacao_id', $produtoVariacaoId);
            $stmt->bindValue(':variacao_descricao', $variacaoDescricao);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':valor_unitario', $produto['valor']);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->execute();
        }
        
        $this->atualizarTotais($carrinhoId);
        return true;
    }

    public function atualizarTotais($carrinhoId) {
        // Obter itens do carrinho
        $pesoExpr = $this->getPesoExpressionForProdutos('p');
        $stmt = $this->connection->prepare("
            SELECT ci.*, {$pesoExpr} AS peso 
            FROM carrinho_items ci 
            JOIN produtos p ON ci.produto_id = p.id 
            WHERE ci.carrinho_id = :carrinho_id
        ");
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $subtotalProdutos = 0;
        $pesoTotal = 0;
        
        foreach ($items as $item) {
            $subtotalProdutos += $item['subtotal'];
            $pesoTotal += $item['peso'] * $item['quantidade'];
        }
        
        // Obter dados do carrinho
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $carrinhoId);
        $stmt->execute();
        $carrinho = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Calcular taxas
        $taxaServico = $this->calcularTaxaServico($pesoTotal, $carrinho['moeda'], $carrinho['taxa_conversao']);
        $valorImpostos = $this->calcularImpostos($subtotalProdutos, $carrinho['frete_manual']);
        
        $valorTotal = $subtotalProdutos + $carrinho['frete_manual'] + $taxaServico + $valorImpostos;
        
        // Atualizar carrinho
        $stmt = $this->connection->prepare("
            UPDATE {$this->table} 
            SET subtotal_produtos = :subtotal, 
                peso_total = :peso, 
                taxa_servico = :taxa_servico, 
                valor_impostos = :impostos, 
                valor_total = :total,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->bindParam(':subtotal', $subtotalProdutos);
        $stmt->bindParam(':peso', $pesoTotal);
        $stmt->bindParam(':taxa_servico', $taxaServico);
        $stmt->bindParam(':impostos', $valorImpostos);
        $stmt->bindParam(':total', $valorTotal);
        $stmt->bindParam(':id', $carrinhoId);
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
        $stmt = $this->connection->prepare("
            SELECT ci.*, p.nome, p.sku, p.descricao, {$pesoExpr} AS peso, p.moeda as moeda_produto
            FROM carrinho_items ci 
            JOIN produtos p ON ci.produto_id = p.id 
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
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->bindValue(':produto_variacao_id', $produtoVariacaoId);
        $stmt->execute();
        
        $this->atualizarTotais($carrinhoId);
    }

    public function setQuantidadeItem($carrinhoId, $produtoId, $quantidade, $produtoVariacaoId = null) {
        $quantidade = (int) $quantidade;
        if ($quantidade < 1) {
            $quantidade = 1;
        }

        // Obter item
        $stmt = $this->connection->prepare("SELECT * FROM carrinho_items WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id AND COALESCE(produto_variacao_id,0) = COALESCE(:produto_variacao_id,0) LIMIT 1");
        $stmt->bindParam(':carrinho_id', $carrinhoId);
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->bindValue(':produto_variacao_id', $produtoVariacaoId);
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
