<?php
namespace App\Services;

/**
 * Serviço de Brindes Vinculados a Produtos
 * 
 * Quando um produto com brinde ativo é adicionado ao carrinho:
 * - O brinde entra com preço = $0
 * - Taxa de serviço e impostos são cobrados normalmente
 * - Após pagamento aprovado, o valor dos impostos BR (II + ICMS) do brinde é devolvido na carteira
 * - Imposto local EUA NÃO é devolvido
 */
class BrindeService {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    /**
     * Verifica se um produto tem brinde ativo no momento
     * Retorna array com dados do brinde ou null
     */
    public function getBrindeAtivo(int $produtoId): ?array {
        try {
            $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $st = $this->db->prepare("
                SELECT pb.*, p.name as brinde_nome, p.sku as brinde_sku, p.price as brinde_preco,
                       COALESCE(p.weight, 0) as brinde_peso
                FROM produto_brindes pb
                JOIN produtos p ON p.id = pb.brinde_produto_id
                WHERE pb.produto_id = ? AND pb.ativo = 1
                AND pb.data_inicio <= ? AND pb.data_fim >= ?
                ORDER BY pb.id DESC LIMIT 1
            ");
            $st->execute([$produtoId, $now, $now]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            error_log("[BRINDE] getBrindeAtivo produto={$produtoId} now={$now} encontrado=" . ($row ? 'SIM (id=' . $row['id'] . ')' : 'NAO'));
            return $row ?: null;
        } catch (\Exception $e) {
            error_log('[BRINDE] Erro ao buscar brinde ativo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calcula o valor dos impostos BR (II + ICMS) para o brinde
     * Usa o preço original do produto brinde como base para cálculo de impostos
     */
    public function calcularImpostosBrinde(float $precoBrindeUsd): float {
        if ($precoBrindeUsd <= 0) return 0.0;

        // Buscar configurações
        $certificado = true; // Braziliana é certificada Remessa Conforme
        $aliqIcms = 0.17;

        try {
            $st = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('icms_aliquota', 'remessa_conforme_certificado')");
            $st->execute();
            $configs = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($configs as $cfg) {
                $k = (string) ($cfg['chave'] ?? '');
                $v = str_replace(',', '.', trim((string) ($cfg['valor'] ?? '')));
                if ($k === 'icms_aliquota' && $v !== '') {
                    $a = (float) $v;
                    if ($a > 0 && $a <= 1.0) $a = $a * 100.0;
                    if ($a > 0 && $a < 100) $aliqIcms = $a / 100.0;
                }
                if ($k === 'remessa_conforme_certificado' && $v !== '') {
                    $certificado = in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
                }
            }
        } catch (\Exception $e) {}

        // Valor aduaneiro (sem frete, sem seguro para brinde)
        $valorAduaneiro = $precoBrindeUsd;

        // II (Imposto de Importação)
        $ii = 0.0;
        if ($certificado) {
            if ($valorAduaneiro <= 50) {
                $ii = $valorAduaneiro * 0.20;
            } else {
                $ii = max(0, ($valorAduaneiro * 0.60) - 20);
            }
        } else {
            $ii = $valorAduaneiro * 0.60;
        }

        // ICMS (por dentro)
        $baseIcms = ($valorAduaneiro + $ii) / (1 - $aliqIcms);
        $icms = $baseIcms * $aliqIcms;

        return round($ii + $icms, 2);
    }

    /**
     * Registra a devolução pendente de impostos do brinde
     */
    public function registrarDevolucaoPendente(int $pedidoId, int $usuarioId, int $produtoBrindeId, float $valorImpostoUsd, ?float $valorImpostoBrl = null, ?int $pedidoItemId = null): int {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS brinde_devolucao_impostos (
                id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL, pedido_item_id INT NULL,
                usuario_id INT NOT NULL, produto_brinde_id INT NOT NULL,
                valor_imposto_devolvido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_imposto_devolvido_brl DECIMAL(10,2) NULL,
                status ENUM('pendente','devolvido','cancelado') NOT NULL DEFAULT 'pendente',
                devolvido_em DATETIME NULL, carteira_transacao_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bdi_pedido (pedido_id), INDEX idx_bdi_usuario (usuario_id), INDEX idx_bdi_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $st = $this->db->prepare("INSERT INTO brinde_devolucao_impostos (pedido_id, pedido_item_id, usuario_id, produto_brinde_id, valor_imposto_devolvido, valor_imposto_devolvido_brl, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')");
            $st->execute([$pedidoId, $pedidoItemId, $usuarioId, $produtoBrindeId, $valorImpostoUsd, $valorImpostoBrl]);
            return (int) $this->db->lastInsertId();
        } catch (\Exception $e) {
            error_log('[BRINDE] Erro ao registrar devolução: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Processa devoluções pendentes para um pedido (chamado quando pagamento é aprovado)
     */
    public function processarDevolucoesParaPedido(int $pedidoId): array {
        $resultado = ['processados' => 0, 'valor_total' => 0.0, 'erros' => []];

        try {
            $st = $this->db->prepare("SELECT * FROM brinde_devolucao_impostos WHERE pedido_id = ? AND status = 'pendente'");
            $st->execute([$pedidoId]);
            $pendentes = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            if (empty($pendentes)) return $resultado;

            foreach ($pendentes as $dev) {
                try {
                    $usuarioId = (int) $dev['usuario_id'];
                    $valor = (float) $dev['valor_imposto_devolvido'];
                    if ($valor <= 0 || $usuarioId <= 0) continue;

                    // Creditar na carteira do usuário
                    $transacaoId = $this->creditarCarteira($usuarioId, $valor, 'Devolução imposto brinde - Pedido #' . $pedidoId);

                    // Marcar como devolvido
                    $stUpd = $this->db->prepare("UPDATE brinde_devolucao_impostos SET status = 'devolvido', devolvido_em = NOW(), carteira_transacao_id = ? WHERE id = ?");
                    $stUpd->execute([$transacaoId, (int) $dev['id']]);

                    $resultado['processados']++;
                    $resultado['valor_total'] += $valor;
                } catch (\Exception $e) {
                    $resultado['erros'][] = 'Dev #' . $dev['id'] . ': ' . $e->getMessage();
                    error_log('[BRINDE] Erro ao processar devolução #' . $dev['id'] . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $resultado['erros'][] = $e->getMessage();
            error_log('[BRINDE] Erro geral ao processar devoluções pedido #' . $pedidoId . ': ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Credita valor na carteira do usuário (USD)
     */
    private function creditarCarteira(int $usuarioId, float $valor, string $descricao): ?int {
        // Verificar se tabela carteira_transacoes existe
        $temCarteira = false;
        try {
            $st = $this->db->query("SHOW TABLES LIKE 'carteira_transacoes'");
            $temCarteira = (bool) ($st ? $st->fetchColumn() : false);
        } catch (\Exception $e) {}

        if (!$temCarteira) {
            // Fallback: usar tabela carrinhos/carteira simples
            try {
                $st = $this->db->query("SHOW TABLES LIKE 'carteiras'");
                $temCarteiras = (bool) ($st ? $st->fetchColumn() : false);
                if ($temCarteiras) {
                    // Atualizar saldo
                    $stSaldo = $this->db->prepare("UPDATE carteiras SET saldo_usd = saldo_usd + ? WHERE usuario_id = ?");
                    $stSaldo->execute([$valor, $usuarioId]);
                    if ($stSaldo->rowCount() === 0) {
                        $this->db->prepare("INSERT INTO carteiras (usuario_id, saldo_usd) VALUES (?, ?)")->execute([$usuarioId, $valor]);
                    }
                }
            } catch (\Exception $e) {}
            return null;
        }

        // Inserir transação na carteira
        $cols = [];
        try { $st = $this->db->query('DESCRIBE carteira_transacoes'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

        $data = [
            'usuario_id' => $usuarioId,
            'tipo' => 'credito',
            'valor' => $valor,
            'moeda' => 'USD',
            'descricao' => $descricao,
            'origem' => 'brinde_devolucao_imposto',
        ];

        $insCols = [];
        $insVals = [];
        $insPlaceholders = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $cols, true)) {
                $insCols[] = $k;
                $insVals[] = $v;
                $insPlaceholders[] = '?';
            }
        }
        if (in_array('created_at', $cols, true)) {
            $insCols[] = 'created_at';
            $insVals[] = date('Y-m-d H:i:s');
            $insPlaceholders[] = '?';
        }

        if (!empty($insCols)) {
            $st = $this->db->prepare('INSERT INTO carteira_transacoes (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $insPlaceholders) . ')');
            $st->execute($insVals);
            $transacaoId = (int) $this->db->lastInsertId();

            // Atualizar saldo na carteira
            try {
                $stSaldo = $this->db->prepare("UPDATE carteiras SET saldo_usd = saldo_usd + ? WHERE usuario_id = ?");
                $stSaldo->execute([$valor, $usuarioId]);
                if ($stSaldo->rowCount() === 0) {
                    $this->db->prepare("INSERT INTO carteiras (usuario_id, saldo_usd) VALUES (?, ?) ON DUPLICATE KEY UPDATE saldo_usd = saldo_usd + VALUES(saldo_usd)")->execute([$usuarioId, $valor]);
                }
            } catch (\Exception $e) {}

            return $transacaoId;
        }

        return null;
    }
}
