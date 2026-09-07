<?php
namespace App\Controllers;

use App\Models\Cupom;
use App\Services\AuthService;
use App\Core\Request;

class AdminCuponsController extends Controller {
    private $model;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->model = new Cupom();
    }

    /** Lista de cupons */
    public function index(Request $request): void {
        $busca = trim((string) ($request->getParams()['busca'] ?? ''));
        $cupons = $this->model->listar($busca);
        $this->view('admin.cupons.index', compact('cupons', 'busca'));
    }

    /** Formulário de novo cupom */
    public function novo(Request $request): void {
        $cupom = null;
        $this->view('admin.cupons.form', compact('cupom'));
    }

    /** Formulário de edição */
    public function editar(Request $request): void {
        $id = (int) ($request->getParams()['id'] ?? 0);
        $cupom = $id > 0 ? $this->model->find($id) : null;
        if (!$cupom) {
            $this->setFlash('Cupom não encontrado.', 'danger');
            $this->redirect('/admin/cupons');
            return;
        }
        $this->view('admin.cupons.form', compact('cupom'));
    }

    /** Salvar (novo ou edição) */
    public function salvar(Request $request): void {
        $p = $request->getParams();
        $id = (int) ($p['id'] ?? 0);

        $codigo = Cupom::normalizarCodigo((string) ($p['codigo'] ?? ''));
        $descricao = trim((string) ($p['descricao'] ?? ''));
        $tipo = in_array(($p['tipo'] ?? ''), ['percentual', 'fixo'], true) ? $p['tipo'] : 'percentual';
        $valor = (float) str_replace(',', '.', (string) ($p['valor'] ?? '0'));
        $dataInicio = trim((string) ($p['data_inicio'] ?? '')) ?: null;
        $dataExpiracao = trim((string) ($p['data_expiracao'] ?? '')) ?: null;
        $limiteTotal = ($p['limite_uso_total'] ?? '') !== '' ? (int) $p['limite_uso_total'] : null;
        $limitePorUsuario = ($p['limite_uso_por_usuario'] ?? '') !== '' ? (int) $p['limite_uso_por_usuario'] : null;
        $valorMinimo = ($p['valor_minimo_pedido'] ?? '') !== '' ? (float) str_replace(',', '.', (string) $p['valor_minimo_pedido']) : null;
        $ativo = !empty($p['ativo']) ? 1 : 0;

        // Validações
        if ($codigo === '') {
            $this->setFlash('Informe o código do cupom.', 'danger');
            $this->redirect($id > 0 ? '/admin/cupons/editar/' . $id : '/admin/cupons/novo');
            return;
        }
        if ($valor <= 0) {
            $this->setFlash('Informe um valor de desconto maior que zero.', 'danger');
            $this->redirect($id > 0 ? '/admin/cupons/editar/' . $id : '/admin/cupons/novo');
            return;
        }
        if ($tipo === 'percentual' && $valor > 100) {
            $this->setFlash('Desconto percentual não pode ser maior que 100%.', 'danger');
            $this->redirect($id > 0 ? '/admin/cupons/editar/' . $id : '/admin/cupons/novo');
            return;
        }
        if ($dataInicio && $dataExpiracao && $dataExpiracao < $dataInicio) {
            $this->setFlash('A data de expiração não pode ser anterior à data de início.', 'danger');
            $this->redirect($id > 0 ? '/admin/cupons/editar/' . $id : '/admin/cupons/novo');
            return;
        }

        // Código único
        $existente = $this->model->findByCodigo($codigo);
        if ($existente && (int) $existente['id'] !== $id) {
            $this->setFlash('Já existe um cupom com este código.', 'danger');
            $this->redirect($id > 0 ? '/admin/cupons/editar/' . $id : '/admin/cupons/novo');
            return;
        }

        $data = [
            'codigo' => $codigo,
            'descricao' => $descricao !== '' ? $descricao : null,
            'tipo' => $tipo,
            'valor' => $valor,
            'data_inicio' => $dataInicio,
            'data_expiracao' => $dataExpiracao,
            'limite_uso_total' => $limiteTotal,
            'limite_uso_por_usuario' => $limitePorUsuario,
            'valor_minimo_pedido' => $valorMinimo,
            'ativo' => $ativo,
        ];

        if ($id > 0) {
            $this->model->update($id, $data);
            $this->setFlash('Cupom atualizado com sucesso.', 'success');
        } else {
            $this->model->create($data);
            $this->setFlash('Cupom criado com sucesso.', 'success');
        }
        $this->redirect('/admin/cupons');
    }

    /** Excluir cupom */
    public function excluir(Request $request): void {
        $id = (int) ($request->getParams()['id'] ?? 0);
        if ($id > 0) {
            $this->model->delete($id);
            $this->setFlash('Cupom excluído.', 'success');
        }
        $this->redirect('/admin/cupons');
    }

    /** Ativar/desativar rapidamente */
    public function toggle(Request $request): void {
        $id = (int) ($request->getParams()['id'] ?? 0);
        $cupom = $id > 0 ? $this->model->find($id) : null;
        if ($cupom) {
            $novo = ((int) $cupom['ativo'] === 1) ? 0 : 1;
            $this->model->update($id, ['ativo' => $novo]);
            $this->setFlash($novo ? 'Cupom ativado.' : 'Cupom desativado.', 'success');
        }
        $this->redirect('/admin/cupons');
    }

    /** Helper de mensagem flash */
    private function setFlash(string $message, string $type = 'info'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
}
