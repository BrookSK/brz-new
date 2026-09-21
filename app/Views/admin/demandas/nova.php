<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="page-title"><?= htmlspecialchars(__('admin.demands.new_request', 'Nova Solicitação'), ENT_QUOTES, 'UTF-8') ?></h1>

            <!-- Seletor de tipo -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <label class="form-label fw-bold"><?= htmlspecialchars(__('admin.demands.form.request_type', 'Tipo de solicitação'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <label class="btn btn-outline-primary px-4 py-3 flex-fill text-center" id="btn-tipo-funcao" style="cursor:pointer;">
                        <input type="radio" name="tipo_solicitacao" value="funcao" class="d-none" onchange="toggleTipo()" checked>
                        <i class="fas fa-rocket d-block fs-4 mb-1"></i><span class="fw-semibold"><?= htmlspecialchars(__('admin.demands.form.new_feature', 'Nova Função'), ENT_QUOTES, 'UTF-8') ?></span><br><small class="text-muted"><?= htmlspecialchars(__('admin.demands.form.new_feature_desc', 'Recurso, melhoria ou mudança'), ENT_QUOTES, 'UTF-8') ?></small>
                    </label>
                    <label class="btn btn-outline-danger px-4 py-3 flex-fill text-center" id="btn-tipo-bug" style="cursor:pointer;">
                        <input type="radio" name="tipo_solicitacao" value="bug" class="d-none" onchange="toggleTipo()">
                        <i class="fas fa-bug d-block fs-4 mb-1"></i><span class="fw-semibold"><?= htmlspecialchars(__('admin.demands.form.bug_error', 'Bug / Erro'), ENT_QUOTES, 'UTF-8') ?></span><br><small class="text-muted"><?= htmlspecialchars(__('admin.demands.form.bug_error_desc', 'Algo não funciona corretamente'), ENT_QUOTES, 'UTF-8') ?></small>
                    </label>
                </div>
            </div></div>

            <form method="POST" action="/admin/demandas/criar" id="formDemanda" novalidate enctype="multipart/form-data">
            <input type="hidden" name="tipo_solicitacao" id="hidden-tipo" value="funcao">

            <!-- BLOCO 1 (ambos) -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><?= htmlspecialchars(__('admin.demands.form.b1_title', '1. Identificação'), ENT_QUOTES, 'UTF-8') ?></h6></div><div class="card-body">
                <div class="mb-3"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.col.requester', 'Solicitante'), ENT_QUOTES, 'UTF-8') ?></label><input type="text" name="bloco1_solicitante" class="form-control req-field" value="<?= htmlspecialchars($nomeUsuario ?? '') ?>" readonly style="background:#f8fafc;"></div>
                <div class="mb-0"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.form.demand_title', 'Título da demanda'), ENT_QUOTES, 'UTF-8') ?></label><input type="text" name="bloco1_titulo" class="form-control req-field" placeholder="<?= htmlspecialchars(__('admin.demands.form.demand_title_ph', 'Ex: Produto grátis na primeira compra'), ENT_QUOTES, 'UTF-8') ?>"></div>
            </div></div>

            <!-- === FLUXO NOVA FUNÇÃO === -->
            <div id="blocos-funcao">

            <!-- Aviso sobre "Não se aplica" -->
            <div class="alert alert-secondary small mb-4 d-flex align-items-start gap-2" style="border-radius:10px;">
                <i class="fas fa-info-circle mt-1 text-secondary"></i>
                <div>
                    <strong><?= htmlspecialchars(__('admin.demands.form.na_notice_title', 'Sobre o "Não se aplica":'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(__('admin.demands.form.na_notice_text', 'Alguns campos possuem a opção de marcar como "Não se aplica" para casos simples (ex: um botão novo que não impacta financeiramente).'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars(__('admin.demands.form.na_notice_moderate', 'Use com moderação.'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(__('admin.demands.form.na_notice_warn', 'Se identificarmos uso excessivo ou inadequado, essa opção será removida e todos os campos voltarão a ser obrigatórios.'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <!-- BLOCO 2 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><?= htmlspecialchars(__('admin.demands.form.b2_title', '2. Por que você quer isso?'), ENT_QUOTES, 'UTF-8') ?></h6></div><div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b2_problem', 'O que está acontecendo hoje que te incomoda ou te prejudica?'), ENT_QUOTES, 'UTF-8') ?></label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b2_problem_hint', 'Descreva o problema atual de forma direta. Não escreva soluções ainda, apenas o problema.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco2_problema" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b2_improve', 'O que vai melhorar se essa demanda for executada?'), ENT_QUOTES, 'UTF-8') ?></label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b2_improve_hint', 'Descreva o resultado esperado.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco2_melhoria" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-0">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b2_consequence', 'O que acontece se essa demanda não for executada?'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco2_consequencia">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b2_consequence_hint', 'Descreva as consequências de não fazer.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco2_consequencia" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
            </div></div>

            <!-- BLOCO 3 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_title', '3. Quais são os impactos?'), ENT_QUOTES, 'UTF-8') ?></h6></div><div class="card-body">
                <div class="alert alert-warning small mb-4"><i class="fas fa-exclamation-triangle me-1"></i><strong><?= htmlspecialchars(__('admin.demands.form.b3_warn_strong', 'Você é responsável por entender e analisar cada impacto abaixo antes de enviar.'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(__('admin.demands.form.b3_warn_text', 'Campos genéricos como "não sei", "barato" ou "rápido" não são aceitos e farão a demanda ser devolvida.'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_1', '3.1 — Impacto financeiro direto'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco3_financeiro">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b3_1_hint', 'Quanto custa ou deixa de gerar? Seja específico com valores.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco3_financeiro" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_2', '3.2 — Impacto no capital de giro'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco3_capital_giro">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b3_2_hint', 'O negócio tem dinheiro disponível para financiar isso agora?'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco3_capital_giro" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_3', '3.3 — Impacto nos custos operacionais'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco3_custos_operacionais">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b3_3_hint', 'Comissão, embalagem, frete, atendimento, estoque — detalhe cada um.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco3_custos_operacionais" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_4', '3.4 — Impacto na jornada do cliente'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco3_jornada_cliente">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b3_4_hint', 'Descreva passo a passo o que o cliente faz.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco3_jornada_cliente" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_5', '3.5 — Impacto na equipe'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco3_equipe">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b3_5_hint', 'Alguém precisa fazer algo diferente? Quem? O que muda?'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco3_equipe" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
                <div class="mb-0">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b3_6', '3.6 — Conflito com regras existentes'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco3_conflitos">N/A</label></div>
                    <p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.form.b3_6_hint', 'Essa solicitação entra em conflito com algo que já existe?'), ENT_QUOTES, 'UTF-8') ?></p>
                    <textarea name="bloco3_conflitos" class="form-control req-funcao na-field" rows="4"></textarea>
                </div>
            </div></div>

            <!-- BLOCO 4 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><div class="d-flex justify-content-between align-items-center"><h6 class="fw-bold mb-0"><?= htmlspecialchars(__('admin.demands.form.b4_title', '4. Custo por etapa de execução'), ENT_QUOTES, 'UTF-8') ?></h6><label class="form-check-label small text-muted flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input me-1" id="na-etapas" onchange="toggleNaEtapas(this)">N/A</label></div></div><div class="card-body" id="etapas-body">
                <p class="text-muted small mb-3"><?= htmlspecialchars(__('admin.demands.form.b4_hint', 'Não escreva "barato", "rápido" ou "não sei". Se não souber o valor, pesquise antes de enviar.'), ENT_QUOTES, 'UTF-8') ?></p>
                <div id="etapas-container">
                    <div class="row g-2 mb-2 etapa-row"><div class="col-md-8"><input type="text" name="etapa_desc[]" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(__('admin.demands.form.step_desc_ph', 'Descrição da etapa'), ENT_QUOTES, 'UTF-8') ?>"></div><div class="col-md-4"><input type="text" name="etapa_custo[]" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(__('admin.demands.form.step_cost_ph', 'Custo estimado (R$)'), ENT_QUOTES, 'UTF-8') ?>"></div></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEtapa()"><i class="fas fa-plus me-1"></i><?= htmlspecialchars(__('admin.demands.form.add_step', 'Adicionar etapa'), ENT_QUOTES, 'UTF-8') ?></button>
            </div></div>

            <!-- BLOCO 5 -->
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><?= htmlspecialchars(__('admin.demands.form.b5_title', '5. O que precisa ser feito?'), ENT_QUOTES, 'UTF-8') ?></h6></div><div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b5_1', '5.1 — Isso cria algo novo ou muda algo que já existe?'), ENT_QUOTES, 'UTF-8') ?></label></div>
                    <textarea name="bloco5_novo_ou_existente" class="form-control req-funcao na-field" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b5_2', '5.2 — Tem alguma ferramenta, sistema ou aplicativo envolvido?'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco5_ferramentas">N/A</label></div>
                    <textarea name="bloco5_ferramentas" class="form-control req-funcao na-field" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b5_3', '5.3 — Tem alguma regra que a equipe precisa seguir?'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco5_regras">N/A</label></div>
                    <textarea name="bloco5_regras" class="form-control req-funcao na-field" rows="3"></textarea>
                </div>
                <div class="mb-0">
                    <div class="d-flex justify-content-between align-items-center"><label class="form-label fw-semibold small mb-0"><?= htmlspecialchars(__('admin.demands.form.b5_4', '5.4 — Quem vai usar isso no dia a dia?'), ENT_QUOTES, 'UTF-8') ?></label><label class="form-check-label small text-muted ms-2 flex-shrink-0" style="cursor:pointer;"><input type="checkbox" class="form-check-input na-check me-1" data-target="bloco5_usuarios">N/A</label></div>
                    <textarea name="bloco5_usuarios" class="form-control req-funcao na-field" rows="3"></textarea>
                </div>
            </div></div>

            </div><!-- /blocos-funcao -->

            <!-- === FLUXO BUG === -->
            <div id="blocos-bug" style="display:none;">

            <div class="card border-0 shadow-sm mb-4 border-danger" style="border-left:4px solid #ef4444!important;"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0 text-danger"><i class="fas fa-bug me-2"></i><?= htmlspecialchars(__('admin.demands.bug.title', '2. Identificação do Bug'), ENT_QUOTES, 'UTF-8') ?></h6></div><div class="card-body">
                <div class="mb-3"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.bug.error', 'Qual é o erro exato?'), ENT_QUOTES, 'UTF-8') ?></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.error_hint', 'Descreva a mensagem de erro, comportamento inesperado ou o que aparece na tela.'), ENT_QUOTES, 'UTF-8') ?></p><textarea name="bug_erro" class="form-control req-bug" rows="4" placeholder="<?= htmlspecialchars(__('admin.demands.bug.error_ph', "Ex: Ao clicar em 'Salvar', aparece tela branca e o pedido não é salvo."), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.bug.action', 'O que você estava fazendo quando o erro aconteceu?'), ENT_QUOTES, 'UTF-8') ?></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.action_hint', 'Descreva passo a passo o que fez antes do erro aparecer.'), ENT_QUOTES, 'UTF-8') ?></p><textarea name="bug_acao" class="form-control req-bug" rows="4" placeholder="<?= htmlspecialchars(__('admin.demands.bug.action_ph', 'Passo 1: Abri a página de pedidos. Passo 2: Cliquei em editar. Passo 3: Alterei o status...'), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.bug.when', 'Quando aconteceu?'), ENT_QUOTES, 'UTF-8') ?></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.when_hint', 'Data, hora aproximada e frequência (sempre acontece? às vezes? só uma vez?).'), ENT_QUOTES, 'UTF-8') ?></p><textarea name="bug_quando" class="form-control req-bug" rows="3" placeholder="<?= htmlspecialchars(__('admin.demands.bug.when_ph', 'Aconteceu hoje às 14h. Testei 3 vezes e sempre dá o mesmo erro.'), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.bug.where', 'Onde aconteceu? (URL ou tela)'), ENT_QUOTES, 'UTF-8') ?></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.where_hint', 'Cole a URL da página ou descreva qual tela/seção do sistema.'), ENT_QUOTES, 'UTF-8') ?></p><textarea name="bug_onde" class="form-control req-bug" rows="2" placeholder="https://novosite.brazilianashop.com.br/admin/pedidos/detalhes/732"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.bug.prints', 'Prints / Evidências'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger"><?= htmlspecialchars(__('admin.demands.bug.required', '*obrigatório'), ENT_QUOTES, 'UTF-8') ?></span></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.prints_hint', 'Tire print da tela com o erro e descreva o que aparece. Sem print, o bug não será aceito.'), ENT_QUOTES, 'UTF-8') ?></p><textarea name="bug_prints" class="form-control req-bug" rows="4" placeholder="<?= htmlspecialchars(__('admin.demands.bug.prints_ph', "Descreva o que aparece no print. Ex: Tela mostra 'Error 500'. Console mostra 'Unexpected token...'"), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold small"><i class="fas fa-paperclip me-1"></i><?= htmlspecialchars(__('admin.demands.bug.attach', 'Anexar Arquivos (prints, vídeos, etc)'), ENT_QUOTES, 'UTF-8') ?></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.attach_hint', 'Fotos, vídeos ou qualquer arquivo que ajude a entender o problema.'), ENT_QUOTES, 'UTF-8') ?></p><input type="file" name="arquivos_bug[]" multiple class="form-control form-control-sm" accept="image/*,video/*,.pdf,.doc,.docx,.zip"></div>
                <div class="mb-0"><label class="form-label fw-semibold small"><?= htmlspecialchars(__('admin.demands.bug.details', 'Explicação detalhada'), ENT_QUOTES, 'UTF-8') ?></label><p class="text-muted small mb-2"><?= htmlspecialchars(__('admin.demands.bug.details_hint', 'Qualquer informação adicional que ajude a entender e reproduzir o problema.'), ENT_QUOTES, 'UTF-8') ?></p><textarea name="bug_detalhes" class="form-control req-bug" rows="4" placeholder="<?= htmlspecialchars(__('admin.demands.bug.details_ph', 'Só acontece com pedidos em USD. Pedidos em BRL funcionam normalmente.'), ENT_QUOTES, 'UTF-8') ?>"></textarea></div>
            </div></div>

            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><?= htmlspecialchars(__('admin.demands.bug.priority_title', '3. Prioridade'), ENT_QUOTES, 'UTF-8') ?></h6></div><div class="card-body">
                <select name="bug_prioridade" class="form-select req-bug">
                    <option value=""><?= htmlspecialchars(__('admin.demands.bug.priority_select', 'Selecione a prioridade'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="critica"><?= htmlspecialchars(__('admin.demands.bug.priority_critical', '🔴 Crítica — Sistema parado ou dados corrompidos'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="alta"><?= htmlspecialchars(__('admin.demands.bug.priority_high', '🟠 Alta — Funcionalidade importante não funciona'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="media"><?= htmlspecialchars(__('admin.demands.bug.priority_medium', '🟡 Média — Funciona mas com problemas'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="baixa"><?= htmlspecialchars(__('admin.demands.bug.priority_low', '🟢 Baixa — Inconveniente menor'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div></div>

            </div><!-- /blocos-bug -->

            <button type="submit" class="btn btn-dark btn-lg w-100 mb-4"><i class="fas fa-paper-plane me-2"></i><?= htmlspecialchars(__('admin.demands.form.submit', 'Enviar Solicitação'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </div>
    </div>
</div>
<script>
window.DEMANDS_I18N = {
    step_desc: <?= json_encode(__('admin.demands.form.step_desc_ph', 'Descrição da etapa'), JSON_UNESCAPED_UNICODE) ?>,
    step_cost: <?= json_encode(__('admin.demands.form.step_cost_ph', 'Custo estimado (R$)'), JSON_UNESCAPED_UNICODE) ?>,
    na: <?= json_encode(__('admin.demands.form.na_value', 'Não se aplica'), JSON_UNESCAPED_UNICODE) ?>,
    required: <?= json_encode(__('admin.demands.form.field_required', 'Este campo é obrigatório. Preencha antes de continuar.'), JSON_UNESCAPED_UNICODE) ?>,
    fill_one_step: <?= json_encode(__('admin.demands.form.fill_one_step', 'Preencha ao menos uma etapa completa.'), JSON_UNESCAPED_UNICODE) ?>
};
function addEtapa() { document.getElementById('etapas-container').insertAdjacentHTML('beforeend', '<div class="row g-2 mb-2 etapa-row"><div class="col-md-8"><input type="text" name="etapa_desc[]" class="form-control form-control-sm" placeholder="' + window.DEMANDS_I18N.step_desc + '"></div><div class="col-md-4"><input type="text" name="etapa_custo[]" class="form-control form-control-sm" placeholder="' + window.DEMANDS_I18N.step_cost + '"></div></div>'); }

function toggleNaEtapas(cb) {
    var body = document.getElementById('etapas-body');
    var inputs = body.querySelectorAll('input[type="text"]');
    var btn = body.querySelector('button');
    if (cb.checked) {
        inputs.forEach(function(i) { i._origVal = i.value; i.value = window.DEMANDS_I18N.na; i.disabled = true; i.style.opacity = '0.5'; i.style.background = '#f1f5f9'; });
        if (btn) btn.style.display = 'none';
    } else {
        inputs.forEach(function(i) { i.value = i._origVal || ''; i.disabled = false; i.style.opacity = '1'; i.style.background = ''; });
        if (btn) btn.style.display = '';
    }
}

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
    document.querySelectorAll('.req-field').forEach(f => { if (!f.value.trim()) { valid = false; f.classList.add('is-invalid'); f.insertAdjacentHTML('afterend', '<div class="invalid-feedback">' + window.DEMANDS_I18N.required + '</div>'); } });

    // Validar campos do tipo selecionado (pular os marcados como N/A)
    document.querySelectorAll(reqClass).forEach(f => { if (f.disabled) return; if (!f.value.trim()) { valid = false; f.classList.add('is-invalid'); f.insertAdjacentHTML('afterend', '<div class="invalid-feedback">' + window.DEMANDS_I18N.required + '</div>'); } });

    // Validar etapas (só para função, pular se N/A)
    if (tipo === 'funcao' && !document.getElementById('na-etapas').checked) {
        const descs = document.querySelectorAll('input[name="etapa_desc[]"]'); const custos = document.querySelectorAll('input[name="etapa_custo[]"]');
        let temEtapa = false; for (let i = 0; i < descs.length; i++) { if (descs[i].value.trim() && custos[i].value.trim()) { temEtapa = true; break; } }
        if (!temEtapa) { valid = false; descs[0].classList.add('is-invalid'); descs[0].insertAdjacentHTML('afterend', '<div class="invalid-feedback">' + window.DEMANDS_I18N.fill_one_step + '</div>'); }
    }

    if (!valid) { e.preventDefault(); window.scrollTo({top: document.querySelector('.is-invalid').offsetTop - 100, behavior: 'smooth'}); }
});

// "Não se aplica" checkbox handler
document.querySelectorAll('.na-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var target = this.getAttribute('data-target');
        var textarea = document.querySelector('textarea[name="' + target + '"]');
        if (!textarea) return;
        if (this.checked) {
            textarea._originalValue = textarea.value;
            textarea.value = window.DEMANDS_I18N.na;
            textarea.disabled = true;
            textarea.style.opacity = '0.5';
            textarea.style.background = '#f1f5f9';
            textarea.classList.remove('is-invalid');
            var fb = textarea.nextElementSibling;
            if (fb && fb.classList.contains('invalid-feedback')) fb.remove();
        } else {
            textarea.value = textarea._originalValue || '';
            textarea.disabled = false;
            textarea.style.opacity = '1';
            textarea.style.background = '';
        }
    });
});

// Antes de submit, habilitar campos N/A para enviar o valor
document.getElementById('formDemanda').addEventListener('submit', function() {
    document.querySelectorAll('textarea[disabled], input[disabled]').forEach(function(t) { t.disabled = false; });
}, true);
</script>
