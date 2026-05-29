<?php $title = 'Detalhe Carnê #' . $carne['id'] . ' - Admin'; ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <a href="/admin/carnes" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Voltar</a>
    <a href="/admin/pedidos/detalhes/<?= (int) $carne['pedido_id'] ?>" class="btn btn-sm btn-outline-primary mb-3 ms-2" target="_blank"><i class="fas fa-external-link-alt me-1"></i> Abrir Pedido #<?= (int) $carne['pedido_id'] ?></a>

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
                                <tr><th>#</th><th>Vencimento</th><th>Valor</th><th class="d-none d-lg-table-cell">Prod. (Câmbio)</th><th class="d-none d-lg-table-cell">Taxa (CR Taxas)</th><th>Status</th><th>Ações</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($carne['parcelas'] as $p): ?>
                                <tr>
                                    <td><?= $p['numero_parcela'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
                                    <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                                    <td class="d-none d-lg-table-cell"><span class="badge bg-<?= $p['boleto_produtos_pago'] ? 'success' : 'warning' ?>">R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?> <?= $p['boleto_produtos_pago'] ? '✓' : '⏳' ?></span></td>
                                    <td class="d-none d-lg-table-cell"><span class="badge bg-<?= $p['boleto_taxas_pago'] ? 'success' : 'warning' ?>">R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?> <?= $p['boleto_taxas_pago'] ? '✓' : '⏳' ?></span></td>
                                    <td><span class="badge bg-<?= $p['status'] === 'paga' ? 'success' : ($p['status'] === 'em_atraso' ? 'danger' : 'secondary') ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span></td>
                                    <td>
                                        <form method="POST" action="/admin/carnes/reemitir-boleto/<?= $p['id'] ?>" class="d-inline"><button type="submit" class="btn btn-sm btn-outline-warning" title="Reemitir"><i class="fas fa-redo"></i></button></form>
                                        <?php if (in_array($p['status'], ['pendente', 'aguardando_pagamento', 'vencida', 'em_atraso'])): ?>
                                        <form method="POST" action="/admin/carnes/enviar-cobranca/<?= $p['id'] ?>" class="d-inline ms-1" onsubmit="return confirm('Enviar email de cobrança para esta parcela?')"><button type="submit" class="btn btn-sm btn-outline-info" title="Enviar cobrança por email"><i class="fas fa-paper-plane"></i></button></form>
                                        <?php endif; ?>
                                        <?php if ($p['status'] !== 'paga'): ?>
                                        <div class="btn-group btn-group-sm ms-1">
                                            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Marcar como pago"><i class="fas fa-check"></i></button>
                                            <ul class="dropdown-menu">
                                                <?php if (!$p['boleto_produtos_pago']): ?>
                                                <li><form method="POST" action="/admin/carnes/marcar-parcela-paga/<?= $p['id'] ?>"><input type="hidden" name="tipo" value="produtos"><button type="submit" class="dropdown-item" onclick="return confirm('Marcar PRODUTOS como pago?')"><i class="fas fa-box me-1"></i> Produtos</button></form></li>
                                                <?php endif; ?>
                                                <?php if (!$p['boleto_taxas_pago']): ?>
                                                <li><form method="POST" action="/admin/carnes/marcar-parcela-paga/<?= $p['id'] ?>"><input type="hidden" name="tipo" value="taxas"><button type="submit" class="dropdown-item" onclick="return confirm('Marcar TAXAS como pago?')"><i class="fas fa-receipt me-1"></i> Taxas</button></form></li>
                                                <?php endif; ?>
                                                <li><form method="POST" action="/admin/carnes/marcar-parcela-paga/<?= $p['id'] ?>"><input type="hidden" name="tipo" value="ambos"><button type="submit" class="dropdown-item" onclick="return confirm('Marcar AMBOS como pago?')"><i class="fas fa-check-double me-1"></i> Ambos</button></form></li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Produtos do Pedido -->
            <?php if (!empty($itensPedido)): ?>
            <?php
            // Verificar se este carnê usou preço original (flag salva na criação)
            $carneUsouPrecoOriginal = false;
            $pedidoIdMeta = (int) ($carne['pedido_id'] ?? 0);
            if ($pedidoIdMeta > 0) {
                try {
                    $db = \Config\Database::getConnection();
                    $stMeta = $db->prepare("SELECT meta_value FROM pedido_meta WHERE pedido_id = ? AND meta_key = 'carne_usou_preco_original' LIMIT 1");
                    $stMeta->execute([$pedidoIdMeta]);
                    $carneUsouPrecoOriginal = ((string) $stMeta->fetchColumn() === '1');
                } catch (\Exception $e) {}
            }
            // Se usou preço original, os itens estão em USD e precisam converter para BRL
            $taxaConvCarne = 0;
            if ($carneUsouPrecoOriginal) {
                $taxaConvCarne = (float) ($pedido['taxa_conversao'] ?? 0);
                if ($taxaConvCarne <= 1.01) {
                    try {
                        $db2 = \Config\Database::getConnection();
                        $stTx = $db2->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                        $stTx->execute();
                        $txV = (float) ($stTx->fetchColumn() ?: 0);
                        if ($txV > 1.01) $taxaConvCarne = $txV;
                    } catch (\Exception $e) {}
                }
                if ($taxaConvCarne <= 1.01) $taxaConvCarne = 5.85;
            }
            ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-box-open me-2"></i>Produtos do Pedido</h6></div>
                <div class="card-body p-0">
                    <?php if ($carneUsouPrecoOriginal): ?>
                    <div class="alert alert-info small m-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Carnê Braziliana:</strong> Os produtos foram cobrados pelo valor original (sem promoção), pois promoções podem não estar vigentes durante todo o período de parcelamento.
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Produto</th><th class="text-center">Qtd</th><th class="text-end">Preço Unit.</th><th class="text-end">Subtotal</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($itensPedido as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($item['produto_imagem'])): ?>
                                                <img src="<?= htmlspecialchars($item['produto_imagem']) ?>" class="rounded border" style="width:40px;height:40px;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                <div class="rounded bg-light align-items-center justify-content-center" style="width:40px;height:40px;display:none;"><i class="fas fa-image text-muted"></i></div>
                                            <?php else: ?>
                                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fas fa-image text-muted"></i></div>
                                            <?php endif; ?>
                                            <span class="fw-semibold small"><?= htmlspecialchars($item['nome_display'] ?? 'Produto') ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= (int)($item['quantidade'] ?? 1) ?></td>
                                    <?php
                                    $puCarne = (float) ($item['preco_unitario'] ?? 0);
                                    $stCarne = (float) ($item['subtotal'] ?? 0);
                                    if ($carneUsouPrecoOriginal && $taxaConvCarne > 1.01 && $puCarne > 0 && $puCarne < 500) {
                                        $puCarne = round($puCarne * $taxaConvCarne, 2);
                                        $stCarne = round($stCarne * $taxaConvCarne, 2);
                                    }
                                    ?>
                                    <td class="text-end"><?= $puCarne > 0 ? 'R$ ' . number_format($puCarne, 2, ',', '.') : '-' ?></td>
                                    <td class="text-end"><?= $stCarne > 0 ? 'R$ ' . number_format($stCarne, 2, ',', '.') : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
                            <!-- Tabs -->
                            <ul class="nav nav-tabs nav-fill mb-2" role="tablist">
                                <li class="nav-item"><a class="nav-link active small" data-bs-toggle="tab" href="#tab-credito">Crédito Carteira</a></li>
                                <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" href="#tab-diferenca">Cobrar Diferença</a></li>
                            </ul>
                            <div class="tab-content">
                                <!-- Tab Crédito -->
                                <div class="tab-pane active" id="tab-credito">
                                    <form method="POST" action="/admin/carnes/produto-indisponivel/<?= $carne['id'] ?>">
                                        <input type="hidden" name="acao" value="credito_carteira">
                                        <div class="mb-2">
                                            <label class="form-label small">Valor (R$)</label>
                                            <input type="number" name="valor" step="0.01" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Observações</label>
                                            <textarea name="observacoes" class="form-control form-control-sm" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-warning w-100"><i class="fas fa-wallet me-1"></i>Gerar Crédito</button>
                                    </form>
                                </div>
                                <!-- Tab Diferença -->
                                <div class="tab-pane" id="tab-diferenca">
                                    <div class="mb-2">
                                        <label class="form-label small">Valor da diferença (R$)</label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" id="diferenca-valor" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Observações</label>
                                        <textarea class="form-control form-control-sm" rows="2" id="diferenca-obs"></textarea>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btn-gerar-diferenca" onclick="gerarLinkDiferenca()">
                                        <i class="fas fa-link me-1"></i>Gerar Link de Pagamento
                                    </button>
                                    <div id="diferenca-resultado" class="mt-2" style="display:none;">
                                        <div class="alert alert-success small py-2 mb-1">
                                            <i class="fas fa-check-circle me-1"></i>Link gerado com sucesso!
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control bg-light" readonly id="diferenca-link-url">
                                            <button class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('diferenca-link-url').value);this.innerHTML='✓ Copiado'">
                                                <i class="fas fa-copy"></i> Copiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    function gerarLinkDiferenca() {
                        const btn = document.getElementById('btn-gerar-diferenca');
                        const valor = parseFloat(document.getElementById('diferenca-valor').value || 0);
                        const obs = document.getElementById('diferenca-obs').value || '';
                        if (valor <= 0) { alert('Informe o valor da diferença'); return; }
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';
                        fetch('/admin/carnes/gerar-link-diferenca/<?= $carne['id'] ?>', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({valor: valor, observacoes: obs})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('diferenca-resultado').style.display = 'block';
                                var linkUrl = data.link_url || '';
                                if (linkUrl && !linkUrl.startsWith('http')) {
                                    linkUrl = window.location.origin + linkUrl;
                                }
                                document.getElementById('diferenca-link-url').value = linkUrl;
                                btn.innerHTML = '<i class="fas fa-check me-1"></i>Link Gerado';
                            } else {
                                alert(data.error || 'Erro ao gerar link');
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-link me-1"></i>Gerar Link de Pagamento';
                            }
                        })
                        .catch(() => {
                            alert('Erro de conexão');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-link me-1"></i>Gerar Link de Pagamento';
                        });
                    }
                    </script>

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
