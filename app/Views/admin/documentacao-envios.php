<?php ob_start(); ?>
<style>
.doc-item{border:1px solid #e2e8f0;border-radius:10px;margin-bottom:10px;overflow:hidden;transition:box-shadow .2s;}
.doc-item:hover{box-shadow:0 2px 8px rgba(0,0,0,.06);}
.doc-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;cursor:pointer;background:#fff;gap:10px;user-select:none;}
.doc-header:hover{background:#f8fafc;}
.doc-header .doc-title{font-weight:600;font-size:.9rem;display:flex;align-items:center;gap:8px;flex:1;min-width:0;}
.doc-header .doc-meta{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.doc-header .doc-chevron{transition:transform .2s;color:#94a3b8;font-size:.7rem;}
.doc-item.open .doc-chevron{transform:rotate(180deg);color:#3b82f6;}
.doc-body{display:none;padding:0 16px 16px;background:#fff;}
.doc-item.open .doc-body{display:block;}
.doc-file{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;margin:4px 4px 4px 0;font-size:.8rem;background:#fff;transition:border-color .15s,background .15s;}
.doc-file:hover{border-color:#3b82f6;background:#eff6ff;}
.doc-file a{text-decoration:none;color:#1e293b;}
.doc-section-label{font-size:.7rem;text-transform:uppercase;font-weight:700;color:#64748b;margin-bottom:6px;letter-spacing:.5px;}
.doc-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;}
.doc-pagination button{border:1px solid #e2e8f0;background:#fff;border-radius:6px;padding:6px 12px;font-size:.8rem;cursor:pointer;transition:all .15s;}
.doc-pagination button:hover:not(:disabled){border-color:#3b82f6;color:#3b82f6;}
.doc-pagination button:disabled{opacity:.4;cursor:not-allowed;}
.doc-pagination button.active{background:#3b82f6;color:#fff;border-color:#3b82f6;}
.doc-pagination .doc-page-info{font-size:.8rem;color:#64748b;}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><i class="fas fa-file-alt me-2 text-primary"></i>Documentação de Envios</h1>
        <button class="btn btn-sm btn-outline-primary" onclick="carregarDocumentacao()"><i class="fas fa-sync me-1"></i>Atualizar</button>
    </div>

    <p class="text-muted small mb-3">Clique em uma fatura para expandir e ver os documentos. Use "Baixar Docs" para baixar todos os PDFs de uma vez.</p>

    <div id="doc-loading" class="text-center py-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 text-muted">Carregando...</div>
    </div>

    <div id="doc-lista"></div>
    <div id="doc-pagination"></div>
</div>

<script>
const BASE = '/admin/etiquetas-wp';
let allFaturas = [];
let allContainers = [];
const PER_PAGE = 10;
let currentPage = 1;

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

async function carregarDocumentacao() {
    const el = document.getElementById('doc-lista');
    const loading = document.getElementById('doc-loading');
    el.innerHTML = '';
    document.getElementById('doc-pagination').innerHTML = '';
    loading.style.display = 'block';

    try {
        const [rCnt, rFat] = await Promise.all([
            fetch(BASE + '/listar-containers?per_page=500'),
            fetch(BASE + '/listar-faturas?per_page=500')
        ]);
        const dCnt = await rCnt.json();
        const dFat = await rFat.json();
        loading.style.display = 'none';

        allContainers = (dCnt.success && dCnt.data) ? dCnt.data : [];
        allFaturas = (dFat.success && dFat.data) ? dFat.data : [];

        if (!allFaturas.length) {
            el.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-inbox d-block mb-2" style="font-size:2rem;"></i>Nenhuma fatura encontrada.</div>';
            return;
        }

        currentPage = 1;
        renderPage();
    } catch (e) {
        loading.style.display = 'none';
        el.innerHTML = '<div class="alert alert-danger">Erro ao carregar: ' + e.message + '</div>';
    }
}

function renderPage() {
    const el = document.getElementById('doc-lista');
    const totalPages = Math.ceil(allFaturas.length / PER_PAGE);
    const start = (currentPage - 1) * PER_PAGE;
    const end = Math.min(start + PER_PAGE, allFaturas.length);
    const pageItems = allFaturas.slice(start, end);

    let html = '';
    pageItems.forEach((fat, localIdx) => {
        const globalIdx = start + localIdx;
        const cn38 = fat.cn38_code || '-';
        const dns = Array.isArray(fat.dispatch_numbers) ? fat.dispatch_numbers.map(d => String(d)) : [];
        const isFirst = globalIdx === 0;
        const badge = isFirst ? '<span class="badge bg-info text-dark" style="font-size:.6rem;">Última</span>' : '';
        const status = fat.departure_id
            ? '<span class="badge bg-success" style="font-size:.65rem;">Embarcado</span>'
            : '<span class="badge bg-warning text-dark" style="font-size:.65rem;">Aguardando</span>';

        const fatContainers = allContainers.filter(c => dns.includes(String(c.dispatch_number)));
        const docsCount = (fat.wp_post_id ? 1 : 0) + fatContainers.filter(c => c.wp_post_id).length;

        html += '<div class="doc-item" id="doc-item-' + globalIdx + '">';
        html += '<div class="doc-header" onclick="toggleDocItem(' + globalIdx + ')">';
        html += '<div class="doc-title">';
        html += '<i class="fas fa-chevron-down doc-chevron"></i>';
        html += '<span>Fatura <strong>' + escHtml(cn38) + '</strong></span> ' + badge + ' ' + status;
        html += '<span class="text-muted small ms-2">Remessas: ' + escHtml(dns.join(', ') || '—') + '</span>';
        html += '</div>';
        html += '<div class="doc-meta">';
        html += '<span class="badge bg-light text-dark border" style="font-size:.7rem;">' + docsCount + ' PDF' + (docsCount !== 1 ? 's' : '') + '</span>';
        html += '<button class="btn btn-sm btn-primary py-1 px-2" onclick="event.stopPropagation();baixarDocsFatura(' + globalIdx + ')" style="font-size:.75rem;"><i class="fas fa-download me-1"></i>Baixar</button>';
        html += '</div>';
        html += '</div>';

        // Body (colapsado por padrão)
        html += '<div class="doc-body">';
        html += '<div class="row">';

        // Fatura PDF
        html += '<div class="col-md-4 mb-2">';
        html += '<div class="doc-section-label"><i class="fas fa-file-invoice me-1"></i>Fatura</div>';
        if (fat.wp_post_id) {
            html += '<div class="doc-file"><a href="' + BASE + '/pdf/fatura/' + fat.wp_post_id + '" target="_blank" class="doc-pdf-link" data-url="' + BASE + '/pdf/fatura/' + fat.wp_post_id + '" data-name="fatura_' + escHtml(cn38) + '"><i class="fas fa-file-pdf text-danger me-1"></i>' + escHtml(cn38) + '</a></div>';
        } else {
            html += '<span class="text-muted small">Indisponível</span>';
        }
        html += '</div>';

        // Containers
        html += '<div class="col-md-8 mb-2">';
        html += '<div class="doc-section-label"><i class="fas fa-box me-1"></i>Containers</div>';
        if (fatContainers.length > 0) {
            html += '<div class="d-flex flex-wrap">';
            fatContainers.forEach(c => {
                const tksCount = Array.isArray(c.tracking_codes) ? c.tracking_codes.length : 0;
                if (c.wp_post_id) {
                    html += '<div class="doc-file"><a href="' + BASE + '/pdf/container/' + c.wp_post_id + '" target="_blank" class="doc-pdf-link" data-url="' + BASE + '/pdf/container/' + c.wp_post_id + '" data-name="container_' + c.dispatch_number + '"><i class="fas fa-file-pdf text-danger me-1"></i>Remessa ' + c.dispatch_number + '</a><span class="text-muted ms-1" style="font-size:.7rem;">(' + tksCount + ' pct)</span></div>';
                } else {
                    html += '<div class="doc-file"><span class="text-muted"><i class="fas fa-box me-1"></i>Remessa ' + c.dispatch_number + '</span></div>';
                }
            });
            html += '</div>';
        } else {
            html += '<span class="text-muted small">Nenhum container</span>';
        }
        html += '</div>';

        html += '</div>'; // row
        html += '</div>'; // doc-body
        html += '</div>'; // doc-item
    });

    el.innerHTML = html;

    // Expandir o primeiro automaticamente
    if (pageItems.length > 0) {
        const firstItem = document.getElementById('doc-item-' + start);
        if (firstItem) firstItem.classList.add('open');
    }

    // Paginação
    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    const pag = document.getElementById('doc-pagination');
    if (totalPages <= 1) { pag.innerHTML = ''; return; }

    let html = '<div class="doc-pagination">';
    html += '<button onclick="goToPage(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';

    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

    if (startPage > 1) {
        html += '<button onclick="goToPage(1)">1</button>';
        if (startPage > 2) html += '<span class="doc-page-info">...</span>';
    }

    for (let i = startPage; i <= endPage; i++) {
        html += '<button onclick="goToPage(' + i + ')" class="' + (i === currentPage ? 'active' : '') + '">' + i + '</button>';
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += '<span class="doc-page-info">...</span>';
        html += '<button onclick="goToPage(' + totalPages + ')">' + totalPages + '</button>';
    }

    html += '<button onclick="goToPage(' + (currentPage + 1) + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
    html += '<span class="doc-page-info ms-2">' + allFaturas.length + ' fatura(s)</span>';
    html += '</div>';
    pag.innerHTML = html;
}

function goToPage(page) {
    const totalPages = Math.ceil(allFaturas.length / PER_PAGE);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderPage();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleDocItem(idx) {
    const item = document.getElementById('doc-item-' + idx);
    if (!item) return;
    item.classList.toggle('open');
}

async function baixarDocsFatura(globalIdx) {
    const item = document.getElementById('doc-item-' + globalIdx);
    if (!item) return;

    const links = item.querySelectorAll('.doc-pdf-link');
    if (!links.length) {
        alert('Nenhum PDF disponível.');
        return;
    }

    const btn = item.querySelector('.doc-meta button');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>...'; }

    let downloaded = 0;
    for (const link of links) {
        const url = link.getAttribute('data-url');
        const name = link.getAttribute('data-name') || 'documento';
        if (!url) continue;
        try {
            const resp = await fetch(url);
            if (!resp.ok) continue;
            const blob = await resp.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = name + '.pdf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
            downloaded++;
            await new Promise(r => setTimeout(r, 400));
        } catch (e) {
            console.error('Erro baixando ' + url, e);
        }
    }

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-download me-1"></i>Baixar'; }
    if (downloaded > 0) alert(downloaded + ' documento(s) baixado(s)!');
}

document.addEventListener('DOMContentLoaded', carregarDocumentacao);
</script>

<?php
$content = ob_get_clean();
$title = 'Documentação de Envios';
$sidebarActive = $sidebarActive ?? 'documentacao-envios';
require __DIR__ . '/../layouts/admin.php';
?>
