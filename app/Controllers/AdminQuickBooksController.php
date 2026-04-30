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
            $ped = $pdo->prepare(
                'SELECT p.*, u.nome, u.name, u.email, u.telefone, u.phone, u.cpf
                 FROM pedidos p
                 LEFT JOIN usuarios u ON u.id = p.usuario_id
                 WHERE p.id = ? LIMIT 1'
            );
            $ped->execute([$pedidoId]);
            $pedido = $ped->fetch(\PDO::FETCH_ASSOC);
            if ($pedido) {
                // Normalizar nome/email independente do nome da coluna
                $pedido['nome']     = $pedido['nome']     ?? $pedido['name']  ?? '';
                $pedido['email']    = $pedido['email']    ?? '';
                $pedido['telefone'] = $pedido['telefone'] ?? $pedido['phone'] ?? '';
            }
            if(!$pedido){echo json_encode(['ok'=>false,'erro'=>'Pedido nao encontrado']); return;}
            $itStmt=$pdo->prepare("SELECT pi.*,pr.nome AS produto_nome FROM pedido_itens pi LEFT JOIN produtos pr ON pr.id=pi.produto_id WHERE pi.pedido_id=?");
            $itStmt->execute([$pedidoId]); $itens=$itStmt->fetchAll(\PDO::FETCH_ASSOC);
            $qb=$this->qb(); $res=$qb->criarInvoiceDePedido($pedido,$itens,$pedido);
            echo json_encode(['ok'=>true,'qb_invoice_id'=>$res['Invoice']['Id']??null]);
        }catch(\Throwable $ex){echo json_encode(['ok'=>false,'erro'=>$ex->getMessage()]);}
    }
}
