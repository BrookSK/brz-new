<?php
/**
 * Template inline de etiqueta Correios Brasil (novo layout padrão)
 * Usado dentro de imprimirTodasEtiquetas (sem wrapper HTML completo)
 * Variáveis: $uid, $codigo, $codigoFormatado, $servicoLabel, $contrato,
 *   $pesoGramas, $pedidoId, $destNome, $destEndereco, $destBairro, $destCidade,
 *   $destUf, $destCep, $remNome, $remEndereco, $remCidade, $remUf, $remCep,
 *   $remCnpj, $servicosAdicionais, $datamatrixContent, $simboloEncaminhamento
 */
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$simbolo = $simboloEncaminhamento ?? 'sedex';
?>
<style>
.et-<?= $uid ?>{width:138mm;min-height:106mm;border:1.5px solid #000;display:flex;flex-direction:column;font-family:Arial,sans-serif;font-size:9pt;color:#000;background:#fff;margin:0 auto;box-sizing:border-box}
.et-<?= $uid ?> .hd{display:flex;align-items:center;justify-content:space-between;padding:3mm 5mm 2mm;border-bottom:1.5px solid #000;min-height:28mm}
.et-<?= $uid ?> .hd .lg svg{width:25mm;height:auto}
.et-<?= $uid ?> .hd .dm canvas{width:25mm;height:25mm}
.et-<?= $uid ?> .hd .sm svg{width:20mm;height:15mm}
.et-<?= $uid ?> .inf{display:flex;justify-content:space-between;padding:1.5mm 5mm;border-bottom:1.5px solid #000;font-size:8pt;line-height:1.4}
.et-<?= $uid ?> .inf .cc{text-align:center;font-weight:bold;font-size:9pt}
.et-<?= $uid ?> .inf .cr{text-align:right}
.et-<?= $uid ?> .trk{padding:2mm 5mm;border-bottom:1.5px solid #000}
.et-<?= $uid ?> .trk .ct{font-size:11pt;font-weight:bold;letter-spacing:1px;text-align:center;margin-bottom:1mm}
.et-<?= $uid ?> .trk .br{display:flex;align-items:center;gap:3mm}
.et-<?= $uid ?> .trk .br svg{flex:1;height:15mm}
.et-<?= $uid ?> .trk .sa{font-size:9pt;font-weight:bold;text-align:right;line-height:1.3;white-space:nowrap}
.et-<?= $uid ?> .rcv{padding:2mm 5mm;border-bottom:1.5px solid #000;font-size:8pt;line-height:2}
.et-<?= $uid ?> .rcv .f{display:flex;align-items:baseline;gap:1mm}
.et-<?= $uid ?> .rcv .f .l{font-weight:bold;white-space:nowrap}
.et-<?= $uid ?> .rcv .f .v{flex:1;border-bottom:0.5px solid #000;min-height:4mm}
.et-<?= $uid ?> .rcv .fr{display:flex;gap:5mm}
.et-<?= $uid ?> .rcv .fr .f{flex:1}
.et-<?= $uid ?> .dst{border-bottom:1.5px dashed #666;flex:1;display:flex;flex-direction:column}
.et-<?= $uid ?> .dst .dh{display:flex;align-items:center;justify-content:space-between;background:#000;color:#fff;padding:1mm 5mm;font-size:9pt;font-weight:bold}
.et-<?= $uid ?> .dst .dd{padding:2mm 5mm;flex:1;display:flex;gap:3mm}
.et-<?= $uid ?> .dst .dd .dt{flex:1}
.et-<?= $uid ?> .dst .dd .dt .dn{font-size:10pt;font-weight:bold;margin-bottom:0.5mm}
.et-<?= $uid ?> .dst .dd .dt .de{font-size:9pt;line-height:1.4}
.et-<?= $uid ?> .dst .dd .dt .dc{font-size:11pt;font-weight:900;margin-top:1mm}
.et-<?= $uid ?> .dst .dd .bc svg{width:40mm;height:15mm}
.et-<?= $uid ?> .rem{padding:2mm 5mm;font-size:8pt;line-height:1.4}
.et-<?= $uid ?> .rem .rt{font-weight:bold}
.et-<?= $uid ?> .rem .rc{font-weight:bold;margin-top:0.5mm}
</style>

<div class="et-<?= $uid ?>">
  <div class="hd">
    <div class="lg"><img src="/assets/img/correiosLogoDeitado.png" alt="Correios" style="width:25mm;height:auto;"></div>
    <div class="dm"><canvas id="dm-<?= $uid ?>"></canvas></div>
    <div class="sm"><img src="/assets/img/icones_guia_sedex_amarelo_130.png" alt="<?= htmlspecialchars(__('admin.shipment_correios.symbol','Símbolo'), ENT_QUOTES, 'UTF-8') ?>" style="width:20mm;height:auto;"></div>
  </div>
  <div class="inf">
    <div><?= __('admin.shipment_correios.nf','NF:') ?><br><?= __('admin.shipment_correios.order','Pedido:') ?> <strong><?= $pedidoId > 0 ? $pedidoId : '0' ?></strong></div>
    <div class="cc"><?= __('admin.shipment_correios.contract','Contrato:') ?> <strong><?= $h($contrato) ?></strong><br><strong><?= $h($servicoLabel) ?></strong></div>
    <div class="cr"><?= __('admin.shipment_correios.volume','Volume:') ?> 1/1<br><?= __('admin.shipment_correios.weight_g','Peso (g):') ?> <strong><?= $h($pesoGramas) ?></strong></div>
  </div>
  <div class="trk">
    <div class="ct"><?= $h($codigoFormatado) ?></div>
    <div class="br"><svg id="bc-<?= $uid ?>"></svg><div class="sa"><?php foreach($servicosAdicionais as $sa): ?><?= $h($sa) ?><br><?php endforeach; ?></div></div>
  </div>
  <div class="rcv">
    <div class="f"><span class="l"><?= __('admin.shipment_correios.receiver','Recebedor:') ?></span><span class="v"></span></div>
    <div class="fr"><div class="f"><span class="l"><?= __('admin.shipment_correios.signature','Assinatura:') ?></span><span class="v"></span></div><div class="f"><span class="l"><?= __('admin.shipment_correios.document','Documento:') ?></span><span class="v"></span></div></div>
  </div>
  <div class="dst">
    <div class="dh"><span><?= __('admin.shipment_correios.recipient','DESTINATÁRIO') ?></span><img src="/assets/img/correiosLogoDeitado.png" alt="Correios" style="height:4mm;width:auto;"></div>
    <div class="dd">
      <div class="dt">
        <div class="dn"><?= $h($destNome) ?></div>
        <div class="de"><?= $h($destEndereco) ?></div>
        <div class="de"><?= $h($destBairro) ?></div>
        <div class="dc"><?= $h($destCep) ?> <?= $h($destCidade . '/' . $destUf) ?></div>
      </div>
      <div class="bc"><svg id="cep-<?= $uid ?>"></svg></div>
    </div>
  </div>
  <div class="rem">
    <div class="rt"><?= __('admin.shipment_correios.sender','Remetente:') ?> <?= $h($remNome) ?></div>
    <div><?= $h($remEndereco) ?></div>
    <div class="rc"><?= $h($remCep) ?> <?= $h($remCidade . '/' . $remUf) ?></div>
    <?php if (!empty($remCnpj)): ?><div><?= __('admin.shipment_correios.cnpj','CNPJ:') ?> <?= $h($remCnpj) ?></div><?php endif; ?>
  </div>
</div>
<script>
(function(){
  var u=<?= json_encode((string)$uid) ?>,c=<?= json_encode($codigo) ?>,cep=<?= json_encode(preg_replace('/\D+/','',$destCep)) ?>,dm=<?= json_encode($datamatrixContent) ?>;
  try{JsBarcode('#bc-'+u,c,{format:'CODE128',displayValue:false,margin:5,height:55,width:2})}catch(e){}
  if(cep&&cep.length>=5){try{JsBarcode('#cep-'+u,cep,{format:'CODE128C',displayValue:false,margin:5,height:50,width:1.8})}catch(e){}}
  if(dm){try{bwipjs.toCanvas('dm-'+u,{bcid:'datamatrix',text:dm,scale:3,padding:3})}catch(e){}}
})();
</script>
