<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h4 class="fw-bold mb-4"><i class="fas fa-file-alt me-2"></i>Nova Solicitação</h4>

            <!-- Seletor de tipo -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <label class="form-label fw-bold">Tipo de solicitação</label>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <label class="btn btn-outline-primary px-4 py-3 flex-fill text-center" id="btn-tipo-funcao" style="cursor:pointer;">
                        <input type="radio" name="tipo_solicitacao" value="funcao" class="d-none" onchange="toggleTipo()" checked>
                        <i class="fas fa-rocket d-block fs-4 mb-1"></i><span class="fw-semibold">Nova Função</span><br><small class="text-muted">Recurso, melhoria ou mudança</small>
                    </label>
                    <label class="btn btn-outline-danger px-4 py-3 flex-fill text-center" id="btn-tipo-bug" style="cursor:pointer;">
                        <input type="radio" name="tipo_solicitacao" value="bug" class="d-none" onchange="toggleTipo()">
                        <i class="fas fa-bug d-block fs-4 mb-1"></i><span class="fw-semibold">Bug / Erro</span><br><small class="text-muted">Algo não funciona corretamente</small>
                    </label>
                </div>
            </div></div>

            <form method="POST" action="/admin/demandas/criar" id="formDemanda" novalidate>
            <input type="hidden" name="tipo_solicitacao" id="hidden-tipo" value="funcao">

            <!-- BLOCO 1 (ambos) -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">1. Identificação</h6></div><div class="card-body">
                <div class="mb-3"><label class="form-label fw-semibold small">Solicitante</label><input type="text" name="bloco1_solicitante" class="form-control req-field" value="<?= htmlspecialchars($nomeUsuario ?? '') ?>" readonly style="background:#f8fafc;"></div>
                <div class="mb-0"><label class="form-label fw-semibold small">Título da demanda</label><input type="text" name="bloco1_titulo" class="form-control req-field" placeholder="Ex: Produto grátis na primeira compra"></div>
            </div></div>

            <!-- === FLUXO NOVA FUNÇÃO === -->
            <div id="blocos-funcao">

            <!-- BLOCO 2 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">2. Por que você quer isso?</h6></div><div class="card-body">
                <div class="mb-3"><label class="form-label fw-semibold small">O que está acontecendo hoje que te incomoda ou te prejudica?</label><p class="text-muted small mb-2">Descreva o problema atual de forma direta. Não escreva soluções ainda, apenas o problema.</p><textarea name="bloco2_problema" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">O que vai melhorar se essa demanda for executada?</label><p class="text-muted small mb-2">Descreva o resultado esperado.</p><textarea name="bloco2_melhoria" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-0"><label class="form-label fw-semibold small">O que acontece se essa demanda não for executada?</label><p class="text-muted small mb-2">Descreva as consequências de não fazer.</p><textarea name="bloco2_consequencia" class="form-control req-funcao" rows="4"></textarea></div>
            </div></div>

            <!-- BLOCO 3 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">3. Quais são os impactos?</h6></div><div class="card-body">
                <div class="alert alert-warning small mb-4"><i class="fas fa-exclamation-triangle me-1"></i><strong>Você é responsável por entender e analisar cada impacto abaixo antes de enviar.</strong> Campos genéricos como "não sei", "barato" ou "rápido" não são aceitos e farão a demanda ser devolvida.</div>
                <div class="mb-3"><label class="form-label fw-semibold small">3.1 — Impacto financeiro direto</label><p class="text-muted small mb-2">Quanto custa ou deixa de gerar? Seja específico com valores.</p><textarea name="bloco3_financeiro" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">3.2 — Impacto no capital de giro</label><p class="text-muted small mb-2">O negócio tem dinheiro disponível para financiar isso agora?</p><textarea name="bloco3_capital_giro" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">3.3 — Impacto nos custos operacionais</label><p class="text-muted small mb-2">Comissão, embalagem, frete, atendimento, estoque — detalhe cada um.</p><textarea name="bloco3_custos_operacionais" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">3.4 — Impacto na jornada do cliente</label><p class="text-muted small mb-2">Descreva passo a passo o que o cliente faz.</p><textarea name="bloco3_jornada_cliente" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">3.5 — Impacto na equipe</label><p class="text-muted small mb-2">Alguém precisa fazer algo diferente? Quem? O que muda?</p><textarea name="bloco3_equipe" class="form-control req-funcao" rows="4"></textarea></div>
                <div class="mb-0"><label class="form-label fw-semibold small">3.6 — Conflito com regras existentes</label><p class="text-muted small mb-2">Essa solicitação entra em conflito com algo que já existe?</p><textarea name="bloco3_conflitos" class="form-control req-funcao" rows="4"></textarea></div>
            </div></div>

            <!-- BLOCO 4 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">4. Custo por etapa de execução</h6></div><div class="card-body">
                <p class="text-muted small mb-3">Não escreva "barato", "rápido" ou "não sei". Se não souber o valor, pesquise antes de enviar.</p>
                <div id="etapas-container">
                    <div class="row g-2 mb-2 etapa-row"><div class="col-md-8"><input type="text" name="etapa_desc[]" class="form-control form-control-sm" placeholder="Descrição da etapa"></div><div class="col-md-4"><input type="text" name="etapa_custo[]" class="form-control form-control-sm" placeholder="Custo estimado (R$)"></div></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEtapa()"><i class="fas fa-plus me-1"></i>Adicionar etapa</button>
            </div></div>

            <!-- BLOCO 5 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">5. O que precisa ser feito?</h6></div><div class="card-body">
                <div class="mb-3"><label class="form-label fw-semibold small">5.1 — Isso cria algo novo ou muda algo que já existe?</label><textarea name="bloco5_novo_ou_existente" class="form-control req-funcao" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">5.2 — Tem alguma ferramenta, sistema ou aplicativo envolvido?</label><textarea name="bloco5_ferramentas" class="form-control req-funcao" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">5.3 — Tem alguma regra que a equipe precisa seguir?</label><textarea name="bloco5_regras" class="form-control req-funcao" rows="3"></textarea></div>
                <div class="mb-0"><label class="form-label fw-semibold small">5.4 — Quem vai usar isso no dia a dia?</label><textarea name="bloco5_usuarios" class="form-control req-funcao" rows="3"></textarea></div>
            </div></div>

            </div><!-- /blocos-funcao -->

            <!-- === FLUXO BUG === -->
            <div id="blocos-bug" style="display:none;">

            <div class="card border-0 shadow-sm mb-4 border-danger" style="border-left:4px solid #ef4444!important;"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0 text-danger"><i class="fas fa-bug me-2"></i>2. Identificação do Bug</h6></div><div class="card-body">
                <div class="mb-3"><label class="form-label fw-semibold small">Qual é o erro exato?</label><p class="text-muted small mb-2">Descreva a mensagem de erro, comportamento inesperado ou o que aparece na tela.</p><textarea name="bug_erro" class="form-control req-bug" rows="4" placeholder="Ex: Ao clicar em 'Salvar', aparece tela branca e o pedido não é salvo."></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">O que você estava fazendo quando o erro aconteceu?</label><p class="text-muted small mb-2">Descreva passo a passo o que fez antes do erro aparecer.</p><textarea name="bug_acao" class="form-control req-bug" rows="4" placeholder="Passo 1: Abri a página de pedidos. Passo 2: Cliquei em editar. Passo 3: Alterei o status..."></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">Quando aconteceu?</label><p class="text-muted small mb-2">Data, hora aproximada e frequência (sempre acontece? às vezes? só uma vez?).</p><textarea name="bug_quando" class="form-control req-bug" rows="3" placeholder="Aconteceu hoje às 14h. Testei 3 vezes e sempre dá o mesmo erro."></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">Onde aconteceu? (URL ou tela)</label><p class="text-muted small mb-2">Cole a URL da página ou descreva qual tela/seção do sistema.</p><textarea name="bug_onde" class="form-control req-bug" rows="2" placeholder="https://novosite.brazilianashop.com.br/admin/pedidos/detalhes/732"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small">Prints / Evidências</label><p class="text-muted small mb-2">Descreva o que aparece na tela. Se possível, tire print e cole aqui a descrição do que mostra.</p><textarea name="bug_prints" class="form-control req-bug" rows="3" placeholder="Print mostra mensagem 'Error 500' na tela. Console do navegador mostra 'Unexpected token...'"></textarea></div>
                <div class="mb-0"><label class="form-label fw-semibold small">Explicação detalhada</label><p class="text-muted small mb-2">Qualquer informação adicional que ajude a entender e reproduzir o problema.</p><textarea name="bug_detalhes" class="form-control req-bug" rows="4" placeholder="Só acontece com pedidos em USD. Pedidos em BRL funcionam normalmente."></textarea></div>
            </div></div>

            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">3. Prioridade</h6></div><div class="card-body">
                <select name="bug_prioridade" class="form-select req-bug">
                    <option value="">Selecione a prioridade</option>
                    <option value="critica">🔴 Crítica — Sistema parado ou dados corrompidos</option>
                    <option value="alta">🟠 Alta — Funcionalidade importante não funciona</option>
                    <option value="media">🟡 Média — Funciona mas com problemas</option>
                    <option value="baixa">🟢 Baixa — Inconveniente menor</option>
                </select>
            </div></div>

            </div><!-- /blocos-bug -->

            <button type="submit" class="btn btn-dark btn-lg w-100 mb-4"><i class="fas fa-paper-plane me-2"></i>Enviar Solicitação</button>
            </form>
        </div>
    </div>
