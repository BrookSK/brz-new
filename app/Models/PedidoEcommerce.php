<?php
namespace App\Models;

class PedidoEcommerce extends Model {
    protected $table = 'pedidos';

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
            
            // Calcular valor em BRL
            $valorTotalBrl = $carrinho['valor_total'] * $carrinho['taxa_conversao'];
            
            // Criar pedido
            $pedidoData = [
                'codigo_pedido' => $codigoPedido,
                'usuario_id' => $usuarioId,
                'endereco_entrega_id' => $enderecoEntregaId,
                'endereco_cobranca_id' => $enderecoCobrancaId,
                'moeda_original' => $carrinho['moeda'],
                'taxa_conversao_utilizada' => $carrinho['taxa_conversao'],
                'subtotal_produtos' => $carrinho['subtotal_produtos'],
                'valor_frete' => $carrinho['frete_manual'],
                'taxa_servico' => $carrinho['taxa_servico'],
                'valor_impostos' => $carrinho['valor_impostos'],
                'valor_total' => $carrinho['valor_total'],
                'valor_total_brl' => $valorTotalBrl,
                'payment_gateway' => $dadosPagamento['gateway'],
                'payment_id' => $dadosPagamento['payment_id'],
                'payment_status' => 'approved',
                'pago_em' => date('Y-m-d H:i:s'),
                'status' => 'pago',
                'peso_total' => $carrinho['peso_total'],
                'criado_por' => $usuarioId
            ];
            
            $this->create($pedidoData);
            $pedidoId = $this->connection->lastInsertId();
            
