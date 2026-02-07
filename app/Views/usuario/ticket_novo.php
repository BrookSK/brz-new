<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'tickets'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="mb-1">Abrir Ticket</h2>
                    <div class="text-muted small">Pedido #<?= (int) ($pedidoId ?? 0) ?></div>
                </div>
                <div>
                    <a class="btn btn-outline-secondary" href="/meus-pedidos"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="/meu-ticket/abrir/pedido/<?= (int) ($pedidoId ?? 0) ?>" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Motivo do ticket</label>
                                <select class="form-select" name="motivo" required>
                                    <option value="">Selecione...</option>
                                    <?php
                                        $motivos = [
                                            'Problema no pedido',
                                            'Pagamento',
                                            'Envio / Rastreamento',
                                            'Produto com defeito',
                                            'Troca / Devolução',
                                            'Dúvida',
                                            'Outro',
                                        ];
                                        $sel = trim((string) ($motivo ?? ''));
                                        foreach ($motivos as $m) {
                                            $s = ($sel === $m) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '" ' . $s . '>' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</option>';
                                        }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Assunto</label>
                                <input type="text" class="form-control" name="assunto" value="<?= htmlspecialchars((string) ($assunto ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Descreva o problema</label>
                                <textarea class="form-control" name="mensagem" rows="4" required><?= htmlspecialchars((string) ($mensagem ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="form-text">Dica: inclua detalhes como item, tamanho/cor, e o que aconteceu.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Anexar imagens (opcional)</label>
                                <input class="form-control" type="file" name="imagens[]" accept="image/jpeg,image/png,image/webp" multiple>
                                <div class="form-text">JPG/PNG/WebP até 5MB por imagem.</div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Criar ticket
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
