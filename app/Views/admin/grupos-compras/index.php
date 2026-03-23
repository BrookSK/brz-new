<?php
$grupos = is_array($grupos ?? null) ? $grupos : [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Grupos de Compras</h1>
            <div class="text-muted small"><?= count($grupos) ?> grupo(s) cadastrado(s)</div>
        </div>
        <button class="btn btn-primary btn-sm" id="btnNovoGrupo"><i class="fas fa-plus me-1"></i>Novo grupo</button>
    </div>

    <div id="listaGrupos" class="row g-3">
        <?php if (empty($grupos)): ?>
        <div class="col-12 text-center text-muted py-5" id="emptyState">
            <i class="fas fa-store fa-3x mb-3 d-block opacity-25"></i>
            Nenhum grupo de compras cadastrado ainda.
        </div>
        <?php else: foreach ($grupos as $g): ?>
        <?php
            $ativo = (int)($g['ativo'] ?? 1);
            $cobraImposto = (int)($g['cobra_imposto_eua'] ?? 0);
            $impostoLocal = (float)($g['imposto_local_percent'] ?? 0);
            $qtdPedidos = (int)($g['qtd_pedidos'] ?? 0);
            $qtdProdutos = (int)($g['qtd_produtos'] ?? 0);
            $criadoPor = htmlspecialchars($g['criado_por_nome'] ?? '—', ENT_QUOTES, 'UTF-8');
            $criadoEm = $g['created_at'] ? date('d/m/Y H:i', strtotime($g['created_at'])) : '—';
            $slug = htmlspecialchars($g['slug'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>
        <div class="col-md-6 col-xl-4 grupo-card" data-id="<?= (int)$g['id'] ?>">
            <div class="card border-0 shadow-sm h-100 <?= $ativo ? '' : 'opacity-60' ?>">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($g['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted">/grupo/<?= $slug ?></div>
                        </div>
                        <span class="badge <?= $ativo ? 'bg-success' : 'bg-secondary' ?> ms-2"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                    </div>
                    <div class="small mb-1">Imposto local: <strong><?= $impostoLocal > 0 ? number_format($impostoLocal, 1) . '%' : 'Não' ?></strong></div>
                    <div class="small mb-1"><i class="fas fa-box me-1 text-muted"></i>Produtos: <strong><?= $qtdProdutos ?></strong></div>
                    <div class="small mb-1"><i class="fas fa-shopping-cart me-1 text-muted"></i>Pedidos: <strong><?= $qtdPedidos ?></strong></div>
                    <div class="small mb-1"><i class="fas fa-user me-1 text-muted"></i>Cadastrado por: <strong><?= $criadoPor ?></strong></div>
                    <div class="small mb-3"><i class="fas fa-clock me-1 text-muted"></i><?= $criadoEm ?></div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary btn-produtos" data-id="<?= (int)$g['id'] ?>" data-nome="<?= htmlspecialchars($g['nome'], ENT_QUOTES, 'UTF-8') ?>" title="Ver produtos">
                            <i class="fas fa-box"></i> Produtos
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-editar"
                            data-id="<?= (int)$g['id'] ?>"
                            data-nome="<?= htmlspecialchars($g['nome'], ENT_QUOTES, 'UTF-8') ?>"
                            data-descricao="<?= htmlspecialchars($g['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-imposto="<?= $cobraImposto ?>"
                            data-imposto-local="<?= $impostoLocal ?>"
                            data-ativo="<?= $ativo ?>"
                            title="Editar">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="btn btn-sm <?= $ativo ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-toggle" data-id="<?= (int)$g['id'] ?>" title="<?= $ativo ? 'Desativar' : 'Ativar' ?>">
                            <i class="fas <?= $ativo ? 'fa-ban' : 'fa-check' ?>"></i>
                        </button>
                        <a href="/grupo/<?= $slug ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Ver página pública">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-danger btn-excluir-grupo" data-id="<?= (int)$g['id'] ?>" data-nome="<?= htmlspecialchars($g['nome'], ENT_QUOTES, 'UTF-8') ?>" title="Excluir grupo">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Modal criar/editar grupo -->
<div class="modal fade" id="modalGrupo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGrupoTitulo">Novo grupo de compras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="grupoId" value="">
                <div class="mb-3">
                    <label class="form-label">Nome do grupo <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" id="grupoNome" placeholder="Ex: Walmart, Amazon...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" id="grupoDescricao" rows="2" placeholder="Opcional"></textarea>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="grupoImposto" role="switch">
                    <label class="form-check-label" for="grupoImposto">Cobrar imposto local neste grupo</label>
                </div>
                <div class="mb-3" id="grupoImpostoPercentWrap" style="display:none">
                    <label class="form-label">Percentual do imposto local (%)</label>
                    <input class="form-control" type="number" id="grupoImpostoPercent" step="0.1" min="0" max="99" value="8" placeholder="Ex: 8">
                </div>
                <div class="form-check form-switch" id="grupoAtivoWrap">
                    <input class="form-check-input" type="checkbox" id="grupoAtivo" role="switch" checked>
                    <label class="form-check-label" for="grupoAtivo">Grupo ativo</label>
                </div>
                <div id="msgGrupo" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarGrupo">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal produtos do grupo -->
<div class="modal fade" id="modalProdutos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProdutosTitulo">Produtos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalProdutosBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Novo/Editar grupo ──────────────────────────────────────────────────────
document.getElementById('btnNovoGrupo').addEventListener('click', () => {
    document.getElementById('grupoId').value = '';
    document.getElementById('grupoNome').value = '';
    document.getElementById('grupoDescricao').value = '';
    document.getElementById('grupoImposto').checked = false;
    document.getElementById('grupoImpostoPercent').value = '8';
    document.getElementById('grupoImpostoPercentWrap').style.display = 'none';
    document.getElementById('grupoAtivo').checked = true;
    document.getElementById('grupoAtivoWrap').style.display = 'none';
    document.getElementById('modalGrupoTitulo').textContent = 'Novo grupo de compras';
    document.getElementById('msgGrupo').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalGrupo')).show();
});

document.getElementById('grupoImposto').addEventListener('change', function() {
    document.getElementById('grupoImpostoPercentWrap').style.display = this.checked ? '' : 'none';
});

document.querySelectorAll('.btn-editar').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('grupoId').value = btn.dataset.id;
        document.getElementById('grupoNome').value = btn.dataset.nome;
        document.getElementById('grupoDescricao').value = btn.dataset.descricao;
        const impostoLocal = parseFloat(btn.dataset.impostoLocal || '0');
        document.getElementById('grupoImposto').checked = impostoLocal > 0;
        document.getElementById('grupoImpostoPercent').value = impostoLocal > 0 ? impostoLocal : '8';
        document.getElementById('grupoImpostoPercentWrap').style.display = impostoLocal > 0 ? '' : 'none';
        document.getElementById('grupoAtivo').checked = btn.dataset.ativo === '1';
        document.getElementById('grupoAtivoWrap').style.display = '';
        document.getElementById('modalGrupoTitulo').textContent = 'Editar grupo';
        document.getElementById('msgGrupo').innerHTML = '';
        new bootstrap.Modal(document.getElementById('modalGrupo')).show();
    });
});

document.getElementById('btnSalvarGrupo').addEventListener('click', async () => {
    const btn = document.getElementById('btnSalvarGrupo');
    const msg = document.getElementById('msgGrupo');
    const nome = document.getElementById('grupoNome').value.trim();
    if (!nome) { msg.innerHTML = '<div class="alert alert-danger py-1 small">Nome obrigatório.</div>'; return; }
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';
    const fd = new FormData();
    fd.append('id', document.getElementById('grupoId').value);
    fd.append('nome', nome);
    fd.append('descricao', document.getElementById('grupoDescricao').value);
    if (document.getElementById('grupoImposto').checked) {
        fd.append('cobra_imposto_eua', '1');
        fd.append('imposto_local_percent', document.getElementById('grupoImpostoPercent').value || '8');
    } else {
        fd.append('imposto_local_percent', '0');
    }
    fd.append('ativo', document.getElementById('grupoAtivo').checked ? '1' : '0');
    const r = await fetch('/admin/grupos-compras/salvar', {method:'POST', body:fd});
    const j = await r.json();
    btn.disabled = false; btn.innerHTML = 'Salvar';
    if (j.ok) { location.reload(); }
    else { msg.innerHTML = '<div class="alert alert-danger py-1 small">' + (j.msg||'Erro') + '</div>'; }
});

// ── Toggle ativo ───────────────────────────────────────────────────────────
document.querySelectorAll('.btn-toggle').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Confirmar alteração de status?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/grupos-compras/toggle-ativo', {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) location.reload();
    });
});

