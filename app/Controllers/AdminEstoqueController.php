<?php
namespace App\Controllers;

use App\Services\AuthService;

class AdminEstoqueController extends Controller {
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $this->connection = \Config\Database::getConnection();
    }

    private function getTotalEstoqueProduto(int $produtoId): int {
        if ($produtoId <= 0 || !$this->tableExists('estoque_interno')) {
            return 0;
        }
        try {
            $stmt = $this->connection->prepare('SELECT COALESCE(SUM(quantidade),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
            $stmt->execute([':produto_id' => $produtoId]);
            return (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTotalReservadoProduto(int $produtoId): int {
        if ($produtoId <= 0) {
            return 0;
        }

        $total = 0;

        // Reservas reais
        if ($this->tableExists('estoque_reservas')) {
            try {
                $stmt = $this->connection->prepare("SELECT COALESCE(SUM(er.quantidade_reservada),0) as total\n                    FROM estoque_reservas er\n                    LEFT JOIN pedidos p ON p.id = er.pedido_id\n                    WHERE er.produto_id = :produto_id\n                      AND er.status = 'ativa'\n                      AND (p.id IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','concluido','concluído','finalizado','finalizada','entregue','entregue ao cliente','completed','refunded','estornado','estornada'))");
                $stmt->execute([':produto_id' => $produtoId]);
                $total += (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
            } catch (\Exception $e) {
            }
        }

        // Demanda pendente (lista de compras)
        if ($this->tableExists('lista_compras')) {
            try {
                $stmt = $this->connection->prepare("SELECT COALESCE(SUM(COALESCE(quantidade_faltante,0)),0) as total FROM lista_compras WHERE produto_id = :produto_id AND status = 'pendente'");
                $stmt->execute([':produto_id' => $produtoId]);
                $total += (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
            } catch (\Exception $e) {
            }
        }

        return $total;
    }

    private function getTotalReservadoRealProduto(int $produtoId): int {
        if ($produtoId <= 0 || !$this->tableExists('estoque_reservas')) {
            return 0;
        }

        try {
            $stmt = $this->connection->prepare("SELECT COALESCE(SUM(er.quantidade_reservada),0) as total
                FROM estoque_reservas er
                LEFT JOIN pedidos p ON p.id = er.pedido_id
                WHERE er.produto_id = :produto_id
                  AND er.status = 'ativa'
                  AND (p.id IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','concluido','concluído','finalizado','finalizada','entregue','entregue ao cliente','completed','refunded','estornado','estornada'))");
            $stmt->execute([':produto_id' => $produtoId]);
            return (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getTotalPendenciaCompraProduto(int $produtoId): int {
        if ($produtoId <= 0 || !$this->tableExists('lista_compras')) {
            return 0;
        }
        try {
            $stmt = $this->connection->prepare("SELECT COALESCE(SUM(COALESCE(quantidade_faltante,0)),0) as total FROM lista_compras WHERE produto_id = :produto_id AND status = 'pendente'");
            $stmt->execute([':produto_id' => $produtoId]);
            return (int) (($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function garantirSomaZeroAposReducao(int $produtoId): int {
        // Regra: não permitir que o sistema "fique devendo" estoque reservado.
        // Se estoque_total < reservado_total, criar pendência de compra suficiente para cobrir o déficit.
        $estoqueTotal = $this->getTotalEstoqueProduto($produtoId);
        // IMPORTANTE: aqui deve considerar apenas reservas reais. Se incluir lista_compras (pendência),
        // a pendência passa a se auto-inflar a cada ajuste.
        $reservado = $this->getTotalReservadoRealProduto($produtoId);
        if ($reservado <= 0) {
            return 0;
        }

        $deficit = $reservado - $estoqueTotal;
        $pendAtual = $this->getTotalPendenciaCompraProduto($produtoId);

        // Se não existe déficit, a pendência não deve existir. Reduzir/zerar pendências pendentes.
        if ($deficit <= 0) {
            if ($pendAtual > 0 && $this->tableExists('lista_compras')) {
                try {
                    $stmt = $this->connection->prepare("UPDATE lista_compras SET status = 'comprado', quantidade_faltante = 0 WHERE produto_id = :p AND status = 'pendente'");
                    $stmt->execute([':p' => $produtoId]);
                } catch (\Throwable $e) {
                }
            }
            return 0;
        }

        // Se a pendência atual estiver maior que o déficit, reduzir para evitar efeito bola de neve.
        if ($pendAtual > $deficit && $this->tableExists('lista_compras')) {
            $excesso = $pendAtual - $deficit;
            try {
                $stmtRows = $this->connection->prepare("SELECT id, COALESCE(quantidade_faltante,0) AS quantidade_faltante FROM lista_compras WHERE produto_id = :p AND status = 'pendente' ORDER BY id DESC");
                $stmtRows->execute([':p' => $produtoId]);
                $rows = $stmtRows->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $r) {
                    if ($excesso <= 0) break;
                    $id = (int) ($r['id'] ?? 0);
                    if ($id <= 0) continue;
                    $q = (int) ($r['quantidade_faltante'] ?? 0);
                    if ($q <= 0) {
                        continue;
                    }

                    $reduz = ($q >= $excesso) ? $excesso : $q;
                    $novo = $q - $reduz;
                    $stUpd = $this->connection->prepare("UPDATE lista_compras SET quantidade_faltante = :q, status = CASE WHEN :q = 0 THEN 'comprado' ELSE status END WHERE id = :id AND status = 'pendente'");
                    $stUpd->execute([':q' => $novo, ':id' => $id]);
                    $excesso -= $reduz;
                }
            } catch (\Throwable $e) {
            }
        }

        $pendAtual = $this->getTotalPendenciaCompraProduto($produtoId);
        $adicional = $deficit - $pendAtual;
        if ($adicional <= 0) {
            return 0;
        }

        // Reaproveitar lógica existente (inferir loja + último pedido) para criar pendência.
        $this->ajustarListaComprasAposSaida($produtoId, $adicional);
        return $adicional;
    }

    public function reservasProduto($request) {
        header('Content-Type: application/json; charset=utf-8');

        $produtoId = (int) $request->getParam('produto_id', 0);
        if ($produtoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            return;
        }

        try {
            // Se existir reserva real, ela é a fonte de verdade
            if ($this->tableExists('estoque_reservas')) {
                $stmt = $this->connection->prepare(
                    "SELECT er.pedido_id, SUM(COALESCE(er.quantidade_reservada,0)) as quantidade_reservada
                     FROM estoque_reservas er
                     LEFT JOIN pedidos p ON p.id = er.pedido_id
                     WHERE er.produto_id = :produto_id
                       AND er.status = 'ativa'
                       AND (p.id IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','concluido','concluído','finalizado','finalizada','entregue','entregue ao cliente','completed','refunded','estornado','estornada'))
                     GROUP BY er.pedido_id
                     ORDER BY er.pedido_id DESC"
                );
                $stmt->execute([':produto_id' => $produtoId]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $pedidoIds = [];
                $qPorPedido = [];
                $orfas = 0;
                foreach ($rows as $r) {
                    $pid = (int) ($r['pedido_id'] ?? 0);
                    $q = (int) ($r['quantidade_reservada'] ?? 0);
                    if ($pid <= 0) {
                        $orfas += $q;
                        continue;
                    }
                    $pedidoIds[] = $pid;
                    $qPorPedido[$pid] = $q;
                }

                if (empty($pedidoIds)) {
                    $out = [];
                    if ($orfas > 0) {
                        $out[] = [
                            'id' => 0,
                            'codigo_pedido' => '',
                            'status' => 'Reserva órfã (sem pedido)',
                            'valor_total' => null,
                            'moeda' => '',
                            'created_at' => '',
                            'pago_em' => '',
                            'cliente_nome' => '',
                            'cliente_email' => '',
                            'quantidade_reservada' => (int) $orfas,
                            'itens' => [],
                        ];
                    }
                    echo json_encode(['success' => true, 'pedidos' => $out]);
                    return;
                }

                $in = implode(',', array_fill(0, count($pedidoIds), '?'));
                $stmtPedidos = $this->connection->prepare(
                    "SELECT p.*, u.name as cliente_nome, u.email as cliente_email
                     FROM pedidos p
                     LEFT JOIN usuarios u ON u.id = p.usuario_id
                     WHERE p.id IN ($in)"
                );
                $stmtPedidos->execute(array_map('intval', $pedidoIds));
                $pedidosRows = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                $itensTable = $this->findPedidoItensTable();
                $pedidos = [];
                foreach ($pedidosRows as $p) {
                    $pid = (int) ($p['id'] ?? 0);
                    $pedidos[$pid] = [
                        'id' => $pid,
                        'codigo_pedido' => (string) ($p['codigo_pedido'] ?? ''),
                        'status' => (string) ($p['status'] ?? ''),
                        'valor_total' => isset($p['valor_total']) ? (float) $p['valor_total'] : null,
                        'moeda' => (string) ($p['moeda'] ?? ''),
                        'created_at' => (string) ($p['created_at'] ?? ''),
                        'pago_em' => isset($p['pago_em']) ? (string) $p['pago_em'] : '',
                        'cliente_nome' => (string) ($p['cliente_nome'] ?? ''),
                        'cliente_email' => (string) ($p['cliente_email'] ?? ''),
                        'quantidade_reservada' => (int) ($qPorPedido[$pid] ?? 0),
                        'itens' => [],
                    ];
                }

                if ($itensTable) {
                    $stmtItens = $this->connection->prepare(
                        "SELECT i.*
                         FROM $itensTable i
                         WHERE i.pedido_id IN ($in) AND i.produto_id = ?"
                    );
                    $vals = array_map('intval', $pedidoIds);
                    $vals[] = $produtoId;
                    $stmtItens->execute($vals);
                    $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($itens as $it) {
                        $pid = (int) ($it['pedido_id'] ?? 0);
                        if (!isset($pedidos[$pid])) continue;
                        $pedidos[$pid]['itens'][] = [
                            'produto_id' => (int) ($it['produto_id'] ?? 0),
                            'quantidade' => (int) ($it['quantidade'] ?? 0),
                            'preco_unitario' => isset($it['preco_unitario']) ? (float) $it['preco_unitario'] : (isset($it['valor_unitario']) ? (float) $it['valor_unitario'] : null),
                            'subtotal' => isset($it['subtotal']) ? (float) $it['subtotal'] : null,
                            'nome_produto' => (string) ($it['nome_produto'] ?? ''),
                            'nome_produto_sku' => (string) ($it['nome_produto_sku'] ?? ''),
                        ];
                    }
                }

                if ($orfas > 0) {
                    $pedidos[0] = [
                        'id' => 0,
                        'codigo_pedido' => '',
                        'status' => 'Reserva órfã (sem pedido)',
                        'valor_total' => null,
                        'moeda' => '',
                        'created_at' => '',
                        'pago_em' => '',
                        'cliente_nome' => '',
                        'cliente_email' => '',
                        'quantidade_reservada' => (int) $orfas,
                        'itens' => [],
                    ];
                }

                echo json_encode(['success' => true, 'pedidos' => array_values($pedidos)]);
                return;
            }

            // Fallback: sem reserva real, usa pedidos relacionados via lista_compras
            if (!$this->tableExists('lista_compras') || !$this->columnExists('lista_compras', 'pedido_id')) {
                echo json_encode(['success' => true, 'pedidos' => []]);
                return;
            }

            $stmt = $this->connection->prepare(
                "SELECT DISTINCT lc.pedido_id
                 FROM lista_compras lc
                 WHERE lc.produto_id = :produto_id
                   AND lc.pedido_id IS NOT NULL
                   AND lc.pedido_id <> 0
                 ORDER BY lc.pedido_id DESC"
            );
            $stmt->execute([':produto_id' => $produtoId]);
            $pedidoIds = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            if (empty($pedidoIds)) {
                echo json_encode(['success' => true, 'pedidos' => []]);
                return;
            }

            $in = implode(',', array_fill(0, count($pedidoIds), '?'));
            $stmtPedidos = $this->connection->prepare(
                "SELECT p.*, u.name as cliente_nome, u.email as cliente_email
                 FROM pedidos p
                 LEFT JOIN usuarios u ON u.id = p.usuario_id
                 WHERE p.id IN ($in)"
            );
            $stmtPedidos->execute(array_map('intval', $pedidoIds));
            $pedidosRows = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $itensTable = $this->findPedidoItensTable();
            $pedidos = [];
            foreach ($pedidosRows as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $pedidos[$pid] = [
                    'id' => $pid,
                    'codigo_pedido' => (string) ($p['codigo_pedido'] ?? ''),
                    'status' => (string) ($p['status'] ?? ''),
                    'valor_total' => isset($p['valor_total']) ? (float) $p['valor_total'] : null,
                    'moeda' => (string) ($p['moeda'] ?? ''),
                    'created_at' => (string) ($p['created_at'] ?? ''),
                    'pago_em' => isset($p['pago_em']) ? (string) $p['pago_em'] : '',
                    'cliente_nome' => (string) ($p['cliente_nome'] ?? ''),
                    'cliente_email' => (string) ($p['cliente_email'] ?? ''),
                    'itens' => [],
                ];
            }

            if ($itensTable) {
                $stmtItens = $this->connection->prepare(
                    "SELECT i.*
                     FROM $itensTable i
                     WHERE i.pedido_id IN ($in) AND i.produto_id = ?"
                );
                $vals = array_map('intval', $pedidoIds);
                $vals[] = $produtoId;
                $stmtItens->execute($vals);
                $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($itens as $it) {
                    $pid = (int) ($it['pedido_id'] ?? 0);
                    if (!isset($pedidos[$pid])) continue;
                    $pedidos[$pid]['itens'][] = [
                        'produto_id' => (int) ($it['produto_id'] ?? 0),
                        'quantidade' => (int) ($it['quantidade'] ?? 0),
                        'preco_unitario' => isset($it['preco_unitario']) ? (float) $it['preco_unitario'] : (isset($it['valor_unitario']) ? (float) $it['valor_unitario'] : null),
                        'subtotal' => isset($it['subtotal']) ? (float) $it['subtotal'] : null,
                        'nome_produto' => (string) ($it['nome_produto'] ?? ''),
                        'nome_produto_sku' => (string) ($it['nome_produto_sku'] ?? ''),
                    ];
                }
            }

            echo json_encode(['success' => true, 'pedidos' => array_values($pedidos)]);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar reservas.']);
            return;
        }
    }

    private function findPedidoItensTable(): ?string {
        try {
            $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmtT->execute(['pedido_itens']);
            if ((int) $stmtT->fetchColumn() > 0) {
                return 'pedido_itens';
            }
            $stmtT->execute(['pedido_items']);
            if ((int) $stmtT->fetchColumn() > 0) {
                return 'pedido_items';
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    private function findUltimoPedidoDoProduto(int $produtoId): int {
        $itensTable = $this->findPedidoItensTable();
        if (!$itensTable) {
            return 0;
        }

        try {
            $stmt = $this->connection->prepare(
                "SELECT p.id
                 FROM pedidos p
                 JOIN {$itensTable} i ON i.pedido_id = p.id
                 WHERE i.produto_id = :produto_id
                 ORDER BY COALESCE(p.pago_em, p.created_at) DESC, p.id DESC
                 LIMIT 1"
            );
            $stmt->execute([':produto_id' => $produtoId]);
            $pid = (int) ($stmt->fetchColumn() ?: 0);
            return $pid;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function ajustarListaComprasAposEntrada(int $produtoId, int $quantidadeEntrada): void {
        if ($produtoId <= 0 || $quantidadeEntrada <= 0) {
            return;
        }
        if (!$this->tableExists('lista_compras')) {
            return;
        }

        try {
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            $selectPedido = $temPedidoEmLista ? ', pedido_id' : '';
            $stmt = $this->connection->prepare(
                "SELECT id, quantidade_faltante
                 {$selectPedido}
                 FROM lista_compras
                 WHERE produto_id = :produto_id AND status = 'pendente'
                 ORDER BY COALESCE(data_solicitacao, created_at) ASC, id ASC"
            );
            $stmt->execute([':produto_id' => $produtoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $restante = $quantidadeEntrada;

            $podeReservar = $this->tableExists('estoque_reservas')
                && $this->columnExists('estoque_reservas', 'produto_id')
                && $this->columnExists('estoque_reservas', 'quantidade_reservada')
                && $this->columnExists('estoque_reservas', 'status');

            $temPedidoEmReserva = $this->tableExists('estoque_reservas') && $this->columnExists('estoque_reservas', 'pedido_id');

            foreach ($rows as $r) {
                if ($restante <= 0) {
                    break;
                }
                $id = (int) ($r['id'] ?? 0);
                $falt = (int) ($r['quantidade_faltante'] ?? 0);
                if ($id <= 0 || $falt <= 0) {
                    continue;
                }

                $consumir = ($falt <= $restante) ? $falt : $restante;
                $pedidoId = $temPedidoEmLista ? (int) ($r['pedido_id'] ?? 0) : 0;

                // Quando entra estoque, a pendência (faltante) só pode diminuir se conseguirmos manter o "reservado" apontando
                // (convertendo a parte atendida em reserva real). Se não existir estoque_reservas, NÃO baixamos a pendência.
                if ($podeReservar && $consumir > 0) {
                    try {
                        if ($temPedidoEmReserva && $pedidoId > 0) {
                            $stmtChk = $this->connection->prepare(
                                "SELECT id, quantidade_reservada FROM estoque_reservas WHERE produto_id = :produto_id AND pedido_id = :pedido_id AND status = 'ativa' LIMIT 1"
                            );
                            $stmtChk->execute([':produto_id' => $produtoId, ':pedido_id' => $pedidoId]);
                            $ex = $stmtChk->fetch(\PDO::FETCH_ASSOC);
                            if ($ex && (int) ($ex['id'] ?? 0) > 0) {
                                $stmtUpRes = $this->connection->prepare(
                                    "UPDATE estoque_reservas SET quantidade_reservada = (COALESCE(quantidade_reservada,0) + :q) WHERE id = :id LIMIT 1"
                                );
                                $stmtUpRes->execute([':q' => $consumir, ':id' => (int) $ex['id']]);
                            } else {
                                $cols = ['produto_id', 'pedido_id', 'quantidade_reservada', 'status'];
                                $vals = [':produto_id', ':pedido_id', ':q', "'ativa'"];
                                $params = [':produto_id' => $produtoId, ':pedido_id' => $pedidoId, ':q' => $consumir];
                                $stmtInsRes = $this->connection->prepare(
                                    'INSERT INTO estoque_reservas (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'
                                );
                                $stmtInsRes->execute($params);
                            }
                        } else {
                            // Sem pedido_id disponível: cria uma reserva genérica (mantém o "reservado" apontando)
                            $cols = ['produto_id', 'quantidade_reservada', 'status'];
                            $vals = [':produto_id', ':q', "'ativa'"];
                            $params = [':produto_id' => $produtoId, ':q' => $consumir];
                            $stmtInsRes = $this->connection->prepare(
                                'INSERT INTO estoque_reservas (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'
                            );
                            $stmtInsRes->execute($params);
                        }
                    } catch (\Exception $e) {
                    }
                } else {
                    // Sem suporte a reserva real: manter a demanda na lista de compras para não zerar o reservado.
                    continue;
                }

                if ($falt <= $restante) {
                    $stmtUpd = $this->connection->prepare("UPDATE lista_compras SET quantidade_faltante = 0 WHERE id = :id LIMIT 1");
                    $stmtUpd->execute([':id' => $id]);
                    $restante -= $falt;
                } else {
                    $stmtUpd = $this->connection->prepare("UPDATE lista_compras SET quantidade_faltante = :falt WHERE id = :id LIMIT 1");
                    $stmtUpd->execute([':id' => $id, ':falt' => ($falt - $restante)]);
                    $restante = 0;
                    break;
                }
            }
        } catch (\Exception $e) {
        }
    }

    private function ajustarListaComprasAposSaida(int $produtoId, int $quantidadeSaida): void {
        if ($produtoId <= 0 || $quantidadeSaida <= 0) {
            return;
        }
        if (!$this->tableExists('lista_compras')) {
            return;
        }

        try {
            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');
            $temPedidoEmLista = $this->columnExists('lista_compras', 'pedido_id');
            $temLojaIdEmProdutos = $this->columnExists('produtos', 'loja_id');
            $lojaId = 0;
            if ($temLojaIdEmProdutos) {
                try {
                    $stmtL = $this->connection->prepare('SELECT loja_id FROM produtos WHERE id = :id LIMIT 1');
                    $stmtL->execute([':id' => $produtoId]);
                    $lojaId = (int) ($stmtL->fetchColumn() ?: 0);
                } catch (\Exception $e) {
                    $lojaId = 0;
                }
            }

            $pedidoId = $this->findUltimoPedidoDoProduto($produtoId);

            $cols = ['produto_id', 'quantidade_necessaria', 'quantidade_faltante', 'prioridade', 'status', 'data_solicitacao'];
            $vals = [':produto_id', ':q', ':q', "'media'", "'pendente'", 'CURDATE()'];
            $params = [':produto_id' => $produtoId, ':q' => $quantidadeSaida];

            if ($temLojaIdEmLista) {
                $cols[] = 'loja_id';
                if ($lojaId > 0) {
                    $vals[] = ':loja_id';
                    $params[':loja_id'] = $lojaId;
                } else {
                    $vals[] = 'NULL';
                }
            }
            if ($temPedidoEmLista) {
                $cols[] = 'pedido_id';
                if ($pedidoId > 0) {
                    $vals[] = ':pedido_id';
                    $params[':pedido_id'] = $pedidoId;
                } else {
                    $vals[] = 'NULL';
                }
            }

            $stmtIns = $this->connection->prepare('INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
            $stmtIns->execute($params);
        } catch (\Exception $e) {
        }
    }

    private function setFlash(string $message, string $type = 'success'): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }

    private function renderFlashIfAny(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION['message'])) {
            return;
        }
        $type = (string) ($_SESSION['message_type'] ?? 'info');
        $msg = (string) $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show mt-3" role="alert">'
            . htmlspecialchars($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
            . '</div>';
    }

    private function requireWriteAccess(bool $json = false): bool {
        $logged = $this->getLoggedUser();
        $perfil = strtolower(trim((string) ($logged['perfil'] ?? '')));
        $ok = in_array($perfil, ['admin', 'vendedor'], true);
        if ($ok) {
            return true;
        }

        if ($json) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            return false;
        }

        $this->setFlash('Acesso negado.', 'danger');
        header('Location: /admin/estoque');
        exit;
    }

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

    private function getLoggedUser(): ?array {
        try {
            $auth = new AuthService();
            $u = $auth->getUsuarioLogado();
            if (!$u) {
                return null;
            }
            return $u;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getProdutosSchema(): array {
        $cols = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE produtos');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $nameCol = null;
        foreach (['name', 'nome', 'titulo', 'title', 'produto_nome'] as $c) {
            if (in_array($c, $cols, true)) {
                $nameCol = $c;
                break;
            }
        }

        $skuCol = in_array('sku', $cols, true) ? 'sku' : null;

        $activeCol = null;
        foreach (['active', 'ativo'] as $c) {
            if (in_array($c, $cols, true)) {
                $activeCol = $c;
                break;
            }
        }

        $priceCol = null;
        foreach (['price', 'valor', 'preco', 'sale_price', 'cost_price'] as $c) {
            if (in_array($c, $cols, true)) {
                $priceCol = $c;
                break;
            }
        }

        $currencyCol = null;
        foreach (['moeda', 'currency'] as $c) {
            if (in_array($c, $cols, true)) {
                $currencyCol = $c;
                break;
            }
        }

        $imgCol = null;
        foreach (['foto_principal', 'image_url', 'image', 'imagem', 'images'] as $c) {
            if (in_array($c, $cols, true)) {
                $imgCol = $c;
                break;
            }
        }

        return [
            'cols' => $cols,
            'nameCol' => $nameCol,
            'skuCol' => $skuCol,
            'activeCol' => $activeCol,
            'priceCol' => $priceCol,
            'currencyCol' => $currencyCol,
            'imgCol' => $imgCol,
        ];
    }

    private function getProdutosStockCol(): ?string {
        $schema = $this->getProdutosSchema();
        $cols = $schema['cols'] ?? [];
        if (!is_array($cols) || empty($cols)) {
            return null;
        }

        foreach (['estoque', 'estoque_atual', 'saldo', 'quantidade', 'qty', 'stock_quantity', 'stock'] as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }

        return null;
    }

    private function syncProdutoEstoqueFromInterno(int $produtoId): void {
        if ($produtoId <= 0) {
            return;
        }
        if (!$this->tableExists('estoque_interno')) {
            return;
        }

        $stockCol = $this->getProdutosStockCol();
        if (!$stockCol) {
            return;
        }

        try {
            $stmtTotal = $this->connection->prepare('SELECT COALESCE(SUM(COALESCE(quantidade,0)),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
            $stmtTotal->execute([':produto_id' => $produtoId]);
            $total = (int) (($stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));

            $stmtUpd = $this->connection->prepare('UPDATE produtos SET `' . $stockCol . '` = :total WHERE id = :id LIMIT 1');
            $stmtUpd->execute([':total' => $total, ':id' => $produtoId]);
        } catch (\Exception $e) {
        }
    }

    private function resolveProdutoImagem(array $produto, ?string $imgCol): ?string {
        if (!$imgCol) {
            return null;
        }

        $raw = $produto[$imgCol] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        // coluna images pode vir como JSON (array de URLs/paths)
        if ($imgCol === 'images' && ($raw[0] === '[' || $raw[0] === '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $candidate = null;
                if (isset($decoded[0]) && is_string($decoded[0])) {
                    $candidate = $decoded[0];
                } elseif (isset($decoded['0']) && is_string($decoded['0'])) {
                    $candidate = $decoded['0'];
                } elseif (isset($decoded['url']) && is_string($decoded['url'])) {
                    $candidate = $decoded['url'];
                }
                if (is_string($candidate) && trim($candidate) !== '') {
                    $raw = trim($candidate);
                }
            }
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        if (strpos($raw, '/uploads/') === 0) {
            return $raw;
        }

        if (strpos($raw, 'uploads/') === 0) {
            return '/' . $raw;
        }

        return '/uploads/produtos/' . ltrim($raw, '/');
    }

    public function buscarProdutos($request) {
        try {
            $schema = $this->getProdutosSchema();
            $nameCol = $schema['nameCol'];
            $skuCol = $schema['skuCol'];
            $activeCol = $schema['activeCol'];
            $priceCol = $schema['priceCol'];
            $currencyCol = $schema['currencyCol'];
            $imgCol = $schema['imgCol'];

            $q = trim((string) $request->getParam('q', ''));
            $limit = (int) $request->getParam('limit', 25);
            if ($limit <= 0) {
                $limit = 25;
            }
            if ($limit > 50) {
                $limit = 50;
            }

            $select = ['id'];
            if ($nameCol) {
                $select[] = $nameCol . ' AS nome';
            } else {
                $select[] = "CAST(id AS CHAR) AS nome";
            }
            if ($skuCol) {
                $select[] = $skuCol . ' AS sku';
            } else {
                $select[] = "'' AS sku";
            }
            if ($priceCol) {
                $select[] = $priceCol . ' AS preco';
            } else {
                $select[] = "NULL AS preco";
            }
            if ($currencyCol) {
                $select[] = $currencyCol . ' AS moeda';
            } else {
                $select[] = "'' AS moeda";
            }
            if ($imgCol) {
                $select[] = $imgCol . ' AS imagem_raw';
            } else {
                $select[] = "'' AS imagem_raw";
            }

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM produtos';
            $where = [];
            $params = [];

            if ($activeCol) {
                $where[] = $activeCol . ' = 1';
            }

            if ($q !== '') {
                $likeClauses = [];
                if ($nameCol) {
                    $likeClauses[] = $nameCol . ' LIKE :q';
                }
                if ($skuCol) {
                    $likeClauses[] = $skuCol . ' LIKE :q';
                }

                $params[':q'] = '%' . $q . '%';

                if (ctype_digit($q)) {
                    $or = ['id = :id'];
                    $params[':id'] = (int) $q;
                    if (!empty($likeClauses)) {
                        $or[] = '(' . implode(' OR ', $likeClauses) . ')';
                    }
                    $where[] = '(' . implode(' OR ', $or) . ')';
                } else {
                    if (!empty($likeClauses)) {
                        $where[] = '(' . implode(' OR ', $likeClauses) . ')';
                    }
                }
            }

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY nome LIMIT ' . (int) $limit;

            $stmt = $this->connection->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $out = [];
            foreach ($rows as $r) {
                $img = null;
                if ($imgCol) {
                    $img = $this->resolveProdutoImagem(['imagem_raw' => $r['imagem_raw']], 'imagem_raw');
                }

                $out[] = [
                    'id' => (int) ($r['id'] ?? 0),
                    'nome' => (string) ($r['nome'] ?? ''),
                    'sku' => (string) ($r['sku'] ?? ''),
                    'preco' => isset($r['preco']) ? (float) $r['preco'] : null,
                    'moeda' => (string) ($r['moeda'] ?? ''),
                    'imagem' => $img,
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'items' => $out]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'items' => []]);
            exit;
        }
    }

    public function entrada($request) {
        $this->requireWriteAccess(false);
        $prefillProdutoId = (int) $request->getParam('produto_id', 0);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de Estoque - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('estoque');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-plus me-2"></i>Entrada de Estoque (Galpão)</h1>
                    <div>
                        <a class="btn btn-outline-secondary" href="/admin/estoque">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                </div>';

        $this->renderFlashIfAny();

        echo '
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Buscar produto</h6>
                            </div>
                            <div class="card-body">
                                <input type="text" class="form-control" id="produto_busca" placeholder="Digite nome ou SKU..." oninput="buscarProdutos()" autocomplete="off">
                                <div class="text-muted small mt-2">Clique em um produto para selecionar.</div>
                                <div id="resultado_busca" class="mt-3" style="max-height: 520px; overflow:auto;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Dados da entrada</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="/admin/estoque/salvar" id="form_entrada_estoque">
                                    <input type="hidden" name="produto_id" id="produto_id" value="' . (int) $prefillProdutoId . '">

                                    <div class="mb-3">
                                        <label class="form-label">Produto selecionado</label>
                                        <div id="produto_selecionado" class="p-3" style="border: 1px solid rgba(148, 163, 184, 0.28); border-radius: 14px; background: #fff;">
                                            <div class="text-muted">Nenhum produto selecionado.</div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Quantidade disponível</label>
                                            <input type="number" class="form-control" name="quantidade" min="1" step="1" required>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Data da compra</label>
                                            <input type="date" class="form-control" name="data_compra">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Alimentício</label>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input" type="checkbox" value="1" id="is_alimenticio" name="is_alimenticio" onchange="toggleValidade()">
                                                <label class="form-check-label" for="is_alimenticio">Controlar validade</label>
                                            </div>
                                        </div>

                                        <div class="col-md-4" id="grupo_validade" style="display:none;">
                                            <label class="form-label">Data de validade</label>
                                            <input type="date" class="form-control" name="data_validade">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Galpão</label>
                                            <input type="text" class="form-control" name="galpao" placeholder="Ex: Galpão A">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Prateleira</label>
                                            <input type="text" class="form-control" name="prateleira" placeholder="Ex: 3">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Observação</label>
                                            <input type="text" class="form-control" name="observacao" placeholder="Opcional">
                                        </div>
                                    </div>

                                    <div class="mt-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary" onclick="return validarEntradaEstoque()">
                                            <i class="fas fa-save me-1"></i>Salvar entrada
                                        </button>
                                        <a class="btn btn-outline-secondary" href="/admin/estoque">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        var lastQuery = "";
        var busy = false;
        var produtosCache = {};

        function toggleValidade() {
            var chk = document.getElementById("is_alimenticio");
            var grp = document.getElementById("grupo_validade");
            if (!chk || !grp) return;
            grp.style.display = chk.checked ? "" : "none";
        }

        function formatMoney(v, moeda) {
            if (v === null || typeof v === "undefined" || isNaN(v)) return "";
            try {
                return (moeda ? (moeda + " ") : "") + Number(v).toFixed(2);
            } catch (e) {
                return (moeda ? (moeda + " ") : "") + v;
            }
        }

        function renderItem(item) {
            produtosCache[item.id] = item;
            var img = item.imagem ? `<img src="${item.imagem}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:12px;">` : `<div style="width:56px;height:56px;border-radius:12px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>`;
            var sku = item.sku ? `<div class="text-muted small">SKU: ${item.sku}</div>` : ``;
            var preco = item.preco !== null ? `<div class="small" style="color:#0b1f3a;font-weight:700;">${formatMoney(item.preco, item.moeda)}</div>` : ``;
            return `
                <button type="button" class="w-100 text-start p-2 mb-2" style="border:1px solid rgba(148,163,184,.22);border-radius:14px;background:#fff;" onclick="selecionarProduto(${item.id})" id="produto_item_${item.id}">
                    <div class="d-flex gap-3 align-items-center">
                        ${img}
                        <div class="flex-grow-1">
                            <div style="font-weight:700;color:#0f172a;">${item.nome || "(Sem nome)"}</div>
                            ${sku}
                            ${preco}
                        </div>
                    </div>
                </button>
            `;
        }

        function buscarProdutos(force) {
            var input = document.getElementById("produto_busca");
            var box = document.getElementById("resultado_busca");
            if (!input || !box) return;
            var q = (input.value || "").trim();
            if (!force && q === lastQuery) return;
            lastQuery = q;
            if (busy) return;
            busy = true;

            fetch("/admin/estoque/buscar-produtos?q=" + encodeURIComponent(q) + "&limit=30")
                .then(r => r.json())
                .then(data => {
                    var items = (data && data.items) ? data.items : [];
                    if (!items.length) {
                        box.innerHTML = `<div class="text-muted">Nenhum produto encontrado.</div>`;
                        return;
                    }
                    box.innerHTML = items.map(renderItem).join("");
                })
                .catch(() => {
                    box.innerHTML = `<div class="text-muted">Erro ao buscar produtos.</div>`;
                })
                .finally(() => {
                    busy = false;
                });
        }

        function selecionarProduto(id) {
            var inputId = document.getElementById("produto_id");
            var preview = document.getElementById("produto_selecionado");
            if (!inputId || !preview) return;

            var item = produtosCache[id] || null;
            if (!item) {
                alert("Não foi possível carregar os dados do produto selecionado.");
                return;
            }

            inputId.value = String(id);
            var img = item.imagem ? `<img src="${item.imagem}" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:12px;">` : `<div style="width:64px;height:64px;border-radius:12px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>`;
            var sku = item.sku ? `<div class="text-muted small">SKU: ${item.sku}</div>` : ``;
            var preco = item.preco !== null ? `<div class="small" style="color:#0b1f3a;font-weight:700;">${formatMoney(item.preco, item.moeda)}</div>` : ``;

            preview.innerHTML = `
                <div class="d-flex gap-3 align-items-center">
                    ${img}
                    <div>
                        <div style="font-weight:800;color:#0f172a;">${item.nome || "(Sem nome)"}</div>
                        ${sku}
                        ${preco}
                    </div>
                </div>
            `;
        }

        function validarEntradaEstoque() {
            var produtoId = document.getElementById("produto_id");
            if (!produtoId || !produtoId.value || produtoId.value === "0") {
                alert("Selecione um produto antes de salvar.");
                return false;
            }
            return true;
        }

        document.addEventListener("DOMContentLoaded", function() {
            buscarProdutos(true);
            var pre = ' . (int) $prefillProdutoId . ';
            if (pre && pre > 0) {
                fetch("/admin/estoque/buscar-produtos?q=" + encodeURIComponent(String(pre)) + "&limit=30")
                    .then(r => r.json())
                    .then(data => {
                        var items = (data && data.items) ? data.items : [];
                        for (var i = 0; i < items.length; i++) {
                            if (items[i].id === pre) {
                                var box = document.getElementById("resultado_busca");
                                if (box) box.innerHTML = items.map(renderItem).join("");
                                selecionarProduto(pre);
                                break;
                            }
                        }
                    });
            }
        });
    </script>';

        renderAdminScripts();

        echo '</body></html>';
        exit;
    }

    public function index($request) {
        try {
            // Esta tela é apenas para listagem. A entrada é feita em /admin/estoque/entrada.

            $schemaProdutos = $this->getProdutosSchema();
            $nameCol = $schemaProdutos['nameCol'] ?? null;
            $skuCol = $schemaProdutos['skuCol'] ?? null;
            $imgCol = $schemaProdutos['imgCol'] ?? null;
            $imgSelect = "'' AS imagem_raw";
            if (is_string($imgCol) && $imgCol !== '') {
                $imgSelect = 'p.' . $imgCol . ' AS imagem_raw';
            }

            $nameExpr = (is_string($nameCol) && $nameCol !== '') ? ('p.' . $nameCol) : "CAST(p.id AS CHAR)";
            $skuExpr = (is_string($skuCol) && $skuCol !== '') ? ('p.' . $skuCol) : "''";

            // Buscar status geral do estoque (apenas itens com quantidade no galpão)
            // Regra (UI): Reservado = apenas reservas reais (estoque_reservas ativa). A pendência de compra fica na tela de compras.
            $reservaJoin = '';
            $reservadoSelectExpr = '0';

            if ($this->tableExists('estoque_reservas')) {
                $reservaJoin .= "
                    LEFT JOIN (
                        SELECT er.produto_id, SUM(COALESCE(er.quantidade_reservada,0)) as reservado
                        FROM estoque_reservas er
                        LEFT JOIN pedidos p ON p.id = er.pedido_id
                        WHERE er.status = 'ativa'
                          AND (p.id IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','concluido','concluído','finalizado','finalizada','entregue','entregue ao cliente','completed','refunded','estornado','estornada'))
                        GROUP BY er.produto_id
                    ) res_er ON res_er.produto_id = p.id
                ";
                $reservadoSelectExpr = 'COALESCE(res_er.reservado, 0)';
            }

            $stmt = $this->connection->prepare("
                SELECT
                    p.id as produto_id,
                    {$nameExpr} as produto_nome,
                    {$skuExpr} as sku,
                    e.total as quantidade_estoque,
                    CASE
                        WHEN e.total <= COALESCE(ec.estoque_minimo, 5) THEN 'crítico'
                        WHEN e.total <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
                        ELSE 'normal'
                    END as status_estoque,
                    loc.localizacao,
                    loc.data_compra_mais_recente,
                    loc.validade_mais_proxima,
                    {$reservadoSelectExpr} as reservado,
                    {$imgSelect}
                FROM (
                    SELECT produto_id, SUM(COALESCE(quantidade,0)) as total
                    FROM estoque_interno
                    WHERE quantidade > 0
                    GROUP BY produto_id
                ) e
                JOIN produtos p ON p.id = e.produto_id
                LEFT JOIN estoque_configuracoes ec ON ec.produto_id = p.id
                {$reservaJoin}
                JOIN (
                    SELECT
                        e.produto_id,
                        GROUP_CONCAT(DISTINCT CONCAT(
                            COALESCE(e.galpao, ''),
                            CASE WHEN COALESCE(e.galpao, '') <> '' AND COALESCE(e.prateleira, '') <> '' THEN ' - ' ELSE '' END,
                            COALESCE(e.prateleira, '')
                        ) SEPARATOR ', ') AS localizacao,
                        MAX(e.data_compra) AS data_compra_mais_recente,
                        MIN(CASE WHEN e.is_alimenticio = 1 AND e.data_validade IS NOT NULL THEN e.data_validade ELSE NULL END) AS validade_mais_proxima
                    FROM estoque_interno e
                    WHERE e.quantidade > 0
                    GROUP BY e.produto_id
                ) loc ON loc.produto_id = p.id
                ORDER BY produto_nome
            ");
            $stmt->execute();
            $status_geral = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas
            $stmt = $this->connection->prepare("SELECT
                    COUNT(*) as total_produtos,
                    SUM(CASE WHEN status_estoque IN ('crítico','critico') THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN status_estoque = 'baixo' THEN 1 ELSE 0 END) as baixos,
                    SUM(CASE WHEN status_estoque = 'normal' THEN 1 ELSE 0 END) as normais
                FROM (
                    SELECT
                        p.id as produto_id,
                        CASE
                            WHEN e.total <= COALESCE(ec.estoque_minimo, 5) THEN 'crítico'
                            WHEN e.total <= COALESCE(ec.estoque_ideal, 20) THEN 'baixo'
                            ELSE 'normal'
                        END as status_estoque
                    FROM (
                        SELECT produto_id, SUM(COALESCE(quantidade,0)) as total
                        FROM estoque_interno
                        WHERE quantidade > 0
                        GROUP BY produto_id
                    ) e
                    JOIN produtos p ON p.id = e.produto_id
                    LEFT JOIN estoque_configuracoes ec ON ec.produto_id = p.id
                ) t
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $produtos = [];
            $status_geral = [];
            $estatisticas = ['total_produtos' => 0, 'criticos' => 0, 'baixos' => 0, 'normais' => 0];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque Interno - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('estoque');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-warehouse me-2"></i>Estoque Interno</h1>
                    <div>
                        <a class="btn btn-success me-2" href="/admin/estoque/entrada">
                            <i class="fas fa-plus me-1"></i>Entrada de Estoque
                        </a>
                        <button type="button" class="btn btn-primary me-2" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')">
                            <i class="fas fa-file-pdf me-1"></i>Gerar PDF
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';

        $this->renderFlashIfAny();

                // Cards de Estatísticas
                echo '<div class="container py-4"><div class="row g-3">';
                    echo '<div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Produtos</h5>
                                <h3>' . number_format($estatisticas['total_produtos']) . '</h3>
                                <small>Ativos no sistema</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Crítico</h5>
                                <h3>' . number_format($estatisticas['criticos']) . '</h3>
                                <small>Abaixo do mínimo</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Baixo</h5>
                                <h3>' . number_format($estatisticas['baixos']) . '</h3>
                                <small>Abaixo do ideal</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Normal</h5>
                                <h3>' . number_format($estatisticas['normais']) . '</h3>
                                <small>Níveis adequados</small>
                            </div>
                        </div>
                    </div>
                </div></div>';

                // Tabela de Estoque
                echo '<div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Estoque Atual</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="estoque_busca" placeholder="Buscar por produto, SKU, loja, localização ou status..." oninput="filtrarTabelaEstoque()">
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <small class="text-muted" id="estoque_busca_info"></small>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="estoque_tabela">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Quantidade</th>
                                        <th>Reservado</th>
                                        <th>Disponível</th>
                                        <th>Status</th>
                                        <th>Localização</th>
                                        <th>Validade</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="estoque_tbody">';
                                
                                foreach ($status_geral as $item) {
                                    $produtoId = (int) ($item['produto_id'] ?? 0);
                                    $produtoNome = (string) ($item['produto_nome'] ?? '');
                                    $sku = (string) ($item['sku'] ?? '');
                                    $qtd = (int) ($item['quantidade_estoque'] ?? 0);
                                    $reservado = (int) ($item['reservado'] ?? 0);
                                    $disponivel = $qtd - $reservado;
                                    $status = (string) ($item['status_estoque'] ?? '');
                                    if ($reservado > $qtd) {
                                        $status = 'reposicao';
                                    }
                                    $loc = (string) ($item['localizacao'] ?? '');
                                    $validade = $item['validade_mais_proxima'] ?? null;

                                    $imgUrl = null;
                                    if (!empty($item['imagem_raw'])) {
                                        $imgUrl = $this->resolveProdutoImagem(['imagem_raw' => (string) $item['imagem_raw']], 'imagem_raw');
                                    }
                                    $imgTag = $imgUrl
                                        ? '<img src="' . htmlspecialchars($imgUrl) . '" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:10px; border: 1px solid rgba(148, 163, 184, 0.22); background: rgba(148, 163, 184, 0.06);">'
                                        : '<div style="width:36px;height:36px;border-radius:10px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>';
                                    
                                    $rowSearch = strtolower(
                                        (string) ($produtoNome ?? '') . ' ' .
                                        (string) ($sku ?? '') . ' ' .
                                        (string) ($loc ?? '') . ' ' .
                                        (string) ($status ?? '')
                                    );

                                    $btnEye = ($reservado > 0)
                                        ? '<button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#modalReservas" data-produto-id="' . (int) $produtoId . '" data-produto-nome="' . htmlspecialchars($produtoNome) . '"><i class="fas fa-eye"></i></button>'
                                        : '';
                                    $acoes = '<div class="btn-group btn-group-sm">'
                                        . '<a href="/admin/estoque/editar/' . (int) $produtoId . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>'
                                        . $btnEye
                                        . '</div>';

                                    $reservadoBadge = $reservado > 0 ? '<span class="badge bg-dark">' . (int) $reservado . '</span>' : '-';
                                    $dispClass = ($disponivel < 0) ? 'danger' : 'secondary';
                                    $dispBadge = '<span class="badge bg-' . $dispClass . '">' . (int) $disponivel . '</span>';

                                    $status_class = $status == 'crítico' ? 'danger' :
                                                   ($status == 'baixo' ? 'warning' :
                                                   ($status == 'reposicao' ? 'danger' : 'success'));

                                    echo '<tr data-search="' . htmlspecialchars($rowSearch) . '">
                                        <td>
                                            <div class="d-flex gap-2 align-items-center">
                                                ' . $imgTag . '
                                                <div>
                                                    <strong>' . htmlspecialchars($produtoNome) . '</strong>
                                                    <br><small class="text-muted">ID: ' . $produtoId . '</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>' . htmlspecialchars($sku) . '</td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . $qtd . '</span>
                                        </td>
                                        <td>' . $reservadoBadge . '</td>
                                        <td>' . $dispBadge . '</td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ($status === 'reposicao' ? 'Reposição' : ucfirst($status)) . '</span>
                                        </td>
                                        <td>' . (!empty($loc) ? htmlspecialchars($loc) : '-') . '</td>
                                        <td>' . (!empty($validade) ? date('d/m/Y', strtotime($validade)) : '-') . '</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a class="btn btn-outline-primary" href="/admin/estoque/editar/' . (int) $produtoId . '">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                ' . $btnEye . '
                                            </div>
                                        </td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>';

                echo '<script>
                    function filtrarTabelaEstoque() {
                        var input = document.getElementById("estoque_busca");
                        var tbody = document.getElementById("estoque_tbody");
                        var info = document.getElementById("estoque_busca_info");
                        if (!input || !tbody) return;
                        var q = (input.value || "").toLowerCase().trim();
                        var rows = tbody.querySelectorAll("tr");
                        var vis = 0;
                        for (var i = 0; i < rows.length; i++) {
                            var r = rows[i];
                            var hay = (r.getAttribute("data-search") || "").toLowerCase();
                            var show = (q === "") || (hay.indexOf(q) !== -1);
                            r.style.display = show ? "" : "none";
                            if (show) vis++;
                        }
                        if (info) {
                            info.textContent = q === "" ? ("Exibindo " + vis + " item(ns).") : ("Encontrado(s) " + vis + " item(ns). ");
                        }
                    }
                    document.addEventListener("DOMContentLoaded", function() { filtrarTabelaEstoque(); });
                </script>';

                echo '<div class="modal fade" id="modalReservas" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reservas / Pedidos relacionados</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2 text-muted" id="reservas_produto_nome"></div>
                                    <div id="reservas_loading" class="text-muted">Carregando...</div>
                                    <div id="reservas_empty" class="alert alert-warning d-none">Nenhum pedido encontrado.</div>
                                    <div class="accordion" id="accordionReservas"></div>
                                </div>
                                <div class="modal-footer">
                                    <a class="btn btn-outline-primary" id="reservas_ir_compras" href="/admin/estoque/compras" target="_blank">Abrir lista de compras</a>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>';

                echo '<script>
                    function escapeHtml2(str){
                        if (str === null || str === undefined) return "";
                        return String(str)
                            .replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/\"/g, "&quot;")
                            .replace(/\'/g, "&#039;");
                    }
                    function formatMoney2(v){
                        if (v === null || v === undefined || v === "") return "-";
                        var n = Number(v);
                        if (isNaN(n)) return String(v);
                        return "$ " + n.toFixed(2);
                    }
                    function renderAccordionReservas(pedidos){
                        var acc = document.getElementById("accordionReservas");
                        if (!acc) return;
                        acc.innerHTML = "";
                        pedidos.forEach(function(p){
                            var pid = p.id || 0;
                            var headId = "resHead_" + pid;
                            var bodyId = "resBody_" + pid;
                            var total = (p.valor_total !== null && p.valor_total !== undefined) ? formatMoney2(p.valor_total) : "-";
                            var status = p.status ? escapeHtml2(p.status) : "";
                            var codigo = p.codigo_pedido ? escapeHtml2(p.codigo_pedido) : "";
                            var cliente = (p.cliente_nome || "") + (p.cliente_email ? (" - " + p.cliente_email) : "");
                            var criado = p.created_at ? escapeHtml2(p.created_at) : "";
                            var pagoEm = p.pago_em ? escapeHtml2(p.pago_em) : "";
                            var itensHtml = "";
                            if (Array.isArray(p.itens) && p.itens.length > 0) {
                                itensHtml += "<div class=\"table-responsive\"><table class=\"table table-sm\">";
                                itensHtml += "<thead><tr><th>Produto</th><th style=\"width:90px;\">Qtd</th><th style=\"width:120px;\">Preço</th><th style=\"width:120px;\">Subtotal</th></tr></thead><tbody>";
                                p.itens.forEach(function(it){
                                    itensHtml += "<tr>";
                                    itensHtml += "<td>" + escapeHtml2(it.nome_produto || it.nome_produto_sku || ("Produto ID: " + (it.produto_id||""))) + "</td>";
                                    itensHtml += "<td>" + escapeHtml2(it.quantidade || 0) + "</td>";
                                    itensHtml += "<td>" + formatMoney2(it.preco_unitario) + "</td>";
                                    itensHtml += "<td>" + formatMoney2(it.subtotal) + "</td>";
                                    itensHtml += "</tr>";
                                });
                                itensHtml += "</tbody></table></div>";
                            } else {
                                itensHtml = "<div class=\"text-muted\">Itens do pedido não disponíveis.</div>";
                            }
                            var html = "";
                            html += "<div class=\"accordion-item\">";
                            html += "<h2 class=\"accordion-header\" id=\"" + headId + "\">";
                            html += "<button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#" + bodyId + "\">";
                            html += "Pedido #" + pid + (codigo ? (" (" + codigo + ")") : "") + " - " + status + " - " + total;
                            html += "</button></h2>";
                            html += "<div id=\"" + bodyId + "\" class=\"accordion-collapse collapse\" data-bs-parent=\"#accordionReservas\">";
                            html += "<div class=\"accordion-body\">";
                            html += "<div class=\"mb-2\">";
                            html += "<div><strong>Cliente:</strong> " + escapeHtml2(cliente) + "</div>";
                            html += "<div><strong>Criado em:</strong> " + criado + "</div>";
                            if (pagoEm) html += "<div><strong>Pago em:</strong> " + pagoEm + "</div>";
                            html += "<div class=\"mt-2\"><a class=\"btn btn-sm btn-outline-primary\" href=\"/admin/pedidos/detalhes/" + pid + "\" target=\"_blank\">Abrir pedido</a></div>";
                            html += "</div>";
                            html += itensHtml;
                            html += "</div></div></div>";
                            acc.insertAdjacentHTML("beforeend", html);
                        });
                    }
                    var modalReservas = document.getElementById("modalReservas");
                    if (modalReservas) {
                        modalReservas.addEventListener("show.bs.modal", function (event) {
                            var button = event.relatedTarget;
                            var produtoId = button.getAttribute("data-produto-id") || "";
                            var produtoNome = button.getAttribute("data-produto-nome") || "";
                            var label = document.getElementById("reservas_produto_nome");
                            if (label) label.textContent = produtoNome;

                            var linkCompras = document.getElementById("reservas_ir_compras");
                            if (linkCompras) linkCompras.href = "/admin/estoque/compras?status=pendente";

                            var loading = document.getElementById("reservas_loading");
                            var empty = document.getElementById("reservas_empty");
                            var acc = document.getElementById("accordionReservas");
                            if (loading) loading.classList.remove("d-none");
                            if (empty) empty.classList.add("d-none");
                            if (acc) acc.innerHTML = "";

                            var url = "/admin/estoque/reservas?produto_id=" + encodeURIComponent(produtoId);
                            fetch(url, { headers: { "Accept": "application/json" } })
                                .then(function(r){ return r.json(); })
                                .then(function(data){
                                    if (loading) loading.classList.add("d-none");
                                    if (!data || !data.success) {
                                        if (empty) {
                                            empty.classList.remove("d-none");
                                            empty.textContent = (data && data.message) ? data.message : "Erro ao buscar pedidos.";
                                        }
                                        return;
                                    }
                                    var pedidos = data.pedidos || [];
                                    if (!pedidos.length) {
                                        if (empty) empty.classList.remove("d-none");
                                        return;
                                    }
                                    renderAccordionReservas(pedidos);
                                })
                                .catch(function(){
                                    if (loading) loading.classList.add("d-none");
                                    if (empty) {
                                        empty.classList.remove("d-none");
                                        empty.textContent = "Erro ao buscar pedidos.";
                                    }
                                });
                        });
                    }
                </script>';

                echo '</main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function salvar($request) {
        $this->requireWriteAccess(false);
        try {
            $produtoId = (int) $request->getParam('produto_id');
            $quantidade = (int) $request->getParam('quantidade');
            $dataCompra = trim((string) $request->getParam('data_compra', ''));
            $isAlimenticio = $request->getParam('is_alimenticio', '0') ? 1 : 0;
            $dataValidade = trim((string) $request->getParam('data_validade', ''));
            $galpao = trim((string) $request->getParam('galpao', ''));
            $prateleira = trim((string) $request->getParam('prateleira', ''));
            $observacao = trim((string) $request->getParam('observacao', ''));

            // Normalizar campos de localização para evitar duplicidade e repetição (ex.: "Prateleira Prateleira 2")
            if ($galpao !== '') {
                $galpao = preg_replace('/\s+/', ' ', $galpao);
                $galpao = trim($galpao);
            }
            if ($prateleira !== '') {
                $prateleira = preg_replace('/^\s*prateleira\s+/i', '', $prateleira);
                $prateleira = preg_replace('/\s+/', ' ', $prateleira);
                $prateleira = trim($prateleira);
                if ($prateleira !== '' && stripos($prateleira, 'prateleira') !== 0) {
                    $prateleira = 'Prateleira ' . $prateleira;
                }
            }

            if ($produtoId <= 0) {
                $this->setFlash('Selecione um produto válido.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }
            if ($quantidade <= 0) {
                $this->setFlash('Informe uma quantidade válida.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }
            if ($isAlimenticio === 0) {
                $dataValidade = '';
            }

            if ($isAlimenticio === 1 && $dataValidade !== '') {
                $validadeTs = strtotime($dataValidade);
                if ($validadeTs !== false) {
                    $minTs = strtotime('+90 days');
                    if ($minTs !== false && $validadeTs < $minTs) {
                        $this->setFlash('Produto com validade menor que 90 dias. O produto deve ser trocado antes de cadastrar novamente.', 'danger');
                        header('Location: /admin/estoque/entrada?produto_id=' . (int) $produtoId);
                        exit;
                    }
                }
            }

            if (!$this->tableExists('estoque_interno') || !$this->tableExists('estoque_movimentacao')) {
                $this->setFlash('Tabelas de estoque não encontradas no banco. Rode a migration 020_create_estoque_profissional_fix.sql no banco do servidor.', 'danger');
                header('Location: /admin/estoque/entrada');
                exit;
            }

            // Validar produto existente
            $stmtProduto = $this->connection->prepare('SELECT id FROM produtos WHERE id = :id LIMIT 1');
            $stmtProduto->execute([':id' => $produtoId]);
            if (!$stmtProduto->fetchColumn()) {
                $this->setFlash('Produto não encontrado.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            $this->connection->beginTransaction();

            // Se já existe um registro para a mesma localização (produto + galpão + prateleira), não duplicar: somar quantidade.
            $stmtExist = $this->connection->prepare('
                SELECT id, quantidade
                FROM estoque_interno
                WHERE produto_id = :produto_id
                  AND COALESCE(galpao, \'\') = :galpao
                  AND COALESCE(prateleira, \'\') = :prateleira
                ORDER BY id ASC
                LIMIT 1
            ');
            $stmtExist->execute([
                ':produto_id' => $produtoId,
                ':galpao' => $galpao,
                ':prateleira' => $prateleira,
            ]);
            $existRow = $stmtExist->fetch(\PDO::FETCH_ASSOC);

            if ($existRow && isset($existRow['id'])) {
                $estoqueId = (int) $existRow['id'];
                $quantidadeAnterior = (int) ($existRow['quantidade'] ?? 0);
                $quantidadeNova = $quantidadeAnterior + $quantidade;

                $stmtUpd = $this->connection->prepare('
                    UPDATE estoque_interno
                    SET
                        quantidade = :quantidade,
                        data_compra = COALESCE(:data_compra, data_compra),
                        data_validade = COALESCE(:data_validade, data_validade),
                        is_alimenticio = :is_alimenticio,
                        observacao = COALESCE(NULLIF(:observacao, \'\'), observacao)
                    WHERE id = :id
                    LIMIT 1
                ');
                $stmtUpd->execute([
                    ':quantidade' => $quantidadeNova,
                    ':data_compra' => ($dataCompra !== '' ? $dataCompra : null),
                    ':data_validade' => ($dataValidade !== '' ? $dataValidade : null),
                    ':is_alimenticio' => $isAlimenticio,
                    ':observacao' => $observacao,
                    ':id' => $estoqueId,
                ]);

                $stmtMov = $this->connection->prepare('
                    INSERT INTO estoque_movimentacao (
                        produto_id,
                        tipo_movimentacao,
                        quantidade,
                        quantidade_anterior,
                        quantidade_nova,
                        motivo
                    ) VALUES (
                        :produto_id,
                        :tipo_movimentacao,
                        :quantidade,
                        :quantidade_anterior,
                        :quantidade_nova,
                        :motivo
                    )
                ');
                $stmtMov->execute([
                    ':produto_id' => $produtoId,
                    ':tipo_movimentacao' => 'entrada',
                    ':quantidade' => $quantidade,
                    ':quantidade_anterior' => $quantidadeAnterior,
                    ':quantidade_nova' => $quantidadeNova,
                    ':motivo' => 'Entrada no galpão (atualização de quantidade)',
                ]);

                $this->ajustarListaComprasAposEntrada($produtoId, $quantidade);

                $this->syncProdutoEstoqueFromInterno($produtoId);

                $this->connection->commit();

                $this->setFlash('Quantidade atualizada com sucesso (sem duplicar localização).', 'success');
                header('Location: /admin/estoque');
                exit;
            }

            $stmtEstoque = $this->connection->prepare('
                INSERT INTO estoque_interno (
                    produto_id,
                    quantidade,
                    data_compra,
                    data_validade,
                    is_alimenticio,
                    galpao,
                    prateleira,
                    observacao
                ) VALUES (
                    :produto_id,
                    :quantidade,
                    :data_compra,
                    :data_validade,
                    :is_alimenticio,
                    :galpao,
                    :prateleira,
                    :observacao
                )
            ');

            $stmtEstoque->execute([
                ':produto_id' => $produtoId,
                ':quantidade' => $quantidade,
                ':data_compra' => ($dataCompra !== '' ? $dataCompra : null),
                ':data_validade' => ($dataValidade !== '' ? $dataValidade : null),
                ':is_alimenticio' => $isAlimenticio,
                ':galpao' => ($galpao !== '' ? $galpao : null),
                ':prateleira' => ($prateleira !== '' ? $prateleira : null),
                ':observacao' => ($observacao !== '' ? $observacao : null),
            ]);

            // Registrar movimentação (entrada)
            $stmtMov = $this->connection->prepare('
                INSERT INTO estoque_movimentacao (
                    produto_id,
                    tipo_movimentacao,
                    quantidade,
                    quantidade_anterior,
                    quantidade_nova,
                    motivo,
                    usuario_id
                ) VALUES (
                    :produto_id,
                    :tipo_movimentacao,
                    :quantidade,
                    :quantidade_anterior,
                    :quantidade_nova,
                    :motivo,
                    :usuario_id
                )
            ');

            $stmtAtual = $this->connection->prepare('SELECT COALESCE(SUM(quantidade),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
            $stmtAtual->execute([':produto_id' => $produtoId]);
            $atual = (int) ($stmtAtual->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
            $anterior = $atual - $quantidade;

            $usuarioId = null;
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            if (!empty($_SESSION['user_id'])) {
                $usuarioId = (int) $_SESSION['user_id'];
            } elseif (!empty($_SESSION['usuario_id'])) {
                $usuarioId = (int) $_SESSION['usuario_id'];
            }

            $motivo = 'Entrada de estoque (galpão)';
            if ($galpao !== '' || $prateleira !== '') {
                $motivo .= ' - ' . trim($galpao . ' - Prateleira ' . $prateleira);
            }

            $stmtMov->execute([
                ':produto_id' => $produtoId,
                ':tipo_movimentacao' => 'entrada',
                ':quantidade' => $quantidade,
                ':quantidade_anterior' => $anterior,
                ':quantidade_nova' => $atual,
                ':motivo' => $motivo,
                ':usuario_id' => $usuarioId,
            ]);

            $this->ajustarListaComprasAposEntrada($produtoId, $quantidade);

            $this->syncProdutoEstoqueFromInterno($produtoId);

            $this->connection->commit();

            $this->setFlash('Entrada de estoque registrada com sucesso.', 'success');
            header('Location: /admin/estoque');
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log('Erro ao registrar entrada de estoque: ' . $e->getMessage());
            $this->setFlash('Erro ao registrar entrada de estoque: ' . $e->getMessage(), 'danger');
            header('Location: /admin/estoque/entrada');
            exit;
        }
    }

    public function marcarComprado($request) {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->requireWriteAccess(true)) {
            return;
        }

        try {
            if (!$this->tableExists('lista_compras')) {
                echo json_encode(['success' => false, 'message' => 'Tabela lista_compras não encontrada.']);
                return;
            }

            $produtoId = (int) $request->getParam('produto_id', 0);
            $itemId = (int) $request->getParam('item_id', 0);
            $lojaId = (int) $request->getParam('loja_id', 0);
            $semLoja = (string) $request->getParam('sem_loja', '0') === '1';

            if ($itemId <= 0 && $produtoId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
                return;
            }

            $temLojaIdEmLista = $this->columnExists('lista_compras', 'loja_id');

            $sql = "UPDATE lista_compras lc SET lc.status = 'comprado', lc.quantidade_faltante = 0 WHERE lc.status = 'pendente'";
            $params = [];

            if ($itemId > 0) {
                $sql .= ' AND lc.id = :id';
                $params[':id'] = $itemId;
            } else {
                $sql .= ' AND lc.produto_id = :produto_id';
                $params[':produto_id'] = $produtoId;
            }

            if ($temLojaIdEmLista) {
                if ($semLoja) {
                    $sql .= ' AND (lc.loja_id IS NULL OR lc.loja_id = 0)';
                } elseif ($lojaId > 0) {
                    $sql .= ' AND lc.loja_id = :loja_id';
                    $params[':loja_id'] = $lojaId;
                }
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Item(s) marcado(s) como comprado.']);
            return;
        } catch (\Exception $e) {
            error_log('Erro ao marcar comprado (estoque): ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro ao marcar como comprado.']);
            return;
        }
    }

    public function editar($request) {
        try {
            $produtoId = (int) $request->getParam('produto_id');
            if ($produtoId <= 0) {
                $this->setFlash('Produto inválido.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            if (!$this->tableExists('estoque_interno') || !$this->tableExists('estoque_movimentacao')) {
                $this->setFlash('Tabelas de estoque não encontradas no banco. Rode as migrations de estoque no banco do servidor.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            // Produto
            $schema = $this->getProdutosSchema();
            $nameCol = $schema['nameCol'] ?? null;
            $skuCol = $schema['skuCol'] ?? null;
            $priceCol = $schema['priceCol'] ?? null;
            $imgCol = $schema['imgCol'] ?? null;

            $select = ['id'];
            $select[] = ($nameCol ? ($nameCol . ' AS nome') : "CAST(id AS CHAR) AS nome");
            $select[] = ($skuCol ? ($skuCol . ' AS sku') : "'' AS sku");
            $select[] = ($priceCol ? ($priceCol . ' AS preco') : "NULL AS preco");
            $select[] = ($imgCol ? ($imgCol . ' AS imagem_raw') : "'' AS imagem_raw");

            $stmtP = $this->connection->prepare('SELECT ' . implode(', ', $select) . ' FROM produtos WHERE id = :id LIMIT 1');
            $stmtP->execute([':id' => $produtoId]);
            $produto = $stmtP->fetch(\PDO::FETCH_ASSOC);
            if (!$produto) {
                $this->setFlash('Produto não encontrado.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            $imgUrl = null;
            if (!empty($produto['imagem_raw'])) {
                $imgUrl = $this->resolveProdutoImagem(['imagem_raw' => (string) $produto['imagem_raw']], 'imagem_raw');
            }

            // Entradas existentes (localizações)
            $stmtE = $this->connection->prepare('
                SELECT *
                FROM estoque_interno
                WHERE produto_id = :produto_id
                ORDER BY id ASC
            ');
            $stmtE->execute([':produto_id' => $produtoId]);
            $entradas = $stmtE->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Logs
            $stmtL = $this->connection->prepare('
                SELECT *
                FROM estoque_movimentacao
                WHERE produto_id = :produto_id
                ORDER BY data_movimentacao DESC, id DESC
                LIMIT 50
            ');
            $stmtL->execute([':produto_id' => $produtoId]);
            $logs = $stmtL->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Resumo (estoque / reservado / disponível / pendência)
            $totalEstoque = 0;
            try {
                $stmtTot = $this->connection->prepare('SELECT COALESCE(SUM(quantidade),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
                $stmtTot->execute([':produto_id' => $produtoId]);
                $totalEstoque = (int) (($stmtTot->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
            } catch (\Exception $e) {
                $totalEstoque = 0;
            }

            $totalReservadoReal = 0;
            if ($this->tableExists('estoque_reservas')) {
                try {
                    $stmtRes = $this->connection->prepare("SELECT COALESCE(SUM(er.quantidade_reservada),0) as total\n                        FROM estoque_reservas er\n                        LEFT JOIN pedidos p ON p.id = er.pedido_id\n                        WHERE er.produto_id = :produto_id\n                          AND er.status = 'ativa'\n                          AND (p.id IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','concluido','concluído','finalizado','finalizada','entregue','entregue ao cliente','completed','refunded','estornado','estornada'))");
                    $stmtRes->execute([':produto_id' => $produtoId]);
                    $totalReservadoReal = (int) (($stmtRes->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
                } catch (\Exception $e) {
                    $totalReservadoReal = 0;
                }
            }

            $pendenciaCompra = 0;
            if ($this->tableExists('lista_compras')) {
                try {
                    $stmtPend = $this->connection->prepare("SELECT COALESCE(SUM(COALESCE(quantidade_faltante,0)),0) as total FROM lista_compras WHERE produto_id = :produto_id AND status = 'pendente'");
                    $stmtPend->execute([':produto_id' => $produtoId]);
                    $pendenciaCompra = (int) (($stmtPend->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0));
                } catch (\Exception $e) {
                    $pendenciaCompra = 0;
                }
            }

            // IMPORTANTE (UI): "Reservado" aqui representa apenas reserva real (pedidos/estoque_reservas).
            // A pendência de compra é exibida separadamente para não parecer duplicação.
            $totalReservado = $totalReservadoReal;

            $totalDisponivel = $totalEstoque - $totalReservadoReal;
            $statusReposicao = ($totalReservadoReal > $totalEstoque);

            $reservasAtivas = [];
            if ($this->tableExists('estoque_reservas')) {
                try {
                    $stmtRA = $this->connection->prepare(
                        "SELECT er.pedido_id, SUM(COALESCE(er.quantidade_reservada,0)) as quantidade
                         FROM estoque_reservas er
                         LEFT JOIN pedidos p ON p.id = er.pedido_id
                         WHERE er.produto_id = :produto_id AND er.status = 'ativa'
                           AND (p.id IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelada','cancelled','canceled','concluido','concluído','finalizado','finalizada','entregue','entregue ao cliente','completed','refunded','estornado','estornada'))
                         GROUP BY er.pedido_id
                         ORDER BY er.pedido_id DESC"
                    );
                    $stmtRA->execute([':produto_id' => $produtoId]);
                    $reservasAtivas = $stmtRA->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) {
                    $reservasAtivas = [];
                }
            }

        } catch (\Exception $e) {
            $this->setFlash('Erro ao carregar edição de estoque: ' . $e->getMessage(), 'danger');
            header('Location: /admin/estoque');
            exit;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Estoque - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('estoque');

        $produtoNome = (string) ($produto['nome'] ?? '');
        $produtoSku = (string) ($produto['sku'] ?? '');
        $produtoPreco = isset($produto['preco']) ? (float) $produto['preco'] : null;

        $imgTag = $imgUrl
            ? '<img src="' . htmlspecialchars($imgUrl) . '" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:14px; border: 1px solid rgba(148, 163, 184, 0.22); background: rgba(148, 163, 184, 0.06);">'
            : '<div style="width:56px;height:56px;border-radius:14px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>';

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2 mb-0"><i class="fas fa-pen me-2"></i>Editar Estoque</h1>
                        <div class="text-muted">Produto #' . (int) $produtoId . '</div>
                    </div>
                    <div>
                        <a class="btn btn-outline-secondary" href="/admin/estoque"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
                        <a class="btn btn-success ms-2" href="/admin/estoque/entrada?produto_id=' . (int) $produtoId . '"><i class="fas fa-plus me-1"></i>Adicionar localização</a>
                    </div>
                </div>';

        $this->renderFlashIfAny();

        echo '<div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex gap-3 align-items-center">
                        ' . $imgTag . '
                        <div>
                            <div style="font-weight:800;color:#0f172a;">' . htmlspecialchars($produtoNome) . '</div>
                            <div class="text-muted small">SKU: ' . htmlspecialchars($produtoSku !== '' ? $produtoSku : '-') . '</div>
                            <div class="small" style="color:#0b1f3a;font-weight:700;">' . ($produtoPreco !== null ? 'R$ ' . number_format($produtoPreco, 2, ',', '.') : '') . '</div>
                        </div>
                    </div>
                </div>
            </div>';

        echo '<div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Resumo (evitar vender/comprar errado)</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3"><div class="p-3" style="border:1px solid rgba(148,163,184,.22);border-radius:14px;background:#fff;"><div class="text-muted small">Estoque total</div><div style="font-weight:900;font-size:20px;">' . (int) $totalEstoque . '</div></div></div>
                        <div class="col-md-3"><div class="p-3" style="border:1px solid rgba(148,163,184,.22);border-radius:14px;background:#fff;"><div class="text-muted small">Reserva (real)</div><div style="font-weight:900;font-size:20px;">' . (int) $totalReservadoReal . '</div></div></div>
                        <div class="col-md-3"><div class="p-3" style="border:1px solid rgba(148,163,184,.22);border-radius:14px;background:#fff;"><div class="text-muted small">Pendência (compras)</div><div style="font-weight:900;font-size:20px;">' . (int) $pendenciaCompra . '</div></div></div>
                        <div class="col-md-3"><div class="p-3" style="border:1px solid rgba(148,163,184,.22);border-radius:14px;background:#fff;"><div class="text-muted small">Disponível</div><div style="font-weight:900;font-size:20px;color:' . ($totalDisponivel < 0 ? '#b91c1c' : '#0f172a') . ';">' . (int) $totalDisponivel . '</div></div></div>
                    </div>
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="/admin/estoque/compras?produto_id=' . (int) $produtoId . '" target="_blank">Abrir lista de compras</a>
                        <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalReservas" data-produto-id="' . (int) $produtoId . '" data-produto-nome="' . htmlspecialchars($produtoNome) . '">Ver reservas</button>
                    </div>
                    ' . ($statusReposicao ? '<div class="alert alert-warning mt-3 mb-0">Status: <strong>Reposição</strong>. Reservado acima do estoque; o disponível fica negativo até entrada.</div>' : '') . '
                </div>
            </div>';

        echo '<div class="modal fade" id="modalReservas" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reservas / Pedidos relacionados</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-2 text-muted" id="reservas_produto_nome"></div>
                            <div id="reservas_loading" class="text-muted">Carregando...</div>
                            <div id="reservas_empty" class="alert alert-warning d-none">Nenhum pedido encontrado.</div>
                            <div class="accordion" id="accordionReservas"></div>
                        </div>
                        <div class="modal-footer">
                            <a class="btn btn-outline-primary" id="reservas_ir_compras" href="/admin/estoque/compras" target="_blank">Abrir lista de compras</a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>';

        echo '<script>
            function escapeHtml2(str){
                if (str === null || str === undefined) return "";
                return String(str)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/\"/g, "&quot;")
                    .replace(/\'/g, "&#039;");
            }
            function formatMoney2(v){
                if (v === null || v === undefined || v === "") return "-";
                var n = Number(v);
                if (isNaN(n)) return String(v);
                return "$ " + n.toFixed(2);
            }
            function renderAccordionReservas(pedidos){
                var acc = document.getElementById("accordionReservas");
                if (!acc) return;
                acc.innerHTML = "";
                pedidos.forEach(function(p){
                    var pid = p.id || 0;
                    var headId = "resHead_" + pid;
                    var bodyId = "resBody_" + pid;
                    var total = (p.valor_total !== null && p.valor_total !== undefined) ? formatMoney2(p.valor_total) : "-";
                    var status = p.status ? escapeHtml2(p.status) : "";
                    var codigo = p.codigo_pedido ? escapeHtml2(p.codigo_pedido) : "";
                    var cliente = (p.cliente_nome || "") + (p.cliente_email ? (" - " + p.cliente_email) : "");
                    var criado = p.created_at ? escapeHtml2(p.created_at) : "";
                    var pagoEm = p.pago_em ? escapeHtml2(p.pago_em) : "";
                    var qtdReservada = (p.quantidade_reservada !== null && p.quantidade_reservada !== undefined) ? Number(p.quantidade_reservada) : 0;
                    var itensHtml = "";
                    if (Array.isArray(p.itens) && p.itens.length > 0) {
                        itensHtml += "<div class=\"table-responsive\"><table class=\"table table-sm\">";
                        itensHtml += "<thead><tr><th>Produto</th><th style=\"width:90px;\">Qtd</th><th style=\"width:120px;\">Preço</th><th style=\"width:120px;\">Subtotal</th></tr></thead><tbody>";
                        p.itens.forEach(function(it){
                            itensHtml += "<tr>";
                            itensHtml += "<td>" + escapeHtml2(it.nome_produto || it.nome_produto_sku || ("Produto ID: " + (it.produto_id||""))) + "</td>";
                            itensHtml += "<td>" + escapeHtml2(it.quantidade || 0) + "</td>";
                            itensHtml += "<td>" + formatMoney2(it.preco_unitario) + "</td>";
                            itensHtml += "<td>" + formatMoney2(it.subtotal) + "</td>";
                            itensHtml += "</tr>";
                        });
                        itensHtml += "</tbody></table></div>";
                    } else {
                        itensHtml = "<div class=\"text-muted\">Itens do pedido não disponíveis.</div>";
                    }
                    var html = "";
                    html += "<div class=\"accordion-item\">";
                    html += "<h2 class=\"accordion-header\" id=\"" + headId + "\">";
                    html += "<button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#" + bodyId + "\">";
                    html += "Pedido #" + pid + (codigo ? (" (" + codigo + ")") : "") + " - Qtd reservada: " + (isNaN(qtdReservada) ? 0 : qtdReservada) + (status ? (" - " + status) : "") + " - " + total;
                    html += "</button></h2>";
                    html += "<div id=\"" + bodyId + "\" class=\"accordion-collapse collapse\" data-bs-parent=\"#accordionReservas\">";
                    html += "<div class=\"accordion-body\">";
                    html += "<div class=\"mb-2\">";
                    html += "<div><strong>Quantidade reservada:</strong> " + escapeHtml2(isNaN(qtdReservada) ? 0 : qtdReservada) + "</div>";
                    html += "<div><strong>Cliente:</strong> " + escapeHtml2(cliente) + "</div>";
                    html += "<div><strong>Criado em:</strong> " + criado + "</div>";
                    if (pagoEm) html += "<div><strong>Pago em:</strong> " + pagoEm + "</div>";
                    html += "<div class=\"mt-2\"><a class=\"btn btn-sm btn-outline-primary\" href=\"/admin/pedidos/detalhes/" + pid + "\" target=\"_blank\">Abrir pedido</a></div>";
                    html += "</div>";
                    html += itensHtml;
                    html += "</div></div></div>";
                    acc.insertAdjacentHTML("beforeend", html);
                });
            }
            var modalReservas = document.getElementById("modalReservas");
            if (modalReservas) {
                modalReservas.addEventListener("show.bs.modal", function (event) {
                    var button = event.relatedTarget;
                    var produtoId = (button && button.getAttribute) ? (button.getAttribute("data-produto-id") || "") : "";
                    if (!produtoId) {
                        produtoId = "' . (int) $produtoId . '";
                    }
                    var produtoNome = (button && button.getAttribute) ? (button.getAttribute("data-produto-nome") || "") : "";
                    if (!produtoNome) {
                        produtoNome = "' . htmlspecialchars($produtoNome) . '";
                    }
                    var label = document.getElementById("reservas_produto_nome");
                    if (label) label.textContent = produtoNome;
                    var linkCompras = document.getElementById("reservas_ir_compras");
                    if (linkCompras) linkCompras.href = "/admin/estoque/compras?produto_id=" + encodeURIComponent(String(produtoId)) + "&status=pendente";
                    var loading = document.getElementById("reservas_loading");
                    var empty = document.getElementById("reservas_empty");
                    var acc = document.getElementById("accordionReservas");
                    if (acc) acc.innerHTML = "";
                    if (empty) empty.classList.add("d-none");
                    if (loading) loading.style.display = "block";

                    fetch("/admin/estoque/reservas?produto_id=" + encodeURIComponent(String(produtoId)))
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            var pedidos = (data && data.pedidos) ? data.pedidos : [];
                            if (loading) loading.style.display = "none";
                            if (!Array.isArray(pedidos) || pedidos.length === 0) {
                                if (empty) empty.classList.remove("d-none");
                                return;
                            }
                            renderAccordionReservas(pedidos);
                        })
                        .catch(function(){
                            if (loading) loading.style.display = "none";
                            if (empty) empty.classList.remove("d-none");
                        });
                });
            }
        </script>';

        echo '<div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Informações do produto no estoque</h5></div>
                        <div class="card-body">';

        if (empty($entradas)) {
            echo '<p class="text-muted mb-0">Nenhuma entrada encontrada para este produto no estoque interno.</p>';
        } else {
            echo '<form method="POST" action="/admin/estoque/editar/salvar" id="form_editar_estoque">'
                . '<input type="hidden" name="produto_id" value="' . (int) $produtoId . '">'
                . '<div class="table-responsive">'
                . '<table class="table table-hover">'
                . '<thead><tr><th>Localização</th><th>Qtd</th><th>Data compra</th><th>Validade</th><th>Obs.</th><th style="width:120px;">Ações</th></tr></thead><tbody>';

            $qtdMap = [];
            foreach ($entradas as $e) {
                $eid = (int) ($e['id'] ?? 0);
                $loc = trim((string) ($e['galpao'] ?? ''));
                $pr = trim((string) ($e['prateleira'] ?? ''));
                $locFull = $loc;
                if ($loc !== '' && $pr !== '') {
                    $locFull .= ' - ' . $pr;
                } elseif ($pr !== '') {
                    $locFull = $pr;
                }
                $qtd = (int) ($e['quantidade'] ?? 0);
                if ($eid > 0) {
                    $qtdMap[$eid] = $qtd;
                }
                $dc = (string) ($e['data_compra'] ?? '');
                $dv = (string) ($e['data_validade'] ?? '');
                $obs = (string) ($e['observacao'] ?? '');
                $isAli = (int) ($e['is_alimenticio'] ?? 0);

                echo '<tr>'
                    . '<td>'
                    . '<input type="hidden" name="estoque_id[]" value="' . $eid . '">'
                    . '<div class="row g-2">'
                    . '<div class="col-6"><input type="text" class="form-control" name="galpao[]" value="' . htmlspecialchars($loc) . '" placeholder="Galpão"></div>'
                    . '<div class="col-6"><input type="text" class="form-control" name="prateleira[]" value="' . htmlspecialchars($pr) . '" placeholder="Prateleira"></div>'
                    . '</div>'
                    . '</td>'
                    . '<td style="max-width:140px;"><input type="number" class="form-control" name="quantidade[]" min="0" step="1" value="' . $qtd . '" required></td>'
                    . '<td style="max-width:170px;"><input type="date" class="form-control" name="data_compra[]" value="' . htmlspecialchars($dc) . '"></td>'
                    . '<td style="max-width:170px;">'
                    . '<input type="hidden" name="is_alimenticio[]" value="' . $isAli . '">'
                    . '<input type="date" class="form-control" name="data_validade[]" value="' . htmlspecialchars($dv) . '"></td>'
                    . '<td><input type="text" class="form-control" name="observacao[]" value="' . htmlspecialchars($obs) . '"></td>'
                    . '<td>'
                    . '<button type="button" class="btn btn-sm btn-outline-danger" onclick="return excluirEntradaEstoque(' . (int) $produtoId . ', ' . $eid . ');"><i class="fas fa-trash"></i></button>'
                    . '</td>'
                    . '</tr>';
            }

            echo '</tbody></table></div>'
                . '<div class="d-flex gap-2">'
                . '<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar alterações</button>'
                . '</div>'
                . '</form>';

            echo '<form id="form_del_global" method="POST" action="/admin/estoque/editar/excluir" style="display:none;">'
                . '<input type="hidden" name="produto_id" id="del_produto_id" value="' . (int) $produtoId . '">'
                . '<input type="hidden" name="estoque_id" id="del_estoque_id" value="">'
                . '</form>';

            echo '<div class="modal fade" id="modalConfirmEstoque" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirmação</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="confirmEstoqueMessage" style="white-space:pre-wrap;"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-danger" id="confirmEstoqueOk">Confirmar</button>
                            </div>
                        </div>
                    </div>
                </div>';

            echo '<script>
                (function(){
                    var reservadoTotal = ' . (int) $totalReservado . ';
                    var totalAtual = ' . (int) $totalEstoque . ';
                    var qtdMap = ' . json_encode($qtdMap) . ';
                    var pendingAction = null;

                    function showConfirmModal(message, onConfirm) {
                        var modalEl = document.getElementById("modalConfirmEstoque");
                        var msgEl = document.getElementById("confirmEstoqueMessage");
                        var btnOk = document.getElementById("confirmEstoqueOk");
                        if (!modalEl || !msgEl || !btnOk || typeof bootstrap === "undefined") {
                            if (window.confirm(message)) onConfirm();
                            return;
                        }
                        msgEl.textContent = message;
                        pendingAction = onConfirm;
                        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }

                    document.addEventListener("DOMContentLoaded", function(){
                        var btnOk = document.getElementById("confirmEstoqueOk");
                        if (btnOk) {
                            btnOk.addEventListener("click", function(){
                                var fn = pendingAction;
                                pendingAction = null;
                                try {
                                    var modalEl = document.getElementById("modalConfirmEstoque");
                                    if (modalEl && typeof bootstrap !== "undefined") {
                                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                                    }
                                } catch (e) {}
                                if (typeof fn === "function") fn();
                            });
                        }

                        var form = document.getElementById("form_editar_estoque");
                        if (form) {
                            form.addEventListener("submit", function(ev){
                                if (reservadoTotal <= 0) return;
                                var inputs = form.querySelectorAll("input[name=\"quantidade[]\"]");
                                var total = 0;
                                for (var i = 0; i < inputs.length; i++) {
                                    var n = parseInt(inputs[i].value || "0", 10);
                                    if (!isNaN(n)) total += n;
                                }
                                if (total < reservadoTotal) {
                                    ev.preventDefault();
                                    showConfirmModal(
                                        "ATENÇÃO: ao salvar, o estoque total ficará em " + total + ", abaixo do reservado (" + reservadoTotal + ").\n\nDeseja continuar mesmo assim?",
                                        function(){ form.submit(); }
                                    );
                                    return false;
                                }
                            });
                        }
                    });

                    window.excluirEntradaEstoque = function(produtoId, estoqueId) {
                        try {
                            var qtdRemover = 0;
                            if (qtdMap && Object.prototype.hasOwnProperty.call(qtdMap, String(estoqueId))) {
                                qtdRemover = Number(qtdMap[String(estoqueId)] || 0);
                            }
                            var novoTotal = totalAtual - qtdRemover;

                            var f = document.getElementById("form_del_global");
                            var p = document.getElementById("del_produto_id");
                            var e = document.getElementById("del_estoque_id");
                            if (!f || !p || !e) return false;
                            p.value = String(produtoId);
                            e.value = String(estoqueId);

                            if (reservadoTotal > 0 && novoTotal < reservadoTotal) {
                                showConfirmModal(
                                    "ATENÇÃO: esta exclusão deixará o estoque total (" + novoTotal + ") abaixo do reservado (" + reservadoTotal + ").\n\nDeseja continuar mesmo assim?",
                                    function(){ f.submit(); }
                                );
                                return false;
                            }

                            showConfirmModal(
                                "Excluir esta localização do estoque? Esta ação será registrada no log.",
                                function(){ f.submit(); }
                            );
                            return false;
                        } catch (e) {
                            return window.confirm("Excluir esta localização do estoque?");
                        }
                    };
                })();
            </script>';
        }

        echo '        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Logs de alterações</h5></div>
                        <div class="card-body" style="max-height: 560px; overflow:auto;">';

        if (empty($logs)) {
            echo '<p class="text-muted mb-0">Nenhum log encontrado.</p>';
        } else {
            foreach ($logs as $l) {
                $tipo = (string) ($l['tipo_movimentacao'] ?? '');
                $qtd = (string) ($l['quantidade'] ?? '');
                $ant = (string) ($l['quantidade_anterior'] ?? '');
                $nov = (string) ($l['quantidade_nova'] ?? '');
                $motivo = (string) ($l['motivo'] ?? '');
                $data = (string) ($l['data_movimentacao'] ?? '');
                $who = (string) ($l['usuario_login'] ?? ($l['usuario_id'] ?? ''));
                $badge = 'bg-info';
                if ($tipo === 'entrada') $badge = 'bg-success';
                if ($tipo === 'saida') $badge = 'bg-danger';
                if ($tipo === 'ajuste') $badge = 'bg-warning';
                echo '<div class="mb-3">'
                    . '<div class="d-flex justify-content-between">'
                    . '<span class="badge ' . $badge . '">' . htmlspecialchars($tipo) . '</span>'
                    . '<span class="text-muted small">' . ($data !== '' ? date('d/m/Y H:i', strtotime($data)) : '-') . '</span>'
                    . '</div>'
                    . '<div class="small">Qtd: ' . htmlspecialchars($qtd) . ' (de ' . htmlspecialchars($ant) . ' para ' . htmlspecialchars($nov) . ')</div>'
                    . ($who !== '' ? '<div class="text-muted small">Por: ' . htmlspecialchars((string) $who) . '</div>' : '')
                    . ($motivo !== '' ? '<div class="text-muted small">' . htmlspecialchars($motivo) . '</div>' : '')
                    . '</div>';
            }
        }

        echo '        </div>
                    </div>
                </div>
            </div>

            </main>
        </div>
    </div>';

        renderAdminScripts();
        echo '</body></html>';
        exit;
    }

    public function salvarEdicao($request) {
        $this->requireWriteAccess(false);
        try {
            $produtoId = (int) $request->getParam('produto_id');
            if ($produtoId <= 0) {
                $this->setFlash('Produto inválido.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            $normalizeDate = static function ($v): string {
                $s = trim((string) ($v ?? ''));
                if ($s === '') return '';
                // Aceitar DATETIME do banco e manter apenas YYYY-MM-DD
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
                    return substr($s, 0, 10);
                }
                // Aceitar DD/MM/YYYY
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
                    return $m[3] . '-' . $m[2] . '-' . $m[1];
                }
                return $s;
            };

            $ids = $request->getParam('estoque_id', []);
            $qtds = $request->getParam('quantidade', []);
            $dcs = $request->getParam('data_compra', []);
            $dvs = $request->getParam('data_validade', []);
            $obsArr = $request->getParam('observacao', []);
            $galpoes = $request->getParam('galpao', []);
            $prats = $request->getParam('prateleira', []);
            $isAliArr = $request->getParam('is_alimenticio', []);

            if (!is_array($ids) || empty($ids)) {
                $this->setFlash('Nenhuma entrada para atualizar.', 'warning');
                header('Location: /admin/estoque/editar/' . (int) $produtoId);
                exit;
            }

            $this->connection->beginTransaction();

            $stmtGet = $this->connection->prepare('SELECT * FROM estoque_interno WHERE id = :id AND produto_id = :produto_id LIMIT 1');
            $stmtUpd = $this->connection->prepare('
                UPDATE estoque_interno
                SET
                    quantidade = :quantidade,
                    data_compra = :data_compra,
                    data_validade = :data_validade,
                    is_alimenticio = :is_alimenticio,
                    observacao = :observacao,
                    galpao = :galpao,
                    prateleira = :prateleira
                WHERE id = :id AND produto_id = :produto_id
                LIMIT 1
            ');
            $hasUsuarioLogin = $this->columnExists('estoque_movimentacao', 'usuario_login');
            $sqlMov = '
                INSERT INTO estoque_movimentacao (
                    produto_id,
                    tipo_movimentacao,
                    quantidade,
                    quantidade_anterior,
                    quantidade_nova,
                    motivo,
                    usuario_id' . ($hasUsuarioLogin ? ', usuario_login' : '') . '
                ) VALUES (
                    :produto_id,
                    :tipo_movimentacao,
                    :quantidade,
                    :quantidade_anterior,
                    :quantidade_nova,
                    :motivo,
                    :usuario_id' . ($hasUsuarioLogin ? ', :usuario_login' : '') . '
                )
            ';
            $stmtMov = $this->connection->prepare($sqlMov);

            $changedAny = false;
            $saidaTotal = 0;
            $entradaTotal = 0;
            $logged = $this->getLoggedUser();
            $loggedId = $logged ? (int) ($logged['id'] ?? 0) : 0;
            $loggedLogin = $logged ? (string) ($logged['email'] ?? ($logged['nome'] ?? '')) : '';
            for ($i = 0; $i < count($ids); $i++) {
                $estoqueId = (int) ($ids[$i] ?? 0);
                if ($estoqueId <= 0) {
                    continue;
                }
                $stmtGet->execute([':id' => $estoqueId, ':produto_id' => $produtoId]);
                $old = $stmtGet->fetch(\PDO::FETCH_ASSOC);
                if (!$old) {
                    continue;
                }

                $oldQtd = (int) ($old['quantidade'] ?? 0);
                $newQtd = (int) ($qtds[$i] ?? 0);
                if ($newQtd < $oldQtd) {
                    $saidaTotal += ($oldQtd - $newQtd);
                } elseif ($newQtd > $oldQtd) {
                    $entradaTotal += ($newQtd - $oldQtd);
                }
                $newDc = trim((string) ($dcs[$i] ?? ''));
                $newDv = trim((string) ($dvs[$i] ?? ''));
                $newObs = trim((string) ($obsArr[$i] ?? ''));
                $gal = trim((string) ($galpoes[$i] ?? ''));
                $pra = trim((string) ($prats[$i] ?? ''));
                $isAli = (int) ($isAliArr[$i] ?? 0);

                $newDc = $normalizeDate($newDc);
                $newDv = $normalizeDate($newDv);

                // Normalizar prateleira (mesma regra do salvar entrada)
                if ($gal !== '') {
                    $gal = preg_replace('/\s+/', ' ', $gal);
                    $gal = trim($gal);
                }
                if ($pra !== '') {
                    $pra = preg_replace('/^\s*prateleira\s+/i', '', $pra);
                    $pra = preg_replace('/\s+/', ' ', $pra);
                    $pra = trim($pra);
                    if ($pra !== '' && stripos($pra, 'prateleira') !== 0) {
                        $pra = 'Prateleira ' . $pra;
                    }
                }

                // Se o usuário preencheu a validade, considerar como item com controle de validade.
                if ($newDv !== '') {
                    $isAli = 1;
                }

                $oldDc = $normalizeDate((string) ($old['data_compra'] ?? ''));
                $oldDv = $normalizeDate((string) ($old['data_validade'] ?? ''));
                $oldObs = (string) ($old['observacao'] ?? '');
                $oldGal = trim((string) ($old['galpao'] ?? ''));
                $oldPra = trim((string) ($old['prateleira'] ?? ''));
                $oldIsAli = (int) ($old['is_alimenticio'] ?? 0);

                // Impedir conflito: não permitir renomear para localização já existente no mesmo produto
                $stmtDup = $this->connection->prepare('
                    SELECT id
                    FROM estoque_interno
                    WHERE produto_id = :produto_id
                      AND COALESCE(galpao, \'\') = :galpao
                      AND COALESCE(prateleira, \'\') = :prateleira
                      AND id <> :id
                    LIMIT 1
                ');
                $stmtDup->execute([
                    ':produto_id' => $produtoId,
                    ':galpao' => $gal,
                    ':prateleira' => $pra,
                    ':id' => $estoqueId,
                ]);
                if ($stmtDup->fetchColumn()) {
                    $this->connection->rollBack();
                    $this->setFlash('Já existe uma entrada deste produto para esta localização. Ajuste a quantidade no registro existente.', 'danger');
                    header('Location: /admin/estoque/editar/' . (int) $produtoId);
                    exit;
                }

                $locFull = $gal;
                if ($gal !== '' && $pra !== '') {
                    $locFull .= ' - ' . $pra;
                } elseif ($pra !== '') {
                    $locFull = $pra;
                }

                $diffs = [];
                if ($newQtd !== $oldQtd) {
                    $diffs[] = 'Quantidade: ' . $oldQtd . ' -> ' . $newQtd;
                }
                if ($gal !== $oldGal || $pra !== $oldPra) {
                    $from = trim($oldGal . ($oldGal !== '' && $oldPra !== '' ? ' - ' : '') . $oldPra);
                    $to = trim($gal . ($gal !== '' && $pra !== '' ? ' - ' : '') . $pra);
                    $diffs[] = 'Localização: ' . ($from !== '' ? $from : '-') . ' -> ' . ($to !== '' ? $to : '-');
                }
                if ($newDc !== $oldDc) {
                    $diffs[] = 'Data compra: ' . ($oldDc !== '' ? $oldDc : '-') . ' -> ' . ($newDc !== '' ? $newDc : '-');
                }
                if ($newDv !== $oldDv) {
                    $diffs[] = 'Validade: ' . ($oldDv !== '' ? $oldDv : '-') . ' -> ' . ($newDv !== '' ? $newDv : '-');
                }
                if ($isAli !== $oldIsAli) {
                    $diffs[] = 'Controlar validade: ' . ($oldIsAli ? 'Sim' : 'Não') . ' -> ' . ($isAli ? 'Sim' : 'Não');
                }
                if ($newObs !== $oldObs) {
                    $diffs[] = 'Obs.: ' . ($oldObs !== '' ? $oldObs : '-') . ' -> ' . ($newObs !== '' ? $newObs : '-');
                }

                if (empty($diffs)) {
                    continue;
                }

                $stmtUpd->execute([
                    ':quantidade' => $newQtd,
                    ':data_compra' => ($newDc !== '' ? $newDc : null),
                    ':data_validade' => ($newDv !== '' ? $newDv : null),
                    ':is_alimenticio' => $isAli,
                    ':observacao' => ($newObs !== '' ? $newObs : null),
                    ':galpao' => ($gal !== '' ? $gal : null),
                    ':prateleira' => ($pra !== '' ? $pra : null),
                    ':id' => $estoqueId,
                    ':produto_id' => $produtoId,
                ]);

                $motivo = 'Edição manual (' . ($locFull !== '' ? $locFull : 'Sem localização') . '): ' . implode(' | ', $diffs);
                $paramsMov = [
                    ':produto_id' => $produtoId,
                    ':tipo_movimentacao' => 'ajuste',
                    ':quantidade' => ($newQtd - $oldQtd),
                    ':quantidade_anterior' => $oldQtd,
                    ':quantidade_nova' => $newQtd,
                    ':motivo' => $motivo,
                    ':usuario_id' => ($loggedId > 0 ? $loggedId : null),
                ];
                if ($hasUsuarioLogin) {
                    $paramsMov[':usuario_login'] = ($loggedLogin !== '' ? $loggedLogin : null);
                }
                $stmtMov->execute($paramsMov);
                $changedAny = true;
            }

            if ($entradaTotal > 0) {
                // Se houve aumento manual, tratar como entrada e dar baixa automática na lista de compras.
                $this->ajustarListaComprasAposEntrada($produtoId, $entradaTotal);
            }

            if ($saidaTotal > 0) {
                // Se a redução colocou o estoque abaixo do reservado real, garantir pendência suficiente para cobrir o déficit.
                $adicional = $this->garantirSomaZeroAposReducao($produtoId);
                $reservadoAtual = $this->getTotalReservadoProduto($produtoId);
                if ($reservadoAtual > 0) {
                    $msg = 'Atenção: houve redução de estoque e existem reservas ativas. Pendência de compra foi ajustada automaticamente.';
                    if ($adicional > 0) {
                        $msg .= ' (+ ' . (int) $adicional . ' para cobrir déficit vs reservado)';
                    }
                    $this->setFlash($msg, 'warning');
                }
            }

            $this->syncProdutoEstoqueFromInterno($produtoId);

            $this->connection->commit();

            if ($changedAny) {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                if (empty($_SESSION['message'])) {
                    $this->setFlash('Alterações salvas e registradas no log.', 'success');
                }
            } else {
                $this->setFlash('Nenhuma alteração para salvar.', 'info');
            }
            header('Location: /admin/estoque/editar/' . (int) $produtoId);
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log('Erro ao salvar edição de estoque: ' . $e->getMessage());
            $this->setFlash('Erro ao salvar edição de estoque: ' . $e->getMessage(), 'danger');
            header('Location: /admin/estoque');
            exit;
        }
    }

    public function excluirEntrada($request) {
        $this->requireWriteAccess(false);
        try {
            $produtoId = (int) $request->getParam('produto_id');
            $estoqueId = (int) $request->getParam('estoque_id');
            if ($produtoId <= 0 || $estoqueId <= 0) {
                $this->setFlash('Parâmetros inválidos para exclusão.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            $this->connection->beginTransaction();

            $stmtGet = $this->connection->prepare('SELECT * FROM estoque_interno WHERE id = :id AND produto_id = :produto_id LIMIT 1');
            $stmtGet->execute([':id' => $estoqueId, ':produto_id' => $produtoId]);
            $old = $stmtGet->fetch(\PDO::FETCH_ASSOC);
            if (!$old) {
                $this->connection->rollBack();
                $this->setFlash('Entrada não encontrada.', 'danger');
                header('Location: /admin/estoque/editar/' . (int) $produtoId);
                exit;
            }

            $oldQtd = (int) ($old['quantidade'] ?? 0);
            $gal = trim((string) ($old['galpao'] ?? ''));
            $pra = trim((string) ($old['prateleira'] ?? ''));
            $locFull = $gal;
            if ($gal !== '' && $pra !== '') {
                $locFull .= ' - ' . $pra;
            } elseif ($pra !== '') {
                $locFull = $pra;
            }

            $stmtDel = $this->connection->prepare('DELETE FROM estoque_interno WHERE id = :id AND produto_id = :produto_id LIMIT 1');
            $stmtDel->execute([':id' => $estoqueId, ':produto_id' => $produtoId]);

            $logged = $this->getLoggedUser();
            $loggedId = $logged ? (int) ($logged['id'] ?? 0) : 0;
            $loggedLogin = $logged ? (string) ($logged['email'] ?? ($logged['nome'] ?? '')) : '';

            $hasUsuarioLogin = $this->columnExists('estoque_movimentacao', 'usuario_login');
            $sqlMov = '
                INSERT INTO estoque_movimentacao (
                    produto_id,
                    tipo_movimentacao,
                    quantidade,
                    quantidade_anterior,
                    quantidade_nova,
                    motivo,
                    usuario_id' . ($hasUsuarioLogin ? ', usuario_login' : '') . '
                ) VALUES (
                    :produto_id,
                    :tipo_movimentacao,
                    :quantidade,
                    :quantidade_anterior,
                    :quantidade_nova,
                    :motivo,
                    :usuario_id' . ($hasUsuarioLogin ? ', :usuario_login' : '') . '
                )
            ';
            $stmtMov = $this->connection->prepare($sqlMov);
            $paramsMov = [
                ':produto_id' => $produtoId,
                ':tipo_movimentacao' => 'ajuste',
                ':quantidade' => (0 - $oldQtd),
                ':quantidade_anterior' => $oldQtd,
                ':quantidade_nova' => 0,
                ':motivo' => 'Exclusão da localização (' . ($locFull !== '' ? $locFull : 'Sem localização') . ') do estoque interno.',
                ':usuario_id' => ($loggedId > 0 ? $loggedId : null),
            ];
            if ($hasUsuarioLogin) {
                $paramsMov[':usuario_login'] = ($loggedLogin !== '' ? $loggedLogin : null);
            }
            $stmtMov->execute($paramsMov);

            $adicional = $this->garantirSomaZeroAposReducao($produtoId);
            $reservadoAtual = $this->getTotalReservadoProduto($produtoId);
            if ($reservadoAtual > 0) {
                $msg = 'Atenção: você removeu estoque e existem reservas ativas. Pendência de compra foi ajustada automaticamente.';
                if ($adicional > 0) {
                    $msg .= ' (+ ' . (int) $adicional . ' para cobrir déficit vs reservado)';
                }
                $this->setFlash($msg, 'warning');
            }

            $this->syncProdutoEstoqueFromInterno($produtoId);

            $this->connection->commit();

            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            if (empty($_SESSION['message'])) {
                $this->setFlash('Localização excluída e registrada no log.', 'success');
            }
            header('Location: /admin/estoque/editar/' . (int) $produtoId);
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log('Erro ao excluir entrada de estoque: ' . $e->getMessage());
            $this->setFlash('Erro ao excluir entrada de estoque: ' . $e->getMessage(), 'danger');
            header('Location: /admin/estoque');
            exit;
        }
    }
}
