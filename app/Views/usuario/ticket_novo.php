<?php ob_start(); ?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'tickets'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="mb-1"><?= __('ticket_new.title', 'Abrir Ticket') ?></h2>
                    <div class="text-muted small"><?= __('ticket_new.order', 'Pedido #{id}', ['id' => (int) ($pedidoId ?? 0)]) ?></div>
                </div>
                <div>
                    <a class="btn btn-outline-secondary" href="/meus-pedidos"><i class="fas fa-arrow-left me-1"></i><?= __('common.back', 'Voltar') ?></a>
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
                                <label class="form-label"><?= __('ticket_new.reason', 'Motivo do ticket') ?></label>
                                <select class="form-select" name="motivo" required>
                                    <option value=""><?= __('common.select', 'Selecione...') ?></option>
                                    <?php
                                        $motivos = [
                                            __('ticket_new.reasons.order_issue', 'Problema no pedido'),
                                            __('ticket_new.reasons.payment', 'Pagamento'),
                                            __('ticket_new.reasons.shipping_tracking', 'Envio / Rastreamento'),
                                            __('ticket_new.reasons.defective_product', 'Produto com defeito'),
                                            __('ticket_new.reasons.exchange_return', 'Troca / Devolução'),
                                            __('ticket_new.reasons.question', 'Dúvida'),
                                            __('ticket_new.reasons.other', 'Outro'),
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
                                <label class="form-label"><?= __('ticket_new.subject', 'Assunto') ?></label>
                                <input type="text" class="form-control" name="assunto" value="<?= htmlspecialchars((string) ($assunto ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= __('ticket_new.describe', 'Descreva o problema') ?></label>
                                <textarea class="form-control" name="mensagem" rows="4" required><?= htmlspecialchars((string) ($mensagem ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="form-text"><?= __('ticket_new.tip', 'Dica: inclua detalhes como item, tamanho/cor, e o que aconteceu.') ?></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= __('ticket_new.attach_images', 'Anexar imagens (opcional)') ?></label>
                                <input class="form-control" type="file" name="imagens[]" accept="image/jpeg,image/png,image/webp" multiple>
                                <div class="form-text"><?= __('ticket_new.attach_hint', 'JPG/PNG/WebP até 5MB por imagem.') ?></div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> <?= __('ticket_new.create', 'Criar ticket') ?>
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
