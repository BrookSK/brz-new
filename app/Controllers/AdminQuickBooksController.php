<?php
namespace App\Controllers;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\QuickBooksService;
use Config\Database;
class AdminQuickBooksController extends Controller {
    private function qb(): QuickBooksService { return new QuickBooksService(); }
    public function index(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $qb=$this->qb(); $configurado=$qb->isConfigurado(); $conectado=false; $companyInfo=null; $token=null;
        if($configurado){try{$conectado=$qb->isConectado();if($conectado){$token=$qb->getToken();try{$companyInfo=$qb->getCompanyInfo();}catch(\Throwable $e){}}}catch(\Throwable $e){}}
        $pdo=Database::getConnection();
        $stmt = $pdo->query(
            "SELECT chave, valor FROM configuracoes_sistema
             WHERE chave IN ('qb_client_id','qb_client_secret','qb_redirect_uri','qb_ambiente','qb_ativo','qb_verifier_token')"
        );
        $config = $stmt ? $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
        $logs=$conectado?$qb->getLogsRecentes(20):[];
        require __DIR__.'/../Views/admin/quickbooks/index.php';
    }
    public function oauthCallback(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $code=(string)($req->getParam('code')??''); $realmId=(string)($req->getParam('realmId')??''); $state=(string)($req->getParam('state')??'');
        if($code===''||$realmId===''){header('Location: /admin/quickbooks?erro=callback_invalido'); exit;}
        try{$qb=$this->qb(); $qb->trocarCodigoPorToken($code,$realmId); header('Location: /admin/quickbooks?sucesso=conectado'); exit;}
        catch(\Throwable $ex){header('Location: /admin/quickbooks?erro='.urlencode($ex->getMessage())); exit;}
    }
    public function conectar(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $qb=$this->qb();
        if(!$qb->isConfigurado()){header('Location: /admin/quickbooks?erro=nao_configurado'); exit;}
        $state=bin2hex(random_bytes(16)); $_SESSION['qb_oauth_state']=$state;
        header('Location: '.$qb->getAuthorizationUrl($state)); exit;
    }
    public function desconectar(Request $req) {
        (new AuthService())->requerPerfil('admin');
        (new QuickBooksService())->revogarToken();
        header('Location: /admin/quickbooks?sucesso=desconectado'); exit;
    }
    public function salvarConfig(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $pdo=Database::getConnection();
        $campos=['qb_client_id','qb_client_secret','qb_redirect_uri','qb_ambiente','qb_ativo','qb_verifier_token'];
        foreach ($campos as $chave) {
            $val = (string) ($req->getParam($chave) ?? '');
            // Não sobrescrever o secret se vier vazio (campo password em branco = não alterado)
            if ($chave === 'qb_client_secret' && $val === '') {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO configuracoes_sistema (chave, valor)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
            )->execute([$chave, $val]);
        }
        header('Location: /admin/quickbooks?sucesso=config_salva'); exit;
    }
    public function invoices(Request $req) {
        (new AuthService())->requerPerfil('admin');
        $qb=$this->qb();
        if(!$qb->isConectado()){header('Location: /admin/quickbooks?erro=nao_conectado'); exit;}
        $filtros=['data_inicio'=>(string)($req->getParam('data_inicio')??date('Y-m-01')),'data_fim'=>(string)($req->getParam('data_fim')??date('Y-m-d')),'status'=>(string)($req->getParam('status')??''),'limit'=>100];
        try{$result=$qb->listarInvoices($filtros); $invoices=$result['QueryResponse']['Invoice']??[];}catch(\Throwable $ex){$invoices=[];$erro=$ex->getMessage();}
        require __DIR__.'/../Views/admin/quickbooks/invoices.php';
    }
    public function sincronizarPedido(Request $req) {
        (new AuthService())->requerPerfil('admin');
        header('Content-Type: application/json');
        $pedidoId=(int)($req->getParam('pedido_id')??0);
        if($pedidoId<=0){echo json_encode(['ok'=>false,'erro'=>'ID invalido']); return;}
        try{
            $pdo = Database::getConnection();

            // Buscar pedido
            $ped = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? LIMIT 1');
            $ped->execute([$pedidoId]);
            $pedido = $ped->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                echo json_encode(['ok' => false, 'erro' => 'Pedido não encontrado']);
                return;
            }

            // Buscar usuário separadamente (evita assumir nomes de colunas)
            $usuarioId = (int) ($pedido['usuario_id'] ?? 0);
            if ($usuarioId > 0) {
                $uStmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                $uStmt->execute([$usuarioId]);
                $usuario = $uStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                // Normalizar campos que podem ter nomes diferentes
                $pedido['nome']     = $usuario['nome']     ?? $usuario['name']  ?? '';
                $pedido['email']    = $usuario['email']    ?? '';
                $pedido['telefone'] = $usuario['telefone'] ?? '';
                $pedido['cpf']      = $usuario['cpf']      ?? $usuario['documento'] ?? '';
            }
            $itStmt = $pdo->prepare('SELECT * FROM pedido_itens WHERE pedido_id = ?');
            $itStmt->execute([$pedidoId]);
            $itens = $itStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Enriquecer cada item com o nome do produto (coluna pode ser 'name' ou 'nome')
            foreach ($itens as &$item) {
                $prodId = (int) ($item['produto_id'] ?? 0);
                if ($prodId > 0) {
                    $pStmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
                    $pStmt->execute([$prodId]);
                    $prod = $pStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $item['produto_nome'] = $prod['name'] ?? $prod['nome'] ?? $prod['title'] ?? 'Produto #' . $prodId;
                } else {
                    $item['produto_nome'] = $item['produto_nome'] ?? 'Produto';
                }
            }
            unset($item);

            // Debug: retornar campos do pedido para diagnóstico
            $camposTotal = [];
            foreach (['total', 'valor_total', 'total_brl', 'valor_total_brl', 'amount', 'valor', 'subtotal', 'preco_total', 'grand_total'] as $c) {
                if (array_key_exists($c, $pedido)) {
                    $camposTotal[$c] = $pedido[$c];
                }
            }
            $camposItens = array_map(fn($it) => [
                'produto_nome'   => $it['produto_nome'] ?? '',
                'quantidade'     => $it['quantidade']   ?? $it['qty'] ?? '',
                'preco_unitario' => $it['preco_unitario'] ?? $it['preco'] ?? '',
                'subtotal'       => $it['subtotal']      ?? '',
            ], $itens);

            echo json_encode(['debug' => true, 'campos_total' => $camposTotal, 'itens' => $camposItens]);
            return;
            foreach (['total_brl', 'valor_total_brl', 'total', 'valor_total', 'amount', 'valor'] as $col) {
                if (!empty($pedido[$col]) && (float) $pedido[$col] > 0) {
                    $pedido['total_brl'] = (float) $pedido[$col];
                    break;
                }
            }

            $qb=$this->qb(); $res=$qb->criarInvoiceDePedido($pedido,$itens,$pedido);
            echo json_encode(['ok'=>true,'qb_invoice_id'=>$res['Invoice']['Id']??null]);
        }catch(\Throwable $ex){echo json_encode(['ok'=>false,'erro'=>$ex->getMessage()]);}
    }
}
