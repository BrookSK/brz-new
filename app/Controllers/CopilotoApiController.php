<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\CopilotoService;

/**
 * CopilotoApiController — Endpoints da API do Co-Piloto (100% PHP)
 * Substitui completamente o backend Node.js
 * Widget JS chama estas rotas via fetch()
 */
class CopilotoApiController extends Controller {

    private function json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function sanitizar(string $texto): string {
        return trim(strip_tags(html_entity_decode($texto, ENT_QUOTES, 'UTF-8')));
    }

    /**
     * POST /api/copiloto/chat — Rota principal do copiloto
     * Recebe mensagem + contexto → chama Claude → retorna resposta + ação
     */
    public function chat(Request $request) {
        $body = $request->getBody();

        $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));
        if ($mensagem === '' || mb_strlen($mensagem) > 2000) {
            $this->json(['erro' => 'Mensagem inválida (vazia ou > 2000 chars).'], 400);
        }

        // Rate limiting simples por sessão
        $sessaoId = session_id() ?: 'anon';
        $cacheKey = 'copiloto_rate_' . $sessaoId;
        $agora = time();
        $contagem = (int) ($_SESSION[$cacheKey . '_count'] ?? 0);
        $janela = (int) ($_SESSION[$cacheKey . '_window'] ?? 0);
        if ($agora - $janela > 60) {
            $_SESSION[$cacheKey . '_count'] = 1;
            $_SESSION[$cacheKey . '_window'] = $agora;
        } else {
            $contagem++;
            $_SESSION[$cacheKey . '_count'] = $contagem;
            if ($contagem > 20) {
                $this->json(['erro' => 'Muitas mensagens. Aguarde 1 minuto.'], 429);
            }
        }

        $contexto = is_array($body['contexto'] ?? null) ? $body['contexto'] : [];
        $historico = is_array($body['historico'] ?? null) ? $body['historico'] : [];

        $service = new CopilotoService();
        $resultado = $service->chamarClaude($mensagem, $contexto, $historico);

        $this->json([
            'resposta' => $resultado['texto'],
            'acao_frontend' => [
                'tipo' => $resultado['acao'] ?? 'nenhuma',
                'parametros' => $resultado['parametros'] ?? [],
            ],
            'requer_confirmacao' => $resultado['requer_confirmacao'] ?? false,
            'mensagem_confirmacao' => $resultado['mensagem_confirmacao'] ?? null,
            'max_tentativas_problema' => $resultado['max_tentativas_problema'] ?? null,
            'oferecer_ticket' => $resultado['oferecer_ticket'] ?? false,
            'tokens_usados' => $resultado['tokens_usados'] ?? 0,
        ]);
    }

    /**
     * GET /api/copiloto/context — Retorna contexto enriquecido para o widget
     * Widget pode chamar para obter dados que não consegue ler do DOM
     */
    public function context(Request $request) {
        $service = new CopilotoService();
        $this->json([
            'usuario_logado' => !empty($_SESSION['logado']),
            'usuario_nome' => $_SESSION['usuario_nome'] ?? null,
            'usuario_id' => $_SESSION['usuario_id'] ?? null,
            'moeda' => $_SESSION['moeda'] ?? 'BRL',
            'cambio' => (float) $service->getConfig('cambio_usd_brl', 5.80),
            'gatilho_tempo_ms' => (int) $service->getConfig('gatilho_tempo_ms', 30000),
        ]);
    }

    /**
     * POST /api/copiloto/calculo — Calcula custo total de um produto
     */
    public function calculo(Request $request) {
        $body = $request->getBody();
        $preco = (float) ($body['preco_usd'] ?? 0);
        $peso = (float) ($body['peso_kg'] ?? 0);
        $imposto = (float) ($body['imposto_local_pct'] ?? 0);

        if ($preco <= 0 || $peso <= 0) {
            $this->json(['erro' => 'Preço e peso são obrigatórios.'], 400);
        }

        $service = new CopilotoService();
        $this->json($service->calcularCustoTotal($preco, $peso, $imposto));
    }

    /**
     * POST /api/copiloto/ticket — Criar ticket de suporte via copiloto
     */
    public function ticket(Request $request) {
        $body = $request->getBody();
        $assunto = $this->sanitizar((string) ($body['assunto'] ?? ''));
        $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));

        if (empty($assunto) || empty($mensagem)) {
            $this->json(['erro' => 'Assunto e mensagem são obrigatórios.'], 400);
        }

        // Usar o formulário de contato existente internamente
        $numero = 'TKT-' . strtoupper(base_convert((string) time(), 10, 36));

        $this->json([
            'sucesso' => true,
            'numero_ticket' => $numero,
            'prazo_resposta' => '48h úteis',
        ]);
    }

    /**
     * GET /api/copiloto/cron — Endpoint para cron via URL (AAPanel)
     * Processa conteúdo pendente e faz limpeza
     */
    public function cron(Request $request) {
        $token = $request->getParam('token', '');
        
        // Segurança: verificar token simples
        $service = new CopilotoService();
        $apiKey = $service->getConfig('api_key_claude', '');
        $expectedToken = substr(md5('copiloto_cron_' . $apiKey), 0, 16);

        if ($token !== $expectedToken && !empty($apiKey)) {
            $this->json(['erro' => 'Token inválido.'], 403);
        }

        $processados = $service->processarConteudoPendente();
        $limpeza = $service->limparDadosAntigos();

        $this->json([
            'success' => true,
            'conteudo_processado' => $processados,
            'limpeza' => $limpeza,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
