<?php $d = $demanda; $etapas = json_decode($d['bloco4_etapas'] ?? '[]', true) ?: []; $statusLabels = ['pendente'=>'Pendente','em_analise'=>'Em Análise','em_execucao'=>'Em Execução','em_teste'=>'Em Teste','recusado'=>'Recusado','concluido'=>'Concluído']; ?>
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

    <!-- Arquivos anexados (prints do bug) -->
    <?php if (!empty($arquivosBug)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-paperclip me-1"></i>Arquivos Anexados</h6></div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($arquivosBug as $arq):
                    $isImg = str_starts_with($arq['tipo'] ?? '', 'image/');
                    $isVideo = str_starts_with($arq['tipo'] ?? '', 'video/');
                ?>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-2 text-center">
                        <?php if ($isImg): ?>
                            <a href="<?= htmlspecialchars($arq['caminho']) ?>" target="_blank"><img src="<?= htmlspecialchars($arq['caminho']) ?>" class="img-fluid rounded mb-1" style="max-height:120px;object-fit:cover;"></a>
                        <?php elseif ($isVideo): ?>
                            <video src="<?= htmlspecialchars($arq['caminho']) ?>" controls class="w-100 rounded mb-1" style="max-height:120px;"></video>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($arq['caminho']) ?>" target="_blank" class="d-block py-3"><i class="fas fa-file fs-2 text-muted"></i></a>
                        <?php endif; ?>
                        <div class="text-truncate small text-muted"><?= htmlspecialchars($arq['nome_original']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chat / Comunicação -->
    <div class="card border-0 shadow-sm mb-4" id="chat">
        <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold small mb-0"><i class="fas fa-comments me-1"></i>Comunicação</h6>
            <span class="badge bg-secondary"><?= count($mensagens ?? []) ?></span>
        </div>
        <div class="card-body" style="max-height:400px;overflow-y:auto;" id="chat-body">
            <?php if (empty($mensagens)): ?>
                <div class="text-center text-muted small py-3"><i class="fas fa-inbox d-block mb-1 fs-4 opacity-50"></i>Nenhuma mensagem ainda. Use o campo abaixo para se comunicar.</div>
            <?php else: ?>
                <?php $meuId = $_SESSION['usuario_id'] ?? 0; ?>
                <?php foreach ($mensagens as $msg):
                    $isMeu = ((int)($msg['usuario_id'] ?? 0) === $meuId);
                ?>
                <div class="mb-3 d-flex <?= $isMeu ? 'justify-content-end' : 'justify-content-start' ?>">
                    <div class="<?= $isMeu ? 'bg-primary bg-opacity-10 border-primary' : 'bg-light' ?> border rounded p-2" style="max-width:80%;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold" style="font-size:11px;"><?= htmlspecialchars($msg['usuario_nome']) ?></span>
                            <span class="text-muted" style="font-size:10px;"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($msg['mensagem'])): ?>
                            <div class="small"><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($msg['arquivos'])): ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($msg['arquivos'] as $arq):
                                    $isImg = str_starts_with($arq['tipo'] ?? '', 'image/');
                                    $isVideo = str_starts_with($arq['tipo'] ?? '', 'video/');
                                ?>
                                    <?php if ($isImg): ?>
                                        <a href="<?= htmlspecialchars($arq['caminho']) ?>" target="_blank"><img src="<?= htmlspecialchars($arq['caminho']) ?>" class="rounded border" style="max-height:80px;max-width:120px;object-fit:cover;"></a>
                                    <?php elseif ($isVideo): ?>
                                        <video src="<?= htmlspecialchars($arq['caminho']) ?>" controls class="rounded border" style="max-height:80px;max-width:150px;"></video>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($arq['caminho']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="fas fa-download me-1"></i><?= htmlspecialchars($arq['nome_original']) ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white border-top">
            <form method="POST" action="/admin/demandas/<?= $d['id'] ?>/mensagem" enctype="multipart/form-data">
                <div class="d-flex gap-2">
                    <div class="flex-grow-1">
                        <textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Escreva uma mensagem..."></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div>
                        <label class="btn btn-sm btn-outline-secondary mb-0" style="cursor:pointer;">
                            <i class="fas fa-paperclip me-1"></i>Anexar
                            <input type="file" name="arquivos[]" multiple class="d-none" accept="image/*,video/*,.pdf,.doc,.docx,.zip" onchange="this.closest('label').querySelector('span')&&this.closest('label').querySelector('span').remove();var s=document.createElement('span');s.className='ms-1 badge bg-primary';s.textContent=this.files.length+' arquivo(s)';this.closest('label').appendChild(s);">
                        </label>
                        <span class="text-muted small ms-2">Imagens, vídeos, PDF, DOC, ZIP</span>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i>Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>
