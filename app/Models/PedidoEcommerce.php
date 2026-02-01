<?php
namespace App\Models;

use App\Models\Carrinho;
use App\Models\Produto;
use App\Services\NotificationService;

class PedidoEcommerce extends Model {
    protected $table = 'pedidos';

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->connection->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function consumirEstoqueInternoEGerarCompras(int $pedidoId, int $usuarioId, array $itens): void {
        // Este método é best-effort: se tabelas não existirem, não interrompe a criação do pedido
        try {
            if (!$this->tableExists('estoque_interno') || !$this->tableExists('estoque_movimentacao') || !$this->tableExists('lista_compras')) {
                return;
            }

            $temLojaEmProdutos = $this->columnExists('produtos', 'loja');
            $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
            $temPedidoEmMov = $this->columnExists('estoque_movimentacao', 'pedido_id');
            $temUsuarioLoginEmMov = $this->columnExists('estoque_movimentacao', 'usuario_login');
            $temLojaEmLista = $this->columnExists('lista_compras', 'loja');
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');

            $usuarioLogin = null;
            try {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                if (!empty($_SESSION['usuario_email'])) {
                    $usuarioLogin = (string) $_SESSION['usuario_email'];
                }
            } catch (\Exception $e) {
            }

            // Prepared statements
            $stmtLocs = $this->connection->prepare('
                SELECT id, quantidade, galpao, prateleira
                FROM estoque_interno
                WHERE produto_id = :produto_id AND quantidade > 0
                ORDER BY
                    CASE WHEN data_compra IS NULL THEN 1 ELSE 0 END ASC,
                    data_compra ASC,
                    id ASC
            ');
            $stmtUpdStock = $this->connection->prepare('UPDATE estoque_interno SET quantidade = :quantidade WHERE id = :id LIMIT 1');

            $sqlMovCols = 'produto_id, tipo_movimentacao, quantidade, quantidade_anterior, quantidade_nova, motivo, usuario_id';
            $sqlMovVals = ':produto_id, :tipo_movimentacao, :quantidade, :quantidade_anterior, :quantidade_nova, :motivo, :usuario_id';
            if ($temPedidoEmMov) {
                $sqlMovCols .= ', pedido_id';
                $sqlMovVals .= ', :pedido_id';
            }
            if ($temUsuarioLoginEmMov) {
                $sqlMovCols .= ', usuario_login';
                $sqlMovVals .= ', :usuario_login';
            }
            $stmtMov = $this->connection->prepare('INSERT INTO estoque_movimentacao (' . $sqlMovCols . ') VALUES (' . $sqlMovVals . ')');

            $sqlListaSelect = 'SELECT id, quantidade_necessaria, quantidade_faltante FROM lista_compras WHERE produto_id = :produto_id AND status = \'pendente\'';
            if ($temLojaIdEmLista) {
                $sqlListaSelect .= ' AND COALESCE(loja_id, 0) = :loja_id';
            } elseif ($temLojaEmLista) {
                $sqlListaSelect .= ' AND COALESCE(loja, \'\') = :loja';
            }
            $sqlListaSelect .= ' LIMIT 1';
            $stmtListaGet = $this->connection->prepare($sqlListaSelect);

            $stmtListaUpd = null;
            $stmtListaIns = null;
            // update
            $sqlUpd = 'UPDATE lista_compras SET quantidade_necessaria = :quantidade_necessaria, quantidade_faltante = :quantidade_faltante';
            if ($temPedidoEmLista) {
                $sqlUpd .= ', pedido_id = :pedido_id';
            }
            $sqlUpd .= ' WHERE id = :id LIMIT 1';
            $stmtListaUpd = $this->connection->prepare($sqlUpd);
            // insert
            $cols = ['produto_id'];
            $vals = [':produto_id'];
            if ($temLojaIdEmLista) {
                $cols[] = 'loja_id';
                $vals[] = ':loja_id';
            } elseif ($temLojaEmLista) {
                $cols[] = 'loja';
                $vals[] = ':loja';
            }
            if ($temPedidoEmLista) {
                $cols[] = 'pedido_id';
                $vals[] = ':pedido_id';
            }
            $cols[] = 'quantidade_necessaria';
            $vals[] = ':quantidade_necessaria';
            $cols[] = 'quantidade_faltante';
            $vals[] = ':quantidade_faltante';
            $cols[] = 'prioridade';
            $vals[] = ':prioridade';
            $cols[] = 'status';
            $vals[] = '\'pendente\'';
            $cols[] = 'data_solicitacao';
            $vals[] = 'CURDATE()';
            $stmtListaIns = $this->connection->prepare('INSERT INTO lista_compras (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');

            $stmtProdutoLoja = null;
            if ($temLojaEmProdutos || $temLojaIdEmProdutos) {
                $selectParts = [];
                if ($temLojaIdEmProdutos) {
                    $selectParts[] = 'COALESCE(loja_id, 0) as loja_id';
                } else {
                    $selectParts[] = '0 as loja_id';
                }
                if ($temLojaEmProdutos) {
                    $selectParts[] = 'COALESCE(loja, \'\') as loja';
                } else {
                    $selectParts[] = '\'\' as loja';
                }
                $stmtProdutoLoja = $this->connection->prepare('SELECT ' . implode(', ', $selectParts) . ' FROM produtos WHERE id = :id LIMIT 1');
            }

            foreach ($itens as $item) {
                $produtoId = (int) ($item['produto_id'] ?? 0);
                $qtdPedido = (int) ($item['quantidade'] ?? 0);
                if ($produtoId <= 0 || $qtdPedido <= 0) {
                    continue;
                }

                $loja = '';
                $lojaId = 0;
                if ($stmtProdutoLoja) {
                    $stmtProdutoLoja->execute([':id' => $produtoId]);
                    $rowLoja = $stmtProdutoLoja->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($rowLoja)) {
                        $lojaId = (int) ($rowLoja['loja_id'] ?? 0);
                        $loja = (string) ($rowLoja['loja'] ?? '');
                    }
                }

                $restante = $qtdPedido;

                // Consumir estoque interno
                $stmtLocs->execute([':produto_id' => $produtoId]);
                $locs = $stmtLocs->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($locs as $loc) {
                    if ($restante <= 0) {
                        break;
                    }

                    $estoqueId = (int) ($loc['id'] ?? 0);
                    $qtdDisponivel = (int) ($loc['quantidade'] ?? 0);
                    if ($estoqueId <= 0 || $qtdDisponivel <= 0) {
                        continue;
                    }

                    $tirar = min($qtdDisponivel, $restante);
                    $novo = $qtdDisponivel - $tirar;
                    $stmtUpdStock->execute([':quantidade' => $novo, ':id' => $estoqueId]);

                    $gal = trim((string) ($loc['galpao'] ?? ''));
                    $pra = trim((string) ($loc['prateleira'] ?? ''));
                    $locFull = $gal;
                    if ($gal !== '' && $pra !== '') {
                        $locFull .= ' - ' . $pra;
                    } elseif ($pra !== '') {
                        $locFull = $pra;
                    }

                    $motivo = 'Saída para pedido #' . $pedidoId;
                    if ($locFull !== '') {
                        $motivo .= ' (' . $locFull . ')';
                    }

                    $params = [
                        ':produto_id' => $produtoId,
                        ':tipo_movimentacao' => 'saida',
                        ':quantidade' => $tirar,
                        ':quantidade_anterior' => $qtdDisponivel,
                        ':quantidade_nova' => $novo,
                        ':motivo' => $motivo,
                        ':usuario_id' => $usuarioId,
                    ];
                    if ($temPedidoEmMov) {
                        $params[':pedido_id'] = $pedidoId;
                    }
                    if ($temUsuarioLoginEmMov) {
                        $params[':usuario_login'] = $usuarioLogin;
                    }
                    $stmtMov->execute($params);

                    $restante -= $tirar;
                }

                // Gerar/atualizar lista de compras para faltante
                if ($restante > 0) {
                    $paramsGet = [':produto_id' => $produtoId];
                    if ($temLojaIdEmLista) {
                        $paramsGet[':loja_id'] = $lojaId;
                    } elseif ($temLojaEmLista) {
                        $paramsGet[':loja'] = $loja;
                    }
                    $stmtListaGet->execute($paramsGet);
                    $row = $stmtListaGet->fetch(\PDO::FETCH_ASSOC);

                    if ($row && isset($row['id'])) {
                        $id = (int) $row['id'];
                        $qn = (int) ($row['quantidade_necessaria'] ?? 0);
                        $qf = (int) ($row['quantidade_faltante'] ?? 0);
                        $qn += $qtdPedido;
                        $qf += $restante;

                        $paramsUpd = [
                            ':quantidade_necessaria' => $qn,
                            ':quantidade_faltante' => $qf,
                            ':id' => $id,
                        ];
                        if ($temPedidoEmLista) {
                            $paramsUpd[':pedido_id'] = $pedidoId;
                        }
                        $stmtListaUpd->execute($paramsUpd);
                    } else {
                        $paramsIns = [
                            ':produto_id' => $produtoId,
                            ':quantidade_necessaria' => $qtdPedido,
                            ':quantidade_faltante' => $restante,
                            ':prioridade' => 'alta',
                        ];
                        if ($temLojaIdEmLista) {
                            $paramsIns[':loja_id'] = $lojaId;
                        } elseif ($temLojaEmLista) {
                            $paramsIns[':loja'] = $loja;
                        }
                        if ($temPedidoEmLista) {
                            $paramsIns[':pedido_id'] = $pedidoId;
                        }
                        $stmtListaIns->execute($paramsIns);
                    }
                }
            }
        } catch (\Exception $e) {
            // Não interromper fluxo do pedido; registrar para diagnóstico
            error_log('Erro ao consumir estoque interno/gerar lista de compras: ' . $e->getMessage());
        }
    }

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
            $pedidoItens = [];
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

