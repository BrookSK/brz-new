<?php
namespace App\Models;

use App\Services\NotificationService;

class PedidoEcommerce {
    private \PDO $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function tableExists(string $table): bool {
        try {
            $st = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $st->execute([$table]);
            return ((int) $st->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getComDetalhes($pedidoId) {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return null;

        $pedido = null;
        try {
            $stmt = $this->connection->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $pedido = null;
        }

        if (!$pedido) return null;

        $pedido['items'] = [];
        $pedido['historico'] = [];

        // Itens do pedido (tolerante a schemas)
        try {
            $temPedidoItens = $this->tableExists('pedido_itens');
            $temPedidoItems = $this->tableExists('pedido_items');
            $itensTable = $temPedidoItens ? 'pedido_itens' : ($temPedidoItems ? 'pedido_items' : 'pedido_itens');

            if ($temPedidoItens && $temPedidoItems) {
                $c1 = 0;
                $c2 = 0;
                try {
                    $st = $this->connection->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = :id');
                    $st->execute([':id' => $pedidoId]);
                    $c1 = (int) ($st->fetchColumn() ?: 0);
                } catch (\Exception $e) {
                    $c1 = 0;
                }
                try {
                    $st = $this->connection->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = :id');
                    $st->execute([':id' => $pedidoId]);
                    $c2 = (int) ($st->fetchColumn() ?: 0);
                } catch (\Exception $e) {
                    $c2 = 0;
                }
                $itensTable = ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
            }

            $colsItens = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE ' . $itensTable);
                $colsItens = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsItens = [];
            }

            $pick = function(array $cands) use ($colsItens) {
                foreach ($cands as $c) {
                    if (is_array($colsItens) && in_array($c, $colsItens, true)) {
                        return $c;
                    }
                }
                return null;
            };

            $colPedidoId = $pick(['pedido_id']);
            $colProdutoId = $pick(['produto_id']);
            $colProdutoVariacaoId = $pick(['produto_variacao_id']);
            $colQtd = $pick(['quantidade', 'qty']);
            $colPrecoUnit = $pick(['preco_unitario', 'valor_unitario', 'price', 'preco']);
            $colSubtotal = $pick(['subtotal']);
            $colNomeProduto = $pick(['nome_produto', 'produto_nome', 'nome']);
            $colSku = $pick(['nome_produto_sku', 'sku']);

            if (!$colPedidoId) {
                throw new \Exception('Tabela de itens sem pedido_id');
            }

            $selectParts = [];
            if ($pick(['id']) !== null) $selectParts[] = 'pi.id';
            if ($colProdutoId) $selectParts[] = 'pi.' . $colProdutoId . ' AS produto_id';
            if ($colProdutoVariacaoId) $selectParts[] = 'pi.' . $colProdutoVariacaoId . ' AS produto_variacao_id';
            if ($colQtd) $selectParts[] = 'pi.' . $colQtd . ' AS quantidade';
            if ($colPrecoUnit) $selectParts[] = 'pi.' . $colPrecoUnit . ' AS preco_unitario';
            if ($colSubtotal) $selectParts[] = 'pi.' . $colSubtotal . ' AS subtotal';
            if ($colNomeProduto) $selectParts[] = 'pi.' . $colNomeProduto . ' AS nome_produto';
            if ($colSku) $selectParts[] = 'pi.' . $colSku . ' AS nome_produto_sku';
            if ($pick(['created_at']) !== null) $selectParts[] = 'pi.created_at';
            $selectParts[] = "(SELECT pf.nome_arquivo FROM produto_fotos pf WHERE pf.produto_id = pi." . ($colProdutoId ?: 'produto_id') . " ORDER BY pf.principal DESC, pf.ordem ASC LIMIT 1) as imagem_principal";

            $sqlItens = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $itensTable . ' pi WHERE pi.' . $colPedidoId . ' = :id ORDER BY pi.id';
            $stmtItens = $this->connection->prepare($sqlItens);
            $stmtItens->execute([':id' => $pedidoId]);
            $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Descrições de variação
            $variacaoDescById = [];
            if (!empty($itens) && $colProdutoVariacaoId) {
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
                        $stVar = $this->connection->prepare($sqlVar);
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
                $item['referencia'] = $item['referencia'] ?? ($item['nome_produto_sku'] ?? '');
                $item['imagem'] = $item['imagem_principal'] ?? 'default.jpg';
                $pid = (int) ($item['produto_id'] ?? 0);
                if (empty($item['nome_produto'])) {
                    $item['nome_produto'] = $pid > 0 ? ('Produto #' . $pid) : 'Produto';
                }

                $pvId = (int) ($item['produto_variacao_id'] ?? 0);
                if ($pvId > 0) {
                    $desc = (string) ($variacaoDescById[$pvId] ?? '');
                    $item['variacao_descricao'] = $desc !== '' ? $desc : null;
                    $item['variacao_label'] = $desc;
                    if ($desc !== '') {
                        $attrs = [];
                        foreach (explode(' / ', $desc) as $part) {
                            $part = trim($part);
                            if ($part === '') continue;
                            $p = explode('=', $part, 2);
                            if (count($p) === 2) {
                                $k = trim((string) $p[0]);
                                $v = trim((string) $p[1]);
                                if ($k !== '' && $v !== '') {
                                    $attrs[$k] = $v;
                                }
                            }
                        }
                        $item['variacao_atributos'] = $attrs;
                    }
                }

                $q = (int) ($item['quantidade'] ?? 0);
                $pu = (float) ($item['preco_unitario'] ?? 0);
                if (!isset($item['subtotal']) || $item['subtotal'] === null) {
                    $item['subtotal'] = $pu * $q;
                }
            }
            unset($item);

            $pedido['items'] = $itens;
        } catch (\Exception $e) {
            $pedido['items'] = [];
        }

        // Código do pedido (fallback)
        if (empty($pedido['codigo_pedido']) && !empty($pedido['numero_pedido'])) {
            $pedido['codigo_pedido'] = $pedido['numero_pedido'];
        }
        if (empty($pedido['codigo_pedido'])) {
            $pedido['codigo_pedido'] = 'PED-' . str_pad((string) $pedidoId, 6, '0', STR_PAD_LEFT);
        }

        // Histórico (se existir)
        try {
            if ($this->tableExists('pedido_status_history')) {
                $userNomeCol = 'nome';
                try {
                    $stmtUserCols = $this->connection->query('DESCRIBE usuarios');
                    $userCols = $stmtUserCols ? $stmtUserCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                    if (is_array($userCols) && !in_array('nome', $userCols, true) && in_array('name', $userCols, true)) {
                        $userNomeCol = 'name';
                    }
                } catch (\Exception $e) {
                }

                $stmtHist = $this->connection->prepare("\
                    SELECT psh.*, u.{$userNomeCol} as usuario_alterou
                    FROM pedido_status_history psh
                    LEFT JOIN usuarios u ON psh.usuario_id = u.id
                    WHERE psh.pedido_id = :id
                    ORDER BY psh.created_at DESC
                ");
                $stmtHist->execute([':id' => $pedidoId]);
                $pedido['historico'] = $stmtHist->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $pedido['historico'] = [];
        }

        return $pedido;
    }

    public function getRastreamento($pedidoId) {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return [];

        try {
            $userNomeCol = 'nome';
            try {
                $stmtUserCols = $this->connection->query('DESCRIBE usuarios');
                $userCols = $stmtUserCols ? $stmtUserCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                if (is_array($userCols) && !in_array('nome', $userCols, true) && in_array('name', $userCols, true)) {
                    $userNomeCol = 'name';
                }
            } catch (\Exception $e) {
            }

            $stmt = $this->connection->prepare("\
                SELECT psh.*, u.{$userNomeCol} as usuario_alterou
                FROM pedido_status_history psh
                LEFT JOIN usuarios u ON psh.usuario_id = u.id
                WHERE psh.pedido_id = :id
                ORDER BY psh.created_at DESC
            ");
            $stmt->execute([':id' => $pedidoId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function atualizarStatus(int $pedidoId, string $novoStatus, ?string $observacao = null, $usuarioId = null): bool {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) return false;

        try {
            $st = $this->connection->prepare('UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?');
            $st->execute([$novoStatus, $pedidoId]);

            if ($this->tableExists('pedido_status_history')) {
                try {
                    $stH = $this->connection->prepare('INSERT INTO pedido_status_history (pedido_id, status_anterior, status_novo, observacao, usuario_id, created_at) VALUES (?, NULL, ?, ?, ?, NOW())');
                    $uid = $usuarioId !== null ? (int) $usuarioId : null;
                    $stH->execute([$pedidoId, $novoStatus, $observacao, $uid]);
                } catch (\Exception $e) {
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function dispararEvento($eventoNome, $pedidoId) {
        error_log("Evento disparado: {$eventoNome} para pedido #{$pedidoId}");

        try {
            $service = new NotificationService();
            $service->notificarEventoPedido(is_string($eventoNome) ? $eventoNome : null, (int) $pedidoId);
        } catch (\Exception $e) {
            error_log('[NOTIFICACOES] Falha ao disparar notificacoes: ' . $e->getMessage());
        }
    }
}
