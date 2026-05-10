<?php $d = $demanda; $etapas = json_decode($d['bloco4_etapas'] ?? '[]', true) ?: []; $statusLabels = ['pendente'=>'Pendente','em_analise'=>'Em Análise','em_execucao'=>'Em Execução','em_teste'=>'Em Teste','bloqueado'=>'Bloqueado','concluido'=>'Concluído']; ?>
<div class="container-fluid py-3">
    <a href="/admin/demandas/painel" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    <?php if ($d['status'] === 'concluido'): ?><a href="/admin/demandas/pdf/<?= $d['id'] ?>" class="btn btn-sm btn-outline-dark mb-3 ms-2" target="_blank"><i class="fas fa-file-pdf me-1"></i>Gerar PDF</a><?php endif; ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><?= htmlspecialchars($d['bloco1_titulo']) ?></h5><span class="badge bg-primary fs-6"><?= $statusLabels[$d['status']] ?? $d['status'] ?></span></div><div class="card-body">
                <p><strong>Solicitante:</strong> <?= htmlspecialchars($d['bloco1_solicitante']) ?></p>
                <p><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></p>
                <?php if ($d['prazo_entrega']): ?><p><strong>Prazo:</strong> <?= date('d/m/Y', strtotime($d['prazo_entrega'])) ?></p><?php endif; ?>
            </div></div>

            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">2. Por que você quer isso?</h6></div><div class="card-body small">
                <p><strong>Problema:</strong> <?= nl2br(htmlspecialchars($d['bloco2_problema'])) ?></p>
                <p><strong>Melhoria:</strong> <?= nl2br(htmlspecialchars($d['bloco2_melhoria'])) ?></p>
                <p><strong>Consequência:</strong> <?= nl2br(htmlspecialchars($d['bloco2_consequencia'])) ?></p>
            </div></div>

            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">3. Impactos</h6></div><div class="card-body small">
                <p><strong>Financeiro:</strong> <?= nl2br(htmlspecialchars($d['bloco3_financeiro'])) ?></p>
                <p><strong>Capital de giro:</strong> <?= nl2br(htmlspecialchars($d['bloco3_capital_giro'])) ?></p>
                <p><strong>Custos operacionais:</strong> <?= nl2br(htmlspecialchars($d['bloco3_custos_operacionais'])) ?></p>
                <p><strong>Jornada do cliente:</strong> <?= nl2br(htmlspecialchars($d['bloco3_jornada_cliente'])) ?></p>
                <p><strong>Equipe:</strong> <?= nl2br(htmlspecialchars($d['bloco3_equipe'])) ?></p>
                <p><strong>Conflitos:</strong> <?= nl2br(htmlspecialchars($d['bloco3_conflitos'])) ?></p>
            </div></div>

            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">4. Etapas e Custos</h6></div><div class="card-body p-0">
                <table class="table table-sm mb-0"><thead class="table-light"><tr><th>Etapa</th><th>Custo</th></tr></thead><tbody>
                <?php foreach ($etapas as $et): ?><tr><td><?= htmlspecialchars($et['descricao'] ?? '') ?></td><td><?= htmlspecialchars($et['custo'] ?? '') ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </div></div>

            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">5. O que precisa ser feito?</h6></div><div class="card-body small">
                <p><strong>Novo ou existente:</strong> <?= nl2br(htmlspecialchars($d['bloco5_novo_ou_existente'])) ?></p>
                <p><strong>Ferramentas:</strong> <?= nl2br(htmlspecialchars($d['bloco5_ferramentas'])) ?></p>
                <p><strong>Regras:</strong> <?= nl2br(htmlspecialchars($d['bloco5_regras'])) ?></p>
                <p><strong>Usuários:</strong> <?= nl2br(htmlspecialchars($d['bloco5_usuarios'])) ?></p>
            </div></div>
        </div>

        <!-- Sidebar ações -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">Alterar Status</h6></div><div class="card-body">
                <form method="POST" action="/admin/demandas/mover/<?= $d['id'] ?>">
                    <select name="status" class="form-select form-select-sm mb-2">
                        <?php foreach ($statusLabels as $k => $v): ?><option value="<?= $k ?>" <?= $d['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                    </select>
                    <textarea name="nota" class="form-control form-control-sm mb-2" rows="2" placeholder="Nota interna (opcional)"></textarea>
                    <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-check me-1"></i>Atualizar</button>
                </form>
            </div></div>

            <?php if ($d['nota_admin']): ?>
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">Nota do Admin</h6></div><div class="card-body small"><?= nl2br(htmlspecialchars($d['nota_admin'])) ?></div></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small">Histórico</h6></div><div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
                <ul class="list-group list-group-flush">
                <?php foreach ($historico as $h): ?>
                <li class="list-group-item small"><strong><?= date('d/m H:i', strtotime($h['created_at'])) ?></strong> — <?= ucfirst(str_replace('_',' ',$h['status_novo'])) ?><?php if ($h['observacao']): ?><br><span class="text-muted"><?= htmlspecialchars($h['observacao']) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
                </ul>
            </div></div>
        </div>
    </div>
</div>
