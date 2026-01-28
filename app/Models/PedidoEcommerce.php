<?php
namespace App\Models;

class PedidoEcommerce extends Model {
    protected $table = 'pedidos';

    public function getPedidos($usuarioId = null, $limite = 10, $offset = 0) {
        $sql = "SELECT p.* FROM {$this->table} p";
        $where = [];
        $params = [];

        if (!empty($usuarioId)) {
            $where[] = 'p.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $usuarioId;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset';

        $stmt = $this->connection->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $stmt->bindValue(':limite', (int) $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function criarPedidoAPartirDoCarrinho($carrinhoId, $usuarioId, $enderecoEntregaId, $enderecoCobrancaId, $dadosPagamento) {
        $this->connection->beginTransaction();
        
        try {
            // Obter carrinho
            $carrinhoModel = new Carrinho();
            $carrinho = $carrinhoModel->find($carrinhoId);
            $items = $carrinhoModel->getItems($carrinhoId);
            
            if (empty($items)) {
                throw new \Exception('Carrinho vazio');
            }
            
            // Gerar código do pedido
            $codigoPedido = $this->gerarCodigoPedido();
            
            // Calcular totais
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += ($item['preco'] * $item['quantidade']);
            }
            
            // Inserir pedido
            $stmt = $this->connection->prepare("
                INSERT INTO {$this->table} (
                    usuario_id, codigo_pedido, status, subtotal, valor_total, 
                    moeda, endereco_entrega_id, endereco_cobranca_id, created_at
                ) VALUES (
                    :usuario_id, :codigo_pedido, 'pago', :subtotal, :valor_total,
                    :moeda, :endereco_entrega_id, :endereco_cobranca_id, NOW()
                )
            ");
            $stmt->bindParam(':usuario_id', $usuarioId);
            $stmt->bindParam(':codigo_pedido', $codigoPedido);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->bindParam(':valor_total', $subtotal);
            $stmt->bindParam(':moeda', $dadosPagamento['moeda']);
            $stmt->bindParam(':endereco_entrega_id', $enderecoEntregaId);
            $stmt->bindParam(':endereco_cobranca_id', $enderecoCobrancaId);
            $stmt->execute();
            
            $pedidoId = $this->connection->lastInsertId();
            
            // Criar itens do pedido
            foreach ($items as $item) {
                $itemData = [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['produto_id'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco'],
                    'subtotal' => $item['preco'] * $item['quantidade']
                ];
                $this->connection->prepare("
                    INSERT INTO pedido_items (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
                    VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal)
                ")->execute($itemData);
                
                // Atualizar estoque
                $produtoModel = new Produto();
                $produtoModel->updateEstoque($item['produto_id'], $item['quantidade']);
            }
            
            // Adicionar histórico de status
            $this->adicionarHistoricoStatus($pedidoId, null, 'pago', 'Pedido criado e pago com sucesso', $usuarioId);
            
            $this->connection->commit();
            
            // Disparar evento
            $this->dispararEvento('pagamento_aprovado', $pedidoId);
            
            return $pedidoId;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function gerarCodigoPedido() {
        do {
            $codigo = 'BZS' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt = $this->connection->prepare("SELECT id FROM {$this->table} WHERE codigo_pedido = :codigo");
            $stmt->bindParam(':codigo', $codigo);
            $stmt->execute();
        } while ($stmt->fetch());
        
        return $codigo;
    }

    public function adicionarHistoricoStatus($pedidoId, $statusAnterior, $statusNovo, $observacao, $usuarioId) {
        $stmt = $this->connection->prepare("
            INSERT INTO pedido_status_history (pedido_id, status_anterior, status_novo, observacoes, usuario_id) 
            VALUES (:pedido_id, :status_anterior, :status_novo, :observacoes, :usuario_id)
        ");
        $stmt->bindParam(':pedido_id', $pedidoId);
        $stmt->bindParam(':status_anterior', $statusAnterior);
        $stmt->bindParam(':status_novo', $statusNovo);
        $stmt->bindParam(':observacoes', $observacao);
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();
    }

    public function atualizarStatus($pedidoId, $novoStatus, $observacao = '', $usuarioId = null) {
        $this->connection->beginTransaction();
        
        try {
            // Obter status atual
            $pedidoAtual = $this->find($pedidoId);
            $statusAnterior = $pedidoAtual['status'];
            
            // Atualizar pedido
            $this->update($pedidoId, ['status' => $novoStatus]);
            
            // Adicionar histórico
            $this->adicionarHistoricoStatus($pedidoId, $statusAnterior, $novoStatus, $observacao, $usuarioId);
            
            $this->connection->commit();
            
            // Disparar evento
            $this->dispararEvento($this->mapearStatusParaEvento($novoStatus), $pedidoId);
            
            return true;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function mapearStatusParaEvento($status) {
        $mapeamento = [
            'consolidado' => 'pedido_consolidado',
            'rascunho_etiqueta' => 'rascunho_etiqueta_gerado',
            'etiqueta_efetivada' => 'etiqueta_efetivada',
            'enviado' => 'pedido_enviado',
            'entrega_finalizada' => 'pedido_finalizado'
        ];
        
        return $mapeamento[$status] ?? null;
    }

    public function getComDetalhes($pedidoId) {
        $joinPagamentos = '';
        $selectPagamentos = '';
        $selectFormaPagamento = '';

        try {
            $stmtColsP = $this->connection->query("DESCRIBE {$this->table}");
            $colsP = $stmtColsP->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsP) && in_array('forma_pagamento', $colsP, true)) {
                $selectFormaPagamento = ', p.forma_pagamento as forma_pagamento';
            }
        } catch (\Exception $e) {
        }

        try {
            $stmtColsPg = $this->connection->query('DESCRIBE pagamentos');
            $colsPg = $stmtColsPg->fetchAll(\PDO::FETCH_COLUMN);
            if (is_array($colsPg) && !empty($colsPg)) {
                $joinPagamentos = 'LEFT JOIN pagamentos pg ON p.id = pg.pedido_id';

                $metodoCol = null;
                foreach (['metodo', 'forma_pagamento', 'payment_method', 'tipo'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $metodoCol = $c;
                        break;
                    }
                }

                $statusCol = null;
                foreach (['status', 'status_pagamento', 'payment_status'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $statusCol = $c;
                        break;
                    }
                }

                $gatewayCol = null;
                foreach (['gateway', 'provedor', 'provider'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $gatewayCol = $c;
                        break;
                    }
                }

                $transacaoCol = null;
                foreach (['codigo_transacao', 'transaction_id', 'transacao', 'payment_id'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $transacaoCol = $c;
                        break;
                    }
                }

                $dataCol = null;
                foreach (['data_pagamento', 'paid_at', 'data_confirmacao', 'updated_at', 'created_at'] as $c) {
                    if (in_array($c, $colsPg, true)) {
                        $dataCol = $c;
                        break;
                    }
                }

                if (!empty($metodoCol)) {
                    $selectPagamentos .= ", pg.{$metodoCol} AS pagamento_metodo";
                }
                if (!empty($statusCol)) {
                    $selectPagamentos .= ", pg.{$statusCol} AS pagamento_status";
                }
                if (!empty($gatewayCol)) {
                    $selectPagamentos .= ", pg.{$gatewayCol} AS pagamento_gateway";
                }
                if (!empty($transacaoCol)) {
                    $selectPagamentos .= ", pg.{$transacaoCol} AS pagamento_transacao";
                }
                if (!empty($dataCol)) {
                    $selectPagamentos .= ", pg.{$dataCol} AS pagamento_data";
                }
            }
        } catch (\Exception $e) {
        }

        // Adaptar query para a estrutura correta das tabelas
        $stmt = $this->connection->prepare("
            SELECT p.*, 
                   COALESCE(c.nome_razao_social, u.nome, u.name, p.nome) as cliente_nome,
                   COALESCE(c.email, u.email) as cliente_email,
                   COALESCE(c.telefone, u.telefone) as cliente_telefone
                   {$selectFormaPagamento}
                   {$selectPagamentos},
                   e_ent.cep as cep_entrega, e_ent.endereco as endereco_entrega, 
                   e_ent.numero as numero_entrega, e_ent.complemento as complemento_entrega,
                   e_ent.bairro as bairro_entrega, e_ent.cidade as cidade_entrega, e_ent.estado as estado_entrega,
                   e_cob.cep as cep_cobranca
            FROM {$this->table} p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN clientes c ON p.cliente_id = c.id
            {$joinPagamentos}
            LEFT JOIN enderecos e_ent ON p.endereco_entrega_id = e_ent.id
            LEFT JOIN enderecos e_cob ON p.endereco_cobranca_id = e_cob.id
            WHERE p.id = :id
        ");
        $stmt->bindParam(':id', $pedidoId);
        $stmt->execute();
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($pedido) {
            // Obter itens do pedido com dados do produto
            try {
                // Buscar itens diretamente com as colunas que criamos
                $stmt = $this->connection->prepare("
                    SELECT 
                        pi.id,
                        pi.pedido_id,
                        pi.produto_id,
                        pi.quantidade,
                        pi.preco_unitario,
                        pi.subtotal,
                        pi.nome_produto,
                        pi.nome_produto_sku,
                        pi.produto_preco,
                        pi.produto_ncm,
                        pi.produto_peso,
                        pi.produto_dimensoes,
                        pi.produto_tipo,
                        pi.produto_status,
                        pi.created_at,
                        (SELECT pf.nome_arquivo 
                         FROM produto_fotos pf 
                         WHERE pf.produto_id = pi.produto_id 
                         ORDER BY pf.principal DESC, pf.ordem ASC 
                         LIMIT 1) as imagem_principal
                    FROM pedido_itens pi 
                    WHERE pi.pedido_id = :id 
                    ORDER BY pi.id
                ");
                $stmt->bindParam(':id', $pedidoId);
                $stmt->execute();
                $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Garantir que os itens tenham todos os campos necessários
                foreach ($itens as &$item) {
                    $item['referencia'] = $item['referencia'] ?? $item['nome_produto_sku'] ?? '';
                    $item['imagem'] = $item['imagem_principal'] ?? 'default.jpg';
                    $item['descricao_produto'] = $item['descricao_produto'] ?? '';
                    
                    // Se não tiver nome_produto, usar fallback
                    if (empty($item['nome_produto'])) {
                        $item['nome_produto'] = 'Produto #' . $item['produto_id'];
                    }
                    
                    $item['subtotal'] = ($item['preco_unitario'] ?? 0) * ($item['quantidade'] ?? 0);
                }
                
                $pedido['items'] = $itens;
                
            } catch (\Exception $e) {
                $pedido['items'] = [];
            }
            
            // Obter histórico de status
            try {
                $stmt = $this->connection->prepare("
                    SELECT psh.*, u.nome as usuario_alterou 
                    FROM pedido_status_history psh 
                    LEFT JOIN usuarios u ON psh.usuario_id = u.id 
                    WHERE psh.pedido_id = :id 
                    ORDER BY psh.created_at DESC
                ");
                $stmt->bindParam(':id', $pedidoId);
                $stmt->execute();
                $pedido['historico'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Garantir que o histórico tenha todos os campos
                foreach ($pedido['historico'] as &$item) {
                    $item['novo_status'] = $item['status_novo'] ?? 'Status atualizado';
                    $item['observacao'] = $item['observacoes'] ?? 'Sem observação';
                    $item['usuario_alterou'] = $item['usuario_alterou'] ?? 'Sistema';
                }
                
            } catch (\Exception $e) {
                $pedido['historico'] = [];
            }
            
            // Garantir que o pedido tenha todos os campos necessários
            $pedido['codigo_pedido'] = $pedido['numero_pedido'] ?? 'PED-' . str_pad($pedidoId, 6, '0', STR_PAD_LEFT);
            $pedido['status'] = $pedido['status'] ?? 'pendente';
            $pedido['subtotal_produtos'] = $pedido['subtotal'] ?? 0;
            $pedido['valor_frete'] = $pedido['frete'] ?? 0;
            $pedido['taxa_servico'] = $pedido['servicos'] ?? 0;
            $pedido['valor_impostos'] = $pedido['impostos'] ?? 0;
            $pedido['valor_total'] = $pedido['total'] ?? 0;
            
            // Garantir que os campos de endereço tenham valores padrão
            $pedido['endereco_entrega'] = $pedido['endereco_entrega'] ?? 'Não informado';
            $pedido['numero_entrega'] = $pedido['numero_entrega'] ?? '';
            $pedido['complemento_entrega'] = $pedido['complemento_entrega'] ?? '';
            $pedido['bairro_entrega'] = $pedido['bairro_entrega'] ?? '';
            $pedido['cidade_entrega'] = $pedido['cidade_entrega'] ?? '';
            $pedido['estado_entrega'] = $pedido['estado_entrega'] ?? '';
            $pedido['cep_entrega'] = $pedido['cep_entrega'] ?? '';
        }
        
        return $pedido;
    }

    public function getRastreamento($pedidoId) {
        try {
            // Adaptar query para a estrutura correta da tabela pedido_status_historico
            $stmt = $this->connection->prepare("
                SELECT psh.*, u.nome as usuario_alterou 
                FROM pedido_status_history psh 
                LEFT JOIN usuarios u ON psh.usuario_id = u.id 
                WHERE psh.pedido_id = :id 
                ORDER BY psh.created_at DESC
            ");
            $stmt->bindParam(':id', $pedidoId);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function dispararEvento($eventoNome, $pedidoId) {
        // Aqui será implementada a lógica de disparo de eventos
        // Por enquanto, apenas registrar que o evento ocorreu
        error_log("Evento disparado: {$eventoNome} para pedido #{$pedidoId}");
    }
}
