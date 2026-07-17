<?php
namespace App\Controllers;

use App\Models\PacoteRecebido;
use App\Services\EmailService;
use App\Core\Request;

/**
 * Cron job diário para verificação de armazenamento de pacotes.
 * Rota: GET /cron/pacotes/armazenamento
 * 
 * Lógica:
 * 1. Buscar todos pacotes com status = 'pendente'
 * 2. Calcular dias desde data_recebimento
 * 3. Atualizar dias_armazenamento
 * 4. Se dias >= multa_inicio e (dias - multa_inicio) % intervalo == 0: enviar lembrete
 * 5. Se dias >= dias_descarte: descartar + enviar e-mail
 */
class CronPacotesController extends Controller {
    private $model;
    private $connection;

    public function __construct() {
        $this->model = new PacoteRecebido();
        $this->connection = \Config\Database::getConnection();
    }

    /**
     * Execução do cron de armazenamento
     */
    public function verificarArmazenamento(Request $request): void {
        $inicio = microtime(true);
        $resultados = [
            'total_verificados' => 0,
            'atualizados' => 0,
            'lembretes_enviados' => 0,
            'descartados' => 0,
            'erros' => [],
        ];

        // Buscar configurações
        $diasMultaInicio = (int) $this->getConfig('pacote_dias_multa_inicio', 15);
        $valorDia = (float) $this->getConfig('pacote_multa_valor_dia_usd', 2.00);
        $diasDescarte = (int) $this->getConfig('pacote_dias_descarte', 42);
        $intervaloLembrete = (int) $this->getConfig('pacote_lembrete_intervalo_dias', 5);

        // Buscar pacotes pendentes
        $pacotes = $this->model->getPendentesComDias();
        $resultados['total_verificados'] = count($pacotes);

        foreach ($pacotes as $pacote) {
            try {
                $dias = (int) ($pacote['dias_desde_recebimento'] ?? 0);

                // Atualizar dias_armazenamento
                $this->model->update((int) $pacote['id'], ['dias_armazenamento' => $dias]);
                $resultados['atualizados']++;

                // Verificar descarte
                if ($dias >= $diasDescarte) {
                    $this->model->atualizarStatus((int) $pacote['id'], 'descartado');
                    $this->enviarEmailDescarte($pacote, $dias);
                    $resultados['descartados']++;
                    continue;
                }

                // Verificar lembrete de multa
                if ($dias >= $diasMultaInicio) {
                    $diasAtraso = $dias - $diasMultaInicio;
                    if ($intervaloLembrete > 0 && $diasAtraso % $intervaloLembrete === 0 && $diasAtraso > 0) {
                        $multa = $diasAtraso * $valorDia;
                        $this->enviarEmailLembrete($pacote, $dias, $multa, $diasDescarte);
                        $resultados['lembretes_enviados']++;
                    }
                }
            } catch (\Throwable $e) {
                $resultados['erros'][] = 'Pacote #' . ($pacote['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        $duracao = round((microtime(true) - $inicio) * 1000);
        $resultados['duracao_ms'] = $duracao;

        // Retornar resultado como JSON (para monitoramento)
        $this->json([
            'success' => true,
            'message' => 'Cron de armazenamento executado.',
            'resultados' => $resultados,
        ]);
    }

    // ==================== Métodos privados ====================

    private function getConfig(string $chave, $default = null) {
        try {
            $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function getUsuarioByPacote(array $pacote): ?array {
        try {
            $stmt = $this->connection->prepare('SELECT id, nome, email FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$pacote['usuario_id']]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function enviarEmailLembrete(array $pacote, int $dias, float $multa, int $diasDescarte): void {
        $usuario = $this->getUsuarioByPacote($pacote);
        if (!$usuario) return;

        $nome = htmlspecialchars($usuario['nome'] ?? 'Cliente');
        $prodNome = htmlspecialchars($pacote['nome'] ?? 'Produto');
        $multaFmt = number_format($multa, 2, ',', '.');
        $diasRestantes = $diasDescarte - $dias;

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #1a3a5c; padding: 20px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Braziliana</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #dc3545;">⚠️ Lembrete de Armazenamento</h2>
        <p>Olá, <strong>{$nome}</strong>!</p>
        <p>Seu produto <strong>"{$prodNome}"</strong> está há <strong>{$dias} dias</strong> em nosso armazém.</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <strong>Taxa de armazenamento acumulada: US$ {$multaFmt}</strong><br>
            <small>Esta taxa será cobrada junto ao envio.</small>
        </div>

        <p><strong>Atenção:</strong> Restam <strong>{$diasRestantes} dias</strong> antes do descarte automático.</p>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="/carrinho" style="background: #1a3a5c; color: #fff; text-decoration: none; padding: 12px 30px; border-radius: 5px; display: inline-block;">
                Solicitar Envio Agora
            </a>
        </p>
    </div>
</body></html>
HTML;

        try {
            $emailService = new EmailService();
            $emailService->send(
                $usuario['email'],
                'Lembrete: Taxa de armazenamento - ' . $prodNome,
                $html,
                'pacote_lembrete_' . $pacote['id'] . '_dia_' . $dias,
                ['evento' => 'pacote_lembrete', 'usuario_id' => $usuario['id']]
            );
        } catch (\Throwable $e) {
            error_log('[CronPacotes] Erro e-mail lembrete: ' . $e->getMessage());
        }
    }

    private function enviarEmailDescarte(array $pacote, int $dias): void {
        $usuario = $this->getUsuarioByPacote($pacote);
        if (!$usuario) return;

        $nome = htmlspecialchars($usuario['nome'] ?? 'Cliente');
        $prodNome = htmlspecialchars($pacote['nome'] ?? 'Produto');

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #1a3a5c; padding: 20px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Braziliana</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #dc3545;">Produto Descartado</h2>
        <p>Olá, <strong>{$nome}</strong>.</p>
        <p>Informamos que o produto <strong>"{$prodNome}"</strong> foi descartado após {$dias} dias em nosso armazém sem solicitação de envio.</p>
        
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;">
            Conforme nossa política, produtos que excedem o prazo máximo de armazenamento são descartados automaticamente.
        </div>

        <p>Em caso de dúvidas, entre em contato com nossa equipe de suporte.</p>
    </div>
</body></html>
HTML;

        try {
            $emailService = new EmailService();
            $emailService->send(
                $usuario['email'],
                'Produto descartado - ' . $prodNome,
                $html,
                'pacote_descarte_' . $pacote['id'],
                ['evento' => 'pacote_descartado', 'usuario_id' => $usuario['id']]
            );
        } catch (\Throwable $e) {
            error_log('[CronPacotes] Erro e-mail descarte: ' . $e->getMessage());
        }
    }
}
