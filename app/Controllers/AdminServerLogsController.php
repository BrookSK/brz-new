<?php
namespace App\Controllers;

use App\Core\Services\AuthService;

class AdminServerLogsController {
    
    public function index($request) {
        $auth = new AuthService();
        $usuario = $auth->getUsuarioLogado();
        $perfil = strtolower(trim((string) ($usuario['perfil'] ?? ($_SESSION['usuario_perfil'] ?? ''))));
        if ($perfil !== 'admin') {
            header('Location: /admin');
            exit;
        }

        $busca = trim((string) ($request->getParam('busca') ?? ''));
        $arquivo = trim((string) ($request->getParam('arquivo') ?? ''));
        $linhas = (int) ($request->getParam('linhas') ?? 500);
        if ($linhas < 50) $linhas = 50;
        if ($linhas > 5000) $linhas = 5000;

        // Caminhos comuns de logs
        $logPaths = [
            '/var/log/nginx/error.log',
            '/var/log/nginx/error.log.1',
            '/var/log/php-fpm/error.log',
            '/var/log/php8.2-fpm.log',
            '/var/log/php8.1-fpm.log',
            '/var/log/php-fpm/www-error.log',
            '/tmp/php_errors.log',
            '/tmp/php-errors.log',
            '/var/log/syslog',
        ];

        // Detectar logs disponíveis
        $disponíveis = [];
        foreach ($logPaths as $p) {
            if (file_exists($p) && is_readable($p)) {
                $disponíveis[] = $p;
            }
        }

        // Adicionar qualquer error.log rotacionado
        foreach (glob('/var/log/nginx/error.log.*') as $f) {
            if (is_readable($f) && !in_array($f, $disponíveis)) {
                $disponíveis[] = $f;
            }
        }

        $resultados = [];
        $totalEncontrados = 0;
        $arquivoUsado = '';

        if ($busca !== '') {
            // Se arquivo específico, usar ele; senão procurar em todos
            $arquivosParaBuscar = [];
            if ($arquivo !== '' && in_array($arquivo, $disponíveis, true)) {
                $arquivosParaBuscar = [$arquivo];
            } else {
                $arquivosParaBuscar = $disponíveis;
            }

            foreach ($arquivosParaBuscar as $logFile) {
                // Usar grep para performance (evitar ler arquivos gigantes em PHP)
                $buscaEscapada = escapeshellarg($busca);
                $cmd = "grep -i {$buscaEscapada} " . escapeshellarg($logFile) . " 2>/dev/null | tail -n {$linhas}";
                $output = [];
                @exec($cmd, $output);
                
                if (!empty($output)) {
                    $arquivoUsado = $logFile;
                    foreach ($output as $line) {
                        $resultados[] = ['arquivo' => basename($logFile), 'linha' => $line];
                        $totalEncontrados++;
                    }
                }

                // Limitar resultado total
                if ($totalEncontrados >= $linhas) break;
            }
        }

        // Renderizar
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Busca Logs do Servidor</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .log-line { font-family: monospace; font-size: 12px; padding: 4px 8px; border-bottom: 1px solid #eee; word-break: break-all; }
            .log-line:hover { background: #f8f9fa; }
            .highlight { background-color: #fff3cd; font-weight: bold; }
        </style>
        </head><body>
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="fas fa-search"></i> Busca nos Logs do Servidor</h4>
                <a href="/admin" class="btn btn-outline-secondary btn-sm">Voltar ao Admin</a>
            </div>
            
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Termo de busca</label>
                    <input type="text" name="busca" class="form-control" value="' . htmlspecialchars($busca) . '" placeholder="Ex: declaration_value, 1000132, CRIAR_PEDIDO...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Arquivo de log</label>
                    <select name="arquivo" class="form-select">
                        <option value="">Todos disponíveis</option>';
        foreach ($disponíveis as $d) {
            $sel = ($arquivo === $d) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($d) . '" ' . $sel . '>' . htmlspecialchars($d) . '</option>';
        }
        echo '      </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Máx. linhas</label>
                    <input type="number" name="linhas" class="form-control" value="' . $linhas . '" min="50" max="5000">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Buscar</button>
                </div>
            </form>

            <div class="mb-3">
                <small class="text-muted">Logs disponíveis: ' . count($disponíveis) . ' arquivos</small>';
        if ($busca !== '') {
            echo ' | <small class="text-success fw-bold">Encontrados: ' . $totalEncontrados . ' resultados para "' . htmlspecialchars($busca) . '"</small>';
            if ($arquivoUsado) {
                echo ' | <small class="text-muted">Fonte: ' . htmlspecialchars($arquivoUsado) . '</small>';
            }
        }
        echo '  </div>';

        if (!empty($resultados)) {
            echo '<div class="card"><div class="card-body p-0" style="max-height:70vh; overflow-y:auto;">';
            foreach ($resultados as $r) {
                $lineHtml = htmlspecialchars($r['linha']);
                // Destacar o termo buscado
                if ($busca !== '') {
                    $lineHtml = preg_replace('/(' . preg_quote(htmlspecialchars($busca), '/') . ')/i', '<span class="highlight">$1</span>', $lineHtml);
                }
                echo '<div class="log-line"><small class="text-muted me-2">[' . htmlspecialchars($r['arquivo']) . ']</small>' . $lineHtml . '</div>';
            }
            echo '</div></div>';
        } elseif ($busca !== '') {
            echo '<div class="alert alert-warning">Nenhum resultado encontrado para "' . htmlspecialchars($busca) . '" nos logs disponíveis.</div>';
            echo '<div class="alert alert-info"><strong>Dica:</strong> Tente buscar por:<ul>
                <li><code>declaration_value</code> — valores declarados</li>
                <li><code>CRIAR_PEDIDO</code> — logs do checkout</li>
                <li><code>1000132</code> — produto_id específico</li>
                <li><code>pedido 1225</code> — referências ao pedido</li>
            </ul></div>';
        }

        echo '</div></body></html>';
    }
}
