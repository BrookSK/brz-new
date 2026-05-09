<?php
/**
 * Cron Job: Arquivamento de gravações de lives
 * 
 * Executa a cada 30 minutos:
 * */30 * * * * php /path/to/cron/live-recording-archive.php
 * 
 * Fluxo:
 * 1. Busca lives encerradas sem recording_url
 * 2. Verifica se gravação está pronta no Cloudflare
 * 3. Salva URL da gravação no banco
 * 4. (Opcional) Baixa MP4 para storage local
 */

// Bootstrap da aplicação
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Models\Live;
use App\Services\LiveStreamService;

echo "[" . date('Y-m-d H:i:s') . "] Live Recording Archive - Iniciando...\n";

try {
    $liveModel = new Live();
    $streamService = new LiveStreamService();

    // Buscar lives encerradas sem gravação
    $lives = $liveModel->getEndedWithoutRecording(10);

    if (empty($lives)) {
        echo "Nenhuma live pendente de arquivamento.\n";
        exit(0);
    }

    echo count($lives) . " live(s) pendente(s) de arquivamento.\n";

    foreach ($lives as $live) {
        $liveId = $live['id'];
        $cfInputId = $live['cf_live_input_id'];

        echo "  Processando Live #{$liveId} (CF: {$cfInputId})...\n";

        // Verificar status da gravação
        $recording = $streamService->getRecordingStatus($cfInputId);

        if (!$recording) {
            echo "    ⚠ Erro ao consultar status da gravação.\n";
            continue;
        }

        if ($recording['status'] !== 'ready') {
            echo "    ⏳ Gravação ainda não está pronta (status: {$recording['status']}).\n";
            continue;
        }

        // Gravação pronta — salvar URL
        $playbackUrl = $recording['playback_url'] ?? '';
        
        if (empty($playbackUrl)) {
            echo "    ⚠ URL de playback não disponível.\n";
            continue;
        }

        // Salvar no banco
        $liveModel->update($liveId, [
            'recording_url' => $playbackUrl,
        ]);

        echo "    ✓ Gravação salva: {$playbackUrl}\n";

        // (Opcional) Baixar MP4 para storage local
        // Descomente se quiser manter cópia local:
        /*
        $storageDir = __DIR__ . '/../storage/lives/';
        if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);
        
        $localFile = $storageDir . "live_{$liveId}_" . date('Ymd') . '.mp4';
        // Nota: CF Stream não oferece download direto via API pública.
        // Para baixar, usar a URL HLS com ffmpeg ou ferramenta similar.
        // exec("ffmpeg -i '{$playbackUrl}' -c copy '{$localFile}' 2>&1", $output, $code);
        
        if (file_exists($localFile)) {
            $liveModel->update($liveId, ['recording_url' => '/storage/lives/' . basename($localFile)]);
            echo "    ✓ MP4 salvo localmente: {$localFile}\n";
            
            // Deletar do CF (opcional)
            // $streamService->deleteLiveInput($cfInputId);
        }
        */
    }

    echo "[" . date('Y-m-d H:i:s') . "] Concluído.\n";

} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
