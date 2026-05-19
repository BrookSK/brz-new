<?php
/**
 * Template inline de etiqueta Correios Brasil (novo layout padrão)
 * Usado dentro de imprimirTodasEtiquetas (sem wrapper HTML)
 * Variáveis esperadas: $uid, $codigo, $codigoFormatado, $servicoLabel, $contrato,
 *   $pesoGramas, $pedidoId, $destNome, $destEndereco, $destBairro, $destCidade,
 *   $destUf, $destCep, $remNome, $remEndereco, $remCidade, $remUf, $remCep,
 *   $remCnpj, $servicosAdicionais, $datamatrixContent
 */
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<style>
.label-<?= $uid ?> {
  width: 100mm; min-height: 140mm;
  border: 1.5px solid #000;
  display: flex; flex-direction: column;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8pt; color: #000; background: #fff;
  margin: 0 auto; box-sizing: border-box;
}
.label-<?= $uid ?> .lbl-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 3mm 4mm 2mm 4mm; border-bottom: 1.5px solid #000;
}
.label-<?= $uid ?> .lbl-header .logo-c svg { width: 28mm; height: auto; }
.label-<?= $uid ?> .lbl-header .dm canvas { width: 18mm; height: 18mm; }
.label-<?= $uid ?> .lbl-header .simb svg { width: 22mm; height: auto; }
.label-<?= $uid ?> .lbl-info {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.5mm 4mm; border-bottom: 1.5px solid #000; font-size: 7pt; line-height: 1.3;
}
.label-<?= $uid ?> .lbl-info .svc { font-weight: bold; font-size: 8pt; text-align: center; }
.label-<?= $uid ?> .lbl-track {
  padding: 2mm 4mm; border-bottom: 1.5px solid #000; text-align: center;
}
.label-<?= $uid ?> .lbl-track .code { font-size: 11pt; font-weight: bold; letter-spacing: 1.5px; margin-bottom: 1mm; }
.label-<?= $uid ?> .lbl-track .bc-row { display: flex; align-items: center; justify-content: center; gap: 3mm; }
.label-<?= $uid ?> .lbl-track .bc-row svg { width: 70mm; height: 14mm; }
.label-<?= $uid ?> .lbl-track .sa { font-size: 7pt; font-weight: bold; text-align: right; line-height: 1.4; }
.label-<?= $uid ?> .lbl-recv {
  padding: 2mm 4mm; border-bottom: 1.5px solid #000; font-size: 7.5pt; line-height: 1.8;
}
.label-<?= $uid ?> .lbl-recv .fl { display: flex; align-items: baseline; gap: 1mm; }
.label-<?= $uid ?> .lbl-recv .fl .flab { font-weight: bold; white-space: nowrap; }
.label-<?= $uid ?> .lbl-recv .fl .fval { flex: 1; border-bottom: 0.5px solid #000; min-height: 4mm; }
.label-<?= $uid ?> .lbl-recv .fr { display: flex; gap: 4mm; }
.label-<?= $uid ?> .lbl-recv .fr .fl { flex: 1; }
.label-<?= $uid ?> .lbl-dest { border-bottom: 1.5px dashed #000; flex: 1; }
.label-<?= $uid ?> .lbl-dest .dh {
  display: flex; align-items: center; justify-content: space-between;
  background: #000; color: #fff; padding: 1mm 4mm; font-size: 8pt; font-weight: bold;
}
.label-<?= $uid ?> .lbl-dest .db { padding: 2mm 4mm; }
.label-<?= $uid ?> .lbl-dest .db .dn { font-size: 9pt; font-weight: bold; line-height: 1.3; margin-bottom: 0.5mm; }
.label-<?= $uid ?> .lbl-dest .db .de { font-size: 8pt; line-height: 1.4; }
.label-<?= $uid ?> .lbl-dest .db .dc { font-size: 10pt; font-weight: 900; margin-top: 1mm; }
.label-<?= $uid ?> .lbl-dest .db .dcb { margin-top: 1.5mm; }
.label-<?= $uid ?> .lbl-dest .db .dcb svg { width: 40mm; height: 10mm; }
.label-<?= $uid ?> .lbl-rem { padding: 2mm 4mm; font-size: 7pt; line-height: 1.4; }
.label-<?= $uid ?> .lbl-rem .rh { font-weight: bold; font-size: 7.5pt; margin-bottom: 0.5mm; }
.label-<?= $uid ?> .lbl-rem .rc { font-weight: bold; margin-top: 0.5mm; }
</style>

<div class="label-<?= $uid ?>">
  <div class="lbl-header">
    <div class="logo-c">
      <svg viewBox="0 0 120 45" xmlns="http://www.w3.org/2000/svg">
        <polygon points="18,8 30,22 18,36 6,22" fill="#8c8c8c"/>
        <polygon points="28,8 40,22 28,36 16,22" fill="#555"/>
        <text x="0" y="44" font-family="Arial" font-weight="900" font-size="14" fill="#333">Correios</text>
      </svg>
    </div>
    <div class="dm"><canvas id="dm-<?= $uid ?>"></canvas></div>
    <div class="simb">
      <svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,50 A40,40 0 0,1 80,50" fill="#f7a800" opacity="0.6"/>
        <path d="M10,50 A30,30 0 0,1 70,50" fill="#f7a800"/>
      </svg>
    </div>
  </div>

  <div class="lbl-info">
    <div><span>Contrato:</span> <strong><?= $h($contrato) ?></strong><br><span>Pedido:</span> <strong><?= $pedidoId > 0 ? $pedidoId : '' ?></strong></div>
    <div class="svc"><?= $h($servicoLabel) ?> CONTRATO<br>AG</div>
    <div style="text-align:right">Peso(g): <?= $h($pesoGramas) ?><br>Volume: 1/1</div>
  </div>

  <div class="lbl-track">
    <div class="code"><?= $h($codigoFormatado) ?></div>
    <div class="bc-row">
      <svg id="bc-<?= $uid ?>"></svg>
      <div class="sa"><?php foreach ($servicosAdicionais as $sa): ?><?= $h($sa) ?><br><?php endforeach; ?></div>
    </div>
  </div>

  <div class="lbl-recv">
    <div class="fl"><span class="flab">Recebedor:</span><span class="fval"></span></div>
    <div class="fr">
      <div class="fl"><span class="flab">Assinatura:</span><span class="fval"></span></div>
      <div class="fl"><span class="flab">Documento:</span><span class="fval"></span></div>
    </div>
  </div>

  <div class="lbl-dest">
    <div class="dh"><span>DESTINATÁRIO</span><span>✉ Correios</span></div>
    <div class="db">
      <div class="dn"><?= $h($destNome) ?></div>
      <div class="de"><?= $h($destEndereco) ?></div>
      <div class="de"><?= $h($destBairro) ?></div>
      <div class="dc"><strong><?= $h($destCep) ?></strong> <?= $h($destCidade . '/' . $destUf) ?></div>
      <div class="dcb"><svg id="cep-<?= $uid ?>"></svg></div>
    </div>
  </div>

  <div class="lbl-rem">
    <div class="rh">REMETENTE: <?= $h($remNome) ?></div>
    <div><?= $h($remEndereco) ?></div>
    <div class="rc"><?= $h($remCep) ?> <?= $h($remCidade . '/' . $remUf) ?></div>
    <?php if (!empty($remCnpj)): ?><div>CNPJ: <?= $h($remCnpj) ?></div><?php endif; ?>
  </div>
</div>

<script>
(function(){
  var uid = <?= json_encode((string)$uid) ?>;
  var code = <?= json_encode($codigo) ?>;
  var cep = <?= json_encode(preg_replace('/\D+/', '', $destCep)) ?>;
  var dmContent = <?= json_encode($datamatrixContent) ?>;

  try { JsBarcode('#bc-'+uid, code, {format:'CODE128',displayValue:false,margin:0,height:50,width:1.8}); } catch(e){}
  if (cep && cep.length >= 5) { try { JsBarcode('#cep-'+uid, cep, {format:'CODE128',displayValue:false,margin:0,height:35,width:1.5}); } catch(e){} }
  try { bwipjs.toCanvas('dm-'+uid, {bcid:'datamatrix',text:dmContent,scale:3,padding:2}); } catch(e){}
})();
</script>
