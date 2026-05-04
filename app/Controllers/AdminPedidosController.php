<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\PedidoEcommerce;
use App\Services\PdfPedidoService;
use App\Services\PaymentService;
use App\Services\AuthService;
use App\Services\SupportTicketNotificationService;
use App\Services\CpfValidator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminPedidosController extends Controller {

    public function atualizarCliente(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $id = $id ?? $request->getParam('id');
        $pedidoId = (int) $id;
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => 'Pedido inválido'], 400);
            return;
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $cols = $this->getTableColumnsPdo($pdo, 'pedidos');

            $usuarioLogado = $auth->getUsuarioLogado();
            $audUsuarioId = (int) ($usuarioLogado['id'] ?? 0);
            $oldRow = [];
            try {
                $stOld = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
                $stOld->execute([$pedidoId]);
                $oldRow = $stOld->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $oldRow = [];
            }

            $pickCol = function(array $candidates) use ($cols): string {
                foreach ($candidates as $c) {
                    if (is_array($cols) && in_array($c, $cols, true)) {
                        return $c;
                    }
                }
                return '';
            };

            $colNome = $pickCol(['cliente_nome', 'customer_name', 'nome', 'name']);
            $colEmail = $pickCol(['cliente_email', 'customer_email', 'email']);
            $colTelefone = $pickCol(['cliente_telefone', 'customer_phone', 'telefone', 'phone', 'celular']);
            $colDoc = $pickCol(['cliente_documento', 'cliente_cpf_cnpj', 'cpf_cnpj', 'documento', 'customer_document', 'cpf']);

            $colPais = $pickCol(['pais_entrega', 'country_entrega', 'pais', 'country', 'customer_country']);
            $colCep = $pickCol(['cep', 'zipcode', 'zip_code', 'customer_zipcode']);
            $colEndereco = $pickCol(['endereco', 'logradouro', 'address', 'customer_address']);
            $colNumero = $pickCol(['numero', 'address_number', 'customer_address_number']);
            $colComplemento = $pickCol(['complemento', 'address_complement', 'customer_address_complement']);
            $colBairro = $pickCol(['bairro', 'province', 'district', 'customer_province']);
            $colCidade = $pickCol(['cidade', 'city', 'customer_city']);
            $colEstado = $pickCol(['estado', 'state', 'customer_state']);

            $set = [];
            $params = [];

            $addSet = function(string $col, $val) use (&$set, &$params): void {
                if ($col === '') return;
                $set[] = $col . ' = ?';
                $params[] = $val;
            };

            $addSet($colNome, trim((string) $request->getParam('nome')));
            $addSet($colEmail, trim((string) $request->getParam('email')));
            $addSet($colTelefone, trim((string) $request->getParam('telefone')));
            $addSet($colDoc, trim((string) $request->getParam('documento')));

            $addSet($colPais, trim((string) $request->getParam('pais')));
            $addSet($colCep, trim((string) $request->getParam('cep')));
            $addSet($colEndereco, trim((string) $request->getParam('endereco')));
            $addSet($colNumero, trim((string) $request->getParam('numero')));
            $addSet($colComplemento, trim((string) $request->getParam('complemento')));
            $addSet($colBairro, trim((string) $request->getParam('bairro')));
            $addSet($colCidade, trim((string) $request->getParam('cidade')));
            $addSet($colEstado, trim((string) $request->getParam('estado')));

            // Destinatário (entrega para outra pessoa)
            $colDestNome = $pickCol(['destinatario_nome']);
            $colDestDoc = $pickCol(['destinatario_documento']);
            $colDestTel = $pickCol(['destinatario_telefone']);
            $addSet($colDestNome, trim((string) $request->getParam('destinatario_nome')));
            $addSet($colDestDoc, trim((string) $request->getParam('destinatario_documento')));
            $addSet($colDestTel, trim((string) $request->getParam('destinatario_telefone')));

            $set = array_values(array_filter($set, static function($x){ return is_string($x) && trim($x) !== ''; }));

            if (empty($set)) {
                $this->json(['success' => false, 'error' => 'Nenhum campo suportado para atualizar neste schema'], 400);
                return;
            }

            $params[] = $pedidoId;
            $sql = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = ?';
            $st = $pdo->prepare($sql);
            $st->execute($params);

            try {
                $newRow = [];
                try {
                    $stNew = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
                    $stNew->execute([$pedidoId]);
                    $newRow = $stNew->fetch(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $newRow = [];
                }

                $keys = ['cliente_nome','customer_name','nome','name','cliente_email','customer_email','email','cliente_telefone','customer_phone','telefone','phone','celular','cliente_documento','cliente_cpf_cnpj','cpf_cnpj','documento','customer_document','cpf','pais_entrega','country_entrega','pais','country','customer_country','cep','zipcode','zip_code','customer_zipcode','endereco','logradouro','address','customer_address','numero','address_number','customer_address_number','complemento','address_complement','customer_address_complement','bairro','province','district','customer_province','cidade','city','customer_city','estado','state','customer_state','destinatario_nome','destinatario_documento','destinatario_telefone'];
                $oldPick = [];
                $newPick = [];
                foreach ($keys as $k) {
                    if (is_array($oldRow) && array_key_exists($k, $oldRow)) $oldPick[$k] = $oldRow[$k];
                    if (is_array($newRow) && array_key_exists($k, $newRow)) $newPick[$k] = $newRow[$k];
                }

                $auth->registrarLogAuditoria($audUsuarioId > 0 ? $audUsuarioId : null, 'pedido_atualizado_cliente', 'pedidos', (int) $pedidoId, $oldPick, $newPick);
            } catch (\Throwable $e) {
            }

            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function ensurePedidoMedidasColumnsPdo(\PDO $pdo): void {
        try {
            $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmtT->execute(['pedidos']);
            if ((int) ($stmtT->fetchColumn() ?: 0) <= 0) {
                return;
            }

            $cols = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE pedidos');
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $need = [
                'peso_total' => "ALTER TABLE pedidos ADD COLUMN peso_total DECIMAL(10,3) NULL",
                'altura' => "ALTER TABLE pedidos ADD COLUMN altura INT NULL",
                'largura' => "ALTER TABLE pedidos ADD COLUMN largura INT NULL",
                'comprimento' => "ALTER TABLE pedidos ADD COLUMN comprimento INT NULL",
            ];

            foreach ($need as $col => $sql) {
                if (!is_array($cols) || !in_array($col, $cols, true)) {
                    try {
                        $pdo->exec($sql);
                    } catch (\Exception $e) {
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }

    private function tableExistsPdo(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableColumnsPdo(\PDO $pdo, string $table): array {
        try {
            $st = $pdo->query('DESCRIBE ' . $table);
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function getPedidosMissingDataWarnings(\PDO $pdo, array $pedidoIds): array {
        $out = [];
        $pedidoIds = array_values(array_filter(array_map('intval', $pedidoIds), function ($v) { return $v > 0; }));
        if (empty($pedidoIds)) {
            return $out;
        }

        $itensTable = null;
        if ($this->tableExistsPdo($pdo, 'pedido_itens')) {
            $itensTable = 'pedido_itens';
        } elseif ($this->tableExistsPdo($pdo, 'pedido_items')) {
            $itensTable = 'pedido_items';
        }
        if (!$itensTable || !$this->tableExistsPdo($pdo, 'produtos')) {
            return $out;
        }

        $colsItens = $this->getTableColumnsPdo($pdo, $itensTable);
        $colsProd = $this->getTableColumnsPdo($pdo, 'produtos');

        $colPedidoId = $this->pickColumn($colsItens, ['pedido_id']);
        $colProdutoId = $this->pickColumn($colsItens, ['produto_id']);
        $colQtd = $this->pickColumn($colsItens, ['quantidade', 'qty']);

        $colCusto = $this->pickColumn($colsProd, ['preco_custo', 'custo', 'cost_price', 'valor_custo']);
        $colNcm = $this->pickColumn($colsProd, ['ncm', 'codigo_ncm', 'ncm_code']);

        if (!$colPedidoId || !$colProdutoId) {
            return $out;
        }

        $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));

        // Missing cost
        if ($colCusto) {
            try {
                $sql = 'SELECT pi.' . $colPedidoId . ' AS pedido_id, COUNT(*) AS cnt'
                    . ' FROM ' . $itensTable . ' pi'
                    . ' INNER JOIN produtos pr ON pr.id = pi.' . $colProdutoId
                    . ' WHERE pi.' . $colPedidoId . ' IN (' . $placeholders . ')'
                    . ' AND (pr.' . $colCusto . ' IS NULL OR COALESCE(pr.' . $colCusto . ',0) <= 0)'
                    . ' GROUP BY pi.' . $colPedidoId;

                $st = $pdo->prepare($sql);
                $st->execute($pedidoIds);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $pid = (int) ($r['pedido_id'] ?? 0);
                    if ($pid <= 0) continue;
                    if (!isset($out[$pid])) $out[$pid] = ['missing_cost' => false, 'missing_ncm' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                    $out[$pid]['missing_cost'] = true;
                    $out[$pid]['missing_cost_count'] = (int) ($r['cnt'] ?? 0);
                }
            } catch (\Exception $e) {
            }
        }

        // Missing NCM
        if ($colNcm) {
            try {
                $sql = 'SELECT pi.' . $colPedidoId . ' AS pedido_id, COUNT(*) AS cnt'
                    . ' FROM ' . $itensTable . ' pi'
                    . ' INNER JOIN produtos pr ON pr.id = pi.' . $colProdutoId
                    . ' WHERE pi.' . $colPedidoId . ' IN (' . $placeholders . ')'
                    . ' AND (pr.' . $colNcm . ' IS NULL OR TRIM(COALESCE(pr.' . $colNcm . ', \'\')) = \'\')'
                    . ' GROUP BY pi.' . $colPedidoId;

                $st = $pdo->prepare($sql);
                $st->execute($pedidoIds);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $pid = (int) ($r['pedido_id'] ?? 0);
                    if ($pid <= 0) continue;
                    if (!isset($out[$pid])) $out[$pid] = ['missing_cost' => false, 'missing_ncm' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                    $out[$pid]['missing_ncm'] = true;
                    $out[$pid]['missing_ncm_count'] = (int) ($r['cnt'] ?? 0);
                }
            } catch (\Exception $e) {
            }
        }

        // Valor informado pelo cliente (assessoria)
        $colValorInformado = $this->pickColumn($colsItens, ['valor_informado_cliente']);
        if ($colValorInformado) {
            try {
                $sql = 'SELECT pi.' . $colPedidoId . ' AS pedido_id, COUNT(*) AS cnt'
                    . ' FROM ' . $itensTable . ' pi'
                    . ' WHERE pi.' . $colPedidoId . ' IN (' . $placeholders . ')'
                    . ' AND pi.' . $colValorInformado . ' = 1'
                    . ' GROUP BY pi.' . $colPedidoId;

                $st = $pdo->prepare($sql);
                $st->execute($pedidoIds);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $pid = (int) ($r['pedido_id'] ?? 0);
                    if ($pid <= 0) continue;
                    if (!isset($out[$pid])) $out[$pid] = ['missing_cost' => false, 'missing_ncm' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                    $out[$pid]['valor_informado_cliente'] = true;
                }
            } catch (\Exception $e) {
            }
        }

        // Invalid CPF (from pedido or usuario)
        try {
            if ($this->tableExistsPdo($pdo, 'pedidos')) {
                $colsPed = $this->getTableColumnsPdo($pdo, 'pedidos');
                $colsUsu = $this->tableExistsPdo($pdo, 'usuarios') ? $this->getTableColumnsPdo($pdo, 'usuarios') : [];

                $colUsuarioId = $this->pickColumn($colsPed, ['usuario_id', 'user_id', 'cliente_id']);

                $docPedCols = [];
                foreach (['cliente_documento', 'documento', 'cpf_cnpj', 'customer_document', 'cpf'] as $c) {
                    if (in_array($c, $colsPed, true)) {
                        $docPedCols[] = $c;
                    }
                }
                $docUsuCols = [];
                foreach (['documento', 'cpf', 'cpf_cnpj'] as $c) {
                    if (in_array($c, $colsUsu, true)) {
                        $docUsuCols[] = $c;
                    }
                }

                if (!empty($docPedCols) || (!empty($docUsuCols) && $colUsuarioId)) {
                    $select = ['p.id AS pedido_id'];
                    foreach ($docPedCols as $c) {
                        $select[] = 'p.' . $c . ' AS ped_' . $c;
                    }
                    if (!empty($docUsuCols) && $colUsuarioId) {
                        foreach ($docUsuCols as $c) {
                            $select[] = 'u.' . $c . ' AS usu_' . $c;
                        }
                        $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p LEFT JOIN usuarios u ON u.id = p.' . $colUsuarioId . ' WHERE p.id IN (' . $placeholders . ')';
                    } else {
                        $sql = 'SELECT ' . implode(', ', $select) . ' FROM pedidos p WHERE p.id IN (' . $placeholders . ')';
                    }

                    $stCpf = $pdo->prepare($sql);
                    $stCpf->execute($pedidoIds);
                    $rowsCpf = $stCpf->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rowsCpf as $r) {
                        $pid = (int) ($r['pedido_id'] ?? 0);
                        if ($pid <= 0) continue;

                        $doc = '';
                        foreach ($docPedCols as $c) {
                            $v = trim((string) ($r['ped_' . $c] ?? ''));
                            if ($v !== '') {
                                $doc = $v;
                                break;
                            }
                        }
                        if ($doc === '' && !empty($docUsuCols)) {
                            foreach ($docUsuCols as $c) {
                                $v = trim((string) ($r['usu_' . $c] ?? ''));
                                if ($v !== '') {
                                    $doc = $v;
                                    break;
                                }
                            }
                        }

                        $digits = CpfValidator::onlyDigits($doc);
                        $cpfInvalid = ($digits !== '' && strlen($digits) === 11 && !CpfValidator::isValid($digits));
                        if ($cpfInvalid) {
                            if (!isset($out[$pid])) {
                                $out[$pid] = ['missing_cost' => false, 'missing_ncm' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                            }
                            $out[$pid]['cpf_invalid'] = true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return $out;
    }

    public function exportXlsx(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $busca = (string) ($request->getParam('busca', '') ?? '');
        $status = (string) ($request->getParam('status', '') ?? '');

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $this->ensurePedidoMedidasColumnsPdo($pdo);

            $colsPedidos = [];
            try {
                $stmtColsP = $pdo->query('DESCRIBE pedidos');
                $colsPedidos = $stmtColsP ? ($stmtColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $pickCol = function (array $cols, array $candidates): ?string {
                foreach ($candidates as $c) {
                    if (in_array($c, $cols, true)) {
                        return $c;
                    }
                }
                return null;
            };

            $colsUsuarios = [];
            try {
                $stmtColsU = $pdo->query('DESCRIBE usuarios');
                $colsUsuarios = $stmtColsU ? ($stmtColsU->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsUsuarios = [];
            }

            $colUserName = $pickCol($colsUsuarios, ['name', 'nome', 'full_name', 'nome_completo']) ?: 'name';
            $colUserEmail = $pickCol($colsUsuarios, ['email', 'mail']) ?: 'email';
            $colNumero = $pickCol($colsPedidos, ['numero_pedido', 'order_number', 'numero', 'codigo']);
            $temDeletedAt = in_array('deleted_at', $colsPedidos, true);

            $sql = "SELECT p.*, u." . $colUserName . " as cliente_nome, u." . $colUserEmail . " as cliente_email FROM pedidos p LEFT JOIN usuarios u ON p." . (in_array("usuario_id", $colsPedidos, true) ? "usuario_id" : "cliente_id") . " = u.id WHERE 1=1";
            $params = [];
            if ($temDeletedAt) {
                $sql .= " AND p.deleted_at IS NULL";
            }

            if (trim($busca) !== '') {
                $buscaRaw = trim($busca);
                $buscaDigits = preg_replace('/\D+/', '', $buscaRaw);
                $buscaInt = ($buscaDigits !== '') ? (int) $buscaDigits : 0;

                $searchParts = [];
                if ($buscaInt > 0) {
                    $searchParts[] = 'p.id = :busca_int';
                    $searchParts[] = 'CAST(p.id AS CHAR) LIKE :busca_int_like';
                }
                $searchParts[] = 'CAST(p.id AS CHAR) LIKE :busca';
                $searchParts[] = 'u.' . $colUserName . ' LIKE :busca';
                $searchParts[] = 'u.' . $colUserEmail . ' LIKE :busca';
                if ($colNumero) {
                    $searchParts[] = 'p.' . $colNumero . ' LIKE :busca';
                }
                if (in_array('codigo_pedido', $colsPedidos, true)) {
                    $searchParts[] = 'p.codigo_pedido LIKE :busca';
                }
                if (in_array('numero_pedido', $colsPedidos, true)) {
                    $searchParts[] = 'p.numero_pedido LIKE :busca';
                }
                $sql .= ' AND (' . implode(' OR ', $searchParts) . ')';
                $params[':busca'] = "%{$busca}%";
                if ($buscaInt > 0) {
                    $params[':busca_int'] = $buscaInt;
                    $params[':busca_int_like'] = "%{$buscaInt}%";
                }
            }

            if (trim($status) !== '') {
                $sql .= ' AND p.status = :status';
                $params[':status'] = $status;
            }

            $sql .= ' ORDER BY p.created_at DESC';
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $pedidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Items (tolerant schema)
            $itens = [];
            $pedidoIds = [];
            foreach ($pedidos as $p) {
                if (isset($p['id'])) {
                    $pedidoIds[] = (int) $p['id'];
                }
            }
            $pedidoIds = array_values(array_filter($pedidoIds, static fn($v) => $v > 0));

            $itensTable = null;
            if ($this->tableExistsPdo($pdo, 'pedido_itens')) {
                $itensTable = 'pedido_itens';
            } elseif ($this->tableExistsPdo($pdo, 'pedido_items')) {
                $itensTable = 'pedido_items';
            }

            if ($itensTable && !empty($pedidoIds)) {
                $colsItens = $this->getTableColumnsPdo($pdo, $itensTable);
                $colPedidoId = $pickCol($colsItens, ['pedido_id']);
                $colProdutoId = $pickCol($colsItens, ['produto_id', 'product_id']);
                $colProdutoNome = $pickCol($colsItens, ['produto_nome', 'product_name', 'nome_produto', 'produto']);
                $colQtd = $pickCol($colsItens, ['quantidade', 'qty']);
                $colPreco = $pickCol($colsItens, ['preco', 'price', 'valor_unitario', 'unit_price']);
                $colSubtotal = $pickCol($colsItens, ['subtotal', 'valor_total', 'total', 'line_total']);

                if ($colPedidoId) {
                    $in = implode(',', array_fill(0, count($pedidoIds), '?'));
                    $select = ['pi.' . $colPedidoId . ' AS pedido_id'];
                    if ($colProdutoId) $select[] = 'pi.' . $colProdutoId . ' AS produto_id';
                    if ($colProdutoNome) $select[] = 'pi.' . $colProdutoNome . ' AS produto';
                    if ($colQtd) $select[] = 'pi.' . $colQtd . ' AS quantidade';
                    if ($colPreco) $select[] = 'pi.' . $colPreco . ' AS preco_unitario';
                    if ($colSubtotal) $select[] = 'pi.' . $colSubtotal . ' AS subtotal';

                    $sqlItens = 'SELECT ' . implode(', ', $select) . ' FROM ' . $itensTable . ' pi WHERE pi.' . $colPedidoId . ' IN (' . $in . ')';
                    $stI = $pdo->prepare($sqlItens);
                    $stI->execute($pedidoIds);
                    $itens = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Braziliana')
                ->setTitle('Pedidos - Exportação');

            // Sheet 1: Pedidos
            $sheetPedidos = $spreadsheet->getActiveSheet();
            $sheetPedidos->setTitle('Pedidos');
            $headersPedidos = [
                'ID',
                'Número',
                'Status',
                'Data',
                'Cliente',
                'Email',
                'Moeda',
                'Total'
            ];
            $sheetPedidos->fromArray($headersPedidos, null, 'A1');
            $row = 2;
            foreach ($pedidos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $numero = '';
                if (isset($p['numero_pedido'])) {
                    $numero = (string) $p['numero_pedido'];
                } elseif ($colNumero && isset($p[$colNumero])) {
                    $numero = (string) $p[$colNumero];
                } elseif (isset($p['codigo_pedido'])) {
                    $numero = (string) $p['codigo_pedido'];
                }
                $createdAt = (string) ($p['created_at'] ?? ($p['data_criacao'] ?? ($p['data_pedido'] ?? '')));
                $total = null;
                foreach (['total', 'valor_total', 'amount', 'valor'] as $c) {
                    if (array_key_exists($c, $p)) {
                        $total = (float) ($p[$c] ?? 0);
                        break;
                    }
                }
                if ($total === null) {
                    $total = 0.0;
                }
                $moeda = strtoupper(trim((string) ($p['moeda'] ?? ($p['currency'] ?? 'BRL'))));
                if ($moeda === '') $moeda = 'BRL';

                $sheetPedidos->fromArray([
                    $pid,
                    $numero,
                    (string) ($p['status'] ?? ''),
                    $createdAt,
                    (string) ($p['cliente_nome'] ?? ''),
                    (string) ($p['cliente_email'] ?? ''),
                    $moeda,
                    $total
                ], null, 'A' . $row);
                $row++;
            }

            // Sheet 2: Itens
            $sheetItens = $spreadsheet->createSheet();
            $sheetItens->setTitle('Itens');
            $headersItens = ['Pedido ID', 'Produto ID', 'Produto', 'Quantidade', 'Preço Unitário', 'Subtotal'];
            $sheetItens->fromArray($headersItens, null, 'A1');
            $row = 2;
            foreach ($itens as $it) {
                $sheetItens->fromArray([
                    (int) ($it['pedido_id'] ?? 0),
                    (string) ($it['produto_id'] ?? ''),
                    (string) ($it['produto'] ?? ''),
                    (string) ($it['quantidade'] ?? ''),
                    (string) ($it['preco_unitario'] ?? ''),
                    (string) ($it['subtotal'] ?? '')
                ], null, 'A' . $row);
                $row++;
            }

            $filename = 'pedidos_' . date('Y-m-d_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            http_response_code(500);
            echo 'Erro ao exportar: ' . $e->getMessage();
            exit;
        }
    }

    public function importarPedidosModelo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $headers = explode("\t", trim("post_id\tpost_date\tpost_type\tstatus\ttitle\tpost_name\tcreator\tpubDate\tlink\tguid\tMercado Pago - 139132911483 - card_last_four_digits\tMercado Pago - 139132911483 - installment_amount\tMercado Pago - 139132911483 - installments\tMercado Pago - 139132911483 - total_paid_amount\tMercado Pago - 139132911483 - transaction_amount\tMercado Pago - 139779301638 - card_last_four_digits\tMercado Pago - 139779301638 - installment_amount\tMercado Pago - 139779301638 - installments\tMercado Pago - 139779301638 - total_paid_amount\tMercado Pago - 139779301638 - transaction_amount\tMercado Pago - 141615422750 - card_last_four_digits\tMercado Pago - 141615422750 - installment_amount\tMercado Pago - 141615422750 - installments\tMercado Pago - 141615422750 - total_paid_amount\tMercado Pago - 141615422750 - transaction_amount\tMercado Pago - Payment 1325685362\tMercado Pago - Payment 137809090869\tMercado Pago - Payment 137828409527\tMercado Pago - Payment 137858461491\tMercado Pago - Payment 137970692673\tMercado Pago - Payment 138114864009\tMercado Pago - Payment 138160150449\tMercado Pago - Payment 138398316403\tMercado Pago - Payment 138408239279\tMercado Pago - Payment 138413245495\tMercado Pago - Payment 138442534612\tMercado Pago - Payment 138760913710\tMercado Pago - Payment 138763253086\tMercado Pago - Payment 138764416918\tMercado Pago - Payment 138934313819\tMercado Pago - Payment 139042864960\tMercado Pago - Payment 139043969606\tMercado Pago - Payment 139052696496\tMercado Pago - Payment 139128027471\tMercado Pago - Payment 139132911483\tMercado Pago - Payment 139173227978\tMercado Pago - Payment 139198918624\tMercado Pago - Payment 139588728906\tMercado Pago - Payment 139779301638\tMercado Pago - Payment 139780223608\tMercado Pago - Payment 140081615335\tMercado Pago - Payment 140394624492\tMercado Pago - Payment 140396137678\tMercado Pago - Payment 140655178553\tMercado Pago - Payment 140690315291\tMercado Pago - Payment 140724181308\tMercado Pago - Payment 140726199884\tMercado Pago - Payment 140791142853\tMercado Pago - Payment 141051085759\tMercado Pago - Payment 141095168033\tMercado Pago - Payment 141096070263\tMercado Pago - Payment 141253845215\tMercado Pago - Payment 141316218448\tMercado Pago - Payment 141319526220\tMercado Pago - Payment 141379310532\tMercado Pago - Payment 141415435559\tMercado Pago - Payment 141419174231\tMercado Pago - Payment 141499022953\tMercado Pago - Payment 141516626021\tMercado Pago - Payment 141615422750\tMercado Pago - Payment 141632778467\tMercado Pago - Payment 141750566566\tMercado Pago - Payment 141778613377\tMercado Pago - Payment 141783462809\tMercado Pago - Payment 141796904623\tMercado Pago - Payment 141903960476\tMercado Pago - Payment 142025716099\tMercado Pago - Payment 142028713787\tMercado Pago - Payment 142033041219\tMercado Pago - Payment 142034557529\tMercado Pago - Payment 142041162737\tMercado Pago - Payment 142080429274\tMercado Pago - Payment 142081528756\tMercado Pago - Payment 142082337636\tMercado Pago - Payment 142082663422\tMercado Pago - Payment 142114637341\tMercado Pago - Payment 142153416700\tMercado Pago - Payment 142165932226\tMercado Pago - Payment 142176757379\tMercado Pago - Payment 142178111735\tMercado Pago - Payment 142192276797\tMercado Pago - Payment 142194403667\tMercado Pago - Payment 142274860093\tMercado Pago - Payment 142307478387\tMercado Pago - Payment 142312798137\tMercado Pago - Payment 142386523179\tMercado Pago - Payment 142451359954\tMercado Pago - Payment 142483850170\tMercado Pago - Payment 142555055507\tMercado Pago - Payment 142678683181\tMercado Pago - Payment 142682522683\tMercado Pago - Payment 142688680069\tMercado Pago - Payment 142691431903\tMercado Pago - Payment 142692373637\tMercado Pago - Payment 142697311030\tMercado Pago - Payment 142700806678\tMercado Pago - Payment 142704403095\tMercado Pago - Payment 142704795873\tMercado Pago - Payment 142705935932\tMercado Pago - Payment 142709320442\tMercado Pago - Payment 142712043364\tMercado Pago - Payment 142825837080\tMercado Pago - Payment 142859787740\tMercado Pago - Payment 142866705548\tMercado Pago - Payment 142896987737\tMercado Pago - Payment 142940409911\tMercado Pago - Payment 142950908656\tMercado Pago - Payment 142951415728\tMercado Pago - Payment 142974036426\tMercado Pago - Payment 143022121065\tMercado Pago - Payment 143023335535\tMercado Pago - Payment 143067529734\tMercado Pago - Payment 143079256825\tMercado Pago - Payment 143090773899\tMercado Pago - Payment 143097800305\tMercado Pago - Payment 143245509584\tMercado Pago - Payment 143246887252\tMercado Pago - Payment 143250607572\tMercado Pago - Payment 143304958565\tMercado Pago - Payment 143306952223\tMercado Pago - Payment 143341561562\tMercado Pago - Payment 143343032634\tMercado Pago - Payment 143345712370\tMercado Pago - Payment 143348927958\tMercado Pago - Payment 143351513522\tMercado Pago - Payment 143380329616\tMercado Pago - Payment 143402346344\tMercado Pago - Payment 143561517558\tMercado Pago - Payment 143562623296\tMercado Pago - Payment 143567340914\tMercado Pago - Payment 143579138548\tMercado Pago - Payment 143589505152\tMercado Pago - Payment 143600501202\tMercado Pago - Payment 143601485018\tMercado Pago - Payment 143619430202\tMercado Pago - Payment 143625396619\tMercado Pago - Payment 143638326927\tMercado Pago - Payment 143711213020\tMercado Pago - Payment 143723982988\tMercado Pago - Payment 143734184860\tMercado Pago - Payment 143739623532\tMercado Pago - Payment 143753944480\tMercado Pago - Payment 143769558176\tMercado Pago - Payment 143839218211\tMercado Pago - Payment 143841539339\tMercado Pago - Payment 143854187652\tMercado Pago - Payment 143854234695\tMercado Pago - Payment 143860509576\tMercado Pago - Payment 143862559906\tMercado Pago - Payment 143864548398\tMercado Pago - Payment 143866655046\tMercado Pago - Payment 143942487951\tMercado Pago - Payment 143971750698\tMercado Pago - Payment 143973303890\tMercado Pago - Payment 143988939043\tMercado Pago - Payment 144008338271\tMercado Pago - Payment 144149932835\tMercado Pago - Payment 144169130116\tMercado Pago - Payment 144271989049\tMercado Pago - Payment 144302223279\tMercado Pago - Payment 144533638384\tMercado Pago - Payment 144666973580\tMercado Pago - Payment 144689517098\tMercado Pago - Payment 144759718338\tMercado Pago - Payment 144761854066\tMercado Pago - Payment 144795992064\tMercado Pago - Payment 144950180220\tMercado Pago - Payment 144951847650\tPAYMENT_ID: DATE\t_Mercado_Pago_Payment_IDs\t_accept_product_replacement\t_billing_address_1\t_billing_address_2\t_billing_address_index\t_billing_birthdate\t_billing_cellphone\t_billing_city\t_billing_company\t_billing_country\t_billing_cpf\t_billing_email\t_billing_first_name\t_billing_last_name\t_billing_neighborhood\t_billing_number\t_billing_phone\t_billing_postcode\t_billing_state\t_cart_discount\t_cart_discount_tax\t_cart_hash\t_completed_date\t_created_via\t_currency_ratio\t_customer_ip_address\t_customer_user\t_customer_user_agent\t_date_completed\t_date_paid\t_download_permissions_granted\t_edit_last\t_invoice_contest\t_invoice_images\t_last_printed_by\t_migrated_at\t_migration_batch\t_new_order_email_sent\t_old_order_id\t_order_currency\t_order_key\t_order_shipping\t_order_shipping_tax\t_order_stock_reduced\t_order_tax\t_order_total\t_order_version\t_pagamentos_para_woocommerce_com_appmax_label\t_pagamentos_para_woocommerce_com_appmax_media\t_pagamentos_para_woocommerce_com_appmax_payment_code\t_pagamentos_para_woocommerce_com_appmax_paymentid\t_paid_date\t_payment_method\t_payment_method_title\t_pdf_generated\t_prices_include_tax\t_print_count\t_recipient_document_number\t_recorded_coupon_usage_counts\t_recorded_sales\t_shipping_address_1\t_shipping_address_2\t_shipping_address_index\t_shipping_city\t_shipping_company\t_shipping_country\t_shipping_first_name\t_shipping_last_name\t_shipping_neighborhood\t_shipping_number\t_shipping_phone\t_shipping_postcode\t_shipping_state\t_shipping_suite\t_stripe_charge_captured\t_stripe_currency\t_stripe_customer_id\t_stripe_fee\t_stripe_intent_id\t_stripe_net\t_stripe_source_id\t_stripe_upe_payment_type\t_stripe_upe_redirect_processed\t_stripe_upe_waiting_for_redirect\t_stripe_upe_waiting_for_redirect\t_suite\t_tracking_code\t_transaction_id\t_used_gateway\t_wallet_rechargeable_order\t_wc_order_attribution_device_type\t_wc_order_attribution_referrer\t_wc_order_attribution_session_count\t_wc_order_attribution_session_entry\t_wc_order_attribution_session_pages\t_wc_order_attribution_session_start_time\t_wc_order_attribution_source_type\t_wc_order_attribution_user_agent\t_wc_order_attribution_utm_content\t_wc_order_attribution_utm_medium\t_wc_order_attribution_utm_source\t_wccs_base_currency\t_wccs_currency_rate\t_wccs_shop_currency\t_wccs_total_in_base_currency\t_wp_desired_post_slug\t_wp_trash_meta_comments_status\t_wp_trash_meta_status\t_wp_trash_meta_time\t_wpam_id\tblocks_payment\tcheckout_pix_date_expiration\tfinancing_fee\tis_production_mode\tis_vat_exempt\tjupiterx_reading_time\tlogic_results_storage\tmercadopago_fee\tmp_installments\tmp_pix_qr_base64\tmp_pix_qr_code\tmp_total_paid_amount\tmp_transaction_amount\tmp_transaction_details\tpix_on\tshopping_list_id\ttpul_visitor_id\ttrp_language"));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="import_pedidos_modelo.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        fclose($out);
        exit;
    }

    public function lixeira(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $colsPedidos = [];
            try {
                $stmtColsP = $pdo->query('DESCRIBE pedidos');
                $colsPedidos = $stmtColsP ? ($stmtColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            if (!in_array('deleted_at', $colsPedidos, true)) {
                echo '<div class="alert alert-warning">Sua base ainda não possui lixeira (deleted_at). Rode a migration 087_soft_delete_pedidos_lixeira.sql.</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $hasDeletedBy = in_array('deleted_by', $colsPedidos, true);
            $deletedByJoin = $hasDeletedBy ? 'LEFT JOIN usuarios d ON p.deleted_by = d.id' : '';
            $deletedBySelect = $hasDeletedBy ? ', d.name as deletado_por_nome, d.email as deletado_por_email' : '';

            $stmt = $pdo->query("SELECT p.*, u.name as cliente_nome, u.email as cliente_email {$deletedBySelect} FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id {$deletedByJoin} WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC LIMIT 200");
            $pedidos = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            $pedidos = [];
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lixeira de Pedidos - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        renderAdminSidebar('pedidos');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-trash me-2"></i>Lixeira de Pedidos</h1>
                    <div>
                        <a href="/admin/pedidos" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                    </div>
                </div>';

        if (empty($pedidos)) {
            echo '<div class="text-muted">Nenhum pedido na lixeira.</div>';
        } else {
            echo '<div class="table-responsive">'
                . '<table class="table table-sm align-middle">'
                . '<thead><tr><th>Pedido</th><th>Cliente</th><th>Email</th><th>Excluído por</th><th>Excluído em</th><th>Ações</th></tr></thead><tbody>';
            foreach ($pedidos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $dt = (string) ($p['deleted_at'] ?? '');
                $dtFmt = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '-';
                $deletadoPor = trim((string) ($p['deletado_por_nome'] ?? ''));
                $deletadoPorEmail = trim((string) ($p['deletado_por_email'] ?? ''));
                $deletadoPorDisplay = $deletadoPor !== '' ? $deletadoPor : ($deletadoPorEmail !== '' ? $deletadoPorEmail : '<span class="text-muted">—</span>');
                echo '<tr>'
                    . '<td><strong>#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</strong></td>'
                    . '<td>' . htmlspecialchars((string) ($p['cliente_nome'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($p['cliente_email'] ?? '')) . '</td>'
                    . '<td>' . ($deletadoPor !== '' || $deletadoPorEmail !== '' ? htmlspecialchars($deletadoPorDisplay) : '<span class="text-muted">—</span>') . '</td>'
                    . '<td>' . htmlspecialchars($dtFmt) . '</td>'
                    . '<td>'
                    . '<div class="d-flex gap-2">'
                    . '<a class="btn btn-sm btn-outline-primary" href="/admin/pedidos/detalhes/' . $pid . '" target="_blank"><i class="fas fa-eye"></i></a>'
                    . '<form method="POST" action="/admin/pedidos/restaurar/' . $pid . '">'
                    . '<button type="submit" class="btn btn-sm btn-success"><i class="fas fa-rotate-left me-1"></i>Restaurar</button>'
                    . '</form>'
                    . '</div>'
                    . '</td>'
                    . '</tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '</main></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function restaurar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $id = $id ?? $request->getParam('id');
        $id = (int) $id;
        if ($id <= 0) {
            header('Location: /admin/pedidos/lixeira');
            exit;
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $colsPedidos = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            if (!in_array('deleted_at', $colsPedidos, true)) {
                header('Location: /admin/pedidos');
                exit;
            }

            $set = ['deleted_at = NULL'];
            if (in_array('deleted_by', $colsPedidos, true)) {
                $set[] = 'deleted_by = NULL';
            }
            $st = $pdo->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = ?');
            $st->execute([(int) $id]);
        } catch (\Exception $e) {
        }

        header('Location: /admin/pedidos/lixeira');
        exit;
    }

    public function importarPedidosIniciar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=UTF-8');

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        if (!isset($_FILES['pedidos_import_csv']) || empty($_FILES['pedidos_import_csv']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Arquivo CSV não enviado.']);
            exit;
        }
        if (!empty($_FILES['pedidos_import_csv']['error']) && $_FILES['pedidos_import_csv']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Falha no upload do CSV.']);
            exit;
        }

        $tmpUpload = (string) $_FILES['pedidos_import_csv']['tmp_name'];
        $token = bin2hex(random_bytes(16));
        $csvPath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pedidos_import_' . $token . '.csv';
        $statePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pedidos_import_' . $token . '.json';

        if (!@move_uploaded_file($tmpUpload, $csvPath)) {
            if (!@copy($tmpUpload, $csvPath)) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar o arquivo no servidor.']);
                exit;
            }
        }

        $scan = $this->scanPedidosCsv($csvPath);
        if (!($scan['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => (string) ($scan['error'] ?? 'CSV inválido')]);
            exit;
        }

        $state = [
            'token' => $token,
            'csv' => $csvPath,
            'delimiter' => (string) ($scan['delimiter'] ?? ','),
            'hasHeader' => (bool) ($scan['hasHeader'] ?? true),
            'header' => (is_array($scan['header'] ?? null) ? ($scan['header'] ?? null) : null),
            'total' => (int) ($scan['total'] ?? 0),
            'offset' => 0,
            'okCount' => 0,
            'failCount' => 0,
            'done' => false,
            'createdAt' => date('c'),
        ];
        @file_put_contents($statePath, json_encode($state));

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'total' => $state['total'],
            'processed' => 0,
            'okCount' => 0,
            'failCount' => 0,
            'done' => false,
        ]);
        exit;
    }

    public function importarPedidosProcessar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        header('Content-Type: application/json; charset=UTF-8');

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $token = trim((string) ($request->getParam('token') ?? ''));
        $batchSize = (int) ($request->getParam('batch') ?? 150);
        if ($batchSize <= 0) $batchSize = 150;
        if ($batchSize > 500) $batchSize = 500;

        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $statePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pedidos_import_' . $token . '.json';
        if (!is_file($statePath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Importação não encontrada (expirada).']);
            exit;
        }

        $stateRaw = @file_get_contents($statePath);
        $state = is_string($stateRaw) ? json_decode($stateRaw, true) : null;
        if (!is_array($state)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Estado da importação corrompido.']);
            exit;
        }

        if (!empty($state['done'])) {
            echo json_encode([
                'ok' => true,
                'token' => $token,
                'total' => (int) ($state['total'] ?? 0),
                'processed' => (int) ($state['offset'] ?? 0),
                'okCount' => (int) ($state['okCount'] ?? 0),
                'failCount' => (int) ($state['failCount'] ?? 0),
                'done' => true,
            ]);
            exit;
        }

        $csvPath = (string) ($state['csv'] ?? '');
        if ($csvPath === '' || !is_file($csvPath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Arquivo CSV não encontrado no servidor.']);
            exit;
        }

        $delimiter = (string) ($state['delimiter'] ?? ',');
        $hasHeader = (bool) ($state['hasHeader'] ?? true);
        $header = (is_array($state['header'] ?? null) ? ($state['header'] ?? null) : null);
        $offset = (int) ($state['offset'] ?? 0);
        if ($offset < 0) $offset = 0;

        $res = $this->processPedidosCsvBatch($pdo, $csvPath, $delimiter, $hasHeader, $header, $offset, $batchSize);

        $state['offset'] = $offset + (int) ($res['processedNow'] ?? 0);
        $state['okCount'] = (int) ($state['okCount'] ?? 0) + (int) ($res['okNow'] ?? 0);
        $state['failCount'] = (int) ($state['failCount'] ?? 0) + (int) ($res['failNow'] ?? 0);
        $total = (int) ($state['total'] ?? 0);
        $processed = (int) ($state['offset'] ?? 0);
        $state['done'] = ($total > 0 && $processed >= $total) || (int) ($res['processedNow'] ?? 0) === 0;

        @file_put_contents($statePath, json_encode($state));

        if (!empty($state['done'])) {
            try { @unlink($csvPath); } catch (\Exception $e) {}
            try { @unlink($statePath); } catch (\Exception $e) {}
        }

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'total' => $total,
            'processed' => $processed,
            'okCount' => (int) ($state['okCount'] ?? 0),
            'failCount' => (int) ($state['failCount'] ?? 0),
            'done' => (bool) ($state['done'] ?? false),
        ]);
        exit;
    }

    private function scanPedidosCsv(string $csvPath): array {
        $fh = @fopen($csvPath, 'r');
        if (!$fh) {
            return ['ok' => false, 'error' => 'Não foi possível ler o CSV.'];
        }

        $first = fgetcsv($fh, 0, ',');
        $delimiter = ',';
        if (is_array($first) && count($first) === 1) {
            rewind($fh);
            $first = fgetcsv($fh, 0, ';');
            $delimiter = ';';
        }

        $normalizeHeader = function($v) {
            $s = trim((string) $v);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        $header = is_array($first) ? array_map($normalizeHeader, $first) : [];
        $hasHeader = !empty($header);
        if ($hasHeader) {
            $joined = strtolower(implode('|', $header));
            if (strpos($joined, 'post_id') === false || strpos($joined, '_order_total') === false) {
                $hasHeader = false;
                $header = null;
                rewind($fh);
            }
        } else {
            rewind($fh);
        }

        $total = 0;
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 2) {
                continue;
            }
            $total++;
        }
        fclose($fh);
        return ['ok' => true, 'delimiter' => $delimiter, 'hasHeader' => (bool) $hasHeader, 'header' => $header, 'total' => $total];
    }

    private function processPedidosCsvBatch(\PDO $pdo, string $csvPath, string $delimiter, bool $hasHeader, ?array $header, int $offset, int $limit): array {
        $fh = @fopen($csvPath, 'r');
        if (!$fh) {
            return ['processedNow' => 0, 'okNow' => 0, 'failNow' => 0];
        }

        $normalizeHeader = function($v) {
            $s = trim((string) $v);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        if ($hasHeader) {
            $hdrRow = fgetcsv($fh, 0, $delimiter);
            if ($header === null && is_array($hdrRow)) {
                $header = array_map($normalizeHeader, $hdrRow);
            }
        }

        $skipped = 0;
        while ($skipped < $offset && ($rowSkip = fgetcsv($fh, 0, $delimiter)) !== false) {
            $skipped++;
        }

        $processedNow = 0;
        $okNow = 0;
        $failNow = 0;

        $this->ensureImportRowStatusTable($pdo);

        while ($processedNow < $limit && ($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 2) {
                continue;
            }

            $assoc = [];
            if ($hasHeader && is_array($header)) {
                foreach ($header as $i => $k) {
                    if ($k === '') continue;
                    $assoc[$k] = array_key_exists($i, $row) ? (string) $row[$i] : '';
                }
            } else {
                foreach ($row as $i => $v) {
                    $assoc[(string) $i] = (string) $v;
                }
            }

            $rowKey = $this->getPedidoImportRowKey($assoc);
            if ($rowKey !== '' && $this->isImportRowOk($pdo, 'pedidos', $rowKey)) {
                $okNow++;
                $processedNow++;
                continue;
            }
            try {
                $this->processPedidoAssocRow($pdo, $assoc);
                if ($rowKey !== '') {
                    $this->markImportRowOk($pdo, 'pedidos', $rowKey);
                }
                $okNow++;
            } catch (\Exception $e) {
                if ($rowKey !== '') {
                    $this->markImportRowFail($pdo, 'pedidos', $rowKey, $e->getMessage());
                }
                $failNow++;
            }
            $processedNow++;
        }

        fclose($fh);
        return ['processedNow' => $processedNow, 'okNow' => $okNow, 'failNow' => $failNow];
    }

    private function getPedidoImportRowKey(array $assoc): string {
        $postId = trim((string) ($assoc['post_id'] ?? ''));
        $orderKey = trim((string) ($assoc['_order_key'] ?? ''));
        $oldOrderId = trim((string) ($assoc['_old_order_id'] ?? ''));

        // Quando o CSV vier "um item por linha", precisamos diferenciar itens do mesmo pedido.
        $produtoIdExt = trim((string) ($assoc['Produto ID'] ?? $assoc['produto_id'] ?? ''));
        $ref = trim((string) ($assoc['Referência'] ?? $assoc['Referencia'] ?? $assoc['SKU'] ?? $assoc['Sku'] ?? $assoc['sku'] ?? ''));
        $nome = trim((string) ($assoc['Produto'] ?? $assoc['produto'] ?? ''));
        $qtd = trim((string) ($assoc['Quantidade'] ?? $assoc['quantidade'] ?? $assoc['qty'] ?? ''));
        $sub = trim((string) ($assoc['Subtotal'] ?? $assoc['subtotal'] ?? ''));
        $hasItem = ($produtoIdExt !== '' || $ref !== '' || $nome !== '');

        if ($postId !== '') {
            if ($hasItem) {
                return 'post_id:' . $postId . '|item:' . strtolower($produtoIdExt . '|' . $ref . '|' . $nome . '|' . $qtd . '|' . $sub);
            }
            return 'post_id:' . $postId;
        }
        if ($orderKey !== '') return 'order_key:' . $orderKey;
        if ($oldOrderId !== '') return 'old_order_id:' . $oldOrderId;
        return '';
    }

    private function ensureImportRowStatusTable(\PDO $pdo): void {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['import_row_status']);
            $ok = (bool) $st->fetchColumn();
            if ($ok) return;
        } catch (\Exception $e) {
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS import_row_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                import_type VARCHAR(40) NOT NULL,
                row_key VARCHAR(191) NOT NULL,
                status VARCHAR(10) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                ok_at DATETIME NULL,
                fail_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_import_row (import_type, row_key),
                KEY idx_import_type_status (import_type, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    private function isImportRowOk(\PDO $pdo, string $type, string $rowKey): bool {
        try {
            $st = $pdo->prepare('SELECT status FROM import_row_status WHERE import_type = :t AND row_key = :k LIMIT 1');
            $st->execute([':t' => $type, ':k' => $rowKey]);
            $s = strtolower((string) ($st->fetchColumn() ?: ''));
            return $s === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function markImportRowOk(\PDO $pdo, string $type, string $rowKey): void {
        try {
            $st = $pdo->prepare('INSERT INTO import_row_status (import_type, row_key, status, attempts, ok_at) VALUES (:t,:k,\'ok\',1,NOW()) ON DUPLICATE KEY UPDATE status=\'ok\', attempts=attempts+1, last_error=NULL, ok_at=NOW(), updated_at=NOW()');
            $st->execute([':t' => $type, ':k' => $rowKey]);
        } catch (\Exception $e) {
        }
    }

    private function markImportRowFail(\PDO $pdo, string $type, string $rowKey, string $error): void {
        $error = trim((string) $error);
        if (strlen($error) > 2000) {
            $error = substr($error, 0, 2000);
        }
        try {
            $st = $pdo->prepare('INSERT INTO import_row_status (import_type, row_key, status, attempts, last_error, fail_at) VALUES (:t,:k,\'fail\',1,:e,NOW()) ON DUPLICATE KEY UPDATE status=\'fail\', attempts=attempts+1, last_error=:e, fail_at=NOW(), updated_at=NOW()');
            $st->execute([':t' => $type, ':k' => $rowKey, ':e' => $error]);
        } catch (\Exception $e) {
        }
    }

    private function processPedidoAssocRow(\PDO $pdo, array $row): void {
        $get = function(string $key) use ($row) {
            return trim((string) ($row[$key] ?? ''));
        };

        $getAny = function(array $keys) use ($row) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $row)) {
                    $v = trim((string) ($row[$k] ?? ''));
                    if ($v !== '') return $v;
                }
            }
            return '';
        };

        $postId = $get('post_id');
        $orderKey = $get('_order_key');
        $oldOrderId = $get('_old_order_id');
        $billingEmail = $get('_billing_email');
        $customerUser = $get('_customer_user');

        if ($postId === '' && $orderKey === '' && $oldOrderId === '') {
            throw new \RuntimeException('Pedido sem identificador');
        }

        $usuarioId = 0;
        try {
            if ($customerUser !== '' && ctype_digit($customerUser)) {
                $usuarioId = $this->findUsuarioIdById($pdo, (int) $customerUser);
            }
            if ($usuarioId <= 0 && $billingEmail !== '') {
                $usuarioId = $this->findUsuarioIdByEmail($pdo, $billingEmail);
            }
        } catch (\Exception $e) {
            $usuarioId = 0;
        }

        if ($usuarioId <= 0) {
            throw new \RuntimeException('Usuário não encontrado para o pedido');
        }

        static $colsPedidosCache = null;
        if (!is_array($colsPedidosCache)) {
            $colsPedidosCache = [];
            try {
                $stmtColsP = $pdo->query('DESCRIBE pedidos');
                $colsPedidosCache = $stmtColsP ? ($stmtColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidosCache = [];
            }
        }
        $colsPedidos = $colsPedidosCache;
        if (empty($colsPedidos)) {
            throw new \RuntimeException('Tabela pedidos não encontrada');
        }

        $pickCol = function(array $cols, array $cands): string {
            foreach ($cands as $c) {
                if (in_array($c, $cols, true)) return $c;
            }
            return '';
        };

        $colUsuario = $pickCol($colsPedidos, ['usuario_id', 'user_id', 'cliente_id']);
        $colTotal = $pickCol($colsPedidos, ['valor_total', 'total', 'amount', 'valor']);
        $colMoeda = $pickCol($colsPedidos, ['moeda', 'currency', 'order_currency']);
        $colStatus = $pickCol($colsPedidos, ['status', 'status_pedido', 'pedido_status']);
        $colPaymentGateway = $pickCol($colsPedidos, ['payment_gateway', 'gateway']);
        $colPaymentId = $pickCol($colsPedidos, ['payment_id', 'transaction_id', 'codigo_transacao']);
        $colPaymentStatus = $pickCol($colsPedidos, ['payment_status', 'status_pagamento']);
        $colTracking = $pickCol($colsPedidos, ['tracking_code', 'codigo_rastreio', 'rastreamento']);
        $colCodigoPedido = $pickCol($colsPedidos, ['codigo_pedido', 'numero_pedido', 'codigo', 'order_number']);

        if ($colUsuario === '' || $colTotal === '') {
            throw new \RuntimeException('Schema de pedidos não compatível (faltando usuario/total)');
        }

        $orderTotalRaw = $get('_order_total');
        $total = 0.0;
        if ($orderTotalRaw !== '') {
            $num = str_replace(['R$', 'USD', 'BRL'], '', $orderTotalRaw);
            $num = preg_replace('/\s+/', '', (string) $num);
            if (strpos($num, ',') !== false && strpos($num, '.') !== false) {
                $num = str_replace('.', '', $num);
                $num = str_replace(',', '.', $num);
            } elseif (strpos($num, ',') !== false) {
                $num = str_replace(',', '.', $num);
            }
            if (is_numeric($num)) {
                $total = (float) $num;
            }
        }

        $moeda = strtoupper(trim($get('_order_currency')));
        if ($moeda === '') {
            $moeda = strtoupper(trim($get('_order_currency')));
        }
        if ($moeda === '') {
            $moeda = 'BRL';
        }

        $status = strtolower(trim($get('status')));
        if ($status === '') {
            $status = strtolower(trim($get('_payment_method')));
        }
        if ($status === '') {
            $status = 'pendente';
        }

        $paymentMethod = trim($get('_payment_method'));
        if ($paymentMethod === '') {
            $paymentMethod = trim($get('_used_gateway'));
        }
        $paymentId = trim($get('_transaction_id'));
        if ($paymentId === '') {
            $paymentId = trim($get('Mercado Pago - Payment 139132911483'));
        }

        $tracking = trim($get('_tracking_code'));
        if ($tracking === '') {
            $tracking = trim($get('_tracking_code'));
        }

        $codigoPedido = $postId !== '' ? $postId : ($oldOrderId !== '' ? $oldOrderId : $orderKey);

        $pedidoId = 0;
        $lookupKey = $orderKey !== '' ? $orderKey : ($oldOrderId !== '' ? $oldOrderId : $postId);
        if ($lookupKey !== '') {
            $pedidoId = $this->findPedidoIdByMeta($pdo, '_order_key', $orderKey);
            if ($pedidoId <= 0 && $oldOrderId !== '') {
                $pedidoId = $this->findPedidoIdByMeta($pdo, '_old_order_id', $oldOrderId);
            }
            if ($pedidoId <= 0 && $postId !== '') {
                $pedidoId = $this->findPedidoIdByMeta($pdo, 'post_id', $postId);
            }
        }

        if ($pedidoId > 0) {
            $existing = [];
            try {
                $stCur = $pdo->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
                $stCur->execute([':id' => (int) $pedidoId]);
                $existing = $stCur->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $existing = [];
            }

            $isEmpty = function($v): bool {
                if ($v === null) return true;
                if (is_string($v)) return trim($v) === '';
                return false;
            };

            $set = [];
            $params = [];

            if ($colUsuario !== '' && (!array_key_exists($colUsuario, $existing) || $isEmpty($existing[$colUsuario] ?? null))) {
                $set[] = $colUsuario . ' = :uid';
                $params[':uid'] = (int) $usuarioId;
            }
            if ($colTotal !== '' && (!array_key_exists($colTotal, $existing) || $isEmpty($existing[$colTotal] ?? null))) {
                $set[] = $colTotal . ' = :tot';
                $params[':tot'] = $total;
            }
            if ($colMoeda !== '' && (!array_key_exists($colMoeda, $existing) || $isEmpty($existing[$colMoeda] ?? null))) {
                $set[] = $colMoeda . ' = :mo';
                $params[':mo'] = $moeda;
            }
            if ($colStatus !== '' && (!array_key_exists($colStatus, $existing) || $isEmpty($existing[$colStatus] ?? null))) {
                $set[] = $colStatus . ' = :st';
                $params[':st'] = $status;
            }
            if ($colPaymentGateway !== '' && $paymentMethod !== '' && (!array_key_exists($colPaymentGateway, $existing) || $isEmpty($existing[$colPaymentGateway] ?? null))) {
                $set[] = $colPaymentGateway . ' = :pg';
                $params[':pg'] = $paymentMethod;
            }
            if ($colPaymentId !== '' && $paymentId !== '' && (!array_key_exists($colPaymentId, $existing) || $isEmpty($existing[$colPaymentId] ?? null))) {
                $set[] = $colPaymentId . ' = :pid';
                $params[':pid'] = $paymentId;
            }
            if ($colPaymentStatus !== '' && (!array_key_exists($colPaymentStatus, $existing) || $isEmpty($existing[$colPaymentStatus] ?? null))) {
                $set[] = $colPaymentStatus . ' = :pst';
                $params[':pst'] = $status;
            }
            if ($colTracking !== '' && $tracking !== '' && (!array_key_exists($colTracking, $existing) || $isEmpty($existing[$colTracking] ?? null))) {
                $set[] = $colTracking . ' = :trk';
                $params[':trk'] = $tracking;
            }
            if ($colCodigoPedido !== '' && $codigoPedido !== '' && (!array_key_exists($colCodigoPedido, $existing) || $isEmpty($existing[$colCodigoPedido] ?? null))) {
                $set[] = $colCodigoPedido . ' = :cod';
                $params[':cod'] = $codigoPedido;
            }

            if (in_array('updated_at', $colsPedidos, true)) {
                $set[] = 'updated_at = NOW()';
            }

            if (!empty($set)) {
                $sqlUp = 'UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $params[':id'] = (int) $pedidoId;
                $st = $pdo->prepare($sqlUp);
                $st->execute($params);
            }
        } else {
            $cols = [];
            $vals = [];
            $params = [];

            $cols[] = $colUsuario;
            $vals[] = ':uid';
            $params[':uid'] = (int) $usuarioId;

            $cols[] = $colTotal;
            $vals[] = ':tot';
            $params[':tot'] = $total;

            if ($colMoeda !== '') {
                $cols[] = $colMoeda;
                $vals[] = ':mo';
                $params[':mo'] = $moeda;
            }
            if ($colStatus !== '') {
                $cols[] = $colStatus;
                $vals[] = ':st';
                $params[':st'] = $status;
            }
            if ($colPaymentGateway !== '' && $paymentMethod !== '') {
                $cols[] = $colPaymentGateway;
                $vals[] = ':pg';
                $params[':pg'] = $paymentMethod;
            }
            if ($colPaymentId !== '' && $paymentId !== '') {
                $cols[] = $colPaymentId;
                $vals[] = ':pid';
                $params[':pid'] = $paymentId;
            }
            if ($colPaymentStatus !== '') {
                $cols[] = $colPaymentStatus;
                $vals[] = ':pst';
                $params[':pst'] = $status;
            }
            if ($colTracking !== '' && $tracking !== '') {
                $cols[] = $colTracking;
                $vals[] = ':trk';
                $params[':trk'] = $tracking;
            }
            if ($colCodigoPedido !== '' && $codigoPedido !== '') {
                $cols[] = $colCodigoPedido;
                $vals[] = ':cod';
                $params[':cod'] = $codigoPedido;
            }

            $sqlIn = 'INSERT INTO pedidos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $st = $pdo->prepare($sqlIn);
            $st->execute($params);
            $pedidoId = (int) $pdo->lastInsertId();
        }

        if ($pedidoId <= 0) {
            throw new \RuntimeException('Falha ao persistir pedido');
        }

        $this->ensurePedidoMetaTable($pdo);

        $metaPairs = [
            'post_id' => $postId,
            '_order_key' => $orderKey,
            '_old_order_id' => $oldOrderId,
        ];

        foreach ($metaPairs as $mk => $mv) {
            if ($mv === '') continue;
            $this->upsertPedidoMeta($pdo, $pedidoId, $mk, $mv);
        }

        // CSV de pedidos vem 1 linha por item (repete dados do pedido). Para evitar milhares de UPSERTs
        // de meta por pedido, importar os metadados completos do pedido apenas 1x por pedido_id.
        static $pedidoMetaFullImported = [];
        if (!isset($pedidoMetaFullImported[$pedidoId])) {
            foreach ($row as $k => $v) {
                $k = trim((string) $k);
                if ($k === '') continue;
                $vv = trim((string) $v);
                if ($vv === '') continue;
                $this->upsertPedidoMeta($pdo, $pedidoId, $k, $vv);
            }
            $pedidoMetaFullImported[$pedidoId] = true;
        }

        // Itens do pedido (quando o CSV trouxer colunas de produto)
        $produtoIdExt = $getAny(['Produto ID', 'produto_id', 'product_id']);
        $produtoNome = $getAny(['Produto', 'produto', 'nome_produto', 'produto_nome']);
        $produtoNcm = $getAny(['NCM', 'ncm']);
        $produtoRef = $getAny(['Referência', 'Referencia', 'Ref', 'SKU', 'Sku', 'sku']);
        $produtoImg = $getAny(['Imagem', 'imagem', 'Image', 'image']);
        $qtdRaw = $getAny(['Quantidade', 'quantidade', 'qty']);
        $vuRaw = $getAny(['Preço Unitário', 'Preco Unitario', 'preco_unitario', 'valor_unitario', 'price']);
        $subRaw = $getAny(['Subtotal', 'subtotal']);

        $hasItem = ($produtoIdExt !== '' || $produtoNome !== '' || $produtoRef !== '');
        if ($hasItem) {
            $qtd = 0;
            if ($qtdRaw !== '') {
                $qtdNum = preg_replace('/[^0-9]/', '', (string) $qtdRaw);
                if ($qtdNum !== '' && ctype_digit($qtdNum)) {
                    $qtd = (int) $qtdNum;
                }
            }

            $parseMoney = function(string $s): float {
                $s = trim($s);
                if ($s === '') return 0.0;
                $num = str_replace(['R$', 'USD', 'BRL'], '', $s);
                $num = preg_replace('/\s+/', '', (string) $num);
                if (strpos($num, ',') !== false && strpos($num, '.') !== false) {
                    $num = str_replace('.', '', $num);
                    $num = str_replace(',', '.', $num);
                } elseif (strpos($num, ',') !== false) {
                    $num = str_replace(',', '.', $num);
                }
                return is_numeric($num) ? (float) $num : 0.0;
            };

            $valorUnit = $parseMoney($vuRaw);
            $subtotal = $parseMoney($subRaw);
            if ($subtotal <= 0 && $qtd > 0 && $valorUnit >= 0) {
                $subtotal = round($valorUnit * $qtd, 2);
            }

            $produtoIdInt = $this->resolveProdutoIdForPedidoItem($pdo, $produtoIdExt, $produtoRef);
            if ($produtoIdInt > 0 && $qtd > 0) {
                $this->upsertPedidoItem($pdo, $pedidoId, $produtoIdInt, [
                    'sku' => $produtoRef,
                    'nome_produto' => $produtoNome,
                    'ncm' => $produtoNcm,
                    'imagem' => $produtoImg,
                    'quantidade' => $qtd,
                    'valor_unitario' => $valorUnit,
                    'subtotal' => $subtotal,
                ]);
            }
        }
    }

    private function resolveProdutoIdForPedidoItem(\PDO $pdo, string $produtoIdExt, string $skuOrRef): int {
        $produtoIdExt = trim($produtoIdExt);
        $skuOrRef = trim($skuOrRef);

        if ($skuOrRef !== '') {
            try {
                $st = $pdo->prepare('SELECT id FROM produtos WHERE LOWER(sku) = LOWER(?) LIMIT 1');
                $st->execute([$skuOrRef]);
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            } catch (\Exception $e) {
            }
        }

        if ($produtoIdExt !== '' && ctype_digit($produtoIdExt)) {
            $wooId = (int) $produtoIdExt;
            if ($wooId > 0) {
                // Tentativa via produto_meta (import de produtos costuma salvar woo_id)
                try {
                    $st = $pdo->prepare('SELECT produto_id FROM produto_meta WHERE meta_key = :k AND meta_value = :v ORDER BY produto_id DESC LIMIT 1');
                    $st->execute([':k' => 'woo_id', ':v' => (string) $wooId]);
                    $id = (int) ($st->fetchColumn() ?: 0);
                    if ($id > 0) return $id;
                } catch (\Exception $e) {
                }
            }
        }

        return 0;
    }

    private function upsertPedidoItem(\PDO $pdo, int $pedidoId, int $produtoId, array $data): void {
        static $itensTableCache = null;
        static $colsItensCache = null;

        if (!is_string($itensTableCache) || $itensTableCache === '') {
            $itensTableCache = 'pedido_itens';
            try {
                $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
                $st->execute(['pedido_itens']);
                $ok = (bool) $st->fetchColumn();
                if (!$ok) {
                    $itensTableCache = 'pedido_items';
                }
            } catch (\Exception $e) {
                $itensTableCache = 'pedido_items';
            }
        }
        $table = $itensTableCache;

        if (!is_array($colsItensCache)) {
            $colsItensCache = [];
            try {
                $stmt = $pdo->query('DESCRIBE ' . $table);
                $colsItensCache = $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsItensCache = [];
            }
        }
        $colsItens = $colsItensCache;
        if (empty($colsItens)) return;

        // Se já existir item com pedido_id+produto_id, somar quantidade/subtotal.
        try {
            $stSel = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE pedido_id = ? AND produto_id = ? LIMIT 1');
            $stSel->execute([(int) $pedidoId, (int) $produtoId]);
            $exists = (int) ($stSel->fetchColumn() ?: 0);
            if ($exists > 0) {
                $qtdAdd = (int) ($data['quantidade'] ?? 0);
                $subAdd = (float) ($data['subtotal'] ?? 0.0);
                $vu = (float) ($data['valor_unitario'] ?? 0.0);

                $set = [];
                $params = [':id' => (int) $exists];

                $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
                if ($colQtd !== '' && $qtdAdd > 0) {
                    $set[] = $colQtd . ' = ' . $colQtd . ' + :qtd_add';
                    $params[':qtd_add'] = $qtdAdd;
                }

                if (in_array('subtotal', $colsItens, true) && $subAdd > 0) {
                    $set[] = 'subtotal = subtotal + :sub_add';
                    $params[':sub_add'] = $subAdd;
                }

                $colUnit = in_array('preco_unitario', $colsItens, true) ? 'preco_unitario' : (in_array('valor_unitario', $colsItens, true) ? 'valor_unitario' : (in_array('price', $colsItens, true) ? 'price' : (in_array('preco', $colsItens, true) ? 'preco' : '')));
                if ($colUnit !== '' && $vu > 0) {
                    // Só preencher se estiver vazio/0
                    $set[] = $colUnit . " = IF(" . $colUnit . " IS NULL OR " . $colUnit . " = 0, :vu, " . $colUnit . ")";
                    $params[':vu'] = $vu;
                }

                if (!empty($set)) {
                    $stUp = $pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE id = :id');
                    $stUp->execute($params);
                }
                return;
            }
        } catch (\Exception $e) {
        }

        $colPedido = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : '';
        $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
        if ($colPedido === '' || $colProduto === '') return;

        $cols = [$colPedido, $colProduto];
        $vals = [':pedido_id', ':produto_id'];
        $params = [':pedido_id' => (int) $pedidoId, ':produto_id' => (int) $produtoId];

        $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
        if ($colQtd !== '') {
            $cols[] = $colQtd;
            $vals[] = ':qtd';
            $params[':qtd'] = (int) ($data['quantidade'] ?? 0);
        }

        $colNome = in_array('nome_produto', $colsItens, true) ? 'nome_produto' : (in_array('produto_nome', $colsItens, true) ? 'produto_nome' : (in_array('nome', $colsItens, true) ? 'nome' : ''));
        if ($colNome !== '') {
            $cols[] = $colNome;
            $vals[] = ':nome';
            $params[':nome'] = (string) ($data['nome_produto'] ?? '');
        }

        $colSku = in_array('sku', $colsItens, true) ? 'sku' : '';
        if ($colSku !== '') {
            $cols[] = $colSku;
            $vals[] = ':sku';
            $params[':sku'] = (string) ($data['sku'] ?? '');
        }

        $colUnit = in_array('preco_unitario', $colsItens, true) ? 'preco_unitario' : (in_array('valor_unitario', $colsItens, true) ? 'valor_unitario' : (in_array('price', $colsItens, true) ? 'price' : (in_array('preco', $colsItens, true) ? 'preco' : (in_array('preco_unitario', $colsItens, true) ? 'preco_unitario' : ''))));
        if ($colUnit !== '') {
            $cols[] = $colUnit;
            $vals[] = ':vu';
            $params[':vu'] = (float) ($data['valor_unitario'] ?? 0.0);
        }

        $colSub = in_array('subtotal', $colsItens, true) ? 'subtotal' : '';
        if ($colSub !== '') {
            $cols[] = $colSub;
            $vals[] = ':sub';
            $params[':sub'] = (float) ($data['subtotal'] ?? 0.0);
        }

        // Campos extras quando existirem
        if (in_array('ncm', $colsItens, true) && isset($data['ncm'])) {
            $cols[] = 'ncm';
            $vals[] = ':ncm';
            $params[':ncm'] = (string) $data['ncm'];
        }
        if (in_array('imagem', $colsItens, true) && isset($data['imagem'])) {
            $cols[] = 'imagem';
            $vals[] = ':img';
            $params[':img'] = (string) $data['imagem'];
        }

        try {
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $st = $pdo->prepare($sql);
            $st->execute($params);
        } catch (\Exception $e) {
            // Não derrubar a importação do pedido por causa de um item
        }
    }

    private function ensurePedidoMetaTable(\PDO $pdo): void {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['pedido_meta']);
            $ok = (bool) $st->fetchColumn();
            if ($ok) {
                return;
            }
        } catch (\Exception $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS pedido_meta (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            meta_key VARCHAR(191) NOT NULL,
            meta_value LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_pedido_meta (pedido_id, meta_key),
            KEY idx_meta_key (meta_key),
            KEY idx_pedido_id (pedido_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function upsertPedidoMeta(\PDO $pdo, int $pedidoId, string $key, string $value): void {
        $key = trim($key);
        if ($key === '') return;
        try {
            $stSel = $pdo->prepare('SELECT meta_value FROM pedido_meta WHERE pedido_id = :pid AND meta_key = :k LIMIT 1');
            $stSel->execute([':pid' => (int) $pedidoId, ':k' => $key]);
            $curr = $stSel->fetchColumn();

            $isEmpty = function($v): bool {
                if ($v === null) return true;
                if (is_string($v)) return trim($v) === '';
                return false;
            };

            if ($curr === false) {
                $st = $pdo->prepare('INSERT INTO pedido_meta (pedido_id, meta_key, meta_value) VALUES (:pid, :k, :v)');
                $st->execute([':pid' => (int) $pedidoId, ':k' => $key, ':v' => $value]);
                return;
            }

            if ($isEmpty($curr) && trim((string) $value) !== '') {
                $stUp = $pdo->prepare('UPDATE pedido_meta SET meta_value = :v, updated_at = NOW() WHERE pedido_id = :pid AND meta_key = :k');
                $stUp->execute([':pid' => (int) $pedidoId, ':k' => $key, ':v' => $value]);
            }
        } catch (\Exception $e) {
        }
    }

    private function findPedidoIdByMeta(\PDO $pdo, string $metaKey, string $metaValue): int {
        $metaKey = trim($metaKey);
        $metaValue = trim($metaValue);
        if ($metaKey === '' || $metaValue === '') return 0;
        try {
            $this->ensurePedidoMetaTable($pdo);
            $st = $pdo->prepare('SELECT pedido_id FROM pedido_meta WHERE meta_key = :k AND meta_value = :v ORDER BY pedido_id DESC LIMIT 1');
            $st->execute([':k' => $metaKey, ':v' => $metaValue]);
            return (int) ($st->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function findUsuarioIdById(\PDO $pdo, int $id): int {
        if ($id <= 0) return 0;
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? LIMIT 1');
        $st->execute([(int) $id]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function findUsuarioIdByEmail(\PDO $pdo, string $email): int {
        $email = trim($email);
        if ($email === '') return 0;
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$email]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function getPedidosImportJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnImportarPedidosCsv');
    const fileInput = document.getElementById('pedidos_import_csv');
    const wrap = document.getElementById('pedidosImportProgressWrap');
    const bar = document.getElementById('pedidosImportProgressBar');
    const percentEl = document.getElementById('pedidosImportProgressPercent');
    const labelEl = document.getElementById('pedidosImportProgressLabel');
    const statsEl = document.getElementById('pedidosImportProgressStats');

    if (!btn || !fileInput || !wrap || !bar || !percentEl || !labelEl || !statsEl) return;

    let running = false;

    function setProgress(processed, total, okCount, failCount, label){
        const t = (typeof total === 'number' && total > 0) ? total : 0;
        const p = (typeof processed === 'number' && processed > 0) ? processed : 0;
        let pct = (t > 0) ? Math.floor((p / t) * 100) : 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        percentEl.textContent = pct + '%';
        labelEl.textContent = label || 'Processando...';
        statsEl.textContent = 'Processados: ' + p + ' / ' + t + ' | OK: ' + (okCount||0) + ' | Falhas: ' + (failCount||0);
    }

    async function iniciarImportacao(file){
        const fd = new FormData();
        fd.append('pedidos_import_csv', file);
        const resp = await fetch('/admin/pedidos/importar/iniciar', { method: 'POST', body: fd });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao iniciar a importação.');
        }
        return json;
    }

    async function processarLote(token, batchSize){
        const fd = new URLSearchParams();
        fd.set('token', token);
        fd.set('batch', String(batchSize || 200));
        const resp = await fetch('/admin/pedidos/importar/processar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: fd.toString()
        });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao processar lote.');
        }
        return json;
    }

    btn.addEventListener('click', async function(){
        if (running) return;
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert('Selecione um arquivo CSV primeiro.');
            return;
        }

        running = true;
        btn.disabled = true;
        wrap.style.display = '';
        setProgress(0, 0, 0, 0, 'Enviando arquivo...');

        try {
            const init = await iniciarImportacao(file);
            const token = init.token;
            const total = init.total || 0;
            let last = init;

            setProgress(0, total, 0, 0, 'Importação iniciada...');

            while (!last.done) {
                last = await processarLote(token, 200);
                setProgress(last.processed || 0, last.total || total, last.okCount || 0, last.failCount || 0, 'Processando em lotes...');
            }

            setProgress(last.processed || total, last.total || total, last.okCount || 0, last.failCount || 0, 'Finalizado');
        } catch (e) {
            alert(e && e.message ? e.message : 'Erro na importação.');
            labelEl.textContent = 'Erro';
        } finally {
            running = false;
            btn.disabled = false;
        }
    });
});
</script>
JS;
    }
    
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $status = $request->getParam('status', '');

            $colsPedidos = [];
            try {
                $stmtColsP = $pdo->query('DESCRIBE pedidos');
                $colsPedidos = $stmtColsP ? ($stmtColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $pickCol = function (array $cols, array $candidates): ?string {
                foreach ($candidates as $c) {
                    if (in_array($c, $cols, true)) {
                        return $c;
                    }
                }
                return null;
            };

            // Campos opcionais para enriquecer a listagem (sem depender de schema fixo)
            $colPais = $pickCol($colsPedidos, ['pais', 'country', 'pais_entrega', 'country_entrega', 'shipping_country', 'pais_destino', 'pais_entrega_nome']);
            $colOrigem = $pickCol($colsPedidos, ['origem', 'canal', 'channel', 'source', 'utm_source', 'pedido_origem']);
            $colManual = $pickCol($colsPedidos, ['pedido_manual', 'manual', 'is_manual', 'criado_manual', 'admin_criou', 'criado_por_admin']);
            $colNumero = $pickCol($colsPedidos, ['numero_pedido', 'order_number', 'numero', 'codigo']);
            $temDeletedAt = in_array('deleted_at', $colsPedidos, true);

            $colsUsuarios = [];
            try {
                $stmtColsU = $pdo->query('DESCRIBE usuarios');
                $colsUsuarios = $stmtColsU ? ($stmtColsU->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsUsuarios = [];
            }
            $colUserName = $pickCol($colsUsuarios, ['name', 'nome', 'full_name', 'nome_completo']);
            if (!$colUserName) {
                $colUserName = 'name';
            }
            $colUserEmail = $pickCol($colsUsuarios, ['email', 'mail']);
            if (!$colUserEmail) {
                $colUserEmail = 'email';
            }

            // Fallback de taxa USD->BRL para exibição, quando o pedido não tiver taxa_conversao persistida
            $rateUSDBRL = 5.5;
            try {
                $stmtTx = $pdo->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                $stmtTx->execute();
                $rowTx = $stmtTx->fetch(\PDO::FETCH_ASSOC);
                $tx = (float) ($rowTx['taxa_conversao'] ?? 0);
                if ($tx > 1.01) {
                    $rateUSDBRL = $tx;
                }
            } catch (\Exception $e) {
            }
            
            $sql = "SELECT p.*, u." . $colUserName . " as cliente_nome, u." . $colUserEmail . " as cliente_email FROM pedidos p LEFT JOIN usuarios u ON p." . (in_array("usuario_id", $colsPedidos, true) ? "usuario_id" : "cliente_id") . " = u.id WHERE 1=1";
            $params = [];

            if ($temDeletedAt) {
                $sql .= " AND p.deleted_at IS NULL";
            }
            
            if (!empty($busca)) {
                $buscaRaw = trim((string) $busca);
                $buscaDigits = preg_replace('/\D+/', '', $buscaRaw);
                $buscaInt = ($buscaDigits !== '') ? (int) $buscaDigits : 0;

                $searchParts = [];
                if ($buscaInt > 0) {
                    $searchParts[] = 'p.id = :busca_int';
                    $searchParts[] = 'CAST(p.id AS CHAR) LIKE :busca_int_like';
                }
                $searchParts[] = 'CAST(p.id AS CHAR) LIKE :busca';
                $searchParts[] = 'u.' . $colUserName . ' LIKE :busca';
                $searchParts[] = 'u.' . $colUserEmail . ' LIKE :busca';
                if ($colNumero) {
                    $searchParts[] = 'p.' . $colNumero . ' LIKE :busca';
                }
                if (in_array('codigo_pedido', $colsPedidos, true)) {
                    $searchParts[] = 'p.codigo_pedido LIKE :busca';
                }
                if (in_array('numero_pedido', $colsPedidos, true)) {
                    $searchParts[] = 'p.numero_pedido LIKE :busca';
                }

                $sql .= ' AND (' . implode(' OR ', $searchParts) . ')';
                $params[':busca'] = "%{$busca}%";
                if ($buscaInt > 0) {
                    $params[':busca_int'] = $buscaInt;
                    $params[':busca_int_like'] = "%{$buscaInt}%";
                }
            }
            if (!empty($status)) {
                if ($status === 'aguardando_comprovante') {
                    // Filtro especial: pedidos offline sem comprovante
                    $hasFP = in_array('forma_pagamento', $colsPedidos, true);
                    if ($hasFP) {
                        $sql .= " AND p.forma_pagamento IN ('nomad_transferencia','appmax_pix','pagdev')";
                        // Excluir pedidos que já têm comprovante OK
                        $hasDocsTableFilter = false;
                        try {
                            $stChk = $pdo->prepare('SHOW TABLES LIKE ?');
                            $stChk->execute(['pedidos_pagamento_documentos']);
                            $hasDocsTableFilter = (bool) $stChk->fetchColumn();
                        } catch (\Exception $e) {}
                        if ($hasDocsTableFilter) {
                            $sql .= " AND p.id NOT IN (SELECT pedido_id FROM pedidos_pagamento_documentos WHERE status = 'ok' AND arquivo_path IS NOT NULL AND arquivo_path != '')";
                        }
                    }
                } else {
                    $sql .= " AND p.status = :status";
                    $params[':status'] = $status;
                }
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Fallback: preencher cliente_nome de colunas do pedido quando JOIN não trouxe
            if (is_array($pedidos)) {
                foreach ($pedidos as &$_p) {
                    if (empty($_p['cliente_nome']) || trim((string)$_p['cliente_nome']) === '') {
                        foreach (['cliente_nome','nome','customer_name'] as $_nc) {
                            if (!empty($_p[$_nc]) && trim((string)$_p[$_nc]) !== '') {
                                $_p['cliente_nome'] = (string)$_p[$_nc];
                                break;
                            }
                        }
                    }
                    if (empty($_p['cliente_email']) || trim((string)$_p['cliente_email']) === '') {
                        foreach (['cliente_email','email','customer_email'] as $_ec) {
                            if (!empty($_p[$_ec]) && trim((string)$_p[$_ec]) !== '') {
                                $_p['cliente_email'] = (string)$_p[$_ec];
                                break;
                            }
                        }
                    }
                }
                unset($_p);
            }

            $warningsMap = [];
            try {
                $pedidoIds = [];
                if (is_array($pedidos)) {
                    foreach ($pedidos as $pp) {
                        if (!isset($pp['id'])) continue;
                        $pedidoIds[] = (int) $pp['id'];
                    }
                }
                $warningsMap = $this->getPedidosMissingDataWarnings($pdo, $pedidoIds);
            } catch (\Exception $e) {
                $warningsMap = [];
            }

            // Detectar pedidos offline aguardando comprovante
            $aguardandoComprovanteMap = [];
            try {
                $offlineMethods = ['nomad_transferencia', 'appmax_pix', 'pagdev'];
                $hasFP = in_array('forma_pagamento', $colsPedidos, true);
                if ($hasFP && !empty($pedidoIds)) {
                    // Verificar se tabela de documentos existe
                    $hasDocsTable = false;
                    try {
                        $stCheck = $pdo->prepare('SHOW TABLES LIKE ?');
                        $stCheck->execute(['pedidos_pagamento_documentos']);
                        $hasDocsTable = (bool) $stCheck->fetchColumn();
                    } catch (\Exception $e) {}

                    $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
                    $fpPlaceholders = implode(',', array_fill(0, count($offlineMethods), '?'));

                    // Buscar pedidos offline da página atual
                    $stOff = $pdo->prepare("SELECT id, forma_pagamento FROM pedidos WHERE id IN ({$placeholders}) AND forma_pagamento IN ({$fpPlaceholders})");
                    $stOff->execute(array_merge($pedidoIds, $offlineMethods));
                    $offlinePedidos = $stOff->fetchAll(\PDO::FETCH_ASSOC);

                    if (!empty($offlinePedidos)) {
                        $offlineIds = array_column($offlinePedidos, 'id');
                        // Verificar quais já têm AMBOS os comprovantes OK (produtos + taxas)
                        // Se a coluna tipo não existir, basta ter 1 comprovante OK
                        $hasColTipo = false;
                        if ($hasDocsTable) {
                            try {
                                $stColCheck = $pdo->prepare('SHOW COLUMNS FROM pedidos_pagamento_documentos LIKE ?');
                                $stColCheck->execute(['tipo']);
                                $hasColTipo = (bool) $stColCheck->fetchColumn();
                            } catch (\Exception $e) {}
                        }

                        $docsOkMap = []; // pedido_id => ['produtos' => bool, 'taxas' => bool]
                        if ($hasDocsTable && !empty($offlineIds)) {
                            $phOff = implode(',', array_fill(0, count($offlineIds), '?'));
                            if ($hasColTipo) {
                                $stDocs = $pdo->prepare("SELECT pedido_id, tipo FROM pedidos_pagamento_documentos WHERE pedido_id IN ({$phOff}) AND status = 'ok' AND arquivo_path IS NOT NULL AND arquivo_path != ''");
                                $stDocs->execute($offlineIds);
                                foreach ($stDocs->fetchAll(\PDO::FETCH_ASSOC) as $dRow) {
                                    $dPid = (int) $dRow['pedido_id'];
                                    $dTipo = (string) ($dRow['tipo'] ?? 'produtos');
                                    if (!isset($docsOkMap[$dPid])) $docsOkMap[$dPid] = [];
                                    $docsOkMap[$dPid][$dTipo] = true;
                                }
                            } else {
                                $stDocs = $pdo->prepare("SELECT pedido_id FROM pedidos_pagamento_documentos WHERE pedido_id IN ({$phOff}) AND status = 'ok' AND arquivo_path IS NOT NULL AND arquivo_path != ''");
                                $stDocs->execute($offlineIds);
                                foreach ($stDocs->fetchAll(\PDO::FETCH_COLUMN) as $dPid) {
                                    $docsOkMap[(int)$dPid] = ['produtos' => true];
                                }
                            }
                        }
                        foreach ($offlinePedidos as $op) {
                            $opId = (int) $op['id'];
                            $okP = !empty($docsOkMap[$opId]['produtos']);
                            $okT = !$hasColTipo || !empty($docsOkMap[$opId]['taxas']);
                            if (!($okP && $okT)) {
                                $aguardandoComprovanteMap[$opId] = true;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $aguardandoComprovanteMap = [];
            }

            // Normalizar moeda/total para exibição (sem alterar o banco)
            if (is_array($pedidos) && !empty($pedidos)) {
                foreach ($pedidos as &$p) {
                    $moeda = strtoupper(trim((string) ($p['moeda'] ?? ($p['currency'] ?? 'BRL'))));
                    if ($moeda === '') {
                        $moeda = 'BRL';
                    }
                    $p['moeda'] = $moeda;

                    $taxaConversao = null;
                    foreach (['taxa_conversao', 'exchange_rate', 'conversion_rate'] as $c) {
                        if (array_key_exists($c, $p)) {
                            $taxaConversao = (float) ($p[$c] ?? 0);
                            break;
                        }
                    }
                    if ($taxaConversao === null || $taxaConversao <= 0) {
                        $taxaConversao = 1.0;
                    }
                    if ($moeda === 'BRL' && $taxaConversao <= 1.01 && $rateUSDBRL > 1.01) {
                        $taxaConversao = $rateUSDBRL;
                    }
                    $p['taxa_conversao'] = $taxaConversao;

                    // Total base usado pela tela
                    $totalField = '';
                    foreach (['total', 'valor_total', 'amount', 'valor'] as $c) {
                        if (array_key_exists($c, $p)) {
                            $totalField = $c;
                            break;
                        }
                    }
                    if ($totalField === '') {
                        continue;
                    }

                    if ($moeda === 'BRL' && $taxaConversao > 1.01) {
                        // Preferir total BRL quando existir
                        $valorTotalBRL = null;
                        foreach (['valor_total_brl', 'total_brl'] as $c) {
                            if (array_key_exists($c, $p)) {
                                $v = (float) ($p[$c] ?? 0);
                                if ($v > 0) {
                                    $valorTotalBRL = $v;
                                    break;
                                }
                            }
                        }

                        $baseTotal = (float) ($p[$totalField] ?? 0);
                        $moedaOriginal = strtoupper(trim((string) ($p['moeda_original'] ?? '')));
                        $deveConverter = ($moedaOriginal === 'USD');

                        if ($valorTotalBRL !== null) {
                            $p[$totalField] = $valorTotalBRL;
                            $p['total'] = $valorTotalBRL;
                        } elseif ($deveConverter) {
                            $conv = $baseTotal * $taxaConversao;
                            $p[$totalField] = $conv;
                            $p['total'] = $conv;
                        } else {
                            // Pedido já está em BRL; não converter novamente.
                            $p['total'] = $baseTotal;
                        }
                    } else {
                        // Garantir que a view tenha total preenchido
                        if (!array_key_exists('total', $p) && $totalField !== '') {
                            $p['total'] = (float) ($p[$totalField] ?? 0);
                        }
                    }

                    // Normalizar/garantir alguns campos para exibição
                    if (!array_key_exists('numero_pedido', $p) && $colNumero) {
                        $p['numero_pedido'] = (string) ($p[$colNumero] ?? '');
                    }
                }
                unset($p);
            }
            
            $sqlTotal = "SELECT COUNT(*) as total FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE 1=1";
            $paramsTotal = [];
            if ($temDeletedAt) {
                $sqlTotal .= " AND p.deleted_at IS NULL";
            }
            if (!empty($busca)) {
                $buscaRaw = trim((string) $busca);
                $buscaDigits = preg_replace('/\D+/', '', $buscaRaw);
                $buscaInt = ($buscaDigits !== '') ? (int) $buscaDigits : 0;

                $searchParts = [];
                if ($buscaInt > 0) {
                    $searchParts[] = 'p.id = :busca_int';
                    $searchParts[] = 'CAST(p.id AS CHAR) LIKE :busca_int_like';
                }
                $searchParts[] = 'CAST(p.id AS CHAR) LIKE :busca';
                $searchParts[] = 'u.' . $colUserName . ' LIKE :busca';
                $searchParts[] = 'u.' . $colUserEmail . ' LIKE :busca';
                if ($colNumero) {
                    $searchParts[] = 'p.' . $colNumero . ' LIKE :busca';
                }
                if (in_array('codigo_pedido', $colsPedidos, true)) {
                    $searchParts[] = 'p.codigo_pedido LIKE :busca';
                }
                if (in_array('numero_pedido', $colsPedidos, true)) {
                    $searchParts[] = 'p.numero_pedido LIKE :busca';
                }

                $sqlTotal .= ' AND (' . implode(' OR ', $searchParts) . ')';
                $paramsTotal[':busca'] = "%{$busca}%";
                if ($buscaInt > 0) {
                    $paramsTotal[':busca_int'] = $buscaInt;
                    $paramsTotal[':busca_int_like'] = "%{$buscaInt}%";
                }
            }
            if (!empty($status)) {
                if ($status === 'aguardando_comprovante') {
                    $hasFP = in_array('forma_pagamento', $colsPedidos, true);
                    if ($hasFP) {
                        $sqlTotal .= " AND p.forma_pagamento IN ('nomad_transferencia','appmax_pix','pagdev')";
                        $hasDocsTableTotal = false;
                        try {
                            $stChk2 = $pdo->prepare('SHOW TABLES LIKE ?');
                            $stChk2->execute(['pedidos_pagamento_documentos']);
                            $hasDocsTableTotal = (bool) $stChk2->fetchColumn();
                        } catch (\Exception $e) {}
                        if ($hasDocsTableTotal) {
                            $sqlTotal .= " AND p.id NOT IN (SELECT pedido_id FROM pedidos_pagamento_documentos WHERE status = 'ok' AND arquivo_path IS NOT NULL AND arquivo_path != '')";
                        }
                    }
                } else {
                    $sqlTotal .= " AND p.status = :status";
                    $paramsTotal[':status'] = $status;
                }
            }
            
            $stmtTotal = $pdo->prepare($sqlTotal);
            foreach ($paramsTotal as $key => $value) $stmtTotal->bindValue($key, $value);
            $stmtTotal->execute();
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
        } catch (\Exception $e) {
            $pedidos = [];
            $total = 0;
            $totalPaginas = 0;
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .order-card { 
            transition: none;
            border-left: 4px solid #dee2e6;
        }
        .order-card.needs-review {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.08);
            border-color: rgba(255, 193, 7, 0.35) !important;
        }
        .order-card .badge {
            font-size: 1.2rem;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('pedidos');

        $exportUrl = '/admin/pedidos/export-xlsx';
        $exportQuery = [];
        if (trim((string) $busca) !== '') {
            $exportQuery['busca'] = (string) $busca;
        }
        if (trim((string) $status) !== '') {
            $exportQuery['status'] = (string) $status;
        }
        if (!empty($exportQuery)) {
            $exportUrl .= '?' . http_build_query($exportQuery);
        }
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pedidos (' . $total . ')</h1>
                    <div>
                        <a href="/admin/pedidos/novo-manual" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>Novo Pedido Manual
                        </a>
                        <a href="/admin/pedidos/comissoes" class="btn btn-outline-primary me-2">
                            <i class="fas fa-percentage me-1"></i>Minhas Comissões
                        </a>
                        <a href="/admin/pedidos/lixeira" class="btn btn-outline-danger me-2">
                            <i class="fas fa-trash me-1"></i>Lixeira
                        </a>
                        <a class="btn btn-success me-2" href="' . htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') . '">
                            <i class="fas fa-download me-1"></i>Exportar XLSX
                        </a>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>
                
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="busca" placeholder="Buscar pedido, cliente ou email..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">Todos status</option>
                            ' . $this->buildStatusOptions($status) . '
                            <option value="aguardando_comprovante" ' . ($status === 'aguardando_comprovante' ? 'selected' : '') . '>Aguardando Comprovante</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Filtrar</button>
                    </div>
                </form>
                
                <!-- Abas de Pedidos por Moeda -->
                <div class="mb-3">
                    <ul class="nav nav-pills" id="pedidosTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pedidos-todos-tab" data-bs-toggle="pill" data-bs-target="#pedidos-todos" type="button">
                                <i class="fas fa-list"></i> Todos os Pedidos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pedidos-dolar-tab" data-bs-toggle="pill" data-bs-target="#pedidos-dolar" type="button">
                                <i class="fas fa-dollar-sign"></i> Pagamentos em Dólar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pedidos-real-tab" data-bs-toggle="pill" data-bs-target="#pedidos-real" type="button">
                                <i class="fas fa-currency-brl"></i> Pagamentos em Reais
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="pedidosTabContent">
                        <div class="tab-pane fade show active" id="pedidos-todos" role="tabpanel">
                            <div class="row">';
                
                foreach ($pedidos as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    $statusIcon = $this->getStatusIcon($pedido['status']);
                    $statusColor = $this->getStatusColor($pedido['status']);

                    $pid = (int) ($pedido['id'] ?? 0);
                    $warn = ($pid > 0 && isset($warningsMap[$pid]) && is_array($warningsMap[$pid]))
                        ? $warningsMap[$pid]
                        : ['missing_cost' => false, 'missing_ncm' => false, 'cpf_invalid' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                    $needsReview = (!empty($warn['missing_cost']) || !empty($warn['missing_ncm']) || !empty($warn['cpf_invalid']) || !empty($warn['valor_informado_cliente']));
                    $reviewBadges = '';
                    if ($needsReview) {
                        if (!empty($warn['missing_cost'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark me-2">Custo 0/vazio</span>';
                        }
                        if (!empty($warn['missing_ncm'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark">Sem NCM</span>';
                        }
                        if (!empty($warn['cpf_invalid'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark ms-2">CPF inválido</span>';
                        }
                        if (!empty($warn['valor_informado_cliente'])) {
                            $reviewBadges .= '<span class="badge bg-danger ms-2"><i class="fas fa-exclamation-circle me-1"></i>Valor cliente</span>';
                        }
                    }
                    $aguardandoComprovante = !empty($aguardandoComprovanteMap[$pid]);
                    if ($aguardandoComprovante) {
                        $reviewBadges .= '<span class="badge bg-info text-dark ms-2"><i class="fas fa-file-upload me-1"></i>Aguardando comprovante</span>';
                    }
                    
                    $paisTxt = '';
                    if (!empty($colPais) && array_key_exists($colPais, $pedido)) {
                        $paisTxt = trim((string) ($pedido[$colPais] ?? ''));
                    }
                    if ($paisTxt === '' && array_key_exists('pais', $pedido)) {
                        $paisTxt = trim((string) ($pedido['pais'] ?? ''));
                    }
                    if ($paisTxt === '') {
                        $paisTxt = 'Brazil';
                    }

                    $paisLower = strtolower($paisTxt);
                    $paisIsBrazil = ($paisLower === 'brazil' || $paisLower === 'brasil');
                    $paisStyle = $paisIsBrazil
                        ? 'color:#14532d;font-weight:700;'
                        : 'color:#b91c1c;font-weight:700;';

                    $isManualBool = false;
                    $manualTxt = '';
                    if (!empty($colManual) && array_key_exists($colManual, $pedido)) {
                        $v = $pedido[$colManual];
                        $isManualBool = ($v === 1 || $v === '1' || $v === true || $v === 'true');
                        $manualTxt = $isManualBool ? 'Sim' : 'Não';
                    } elseif (!empty($pedido['origem_pedido'])) {
                        $isManualBool = (strtolower((string) $pedido['origem_pedido']) === 'manual');
                        $manualTxt = $isManualBool ? 'Sim' : 'Não';
                    }
                    $origemTxt = $isManualBool ? 'Manual' : 'Orgânica';

                    echo '<div class="col-12 mb-3">
                        <div class="card order-card' . ($needsReview ? ' needs-review border border-warning' : '') . '">
                            <div class="card-body">
                                <div class="row align-items-center gy-3">
                                    <div class="col-6 col-lg-2">
                                        <div class="text-center">
                                            <div class="badge bg-' . $statusColor . ' fs-6 mb-2">
                                                <i class="' . $statusIcon . '"></i>
                                            </div>
                                            <h6 class="mb-0">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h6>
                                            <small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <h6 class="mb-1">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                        <p class="text-muted small mb-1">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                        <p class="text-muted small mb-0">' . htmlspecialchars((string) ($pedido['numero_pedido'] ?? '')) . '</p>
                                        ' . ($reviewBadges !== '' ? ('<div class="mt-2">' . $reviewBadges . '</div>' . ($needsReview ? '<div class="text-muted small" style="margin-top:6px;">Precisa revisar itens do pedido (editar produto)</div>' : '')) : '') . '
                                        <div class="text-muted small mt-1">
                                            <span class="me-3" style="' . $paisStyle . '">' . htmlspecialchars($paisTxt) . '</span>
                                            <span class="me-3">UID: <strong>' . (int) ($pedido['usuario_id'] ?? 0) . '</strong></span>
                                            <span class="me-3">Origem: <strong>' . htmlspecialchars($origemTxt) . '</strong></span>' . $this->getCarneBadgeHtml($pedido) . '
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="text-center">
                                            <h5 class="mb-0 text-primary text-nowrap">' . $this->formatarMoeda($pedido['total'], $pedido['moeda']) . '</h5>
                                            <small class="text-muted">Total do Pedido</small>
                                            <div class="mt-1"><span class="badge ' . (strtoupper(trim((string)($pedido['moeda'] ?? ''))) === 'BRL' ? 'bg-success' : 'bg-info') . '" style="font-size:.65rem;">' . (strtoupper(trim((string)($pedido['moeda'] ?? ''))) === 'BRL' ? 'Pago em R$' : 'Pago em US$') . '</span></div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-3">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalLixeiraPedido" data-pedido-id="' . (int) $pedido['id'] . '">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <select class="form-select form-select-sm" style="width: auto; min-width: 120px;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                ' . $this->buildStatusOptions((string)($pedido['status'] ?? '')) . '
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pedidos)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pedido encontrado</h5>
                    </div>';
                }
                
                echo '</div>
                            </div>
                            
                            <!-- Aba de Pedidos em Dólar -->
                            <div class="tab-pane fade" id="pedidos-dolar" role="tabpanel">
                                <div class="row">';
                
                // Filtrar pedidos em USD
                $pedidosUSD = array_filter($pedidos, function($pedido) {
                    return $pedido['moeda'] === 'USD';
                });
                
                foreach ($pedidosUSD as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    $statusIcon = $this->getStatusIcon($pedido['status']);
                    $statusColor = $this->getStatusColor($pedido['status']);

                    $pid = (int) ($pedido['id'] ?? 0);
                    $warn = ($pid > 0 && isset($warningsMap[$pid]) && is_array($warningsMap[$pid]))
                        ? $warningsMap[$pid]
                        : ['missing_cost' => false, 'missing_ncm' => false, 'cpf_invalid' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                    $needsReview = (!empty($warn['missing_cost']) || !empty($warn['missing_ncm']) || !empty($warn['cpf_invalid']) || !empty($warn['valor_informado_cliente']));
                    $reviewBadges = '';
                    if ($needsReview) {
                        if (!empty($warn['missing_cost'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark me-2">Custo 0/vazio</span>';
                        }
                        if (!empty($warn['missing_ncm'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark">Sem NCM</span>';
                        }
                        if (!empty($warn['cpf_invalid'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark ms-2">CPF inválido</span>';
                        }
                        if (!empty($warn['valor_informado_cliente'])) {
                            $reviewBadges .= '<span class="badge bg-danger ms-2"><i class="fas fa-exclamation-circle me-1"></i>Valor cliente</span>';
                        }
                    }
                    $aguardandoComprovante = !empty($aguardandoComprovanteMap[$pid]);
                    if ($aguardandoComprovante) {
                        $reviewBadges .= '<span class="badge bg-info text-dark ms-2"><i class="fas fa-file-upload me-1"></i>Aguardando comprovante</span>';
                    }

                    $paisTxt = '';
                    if (!empty($colPais) && array_key_exists($colPais, $pedido)) {
                        $paisTxt = trim((string) ($pedido[$colPais] ?? ''));
                    }
                    if ($paisTxt === '' && array_key_exists('pais', $pedido)) {
                        $paisTxt = trim((string) ($pedido['pais'] ?? ''));
                    }
                    if ($paisTxt === '') {
                        $paisTxt = 'Brazil';
                    }

                    $paisLower = strtolower($paisTxt);
                    $paisIsBrazil = ($paisLower === 'brazil' || $paisLower === 'brasil');
                    $paisStyle = $paisIsBrazil
                        ? 'color:#14532d;font-weight:700;'
                        : 'color:#b91c1c;font-weight:700;';

                    $isManualBool = false;
                    $manualTxt = '';
                    if (!empty($colManual) && array_key_exists($colManual, $pedido)) {
                        $v = $pedido[$colManual];
                        $isManualBool = ($v === 1 || $v === '1' || $v === true || $v === 'true');
                        $manualTxt = $isManualBool ? 'Sim' : 'Não';
                    } elseif (!empty($pedido['origem_pedido'])) {
                        $isManualBool = (strtolower((string) $pedido['origem_pedido']) === 'manual');
                        $manualTxt = $isManualBool ? 'Sim' : 'Não';
                    }
                    $origemTxt = $isManualBool ? 'Manual' : 'Orgânica';

                    echo '<div class="col-12 mb-3">
                        <div class="card order-card' . ($needsReview ? ' needs-review border border-warning' : '') . '">
                            <div class="card-body">
                                <div class="row align-items-center gy-3">
                                    <div class="col-6 col-lg-2">
                                        <div class="text-center">
                                            <div class="badge bg-' . $statusColor . ' fs-6 mb-2">
                                                <i class="' . $statusIcon . '"></i>
                                            </div>
                                            <h6 class="mb-0">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h6>
                                            <small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <h6 class="mb-1">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                        <p class="text-muted small mb-1">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                        <p class="text-muted small mb-0">' . htmlspecialchars((string) ($pedido['numero_pedido'] ?? '')) . '</p>
                                        ' . ($reviewBadges !== '' ? ('<div class="mt-2">' . $reviewBadges . '</div>' . ($needsReview ? '<div class="text-muted small" style="margin-top:6px;">Precisa revisar itens do pedido (editar produto)</div>' : '')) : '') . '
                                        <div class="text-muted small mt-1">
                                            <span class="me-3" style="' . $paisStyle . '">' . htmlspecialchars($paisTxt) . '</span>
                                            <span class="me-3">UID: <strong>' . (int) ($pedido['usuario_id'] ?? 0) . '</strong></span>
                                            <span class="me-3">Origem: <strong>' . htmlspecialchars($origemTxt) . '</strong></span>' . $this->getCarneBadgeHtml($pedido) . '
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="text-center">
                                            <h5 class="mb-0 text-success text-nowrap">$ ' . number_format((float) ($pedido['total'] ?? 0), 2, '.', ',') . '</h5>
                                            <small class="text-muted">Total (USD)</small>
                                            ' . (((float) ($pedido['imposto_local'] ?? 0)) > 0 ? '<div class="mt-1"><span class="badge" style="background:rgba(245,158,11,.15);color:#92400e;border:1px solid rgba(245,158,11,.3);font-size:.7rem;">Imposto local</span></div>' : '') . '
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-3">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalLixeiraPedido" data-pedido-id="' . (int) $pedido['id'] . '">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <select class="form-select form-select-sm" style="width: auto; min-width: 120px;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                ' . $this->buildStatusOptions((string)($pedido['status'] ?? '')) . '
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pedidosUSD)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-dollar-sign fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pedido em dólar encontrado</h5>
                    </div>';
                }
                
                echo '</div>
                            </div>
                            
                            <!-- Aba de Pedidos em Real -->
                            <div class="tab-pane fade" id="pedidos-real" role="tabpanel">
                                <div class="row">';
                
                $pedidosBRL = array_filter($pedidos, function($pedido) {
                    return $pedido['moeda'] === 'BRL';
                });
                
                foreach ($pedidosBRL as $pedido) {
                    $statusClass = 'status-' . $pedido['status'];
                    $statusIcon = $this->getStatusIcon($pedido['status']);
                    $statusColor = $this->getStatusColor($pedido['status']);

                    $pid = (int) ($pedido['id'] ?? 0);
                    $warn = ($pid > 0 && isset($warningsMap[$pid]) && is_array($warningsMap[$pid]))
                        ? $warningsMap[$pid]
                        : ['missing_cost' => false, 'missing_ncm' => false, 'cpf_invalid' => false, 'missing_cost_count' => 0, 'missing_ncm_count' => 0];
                    $needsReview = (!empty($warn['missing_cost']) || !empty($warn['missing_ncm']) || !empty($warn['cpf_invalid']) || !empty($warn['valor_informado_cliente']));
                    $reviewBadges = '';
                    if ($needsReview) {
                        if (!empty($warn['missing_cost'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark me-2">Custo 0/vazio</span>';
                        }
                        if (!empty($warn['missing_ncm'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark">Sem NCM</span>';
                        }
                        if (!empty($warn['cpf_invalid'])) {
                            $reviewBadges .= '<span class="badge bg-warning text-dark ms-2">CPF inválido</span>';
                        }
                        if (!empty($warn['valor_informado_cliente'])) {
                            $reviewBadges .= '<span class="badge bg-danger ms-2"><i class="fas fa-exclamation-circle me-1"></i>Valor cliente</span>';
                        }
                    }
                    $aguardandoComprovante = !empty($aguardandoComprovanteMap[$pid]);
                    if ($aguardandoComprovante) {
                        $reviewBadges .= '<span class="badge bg-info text-dark ms-2"><i class="fas fa-file-upload me-1"></i>Aguardando comprovante</span>';
                    }

                    $paisTxt = '';
                    if (!empty($colPais) && array_key_exists($colPais, $pedido)) {
                        $paisTxt = trim((string) ($pedido[$colPais] ?? ''));
                    }
                    if ($paisTxt === '' && array_key_exists('pais', $pedido)) {
                        $paisTxt = trim((string) ($pedido['pais'] ?? ''));
                    }
                    if ($paisTxt === '') {
                        $paisTxt = 'Brazil';
                    }

                    $paisLower = strtolower($paisTxt);
                    $paisIsBrazil = ($paisLower === 'brazil' || $paisLower === 'brasil');
                    $paisStyle = $paisIsBrazil
                        ? 'color:#14532d;font-weight:700;'
                        : 'color:#b91c1c;font-weight:700;';

                    $isManualBool = false;
                    $manualTxt = '';
                    if (!empty($colManual) && array_key_exists($colManual, $pedido)) {
                        $v = $pedido[$colManual];
                        $isManualBool = ($v === 1 || $v === '1' || $v === true || $v === 'true');
                        $manualTxt = $isManualBool ? 'Sim' : 'Não';
                    } elseif (!empty($pedido['origem_pedido'])) {
                        $isManualBool = (strtolower((string) $pedido['origem_pedido']) === 'manual');
                        $manualTxt = $isManualBool ? 'Sim' : 'Não';
                    }
                    $origemTxt = $isManualBool ? 'Manual' : 'Orgânica';

                    echo '<div class="col-12 mb-3">
                        <div class="card order-card' . ($needsReview ? ' needs-review border border-warning' : '') . '">
                            <div class="card-body">
                                <div class="row align-items-center gy-3">
                                    <div class="col-6 col-lg-2">
                                        <div class="text-center">
                                            <div class="badge bg-' . $statusColor . ' fs-6 mb-2">
                                                <i class="' . $statusIcon . '"></i>
                                            </div>
                                            <h6 class="mb-0">#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</h6>
                                            <small class="text-muted">' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <h6 class="mb-1">' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</h6>
                                        <p class="text-muted small mb-1">' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                        <p class="text-muted small mb-0">' . htmlspecialchars((string) ($pedido['numero_pedido'] ?? '')) . '</p>
                                        ' . ($reviewBadges !== '' ? ('<div class="mt-2">' . $reviewBadges . '</div>' . ($needsReview ? '<div class="text-muted small" style="margin-top:6px;">Precisa revisar itens do pedido (editar produto)</div>' : '')) : '') . '
                                        <div class="text-muted small mt-1">
                                            <span class="me-3" style="' . $paisStyle . '">' . htmlspecialchars($paisTxt) . '</span>
                                            <span class="me-3">UID: <strong>' . (int) ($pedido['usuario_id'] ?? 0) . '</strong></span>
                                            <span class="me-3">Origem: <strong>' . htmlspecialchars($origemTxt) . '</strong></span>' . $this->getCarneBadgeHtml($pedido) . '
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="text-center">
                                            <h5 class="mb-0 text-info text-nowrap">R$ ' . number_format($pedido['total'], 2, ',', '.') . '</h5>
                                            <small class="text-muted">Total (BRL)</small>
                                            ' . (((float) ($pedido['imposto_local'] ?? 0)) > 0 ? '<div class="mt-1"><span class="badge" style="background:rgba(245,158,11,.15);color:#92400e;border:1px solid rgba(245,158,11,.3);font-size:.7rem;">Imposto local</span></div>' : '') . '
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-3">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalLixeiraPedido" data-pedido-id="' . (int) $pedido['id'] . '">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <select class="form-select form-select-sm" style="width: auto; min-width: 120px;" onchange="location.href=\'/admin/pedidos/atualizar-status/' . $pedido['id'] . '/\'+this.value">
                                                <option value="">Status</option>
                                                ' . $this->buildStatusOptions((string)($pedido['status'] ?? '')) . '
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                if (empty($pedidosBRL)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-currency-brl fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum pedido em real encontrado</h5>
                    </div>';
                }
                
                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>';
                
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/pedidos?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "") . (!empty($status) ? "&status={$status}" : "");
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                
                echo '</main></div></div>';

        echo <<<'HTML'

    <div class="modal fade" id="modalLixeiraPedido" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="formLixeiraPedido">
                    <div class="modal-header">
                        <h5 class="modal-title">Enviar pedido para lixeira</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div>Confirma enviar o pedido <strong id="lixeiraPedidoIdLabel"></strong> para a lixeira?</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Enviar para lixeira</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
HTML;

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            var modal = document.getElementById("modalLixeiraPedido");
            if(!modal) return;
            modal.addEventListener("show.bs.modal", function (event) {
                var btn = event.relatedTarget;
                if(!btn) return;
                var pid = btn.getAttribute("data-pedido-id") || "";
                var label = document.getElementById("lixeiraPedidoIdLabel");
                var form = document.getElementById("formLixeiraPedido");
                if(label) label.textContent = "#" + pid;
                if(form) form.action = "/admin/pedidos/excluir/" + pid;
            });
        })();
    </script>
</body>
</html>';
        exit;
    }

    public function pdf(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $id = $request->getParam('id');
        try {
            $pedidoModel = new PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($id);
            if (!$pedido) {
                echo 'Pedido não encontrado';
                return;
            }

            $itens = $pedido['items'] ?? [];

            $paymentDetails = null;
            try {
                $paymentService = new PaymentService();
                $paymentId = (string) ($pedido['pagamento_transacao'] ?? ($pedido['payment_id'] ?? ''));
                $gateway = (string) ($pedido['pagamento_gateway'] ?? ($pedido['payment_gateway'] ?? ''));
                if ($paymentId !== '' && strtolower($gateway) === 'asaas') {
                    $paymentDetails = $paymentService->obterPagamentoAsaas($paymentId);
                }
            } catch (\Exception $e) {
                $paymentDetails = null;
            }

            $svc = new PdfPedidoService();
            $html = $svc->renderPedidoHtml($pedido, is_array($itens) ? $itens : [], is_array($paymentDetails) ? $paymentDetails : null);
            $svc->outputPdfOrHtml($html, 'pedido_' . (string) ($pedido['codigo_pedido'] ?? $pedido['id'] ?? $id));
        } catch (\Exception $e) {
            echo 'Erro ao gerar PDF: ' . $e->getMessage();
        }
    }
    
    public function detalhes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $id = $request->getParam('id');
        $embed = ((string) $request->getParam('embed', '0') === '1');
        $syncOk = ((string) $request->getParam('sync_ok', '0') === '1');
        $syncErr = (string) $request->getParam('sync_err', '');
        
        try {
            // Usar o PedidoEcommerce que já está corrigido e adaptativo
            $pedidoModel = new PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($id);
            
            if (!$pedido) {
                echo '<div class="alert alert-danger">Pedido não encontrado</div>';
                echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
                exit;
            }
            
            // Obter itens do pedido (já vem com dados do produto adaptados)
            $itens = $pedido['items'] ?? [];

            $quantidadeTotalItens = 0;
            if (is_array($itens)) {
                foreach ($itens as $it) {
                    $quantidadeTotalItens += (int) ($it['quantidade'] ?? 0);
                }
            }
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
            exit;
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . ' - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .status-pendente { background-color: #ffc107; }
        .status-pago { background-color: #28a745; }
        .status-carne_pagando { background-color: #6f42c1; }
        .status-carne_aguardando { background-color: #6f42c1; }
        .status-processando { background-color: #0d6efd; }
        .status-produto_consolidado { background-color: #212529; }
        .status-em_transporte { background-color: #17a2b8; }
        .status-aguardando_liberacao_aduaneira { background-color: #6c757d; }
        .status-enviado_ao_destinatario { background-color: #17a2b8; }
        .status-cancelado { background-color: #dc3545; }
        .status-enviado { background-color: #17a2b8; }
        .status-entregue { background-color: #6f42c1; }
    </style>
</head>
<body>
    ' . ($embed ? '<style>
        #adminSidebar { display: none !important; }
        .admin-menu-toggle { display: none !important; }
        main.col-md-9, main.col-lg-10 { width: 100% !important; margin-left: 0 !important; }
        .container-fluid > .row { --bs-gutter-x: 0; }
    </style>' : '') . '
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('pedidos');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shopping-cart me-2"></i>Detalhes do Pedido #' . $pedido['codigo_pedido'] . '</h2>
                <div>
                    ' . (((string) ($pedido['origem_pedido'] ?? '') === 'manual')
                        ? ('<a href="/admin/pedidos/novo-manual?pedido_id=' . (int) $id . '" class="btn btn-outline-primary me-2">'
                            . '<i class="fas fa-pen-to-square me-1"></i>Editar Pedido Manual</a>')
                        : '') . '
                    <form method="POST" action="/admin/pedidos/' . (int) $id . '/criar-ticket" style="display:inline-block" class="me-2">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-headset me-1"></i>Criar ticket
                        </button>
                    </form>
                    <form method="POST" action="/admin/pedidos/sincronizar-pagamentos/' . (int) $id . '" style="display:inline-block" class="me-2" onsubmit="return confirm(' . "'" . 'Sincronizar status de pagamento (Câmbio Real + AppMax + Stripe) agora?' . "'" . ');">
                        <button type="submit" class="btn btn-outline-success">
                            <i class="fas fa-rotate me-1"></i>Sincronizar pagamentos
                        </button>
                    </form>
                    <a href="/admin/pedidos/detalhes/' . $id . '/pdf" class="btn btn-outline-dark me-2" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                    </a>
                    <a href="/admin/pedidos/editar/' . $id . '" class="btn btn-warning me-2">
                        <i class="fas fa-edit me-1"></i>Editar Pedido
                    </a>
                    <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#modalLixeiraPedido" data-pedido-id="' . (int) $id . '">
                        <i class="fas fa-trash me-1"></i>Enviar para Lixeira
                    </button>
                    <a href="/admin/pedidos" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>';

            if ($syncOk) {
                echo '<div class="alert alert-success">Sincronização de pagamentos executada com sucesso.</div>';
            } elseif ($syncErr !== '') {
                echo '<div class="alert alert-warning">Falha ao sincronizar pagamentos: ' . htmlspecialchars($syncErr) . '</div>';
            }

            // Badge: sem comissão (já lançado no vendas.braziliana)
            if (!empty($pedido['sem_comissao'])) {
                echo '<div class="alert alert-info py-2 px-3 d-inline-block mb-3"><i class="fas fa-store me-1"></i> Já lançado no vendas.braziliana <span class="badge bg-secondary ms-1">Sem comissão</span></div>';
            }

            // Destaque: pendência de pagamento (diferença)
            $colsPedido = [];
            try {
                $pdoCols = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                $stmtColsP = $pdoCols->query('DESCRIBE pedidos');
                $colsPedido = $stmtColsP ? $stmtColsP->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsPedido = [];
            }
            $temDif = is_array($colsPedido) && in_array('payment_diferenca_id', $colsPedido, true);
            $difId = $temDif ? (string) ($pedido['payment_diferenca_id'] ?? '') : '';
            $difStatus = $temDif ? (string) ($pedido['payment_diferenca_status'] ?? '') : '';
            $difValor = $temDif ? (float) ($pedido['payment_diferenca_valor'] ?? 0) : 0.0;
            $difInvoiceUrl = $temDif ? (string) ($pedido['payment_diferenca_invoice_url'] ?? '') : '';
            $difBoletoUrl = $temDif ? (string) ($pedido['payment_diferenca_bank_slip_url'] ?? '') : '';
            $difPaidAt = $temDif ? (string) ($pedido['payment_diferenca_paid_at'] ?? '') : '';

            $temDebito = ($difId !== '' && $difValor > 0 && $difPaidAt === '');
            if ($temDebito) {
                $link = $difBoletoUrl !== '' ? $difBoletoUrl : $difInvoiceUrl;
                echo '<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div style="font-weight:800;">Pendência de pagamento (diferença)</div>
                            <div class="small">Valor: <strong>R$ ' . number_format($difValor, 2, ',', '.') . '</strong>'
                                . ($difStatus !== '' ? (' | Status: <strong>' . htmlspecialchars($difStatus) . '</strong>') : '')
                                . '</div>
                        </div>
                        <div class="d-flex gap-2">
                            ' . ($link !== '' ? '<a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener">Abrir link de pagamento</a>' : '') . '
                        </div>
                    </div>';
            } elseif ($temDif && $difId !== '' && $difPaidAt !== '') {
                echo '<div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div style="font-weight:800;">Diferença quitada</div>
                            <div class="small">Pago em: <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($difPaidAt))) . '</strong></div>
                        </div>
                    </div>';
            }

            // Aviso: itens sem custo / sem NCM / CPF inválido
            try {
                $pdoWarn = $pdoCols ?? null;
                if (!($pdoWarn instanceof \PDO)) {
                    $pdoWarn = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                }
                $warnMap = $this->getPedidosMissingDataWarnings($pdoWarn, [(int) $id]);
                $warn = isset($warnMap[(int) $id]) && is_array($warnMap[(int) $id]) ? $warnMap[(int) $id] : null;
                if (is_array($warn) && (!empty($warn['missing_cost']) || !empty($warn['missing_ncm']) || !empty($warn['cpf_invalid']))) {
                    $parts = [];
                    if (!empty($warn['missing_cost'])) $parts[] = 'custo do produto vazio/0';
                    if (!empty($warn['missing_ncm'])) $parts[] = 'NCM não cadastrado';
                    if (!empty($warn['cpf_invalid'])) $parts[] = 'CPF inválido';
                    echo '<div class="alert alert-warning">
                            <div style="font-weight:800;">Atenção: pedido precisa de revisão</div>
                            <div class="small">Encontrado item com ' . htmlspecialchars(implode(' e ', $parts)) . '. Edite o(s) produto(s) do pedido e cadastre corretamente.</div>
                        </div>';
                }
                if (is_array($warn) && !empty($warn['valor_informado_cliente'])) {
                    echo '<div class="alert alert-danger">
                            <div style="font-weight:800;"><i class="fas fa-exclamation-triangle me-1"></i>Atenção: valor informado pelo cliente</div>
                            <div class="small">Este pedido contém itens cujo preço foi informado manualmente pelo cliente (assessoria). Confira os valores antes de processar.</div>
                        </div>';
                }
            } catch (\Exception $e) {
            }

            // Bloco: rastreio / etiqueta (Correios ou W-Express)
            try {
                $pdoTrack = null;
                try {
                    if (isset($pdoCols) && ($pdoCols instanceof \PDO)) {
                        $pdoTrack = $pdoCols;
                    } else {
                        $pdoTrack = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                    }
                } catch (\Exception $e) {
                    $pdoTrack = null;
                }

                $tracking = '';
                $trackingFonte = '';
                $trackingUrl = '';

                if ($pdoTrack instanceof \PDO) {
                    // ShipStation (UPS - exterior)
                    if ($tracking === '') {
                        try {
                            $st = $pdoTrack->prepare("SELECT tracking_number, label_url, carrier_code FROM shipstation_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
                            $st->execute([(int) $id]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $trk = trim((string) ($row['tracking_number'] ?? ''));
                            $url = trim((string) ($row['label_url'] ?? ''));
                            $car = trim((string) ($row['carrier_code'] ?? ''));
                            if ($trk !== '') {
                                $tracking = $trk;
                                $trackingFonte = 'ShipStation' . ($car !== '' ? (' (' . $car . ')') : '');
                                $trackingUrl = $url;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    // Stamps (UPS - exterior)
                    if ($tracking === '') {
                        try {
                            $st = $pdoTrack->prepare("SELECT tracking_number, label_url, carrier FROM stamps_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
                            $st->execute([(int) $id]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $trk = trim((string) ($row['tracking_number'] ?? ''));
                            $url = trim((string) ($row['label_url'] ?? ''));
                            $car = trim((string) ($row['carrier'] ?? ''));
                            if ($trk !== '') {
                                $tracking = $trk;
                                $trackingFonte = 'Stamps' . ($car !== '' ? (' (' . $car . ')') : '');
                                $trackingUrl = $url;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    // Correios
                    try {
                        $st = $pdoTrack->prepare("SELECT codigo_etiqueta FROM correios_etiquetas WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
                        $st->execute([(int) $id]);
                        $c = (string) ($st->fetchColumn() ?: '');
                        if ($c !== '') {
                            $tracking = $c;
                            $trackingFonte = 'Correios';
                        }
                    } catch (\Exception $e) {
                    }

                    // W-Express (internacional)
                    if ($tracking === '') {
                        try {
                            $st = $pdoTrack->prepare("SELECT courier_tracking_number, wexpress_tracking_number, wexpress_status FROM remessa_janela_pedidos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
                            $st->execute([(int) $id]);
                            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                            $courier = trim((string) ($row['courier_tracking_number'] ?? ''));
                            $wx = trim((string) ($row['wexpress_tracking_number'] ?? ''));
                            $wxStatus = trim((string) ($row['wexpress_status'] ?? ''));
                            if ($courier !== '' || $wx !== '') {
                                $tracking = $courier !== '' ? $courier : $wx;
                                $trackingFonte = 'W-Express' . ($wxStatus !== '' ? (' (' . $wxStatus . ')') : '');
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                if ($tracking !== '') {
                    echo '<div class="alert alert-info mb-3">'
                        . '<div><strong>Código de rastreio:</strong> ' . htmlspecialchars($tracking) . '</div>'
                        . ($trackingFonte !== '' ? ('<div class="small text-muted">Fonte: ' . htmlspecialchars($trackingFonte) . '</div>') : '')
                        . ($trackingUrl !== '' ? ('<div class="small"><a href="' . htmlspecialchars($trackingUrl) . '" target="_blank" rel="noopener">Ver etiqueta</a></div>') : '')
                        . '</div>';
                }
            } catch (\Exception $e) {
            }

            // Comprovante de compra (online) + comissão de processamento
            try {
                $pdoLocal2 = null;
                try {
                    if (isset($pdoCols) && ($pdoCols instanceof \PDO)) {
                        $pdoLocal2 = $pdoCols;
                    } else {
                        $pdoLocal2 = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                    }
                } catch (\Exception $e) {
                    $pdoLocal2 = null;
                }

                if ($pdoLocal2 instanceof \PDO) {
                    $hasCompraDocs = false;
                    try {
                        $st = $pdoLocal2->prepare('SHOW TABLES LIKE ?');
                        $st->execute(['pedidos_compra_documentos']);
                        $hasCompraDocs = (bool) $st->fetchColumn();
                    } catch (\Exception $e) {
                        $hasCompraDocs = false;
                    }

                    if ($hasCompraDocs) {
                        $doc = null;
                        try {
                            $st = $pdoLocal2->prepare("SELECT status, arquivo_path, uploaded_at, usuario_id FROM pedidos_compra_documentos WHERE pedido_id = ? AND tipo_compra = 'online' ORDER BY id DESC LIMIT 1");
                            $st->execute([(int) $id]);
                            $doc = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
                        } catch (\Exception $e) {
                            $doc = null;
                        }

                        if (is_array($doc) && !empty($doc['arquivo_path'])) {
                            $upAt = (string) ($doc['uploaded_at'] ?? '');
                            $path = (string) ($doc['arquivo_path'] ?? '');
                            echo '<div class="alert alert-success mb-3">'
                                . '<div><strong>Comprovante de compra (Online) anexado.</strong></div>'
                                . ($upAt !== '' ? ('<div class="small">Enviado em: <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($upAt))) . '</strong></div>') : '')
                                . '<div class="mt-2"><a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener">Abrir comprovante</a></div>'
                                . '</div>';
                        }
                    }

                    $hasComProc = false;
                    try {
                        $st = $pdoLocal2->prepare('SHOW TABLES LIKE ?');
                        $st->execute(['comissoes_processamento']);
                        $hasComProc = (bool) $st->fetchColumn();
                    } catch (\Exception $e) {
                        $hasComProc = false;
                    }

                    if ($hasComProc) {
                        $rowC = null;
                        try {
                            $st = $pdoLocal2->prepare('SELECT usuario_id, moeda, percentual, base_liquida, valor_comissao, created_at FROM comissoes_processamento WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                            $st->execute([(int) $id]);
                            $rowC = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
                        } catch (\Exception $e) {
                            $rowC = null;
                        }

                        if (is_array($rowC) && (float) ($rowC['valor_comissao'] ?? 0) > 0) {
                            $m = strtoupper(trim((string) ($rowC['moeda'] ?? 'BRL')));
                            if ($m === '') $m = 'BRL';
                            $dt = (string) ($rowC['created_at'] ?? '');
                            echo '<div class="alert alert-info mb-3">'
                                . '<div><strong>Comissão de processamento registrada.</strong></div>'
                                . '<div class="small">Percentual: <strong>' . number_format((float) ($rowC['percentual'] ?? 0), 2, ',', '.') . '%</strong></div>'
                                . '<div class="small">Base líquida: <strong>' . $this->formatarMoeda((float) ($rowC['base_liquida'] ?? 0), $m) . '</strong></div>'
                                . '<div class="small">Comissão: <strong>' . $this->formatarMoeda((float) ($rowC['valor_comissao'] ?? 0), $m) . '</strong></div>'
                                . ($dt !== '' ? ('<div class="small">Registrada em: <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($dt))) . '</strong></div>') : '')
                                . '</div>';
                        }
                    }
                }
            } catch (\Exception $e) {
            }
            
            // Pré-carregar dados do produto (custo/NCM) para sugerir edição quando faltar
            $produtoMetaById = [];
            try {
                $pids = [];
                if (is_array($itens)) {
                    foreach ($itens as $it) {
                        $pid = (int) ($it['produto_id'] ?? 0);
                        if ($pid > 0) {
                            $pids[$pid] = true;
                        }
                    }
                }
                $pids = array_keys($pids);

                if (!empty($pids)) {
                    $pdoProd = null;
                    try {
                        $pdoProd = $pdoCols ?? null;
                    } catch (\Exception $e) {
                        $pdoProd = null;
                    }
                    if (!($pdoProd instanceof \PDO)) {
                        $pdoProd = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                    }

                    $colsProd = [];
                    try {
                        $st = $pdoProd->query('DESCRIBE produtos');
                        $colsProd = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    } catch (\Exception $e) {
                        $colsProd = [];
                    }

                    $pick = function(array $candidates) use ($colsProd): string {
                        foreach ($candidates as $c) {
                            if (is_array($colsProd) && in_array($c, $colsProd, true)) {
                                return $c;
                            }
                        }
                        return '';
                    };

                    $colCusto = $pick(['preco_custo', 'custo', 'cost_price', 'valor_custo']);
                    $colNcm = $pick(['ncm', 'codigo_ncm', 'ncm_code', 'tariff_code', 'ncm_produto']);

                    $sel = ['id'];
                    if ($colCusto !== '') $sel[] = $colCusto . ' AS custo';
                    if ($colNcm !== '') $sel[] = $colNcm . ' AS ncm';

                    if (count($sel) > 1) {
                        $ph = implode(',', array_fill(0, count($pids), '?'));
                        $st = $pdoProd->prepare('SELECT ' . implode(', ', $sel) . ' FROM produtos WHERE id IN (' . $ph . ')');
                        $st->execute($pids);
                        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        foreach ($rows as $r) {
                            $pid = (int) ($r['id'] ?? 0);
                            if ($pid <= 0) continue;
                            $produtoMetaById[$pid] = [
                                'custo' => isset($r['custo']) ? (float) $r['custo'] : null,
                                'ncm' => isset($r['ncm']) ? (string) $r['ncm'] : null,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                $produtoMetaById = [];
            }

            // Conteúdo principal
            // Verificar se pedido tem item gratuito
            $temItemGratuito = false;
            $freeOfferItemInfo = null;
            if (is_array($itens)) {
                foreach ($itens as $it) {
                    if (!empty($it['is_free_offer'])) {
                        $temItemGratuito = true;
                        $freeOfferItemInfo = $it;
                        break;
                    }
                }
            }
            if ($temItemGratuito) {
                $freeOrigPrice = (float) ($freeOfferItemInfo['free_offer_original_price'] ?? ($freeOfferItemInfo['preco_unitario'] ?? 0));
                echo '<div class="alert alert-success mb-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-gift fa-2x me-3"></i>
                        <div>
                            <strong>Este pedido contém um produto gratuito promocional</strong>
                            <div class="small">Produto: ' . htmlspecialchars((string) ($freeOfferItemInfo['nome_produto'] ?? '')) . '</div>
                            <div class="small">Valor original: $ ' . number_format($freeOrigPrice, 2) . ' | Valor cobrado: $ 0,00 | Imposto não cobrado</div>
                        </div>
                    </div>
                </div>';
            }

            // Determinar moeda de exibição para todo o pedido
            $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? 'USD')));
            if ($moedaPedido === '') $moedaPedido = 'USD';
            $taxaConvPedido = (float) ($pedido['taxa_conversao'] ?? 1);
            if ($taxaConvPedido <= 0) $taxaConvPedido = 1;
            $exibirEmBrl = ($moedaPedido === 'BRL');

            echo '<div class="row">
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Itens do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Imagem</th>
                                                <th>Produto</th>
                                                <th>ID Produto</th>
                                                <th>NCM</th>
                                                <th>Custo</th>
                                                <th>Quantidade</th>
                                                <th>Preço Unitário</th>
                                                <th>Subtotal</th>
                                                <th>Data de Criação</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        if (empty($itens)) {
                                            echo '<tr><td colspan="10" class="text-center text-warning">Nenhum item encontrado para este pedido</td></tr>';
                                        }
                                        
                                        foreach ($itens as $item) {
                                            $pidItem = (int) ($item['produto_id'] ?? 0);
                                            $isFreeOfferItem = !empty($item['is_free_offer']);
                                            $metaProd = ($pidItem > 0 && isset($produtoMetaById[$pidItem]) && is_array($produtoMetaById[$pidItem]))
                                                ? $produtoMetaById[$pidItem]
                                                : null;
                                            $custoProd = is_array($metaProd) ? ($metaProd['custo'] ?? null) : null;
                                            $ncmProd = is_array($metaProd) ? trim((string) ($metaProd['ncm'] ?? '')) : '';
                                            $missingCost = ($custoProd === null || (float) $custoProd <= 0);
                                            $missingNcm = ($ncmProd === '');

                                            echo '<tr' . ($isFreeOfferItem ? ' style="background: rgba(40,167,69,0.06);"' : '') . '>
                                                <td>';
                                            
                                            // Mostrar imagem apenas se existir
                                            if (!empty($item['imagem']) && $item['imagem'] !== 'default.jpg') {
                                                $img = (string) $item['imagem'];
                                                // Se já for URL externa, usar diretamente
                                                if (preg_match('#^https?://#i', $img) || strpos($img, '//') === 0) {
                                                    if (strpos($img, '//') === 0) {
                                                        $img = 'https:' . $img;
                                                    }
                                                    echo '<img src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($item['nome_produto']) . '" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">';
                                                } else {
                                                    // Remover caminho duplicado se existir
                                                    $imagemPath = $img;
                                                    if (strpos($imagemPath, 'uploads/produtos/') !== false) {
                                                        $imagemPath = str_replace('uploads/produtos/', '', $imagemPath);
                                                    }
                                                    echo '<img src="/uploads/produtos/' . htmlspecialchars($imagemPath) . '" alt="' . htmlspecialchars($item['nome_produto']) . '" 
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">';
                                                }
                                            }
                                            
                                            $nomeProduto = (string) ($item['nome_produto'] ?? 'Produto #' . $item['produto_id']);
                                            $sku = (string) ($item['nome_produto_sku'] ?? $item['referencia'] ?? '');
                                            $urlOriginal = (string) ($item['url_original'] ?? '');
                                            $variacaoLabel = (string) ($item['variacao_label'] ?? '');
                                            $variacaoAttrs = $item['variacao_atributos'] ?? null;

                                            $nomeHtml = '';
                                            if ($urlOriginal !== '') {
                                                $nomeHtml = '<a href="' . htmlspecialchars($urlOriginal) . '" target="_blank" class="text-decoration-none">' . htmlspecialchars($nomeProduto) . '</a>';
                                            } else {
                                                $nomeHtml = htmlspecialchars($nomeProduto);
                                            }

                                            // Badge de produto gratuito promocional
                                            $isFreeOfferItem = !empty($item['is_free_offer']);
                                            if ($isFreeOfferItem) {
                                                $nomeHtml .= ' <span class="badge bg-success"><i class="fas fa-gift me-1"></i>Produto Gratuito</span>';
                                            }

                                            // Badge de valor informado pelo cliente (assessoria - revisão pendente)
                                            $isValorInformadoCliente = !empty($item['valor_informado_cliente']);
                                            if ($isValorInformadoCliente) {
                                                $nomeHtml .= ' <span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i>Valor informado pelo cliente</span>';
                                            }

                                            $extraHtml = '';

                                            // Observação do cliente (assessoria)
                                            $obsCliente = trim((string) ($item['observacao_cliente'] ?? ''));
                                            if ($obsCliente !== '') {
                                                $extraHtml .= '<div class="alert alert-warning py-1 px-2 mt-1 mb-1 small"><i class="fas fa-comment me-1"></i><strong>Obs. do cliente:</strong> ' . htmlspecialchars($obsCliente) . '</div>';
                                            }

                                            // Valor real conferido (assessoria)
                                            $valorRealConf = isset($item['valor_real_conferencia']) ? (float) $item['valor_real_conferencia'] : 0;
                                            $conferidoEm = trim((string) ($item['conferido_em'] ?? ''));
                                            if ($valorRealConf > 0) {
                                                $diffLabel = '';
                                                $precoItem = (float) ($item['preco_unitario'] ?? 0);
                                                if ($precoItem > 0 && abs($valorRealConf - $precoItem) > 0.01) {
                                                    $diff = $valorRealConf - $precoItem;
                                                    $diffLabel = ' (' . ($diff > 0 ? '+' : '') . number_format($diff, 2) . ')';
                                                }
                                                $extraHtml .= '<div class="alert alert-info py-1 px-2 mt-1 mb-1 small"><i class="fas fa-check-circle me-1"></i><strong>Valor conferido:</strong> $ ' . number_format($valorRealConf, 2) . $diffLabel
                                                    . ($conferidoEm !== '' ? ' <span class="text-muted">em ' . htmlspecialchars(date('d/m/Y H:i', strtotime($conferidoEm))) . '</span>' : '')
                                                    . '</div>';
                                            }

                                            if ($sku !== '') {
                                                $extraHtml .= '<div class="small text-muted">SKU/Ref: ' . htmlspecialchars($sku) . '</div>';
                                            }
                                            if ($urlOriginal !== '') {
                                                $extraHtml .= '<div class="small text-muted">link de acesso original</div>';
                                            }
                                            $variacaoLinha = '';
                                            if (is_array($variacaoAttrs) && !empty($variacaoAttrs)) {
                                                $pairs = [];
                                                foreach ($variacaoAttrs as $k => $v) {
                                                    if ($k === '' || $v === null) continue;
                                                    $pairs[] = (string) $k . ': ' . (string) $v;
                                                }
                                                if (!empty($pairs)) {
                                                    $variacaoLinha = implode(' | ', $pairs);
                                                }
                                            }
                                            if ($variacaoLinha === '' && $variacaoLabel !== '') {
                                                $variacaoLinha = $variacaoLabel;
                                            }
                                            if ($variacaoLinha !== '') {
                                                $extraHtml .= '<div class="small text-muted" style="margin-top: 6px;">' . htmlspecialchars($variacaoLinha) . '</div>';
                                            }

                                            $ncmVal = trim((string) ($item['ncm'] ?? ''));
                                            if ($ncmVal === '' && $ncmProd !== '') {
                                                $ncmVal = $ncmProd;
                                            }
                                            if ($ncmVal !== '') {
                                                $ncmHtml = '<span>' . htmlspecialchars($ncmVal, ENT_QUOTES, 'UTF-8') . '</span>'
                                                    . ' <a href="#" class="text-muted js-ncm-quick" data-produto-id="' . (int) $pidItem . '" data-ncm-current="' . htmlspecialchars($ncmVal, ENT_QUOTES, 'UTF-8') . '" title="Editar NCM" style="text-decoration:none;">'
                                                    . '<i class="fas fa-pen-to-square"></i>'
                                                    . '</a>';
                                            } else {
                                                $ncmHtml = '<a href="#" class="badge bg-warning text-dark js-ncm-quick" data-produto-id="' . (int) $pidItem . '" style="text-decoration:none;">Sem NCM</a>';
                                            }

                                            $acoesHtml = '';
                                            if ($pidItem > 0 && ($missingCost || $missingNcm)) {
                                                $label = 'Editar produto';
                                                if ($missingCost && $missingNcm) {
                                                    $label = 'Editar (custo + NCM)';
                                                } elseif ($missingCost) {
                                                    $label = 'Editar (custo)';
                                                } elseif ($missingNcm) {
                                                    $label = 'Editar (NCM)';
                                                }
                                                $acoesHtml = '<a href="/admin/produtos/editar/' . (int) $pidItem . '" class="btn btn-sm btn-warning">'
                                                    . '<i class="fas fa-pen-to-square me-1"></i>' . htmlspecialchars($label) . '</a>';
                                            } elseif ($pidItem > 0) {
                                                $acoesHtml = '<a href="/admin/produtos/editar/' . (int) $pidItem . '" class="btn btn-sm btn-outline-secondary">'
                                                    . '<i class="fas fa-pen-to-square me-1"></i>Editar</a>';
                                            }

                                            echo '</td>
                                                <td>' . $nomeHtml . $extraHtml . '</td>
                                                <td>' . $item['produto_id'] . '</td>
                                                <td>' . $ncmHtml . '</td>
                                                <td>';
                                            // Custo do produto com edição rápida
                                            $custoDisplay = ($custoProd !== null && (float) $custoProd > 0) ? number_format((float) $custoProd, 2, ',', '.') : '';
                                            if ($custoDisplay !== '') {
                                                echo '<span>' . htmlspecialchars($custoDisplay, ENT_QUOTES, 'UTF-8') . '</span>'
                                                    . ' <a href="#" class="text-muted js-custo-quick" data-produto-id="' . (int) $pidItem . '" data-custo-current="' . htmlspecialchars(number_format((float) $custoProd, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '" title="Editar Custo" style="text-decoration:none;">'
                                                    . '<i class="fas fa-pen-to-square"></i>'
                                                    . '</a>';
                                            } else {
                                                if ($pidItem > 0) {
                                                    echo '<a href="#" class="badge bg-warning text-dark js-custo-quick" data-produto-id="' . (int) $pidItem . '" data-custo-current="" style="text-decoration:none;">Sem custo</a>';
                                                } else {
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                            }
                                            echo '</td>
                                                <td>' . $item['quantidade'] . '</td>
                                                <td>' . ($isFreeOfferItem
                                                    ? '<span class="text-decoration-line-through text-muted">' . ($exibirEmBrl ? 'R$ ' . number_format((float)($item['free_offer_original_price'] ?? $item['preco_unitario'] ?? 0), 2, ',', '.') : 'US$ ' . number_format((float)($item['free_offer_original_price'] ?? $item['preco_unitario'] ?? 0), 2, '.', ',')) . '</span>'
                                                    : ($exibirEmBrl ? 'R$ ' . number_format((float)($item['preco_unitario'] ?? 0), 2, ',', '.') : 'US$ ' . number_format((float)($item['preco_unitario'] ?? 0), 2, '.', ','))
                                                ) . '</td>
                                                <td>' . ($isFreeOfferItem
                                                    ? '<span class="badge bg-success">GRÁTIS</span>'
                                                    : ($exibirEmBrl ? 'R$ ' . number_format((float)($item['subtotal'] ?? 0), 2, ',', '.') : 'US$ ' . number_format((float)($item['subtotal'] ?? 0), 2, '.', ','))
                                                ) . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($item['created_at'])) . '</td>
                                                <td>' . $acoesHtml . '</td>
                                            </tr>';
                                        }
                                        
                                    echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>';

                    echo '<div class="modal fade" id="modalNcmQuick" tabindex="-1" aria-hidden="true">'
                        . '<div class="modal-dialog modal-lg">'
                        . '<div class="modal-content">'
                        . '<div class="modal-header">'
                        . '<h5 class="modal-title">Selecionar NCM</h5>'
                        . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                        . '</div>'
                        . '<div class="modal-body">'
                        . '<div id="ncmQuickAlert" class="alert alert-info" style="display:none;"></div>'
                        . '<input type="hidden" id="ncmQuickProdutoId" value="" />'
                        . '<div class="mb-2">'
                        . '<input type="text" class="form-control" id="ncmQuickSearch" placeholder="Pesquisar NCM (código ou descrição)..." autocomplete="off" />'
                        . '</div>'
                        . '<div class="list-group" id="ncmQuickResults" style="max-height:360px; overflow:auto;"></div>'
                        . '</div>'
                        . '<div class="modal-footer">'
                        . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>'
                        . '<button type="button" class="btn btn-primary" id="btnNcmQuickSalvar" disabled>Salvar NCM</button>'
                        . '</div>'
                        . '</div>'
                        . '</div>'
                        . '</div>';

                    echo <<<HTML
<script>(function(){
    function qs(sel, root){ return (root||document).querySelector(sel); }
    function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
    var state = { produtoId: 0, selectedNcm: "", triggerEl: null };

    function setAlert(msg, cls){
        var el = qs("#ncmQuickAlert");
        if(!el) return;
        el.style.display = msg ? "block" : "none";
        el.className = "alert " + (cls||"alert-info");
        el.textContent = msg||"";
    }
    function openModal(){
        var el = qs("#modalNcmQuick");
        if(!el || !window.bootstrap || !window.bootstrap.Modal) return;
        window.bootstrap.Modal.getOrCreateInstance(el).show();
    }
    function setSalvarEnabled(on){
        var b = qs("#btnNcmQuickSalvar");
        if(b) b.disabled = !on;
    }
    function setResults(items){
        var wrap = qs("#ncmQuickResults");
        if(!wrap) return;
        wrap.innerHTML = "";
        (items||[]).forEach(function(it){
            var a = document.createElement("a");
            a.href = "#";
            a.className = "list-group-item list-group-item-action";
            a.setAttribute("data-ncm", it.code||"");
            a.textContent = it.text || (it.code || "");
            if(state.selectedNcm && String(it.code||"") === String(state.selectedNcm)){
                a.classList.add("active");
                setSalvarEnabled(true);
            }
            a.addEventListener("click", function(ev){
                ev.preventDefault();
                state.selectedNcm = String(it.code||"");
                qsa("#ncmQuickResults .list-group-item").forEach(function(x){ x.classList.remove("active"); });
                a.classList.add("active");
                setSalvarEnabled(!!state.selectedNcm);
            });
            wrap.appendChild(a);
        });
    }
    function doSearch(q){
        var body = new URLSearchParams();
        body.set("q", String(q||""));
        return fetch("/admin/produtos/ncm/search", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: body.toString()
        }).then(function(r){ return r.json(); });
    }
    function doSave(produtoId, ncm){
        var body = new URLSearchParams();
        body.set("ncm", String(ncm||""));
        return fetch("/admin/produtos/ncm/atualizar/" + encodeURIComponent(String(produtoId||"")), {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: body.toString()
        }).then(function(r){ return r.json(); });
    }

    document.addEventListener("click", function(ev){
        var a = ev.target && ev.target.closest ? ev.target.closest(".js-ncm-quick") : null;
        if(!a) return;
        ev.preventDefault();
        state.triggerEl = a;
        state.produtoId = parseInt(a.getAttribute("data-produto-id")||"0", 10) || 0;
        state.selectedNcm = String(a.getAttribute("data-ncm-current")||"");
        var hid = qs("#ncmQuickProdutoId");
        if(hid) hid.value = String(state.produtoId||"");
        var input = qs("#ncmQuickSearch");
        if(input) input.value = "";
        setSalvarEnabled(!!state.selectedNcm);
        setAlert("Pesquise e selecione o NCM.", "alert-info");
        setResults([]);
        openModal();
        if(input){
            setTimeout(function(){ try{ input.focus(); } catch(e){} }, 200);
            doSearch("").then(function(j){
                if(!j || !j.success){ return; }
                setResults(j.items||[]);
            }).catch(function(){});
        }
    });

    var searchInput = qs("#ncmQuickSearch");
    if(searchInput){
        var t = null;
        searchInput.addEventListener("input", function(){
            if(t) clearTimeout(t);
            t = setTimeout(function(){
                doSearch(searchInput.value||"").then(function(j){
                    if(!j || !j.success){ return; }
                    setResults(j.items||[]);
                }).catch(function(){});
            }, 200);
        });
    }

    var btnSave = qs("#btnNcmQuickSalvar");
    if(btnSave){
        btnSave.addEventListener("click", function(){
            if(!state.produtoId || !state.selectedNcm) return;
            setAlert("Salvando NCM...", "alert-info");
            btnSave.disabled = true;
            doSave(state.produtoId, state.selectedNcm).then(function(j){
                if(!j || !j.success){
                    setAlert((j && j.error) ? j.error : "Falha ao salvar NCM", "alert-warning");
                    btnSave.disabled = false;
                    return;
                }
                setAlert("NCM atualizado.", "alert-success");
                try {
                    var td = state.triggerEl && state.triggerEl.closest ? state.triggerEl.closest('td') : null;
                    if(td){
                        var ncmNew = String(j.ncm || state.selectedNcm || '');
                        if(ncmNew){
                            td.innerHTML = '<span>' + ncmNew + '</span> ' +
                                '<a href="#" class="text-muted js-ncm-quick" data-produto-id="' + String(state.produtoId) + '" data-ncm-current="' + ncmNew + '" title="Editar NCM" style="text-decoration:none;">' +
                                '<i class="fas fa-pen-to-square"></i>' +
                                '</a>';
                        }
                    }
                } catch(e) {}
                btnSave.disabled = false;
            }).catch(function(){
                setAlert("Erro de rede ao salvar NCM", "alert-warning");
                btnSave.disabled = false;
            });
        });
    }
})();</script>
HTML;

                    // Modal de edição rápida de custo
                    echo '<div class="modal fade" id="modalCustoQuick" tabindex="-1" aria-hidden="true">'
                        . '<div class="modal-dialog modal-sm">'
                        . '<div class="modal-content">'
                        . '<div class="modal-header">'
                        . '<h5 class="modal-title"><i class="fas fa-dollar-sign me-1"></i>Editar Custo</h5>'
                        . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                        . '</div>'
                        . '<div class="modal-body">'
                        . '<div id="custoQuickAlert" class="alert alert-info" style="display:none;"></div>'
                        . '<input type="hidden" id="custoQuickProdutoId" value="" />'
                        . '<label class="form-label">Custo do produto (R$)</label>'
                        . '<input type="number" step="0.01" min="0" class="form-control" id="custoQuickInput" placeholder="0.00" />'
                        . '</div>'
                        . '<div class="modal-footer">'
                        . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>'
                        . '<button type="button" class="btn btn-primary" id="btnCustoQuickSalvar">Salvar Custo</button>'
                        . '</div>'
                        . '</div>'
                        . '</div>'
                        . '</div>';

                    echo <<<'CUSTOSCRIPT'
<script>(function(){
    var cState = { produtoId: 0, triggerEl: null };
    function qs(s){ return document.querySelector(s); }
    function custoAlert(msg, cls){
        var el = qs("#custoQuickAlert");
        if(!el) return;
        el.style.display = msg ? "block" : "none";
        el.className = "alert " + (cls||"alert-info");
        el.textContent = msg||"";
    }
    document.addEventListener("click", function(ev){
        var a = ev.target && ev.target.closest ? ev.target.closest(".js-custo-quick") : null;
        if(!a) return;
        ev.preventDefault();
        cState.triggerEl = a;
        cState.produtoId = parseInt(a.getAttribute("data-produto-id")||"0", 10) || 0;
        var cur = a.getAttribute("data-custo-current") || "";
        qs("#custoQuickProdutoId").value = String(cState.produtoId);
        qs("#custoQuickInput").value = cur;
        custoAlert("", "");
        var el = qs("#modalCustoQuick");
        if(el && window.bootstrap && window.bootstrap.Modal){
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        }
        setTimeout(function(){ try{ qs("#custoQuickInput").focus(); } catch(e){} }, 200);
    });
    var btnSave = qs("#btnCustoQuickSalvar");
    if(btnSave){
        btnSave.addEventListener("click", function(){
            if(!cState.produtoId) return;
            var val = parseFloat(qs("#custoQuickInput").value || "0");
            if(isNaN(val) || val < 0){ custoAlert("Informe um valor válido.", "alert-warning"); return; }
            custoAlert("Salvando...", "alert-info");
            btnSave.disabled = true;
            var body = new URLSearchParams();
            body.set("custo", String(val));
            fetch("/admin/produtos/custo/atualizar/" + encodeURIComponent(String(cState.produtoId)), {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: body.toString()
            }).then(function(r){ return r.json(); }).then(function(j){
                if(!j || !j.success){
                    custoAlert((j && j.error) ? j.error : "Falha ao salvar", "alert-warning");
                    btnSave.disabled = false;
                    return;
                }
                custoAlert("Custo atualizado!", "alert-success");
                btnSave.disabled = false;
                try {
                    var td = cState.triggerEl && cState.triggerEl.closest ? cState.triggerEl.closest("td") : null;
                    if(td){
                        var fmt = j.custo_fmt || String(val.toFixed(2)).replace(".", ",");
                        td.innerHTML = '<span>' + fmt + '</span> ' +
                            '<a href="#" class="text-muted js-custo-quick" data-produto-id="' + String(cState.produtoId) + '" data-custo-current="' + String(val) + '" title="Editar Custo" style="text-decoration:none;">' +
                            '<i class="fas fa-pen-to-square"></i></a>';
                    }
                } catch(e) {}
            }).catch(function(){
                custoAlert("Erro de rede.", "alert-warning");
                btnSave.disabled = false;
            });
        });
    }
})();</script>
CUSTOSCRIPT;

                    $clienteNome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? ''));
                    $clienteEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ($pedido['customer_email'] ?? '')));
                    $clienteTelefone = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ''));
                    $clienteDoc = (string) ($pedido['cliente_cpf_cnpj'] ?? ($pedido['cliente_documento'] ?? ($pedido['documento'] ?? '')));
                    $pais = (string) ($pedido['pais_entrega'] ?? ($pedido['country_entrega'] ?? ($pedido['pais'] ?? '')));
                    $cep = (string) ($pedido['cep'] ?? '');
                    $endereco = (string) ($pedido['endereco'] ?? '');
                    $numero = (string) ($pedido['numero'] ?? '');
                    $complemento = (string) ($pedido['complemento'] ?? '');
                    $bairro = (string) ($pedido['bairro'] ?? '');
                    $cidade = (string) ($pedido['cidade'] ?? '');
                    $estado = (string) ($pedido['estado'] ?? '');

                    echo '<div class="modal fade" id="modalEditarClientePedido" tabindex="-1" aria-hidden="true">'
                        . '<div class="modal-dialog modal-lg">'
                        . '<div class="modal-content">'
                        . '<div class="modal-header">'
                        . '<h5 class="modal-title">Editar dados do cliente / endereço</h5>'
                        . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                        . '</div>'
                        . '<div class="modal-body">'
                        . '<div id="editClienteAlert" class="alert alert-info" style="display:none;"></div>'
                        . '<div class="row g-3">'
                        . '<div class="col-md-6"><label class="form-label">Nome</label><input type="text" class="form-control" id="editClienteNome" value="' . htmlspecialchars($clienteNome, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-6"><label class="form-label">E-mail</label><input type="email" class="form-control" id="editClienteEmail" value="' . htmlspecialchars($clienteEmail, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">Telefone</label><input type="text" class="form-control" id="editClienteTelefone" value="' . htmlspecialchars($clienteTelefone, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">CPF/CNPJ</label><input type="text" class="form-control" id="editClienteDocumento" value="' . htmlspecialchars($clienteDoc, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">País</label><input type="text" class="form-control" id="editClientePais" value="' . htmlspecialchars($pais, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-3"><label class="form-label">CEP</label><input type="text" class="form-control" id="editClienteCep" value="' . htmlspecialchars($cep, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-6"><label class="form-label">Endereço</label><input type="text" class="form-control" id="editClienteEndereco" value="' . htmlspecialchars($endereco, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-3"><label class="form-label">Número</label><input type="text" class="form-control" id="editClienteNumero" value="' . htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">Complemento</label><input type="text" class="form-control" id="editClienteComplemento" value="' . htmlspecialchars($complemento, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">Bairro</label><input type="text" class="form-control" id="editClienteBairro" value="' . htmlspecialchars($bairro, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">Cidade</label><input type="text" class="form-control" id="editClienteCidade" value="' . htmlspecialchars($cidade, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">Estado</label><input type="text" class="form-control" id="editClienteEstado" value="' . htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '</div>'
                        . '<hr class="mt-3 mb-2"><h6 class="mb-2"><i class="fas fa-user-friends me-1"></i>Destinatário (entrega para outra pessoa)</h6>'
                        . '<div class="row g-3">'
                        . '<div class="col-md-4"><label class="form-label">Nome destinatário</label><input type="text" class="form-control" id="editDestinatarioNome" value="' . htmlspecialchars(trim((string) ($pedido['destinatario_nome'] ?? '')), ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">CPF/Doc destinatário</label><input type="text" class="form-control" id="editDestinatarioDocumento" value="' . htmlspecialchars(trim((string) ($pedido['destinatario_documento'] ?? '')), ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '<div class="col-md-4"><label class="form-label">Telefone destinatário</label><input type="text" class="form-control" id="editDestinatarioTelefone" value="' . htmlspecialchars(trim((string) ($pedido['destinatario_telefone'] ?? '')), ENT_QUOTES, 'UTF-8') . '"></div>'
                        . '</div>'
                        . '</div>'
                        . '<div class="modal-footer">'
                        . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>'
                        . '<button type="button" class="btn btn-primary" id="btnSalvarClientePedido" data-pedido-id="' . (int) $pedido['id'] . '">Salvar</button>'
                        . '</div>'
                        . '</div>'
                        . '</div>'
                        . '</div>';

                    echo <<<HTML
<script>(function(){
    function qs(sel, root){ return (root||document).querySelector(sel); }
    function setAlert(msg, cls){
        var el = qs('#editClienteAlert');
        if(!el) return;
        el.style.display = msg ? 'block' : 'none';
        el.className = 'alert ' + (cls||'alert-info');
        el.textContent = msg||'';
    }
    function openModal(){
        var el = qs('#modalEditarClientePedido');
        if(!el || !window.bootstrap || !window.bootstrap.Modal) return;
        window.bootstrap.Modal.getOrCreateInstance(el).show();
    }

    document.addEventListener('keydown', function(ev){
        if(ev.key === 'e' && (ev.ctrlKey || ev.metaKey)){
            ev.preventDefault();
            setAlert('', 'alert-info');
            openModal();
        }
    });

    document.addEventListener('click', function(ev){
        var a = ev.target && ev.target.closest ? ev.target.closest('.js-abrir-editar-cliente-pedido') : null;
        if(!a) return;
        ev.preventDefault();
        setAlert('', 'alert-info');
        openModal();
    });

    var btnSave = qs('#btnSalvarClientePedido');
    if(btnSave){
        btnSave.addEventListener('click', function(){
            var pedidoId = btnSave.getAttribute('data-pedido-id')||'';
            if(!pedidoId) return;
            btnSave.disabled = true;
            setAlert('Salvando...', 'alert-info');

            var body = new URLSearchParams();
            body.set('nome', (qs('#editClienteNome')||{}).value || '');
            body.set('email', (qs('#editClienteEmail')||{}).value || '');
            body.set('telefone', (qs('#editClienteTelefone')||{}).value || '');
            body.set('documento', (qs('#editClienteDocumento')||{}).value || '');
            body.set('pais', (qs('#editClientePais')||{}).value || '');
            body.set('cep', (qs('#editClienteCep')||{}).value || '');
            body.set('endereco', (qs('#editClienteEndereco')||{}).value || '');
            body.set('numero', (qs('#editClienteNumero')||{}).value || '');
            body.set('complemento', (qs('#editClienteComplemento')||{}).value || '');
            body.set('bairro', (qs('#editClienteBairro')||{}).value || '');
            body.set('cidade', (qs('#editClienteCidade')||{}).value || '');
            body.set('estado', (qs('#editClienteEstado')||{}).value || '');
            body.set('destinatario_nome', (qs('#editDestinatarioNome')||{}).value || '');
            body.set('destinatario_documento', (qs('#editDestinatarioDocumento')||{}).value || '');
            body.set('destinatario_telefone', (qs('#editDestinatarioTelefone')||{}).value || '');

            fetch('/admin/pedidos/atualizar-cliente/' + encodeURIComponent(String(pedidoId)), {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: body.toString()
            })
            .then(function(r){ return r.json().catch(function(){ return null; }); })
            .then(function(j){
                if(!j || !j.success){
                    setAlert((j && j.error) ? j.error : 'Falha ao salvar', 'alert-warning');
                    btnSave.disabled = false;
                    return;
                }
                setAlert('Dados atualizados. Recarregue a página para ver tudo refletido.', 'alert-success');
                btnSave.disabled = false;
            })
            .catch(function(){
                setAlert('Erro de rede ao salvar', 'alert-warning');
                btnSave.disabled = false;
            });
        });
    }
})();</script>
HTML;
                    
                    echo '<div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Dados Completos do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Campo</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><strong>ID</strong></td><td>' . $pedido['id'] . '</td></tr>
                                            <tr><td><strong>Número Pedido</strong></td><td>' . htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['numero_pedido']) . '</td></tr>
                                            <tr><td><strong>Status</strong></td><td><span class="badge status-' . $pedido['status'] . '">' . htmlspecialchars($this->getStatusLabel((string) ($pedido['status'] ?? ''))) . '</span></td></tr>
                                            <tr><td><strong>Nome Cliente</strong></td><td>' . htmlspecialchars($pedido['cliente_nome'] ?? $pedido['nome']) . '</td></tr>
                                            <tr><td><strong>CPF</strong></td><td>'
                                                . (
                                                    !empty($pedido['cliente_cpf_cnpj'])
                                                        ? htmlspecialchars((string) $pedido['cliente_cpf_cnpj'])
                                                        : ('<span class="badge bg-warning text-dark">CPF não informado</span>'
                                                            . (((int) ($pedido['usuario_id'] ?? 0)) > 0
                                                                ? (' <a href="/admin/usuarios/editar/' . (int) ($pedido['usuario_id'] ?? 0) . '" class="btn btn-sm btn-warning ms-2">'
                                                                    . '<i class="fas fa-user-pen me-1"></i>Editar cliente</a>')
                                                                : '')
                                                        )
                                                )
                                                . '</td></tr>
                                            <tr><td><strong>Suite Cliente</strong></td><td>' . (!empty($pedido['cliente_suite']) ? (int) $pedido['cliente_suite'] : 'N/A') . '</td></tr>
                                            <tr><td><strong>Data Criação</strong></td><td>' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</td></tr>
                                            <tr><td><strong>Última Atualização</strong></td><td>' . date('d/m/Y H:i', strtotime($pedido['updated_at'])) . '</td></tr>
                                            <tr><td><strong>Usuário ID</strong></td><td>' . $pedido['usuario_id'] . '</td></tr>
                                            <tr><td><strong>Cliente ID</strong></td><td>' . $pedido['cliente_id'] . '</td></tr>
                                            ' . (!empty($pedido['origem_pedido']) ? ('<tr><td><strong>Origem</strong></td><td>' . htmlspecialchars($pedido['origem_pedido']) . (!empty($pedido['admin_criador_nome']) || !empty($pedido['admin_criador_email']) ? ('<div class="small text-muted">Admin: ' . htmlspecialchars((string) ($pedido['admin_criador_nome'] ?? '')) . (!empty($pedido['admin_criador_email']) ? (' &lt;' . htmlspecialchars((string) $pedido['admin_criador_email']) . '&gt;') : '') . '</div>') : '') . '</td></tr>') : '') . '
                                            <tr><td><strong>Quantidade de itens</strong></td><td>' . (int) $quantidadeTotalItens . '</td></tr>';

            // Função helper: só troca o símbolo, sem converter valores
            $fmtPedido = function(float $valor) use ($exibirEmBrl) {
                if ($exibirEmBrl) {
                    return 'R$ ' . number_format($valor, 2, ',', '.');
                }
                return $this->formatarMoeda($valor, 'USD');
            };

            echo '
                                            <tr><td><strong>Subtotal</strong></td><td>' . $fmtPedido((float) ($pedido['subtotal'] ?? 0)) . '</td></tr>
                                            ' . ($temItemGratuito ? '<tr><td><strong><i class="fas fa-gift text-success me-1"></i>Brinde (valor original)</strong></td><td><span class="text-decoration-line-through text-muted">' . $fmtPedido((float) ($freeOfferItemInfo['free_offer_original_price'] ?? 0)) . '</span> <span class="badge bg-success">GRÁTIS</span></td></tr>' : '') . '
                                            <tr><td><strong>Serviços</strong></td><td>' . $fmtPedido((float) ($pedido['servicos'] ?? 0)) . '</td></tr>
                                            ' . (((float) ($pedido['taxa_servico_desconto_aplicado'] ?? 0)) > 0 ? '
                                            <tr><td class="small text-muted ps-3">Taxa de serviço original</td><td class="small text-muted">' . $fmtPedido((float) ($pedido['taxa_servico_original'] ?? 0)) . '</td></tr>
                                            <tr><td class="small text-success ps-3"><i class="fas fa-tags me-1"></i>Desconto promocional' . ((string) ($pedido['taxa_servico_desconto_tipo'] ?? '') === 'percentual' ? ' (' . number_format((float) ($pedido['taxa_servico_desconto_valor'] ?? 0), 2) . '%)' : ' (fixo)') . '</td><td class="small text-success">-' . $fmtPedido((float) ($pedido['taxa_servico_desconto_aplicado'] ?? 0)) . '</td></tr>
                                            <tr><td class="small fw-semibold ps-3">Taxa de serviço final cobrada</td><td class="small fw-semibold">' . $fmtPedido((float) ($pedido['servicos'] ?? 0)) . '</td></tr>
                                            ' : '') . '
                                            <tr><td><strong>Impostos</strong></td><td>' . $fmtPedido((float) ($pedido['impostos'] ?? 0)) . '</td></tr>
                                            ' . ($temItemGratuito ? '<tr><td class="small text-muted ps-3">Imposto do brinde (não cobrado)</td><td><span class="text-decoration-line-through text-muted small">' . $fmtPedido(round($freeOrigPrice * (($pedido['subtotal'] > 0 && $pedido['impostos'] > 0) ? ($pedido['impostos'] / $pedido['subtotal']) : 0), 2)) . '</span> <span class="small text-success">pago pela Braziliana</span></td></tr>' : '') . '
                                            ' . (((float) ($pedido['imposto_local'] ?? 0)) > 0 ? '<tr><td><strong>Imposto local</strong></td><td><span class="badge" style="background:rgba(245,158,11,.15);color:#92400e;border:1px solid rgba(245,158,11,.3);">' . $fmtPedido((float) $pedido['imposto_local']) . '</span></td></tr>' : '') . '
                                            <tr><td><strong>Frete</strong></td><td>' . (((float) ($pedido['frete'] ?? 0)) <= 0 ? 'Frete grátis' : $fmtPedido((float) ($pedido['frete'] ?? 0))) . '</td></tr>
                                            <tr><td><strong>Desconto</strong></td><td>' . $fmtPedido((float) ($pedido['desconto'] ?? 0)) . '</td></tr>
                                            <tr><td><strong>Total</strong></td><td><strong>' . $fmtPedido((float) ($pedido['total'] ?? 0)) . '</strong></td></tr>
                                            <tr><td><strong>Moeda</strong></td><td>' . htmlspecialchars((string) ($pedido['moeda'] ?? 'BRL')) . '</td></tr>
                                            <tr><td><strong>Taxa Conversão</strong></td><td>' . (
                                                (strtoupper((string) ($pedido['moeda'] ?? '')) === 'BRL' && (float) ($pedido['taxa_conversao'] ?? 1) > 1.01)
                                                    ? ('1 USD = R$ ' . number_format((float) $pedido['taxa_conversao'], 2, ',', '.'))
                                                    : htmlspecialchars((string) ($pedido['taxa_conversao'] ?? '1'))
                                            ) . '</td></tr>
                                            <tr><td><strong>End. Entrega ID</strong></td><td>' . ($pedido['endereco_entrega_id'] ?? 'N/A') . '</td></tr>
                                            <tr><td><strong>End. Cobrança ID</strong></td><td>' . ($pedido['endereco_cobranca_id'] ?? 'N/A') . '</td></tr>
                                            <tr><td><strong>Observações</strong></td><td>' . htmlspecialchars($pedido['observacoes'] ?? 'Nenhuma') . '</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>';
                    
                    echo '<div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informações do Pedido</h5>
                                <hr>
                                <p><strong>Status:</strong> ' . htmlspecialchars($this->getStatusLabel((string) ($pedido['status'] ?? ''))) . '</p>
                                <p><strong>Data:</strong> ' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</p>
                                <p><strong>Forma Pagamento:</strong> ' . htmlspecialchars($pedido['forma_pagamento'] ?? 'N/A') . '</p>
                                <p><strong>Moeda:</strong> ' . htmlspecialchars(strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'N/A'))))) . '</p>
                                <p><strong>País Entrega:</strong> ' . htmlspecialchars(strtoupper(trim((string) ($pedido['pais_entrega'] ?? ($pedido['pais'] ?? ($pedido['country'] ?? 'N/A')))))) . '</p>
                                <p><strong>Frete:</strong> ' . (((float) ($pedido['frete'] ?? 0)) <= 0 ? 'Frete grátis' : $this->formatarMoeda((float) ($pedido['frete'] ?? 0), (string) ($pedido['moeda'] ?? 'BRL'))) . '</p>
                                <hr>
                                <div class="mb-3">
                                    <h6 class="mb-2">Pagamento</h6>';

                                    $pgMetodoView = (string) ($pedido['pagamento_metodo'] ?? ($pedido['forma_pagamento'] ?? ''));
                                    if (trim($pgMetodoView) === '') {
                                        $pgMetodoView = 'N/A';
                                    }
                                    $pgStatusView = (string) ($pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? ($pedido['status_pagamento'] ?? '')));
                                    if (trim($pgStatusView) === '') {
                                        // Fallback: detectar status a partir dos splits
                                        try {
                                            $dbSt = \Config\Database::getConnection();
                                            $stSt = $dbSt->prepare('SELECT status FROM pedido_pagamentos WHERE pedido_id = ? ORDER BY id ASC');
                                            $stSt->execute([(int) $pedido['id']]);
                                            $statuses = $stSt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                                            if (!empty($statuses)) {
                                                $allPaid = true;
                                                foreach ($statuses as $s) {
                                                    if (!in_array(strtolower(trim((string) $s)), ['paid', 'pago', 'approved', 'confirmed', 'succeeded'], true)) {
                                                        $allPaid = false;
                                                        break;
                                                    }
                                                }
                                                $pgStatusView = $allPaid ? 'Pago' : 'Pendente';
                                            }
                                        } catch (\Exception $e) {}
                                    }
                                    if (trim($pgStatusView) === '') {
                                        $pgStatusView = 'Pendente';
                                    }

                                    $pgStatusKey = strtolower(trim((string) $pgStatusView));
                                    $statusPedidoKey = strtolower(trim((string) ($pedido['status'] ?? '')));
                                    if (in_array($pgStatusKey, ['approved', 'aprovado', 'confirmed', 'received', 'paid', 'pago', 'succeeded', 'success'], true)) {
                                        if ($statusPedidoKey !== '') {
                                            $pgStatusView = $this->getStatusLabel((string) $statusPedidoKey);
                                        }
                                    }
                                    $pgGatewayView = (string) ($pedido['pagamento_gateway'] ?? ($pedido['payment_gateway'] ?? ($pedido['gateway'] ?? '')));
                                    if (trim($pgGatewayView) === '') {
                                        // Fallback: detectar gateway a partir dos splits (pedido_pagamentos)
                                        try {
                                            $dbGw = \Config\Database::getConnection();
                                            $stGw = $dbGw->prepare('SELECT DISTINCT gateway FROM pedido_pagamentos WHERE pedido_id = ? ORDER BY id ASC');
                                            $stGw->execute([(int) $pedido['id']]);
                                            $gws = $stGw->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                                            if (!empty($gws)) {
                                                $gwLabels = array_map(function($g) {
                                                    $g = strtolower(trim((string) $g));
                                                    if ($g === 'cambioreal') return 'Câmbio Real';
                                                    if ($g === 'appmax') return 'AppMax';
                                                    if ($g === 'stripe') return 'Stripe';
                                                    return strtoupper($g);
                                                }, $gws);
                                                $pgGatewayView = implode(' + ', array_unique($gwLabels));
                                            }
                                        } catch (\Exception $e) {}
                                    }
                                    if (trim($pgGatewayView) === '') {
                                        $pgGatewayView = 'N/A';
                                    }
                                    $pgTransView = (string) ($pedido['pagamento_transacao'] ?? ($pedido['payment_id'] ?? ($pedido['transaction_id'] ?? ($pedido['codigo_transacao'] ?? ''))));
                                    if (trim($pgTransView) === '') {
                                        $pgTransView = 'N/A';
                                    }
                                    $pgDataView = (string) ($pedido['pagamento_data'] ?? ($pedido['pago_em'] ?? ($pedido['paid_at'] ?? ($pedido['data_pagamento'] ?? ''))));

                                    echo '<p class="mb-1"><strong>Método:</strong> ' . htmlspecialchars($pgMetodoView) . '</p>'
                                        . '<p class="mb-1"><strong>Status:</strong> ' . htmlspecialchars($pgStatusView) . '</p>'
                                        . '<p class="mb-1"><strong>Gateway:</strong> ' . htmlspecialchars($pgGatewayView) . '</p>';

                                    // Transação com link para o Stripe Dashboard
                                    if (strtolower($pgGatewayView) === 'stripe' && str_starts_with($pgTransView, 'pi_')) {
                                        echo '<p class="mb-1"><strong>Transação:</strong> <a href="https://dashboard.stripe.com/payments/' . htmlspecialchars($pgTransView) . '" target="_blank" class="text-primary">' . htmlspecialchars($pgTransView) . ' <i class="fas fa-external-link-alt small"></i></a></p>';
                                    } else {
                                        echo '<p class="mb-1"><strong>Transação:</strong> ' . htmlspecialchars($pgTransView) . '</p>';
                                    }

                                    echo '<p class="mb-0"><strong>Data:</strong> ' . (!empty($pgDataView) ? date('d/m/Y H:i', strtotime($pgDataView)) : 'N/A') . '</p>';

                                    // Split: exibir quanto foi para cada conta/gateway (pedido_pagamentos)
                                    try {
                                        $dbSplit = \Config\Database::getConnection();
                                        $stSplit = $dbSplit->prepare('SELECT componente, gateway, metodo, moeda, valor, status, gateway_status, payment_id, invoice_url, bank_slip_url, digitable_line, pix_payload, pix_encoded_image FROM pedido_pagamentos WHERE pedido_id = :p ORDER BY id ASC');
                                        $stSplit->execute([':p' => (int) $pedido['id']]);
                                        $rowsSplit = $stSplit->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                                        if (!empty($rowsSplit)) {
                                            echo '<hr><div class="mb-2"><strong>Split (por conta/gateway):</strong></div>';
                                            echo '<div class="table-responsive"><table class="table table-sm table-bordered">'
                                                . '<thead><tr>'
                                                . '<th>Componente</th><th>Gateway</th><th>Método</th><th>Valor</th><th>Status</th><th>Link/PIX</th><th style="width:140px;">Ações</th>'
                                                . '</tr></thead><tbody>';

                                            foreach ($rowsSplit as $r) {
                                                $comp = strtoupper((string) ($r['componente'] ?? ''));
                                                $compLabel = $comp;
                                                $compMap = ['PRODUTO' => 'Produtos', 'TAXA_SERVICO' => 'Taxa de Serviço', 'IMPOSTO' => 'Impostos', 'PAGAMENTO' => 'Pagamento Total', 'TAXA' => 'Taxa de Serviço'];
                                                if (isset($compMap[$comp])) $compLabel = $compMap[$comp];
                                                $gw = strtolower(trim((string) ($r['gateway'] ?? '')));
                                                $gwLabel = $gw !== '' ? strtoupper($gw) : 'N/A';
                                                if ($gw === 'cambioreal') $gwLabel = 'Câmbio Real';
                                                if ($gw === 'appmax') $gwLabel = 'AppMax';
                                                if ($gw === 'stripe') $gwLabel = 'Stripe';
                                                $met = (string) ($r['metodo'] ?? '');
                                                $moeda = strtoupper((string) ($r['moeda'] ?? 'BRL'));
                                                $val = (float) ($r['valor'] ?? 0);
                                                $st = (string) ($r['status'] ?? '');
                                                $gwStatus = strtoupper(trim((string) ($r['gateway_status'] ?? '')));
                                                $paymentIdRow = trim((string) ($r['payment_id'] ?? ''));

                                                $url = trim((string) ($r['invoice_url'] ?? ''));
                                                $boleto = trim((string) ($r['bank_slip_url'] ?? ''));
                                                $dig = trim((string) ($r['digitable_line'] ?? ''));
                                                $pix = trim((string) ($r['pix_payload'] ?? ''));
                                                $pixImg = trim((string) ($r['pix_encoded_image'] ?? ''));

                                                $link = '';
                                                if ($url !== '') {
                                                    $link = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">Abrir</a>';
                                                } elseif ($boleto !== '') {
                                                    $link = '<a href="' . htmlspecialchars($boleto) . '" target="_blank" rel="noopener">Abrir boleto</a>';
                                                } elseif ($pix !== '') {
                                                    $link = '<span class="small text-muted">PIX disponível</span>';
                                                } elseif ($dig !== '') {
                                                    $link = '<span class="small text-muted">Linha digitável</span>';
                                                }

                                                // Link para Stripe Dashboard quando aplicável
                                                if ($gw === 'stripe' && $paymentIdRow !== '' && str_starts_with($paymentIdRow, 'pi_')) {
                                                    $link .= ($link !== '' ? ' | ' : '') . '<a href="https://dashboard.stripe.com/payments/' . htmlspecialchars($paymentIdRow) . '" target="_blank" class="small"><i class="fas fa-external-link-alt me-1"></i>Stripe</a>';
                                                }

                                                $stNorm = strtolower(trim($st !== '' ? $st : 'pending'));
                                                $metNorm = strtolower(trim((string) $met));
                                                $gwStatusNorm = strtoupper(trim((string) $gwStatus));
                                                $isExpired = ($gwStatusNorm === 'EXPIRED') || (strpos($gwStatusNorm, 'EXPIR') !== false);
                                                $isPending = in_array($stNorm, ['pending', 'rejected', 'expired'], true) || $isExpired;
                                                $podeGerarNovoLink = $isPending && in_array($gw, ['appmax', 'cambioreal'], true);
                                                $pedidoEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ($pedido['customer_email'] ?? '')));
                                                $acoes = $podeGerarNovoLink
                                                    ? ('<button type="button" class="btn btn-sm btn-outline-primary js-gerar-novo-link"'
                                                        . ' data-pedido-id="' . (int) $pedido['id'] . '"'
                                                        . ' data-componente="' . htmlspecialchars(strtolower(trim((string) ($r['componente'] ?? '')))) . '"'
                                                        . ' data-gateway="' . htmlspecialchars($gw) . '"'
                                                        . ' data-email="' . htmlspecialchars($pedidoEmail) . '">' 
                                                        . '<i class="fas fa-link me-1"></i>Gerar Link</button>')
                                                    : '';

                                                echo '<tr>'
                                                    . '<td>' . htmlspecialchars($compLabel) . '</td>'
                                                    . '<td>' . htmlspecialchars($gwLabel) . '</td>'
                                                    . '<td>' . htmlspecialchars($met !== '' ? $met : 'N/A') . '</td>'
                                                    . '<td class="text-end">' . htmlspecialchars($this->formatarMoeda($val, $moeda)) . '</td>'
                                                    . '<td>' . htmlspecialchars($st !== '' ? $st : 'pending') . ($isExpired ? ' <span class="badge bg-secondary">EXPIRADO</span>' : '') . '</td>'
                                                    . '<td>' . $link . '</td>'
                                                    . '<td>' . $acoes . '</td>'
                                                    . '</tr>';
                                            }

                                            echo '</tbody></table></div>';

                                            echo '<div class="modal fade" id="modalNovoLink" tabindex="-1" aria-hidden="true">'
                                                . '<div class="modal-dialog">'
                                                . '<div class="modal-content">'
                                                . '<div class="modal-header">'
                                                . '<h5 class="modal-title"><i class="fas fa-link me-1"></i>Gerar Link de Pagamento</h5>'
                                                . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                                                . '</div>'
                                                . '<div class="modal-body">'
                                                . '<div id="novoLinkAlert" class="alert alert-info" style="display:none;"></div>'
                                                . '<label class="form-label">E-mail do cliente</label>'
                                                . '<input type="email" id="novoLinkEmail" class="form-control mb-3" value="' . htmlspecialchars($pedidoEmail) . '" autocomplete="email" />'
                                                . '<div class="form-text mb-3">Confirme/ajuste o e-mail antes de gerar.</div>'
                                                . '<label class="form-label">Link de pagamento</label>'
                                                . '<div class="input-group">'
                                                . '<input type="text" id="novoLinkUrl" class="form-control" readonly placeholder="Clique em Gerar Link..." />'
                                                . '<button type="button" class="btn btn-outline-secondary" id="btnCopiarLink" disabled><i class="fas fa-copy"></i></button>'
                                                . '</div>'
                                                . '<div class="d-flex gap-2 mt-3">'
                                                . '<button type="button" class="btn btn-primary" id="btnGerarLinkConfirm"><i class="fas fa-link me-1"></i>Gerar Link</button>'
                                                . '<a href="#" class="btn btn-outline-success" id="btnAbrirLink" target="_blank" rel="noopener" style="display:none;"><i class="fas fa-external-link-alt me-1"></i>Abrir</a>'
                                                . '</div>'
                                                . '</div>'
                                                . '<div class="modal-footer">'
                                                . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>'
                                                . '</div>'
                                                . '</div>'
                                                . '</div>'
                                                . '</div>';

                                            echo <<<'LINKSCRIPT'
<script>(function(){
    function qs(s){ return document.querySelector(s); }
    var pending = {pedidoId:"", componente:"", gateway:"", email:""};
    function linkAlert(msg, cls){
        var el = qs("#novoLinkAlert");
        if(!el) return;
        el.style.display = msg ? "block" : "none";
        el.className = "alert " + (cls||"alert-info");
        el.textContent = msg||"";
    }
    document.addEventListener("click", function(ev){
        var btn = ev.target && ev.target.closest ? ev.target.closest(".js-gerar-novo-link") : null;
        if(!btn) return;
        pending = {
            pedidoId: btn.getAttribute("data-pedido-id")||"",
            componente: btn.getAttribute("data-componente")||"",
            gateway: btn.getAttribute("data-gateway")||"",
            email: btn.getAttribute("data-email")||""
        };
        linkAlert("Confirme o e-mail e clique em Gerar Link.", "alert-info");
        var emailInput = qs("#novoLinkEmail");
        if(emailInput) emailInput.value = pending.email;
        var urlInput = qs("#novoLinkUrl");
        if(urlInput) urlInput.value = "";
        var btnCopy = qs("#btnCopiarLink");
        if(btnCopy) btnCopy.disabled = true;
        var btnOpen = qs("#btnAbrirLink");
        if(btnOpen){ btnOpen.style.display = "none"; btnOpen.href = "#"; }
        var el = qs("#modalNovoLink");
        if(el && window.bootstrap && window.bootstrap.Modal){
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        }
    });
    var btnGerar = qs("#btnGerarLinkConfirm");
    if(btnGerar){
        btnGerar.addEventListener("click", function(){
            var email = (qs("#novoLinkEmail")||{}).value||"";
            if(!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
                linkAlert("Informe um e-mail válido.", "alert-warning");
                return;
            }
            if(!pending.pedidoId){
                linkAlert("Pedido inválido.", "alert-warning");
                return;
            }
            linkAlert("Gerando link de pagamento...", "alert-info");
            btnGerar.disabled = true;
            var body = new URLSearchParams();
            body.set("componente", pending.componente);
            body.set("gateway", pending.gateway);
            body.set("email", email.trim());
            fetch("/admin/pedidos/gerar-novo-pix/" + encodeURIComponent(pending.pedidoId), {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: body.toString()
            }).then(function(r){ return r.json(); })
            .then(function(data){
                btnGerar.disabled = false;
                if(!data || !data.success){
                    linkAlert((data && data.error) ? data.error : "Falha ao gerar link", "alert-warning");
                    return;
                }
                var link = data.payment_link || "";
                linkAlert("Link gerado! Copie e envie ao cliente.", "alert-success");
                var urlInput = qs("#novoLinkUrl");
                if(urlInput) urlInput.value = link;
                var btnCopy = qs("#btnCopiarLink");
                if(btnCopy) btnCopy.disabled = !link;
                var btnOpen = qs("#btnAbrirLink");
                if(btnOpen && link){
                    btnOpen.href = link;
                    btnOpen.style.display = "inline-block";
                }
            }).catch(function(){
                btnGerar.disabled = false;
                linkAlert("Erro de rede ao gerar link.", "alert-warning");
            });
        });
    }
    var btnCopy = qs("#btnCopiarLink");
    if(btnCopy){
        btnCopy.addEventListener("click", function(){
            var v = (qs("#novoLinkUrl")||{}).value||"";
            if(!v) return;
            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(v).then(function(){ linkAlert("Link copiado!", "alert-success"); });
            } else {
                var inp = qs("#novoLinkUrl");
                if(inp){ inp.focus(); inp.select(); try{ document.execCommand("copy"); linkAlert("Link copiado!", "alert-success"); } catch(e){} }
            }
        });
    }
})();</script>
LINKSCRIPT;
                                        }
                                    } catch (\Exception $e) {
                                    }

                                    $pgGateway = (string) ($pedido['pagamento_gateway'] ?? '');
                                    $pgMetodo = strtoupper((string) ($pedido['pagamento_metodo'] ?? $pedido['forma_pagamento'] ?? ''));
                                    $pgStatus = strtoupper((string) ($pedido['pagamento_status'] ?? ''));
                                    $podeReemitir = ($pgGateway === 'asaas') && in_array($pgMetodo, ['PIX', 'BOLETO', 'PXD', 'PIX '], true) && !in_array($pgStatus, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true);

                                    if ($podeReemitir) {
                                        echo '<form method="POST" action="/admin/pedidos/reemitir-pagamento/' . (int) $pedido['id'] . '" class="mt-2">'
                                            . '<button type="submit" class="btn btn-outline-secondary btn-sm">Gerar nova cobrança</button>'
                                            . '</form>';
                                    }

                                    $pgGateway2 = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
                                    $pgMetodo2 = strtoupper((string) ($pedido['forma_pagamento'] ?? ($pedido['pagamento_metodo'] ?? '')));
                                    $pgStatus2 = strtoupper((string) ($pedido['payment_status'] ?? ($pedido['pagamento_status'] ?? '')));
                                    $isPending2 = !in_array($pgStatus2, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true);
                                    $pixPayload = '';
                                    if ($pgGateway2 === 'appmax' && $pgMetodo2 === 'PIX' && $isPending2) {
                                        $pixPayload = (string) (
                                            $pedido['payment_pix_payload'] ??
                                            $pedido['pix_payload'] ??
                                            $pedido['pix_emv'] ??
                                            $pedido['pix_copy_paste'] ??
                                            ''
                                        );
                                    }

                                    $stripeInvoiceUrl = '';
                                    if ($pgGateway2 === 'stripe' && $isPending2) {
                                        $stripeInvoiceUrl = (string) (
                                            $pedido['payment_invoice_url'] ??
                                            $pedido['invoice_url'] ??
                                            $pedido['invoiceUrl'] ??
                                            ''
                                        );
                                        $stripeInvoiceUrl = trim($stripeInvoiceUrl);
                                    }

                                    if ($pixPayload !== '') {
                                        $pixPayloadEsc = htmlspecialchars($pixPayload, ENT_QUOTES, 'UTF-8');
                                        echo '<div class="mt-3">'
                                            . '<div class="small text-muted mb-1">PIX (copia e cola)</div>'
                                            . '<textarea class="form-control" rows="3" readonly id="admin-pix-payload">' . $pixPayloadEsc . '</textarea>'
                                            . '<button type="button" class="btn btn-sm btn-outline-dark mt-2" id="admin-pix-copy-btn" onclick="copiarPixAdmin()">Copiar PIX</button>'
                                            . '<div id="admin-pix-copied" class="small text-success mt-1" style="display:none;">Copiado!</div>'
                                            . '</div>';
                                    }

                                    if ($stripeInvoiceUrl !== '') {
                                        $stripeEsc = htmlspecialchars($stripeInvoiceUrl, ENT_QUOTES, 'UTF-8');
                                        echo '<div class="mt-3">'
                                            . '<div class="small text-muted mb-1">Stripe (link de pagamento)</div>'
                                            . '<div class="d-flex gap-2 flex-wrap">'
                                            . '<a class="btn btn-sm btn-outline-primary" href="' . $stripeEsc . '" target="_blank" rel="noopener">Abrir link</a>'
                                            . '<button type="button" class="btn btn-sm btn-outline-dark" id="admin-stripe-copy-btn" onclick="copiarStripeAdmin()">Copiar link</button>'
                                            . '</div>'
                                            . '<textarea class="form-control mt-2" rows="2" readonly id="admin-stripe-link">' . $stripeEsc . '</textarea>'
                                            . '<div id="admin-stripe-copied" class="small text-success mt-1" style="display:none;">Copiado!</div>'
                                            . '</div>';
                                    }

                                    $pdoLocal = null;
                                    try {
                                        if (isset($pdoCols) && ($pdoCols instanceof \PDO)) {
                                            $pdoLocal = $pdoCols;
                                        } else {
                                            $pdoLocal = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
                                        }
                                    } catch (\Exception $e) {
                                        $pdoLocal = null;
                                    }

                                    $fp = strtolower(trim((string) ($pedido['forma_pagamento'] ?? '')));
                                    $statusBloqueadoPorComprovante = false;
                                    if (in_array($fp, ['nomad_transferencia', 'appmax_pix', 'pagdev'], true)) {
                                        $hasDocs = false;
                                        if ($pdoLocal instanceof \PDO) {
                                            try {
                                                $st = $pdoLocal->prepare('SHOW TABLES LIKE ?');
                                                $st->execute(['pedidos_pagamento_documentos']);
                                                $hasDocs = (bool) $st->fetchColumn();
                                            } catch (\Exception $e) {
                                                $hasDocs = false;
                                            }
                                        }

                                        // Pagamentos offline exigem comprovante; bloquear status por padrão.
                                        $statusBloqueadoPorComprovante = true;

                                        echo '<hr>';
                                        echo '<div class="mb-3" id="comprovante">'
                                            . '<h6 class="mb-2">Comprovantes de Pagamento</h6>';

                                        if (!$hasDocs) {
                                            echo '<div class="alert alert-warning">'
                                                . '<div><strong>Aguardando comprovantes.</strong> Para anexar, é necessário criar a tabela <code>pedidos_pagamento_documentos</code>.</div>'
                                                . '<div class="small mt-2">Rode as migrations: <strong>055_create_pedidos_pagamento_documentos.sql</strong>, <strong>056_add_fk_pedidos_pagamento_documentos.sql</strong> e <strong>131_add_tipo_to_pedidos_pagamento_documentos.sql</strong>.</div>'
                                                . '</div>';
                                            echo '<button type="button" class="btn btn-sm btn-secondary" disabled>Anexar comprovantes</button>';
                                        } else {
                                            $temColTipo = false;
                                            try {
                                                if ($pdoLocal instanceof \PDO) {
                                                    $stCols = $pdoLocal->query('DESCRIBE pedidos_pagamento_documentos');
                                                    $colsDoc = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                                                    $temColTipo = in_array('tipo', $colsDoc, true);
                                                }
                                            } catch (\Exception $e) {}

                                            $docProdutos = null;
                                            $docTaxas    = null;
                                            try {
                                                if ($pdoLocal instanceof \PDO) {
                                                    if ($temColTipo) {
                                                        $stDoc = $pdoLocal->prepare('SELECT id, status, arquivo_path, uploaded_at FROM pedidos_pagamento_documentos WHERE pedido_id = :pid AND metodo = :metodo AND tipo = :tipo ORDER BY id DESC LIMIT 1');
                                                        $stDoc->execute([':pid' => (int) $pedido['id'], ':metodo' => $fp, ':tipo' => 'produtos']);
                                                        $rowP = $stDoc->fetch(\PDO::FETCH_ASSOC);
                                                        $docProdutos = is_array($rowP) ? $rowP : null;
                                                        $stDoc->execute([':pid' => (int) $pedido['id'], ':metodo' => $fp, ':tipo' => 'taxas']);
                                                        $rowT = $stDoc->fetch(\PDO::FETCH_ASSOC);
                                                        $docTaxas = is_array($rowT) ? $rowT : null;
                                                    } else {
                                                        $stDoc = $pdoLocal->prepare('SELECT id, status, arquivo_path, uploaded_at FROM pedidos_pagamento_documentos WHERE pedido_id = :pid AND metodo = :metodo ORDER BY id DESC LIMIT 1');
                                                        $stDoc->execute([':pid' => (int) $pedido['id'], ':metodo' => $fp]);
                                                        $rowP = $stDoc->fetch(\PDO::FETCH_ASSOC);
                                                        $docProdutos = is_array($rowP) ? $rowP : null;
                                                    }
                                                }
                                            } catch (\Exception $e) {}

                                            $okProdutos = !empty($docProdutos['arquivo_path']) && strtolower((string)($docProdutos['status'] ?? '')) === 'ok';
                                            $okTaxas    = !$temColTipo || (!empty($docTaxas['arquivo_path']) && strtolower((string)($docTaxas['status'] ?? '')) === 'ok');
                                            $statusBloqueadoPorComprovante = !($okProdutos && $okTaxas);

                                            echo '<div class="row g-3">';

                                            // Bloco Produtos
                                            echo '<div class="col-md-6">';
                                            echo '<div class="card border h-100"><div class="card-body">';
                                            echo '<h6 class="card-title mb-3"><i class="fas fa-box me-2 text-primary"></i>Comprovante de Produtos</h6>';
                                            if ($okProdutos) {
                                                $atP = (string)($docProdutos['uploaded_at'] ?? '');
                                                echo '<div class="alert alert-success py-2 mb-2"><strong>Recebido.</strong>'
                                                    . (!empty($atP) ? ' <span class="small">Enviado em ' . htmlspecialchars(date('d/m/Y H:i', strtotime($atP))) . '</span>' : '')
                                                    . '</div>';
                                                echo '<a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars((string)($docProdutos['arquivo_path'] ?? '')) . '" target="_blank" rel="noopener">Abrir arquivo</a>';
                                            } else {
                                                echo '<div class="alert alert-warning py-2 mb-2"><strong>Aguardando.</strong> Comprovante do pagamento dos produtos.</div>';
                                                echo '<form method="POST" action="/admin/pedidos/upload-comprovante/' . (int) $pedido['id'] . '" enctype="multipart/form-data">'
                                                    . '<input type="hidden" name="tipo_comprovante" value="produtos">'
                                                    . '<div class="mb-2"><input class="form-control form-control-sm" type="file" name="comprovante" accept="image/*,application/pdf" required></div>'
                                                    . '<button type="submit" class="btn btn-sm btn-primary">Anexar</button>'
                                                    . '</form>';
                                            }
                                            echo '</div></div></div>';

                                            // Bloco Taxas/Impostos
                                            echo '<div class="col-md-6">';
                                            echo '<div class="card border h-100"><div class="card-body">';
                                            echo '<h6 class="card-title mb-3"><i class="fas fa-receipt me-2 text-warning"></i>Comprovante de Taxas / Impostos</h6>';
                                            if (!$temColTipo) {
                                                echo '<div class="alert alert-secondary py-2 small">Rode a migration <strong>131_add_tipo_to_pedidos_pagamento_documentos.sql</strong> para habilitar este campo.</div>';
                                            } elseif ($okTaxas) {
                                                $atT = (string)($docTaxas['uploaded_at'] ?? '');
                                                echo '<div class="alert alert-success py-2 mb-2"><strong>Recebido.</strong>'
                                                    . (!empty($atT) ? ' <span class="small">Enviado em ' . htmlspecialchars(date('d/m/Y H:i', strtotime($atT))) . '</span>' : '')
                                                    . '</div>';
                                                echo '<a class="btn btn-sm btn-outline-dark" href="' . htmlspecialchars((string)($docTaxas['arquivo_path'] ?? '')) . '" target="_blank" rel="noopener">Abrir arquivo</a>';
                                            } else {
                                                echo '<div class="alert alert-warning py-2 mb-2"><strong>Aguardando.</strong> Comprovante de taxas, impostos.</div>';
                                                echo '<form method="POST" action="/admin/pedidos/upload-comprovante/' . (int) $pedido['id'] . '" enctype="multipart/form-data">'
                                                    . '<input type="hidden" name="tipo_comprovante" value="taxas">'
                                                    . '<div class="mb-2"><input class="form-control form-control-sm" type="file" name="comprovante" accept="image/*,application/pdf" required></div>'
                                                    . '<button type="submit" class="btn btn-sm btn-primary">Anexar</button>'
                                                    . '</form>';
                                            }
                                            echo '</div></div></div>';

                                            echo '</div>'; // row
                                        }

                                        echo '</div>';
                                    }

                                echo '</div>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label">Atualizar Status:</label>
                                    <select class="form-select" id="novo_status">
                                        <option value="">Selecione...</option>
                                        ' . $this->buildStatusOptions((string)($pedido['status'] ?? ''), false) . '
                                    </select>
                                </div>
                                ' . (($statusBloqueadoPorComprovante ?? false) ? '<div class="alert alert-warning">Envie o comprovante para liberar a edição do status.</div>' : '') . '
                                <button onclick="atualizarStatus()" class="btn btn-primary w-100" ' . (($statusBloqueadoPorComprovante ?? false) ? 'disabled' : '') . '>Atualizar Status</button>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0">Dados do Cliente</h5>
                                <a href="#" class="btn btn-sm btn-outline-primary js-abrir-editar-cliente-pedido"><i class="fas fa-pen-to-square me-1"></i>Editar dados</a>
                            </div>
                            <div class="card-body">
                                <p><strong>Nome:</strong> ' . htmlspecialchars($pedido['cliente_nome'] ?? 'Visitante') . '</p>
                                <p><strong>Email:</strong> ' . htmlspecialchars($pedido['cliente_email'] ?? 'N/A') . '</p>
                                <p><strong>Telefone:</strong> ' . htmlspecialchars($pedido['cliente_telefone'] ?? 'N/A') . '</p>
                                <p><strong>Suite:</strong> ' . (!empty($pedido['cliente_suite']) ? (int) $pedido['cliente_suite'] : 'N/A') . '</p>
                                <hr>
                                <p><strong>Endereço:</strong><br>' .
                                    htmlspecialchars(
                                        trim(
                                            ($pedido['endereco_entrega'] ?? '') .
                                            (!empty($pedido['numero_entrega']) ? ', ' . $pedido['numero_entrega'] : '') .
                                            (!empty($pedido['complemento_entrega']) ? ' - ' . $pedido['complemento_entrega'] : '') .
                                            (!empty($pedido['bairro_entrega']) ? ' - ' . $pedido['bairro_entrega'] : '') .
                                            (!empty($pedido['cidade_entrega']) ? ' - ' . $pedido['cidade_entrega'] : '') .
                                            (!empty($pedido['estado_entrega']) ? '/' . $pedido['estado_entrega'] : '') .
                                            (!empty($pedido['cep_entrega']) ? ' - CEP: ' . $pedido['cep_entrega'] : '')
                                        )
                                    ) .
                                '</p>';

        // Destinatário (entrega para outra pessoa)
        $destNome = trim((string) ($pedido['destinatario_nome'] ?? ''));
        $destDoc = trim((string) ($pedido['destinatario_documento'] ?? ''));
        $destTel = trim((string) ($pedido['destinatario_telefone'] ?? ''));
        $temDestinatario = ($destNome !== '' || $destDoc !== '' || $destTel !== '');

        if ($temDestinatario) {
            echo '<hr>
                                <div class="mb-0">
                                    <span class="badge bg-info text-dark mb-2"><i class="fas fa-user-friends me-1"></i>Entrega para outra pessoa</span>
                                    <p class="mb-1"><strong>Destinatário:</strong> ' . htmlspecialchars($destNome ?: 'N/A') . '</p>'
                                    . ($destDoc !== '' ? '<p class="mb-1"><strong>CPF/Doc:</strong> ' . htmlspecialchars($destDoc) . '</p>' : '')
                                    . ($destTel !== '' ? '<p class="mb-0"><strong>Telefone:</strong> ' . htmlspecialchars($destTel) . '</p>' : '')
                                . '</div>';
        }

        echo '
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function atualizarStatus() {
            const status = document.getElementById("novo_status").value;
            if (status) {
                let estornar = 0;
                if (status === "cancelado") {
                    estornar = confirm("Deseja estornar/cancelar o pagamento também?") ? 1 : 0;
                }
                window.location.href = "/admin/pedidos/atualizar-status/' . $id . '/" + status + "?estornar=" + estornar;
            }
        }

        function copiarPixAdmin() {
            const el = document.getElementById("admin-pix-payload");
            const msg = document.getElementById("admin-pix-copied");
            const btn = document.getElementById("admin-pix-copy-btn");
            if (!el) return;
            const txt = el.value || el.textContent || "";
            if (!txt) return;

            const old = btn ? btn.innerText : "";
            const ok = () => {
                if (msg) {
                    msg.style.display = "block";
                    setTimeout(() => { msg.style.display = "none"; }, 1800);
                }
                if (btn) {
                    btn.innerText = "Copiado";
                    setTimeout(() => { btn.innerText = old || "Copiar PIX"; }, 1800);
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(ok).catch(() => {
                    el.focus();
                    el.select();
                    try { document.execCommand("copy"); ok(); } catch (e) {}
                });
                return;
            }
            el.focus();
            el.select();
            try {
                document.execCommand("copy");
                ok();
            } catch (e) {
            }
        }

        function copiarStripeAdmin() {
            const el = document.getElementById("admin-stripe-link");
            const msg = document.getElementById("admin-stripe-copied");
            const btn = document.getElementById("admin-stripe-copy-btn");
            if (!el) return;
            const txt = el.value || el.textContent || "";
            if (!txt) return;

            const old = btn ? btn.innerText : "";
            const ok = () => {
                if (msg) {
                    msg.style.display = "block";
                    setTimeout(() => { msg.style.display = "none"; }, 1800);
                }
                if (btn) {
                    btn.innerText = "Copiado";
                    setTimeout(() => { btn.innerText = old || "Copiar link"; }, 1800);
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(ok).catch(() => {
                    el.focus();
                    el.select();
                    try { document.execCommand("copy"); ok(); } catch (e) {}
                });
                return;
            }
            el.focus();
            el.select();
            try {
                document.execCommand("copy");
                ok();
            } catch (e) {
            }
        }
    </script>';

    // Renderizar scripts
    renderAdminScripts();
        
    echo '</body>
</html>';
    exit;

    }

    public function uploadComprovante(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $pedidoId = (int) $request->getParam('id');
        if ($pedidoId <= 0) {
            $this->redirect('/admin/pedidos?erro=' . urlencode('Pedido inválido'));
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $colsPedidos = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $formaPagamento = '';
            if (is_array($colsPedidos) && in_array('forma_pagamento', $colsPedidos, true)) {
                $stmt = $pdo->prepare('SELECT forma_pagamento FROM pedidos WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $pedidoId]);
                $formaPagamento = (string) ($stmt->fetchColumn() ?: '');
            }

            $fp = strtolower(trim($formaPagamento));
            if (!in_array($fp, ['nomad_transferencia', 'appmax_pix', 'pagdev'], true)) {
                $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
            }

            $hasDocs = false;
            try {
                $st = $pdo->prepare('SHOW TABLES LIKE ?');
                $st->execute(['pedidos_pagamento_documentos']);
                $hasDocs = (bool) $st->fetchColumn();
            } catch (\Exception $e) {
                $hasDocs = false;
            }

            if (!$hasDocs) {
                $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
            }

            if (!isset($_FILES['comprovante']) || !is_array($_FILES['comprovante'])) {
                $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
            }

            $f = $_FILES['comprovante'];
            $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
            }

            $tmp = (string) ($f['tmp_name'] ?? '');
            $origName = (string) ($f['name'] ?? '');
            $mime = (string) ($f['type'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
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
            $targetDir = rtrim($baseDir, '/\\') . '/uploads/comprovantes';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $fname = 'pedido_' . (int) $pedidoId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . $ext;
            $absPath = rtrim($targetDir, '/\\') . '/' . $fname;
            $relPath = '/uploads/comprovantes/' . $fname;

            if (!move_uploaded_file($tmp, $absPath)) {
                $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
            }

            $colsDocs = [];
            try {
                $stmtColsD = $pdo->query('DESCRIBE pedidos_pagamento_documentos');
                $colsDocs = $stmtColsD ? $stmtColsD->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $colsDocs = [];
            }

            // Tipo do comprovante: 'produtos' ou 'taxas'
            $tipoComprovante = 'produtos';
            $tipoRaw = strtolower(trim((string) ($request->getParam('tipo_comprovante', 'produtos'))));
            if ($tipoRaw === 'taxas') {
                $tipoComprovante = 'taxas';
            }
            $temColTipo = in_array('tipo', $colsDocs, true);

            $docId = 0;
            try {
                if ($temColTipo) {
                    $st = $pdo->prepare('SELECT id FROM pedidos_pagamento_documentos WHERE pedido_id = :pid AND metodo = :metodo AND tipo = :tipo LIMIT 1');
                    $st->execute([':pid' => $pedidoId, ':metodo' => $fp, ':tipo' => $tipoComprovante]);
                } else {
                    $st = $pdo->prepare('SELECT id FROM pedidos_pagamento_documentos WHERE pedido_id = :pid AND metodo = :metodo LIMIT 1');
                    $st->execute([':pid' => $pedidoId, ':metodo' => $fp]);
                }
                $docId = (int) ($st->fetchColumn() ?: 0);
            } catch (\Exception $e) {
                $docId = 0;
            }

            $adminId = null;
            try {
                $auth = new AuthService();
                $u = $auth->getUsuarioLogado();
                if (is_array($u) && (($u['perfil'] ?? '') === 'admin')) {
                    $adminId = (int) ($u['id'] ?? 0);
                }
            } catch (\Exception $e) {
                $adminId = null;
            }

            if ($docId > 0) {
                $set = ['status = :status', 'arquivo_path = :path', 'mime = :mime', 'uploaded_at = NOW()'];
                $params = [':id' => $docId, ':status' => 'ok', ':path' => $relPath, ':mime' => $mime];
                if ($temColTipo) {
                    $set[] = 'tipo = :tipo';
                    $params[':tipo'] = $tipoComprovante;
                }
                if ($adminId !== null && $adminId > 0 && in_array('usuario_id', $colsDocs, true)) {
                    $set[] = 'usuario_id = :usuario_id';
                    $params[':usuario_id'] = (int) $adminId;
                }
                $sql = 'UPDATE pedidos_pagamento_documentos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $st = $pdo->prepare($sql);
                $st->execute($params);
            } else {
                $insertCols = ['pedido_id', 'metodo', 'status', 'arquivo_path', 'mime', 'uploaded_at'];
                $insertVals = [':pedido_id', ':metodo', ':status', ':path', ':mime', 'NOW()'];
                $params = [':pedido_id' => $pedidoId, ':metodo' => $fp, ':status' => 'ok', ':path' => $relPath, ':mime' => $mime];
                if ($temColTipo) {
                    $insertCols[] = 'tipo';
                    $insertVals[] = ':tipo';
                    $params[':tipo'] = $tipoComprovante;
                }
                if ($adminId !== null && $adminId > 0 && in_array('usuario_id', $colsDocs, true)) {
                    $insertCols[] = 'usuario_id';
                    $insertVals[] = ':usuario_id';
                    $params[':usuario_id'] = (int) $adminId;
                }
                $sql = 'INSERT INTO pedidos_pagamento_documentos (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
                $st = $pdo->prepare($sql);
                $st->execute($params);
            }

            $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
        } catch (\Exception $e) {
            $this->redirect('/admin/pedidos/detalhes/' . (int) $pedidoId);
        }
    }

    public function reemitirPagamento(Request $request) {
        $id = (int) $request->getParam('id');
        if (empty($id)) {
            header('Location: /admin/pedidos');
            exit;
        }

        try {
            $paymentService = new PaymentService();

            $pedido = null;
            try {
                $pedidoModel = new PedidoEcommerce();
                $pedido = $pedidoModel->getComDetalhes($id);
            } catch (\Exception $e) {
                $pedido = null;
            }

            $gateway = is_array($pedido) ? (string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? '')) : '';
            if ($gateway !== 'asaas') {
                header('Location: /admin/pedidos/detalhes/' . $id . '?reemitido=0');
                exit;
            }

            $paymentService->reemitirCobrancaAsaasPorPedido($id);
            header('Location: /admin/pedidos/detalhes/' . $id . '?reemitido=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /admin/pedidos/detalhes/' . $id . '?reemitido=0');
            exit;
        }
    }

    private function getCarneBadgeHtml(array $pedido): string {
        $fp = strtolower(trim((string) ($pedido['forma_pagamento'] ?? '')));
        $st = strtolower(trim((string) ($pedido['status'] ?? '')));
        if ($fp === 'carne_braziliana' || in_array($st, ['carne_pagando', 'carne_aguardando'], true)) {
            return ' <span class="badge text-white" style="background:#6f42c1;font-size:.7rem;"><i class="fas fa-file-invoice-dollar me-1"></i>Carnê</span>';
        }
        return '';
    }

    private function formatarMoeda($valor, $moeda) {
        if ($moeda === 'USD') {
            return '$ ' . number_format($valor, 2, '.', ',');
        } else {
            return 'R$ ' . number_format($valor, 2, ',', '.');
        }
    }

    private function getStatusLabel(string $status): string {
        $map = array_merge(self::getStatusList(), [
            'enviado'          => 'Etiqueta Gerada', // alias legado
            'pagamento'        => 'Pagamento',
            'aprovado'         => 'Aprovado',
            'separacao'        => 'Separação',
            'carne_pagando'    => 'Carnê em Pagamento',
            'carne_aguardando' => 'Carnê Aguardando',
        ]);
        $status = trim($status);
        return $map[$status] ?? ($status !== '' ? ucfirst($status) : '');
    }
    
    private function getStatusIcon($status) {
        $icons = [
            'pendente'                       => 'fas fa-clock',
            'processando'                    => 'fas fa-cogs',
            'pago'                           => 'fas fa-check-circle',
            'produto_consolidado'            => 'fas fa-boxes-stacked',
            'itens_comprados'                => 'fas fa-shopping-cart',
            'etiqueta_gerada'                => 'fas fa-tag',
            'em_transporte'                  => 'fas fa-truck-moving',
            'aguardando_liberacao_aduaneira' => 'fas fa-passport',
            'enviado_ao_destinatario'        => 'fas fa-route',
            'enviado'                        => 'fas fa-truck',
            'entregue'                       => 'fas fa-check-double',
            'cancelado'                      => 'fas fa-times-circle',
        ];
        if (!isset($icons[$status])) {
            $icons['pagamento'] = 'fas fa-credit-card';
            $icons['aprovado']  = 'fas fa-check-circle';
            $icons['separacao'] = 'fas fa-box';
        }
        return $icons[$status] ?? 'fas fa-question-circle';
    }

    private function getStatusColor($status) {
        $colors = [
            'pendente'                       => 'warning',
            'processando'                    => 'primary',
            'pago'                           => 'success',
            'produto_consolidado'            => 'dark',
            'itens_comprados'                => 'info',
            'etiqueta_gerada'                => 'primary',
            'em_transporte'                  => 'info',
            'aguardando_liberacao_aduaneira' => 'secondary',
            'enviado_ao_destinatario'        => 'info',
            'enviado'                        => 'info',
            'entregue'                       => 'success',
            'cancelado'                      => 'danger',
        ];
        if (!isset($colors[$status])) {
            $colors['pagamento'] = 'info';
            $colors['aprovado']  = 'success';
            $colors['separacao'] = 'primary';
        }
        return $colors[$status] ?? 'secondary';
    }

    /**
     * Retorna os status na ordem do fluxo logístico.
     * Usado para gerar <option> em todos os selects de status.
     */
    public static function getStatusList(): array {
        return [
            'pendente'                       => 'Pendente',
            'processando'                    => 'Processando',
            'pago'                           => 'Pago',
            'itens_comprados'                => 'Itens Comprados',
            'produto_consolidado'            => 'Caixa Fechada',
            'etiqueta_gerada'                => 'Etiqueta Gerada',
            'em_transporte'                  => 'Em Transporte',
            'aguardando_liberacao_aduaneira' => 'Aguardando Liberação Aduaneira',
            'enviado_ao_destinatario'        => 'Enviado ao Destinatário',
            'entregue'                       => 'Entregue',
            'cancelado'                      => 'Cancelado',
        ];
    }

    /** Gera as <option> de status com o valor atual selecionado. */
    private function buildStatusOptions(string $current, bool $withEmpty = false): string {
        $html = $withEmpty ? '<option value="">Selecione...</option>' : '';
        foreach (self::getStatusList() as $val => $label) {
            $sel = ($current === $val) ? ' selected' : '';
            $html .= '<option value="' . $val . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        return $html;
    }

    public function comissoes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $admin = $auth->getUsuarioLogado();
        $perfil = strtolower(trim((string) ($admin['perfil'] ?? '')));

        $escopo = strtolower(trim((string) $request->getParam('escopo', '')));
        if ($escopo !== 'todos') {
            $escopo = 'me';
        }

        $pedidoModel = new PedidoEcommerce();
        $resumo = [
            'pedidos' => [],
            'total_faturado' => 0.0,
            'total_custo_produtos' => 0.0,
            'total_liquido' => 0.0,
            'percentual_comissao' => 0.0,
            'valor_comissao' => 0.0,
            'faixas' => [],
        ];
        try {
            $adminId = (int) ($admin['id'] ?? 0);
            if ($perfil === 'admin') {
                if ($escopo === 'todos') {
                    $resumo = $pedidoModel->getResumoComissoesPedidosManuaisTodos();
                } else {
                    $resumo = $pedidoModel->getResumoComissoesPedidosManuaisPorAdminCriador($adminId);
                }
            } else {
                $resumo = $pedidoModel->getResumoComissoesPedidosManuaisPorAdminCriador($adminId);
            }
        } catch (\Exception $e) {
            $resumo = $resumo;
        }

        // Comissões de processamento (pedidos online finalizados com comprovante)
        $resumoProc = [
            'por_moeda' => [
                'USD' => ['base_liquida' => 0.0, 'valor_comissao' => 0.0, 'percentual_medio' => 0.0, 'linhas' => []],
                'BRL' => ['base_liquida' => 0.0, 'valor_comissao' => 0.0, 'percentual_medio' => 0.0, 'linhas' => []],
            ],
        ];
        try {
            $pdo = \Config\Database::getConnection();
            $stT = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stT->execute(['comissoes_processamento']);
            $has = (int) ($stT->fetchColumn() ?: 0) > 0;
            if ($has) {
                $where = '';
                $params = [];
                if ($perfil !== 'admin' || $escopo !== 'todos') {
                    $where = ' WHERE usuario_id = ?';
                    $params[] = (int) ($admin['id'] ?? 0);
                }

                $st = $pdo->prepare('SELECT pedido_id, usuario_id, moeda, percentual, base_liquida, valor_comissao, created_at FROM comissoes_processamento' . $where . ' ORDER BY created_at DESC LIMIT 500');
                $st->execute($params);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                // Enriquecer com dados do pedido (valor pago, impostos, custo, líquido)
                $pedidoInfoMap = [];
                try {
                    $pedidoIds = [];
                    foreach ($rows as $r) {
                        $pid = (int) ($r['pedido_id'] ?? 0);
                        if ($pid > 0) $pedidoIds[$pid] = true;
                    }
                    $pedidoIds = array_keys($pedidoIds);

                    if (!empty($pedidoIds)) {
                        $colsP = [];
                        try {
                            $stCols = $pdo->query('DESCRIBE pedidos');
                            $colsP = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsP = [];
                        }

                        $pick = function(array $cols, array $cands): string {
                            foreach ($cands as $c) {
                                if (in_array($c, $cols, true)) return $c;
                            }
                            return '';
                        };

                        $colMoeda = $pick($colsP, ['moeda', 'currency']);
                        $colTotal = $pick($colsP, ['total', 'valor_total', 'amount', 'valor']);
                        $colImpostos = $pick($colsP, ['impostos', 'valor_impostos', 'taxes']);
                        $colOrigem = $pick($colsP, ['origem_pedido', 'origem', 'tipo']);

                        $sel = ['id'];
                        if ($colMoeda !== '') $sel[] = $colMoeda . ' AS moeda';
                        if ($colTotal !== '') $sel[] = $colTotal . ' AS total';
                        if ($colImpostos !== '') $sel[] = $colImpostos . ' AS impostos';
                        if ($colOrigem !== '') $sel[] = $colOrigem . ' AS origem_pedido';

                        $in = implode(',', array_fill(0, count($pedidoIds), '?'));
                        $stP = $pdo->prepare('SELECT ' . implode(', ', $sel) . ' FROM pedidos WHERE id IN (' . $in . ')');
                        $stP->execute($pedidoIds);
                        $pRows = $stP->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                        // descobrir tabela de itens
                        $itensTable = null;
                        try {
                            $stT->execute(['pedido_itens']);
                            $tem1 = (int) ($stT->fetchColumn() ?: 0) > 0;
                            $stT->execute(['pedido_items']);
                            $tem2 = (int) ($stT->fetchColumn() ?: 0) > 0;
                            if ($tem1 && !$tem2) $itensTable = 'pedido_itens';
                            elseif ($tem2 && !$tem1) $itensTable = 'pedido_items';
                            elseif ($tem1 && $tem2) $itensTable = 'pedido_itens';
                        } catch (\Exception $e) {
                            $itensTable = null;
                        }

                        $colsItens = [];
                        if ($itensTable) {
                            try {
                                $stColsI = $pdo->query('DESCRIBE ' . $itensTable);
                                $colsItens = $stColsI ? ($stColsI->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                            } catch (\Exception $e) {
                                $colsItens = [];
                            }
                        }

                        $colPedidoId = $pick($colsItens, ['pedido_id']);
                        $colQtd = $pick($colsItens, ['quantidade', 'qty']);
                        $colSubtotal = $pick($colsItens, ['subtotal']);
                        $colUnit = $pick($colsItens, ['preco_unitario', 'valor_unitario', 'price', 'valor']);
                        $colProdutoId = $pick($colsItens, ['produto_id']);

                        $colsProd = [];
                        try {
                            $stColsPr = $pdo->query('DESCRIBE produtos');
                            $colsProd = $stColsPr ? ($stColsPr->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsProd = [];
                        }
                        $colCustoProd = $pick($colsProd, ['preco_custo', 'cost_price', 'custo', 'valor_custo']);

                        foreach ($pRows as $pr) {
                            $pid = (int) ($pr['id'] ?? 0);
                            if ($pid <= 0) continue;
                            $moeda = strtoupper(trim((string) ($pr['moeda'] ?? 'BRL')));
                            if ($moeda === '') $moeda = 'BRL';
                            $total = (float) ($pr['total'] ?? 0);
                            $impostos = (float) ($pr['impostos'] ?? 0);
                            $origem = strtolower(trim((string) ($pr['origem_pedido'] ?? '')));

                            $custo = 0.0;
                            $isAssessoria = in_array($origem, ['assessoria', 'redirecionamento'], true);
                            try {
                                if ($itensTable && $colPedidoId !== '' && $colQtd !== '') {
                                    if ($isAssessoria) {
                                        if ($colSubtotal !== '') {
                                            $stC = $pdo->prepare('SELECT SUM(COALESCE(' . $colSubtotal . ',0)) FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' = ?');
                                            $stC->execute([$pid]);
                                            $custo = (float) ($stC->fetchColumn() ?: 0);
                                        } elseif ($colUnit !== '') {
                                            $stC = $pdo->prepare('SELECT SUM(COALESCE(' . $colUnit . ',0) * COALESCE(' . $colQtd . ',0)) FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' = ?');
                                            $stC->execute([$pid]);
                                            $custo = (float) ($stC->fetchColumn() ?: 0);
                                        }
                                    } else {
                                        if ($colProdutoId !== '' && $colCustoProd !== '') {
                                            $stC = $pdo->prepare('SELECT SUM(COALESCE(pr.' . $colCustoProd . ',0) * COALESCE(pi.' . $colQtd . ',0)) FROM ' . $itensTable . ' pi INNER JOIN produtos pr ON pr.id = pi.' . $colProdutoId . ' WHERE pi.' . $colPedidoId . ' = ?');
                                            $stC->execute([$pid]);
                                            $custo = (float) ($stC->fetchColumn() ?: 0);
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                $custo = 0.0;
                            }

                            $liq = $total - $impostos - $custo;
                            $pedidoInfoMap[$pid] = [
                                'moeda' => $moeda,
                                'total' => $total,
                                'impostos' => $impostos,
                                'custo' => $custo,
                                'liquido' => $liq,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $pedidoInfoMap = [];
                }

                foreach ($rows as $r) {
                    $m = strtoupper(trim((string) ($r['moeda'] ?? 'BRL')));
                    if ($m === '') $m = 'BRL';
                    if (!isset($resumoProc['por_moeda'][$m])) {
                        $resumoProc['por_moeda'][$m] = ['base_liquida' => 0.0, 'valor_comissao' => 0.0, 'percentual_medio' => 0.0, 'linhas' => []];
                    }

                    $pid = (int) ($r['pedido_id'] ?? 0);
                    if ($pid > 0 && isset($pedidoInfoMap[$pid]) && is_array($pedidoInfoMap[$pid])) {
                        $r['valor_pago'] = (float) ($pedidoInfoMap[$pid]['total'] ?? 0);
                        $r['impostos'] = (float) ($pedidoInfoMap[$pid]['impostos'] ?? 0);
                        $r['custo'] = (float) ($pedidoInfoMap[$pid]['custo'] ?? 0);
                        $r['liquido_calc'] = (float) ($pedidoInfoMap[$pid]['liquido'] ?? 0);
                        // preferir moeda do pedido
                        $r['moeda'] = (string) ($pedidoInfoMap[$pid]['moeda'] ?? $m);
                        $m = strtoupper(trim((string) ($r['moeda'] ?? $m)));
                        if ($m === '') $m = 'BRL';
                        if (!isset($resumoProc['por_moeda'][$m])) {
                            $resumoProc['por_moeda'][$m] = ['base_liquida' => 0.0, 'valor_comissao' => 0.0, 'percentual_medio' => 0.0, 'linhas' => []];
                        }
                    }

                    $resumoProc['por_moeda'][$m]['base_liquida'] += (float) ($r['base_liquida'] ?? 0);
                    $resumoProc['por_moeda'][$m]['valor_comissao'] += (float) ($r['valor_comissao'] ?? 0);
                    $resumoProc['por_moeda'][$m]['linhas'][] = $r;
                }

                foreach ($resumoProc['por_moeda'] as $m => &$t) {
                    $sumPerc = 0.0;
                    $n = 0;
                    foreach (($t['linhas'] ?? []) as $r) {
                        $sumPerc += (float) ($r['percentual'] ?? 0);
                        $n++;
                    }
                    $t['percentual_medio'] = $n > 0 ? ($sumPerc / $n) : 0.0;
                }
                unset($t);
            }
        } catch (\Exception $e) {
            $resumoProc = $resumoProc;
        }

        $cPedidos = is_array($resumo) && isset($resumo['pedidos']) && is_array($resumo['pedidos']) ? $resumo['pedidos'] : [];
        $porMoeda = (is_array($resumo) && isset($resumo['por_moeda']) && is_array($resumo['por_moeda'])) ? $resumo['por_moeda'] : [];
        if (empty($porMoeda)) {
            $porMoeda = [
                'BRL' => [
                    'total_faturado' => (float) ($resumo['total_faturado'] ?? 0),
                    'total_custo_produtos' => (float) ($resumo['total_custo_produtos'] ?? 0),
                    'total_liquido' => (float) ($resumo['total_liquido'] ?? 0),
                    'percentual_comissao' => (float) ($resumo['percentual_comissao'] ?? 0),
                    'valor_comissao' => (float) ($resumo['valor_comissao'] ?? 0),
                    'pedidos' => $cPedidos,
                ],
            ];
        }

        foreach (['USD', 'BRL'] as $mBase) {
            if (!isset($porMoeda[$mBase]) || !is_array($porMoeda[$mBase])) {
                $porMoeda[$mBase] = [
                    'total_faturado' => 0.0,
                    'total_custo_produtos' => 0.0,
                    'total_liquido' => 0.0,
                    'percentual_comissao' => 0.0,
                    'valor_comissao' => 0.0,
                    'pedidos' => [],
                ];
            }
        }

        $formatMoney = function (float $v, string $moeda): string {
            $moeda = strtoupper(trim($moeda));
            if ($moeda === 'USD') {
                return '$ ' . number_format($v, 2, '.', ',');
            }
            return 'R$ ' . number_format($v, 2, ',', '.');
        };

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Comissões - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '<style>
        .comm-cards{display:flex;flex-wrap:nowrap;gap:12px;overflow-x:auto;padding-bottom:6px;align-items:stretch}
        .comm-card{flex:0 0 240px;min-height:92px;background:#fff}
        </style></head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('pedidos-comissoes');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Minhas Comissões</h1>
                    <div>
                        ' . ($perfil === 'admin'
                            ? (
                                $escopo === 'todos'
                                    ? '<a href="/admin/pedidos/comissoes" class="btn btn-outline-dark me-2"><i class="fas fa-user"></i> Ver minhas</a>'
                                    : '<a href="/admin/pedidos/comissoes?escopo=todos" class="btn btn-outline-dark me-2"><i class="fas fa-users"></i> Ver todos</a>'
                            )
                            : '') . '
                        <a href="/admin/pedidos" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i> Voltar</a>
                        <a href="/admin/pedidos/novo-manual" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Pedido Manual</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">';

        foreach ($porMoeda as $moeda => $t) {
            $moeda = strtoupper(trim((string) $moeda));
            if ($moeda === '') $moeda = 'BRL';
            $totalFaturado = (float) ($t['total_faturado'] ?? 0);
            $totalCusto = (float) ($t['total_custo_produtos'] ?? 0);
            $totalLiquido = (float) ($t['total_liquido'] ?? 0);
            $percent = (float) ($t['percentual_comissao'] ?? 0);
            $valorComissao = (float) ($t['valor_comissao'] ?? 0);

            $tp = $resumoProc['por_moeda'][$moeda] ?? ['base_liquida' => 0.0, 'valor_comissao' => 0.0, 'percentual_medio' => 0.0, 'linhas' => []];
            $procBase = (float) ($tp['base_liquida'] ?? 0);
            $procVal = (float) ($tp['valor_comissao'] ?? 0);
            $procPercMed = (float) ($tp['percentual_medio'] ?? 0);

            echo '<div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Moeda: ' . htmlspecialchars($moeda) . '</h5>
                    </div>
                    <div class="comm-cards">
                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Total Faturado (Manuais)</div>
                            <div class="fs-5 fw-bold">' . $formatMoney($totalFaturado, $moeda) . '</div>
                        </div>
                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Custo dos Produtos</div>
                            <div class="fs-5 fw-bold">' . $formatMoney($totalCusto, $moeda) . '</div>
                        </div>
                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Total Líquido</div>
                            <div class="fs-5 fw-bold">' . $formatMoney($totalLiquido, $moeda) . '</div>
                        </div>
                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Comissão</div>
                            <div class="fs-5 fw-bold">' . number_format($percent, 2, ',', '.') . '% (' . $formatMoney($valorComissao, $moeda) . ')</div>
                        </div>
                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Comissão total</div>
                            <div class="fs-5 fw-bold">' . $formatMoney($valorComissao, $moeda) . '</div>
                        </div>

                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Processamento (Online) - Base líquida</div>
                            <div class="fs-5 fw-bold">' . $formatMoney($procBase, $moeda) . '</div>
                        </div>
                        <div class="border rounded p-3 comm-card">
                            <div class="text-muted small">Processamento (Online) - Comissão</div>
                            <div class="fs-5 fw-bold">' . $formatMoney($procVal, $moeda) . '</div>
                            <div class="small text-muted">% médio: ' . number_format($procPercMed, 2, ',', '.') . '%</div>
                        </div>
                    </div>
                </div>';
        }

        $pedidosUsd = [];
        $pedidosBrl = [];
        foreach ($cPedidos as $p) {
            $m = strtoupper(trim((string) ($p['moeda'] ?? '')));
            if ($m === '') $m = 'BRL';
            if ($m === 'USD') $pedidosUsd[] = $p;
            else $pedidosBrl[] = $p;
        }

        echo '</div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Pedidos Manuais Pagos</strong></div>
                    <div class="card-body">';

        $renderTabelaPedidos = function(array $pedidos, string $moedaLabel, float $percentMoeda) use ($formatMoney) {
            if (empty($pedidos)) {
                echo '<div class="text-muted">Sem pedidos manuais pagos em ' . htmlspecialchars($moedaLabel) . '.</div>';
                return;
            }

            echo '<div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Data</th>
                                <th class="text-end">Faturado</th>
                                <th class="text-end">Impostos</th>
                                <th class="text-end">Custo</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Comissão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach ($pedidos as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $codigo = (string) ($p['codigo'] ?? $pid);
                $fat = (float) ($p['faturado'] ?? 0);
                $imp = (float) ($p['impostos'] ?? 0);
                $cus = (float) ($p['custo'] ?? 0);
                $liq = (float) ($p['liquido'] ?? ($fat - $imp - $cus));
                $com = max(0.0, $liq) * (max(0.0, $percentMoeda) / 100.0);
                $moeda = strtoupper(trim((string) ($p['moeda'] ?? '')));
                if ($moeda === '') $moeda = 'BRL';
                $dt = (string) ($p['created_at'] ?? '');
                $dtFmt = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '-';

                echo '<tr>
                        <td><strong>' . htmlspecialchars($codigo) . '</strong><div class="text-muted small">#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</div></td>
                        <td>' . htmlspecialchars($dtFmt) . '</td>
                        <td class="text-end fw-semibold">' . $formatMoney($fat, $moeda) . '</td>
                        <td class="text-end">' . $formatMoney($imp, $moeda) . '</td>
                        <td class="text-end">' . $formatMoney($cus, $moeda) . '</td>
                        <td class="text-end">' . $formatMoney($liq, $moeda) . '</td>
                        <td class="text-end fw-semibold">' . number_format($percentMoeda, 2, ',', '.') . '% (' . $formatMoney($com, $moeda) . ')</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="/admin/pedidos/detalhes/' . $pid . '"><i class="fas fa-eye"></i></a></td>
                      </tr>';
            }

            echo '        </tbody>
                    </table>
                </div>';
        };

        echo '<div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>USD</strong>
                </div>';
        $renderTabelaPedidos($pedidosUsd, 'USD', (float) ($porMoeda['USD']['percentual_comissao'] ?? 0));
        echo '</div>';

        echo '<div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>BRL</strong>
                </div>';
        $renderTabelaPedidos($pedidosBrl, 'BRL', (float) ($porMoeda['BRL']['percentual_comissao'] ?? 0));
        echo '</div>';

        echo '        </div>
                </div>
                <div class="card">
                    <div class="card-header"><strong>Comissões de Processamento (Online)</strong></div>
                    <div class="card-body">';

        $renderTabelaProc = function(array $linhas, string $moedaLabel) use ($formatMoney) {
            if (empty($linhas)) {
                echo '<div class="text-muted">Sem comissões de processamento em ' . htmlspecialchars($moedaLabel) . '.</div>';
                return;
            }
            echo '<div class="table-responsive">'
                . '<table class="table table-hover">'
                . '<thead><tr><th>Pedido</th><th>Data</th><th class="text-end">Pago</th><th class="text-end">Impostos</th><th class="text-end">Custo</th><th class="text-end">Líquido</th><th class="text-end">%</th><th class="text-end">Comissão</th><th>Ações</th></tr></thead><tbody>';
            foreach ($linhas as $r) {
                $pid = (int) ($r['pedido_id'] ?? 0);
                $dt = (string) ($r['created_at'] ?? '');
                $dtFmt = $dt !== '' ? date('d/m/Y H:i', strtotime($dt)) : '-';
                $m = strtoupper(trim((string) ($r['moeda'] ?? 'BRL')));
                if ($m === '') $m = 'BRL';
                $pago = (float) ($r['valor_pago'] ?? 0);
                $imp = (float) ($r['impostos'] ?? 0);
                $cus = (float) ($r['custo'] ?? 0);
                $liq = (float) ($r['liquido_calc'] ?? (($r['base_liquida'] ?? 0)));
                echo '<tr>'
                    . '<td><strong>#' . $pid . '</strong></td>'
                    . '<td>' . htmlspecialchars($dtFmt) . '</td>'
                    . '<td class="text-end">' . $formatMoney($pago, $m) . '</td>'
                    . '<td class="text-end">' . $formatMoney($imp, $m) . '</td>'
                    . '<td class="text-end">' . $formatMoney($cus, $m) . '</td>'
                    . '<td class="text-end">' . $formatMoney($liq, $m) . '</td>'
                    . '<td class="text-end">' . number_format((float) ($r['percentual'] ?? 0), 2, ',', '.') . '%</td>'
                    . '<td class="text-end fw-semibold">' . $formatMoney((float) ($r['valor_comissao'] ?? 0), $m) . '</td>'
                    . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/pedidos/detalhes/' . $pid . '"><i class="fas fa-eye"></i></a></td>'
                    . '</tr>';
            }
            echo '</tbody></table></div>';
        };

        $procUsd = (array) (($resumoProc['por_moeda']['USD']['linhas'] ?? []) ?: []);
        $procBrl = (array) (($resumoProc['por_moeda']['BRL']['linhas'] ?? []) ?: []);

        echo '<div class="mb-4"><strong>USD</strong>';
        $renderTabelaProc($procUsd, 'USD');
        echo '</div>';

        echo '<div><strong>BRL</strong>';
        $renderTabelaProc($procBrl, 'BRL');
        echo '</div>';

        echo '        </div>
                </div>
            </main>
        </div>
    </div>';

        echo <<<'HTML'

    <div class="modal fade" id="modalLixeiraPedido" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="formLixeiraPedido">
                    <div class="modal-header">
                        <h5 class="modal-title">Enviar pedido para lixeira</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div>Confirma enviar o pedido <strong id="lixeiraPedidoIdLabel"></strong> para a lixeira?</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Enviar para lixeira</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            var modal = document.getElementById("modalLixeiraPedido");
            if(!modal) return;
            modal.addEventListener("show.bs.modal", function (event) {
                var btn = event.relatedTarget;
                if(!btn) return;
                var pid = btn.getAttribute("data-pedido-id") || "";
                var label = document.getElementById("lixeiraPedidoIdLabel");
                var form = document.getElementById("formLixeiraPedido");
                if(label) label.textContent = "#" + pid;
                if(form) form.action = "/admin/pedidos/excluir/" + pid;
            });
        })();
    </script>
</body>
</html>
HTML;
        exit;
    }
    
    public function atualizarStatus(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $id = $request->getParam('id');
        $novoStatus = $request->getParam('status');
        $estornar = (int) $request->getParam('estornar', 0) === 1;
        
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $cols = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE pedidos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $statusCol = 'status';
            if (is_array($cols) && !in_array('status', $cols, true)) {
                foreach (['status_pedido', 'pedido_status'] as $cand) {
                    if (in_array($cand, $cols, true)) {
                        $statusCol = $cand;
                        break;
                    }
                }
            }

            // Se a coluna de status for ENUM, validar se o valor existe; caso contr2rio o MySQL pode gravar '' (string vazia)
            $enumAllowed = null;
            try {
                $stmtType = $pdo->prepare("SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = ? LIMIT 1");
                $stmtType->execute([$statusCol]);
                $colInfo = $stmtType->fetch(\PDO::FETCH_ASSOC);
                if (is_array($colInfo) && isset($colInfo['DATA_TYPE']) && strtolower((string) $colInfo['DATA_TYPE']) === 'enum') {
                    $colType = (string) ($colInfo['COLUMN_TYPE'] ?? '');
                    // COLUMN_TYPE vem como enum('a','b',...)
                    if (preg_match("/^enum\((.*)\)$/i", $colType, $m)) {
                        $raw = $m[1];
                        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $raw, $mm);
                        $vals = [];
                        if (!empty($mm[1])) {
                            foreach ($mm[1] as $v) {
                                $vals[] = stripcslashes($v);
                            }
                        }
                        $enumAllowed = $vals;
                    }
                }
            } catch (\Exception $e) {
                $enumAllowed = null;
            }

            if (is_array($enumAllowed) && !empty($enumAllowed) && !in_array((string) $novoStatus, $enumAllowed, true)) {
                echo '<div class="alert alert-danger">Status inválido para a coluna <strong>' . htmlspecialchars($statusCol) . '</strong>: <strong>' . htmlspecialchars((string) $novoStatus) . '</strong>. Esta coluna é ENUM e o MySQL pode converter valores inválidos para <strong>string vazia</strong>, parecendo que "processou" mas não persiste.</div>';
                echo '<div class="alert alert-secondary"><strong>Valores permitidos</strong><br>' . htmlspecialchars(implode(', ', $enumAllowed)) . '</div>';
                echo '<div class="alert alert-warning">Para permitir novos status (ex: produto_consolidado), crie uma migration SQL para atualizar o ENUM (ou trocar para VARCHAR) no banco.</div>';
                echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $set = [$statusCol . ' = ?'];
            $params = [$novoStatus];

            $statusAnterior = null;
            try {
                $stPrev = $pdo->prepare('SELECT ' . $statusCol . ' FROM pedidos WHERE id = ? LIMIT 1');
                $stPrev->execute([(int) $id]);
                $tmpPrev = $stPrev->fetchColumn();
                if ($tmpPrev !== false && $tmpPrev !== null) {
                    $statusAnterior = (string) $tmpPrev;
                }
            } catch (\Exception $e) {
                $statusAnterior = null;
            }

            $novoStatusKey = strtolower(trim((string) $novoStatus));
            $cicloFechado = in_array($novoStatusKey, [
                'produto_consolidado',
                'em_transporte',
                'aguardando_liberacao_aduaneira',
                'enviado_ao_destinatario',
                'enviado',
                'entregue',
            ], true);
            if ($cicloFechado) {
                try {
                    $stM = $pdo->prepare('SELECT peso_total, altura, largura, comprimento FROM pedidos WHERE id = ? LIMIT 1');
                    $stM->execute([(int) $id]);
                    $m = $stM->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $peso = isset($m['peso_total']) ? (float) $m['peso_total'] : 0.0;
                    $altura = isset($m['altura']) ? (int) $m['altura'] : 0;
                    $largura = isset($m['largura']) ? (int) $m['largura'] : 0;
                    $comprimento = isset($m['comprimento']) ? (int) $m['comprimento'] : 0;
                    if ($peso <= 0 || $altura <= 0 || $largura <= 0 || $comprimento <= 0) {
                        echo '<div class="alert alert-danger">Para marcar como <strong>Caixa Fechada</strong> (ou status seguintes), preencha no pedido: <strong>Peso real (kg)</strong>, <strong>Altura</strong>, <strong>Largura</strong> e <strong>Comprimento</strong>.</div>';
                        echo '<a href="/admin/pedidos/editar/' . (int) $id . '" class="btn btn-primary me-2">Editar pedido</a>';
                        echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                        exit;
                    }
                } catch (\Exception $e) {
                    echo '<div class="alert alert-danger">Não foi possível validar medidas/peso do pedido. Edite o pedido e tente novamente.</div>';
                    echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                    exit;
                }
            }

            // Se marcou como pago/aprovado, manter colunas relacionadas consistentes.
            // Além disso, atualizar o texto exibido no bloco "Pagamento" para bater com o status selecionado.
            $paidValues = ['pago','paid','approved','aprovado','concluido','concluído','confirmed','received','succeeded','success'];
            $isPaid = in_array(strtolower(trim((string) $novoStatus)), $paidValues, true);

            $statusLabelMap = array_merge(self::getStatusList(), [
                'enviado' => 'Etiqueta Gerada', // alias legado
            ]);
            $pagamentoStatusTexto = $statusLabelMap[$novoStatusKey] ?? ucfirst(str_replace('_', ' ', $novoStatusKey));

            if ($isPaid && is_array($cols)) {
                // 1) pago_em
                if (in_array('pago_em', $cols, true)) {
                    $set[] = 'pago_em = COALESCE(pago_em, NOW())';
                }

                // 2) payment_status / status_pagamento
                if (in_array('payment_status', $cols, true) && $statusCol !== 'payment_status') {
                    $set[] = 'payment_status = ?';
                    $params[] = 'approved';
                }
                if (in_array('status_pagamento', $cols, true) && $statusCol !== 'status_pagamento') {
                    $set[] = 'status_pagamento = ?';
                    $params[] = 'aprovado';
                }

                // 2b) colunas usadas na tela de detalhes do admin
                if (in_array('pagamento_status', $cols, true) && $statusCol !== 'pagamento_status') {
                    $set[] = 'pagamento_status = ?';
                    $params[] = $pagamentoStatusTexto;
                }
                if (in_array('pagamento_data', $cols, true)) {
                    $set[] = 'pagamento_data = COALESCE(pagamento_data, NOW())';
                }

                // 3) status (caso a coluna atualizada tenha sido payment_status/status_pagamento)
                if (in_array('status', $cols, true) && $statusCol !== 'status') {
                    $set[] = 'status = ?';
                    $params[] = 'pago';
                }
            }

            if (!$isPaid && is_array($cols) && in_array('pagamento_status', $cols, true) && $statusCol !== 'pagamento_status') {
                $set[] = 'pagamento_status = ?';
                $params[] = $pagamentoStatusTexto;
            }

            if (is_array($cols) && in_array('updated_at', $cols, true)) {
                $set[] = 'updated_at = NOW()';
            }

            $params[] = $id;
            $stmt = $pdo->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($params);

            if ($isPaid) {
                try {
                    $stT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");

                    $stT->execute(['pagamentos']);
                    $temPagamentos = ((int) ($stT->fetchColumn() ?: 0)) > 0;
                    if ($temPagamentos) {
                        $colsPg = [];
                        try {
                            $stCols = $pdo->query('DESCRIBE pagamentos');
                            $colsPg = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsPg = [];
                        }

                        if (is_array($colsPg) && !empty($colsPg)) {
                            $pedidoCol = in_array('pedido_id', $colsPg, true) ? 'pedido_id' : (in_array('order_id', $colsPg, true) ? 'order_id' : '');
                            if ($pedidoCol !== '') {
                                $statusPgCol = '';
                                foreach (['status_pagamento', 'payment_status', 'status'] as $cand) {
                                    if (in_array($cand, $colsPg, true)) {
                                        $statusPgCol = $cand;
                                        break;
                                    }
                                }

                                $dataPgCol = '';
                                foreach (['data_pagamento', 'paid_at', 'pago_em', 'paid_date'] as $cand) {
                                    if (in_array($cand, $colsPg, true)) {
                                        $dataPgCol = $cand;
                                        break;
                                    }
                                }

                                if ($statusPgCol !== '') {
                                    $setPg = [$statusPgCol . " = 'aprovado'"];
                                    if ($dataPgCol !== '') {
                                        $setPg[] = $dataPgCol . ' = COALESCE(' . $dataPgCol . ', NOW())';
                                    }
                                    $stUpPg = $pdo->prepare('UPDATE pagamentos SET ' . implode(', ', $setPg) . ' WHERE ' . $pedidoCol . ' = ?');
                                    $stUpPg->execute([(int) $id]);
                                }
                            }
                        }
                    }

                    $stT->execute(['pedido_pagamentos']);
                    $temPedidoPagamentos = ((int) ($stT->fetchColumn() ?: 0)) > 0;
                    if ($temPedidoPagamentos) {
                        $colsPP = [];
                        try {
                            $stCols = $pdo->query('DESCRIBE pedido_pagamentos');
                            $colsPP = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Exception $e) {
                            $colsPP = [];
                        }

                        if (is_array($colsPP) && !empty($colsPP)) {
                            $pedidoCol = in_array('pedido_id', $colsPP, true) ? 'pedido_id' : (in_array('order_id', $colsPP, true) ? 'order_id' : '');
                            if ($pedidoCol !== '') {
                                $statusPPCol = '';
                                foreach (['status', 'status_pagamento', 'payment_status'] as $cand) {
                                    if (in_array($cand, $colsPP, true)) {
                                        $statusPPCol = $cand;
                                        break;
                                    }
                                }

                                $paidAtCol = '';
                                foreach (['pago_em', 'paid_at', 'data_pagamento', 'paid_date'] as $cand) {
                                    if (in_array($cand, $colsPP, true)) {
                                        $paidAtCol = $cand;
                                        break;
                                    }
                                }

                                if ($statusPPCol !== '') {
                                    $setPP = [$statusPPCol . " = 'approved'"];
                                    if ($paidAtCol !== '') {
                                        $setPP[] = $paidAtCol . ' = COALESCE(' . $paidAtCol . ', NOW())';
                                    }
                                    $stUpPP = $pdo->prepare('UPDATE pedido_pagamentos SET ' . implode(', ', $setPP) . ' WHERE ' . $pedidoCol . ' = ?');
                                    $stUpPP->execute([(int) $id]);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            // Persistir histórico de status para exibição ao usuário (se a tabela existir)
            try {
                $stT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $stT->execute(['pedido_status_history']);
                $temHist = ((int) ($stT->fetchColumn() ?: 0) > 0);
                if ($temHist) {
                    $stC = $pdo->query('DESCRIBE pedido_status_history');
                    $colsH = $stC ? ($stC->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    if (is_array($colsH)) {
                        $colUser = in_array('alterado_por', $colsH, true) ? 'alterado_por' : (in_array('usuario_id', $colsH, true) ? 'usuario_id' : 'alterado_por');
                        $hasStatusAnterior = in_array('status_anterior', $colsH, true);
                        $hasStatusNovo = in_array('status_novo', $colsH, true);
                        $hasObs = in_array('observacao', $colsH, true);
                        $hasCreatedAt = in_array('created_at', $colsH, true);
                        if ($hasStatusNovo) {
                            $uid = null;
                            try {
                                if (session_status() === PHP_SESSION_NONE) {
                                    session_start();
                                }
                                $uSess = (int) ($_SESSION['usuario_id'] ?? 0);
                                if ($uSess > 0) {
                                    $uid = $uSess;
                                }
                            } catch (\Exception $e) {
                                $uid = null;
                            }

                            $fields = ['pedido_id'];
                            $vals = ['?'];
                            $bind = [(int) $id];

                            if ($hasStatusAnterior) {
                                $fields[] = 'status_anterior';
                                $vals[] = '?';
                                $bind[] = $statusAnterior;
                            }
                            $fields[] = 'status_novo';
                            $vals[] = '?';
                            $bind[] = (string) $novoStatus;

                            if ($hasObs) {
                                $fields[] = 'observacao';
                                $vals[] = '?';
                                $bind[] = (string) ($observacao ?? '');
                            }

                            if (!empty($colUser)) {
                                $fields[] = $colUser;
                                $vals[] = '?';
                                $bind[] = $uid;
                            }

                            if ($hasCreatedAt) {
                                $fields[] = 'created_at';
                                $vals[] = 'NOW()';
                            }

                            $sqlH = 'INSERT INTO pedido_status_history (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $vals) . ')';
                            $stH = $pdo->prepare($sqlH);
                            $stH->execute($bind);
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            if ((string) $novoStatus === 'cancelado' && $estornar) {
                try {
                    $stmtP = $pdo->prepare('SELECT payment_gateway, payment_id, payment_status, pagamento_gateway, pagamento_transacao, pagamento_status FROM pedidos WHERE id = ? LIMIT 1');
                    $stmtP->execute([(int) $id]);
                    $pedido = $stmtP->fetch(\PDO::FETCH_ASSOC);

                    $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? ($pedido['pagamento_gateway'] ?? ''))));
                    $pstatus = strtolower(trim((string) ($pedido['payment_status'] ?? ($pedido['pagamento_status'] ?? ''))));
                    $isPaid = in_array($pstatus, ['approved', 'aprovado', 'paid', 'pago', 'succeeded', 'success'], true);
                    $hasPayment = trim((string) ($pedido['payment_id'] ?? ($pedido['pagamento_transacao'] ?? ''))) !== '';

                    if ($hasPayment && $gateway !== '') {
                        $paySvc = new PaymentService();
                        if ($isPaid) {
                            if ($gateway === 'stripe') {
                                $paySvc->estornarPagamentoStripePorPedido((int) $id, 'Cancelamento do pedido no sistema');
                            } elseif ($gateway === 'appmax') {
                                $paySvc->estornarPagamentoAppmaxPorPedido((int) $id, null);
                            } elseif ($gateway === 'carteira') {
                                $paySvc->estornarPagamentoCarteiraPorPedido((int) $id, null, 'Cancelamento do pedido no sistema');
                            }
                        } else {
                            if ($gateway === 'stripe') {
                                $paySvc->cancelarPagamentoStripePorPedido((int) $id);
                            } elseif ($gateway === 'appmax') {
                                $paySvc->cancelarPagamentoAppmaxPorPedido((int) $id);
                            } elseif ($gateway === 'carteira') {
                                $paySvc->cancelarPagamentoCarteiraPorPedido((int) $id);
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            if ($stmt->rowCount() <= 0) {
                echo '<div class="alert alert-warning">Nenhuma linha foi atualizada. Verifique se o pedido existe e se a coluna de status está correta (coluna usada: <strong>' . htmlspecialchars($statusCol) . '</strong>).</div>';
                echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            $statusColsToCheck = [];
            foreach (['status', 'status_pedido', 'pedido_status'] as $cand) {
                if (is_array($cols) && in_array($cand, $cols, true)) {
                    $statusColsToCheck[] = $cand;
                }
            }
            if (empty($statusColsToCheck)) {
                $statusColsToCheck[] = $statusCol;
            }

            $selectCols = array_values(array_unique($statusColsToCheck));
            $stmtCheck = $pdo->prepare('SELECT ' . implode(', ', $selectCols) . ' FROM pedidos WHERE id = ? LIMIT 1');
            $stmtCheck->execute([$id]);
            $row = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $persistiu = false;
            if (is_array($row)) {
                foreach ($selectCols as $c) {
                    if (isset($row[$c]) && (string) $row[$c] === (string) $novoStatus) {
                        $persistiu = true;
                        break;
                    }
                }
            }

            if (!$persistiu) {
                echo '<div class="alert alert-danger">O status foi enviado como <strong>' . htmlspecialchars((string) $novoStatus) . '</strong>, mas n\u00e3o permaneceu gravado no banco ap\u00f3s o UPDATE.</div>';
                echo '<div class="alert alert-secondary"><strong>Diagn\u00f3stico</strong><br>Coluna atualizada: <strong>' . htmlspecialchars($statusCol) . '</strong><br>';
                if (is_array($row)) {
                    foreach ($selectCols as $c) {
                        echo htmlspecialchars($c) . ': <strong>' . htmlspecialchars((string) ($row[$c] ?? 'NULL')) . '</strong><br>';
                    }
                } else {
                    echo 'N\u00e3o foi poss\u00edvel reler o registro ap\u00f3s o UPDATE.';
                }
                echo '</div>';
                echo '<a href="/admin/pedidos/detalhes/' . (int) $id . '" class="btn btn-secondary">Voltar</a>';
                exit;
            }

            // Ao finalizar o ciclo do pedido (ex.: entregue), dar baixa física no estoque pelo que estava reservado.
            // Sem isso, o reservado some e o disponível sobe, sem reduzir o estoque total.
            $cicloFechado = in_array(strtolower(trim((string) $novoStatus)), [
                'produto_consolidado',
                'em_transporte',
                'aguardando_liberacao_aduaneira',
                'enviado_ao_destinatario',
                'enviado',
                'entregue',
            ], true);

            if ($cicloFechado) {
                $temReservas = false;
                $temEstoqueInterno = false;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_reservas']);
                    $temReservas = ((int) $stmtT->fetchColumn() > 0);
                    $stmtT->execute(['estoque_interno']);
                    $temEstoqueInterno = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temReservas = false;
                    $temEstoqueInterno = false;
                }

                if ($temReservas && $temEstoqueInterno) {
                    $temStatusReserva = false;
                    try {
                        $st = $pdo->query('DESCRIBE estoque_reservas');
                        $colsRes = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        $temStatusReserva = is_array($colsRes) && in_array('status', $colsRes, true);
                        $temPedidoIdReserva = is_array($colsRes) && in_array('pedido_id', $colsRes, true);
                        $temProdutoIdReserva = is_array($colsRes) && in_array('produto_id', $colsRes, true);
                        $temQtdReserva = is_array($colsRes) && in_array('quantidade_reservada', $colsRes, true);
                    } catch (\Exception $e) {
                        $temStatusReserva = false;
                        $temPedidoIdReserva = false;
                        $temProdutoIdReserva = false;
                        $temQtdReserva = false;
                    }

                    if (!empty($temPedidoIdReserva) && !empty($temProdutoIdReserva) && !empty($temQtdReserva)) {
                        // Somar reservas por produto e consumir FIFO do estoque_interno
                        try {
                            $sql = 'SELECT produto_id, COALESCE(SUM(COALESCE(quantidade_reservada,0)),0) as qtd FROM estoque_reservas WHERE pedido_id = ?';
                            $params = [(int) $id];
                            if ($temStatusReserva) {
                                $sql .= " AND (status IS NULL OR status = '' OR status <> 'finalizada')";
                            }
                            $sql .= ' GROUP BY produto_id';
                            $stRes = $pdo->prepare($sql);
                            $stRes->execute($params);
                            $resRows = $stRes->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        } catch (\Exception $e) {
                            $resRows = [];
                        }

                        foreach (($resRows ?? []) as $rr) {
                            $produtoId = (int) ($rr['produto_id'] ?? 0);
                            $qtdReservada = (int) ($rr['qtd'] ?? 0);
                            if ($produtoId <= 0 || $qtdReservada <= 0) {
                                continue;
                            }

                            $restante = $qtdReservada;
                            try {
                                $stmtLocs = $pdo->prepare(
                                    'SELECT id, quantidade FROM estoque_interno WHERE produto_id = ? AND quantidade > 0 '
                                    . 'ORDER BY CASE WHEN data_compra IS NULL THEN 1 ELSE 0 END ASC, data_compra ASC, id ASC'
                                );
                                $stmtLocs->execute([$produtoId]);
                                $locs = $stmtLocs->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                            } catch (\Exception $e) {
                                $locs = [];
                            }

                            foreach ($locs as $loc) {
                                if ($restante <= 0) break;
                                $locId = (int) ($loc['id'] ?? 0);
                                $qAtual = (int) ($loc['quantidade'] ?? 0);
                                if ($locId <= 0 || $qAtual <= 0) continue;
                                $consumir = ($qAtual <= $restante) ? $qAtual : $restante;
                                $novoQ = $qAtual - $consumir;
                                try {
                                    $stmtUpd = $pdo->prepare('UPDATE estoque_interno SET quantidade = ? WHERE id = ? LIMIT 1');
                                    $stmtUpd->execute([$novoQ, $locId]);
                                } catch (\Exception $e) {
                                }
                                $restante -= $consumir;
                            }
                        }

                        // Finalizar reservas do pedido (para não “voltar” a contar depois)
                        if ($temStatusReserva) {
                            try {
                                $stFin = $pdo->prepare("UPDATE estoque_reservas SET status = 'finalizada' WHERE pedido_id = ? AND (status IS NULL OR status = '' OR status <> 'finalizada')");
                                $stFin->execute([(int) $id]);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }
            }

            if ((string) $novoStatus === 'produto_consolidado') {
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_reservas']);
                    $temReservas = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temReservas = false;
                }

                // Determinar tabela de itens do pedido (pedido_itens vs pedido_items)
                $itensTable = null;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['pedido_itens']);
                    $temPedidoItens = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temPedidoItens = false;
                }
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['pedido_items']);
                    $temPedidoItems = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temPedidoItems = false;
                }
                if ($temPedidoItens && !$temPedidoItems) {
                    $itensTable = 'pedido_itens';
                } elseif ($temPedidoItems && !$temPedidoItens) {
                    $itensTable = 'pedido_items';
                } elseif ($temPedidoItens && $temPedidoItems) {
                    // escolher a tabela com mais itens para este pedido
                    $c1 = 0;
                    $c2 = 0;
                    try {
                        $st = $pdo->prepare('SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = ?');
                        $st->execute([(int) $id]);
                        $c1 = (int) ($st->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $c1 = 0;
                    }
                    try {
                        $st = $pdo->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = ?');
                        $st->execute([(int) $id]);
                        $c2 = (int) ($st->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $c2 = 0;
                    }
                    $itensTable = ($c2 > $c1) ? 'pedido_items' : 'pedido_itens';
                }

                // Recalcular faltantes: pedido - reservado (e manter pendancias)
                $itens = [];
                if (!empty($itensTable)) {
                    try {
                        $st = $pdo->prepare('SELECT produto_id, quantidade FROM ' . $itensTable . ' WHERE pedido_id = ?');
                        $st->execute([(int) $id]);
                        $itens = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Exception $e) {
                        $itens = [];
                    }
                }

                $temLista = false;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['lista_compras']);
                    $temLista = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temLista = false;
                }

                $colsLista = [];
                if ($temLista) {
                    try {
                        $st = $pdo->query('DESCRIBE lista_compras');
                        $colsLista = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
                    } catch (\Exception $e) {
                        $colsLista = [];
                    }
                }
                $temPedidoIdLista = $temLista && is_array($colsLista) && in_array('pedido_id', $colsLista, true);
                $temProdutoIdLista = $temLista && is_array($colsLista) && in_array('produto_id', $colsLista, true);

                // limpar pendancias antigas deste pedido para regravar somente o que faltar
                if ($temPedidoIdLista) {
                    try {
                        $stmtDel = $pdo->prepare('DELETE FROM lista_compras WHERE pedido_id = ?');
                        $stmtDel->execute([(int) $id]);
                    } catch (\Exception $e) {
                    }
                }

                // preparar leitura de reservas do pedido (quantidade efetivamente reservada)
                $temPedidoIdReserva = false;
                $temProdutoIdReserva = false;
                $temQtdReserva = false;
                $temStatusReserva = false;
                if (!empty($temReservas)) {
                    try {
                        $st = $pdo->query('DESCRIBE estoque_reservas');
                        $colsRes = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
                        $temPedidoIdReserva = is_array($colsRes) && in_array('pedido_id', $colsRes, true);
                        $temProdutoIdReserva = is_array($colsRes) && in_array('produto_id', $colsRes, true);
                        $temQtdReserva = is_array($colsRes) && in_array('quantidade_reservada', $colsRes, true);
                        $temStatusReserva = is_array($colsRes) && in_array('status', $colsRes, true);
                    } catch (\Exception $e) {
                        $temPedidoIdReserva = false;
                        $temProdutoIdReserva = false;
                        $temQtdReserva = false;
                        $temStatusReserva = false;
                    }
                }

                // Verificar suporte ao estoque_interno (para dar baixa do que foi realmente reservado)
                $temEstoqueInterno = false;
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_interno']);
                    $temEstoqueInterno = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temEstoqueInterno = false;
                }

                // Para cada item, pendenciar somente o faltante (qtd_pedido - qtd_reservada) e dar baixa no estoque pelo reservado
                if ($temPedidoIdLista && $temProdutoIdLista && is_array($itens)) {
                    // Verificar se a tabela de itens tem coluna ja_comprado
                    $temJaComprado = false;
                    try {
                        $itensTableCheck = $this->tableExistsPdo($pdo, 'pedido_itens') ? 'pedido_itens' : ($this->tableExistsPdo($pdo, 'pedido_items') ? 'pedido_items' : '');
                        if ($itensTableCheck !== '') {
                            $colsItCheck = $this->getTableColumnsPdo($pdo, $itensTableCheck);
                            $temJaComprado = is_array($colsItCheck) && in_array('ja_comprado', $colsItCheck, true);
                        }
                    } catch (\Exception $e) { $temJaComprado = false; }

                    // Buscar itens marcados como já comprados
                    $itensJaComprados = [];
                    if ($temJaComprado && $itensTableCheck !== '') {
                        try {
                            $stJc = $pdo->prepare('SELECT produto_id FROM ' . $itensTableCheck . ' WHERE pedido_id = ? AND ja_comprado = 1');
                            $stJc->execute([(int) $id]);
                            $rowsJc = $stJc->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                            $itensJaComprados = array_map('intval', $rowsJc);
                        } catch (\Exception $e) { $itensJaComprados = []; }
                    }

                    foreach ($itens as $it) {
                        $produtoId = (int) ($it['produto_id'] ?? 0);
                        $qtdPedido = (int) ($it['quantidade'] ?? 0);
                        if ($produtoId <= 0 || $qtdPedido <= 0) continue;

                        // Pular itens marcados como já comprados
                        if (in_array($produtoId, $itensJaComprados, true)) continue;

                        $qtdReservada = 0;
                        if ($temPedidoIdReserva && $temProdutoIdReserva && $temQtdReserva) {
                            try {
                                $sql = 'SELECT COALESCE(SUM(quantidade_reservada),0) FROM estoque_reservas WHERE pedido_id = ? AND produto_id = ?';
                                $params = [(int) $id, $produtoId];
                                if ($temStatusReserva) {
                                    $sql .= " AND status = 'ativa'";
                                }
                                $st = $pdo->prepare($sql);
                                $st->execute($params);
                                $qtdReservada = (int) ($st->fetchColumn() ?: 0);
                            } catch (\Exception $e) {
                                $qtdReservada = 0;
                            }
                        }

                        $faltante = $qtdPedido - $qtdReservada;

                        // Baixa fsica do estoque: consome apenas o que estava reservado (o que de fato existia)
                        if ($temEstoqueInterno && $qtdReservada > 0) {
                            $restante = $qtdReservada;
                            try {
                                $stmtLocs = $pdo->prepare(
                                    'SELECT id, quantidade FROM estoque_interno WHERE produto_id = ? AND quantidade > 0 ORDER BY CASE WHEN data_compra IS NULL THEN 1 ELSE 0 END ASC, data_compra ASC, id ASC'
                                );
                                $stmtLocs->execute([$produtoId]);
                                $locs = $stmtLocs->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                                foreach ($locs as $loc) {
                                    if ($restante <= 0) break;
                                    $locId = (int) ($loc['id'] ?? 0);
                                    $qAtual = (int) ($loc['quantidade'] ?? 0);
                                    if ($locId <= 0 || $qAtual <= 0) continue;
                                    $consumir = ($qAtual <= $restante) ? $qAtual : $restante;
                                    $novoQ = $qAtual - $consumir;
                                    $stmtUpd = $pdo->prepare('UPDATE estoque_interno SET quantidade = ? WHERE id = ? LIMIT 1');
                                    $stmtUpd->execute([$novoQ, $locId]);
                                    $restante -= $consumir;
                                }
                            } catch (\Exception $e) {
                            }
                        }

                        if ($faltante <= 0) {
                            continue;
                        }

                        // inserir pendancia na lista_compras com o que faltar
                        try {
                            $cols = ['produto_id', 'pedido_id'];
                            $vals = [':produto_id', ':pedido_id'];
                            $params = [':produto_id' => $produtoId, ':pedido_id' => (int) $id];

                            if (in_array('quantidade_faltante', $colsLista, true)) {
                                $cols[] = 'quantidade_faltante';
                                $vals[] = ':q';
                                $params[':q'] = $faltante;
                            } elseif (in_array('quantidade_necessaria', $colsLista, true)) {
                                $cols[] = 'quantidade_necessaria';
                                $vals[] = ':q';
                                $params[':q'] = $faltante;
                            }

                            if (in_array('status', $colsLista, true)) {
                                $cols[] = 'status';
                                $vals[] = "'pendente'";
                            }

                            $sql = 'INSERT INTO lista_compras (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
                            $st = $pdo->prepare($sql);
                            $st->execute($params);
                        } catch (\Exception $e) {
                        }
                    }
                }

                if (!empty($temReservas)) {
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'estoque_reservas' AND column_name = 'pedido_id'");
                        $stmtC->execute();
                        $temPedidoId = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temPedidoId = false;
                    }

                    $temStatus = false;
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'estoque_reservas' AND column_name = 'status'");
                        $stmtC->execute();
                        $temStatus = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temStatus = false;
                    }

                    if (!empty($temPedidoId) && $temStatus) {
                        try {
                            $stmtFin = $pdo->prepare("UPDATE estoque_reservas SET status = 'finalizada' WHERE pedido_id = ? AND status = 'ativa'");
                            $stmtFin->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    } elseif (!empty($temPedidoId)) {
                        // Fallback legado sem coluna status
                        try {
                            $stmtDel = $pdo->prepare('DELETE FROM estoque_reservas WHERE pedido_id = ?');
                            $stmtDel->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    }
                }
            }

            if ((string) $novoStatus === 'cancelado') {
                // Cancelamento: liberar reservas e remover pendncias do pedido
                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['estoque_reservas']);
                    $temReservas = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temReservas = false;
                }
                if (!empty($temReservas)) {
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'estoque_reservas' AND column_name = 'pedido_id'");
                        $stmtC->execute();
                        $temPedidoId = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temPedidoId = false;
                    }
                    if (!empty($temPedidoId)) {
                        try {
                            $stmtDel = $pdo->prepare('DELETE FROM estoque_reservas WHERE pedido_id = ?');
                            $stmtDel->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    }
                }

                try {
                    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                    $stmtT->execute(['lista_compras']);
                    $temLista = ((int) $stmtT->fetchColumn() > 0);
                } catch (\Exception $e) {
                    $temLista = false;
                }
                if (!empty($temLista)) {
                    try {
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'lista_compras' AND column_name = 'pedido_id'");
                        $stmtC->execute();
                        $temPedidoIdLista = ((int) $stmtC->fetchColumn() > 0);
                    } catch (\Exception $e) {
                        $temPedidoIdLista = false;
                    }
                    if (!empty($temPedidoIdLista)) {
                        try {
                            $stmtDel = $pdo->prepare('DELETE FROM lista_compras WHERE pedido_id = ?');
                            $stmtDel->execute([(int) $id]);
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
            
            header('Location: /admin/pedidos/detalhes/' . $id . '?success=1');
            exit;
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao atualizar status: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos/detalhes/' . $id . '" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }

    public function excluir(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $admin = $auth->getUsuarioLogado();
        $id = $id ?? $request->getParam('id');

        if (empty($id)) {
            echo '<div class="alert alert-danger">Pedido inválido</div>';
            echo '<a href="/admin/pedidos" class="btn btn-secondary">Voltar</a>';
            exit;
        }

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            $colsPedidos = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE pedidos');
                $colsPedidos = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsPedidos = [];
            }

            $temDeletedAt = is_array($colsPedidos) && in_array('deleted_at', $colsPedidos, true);
            $temDeletedBy = is_array($colsPedidos) && in_array('deleted_by', $colsPedidos, true);

            if ($temDeletedAt) {
                // Devolver estoque dos itens do pedido
                try {
                    $itensTable = '';
                    if ($this->tableExistsPdo($pdo, 'pedido_itens')) {
                        $itensTable = 'pedido_itens';
                    } elseif ($this->tableExistsPdo($pdo, 'pedido_items')) {
                        $itensTable = 'pedido_items';
                    }
                    if ($itensTable !== '') {
                        $colsItens = $this->getTableColumnsPdo($pdo, $itensTable);
                        $colPedidoId = $this->pickColumn($colsItens, ['pedido_id']);
                        $colProdutoId = $this->pickColumn($colsItens, ['produto_id', 'product_id']);
                        $colQtd = $this->pickColumn($colsItens, ['quantidade', 'qty']);

                        if ($colPedidoId && $colProdutoId && $colQtd) {
                            $stItens = $pdo->prepare('SELECT ' . $colProdutoId . ' AS produto_id, ' . $colQtd . ' AS quantidade FROM ' . $itensTable . ' WHERE ' . $colPedidoId . ' = ?');
                            $stItens->execute([(int) $id]);
                            $itens = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                            $colsProd = $this->getTableColumnsPdo($pdo, 'produtos');
                            $colEstoque = $this->pickColumn($colsProd, ['stock', 'estoque']);

                            if ($colEstoque && !empty($itens)) {
                                foreach ($itens as $item) {
                                    $pid = (int) ($item['produto_id'] ?? 0);
                                    $qtd = (int) ($item['quantidade'] ?? 0);
                                    if ($pid > 0 && $qtd > 0) {
                                        $stUpd = $pdo->prepare('UPDATE produtos SET ' . $colEstoque . ' = ' . $colEstoque . ' + ? WHERE id = ?');
                                        $stUpd->execute([$qtd, $pid]);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Não bloquear a exclusão se falhar a devolução de estoque
                    error_log('[EXCLUIR_PEDIDO] Erro ao devolver estoque: ' . $e->getMessage());
                }

                $set = ['deleted_at = NOW()'];
                $params = [':id' => (int) $id];
                if ($temDeletedBy) {
                    $set[] = 'deleted_by = :uid';
                    $params[':uid'] = (int) ($admin['id'] ?? 0);
                }
                $st = $pdo->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                $st->execute($params);
                $pdo->commit();
                header('Location: /admin/pedidos?success=lixeira');
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ?");
            $stmt->execute([$id]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }

            $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE pedido_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();
            header('Location: /admin/pedidos?success=excluido');
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo '<div class="alert alert-danger">Erro ao excluir pedido: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/pedidos/detalhes/' . htmlspecialchars((string)$id) . '" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }

    public function criarTicket(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte']);
        $admin = $auth->getUsuarioLogado();
        $id = (int) ($id ?? $request->getParam('id'));
        if ($id <= 0) {
            header('Location: /admin/pedidos');
            exit;
        }

        $adminUid = (int) ($admin['id'] ?? 0);
        $pdo = \Config\Database::getConnection();

        // Cliente do pedido
        $colsP = [];
        try {
            $stCols = $pdo->query('DESCRIBE pedidos');
            $colsP = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsP = [];
        }
        $colUsuario = null;
        foreach (['usuario_id', 'user_id', 'cliente_id'] as $c) {
            if (in_array($c, $colsP, true)) {
                $colUsuario = $c;
                break;
            }
        }
        if ($colUsuario === null) {
            header('Location: /admin/pedidos/detalhes/' . $id . '?ticket_error=1');
            exit;
        }

        $stOwner = $pdo->prepare('SELECT ' . $colUsuario . ' FROM pedidos WHERE id = ? LIMIT 1');
        $stOwner->execute([(int) $id]);
        $clienteId = (int) ($stOwner->fetchColumn() ?: 0);
        if ($clienteId <= 0) {
            header('Location: /admin/pedidos/detalhes/' . $id . '?ticket_error=1');
            exit;
        }

        // Reutilizar ticket aberto
        try {
            $stFind = $pdo->prepare("SELECT id FROM support_tickets WHERE usuario_id = ? AND pedido_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
            $stFind->execute([(int) $clienteId, (int) $id]);
            $existingOpen = (int) ($stFind->fetchColumn() ?: 0);
            if ($existingOpen > 0) {
                header('Location: /admin/tickets/' . $existingOpen);
                exit;
            }
        } catch (\Exception $e) {
        }

        $assunto = 'Suporte do Pedido #' . (int) $id;
        $motivo = 'Problema no pedido';
        $mensagem = trim((string) ($request->getParam('mensagem') ?? 'Ticket iniciado pelo suporte.'));
        if ($mensagem === '') {
            $mensagem = 'Ticket iniciado pelo suporte.';
        }

        $pdo->beginTransaction();
        try {
            $colsT = [];
            try {
                $stT = $pdo->query('DESCRIBE support_tickets');
                $colsT = $stT ? ($stT->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $colsT = [];
            }

            if (in_array('motivo', $colsT, true)) {
                $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, motivo, status) VALUES (?, ?, ?, ?, 'open')");
                $stIns->execute([(int) $clienteId, (int) $id, (string) $assunto, (string) $motivo]);
            } else {
                $stIns = $pdo->prepare("INSERT INTO support_tickets (usuario_id, pedido_id, assunto, status) VALUES (?, ?, ?, 'open')");
                $stIns->execute([(int) $clienteId, (int) $id, (string) $assunto]);
            }
            $ticketId = (int) $pdo->lastInsertId();

            $stMsg = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, ?, ?, ?)');
            $stMsg->execute([(int) $ticketId, 'admin', (int) $adminUid, (string) $mensagem]);

            $pdo->commit();

            try {
                $not = new SupportTicketNotificationService();
                $not->notificarTicketCriado((int) $id, (int) $ticketId, [
                    'assunto' => $assunto,
                    'motivo' => $motivo,
                ]);
            } catch (\Exception $e) {
            }

            header('Location: /admin/tickets/' . $ticketId);
            exit;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header('Location: /admin/pedidos/detalhes/' . $id . '?ticket_error=1');
            exit;
        }
    }

    public function sincronizarPagamentos(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $id = $id ?? $request->getParam('id');
        $pedidoId = (int) $id;

        if ($pedidoId <= 0) {
            header('Location: /admin/pedidos?error=pedido_invalido');
            exit;
        }

        try {
            $paymentService = new PaymentService();

            $r1 = $paymentService->atualizarStatusPagamentoCambioRealSplitPorPedido($pedidoId);
            $r2 = $paymentService->atualizarStatusPagamentoAppmaxSplitPorPedido($pedidoId);

            // Sincronizar Stripe: se o pedido tem payment_id com pi_, consultar status no Stripe
            $r3 = ['success' => true];
            try {
                $pdo = \Config\Database::getConnection();
                $st = $pdo->prepare('SELECT payment_id, payment_gateway FROM pedidos WHERE id = ? LIMIT 1');
                $st->execute([$pedidoId]);
                $pedRow = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                $piId = trim((string) ($pedRow['payment_id'] ?? ''));
                $gw = strtolower(trim((string) ($pedRow['payment_gateway'] ?? '')));

                if ($piId !== '' && ($gw === 'stripe' || str_starts_with($piId, 'pi_'))) {
                    $pi = $paymentService->retrieveStripePaymentIntent($piId);
                    if (is_array($pi) && !empty($pi['status'])) {
                        $stripeStatus = strtolower(trim((string) $pi['status']));
                        if ($stripeStatus === 'succeeded') {
                            $paymentService->atualizarPagamentoPedidoPorPedidoId($pedidoId, 'stripe', 'approved', 'SUCCEEDED');
                        } elseif (in_array($stripeStatus, ['canceled', 'requires_payment_method'], true)) {
                            $paymentService->atualizarPagamentoPedidoPorPedidoId($pedidoId, 'stripe', 'rejected', strtoupper($stripeStatus));
                        }
                    }
                }
            } catch (\Exception $e) {
                $r3 = ['success' => true]; // não falhar por causa do Stripe
            }

            $ok = (!empty($r1['success']) && !empty($r2['success']));
            if ($ok) {
                header('Location: /admin/pedidos/detalhes/' . $pedidoId . '?sync_ok=1');
                exit;
            }

            $err = (string) (($r1['error'] ?? '') !== '' ? ($r1['error'] ?? '') : ($r2['error'] ?? 'Falha ao sincronizar pagamentos'));
            header('Location: /admin/pedidos/detalhes/' . $pedidoId . '?sync_err=' . rawurlencode($err));
            exit;
        } catch (\Exception $e) {
            header('Location: /admin/pedidos/detalhes/' . $pedidoId . '?sync_err=' . rawurlencode($e->getMessage()));
            exit;
        }
    }

    public function debugPagamento(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $id = $id ?? $request->getParam('id');
        $pedidoId = (int) $id;
        if ($pedidoId <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        try {
            $pdo = \Config\Database::getConnection();
            $result = ['pedido_id' => $pedidoId];

            // Dados do pedido (campos de pagamento)
            $stPed = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
            $stPed->execute([$pedidoId]);
            $pedido = $stPed->fetch(\PDO::FETCH_ASSOC) ?: [];
            $payFields = [];
            foreach ($pedido as $k => $v) {
                if (stripos($k, 'pagamento') !== false || stripos($k, 'payment') !== false
                    || stripos($k, 'gateway') !== false || stripos($k, 'transacao') !== false
                    || stripos($k, 'transaction') !== false || stripos($k, 'forma_pagamento') !== false
                    || stripos($k, 'moeda') !== false || stripos($k, 'currency') !== false
                    || stripos($k, 'total') !== false || stripos($k, 'subtotal') !== false
                    || stripos($k, 'status') !== false || stripos($k, 'pix') !== false
                    || stripos($k, 'invoice') !== false || stripos($k, 'split') !== false
                ) {
                    $payFields[$k] = $v;
                }
            }
            $result['pedido_payment_fields'] = $payFields;

            // Tabela pedido_pagamentos
            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pedido_pagamentos'");
                $st->execute();
                if ((int) $st->fetchColumn() > 0) {
                    $stPag = $pdo->prepare('SELECT * FROM pedido_pagamentos WHERE pedido_id = ? ORDER BY id ASC');
                    $stPag->execute([$pedidoId]);
                    $result['pedido_pagamentos'] = $stPag->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } else {
                    $result['pedido_pagamentos'] = 'tabela não existe';
                }
            } catch (\Exception $e) {
                $result['pedido_pagamentos_error'] = $e->getMessage();
            }

            // Tabela pagamentos (legada)
            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pagamentos'");
                $st->execute();
                if ((int) $st->fetchColumn() > 0) {
                    $stPag = $pdo->prepare('SELECT * FROM pagamentos WHERE pedido_id = ? ORDER BY id ASC');
                    $stPag->execute([$pedidoId]);
                    $result['pagamentos'] = $stPag->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } else {
                    $result['pagamentos'] = 'tabela não existe';
                }
            } catch (\Exception $e) {
                $result['pagamentos_error'] = $e->getMessage();
            }

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function gerarNovoPixSplit(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        $id = $id ?? $request->getParam('id');
        $pedidoId = (int) $id;
        if ($pedidoId <= 0) {
            $this->json(['success' => false, 'error' => 'Pedido inválido'], 400);
            return;
        }

        $componente = strtolower(trim((string) $request->getParam('componente')));
        $gateway = strtolower(trim((string) $request->getParam('gateway')));
        $emailOverride = trim((string) $request->getParam('email'));

        if ($componente === '' || $gateway === '') {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }
        if (!in_array($gateway, ['appmax', 'cambioreal'], true)) {
            $this->json(['success' => false, 'error' => 'Gateway não suportado'], 400);
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $st = $db->prepare('SELECT componente, gateway, metodo, moeda, valor FROM pedido_pagamentos WHERE pedido_id = :p AND LOWER(componente) = :c AND LOWER(gateway) = :g ORDER BY id DESC LIMIT 1');
            $st->execute([':p' => $pedidoId, ':c' => $componente, ':g' => $gateway]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (empty($row)) {
                $this->json(['success' => false, 'error' => 'Split não encontrado para este componente/gateway'], 404);
                return;
            }
            $valor = (float) ($row['valor'] ?? 0);
            $moeda = strtoupper(trim((string) ($row['moeda'] ?? 'BRL')));
            if ($valor <= 0) {
                $this->json(['success' => false, 'error' => 'Valor inválido'], 400);
                return;
            }
            if ($moeda !== 'BRL') {
                $this->json(['success' => false, 'error' => 'Somente BRL suportado'], 400);
                return;
            }

            $pedidoModel = new PedidoEcommerce();
            $pedido = $pedidoModel->getComDetalhes($pedidoId);
            if (!$pedido) {
                $this->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
                return;
            }

            $paymentService = new PaymentService();

            $clienteNome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? 'Cliente'));
            $clienteEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ''));
            if ($emailOverride !== '') {
                if (!filter_var($emailOverride, FILTER_VALIDATE_EMAIL)) {
                    $this->json(['success' => false, 'error' => 'E-mail inválido'], 400);
                    return;
                }
                $clienteEmail = $emailOverride;
            }
            $clienteDoc = (string) ($pedido['cliente_cpf_cnpj'] ?? ($pedido['documento'] ?? ($pedido['cpf'] ?? '')));
            $clienteNasc = (string) ($pedido['cliente_data_nascimento'] ?? ($pedido['data_nascimento'] ?? ''));
            $clienteTel = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ($pedido['celular'] ?? '')));

            $codigoPedido = (string) ($pedido['codigo_pedido'] ?? $pedidoId);
            $desc = 'Pedido #' . $codigoPedido . ' (' . $componente . ')';

            // ---- Câmbio Real: gerar link de checkout ----
            if ($gateway === 'cambioreal') {
                // Fallback: completar dados do usuário se necessário
                $uid = (int) ($pedido['usuario_id'] ?? 0);
                $uRow = [];
                $eRow = [];
                try {
                    if ($uid > 0) {
                        try {
                            $colsU = $db->query('DESCRIBE usuarios');
                            $uCols = $colsU ? ($colsU->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                            if (!empty($uCols)) {
                                $pick = function(array $cands) use ($uCols) {
                                    foreach ($cands as $c) { if (in_array($c, $uCols, true)) return $c; }
                                    return null;
                                };
                                $sel = ['id'];
                                $colNome = $pick(['nome', 'name', 'full_name']); if ($colNome) $sel[] = $colNome . ' AS nome';
                                $colEmail = $pick(['email']); if ($colEmail) $sel[] = $colEmail . ' AS email';
                                $colTel = $pick(['telefone', 'celular', 'phone', 'mobile', 'whatsapp']); if ($colTel) $sel[] = $colTel . ' AS telefone';
                                $colDoc = $pick(['cpf_cnpj', 'documento', 'cpf', 'cnpj', 'document']); if ($colDoc) $sel[] = $colDoc . ' AS documento';
                                $colNasc = $pick(['data_nascimento', 'nascimento', 'birth_date', 'dob']); if ($colNasc) $sel[] = $colNasc . ' AS nascimento';
                                $stU = $db->prepare('SELECT ' . implode(', ', $sel) . ' FROM usuarios WHERE id = ? LIMIT 1');
                                $stU->execute([$uid]);
                                $uRow = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];
                            }
                        } catch (\Exception $e) { $uRow = []; }
                        try {
                            $colsE = $db->query('DESCRIBE enderecos');
                            $eCols = $colsE ? ($colsE->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                            if (!empty($eCols) && in_array('usuario_id', $eCols, true)) {
                                $orderBy = in_array('principal', $eCols, true) ? 'principal DESC, id DESC' : 'id DESC';
                                $stE = $db->prepare('SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY ' . $orderBy . ' LIMIT 1');
                                $stE->execute([$uid]);
                                $eRow = $stE->fetch(\PDO::FETCH_ASSOC) ?: [];
                            }
                        } catch (\Exception $e) { $eRow = []; }
                    }
                } catch (\Exception $e) { $uRow = []; $eRow = []; }

                if (trim($clienteNome) === '' && !empty($uRow['nome'])) $clienteNome = (string) $uRow['nome'];
                if (trim($clienteEmail) === '' && !empty($uRow['email'])) $clienteEmail = (string) $uRow['email'];
                if (trim($clienteDoc) === '' && !empty($uRow['documento'])) $clienteDoc = (string) $uRow['documento'];
                if (trim($clienteTel) === '' && !empty($uRow['telefone'])) $clienteTel = (string) $uRow['telefone'];
                if (trim($clienteNasc) === '' && !empty($uRow['nascimento'])) $clienteNasc = (string) $uRow['nascimento'];

                $docDigits = preg_replace('/\D+/', '', (string) $clienteDoc);
                $telDigits = preg_replace('/\D+/', '', (string) $clienteTel);
                $birth = trim((string) $clienteNasc);
                if ($birth !== '' && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birth, $m)) {
                    $birth = $m[3] . '-' . $m[2] . '-' . $m[1];
                }

                $base = \App\Core\Url::base();
                $successUrl = rtrim($base, '/') . '/checkout/conclusao/' . $pedidoId . '?cambioreal=success';
                $errorUrl = rtrim($base, '/') . '/checkout/conclusao/' . $pedidoId . '?cambioreal=error';

                $client = [
                    'name' => $clienteNome,
                    'email' => $clienteEmail,
                    'document' => $docDigits,
                    'birth_date' => $birth,
                    'phone' => $telDigits,
                    'cpf' => $docDigits,
                    'phone_number' => $telDigits,
                    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                    'address' => [
                        'state' => (string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? ($eRow['estado'] ?? ''))),
                        'city' => (string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ($eRow['cidade'] ?? ''))),
                        'zip_code' => (string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? ($eRow['cep'] ?? ''))),
                        'district' => (string) ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ($eRow['bairro'] ?? ''))),
                        'street' => (string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ($eRow['endereco'] ?? ($eRow['logradouro'] ?? '')))),
                        'number' => (string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ($eRow['numero'] ?? ''))),
                    ],
                ];

                $cr = $paymentService->createCambioRealCheckoutRequestProduto($pedidoId, $valor, $desc, $client, $successUrl, $errorUrl);
                if (empty($cr['success'])) {
                    $this->json(['success' => false, 'error' => (string) ($cr['error'] ?? 'Falha ao gerar link Câmbio Real')], 400);
                    return;
                }

                $invoiceUrl = (string) ($cr['invoice_url'] ?? '');
                $paymentId = (string) ($cr['payment_id'] ?? '');

                $this->json([
                    'success' => true,
                    'gateway' => 'cambioreal',
                    'pedido_id' => $pedidoId,
                    'componente' => $componente,
                    'payment_id' => $paymentId,
                    'payment_link' => $invoiceUrl,
                ]);
                return;
            }

            // ---- AppMax: gerar link de pagamento interno (/pagar/{token}) ----
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $adminId = (int) ($_SESSION['usuario_id'] ?? 0);

            $linkSvc = new \App\Services\PaymentLinkService();
            $linkResult = $linkSvc->createLink([
                'currency' => 'BRL',
                'produto_valor' => 0,
                'taxa_servico_valor' => (string) round($valor, 2),
                'impostos_valor' => 0,
                'descricao' => $desc,
                'products' => [
                    ['name' => $desc, 'value' => round($valor, 2)],
                ],
            ], $adminId);

            if (empty($linkResult['success'])) {
                $this->json(['success' => false, 'error' => (string) ($linkResult['error'] ?? 'Falha ao criar link de pagamento AppMax')], 500);
                return;
            }

            $token = (string) ($linkResult['token'] ?? '');
            $linkId = (int) ($linkResult['id'] ?? 0);
            $publicPath = (string) ($linkResult['public_url'] ?? ('/pagar/' . $token));
            $base = \App\Core\Url::base();
            $publicUrl = rtrim($base, '/') . $publicPath;

            $paymentService->registrarPedidoPagamentoSplit([
                'pedido_id' => $pedidoId,
                'componente' => $componente,
                'gateway' => 'appmax',
                'metodo' => 'payment_link',
                'moeda' => 'BRL',
                'valor' => $valor,
                'payment_id' => 'PAYLINK_' . $linkId,
                'status' => 'pending',
                'invoice_url' => $publicUrl,
            ]);

            $this->json([
                'success' => true,
                'gateway' => 'appmax',
                'pedido_id' => $pedidoId,
                'componente' => $componente,
                'payment_id' => 'PAYLINK_' . $linkId,
                'payment_link' => $publicUrl,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