</div>
<script>
function addEtapa() { document.getElementById('etapas-container').insertAdjacentHTML('beforeend', '<div class="row g-2 mb-2 etapa-row"><div class="col-md-8"><input type="text" name="etapa_desc[]" class="form-control form-control-sm" placeholder="Descrição da etapa"></div><div class="col-md-4"><input type="text" name="etapa_custo[]" class="form-control form-control-sm" placeholder="Custo estimado (R$)"></div></div>'); }

function toggleTipo() {
    const tipo = document.querySelector('input[name="tipo_solicitacao"]:checked').value;
    document.getElementById('hidden-tipo').value = tipo;
    document.getElementById('blocos-funcao').style.display = tipo === 'funcao' ? '' : 'none';
    document.getElementById('blocos-bug').style.display = tipo === 'bug' ? '' : 'none';
    document.getElementById('btn-tipo-funcao').classList.toggle('btn-primary', tipo === 'funcao');
    document.getElementById('btn-tipo-funcao').classList.toggle('btn-outline-primary', tipo !== 'funcao');
    document.getElementById('btn-tipo-bug').classList.toggle('btn-danger', tipo === 'bug');
    document.getElementById('btn-tipo-bug').classList.toggle('btn-outline-danger', tipo !== 'bug');
}
toggleTipo();

