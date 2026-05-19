<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Etiqueta <?= htmlspecialchars($codigo ?? '', ENT_QUOTES, 'UTF-8') ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bwip-js@4.3.0/dist/bwip-js-min.js"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: 100mm 140mm; margin: 0; }
body {
  width: 100mm; min-height: 140mm;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8pt; background: #fff; color: #000;
}
.label {
  width: 100mm; min-height: 140mm;
  border: 1.5px solid #000;
  display: flex; flex-direction: column;
}

/* ─── HEADER: Logo + DataMatrix + Símbolo ─── */
.header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 3mm 4mm 2mm 4mm;
  border-bottom: 1.5px solid #000;
}
.logo-correios svg { width: 28mm; height: auto; }
.datamatrix-container { text-align: center; }
.datamatrix-container canvas { width: 18mm; height: 18mm; }
.simbolo-encaminhamento svg { width: 22mm; height: auto; }

/* ─── INFO SERVIÇO ─── */
.info-servico {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.5mm 4mm;
  border-bottom: 1.5px solid #000;
  font-size: 7pt; line-height: 1.3;
}
.info-servico .contrato-label { font-weight: normal; }
.info-servico .contrato-valor { font-weight: bold; }
.info-servico .servico-tipo { font-weight: bold; font-size: 8pt; text-align: center; }
.info-servico .peso-volume { text-align: right; font-size: 7pt; }

/* ─── CÓDIGO RASTREIO + BARCODE ─── */
.tracking-section {
  padding: 2mm 4mm;
  border-bottom: 1.5px solid #000;
  text-align: center;
}
.tracking-code-text {
  font-size: 11pt; font-weight: bold; letter-spacing: 1.5px;
  margin-bottom: 1mm;
}
.tracking-barcode { display: flex; align-items: center; justify-content: center; gap: 3mm; }
.tracking-barcode svg { width: 70mm; height: 14mm; }
.servicos-adicionais { font-size: 7pt; font-weight: bold; text-align: right; line-height: 1.4; }

