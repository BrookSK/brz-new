<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><i class="fas fa-file-alt me-2 text-primary"></i>Documentação de Envios</h1>
        <button class="btn btn-sm btn-outline-primary" onclick="carregarDocumentacao()"><i class="fas fa-sync me-1"></i>Atualizar</button>
    </div>

    <p class="text-muted small mb-4">Baixe os PDFs dos containers e da fatura de cada embarque. Clique em "Baixar Docs" para baixar todos os documentos daquele embarque de uma vez.</p>

    <div id="doc-loading" class="text-center py-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 text-muted">Carregando embarques...</div>
    </div>

    <div id="doc-lista"></div>
</div>

<script>
const BASE = '/admin/etiquetas-wp';

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

async function carregarDocumentacao() {
    const el = document.getElementById('doc-lista');
    const loading = document.getElementById('doc-loading');
    el.innerHTML = '';
    loading.style.display = 'block';

    try {
        const [rEmb, rCnt, rFat] = await Promise.all([
            fetch(BASE + '/listar-embarques'),
            fetch(BASE + '/listar-containers?per_page=200'),
            fetch(BASE + '/listar-faturas?per_page=200')
        ]);
        const dEmb = await rEmb.json();
        const dCnt = await rCnt.json();
        const dFat = await rFat.json();
        loading.style.display = 'none';

        const embarques = (dEmb.success && dEmb.data) ? dEmb.data : [];
        const containers = (dCnt.success && dCnt.data) ? dCnt.data : [];
        const faturas = (dFat.success && dFat.data) ? dFat.data : [];

        if (!embarques.length) {
            el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="fas fa-inbox d-block mb-2" style="font-size:2rem;"></i>Nenhum embarque encontrado.</div></div>';
            return;
        }

        let html = '';
        embarques.forEach((emb, idx) => {
            const fl = emb.flight || {};
            const cn38Codes = Array.isArray(emb.cn38_codes) ? emb.cn38_codes : [];
            const depDate = fl.departureDate ? new Date(fl.departureDate).toLocaleDateString('pt-BR') : '?';
            const status = emb.status === 'confirmed'
                ? '<span class="badge bg-success">OK</span>'
                : '<span class="badge bg-danger">Erro</span>';

            html += '<div class="card border-0 shadow-sm mb-3">';
            html += '<div class="card-header d-flex justify-content-between align-items-center">';
            html += '<div><strong>Embarque - Voo ' + escHtml(String(fl.flightNumber || '-')) + '</strong>';
            html += ' <small class="text-muted ms-2">' + escHtml(fl.airlineCode || '') + '</small>';
            html += ' <small class="text-muted ms-2">' + depDate + '</small> ' + status + '</div>';
            html += '<button class="btn btn-sm btn-primary" onclick="baixarDocumentosEmbarque(' + idx + ')"><i class="fas fa-download me-1"></i>Baixar Docs</button>';
            html += '</div>';
            html += '<div class="card-body">';

            html += '<div class="row">';

            // Faturas
            html += '<div class="col-md-6 mb-3">';
            html += '<h6 class="small fw-bold mb-2"><i class="fas fa-file-invoice me-1 text-danger"></i>Faturas (CN38)</h6>';
            const embFaturas = faturas.filter(f => cn38Codes.includes(f.cn38_code));
            if (embFaturas.length > 0) {
                embFaturas.forEach(f => {
                    html += '<div class="d-flex align-items-center gap-2 mb-2 p-2 border rounded">';
                    html += '<code class="small">' + escHtml(f.cn38_code || '-') + '</code>';
                    if (f.wp_post_id) {
                        html += ' <a href="' + BASE + '/pdf/fatura/' + f.wp_post_id + '" target="_blank" class="btn btn-xs btn-outline-danger doc-pdf-link" data-url="' + BASE + '/pdf/fatura/' + f.wp_post_id + '" data-name="fatura_' + escHtml(f.cn38_code || '') + '"><i class="fas fa-file-pdf me-1"></i>PDF</a>';
                    }
                    html += '</div>';
                });
            } else {
                html += '<span class="text-muted small">Nenhuma fatura vinculada</span>';
            }
            html += '</div>';

            // Containers
            html += '<div class="col-md-6 mb-3">';
            html += '<h6 class="small fw-bold mb-2"><i class="fas fa-box me-1 text-primary"></i>Containers</h6>';
            const embContainerDns = [];
            embFaturas.forEach(f => {
                if (Array.isArray(f.dispatch_numbers)) {
                    f.dispatch_numbers.forEach(d => {
                        if (!embContainerDns.includes(d)) embContainerDns.push(d);
                    });
                }
            });
            const embContainers = containers.filter(c =>
                embContainerDns.includes(c.dispatch_number) ||
                (c.bill_id && embFaturas.some(f => f.wp_post_id === c.bill_id))
            );
            if (embContainers.length > 0) {
                embContainers.forEach(c => {
                    html += '<div class="d-flex align-items-center gap-2 mb-2 p-2 border rounded">';
                    html += '<span class="small">Remessa <strong>' + c.dispatch_number + '</strong></span>';
                    html += ' <code class="small">' + escHtml(c.unit_code || '') + '</code>';
                    if (c.wp_post_id) {
                        html += ' <a href="' + BASE + '/pdf/container/' + c.wp_post_id + '" target="_blank" class="btn btn-xs btn-outline-danger doc-pdf-link" data-url="' + BASE + '/pdf/container/' + c.wp_post_id + '" data-name="container_' + c.dispatch_number + '"><i class="fas fa-file-pdf me-1"></i>PDF</a>';
                    }
                    html += '</div>';
                });
            } else {
                html += '<span class="text-muted small">Nenhum container vinculado</span>';
            }
            html += '</div>';

            html += '</div>'; // row
            html += '</div>'; // card-body
            html += '</div>'; // card
        });

        el.innerHTML = html;
    } catch (e) {
        loading.style.display = 'none';
        el.innerHTML = '<div class="alert alert-danger">Erro ao carregar: ' + e.message + '</div>';
    }
}

async function baixarDocumentosEmbarque(idx) {
    const cards = document.querySelectorAll('#doc-lista .card');
    const card = cards[idx];
    if (!card) return;

    const links = card.querySelectorAll('.doc-pdf-link');
    if (!links.length) {
        alert('Nenhum PDF disponível para este embarque.');
        return;
    }

    const btn = card.querySelector('button');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Baixando...';
    }

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

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download me-1"></i>Baixar Docs';
    }

    if (downloaded > 0) {
        alert(downloaded + ' documento(s) baixado(s) com sucesso!');
    }
}

document.addEventListener('DOMContentLoaded', carregarDocumentacao);
</script>

<?php
$content = ob_get_clean();
$title = 'Documentação de Envios';
$sidebarActive = $sidebarActive ?? 'documentacao-envios';
require __DIR__ . '/../layouts/admin.php';
?>
