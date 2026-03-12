<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Novo container (Unitizador) - Correios Mundial (PACKET)</h1>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/correios-mundial/containers">Voltar</a>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/correios-mundial/containers/criar" class="card border-0 shadow-sm">
        <div class="card-header"><strong>Dados do container</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Número da remessa (dispatchNumber)</label>
                    <input type="number" class="form-control" name="dispatchNumber" value="<?= htmlspecialchars((string) ($defaults['dispatchNumber'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">País de origem</label>
                    <input type="text" class="form-control" name="originCountry" value="US" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operador origem</label>
                    <input type="text" class="form-control" name="originOperatorName" value="<?= htmlspecialchars((string) ($defaults['originOperatorName'] ?? 'BRAS')) ?>" maxlength="4" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operador destino</label>
                    <input type="text" class="form-control" name="destinationOperatorName" value="<?= htmlspecialchars((string) ($defaults['destinationOperatorName'] ?? 'SAOD')) ?>" maxlength="4" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Categoria postal</label>
                    <input type="text" class="form-control" name="postalCategoryCode" value="<?= htmlspecialchars((string) ($defaults['postalCategoryCode'] ?? 'A')) ?>" maxlength="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Subclasse serviço</label>
                    <select class="form-select" name="serviceSubclassCode" required>
                        <?php $ssc = (string) ($defaults['serviceSubclassCode'] ?? 'NX'); ?>
                        <option value="NX" <?= $ssc === 'NX' ? 'selected' : '' ?>>NX (padrão)</option>
                        <option value="IX" <?= $ssc === 'IX' ? 'selected' : '' ?>>IX (expresso)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo unidade</label>
                    <select class="form-select" name="unitType" required>
                        <?php $ut = (string) ($defaults['unitType'] ?? '2'); ?>
                        <option value="1" <?= $ut === '1' ? 'selected' : '' ?>>1 (saco até 30kg)</option>
                        <option value="2" <?= $ut === '2' ? 'selected' : '' ?>>2 (pallet até 500kg)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">AWB (nº do voo)</label>
                    <input type="text" class="form-control" name="awb" value="<?= htmlspecialchars((string) ($defaults['awb'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grupo de triagem</label>
                    <?php $tg = (string) ($defaults['triageGroup'] ?? '1'); ?>
                    <select class="form-select" name="triageGroup" required>
                        <option value="1" <?= $tg === '1' ? 'selected' : '' ?>>1 - São Paulo/SP</option>
                        <option value="2" <?= $tg === '2' ? 'selected' : '' ?>>2 - Valinhos/SP</option>
                        <option value="3" <?= $tg === '3' ? 'selected' : '' ?>>3 - Rio de Janeiro/RJ</option>
                        <option value="4" <?= $tg === '4' ? 'selected' : '' ?>>4 - Curitiba/PR</option>
                        <option value="5" <?= $tg === '5' ? 'selected' : '' ?>>5 - Curitiba/PR</option>
                    </select>
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Colar lista (pedidos ou tracking)</label>
                    <textarea class="form-control" name="bulk" rows="5" placeholder="Cole aqui: ex. 12345\nNC000005113BR\n..."><?= htmlspecialchars((string) ($defaults['bulk'] ?? '')) ?></textarea>
                    <div class="form-text">
                        Você pode colar IDs de pedido (números) e/ou trackingNumbers. O sistema tenta resolver e seleciona automaticamente.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Pacotes disponíveis</label>
                    <select class="form-select" name="trackingNumbers[]" multiple size="12" required>
                        <?php $available = isset($availablePackages) && is_array($availablePackages) ? $availablePackages : []; ?>
                        <?php $pre = isset($preselected) && is_array($preselected) ? $preselected : []; ?>
                        <?php if (empty($available)): ?>
                            <option value="">Nenhum pacote disponível</option>
                        <?php else: ?>
                            <?php foreach ($available as $p): ?>
                                <?php $trk = (string) ($p['tracking_number'] ?? ''); ?>
                                <?php $pid = (int) ($p['pedido_id'] ?? 0); ?>
                                <?php if ($trk === '') continue; ?>
                                <option value="<?= htmlspecialchars($trk) ?>" <?= in_array($trk, $pre, true) ? 'selected' : '' ?>>Pedido #<?= str_pad((string) $pid, 6, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($trk) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Use Ctrl/Shift para selecionar múltiplos.</div>
                </div>
            </div>

            <?php if (!empty($bulkResult) && is_array($bulkResult)): ?>
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong>Encontrados</strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['found'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong>Não encontrados</strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['not_found'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header"><strong>Já usados em container</strong></div>
                            <div class="card-body">
                                <pre style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars(implode("\n", (array) ($bulkResult['already_used'] ?? []))); ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Criar container</button>
        </div>
    </form>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
