<?php
/**
 * Template de etiqueta Correios Brasil - Layout padrão conforme
 * Guia Técnico de Endereçamento de Encomendas dos Correios.
 *
 * Variáveis esperadas (definidas no controller antes do include):
 *   $codigo, $codigoFormatado, $servicoLabel, $contrato, $pesoGramas,
 *   $pedidoId, $destNome, $destEndereco, $destBairro, $destCidade,
 *   $destUf, $destCep, $remNome, $remEndereco, $remCidade, $remUf,
 *   $remCep, $remCnpj, $servicosAdicionais, $datamatrixContent,
 *   $simboloEncaminhamento (sedex|pac|sedex10|mini)
 */
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$simbolo = $simboloEncaminhamento ?? 'sedex';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Etiqueta <?= $h($codigo) ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bwip-js@4.3.0/dist/bwip-js-min.js"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: 100mm 150mm; margin: 0; }
body {
  width: 100mm; height: 150mm;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8pt; background: #fff; color: #000;
  overflow: hidden;
}
.label {
  width: 100mm; height: 150mm;
  border: 1.5px solid #000;
  display: flex; flex-direction: column;
  overflow: hidden;
}

/* ─── HEADER: Logo/Marca + DataMatrix + Símbolo ─── */
.lbl-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 2mm 4mm 1.5mm 4mm;
  border-bottom: 1.5px solid #000;
  min-height: 22mm;
}
.lbl-header .logo-correios {
  width: 22mm; display: flex; flex-direction: column; align-items: flex-start;
}
.lbl-header .logo-correios img { width: 22mm; height: auto; }
.lbl-header .datamatrix { text-align: center; }
.lbl-header .datamatrix canvas { width: 20mm; height: 20mm; }
.lbl-header .simbolo { width: 18mm; text-align: right; }
.lbl-header .simbolo img { width: 18mm; height: auto; }

/* ─── INFO SERVIÇO ─── */
.lbl-info {
  display: flex; justify-content: space-between; align-items: stretch;
  padding: 1mm 4mm;
  border-bottom: 1.5px solid #000;
  font-size: 7pt; line-height: 1.3;
}
.lbl-info .col-left { }
.lbl-info .col-center { text-align: center; font-weight: bold; font-size: 9pt; }
.lbl-info .col-right { text-align: right; }
.lbl-info strong { font-weight: bold; }

/* ─── CÓDIGO RASTREIO ─── */
.lbl-tracking {
  padding: 1.5mm 4mm;
  border-bottom: 1.5px solid #000;
}
.lbl-tracking .code-text {
  font-size: 10pt; font-weight: bold; letter-spacing: 1px;
  text-align: center; margin-bottom: 0.5mm;
}
.lbl-tracking .barcode-row {
  display: flex; align-items: center; gap: 2mm;
}
.lbl-tracking .barcode-row svg { flex: 1; height: 12mm; }
.lbl-tracking .servicos-adic {
  font-size: 8pt; font-weight: bold; text-align: right;
  line-height: 1.2; white-space: nowrap;
}

