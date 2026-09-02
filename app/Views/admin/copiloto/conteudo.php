<div class="py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title"><?= __('admin.copilot.reference_content','Conteúdo de Referência') ?></h1>
            <p class="page-subtitle"><?= __('admin.copilot.reference_content_subtitle','Materiais que formam a inteligência de fundo da Bri') ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalUpload">
                <i class="fas fa-upload me-1"></i><?= __('admin.copilot.new_content','Novo Conteúdo') ?>
            </button>
            <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i><?= __('common.back','Voltar') ?>
            </a>
        </div>
    </div>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <?php if (empty($arquivos)): ?>
                <div class="card p-5 text-center text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3"></i>
                    <p><?= __('admin.copilot.no_reference_content','Nenhum conteúdo de referência cadastrado.') ?></p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUpload">
                        <i class="fas fa-upload me-1"></i><?= __('admin.copilot.upload_first_file','Enviar primeiro arquivo') ?>
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($arquivos as $arq): ?>
                    <div class="card mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-<?= $arq['status'] === 'ativo' ? 'success' : ($arq['status'] === 'processando' ? 'warning' : ($arq['status'] === 'erro' ? 'danger' : 'secondary')) ?> me-2">
                                    <?= strtoupper($arq['status']) ?>
                                </span>
                                <strong><?= htmlspecialchars($arq['titulo']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($arq['categoria']) ?> ·
                                    <?= htmlspecialchars($arq['arquivo_nome']) ?> ·
                                    <?= number_format($arq['arquivo_tamanho'] / 1024, 0) ?> KB ·
                                    <?= $arq['total_chunks'] ?> <?= __('admin.copilot.chunks','chunks') ?> ·
                                    <?= __('admin.copilot.uploaded_on','Enviado em') ?> <?= date('d/m/Y', strtotime($arq['criado_em'])) ?>
                                </small>
                                <?php if (!empty($arq['notas_ia'])): ?>
                                    <br><small class="text-info"><i class="fas fa-info-circle"></i> <?= htmlspecialchars(mb_substr($arq['notas_ia'], 0, 150)) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="/admin/copiloto/conteudo/toggle/<?= $arq['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $arq['ativo'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                        <?= $arq['ativo'] ? __('admin.copilot.deactivate','Desativar') : __('admin.copilot.activate','Ativar') ?>
                                    </button>
                                </form>
                                <form method="POST" action="/admin/copiloto/conteudo/remover/<?= $arq['id'] ?>"
                                    onsubmit="return confirm('<?= htmlspecialchars(__('admin.copilot.confirm_remove_content','Remover este conteúdo permanentemente?'), ENT_QUOTES, 'UTF-8') ?>')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

</div>

<!-- Modal de Upload -->
<div class="modal fade" id="modalUpload" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/admin/copiloto/conteudo/upload" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i><?= __('admin.copilot.new_reference_content','Novo Conteúdo de Referência') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="arquivo" class="form-label"><?= __('admin.copilot.file','Arquivo') ?></label>
                        <input type="file" class="form-control" id="arquivo" name="arquivo" required
                            accept=".pdf,.docx,.txt,.md">
                        <small class="text-muted"><?= __('admin.copilot.file_formats_hint','Formatos: PDF, DOCX, TXT, MD · Máx: 50MB') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="titulo" class="form-label"><?= __('admin.copilot.reference_title_internal','Título de referência (interno)') ?></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" placeholder="<?= htmlspecialchars(__('admin.copilot.reference_title_placeholder','Ex: Influence — Robert Cialdini'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="categoria" class="form-label"><?= __('admin.copilot.category','Categoria') ?></label>
                        <select class="form-select" id="categoria" name="categoria">
                            <option value="vendas_e_conversao"><?= __('admin.copilot.cat_sales_conversion','Vendas e Conversão') ?></option>
                            <option value="comportamento_consumidor"><?= __('admin.copilot.cat_consumer_behavior','Comportamento do Consumidor') ?></option>
                            <option value="produto_e_importacao"><?= __('admin.copilot.cat_product_import','Produto e Importação') ?></option>
                            <option value="engajamento"><?= __('admin.copilot.cat_engagement','Engajamento') ?></option>
                            <option value="precificacao"><?= __('admin.copilot.cat_pricing','Precificação') ?></option>
                            <option value="outro" selected><?= __('admin.copilot.cat_other','Outro') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notas_ia" class="form-label"><?= __('admin.copilot.ai_notes_optional','Notas para a IA (opcional)') ?></label>
                        <textarea class="form-control" id="notas_ia" name="notas_ia" rows="2"
                            placeholder="<?= htmlspecialchars(__('admin.copilot.ai_notes_placeholder','Ex: Use para calibrar como a Bri argumenta sobre urgência e escassez'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ativar_imediatamente" name="ativar_imediatamente" value="1" checked>
                        <label class="form-check-label" for="ativar_imediatamente"><?= __('admin.copilot.activate_after_processing','Ativar imediatamente após processamento') ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.cancel','Cancelar') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i><?= __('admin.copilot.upload_and_process','Enviar e processar') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
