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
        $validTabs = ['dashboard','campanhas','pendentes','aprovadas','agendadas','enviados','historico','segmentos','gatilhos','templates','config','logs','metricas'];
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
            $where = '1=1';
            if ($tab === 'pendentes') $where = "status='pendente_revisao'";
            elseif ($tab === 'aprovadas') $where = "status IN ('aprovada','agendada')";
            elseif ($tab === 'agendadas') $where = "status='agendada'";
            elseif ($tab === 'enviados') $where = "status IN ('disparando','finalizada')";
            elseif ($tab === 'historico') $where = "status IN ('finalizada','cancelada','rejeitada')";
            elseif ($tab === 'campanhas') $where = "1=1";
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
                        LEFT JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING ultima_compra IS NOT NULL AND ultima_compra < DATE_SUB(NOW(), INTERVAL {$diasRecompra} DAY)
                        ORDER BY ultima_compra ASC LIMIT 5000";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes sem compra há mais de {$diasRecompra} dias";

            } elseif ($tipo === 'aniversario') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, u.data_nascimento
                        FROM usuarios u
                        WHERE u.data_nascimento IS NOT NULL
                        AND (MONTH(u.data_nascimento) = MONTH(NOW()) AND DAY(u.data_nascimento) BETWEEN DAY(NOW()) AND DAY(NOW())+7)
                        LIMIT 5000";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Aniversariantes da semana";

            } elseif ($tipo === 'pos_venda') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, MAX(p.created_at) AS ultima_compra
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        LIMIT 5000";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes com compra nos últimos 7 dias";

            } elseif ($tipo === 'vip') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email, COUNT(p.id) AS total_pedidos, SUM(COALESCE(p.valor_total,p.total,0)) AS total_gasto
                        FROM usuarios u
                        INNER JOIN pedidos p ON p.usuario_id = u.id AND p.status IN ('pago','entregue','enviado')
                        GROUP BY u.id, u.{$userNomeCol}, u.email
                        HAVING total_pedidos >= 3 OR total_gasto >= 500
                        ORDER BY total_gasto DESC LIMIT 5000";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes VIP (3+ pedidos ou $500+ gastos)";

            } elseif ($tipo === 'institucional') {
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email FROM usuarios u WHERE u.email IS NOT NULL AND u.email != '' ORDER BY u.{$userNomeCol} ASC";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Todos os clientes (institucional)";

            } else {
                // categoria, carrinho_abandonado, etc - get all active clients
                $sql = "SELECT u.id, u.{$userNomeCol} AS nome, u.email FROM usuarios u WHERE u.email IS NOT NULL AND u.email != '' LIMIT 5000";
                $clientes = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $descricaoSegmento = "Clientes ativos - campanha {$tipo}";
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
            'recompra'=>'Recompra','carrinho_abandonado'=>'Carrinho Abandonado','individual'=>'Individual'
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
        $st = $pdo->prepare("INSERT INTO email_mkt_campanhas (nome, tipo, gatilho, status, assunto, pre_header, variaveis_ia, total_clientes, observacoes_ia) VALUES (?, ?, ?, 'pendente_revisao', ?, ?, ?, ?, ?)");
        $st->execute([
            "{$tipoLabel} - " . date('d/m'),
            $tipo,
            $gatilho,
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
        $html = $this->buildEmailHtml($content, $nomeLoja);
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
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); return; }

        // Mark as dispatching
        $pdo->prepare("UPDATE email_mkt_campanhas SET status='disparando', data_inicio_disparo=NOW() WHERE id=? AND status IN ('aprovada','agendada','pendente_revisao')")->execute([$id]);

        // Get campaign and clients
        $st = $pdo->prepare("SELECT * FROM email_mkt_campanhas WHERE id=?"); $st->execute([$id]);
        $campanha = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$campanha) { echo json_encode(['success'=>false,'error'=>'Campanha não encontrada']); return; }

        $stCl = $pdo->prepare("SELECT * FROM email_mkt_campanha_clientes WHERE campanha_id=? AND status='aguardando'"); $stCl->execute([$id]);
        $clientes = $stCl->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // TODO: Integrate with actual email sending service (SMTP, SES, SendGrid, etc.)
        // For now, mark all as "enviado" and log
        $enviados = 0;
        $stUpdate = $pdo->prepare("UPDATE email_mkt_campanha_clientes SET status='enviado', data_envio=NOW() WHERE id=?");
        $stLog = $pdo->prepare("INSERT INTO email_mkt_logs (campanha_id, cliente_id, email, evento, detalhes) VALUES (?, ?, ?, 'enviado', 'Marcado para envio')");
        foreach ($clientes as $cl) {
            $stUpdate->execute([(int)$cl['id']]);
            $stLog->execute([$id, (int)$cl['cliente_id'], $cl['email']]);
            $enviados++;
        }

        $pdo->prepare("UPDATE email_mkt_campanhas SET total_enviado=?, status='finalizada', data_fim_disparo=NOW() WHERE id=?")->execute([$enviados, $id]);

        echo json_encode(['success'=>true, 'message'=>"Disparo concluído! {$enviados} emails processados.", 'enviados'=>$enviados]);
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
        $st2 = $pdo->prepare("SELECT * FROM email_mkt_campanha_clientes WHERE campanha_id = ? ORDER BY id LIMIT 5000"); $st2->execute([$id]);
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
    // RENDER PAGE
    // ============================================================
    private function renderPage(string $tab, array $stats, array $campanhas, \PDO $pdo): void {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Email Marketing - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/dashboard-redesign.css" rel="stylesheet">';
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
        echo '<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
<div class="kpi-card"><div><div class="kpi-label">Campanhas</div><div class="kpi-value">'.$stats['total_campanhas'].'</div></div><div class="kpi-icon"><i class="bi bi-envelope-fill"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Pendentes</div><div class="kpi-value">'.$stats['pendentes'].'</div></div><div class="kpi-icon"><i class="bi bi-clock-fill"></i></div></div>
<div class="kpi-card"><div><div class="kpi-label">Enviados</div><div class="kpi-value">'.$stats['enviadas'].'</div></div><div class="kpi-icon"><i class="bi bi-send-fill"></i></div></div>
<div class="kpi-card is-featured"><div><div class="kpi-label">Convertidos</div><div class="kpi-value">'.$stats['convertidas'].'</div></div><div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div></div>
</div>';

        // Tabs
        $tabs = ['dashboard'=>'Dashboard','campanhas'=>'Todas','pendentes'=>'Pendentes','aprovadas'=>'Aprovadas','agendadas'=>'Agendadas','enviados'=>'Enviados','historico'=>'Histórico','segmentos'=>'Segmentos','config'=>'Configurações'];
        echo '<nav class="mkt-tabs">';
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
    
    if(tipo === "individual" && !individualId){
        alert("Selecione um usuário para campanha individual.");
        return;
    }
    
    document.getElementById("campProgress").style.display = "block";
    
    const body = JSON.stringify({tipo, categoria, usuario_id: individualId || null, apenas_buscar: true});
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
    
    document.getElementById("campProgress").style.display = "block";
    
    const body = JSON.stringify({tipo, instrucoes, categoria, cliente_ids: ids});
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
    if(!currentCampId||!confirm("Disparar campanha agora para todos os clientes vinculados?"))return;
    const r=await fetch("/admin/email-marketing/disparar",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({id:currentCampId})});
    const d=await r.json();
    if(d.success){bootstrap.Modal.getInstance(document.getElementById("modalCampanha")).hide();alert(d.message||"Disparo iniciado!");location.reload();}
    else alert(d.error||"Erro ao disparar");
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
</script>';
        echo '</div></main></div></div></body></html>';
    }

    private function renderCampanhasTable(array $campanhas): void {
        if (empty($campanhas)) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;"><i class="bi bi-envelope" style="font-size:40px;color:#94A3B8;"></i><p style="color:#94A3B8;margin-top:12px;">Nenhuma campanha encontrada. Clique em "Gerar Campanhas com IA" para começar.</p></div></div>';
            return;
        }
        $statusMap = ['rascunho_ia'=>'st-rascunho','pendente_revisao'=>'st-pendente','aprovada'=>'st-aprovada','agendada'=>'st-agendada','disparando'=>'st-disparando','finalizada'=>'st-finalizada','rejeitada'=>'st-rejeitada','cancelada'=>'st-cancelada'];
        $statusLabels = ['rascunho_ia'=>'Rascunho IA','pendente_revisao'=>'Pendente','aprovada'=>'Aprovada','agendada'=>'Agendada','disparando'=>'Disparando','finalizada'=>'Finalizada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada'];

        echo '<div class="section-card"><div style="overflow-x:auto;"><table class="camp-table"><thead><tr><th>Campanha</th><th>Tipo</th><th>Status</th><th>Clientes</th><th>Enviados</th><th>Abertos</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
        foreach ($campanhas as $c) {
            $st = $c['status'] ?? 'rascunho_ia';
            $badge = '<span class="status-pill '.($statusMap[$st]??'st-rascunho').'">'.($statusLabels[$st]??$st).'</span>';
            $data = !empty($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '-';
            echo '<tr><td><strong>'.htmlspecialchars($c['nome']??'').'</strong><br><small style="color:#94A3B8;">'.htmlspecialchars($c['assunto']??'').'</small></td>';
            echo '<td>'.ucfirst(str_replace('_',' ',$c['tipo']??'')).'</td><td>'.$badge.'</td>';
            echo '<td>'.(int)$c['total_clientes'].'</td><td>'.(int)$c['total_enviado'].'</td><td>'.(int)$c['total_aberto'].'</td>';
            echo '<td>'.$data.'</td><td><button class="btn-ghost" style="padding:4px 10px;font-size:12px;" onclick="verCampanha('.(int)$c['id'].')"><i class="bi bi-eye me-1"></i>Ver</button></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private function renderSegmentosTab(\PDO $pdo): void {
        // Get existing segments
        $segmentos = [];
        try { $segmentos = $pdo->query("SELECT * FROM email_mkt_segmentos ORDER BY updated_at DESC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}

        echo '<div class="d-flex justify-content-between align-items-center mb-3">
<h6 style="color:var(--navy);font-weight:700;margin:0;">Segmentações Inteligentes</h6>
<button class="btn-navy" style="padding:8px 14px;font-size:13px;" onclick="gerarSegmentosIA()"><i class="bi bi-stars me-1"></i>Gerar Segmentos com IA</button>
</div>';

        echo '<div id="segProgress" style="display:none;text-align:center;padding:20px;"><i class="bi bi-stars" style="font-size:24px;color:var(--navy);animation:spin 2s linear infinite;"></i><p style="color:#64748B;font-size:13px;margin-top:8px;">Analisando comportamento de clientes...</p></div>';

        if (empty($segmentos)) {
            echo '<div class="section-card"><div class="section-body" style="text-align:center;padding:40px;"><i class="bi bi-diagram-3" style="font-size:40px;color:#94A3B8;"></i><p style="color:#94A3B8;margin-top:12px;">Nenhum segmento criado. Clique em "Gerar Segmentos com IA" para analisar seus clientes.</p></div></div>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">';
            foreach ($segmentos as $seg) {
                echo '<div class="section-card" style="margin-bottom:0;"><div style="padding:16px;">
<div style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:4px;">'.htmlspecialchars($seg['nome']).'</div>
<div style="font-size:12px;color:#64748B;margin-bottom:8px;">'.htmlspecialchars($seg['descricao'] ?? '').'</div>
<div style="display:flex;gap:12px;font-size:12px;">
<span style="color:#18253D;font-weight:600;"><i class="bi bi-people me-1"></i>'.(int)$seg['total_clientes'].' clientes</span>
<span style="color:#94A3B8;">'.($seg['gatilho'] ?? 'automático').'</span>
</div></div></div>';
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
</script>';
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

        // Save segments and generate campaigns for each
        $criados = 0;
        $campanhasCriadas = 0;
        $stInsert = $pdo->prepare("INSERT INTO email_mkt_segmentos (nome, descricao, tipo, gatilho, criterios, total_clientes) VALUES (?, ?, 'automatico', ?, ?, ?)");
        
        $nomeLoja = 'Braziliana';
        try { $st2 = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'loja_nome' OR (categoria='loja' AND chave='nome') LIMIT 1"); $st2->execute(); $v = $st2->fetchColumn(); if ($v) $nomeLoja = $v; } catch (\Exception $e) {}
        $tom = $this->getConfig($pdo, 'tom_marca', 'humanizado, elegante, conversacional');
        $palavrasProibidas = $this->getConfig($pdo, 'palavras_proibidas', '');

        foreach ($segmentos as $seg) {
            if (empty($seg['nome'])) continue;
            $stInsert->execute([
                $seg['nome'],
                $seg['descricao'] ?? '',
                $seg['gatilho'] ?? 'automatico',
                json_encode($seg['criterio'] ?? $seg, JSON_UNESCAPED_UNICODE),
                (int)($seg['total_estimado'] ?? 0)
            ]);
            $segmentoId = (int)$pdo->lastInsertId();
            $criados++;

            // Generate a campaign for this segment (pendente_revisao)
            $segNome = $seg['nome'];
            $segDesc = $seg['descricao'] ?? '';
            
            $campSystemPrompt = "Você é um especialista em email marketing. Gere o conteúdo de uma campanha para o segmento: '{$segNome}' ({$segDesc}).
Tom: {$tom}. Palavras proibidas: {$palavrasProibidas}.
NÃO crie descontos, cupons ou promoções. NÃO use urgência falsa.
Retorne em JSON: {\"assunto\": \"...\", \"pre_header\": \"...\", \"tag_campanha\": \"...\", \"titulo_email\": \"...\", \"subtitulo_email\": \"...\", \"paragrafo_1\": \"...\", \"paragrafo_2\": \"...\", \"texto_destaque\": \"...\", \"paragrafo_fechamento\": \"...\", \"texto_cta\": \"...\", \"texto_sub_cta\": \"...\"}
Assunto máx 50 chars. Corpo 120-220 palavras.";

            $campResult = $this->callAI($pdo, $campSystemPrompt, "Loja: {$nomeLoja}\nSegmento: {$segNome}\nDescrição: {$segDesc}\nClientes estimados: " . (int)($seg['total_estimado'] ?? 0));
            
            if (!isset($campResult['error'])) {
                $campContent = json_decode($campResult['text'], true);
                if (!$campContent) { preg_match('/\{.*\}/s', $campResult['text'], $m2); if (!empty($m2[0])) $campContent = json_decode($m2[0], true); }
                
                if ($campContent && isset($campContent['assunto'])) {
                    $stCamp = $pdo->prepare("INSERT INTO email_mkt_campanhas (nome, tipo, gatilho, segmento_id, status, assunto, pre_header, variaveis_ia, total_clientes, observacoes_ia) VALUES (?, 'reativacao', ?, ?, 'pendente_revisao', ?, ?, ?, ?, ?)");
                    $stCamp->execute([
                        $segNome . ' - ' . date('d/m'),
                        $seg['gatilho'] ?? 'segmento_ia',
                        $segmentoId,
                        $campContent['assunto'] ?? '',
                        $campContent['pre_header'] ?? '',
                        json_encode($campContent, JSON_UNESCAPED_UNICODE),
                        (int)($seg['total_estimado'] ?? 0),
                        "Campanha gerada automaticamente para segmento: {$segNome}. {$segDesc}"
                    ]);
                    $campId = (int)$pdo->lastInsertId();

                    // Build HTML
                    $html = $this->buildEmailHtml($campContent, $nomeLoja);
                    $pdo->prepare("UPDATE email_mkt_campanhas SET html_content = ? WHERE id = ?")->execute([$html, $campId]);
                    $campanhasCriadas++;
                }
            }
        }

        echo json_encode(['success' => true, 'message' => "{$criados} segmentos criados e {$campanhasCriadas} campanhas geradas (pendentes de revisão).", 'segmentos' => $criados, 'campanhas' => $campanhasCriadas]);
    }

    private function renderConfigTab(\PDO $pdo): void {
        $configs = [];
        try { $st = $pdo->query("SELECT chave, valor FROM email_mkt_config"); $configs = $st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: []; } catch (\Exception $e) {}

        echo '<div class="section-card"><div class="section-card-header"><h2 class="section-title"><i class="bi bi-gear-fill"></i> Configurações do Email Marketing</h2></div><div class="section-body">
<div class="row g-3">
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Tom da Marca</label><input type="text" class="form-control" id="cfg_tom_marca" value="'.htmlspecialchars($configs['tom_marca']??'').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Palavras Proibidas</label><input type="text" class="form-control" id="cfg_palavras_proibidas" value="'.htmlspecialchars($configs['palavras_proibidas']??'').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Limite Diário</label><input type="number" class="form-control" id="cfg_limite_diario" value="'.htmlspecialchars($configs['limite_diario']??'200').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Dias Recompra Mínimo</label><input type="number" class="form-control" id="cfg_dias_recompra_minimo" value="'.htmlspecialchars($configs['dias_recompra_minimo']??'30').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Max Campanhas/Mês</label><input type="number" class="form-control" id="cfg_max_campanhas_mes_cliente" value="'.htmlspecialchars($configs['max_campanhas_mes_cliente']??'4').'"></div>
<div class="col-md-3"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Intervalo (dias)</label><input type="number" class="form-control" id="cfg_intervalo_campanhas_dias" value="'.htmlspecialchars($configs['intervalo_campanhas_dias']??'7').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Remetente Nome</label><input type="text" class="form-control" id="cfg_remetente_nome" value="'.htmlspecialchars($configs['remetente_nome']??'').'"></div>
<div class="col-md-6"><label style="font-size:12px;font-weight:600;color:#94A3B8;text-transform:uppercase;">Remetente Email</label><input type="email" class="form-control" id="cfg_remetente_email" value="'.htmlspecialchars($configs['remetente_email']??'').'"></div>
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
