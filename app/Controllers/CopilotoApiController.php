<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\CopilotoService;

/**
 * CopilotoApiController — Endpoints da API do Co-Piloto (100% PHP)
 * Widget JS chama estas rotas via fetch() no mesmo domínio
 */
class CopilotoApiController extends Controller {

    private function responderJson(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function sanitizar(string $texto): string {
        return trim(strip_tags(html_entity_decode($texto, ENT_QUOTES, 'UTF-8')));
    }

    /** POST /api/copiloto/chat */
    public function chat(Request $request) {
        try {
            $body = $request->getBody();
            $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));
            if ($mensagem === '' || mb_strlen($mensagem) > 2000) {
                $this->responderJson(['resposta' => 'Mensagem vazia ou muito longa.', 'acao_frontend' => ['tipo' => 'nenhuma', 'parametros' => []]], 400);
            }

            if (session_status() === PHP_SESSION_NONE) @session_start();
            $cacheKey = 'cop_rate_' . (session_id() ?: 'x');
            $agora = time();
            if ($agora - (int)($_SESSION[$cacheKey.'_w'] ?? 0) > 60) {
                $_SESSION[$cacheKey.'_c'] = 1;
                $_SESSION[$cacheKey.'_w'] = $agora;
            } else {
                $_SESSION[$cacheKey.'_c'] = ($_SESSION[$cacheKey.'_c'] ?? 0) + 1;
                if ($_SESSION[$cacheKey.'_c'] > 20) {
                    $this->responderJson(['resposta' => 'Muitas mensagens. Aguarde 1 minuto.', 'acao_frontend' => ['tipo' => 'nenhuma', 'parametros' => []]], 429);
                }
            }

            $contexto = is_array($body['contexto'] ?? null) ? $body['contexto'] : [];
            $historico = is_array($body['historico'] ?? null) ? $body['historico'] : [];

            $service = new CopilotoService();
            $resultado = $service->chamarClaude($mensagem, $contexto, $historico);

            $this->responderJson([
                'resposta' => $resultado['texto'],
                'acao_frontend' => ['tipo' => $resultado['acao'] ?? 'nenhuma', 'parametros' => $resultado['parametros'] ?? []],
                'requer_confirmacao' => $resultado['requer_confirmacao'] ?? false,
                'mensagem_confirmacao' => $resultado['mensagem_confirmacao'] ?? null,
                'max_tentativas_problema' => $resultado['max_tentativas_problema'] ?? null,
                'oferecer_ticket' => $resultado['oferecer_ticket'] ?? false,
                'tokens_usados' => $resultado['tokens_usados'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            error_log('[CoPiloto] Erro: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            $this->responderJson(['resposta' => 'Desculpa, tive um problema técnico. Tenta de novo?', 'acao_frontend' => ['tipo' => 'nenhuma', 'parametros' => []]], 500);
        }
    }

    /** GET /api/copiloto/context */
    public function context(Request $request) {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $service = new CopilotoService();
        $this->responderJson([
            'usuario_logado' => !empty($_SESSION['logado']),
            'usuario_nome' => $_SESSION['usuario_nome'] ?? null,
            'moeda' => $_SESSION['moeda'] ?? 'BRL',
            'cambio' => (float) $service->getConfig('cambio_usd_brl', 5.80),
            'gatilho_tempo_ms' => (int) $service->getConfig('gatilho_tempo_ms', 30000),
        ]);
    }

    /** POST /api/copiloto/calculo */
    public function calculo(Request $request) {
        $body = $request->getBody();
        $preco = (float) ($body['preco_usd'] ?? 0);
        $peso = (float) ($body['peso_kg'] ?? 0);
        $imposto = (float) ($body['imposto_local_pct'] ?? 0);
        if ($preco <= 0 || $peso <= 0) {
            $this->responderJson(['erro' => 'Preço e peso são obrigatórios.'], 400);
        }
        $service = new CopilotoService();
        $this->responderJson($service->calcularCustoTotal($preco, $peso, $imposto));
    }

    /** POST /api/copiloto/ticket */
    public function ticket(Request $request) {
        $body = $request->getBody();
        $assunto = $this->sanitizar((string) ($body['assunto'] ?? ''));
        $mensagem = $this->sanitizar((string) ($body['mensagem'] ?? ''));
        if (empty($assunto) || empty($mensagem)) {
            $this->responderJson(['erro' => 'Assunto e mensagem são obrigatórios.'], 400);
        }
        $numero = 'TKT-' . strtoupper(base_convert((string) time(), 10, 36));
        $this->responderJson(['sucesso' => true, 'numero_ticket' => $numero, 'prazo_resposta' => '48h úteis']);
    }

    /** GET /api/copiloto/cron */
    public function cron(Request $request) {
        $token = $request->getParam('token', '');
        $service = new CopilotoService();
        $apiKey = $service->getConfig('api_key_claude', '');
        $expectedToken = substr(md5('copiloto_cron_' . $apiKey), 0, 16);
        if ($token !== $expectedToken && !empty($apiKey)) {
            $this->responderJson(['erro' => 'Token inválido.'], 403);
        }
        $processados = $service->processarConteudoPendente();
        $limpeza = $service->limparDadosAntigos();
        $this->responderJson(['success' => true, 'conteudo_processado' => $processados, 'limpeza' => $limpeza, 'timestamp' => date('Y-m-d H:i:s')]);
    }
}
