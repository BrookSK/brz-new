<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Pacotes WordPress</h1>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-sm btn-success" onclick="sincronizarPacotes()" id="btnSync">
                <i class="fas fa-sync-alt me-1"></i>Sincronizar
            </button>
            <a class="btn btn-sm btn-outline-primary" href="/admin/pacotes-wordpress?action=containers">Containers</a>
            <a class="btn btn-sm btn-outline-primary" href="/admin/pacotes-wordpress?action=faturas">Faturas (CN38)</a>
        </div>
    </div>

    <?php if (!empty($lastSync)): ?>
        <div class="text-muted small mb-3">
            <i class="fas fa-clock me-1"></i>Última sincronização: <?= date('d/m/Y H:i', strtotime((string) $lastSync)) ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-success" id="syncSuccess" style="display:none;"></div>
    <div class="alert alert-danger" id="syncError" style="display:none;"></div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="/admin/pacotes-wordpress" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-0">Origem</label>
                    <select name="origem" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="br" <?= ($filtroOrigem ?? '') === 'br' ? 'selected' : '' ?>>BR</option>
                        <option value="red" <?= ($filtroOrigem ?? '') === 'red' ? 'selected' : '' ?>>RED</option>
                        <option value="us" <?= ($filtroOrigem ?? '') === 'us' ? 'selected' : '' ?>>US</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Nº Pedido</label>
                    <input type="text" name="pedido" class="form-control form-control-sm" placeholder="Ex: 68849" value="<?= htmlspecialchars((string) ($filtroPedido ?? '')) ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Tracking</label>
                    <input type="text" name="tracking" class="form-control form-control-sm" placeholder="Código rastreio..." value="<?= htmlspecialchars((string) ($filtroTracking ?? '')) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Filtrar</button>
                    <a href="/admin/pacotes-wordpress" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Etiquetas -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Etiquetas (<?= (int) ($total ?? 0) ?> total)</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Origem</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Rastreio</th>
                            <th>Container</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="7" class="text-muted">Nenhuma etiqueta encontrada. Clique em "Sincronizar" para puxar do WordPress.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php
                                    $eid = (int) ($e['id'] ?? 0);
                                    $origem = strtoupper((string) ($e['origem'] ?? ''));
                                    $pedidoId = (int) ($e['pedido_id'] ?? 0);
                                    $trk = (string) ($e['tracking_number'] ?? '');
                                    $contId = (int) ($e['container_id'] ?? 0);
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?>"><?= $origem ?></span></td>
                                    <td>
                                        <?php if ($pedidoId > 0): ?>
                                            #<?= $pedidoId ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <code class="small"><?= htmlspecialchars($trk) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($contId > 0): ?>
                                            <a href="/admin/pacotes-wordpress?action=container-detalhes&id=<?= $contId ?>">#<?= $contId ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-' ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="/admin/pacotes-wordpress?action=etiqueta-pdf&id=<?= $eid ?>" target="_blank" title="Baixar PDF"><i class="fas fa-file-pdf"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php if (empty($etiquetas)): ?>
                    <div class="text-muted small py-3">Nenhuma etiqueta encontrada.</div>
                <?php else: ?>
                    <?php foreach ($etiquetas as $e): ?>
                        <?php
                            $eid = (int) ($e['id'] ?? 0);
                            $origem = strtoupper((string) ($e['origem'] ?? ''));
                            $pedidoId = (int) ($e['pedido_id'] ?? 0);
                            $trk = (string) ($e['tracking_number'] ?? '');
                        ?>
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="min-width:0;flex:1;">
                                    <span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?> me-1"><?= $origem ?></span>
                                    <?php if ($pedidoId > 0): ?>
                                        <span class="fw-bold small">#<?= $pedidoId ?></span>
                                    <?php endif; ?>
                                    <span class="small ms-1"><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></span>
                                    <?php if ($trk !== ''): ?>
                                        <div class="text-muted small" style="word-break:break-all;"><?= htmlspecialchars($trk) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($trk !== ''): ?>
                                    <a class="btn btn-sm btn-outline-primary py-0 px-2 ms-2" href="/admin/pacotes-wordpress?action=etiqueta-pdf&id=<?= $eid ?>" target="_blank"><i class="fas fa-file-pdf"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Paginação -->
            <?php
                $totalPages = max(1, (int) ceil(($total ?? 0) / ($perPage ?? 50)));
                $currentPage = (int) ($page ?? 1);
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php
                                $queryParams = array_filter([
                                    'origem' => $filtroOrigem ?? '',
                                    'pedido' => $filtroPedido ?? '',
                                    'tracking' => $filtroTracking ?? '',
                                    'page' => $i,
                                ]);
                                $url = '/admin/pacotes-wordpress?' . http_build_query($queryParams);
                            ?>
                            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function sincronizarPacotes() {
    const btn = document.getElementById('btnSync');
    const successEl = document.getElementById('syncSuccess');
    const errorEl = document.getElementById('syncError');
    successEl.style.display = 'none';
    errorEl.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sincronizando...';

    try {
        const r = await fetch('/admin/pacotes-wordpress?action=sincronizar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        const data = await r.json();
        if (data.success) {
            successEl.textContent = 'Sincronização concluída! ' + (data.synced || 0) + ' pacotes sincronizados.';
            successEl.style.display = '';
            setTimeout(() => location.reload(), 1500);
        } else {
            errorEl.textContent = 'Erros: ' + (data.errors || []).join('; ');
            errorEl.style.display = '';
        }
    } catch (e) {
        errorEl.textContent = 'Falha na sincronização: ' + e.message;
        errorEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Sincronizar';
    }
}
</script>

<?php
$content = ob_get_clean();
$title = 'Pacotes WordPress - Admin';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
