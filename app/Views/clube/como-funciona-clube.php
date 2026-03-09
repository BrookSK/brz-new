<?php
$siteLogo = '';
try {
    $raw = '';
    $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
    foreach ($tablesToTry as $t) {
        if ($raw !== '') break;
        try {
            $pdo = \Config\Database::getConnection();
            $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmtT->execute([$t]);
            if (!$stmtT->fetchColumn()) {
                continue;
            }
            $stmtCols = $pdo->query('DESCRIBE ' . $t);
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (!is_array($cols)) {
                $cols = [];
            }
            if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                if ($valCol !== '') {
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                    $stmt->execute(['layout', 'logo']);
                    $raw = (string) ($stmt->fetchColumn() ?: '');
                    if ($raw !== '') break;
                }
            }
            $keyCol = '';
            if (in_array('chave', $cols, true)) $keyCol = 'chave';
            elseif (in_array('key', $cols, true)) $keyCol = 'key';
            elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
            elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
            $valCol = '';
            if (in_array('valor', $cols, true)) $valCol = 'valor';
            elseif (in_array('value', $cols, true)) $valCol = 'value';
            elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
            if ($keyCol !== '' && $valCol !== '') {
                $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                $stmt->execute(['layout_logo']);
                $raw = (string) ($stmt->fetchColumn() ?: '');
                if ($raw !== '') break;
            }
            if (in_array('layout_logo', $cols, true)) {
                $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                $stmt2 = $pdo->query('SELECT layout_logo AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                $raw = (string) ($stmt2 ? ($stmt2->fetchColumn() ?: '') : '');
                if ($raw !== '') break;
            }
        } catch (\Exception $e) {
        }
    }
    $siteLogo = is_string($raw) ? trim($raw) : '';
} catch (\Exception $e) {
    $siteLogo = '';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Como funciona o Clube Braziliana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
    </style>
</head>
<body>

<div class="container" style="padding: 22px 0 0;">
    <div class="text-center mb-3">
        <?php if (!empty($siteLogo)): ?>
            <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" style="max-height: 52px; max-width: 100%; object-fit: contain;">
        <?php else: ?>
            <div style="font-weight:800; color:#0b1f3a; font-size: 20px;">Braziliana</div>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h3 mb-3" style="color:#0b1f3a; font-weight: 800;">Como funciona o Clube Braziliana</h1>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <div class="text-muted mb-2">Visão geral</div>
                    <div class="fw-semibold">O Clube Braziliana é um programa de benefícios baseado em créditos internos da plataforma, destinado a oferecer vantagens aos usuários em compras e serviços disponíveis no sistema. Ao participar, você aceita integralmente as regras abaixo.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.1. Ativação e participação</h2>
                    <div class="text-muted">
                        <div class="mb-2">Para participar do Clube Braziliana, o usuário deverá realizar um depósito mínimo de <strong>US$ 39,00</strong> (ou equivalente em USD) em créditos dentro de sua carteira interna na plataforma.</div>
                        <div class="mb-2">Esse valor corresponde ao depósito mínimo para ativação do programa.</div>
                        <div class="mb-2">Enquanto o saldo mínimo estiver mantido, o usuário poderá acessar os benefícios disponíveis dentro do Clube.</div>
                        <div>Caso o saldo fique abaixo do valor mínimo exigido, o acesso a determinadas funcionalidades e benefícios poderá ser temporariamente suspenso até a regularização do saldo.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.2. Limite máximo do Clube</h2>
                    <div class="text-muted">
                        <div class="mb-3">O Clube Braziliana possui um limite máximo de participação equivalente a <strong>R$ 150.000,00</strong> em créditos totais depositados pelos participantes.</div>

                        <div class="fw-semibold" style="color:#0b1f3a;">Quando esse limite for atingido:</div>
                        <ul class="mb-3">
                            <li>Novos depósitos no Clube poderão ser temporariamente suspensos</li>
                            <li>Novos participantes poderão ficar em lista de espera</li>
                            <li>Depósitos adicionais poderão não ser aceitos</li>
                        </ul>

                        <div class="mb-2">A liberação para novos depósitos poderá ocorrer novamente caso o saldo total do Clube volte a ficar abaixo do limite estabelecido.</div>
                        <div>A Braziliana poderá, a seu critério, alterar o limite máximo de participação do Clube a qualquer momento, conforme necessidades operacionais do sistema.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.3. Prazo de permanência e liberação de utilização</h2>
                    <div class="text-muted">
                        <div class="mb-3">Os créditos depositados para participação no Clube Braziliana ficam vinculados ao programa por um prazo mínimo de <strong>6 (seis) meses</strong>, contados a partir da data do primeiro depósito.</div>

                        <div class="fw-semibold" style="color:#0b1f3a;">Durante esse período:</div>
                        <ul class="mb-3">
                            <li>Os créditos permanecem ativos dentro da carteira do usuário</li>
                            <li>Os benefícios do Clube permanecem válidos</li>
                            <li>O saldo poderá ter restrições de utilização até o término do prazo mínimo</li>
                        </ul>

                        <div>Após o período mínimo de 6 meses, os créditos poderão ser utilizados normalmente dentro das funcionalidades disponíveis na plataforma.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.4. Produtos elegíveis</h2>
                    <div class="text-muted">Os benefícios do Clube são aplicáveis exclusivamente a produtos ou serviços identificados na plataforma como <strong>“Clube Ativo”</strong>. Produtos que não possuam essa identificação não participam das vantagens do programa.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.5. Benefícios do Clube</h2>
                    <div class="text-muted">
                        <div class="mb-2">Os participantes do Clube poderão receber benefícios como:</div>
                        <ul class="mb-3">
                            <li>Descontos em produtos ou serviços elegíveis</li>
                            <li>Cashback em créditos internos</li>
                            <li>Créditos adicionais gerados pelo sistema</li>
                            <li>Participação em sorteios e gratificações exclusivas para membros do Clube</li>
                        </ul>
                        <div class="mb-2">O cashback recebido retorna automaticamente para a carteira interna do usuário, na mesma moeda utilizada na operação.</div>
                        <div class="mb-2">Os benefícios podem variar de acordo com:</div>
                        <ul class="mb-0">
                            <li>Campanhas promocionais</li>
                            <li>Regras operacionais do sistema</li>
                            <li>Alterações realizadas pela plataforma</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.6. Sorteios e gratificações exclusivas</h2>
                    <div class="text-muted">
                        <div class="mb-2">Durante o período mínimo de 6 (seis) meses de participação no Clube, contados a partir do primeiro depósito, os membros poderão participar de:</div>
                        <ul class="mb-3">
                            <li>Sorteios promocionais</li>
                            <li>Campanhas de gratificação</li>
                            <li>Benefícios exclusivos para participantes do Clube</li>
                        </ul>
                        <div class="mb-2">Essas ações promocionais são eventuais e opcionais, podendo ocorrer em datas específicas ou campanhas internas.</div>
                        <div>A participação e as regras de cada sorteio ou gratificação poderão ser definidas em regulamentos próprios divulgados pela plataforma.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.7. Créditos adicionais do Clube</h2>
                    <div class="text-muted">
                        <div class="mb-3">Enquanto o saldo mínimo do Clube estiver ativo e respeitado o prazo mínimo de permanência, o sistema poderá gerar créditos adicionais mensais.</div>
                        <div class="mb-3">O Clube poderá gerar até <strong>10% ao mês</strong> em créditos internos, calculados sobre o saldo elegível mantido na carteira.</div>

                        <div class="fw-semibold" style="color:#0b1f3a;">Esses créditos:</div>
                        <ul class="mb-3">
                            <li>São gerados exclusivamente dentro do sistema</li>
                            <li>Possuem natureza promocional</li>
                            <li>Não são transferíveis para fora da plataforma</li>
                            <li>Não são saqueáveis</li>
                            <li>Podem ser utilizados para pagamentos de serviços ou produtos disponíveis na plataforma, incluindo redirecionamentos</li>
                        </ul>

                        <div>A Braziliana poderá alterar, reduzir, suspender ou encerrar a geração desses créditos adicionais a qualquer momento, sem necessidade de aviso prévio.</div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info" style="border-radius:14px;">
                <div class="fw-bold">18.8. Natureza dos créditos (importante)</div>
                <div class="small">O Clube Braziliana é exclusivamente um programa de benefícios internos da plataforma. A Braziliana não é instituição financeira. O Clube não constitui investimento. Os créditos não representam dinheiro. Os créditos não possuem garantia de rendimento. Os créditos não são saqueáveis. Os créditos não possuem conversão direta em moeda fiduciária. Todos os valores do Clube são créditos internos utilizados exclusivamente dentro do ecossistema da plataforma.</div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h5" style="color:#0b1f3a; font-weight: 800;">18.9. Auditoria e alterações</h2>
                    <div class="text-muted">
                        <div class="mb-2">Os créditos e benefícios do Clube podem ser auditados automaticamente pelo sistema a qualquer momento, com o objetivo de garantir o funcionamento correto do programa.</div>
                        <div class="mb-2">A Braziliana se reserva o direito de:</div>
                        <ul class="mb-0">
                            <li>Alterar regras do Clube</li>
                            <li>Modificar benefícios</li>
                            <li>Ajustar percentuais</li>
                            <li>Suspender funcionalidades</li>
                            <li>Encerrar o programa</li>
                            <li>Sempre que necessário para manutenção do sistema ou adequação às políticas operacionais</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
