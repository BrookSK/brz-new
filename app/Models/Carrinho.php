<?php
namespace App\Models;

class Carrinho extends Model {
    protected $table = 'carrinhos';

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
        $stmt = $this->connection->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = :moeda");
        $stmt->bindParam(':moeda', $moeda);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? $result['taxa_conversao'] : 5.5;
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
        $stmt = $this->connection->prepare("
            SELECT ci.*, p.peso 
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
        $taxaUSD = $pesoKg * $taxaPorKg;
        
        if ($moeda === 'BRL') {
            return $taxaUSD * $taxaConversao;
        }
        
        return $taxaUSD;
    }

    public function calcularImpostos($valorProdutos, $valorFrete) {
        $stmt = $this->connection->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('icms_aliquota', 'ipi_aliquota')");
        $stmt->execute();
        $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $icms = 0;
        $ipi = 0;
        
        foreach ($configs as $config) {
            if ($config['chave'] === 'icms_aliquota') {
                $icms = floatval($config['valor']);
            } elseif ($config['chave'] === 'ipi_aliquota') {
                $ipi = floatval($config['valor']);
            }
        }
        
        $baseCalculo = $valorProdutos + $valorFrete;
        $valorICMS = $baseCalculo * ($icms / 100);
        $valorIPI = $baseCalculo * ($ipi / 100);
        
        return $valorICMS + $valorIPI;
    }

    public function getItems($carrinhoId) {
        $pesoCol = 'peso';
        try {
            $stCols = $this->connection->query('DESCRIBE produtos');
            $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (is_array($cols) && !empty($cols)) {
                if (!in_array('peso', $cols, true) && in_array('weight', $cols, true)) {
                    $pesoCol = 'weight';
                } elseif (!in_array('peso', $cols, true) && in_array('product_weight', $cols, true)) {
                    $pesoCol = 'product_weight';
                }
            }
        } catch (\Exception $e) {
            $pesoCol = 'peso';
        }

        $stmt = $this->connection->prepare("
            SELECT ci.*, p.nome, p.sku, p.descricao, p." . $pesoCol . " AS peso, p.moeda as moeda_produto
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
