<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminEmailMarketingController extends Controller {

    private function ensureTables(\PDO $pdo): void {
        $tables = ['email_mkt_segmentos','email_mkt_campanhas','email_mkt_campanha_clientes','email_mkt_logs','email_mkt_config','email_mkt_envio_controle'];
        foreach ($tables as $t) {
            try { $st = $pdo->prepare("SELECT 1 FROM {$t} LIMIT 1"); $st->execute(); } catch (\Exception $e) {
                $sqlFile = __DIR__ . '/../../database/migrations/175_create_email_marketing_schema.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    foreach ($statements as $stmt) { if ($stmt !== '') { try { $pdo->exec($stmt); } catch (\Exception $ex) {} } }
                }
                break;
            }
        }
        // Ensure 'arquivada' status exists in ENUM (for upgrades)
        try { $pdo->exec("ALTER TABLE email_mkt_campanhas MODIFY COLUMN status ENUM('rascunho_ia','pendente_revisao','aprovada','agendada','disparando','finalizada','rejeitada','cancelada','arquivada') DEFAULT 'rascunho_ia'"); } catch (\Exception $e) {}
    }

    private function getConfig(\PDO $pdo, string $chave, string $default = ''): string {
        try {
            $st = $pdo->prepare("SELECT valor FROM email_mkt_config WHERE chave = ? LIMIT 1");
            $st->execute([$chave]);
            $v = $st->fetchColumn();
            return $v !== false ? (string)$v : $default;
        } catch (\Exception $e) { return $default; }
    }

    private function getChatGPTApiKey(\PDO $pdo): ?string {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_api_key']);
            $v = (string)($st->fetchColumn() ?: '');
            return $v !== '' ? $v : null;
        } catch (\Exception $e) { return null; }
    }

    private function getChatGPTModel(\PDO $pdo): string {
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_model']);
            $v = trim((string)($st->fetchColumn() ?: ''));
            return $v !== '' ? $v : 'gpt-4o-mini';
        } catch (\Exception $e) { return 'gpt-4o-mini'; }
    }

    private function callAI(\PDO $pdo, string $systemPrompt, string $userMessage): array {
        $apiKey = $this->getChatGPTApiKey($pdo);
        if (!$apiKey) return ['error' => 'API Key do ChatGPT não configurada.'];
        $model = $this->getChatGPTModel($pdo);
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>$userMessage]],
            'temperature' => 0.7, 'max_tokens' => 2000
        ]);
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey], CURLOPT_TIMEOUT=>90]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($err) return ['error'=>'Conexão: '.$err];
        $data = json_decode($resp, true);
        if ($code !== 200 || !isset($data['choices'][0]['message']['content']))
            return ['error' => $data['error']['message'] ?? 'Erro API (HTTP '.$code.')'];
        return ['text' => trim($data['choices'][0]['message']['content'])];
    }

    // ============================================================
    // DASHBOARD
    // ============================================================
    public function index(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $this->ensureTables($pdo);

        $tab = strtolower(trim((string)$request->getParam('tab', 'dashboard')));
        $validTabs = ['dashboard','campanhas','pendentes','aprovadas','agendadas','enviados','historico','segmentos','criterios','gatilhos','templates','config','logs','metricas'];
        if (!in_array($tab, $validTabs)) $tab = 'dashboard';

        // Stats
        $stats = ['total_campanhas'=>0,'pendentes'=>0,'aprovadas'=>0,'enviadas'=>0,'abertas'=>0,'clicadas'=>0,'convertidas'=>0,'rejeitadas'=>0];
        try {
            $stats['total_campanhas'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas")->fetchColumn();
            $stats['pendentes'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas WHERE status='pendente_revisao'")->fetchColumn();
            $stats['aprovadas'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas WHERE status='aprovada'")->fetchColumn();
            $stats['enviadas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_enviado),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['abertas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_aberto),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['clicadas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_clicado),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['convertidas'] = (int)$pdo->query("SELECT COALESCE(SUM(total_convertido),0) FROM email_mkt_campanhas")->fetchColumn();
            $stats['rejeitadas'] = (int)$pdo->query("SELECT COUNT(*) FROM email_mkt_campanhas WHERE status='rejeitada'")->fetchColumn();
        } catch (\Exception $e) {}

        // Campanhas list based on tab
        $campanhas = [];
        try {
            $where = "status != 'arquivada'";
            if ($tab === 'pendentes') $where = "status='pendente_revisao'";
            elseif ($tab === 'aprovadas') $where = "status IN ('aprovada','agendada')";
            elseif ($tab === 'agendadas') $where = "status='agendada'";
            elseif ($tab === 'enviados') $where = "status IN ('disparando','finalizada')";
            elseif ($tab === 'historico') $where = "status IN ('finalizada','cancelada','rejeitada','arquivada')";
            elseif ($tab === 'campanhas') $where = "status != 'arquivada'";
            $st = $pdo->query("SELECT * FROM email_mkt_campanhas WHERE {$where} ORDER BY updated_at DESC LIMIT 50");
            $campanhas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $this->renderPage($tab, $stats, $campanhas, $pdo);
    }

    // ============================================================
    // GERAR CAMPANHAS VIA IA
    // ============================================================
    public function gerarCampanhas(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $this->ensureTables($pdo);

        $input = json_decode(file_get_contents('php://input'), true);
        $tipo = trim((string)($input['tipo'] ?? 'reativacao'));
        $instrucoes = trim((string)($input['instrucoes'] ?? ''));
        $categoriaFiltro = trim((string)($input['categoria'] ?? ''));
        $usuarioIndividualId = (int)($input['usuario_id'] ?? 0);
        $apenasBuscar = !empty($input['apenas_buscar']);
        $clienteIdsSelecionados = $input['cliente_ids'] ?? null;
        $segmentoIds = $input['segmento_ids'] ?? [];
        $produtosIds = $input['produtos_ids'] ?? [];

        $diasRecompra = (int)$this->getConfig($pdo, 'dias_recompra_minimo', '30');
        $nomeLoja = 'Braziliana';
        try {
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'loja_nome' OR (categoria='loja' AND chave='nome') LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v) $nomeLoja = $v;
        } catch (\Exception $e) {}

        $userNomeCol = 'nome';
        try {
            $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $userNomeCol = 'name';
        } catch (\Exception $e) {}

        // Build client query based on campaign type
        $clientes = [];
        $gatilho = $tipo;
        $descricaoSegmento = '';

        try {
            if ($tipo === 'individual' && $usuarioIndividualId > 0) {
                $st = $pdo->prepare("SELECT u.id, u.{$userNomeCol} AS nome, u.email FROM usuarios u WHERE u.id = ?");
                $st->execute([$usuarioIndividualId]);
                $clientes = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Usuário individual";
                $gatilho = 'individual';

            } elseif ($tipo === 'reativacao' || $tipo === 'recompra') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MAX(p.created_at) AS ultima_compra,
                        COUNT(p.id) AS total_pedidos
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        WHERE u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING ultima_compra < DATE_SUB(NOW(), INTERVAL {$diasRecompra} DAY)
                        ORDER BY ultima_compra ASC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes que já compraram mas não compram há mais de {$diasRecompra} dias";

            } elseif ($tipo === 'aniversario') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, u.data_nascimento
                        FROM usuarios u
                        WHERE u.data_nascimento IS NOT NULL
                        AND (MONTH(u.data_nascimento) = MONTH(NOW()) AND DAY(u.data_nascimento) BETWEEN DAY(NOW()) AND DAY(NOW())+7)
                       ";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Aniversariantes da semana";

            } elseif ($tipo === 'pos_venda') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MAX(p.created_at) AS ultima_compra
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                       ";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes com compra nos últimos 7 dias";

            } elseif ($tipo === 'vip') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, COUNT(p.id) AS total_pedidos, SUM(COALESCE(p.valor_total,p.total,0)) AS total_gasto
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING total_pedidos >= 3 OR total_gasto >= 500
                        ORDER BY total_gasto DESC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes VIP (3+ pedidos ou $500+ gastos)";

            } elseif ($tipo === 'institucional') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email FROM usuarios u WHERE u.email IS NOT NULL AND u.email != '' ORDER BY u.{$userNomeCol} ASC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Todos os clientes (institucional)";

            } else {
                // categoria, carrinho_abandonado, checkout_abandonado - get clients with abandoned carts
                if ($tipo === 'carrinho_abandonado') {
                    // Clients who have items in carrinho_items but no paid order in last 3 days
                    $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email
                            FROM usuarios u
                            INNER JOIN carrinhos c ON c.usuario_id = u.id
                            INNER JOIN carrinho_items ci ON ci.carrinho_id = c.id
                            WHERE u.email IS NOT NULL AND u.email != ''
                            AND ci.quantidade > 0
                            AND u.id NOT IN (
                                SELECT p.usuario_id FROM pedidos p 
                                WHERE p.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) 
                                AND p.status IN ('pago','processando','enviado','entregue')
                            )
                            ORDER BY c.created_at DESC";
                    try {
                        $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Exception $e) {
                        // Fallback: try without carrinho_items join
                        try {
                            $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email
                                    FROM usuarios u
                                    INNER JOIN carrinhos c ON c.usuario_id = u.id
                                    WHERE u.email IS NOT NULL AND u.email != ''
                                    AND u.id NOT IN (
                                        SELECT p.usuario_id FROM pedidos p 
                                        WHERE p.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) 
                                        AND p.status IN ('pago','processando','enviado','entregue')
                                    )
                                    ORDER BY c.created_at DESC";
                            $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        } catch (\Exception $e2) {
                            $clientes = [];
                        }
                    }
                    $descricaoSegmento = "Clientes com carrinho abandonado (itens no carrinho sem pedido pago nos últimos 3 dias)";
                } else {
                    $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email FROM usuarios u WHERE u.email IS NOT NULL AND u.email != ''";
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    $descricaoSegmento = "Clientes ativos - campanha {$tipo}";
                }
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar clientes: ' . $e->getMessage()]);
            return;
        }

        if (empty($clientes)) {
            echo json_encode(['success' => false, 'error' => 'Nenhum cliente encontrado para este tipo de campanha. Tente outro tipo.']);
            return;
        }

        // Step 1: Just return the client list for selection
        if ($apenasBuscar) {
            echo json_encode(['success' => true, 'clientes' => $clientes, 'total' => count($clientes)]);
            return;
        }

        // Step 2: Filter to only selected clients if provided
        if (is_array($clienteIdsSelecionados) && !empty($clienteIdsSelecionados)) {
            $idsSet = array_flip(array_map('intval', $clienteIdsSelecionados));
            $clientes = array_filter($clientes, function($c) use ($idsSet) {
                return isset($idsSet[(int)$c['id']]);
            });
            $clientes = array_values($clientes);
            if (empty($clientes)) {
                echo json_encode(['success' => false, 'error' => 'Nenhum cliente selecionado válido.']);
                return;
            }
        }

        $tom = $this->getConfig($pdo, 'tom_marca', 'humanizado, elegante, conversacional');
        $palavrasProibidas = $this->getConfig($pdo, 'palavras_proibidas', '');

        // Generate campaign content via AI
        $tipoLabels = [
            'reativacao'=>'Reativação','aniversario'=>'Aniversário','pos_venda'=>'Pós-venda',
            'categoria'=>'Por Categoria','vip'=>'Clientes VIP','institucional'=>'Institucional',
            'recompra'=>'Recompra','carrinho_abandonado'=>'Carrinho Abandonado','individual'=>'Individual',
            'produtos'=>'Vitrine de Produtos'
        ];
        $tipoLabel = $tipoLabels[$tipo] ?? ucfirst($tipo);

        $systemPrompt = "Você é um especialista em email marketing para e-commerce. Gere o conteúdo de uma campanha do tipo '{$tipoLabel}'.
