<?php ob_start(); ?>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">TERMOS E CONDIÇÕES DE USO - BRAZILIANA</h1>
                <p class="lead text-muted">Última Atualização: 01 de fevereiro de 2026</p>
            </div>

            <!-- Busca -->
            <div class="mb-5">
                <div class="input-group">
                    <input type="text" class="form-control" id="faq-search" placeholder="Buscar perguntas...">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
            </div>

            <!-- Perguntas e Respostas -->
            <div class="accordion" id="faqAccordion">
                <?php foreach ($faqItems as $i => $item): ?>
                <div class="accordion-item mb-3 faq-item" data-categoria="todos">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int) $item['id'] ?>">
                            <i class="fas fa-file-contract me-2"></i>
                            <?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </h2>
                    <div id="faq<?= (int) $item['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($item['conteudo'], ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('faq-search').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.faq-item').forEach(function(item) {
        const text = item.textContent.toLowerCase();
        item.style.display = (q === '' || text.includes(q)) ? '' : 'none';
    });
});
</script>
<?php
$content = ob_get_clean();
$title = 'FAQ - Braziliana';
include __DIR__ . '/../layouts/main.php';
?>
