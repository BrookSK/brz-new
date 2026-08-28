<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

/**
 * Runner de migrations SQL.
 *
 * Roda todos os arquivos .sql de database/ e database/migrations/ de forma
 * idempotente: registra cada arquivo aplicado na tabela de controle
 * `schema_migrations` e pula os que já rodaram. Scripts destrutivos ficam numa
 * blacklist e nunca são executados por aqui.
 */
class AdminMigrationsController extends Controller {

    /**
     * Arquivos que NUNCA devem ser rodados automaticamente:
     * - destrutivos / debug (apagam ou injetam dados)
     * - consolidados ALL_*: reagrupam migrations que já existem numeradas
     *   individualmente (fonte canônica), então rodar ambos é redundante e
     *   pode falhar em statements não idempotentes.
     */
    private const BLACKLIST = [
        'delete_usuarios.sql',
        'reset_db_keep_admin.sql',
        'DEBUG_PEDIDOS_INSERT.sql',
        'ALL_branch_session.sql',
        'ALL_redirecionamento_pacotes.sql',
    ];

    /**
     * Ordem de prioridade dos diretórios. Os schemas base ficam na raiz de
     * database/ e precisam rodar antes das migrations incrementais.
     */
    private function baseDir(): string {
        return realpath(__DIR__ . '/../../database') ?: (__DIR__ . '/../../database');
    }

    /**
     * Coleta todos os arquivos .sql elegíveis, já ordenados na sequência correta:
     * primeiro os schemas base da raiz de database/ (001, 002, 003, ...),
     * depois os incrementais de database/migrations/ em ordem natural.
     *
     * @return array<int,array{name:string,path:string}>
     */
    private function coletarArquivos(): array {
        $base = $this->baseDir();
        $migrationsDir = $base . DIRECTORY_SEPARATOR . 'migrations';

        $rootFiles = glob($base . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $migFiles  = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];

        // Ordenação natural garante 002 < 010 < 100
        natcasesort($rootFiles);
        natcasesort($migFiles);

        $arquivos = [];
        foreach (array_merge(array_values($rootFiles), array_values($migFiles)) as $path) {
            $name = basename($path);
            if (in_array($name, self::BLACKLIST, true)) {
                continue;
            }
            // Chave de controle: prefixo do diretório evita colisão de nomes iguais
            $rel = (strpos($path, $migrationsDir) === 0 ? 'migrations/' : '') . $name;
            $arquivos[] = ['name' => $rel, 'path' => $path];
        }
        return $arquivos;
    }

