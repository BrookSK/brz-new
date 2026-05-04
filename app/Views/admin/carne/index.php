<?php $title = 'Carnê Braziliana - Admin'; ?>
<?php ob_start(); ?>
<?php
$statusLabels = [
    'aguardando_primeira_parcela' => ['label' => 'Aguardando 1ª Parcela', 'cor' => 'info'],
    'ativo' => ['label' => 'Ativo', 'cor' => 'primary'],
    'em_andamento' => ['label' => 'Em Andamento', 'cor' => 'primary'],
    'com_atraso' => ['label' => 'Com Atraso', 'cor' => 'danger'],
    'quitado' => ['label' => 'Quitado', 'cor' => 'success'],
    'inadimplente' => ['label' => 'Inadimplente', 'cor' => 'dark'],
    'liberado_envio' => ['label' => 'Liberado p/ Envio', 'cor' => 'success'],
    'encerrado' => ['label' => 'Encerrado', 'cor' => 'secondary'],
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-file-invoice-dollar me-2"></i> Carnê Braziliana</h1>
        <div>
            <a href="/admin/carnes/logs" class="btn btn-outline-info"><i class="fas fa-history me-1"></i> Logs</a>
            <a href="/admin/carnes/compras-internas" class="btn btn-outline-warning"><i class="fas fa-shopping-basket me-1"></i> Compras Internas</a>
            <a href="/admin/carnes/configuracoes" class="btn btn-outline-secondary"><i class="fas fa-cog me-1"></i> Configurações</a>
            <a href="/admin" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Recriar Carnê -->
    <div class="mb-4">
        <button class="btn btn-outline-warning btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#recriarCarneCollapse">
            <i class="fas fa-plus-circle me-1"></i> Recriar Carnê (pedido sem carnê)
        </button>
        <div class="collapse mt-2" id="recriarCarneCollapse">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small">ID do Pedido</label>
                            <input type="number" id="recriar-pedido-id" class="form-control form-control-sm" placeholder="Ex: 715" min="1">
                        </div>
                        <div class="col-md-auto">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="buscarDadosPedidoCarne()">
                                <i class="fas fa-search me-1"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div id="recriar-resultado" class="mt-3" style="display:none;">
                        <div id="recriar-info" class="alert alert-info small"></div>
                        <form method="POST" action="/admin/carnes/recriar" id="recriar-form" onsubmit="return validarCriacaoCarne()">
                            <input type="hidden" name="pedido_id" id="recriar-form-pedido-id">
                            <div class="row g-2 align-items-end" id="recriar-form-campos">
                                <div class="col-md-3">
                                    <label class="form-label small">Quantidade de Parcelas</label>
                                    <select name="quantidade_parcelas" id="recriar-parcelas" class="form-select form-select-sm">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i === 4 ? 'selected' : '' ?>><?= $i ?>x</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" id="recriar-btn-submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-plus me-1"></i> Criar Carnê
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div id="recriar-erro" class="mt-2 alert alert-danger small" style="display:none;"></div>
                    <small class="text-muted d-block mt-2">Para pedidos com forma_pagamento = carne_braziliana que não tiveram o carnê criado automaticamente.</small>
                </div>
            </div>
        </div>
    </div>
    <script>
    function formatarBRL(valor) {
        return valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function atualizarOptionsParcelas(d) {
        var sel = document.getElementById('recriar-parcelas');
        var totalBrl = Number(d.total_brl);
        for (var i = 0; i < sel.options.length; i++) {
            var n = parseInt(sel.options[i].value);
            var vlr = totalBrl / n;
            sel.options[i].text = n + 'x R$ ' + formatarBRL(vlr);
        }
    }
    function buscarDadosPedidoCarne() {
        var pid = document.getElementById('recriar-pedido-id').value;
        if (!pid || pid <= 0) { alert('Informe o ID do pedido'); return; }
        var info = document.getElementById('recriar-info');
        var resultado = document.getElementById('recriar-resultado');
        var erro = document.getElementById('recriar-erro');
        erro.style.display = 'none';
        resultado.style.display = 'none';
        info.textContent = 'Buscando...';
        resultado.style.display = '';

        fetch('/admin/carnes/buscar-pedido?pedido_id=' + encodeURIComponent(pid))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) {
                    resultado.style.display = 'none';
                    erro.textContent = d.error;
                    erro.style.display = '';
                    return;
                }
                document.getElementById('recriar-form-pedido-id').value = d.pedido_id;
                if (d.parcelas_sugeridas) {
                    document.getElementById('recriar-parcelas').value = d.parcelas_sugeridas;
                }
                // Guardar dados do pedido para recalcular ao mudar parcelas
                window._dadosPedidoCarne = d;
                atualizarOptionsParcelas(d);

                var qtdParcelas = d.parcelas_sugeridas || parseInt(document.getElementById('recriar-parcelas').value) || 4;
                var parcelaProdutos = Number(d.subtotal_brl) / qtdParcelas;
                var parcelaTaxas = Number(d.taxas_brl) / qtdParcelas;
                var parcelaTotal = Number(d.total_brl) / qtdParcelas;

                var html = '<strong>Pedido #' + d.pedido_id + '</strong> — ' + d.cliente_nome + ' (' + d.cliente_email + ')<br>';
                html += 'Forma: ' + d.forma_pagamento + ' | Moeda: ' + d.moeda + '<br>';
                html += 'Produtos: R$ ' + Number(d.subtotal_brl).toFixed(2);
                html += ' — <strong>Parcela: R$ ' + parcelaProdutos.toFixed(2) + '</strong><br>';
                html += 'Taxas + Impostos: R$ ' + Number(d.taxas_brl).toFixed(2);
                html += ' — <strong>Parcela: R$ ' + parcelaTaxas.toFixed(2) + '</strong><br>';
                html += '<strong>Total: R$ ' + Number(d.total_brl).toFixed(2) + '</strong>';
                html += ' — <strong>Parcela: R$ ' + parcelaTotal.toFixed(2) + ' (' + qtdParcelas + 'x)</strong>';
                if (d.parcelas_sugeridas) {
                    html += '<br><span class="text-success"><i class="fas fa-check-circle"></i> Parcelas encontradas no registro: <strong>' + d.parcelas_sugeridas + 'x</strong></span>';
                } else {
                    html += '<br><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Quantidade de parcelas não encontrada no registro. Selecione manualmente.</span>';
                }
                if (d.ja_tem_carne) {
                    html += '<br><span class="text-danger"><i class="fas fa-ban"></i> Este pedido já tem um carnê (ID: ' + d.carne_id + ')</span>';
                }
                info.innerHTML = html;

                // Bloquear formulário se já tem carnê
                var formCampos = document.getElementById('recriar-form-campos');
                var btnSubmit = document.getElementById('recriar-btn-submit');
                if (d.ja_tem_carne) {
                    formCampos.style.display = 'none';
                } else {
                    formCampos.style.display = '';
                }
            })
            .catch(function(e) {
                resultado.style.display = 'none';
                erro.textContent = 'Erro ao buscar: ' + e.message;
                erro.style.display = '';
            });
    }
    function validarCriacaoCarne() {
        var d = window._dadosPedidoCarne;
        if (d && d.ja_tem_carne) {
            alert('Este pedido já possui um carnê (ID: ' + d.carne_id + '). Não é possível criar outro.');
            return false;
        }
        return confirm('Criar carnê para este pedido?');
    }
    // Recalcular parcelas ao mudar o dropdown
    document.getElementById('recriar-parcelas').addEventListener('change', function() {
        var d = window._dadosPedidoCarne;
        if (!d) return;
        var qtd = parseInt(this.value) || 1;
        var parcelaProdutos = Number(d.subtotal_brl) / qtd;
        var parcelaTaxas = Number(d.taxas_brl) / qtd;
        var parcelaTotal = Number(d.total_brl) / qtd;
        var info = document.getElementById('recriar-info');

        var html = '<strong>Pedido #' + d.pedido_id + '</strong> — ' + d.cliente_nome + ' (' + d.cliente_email + ')<br>';
        html += 'Forma: ' + d.forma_pagamento + ' | Moeda: ' + d.moeda + '<br>';
        html += 'Produtos: R$ ' + Number(d.subtotal_brl).toFixed(2);
        html += ' — <strong>Parcela: R$ ' + parcelaProdutos.toFixed(2) + '</strong><br>';
        html += 'Taxas + Impostos: R$ ' + Number(d.taxas_brl).toFixed(2);
        html += ' — <strong>Parcela: R$ ' + parcelaTaxas.toFixed(2) + '</strong><br>';
        html += '<strong>Total: R$ ' + Number(d.total_brl).toFixed(2) + '</strong>';
        html += ' — <strong>Parcela: R$ ' + parcelaTotal.toFixed(2) + ' (' + qtd + 'x)</strong>';
        if (d.parcelas_sugeridas) {
            html += '<br><span class="text-success"><i class="fas fa-check-circle"></i> Parcelas encontradas no registro: <strong>' + d.parcelas_sugeridas + 'x</strong></span>';
        } else {
            html += '<br><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Quantidade de parcelas não encontrada no registro. Selecione manualmente.</span>';
        }
        if (d.ja_tem_carne) {
            html += '<br><span class="text-danger"><i class="fas fa-ban"></i> Este pedido já tem um carnê (ID: ' + d.carne_id + ')</span>';
        }
        info.innerHTML = html;
    });
    </script>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($statusLabels as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Cliente</label>
                    <input type="text" name="cliente" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>" placeholder="Nome ou email">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Pedido #</label>
                    <input type="text" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['pedido_id'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <div class="form-check"><input type="checkbox" name="com_atraso" value="1" class="form-check-input" <?= !empty($_GET['com_atraso']) ? 'checked' : '' ?>><label class="form-check-label small">Atraso</label></div>
                </div>
                <div class="col-md-1">
                    <div class="form-check"><input type="checkbox" name="liberado_compra" value="1" class="form-check-input" <?= !empty($_GET['liberado_compra']) ? 'checked' : '' ?>><label class="form-check-label small">Compra</label></div>
                </div>
                <div class="col-md-1">
                    <div class="form-check"><input type="checkbox" name="liberado_envio" value="1" class="form-check-input" <?= !empty($_GET['liberado_envio']) ? 'checked' : '' ?>><label class="form-check-label small">Envio</label></div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th><th>Pedido</th><th>Cliente</th><th>Total</th><th>Parcelas</th>
                            <th>Pagas</th><th>Atraso</th><th>Próx. Venc.</th><th>Status</th><th>Envio</th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($carnes)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-3">Nenhum carnê encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($carnes as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><a href="/admin/pedidos/detalhes/<?= $c['pedido_id'] ?>">#<?= $c['pedido_id'] ?></a></td>
                                <td><?= htmlspecialchars($c['cliente_nome']) ?></td>
                                <td>R$ <?= number_format($c['total_geral'], 2, ',', '.') ?></td>
                                <td><?= $c['quantidade_parcelas'] ?>x</td>
                                <td><?= $c['parcelas_pagas'] ?? 0 ?></td>
                                <td><?php if (($c['parcelas_atrasadas'] ?? 0) > 0): ?><span class="badge bg-danger"><?= $c['parcelas_atrasadas'] ?></span><?php else: ?>-<?php endif; ?></td>
                                <td><?= !empty($c['proximo_vencimento']) ? date('d/m/Y', strtotime($c['proximo_vencimento'])) : '-' ?></td>
                                <td><?php $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'cor' => 'secondary']; ?><span class="badge bg-<?= $st['cor'] ?>"><?= $st['label'] ?></span></td>
                                <td><?= $c['envio_liberado'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-muted"></i>' ?></td>
                                <td><a href="/admin/carnes/detalhes/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../../layouts/admin.php'; ?>
