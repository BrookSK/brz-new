<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

/**
 * Runner de migrations SQL.
 *
 * Roda todos os arquivos .sql de database/migrations/ de forma idempotente:
 * registra cada arquivo aplicado na tabela de controle `schema_migrations` e
 * pula os que já rodaram.
 *
 * Como o projeto tem SQL solto com nomes sem padrão, o runner NÃO tenta
 * adivinhar "o que é migration" pelo nome: ele roda TUDO em migrations/,
 * exceto:
 *   1. Arquivos na BLACKLIST por nome (destrutivos/debug/consolidados conhecidos).
 *   2. Arquivos que a varredura de conteúdo classificar como PERIGOSOS
 *      (DROP DATABASE, TRUNCATE, DELETE/UPDATE sem WHERE, GRANT, etc.).
 * Assim, um SQL novo com nome aleatório é pego automaticamente, mas um script
 * catastrófico é barrado mesmo com nome inocente.
 */
class AdminMigrationsController extends Controller {

    /**
     * Arquivos que NUNCA devem ser rodados automaticamente (bloqueio por nome):
     * - destrutivos / debug (apagam ou injetam dados)
     * - consolidados ALL_*: reagrupam migrations já numeradas individualmente
     *   (fonte canônica), então rodar ambos é redundante e pode falhar em
     *   statements não idempotentes.
     */
    private const BLACKLIST = [
        'delete_usuarios.sql',
        'reset_db_keep_admin.sql',
        'DEBUG_PEDIDOS_INSERT.sql',
        'ALL_branch_session.sql',
        'ALL_redirecionamento_pacotes.sql',
    ];

    /**
     * Allowlist: arquivos liberados manualmente para rodar MESMO que a varredura
     * de conteúdo os marque como perigosos. Use com consciência — só para casos
     * revisados onde o comando perigoso é intencional e seguro.
     * (nome relativo com prefixo 'migrations/')
     */
    private const ALLOWLIST_CONTEUDO = [
        // ex.: 'migrations/203_cleanup_lista_compras_duplicadas.sql',
    ];

    /**
     * Padrões de conteúdo considerados PERIGOSOS demais para rodar
     * automaticamente. Cada item: [regex, motivo legível].
     * As regexes rodam sobre cada statement já sem comentários.
     */
    private function padroesPerigosos(): array {
        return [
            ['/\bDROP\s+DATABASE\b/i',              'DROP DATABASE'],
            ['/\bCREATE\s+DATABASE\b/i',            'CREATE DATABASE'],
            ['/\bUSE\s+[`"\w]+/i',                  'USE <database> (troca de banco)'],
            ['/\bDROP\s+SCHEMA\b/i',                'DROP SCHEMA'],
            ['/\bTRUNCATE\b/i',                     'TRUNCATE (esvazia tabela)'],
            ['/\bGRANT\b/i',                        'GRANT (altera permissões)'],
            ['/\bREVOKE\b/i',                       'REVOKE (altera permissões)'],
            ['/\bDROP\s+USER\b/i',                  'DROP USER'],
            ['/\bCREATE\s+USER\b/i',                'CREATE USER'],
            // DELETE sem WHERE e sem JOIN — apaga a tabela inteira
            ['/\bDELETE\s+(?:FROM\s+)?[`"\w.]+\s*(?:;|$)/i', 'DELETE sem WHERE (apaga tudo)'],
            // UPDATE sem WHERE — sobrescreve todas as linhas
            ['/\bUPDATE\s+[`"\w.]+\s+SET\b(?![\s\S]*\bWHERE\b)/i', 'UPDATE sem WHERE (sobrescreve tudo)'],
        ];
    }

    private function baseDir(): string {
        return realpath(__DIR__ . '/../../database') ?: (__DIR__ . '/../../database');
    }

    /**
     * Remove comentários de um statement para reduzir falso-positivo/negativo
     * na varredura de segurança.
     *
     * IMPORTANTE: `--` e `#` só valem como comentário no INÍCIO de uma linha
     * (após espaços). No meio de uma string SQL eles são literais — ex.:
     * `payload_template = '... #{{carne_id}} ...'`. Tratar `#` no meio como
     * comentário apagaria o resto do statement (incluindo o WHERE) e geraria
     * falso-positivo de "UPDATE sem WHERE".
     */
    private function limparParaAnalise(string $stmt): string {
        $stmt = str_replace(["\r\n", "\r"], "\n", $stmt);
        $linhas = explode("\n", $stmt);
        $out = [];
        foreach ($linhas as $linha) {
            $t = ltrim($linha);
            // Descarta a linha inteira só se ela COMEÇA com comentário
            if (strpos($t, '--') === 0 || strpos($t, '#') === 0) {
                continue;
            }
            $out[] = $linha;
        }
        $limpo = implode("\n", $out);
        // Remove blocos de comentário /* ... */ (esses podem estar inline)
        $limpo = preg_replace('#/\*.*?\*/#s', ' ', $limpo);
        return (string) $limpo;
    }

