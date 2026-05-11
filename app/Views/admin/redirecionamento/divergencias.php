<?php
$sidebarActive = 'redirecionamento-divergencias';
$title = 'Divergências e Ajustes';
$divergencias = is_array($divergencias ?? null) ? $divergencias : [];
$_perfilDiv = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
$_isAdminDiv = in_array($_perfilDiv, ['admin', 'suporte'], true);
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Divergências e Ajustes</h1>
            <div class="text-muted small"><?= count($divergencias) ?> divergência(s) ativa(s)</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Envio</th>
                            <th>Redirecionador</th>
                            <th>Valor pago</th>
                            <th>Valor correto</th>
                            <th>Diferença</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($divergencias)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma divergência encontrada.</td></tr>
                        <?php else: foreach ($divergencias as $d):
                            $dif = (float)($d['diferenca']??0);
                            $tipo = $dif > 0 ? 'Cobrança' : 'Reembolso';
                            $tipoColor = $dif > 0 ? 'danger' : 'success';
                        ?>
                        <tr>
                            <td class="ps-3"><a href="/admin/redirecionamento/envios/<?= (int)$d['envio_id'] ?>">#<?= (int)$d['envio_id'] ?></a></td>
                            <td><?= htmlspecialchars($d['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td>US$ <?= number_format((float)($d['valor_cobrado_usd']??0),2,',','.') ?></td>
                            <td>US$ <?= number_format((float)($d['valor_correto_usd']??0),2,',','.') ?></td>
                            <td class="fw-bold text-<?= $tipoColor ?>">US$ <?= number_format(abs($dif),2,',','.') ?></td>
                            <td><span class="badge bg-<?= $tipoColor ?> bg-opacity-10 text-<?= $tipoColor ?> border border-<?= $tipoColor ?> border-opacity-25"><?= $tipo ?></span></td>
                            <td>
                                <?php $sp = $d['status_pag']??'pendente'; $sc = ['pendente'=>'warning','pago'=>'success','falhou'=>'danger'][$sp]??'secondary'; ?>
                                <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25"><?= ucfirst($sp) ?></span>
                            </td>
                            <td class="pe-3 text-end d-flex gap-1 justify-content-end">
                                <?php if (($d['status_pag']??'pendente') === 'pendente'): ?>
                                    <?php if ($_isAdminDiv): ?>
                                        <button type="button" class="btn btn-xs btn-outline-success btn-marcar-pago" data-pag-id="<?= (int)$d['pag_id'] ?>" style="font-size:.75rem;padding:2px 8px">Marcar pago</button>
                                    <?php else: ?>
                                        <?php if ($dif > 0): ?>
                                        <button type="button" class="btn btn-xs btn-danger btn-pagar-diferenca" data-pag-id="<?= (int)$d['pag_id'] ?>" data-valor="<?= number_format(abs($dif),2,'.','') ?>" style="font-size:.75rem;padding:2px 8px"><i class="fas fa-credit-card me-1"></i>Pagar US$ <?= number_format(abs($dif),2,',','.') ?></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($d['comprovante_url'])): ?>
                                <a href="<?= htmlspecialchars($d['comprovante_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" class="btn btn-xs btn-outline-info" style="font-size:.75rem;padding:2px 8px">Comprovante</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
// Admin: marcar como pago
document.querySelectorAll('.btn-marcar-pago').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Marcar como pago?')) return;
        const fd = new FormData(); fd.append('pag_id', btn.dataset.pagId);
        const r = await fetch('/admin/redirecionamento/divergencias/marcar-pago',{method:'POST',body:fd});
        const j = await r.json();
        if (j.ok) location.reload();
        else alert('Erro: ' + (j.msg||'Tente novamente'));
    });
});

// Redirecionador: pagar diferença via Stripe
document.querySelectorAll('.btn-pagar-diferenca').forEach(btn => {
    btn.addEventListener('click', async () => {
        const pagId = btn.dataset.pagId;
        const valor = btn.dataset.valor;
        if (!confirm('Pagar US$ ' + parseFloat(valor).toFixed(2) + ' de diferença via cartão?')) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processando...';

        // Criar payment intent
        const fd = new FormData();
        fd.append('pag_id', pagId);
        const r = await fetch('/admin/redirecionamento/divergencias/pagar',{method:'POST',body:fd});
        const j = await r.json();
        if (!j.ok) {
            alert('Erro: ' + (j.msg||'Tente novamente'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-credit-card me-1"></i>Pagar US$ ' + parseFloat(valor).toFixed(2);
            return;
        }

        // Redirecionar para checkout Stripe
        if (j.checkout_url) {
            window.location.href = j.checkout_url;
        } else {
            alert('Pagamento criado. Aguarde confirmação.');
            location.reload();
        }
    });
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
