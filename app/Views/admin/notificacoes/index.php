<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="page-title"><?= __('admin.notifications.title', 'Notificações Enviadas') ?></h1>
        <div class="d-flex gap-2">
            <span class="badge bg-primary fs-6"><?= (int) ($stats['total'] ?? 0) ?> <?= __('admin.notifications.total', 'total') ?></span>
            <span class="badge bg-info text-dark fs-6"><?= (int) ($stats['email'] ?? 0) ?> <?= __('admin.notifications.email', 'e-mail') ?></span>
            <span class="badge bg-success fs-6"><?= (int) ($stats['whatsapp'] ?? 0) ?> WhatsApp</span>
            <span class="badge bg-danger fs-6"><?= (int) ($stats['erros'] ?? 0) ?> <?= __('admin.notifications.errors', 'erros') ?></span>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/notificacoes" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small"><?= __('admin.notifications.channel', 'Canal') ?></label>
                    <select name="canal" class="form-select form-select-sm">
                        <option value=""><?= __('admin.notifications.all', 'Todos') ?></option>
                        <option value="email" <?= $filtros['canal'] === 'email' ? 'selected' : '' ?>><?= __('admin.notifications.email', 'e-mail') ?></option>
                        <option value="whatsapp" <?= $filtros['canal'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small"><?= __('admin.notifications.event', 'Evento') ?></label>
                    <select name="evento" class="form-select form-select-sm">
                        <option value=""><?= __('admin.notifications.all', 'Todos') ?></option>
                        <?php foreach ($eventos as $ev): ?>
                            <option value="<?= htmlspecialchars($ev, ENT_QUOTES, 'UTF-8') ?>" <?= $filtros['evento'] === $ev ? 'selected' : '' ?>><?= htmlspecialchars($ev, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small"><?= __('admin.notifications.status', 'Status') ?></label>
                    <select name="status" class="form-select form-select-sm">
                        <option value=""><?= __('admin.notifications.all', 'Todos') ?></option>
                        <option value="sucesso" <?= $filtros['status'] === 'sucesso' ? 'selected' : '' ?>><?= __('admin.notifications.status_success', 'Sucesso') ?></option>
                        <option value="erro" <?= $filtros['status'] === 'erro' ? 'selected' : '' ?>><?= __('admin.notifications.status_error', 'Erro') ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small"><?= __('admin.notifications.order', 'Pedido') ?></label>
                    <input type="number" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['pedido_id'], ENT_QUOTES, 'UTF-8') ?>" placeholder="#">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small"><?= __('admin.notifications.search', 'Buscar (destino/conteúdo)') ?></label>
                    <input type="text" name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['busca'], ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(__('admin.notifications.search_placeholder', 'e-mail, telefone, assunto...'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i><?= __('admin.notifications.filter', 'Filtrar') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><?= __('admin.notifications.channel', 'Canal') ?></th>
                            <th><?= __('admin.notifications.event', 'Evento') ?></th>
                            <th><?= __('admin.notifications.order', 'Pedido') ?></th>
                            <th><?= __('admin.notifications.destination', 'Destino') ?></th>
                            <th><?= __('admin.notifications.content', 'Assunto/Mensagem') ?></th>
                            <th><?= __('admin.notifications.status', 'Status') ?></th>
                            <th><?= __('admin.notifications.date', 'Data') ?></th>
                            <th class="text-end"><?= __('admin.notifications.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4"><?= __('admin.notifications.empty', 'Nenhuma notificação encontrada.') ?></td></tr>
                        <?php else: foreach ($registros as $r):
                            $canal = (string) ($r['canal'] ?? '');
                            $evento = (string) ($r['evento'] ?? '');
                            $pid = (int) ($r['pedido_id'] ?? 0);
                            $statusRaw = strtolower((string) ($r['status'] ?? ''));
                            if ($statusRaw === 'erro') { $stBadge = 'bg-danger'; $stLabel = __('admin.notifications.status_error', 'Erro'); }
                            elseif ($statusRaw === 'pendente') { $stBadge = 'bg-warning text-dark'; $stLabel = __('admin.notifications.status_pending', 'Pendente'); }
                            else { $stBadge = 'bg-success'; $stLabel = __('admin.notifications.status_success', 'Sucesso'); }
                            $data = (string) ($r['created_at'] ?? '');
                            $dataFmt = $data !== '' ? date('d/m/Y H:i', strtotime($data)) : '-';
                        ?>
                            <tr>
                                <td>
                                    <?php if ($canal === 'whatsapp'): ?>
                                        <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i>WhatsApp</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><i class="fas fa-envelope me-1"></i><?= __('admin.notifications.email', 'e-mail') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="small"><?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= $pid > 0 ? ('<a href="/admin/pedidos/detalhes/' . $pid . '" target="_blank">#' . $pid . '</a>') : '-' ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($r['destino'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="small text-truncate" style="max-width:280px;" title="<?= htmlspecialchars((string) ($r['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['assunto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $stBadge ?>"><?= $stLabel ?></span><?php if (!empty($r['detalhe'])): ?> <span class="text-muted small"><?= htmlspecialchars((string) $r['detalhe'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></td>
                                <td class="small text-nowrap"><?= $dataFmt ?></td>
                                <td class="text-end">
                                    <?php if ($pid > 0 && $evento !== ''): ?>
                                        <button class="btn btn-xs btn-outline-primary" onclick="reenviarNotificacao(this, '<?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>', <?= $pid ?>)" title="<?= htmlspecialchars(__('admin.notifications.resend', 'Reenviar'), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-paper-plane"></i> <?= __('admin.notifications.resend', 'Reenviar') ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ((int) $totalPaginas > 1): ?>
        <div class="card-footer">
            <?php
                $paginacao_atual = (int) $pagina;
                $paginacao_total = (int) $totalPaginas;
                $paginacao_url_fn = function ($p) use ($filtros) {
                    return '?' . http_build_query(array_merge($filtros, ['pagina' => $p]));
                };
                include __DIR__ . '/../../partials/pagination.php';
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function reenviarNotificacao(btn, evento, pedidoId) {
    if (!confirm(<?= json_encode(__('admin.notifications.confirm_resend', 'Reenviar a notificação deste evento para o pedido #{n}? Serão reenviados e-mail e WhatsApp conforme configurado.')) ?>.replace('{n}', pedidoId))) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        var r = await fetch('/admin/notificacoes/reenviar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ evento: evento, pedido_id: pedidoId })
        });
        var d = await r.json();
        if (d.success) {
            alert(d.message || <?= json_encode(__('admin.notifications.resend_ok', 'Notificação reenviada.')) ?>);
        } else {
            alert((<?= json_encode(__('admin.notifications.error_prefix', 'Erro:')) ?>) + ' ' + (d.error || ''));
        }
    } catch (e) {
        alert(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