    /**
     * Analisa um arquivo SQL statement a statement e retorna a lista de motivos
     * perigosos encontrados. Vazio = seguro.
     *
     * @return string[]
     */
    private function analisarSeguranca(array $statements): array {
        $padroes = $this->padroesPerigosos();
        $motivos = [];
        foreach ($statements as $stmt) {
            $limpo = $this->limparParaAnalise($stmt);
            if (trim($limpo) === '') {
                continue;
            }
            foreach ($padroes as [$regex, $motivo]) {
                if (preg_match($regex, $limpo)) {
                    $motivos[$motivo] = true;
                }
            }
        }
        return array_keys($motivos);
    }

    /**
     * Coleta os arquivos .sql de database/migrations/, em ordem natural, já
     * classificados: bloqueado por nome, bloqueado por conteúdo, ou elegível.
     *
     * NÃO inclui os arquivos 001/002/003 da RAIZ de database/ (esquema
     * fundacional antigo do banco brz_logistics) nem database/scripts/
     * (scripts de diagnóstico manual, não migrations).
     *
     * @return array<int,array{name:string,path:string,bloqueado:bool,motivos:string[]}>
     */
    private function coletarArquivos(): array {
        $base = $this->baseDir();
        $migrationsDir = $base . DIRECTORY_SEPARATOR . 'migrations';

        $migFiles = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        natcasesort($migFiles);

        $arquivos = [];
        foreach (array_values($migFiles) as $path) {
            $name = basename($path);
            $rel = 'migrations/' . $name;

            // Bloqueio por nome (blacklist)
            if (in_array($name, self::BLACKLIST, true)) {
                $arquivos[] = ['name' => $rel, 'path' => $path, 'bloqueado' => true, 'motivos' => ['blacklist por nome']];
                continue;
            }

            // Varredura de conteúdo
            $sql = @file_get_contents($path);
            $motivos = [];
            if ($sql !== false) {
                $motivos = $this->analisarSeguranca($this->splitStatements($sql));
            }

            // Allowlist libera conteúdo perigoso revisado manualmente
            if (!empty($motivos) && in_array($rel, self::ALLOWLIST_CONTEUDO, true)) {
                $motivos = [];
            }

            $arquivos[] = [
                'name'      => $rel,
                'path'      => $path,
                'bloqueado' => !empty($motivos),
                'motivos'   => $motivos,
            ];
        }
        return $arquivos;
    }

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
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $len = strlen($sql);

        $statements = [];
        $buffer = '';
        $delimiter = ';';

        $i = 0;
        while ($i < $len) {
            $ch = $sql[$i];
            $two = substr($sql, $i, 2);

            // Comentário de linha: -- (seguido de espaço/fim) ou #
            $atLineStartCtx = ($buffer === '' || substr($buffer, -1) === "\n" || trim(substr(strrchr($buffer, "\n") ?: $buffer, 1)) === '');
            if (($two === '--' && (($i + 2 >= $len) || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n")) || $ch === '#') {
                // Consome até o fim da linha
                $nl = strpos($sql, "\n", $i);
                if ($nl === false) break;
                $i = $nl + 1;
                // Se o buffer só tinha espaços até aqui, não deixa lixo
                continue;
            }

            // Comentário de bloco /* ... */
            if ($two === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = ($end === false) ? $len : $end + 2;
                continue;
            }

            // Diretiva DELIMITER (apenas no início de uma linha)
            if (($buffer === '' || substr(rtrim($buffer), -1) === "\n" || trim($buffer) === '')
                && strtoupper(substr($sql, $i, 10)) === 'DELIMITER ') {
                $nl = strpos($sql, "\n", $i);
                $lineEnd = ($nl === false) ? $len : $nl;
                $novo = trim(substr($sql, $i + 10, $lineEnd - ($i + 10)));
                if ($novo !== '') {
                    $delimiter = $novo;
                }
                $buffer = '';
                $i = ($nl === false) ? $len : $nl + 1;
                continue;
            }

            // Strings: ' ou " (com escape por \ ou por duplicação)
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $buffer .= $ch;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    if ($c === '\\' && $i + 1 < $len) {
                        $buffer .= $c . $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($c === $quote) {
                        // Aspas duplicadas = escape literal
                        if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                            $buffer .= $quote . $quote;
                            $i += 2;
                            continue;
                        }
                        $buffer .= $c;
                        $i++;
                        break;
                    }
                    $buffer .= $c;
                    $i++;
                }
                continue;
            }

            // Delimitador de statement (fora de string)
            $dlen = strlen($delimiter);
            if (substr($sql, $i, $dlen) === $delimiter) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
                $i += $dlen;
                continue;
            }