/* ─── RECEBEDOR ─── */
.recebedor-section {
  padding: 2mm 4mm;
  border-bottom: 1.5px solid #000;
  font-size: 7.5pt; line-height: 1.8;
}
.recebedor-section .field-line {
  display: flex; align-items: baseline; gap: 1mm;
}
.recebedor-section .field-label { font-weight: bold; white-space: nowrap; }
.recebedor-section .field-value { flex: 1; border-bottom: 0.5px solid #000; min-height: 4mm; }
.recebedor-section .field-row {
  display: flex; gap: 4mm;
}
.recebedor-section .field-row .field-line { flex: 1; }

/* ─── DESTINATÁRIO ─── */
.destinatario-section {
  padding: 0;
  border-bottom: 1.5px dashed #000;
  flex: 1;
}
.destinatario-header {
  display: flex; align-items: center; justify-content: space-between;
  background: #000; color: #fff;
  padding: 1mm 4mm; font-size: 8pt; font-weight: bold;
}
.destinatario-header .logo-correios-sm { font-size: 8pt; color: #fff; }
.destinatario-body {
  padding: 2mm 4mm 2mm 4mm;
}
.dest-nome { font-size: 9pt; font-weight: bold; line-height: 1.3; margin-bottom: 0.5mm; }
.dest-endereco { font-size: 8pt; line-height: 1.4; }
.dest-bairro { font-size: 8pt; margin-top: 0.5mm; }
.dest-cep-cidade {
  font-size: 10pt; font-weight: 900; margin-top: 1mm; letter-spacing: 0.5px;
}
.dest-barcode-cep { margin-top: 1.5mm; }
.dest-barcode-cep svg { width: 40mm; height: 10mm; }

/* ─── REMETENTE ─── */
.remetente-section {
  padding: 2mm 4mm;
  font-size: 7pt; line-height: 1.4;
}
.remetente-header {
  font-weight: bold; font-size: 7.5pt; margin-bottom: 0.5mm;
}
.rem-nome { font-weight: bold; }
.rem-cep { font-weight: bold; margin-top: 0.5mm; }

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

  <!-- HEADER: Logo Correios + DataMatrix + Símbolo Encaminhamento -->
  <div class="header">
    <div class="logo-correios">
      <svg viewBox="0 0 120 45" xmlns="http://www.w3.org/2000/svg">
        <polygon points="18,8 30,22 18,36 6,22" fill="#8c8c8c"/>
        <polygon points="28,8 40,22 28,36 16,22" fill="#555"/>
        <text x="0" y="44" font-family="Arial" font-weight="900" font-size="14" fill="#333">Correios</text>
      </svg>
    </div>
    <div class="datamatrix-container">
      <canvas id="datamatrix"></canvas>
    </div>
    <div class="simbolo-encaminhamento">
      <svg viewBox="0 0 80 50" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,50 A40,40 0 0,1 80,50" fill="#f7a800" opacity="0.6"/>
        <path d="M10,50 A30,30 0 0,1 70,50" fill="#f7a800"/>
      </svg>
    </div>
  </div>

  <!-- INFO SERVIÇO: Contrato + Tipo + Peso -->
  <div class="info-servico">
    <div>
      <span class="contrato-label">Contrato:</span> <span class="contrato-valor"><?= htmlspecialchars($contrato ?? '', ENT_QUOTES, 'UTF-8') ?></span><br>
      <span class="contrato-label">Pedido:</span> <span class="contrato-valor"><?= $pedidoId > 0 ? $pedidoId : '' ?></span>
    </div>
    <div class="servico-tipo"><?= htmlspecialchars($servicoLabel ?? '', ENT_QUOTES, 'UTF-8') ?> CONTRATO<br>AG</div>
    <div class="peso-volume">
      Peso(g): <?= htmlspecialchars($pesoGramas ?? '', ENT_QUOTES, 'UTF-8') ?><br>
      Volume: 1/1
    </div>
  </div>

  <!-- CÓDIGO DE RASTREIO + BARCODE -->
  <div class="tracking-section">
    <div class="tracking-code-text"><?= htmlspecialchars($codigoFormatado ?? $codigo ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="tracking-barcode">
      <svg id="barcode-tracking"></svg>
      <div class="servicos-adicionais">
        <?php if (!empty($servicosAdicionais)): ?>
          <?php foreach ($servicosAdicionais as $sa): ?>
            <?= htmlspecialchars($sa, ENT_QUOTES, 'UTF-8') ?><br>
          <?php endforeach; ?>
        <?php else: ?>
          VD XX
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RECEBEDOR / ASSINATURA / DOCUMENTO -->
  <div class="recebedor-section">
    <div class="field-line">
      <span class="field-label">Recebedor:</span>
      <span class="field-value"></span>
    </div>
    <div class="field-row">
      <div class="field-line">
        <span class="field-label">Assinatura:</span>
        <span class="field-value"></span>
      </div>
      <div class="field-line">
        <span class="field-label">Documento:</span>
        <span class="field-value"></span>
      </div>
    </div>
  </div>

  <!-- DESTINATÁRIO -->
  <div class="destinatario-section">
    <div class="destinatario-header">
      <span>DESTINATÁRIO</span>
      <span class="logo-correios-sm">✉ Correios</span>
    </div>
    <div class="destinatario-body">
      <div class="dest-nome"><?= htmlspecialchars($destNome ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dest-endereco"><?= htmlspecialchars($destEndereco ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dest-bairro"><?= htmlspecialchars($destBairro ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dest-cep-cidade"><strong><?= htmlspecialchars($destCep ?? '', ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(($destCidade ?? '') . '/' . ($destUf ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="dest-barcode-cep">
        <svg id="barcode-cep"></svg>
      </div>
    </div>
  </div>

  <!-- REMETENTE -->
  <div class="remetente-section">
    <div class="remetente-header">REMETENTE: <?= htmlspecialchars($remNome ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="remetente-body">
      <div><?= htmlspecialchars($remEndereco ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      <div class="rem-cep"><?= htmlspecialchars($remCep ?? '', ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(($remCidade ?? '') . '/' . ($remUf ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      <?php if (!empty($remCnpj)): ?>
      <div>CNPJ: <?= htmlspecialchars($remCnpj, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
(function(){
  var trackingCode = <?= json_encode($codigo ?? '') ?>;
  var cep = <?= json_encode(preg_replace('/\D+/', '', $destCep ?? '')) ?>;
  var datamatrixContent = <?= json_encode($datamatrixContent ?? $codigo ?? '') ?>;

  // Barcode de rastreio (Code 128)
  try {
    JsBarcode('#barcode-tracking', trackingCode, {
      format: 'CODE128',
      displayValue: false,
      margin: 0,
      height: 50,
      width: 1.8
    });
  } catch(e){ console.error('Barcode tracking error:', e); }

  // Barcode do CEP (Code 128)
  if (cep && cep.length >= 5) {
    try {
      JsBarcode('#barcode-cep', cep, {
        format: 'CODE128',
        displayValue: false,
        margin: 0,
        height: 35,
        width: 1.5
      });
    } catch(e){ console.error('Barcode CEP error:', e); }
  }

  // DataMatrix (usando bwip-js)
  try {
    bwipjs.toCanvas('datamatrix', {
      bcid: 'datamatrix',
      text: datamatrixContent,
      scale: 3,
      padding: 2
    });
  } catch(e){ console.error('DataMatrix error:', e); }

  // Auto-print
  setTimeout(function(){ window.print(); }, 800);
})();
</script>
</body>
</html>