            // Criar itens do pedido
            foreach ($items as $item) {
                $itemData = [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['produto_id'],
                    'sku' => $item['sku'],
                    'nome_produto' => $item['nome'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['valor_unitario'],
                    'subtotal' => $item['subtotal']
                ];
                
                $stmt = $this->connection->prepare("
                    INSERT INTO pedido_items (pedido_id, produto_id, sku, nome_produto, quantidade, valor_unitario, subtotal) 
                    VALUES (:pedido_id, :produto_id, :sku, :nome_produto, :quantidade, :valor_unitario, :subtotal)
                ");
                $stmt->execute($itemData);
                
                // Atualizar estoque
                $produtoModel = new Produto();
                $produtoModel->updateEstoque($item['produto_id'], $item['quantidade']);
            }
            
            // Adicionar histórico de status
            $this->adicionarHistoricoStatus($pedidoId, null, 'pago', 'Pedido criado e pago com sucesso', $usuarioId);
            
            // Limpar carrinho
            $carrinhoModel->limparCarrinho($carrinhoId);
            
            $this->connection->commit();
            
            // Disparar eventos
            $this->dispararEvento('pedido_criado', $pedidoId);
            $this->dispararEvento('pagamento_aprovado', $pedidoId);
            
            return $pedidoId;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function gerarCodigoPedido() {
        do {
            $codigo = 'BRZ' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
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

    public function consolidarPedidos($pedidoIds, $usuarioId) {
        $this->connection->beginTransaction();
        
        try {
            // Validar pedidos (mesmo cliente, status adequado)
            $pedidos = $this->validarConsolidacao($pedidoIds);
            
            if (count($pedidos) < 2) {
                throw new \Exception('É necessário pelo menos 2 pedidos para consolidar');
            }
            
            // Gerar código de consolidação
            $codigoConsolidacao = 'CONS' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calcular totais
            $pesoTotal = array_sum(array_column($pedidos, 'peso_total'));
            $valorTotal = array_sum(array_column($pedidos, 'valor_total'));
            
            // Criar registro de consolidação
            $stmt = $this->connection->prepare("
                INSERT INTO consolidacoes (codigo_consolidacao, usuario_id, pedidos_ids, peso_total, valor_total, criado_por) 
                VALUES (:codigo, :usuario_id, :pedidos_ids, :peso, :valor, :criado_por)
            ");
            $stmt->bindParam(':codigo', $codigoConsolidacao);
            $stmt->bindParam(':usuario_id', $pedidos[0]['usuario_id']);
            $stmt->bindParam(':pedidos_ids', json_encode($pedidoIds));
            $stmt->bindParam(':peso', $pesoTotal);
            $stmt->bindParam(':valor', $valorTotal);
            $stmt->bindParam(':criado_por', $usuarioId);
            $stmt->execute();
            
            // Atualizar status dos pedidos
            foreach ($pedidoIds as $pedidoId) {
                $this->atualizarStatus($pedidoId, 'consolidado', 'Pedido consolidado em ' . $codigoConsolidacao, $usuarioId);
            }
            
            $this->connection->commit();
            
            return $codigoConsolidacao;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function validarConsolidacao($pedidoIds) {
        $placeholders = str_repeat('?,', count($pedidoIds) - 1) . '?';
        
        $stmt = $this->connection->prepare("
            SELECT p.*, u.id as usuario_id 
            FROM {$this->table} p 
            JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id IN ($placeholders) AND p.status IN ('pago', 'aguardando_processamento')
        ");
        $stmt->execute($pedidoIds);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (count($pedidos) !== count($pedidoIds)) {
            throw new \Exception('Um ou mais pedidos não podem ser consolidados');
        }
        
        // Verificar se todos são do mesmo cliente
        $usuariosIds = array_unique(array_column($pedidos, 'usuario_id'));
        if (count($usuariosIds) > 1) {
            throw new \Exception('Todos os pedidos devem ser do mesmo cliente');
        }
        
        return $pedidos;
    }

    public function gerarRascunhoEtiqueta($pedidoId, $usuarioId) {
        $pedido = $this->getComDetalhes($pedidoId);
        
        if (!$pedido) {
            throw new \Exception('Pedido não encontrado');
        }
        
        if ($pedido['status'] !== 'aguardando_processamento' && $pedido['status'] !== 'consolidado') {
            throw new \Exception('Status do pedido não permite geração de etiqueta');
        }
        
        // Gerar código da etiqueta
        $codigoEtiqueta = 'ETQ' . date('YmdHis') . str_pad($pedidoId, 6, '0', STR_PAD_LEFT);
        
        // Atualizar pedido
        $this->update($pedidoId, [
            'etiqueta_codigo' => $codigoEtiqueta,
            'etiqueta_status' => 'rascunho',
            'etiqueta_gerada_em' => date('Y-m-d H:i:s'),
            'status' => 'rascunho_etiqueta'
        ]);
        
        $this->adicionarHistoricoStatus($pedidoId, 'aguardando_processamento', 'rascunho_etiqueta', 'Rascunho de etiqueta gerado: ' . $codigoEtiqueta, $usuarioId);
        
        return $codigoEtiqueta;
    }

    public function efetivarEtiqueta($pedidoId, $usuarioId, $dadosTransporte = []) {
        $pedido = $this->find($pedidoId);
        
        if (!$pedido || $pedido['etiqueta_status'] !== 'rascunho') {
            throw new \Exception('Etiqueta não está em status de rascunho');
        }
        
        // Atualizar pedido
        $updateData = [
            'etiqueta_status' => 'efetivada',
            'etiqueta_efetivada_em' => date('Y-m-d H:i:s'),
            'status' => 'etiqueta_efetivada',
            'atualizado_por' => $usuarioId
        ];
        
        if (!empty($dadosTransporte)) {
            $updateData['tracking_code'] = $dadosTransporte['tracking_code'] ?? null;
            $updateData['transportadora'] = $dadosTransporte['transportadora'] ?? null;
            $updateData['peso_cobrado'] = $dadosTransporte['peso_cobrado'] ?? $pedido['peso_total'];
        }
        
        $this->update($pedidoId, $updateData);
        
        $this->adicionarHistoricoStatus($pedidoId, 'rascunho_etiqueta', 'etiqueta_efetivada', 'Etiqueta efetivada para envio', $usuarioId);
        
        return true;
    }

    public function getPedidos($usuarioId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->connection->prepare("
                SELECT p.*, 
                       e_entrega.cep as cep_entrega, e_entrega.cidade as cidade_entrega,
                       e_cobranca.cep as cep_cobranca
                FROM {$this->table} p
                LEFT JOIN enderecos e_entrega ON p.endereco_entrega_id = e_entrega.id
                LEFT JOIN enderecos e_cobranca ON p.endereco_cobranca_id = e_cobranca.id
                WHERE p.usuario_id = :id 
                ORDER BY p.created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindParam(':id', $usuarioId);
            $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('Erro ao obter pedidos do usuário: ' . $e->getMessage());
            error_log('SQL executado: SELECT p.*, e_entrega.cep as cep_entrega, e_entrega.cidade as cidade_entrega, e_cobranca.cep as cep_cobranca FROM pedidos p LEFT JOIN enderecos e_entrega ON p.endereco_entrega_id = e_entrega.id LEFT JOIN enderecos e_cobranca ON p.endereco_cobranca_id = e_cobranca.id WHERE p.usuario_id = :id ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset');
            return [];
        }
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
            error_log('Erro ao obter rastreamento do pedido: ' . $e->getMessage());
            error_log('SQL executado: SELECT psh.*, u.nome as usuario_alterou FROM pedido_status_history psh LEFT JOIN usuarios u ON psh.usuario_id = u.id WHERE psh.pedido_id = :id ORDER BY psh.created_at DESC');
            return [];
        }
    }

    public function getComDetalhes($pedidoId) {
    // Adaptar query para a estrutura correta das tabelas
    $stmt = $this->connection->prepare("
        SELECT p.*, 
               u.nome as cliente_nome, u.email as cliente_email,
               e_ent.cep as cep_entrega, e_ent.endereco as endereco_entrega, 
               e_ent.numero as numero_entrega, e_ent.complemento as complemento_entrega,
               e_ent.bairro as bairro_entrega, e_ent.cidade as cidade_entrega, e_ent.estado as estado_entrega,
               e_cob.cep as cep_cobranca
        FROM {$this->table} p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
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
            // Primeiro, verificar se a tabela produtos existe e tem as colunas necessárias
            $checkTable = $this->connection->query("SHOW TABLES LIKE 'produtos'")->rowCount();
            
            if ($checkTable > 0) {
                // Verificar colunas da tabela produtos
                $columns = $this->connection->query("SHOW COLUMNS FROM produtos")->fetchAll(\PDO::FETCH_COLUMN);
                
                $sql = "SELECT pi.*";
                
                // Adicionar colunas do produto apenas se existirem
                if (in_array('nome', $columns)) {
                    $sql .= ", pr.nome as nome_produto";
                } else {
                    $sql .= ", CONCAT('Produto #', pi.produto_id) as nome_produto";
                }
                
                if (in_array('referencia', $columns)) {
                    $sql .= ", pr.referencia";
                } else {
                    $sql .= ", '' as referencia";
                }
                
                if (in_array('imagem', $columns)) {
                    $sql .= ", pr.imagem";
                } else {
                    $sql .= ", 'default.jpg' as imagem";
                }
                
                if (in_array('descricao', $columns)) {
                    $sql .= ", pr.descricao as descricao_produto";
                } elseif (in_array('descricao_curta', $columns)) {
                    $sql .= ", pr.descricao_curta as descricao_produto";
                } else {
                    $sql .= ", '' as descricao_produto";
                }
                
                // Usar LEFT JOIN para não quebrar se não houver correspondência
                $sql .= " FROM pedido_items pi LEFT JOIN produtos pr ON pi.produto_id = pr.id WHERE pi.pedido_id = :id ORDER BY pi.id";
                
                error_log("SQL executado: " . $sql);
                error_log("Colunas encontradas: " . implode(', ', $columns));
                
                $stmt = $this->connection->prepare($sql);
                $stmt->bindParam(':id', $pedidoId);
                $stmt->execute();
                $pedido['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Garantir que os itens tenham todos os campos necessários
                foreach ($pedido['items'] as &$item) {
                    $item['nome_produto'] = $item['nome_produto'] ?? 'Produto #' . $item['produto_id'];
                    $item['referencia'] = $item['referencia'] ?? '';
                    $item['imagem'] = $item['imagem'] ?? 'default.jpg';
                    $item['descricao_produto'] = $item['descricao_produto'] ?? '';
                    $item['subtotal'] = ($item['preco_unitario'] ?? 0) * ($item['quantidade'] ?? 0);
                }
                
            } else {
                // Tabela produtos não existe, usar dados básicos
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
                error_log('Erro ao obter histórico do pedido: ' . $e->getMessage());
                error_log('SQL executado: SELECT psh.*, u.nome as usuario_alterou FROM pedido_status_history psh LEFT JOIN usuarios u ON psh.usuario_id = u.id WHERE psh.pedido_id = :id ORDER BY psh.created_at DESC');
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

    public function dispararEvento($eventoNome, $pedidoId) {
        // Aqui será implementada a lógica de disparo de eventos
        // Por enquanto, apenas registrar que o evento ocorreu
        error_log("Evento disparado: {$eventoNome} para pedido #{$pedidoId}");
    }
}
