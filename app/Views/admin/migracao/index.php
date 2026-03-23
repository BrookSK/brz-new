<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Migração de Produtos</h1>
    </div>

    <!-- Exportar -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Exportar do Servidor Atual</h6>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Exporte todos os produtos, categorias, variações e fotos do banco de dados atual para importar no novo servidor.
            </p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card border-left-primary h-100">
                        <div class="card-body">
                            <h6 class="font-weight-bold">Dados (JSON)</h6>
                            <p class="small text-muted">Exporta produtos, categorias, variações, fotos (referências) em um arquivo JSON.</p>
                            <a href="/admin/migracao/exportar" class="btn btn-primary btn-sm">
                                <i class="fas fa-download me-1"></i> Baixar JSON
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-left-success h-100">
                        <div class="card-body">
                            <h6 class="font-weight-bold">Imagens (ZIP)</h6>
                            <p class="small text-muted">Exporta todas as imagens dos produtos em um arquivo ZIP. Extraia na pasta <code>uploads/produtos/</code> do novo servidor.</p>
                            <a href="/admin/migracao/exportar-imagens" class="btn btn-success btn-sm" id="btnExportImg">
                                <i class="fas fa-images me-1"></i> Baixar ZIP de Imagens
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Importar -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">Importar no Novo Servidor</h6>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Envie o arquivo JSON exportado do servidor antigo. Produtos com mesmo SKU serão ignorados (sem duplicar).
                <br><strong>Lembre-se:</strong> extraia o ZIP de imagens na pasta <code>uploads/produtos/</code> antes de importar.
            </p>

            <div class="mb-3">
                <label for="arquivoJson" class="form-label">Arquivo JSON de exportação</label>
                <input type="file" class="form-control" id="arquivoJson" accept=".json">
            </div>

            <button type="button" class="btn btn-success" id="btnImportar" disabled>
                <i class="fas fa-upload me-1"></i> Importar Produtos
            </button>

            <div id="importResult" class="mt-3" style="display:none;"></div>
            <div id="importProgress" class="mt-3" style="display:none;">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                <span>Importando, aguarde...</span>
            </div>
        </div>
    </div>

    <!-- Instruções -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-info">Passo a Passo</h6>
        </div>
        <div class="card-body">
            <ol class="mb-0">
                <li>No servidor antigo (Apache), acesse esta página e clique em <strong>Baixar JSON</strong> e <strong>Baixar ZIP de Imagens</strong>.</li>
                <li>No novo servidor (Nginx), extraia o ZIP na pasta <code>/uploads/produtos/</code> dentro do public.</li>
                <li>No novo servidor, acesse esta mesma página e faça o upload do arquivo JSON.</li>
                <li>Clique em <strong>Importar Produtos</strong>. Produtos com SKU já existente serão ignorados automaticamente.</li>
            </ol>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('arquivoJson');
    const btnImportar = document.getElementById('btnImportar');
    const resultDiv = document.getElementById('importResult');
    const progressDiv = document.getElementById('importProgress');

    fileInput.addEventListener('change', function() {
        btnImportar.disabled = !this.files.length;
    });

    btnImportar.addEventListener('click', function() {
        const file = fileInput.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('arquivo', file);

        btnImportar.disabled = true;
        progressDiv.style.display = 'block';
        resultDiv.style.display = 'none';

        fetch('/admin/migracao/importar', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';

            if (data.error) {
                resultDiv.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                btnImportar.disabled = false;
                return;
            }

            const s = data.stats || {};
            let html = '<div class="alert alert-success">';
            html += '<strong>Importação concluída!</strong><br>';
            html += 'Categorias: ' + (s.categorias_importadas || 0) + '<br>';
            html += 'Produtos: ' + (s.produtos_importados || 0) + '<br>';
            html += 'Fotos: ' + (s.fotos_importadas || 0) + '<br>';
            html += 'Variações: ' + (s.variacoes_importadas || 0) + '<br>';

            if (s.erros && s.erros.length > 0) {
                html += '<hr><strong>Avisos/Erros:</strong><ul>';
                s.erros.forEach(function(e) { html += '<li>' + e + '</li>'; });
                html += '</ul>';
            }

            html += '</div>';
            resultDiv.innerHTML = html;
        })
        .catch(err => {
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="alert alert-danger">Erro de conexão: ' + err.message + '</div>';
            btnImportar.disabled = false;
        });
    });
});
</script>