                $pedidoItens[] = [
                    'produto_id' => (int) $item['produto_id'],
                    'quantidade' => (int) $item['quantidade'],
                ];
                
                // Atualizar estoque
                $produtoModel = new Produto();
                $produtoModel->updateEstoque($item['produto_id'], $item['quantidade']);
            }
            
            // Adicionar histórico de status
            $this->adicionarHistoricoStatus($pedidoId, null, 'pago', 'Pedido criado e pago com sucesso', $usuarioId);
            
            // Consumir estoque interno e gerar lista de compras por loja
            $this->consumirEstoqueInternoEGerarCompras((int) $pedidoId, (int) $usuarioId, $pedidoItens);

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
            'pagamento' => 'novo_pedido',
            'pendente' => 'novo_pedido',
            'pago' => 'pedido_aprovado',
            'paid' => 'pedido_aprovado',
            'cancelado' => 'pedido_cancelado',
            'cancelled' => 'pedido_cancelado',
            'consolidado' => 'pedido_consolidado',
            'rascunho_etiqueta' => 'rascunho_etiqueta_gerado',
            'etiqueta_efetivada' => 'etiqueta_efetivada',
            'enviado' => 'pedido_enviado',
            'entregue' => 'pedido_entregue',
            'entrega_finalizada' => 'pedido_entregue'
        ];
        
        return $mapeamento[$status] ?? null;
    }

    public function getComDetalhes($pedidoId) {
        $joinPagamentos = '';
        $selectPagamentos = '';
        $selectFormaPagamento = '';
        $selectExtras = '';
        $colsP = [];
        $temJoinPagamentos = false;

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
                $temJoinPagamentos = true;

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

        // Fallback: alguns schemas guardam dados de pagamento diretamente em pedidos
        $selectPagamentoNoPedido = '';
        if (!$temJoinPagamentos && is_array($colsP) && !empty($colsP)) {
            if (in_array('payment_status', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.payment_status AS pagamento_status';
            }
            if (in_array('payment_gateway', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.payment_gateway AS pagamento_gateway';
            }
            if (in_array('payment_id', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.payment_id AS pagamento_transacao';
            }
            if (in_array('pago_em', $colsP, true)) {
                $selectPagamentoNoPedido .= ', p.pago_em AS pagamento_data';
            }
        }

        $selectExtras = $selectFormaPagamento . $selectPagamentos . $selectPagamentoNoPedido;

        // Adaptar query para a estrutura correta das tabelas
        $stmt = $this->connection->prepare("
            SELECT p.*, 
                   COALESCE(c.nome_razao_social, u.nome, u.name, p.nome) as cliente_nome,
                   COALESCE(c.email, u.email) as cliente_email,
                   COALESCE(c.telefone, u.telefone) as cliente_telefone{$selectExtras},
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
                // Descobrir colunas disponíveis em pedido_itens para evitar SQL quebrar
                $colsItens = [];
                try {
                    $stmtCols = $this->connection->query('DESCRIBE pedido_itens');
                    $colsItens = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
                } catch (\Exception $e) {
                    $colsItens = [];
                }

                $selectExtrasItens = '';
                foreach (['url_original', 'variacao_id', 'variacao_label', 'variacao_atributos'] as $c) {
                    if (is_array($colsItens) && in_array($c, $colsItens, true)) {
                        $selectExtrasItens .= ', pi.' . $c;
                    }
                }

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
                // Injetar colunas extras de forma segura
                if ($selectExtrasItens !== '') {
                    $sql = $stmt->queryString;
                    $sql = str_replace('pi.created_at,', 'pi.created_at' . $selectExtrasItens . ',', $sql);
                    $stmt = $this->connection->prepare($sql);
                }
                $stmt->bindParam(':id', $pedidoId);
                $stmt->execute();
                $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Garantir que os itens tenham todos os campos necessários
                foreach ($itens as &$item) {
                    $item['referencia'] = $item['referencia'] ?? $item['nome_produto_sku'] ?? '';
                    $item['imagem'] = $item['imagem_principal'] ?? 'default.jpg';
                    $item['descricao_produto'] = $item['descricao_produto'] ?? '';

                    if (isset($item['variacao_atributos']) && is_string($item['variacao_atributos']) && $item['variacao_atributos'] !== '') {
                        $decoded = json_decode($item['variacao_atributos'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $item['variacao_atributos'] = $decoded;
                        }
                    }
                    
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
            if (empty($pedido['status'])) {
                $pedido['status'] = 'pendente';
            }

            // Se o pagamento já estiver aprovado, refletir o status do pedido como pago
            $stPag = $pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? null);
            if (is_string($stPag)) {
                $stPag = strtoupper(trim($stPag));
            }
            if (!empty($stPag) && in_array($stPag, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                $pedido['status'] = 'pago';
            }
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
        // Por enquanto, apenas registrar que o evento ocorreu
        error_log("Evento disparado: {$eventoNome} para pedido #{$pedidoId}");

        try {
            $service = new NotificationService();
            $service->notificarEventoPedido(is_string($eventoNome) ? $eventoNome : null, (int) $pedidoId);
        } catch (\Exception $e) {
            error_log('[NOTIFICACOES] Falha ao disparar notificacoes: ' . $e->getMessage());
        }
    }
}
