<?php $title = 'Detalhe Carnê #' . $carne['id'] . ' - Admin'; ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <a href="/admin/carnes" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Voltar</a>

    <div class="row">
        <!-- Info Principal -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Carnê #<?= $carne['id'] ?> — Pedido #<?= $carne['pedido_id'] ?></h5>
                    <span class="badge bg-<?= $carne['status'] === 'quitado' ? 'success' : ($carne['status'] === 'com_atraso' ? 'danger' : 'primary') ?> fs-6">
                        <?= ucfirst(str_replace('_', ' ', $carne['status'])) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Cliente:</strong> <?= htmlspecialchars($carne['cliente_nome']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($carne['cliente_email']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Total:</strong> R$ <?= number_format($carne['total_geral'], 2, ',', '.') ?></p>
                            <p><strong>Produtos:</strong> R$ <?= number_format($carne['total_produtos'], 2, ',', '.') ?></p>
                            <p><strong>Taxas:</strong> R$ <?= number_format($carne['total_taxas'], 2, ',', '.') ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Parcelas:</strong> <?= $carne['quantidade_parcelas'] ?>x</p>
                            <p><strong>Compra Interna:</strong> <?= $carne['compra_interna_liberada'] ? '<span class="badge bg-success">Liberada</span>' : '<span class="badge bg-secondary">Aguardando</span>' ?></p>
                            <p><strong>Envio:</strong> <?= $carne['envio_liberado'] ? '<span class="badge bg-success">Liberado</span>' : '<span class="badge bg-secondary">Bloqueado</span>' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parcelas -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Parcelas</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>#</th><th>Vencimento</th><th>Valor</th><th>Prod. (Câmbio)</th><th>Taxa (Appmax)</th><th>Status</th><th>Ações</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($carne['parcelas'] as $p): ?>
                                <tr>
                                    <td><?= $p['numero_parcela'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
                                    <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                                    <td><span class="badge bg-<?= $p['boleto_produtos_pago'] ? 'success' : 'warning' ?>">R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?> <?= $p['boleto_produtos_pago'] ? '✓' : '⏳' ?></span></td>
                                    <td><span class="badge bg-<?= $p['boleto_taxas_pago'] ? 'success' : 'warning' ?>">R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?> <?= $p['boleto_taxas_pago'] ? '✓' : '⏳' ?></span></td>
                                    <td><span class="badge bg-<?= $p['status'] === 'paga' ? 'success' : ($p['status'] === 'em_atraso' ? 'danger' : 'secondary') ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span></td>
                                    <td><form method="POST" action="/admin/carnes/reemitir-boleto/<?= $p['id'] ?>" class="d-inline"><button type="submit" class="btn btn-sm btn-outline-warning" title="Reemitir"><i class="fas fa-redo"></i></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Ações -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Ações</h6></div>
                <div class="card-body">
                    <?php if ($carne['status'] === 'quitado' && !$carne['envio_liberado']): ?>
                        <form method="POST" action="/admin/carnes/liberar-envio/<?= $carne['id'] ?>" class="mb-2">
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-truck"></i> Liberar Envio</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($compraInterna): ?>
                        <?php if ($compraInterna['status'] === 'aguardando_compra'): ?>
                            <form method="POST" action="/admin/carnes/marcar-comprado/<?= $compraInterna['id'] ?>" class="mb-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-shopping-cart"></i> Marcar Comprado</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($compraInterna['status'] === 'comprado'): ?>
                            <form method="POST" action="/admin/carnes/marcar-recebido/<?= $compraInterna['id'] ?>" class="mb-2">
                                <button type="submit" class="btn btn-info w-100"><i class="fas fa-box"></i> Marcar Recebido</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button class="btn btn-outline-danger w-100 mb-2" data-bs-toggle="collapse" data-bs-target="#prodIndisponivel">
                        <i class="fas fa-exclamation-triangle"></i> Produto Indisponível
                    </button>
                    <div class="collapse" id="prodIndisponivel">
                        <div class="border rounded p-2 mt-2">
                            <p class="small text-muted mb-2">Escolha a ação para quando o produto não está mais disponível:</p>

                            <!-- Opção 1: Crédito em Carteira -->
                            <form method="POST" action="/admin/carnes/produto-indisponivel/<?= $carne['id'] ?>" class="mb-3">
                                <input type="hidden" name="acao" value="credito_carteira">
                                <div class="mb-2">
                                    <label class="form-label small">Valor do crédito (R$)</label>
                                    <input type="number" name="valor" step="0.01" class="form-control form-control-sm" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Observações</label>
                                    <textarea name="observacoes" class="form-control form-control-sm" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-warning w-100"><i class="fas fa-wallet me-1"></i>Gerar Crédito em Carteira</button>
                            </form>

                            <hr class="my-2">

                            <!-- Opção 2: Cobrar diferença via link de pagamento -->
                            <p class="small text-muted mb-1">Se o produto substituto custa mais caro:</p>
                            <a href="/admin/pedidos/editar/<?= $carne['pedido_id'] ?>" class="btn btn-sm btn-outline-primary w-100" target="_blank">
                                <i class="fas fa-edit me-1"></i>Editar Pedido e Cobrar Diferença
                            </a>
                            <small class="text-muted d-block mt-1">Use "Gerar Link de Diferença" na tela de edição do pedido.</small>
                        </div>
                    </div>

                    <form method="POST" action="/admin/carnes/reenviar-notificacao/<?= $carne['id'] ?>" class="mt-2">
                        <div class="input-group input-group-sm">
                            <select name="evento" class="form-select form-select-sm">
                                <option value="carne_criado">Carnê Criado</option>
                                <option value="parcela_paga">Parcela Paga</option>
                                <option value="carne_quitado">Carnê Quitado</option>
                                <option value="envio_liberado">Envio Liberado</option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($compraInterna): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Compra Interna</h6></div>
                <div class="card-body">
                    <p><strong>Status:</strong> <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $compraInterna['status'])) ?></span></p>
                    <?php if ($compraInterna['comprado_em']): ?><p><strong>Comprado em:</strong> <?= date('d/m/Y H:i', strtotime($compraInterna['comprado_em'])) ?></p><?php endif; ?>
                    <?php if ($compraInterna['recebido_em']): ?><p><strong>Recebido em:</strong> <?= date('d/m/Y H:i', strtotime($compraInterna['recebido_em'])) ?></p><?php endif; ?>
                    <?php if ($compraInterna['produto_indisponivel']): ?><div class="alert alert-danger small">Produto indisponível — Ação: <?= $compraInterna['acao_indisponibilidade'] ?></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0">Histórico</h6></div>
                <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($historico as $h): ?>
                        <li class="list-group-item small">
                            <strong><?= date('d/m H:i', strtotime($h['created_at'])) ?></strong> — <?= htmlspecialchars($h['descricao']) ?>
                            <?php if (!empty($h['usuario_nome'])): ?><br><span class="text-muted">por <?= htmlspecialchars($h['usuario_nome']) ?></span><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header"><h6 class="mb-0">Notificações Enviadas</h6></div>
                <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($notificacoes as $n): ?>
                        <li class="list-group-item small">
                            <span class="badge bg-<?= $n['status'] === 'enviado' ? 'success' : ($n['status'] === 'erro' ? 'danger' : 'warning') ?>"><?= $n['canal'] ?></span>
                            <?= htmlspecialchars($n['evento']) ?> — <?= date('d/m H:i', strtotime($n['created_at'])) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
