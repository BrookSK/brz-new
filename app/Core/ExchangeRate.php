<?php
namespace App\Core;

/**
 * Helper centralizado para taxa de câmbio USD → BRL.
 * 
 * Fonte única de verdade: configuracoes_sistema (categoria='sistema', chave='usd_brl_rate')
 * Fallback: tabela configuracoes_moeda (moeda_origem='USD', moeda_destino='BRL')
 * Fallback final: 5.85 (valor padrão caso nada esteja configurado)
 * 
 * Uso:
 *   $taxa = \App\Core\ExchangeRate::getUsdToBrl();
 *   $valorBrl = \App\Core\ExchangeRate::convertUsdToBrl(197.77);
 */
class ExchangeRate
{
    private static ?float $cached = null;

    /**
     * Retorna a taxa de conversão USD → BRL configurada no sistema.
     * Resultado é cacheado em memória durante o request.
     */
    public static function getUsdToBrl(): float
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $taxa = 5.85; // Fallback final

        try {
            $db = \Config\Database::getConnection();

            // 1. Buscar em configuracoes_sistema (campo centralizado)
            $keys = ['sistema_usd_brl_rate', 'usd_brl_rate'];
            foreach ($keys as $key) {
                try {
                    $st = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
                    $st->execute([$key]);
                    $v = $st->fetchColumn();
                    if ($v !== false && $v !== null && $v !== '') {
                        $val = (float) str_replace(',', '.', (string) $v);
                        if ($val > 1.0) {
                            self::$cached = $val;
                            return self::$cached;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // 2. Fallback: tabela configuracoes_moeda
            try {
                $st = $db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                $row = $st ? $st->fetch(\PDO::FETCH_ASSOC) : null;
                if (is_array($row) && isset($row['taxa_conversao'])) {
                    $val = (float) $row['taxa_conversao'];
                    if ($val > 1.0) {
                        $taxa = $val;
                    }
                }
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
        }

        self::$cached = $taxa;
        return self::$cached;
    }

    /**
     * Converte um valor de USD para BRL usando a taxa configurada.
     */
    public static function convertUsdToBrl(float $usd): float
    {
        return round($usd * self::getUsdToBrl(), 2);
    }

    /**
     * Converte um valor de BRL para USD usando a taxa configurada.
     */
    public static function convertBrlToUsd(float $brl): float
    {
        $taxa = self::getUsdToBrl();
        if ($taxa <= 0) return 0.0;
        return round($brl / $taxa, 2);
    }

    /**
     * Limpa o cache em memória (útil em testes ou após atualizar a config).
     */
    public static function reset(): void
    {
        self::$cached = null;
    }
}
