<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminMigracaoController extends Controller
{
    private function getPdo(): \PDO
    {
        return Database::getConnection();
    }
    /**
     * Página principal de migração de produtos
     */
    public function index(Request $request)
    {
        (new AuthService())->requerPerfis(['admin']);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        ob_start();
        include __DIR__ . '/../Views/admin/migracao/index.php';
        $content = ob_get_clean();

        $title = 'Migração de Produtos - Braziliana Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    /**
     * Exportar produtos para JSON (download)
     */
    public function exportar(Request $request)
    {
        (new AuthService())->requerPerfis(['admin']);

        $pdo = $this->getPdo();

        // 1) Produtos
        $produtos = $pdo->query("SELECT * FROM produtos ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);

        // 2) Fotos dos produtos
        $fotos = $pdo->query("SELECT * FROM produto_fotos ORDER BY produto_id, ordem")->fetchAll(\PDO::FETCH_ASSOC);

        // 3) Variação tipos
        $varTipos = [];
        try {
            $varTipos = $pdo->query("SELECT * FROM variacao_tipos ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // 4) Variação opções
        $varOpcoes = [];
        try {
            $varOpcoes = $pdo->query("SELECT * FROM variacao_opcoes ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // 5) Produto atributos
        $prodAtributos = [];
        try {
            $prodAtributos = $pdo->query("SELECT * FROM produto_atributos ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // 6) Produto variações
        $prodVariacoes = [];
        try {
            $prodVariacoes = $pdo->query("SELECT * FROM produto_variacoes ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // 7) Produto variação itens
        $prodVarItens = [];
        try {
            $prodVarItens = $pdo->query("SELECT * FROM produto_variacao_itens ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // 8) Produto variação fotos
        $prodVarFotos = [];
        try {
            $prodVarFotos = $pdo->query("SELECT * FROM produto_variacao_fotos ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // 9) Categorias (para referência)
        $categorias = [];
        try {
            $categorias = $pdo->query("SELECT * FROM categorias ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        $export = [
            'versao'                  => '1.0',
            'exportado_em'            => date('Y-m-d H:i:s'),
            'categorias'              => $categorias,
            'produtos'                => $produtos,
            'produto_fotos'           => $fotos,
            'variacao_tipos'          => $varTipos,
            'variacao_opcoes'         => $varOpcoes,
            'produto_atributos'       => $prodAtributos,
            'produto_variacoes'       => $prodVariacoes,
            'produto_variacao_itens'  => $prodVarItens,
            'produto_variacao_fotos'  => $prodVarFotos,
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="produtos_export_' . date('Y-m-d_His') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    /**
     * Exportar imagens dos produtos como ZIP
     */
    public function exportarImagens(Request $request)
    {
        (new AuthService())->requerPerfis(['admin']);

        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            echo json_encode(['error' => 'Extensão ZIP não disponível no servidor']);
            exit;
        }

        $pdo = $this->getPdo();
        $fotos = $pdo->query("SELECT nome_arquivo FROM produto_fotos WHERE nome_arquivo IS NOT NULL AND nome_arquivo != ''")->fetchAll(\PDO::FETCH_COLUMN);

        // Também pegar foto_principal dos produtos
        $capas = $pdo->query("SELECT foto_principal FROM produtos WHERE foto_principal IS NOT NULL AND foto_principal != ''")->fetchAll(\PDO::FETCH_COLUMN);

        // Fotos de variações
        $varFotos = [];
        try {
            $varFotos = $pdo->query("SELECT nome_arquivo FROM produto_variacao_fotos WHERE nome_arquivo IS NOT NULL AND nome_arquivo != ''")->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {}

        $allPaths = array_unique(array_merge($fotos, $capas, $varFotos));

        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $zipName = 'produtos_imagens_' . date('Y-m-d_His') . '.zip';
        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo json_encode(['error' => 'Não foi possível criar o arquivo ZIP']);
            exit;
        }

        $added = 0;
        foreach ($allPaths as $webPath) {
            $webPath = '/' . ltrim((string) $webPath, '/');
            $candidates = [
                $docRoot . $webPath,
                $docRoot . '/public' . $webPath,
            ];
            foreach ($candidates as $filePath) {
                if (file_exists($filePath)) {
                    // Manter estrutura relativa dentro do zip
                    $zip->addFile($filePath, ltrim($webPath, '/'));
                    $added++;
                    break;
                }
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            http_response_code(404);
            echo json_encode(['error' => 'Nenhuma imagem encontrada para exportar']);
            exit;
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    /**
     * Importar produtos a partir do JSON exportado
     */
    public function importar(Request $request)
    {
        (new AuthService())->requerPerfis(['admin']);

        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum arquivo enviado ou erro no upload']);
            exit;
        }

        $json = file_get_contents($_FILES['arquivo']['tmp_name']);
        $data = json_decode($json, true);

        if (!$data || !isset($data['produtos'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Arquivo JSON inválido ou sem dados de produtos']);
            exit;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $stats = [
                'categorias_importadas' => 0,
                'produtos_importados'   => 0,
                'fotos_importadas'      => 0,
                'variacoes_importadas'  => 0,
                'erros'                 => [],
            ];

            // ── Mapas de ID antigo → novo ──
            $mapCategoria  = [];
            $mapProduto    = [];
            $mapVarTipo    = [];
            $mapVarOpcao   = [];
            $mapProdVar    = [];

            // ── 1) Categorias ──
            if (!empty($data['categorias'])) {
                foreach ($data['categorias'] as $cat) {
                    $oldId = (int) $cat['id'];
                    // Verificar se já existe pelo slug ou nome
                    $nome = $cat['nome'] ?? $cat['name'] ?? '';
                    $slug = $cat['slug'] ?? '';

                    $existing = null;
                    if ($slug !== '') {
                        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE slug = ? LIMIT 1");
                        $stmt->execute([$slug]);
                        $existing = $stmt->fetchColumn();
                    }
                    if (!$existing && $nome !== '') {
                        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nome = ? OR name = ? LIMIT 1");
                        $stmt->execute([$nome, $nome]);
                        $existing = $stmt->fetchColumn();
                    }

                    if ($existing) {
                        $mapCategoria[$oldId] = (int) $existing;
                    } else {
                        // Inserir
                        $cols = array_keys($cat);
                        $cols = array_filter($cols, fn($c) => $c !== 'id');
                        $colsStr = implode(', ', array_map(fn($c) => "`$c`", $cols));
                        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                        $vals = array_map(fn($c) => $cat[$c], $cols);

                        $stmt = $pdo->prepare("INSERT INTO categorias ($colsStr) VALUES ($placeholders)");
                        $stmt->execute($vals);
                        $mapCategoria[$oldId] = (int) $pdo->lastInsertId();
                        $stats['categorias_importadas']++;
                    }
                }
            }

            // ── 2) Variação tipos ──
            if (!empty($data['variacao_tipos'])) {
                foreach ($data['variacao_tipos'] as $vt) {
                    $oldId = (int) $vt['id'];
                    $stmt = $pdo->prepare("SELECT id FROM variacao_tipos WHERE slug = ? LIMIT 1");
                    $stmt->execute([$vt['slug'] ?? '']);
                    $existing = $stmt->fetchColumn();

                    if ($existing) {
                        $mapVarTipo[$oldId] = (int) $existing;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO variacao_tipos (nome, slug, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $vt['nome'] ?? '',
                            $vt['slug'] ?? '',
                            $vt['ativo'] ?? 1,
                            $vt['created_at'] ?? date('Y-m-d H:i:s'),
                            $vt['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                        $mapVarTipo[$oldId] = (int) $pdo->lastInsertId();
                    }
                }
            }

            // ── 3) Variação opções ──
            if (!empty($data['variacao_opcoes'])) {
                foreach ($data['variacao_opcoes'] as $vo) {
                    $oldId = (int) $vo['id'];
                    $newTipoId = $mapVarTipo[(int) ($vo['tipo_id'] ?? 0)] ?? (int) ($vo['tipo_id'] ?? 0);

                    $stmt = $pdo->prepare("SELECT id FROM variacao_opcoes WHERE tipo_id = ? AND slug = ? LIMIT 1");
                    $stmt->execute([$newTipoId, $vo['slug'] ?? '']);
                    $existing = $stmt->fetchColumn();

                    if ($existing) {
                        $mapVarOpcao[$oldId] = (int) $existing;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO variacao_opcoes (tipo_id, valor, slug, ordem, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $newTipoId,
                            $vo['valor'] ?? '',
                            $vo['slug'] ?? '',
                            $vo['ordem'] ?? 0,
                            $vo['ativo'] ?? 1,
                            $vo['created_at'] ?? date('Y-m-d H:i:s'),
                            $vo['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                        $mapVarOpcao[$oldId] = (int) $pdo->lastInsertId();
                    }
                }
            }

            // ── 4) Produtos ──
            foreach ($data['produtos'] as $prod) {
                $oldId = (int) $prod['id'];

                // Verificar se já existe pelo SKU
                $sku = $prod['sku'] ?? '';
                $existing = null;
                if ($sku !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM produtos WHERE sku = ? LIMIT 1");
                    $stmt->execute([$sku]);
                    $existing = $stmt->fetchColumn();
                }

                if ($existing) {
                    $mapProduto[$oldId] = (int) $existing;
                    continue; // Pular produto já existente
                }

                // Remover id e remapear category_id
                $insert = $prod;
                unset($insert['id']);

                if (isset($insert['category_id']) && isset($mapCategoria[(int) $insert['category_id']])) {
                    $insert['category_id'] = $mapCategoria[(int) $insert['category_id']];
                }

                // Garantir slug único
                $slug = $insert['slug'] ?? '';
                if ($slug !== '') {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $insert['slug'] = $slug . '-' . time();
                    }
                }

                // Garantir SKU único
                if (($insert['sku'] ?? '') !== '') {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE sku = ?");
                    $stmt->execute([$insert['sku']]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $insert['sku'] = $insert['sku'] . '-' . time();
                    }
                }

                $cols = array_keys($insert);
                $colsStr = implode(', ', array_map(fn($c) => "`$c`", $cols));
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $vals = array_values($insert);

                try {
                    $stmt = $pdo->prepare("INSERT INTO produtos ($colsStr) VALUES ($placeholders)");
                    $stmt->execute($vals);
                    $mapProduto[$oldId] = (int) $pdo->lastInsertId();
                    $stats['produtos_importados']++;
                } catch (\Exception $e) {
                    $stats['erros'][] = "Produto '{$prod['name']}' (ID orig {$oldId}): " . $e->getMessage();
                }
            }

            // ── 5) Fotos dos produtos ──
            if (!empty($data['produto_fotos'])) {
                foreach ($data['produto_fotos'] as $foto) {
                    $oldProdId = (int) ($foto['produto_id'] ?? 0);
                    $newProdId = $mapProduto[$oldProdId] ?? null;
                    if (!$newProdId) continue;

                    try {
                        $stmt = $pdo->prepare("INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, legenda, ordem, principal, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $newProdId,
                            $foto['nome_arquivo'] ?? '',
                            $foto['arquivo_original'] ?? '',
                            $foto['legenda'] ?? null,
                            $foto['ordem'] ?? 0,
                            $foto['principal'] ?? 0,
                            $foto['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                        $stats['fotos_importadas']++;
                    } catch (\Exception $e) {
                        $stats['erros'][] = "Foto produto (ID orig {$oldProdId}): " . $e->getMessage();
                    }
                }
            }

            // ── 6) Produto atributos ──
            if (!empty($data['produto_atributos'])) {
                foreach ($data['produto_atributos'] as $pa) {
                    $newProdId = $mapProduto[(int) ($pa['produto_id'] ?? 0)] ?? null;
                    $newTipoId = $mapVarTipo[(int) ($pa['tipo_id'] ?? 0)] ?? (int) ($pa['tipo_id'] ?? 0);
                    if (!$newProdId) continue;

                    try {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO produto_atributos (produto_id, tipo_id, created_at, updated_at) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $newProdId,
                            $newTipoId,
                            $pa['created_at'] ?? date('Y-m-d H:i:s'),
                            $pa['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Exception $e) {}
                }
            }

            // ── 7) Produto variações ──
            if (!empty($data['produto_variacoes'])) {
                foreach ($data['produto_variacoes'] as $pv) {
                    $oldVarId = (int) $pv['id'];
                    $newProdId = $mapProduto[(int) ($pv['produto_id'] ?? 0)] ?? null;
                    if (!$newProdId) continue;

                    try {
                        $stmt = $pdo->prepare("INSERT INTO produto_variacoes (produto_id, sku, price_override, stock, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $newProdId,
                            $pv['sku'] ?? null,
                            $pv['price_override'] ?? null,
                            $pv['stock'] ?? 0,
                            $pv['ativo'] ?? 1,
                            $pv['created_at'] ?? date('Y-m-d H:i:s'),
                            $pv['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                        $mapProdVar[$oldVarId] = (int) $pdo->lastInsertId();
                        $stats['variacoes_importadas']++;
                    } catch (\Exception $e) {
                        $stats['erros'][] = "Variação (ID orig {$oldVarId}): " . $e->getMessage();
                    }
                }
            }

            // ── 8) Produto variação itens ──
            if (!empty($data['produto_variacao_itens'])) {
                foreach ($data['produto_variacao_itens'] as $pvi) {
                    $newVarId  = $mapProdVar[(int) ($pvi['produto_variacao_id'] ?? 0)] ?? null;
                    $newTipoId = $mapVarTipo[(int) ($pvi['tipo_id'] ?? 0)] ?? (int) ($pvi['tipo_id'] ?? 0);
                    $newOpcId  = $mapVarOpcao[(int) ($pvi['opcao_id'] ?? 0)] ?? (int) ($pvi['opcao_id'] ?? 0);
                    if (!$newVarId) continue;

                    try {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO produto_variacao_itens (produto_variacao_id, tipo_id, opcao_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $newVarId,
                            $newTipoId,
                            $newOpcId,
                            $pvi['created_at'] ?? date('Y-m-d H:i:s'),
                            $pvi['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Exception $e) {}
                }
            }

            // ── 9) Produto variação fotos ──
            if (!empty($data['produto_variacao_fotos'])) {
                foreach ($data['produto_variacao_fotos'] as $pvf) {
                    $newVarId = $mapProdVar[(int) ($pvf['produto_variacao_id'] ?? 0)] ?? null;
                    if (!$newVarId) continue;

                    try {
                        $stmt = $pdo->prepare("INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, principal, ordem, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $newVarId,
                            $pvf['nome_arquivo'] ?? '',
                            $pvf['arquivo_original'] ?? null,
                            $pvf['principal'] ?? 0,
                            $pvf['ordem'] ?? 0,
                            $pvf['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Exception $e) {}
                }
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'stats'   => $stats,
            ]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                'error'   => 'Erro na importação: ' . $e->getMessage(),
            ]);
        }

        exit;
    }
}
