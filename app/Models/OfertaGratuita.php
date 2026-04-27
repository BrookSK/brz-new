<?php
namespace App\Models;

use Config\Database;

class OfertaGratuita extends Model {
    protected $table = 'oferta_gratuita_log';

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableColumns(string $table): array {
        try {
            $st = $this->connection->query('DESCRIBE ' . $table);
            return $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Verifica se a funcionalidade está ativa globalmente
     */
    public function isOfertaGlobalAtiva(): bool {
        try {
            if (!$this->tableExists('configuracoes_sistema')) return false;
            $cols = $this->getTableColumns('configuracoes_sistema');

            // Schema single_row: coluna direta oferta_gratuita_ativa
            if (in_array('oferta_gratuita_ativa', $cols, true)) {
                $stmt = $this->connection->query("SELECT oferta_gratuita_ativa FROM configuracoes_sistema ORDER BY id ASC LIMIT 1");
                $val = $stmt->fetchColumn();
                return ($val === '1' || $val === 1 || $val === true);
            }

            // Schema categoria+chave+valor
            if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                $stmt = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'oferta_gratuita' AND chave = 'oferta_gratuita_ativa' LIMIT 1");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                return ($val === '1' || $val === 'true' || $val === 'sim');
            }

            // Schema chave+valor (sem categoria)
            if (in_array('chave', $cols, true)) {
                $stmt = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'oferta_gratuita_ativa' LIMIT 1");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                return ($val === '1' || $val === 'true' || $val === 'sim');
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica se o usuário já recebeu/recusou a oferta
     * No modo teste admin, sempre retorna false para permitir testes repetidos
     */
    public function usuarioJaRecebeuOferta(int $usuarioId, bool $ignorarParaTeste = false): bool {
        if ($ignorarParaTeste) return false;
        if ($usuarioId <= 0) return true;
        try {
            if (!$this->tableExists($this->table)) return false;
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM {$this->table} WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioId]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica se a sessão é orgânica (não é admin, não é redirecionamento, não é venda manual)
     * Se $allowTestMode = true e o admin tiver ?test_oferta=1 na URL, permite para teste
     */
    public function isSessaoOrganica(bool $allowTestMode = false): bool {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Não pode ser admin impersonando
        if (!empty($_SESSION['admin_impersonando'])) return false;
        if (!empty($_SESSION['impersonar_usuario_id'])) return false;
        if (!empty($_SESSION['admin_logado_como_cliente'])) return false;

        // Não pode ser venda manual
        if (!empty($_SESSION['venda_manual'])) return false;
        if (!empty($_SESSION['pedido_manual'])) return false;

        // Não pode ser redirecionamento
        if (!empty($_SESSION['redirecionamento_ativo'])) return false;
        if (!empty($_SESSION['is_redirecionamento'])) return false;

        // Deve ter usuário logado
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) return false;

        // Verificar perfil - deve ser cliente
        $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? ''))));
        if (in_array($perfil, ['admin', 'vendedor', 'suporte', 'redirecionador', 'representante', 'conferente'], true)) {
            // Modo teste: admin pode testar com ?test_oferta=1 na página do carrinho
            if ($allowTestMode && $perfil === 'admin' && !empty($_SESSION['oferta_gratuita_test_mode'])) {
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Determina a categoria predominante dos itens ativos do carrinho
     */
    public function getCategoriaPredominante(array $carrinho): ?int {
        $contagem = [];
        foreach ($carrinho as $item) {
            $produtoId = (int) ($item['produto_id'] ?? 0);
            if ($produtoId <= 0) continue;

            // Verificar se item está ativo
            $ativo = (int) ($item['ativo'] ?? 1);
            if (!$ativo) continue;

            try {
                $stmt = $this->connection->prepare('SELECT category_id FROM produtos WHERE id = ? LIMIT 1');
                $stmt->execute([$produtoId]);
                $catId = (int) ($stmt->fetchColumn() ?: 0);
                if ($catId > 0) {
                    $contagem[$catId] = ($contagem[$catId] ?? 0) + (int) ($item['quantidade'] ?? 1);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (empty($contagem)) return null;

        $maxCount = max($contagem);
        $empatadas = array_keys(array_filter($contagem, fn($c) => $c === $maxCount));

        // Se empate, escolher aleatoriamente
        return $empatadas[array_rand($empatadas)];
    }

    /**
     * Busca um produto gratuito elegível aleatório da categoria, excluindo produtos já no carrinho
     */
    public function sortearProdutoGratuito(?int $categoriaId, array $produtoIdsNoCarrinho = []): ?array {
        if ($categoriaId === null || $categoriaId <= 0) return null;

        $cols = $this->getTableColumns('produtos');
        if (!in_array('elegivel_oferta_gratis', $cols, true)) return null;

        try {
            $sql = "
                SELECT id, name, price, weight, stock, category_id, foto_principal
                FROM produtos 
                WHERE elegivel_oferta_gratis = 1 
                  AND category_id = ? 
                  AND active = 1 
                  AND status = 'published'
                  AND stock > 0
                  AND weight >= 0.5
                  AND (grupo_compras_id IS NULL OR grupo_compras_id = 0)
            ";
            $params = [$categoriaId];

            // Excluir produtos que já estão no carrinho
            if (!empty($produtoIdsNoCarrinho)) {
                $placeholders = implode(',', array_fill(0, count($produtoIdsNoCarrinho), '?'));
                $sql .= " AND id NOT IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $produtoIdsNoCarrinho));
            }

            $sql .= " ORDER BY RAND() LIMIT 1";

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Registra a ação do usuário (aceita/recusada/removida)
     */
    public function registrarAcao(int $usuarioId, ?int $produtoId, ?int $categoriaId, string $acao, ?int $carrinhoId = null, ?int $pedidoId = null): bool {
        try {
            if (!$this->tableExists($this->table)) return false;
            $stmt = $this->connection->prepare("
                INSERT INTO {$this->table} (usuario_id, produto_id, categoria_id, acao, carrinho_id, pedido_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$usuarioId, $produtoId, $categoriaId, $acao, $carrinhoId, $pedidoId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Lista produtos elegíveis para oferta gratuita (admin)
     */
    public function listarProdutosElegiveis(int $page = 1, int $perPage = 50): array {
        $cols = $this->getTableColumns('produtos');
        if (!in_array('elegivel_oferta_gratis', $cols, true)) return ['items' => [], 'total' => 0];

        try {
            $offset = ($page - 1) * $perPage;

            $stmtCount = $this->connection->query("SELECT COUNT(*) FROM produtos WHERE elegivel_oferta_gratis = 1");
            $total = (int) $stmtCount->fetchColumn();

            $stmt = $this->connection->prepare("
                SELECT p.id, p.name, p.price, p.stock, p.weight, p.active, p.status, p.category_id, p.foto_principal,
                       c.name AS categoria_nome
                FROM produtos p
                LEFT JOIN categorias c ON c.id = p.category_id
                WHERE p.elegivel_oferta_gratis = 1
                ORDER BY p.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'total' => $total,
            ];
        } catch (\Exception $e) {
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Sincroniza produtos do site (sem grupo de compras) com peso >= 500g como elegíveis.
     * Retorna array com contadores de adicionados e removidos.
     */
    public function sincronizarProdutosSite(): array {
        $cols = $this->getTableColumns('produtos');
        if (!in_array('elegivel_oferta_gratis', $cols, true)) {
            return ['adicionados' => 0, 'removidos' => 0, 'total' => 0];
        }

        $adicionados = 0;
        $removidos = 0;

        try {
            // Marcar como elegíveis: produtos do site (sem grupo de compras), ativos, publicados, peso >= 0.5 kg, com estoque
            $stmt = $this->connection->prepare("
                UPDATE produtos 
                SET elegivel_oferta_gratis = 1 
                WHERE (grupo_compras_id IS NULL OR grupo_compras_id = 0)
                  AND active = 1 
                  AND status = 'published'
                  AND weight >= 0.5
                  AND stock > 0
                  AND elegivel_oferta_gratis = 0
            ");
            $stmt->execute();
            $adicionados = $stmt->rowCount();

            // Remover elegibilidade de produtos que não atendem mais os critérios
            // (ficaram inativos, mudaram de peso, foram vinculados a grupo de compras, sem estoque, etc.)
            $stmt = $this->connection->prepare("
                UPDATE produtos 
                SET elegivel_oferta_gratis = 0 
                WHERE elegivel_oferta_gratis = 1
                  AND (
                      (grupo_compras_id IS NOT NULL AND grupo_compras_id > 0)
                      OR active != 1
                      OR status != 'published'
                      OR weight < 0.5
                      OR stock <= 0
                  )
            ");
            $stmt->execute();
            $removidos = $stmt->rowCount();

            // Contar total atual
            $stmtTotal = $this->connection->query("SELECT COUNT(*) FROM produtos WHERE elegivel_oferta_gratis = 1");
            $total = (int) $stmtTotal->fetchColumn();

            return ['adicionados' => $adicionados, 'removidos' => $removidos, 'total' => $total];
        } catch (\Exception $e) {
            return ['adicionados' => 0, 'removidos' => 0, 'total' => 0, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Ativa/desativa elegibilidade de um produto
     */
    public function toggleElegibilidade(int $produtoId, bool $elegivel): bool {
        $cols = $this->getTableColumns('produtos');
        if (!in_array('elegivel_oferta_gratis', $cols, true)) return false;

        try {
            $stmt = $this->connection->prepare("UPDATE produtos SET elegivel_oferta_gratis = ? WHERE id = ?");
            return $stmt->execute([$elegivel ? 1 : 0, $produtoId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica se pode mostrar oferta para o usuário atual
     */
    public function podeExibirOferta(int $usuarioId, array $carrinho): bool {
        if (!$this->isOfertaGlobalAtiva()) return false;
        if (!$this->isSessaoOrganica(true)) return false;
        if ($this->usuarioJaRecebeuOferta($usuarioId)) return false;
        if (empty($carrinho)) return false;

        $categoriaId = $this->getCategoriaPredominante($carrinho);
        if ($categoriaId === null) return false;

        $produtoIdsNoCarrinho = [];
        foreach ($carrinho as $item) {
            $pid = (int) ($item['produto_id'] ?? 0);
            if ($pid > 0) $produtoIdsNoCarrinho[] = $pid;
        }

        $produto = $this->sortearProdutoGratuito($categoriaId, $produtoIdsNoCarrinho);
        return ($produto !== null);
    }
}
