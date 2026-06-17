<?php
namespace App\Models;

class Carne extends Model {
    protected $table = 'carnes';

    /**
     * Cria um carnê completo com parcelas
     */
    public function criarComParcelas($dados, $qtdParcelas) {
        $this->connection->beginTransaction();
        try {
            $carneId = $this->create($dados);

            // Buscar dias de vencimento da config (padrão 7)
            $diasVencimento = 7;
            try {
                $stCfg = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_dias_vencimento' LIMIT 1");
                $stCfg->execute();
                $v = (int) ($stCfg->fetchColumn() ?: 7);
                if ($v > 0) $diasVencimento = $v;
            } catch (\Exception $e) {}

            $valorProdutosParcela = floor($dados['total_produtos'] / $qtdParcelas * 100) / 100;
            $valorTaxasParcela = floor($dados['total_taxas'] / $qtdParcelas * 100) / 100;

            $somaProds = $valorProdutosParcela * ($qtdParcelas - 1);
            $somaTaxas = $valorTaxasParcela * ($qtdParcelas - 1);

            for ($i = 1; $i <= $qtdParcelas; $i++) {
                $vProds = ($i === $qtdParcelas)
                    ? round($dados['total_produtos'] - $somaProds, 2)
                    : $valorProdutosParcela;
                $vTaxas = ($i === $qtdParcelas)
                    ? round($dados['total_taxas'] - $somaTaxas, 2)
                    : $valorTaxasParcela;

                // Parcela 1: vence em X dias a partir de hoje
                // Parcela 2+: vence mensalmente (mês anterior + 1 mês)
                if ($i === 1) {
                    $vencimento = date('Y-m-d', strtotime("+{$diasVencimento} days"));
                } else {
                    // Meses a partir da primeira parcela
                    $mesesOffset = $i - 1;
                    $vencimento = date('Y-m-d', strtotime("+{$diasVencimento} days +{$mesesOffset} months"));
                }

                $stmt = $this->connection->prepare("
                    INSERT INTO carne_parcelas 
                    (carne_id, numero_parcela, valor_produtos, valor_taxas, valor_total, vencimento, status)
                    VALUES (:carne_id, :numero, :vp, :vt, :vtotal, :venc, :status)
                ");
                $stmt->execute([
                    ':carne_id' => $carneId,
                    ':numero' => $i,
                    ':vp' => $vProds,
                    ':vt' => $vTaxas,
                    ':vtotal' => round($vProds + $vTaxas, 2),
                    ':venc' => $vencimento,
                    ':status' => ($i === 1) ? 'aguardando_pagamento' : 'pendente'
                ]);
            }

            $this->registrarHistorico($carneId, null, 'carne_criado', "Carnê criado com {$qtdParcelas} parcelas");
            $this->connection->commit();
            return $carneId;
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Busca carnê com dados do cliente e pedido
     */
    public function getCompleto($id) {
        $stmt = $this->connection->prepare("
            SELECT c.*, 
                   u.nome as cliente_nome, u.email as cliente_email,
                   p.status as pedido_status
            FROM carnes c
            JOIN usuarios u ON c.cliente_id = u.id
            JOIN pedidos p ON c.pedido_id = p.id
            WHERE c.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $carne = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($carne) {
            $carne['parcelas'] = $this->getParcelas($id);
        }
        return $carne;
    }

    /**
     * Busca carnê pelo pedido_id
     */
    public function getByPedido($pedidoId) {
        $stmt = $this->connection->prepare("SELECT * FROM carnes WHERE pedido_id = :pid");
        $stmt->execute([':pid' => $pedidoId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Lista carnês de um cliente
     */
    public function getByCliente($clienteId) {
        // Verificar se pedidos tem deleted_at (soft delete)
        $hasDeletedAt = false;
        try {
            $stCol = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'deleted_at'");
            $stCol->execute();
            $hasDeletedAt = ((int) $stCol->fetchColumn()) > 0;
        } catch (\Exception $e) {}

        $deletedFilter = $hasDeletedAt ? 'AND p.deleted_at IS NULL' : '';

        $stmt = $this->connection->prepare("
            SELECT c.*, 
                LOWER(COALESCE(p.status,'')) AS pedido_status,
                (SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = c.id AND status = 'paga') as parcelas_pagas,
                (SELECT MIN(vencimento) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente')) as proximo_vencimento
            FROM carnes c
            INNER JOIN pedidos p ON p.id = c.pedido_id
            WHERE c.cliente_id = :cid
              {$deletedFilter}
              AND LOWER(COALESCE(p.status,'')) NOT IN ('excluido','excluída','deleted','lixeira','trash')
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([':cid' => $clienteId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna parcelas de um carnê
     */
    public function getParcelas($carneId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM carne_parcelas WHERE carne_id = :cid ORDER BY numero_parcela ASC
        ");
        $stmt->execute([':cid' => $carneId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma parcela específica
     */
    public function getParcela($parcelaId) {
        $stmt = $this->connection->prepare("SELECT * FROM carne_parcelas WHERE id = :id");
        $stmt->execute([':id' => $parcelaId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza status de uma parcela
     */
    public function atualizarParcela($parcelaId, $dados) {
        $sets = [];
        $params = [':id' => $parcelaId];
        foreach ($dados as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[":{$k}"] = $v;
        }
        $sql = "UPDATE carne_parcelas SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Registra pagamento de boleto (produtos ou taxas)
     */
    public function registrarPagamentoBoleto($parcelaId, $tipo, $dataPagamento = null) {
        $parcela = $this->getParcela($parcelaId);
        if (!$parcela) return false;

        $dataPgto = $dataPagamento ?: date('Y-m-d H:i:s');
        $campo_pago = "boleto_{$tipo}_pago";
        $campo_data = "boleto_{$tipo}_pago_em";

        $this->atualizarParcela($parcelaId, [
            $campo_pago => 1,
            $campo_data => $dataPgto
        ]);

        // Recarregar parcela
        $parcela = $this->getParcela($parcelaId);
        $ambos_pagos = $parcela['boleto_produtos_pago'] && $parcela['boleto_taxas_pago'];

        if ($ambos_pagos) {
            $this->atualizarParcela($parcelaId, ['status' => 'paga']);
            $this->registrarHistorico($parcela['carne_id'], $parcelaId, 'parcela_paga', "Parcela {$parcela['numero_parcela']} quitada");
            $this->verificarStatusCarne($parcela['carne_id']);
        } else {
            $this->atualizarParcela($parcelaId, ['status' => 'parcialmente_paga']);
            $this->registrarHistorico($parcela['carne_id'], $parcelaId, 'boleto_pago', "Boleto de {$tipo} da parcela {$parcela['numero_parcela']} pago");
        }

        // Se é a primeira parcela e foi paga, liberar compra interna
        if ($ambos_pagos && $parcela['numero_parcela'] == 1) {
            $this->liberarCompraInterna($parcela['carne_id']);
        }

        return true;
    }

    /**
     * Libera compra interna após pagamento da 1ª parcela
     */
    public function liberarCompraInterna($carneId) {
        $this->update($carneId, [
            'compra_interna_liberada' => 1,
            'status' => 'em_andamento'
        ]);

        // Criar registro de compra interna
        $stmt = $this->connection->prepare("
            INSERT IGNORE INTO carne_compras_internas (carne_id, pedido_id, status)
            SELECT :cid, pedido_id, 'aguardando_compra' FROM carnes WHERE id = :cid2
        ");
        $stmt->execute([':cid' => $carneId, ':cid2' => $carneId]);

        // Inserir itens do pedido na lista_compras com tipo_compra = 'carne'
        try {
            $carne = $this->find($carneId);
            $pedidoId = (int) ($carne['pedido_id'] ?? 0);
            if ($pedidoId > 0) {
                // Buscar itens do pedido
                $stItens = $this->connection->prepare("SELECT produto_id, quantidade FROM pedido_itens WHERE pedido_id = ? AND produto_id IS NOT NULL AND produto_id > 0");
                $stItens->execute([$pedidoId]);
                $itens = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                // Verificar colunas disponíveis em lista_compras
                $colsLC = [];
                try { $st = $this->connection->query('DESCRIBE lista_compras'); $colsLC = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
                $temTipoCompra = in_array('tipo_compra', $colsLC, true);
                $temPedidoId = in_array('pedido_id', $colsLC, true);
                $temLojaId = in_array('loja_id', $colsLC, true);
                $temQtdFaltante = in_array('quantidade_faltante', $colsLC, true);

                foreach ($itens as $item) {
                    $prodId = (int) ($item['produto_id'] ?? 0);
                    $qtd = (int) ($item['quantidade'] ?? 1);
                    if ($prodId <= 0 || $qtd <= 0) continue;

                    // Verificar se já existe pendência para este produto/pedido
                    $sqlCheck = "SELECT id FROM lista_compras WHERE produto_id = ? AND status = 'pendente'";
                    $paramsCheck = [$prodId];
                    if ($temPedidoId) { $sqlCheck .= " AND pedido_id = ?"; $paramsCheck[] = $pedidoId; }
                    $stCheck = $this->connection->prepare($sqlCheck . " LIMIT 1");
                    $stCheck->execute($paramsCheck);
                    if ($stCheck->fetchColumn()) continue; // Já existe

                    // Buscar loja_id do produto
                    $lojaId = 0;
                    if ($temLojaId) {
                        try {
                            $stLoja = $this->connection->prepare("SELECT loja_id FROM produtos WHERE id = ? LIMIT 1");
                            $stLoja->execute([$prodId]);
                            $lojaId = (int) ($stLoja->fetchColumn() ?: 0);
                        } catch (\Exception $e) {}
                    }

                    $cols = ['produto_id', 'quantidade_necessaria', 'prioridade', 'status', 'data_solicitacao'];
                    $vals = ['?', '?', "'media'", "'pendente'", 'CURDATE()'];
                    $params = [$prodId, $qtd];
                    if ($temQtdFaltante) { $cols[] = 'quantidade_faltante'; $vals[] = '?'; $params[] = $qtd; }
                    if ($temPedidoId) { $cols[] = 'pedido_id'; $vals[] = '?'; $params[] = $pedidoId; }
                    if ($temLojaId) { $cols[] = 'loja_id'; $vals[] = '?'; $params[] = $lojaId; }
                    if ($temTipoCompra) { $cols[] = 'tipo_compra'; $vals[] = "'carne'"; }

                    $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')')->execute($params);
                }
                $this->registrarLog($carneId, $pedidoId, null, 'compra_liberada', "Itens do pedido #{$pedidoId} adicionados à lista de compras", json_encode(['itens' => count($itens)]));
            }
        } catch (\Exception $e) {
            error_log('[CARNE] Erro ao inserir itens na lista_compras: ' . $e->getMessage());
        }

        $this->registrarHistorico($carneId, null, 'compra_liberada', 'Primeira parcela paga. Compra interna liberada.');
    }

    /**
     * Verifica e atualiza status geral do carnê
     */
    public function verificarStatusCarne($carneId) {
        $parcelas = $this->getParcelas($carneId);
        $todas_pagas = true;
        $tem_atraso = false;

        foreach ($parcelas as $p) {
            if ($p['status'] !== 'paga') $todas_pagas = false;
            if (in_array($p['status'], ['vencida', 'em_atraso'])) $tem_atraso = true;
        }

        if ($todas_pagas) {
            $this->update($carneId, [
                'status' => 'quitado',
                'envio_liberado' => 1
            ]);
            $this->registrarHistorico($carneId, null, 'carne_quitado', 'Todas as parcelas pagas. Envio liberado.');
        } elseif ($tem_atraso) {
            $this->update($carneId, ['status' => 'com_atraso']);
        }
    }

    /**
     * Registra histórico
     */
    public function registrarHistorico($carneId, $parcelaId, $tipo, $descricao, $dadosExtra = null, $usuarioId = null) {
        $stmt = $this->connection->prepare("
            INSERT INTO carne_historico (carne_id, parcela_id, tipo, descricao, dados_extra, usuario_id)
            VALUES (:cid, :pid, :tipo, :desc, :dados, :uid)
        ");
        return $stmt->execute([
            ':cid' => $carneId,
            ':pid' => $parcelaId,
            ':tipo' => $tipo,
            ':desc' => $descricao,
            ':dados' => $dadosExtra ? json_encode($dadosExtra) : null,
            ':uid' => $usuarioId
        ]);
    }

    /**
     * Lista carnês para admin com filtros
     */
    public function listarAdmin($filtros = []) {
        $where = ['1=1'];
        $params = [];

        // Sempre excluir carnês de pedidos excluídos/deletados/na lixeira
        $where[] = "p.id IS NOT NULL";
        $where[] = "(p.deleted_at IS NULL)";

        // Quando listando arquivados, não excluir cancelados (pois os arquivados SÃO cancelados)
        if (empty($filtros['incluir_arquivados'])) {
            $where[] = "p.status NOT IN ('cancelado','cancelada','cancelled','canceled','excluido','excluída','deleted','lixeira','trash')";
        } else {
            $where[] = "p.status NOT IN ('excluido','excluída','deleted','lixeira','trash')";
        }

        // Excluir arquivados por padrão (a menos que filtro explícito)
        if (empty($filtros['incluir_arquivados'])) {
            try {
                $stCol = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'carnes' AND column_name = 'arquivado'");
                $stCol->execute();
                if ((int) $stCol->fetchColumn() > 0) {
                    $where[] = "c.arquivado = 0";
                }
            } catch (\Exception $e) {}
        }

        if (!empty($filtros['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['cliente'])) {
            $where[] = '(u.nome LIKE :cliente OR u.email LIKE :cliente2)';
            $params[':cliente'] = "%{$filtros['cliente']}%";
            $params[':cliente2'] = "%{$filtros['cliente']}%";
        }
        if (!empty($filtros['pedido_id'])) {
            $where[] = 'c.pedido_id = :pedido_id';
            $params[':pedido_id'] = $filtros['pedido_id'];
        }
        if (!empty($filtros['com_atraso'])) {
            $where[] = "c.status IN ('com_atraso','inadimplente')";
        }
        if (!empty($filtros['liberado_compra'])) {
            $where[] = 'c.compra_interna_liberada = 1';
        }
        if (!empty($filtros['liberado_envio'])) {
            $where[] = 'c.envio_liberado = 1';
        }

        $sql = "
            SELECT c.*, u.nome as cliente_nome, u.email as cliente_email,
                (SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = c.id AND status = 'paga') as parcelas_pagas,
                (SELECT COUNT(*) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('vencida','em_atraso')) as parcelas_atrasadas,
                (SELECT MIN(vencimento) FROM carne_parcelas WHERE carne_id = c.id AND status IN ('aguardando_pagamento','pendente')) as proximo_vencimento,
                (SELECT MAX(vencimento) FROM carne_parcelas WHERE carne_id = c.id) as ultimo_vencimento
            FROM carnes c
            JOIN usuarios u ON c.cliente_id = u.id
            LEFT JOIN pedidos p ON p.id = c.pedido_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.created_at DESC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lista compras internas pendentes (para a Fabiana)
     */
    public function listarComprasInternas($filtros = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filtros['status'])) {
            $where[] = 'ci.status = :status';
            $params[':status'] = $filtros['status'];
        }

        $sql = "
            SELECT ci.*, c.pedido_id, c.total_geral, c.quantidade_parcelas, c.status as carne_status,
                   u.nome as cliente_nome, u.email as cliente_email,
                   (SELECT status FROM carne_parcelas WHERE carne_id = c.id AND numero_parcela = 1) as status_primeira_parcela
            FROM carne_compras_internas ci
            JOIN carnes c ON ci.carne_id = c.id
            JOIN usuarios u ON c.cliente_id = u.id
            LEFT JOIN pedidos p ON p.id = c.pedido_id
            WHERE " . implode(' AND ', $where) . "
            AND p.id IS NOT NULL AND (p.deleted_at IS NULL)
            AND p.status NOT IN ('cancelado','cancelada','cancelled','canceled','excluido','excluída','deleted','lixeira','trash')
            GROUP BY c.pedido_id
            ORDER BY ci.created_at ASC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca parcelas que precisam de ação (cron)
     */
    public function getParcelasParaProcessar() {
        $hoje = date('Y-m-d');
        $proximoVenc = date('Y-m-d', strtotime('+3 days'));

        $stmt = $this->connection->prepare("
            SELECT cp.*, c.cliente_id, c.pedido_id, c.status as carne_status
            FROM carne_parcelas cp
            JOIN carnes c ON cp.carne_id = c.id
            WHERE (
                (cp.status = 'aguardando_pagamento' AND cp.vencimento < :hoje)
                OR (cp.status = 'pendente' AND cp.vencimento <= :proximo)
                OR (cp.status = 'vencida' AND cp.vencimento < DATE_SUB(:hoje2, INTERVAL 7 DAY))
            )
            ORDER BY cp.vencimento ASC
        ");
        $stmt->execute([
            ':hoje' => $hoje,
            ':proximo' => $proximoVenc,
            ':hoje2' => $hoje
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna histórico do carnê
     */
    public function getHistorico($carneId) {
        $stmt = $this->connection->prepare("
            SELECT h.*, u.nome as usuario_nome
            FROM carne_historico h
            LEFT JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.carne_id = :cid
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([':cid' => $carneId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Registra log no sistema de carne_logs
     */
    public function registrarLog(int $carneId = null, int $pedidoId = null, int $parcelaId = null, string $tipo = 'info', string $mensagem = '', string $detalhes = ''): void {
        try {
            $this->connection->prepare(
                'INSERT INTO carne_logs (carne_id, pedido_id, parcela_id, tipo, mensagem, detalhes) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$carneId ?: null, $pedidoId ?: null, $parcelaId ?: null, $tipo, $mensagem, $detalhes]);
        } catch (\Exception $e) {
            error_log('[CARNE_LOG] Erro ao registrar log: ' . $e->getMessage());
        }
    }

    /**
     * Retorna notificações enviadas do carnê
     */
    public function getNotificacoes($carneId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM carne_notificacoes WHERE carne_id = :cid ORDER BY created_at DESC
        ");
        $stmt->execute([':cid' => $carneId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
