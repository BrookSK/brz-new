<?php
namespace App\Controllers;

use App\Services\AuthService;

class AdminPedidosConferenciaController extends Controller {
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $this->connection = \Config\Database::getConnection();
    }

    private function renderPageStart(string $activeMenuKey = 'pedidos-conferencia'): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos para Conferência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        renderAdminSidebar($activeMenuKey);

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-clipboard-check me-2"></i>Pedidos para conferência</h1>
            </div>';

        $this->renderFlashIfAny();
    }

    private function renderPageEnd(): void {
        renderAdminScripts();
        echo '<script>
            (function(){
                function syncRow(row){
                    if(!row) return;
                    var sel = row.querySelector('select[name="tipo_compra"]');
                    var file = row.querySelector('input[name="comprovante_compra"]');
                    var box = row.querySelector('[data-comprovante-box="1"]');
                    if(!sel || !file) return;
                    var isOnline = (String(sel.value||'').toLowerCase() === 'online');
                    if(box){ box.style.display = isOnline ? 'block' : 'none'; }
                    file.required = isOnline;
                }
                document.querySelectorAll('tr[data-pedido-row="1"]').forEach(function(tr){
                    var sel = tr.querySelector('select[name="tipo_compra"]');
                    if(sel){
                        sel.addEventListener('change', function(){ syncRow(tr); });
                        syncRow(tr);
                    }
                });
            })();
        </script></body></html>';
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
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
            . '</div>';
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

    private function tableExists(string $table): bool {
        try {
            $stmtT = $this->connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmtT->execute([$table]);
            return (int) ($stmtT->fetchColumn() ?: 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getPedidoItensTable(): ?string {
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

    public function index($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $colsPedidos = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }

        if (!in_array('status_conferencia', $colsPedidos, true)) {
            $_SESSION['message'] = 'Sua base ainda não possui o campo status_conferencia. Rode a migration 085_add_tipo_compra_e_conferencia.sql.';
            $_SESSION['message_type'] = 'warning';
        }

        $temOrigem = in_array('origem_pedido', $colsPedidos, true);
        $temCodigo = in_array('codigo_pedido', $colsPedidos, true) ? 'codigo_pedido' : (in_array('numero_pedido', $colsPedidos, true) ? 'numero_pedido' : '');
        $temMoeda = in_array('moeda', $colsPedidos, true);
        $temTotal = in_array('total', $colsPedidos, true) ? 'total' : (in_array('valor_total', $colsPedidos, true) ? 'valor_total' : '');
        $temCreated = in_array('created_at', $colsPedidos, true) ? 'created_at' : '';
        $temTipoCompra = in_array('tipo_compra', $colsPedidos, true);

        $select = ['p.id'];
        if ($temCodigo !== '') $select[] = 'p.' . $temCodigo . ' AS codigo_pedido';
        if ($temOrigem) $select[] = 'p.origem_pedido';
        if ($temMoeda) $select[] = 'p.moeda';
        if ($temTotal !== '') $select[] = 'p.' . $temTotal . ' AS total';
        if ($temCreated !== '') $select[] = 'p.' . $temCreated . ' AS created_at';
        if ($temTipoCompra) $select[] = 'p.tipo_compra';
        $select[] = 'p.status';
        $select[] = 'p.status_conferencia';

        $where = ["p.status_conferencia = 'pendente'"];
        if ($temOrigem) {
            $where[] = "p.origem_pedido IN ('assessoria','redirecionamento')";
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p';
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.id DESC LIMIT 500';

        try {
            $stmt = $this->connection->query($sql);
            $pedidos = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            $pedidos = [];
        }

        $itensPorPedido = [];
        try {
            $ids = array_values(array_filter(array_map(function ($r) {
                return (int) ($r['id'] ?? 0);
            }, $pedidos)));
            if (!empty($ids)) {
                $itensTable = $this->getPedidoItensTable();
                if ($itensTable) {
                    $colsItens = [];
                    try {
                        $stmtColsI = $this->connection->query('DESCRIBE ' . $itensTable);
                        $colsItens = $stmtColsI ? ($stmtColsI->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsItens = [];
                    }

                    $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
                    $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
                    $colPedido = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : '';

                    if ($colProduto !== '' && $colQtd !== '' && $colPedido !== '') {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $sqlItens = 'SELECT i.' . $colPedido . ' AS pedido_id, i.' . $colProduto . ' AS produto_id, i.' . $colQtd . ' AS quantidade'
                            . ', COALESCE(p.nome, p.name, \'\') AS produto_nome'
                            . ' FROM ' . $itensTable . ' i'
                            . ' LEFT JOIN produtos p ON p.id = i.' . $colProduto
                            . ' WHERE i.' . $colPedido . ' IN (' . $placeholders . ')';
                        $stItensAll = $this->connection->prepare($sqlItens);
                        $stItensAll->execute($ids);
                        $rows = $stItensAll->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        foreach ($rows as $r) {
                            $pid = (int) ($r['pedido_id'] ?? 0);
                            if ($pid <= 0) {
                                continue;
                            }
                            if (!isset($itensPorPedido[$pid])) {
                                $itensPorPedido[$pid] = [];
                            }
                            $itensPorPedido[$pid][] = $r;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $itensPorPedido = [];
        }

        $this->renderPageStart('pedidos-conferencia');

        echo '<div class="card">
            <div class="card-body">';

        if (empty($pedidos)) {
            echo '<div class="text-muted">Nenhum pedido pendente de conferência.</div>';
        } else {
            echo '<div class="table-responsive">'
                . '<table class="table table-sm align-middle">'
                . '<thead><tr>'
                . '<th>ID</th>'
                . '<th>Origem</th>'
                . '<th>Moeda</th>'
                . '<th>Total</th>'
                . '<th>Criado em</th>'
                . '<th>Tipo compra</th>'
                . '<th style="width: 320px;">Ações</th>'
                . '</tr></thead><tbody>';

            foreach ($pedidos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $origem = (string) ($p['origem_pedido'] ?? '');
                $moeda = (string) ($p['moeda'] ?? '');
                $total = isset($p['total']) ? (float) $p['total'] : 0.0;
                $createdAt = (string) ($p['created_at'] ?? '');
                $tipoCompraAtual = strtolower(trim((string) ($p['tipo_compra'] ?? '')));

                $detId = 'det-pedido-' . $pid;

                echo '<tr data-pedido-row="1">'
                    . '<td><a href="/admin/pedidos/detalhes/' . $pid . '" target="_blank">#' . $pid . '</a></td>'
                    . '<td>' . htmlspecialchars($origem !== '' ? $origem : '-') . '</td>'
                    . '<td>' . htmlspecialchars($moeda !== '' ? $moeda : '-') . '</td>'
                    . '<td>' . htmlspecialchars(number_format($total, 2, ',', '.')) . '</td>'
                    . '<td>' . htmlspecialchars($createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '-') . '</td>'
                    . '<td>'
                    . '  <form class="d-flex gap-2" method="POST" action="/admin/pedidos/conferencia/confirmar/' . $pid . '" enctype="multipart/form-data">'
                    . '    <select class="form-select form-select-sm" name="tipo_compra" required>'
                    . '      <option value=""' . ($tipoCompraAtual === '' ? ' selected' : '') . '>Selecione...</option>'
                    . '      <option value="online"' . ($tipoCompraAtual === 'online' ? ' selected' : '') . '>Online</option>'
                    . '      <option value="offline"' . ($tipoCompraAtual === 'offline' ? ' selected' : '') . '>Offline</option>'
                    . '    </select>'
                    . '    <div data-comprovante-box="1" style="display:none; min-width: 200px;">'
                    . '      <input class="form-control form-control-sm" type="file" name="comprovante_compra" accept="image/*,application/pdf">'
                    . '      <div class="form-text">Obrigatório para compra online.</div>'
                    . '    </div>'
                    . '</td>'
                    . '<td>'
                    . '    <button class="btn btn-outline-info btn-sm me-1" type="button" data-bs-toggle="collapse" data-bs-target="#' . $detId . '" aria-expanded="false" aria-controls="' . $detId . '"><i class="fas fa-eye me-1"></i>Detalhes</button>'
                    . '    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Confirmar</button>'
                    . '  </form>'
                    . '  <form class="d-inline" method="POST" action="/admin/pedidos/conferencia/cancelar/' . $pid . '" onsubmit="return confirm(\'Cancelar este pedido?\');">'
                    . '    <button type="submit" class="btn btn-outline-danger btn-sm mt-1"><i class="fas fa-times me-1"></i>Cancelar pedido</button>'
                    . '  </form>'
                    . '</td>'
                    . '</tr>';

                echo '<tr class="collapse" id="' . $detId . '">'
                    . '<td colspan="7" class="bg-light">'
                    . '<div class="small">'
                    . '<div class="fw-semibold mb-2">Detalhes do pedido</div>'
                    . '<div style="border: 1px solid rgba(148, 163, 184, 0.35); border-radius: 12px; overflow: hidden; background: #fff;">'
                    . '<iframe src="/admin/pedidos/detalhes/' . $pid . '?embed=1" style="width: 100%; height: 680px; border: 0;" loading="lazy"></iframe>'
                    . '</div>'
                    . '</div>'
                    . '</td>'
                    . '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div></div>';

        $this->renderPageEnd();
        exit;
    }

    public function confirmar($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $id = (int) $request->getParam('id', 0);
        $tipoCompra = strtolower(trim((string) $request->getParam('tipo_compra', '')));

        if ($id <= 0) {
            $_SESSION['message'] = 'Pedido inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }

        if (!in_array($tipoCompra, ['online', 'offline'], true)) {
            $_SESSION['message'] = 'Selecione o tipo de compra (online/offline).';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }

        $usuarioId = 0;
        try {
            $auth = new AuthService();
            $u = $auth->getUsuarioLogado();
            $usuarioId = (int) ($u['id'] ?? 0);
        } catch (\Exception $e) {
            $usuarioId = 0;
        }

        try {
            $this->connection->beginTransaction();

            // atualizar pedido
            $colsPedidos = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $set = [];
            $params = [':id' => $id];

            if (in_array('tipo_compra', $colsPedidos, true)) {
                $set[] = 'tipo_compra = :tipo_compra';
                $params[':tipo_compra'] = $tipoCompra;
            }
            if (in_array('status_conferencia', $colsPedidos, true)) {
                $set[] = 'status_conferencia = :status_conferencia';
                $params[':status_conferencia'] = 'confirmado';
            }

            if (!empty($set)) {
                $st = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                $st->execute($params);
            }

            // Compra online: exige comprovante, conclui o pedido e gera comissão do processador
            if ($tipoCompra === 'online') {
                if (!isset($_FILES['comprovante_compra']) || !is_array($_FILES['comprovante_compra'])) {
                    throw new \Exception('Comprovante obrigatório para compra online');
                }
                $f = $_FILES['comprovante_compra'];
                $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($err !== UPLOAD_ERR_OK) {
                    throw new \Exception('Falha no upload do comprovante');
                }
                $tmp = (string) ($f['tmp_name'] ?? '');
                $origName = (string) ($f['name'] ?? '');
                $mime = (string) ($f['type'] ?? '');
                if ($tmp === '' || !is_uploaded_file($tmp)) {
                    throw new \Exception('Arquivo inválido');
                }

                $ext = '';
                if (strpos($origName, '.') !== false) {
                    $parts = explode('.', $origName);
                    $ext = strtolower(trim((string) end($parts)));
                    if ($ext !== '') {
                        $ext = '.' . preg_replace('/[^a-z0-9]/', '', $ext);
                    }
                }
                if ($ext === '') {
                    $ext = '.bin';
                }

                $baseDir = realpath(__DIR__ . '/../../public');
                if (!$baseDir) {
                    $baseDir = __DIR__ . '/../../public';
                }
                $targetDir = rtrim($baseDir, '/\\') . '/uploads/comprovantes_compra';
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }

                $fname = 'pedido_' . (int) $id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . $ext;
                $absPath = rtrim($targetDir, '/\\') . '/' . $fname;
                $relPath = '/uploads/comprovantes_compra/' . $fname;

                if (!move_uploaded_file($tmp, $absPath)) {
                    throw new \Exception('Não foi possível salvar o arquivo');
                }

                if ($this->tableExists('pedidos_compra_documentos')) {
                    $colsDocs = [];
                    try {
                        $stmtColsD = $this->connection->query('DESCRIBE pedidos_compra_documentos');
                        $colsDocs = $stmtColsD ? ($stmtColsD->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsDocs = [];
                    }

                    $insertCols = ['pedido_id', 'tipo_compra', 'status', 'arquivo_path', 'mime', 'uploaded_at'];
                    $insertVals = [':pedido_id', ':tipo_compra', ':status', ':path', ':mime', 'NOW()'];
                    $p = [':pedido_id' => $id, ':tipo_compra' => 'online', ':status' => 'ok', ':path' => $relPath, ':mime' => $mime];
                    if ($usuarioId > 0 && in_array('usuario_id', $colsDocs, true)) {
                        $insertCols[] = 'usuario_id';
                        $insertVals[] = ':usuario_id';
                        $p[':usuario_id'] = $usuarioId;
                    }
                    $sqlInsDoc = 'INSERT INTO pedidos_compra_documentos (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
                    $st = $this->connection->prepare($sqlInsDoc);
                    $st->execute($p);
                }

                // concluir pedido e registrar finalizador (quando colunas existirem)
                $colsPedidos2 = $colsPedidos;
                $set2 = [];
                $p2 = [':id' => $id];
                if (in_array('status', $colsPedidos2, true)) {
                    $set2[] = 'status = :st';
                    $p2[':st'] = 'produto_consolidado';
                }
                if ($usuarioId > 0 && in_array('compra_finalizada_por', $colsPedidos2, true)) {
                    $set2[] = 'compra_finalizada_por = :cfp';
                    $p2[':cfp'] = $usuarioId;
                }
                if (in_array('compra_finalizada_em', $colsPedidos2, true)) {
                    $set2[] = 'compra_finalizada_em = NOW()';
                }
                if (!empty($set2)) {
                    $st2 = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $set2) . ' WHERE id = :id');
                    $st2->execute($p2);
                }

                // comissão de processamento
                if ($usuarioId > 0 && $this->tableExists('comissoes_processamento')) {
                    $moeda = 'BRL';
                    $total = 0.0;
                    $impostos = 0.0;
                    try {
                        $stP = $this->connection->prepare('SELECT COALESCE(moeda, \'BRL\') AS moeda, COALESCE(total, COALESCE(valor_total,0)) AS total, COALESCE(impostos, COALESCE(valor_impostos,0)) AS impostos FROM pedidos WHERE id = ? LIMIT 1');
                        $stP->execute([$id]);
                        $row = $stP->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $moeda = strtoupper(trim((string) ($row['moeda'] ?? 'BRL')));
                        if ($moeda === '') $moeda = 'BRL';
                        $total = (float) ($row['total'] ?? 0);
                        $impostos = (float) ($row['impostos'] ?? 0);
                    } catch (\Exception $e) {
                        $moeda = 'BRL';
                        $total = 0.0;
                        $impostos = 0.0;
                    }

                    $custo = 0.0;
                    try {
                        $itensTable2 = $this->getPedidoItensTable();
                        if ($itensTable2 && $this->tableExists('produtos')) {
                            $colsItens2 = [];
                            try {
                                $stmtColsI2 = $this->connection->query('DESCRIBE ' . $itensTable2);
                                $colsItens2 = $stmtColsI2 ? ($stmtColsI2->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                            } catch (\Exception $e) {
                                $colsItens2 = [];
                            }
                            $colPedidoId = in_array('pedido_id', $colsItens2, true) ? 'pedido_id' : '';
                            $colProduto2 = in_array('produto_id', $colsItens2, true) ? 'produto_id' : '';
                            $colQtd2 = in_array('quantidade', $colsItens2, true) ? 'quantidade' : (in_array('qty', $colsItens2, true) ? 'qty' : '');
                            if ($colPedidoId !== '' && $colProduto2 !== '' && $colQtd2 !== '') {
                                $colsProd = [];
                                try {
                                    $stmtColsPr = $this->connection->query('DESCRIBE produtos');
                                    $colsProd = $stmtColsPr ? ($stmtColsPr->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                                } catch (\Exception $e) {
                                    $colsProd = [];
                                }
                                $colCusto = in_array('preco_custo', $colsProd, true) ? 'preco_custo' : (in_array('cost_price', $colsProd, true) ? 'cost_price' : (in_array('custo', $colsProd, true) ? 'custo' : ''));
                                if ($colCusto !== '') {
                                    $sqlC = 'SELECT SUM(COALESCE(pr.' . $colCusto . ',0) * COALESCE(pi.' . $colQtd2 . ',0)) AS custo_total FROM ' . $itensTable2 . ' pi INNER JOIN produtos pr ON pr.id = pi.' . $colProduto2 . ' WHERE pi.' . $colPedidoId . ' = ?';
                                    $stC = $this->connection->prepare($sqlC);
                                    $stC->execute([$id]);
                                    $custo = (float) ($stC->fetchColumn() ?: 0);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $custo = 0.0;
                    }

                    $baseLiquida = max(0.0, $total - $custo - $impostos);

                    // percentual via config (fallback 0)
                    $percent = 0.0;
                    try {
                        foreach (['configuracoes_sistema', 'configuracoes', 'settings', 'config'] as $tbl) {
                            if (!$this->tableExists($tbl)) continue;
                            $colsCfg = [];
                            try {
                                $stCols = $this->connection->query('DESCRIBE ' . $tbl);
                                $colsCfg = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                            } catch (\Exception $e) {
                                $colsCfg = [];
                            }
                            $colChave = in_array('chave', $colsCfg, true) ? 'chave' : (in_array('key', $colsCfg, true) ? 'key' : (in_array('nome', $colsCfg, true) ? 'nome' : ''));
                            $colValor = in_array('valor', $colsCfg, true) ? 'valor' : (in_array('value', $colsCfg, true) ? 'value' : (in_array('conteudo', $colsCfg, true) ? 'conteudo' : ''));
                            if ($colChave === '' || $colValor === '') continue;
                            $stV = $this->connection->prepare('SELECT ' . $colValor . ' FROM ' . $tbl . ' WHERE ' . $colChave . ' IN (\'comissao_processamento_percent\',\'processamento_percent\') ORDER BY 1 DESC LIMIT 1');
                            $stV->execute();
                            $raw = (string) ($stV->fetchColumn() ?: '');
                            if ($raw !== '') {
                                $percent = (float) str_replace(',', '.', $raw);
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        $percent = 0.0;
                    }

                    $valorComissao = max(0.0, $baseLiquida) * (max(0.0, $percent) / 100.0);

                    try {
                        $stInsC = $this->connection->prepare('INSERT INTO comissoes_processamento (pedido_id, usuario_id, moeda, percentual, base_liquida, valor_comissao) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE moeda=VALUES(moeda), percentual=VALUES(percentual), base_liquida=VALUES(base_liquida), valor_comissao=VALUES(valor_comissao)');
                        $stInsC->execute([(int) $id, (int) $usuarioId, (string) $moeda, (float) $percent, (float) $baseLiquida, (float) $valorComissao]);
                    } catch (\Exception $e) {
                    }
                }

                $this->connection->commit();

                $_SESSION['message'] = 'Pedido confirmado (online), comprovante anexado e comissão registrada.';
                $_SESSION['message_type'] = 'success';
                header('Location: /admin/pedidos/conferencia');
                exit;
            }

            // inserir itens do pedido na lista_compras (idempotente)
            $itensTable = $this->getPedidoItensTable();
            if ($itensTable) {
                $colsItens = [];
                try {
                    $stmtColsI = $this->connection->query('DESCRIBE ' . $itensTable);
                    $colsItens = $stmtColsI ? ($stmtColsI->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsItens = [];
                }

                $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
                $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');

                if ($colProduto !== '' && $colQtd !== '') {
                    $stItens = $this->connection->prepare('SELECT ' . $colProduto . ' AS produto_id, ' . $colQtd . ' AS quantidade FROM ' . $itensTable . ' WHERE pedido_id = ?');
                    $stItens->execute([$id]);
                    $itens = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                    // cols lista_compras
                    $colsLista = [];
                    try {
                        $stmtColsL = $this->connection->query('DESCRIBE lista_compras');
                        $colsLista = $stmtColsL ? ($stmtColsL->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsLista = [];
                    }

                    $itensConsolidados = [];
                    foreach ($itens as $it) {
                        $produtoId = (int) ($it['produto_id'] ?? 0);
                        $qtd = (int) ($it['quantidade'] ?? 0);
                        if ($produtoId <= 0 || $qtd <= 0) {
                            continue;
                        }
                        if (!isset($itensConsolidados[$produtoId])) {
                            $itensConsolidados[$produtoId] = 0;
                        }
                        $itensConsolidados[$produtoId] += $qtd;
                    }

                    $temQtdFaltante = in_array('quantidade_faltante', $colsLista, true);
                    $temQtdNec = in_array('quantidade_necessaria', $colsLista, true);
                    $colQtdLista = $temQtdFaltante ? 'quantidade_faltante' : ($temQtdNec ? 'quantidade_necessaria' : '');

                    foreach ($itensConsolidados as $produtoId => $qtd) {
                        $produtoId = (int) $produtoId;
                        $qtd = (int) $qtd;
                        if ($produtoId <= 0 || $qtd <= 0) {
                            continue;
                        }

                        // se já existe item pendente desse pedido/produto/tipo_compra, somar quantidade ao invés de duplicar
                        try {
                            if (in_array('pedido_id', $colsLista, true) && in_array('produto_id', $colsLista, true) && $colQtdLista !== '') {
                                $sqlFind = 'SELECT id, ' . $colQtdLista . ' AS qtd FROM lista_compras WHERE pedido_id = ? AND produto_id = ? AND status = \'pendente\'';
                                if (in_array('tipo_compra', $colsLista, true)) {
                                    $sqlFind .= ' AND tipo_compra = ?';
                                }
                                $sqlFind .= ' ORDER BY id DESC LIMIT 1';
                                $stFind = $this->connection->prepare($sqlFind);
                                if (in_array('tipo_compra', $colsLista, true)) {
                                    $stFind->execute([$id, $produtoId, $tipoCompra]);
                                } else {
                                    $stFind->execute([$id, $produtoId]);
                                }
                                $ex = $stFind->fetch(\PDO::FETCH_ASSOC);
                                if (is_array($ex) && !empty($ex['id'])) {
                                    $newQtd = ((int) ($ex['qtd'] ?? 0)) + $qtd;
                                    $stUpd = $this->connection->prepare('UPDATE lista_compras SET ' . $colQtdLista . ' = ? WHERE id = ?');
                                    $stUpd->execute([$newQtd, (int) $ex['id']]);
                                    continue;
                                }
                            }
                        } catch (\Exception $e) {
                        }

                        $colsIns = [];
                        $valsIns = [];
                        $pIns = [];

                        if (in_array('produto_id', $colsLista, true)) {
                            $colsIns[] = 'produto_id';
                            $valsIns[] = ':produto_id';
                            $pIns[':produto_id'] = $produtoId;
                        }
                        if (in_array('pedido_id', $colsLista, true)) {
                            $colsIns[] = 'pedido_id';
                            $valsIns[] = ':pedido_id';
                            $pIns[':pedido_id'] = $id;
                        }
                        if (in_array('quantidade_faltante', $colsLista, true)) {
                            $colsIns[] = 'quantidade_faltante';
                            $valsIns[] = ':q';
                            $pIns[':q'] = $qtd;
                        } elseif (in_array('quantidade_necessaria', $colsLista, true)) {
                            $colsIns[] = 'quantidade_necessaria';
                            $valsIns[] = ':q';
                            $pIns[':q'] = $qtd;
                        }
                        if (in_array('status', $colsLista, true)) {
                            $colsIns[] = 'status';
                            $valsIns[] = "'pendente'";
                        }
                        if (in_array('tipo_compra', $colsLista, true)) {
                            $colsIns[] = 'tipo_compra';
                            $valsIns[] = ':tipo_compra';
                            $pIns[':tipo_compra'] = $tipoCompra;
                        }

                        if (empty($colsIns)) {
                            continue;
                        }

                        $sqlIns = 'INSERT INTO lista_compras (' . implode(',', $colsIns) . ') VALUES (' . implode(',', $valsIns) . ')';
                        $stIns = $this->connection->prepare($sqlIns);
                        $stIns->execute($pIns);
                    }
                }
            }

            $this->connection->commit();

            $_SESSION['message'] = 'Pedido confirmado e enviado para a lista de compras.';
            $_SESSION['message_type'] = 'success';
            header('Location: /admin/pedidos/conferencia');
            exit;
        } catch (\Exception $e) {
            try {
                $this->connection->rollBack();
            } catch (\Exception $e2) {
            }
            $_SESSION['message'] = 'Erro ao confirmar pedido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }
    }

    public function cancelar($request) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['message'] = 'Pedido inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/pedidos/conferencia');
            exit;
        }

        try {
            $colsPedidos = [];
            try {
                $stmtCols = $this->connection->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $set = [];
            $params = [':id' => $id];

            if (in_array('status_conferencia', $colsPedidos, true)) {
                $set[] = 'status_conferencia = :status_conferencia';
                $params[':status_conferencia'] = 'cancelado';
            }
            if (in_array('status', $colsPedidos, true)) {
                $set[] = 'status = :status';
                $params[':status'] = 'cancelado';
            }

            if (!empty($set)) {
                $st = $this->connection->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                $st->execute($params);
            }

            $_SESSION['message'] = 'Pedido cancelado.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao cancelar pedido.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/pedidos/conferencia');
        exit;
    }
}