    /** Garante a existência da tabela de controle de migrations. */
    private function garantirTabelaControle(\PDO $db): void {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return array<string,bool> conjunto de migrations já aplicadas */
    private function jaAplicadas(\PDO $db): array {
        $set = [];
        $st = $db->query('SELECT migration FROM schema_migrations');
        foreach (($st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []) as $m) {
            $set[(string) $m] = true;
        }
        return $set;
    }

    /**
     * Quebra o conteúdo de um arquivo SQL em statements executáveis,
     * respeitando blocos DELIMITER (usados em triggers/procedures) e
     * removendo comentários de linha.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array {
        // Normaliza quebras de linha
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);

        $statements = [];
        $buffer = '';
        $delimiter = ';';
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trim = trim($line);

            // Ignora comentários de linha e linhas vazias fora de um buffer
            if ($buffer === '' && ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '#') === 0)) {
                continue;
            }

            // Troca de delimitador (comando de cliente, não vai pro servidor)
            if (stripos($trim, 'DELIMITER ') === 0) {
                // Antes de trocar, se sobrou algo no buffer, descarta espaços
                $novo = trim(substr($trim, strlen('DELIMITER ')));
                if ($novo !== '') {
                    $delimiter = $novo;
                }
                continue;
            }

            $buffer .= $line . "\n";

            // Verifica se a linha termina o statement com o delimitador atual
            $bufTrim = rtrim(trim($buffer));
            if ($bufTrim !== '' && substr($bufTrim, -strlen($delimiter)) === $delimiter) {
                $stmt = trim(substr($bufTrim, 0, -strlen($delimiter)));
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            }
        }

        // Resto sem delimitador final
        $resto = trim($buffer);
        if ($resto !== '') {
            $statements[] = $resto;
        }

        return $statements;
    }

    /**
     * Executa um arquivo SQL inteiro. Retorna [sucesso, mensagemErro, qtdStatements].
     *
     * @return array{0:bool,1:?string,2:int}
     */
    private function executarArquivo(\PDO $db, string $path): array {
        $sql = file_get_contents($path);
        if ($sql === false) {
            return [false, 'Não foi possível ler o arquivo', 0];
        }

        $statements = $this->splitStatements($sql);
        $count = 0;
        foreach ($statements as $stmt) {
            try {
                $db->exec($stmt);
                $count++;
            } catch (\PDOException $e) {
                return [false, $e->getMessage() . ' | SQL: ' . substr($stmt, 0, 200), $count];
            }
        }
        return [true, null, $count];
    }

    /** Tela com a lista de migrations e status (aplicada / pendente). */
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $db = Database::getConnection();
        $this->garantirTabelaControle($db);
        $aplicadas = $this->jaAplicadas($db);
        $arquivos = $this->coletarArquivos();

        $pendentes = 0;
        foreach ($arquivos as $a) {
            if (empty($aplicadas[$a['name']])) $pendentes++;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>Migrations - Braziliana Admin</title>'
           . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'
           . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">'
           . '</head><body class="bg-light"><div class="container py-4">';

        echo '<h1 class="h3 mb-3"><i class="fas fa-database me-2"></i>Migrations do Banco</h1>';
        echo '<p class="text-muted">Executa os arquivos SQL pendentes em ordem. Já aplicados são ignorados (idempotente). '
           . 'Scripts destrutivos ficam fora por segurança.</p>';

        echo '<div class="d-flex gap-3 mb-3">'
           . '<span class="badge bg-secondary fs-6">Total: ' . count($arquivos) . '</span>'
           . '<span class="badge bg-success fs-6">Aplicadas: ' . (count($arquivos) - $pendentes) . '</span>'
           . '<span class="badge bg-warning text-dark fs-6">Pendentes: ' . $pendentes . '</span>'
           . '</div>';

        echo '<form method="POST" action="/admin/migrations/run" onsubmit="this.querySelector(\'button\').disabled=true;this.querySelector(\'button\').innerHTML=\'<span class=\\\'spinner-border spinner-border-sm me-2\\\'></span>Rodando...\';">'
           . '<button type="submit" class="btn btn-primary mb-4"' . ($pendentes === 0 ? ' disabled' : '') . '>'
           . '<i class="fas fa-play me-1"></i>Rodar ' . $pendentes . ' migration(s) pendente(s)</button></form>';

        echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">'
           . '<thead><tr><th>#</th><th>Arquivo</th><th>Status</th></tr></thead><tbody>';
        $i = 1;
        foreach ($arquivos as $a) {
            $ok = !empty($aplicadas[$a['name']]);
            $badge = $ok
                ? '<span class="badge bg-success">Aplicada</span>'
                : '<span class="badge bg-warning text-dark">Pendente</span>';
            echo '<tr><td>' . ($i++) . '</td><td><code>' . htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') . '</code></td><td>' . $badge . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '</div></body></html>';
        exit;
    }

    /** Executa todas as migrations pendentes e mostra o relatório. */
    public function run(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $db = Database::getConnection();
        $this->garantirTabelaControle($db);
        $aplicadas = $this->jaAplicadas($db);
        $arquivos = $this->coletarArquivos();

        $stMark = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (?) ON DUPLICATE KEY UPDATE aplicada_em = aplicada_em');

        $resultados = [];
        $rodadas = 0;
        $puladas = 0;
        $falhas = 0;

        foreach ($arquivos as $a) {
            if (!empty($aplicadas[$a['name']])) {
                $puladas++;
                continue;
            }

            [$ok, $erro, $count] = $this->executarArquivo($db, $a['path']);

            if ($ok) {
                $stMark->execute([$a['name']]);
                $rodadas++;
                $resultados[] = ['name' => $a['name'], 'ok' => true, 'msg' => $count . ' statement(s)'];
            } else {
                $falhas++;
                $resultados[] = ['name' => $a['name'], 'ok' => false, 'msg' => $erro];
                // Para na primeira falha: migrations dependem umas das outras
                break;
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<title>Migrations - Resultado</title>'
           . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'
           . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">'
           . '</head><body class="bg-light"><div class="container py-4">';

        echo '<h1 class="h3 mb-3"><i class="fas fa-database me-2"></i>Resultado das Migrations</h1>';
        echo '<div class="d-flex gap-3 mb-3">'
           . '<span class="badge bg-success fs-6">Rodadas: ' . $rodadas . '</span>'
           . '<span class="badge bg-secondary fs-6">Já aplicadas: ' . $puladas . '</span>'
           . '<span class="badge bg-danger fs-6">Falhas: ' . $falhas . '</span>'
           . '</div>';

        if ($falhas > 0) {
            echo '<div class="alert alert-danger">Parei na primeira falha. Corrija o erro abaixo e rode novamente '
               . '(as que já passaram não vão repetir).</div>';
        } else {
            echo '<div class="alert alert-success">Tudo certo. Banco atualizado.</div>';
        }

        if (!empty($resultados)) {
            echo '<div class="table-responsive"><table class="table table-sm align-middle">'
               . '<thead><tr><th>Arquivo</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>';
            foreach ($resultados as $r) {
                $badge = $r['ok']
                    ? '<span class="badge bg-success">OK</span>'
                    : '<span class="badge bg-danger">ERRO</span>';
                echo '<tr><td><code>' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</code></td>'
                   . '<td>' . $badge . '</td>'
                   . '<td class="small">' . htmlspecialchars((string) $r['msg'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p class="text-muted">Nenhuma migration pendente.</p>';
        }

        echo '<a href="/admin/migrations" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>';
        echo '</div></body></html>';
        exit;
    }
}