document.getElementById('formDemanda').addEventListener('submit', function(e) {
    let valid = true;
    const tipo = document.getElementById('hidden-tipo').value;
    const reqClass = tipo === 'bug' ? '.req-bug' : '.req-funcao';

    // Limpar erros anteriores
    document.querySelectorAll('.is-invalid').forEach(f => f.classList.remove('is-invalid'));
    document.querySelectorAll('.invalid-feedback').forEach(f => f.remove());

    // Validar campos obrigatórios comuns
    document.querySelectorAll('.req-field').forEach(f => { if (!f.value.trim()) { valid = false; f.classList.add('is-invalid'); f.insertAdjacentHTML('afterend', '<div class="invalid-feedback">Este campo é obrigatório. Preencha antes de continuar.</div>'); } });

    // Validar campos do tipo selecionado
    document.querySelectorAll(reqClass).forEach(f => { if (!f.value.trim()) { valid = false; f.classList.add('is-invalid'); f.insertAdjacentHTML('afterend', '<div class="invalid-feedback">Este campo é obrigatório. Preencha antes de continuar.</div>'); } });

    // Validar etapas (só para função)
    if (tipo === 'funcao') {
        const descs = document.querySelectorAll('input[name="etapa_desc[]"]'); const custos = document.querySelectorAll('input[name="etapa_custo[]"]');
        let temEtapa = false; for (let i = 0; i < descs.length; i++) { if (descs[i].value.trim() && custos[i].value.trim()) { temEtapa = true; break; } }
        if (!temEtapa) { valid = false; descs[0].classList.add('is-invalid'); descs[0].insertAdjacentHTML('afterend', '<div class="invalid-feedback">Preencha ao menos uma etapa completa.</div>'); }
    }

    if (!valid) { e.preventDefault(); window.scrollTo({top: document.querySelector('.is-invalid').offsetTop - 100, behavior: 'smooth'}); }
});
</script>
