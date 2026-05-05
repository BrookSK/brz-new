<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <a href="/admin/copiloto/conversas" class="text-muted text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Voltar às conversas</a>
        <h2 class="mb-0 mt-1"><i class="fas fa-comments me-2"></i>Conversa — <?= htmlspecialchars($sessao['usuario_nome'] ?: 'Visitante') ?></h2>
        <p class="text-muted mb-0 small">
            <?= htmlspecialchars($sessao['usuario_email'] ?? '') ?>
            · <?= (int) $sessao['total_mensagens'] ?> mensagens
            · <?= date('d/m/Y H:i', strtotime($sessao['criado_em'])) ?> — <?= date('d/m/Y H:i', strtotime($sessao['ultima_interacao'])) ?>
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body" style="max-height:70vh;overflow-y:auto;display:flex;flex-direction:column;gap:12px;padding:20px;">
        <?php if (empty($mensagens)): ?>
            <div class="text-muted text-center py-5">Nenhuma mensagem nesta sessão.</div>
        <?php else: ?>
            <?php foreach ($mensagens as $m):
                $isUser = ($m['role'] === 'user');
                $isSystem = ($m['role'] === 'system');
            ?>
                <?php if ($isSystem): continue; endif; ?>
                <div style="align-self:<?= $isUser ? 'flex-end' : 'flex-start' ?>;max-width:75%;">
                    <div style="padding:10px 14px;border-radius:14px;background:<?= $isUser ? '#0b1f3a' : '#f1f5f9' ?>;color:<?= $isUser ? '#fff' : '#0f172a' ?>;white-space:pre-wrap;word-break:break-word;line-height:1.4;font-size:13px;">
                        <?= htmlspecialchars((string) ($m['conteudo'] ?? '')) ?>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1" style="font-size:11px;color:#94a3b8;">
                        <span><?= $isUser ? 'Cliente' : 'Bri' ?></span>
                        <span>·</span>
                        <span><?= date('d/m H:i:s', strtotime($m['criado_em'])) ?></span>
                        <?php if (!$isUser && !empty($m['acao']) && $m['acao'] !== 'nenhuma'): ?>
                            <span>·</span>
                            <span class="badge bg-info" style="font-size:10px;">ação: <?= htmlspecialchars($m['acao']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($m['tokens_usados'])): ?>
                            <span>·</span>
                            <span><?= (int) $m['tokens_usados'] ?> tokens</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