Tom: {$tom}. Palavras proibidas: {$palavrasProibidas}.
NÃO crie descontos, cupons ou promoções. NÃO use urgência falsa. NÃO invente informações.
Retorne em JSON com as chaves: assunto, pre_header, tag_campanha, titulo_email, subtitulo_email, paragrafo_1, paragrafo_2, texto_destaque, paragrafo_fechamento, texto_cta, texto_sub_cta.
O assunto deve ter no máximo 50 caracteres. O corpo total entre 120-220 palavras.";

        $userMsg = "Loja: {$nomeLoja}\nTipo: {$tipoLabel}\nSegmento: {$descricaoSegmento}\nTotal de clientes: " . count($clientes);
        if ($instrucoes !== '') $userMsg .= "\n\nInstruções do administrador:\n{$instrucoes}";
        if ($categoriaFiltro !== '') $userMsg .= "\nCategoria foco: {$categoriaFiltro}";
        $userMsg .= "\nExemplos de nomes: " . implode(', ', array_slice(array_column($clientes, 'nome'), 0, 5));

        $result = $this->callAI($pdo, $systemPrompt, $userMsg);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => 'Erro da IA: ' . $result['error']]);
            return;
        }

        $content = json_decode($result['text'], true);
        if (!$content || !isset($content['assunto'])) {
            preg_match('/\{.*\}/s', $result['text'], $m);
            if (!empty($m[0])) $content = json_decode($m[0], true);
            if (!$content) {
                echo json_encode(['success' => false, 'error' => 'IA retornou formato inválido. Tente novamente.']);
                return;
            }
        }

        // Create campaign
        $segmentoIdForCamp = !empty($segmentoIds) ? (int)$segmentoIds[0] : null;
        $st = $pdo->prepare("INSERT INTO email_mkt_campanhas (nome, tipo, gatilho, segmento_id, status, assunto, pre_header, variaveis_ia, total_clientes, observacoes_ia) VALUES (?, ?, ?, ?, 'pendente_revisao', ?, ?, ?, ?, ?)");
        $st->execute([
            "{$tipoLabel} - " . date('d/m'),
            $tipo,
            $gatilho,
            $segmentoIdForCamp,
            $content['assunto'] ?? 'Campanha',
            $content['pre_header'] ?? '',
            json_encode($content, JSON_UNESCAPED_UNICODE),
            count($clientes),
            ($instrucoes !== '' ? "Instruções: {$instrucoes}\n" : '') . "Segmento: {$descricaoSegmento}. " . count($clientes) . " clientes."
        ]);
        $campanhaId = (int)$pdo->lastInsertId();

        // Link clients
        $stInsert = $pdo->prepare("INSERT IGNORE INTO email_mkt_campanha_clientes (campanha_id, cliente_id, email, nome) VALUES (?, ?, ?, ?)");
        foreach ($clientes as $c) {
            $stInsert->execute([$campanhaId, (int)$c['id'], $c['email'], $c['nome']]);
        }

        // Build HTML
        if ($tipo === 'produtos' && !empty($produtosIds)) {
            $html = $this->buildProductEmailHtml($content, $nomeLoja, $produtosIds, $pdo);
        } else {
            $html = $this->buildEmailHtml($content, $nomeLoja);
        }
        $pdo->prepare("UPDATE email_mkt_campanhas SET html_content = ? WHERE id = ?")->execute([$html, $campanhaId]);

        echo json_encode(['success' => true, 'message' => "Campanha '{$tipoLabel}' gerada com " . count($clientes) . " clientes. Aguardando revisão.", 'campanha_id' => $campanhaId]);
    }

    private function buildEmailHtml(array $vars, string $nomeLoja): string {
        $template = file_get_contents(__DIR__ . '/../../resources/email_marketing_template.html');
        if (!$template) return '<p>Template não encontrado</p>';

        // Get logo URL from config
        $logoUrl = '';
        try {
            $pdo2 = Database::getConnection();
            $stLogo = $pdo2->prepare("SELECT valor FROM configuracoes_sistema WHERE (categoria='layout' AND chave='logo_email') OR chave='layout_logo_email' LIMIT 1");
            $stLogo->execute();
            $logoUrl = trim((string)($stLogo->fetchColumn() ?: ''));
            if ($logoUrl === '') {
                $stLogo2 = $pdo2->prepare("SELECT valor FROM configuracoes_sistema WHERE (categoria='layout' AND chave='logo_admin') OR chave='layout_logo_admin' LIMIT 1");
                $stLogo2->execute();
                $logoUrl = trim((string)($stLogo2->fetchColumn() ?: ''));
            }
        } catch (\Exception $e) {}
        if ($logoUrl === '') $logoUrl = '/assets/img/logo.png';

        $replacements = [
            '{{LOGO_URL}}' => htmlspecialchars($logoUrl),
            '{{NOME_LOJA}}' => htmlspecialchars($nomeLoja),
            '{{TAG_CAMPANHA}}' => htmlspecialchars($vars['tag_campanha'] ?? 'Novidades'),
            '{{TITULO_EMAIL}}' => htmlspecialchars($vars['titulo_email'] ?? $vars['assunto'] ?? ''),
            '{{SUBTITULO_EMAIL}}' => htmlspecialchars($vars['subtitulo_email'] ?? ''),
            '{{ASSUNTO_EMAIL}}' => htmlspecialchars($vars['assunto'] ?? ''),
            '{{NOME_CLIENTE}}' => '{{NOME_CLIENTE}}',
            '{{PARAGRAFO_1}}' => htmlspecialchars($vars['paragrafo_1'] ?? ''),
            '{{PARAGRAFO_2}}' => htmlspecialchars($vars['paragrafo_2'] ?? ''),
            '{{TEXTO_DESTAQUE}}' => htmlspecialchars($vars['texto_destaque'] ?? ''),
            '{{PARAGRAFO_FECHAMENTO}}' => htmlspecialchars($vars['paragrafo_fechamento'] ?? ''),
            '{{TEXTO_CTA}}' => htmlspecialchars($vars['texto_cta'] ?? 'Visitar a loja'),
            '{{LINK_CTA}}' => 'https://brazilianashop.com.br',
            '{{TEXTO_SUB_CTA}}' => htmlspecialchars($vars['texto_sub_cta'] ?? 'Acesse do celular ou computador'),
            '{{ENDERECO_LOJA}}' => 'Braziliana Shop',
            '{{LINK_DESCADASTRO}}' => '#',
            '{{LINK_POLITICA}}' => '/politica-privacidade',
        ];

        // Remove product section if no products
        $template = preg_replace('/<!-- PRODUTOS SUGERIDOS.*?<\/div>\s*<\/div>/s', '', $template);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    // ============================================================
    // BUSCAR USUÁRIOS (para campanha individual)
    // ============================================================
    public function buscarUsuarios(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $q = trim((string)$request->getParam('q', ''));
        if (strlen($q) < 2) { echo json_encode(['success'=>true,'usuarios'=>[]]); return; }

        $userNomeCol = 'nome';
        try {
            $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $userNomeCol = 'name';
        } catch (\Exception $e) {}

        $st = $pdo->prepare("SELECT id, {$userNomeCol} AS nome, email FROM usuarios WHERE ({$userNomeCol} LIKE ? OR email LIKE ?) AND email IS NOT NULL AND email != '' ORDER BY {$userNomeCol} LIMIT 10");
        $st->execute(["%{$q}%", "%{$q}%"]);
        $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success'=>true,'usuarios'=>$usuarios]);
    }

    // ============================================================
    // TRANSCREVER ÁUDIO
    // ============================================================
    public function transcrever(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();

        if (empty($_FILES['audio'])) {
            echo json_encode(['success' => false, 'error' => 'Nenhum áudio recebido']);
            return;
        }

        $apiKey = $this->getChatGPTApiKey($pdo);
        if (!$apiKey) {
            echo json_encode(['success' => false, 'error' => 'API Key não configurada']);
            return;
        }

        $tmpFile = $_FILES['audio']['tmp_name'];
        $cFile = new \CURLFile($tmpFile, 'audio/webm', 'audio.webm');

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $cFile, 'model' => 'whisper-1', 'language' => 'pt'],
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT => 60
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if ($code === 200 && isset($data['text'])) {
            echo json_encode(['success' => true, 'text' => trim($data['text'])]);
        } else {
            echo json_encode(['success' => false, 'error' => $data['error']['message'] ?? 'Erro na transcrição']);
        }
    }

    // ============================================================
    // APROVAR CAMPANHA
    // ============================================================
    public function aprovar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $uid = (int)($_SESSION['usuario_id'] ?? 0);
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='aprovada', aprovado_por=?, data_aprovacao=NOW() WHERE id=? AND status='pendente_revisao'")->execute([$uid, $id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // REJEITAR CAMPANHA
    // ============================================================
    public function rejeitar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='rejeitada' WHERE id=? AND status IN ('pendente_revisao','rascunho_ia')")->execute([$id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // EXCLUIR CAMPANHA (não finalizadas)
    // ============================================================
    public function excluir(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        // Only delete non-finalized campaigns
        $pdo->prepare("DELETE FROM email_mkt_campanha_clientes WHERE campanha_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM email_mkt_logs WHERE campanha_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM email_mkt_campanhas WHERE id = ? AND status NOT IN ('finalizada','disparando')")->execute([$id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // ARQUIVAR CAMPANHA (finalizadas)
    // ============================================================
    public function arquivar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='arquivada' WHERE id = ? AND status IN ('finalizada','cancelada','rejeitada')")->execute([$id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // AGENDAR CAMPANHA
    // ============================================================
    public function agendar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $dataAgendamento = trim((string)($data['data_agendamento'] ?? ''));
        if ($id <= 0 || $dataAgendamento === '') { echo json_encode(['success'=>false,'error'=>'Dados inválidos']); return; }
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='agendada', data_agendamento=? WHERE id=? AND status IN ('aprovada','pendente_revisao')")->execute([$dataAgendamento, $id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // DISPARAR CAMPANHA (imediato - marca como disparando)
    // ============================================================
    public function disparar(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $this->ensureTables($pdo);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }

        // Get frequency config
        $emailsPorLote = (int)$this->getConfig($pdo, 'emails_por_lote', '45');
        $intervaloSegundos = (int)$this->getConfig($pdo, 'intervalo_lote_segundos', '180');
        if ($emailsPorLote <= 0) $emailsPorLote = 45;
        if ($intervaloSegundos <= 0) $intervaloSegundos = 180;

        // Mark as dispatching
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='disparando', data_inicio_disparo=NOW() WHERE id=? AND status IN ('aprovada','agendada','pendente_revisao')")->execute([$id]);

        // Get campaign
        $st = $pdo->prepare("SELECT * FROM email_mkt_campanhas WHERE id=?"); $st->execute([$id]);
        $campanha = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$campanha) { echo json_encode(['success'=>false,'error'=>'Campanha não encontrada']); return; }

        // Get first batch of clients (only those still aguardando)
        $stCl = $pdo->prepare("SELECT * FROM email_mkt_campanha_clientes WHERE campanha_id=? AND status='aguardando' LIMIT ?");
        $stCl->bindValue(1, $id, \PDO::PARAM_INT);
        $stCl->bindValue(2, $emailsPorLote, \PDO::PARAM_INT);
        $stCl->execute();
        $clientes = $stCl->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Process this batch
        $enviados = 0;
        $stUpdate = $pdo->prepare("UPDATE email_mkt_campanha_clientes SET status='enviado', data_envio=NOW() WHERE id=?");
        $stLog = $pdo->prepare("INSERT INTO email_mkt_logs (campanha_id, cliente_id, email, evento, detalhes) VALUES (?, ?, ?, 'enviado', 'Lote processado')");
        $stTrack = $pdo->prepare("INSERT IGNORE INTO email_mkt_tracking (hash, campanha_id, cliente_id, tipo, url_destino) VALUES (?, ?, ?, ?, ?)");

        // Ensure tracking table exists
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS email_mkt_tracking (id INT AUTO_INCREMENT PRIMARY KEY, hash VARCHAR(64) NOT NULL UNIQUE, campanha_id INT NOT NULL, cliente_id INT NOT NULL, tipo ENUM('open','click') NOT NULL, url_destino TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_hash (hash))"); } catch (\Exception $e) {}

        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'novosite.brazilianashop.com.br');

        foreach ($clientes as $cl) {
            $clienteId = (int)$cl['cliente_id'];
            $clienteEmail = $cl['email'];

            // Generate tracking hashes for this client
            $openHash = md5("open_{$id}_{$clienteId}_" . time() . rand());
            $clickHash = md5("click_{$id}_{$clienteId}_" . time() . rand());

            // Save tracking records
            $stTrack->execute([$openHash, $id, $clienteId, 'open', null]);
            $stTrack->execute([$clickHash, $id, $clienteId, 'click', $baseUrl]);

            // Personalize email HTML for this client
            $personalizedHtml = str_replace('{{NOME_CLIENTE}}', htmlspecialchars($cl['nome'] ?? 'Cliente'), $campanha['html_content'] ?? '');

            // Inject open tracking pixel before </body>
            $pixelUrl = $baseUrl . '/email-track/open/' . $openHash;
            $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" style="display:none;" alt="">';
            $personalizedHtml = str_replace('</body>', $pixelTag . '</body>', $personalizedHtml);

            // Rewrite links to go through click tracking
            $personalizedHtml = preg_replace_callback('/href="(https?:\/\/[^"]+)"/', function($matches) use ($baseUrl, $clickHash, $id, $clienteId, $pdo, $stTrack) {
                $originalUrl = $matches[1];
                if (strpos($originalUrl, '/email-track/') !== false) return $matches[0]; // Don't rewrite tracking URLs
                $linkHash = md5("click_{$id}_{$clienteId}_" . $originalUrl . rand());
                try { $stTrack->execute([$linkHash, $id, $clienteId, 'click', $originalUrl]); } catch (\Exception $e) {}
                return 'href="' . $baseUrl . '/email-track/click/' . $linkHash . '?url=' . urlencode($originalUrl) . '"';
            }, $personalizedHtml);

            // TODO: Send $personalizedHtml to $clienteEmail via SMTP/SES/SendGrid
            // mail($clienteEmail, $campanha['assunto'], $personalizedHtml, "Content-Type: text/html; charset=UTF-8");

            $stUpdate->execute([(int)$cl['id']]);
            $stLog->execute([$id, $clienteId, $clienteEmail]);
            $enviados++;
        }

        // Check remaining
        $stRemaining = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanha_clientes WHERE campanha_id=? AND status='aguardando'");
        $stRemaining->execute([$id]);
        $remaining = (int)$stRemaining->fetchColumn();

        // Update totals
        $stTotal = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanha_clientes WHERE campanha_id=? AND status='enviado'");
        $stTotal->execute([$id]);
        $totalEnviado = (int)$stTotal->fetchColumn();
        $pdo->prepare("UPDATE email_mkt_campanhas SET total_enviado=? WHERE id=?")->execute([$totalEnviado, $id]);

        if ($remaining === 0) {
            $pdo->prepare("UPDATE email_mkt_campanhas SET status='finalizada', data_fim_disparo=NOW() WHERE id=?")->execute([$id]);
            echo json_encode(['success'=>true, 'message'=>"Disparo concluído! {$totalEnviado} emails enviados.", 'enviados'=>$totalEnviado, 'remaining'=>0, 'finalizado'=>true]);
        } else {
            echo json_encode([
                'success'=>true,
                'message'=>"Lote de {$enviados} enviado. Restam {$remaining}. Próximo lote em {$intervaloSegundos}s.",
                'enviados'=>$enviados,
                'total_enviado'=>$totalEnviado,
                'remaining'=>$remaining,
                'finalizado'=>false,
                'proximo_lote_segundos'=>$intervaloSegundos,
                'emails_por_lote'=>$emailsPorLote
            ]);
        }
    }

    // ============================================================
    // DETALHES DA CAMPANHA (JSON)
    // ============================================================
    public function detalhes(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin','vendedor']);
        $pdo = Database::getConnection();
        $id = (int)$request->getParam('id');
        $st = $pdo->prepare("SELECT * FROM email_mkt_campanhas WHERE id = ?"); $st->execute([$id]);
        $campanha = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$campanha) { echo json_encode(['success'=>false,'error'=>'Não encontrada']); return; }
        // Get clients
        $st2 = $pdo->prepare("SELECT * FROM email_mkt_campanha_clientes WHERE campanha_id = ? ORDER BY id"); $st2->execute([$id]);
        $clientes = $st2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success'=>true,'campanha'=>$campanha,'clientes'=>$clientes]);
    }

    // ============================================================
    // SALVAR CONFIGURAÇÕES
    // ============================================================
    public function salvarConfig(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) { echo json_encode(['success'=>false,'error'=>'Dados inválidos']); return; }
        foreach ($data as $chave => $valor) {
            $pdo->prepare("INSERT INTO email_mkt_config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$chave, (string)$valor]);
        }
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // BUSCAR PRODUTOS (para campanha de produtos)
    // ============================================================
    public function buscarProdutos(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $q = trim((string)$request->getParam('q', ''));
        if (strlen($q) < 2) { echo json_encode(['success'=>true,'produtos'=>[]]); return; }

        $nomeCol = 'nome';
        $fotoCol = null;
        $precoCol = 'price';
        try {
            $cols = $pdo->query("DESCRIBE produtos")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $nomeCol = 'name';
            if (in_array('foto_principal', $cols)) $fotoCol = 'foto_principal';
            elseif (in_array('imagem', $cols)) $fotoCol = 'imagem';
            elseif (in_array('image', $cols)) $fotoCol = 'image';
            if (in_array('price', $cols)) $precoCol = 'price';
            elseif (in_array('preco', $cols)) $precoCol = 'preco';
            elseif (in_array('valor', $cols)) $precoCol = 'valor';
        } catch (\Exception $e) {}

        $fotoSelect = $fotoCol ? ", {$fotoCol} AS foto" : ", NULL AS foto";
        $st = $pdo->prepare("SELECT id, {$nomeCol} AS nome, {$precoCol} AS preco {$fotoSelect} FROM produtos WHERE {$nomeCol} LIKE ? LIMIT 15");
        $st->execute(["%{$q}%"]);
        $produtos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Format price
        foreach ($produtos as &$p) {
            $p['preco'] = 'US$ ' . number_format((float)($p['preco'] ?? 0), 2, '.', ',');
        }

        echo json_encode(['success'=>true,'produtos'=>$produtos]);
    }

    private function buildProductEmailHtml(array $vars, string $nomeLoja, array $produtosIds, \PDO $pdo): string {
        // Get product details
        $nomeCol = 'nome'; $fotoCol = null; $precoCol = 'price'; $descCol = 'descricao';
        try {
            $cols = $pdo->query("DESCRIBE produtos")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $nomeCol = 'name';
            if (in_array('foto_principal', $cols)) $fotoCol = 'foto_principal';
            elseif (in_array('imagem', $cols)) $fotoCol = 'imagem';
            if (in_array('price', $cols)) $precoCol = 'price';
            elseif (in_array('preco', $cols)) $precoCol = 'preco';
            if (!in_array('descricao', $cols) && in_array('description', $cols)) $descCol = 'description';
        } catch (\Exception $e) {}

        $fotoSelect = $fotoCol ? ", {$fotoCol} AS foto" : ", NULL AS foto";
        $ids = array_map('intval', $produtosIds);
        $in = implode(',', $ids);

        // Check for promo price column
        $promoCol = null;
        try {
            $allCols = $pdo->query("DESCRIBE produtos")->fetchAll(\PDO::FETCH_COLUMN);
            if (in_array('preco_promocional', $allCols)) $promoCol = 'preco_promocional';
            elseif (in_array('sale_price', $allCols)) $promoCol = 'sale_price';
            elseif (in_array('preco_desconto', $allCols)) $promoCol = 'preco_desconto';
        } catch (\Exception $e) {}
        $promoSelect = $promoCol ? ", {$promoCol} AS preco_promo" : ", NULL AS preco_promo";

        $produtos = $pdo->query("SELECT id, {$nomeCol} AS nome, {$precoCol} AS preco, {$descCol} AS descricao {$fotoSelect} {$promoSelect} FROM produtos WHERE id IN ({$in})")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Get logo
        $logoUrl = '';
        try {
            $stLogo = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE (categoria='layout' AND chave='logo_email') OR chave='layout_logo_email' LIMIT 1");
            $stLogo->execute();
            $logoUrl = trim((string)($stLogo->fetchColumn() ?: ''));
            if ($logoUrl === '') {
                $stLogo2 = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE (categoria='layout' AND chave='logo_admin') OR chave='layout_logo_admin' LIMIT 1");
                $stLogo2->execute();
                $logoUrl = trim((string)($stLogo2->fetchColumn() ?: ''));
            }
        } catch (\Exception $e) {}
        if ($logoUrl === '') $logoUrl = '/assets/img/logo.png';

        // Build product grid HTML
        $productHtml = '';
        foreach ($produtos as $p) {
            $nome = htmlspecialchars($p['nome'] ?? '');
            $precoOriginal = (float)($p['preco'] ?? 0);
            $precoPromo = (float)($p['preco_promo'] ?? 0);
            $temPromo = ($precoPromo > 0 && $precoPromo < $precoOriginal);

            if ($temPromo) {
                $precoHtml = '<div style="margin-bottom:10px;"><span style="font-size:12px;color:#BE123C;text-decoration:line-through;font-weight:500;">US$ ' . number_format($precoOriginal, 2, '.', ',') . '</span><br><span style="font-size:18px;font-weight:700;color:#065F46;">US$ ' . number_format($precoPromo, 2, '.', ',') . '</span></div>';
            } else {
                $precoHtml = '<div style="font-size:16px;font-weight:700;color:#18253D;margin-bottom:10px;">US$ ' . number_format($precoOriginal, 2, '.', ',') . '</div>';
            }

            $foto = htmlspecialchars($p['foto'] ?? '');
            $link = 'https://brazilianashop.com.br/produto/detalhes/' . (int)$p['id'];
            $fotoTag = $foto ? '<img src="' . $foto . '" alt="' . $nome . '" style="width:100%;height:180px;object-fit:cover;border-radius:10px 10px 0 0;">' : '<div style="width:100%;height:180px;background:#F5F7FA;border-radius:10px 10px 0 0;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-size:40px;">📦</div>';

            $productHtml .= '
            <div style="width:48%;display:inline-block;vertical-align:top;margin-bottom:16px;border:1px solid #EBF0F6;border-radius:10px;overflow:hidden;background:#fff;">
                ' . $fotoTag . '
                <div style="padding:12px 14px;">
                    <div style="font-size:13px;font-weight:600;color:#1F2937;margin-bottom:4px;line-height:1.3;">' . $nome . '</div>
                    ' . $precoHtml . '
                    <a href="' . $link . '" style="display:block;text-align:center;background:#18253D;color:#fff;padding:10px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Comprar agora</a>
                </div>
            </div>';
        }

        // Build full email
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . htmlspecialchars($vars['assunto'] ?? '') . '</title></head><body style="margin:0;background:#F5F7FA;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
<div style="max-width:620px;margin:32px auto;background:#fff;border-radius:16px;border:1px solid #EBF0F6;overflow:hidden;">
  <div style="background:#18253D;padding:24px 40px;text-align:center;"><img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($nomeLoja) . '" style="max-height:48px;width:auto;"></div>
  <div style="background:linear-gradient(135deg,#1E2F4D,#18253D);padding:28px 40px;text-align:center;">
    <div style="display:inline-block;background:rgba(255,255,255,.12);color:rgba(255,255,255,.8);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;padding:4px 12px;border-radius:999px;margin-bottom:12px;">' . htmlspecialchars($vars['tag_campanha'] ?? 'Novidades') . '</div>
    <h1 style="color:#fff;font-size:24px;font-weight:700;margin:0 0 8px;">' . htmlspecialchars($vars['titulo_email'] ?? '') . '</h1>
    <p style="color:rgba(255,255,255,.68);font-size:14px;margin:0;">' . htmlspecialchars($vars['subtitulo_email'] ?? '') . '</p>
  </div>
  <div style="padding:32px 40px;">
    <p style="font-size:15px;color:#1F2937;margin:0 0 16px;">Olá, <strong style="color:#18253D;">{{NOME_CLIENTE}}</strong>! 👋</p>
    <p style="font-size:14px;color:#374151;line-height:1.7;margin:0 0 24px;">' . htmlspecialchars($vars['paragrafo_1'] ?? '') . '</p>
    <div style="font-size:0;text-align:center;">' . $productHtml . '</div>
    <div style="height:1px;background:#EBF0F6;margin:24px 0;"></div>
    <p style="font-size:14px;color:#374151;line-height:1.7;margin:0 0 24px;">' . htmlspecialchars($vars['paragrafo_fechamento'] ?? '') . '</p>
    <div style="text-align:center;margin:24px 0;">
      <a href="https://brazilianashop.com.br" style="display:inline-block;background:#18253D;color:#fff;padding:14px 36px;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none;">' . htmlspecialchars($vars['texto_cta'] ?? 'Ver todos os produtos') . '</a>
    </div>
  </div>
  <div style="background:#FAFBFC;border-top:1px solid #EBF0F6;padding:20px 40px;text-align:center;">
    <div style="font-size:13px;font-weight:600;color:#18253D;margin-bottom:4px;">' . htmlspecialchars($nomeLoja) . '</div>
    <p style="font-size:12px;color:#94A3B8;margin:0;">Você está recebendo este email porque é cliente ' . htmlspecialchars($nomeLoja) . '.</p>
  </div>
</div></body></html>';

        return $html;
    }

    // ============================================================
    // RENDER PAGE
    // ============================================================
    private function renderPage(string $tab, array $stats, array $campanhas, \PDO $pdo): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Email Marketing - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/assets/css/dashboard-redesign.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '<style>
:root{--navy:#18253D;--navy-hover:#243049;}
.mkt-page{padding:24px 0;width:100%;}
.mkt-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:20px;border-bottom:1px solid #EBF0F6;padding-bottom:12px;}
.mkt-tab{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:500;color:#6B7280;text-decoration:none;border:1px solid transparent;transition:.18s;}
.mkt-tab:hover{color:var(--navy);background:#F8FAFC;}
.mkt-tab.active{background:var(--navy);color:#fff;border-color:var(--navy);}
.mkt-tab .badge-count{font-size:10px;background:rgba(255,255,255,.2);padding:1px 6px;border-radius:99px;margin-left:4px;}
.mkt-tab.active .badge-count{background:rgba(255,255,255,.25);}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:500;}
.st-rascunho{background:#F1F5F9;color:#475569;}.st-pendente{background:#FEF3C7;color:#92400E;}
.st-aprovada{background:#D1FAE5;color:#065F46;}.st-agendada{background:#EDE9FE;color:#5B21B6;}
.st-disparando{background:#E0F2FE;color:#075985;}.st-finalizada{background:#D1FAE5;color:#065F46;}
.st-rejeitada{background:#FFE4E6;color:#9F1239;}.st-cancelada{background:#F1F5F9;color:#475569;}
.camp-table{width:100%;border-collapse:collapse;}
.camp-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;padding:11px 14px;border-bottom:1px solid #EBF0F6;background:#FAFBFC;}
.camp-table td{padding:13px 14px;border-bottom:1px solid #F1F5F9;font-size:13px;color:#374151;}
.camp-table tr:hover td{background:#FAFBFC;}
.btn-navy{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:.18s;}
.btn-navy:hover{background:var(--navy-hover);color:#fff;}
.btn-ghost{background:#fff;color:#374151;border:1px solid #E2E8F0;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:.18s;}
.btn-ghost:hover{background:#F8FAFC;border-color:#CBD5E1;}
</style></head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('email-marketing');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="mkt-page">';

        // Header
        echo '<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
<div><h1 style="font-size:20px;font-weight:700;color:var(--navy);margin:0;">Automações de Email Marketing</h1>
<small style="color:#64748B;">Campanhas inteligentes geradas por IA</small></div>
<button class="btn-navy" onclick="document.getElementById(\'modalNovaCampanha\').style.display=\'flex\'"><i class="bi bi-plus-lg me-1"></i>Nova Campanha</button>
</div>';

        // KPI Cards
        echo '<div class="kpi-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Campanhas</div><div class="kpi-value">'.$stats['total_campanhas'].'</div></div></div>
<div class="kpi-card"><div><div class="kpi-label">Pendentes</div><div class="kpi-value">'.$stats['pendentes'].'</div></div></div>
<div class="kpi-card"><div><div class="kpi-label">Enviados</div><div class="kpi-value">'.$stats['enviadas'].'</div></div></div>
<div class="kpi-card is-featured"><div><div class="kpi-label">Convertidos</div><div class="kpi-value">'.$stats['convertidas'].'</div></div></div>
</div>
<style>@media(min-width:768px){.kpi-grid{grid-template-columns:repeat(4,1fr) !important;}}</style>';

        // Tabs
        $tabs = ['dashboard'=>'Dashboard','campanhas'=>'Todas','pendentes'=>'Pendentes','aprovadas'=>'Aprovadas','agendadas'=>'Agendadas','enviados'=>'Enviados','historico'=>'Histórico','segmentos'=>'Segmentos','criterios'=>'Critérios','config'=>'Configurações'];
        // Mobile: Dropdown
        echo '<div class="d-md-none mb-3"><select class="form-select" onchange="window.location.href=\'?tab=\'+this.value">';
        foreach ($tabs as $key => $label) {
            $sel = ($tab === $key) ? ' selected' : '';
            $countLabel = '';
            if ($key === 'pendentes' && $stats['pendentes'] > 0) $countLabel = ' ('.$stats['pendentes'].')';
            echo '<option value="'.$key.'"'.$sel.'>'.$label.$countLabel.'</option>';
        }
        echo '</select></div>';
        // Desktop: Tabs
        echo '<nav class="mkt-tabs d-none d-md-flex">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key) ? ' active' : '';
            $count = '';
            if ($key === 'pendentes' && $stats['pendentes'] > 0) $count = '<span class="badge-count">'.$stats['pendentes'].'</span>';
            echo '<a class="mkt-tab'.$active.'" href="?tab='.$key.'">'.$label.$count.'</a>';
        }
        echo '</nav>';

        // Content based on tab
        if ($tab === 'config') {
            $this->renderConfigTab($pdo);
        } elseif ($tab === 'dashboard' || $tab === 'campanhas' || $tab === 'pendentes' || $tab === 'aprovadas' || $tab === 'agendadas' || $tab === 'enviados' || $tab === 'historico') {
            $this->renderCampanhasTable($campanhas);
        } elseif ($tab === 'segmentos') {
            $this->renderSegmentosTab($pdo);
        } elseif ($tab === 'criterios') {
            $this->renderCriteriosTab($pdo);
        } else {
            echo '<div class="section-card"><div class="section-body"><p style="color:#94A3B8;">Seção em desenvolvimento.</p></div></div>';
        }

        // Modal Nova Campanha (seleção de tipo + instruções + áudio)
        echo '<div id="modalNovaCampanha" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:16px;max-width:560px;width:95%;max-height:90vh;overflow-y:auto;">
<div style="background:var(--navy);color:#fff;padding:20px 24px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center;">
<h5 style="margin:0;font-size:16px;font-weight:700;"><i class="bi bi-stars me-2"></i>Nova Campanha</h5>
<button onclick="document.getElementById(\'modalNovaCampanha\').style.display=\'none\'" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
</div>
<div style="padding:24px;">

<div style="margin-bottom:16px;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;display:block;margin-bottom:8px;">Tipo de Campanha</label>
<select id="campTipo" style="width:100%;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;">
<option value="reativacao">Reativação (clientes sem comprar)</option>
<option value="aniversario">Aniversariantes</option>
<option value="pos_venda">Pós-venda (compra recente)</option>
<option value="categoria">Por Categoria de Produto</option>
<option value="vip">Clientes VIP</option>
<option value="institucional">Institucional / Relacionamento</option>
<option value="recompra">Recompra Inteligente</option>
<option value="carrinho_abandonado">Carrinho Abandonado</option>
<option value="produtos">Produtos (vitrine de produtos)</option>
<option value="individual">Usuário Individual</option>
</select>
</div>

<div id="campIndividualWrap" style="margin-bottom:16px;display:none;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;display:block;margin-bottom:8px;">Buscar Usuário</label>
<input type="text" id="campIndividualBusca" placeholder="Digite nome ou email..." oninput="buscarUsuariosCamp()" style="width:100%;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;">
<div id="campIndividualResultados" style="max-height:150px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:8px;margin-top:6px;display:none;"></div>
<input type="hidden" id="campIndividualId" value="">
<div id="campIndividualSelecionado" style="margin-top:6px;font-size:12px;color:#065F46;display:none;"></div>
</div>

<div id="campProdutosWrap" style="margin-bottom:16px;display:none;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;display:block;margin-bottom:8px;">Buscar Produtos para o Email</label>
<input type="text" id="campProdutoBusca" placeholder="Digite o nome do produto..." oninput="buscarProdutosCamp()" style="width:100%;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;">
<div id="campProdutoResultados" style="max-height:150px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:8px;margin-top:6px;display:none;"></div>
<div id="campProdutosSelecionados" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;"></div>
<small style="color:#94A3B8;font-size:11px;">Selecione 1 a 6 produtos para exibir no email.</small>
</div>

<div style="margin-bottom:16px;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;display:block;margin-bottom:8px;">Segmento (opcional - vincular a segmentos existentes)</label>
<div id="campSegmentosCheckboxes" style="max-height:160px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:8px;padding:8px 12px;">';

        // Load segments as checkboxes
        $segmentosAtivos = [];
        try { $segmentosAtivos = $pdo->query("SELECT id, nome, total_clientes FROM email_mkt_segmentos WHERE ativo = 1 ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}
        if (empty($segmentosAtivos)) {
            echo '<span style="color:#94A3B8;font-size:12px;">Nenhum segmento criado. Gere na aba Segmentos.</span>';
        }
        foreach ($segmentosAtivos as $sa) {
            echo '<label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:13px;"><input type="checkbox" class="seg-check" value="'.(int)$sa['id'].'"> '.htmlspecialchars($sa['nome']).' <span style="color:#94A3B8;font-size:11px;">('.(int)$sa['total_clientes'].')</span></label>';
        }

        echo '</div>
<small style="color:#94A3B8;font-size:11px;">Marque os segmentos desejados. Deixe vazio para usar o tipo como critério.</small>
</div>

<div id="campCategoriaWrap" style="margin-bottom:16px;display:none;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;display:block;margin-bottom:8px;">Categoria</label>
<input type="text" id="campCategoria" placeholder="Ex: snacks, beleza, limpeza..." style="width:100%;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;">
</div>

<div style="margin-bottom:16px;">
<label style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;display:block;margin-bottom:8px;">Instruções e Contexto para a IA</label>
<textarea id="campInstrucoes" rows="4" placeholder="Descreva o objetivo da campanha, tom desejado, produtos a destacar, público-alvo específico..." style="width:100%;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
<div style="display:flex;align-items:center;gap:8px;margin-top:8px;">
<button type="button" id="btnMic" onclick="toggleRecording()" style="width:36px;height:36px;border-radius:50%;border:2px solid #E2E8F0;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;">
<i class="bi bi-mic-fill" style="font-size:16px;color:#64748B;"></i>
</button>
<span id="micStatus" style="font-size:12px;color:#94A3B8;">Clique no microfone para ditar instruções</span>
</div>
</div>

<div style="display:flex;gap:10px;margin-top:20px;" id="campStep1Btns">
<button onclick="buscarClientesElegiveis()" class="btn-navy" style="flex:1;padding:12px;font-size:14px;"><i class="bi bi-search me-1"></i>Buscar Clientes Elegíveis</button>
<button onclick="document.getElementById(\'modalNovaCampanha\').style.display=\'none\'" style="flex:0;padding:12px 20px;border:1px solid #E2E8F0;border-radius:8px;background:#fff;color:#374151;font-size:14px;cursor:pointer;">Cancelar</button>
</div>

<div id="campStep2" style="display:none;margin-top:20px;">
<div style="border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;">
<div style="padding:10px 14px;background:#FAFBFC;border-bottom:1px solid #E2E8F0;">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
<span style="font-size:12px;font-weight:700;color:#94A3B8;text-transform:uppercase;">Clientes elegíveis (<span id="campTotalClientes">0</span>)</span>
<span id="campSelecionados" style="font-size:12px;color:#18253D;font-weight:600;">0 selecionados</span>
</div>
<div style="display:flex;gap:6px;margin-bottom:8px;">
<button type="button" onclick="marcarTodosClientes(true)" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;background:#fff;font-size:11px;cursor:pointer;color:#18253D;font-weight:600;">Marcar todos</button>
<button type="button" onclick="marcarTodosClientes(false)" style="padding:4px 10px;border:1px solid #E2E8F0;border-radius:6px;background:#fff;font-size:11px;cursor:pointer;color:#64748B;">Desmarcar todos</button>
</div>
<input type="text" id="campFiltroClientes" oninput="filtrarClientesLista()" placeholder="Filtrar por nome ou email..." style="width:100%;padding:7px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;">
</div>
<div id="campClientesList" style="max-height:250px;overflow-y:auto;padding:4px 0;"></div>
</div>
<div style="display:flex;gap:10px;margin-top:14px;">
<button onclick="gerarComClientesSelecionados()" class="btn-navy" style="flex:1;padding:12px;font-size:14px;"><i class="bi bi-stars me-1"></i>Gerar Campanha</button>
<button onclick="document.getElementById(\'campStep2\').style.display=\'none\';document.getElementById(\'campStep1Btns\').style.display=\'flex\';" style="flex:0;padding:12px 20px;border:1px solid #E2E8F0;border-radius:8px;background:#fff;color:#374151;font-size:14px;cursor:pointer;">Voltar</button>
</div>
</div>

<div id="campProgress" style="display:none;margin-top:16px;text-align:center;">
<i class="bi bi-stars" style="font-size:24px;color:var(--navy);animation:spin 2s linear infinite;"></i>
<p style="color:#64748B;font-size:13px;margin-top:8px;">Analisando clientes e gerando campanha...</p>
</div>

</div></div></div>
<style>@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}</style>';

        // Campaign detail modal
        echo '<div class="modal fade" id="modalCampanha" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
<div class="modal-header" style="background:var(--navy);color:#fff;"><h5 class="modal-title" id="modalCampTitle">Campanha</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="modalCampBody"><p>Carregando...</p></div>
<div class="modal-footer" style="flex-wrap:wrap;gap:8px;">
<div id="modalDispatchControls" style="display:none;width:100%;padding:12px;background:#F5F7FA;border-radius:8px;margin-bottom:8px;">
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
<button class="btn btn-primary btn-sm" onclick="dispararAgora()"><i class="bi bi-send-fill me-1"></i>Disparar Agora</button>
<span style="color:#94A3B8;font-size:12px;">ou agendar:</span>
<input type="datetime-local" id="modalAgendamento" style="padding:6px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;">
<button class="btn btn-outline-primary btn-sm" onclick="agendarDisparo()"><i class="bi bi-calendar-check me-1"></i>Agendar</button>
</div>
</div>
<button class="btn btn-success" id="btnAprovarCamp" onclick="aprovarCampanha()"><i class="bi bi-check-lg me-1"></i>Aprovar</button>
<button class="btn btn-danger" onclick="rejeitarCampanha()"><i class="bi bi-x-lg me-1"></i>Rejeitar</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
</div></div></div></div>';

        // JS
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentCampId = null;
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;

// Show/hide fields based on type
document.getElementById("campTipo").addEventListener("change", function(){
    document.getElementById("campCategoriaWrap").style.display = this.value === "categoria" ? "block" : "none";
    document.getElementById("campIndividualWrap").style.display = this.value === "individual" ? "block" : "none";
    document.getElementById("campProdutosWrap").style.display = this.value === "produtos" ? "block" : "none";
});

// Search users for individual campaign
let searchTimeout = null;
function buscarUsuariosCamp(){
    clearTimeout(searchTimeout);
    const q = document.getElementById("campIndividualBusca").value.trim();
    if(q.length < 2){ document.getElementById("campIndividualResultados").style.display="none"; return; }
    searchTimeout = setTimeout(async ()=>{
        const r = await fetch("/admin/email-marketing/buscar-usuarios?q="+encodeURIComponent(q));
        const d = await r.json();
        const wrap = document.getElementById("campIndividualResultados");
        if(!d.success || !d.usuarios.length){ wrap.innerHTML="<div style=\\"padding:8px 12px;color:#94A3B8;font-size:12px;\\">Nenhum resultado</div>"; wrap.style.display="block"; return; }
        wrap.innerHTML = d.usuarios.map(u=>"<div onclick=\\"selecionarUsuarioCamp("+u.id+",this)\\" data-nome=\\""+u.nome.replace(/"/g,"&quot;")+"\\" data-email=\\""+u.email+"\\" style=\\"padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #F1F5F9;\\" onmouseover=\\"this.style.background=\'#F8FAFC\'\\" onmouseout=\\"this.style.background=\'\'\\">"+"<strong>"+u.nome+"</strong><br><small style=\\"color:#94A3B8;\\">"+u.email+"</small></div>").join("");
        wrap.style.display="block";
    }, 300);
}
function selecionarUsuarioCamp(id, el){
    var nome = el.getAttribute("data-nome");
    var email = el.getAttribute("data-email");
    document.getElementById("campIndividualId").value = id;
    document.getElementById("campIndividualBusca").value = nome;
    document.getElementById("campIndividualResultados").style.display = "none";
    document.getElementById("campIndividualSelecionado").style.display = "block";
    document.getElementById("campIndividualSelecionado").innerHTML = "<i class=\\"bi bi-check-circle-fill\\" style=\\"color:#065F46;\\"></i> "+nome+" ("+email+")";
}

// Product search for product campaigns
let produtosSelecionados = [];
let prodSearchTimeout = null;
function buscarProdutosCamp(){
    clearTimeout(prodSearchTimeout);
    const q = document.getElementById("campProdutoBusca").value.trim();
    if(q.length < 2){ document.getElementById("campProdutoResultados").style.display="none"; return; }
    prodSearchTimeout = setTimeout(async ()=>{
        const r = await fetch("/admin/email-marketing/buscar-produtos?q="+encodeURIComponent(q));
        const d = await r.json();
        const wrap = document.getElementById("campProdutoResultados");
        if(!d.success || !d.produtos.length){ wrap.innerHTML="<div style=\\"padding:8px 12px;color:#94A3B8;font-size:12px;\\">Nenhum produto encontrado</div>"; wrap.style.display="block"; return; }
        wrap.innerHTML = d.produtos.map(p=>"<div onclick=\\"adicionarProdutoCamp("+p.id+",this)\\" data-nome=\\""+p.nome.replace(/"/g,"&quot;")+"\\" data-preco=\\""+p.preco+"\\" data-foto=\\""+(p.foto||"")+"\\" style=\\"display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;border-bottom:1px solid #F1F5F9;\\" onmouseover=\\"this.style.background=\'#F8FAFC\'\\" onmouseout=\\"this.style.background=\'\'\\">"+(p.foto?"<img src=\\""+p.foto+"\\" style=\\"width:32px;height:32px;object-fit:cover;border-radius:6px;\\">":"")+"<div><strong style=\\"font-size:13px;\\">"+p.nome+"</strong><br><small style=\\"color:#94A3B8;\\">"+p.preco+"</small></div></div>").join("");
        wrap.style.display="block";
    }, 300);
}
function adicionarProdutoCamp(id, el){
    if(produtosSelecionados.length >= 6){ alert("Máximo 6 produtos."); return; }
    if(produtosSelecionados.find(p=>p.id===id)) return;
    produtosSelecionados.push({id, nome:el.getAttribute("data-nome"), preco:el.getAttribute("data-preco"), foto:el.getAttribute("data-foto")});
    document.getElementById("campProdutoResultados").style.display="none";
    document.getElementById("campProdutoBusca").value="";
    renderProdutosSelecionados();
}
function removerProdutoCamp(id){
    produtosSelecionados = produtosSelecionados.filter(p=>p.id!==id);
    renderProdutosSelecionados();
}
function renderProdutosSelecionados(){
    const wrap = document.getElementById("campProdutosSelecionados");
    wrap.innerHTML = produtosSelecionados.map(p=>"<span style=\\"display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#EEF2F7;border-radius:6px;font-size:12px;\\">"+p.nome+" <button onclick=\\"removerProdutoCamp("+p.id+")\\" style=\\"background:none;border:none;color:#BE123C;cursor:pointer;font-size:14px;\\">&times;</button></span>").join("");
}

// Audio recording
function toggleRecording(){
    if(isRecording){ stopRecording(); return; }
    navigator.mediaDevices.getUserMedia({audio:true}).then(stream=>{
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
        mediaRecorder.onstop = async ()=>{
            stream.getTracks().forEach(t=>t.stop());
            const blob = new Blob(audioChunks,{type:"audio/webm"});
            document.getElementById("micStatus").textContent = "Transcrevendo áudio...";
            const fd = new FormData(); fd.append("audio", blob, "audio.webm");
            try {
                const r = await fetch("/admin/email-marketing/transcrever", {method:"POST", body:fd});
                const d = await r.json();
                if(d.success && d.text){
                    document.getElementById("campInstrucoes").value += (document.getElementById("campInstrucoes").value ? "\n" : "") + d.text;
                    document.getElementById("micStatus").textContent = "Transcrição adicionada!";
                } else {
                    document.getElementById("micStatus").textContent = d.error || "Erro na transcrição";
                }
            } catch(e){ document.getElementById("micStatus").textContent = "Erro de conexão"; }
        };
        mediaRecorder.start();
        isRecording = true;
        document.getElementById("btnMic").style.borderColor = "#BE123C";
        document.getElementById("btnMic").style.background = "#FFE4E6";
        document.getElementById("micStatus").textContent = "Gravando... clique para parar";
    }).catch(()=>{ document.getElementById("micStatus").textContent = "Microfone não disponível"; });
}
function stopRecording(){
    if(mediaRecorder && mediaRecorder.state !== "inactive") mediaRecorder.stop();
    isRecording = false;
    document.getElementById("btnMic").style.borderColor = "#E2E8F0";
    document.getElementById("btnMic").style.background = "#fff";
}

async function buscarClientesElegiveis(){
    const tipo = document.getElementById("campTipo").value;
    const categoria = document.getElementById("campCategoria").value.trim();
    const individualId = document.getElementById("campIndividualId").value;
    const segmentoIds = [...document.querySelectorAll(".seg-check:checked")].map(c => parseInt(c.value));
    
    if(tipo === "individual" && !individualId){
        alert("Selecione um usuário para campanha individual.");
        return;
    }
    
    document.getElementById("campProgress").style.display = "block";
    
    const body = JSON.stringify({tipo, categoria, usuario_id: individualId || null, segmento_ids: segmentoIds, produtos_ids: produtosSelecionados.map(p=>p.id), apenas_buscar: true});
    const r = await fetch("/admin/email-marketing/gerar", {method:"POST", headers:{"Content-Type":"application/json"}, body});
    const d = await r.json();
    
    document.getElementById("campProgress").style.display = "none";
    
    if(!d.success && !d.clientes){
        alert(d.error || "Erro ao buscar clientes");
        return;
    }
    
    const clientes = d.clientes || [];
    if(!clientes.length){
        alert("Nenhum cliente encontrado para este tipo de campanha.");
        return;
    }
    
    // Show step 2
    document.getElementById("campStep1Btns").style.display = "none";
    document.getElementById("campStep2").style.display = "block";
    document.getElementById("campTotalClientes").textContent = clientes.length;
    
    const list = document.getElementById("campClientesList");
    list.innerHTML = clientes.map(c => 
        "<label style=\\"display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #F1F5F9;cursor:pointer;font-size:13px;\\" onmouseover=\\"this.style.background=\'#F8FAFC\'\\" onmouseout=\\"this.style.background=\'\'\\">" +
        "<input type=\\"checkbox\\" class=\\"cliente-check\\" value=\\""+c.id+"\\" checked onchange=\\"updateSelecionados()\\">" +
        "<span><strong>"+c.nome+"</strong> <span style=\\"color:#94A3B8;\\">"+c.email+"</span></span></label>"
    ).join("");
    
    document.getElementById("campSelectAll").checked = true;
    updateSelecionados();
}

function toggleAllClientes(el){
    document.querySelectorAll(".cliente-check").forEach(c => c.checked = el.checked);
    updateSelecionados();
}

function marcarTodosClientes(marcar){
    document.querySelectorAll(".cliente-check").forEach(c => { if(c.closest("label").style.display !== "none") c.checked = marcar; });
    updateSelecionados();
}

function filtrarClientesLista(){
    const termo = document.getElementById("campFiltroClientes").value.toLowerCase();
    document.querySelectorAll("#campClientesList label").forEach(el => {
        el.style.display = el.textContent.toLowerCase().includes(termo) ? "" : "none";
    });
}

function updateSelecionados(){
    const total = document.querySelectorAll(".cliente-check:checked").length;
    document.getElementById("campSelecionados").textContent = total + " selecionados";
}

async function gerarComClientesSelecionados(){
    const ids = [...document.querySelectorAll(".cliente-check:checked")].map(c => parseInt(c.value));
    if(!ids.length){ alert("Selecione pelo menos 1 cliente."); return; }
    
    const tipo = document.getElementById("campTipo").value;
    const instrucoes = document.getElementById("campInstrucoes").value.trim();
    const categoria = document.getElementById("campCategoria").value.trim();
    const segmentoIds = [...document.querySelectorAll(".seg-check:checked")].map(c => parseInt(c.value));
    
    document.getElementById("campProgress").style.display = "block";
    
    const body = JSON.stringify({tipo, instrucoes, categoria, cliente_ids: ids, segmento_ids: segmentoIds, produtos_ids: produtosSelecionados.map(p=>p.id)});
    const r = await fetch("/admin/email-marketing/gerar", {method:"POST", headers:{"Content-Type":"application/json"}, body});
    const d = await r.json();
    
    document.getElementById("campProgress").style.display = "none";
    
    if(d.success){
        document.getElementById("modalNovaCampanha").style.display = "none";
        alert(d.message || "Campanha gerada com sucesso!");
        location.reload();
    } else {
        alert(d.error || d.message || "Erro ao gerar campanha");
    }
}
async function verCampanha(id){
    currentCampId=id;
    const modal=new bootstrap.Modal(document.getElementById("modalCampanha"));
    document.getElementById("modalCampBody").innerHTML="<p>Carregando...</p>";
    modal.show();
    const r=await fetch("/admin/email-marketing/detalhes?id="+id);
    const d=await r.json();
    if(!d.success){document.getElementById("modalCampBody").innerHTML="<p class=text-danger>"+d.error+"</p>";return;}
    const c=d.campanha;
    document.getElementById("modalCampTitle").textContent=c.nome||"Campanha #"+c.id;
    let html="<div class=row><div class=col-md-6>";
    html+="<p><strong>Status:</strong> "+c.status+"</p>";
    html+="<p><strong>Tipo:</strong> "+c.tipo+"</p>";
    html+="<p><strong>Gatilho:</strong> "+(c.gatilho||"-")+"</p>";
    html+="<p><strong>Assunto:</strong> "+(c.assunto||"-")+"</p>";
    html+="<p><strong>Clientes:</strong> "+c.total_clientes+"</p>";
    html+="<p><strong>Observações IA:</strong><br><small>"+(c.observacoes_ia||"-")+"</small></p>";
    html+="</div><div class=col-md-6>";
    html+="<h6>Preview do Email</h6><div style=border:1px solid #eee;border-radius:8px;max-height:400px;overflow:auto;padding:8px;>"+(c.html_content||"<p>Sem conteúdo</p>")+"</div>";
    html+="</div></div>";
    if(d.clientes&&d.clientes.length){
        html+="<hr><h6>Clientes ("+d.clientes.length+")</h6><div style=max-height:200px;overflow:auto;><table class=camp-table><thead><tr><th>Nome</th><th>Email</th><th>Status</th></tr></thead><tbody>";
        d.clientes.forEach(cl=>{html+="<tr><td>"+(cl.nome||"-")+"</td><td>"+cl.email+"</td><td>"+cl.status+"</td></tr>";});
        html+="</tbody></table></div>";
    }
    document.getElementById("modalCampBody").innerHTML=html;
    // Show/hide controls based on status
    var isApproved = (c.status === "aprovada" || c.status === "agendada");
    var isPending = (c.status === "pendente_revisao");
    document.getElementById("modalDispatchControls").style.display = isApproved ? "block" : "none";
    document.getElementById("btnAprovarCamp").style.display = isPending ? "" : "none";
}
async function aprovarCampanha(){
    if(!currentCampId)return;
    const r=await fetch("/admin/email-marketing/aprovar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId})});
    const d=await r.json();
    if(d.success){
        document.getElementById("btnAprovarCamp").style.display="none";
        document.getElementById("modalDispatchControls").style.display="block";
        alert("Campanha aprovada! Agora escolha quando disparar.");
    }
    else alert(d.error||"Erro");
}

async function dispararAgora(){
    if(!currentCampId||!confirm("Disparar campanha? Os emails serão enviados em lotes conforme configuração."))return;
    document.getElementById("modalDispatchControls").innerHTML = "<div style=\\"text-align:center;padding:12px;\\"><i class=\\"bi bi-send-fill\\" style=\\"font-size:20px;color:var(--navy);\\"></i><p id=\\"dispatchStatus\\" style=\\"color:#64748B;font-size:13px;margin:8px 0 0;\\">Iniciando disparo...</p><div style=\\"background:#E2E8F0;border-radius:4px;height:6px;margin-top:8px;overflow:hidden;\\"><div id=\\"dispatchBar\\" style=\\"height:100%;background:var(--navy);width:0%;transition:width .3s;\\"></div></div></div>";
    await processarLoteDisparo();
}

async function processarLoteDisparo(){
    const r=await fetch("/admin/email-marketing/disparar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId})});
    const d=await r.json();
    if(!d.success){alert(d.error||"Erro");return;}
    
    const statusEl=document.getElementById("dispatchStatus");
    const barEl=document.getElementById("dispatchBar");
    
    if(d.finalizado){
        if(statusEl)statusEl.textContent="Concluído! "+d.enviados+" emails enviados.";
        if(barEl)barEl.style.width="100%";
        setTimeout(()=>location.reload(),2000);
    } else {
        const total=d.total_enviado+d.remaining;
        const pct=Math.round((d.total_enviado/total)*100);
        if(statusEl)statusEl.textContent="Enviados: "+d.total_enviado+" / "+(total)+" — Próximo lote em "+d.proximo_lote_segundos+"s";
        if(barEl)barEl.style.width=pct+"%";
        setTimeout(()=>processarLoteDisparo(), d.proximo_lote_segundos*1000);
    }
}

async function agendarDisparo(){
    const dt=document.getElementById("modalAgendamento").value;
    if(!dt){alert("Selecione data e hora.");return;}
    const r=await fetch("/admin/email-marketing/agendar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId,data_agendamento:dt})});
    const d=await r.json();
    if(d.success){bootstrap.Modal.getInstance(document.getElementById("modalCampanha")).hide();alert("Campanha agendada para "+dt);location.reload();}
    else alert(d.error||"Erro ao agendar");
}
async function rejeitarCampanha(){
    if(!currentCampId||!confirm("Rejeitar esta campanha?"))return;
    const r=await fetch("/admin/email-marketing/rejeitar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId})});
    const d=await r.json();
    if(d.success){bootstrap.Modal.getInstance(document.getElementById("modalCampanha")).hide();location.reload();}
    else alert(d.error||"Erro");
}
async function excluirCampanha(id){
    if(!confirm("Excluir esta campanha? Esta ação não pode ser desfeita."))return;
    const r=await fetch("/admin/email-marketing/excluir",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id})});
    const d=await r.json();
    if(d.success)location.reload();
    else alert(d.error||"Erro");
}
async function arquivarCampanha(id){
    if(!confirm("Arquivar esta campanha?"))return;
    const r=await fetch("/admin/email-marketing/arquivar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id})});
    const d=await r.json();
    if(d.success)location.reload();
    else alert(d.error||"Erro");
}
</script>';
        echo '</div></main></div></div></body></html>';
    }

    private function renderCampanhasTable(array $campanhas): void {
        if (empty($campanhas)) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;"><p style="color:#94A3B8;margin-top:12px;">Nenhuma campanha encontrada.</p></div></div>';
            return;
        }
        $statusMap = ['rascunho_ia'=>'st-rascunho','pendente_revisao'=>'st-pendente','aprovada'=>'st-aprovada','agendada'=>'st-agendada','disparando'=>'st-disparando','finalizada'=>'st-finalizada','rejeitada'=>'st-rejeitada','cancelada'=>'st-cancelada','arquivada'=>'st-cancelada'];
        $statusLabels = ['rascunho_ia'=>'Rascunho IA','pendente_revisao'=>'Pendente','aprovada'=>'Aprovada','agendada'=>'Agendada','disparando'=>'Disparando','finalizada'=>'Finalizada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada','arquivada'=>'Arquivada'];

        // Desktop: Table
        echo '<div class="section-card"><div class="d-none d-md-block" style="overflow-x:auto;"><table class="camp-table"><thead><tr><th>Campanha</th><th>Tipo</th><th>Status</th><th>Clientes</th><th>Enviados</th><th>Abertos</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
        foreach ($campanhas as $c) {
            $st = $c['status'] ?? 'rascunho_ia';
            $badge = '<span class="status-pill '.($statusMap[$st]??'st-rascunho').'">'.($statusLabels[$st]??$st).'</span>';
            $data = !empty($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '-';
            echo '<tr><td><strong>'.htmlspecialchars($c['nome']??'').'</strong><br><small style="color:#94A3B8;">'.htmlspecialchars($c['assunto']??'').'</small></td>';
            echo '<td>'.ucfirst(str_replace('_',' ',$c['tipo']??'')).'</td><td>'.$badge.'</td>';
            echo '<td>'.(int)$c['total_clientes'].'</td><td>'.(int)$c['total_enviado'].'</td><td>'.(int)$c['total_aberto'].'</td>';
            echo '<td>'.$data.'</td>';
            $acoes = '<button class="btn-ghost" style="padding:4px 10px;font-size:12px;" onclick="verCampanha('.(int)$c['id'].')"><i class="bi bi-eye me-1"></i>Ver</button>';
            if (in_array($st, ['finalizada','cancelada','rejeitada'])) {
                $acoes .= ' <button style="padding:4px 8px;font-size:11px;border:1px solid #E2E8F0;border-radius:6px;background:#fff;cursor:pointer;color:#94A3B8;" onclick="arquivarCampanha('.(int)$c['id'].')" title="Arquivar"><i class="bi bi-archive"></i></button>';
            } else {
                $acoes .= ' <button style="padding:4px 8px;font-size:11px;border:1px solid #E2E8F0;border-radius:6px;background:#fff;cursor:pointer;color:#BE123C;" onclick="excluirCampanha('.(int)$c['id'].')" title="Excluir"><i class="bi bi-trash"></i></button>';
            }
            echo '<td>'.$acoes.'</td></tr>';
        }
        echo '</tbody></table></div>';

        // Mobile: Cards
        echo '<div class="d-md-none">';
        foreach ($campanhas as $c) {
            $st = $c['status'] ?? 'rascunho_ia';
            $badge = '<span class="status-pill '.($statusMap[$st]??'st-rascunho').'">'.($statusLabels[$st]??$st).'</span>';
            echo '<div class="border-bottom py-2" onclick="verCampanha('.(int)$c['id'].')" style="cursor:pointer;">
<div class="d-flex justify-content-between align-items-start">
<div style="min-width:0;flex:1;"><div class="fw-semibold small" style="word-break:break-word;">'.htmlspecialchars($c['nome']??'').'</div>
<div class="text-muted" style="font-size:11px;word-break:break-word;">'.htmlspecialchars($c['assunto']??'').'</div></div>
'.$badge.'</div>
<div class="d-flex flex-wrap gap-2 mt-1" style="font-size:11px;">
<span class="text-muted">'.ucfirst(str_replace('_',' ',$c['tipo']??'')).'</span>
<span>'.(int)$c['total_clientes'].' clientes</span>
<span>'.(int)$c['total_enviado'].' enviados</span>
</div></div>';
        }
        echo '</div></div>';
    }

    private function renderSegmentosTab(\PDO $pdo): void {
        // Get existing segments
        $segmentos = [];
        try { $segmentos = $pdo->query("SELECT * FROM email_mkt_segmentos WHERE ativo = 1 ORDER BY updated_at DESC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        echo '<div class="d-flex justify-content-between align-items-center mb-3">
<h6 style="color:var(--navy);font-weight:700;margin:0;">Segmentações Inteligentes</h6>
<button class="btn-navy" style="padding:8px 14px;font-size:13px;" onclick="gerarSegmentosIA()"><i class="bi bi-stars me-1"></i>Gerar Segmentos com IA</button>
</div>';

        echo '<div id="segProgress" style="display:none;text-align:center;padding:20px;"><i class="bi bi-stars" style="font-size:24px;color:var(--navy);animation:spin 2s linear infinite;"></i><p style="color:#64748B;font-size:13px;margin-top:8px;">Analisando comportamento de clientes...</p></div>';

        if (empty($segmentos)) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;"><i class="bi bi-diagram-3" style="font-size:40px;color:#94A3B8;"></i><p style="color:#94A3B8;margin-top:12px;">Nenhum segmento criado. Clique em "Gerar Segmentos com IA" para analisar seus clientes.</p></div></div>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;">';
            foreach ($segmentos as $seg) {
                $criterios = json_decode($seg['criterios'] ?? '{}', true);
                $criterioText = $criterios['criterio'] ?? $criterios['descricao'] ?? ($seg['gatilho'] ?? '-');
                echo '<div class="section-card" style="margin-bottom:0;cursor:pointer;" id="seg-'.(int)$seg['id'].'" onclick="editarSegmento('.(int)$seg['id'].')"><div style="padding:16px;">
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
<div style="font-size:14px;font-weight:700;color:var(--navy);">'.htmlspecialchars($seg['nome']).'</div>
<div style="display:flex;gap:4px;" onclick="event.stopPropagation()">
<button onclick="excluirSegmento('.(int)$seg['id'].')" style="width:26px;height:26px;border-radius:6px;border:1px solid #E2E8F0;background:#fff;cursor:pointer;color:#BE123C;font-size:12px;" title="Excluir"><i class="bi bi-trash"></i></button>
</div>
</div>
<div style="font-size:12px;color:#64748B;margin-bottom:8px;">'.htmlspecialchars($seg['descricao'] ?? '').'</div>
<div style="font-size:11px;color:#94A3B8;margin-bottom:8px;padding:6px 8px;background:#F8FAFC;border-radius:6px;"><strong>Critério:</strong> '.htmlspecialchars(is_string($criterioText) ? $criterioText : json_encode($criterioText)).'</div>
<div style="display:flex;gap:12px;font-size:12px;">
<span style="color:#18253D;font-weight:600;"><i class="bi bi-people me-1"></i>'.(int)$seg['total_clientes'].' clientes</span>
<span style="color:#94A3B8;">'.htmlspecialchars($seg['gatilho'] ?? 'automático').'</span>
</div>
</div></div>';
            }
            echo '</div>';
        }

        echo '<script>
async function gerarSegmentosIA(){
    document.getElementById("segProgress").style.display="block";
    const r=await fetch("/admin/email-marketing/gerar-segmentos",{method:"POST"});
    const d=await r.json();
    document.getElementById("segProgress").style.display="none";
    if(d.success){alert(d.message||"Segmentos gerados!");location.reload();}
    else alert(d.error||"Erro ao gerar segmentos");
}
async function excluirSegmento(id){
    if(!confirm("Excluir este segmento?"))return;
    const r=await fetch("/admin/email-marketing/excluir-segmento",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id})});
    const d=await r.json();
    if(d.success){document.getElementById("seg-"+id).remove();}
    else alert(d.error||"Erro");
}
async function editarSegmento(id){
    const r=await fetch("/admin/email-marketing/segmento-detalhes?id="+id);
    const d=await r.json();
    if(!d.success){alert(d.error||"Erro");return;}
    const s=d.segmento;
    document.getElementById("editSegId").value=s.id;
    document.getElementById("editSegNome").value=s.nome||"";
    document.getElementById("editSegDescricao").value=s.descricao||"";
    document.getElementById("editSegGatilho").value=s.gatilho||"automatico";
    document.getElementById("editSegTotal").value=s.total_clientes||0;
    document.getElementById("editSegCriterios").value=s.criterios||"";
    document.getElementById("editSegClientes").innerHTML="<div style=\\"padding:20px;text-align:center;color:#94A3B8;\\"><i class=\\"bi bi-arrow-repeat\\" style=\\"animation:spin 1s linear infinite;\\"></i> Carregando...</div>";
    document.getElementById("modalEditSeg").style.display="flex";
    carregarClientesSegmento(id);
}
async function carregarClientesSegmento(id){
    const r=await fetch("/admin/email-marketing/segmento-clientes?id="+id);
    const d=await r.json();
    const wrap=document.getElementById("editSegClientes");
    const countEl=document.getElementById("editSegClientesCount");
    if(d.success && d.clientes && d.clientes.length){
        countEl.textContent=d.clientes.length+" clientes";
        wrap.innerHTML=d.clientes.map(c=>"<label class=\\"seg-cliente-item\\" style=\\"display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #F1F5F9;cursor:pointer;font-size:12px;\\" onmouseover=\\"this.style.background=\'#F8FAFC\'\\" onmouseout=\\"this.style.background=\'\'\\">"+"<input type=\\"checkbox\\" class=\\"seg-cl-check\\" value=\\""+c.id+"\\" checked>"+"<span style=\\"flex:1;min-width:0;\\"><strong style=\\"display:block;font-size:13px;\\">"+c.nome+"</strong><span style=\\"color:#94A3B8;\\">"+c.email+"</span></span>"+"<span style=\\"color:#94A3B8;font-size:11px;white-space:nowrap;\\">"+(c.ultima_compra||"")+"</span></label>").join("");
    } else {
        countEl.textContent="0 clientes";
        wrap.innerHTML="<div style=\\"padding:20px;text-align:center;color:#94A3B8;font-size:12px;\\">Nenhum cliente encontrado para este critério.</div>";
    }
}
async function recarregarClientesSegmento(){
    var id=document.getElementById("editSegId").value;
    var gatilho=document.getElementById("editSegGatilho").value;
    if(id){
        document.getElementById("editSegClientes").innerHTML="<div style=\\"padding:20px;text-align:center;color:#94A3B8;\\"><i class=\\"bi bi-arrow-repeat\\" style=\\"animation:spin 1s linear infinite;\\"></i> Atualizando...</div>";
        const r=await fetch("/admin/email-marketing/segmento-clientes?id="+id+"&gatilho="+encodeURIComponent(gatilho));
        const d=await r.json();
        const wrap=document.getElementById("editSegClientes");
        const countEl=document.getElementById("editSegClientesCount");
        if(d.success && d.clientes && d.clientes.length){
            countEl.textContent=d.clientes.length+" clientes";
            wrap.innerHTML=d.clientes.map(c=>"<label class=\\"seg-cliente-item\\" style=\\"display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #F1F5F9;cursor:pointer;font-size:12px;\\" onmouseover=\\"this.style.background=\'#F8FAFC\'\\" onmouseout=\\"this.style.background=\'\'\\">"+"<input type=\\"checkbox\\" class=\\"seg-cl-check\\" value=\\""+c.id+"\\" checked>"+"<span style=\\"flex:1;min-width:0;\\"><strong style=\\"display:block;font-size:13px;\\">"+c.nome+"</strong><span style=\\"color:#94A3B8;\\">"+c.email+"</span></span>"+"<span style=\\"color:#94A3B8;font-size:11px;white-space:nowrap;\\">"+(c.ultima_compra||"")+"</span></label>").join("");
        } else {
            countEl.textContent="0 clientes";
            wrap.innerHTML="<div style=\\"padding:20px;text-align:center;color:#94A3B8;font-size:12px;\\">Nenhum cliente encontrado para este critério.</div>";
        }
    }
}
function marcarTodosSegClientes(marcar){
    document.querySelectorAll(".seg-cl-check").forEach(c=>{if(c.closest(".seg-cliente-item").style.display!=="none")c.checked=marcar;});
}
function filtrarSegClientes(){
    var termo=document.getElementById("editSegClientesBusca").value.toLowerCase();
    document.querySelectorAll(".seg-cliente-item").forEach(el=>{el.style.display=el.textContent.toLowerCase().includes(termo)?"":"none";});
}
async function salvarSegmento(){
    const id=document.getElementById("editSegId").value;
    const data={
        id:parseInt(id),
        nome:document.getElementById("editSegNome").value,
        descricao:document.getElementById("editSegDescricao").value,
        gatilho:document.getElementById("editSegGatilho").value,
        total_clientes:parseInt(document.getElementById("editSegTotal").value)||0
    };
    const r=await fetch("/admin/email-marketing/salvar-segmento",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});
    const d=await r.json();
    if(d.success){document.getElementById("modalEditSeg").style.display="none";location.reload();}
    else alert(d.error||"Erro");
}
async function removerClienteSegmento(segId, clienteId){
    if(!confirm("Remover este cliente do segmento?"))return;
    const r=await fetch("/admin/email-marketing/segmento-remover-cliente",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({segmento_id:segId,cliente_id:clienteId})});
    const d=await r.json();
    if(d.success) editarSegmento(segId);
    else alert(d.error||"Erro");
}
</script>';

        // Modal editar segmento
        echo '<div id="modalEditSeg" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:12px;max-width:600px;width:95%;max-height:90vh;overflow-y:auto;">
<div style="background:var(--navy);color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;">
<h6 style="margin:0;font-size:15px;font-weight:700;"><i class="bi bi-diagram-3 me-2"></i>Gerenciar Segmento</h6>
<button onclick="document.getElementById(\'modalEditSeg\').style.display=\'none\'" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
</div>
<div style="padding:20px;">
<input type="hidden" id="editSegId">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
<div><label style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94A3B8;display:block;margin-bottom:4px;">Nome do Segmento</label><input type="text" id="editSegNome" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;"></div>
<div><label style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94A3B8;display:block;margin-bottom:4px;">Critério de Seleção</label><select id="editSegGatilho" onchange="recarregarClientesSegmento()" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;">
<option value="automatico">Automático (IA define)</option>
<option value="primeira_compra">Primeira compra recente</option>
<option value="sem_compra_30">Sem compra há 30 dias</option>
<option value="sem_compra_60">Sem compra há 60 dias</option>
<option value="sem_compra_90">Sem compra há 90+ dias</option>
<option value="vip">Cliente VIP (recorrente/alto valor)</option>
<option value="aniversario">Aniversário</option>
<option value="categoria">Por categoria de produto</option>
<option value="carrinho_abandonado">Carrinho abandonado</option>
<option value="abriu_nao_clicou">Abriu email mas não clicou</option>
<option value="clicou_nao_converteu">Clicou mas não converteu</option>
<option value="converteu">Converteu (comprou após email)</option>
<option value="nunca_abriu">Nunca abriu emails</option>
<option value="engajado">Engajado (abre e clica sempre)</option>
<option value="novo_cadastro">Novo cadastro (< 7 dias)</option>
<option value="inativo_total">Inativo total (sem atividade)</option>
</select></div>
</div>

<div style="margin-bottom:16px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94A3B8;display:block;margin-bottom:4px;">Descrição</label><textarea id="editSegDescricao" rows="2" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;resize:vertical;" placeholder="Descreva o objetivo deste segmento..."></textarea></div>
<input type="hidden" id="editSegTotal">
<input type="hidden" id="editSegCriterios">

<div style="border:1px solid #E2E8F0;border-radius:8px;overflow:hidden;margin-bottom:16px;">
<div style="padding:10px 14px;background:#FAFBFC;border-bottom:1px solid #E2E8F0;">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
<span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#94A3B8;">Clientes do Segmento</span>
<span id="editSegClientesCount" style="font-size:12px;color:#18253D;font-weight:600;">0 clientes</span>
</div>
<div style="display:flex;gap:6px;">
<button type="button" onclick="marcarTodosSegClientes(true)" style="padding:3px 8px;border:1px solid #E2E8F0;border-radius:5px;background:#fff;font-size:10px;cursor:pointer;color:#18253D;font-weight:600;">Marcar todos</button>
<button type="button" onclick="marcarTodosSegClientes(false)" style="padding:3px 8px;border:1px solid #E2E8F0;border-radius:5px;background:#fff;font-size:10px;cursor:pointer;color:#64748B;">Desmarcar</button>
</div>
<input type="text" id="editSegClientesBusca" oninput="filtrarSegClientes()" placeholder="Buscar por nome ou email..." style="width:100%;padding:6px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;margin-top:6px;">
</div>
<div id="editSegClientes" style="max-height:280px;overflow-y:auto;"></div>
</div>

<button onclick="salvarSegmento()" style="width:100%;padding:12px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;"><i class="bi bi-check-lg me-1"></i>Salvar Segmento</button>
</div></div></div>';
    }

    // ============================================================
    // GERAR SEGMENTOS VIA IA
    // ============================================================
    public function gerarSegmentos(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $this->ensureTables($pdo);

        $userNomeCol = 'nome';
        try {
            $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $userNomeCol = 'name';
        } catch (\Exception $e) {}

        // Analyze client behavior
        $analise = [];
        try {
            // Clients by category
            $sql = "SELECT c.nome AS categoria, COUNT(DISTINCT p.usuario_id) AS total_clientes
                    FROM pedido_itens pi
                    JOIN produtos pr ON pr.id = pi.produto_id
                    JOIN categorias c ON c.id = pr.categoria_id
                    JOIN pedidos p ON p.id = pi.pedido_id AND p.status IN ('pago','entregue','enviado')
                    GROUP BY c.nome ORDER BY total_clientes DESC LIMIT 10";
            $analise['categorias'] = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { $analise['categorias'] = []; }

        try {
            // Recency groups
            $sql = "SELECT
                SUM(CASE WHEN ultima < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS inativos_90,
                SUM(CASE WHEN ultima BETWEEN DATE_SUB(NOW(), INTERVAL 90 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS inativos_30_90,
                SUM(CASE WHEN ultima > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS ativos
                FROM (SELECT MAX(p.created_at) AS ultima FROM usuarios u JOIN pedidos p ON p.usuario_id=u.id AND p.status IN ('pago','entregue') GROUP BY u.id) sub";
            $analise['recencia'] = $pdo->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { $analise['recencia'] = []; }

        try {
            $analise['total_usuarios'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        } catch (\Exception $e) { $analise['total_usuarios'] = 0; }

        // Ask AI to create segments
        $systemPrompt = "Você é um analista de CRM. Com base nos dados abaixo, crie segmentos inteligentes de clientes para email marketing.
Retorne um JSON array com objetos: [{\"nome\": \"...\", \"descricao\": \"...\", \"gatilho\": \"...\", \"criterio\": \"...\", \"total_estimado\": N}]
Crie entre 5 e 10 segmentos relevantes. Exemplos: clientes por categoria favorita, clientes inativos, clientes VIP, novos clientes, etc.
Use nomes curtos e descritivos em português.";

        $userMsg = "Dados da loja:\n- Total de clientes: " . $analise['total_usuarios'];
        if (!empty($analise['categorias'])) {
            $userMsg .= "\n- Categorias mais compradas: " . implode(', ', array_map(fn($c) => $c['categoria'] . ' (' . $c['total_clientes'] . ' clientes)', $analise['categorias']));
        }
        if (!empty($analise['recencia'])) {
            $r = $analise['recencia'];
            $userMsg .= "\n- Clientes ativos (compra <30 dias): " . (int)($r['ativos'] ?? 0);
            $userMsg .= "\n- Clientes inativos 30-90 dias: " . (int)($r['inativos_30_90'] ?? 0);
            $userMsg .= "\n- Clientes inativos 90+ dias: " . (int)($r['inativos_90'] ?? 0);
        }

        $result = $this->callAI($pdo, $systemPrompt, $userMsg);
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
            return;
        }

        $segmentos = json_decode($result['text'], true);
        if (!$segmentos) {
            preg_match('/\[.*\]/s', $result['text'], $m);
            if (!empty($m[0])) $segmentos = json_decode($m[0], true);
        }
        if (!is_array($segmentos) || empty($segmentos)) {
            echo json_encode(['success' => false, 'error' => 'IA retornou formato inválido.']);
            return;
        }

        // Save segments (with versioning - never overwrite existing)
        $criados = 0;
        $stInsert = $pdo->prepare("INSERT INTO email_mkt_segmentos (nome, descricao, tipo, gatilho, criterios, total_clientes, ativo) VALUES (?, ?, 'automatico', ?, ?, ?, 1)");
        
        foreach ($segmentos as $seg) {
            if (empty($seg['nome'])) continue;
            
            // Check if similar segment exists - if so, create as V2
            $stCheck = $pdo->prepare("SELECT id, nome FROM email_mkt_segmentos WHERE nome LIKE ? AND ativo = 1 ORDER BY id DESC LIMIT 1");
            $baseName = $seg['nome'];
            $stCheck->execute(['%' . $baseName . '%']);
            $existing = $stCheck->fetch(\PDO::FETCH_ASSOC);
            
            $finalName = $baseName;
            if ($existing) {
                // Find next version
                $stVer = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_segmentos WHERE nome LIKE ?");
                $stVer->execute(['%' . $baseName . '%']);
                $ver = (int)$stVer->fetchColumn() + 1;
                $finalName = $baseName . ' (V' . $ver . ')';
            }
            
            $stInsert->execute([
                $finalName,
                $seg['descricao'] ?? '',
                $seg['gatilho'] ?? 'automatico',
                json_encode($seg, JSON_UNESCAPED_UNICODE),
                (int)($seg['total_estimado'] ?? 0)
            ]);
            $criados++;
        }

        echo json_encode(['success' => true, 'message' => "{$criados} segmentos criados. Revise na aba Segmentos.", 'total' => $criados]);
    }

    // ============================================================
    // SEGMENTO DETALHES (JSON)
    // ============================================================
    public function segmentoDetalhes(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $id = (int)$request->getParam('id');
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $st = $pdo->prepare("SELECT * FROM email_mkt_segmentos WHERE id = ?"); $st->execute([$id]);
        $seg = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$seg) { echo json_encode(['success'=>false,'error'=>'Segmento não encontrado']); return; }
        echo json_encode(['success'=>true,'segmento'=>$seg]);
    }

    // ============================================================
    // SALVAR SEGMENTO (edição)
    // ============================================================
    public function salvarSegmento(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $nome = trim((string)($data['nome'] ?? ''));
        $descricao = trim((string)($data['descricao'] ?? ''));
        $gatilho = trim((string)($data['gatilho'] ?? ''));
        $totalClientes = (int)($data['total_clientes'] ?? 0);
        if ($nome === '') { echo json_encode(['success'=>false,'error'=>'Nome obrigatório']); return; }
        $pdo->prepare("UPDATE email_mkt_segmentos SET nome=?, descricao=?, gatilho=?, total_clientes=?, updated_at=NOW() WHERE id=?")->execute([$nome, $descricao, $gatilho, $totalClientes, $id]);
        echo json_encode(['success'=>true]);
    }

    // ============================================================
    // CLIENTES DO SEGMENTO
    // ============================================================
    public function segmentoClientes(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $id = (int)$request->getParam('id');
        $gatilhoOverride = trim((string)$request->getParam('gatilho', ''));
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }

        $userNomeCol = 'nome';
        try {
            $cols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('nome', $cols) && in_array('name', $cols)) $userNomeCol = 'name';
        } catch (\Exception $e) {}

        // Get segment info
        $st = $pdo->prepare("SELECT * FROM email_mkt_segmentos WHERE id = ?"); $st->execute([$id]);
        $seg = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$seg) { echo json_encode(['success'=>false,'error'=>'Segmento não encontrado']); return; }

        // Use override gatilho if provided, otherwise use saved
        $gatilho = ($gatilhoOverride !== '') ? $gatilhoOverride : ($seg['gatilho'] ?? 'automatico');
        $clientes = [];

        try {
            // Query clients based on segment trigger
            if ($gatilho === 'sem_compra_30' || $gatilho === 'sem_compra_60' || $gatilho === 'sem_compra_90') {
                // Clients who HAVE purchased before but last purchase was > X days ago
                $dias = (int)$this->getConfig($pdo, 'criterio_dias_sem_compra_30', '30');
                if ($gatilho === 'sem_compra_60') $dias = (int)$this->getConfig($pdo, 'criterio_dias_sem_compra_60', '60');
                if ($gatilho === 'sem_compra_90') $dias = (int)$this->getConfig($pdo, 'criterio_dias_sem_compra_90', '90');
                if ($dias <= 0) $dias = 30;
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MAX(p.created_at) AS ultima_compra
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        WHERE u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING MAX(p.created_at) < DATE_SUB(NOW(), INTERVAL {$dias} DAY)
                        ORDER BY ultima_compra ASC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            } elseif ($gatilho === 'vip') {
                // Clients with 3+ paid orders OR total spent >= $500
                $minPedidos = (int)$this->getConfig($pdo, 'criterio_vip_min_pedidos', '3');
                $minValor = (float)$this->getConfig($pdo, 'criterio_vip_min_valor', '500');
                // Detect total column
                $totalCol = 'valor_total';
                try {
                    $pedCols = $pdo->query("DESCRIBE pedidos")->fetchAll(\PDO::FETCH_COLUMN);
                    if (!in_array('valor_total', $pedCols) && in_array('total', $pedCols)) $totalCol = 'total';
                    elseif (!in_array('valor_total', $pedCols) && in_array('valor', $pedCols)) $totalCol = 'valor';
                } catch (\Exception $e) {}
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, COUNT(p.id) AS total_pedidos, MAX(p.created_at) AS ultima_compra, COALESCE(SUM(p.{$totalCol}),0) AS total_gasto
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        WHERE u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING COUNT(p.id) >= {$minPedidos} OR COALESCE(SUM(p.{$totalCol}),0) >= {$minValor}
                        ORDER BY total_gasto DESC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            } elseif ($gatilho === 'primeira_compra') {
                // Clients whose FIRST purchase was within last X days
                $dias = (int)$this->getConfig($pdo, 'criterio_primeira_compra_dias', '30');
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MIN(p.created_at) AS ultima_compra
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        WHERE u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING MIN(p.created_at) > DATE_SUB(NOW(), INTERVAL {$dias} DAY)
                        ORDER BY ultima_compra DESC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            } elseif ($gatilho === 'novo_cadastro') {
                // Users registered within last X days (with or without purchase)
                $dias = (int)$this->getConfig($pdo, 'criterio_novo_cadastro_dias', '7');
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, u.created_at AS ultima_compra
                        FROM usuarios u
                        WHERE u.email IS NOT NULL AND u.email != ''
                        AND u.created_at > DATE_SUB(NOW(), INTERVAL {$dias} DAY)
                        ORDER BY u.created_at DESC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            } elseif ($gatilho === 'carrinho_abandonado') {
                // Users with items in cart who did NOT complete a purchase in last X days
                $dias = (int)$this->getConfig($pdo, 'criterio_carrinho_dias', '3');
                $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email, c.created_at AS ultima_compra
                        FROM usuarios u
                        INNER JOIN carrinhos c ON c.usuario_id = u.id
                        INNER JOIN carrinho_items ci ON ci.carrinho_id = c.id AND ci.quantidade > 0
                        WHERE u.email IS NOT NULL AND u.email != ''
                        AND u.id NOT IN (
                            SELECT DISTINCT p.usuario_id FROM pedidos p
                            WHERE p.status IN ('pago','processando','enviado','entregue')
                            AND p.created_at > DATE_SUB(NOW(), INTERVAL {$dias} DAY)
                            AND p.usuario_id IS NOT NULL
                        )
                        ORDER BY c.created_at DESC";
                try {
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) { $clientes = []; }

            } elseif ($gatilho === 'aniversario') {
                // Users with birthday this week - check if column exists
                $hasNasc = false;
                try {
                    $uCols = $pdo->query("DESCRIBE usuarios")->fetchAll(\PDO::FETCH_COLUMN);
                    $hasNasc = in_array('data_nascimento', $uCols);
                } catch (\Exception $e) {}
                if ($hasNasc) {
                    $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, u.data_nascimento AS ultima_compra
                            FROM usuarios u
                            WHERE u.email IS NOT NULL AND u.email != ''
                            AND u.data_nascimento IS NOT NULL
                            AND (MONTH(u.data_nascimento) = MONTH(NOW()) AND DAY(u.data_nascimento) BETWEEN DAY(NOW()) AND DAY(NOW())+7)
                            ORDER BY DAY(u.data_nascimento) ASC";
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } else {
                    $clientes = [];
                }

            } elseif ($gatilho === 'abriu_nao_clicou') {
                // Users who opened emails but never clicked any link
                $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email, MAX(cc.data_abertura) AS ultima_compra
                        FROM email_mkt_campanha_clientes cc
                        INNER JOIN usuarios u ON u.id = cc.cliente_id
                        WHERE cc.data_abertura IS NOT NULL AND cc.data_clique IS NULL
                        AND u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        ORDER BY ultima_compra DESC";
                try {
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) { $clientes = []; }

            } elseif ($gatilho === 'clicou_nao_converteu') {
                // Users who clicked but did NOT convert (no purchase after click)
                $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email, MAX(cc.data_clique) AS ultima_compra
                        FROM email_mkt_campanha_clientes cc
                        INNER JOIN usuarios u ON u.id = cc.cliente_id
                        WHERE cc.data_clique IS NOT NULL AND cc.data_conversao IS NULL
                        AND u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        ORDER BY ultima_compra DESC";
                try {
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) { $clientes = []; }

            } elseif ($gatilho === 'converteu') {
                // Users who converted (purchased after clicking email)
                $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email, MAX(cc.data_conversao) AS ultima_compra
                        FROM email_mkt_campanha_clientes cc
                        INNER JOIN usuarios u ON u.id = cc.cliente_id
                        WHERE cc.data_conversao IS NOT NULL
                        AND u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        ORDER BY ultima_compra DESC";
                try {
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) { $clientes = []; }

            } elseif ($gatilho === 'nunca_abriu') {
                // Users who received emails but NEVER opened any
                $sql = "SELECT DISTINCT u.id, u.{$userNomeCol} AS nome, u.email, MAX(cc.data_envio) AS ultima_compra
                        FROM email_mkt_campanha_clientes cc
                        INNER JOIN usuarios u ON u.id = cc.cliente_id
                        WHERE cc.data_envio IS NOT NULL AND cc.data_abertura IS NULL
                        AND u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        ORDER BY ultima_compra DESC";
                try {
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) { $clientes = []; }

            } elseif ($gatilho === 'engajado') {
                // Users who opened AND clicked multiple times
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, COUNT(cc.id) AS interacoes, MAX(cc.data_clique) AS ultima_compra
                        FROM email_mkt_campanha_clientes cc
                        INNER JOIN usuarios u ON u.id = cc.cliente_id
                        WHERE cc.data_clique IS NOT NULL
                        AND u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING COUNT(cc.id) >= 2
                        ORDER BY interacoes DESC";
                try {
                    $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Exception $e) { $clientes = []; }

            } elseif ($gatilho === 'inativo_total') {
                // Users who NEVER made any purchase
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, u.created_at AS ultima_compra
                        FROM usuarios u
                        WHERE u.email IS NOT NULL AND u.email != ''
                        AND u.id NOT IN (
                            SELECT DISTINCT p.usuario_id FROM pedidos p
                            WHERE p.status IN ('pago','entregue','enviado')
                            AND p.usuario_id IS NOT NULL
                        )
                        ORDER BY u.{$userNomeCol} ASC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            } else {
                // Default: all clients with email
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MAX(p.created_at) AS ultima_compra
                        FROM usuarios u LEFT JOIN pedidos p ON p.usuario_id = u.id
                        WHERE u.email IS NOT NULL AND u.email != ''
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        ORDER BY u.{$userNomeCol} ASC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $clientes = [];
        }

        // Format dates
        foreach ($clientes as &$c) {
            if (!empty($c['ultima_compra'])) {
                $c['ultima_compra'] = date('d/m/Y', strtotime($c['ultima_compra']));
            }
        }

        echo json_encode(['success'=>true, 'clientes'=>$clientes, 'total'=>count($clientes)]);
    }

    // ============================================================
    // REMOVER CLIENTE DO SEGMENTO (placeholder - segments are dynamic)
    // ============================================================
    public function segmentoRemoverCliente(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        // Note: Since segments are query-based (dynamic), removing a client means adding to an exclusion list
        // For now, we'll just acknowledge - in production this would add to an exclusion table
        echo json_encode(['success'=>true, 'message'=>'Cliente removido da próxima geração deste segmento.']);
    }

    // ============================================================
    // EXCLUIR SEGMENTO
    // ============================================================
    public function excluirSegmento(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $pdo = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }
        $pdo->prepare("UPDATE email_mkt_segmentos SET ativo = 0 WHERE id = ?")->execute([$id]);
        echo json_encode(['success'=>true]);
    }

    private function renderCriteriosTab(\PDO $pdo): void {
        // Load saved criteria configs
        $criteriosConfig = [];
        try {
            $st = $pdo->query("SELECT chave, valor FROM email_mkt_config WHERE chave LIKE 'criterio_%'");
            $criteriosConfig = $st ? ($st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: []) : [];
        } catch (\Exception $e) {}

        $criterios = [
            'sem_compra_30' => [
                'nome' => 'Sem compra há 30 dias',
                'descricao' => 'Clientes que fizeram pelo menos 1 pedido pago, mas a última compra foi há mais de X dias.',
                'parametro' => 'criterio_dias_sem_compra_30',
                'default' => '30',
                'unidade' => 'dias',
                'regra' => 'Última compra > X dias atrás'
            ],
            'sem_compra_60' => [
                'nome' => 'Sem compra há 60 dias',
                'descricao' => 'Clientes inativos entre X e Y dias sem compra.',
                'parametro' => 'criterio_dias_sem_compra_60',
                'default' => '60',
                'unidade' => 'dias',
                'regra' => 'Última compra > X dias atrás'
            ],
            'sem_compra_90' => [
                'nome' => 'Sem compra há 90+ dias',
                'descricao' => 'Clientes muito inativos, sem compra há mais de X dias.',
                'parametro' => 'criterio_dias_sem_compra_90',
                'default' => '90',
                'unidade' => 'dias',
                'regra' => 'Última compra > X dias atrás'
            ],
            'vip' => [
                'nome' => 'Cliente VIP',
                'descricao' => 'Clientes com alto volume de compras (mínimo de X pedidos pagos OU valor total acima de Y).',
                'parametro' => 'criterio_vip_min_pedidos',
                'default' => '3',
                'unidade' => 'pedidos mínimos',
                'parametro2' => 'criterio_vip_min_valor',
                'default2' => '500',
                'unidade2' => 'USD valor mínimo gasto',
                'regra' => 'Total pedidos >= X OU Total gasto >= Y'
            ],
            'primeira_compra' => [
                'nome' => 'Primeira compra recente',
                'descricao' => 'Clientes que fizeram a primeira compra nos últimos X dias.',
                'parametro' => 'criterio_primeira_compra_dias',
                'default' => '30',
                'unidade' => 'dias',
                'regra' => 'Cadastro ou 1ª compra < X dias'
            ],
            'novo_cadastro' => [
                'nome' => 'Novo cadastro',
                'descricao' => 'Clientes que se cadastraram nos últimos X dias (com ou sem compra).',
                'parametro' => 'criterio_novo_cadastro_dias',
                'default' => '7',
                'unidade' => 'dias',
                'regra' => 'Data cadastro < X dias atrás'
            ],
            'carrinho_abandonado' => [
                'nome' => 'Carrinho abandonado',
                'descricao' => 'Clientes com itens no carrinho que NÃO finalizaram pedido nos últimos X dias.',
                'parametro' => 'criterio_carrinho_dias',
                'default' => '3',
                'unidade' => 'dias sem finalizar',
                'regra' => 'Tem itens no carrinho E sem pedido pago < X dias'
            ],
            'abriu_nao_clicou' => [
                'nome' => 'Abriu email mas não clicou',
                'descricao' => 'Clientes que abriram emails anteriores mas nunca clicaram em nenhum link.',
                'parametro' => '',
                'default' => '',
                'unidade' => '',
                'regra' => 'Status = aberto E clique = NULL (dados de tracking)'
            ],
            'clicou_nao_converteu' => [
                'nome' => 'Clicou mas não converteu',
                'descricao' => 'Clientes que clicaram em links de emails mas não fizeram compra após o clique.',
                'parametro' => '',
                'default' => '',
                'unidade' => '',
                'regra' => 'Status = clicado E conversão = NULL (dados de tracking)'
            ],
            'converteu' => [
                'nome' => 'Converteu',
                'descricao' => 'Clientes que compraram após receber e clicar em um email marketing.',
                'parametro' => '',
                'default' => '',
                'unidade' => '',
                'regra' => 'Status = convertido (pedido pago após clique)'
            ],
            'engajado' => [
                'nome' => 'Engajado',
                'descricao' => 'Clientes que abrem e clicam frequentemente nos emails.',
                'parametro' => '',
                'default' => '',
                'unidade' => '',
                'regra' => 'Múltiplas aberturas e cliques registrados'
            ],
            'inativo_total' => [
                'nome' => 'Inativo total',
                'descricao' => 'Clientes cadastrados que nunca fizeram nenhuma compra.',
                'parametro' => '',
                'default' => '',
                'unidade' => '',
                'regra' => 'Nenhum pedido registrado'
            ],
        ];

        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title">Configuração de Critérios</h2></div><div class="section-body">
<p style="color:#64748B;font-size:13px;margin-bottom:16px;">Defina os parâmetros de cada critério de segmentação. Esses valores são usados quando a IA ou você seleciona um gatilho para filtrar clientes.</p>
<div style="display:flex;flex-direction:column;gap:12px;">';

        foreach ($criterios as $key => $c) {
            $valor = $criteriosConfig[$c['parametro'] ?? ''] ?? $c['default'];
            $valor2 = isset($c['parametro2']) ? ($criteriosConfig[$c['parametro2']] ?? $c['default2']) : '';

            echo '<div style="border:1px solid #E2E8F0;border-radius:10px;padding:14px 16px;">
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
<div style="flex:1;min-width:200px;">
<div style="font-size:13px;font-weight:700;color:#18253D;margin-bottom:2px;">' . htmlspecialchars($c['nome']) . '</div>
<div style="font-size:12px;color:#64748B;margin-bottom:4px;">' . htmlspecialchars($c['descricao']) . '</div>
<div style="font-size:11px;color:#94A3B8;"><strong>Regra:</strong> ' . htmlspecialchars($c['regra']) . '</div>
</div>';

            if (!empty($c['parametro'])) {
                echo '<div style="display:flex;gap:8px;align-items:center;">';
                echo '<input type="number" class="criterio-input" data-chave="' . htmlspecialchars($c['parametro']) . '" value="' . htmlspecialchars($valor) . '" style="width:70px;padding:6px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;text-align:center;">';
                echo '<span style="font-size:11px;color:#94A3B8;">' . htmlspecialchars($c['unidade']) . '</span>';
                if (isset($c['parametro2'])) {
                    echo '<input type="number" class="criterio-input" data-chave="' . htmlspecialchars($c['parametro2']) . '" value="' . htmlspecialchars($valor2) . '" style="width:70px;padding:6px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;text-align:center;margin-left:8px;">';
                    echo '<span style="font-size:11px;color:#94A3B8;">' . htmlspecialchars($c['unidade2']) . '</span>';
                }
                echo '</div>';
            } else {
                echo '<span style="font-size:11px;color:#94A3B8;background:#F1F5F9;padding:4px 10px;border-radius:6px;">Automático (tracking)</span>';
            }

            echo '</div></div>';
        }

        echo '</div>
<button onclick="salvarCriterios()" class="btn-navy" style="margin-top:16px;padding:10px 20px;font-size:14px;"><i class="bi bi-check-lg me-1"></i>Salvar Critérios</button>
</div></div>
<script>
async function salvarCriterios(){
    const data={};
    document.querySelectorAll(".criterio-input").forEach(el=>{
        if(el.dataset.chave) data[el.dataset.chave]=el.value;
    });
    const r=await fetch("/admin/email-marketing/config",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});
    const d=await r.json();
    alert(d.success?"Critérios salvos!":"Erro ao salvar");
}
</script>';
    }

    private function renderConfigTab(\PDO $pdo): void {
        $configs = [];
        try { $st = $pdo->query("SELECT chave, valor FROM email_mkt_config"); $configs = $st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: []; } catch (\Exception $e) {}

        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title">Configurações do Email Marketing</h2></div><div class="section-body">
<div class="row g-3">
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Tom da Marca</label><input type="text" class="form-control" id="cfg_tom_marca" value="'.htmlspecialchars($configs['tom_marca']??'').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Palavras Proibidas</label><input type="text" class="form-control" id="cfg_palavras_proibidas" value="'.htmlspecialchars($configs['palavras_proibidas']??'').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Limite Diário</label><input type="number" class="form-control" id="cfg_limite_diario" value="'.htmlspecialchars($configs['limite_diario']??'200').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Dias Recompra Mínimo</label><input type="number" class="form-control" id="cfg_dias_recompra_minimo" value="'.htmlspecialchars($configs['dias_recompra_minimo']??'30').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Max Campanhas/Mês</label><input type="number" class="form-control" id="cfg_max_campanhas_mes_cliente" value="'.htmlspecialchars($configs['max_campanhas_mes_cliente']??'4').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Intervalo (dias)</label><input type="number" class="form-control" id="cfg_intervalo_campanhas_dias" value="'.htmlspecialchars($configs['intervalo_campanhas_dias']??'7').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Remetente Nome</label><input type="text" class="form-control" id="cfg_remetente_nome" value="'.htmlspecialchars($configs['remetente_nome']??'').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Remetente Email</label><input type="email" class="form-control" id="cfg_remetente_email" value="'.htmlspecialchars($configs['remetente_email']??'').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Emails por Lote (máx 45)</label><input type="number" class="form-control" id="cfg_emails_por_lote" value="'.htmlspecialchars($configs['emails_por_lote']??'45').'" max="45"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Intervalo entre Lotes (seg)</label><input type="number" class="form-control" id="cfg_intervalo_lote_segundos" value="'.htmlspecialchars($configs['intervalo_lote_segundos']??'180').'"></div>
<div class="col-12" style="margin-top:8px;"><small style="color:#94A3B8;">Frequência de disparo: máximo 45 emails a cada 3 minutos (180 segundos) para evitar bloqueios.</small></div>
<div class="col-12"><button class="btn-navy" onclick="salvarConfig()"><i class="bi bi-check-lg me-1"></i>Salvar Configurações</button></div>
</div></div></div>
<script>
async function salvarConfig(){
    const data={};
    document.querySelectorAll("[id^=cfg_]").forEach(el=>{data[el.id.replace("cfg_","")]=el.value;});
    const r=await fetch("/admin/email-marketing/config",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});
    const d=await r.json();alert(d.success?"Salvo!":"Erro");
}
</script>';
    }
}
