<?php
/**
 * Calcula AWB & Transporte e Last Mile Brasil para o DRE
 * Incluído pelo AdminRelatorioGeralController
 */
function calcularAwbELastMile(\PDO $db, array $cols, string $dateStart, string $dateEnd, float $taxaUsdBrl): array {
    $awbTransporteUsd = 0;
    $lastMileBrl = 0;
    
    try {
        $itensTable = null;
        try { $db->query("SELECT 1 FROM pedido_itens LIMIT 1"); $itensTable = 'pedido_itens'; } catch (\Exception $e) {
            try { $db->query("SELECT 1 FROM pedido_items LIMIT 1"); $itensTable = 'pedido_items'; } catch (\Exception $e2) {}
        }
        if (!$itensTable) return ['awbTransporteUsd' => 0, 'awbTransporteBrl' => 0, 'lastMileBrl' => 0];

        $colsIt = []; try { $st = $db->query("DESCRIBE {$itensTable}"); $colsIt = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        $colsProd = []; try { $st = $db->query("DESCRIBE produtos"); $colsProd = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}
        
        $colPeso = in_array('weight', $colsProd, true) ? 'weight' : (in_array('peso', $colsProd, true) ? 'peso' : '');
        $cQtd = in_array('quantidade', $colsIt, true) ? 'quantidade' : 'quantidade';
        $cProdId = in_array('produto_id', $colsIt, true) ? 'produto_id' : 'product_id';
        $delF = in_array('deleted_at', $cols, true) ? "AND p.deleted_at IS NULL" : "";
        
        if (!$colPeso) return ['awbTransporteUsd' => 0, 'awbTransporteBrl' => 0, 'lastMileBrl' => 0];

        // AWB: peso total * $4.80/kg
        $sql = "SELECT COALESCE(SUM(prod.{$colPeso} * i.{$cQtd}), 0) FROM {$itensTable} i INNER JOIN pedidos p ON p.id = i.pedido_id INNER JOIN produtos prod ON prod.id = i.{$cProdId} WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY) AND LOWER(COALESCE(p.status,'')) NOT IN ('apagado','deleted','lixeira','trash','cancelado','cancelled') {$delF}";
        $st = $db->prepare($sql);
        $st->execute([$dateStart, $dateEnd]);
        $pesoTotal = (float)($st->fetchColumn() ?: 0);
        $awbTransporteUsd = $pesoTotal * 4.80;

        // Last Mile Brasil: peso de pedidos BR * R$10/kg
        $colPais = '';
        foreach (['pais_entrega','pais','country','shipping_country','pais_destino'] as $cp) {
            if (in_array($cp, $cols, true)) { $colPais = $cp; break; }
        }
        if ($colPais) {
            $sqlBr = "SELECT COALESCE(SUM(prod.{$colPeso} * i.{$cQtd}), 0) FROM {$itensTable} i INNER JOIN pedidos p ON p.id = i.pedido_id INNER JOIN produtos prod ON prod.id = i.{$cProdId} WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY) AND LOWER(COALESCE(p.status,'')) NOT IN ('apagado','deleted','lixeira','trash','cancelado','cancelled') {$delF} AND UPPER(COALESCE(p.{$colPais},'')) IN ('BR','BRASIL','BRAZIL')";
            $stBr = $db->prepare($sqlBr);
            $stBr->execute([$dateStart, $dateEnd]);
            $pesoBr = (float)($stBr->fetchColumn() ?: 0);
            $lastMileBrl = $pesoBr * 10.0;
        }
    } catch (\Throwable $e) {}

    return [
        'awbTransporteUsd' => $awbTransporteUsd,
        'awbTransporteBrl' => $awbTransporteUsd * $taxaUsdBrl,
        'lastMileBrl' => $lastMileBrl,
    ];
}
