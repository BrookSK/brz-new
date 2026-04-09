<?php include __DIR__ . '/../../layouts/admin.php'; ?>

<div class="container-fluid admin-shell">
    <div class="row">
        <?php renderAdminSidebar($activePage ?? 'copiloto-conteudo'); ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-book me-2"></i>Conteúdo de Referência</h2>
                    <p class="text-muted mb-0">Materiais que formam a inteligência de fundo da Bri</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalUpload">
                        <i class="fas fa-upload me-1"></i>Novo Conteúdo
                    </button>
                    <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
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
                    <p>Nenhum conteúdo de referência cadastrado.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUpload">
                        <i class="fas fa-upload me-1"></i>Enviar primeiro arquivo
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
                                    <?= $arq['total_chunks'] ?> chunks ·
                                    Enviado em <?= date('d/m/Y', strtotime($arq['criado_em'])) ?>
                                </small>
                                <?php if (!empty($arq['notas_ia'])): ?>
                                    <br><small class="text-info"><i class="fas fa-info-circle"></i> <?= htmlspecialchars(mb_substr($arq['notas_ia'], 0, 150)) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="/admin/copiloto/conteudo/toggle/<?= $arq['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $arq['ativo'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                        <?= $arq['ativo'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                                <form method="POST" action="/admin/copiloto/conteudo/remover/<?= $arq['id'] ?>"
                                    onsubmit="return confirm('Remover este conteúdo permanentemente?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Modal de Upload -->
<div class="modal fade" id="modalUpload" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/admin/copiloto/conteudo/upload" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Novo Conteúdo de Referência</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="arquivo" class="form-label">Arquivo</label>
                        <input type="file" class="form-control" id="arquivo" name="arquivo" required
                            accept=".pdf,.docx,.txt,.md">
                        <small class="text-muted">Formatos: PDF, DOCX, TXT, MD · Máx: 50MB</small>
                    </div>
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título de referência (interno)</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ex: Influence — Robert Cialdini">
                    </div>
                    <div class="mb-3">
                        <label for="categoria" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria" name="categoria">
                            <option value="vendas_e_conversao">Vendas e Conversão</option>
                            <option value="comportamento_consumidor">Comportamento do Consumidor</option>
                            <option value="produto_e_importacao">Produto e Importação</option>
                            <option value="engajamento">Engajamento</option>
                            <option value="precificacao">Precificação</option>
                            <option value="outro" selected>Outro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notas_ia" class="form-label">Notas para a IA (opcional)</label>
                        <textarea class="form-control" id="notas_ia" name="notas_ia" rows="2"
                            placeholder="Ex: Use para calibrar como a Bri argumenta sobre urgência e escassez"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ativar_imediatamente" name="ativar_imediatamente" value="1" checked>
                        <label class="form-check-label" for="ativar_imediatamente">Ativar imediatamente após processamento</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Enviar e processar</button>
                </div>
            </form>
        </div>
    </div>
</div>