            $buffer .= $ch;
            $i++;
        }

        $resto = trim($buffer);
        if ($resto !== '') {
            $statements[] = $resto;
        }

        return $statements;
    }

    /**
     * Detecta erros benignos do padrão idempotente com PREPARE/EXECUTE:
     *
     *   SET @sql := IF(coluna_existe, '', 'ALTER TABLE ...');
     *   PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
     *
     * Quando a coluna já existe, @sql = '' e a cadeia falha em sequência:
     *   - 1065 "Query was empty": o PREPARE recebeu string vazia.
     *   - 1243 "Unknown prepared statement handler": consequência — como o
     *     PREPARE virou no-op, o EXECUTE/DEALLOCATE seguintes não encontram o
     *     handler 'stmt'.
     *
     * Ambos significam "nada a fazer" (migration já aplicada), então são
     * ignorados em vez de abortar.
     */
    private function isErroPrepareBenigno(\PDOException $e): bool {
        $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        if ($code === 1065 || $code === 1243) {
            return true;
        }
        $msg = $e->getMessage();
        return stripos($msg, 'Query was empty') !== false
            || stripos($msg, 'Unknown prepared statement handler') !== false;
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
                // Usamos query() (não exec()) porque muitas migrations do projeto
                // usam PREPARE/EXECUTE/DEALLOCATE para idempotência via
                // INFORMATION_SCHEMA. Quando @sql vira 'SELECT 1', o EXECUTE
                // devolve um result set: se ele não for consumido/fechado, o
                // statement seguinte (DEALLOCATE) falha com o erro 2014
                // "unbuffered queries are active". closeCursor() drena isso.
                $result = $db->query($stmt);
                if ($result instanceof \PDOStatement) {
                    // Fecha o cursor para liberar a conexão (evita o erro 2014
                    // no statement seguinte, ex.: DEALLOCATE após EXECUTE).
                    $result->closeCursor();
                    $result = null;
                }
                $count++;
            } catch (\PDOException $e) {
                // No-op benigno do padrão idempotente PREPARE/EXECUTE quando
                // @sql = '' (coluna já existe): 1065 no PREPARE e, em cascata,
                // 1243 no EXECUTE/DEALLOCATE. Ignora e segue.
                if ($this->isErroPrepareBenigno($e)) {
                    $count++;
                    continue;
                }
                return [false, $e->getMessage() . ' | SQL: ' . substr($stmt, 0, 200), $count];
            }
        }
        return [true, null, $count];
    }

    /** Tela com a lista de migrations e status. */
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $db = Database::getConnection();
        $this->garantirTabelaControle($db);
        $aplicadas = $this->jaAplicadas($db);
        $arquivos = $this->coletarArquivos();

        $pendentes = 0;
        $bloqueados = 0;
        foreach ($arquivos as $a) {
            if ($a['bloqueado']) { $bloqueados++; continue; }
            if (empty($aplicadas[$a['name']])) $pendentes++;
        }
        $aplicadasCount = 0;
        foreach ($arquivos as $a) {
            if (!$a['bloqueado'] && !empty($aplicadas[$a['name']])) $aplicadasCount++;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>Migrations - Braziliana Admin</title>'
           . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'
           . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">'
           . '</head><body class="bg-light"><div class="container py-4">';

        echo '<h1 class="h3 mb-3"><i class="fas fa-database me-2"></i>Migrations do Banco</h1>';
        echo '<p class="text-muted">Roda todos os SQL de <code>database/migrations/</code> pendentes, em ordem, de forma idempotente. '
           . 'Arquivos com comandos destrutivos (DROP DATABASE, TRUNCATE, DELETE/UPDATE sem WHERE, GRANT...) são <strong>bloqueados</strong> por segurança e precisam ser rodados manualmente.</p>';

        echo '<div class="d-flex gap-3 mb-3 flex-wrap">'
           . '<span class="badge bg-secondary fs-6">Total: ' . count($arquivos) . '</span>'
           . '<span class="badge bg-success fs-6">Aplicadas: ' . $aplicadasCount . '</span>'
           . '<span class="badge bg-warning text-dark fs-6">Pendentes: ' . $pendentes . '</span>'
           . '<span class="badge bg-danger fs-6">Bloqueadas: ' . $bloqueados . '</span>'
           . '</div>';

        echo '<form method="POST" action="/admin/migrations/run" onsubmit="this.querySelector(\'button\').disabled=true;this.querySelector(\'button\').innerHTML=\'<span class=\\\'spinner-border spinner-border-sm me-2\\\'></span>Rodando...\';">'
           . '<button type="submit" class="btn btn-primary mb-4"' . ($pendentes === 0 ? ' disabled' : '') . '>'
           . '<i class="fas fa-play me-1"></i>Rodar ' . $pendentes . ' migration(s) pendente(s)</button></form>';

        echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">'
           . '<thead><tr><th>#</th><th>Arquivo</th><th>Status</th><th>Observação</th></tr></thead><tbody>';
        $i = 1;
        foreach ($arquivos as $a) {
            if ($a['bloqueado']) {
                $badge = '<span class="badge bg-danger">Bloqueada</span>';
                $obs = htmlspecialchars(implode(', ', $a['motivos']), ENT_QUOTES, 'UTF-8');
            } elseif (!empty($aplicadas[$a['name']])) {
                $badge = '<span class="badge bg-success">Aplicada</span>';
                $obs = '';
            } else {
                $badge = '<span class="badge bg-warning text-dark">Pendente</span>';
                $obs = '';
            }
            echo '<tr><td>' . ($i++) . '</td><td><code>' . htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') . '</code></td><td>' . $badge . '</td><td class="small text-danger">' . $obs . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '</div></body></html>';
        exit;
    }

    /** Executa todas as migrations pendentes (não bloqueadas) e mostra o relatório. */
    public function run(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $db = Database::getConnection();
        // Reforço contra o erro 2014 ("unbuffered queries active"): garante que
        // os result sets sejam totalmente bufferizados no cliente. Escopo local
        // desta conexão de request; não altera Config\Database.
        try {
            $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (\Throwable $e) {
            // Se o driver não suportar, seguimos com o closeCursor() do executarArquivo.
        }

        $this->garantirTabelaControle($db);
        $aplicadas = $this->jaAplicadas($db);
        $arquivos = $this->coletarArquivos();

        $stMark = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (?) ON DUPLICATE KEY UPDATE aplicada_em = aplicada_em');

        $resultados = [];
        $rodadas = 0;
        $puladas = 0;
        $bloqueadas = 0;
        $falhas = 0;

        foreach ($arquivos as $a) {
            // Bloqueadas por segurança: nunca executa, apenas reporta
            if ($a['bloqueado']) {
                $bloqueadas++;
                $resultados[] = ['name' => $a['name'], 'status' => 'bloqueada', 'msg' => 'Não executada — ' . implode(', ', $a['motivos'])];
                continue;
            }

            if (!empty($aplicadas[$a['name']])) {
                $puladas++;
                continue;
            }

            [$ok, $erro, $count] = $this->executarArquivo($db, $a['path']);

            if ($ok) {
                $stMark->execute([$a['name']]);
                $rodadas++;
                $resultados[] = ['name' => $a['name'], 'status' => 'ok', 'msg' => $count . ' statement(s)'];
            } else {
                $falhas++;
                $resultados[] = ['name' => $a['name'], 'status' => 'erro', 'msg' => $erro];
                // Para na primeira falha real: migrations dependem umas das outras
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
        echo '<div class="d-flex gap-3 mb-3 flex-wrap">'
           . '<span class="badge bg-success fs-6">Rodadas: ' . $rodadas . '</span>'
           . '<span class="badge bg-secondary fs-6">Já aplicadas: ' . $puladas . '</span>'
           . '<span class="badge bg-danger fs-6">Bloqueadas: ' . $bloqueadas . '</span>'
           . '<span class="badge bg-dark fs-6">Falhas: ' . $falhas . '</span>'
           . '</div>';

        if ($falhas > 0) {
            echo '<div class="alert alert-danger">Parei na primeira falha. Corrija o erro abaixo e rode novamente '
               . '(as que já passaram não vão repetir).</div>';
        } else {
            echo '<div class="alert alert-success">Migrations pendentes aplicadas. '
               . ($bloqueadas > 0 ? 'As bloqueadas por segurança precisam ser revisadas e rodadas manualmente.' : '') . '</div>';
        }

        if (!empty($resultados)) {
            echo '<div class="table-responsive"><table class="table table-sm align-middle">'
               . '<thead><tr><th>Arquivo</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>';
            foreach ($resultados as $r) {
                switch ($r['status']) {
                    case 'ok':        $badge = '<span class="badge bg-success">OK</span>'; break;
                    case 'bloqueada': $badge = '<span class="badge bg-danger">BLOQUEADA</span>'; break;
                    default:          $badge = '<span class="badge bg-dark">ERRO</span>'; break;
                }
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