/* ─── RECEBEDOR ─── */
.lbl-recebedor {
  padding: 1.5mm 4mm;
  border-bottom: 1.5px solid #000;
  font-size: 7pt; line-height: 1.6;
}
.lbl-recebedor .field { display: flex; align-items: baseline; gap: 1mm; }
.lbl-recebedor .field .fl { font-weight: bold; white-space: nowrap; }
.lbl-recebedor .field .fv { flex: 1; border-bottom: 0.5px solid #000; min-height: 4mm; }
.lbl-recebedor .field-row { display: flex; gap: 5mm; }
.lbl-recebedor .field-row .field { flex: 1; }

/* ─── DESTINATÁRIO ─── */
.lbl-dest {
  border-bottom: 1.5px dashed #666;
  flex: 1; display: flex; flex-direction: column;
}
.lbl-dest .dest-header {
  display: flex; align-items: center; justify-content: space-between;
  background: #000; color: #fff;
  padding: 0.5mm 4mm; font-size: 8pt; font-weight: bold;
}
.lbl-dest .dest-header .correios-sm { font-size: 8pt; letter-spacing: 0.5px; }
.lbl-dest .dest-body {
  padding: 1.5mm 4mm; flex: 1;
  display: flex; flex-direction: column;
}
.lbl-dest .dest-body .dest-text { }
.lbl-dest .dest-body .dest-text .dn { font-size: 9pt; font-weight: bold; margin-bottom: 0.3mm; }
.lbl-dest .dest-body .dest-text .de { font-size: 8pt; line-height: 1.3; }
.lbl-dest .dest-body .dest-text .db { font-size: 8pt; }
.lbl-dest .dest-body .dest-text .dc { font-size: 9pt; font-weight: bold; margin-top: 0.5mm; }
.lbl-dest .dest-body .dest-barcode { margin-top: 1mm; }
.lbl-dest .dest-body .dest-barcode svg { width: 35mm; height: 12mm; }

/* ─── REMETENTE ─── */
.lbl-rem {
  padding: 1.5mm 4mm;
  font-size: 7pt; line-height: 1.3;
}
.lbl-rem .rem-title { font-weight: bold; }
.lbl-rem .rem-cep { font-weight: bold; margin-top: 0.5mm; }

/* ─── PRINT ─── */
@media print {
  body { margin: 0; }
  .no-print { display: none !important; }
}
.print-btn {
  position: fixed; top: 10px; right: 10px;
  background: #003087; color: #fff;
  border: none; padding: 10px 22px;
  border-radius: 6px; cursor: pointer;
  font-size: 14px; font-weight: bold; z-index: 9999;
}
.print-btn:hover { background: #001f5c; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">🖨 Imprimir</button>

<div class="label">

  <!-- HEADER: Logo Correios + DataMatrix (25x25mm) + Símbolo Encaminhamento -->
  <div class="lbl-header">
    <div class="logo-correios">
      <img src="/assets/img/correiosLogoDeitado.png" alt="Correios" style="width:22mm;height:auto;">
    </div>
    <div class="datamatrix">
      <canvas id="datamatrix"></canvas>
    </div>
    <div class="simbolo">
      <img src="/assets/img/icones_guia_sedex_amarelo_130.png" alt="Símbolo" style="width:18mm;height:auto;">
    </div>
  </div>

  <!-- INFO SERVIÇO: NF + Contrato + Serviço + Pedido + Volume + Peso -->
  <div class="lbl-info">
    <div class="col-left">
      NF:<br>
      Pedido: <strong><?= $pedidoId > 0 ? $pedidoId : '0' ?></strong>
    </div>
    <div class="col-center">
      Contrato: <strong><?= $h($contrato) ?></strong><br>
      <strong><?= $h($servicoLabel) ?></strong>
    </div>
    <div class="col-right">
      Volume: 1/1<br>
      Peso (g): <strong><?= $h($pesoGramas) ?></strong>
    </div>
  </div>

  <!-- CÓDIGO DE RASTREAMENTO + BARCODE (GS1-128, 90x15mm) -->
  <div class="lbl-tracking">
    <div class="code-text"><?= $h($codigoFormatado) ?></div>
    <div class="barcode-row">
      <svg id="barcode-tracking"></svg>
      <div class="servicos-adic">
        <?php foreach ($servicosAdicionais as $sa): ?>
          <?= $h($sa) ?><br>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- FORMULÁRIO RECEBEDOR -->
  <div class="lbl-recebedor">
    <div class="field">
      <span class="fl">Recebedor:</span>
      <span class="fv"></span>
    </div>
    <div class="field-row">
      <div class="field">
        <span class="fl">Assinatura:</span>
        <span class="fv"></span>
      </div>
      <div class="field">
        <span class="fl">Documento:</span>
        <span class="fv"></span>
      </div>
    </div>
  </div>

  <!-- DESTINATÁRIO -->
  <div class="lbl-dest">
    <div class="dest-header">
      <span>DESTINATÁRIO</span>
      <img src="/assets/img/correiosLogoDeitado.png" alt="Correios" style="height:4mm;width:auto;">
    </div>
    <div class="dest-body">
      <div class="dest-text">
        <div class="dn"><?= $h($destNome) ?></div>
        <div class="de"><?= $h($destEndereco) ?></div>
        <div class="db"><?= $h($destBairro) ?></div>
        <div class="dc"><?= $h($destCep) ?> <?= $h($destCidade . '/' . $destUf) ?></div>
      </div>
      <div class="dest-barcode">
        <svg id="barcode-cep"></svg>
      </div>
    </div>
  </div>

  <!-- REMETENTE -->
  <div class="lbl-rem">
    <div class="rem-title">Remetente: <?= $h($remNome) ?></div>
    <div><?= $h($remEndereco) ?></div>
    <div class="rem-cep"><?= $h($remCep) ?> <?= $h($remCidade . '/' . $remUf) ?></div>
    <?php if (!empty($remCnpj)): ?>
    <div>CNPJ: <?= $h($remCnpj) ?></div>
    <?php endif; ?>
  </div>

</div>

<script>
(function(){
  var trackingCode = <?= json_encode($codigo ?? '') ?>;
  var cep = <?= json_encode(preg_replace('/\D+/', '', $destCep ?? '')) ?>;
  var dmContent = <?= json_encode($datamatrixContent ?? '') ?>;

  // Barcode de rastreio - Code 128 (GS1-128), min 90x15mm
  try {
    JsBarcode('#barcode-tracking', trackingCode, {
      format: 'CODE128',
      displayValue: false,
      margin: 3,
      height: 45,
      width: 1.6
    });
  } catch(e){ console.error('Barcode tracking:', e); }

  // Barcode do CEP destino - Code 128C, min 40x15mm
  if (cep && cep.length >= 5) {
    try {
      JsBarcode('#barcode-cep', cep, {
        format: 'CODE128C',
        displayValue: false,
        margin: 3,
        height: 40,
        width: 1.5
      });
    } catch(e){ console.error('Barcode CEP:', e); }
  }

  // DataMatrix 25x25mm (usando bwip-js)
  if (dmContent) {
    try {
      bwipjs.toCanvas('datamatrix', {
        bcid: 'datamatrix',
        text: dmContent,
        scale: 3,
        padding: 3
      });
    } catch(e){ console.error('DataMatrix:', e); }
  }

  // Auto-print
  setTimeout(function(){ window.print(); }, 800);
})();
</script>
</body>
</html>
