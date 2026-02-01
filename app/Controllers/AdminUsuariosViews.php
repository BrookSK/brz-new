<?php
namespace App\Controllers;

class AdminUsuariosViews {
    
    public static function renderCardUsuario($usuario) {
        return '
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card user-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://ui-avatars.com/api/?name=' . urlencode($usuario['nome']) . '&background=4e73df&color=fff&size=60" class="user-avatar me-3" alt="Avatar">
                            <div>
                                <h6 class="card-title mb-1">' . htmlspecialchars($usuario['nome']) . '</h6>
                                <p class="text-muted small mb-0">' . htmlspecialchars($usuario['email']) . '</p>
                                ' . (!empty($usuario['switch']) ? '<p class="text-muted small mb-0">Switch: <strong>' . (int) $usuario['switch'] . '</strong></p>' : '') . '
                            </div>
                        </div>
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <small class="text-muted d-block">Pedidos</small>
                                <strong>' . $usuario['total_pedidos'] . '</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Carteira</small>
                                <strong class="text-success">$' . number_format($usuario['carteira_usd'], 2, '.', ',') . '</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Status</small>
                                <span class="badge ' . ($usuario['ativo'] ? 'bg-success' : 'bg-danger') . '">' . ($usuario['ativo'] ? 'Ativo' : 'Inativo') . '</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="/admin/usuarios/detalhes/' . $usuario['id'] . '" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>Ver
                            </a>
                            <div>
                                <button type="button" class="btn btn-sm btn-success" onclick="adicionarCredito(' . $usuario['id'] . ', \'' . htmlspecialchars($usuario['nome']) . '\')">
                                    <i class="fas fa-dollar-sign"></i>
                                </button>
                                <a href="/admin/usuarios/editar/' . $usuario['id'] . '" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="/admin/usuarios/excluir/' . $usuario['id'] . '" style="display: inline;">
                                    <button type="submit" onclick="return confirm(\'Tem certeza que deseja excluir este usuário?\')" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    public static function renderStatsCards($stats) {
        return '
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Usuários</h5>
                            <h3>' . ($stats['total_usuarios'] ?? 0) . '</h3>
                            <small>Cadastrados</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card carteira-badge text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total em Carteiras</h5>
                            <h3>$ ' . number_format($stats['total_carteira_usd'] ?? 0, 2, '.', ',') . '</h3>
                            <small>Em USD</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Usuários Ativos</h5>
                            <h3>' . ($stats['usuarios_ativos'] ?? 0) . '</h3>
                            <small>Online recentemente</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Novos Hoje</h5>
                            <h3>' . ($stats['usuarios_hoje'] ?? 0) . '</h3>
                            <small>Registros</small>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    public static function renderDetalhesUsuario($usuario, $pedidos) {
        $html = '
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <img src="https://ui-avatars.com/api/?name=' . urlencode($usuario['nome']) . '&background=4e73df&color=fff&size=120" class="rounded-circle mb-3" style="width: 120px; height: 120px;">
                            <h4>' . htmlspecialchars($usuario['nome']) . '</h4>
                            <p class="text-muted">' . htmlspecialchars($usuario['email']) . '</p>
                            <span class="badge ' . ($usuario['ativo'] ? 'bg-success' : 'bg-danger') . '">' . ($usuario['ativo'] ? 'Ativo' : 'Inativo') . '</span>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-wallet me-2"></i>Carteira</h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <h5 class="text-success">$' . number_format($usuario['carteira_usd'], 2, '.', ',') . '</h5>
                                <small class="text-muted">Saldo em USD</small>
                            </div>
                            <hr>
                            <div class="text-center">
                                <h5 class="text-info">R$ ' . number_format($usuario['carteira_brl'], 2, ',', '.') . '</h5>
                                <small class="text-muted">Saldo em BRL</small>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-success btn-sm w-100" onclick="adicionarCredito(' . $usuario['id'] . ', \'' . htmlspecialchars($usuario['nome']) . '\')">
                                    <i class="fas fa-dollar-sign me-1"></i>Adicionar Crédito
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" onclick="converterMoeda(' . $usuario['id'] . ')">
                                    <i class="fas fa-exchange-alt me-1"></i>Converter USD → BRL
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm w-100 mt-2" onclick="verExtrato(' . $usuario['id'] . ')">
                                    <i class="fas fa-list me-1"></i>Ver Extrato
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações Pessoais</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nome:</strong> ' . htmlspecialchars($usuario['nome']) . '</p>
                                    <p><strong>Email:</strong> ' . htmlspecialchars($usuario['email']) . '</p>
                                    <p><strong>CPF:</strong> ' . htmlspecialchars($usuario['cpf'] ?? 'Não informado') . '</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Telefone:</strong> ' . htmlspecialchars($usuario['telefone'] ?? 'Não informado') . '</p>
                                    <p><strong>Data Cadastro:</strong> ' . date('d/m/Y H:i', strtotime($usuario['created_at'])) . '</p>
                                    <p><strong>Última Atualização:</strong> ' . date('d/m/Y H:i', strtotime($usuario['updated_at'])) . '</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Últimos Pedidos</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Data</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    
                                    if (empty($pedidos)) {
                                        $html .= '<tr><td colspan="4" class="text-center text-muted">Nenhum pedido encontrado</td></tr>';
                                    } else {
                                        foreach ($pedidos as $pedido) {
                                            $html .= '
                                                <tr>
                                                    <td>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                    <td>' . date('d/m/Y', strtotime($pedido['created_at'])) . '</td>
                                                    <td>R$ ' . number_format($pedido['total'], 2, ',', '.') . '</td>
                                                    <td><span class="badge bg-info">' . htmlspecialchars($pedido['status']) . '</span></td>
                                                </tr>';
                                        }
                                    }
                                    
                                    $html .= '
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        
        return $html;
    }
    
    public static function renderModalAdicionarCredito() {
        return '
            <div class="modal fade" id="modalAdicionarCredito" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Adicionar Crédito</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formAdicionarCredito">
                                <input type="hidden" id="creditoUsuarioId">
                                <div class="mb-3">
                                    <label class="form-label">Nome do Usuário</label>
                                    <input type="text" class="form-control" id="creditoNomeUsuario" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Valor em USD</label>
                                    <input type="number" class="form-control" id="creditoValor" step="0.01" min="0.01" required>
                                    <small class="text-muted">O valor será adicionado em dólares americanos</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Descrição (opcional)</label>
                                    <input type="text" class="form-control" id="creditoDescricao" placeholder="Crédito adicionado pelo admin">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" onclick="confirmarAdicionarCredito()">
                                <i class="fas fa-dollar-sign me-1"></i>Adicionar Crédito
                            </button>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    public static function renderModalConverterMoeda() {
        return '
            <div class="modal fade" id="modalConverterMoeda" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Converter USD → BRL</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formConverterMoeda">
                                <input type="hidden" id="conversaoUsuarioId">
                                <div class="mb-3">
                                    <label class="form-label">Saldo Atual USD</label>
                                    <input type="text" class="form-control" id="saldoAtualUSD" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Valor para Converter (USD)</label>
                                    <input type="number" class="form-control" id="valorConverter" step="0.01" min="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Taxa de Conversão</label>
                                    <input type="number" class="form-control" id="taxaConversao" step="0.0001" value="5.50" required>
                                    <small class="text-muted">1 USD = R$ 5.50 (taxa atual)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Valor em BRL</label>
                                    <input type="text" class="form-control" id="valorBRL" readonly>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="confirmarConversao()">
                                <i class="fas fa-exchange-alt me-1"></i>Converter
                            </button>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    public static function renderModalCreditosLote() {
        return '
            <div class="modal fade" id="modalCreditosLote" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Adicionar Créditos em Lote</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCreditosLote">
                                <div class="mb-3">
                                    <label class="form-label">Valor em USD (para todos)</label>
                                    <input type="number" class="form-control" id="loteValor" step="0.01" min="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Descrição</label>
                                    <input type="text" class="form-control" id="loteDescricao" placeholder="Crédito em lote adicionado pelo admin">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Usuários (selecione)</label>
                                    <div class="border p-3" style="max-height: 300px; overflow-y: auto;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllUsers">
                                            <label class="form-check-label" for="selectAllUsers">
                                                <strong>Selecionar Todos</strong>
                                            </label>
                                        </div>
                                        <hr>
                                        <div id="usuariosLoteList">
                                            <!-- Será preenchido via JavaScript -->
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" onclick="confirmarCreditosLote()">
                                <i class="fas fa-users me-1"></i>Adicionar Créditos
                            </button>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    public static function getStyles() {
        return '
            <style>
            .user-card { transition: transform 0.2s; border-left: 4px solid #4e73df; }
            .user-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .user-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
            .carteira-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
            .stats-card { transition: all 0.3s; }
            .stats-card:hover { transform: scale(1.05); }
            </style>';
    }
    
    public static function getScripts() {
        return '
            <script>
            let usuariosData = [];
            
            function adicionarCredito(usuarioId, nomeUsuario) {
                document.getElementById("creditoUsuarioId").value = usuarioId;
                document.getElementById("creditoNomeUsuario").value = nomeUsuario;
                document.getElementById("creditoValor").value = "";
                document.getElementById("creditoDescricao").value = "Crédito adicionado pelo admin";
                
                const modal = new bootstrap.Modal(document.getElementById("modalAdicionarCredito"));
                modal.show();
            }
            
            function confirmarAdicionarCredito() {
                const usuarioId = document.getElementById("creditoUsuarioId").value;
                const valor = parseFloat(document.getElementById("creditoValor").value);
                const descricao = document.getElementById("creditoDescricao").value;
                
                if (!valor || valor <= 0) {
                    alert("Digite um valor válido maior que zero");
                    return;
                }
                
                fetch("/admin/usuarios/adicionar-credito", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        usuario_id: usuarioId,
                        valor: valor,
                        descricao: descricao
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Crédito adicionado com sucesso! $" + valor + " USD");
                        bootstrap.Modal.getInstance(document.getElementById("modalAdicionarCredito")).hide();
                        location.reload();
                    } else {
                        alert("Erro ao adicionar crédito: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao adicionar crédito");
                });
            }
            
            function converterMoeda(usuarioId) {
                const usuario = usuariosData.find(u => u.id == usuarioId);
                if (!usuario) return;
                
                document.getElementById("conversaoUsuarioId").value = usuarioId;
                document.getElementById("saldoAtualUSD").value = "$" + usuario.carteira_usd.toFixed(2);
                document.getElementById("valorConverter").value = "";
                document.getElementById("valorBRL").value = "";
                
                const modal = new bootstrap.Modal(document.getElementById("modalConverterMoeda"));
                modal.show();
            }
            
            document.getElementById("valorConverter")?.addEventListener("input", function() {
                const valor = parseFloat(this.value) || 0;
                const taxa = parseFloat(document.getElementById("taxaConversao").value) || 5.50;
                const valorBRL = valor * taxa;
                document.getElementById("valorBRL").value = "R$ " + valorBRL.toFixed(2);
            });
            
            document.getElementById("taxaConversao")?.addEventListener("input", function() {
                const valor = parseFloat(document.getElementById("valorConverter").value) || 0;
                const taxa = parseFloat(this.value) || 5.50;
                const valorBRL = valor * taxa;
                document.getElementById("valorBRL").value = "R$ " + valorBRL.toFixed(2);
            });
            
            function confirmarConversao() {
                const usuarioId = document.getElementById("conversaoUsuarioId").value;
                const valorUSD = parseFloat(document.getElementById("valorConverter").value);
                const taxa = parseFloat(document.getElementById("taxaConversao").value);
                
                if (!valorUSD || valorUSD <= 0) {
                    alert("Digite um valor válido maior que zero");
                    return;
                }
                
                fetch("/admin/carteira/converter-para-brl", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        usuario_id: usuarioId,
                        valor_usd: valorUSD,
                        taxa_conversao: taxa
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Conversão realizada com sucesso! R$ " + data.valor_brl.toFixed(2));
                        bootstrap.Modal.getInstance(document.getElementById("modalConverterMoeda")).hide();
                        location.reload();
                    } else {
                        alert("Erro ao converter: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao converter moeda");
                });
            }
            
            function adicionarCreditosEmLote() {
                const modal = new bootstrap.Modal(document.getElementById("modalCreditosLote"));
                modal.show();
            }
            
            document.getElementById("selectAllUsers")?.addEventListener("change", function() {
                const checkboxes = document.querySelectorAll("#usuariosLoteList input[type=\'checkbox\']");
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
            
            function confirmarCreditosLote() {
                const valor = parseFloat(document.getElementById("loteValor").value);
                const descricao = document.getElementById("loteDescricao").value;
                const checkboxes = document.querySelectorAll("#usuariosLoteList input[type=\'checkbox\']:checked");
                
                if (!valor || valor <= 0) {
                    alert("Digite um valor válido maior que zero");
                    return;
                }
                
                if (checkboxes.length === 0) {
                    alert("Selecione pelo menos um usuário");
                    return;
                }
                
                const usuarios = Array.from(checkboxes).map(cb => parseInt(cb.value));
                
                fetch("/admin/carteira/adicionar-creditos-em-lote", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        usuarios: usuarios,
                        valor: valor,
                        descricao: descricao
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        bootstrap.Modal.getInstance(document.getElementById("modalCreditosLote")).hide();
                        location.reload();
                    } else {
                        alert("Erro: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao adicionar créditos em lote");
                });
            }
            
            function verExtrato(usuarioId) {
                window.open("/admin/usuarios/extrato/" + usuarioId, "_blank");
            }
            </script>';
    }
}