// ── Excluir grupo ──────────────────────────────────────────────────────────
document.querySelectorAll('.btn-excluir-grupo').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm(`Excluir o grupo "${btn.dataset.nome}"? Os produtos serão desvinculados mas não excluídos.`)) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        const r = await fetch('/admin/grupos-compras/excluir/' + btn.dataset.id, {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) btn.closest('.grupo-card').remove();
        else alert(j.msg || 'Erro ao excluir grupo.');
    });
});

// ── Produtos do grupo ──────────────────────────────────────────────────────
document.querySelectorAll('.btn-produtos').forEach(btn => {
    btn.addEventListener('click', async () => {
        document.getElementById('modalProdutosTitulo').textContent = 'Produtos — ' + btn.dataset.nome;
        document.getElementById('modalProdutosBody').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
        new bootstrap.Modal(document.getElementById('modalProdutos')).show();
        const r = await fetch('/admin/grupos-compras/api/produtos?id=' + btn.dataset.id);
        const j = await r.json();
        if (!j.ok || !j.produtos.length) {
            document.getElementById('modalProdutosBody').innerHTML = '<div class="text-center text-muted py-4">Nenhum produto neste grupo.</div>';
            return;
        }
        let html = '<div class="mb-3"><input type="text" class="form-control form-control-sm" id="buscaProdutoGrupo" placeholder="Pesquisar produto..." autocomplete="off"></div>';
        html += '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Foto</th><th>Nome</th><th>Preço</th><th>Estoque</th><th>Ações</th></tr></thead><tbody>';
        j.produtos.forEach(p => {
            const foto = p.foto_principal || '/uploads/produtos/placeholder.jpg';
            html += `<tr data-produto-id="${p.id}">
                <td><img src="${foto}" style="width:40px;height:40px;object-fit:cover;border-radius:8px"></td>
                <td>${p.nome||''}</td>
                <td>US$ ${parseFloat(p.preco||0).toFixed(2)}</td>
                <td>${p.estoque||0}</td>
                <td class="text-nowrap">
                    <a href="/admin/produtos/editar/${p.id}" class="btn btn-xs btn-outline-primary me-1" style="font-size:.75rem;padding:2px 8px" title="Editar produto"><i class="fas fa-pen"></i></a>
                    <button class="btn btn-xs btn-outline-warning btn-rm-produto me-1" data-id="${p.id}" style="font-size:.75rem;padding:2px 8px" title="Remover do grupo"><i class="fas fa-unlink"></i></button>
                    <button class="btn btn-xs btn-outline-danger btn-del-produto" data-id="${p.id}" style="font-size:.75rem;padding:2px 8px" title="Excluir produto"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        document.getElementById('modalProdutosBody').innerHTML = html;
        document.getElementById('buscaProdutoGrupo').addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            document.querySelectorAll('#modalProdutosBody tbody tr').forEach(tr => {
                const nome = (tr.children[1]?.textContent || '').toLowerCase();
                tr.style.display = nome.includes(termo) ? '' : 'none';
            });
        });
        document.querySelectorAll('.btn-rm-produto').forEach(b => {
            b.addEventListener('click', async () => {
                if (!confirm('Remover produto do grupo (produto continua existindo)?')) return;
                const fd = new FormData(); fd.append('produto_id', b.dataset.id);
                const r2 = await fetch('/admin/grupos-compras/api/remover-produto', {method:'POST', body:fd});
                const j2 = await r2.json();
                if (j2.ok) b.closest('tr').remove();
            });
        });
        document.querySelectorAll('.btn-del-produto').forEach(b => {
            b.addEventListener('click', async () => {
                if (!confirm('Excluir este produto permanentemente? Esta ação não pode ser desfeita.')) return;
                const fd = new FormData(); fd.append('produto_id', b.dataset.id);
                const r2 = await fetch('/admin/grupos-compras/api/excluir-produto', {method:'POST', body:fd});
                const j2 = await r2.json();
                if (j2.ok) b.closest('tr').remove();
                else alert(j2.msg || 'Erro ao excluir produto.');
            });
        });
    });
});
</script>
