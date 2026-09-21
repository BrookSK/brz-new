<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title"><?= __('admin.copilot.conversations_heading','Conversas do Co-Piloto') ?></h1>
        <p class="page-subtitle"><?= __('admin.copilot.conversations_subtitle','Histórico de chats dos clientes com a Bri') ?></p>
    </div>
    <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i><?= __('common.back','Voltar') ?></a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['flash_error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Busca -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET" action="/admin/copiloto/conversas">
            <div class="col-md-8">
                <input type="text" class="form-control" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="<?= htmlspecialchars(__('admin.copilot.search_placeholder','Buscar por nome, email ou ID da sessão...'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i><?= __('common.search','Buscar') ?></button>
                <a class="btn btn-outline-secondary w-100" href="/admin/copiloto/conversas"><?= __('admin.copilot.clear','Limpar') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de sessões -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= $total ?> <?= __('admin.copilot.conversations_found','conversas encontradas') ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sessoes)): ?>
            <div class="text-muted text-center py-5"><?= __('admin.copilot.no_conversations','Nenhuma conversa encontrada.') ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= __('admin.copilot.col_customer','Cliente') ?></th>
                            <th><?= __('admin.copilot.col_messages','Mensagens') ?></th>
                            <th><?= __('admin.copilot.col_origin_page','Página Origem') ?></th>
                            <th><?= __('admin.copilot.col_last_interaction','Última Interação') ?></th>
                            <th><?= __('admin.copilot.col_start','Início') ?></th>
                            <th class="text-end"><?= __('common.actions','Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessoes as $s): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($s['usuario_nome'] ?: __('admin.copilot.visitor','Visitante')) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($s['usuario_email'] ?? '') ?></div>
                            </td>
                            <td><span class="badge bg-primary"><?= (int) $s['total_mensagens'] ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_substr((string) ($s['pagina_origem'] ?? ''), 0, 40)) ?></td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($s['ultima_interacao'])) ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($s['criado_em'])) ?></td>
                            <td class="text-end">
                                <a href="/admin/copiloto/conversas/<?= htmlspecialchars($s['sessao_id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i><?= __('admin.copilot.view','Ver') ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
            <div class="d-flex justify-content-center py-3">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= min($totalPaginas, 20); $i++): ?>
                            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/copiloto/conversas?page=<?= $i ?>&busca=<?= urlencode($busca) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
