<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Pedido;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Carrinho;
use App\Services\AuthService;

class CobrancaController extends Controller {
    private $pedidoModel;
    private $clienteModel;
    private $produtoModel;
    private $authService;
    private $carrinhoModel;

    public function __construct() {
        $this->pedidoModel = new Pedido();
        $this->clienteModel = new Cliente();
        $this->produtoModel = new Produto();
        $this->authService = new AuthService();
        $this->carrinhoModel = new Carrinho();
    }

    private function hydrateCartFromCookie(): void {
        try {
            if (!empty($_SESSION['carrinho']) || empty($_COOKIE['guest_cart'])) {
                return;
            }
            $raw = (string) $_COOKIE['guest_cart'];
            $decoded = base64_decode($raw, true);
            if ($decoded === false || $decoded === '') {
                return;
            }
            $arr = json_decode($decoded, true);
            if (is_array($arr) && !empty($arr)) {
                $_SESSION['carrinho'] = $arr;
            }
        } catch (\Throwable $e) {
        }
    }

    private function getLoggedUserId(): int {
        try {
            $u = $this->authService->getUsuarioLogado();
            $uid = (int) ($u['id'] ?? 0);
            return $uid > 0 ? $uid : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getCarrinhoFromDb(int $usuarioId): array {
        if ($usuarioId <= 0) return [];
        try {
            $cart = $this->carrinhoModel->getOrCreateCarrinho($usuarioId, null, 'BRL');
            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
            if ($cartId <= 0) return [];

            $db = $this->carrinhoModel->getConnection();

            $cols = [];
            try {
                $stCols = $db->query('DESCRIBE carrinho_items');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $cols = [];
            }

            $unitCol = (is_array($cols) && in_array('preco_unitario', $cols, true)) ? 'preco_unitario' : 'valor_unitario';
            $varCol = (is_array($cols) && in_array('produto_variacao_id', $cols, true))
                ? 'produto_variacao_id'
                : ((is_array($cols) && in_array('variacao_id', $cols, true)) ? 'variacao_id' : 'produto_variacao_id');

            $st = $db->prepare('SELECT *, ' . $unitCol . ' AS unit_price, ' . $varCol . ' AS var_id FROM carrinho_items WHERE carrinho_id = ?');
            $st->execute([$cartId]);
            $items = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $out = [];
            foreach (($items ?: []) as $it) {
                $pid = (int) ($it['produto_id'] ?? 0);
                if ($pid <= 0) continue;
                $pvId = (int) ($it['var_id'] ?? ($it['produto_variacao_id'] ?? ($it['variacao_id'] ?? 0)));
                $key = ((string) $pid) . ':' . ((string) $pvId);
                $qtd = (int) ($it['quantidade'] ?? 1);
                if ($qtd < 1) $qtd = 1;
                $vu = (float) ($it['unit_price'] ?? ($it['valor_unitario'] ?? ($it['preco_unitario'] ?? 0)));
                $sub = (float) ($it['subtotal'] ?? ($vu * $qtd));
                $out[$key] = [
                    'produto_id' => $pid,
                    'produto_variacao_id' => ($pvId > 0 ? $pvId : null),
                    'variacao_descricao' => $it['variacao_descricao'] ?? null,
                    'nome' => $it['nome'] ?? null,
                    'price' => $vu,
                    'preco_unitario' => $vu,
                    'quantidade' => $qtd,
                    'subtotal' => $sub,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function index(Request $request) {
        session_start();
        $this->hydrateCartFromCookie();

        $uid = $this->getLoggedUserId();
        $carrinho = [];
        if ($uid > 0) {
            $carrinho = $this->getCarrinhoFromDb($uid);
        }
        if (empty($carrinho)) {
            $carrinho = $_SESSION['carrinho'] ?? [];
        }
        
        if (empty($carrinho)) {
            $this->redirect('/carrinho');
        }

        $items = array_values($carrinho);
        $subtotal = 0.0;
        foreach ($items as $it) {
            $subtotal += (float) ($it['subtotal'] ?? 0);
        }
        
        $this->view('cobranca/index', [
            'carrinho' => $items,
            'subtotal' => $subtotal,
        ]);
    }

    public function calcular(Request $request) {
        session_start();
        $this->hydrateCartFromCookie();

        $uid = $this->getLoggedUserId();
        $carrinho = [];
        if ($uid > 0) {
            $carrinho = $this->getCarrinhoFromDb($uid);
        }
        if (empty($carrinho)) {
            $carrinho = $_SESSION['carrinho'] ?? [];
        }
        
        if (empty($carrinho)) {
            $this->json(['error' => 'Carrinho vazio'], 400);
        }
        
        $servicosSelecionados = $request->getParam('servicos', []);
        if (is_string($servicosSelecionados)) {
            $servicosSelecionados = array_values(array_filter(array_map('trim', explode(',', $servicosSelecionados))));
        }
        if (!is_array($servicosSelecionados)) {
            $servicosSelecionados = [];
        }
        $clienteData = $request->getParams();
        
        $subtotal = 0.0;
        foreach (array_values($carrinho) as $it) {
            $subtotal += (float) ($it['subtotal'] ?? 0);
        }
        
        $servicosDisponiveis = [
            'despacho' => 150.00,
            'translado' => 350.00,
            'armazenamento' => 50.00,
            'envio' => 25.00
        ];
        
        $totalServicos = 0;
        foreach ($servicosSelecionados as $servico) {
            if (isset($servicosDisponiveis[$servico])) {
                $totalServicos += $servicosDisponiveis[$servico];
            }
        }
        
        $impostosPercentual = 60.00 + 17.00 + 0.65 + 3.00;
        $totalImpostos = ($subtotal + $totalServicos) * ($impostosPercentual / 100);
        
        $total = $subtotal + $totalServicos + $totalImpostos;
        
        $_SESSION['calculo'] = [
            'subtotal' => $subtotal,
            'servicos' => $totalServicos,
            'impostos' => $totalImpostos,
            'total' => $total,
            'servicos_selecionados' => $servicosSelecionados,
            'cliente_data' => $clienteData
        ];
        
        $this->json([
            'success' => true,
            'subtotal' => number_format($subtotal, 2, ',', '.'),
            'servicos' => number_format($totalServicos, 2, ',', '.'),
            'impostos' => number_format($totalImpostos, 2, ',', '.'),
            'total' => number_format($total, 2, ',', '.'),
            'impostos_percentual' => $impostosPercentual
        ]);
    }
}
