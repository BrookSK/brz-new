<?php
/**
 * Partial de paginação compacta com ellipsis.
 * 
 * Variáveis esperadas:
 *   $paginacao_atual   - Página atual (int)
 *   $paginacao_total   - Total de páginas (int)
 *   $paginacao_url_fn  - Função/closure que recebe o número da página e retorna a URL (callable)
 * 
 * Formato: « Anterior | 1 ... 4 5 [6] 7 8 ... 42 | Próxima »
 */
$pCurrent = (int)($paginacao_atual ?? 1);
$pTotal = (int)($paginacao_total ?? 1);
if ($pTotal <= 1) return;

$pUrl = $paginacao_url_fn ?? function($p) { return '?pagina=' . $p; };
$pWindow = 2; // páginas ao redor da atual

$pStart = max(1, $pCurrent - $pWindow);
$pEnd = min($pTotal, $pCurrent + $pWindow);
?>
<nav aria-label="<?= htmlspecialchars(__('admin.pagination.aria', 'Paginação'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3">
    <ul class="pagination justify-content-center flex-wrap mb-0">
        <li class="page-item <?= $pCurrent <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $pCurrent > 1 ? htmlspecialchars($pUrl($pCurrent - 1)) : '#' ?>" tabindex="-1"><?= __('common.previous', 'Anterior') ?></a>
        </li>

        <?php if ($pStart > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($pUrl(1)) ?>">1</a></li>
            <?php if ($pStart > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($pi = $pStart; $pi <= $pEnd; $pi++): ?>
            <li class="page-item <?= $pi === $pCurrent ? 'active' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars($pUrl($pi)) ?>"><?= $pi ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($pEnd < $pTotal): ?>
            <?php if ($pEnd < $pTotal - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($pUrl($pTotal)) ?>"><?= $pTotal ?></a></li>
        <?php endif; ?>

        <li class="page-item <?= $pCurrent >= $pTotal ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $pCurrent < $pTotal ? htmlspecialchars($pUrl($pCurrent + 1)) : '#' ?>"><?= __('common.next', 'Próxima') ?></a>
        </li>
    </ul>
</nav>
