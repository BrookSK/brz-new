<?php
namespace App\Services;
use Config\Database;
/** QuickBooksService - Integracao QuickBooks Online via OAuth2 */
class QuickBooksService
{
    private const TOKEN_URL='https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    private const REVOKE_URL='https://developer.api.intuit.com/v2/oauth2/tokens/revoke';
    private const BASE_URL_SANDBOX='https://sandbox-quickbooks.api.intuit.com/v3/company';
    private const BASE_URL_PRODUCTION='https://quickbooks.api.intuit.com/v3/company';
    private const SCOPES='com.intuit.quickbooks.accounting';
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $ambiente;
    private \PDO $pdo;
    public function __construct() { $this->pdo=Database::getConnection(); $this->carregarCredenciais(); }
    private function carregarCredenciais(): void {
        $stmt = $this->pdo->query(
            "SELECT chave, valor FROM configuracoes_sistema
             WHERE chave IN ('qb_client_id','qb_client_secret','qb_redirect_uri','qb_ambiente','qb_ativo','qb_verifier_token')"
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
        $this->clientId     = (string) ($rows['qb_client_id']     ?? '');
        $this->clientSecret = (string) ($rows['qb_client_secret'] ?? '');
        $this->redirectUri  = (string) ($rows['qb_redirect_uri']  ?? '');
        $this->ambiente     = (string) ($rows['qb_ambiente']      ?? 'sandbox');
    }
    public function isConfigurado(): bool { return $this->clientId!=""&&$this->clientSecret!=""&&$this->redirectUri!=""; }
    public function isConectado(): bool { return $this->getToken()!==null; }
    private function getBaseUrl(): string { return $this->ambiente==='production'?self::BASE_URL_PRODUCTION:self::BASE_URL_SANDBOX; }
    public function getAuthorizationUrl(string $state=""): string {
        $params=http_build_query(["client_id"=>$this->clientId,"scope"=>self::SCOPES,"redirect_uri"=>$this->redirectUri,"response_type"=>"code","state"=>$state?:bin2hex(random_bytes(16))]);
        return "https://appcenter.intuit.com/connect/oauth2?".$params;
    }
    public function trocarCodigoPorToken(string $code,string $realmId): array {
        $r=$this->httpPost(self::TOKEN_URL,["grant_type"=>"authorization_code","code"=>$code,"redirect_uri"=>$this->redirectUri],$this->getBasicAuthHeader());
        if(isset($r["error"]))throw new \RuntimeException("Erro token: ".($r["error_description"]??$r["error"]));
        $this->salvarToken($realmId,$r); return $r;
    }
    public function renovarToken(): bool {
        $t=$this->getToken(); if(!$t)return false;
        try { $r=$this->httpPost(self::TOKEN_URL,["grant_type"=>"refresh_token","refresh_token"=>$t["refresh_token"]],$this->getBasicAuthHeader()); if(isset($r["error"]))return false; $this->salvarToken($t["realm_id"],$r); return true; } catch(\Throwable $e){return false;}
    }
    public function revogarToken(): bool {
        $t=$this->getToken(); if(!$t)return false;
        try { $this->httpPost(self::REVOKE_URL,["token"=>$t["refresh_token"]],$this->getBasicAuthHeader()); $this->pdo->prepare("DELETE FROM quickbooks_tokens WHERE realm_id=?") ->execute([$t["realm_id"]]); return true; } catch(\Throwable $e){return false;}
    }
    private function salvarToken(string $realmId,array $d): void {
        $ae=date("Y-m-d H:i:s",time()+(int)($d["expires_in"]??3600));
        $re=date("Y-m-d H:i:s",time()+(int)($d["x_refresh_token_expires_in"]??8726400));
        $this->pdo->prepare("INSERT INTO quickbooks_tokens(realm_id,access_token,refresh_token,access_token_expires_at,refresh_token_expires_at,ambiente) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE access_token=VALUES(access_token),refresh_token=VALUES(refresh_token),access_token_expires_at=VALUES(access_token_expires_at),refresh_token_expires_at=VALUES(refresh_token_expires_at),ambiente=VALUES(ambiente),atualizado_em=NOW()")->execute([$realmId,$d["access_token"],$d["refresh_token"],$ae,$re,$this->ambiente]);
    }
    public function getToken(): ?array { $s=$this->pdo->query("SELECT * FROM quickbooks_tokens ORDER BY atualizado_em DESC LIMIT 1"); $t=$s?$s->fetch(\PDO::FETCH_ASSOC):null; return $t?:null; }
    private function getAccessToken(): string {
        $t=$this->getToken(); if(!$t)throw new \RuntimeException("QuickBooks nao conectado.");
        if(strtotime($t["access_token_expires_at"])<(time()+300)){if(!$this->renovarToken())throw new \RuntimeException("Nao foi possivel renovar token."); $t=$this->getToken();}
        return (string)$t["access_token"];
    }
    private function getRealmId(): string { $t=$this->getToken(); if(!$t)throw new \RuntimeException("QB nao conectado."); return (string)$t["realm_id"]; }
    private function getBasicAuthHeader(): string { return "Basic ".base64_encode($this->clientId.":".$this->clientSecret); }
    public function getCompanyInfo(): array {
        return $this->apiGet('/companyinfo/' . $this->getRealmId());
    }

    public function listarInvoices(array $filtros = []): array {
        $where = [];
        if (!empty($filtros['data_inicio'])) $where[] = "TxnDate >= '" . $filtros['data_inicio'] . "'";
        if (!empty($filtros['data_fim']))    $where[] = "TxnDate <= '" . $filtros['data_fim'] . "'";
        if (!empty($filtros['status'])) {
            if ($filtros['status'] === 'pago')   $where[] = 'Balance = 0';
            elseif ($filtros['status'] === 'aberto') $where[] = 'Balance > 0';
        }
        $lim = (int)($filtros['limit']  ?? 100);
        $off = (int)($filtros['offset'] ?? 1);
        $q = 'SELECT * FROM Invoice' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
           . ' ORDERBY TxnDate DESC STARTPOSITION ' . $off . ' MAXRESULTS ' . $lim;
        return $this->apiQuery($q);
    }

    public function getInvoice(string $qbId): array {
        return $this->apiGet('/invoice/' . $qbId);
    }

    public function registrarPagamento(array $ped, float $valor, string $gateway, string $metodo): array {
        $mapa = $this->getMapeamentoPedido((int)$ped['id']);
        if (!$mapa || empty($mapa['qb_invoice_id'])) {
            throw new \RuntimeException('Invoice não encontrada para pedido #' . $ped['id'] . '. Sincronize primeiro.');
        }
        $labels = [
            'cambioreal'       => 'Câmbio Real (Produtos)',
            'cambioreal_taxas' => 'Câmbio Real (Taxas)',
            'appmax'           => 'Appmax',
            'stripe'           => 'Stripe',
            'asaas'            => 'Asaas',
        ];
        $gl = $labels[$gateway] ?? ucfirst($gateway);
        $pl = [
            'CustomerRef' => ['value' => $mapa['qb_customer_id']],
            'TotalAmt'    => round($valor, 2),
            'TxnDate'     => date('Y-m-d'),
            'PrivateNote' => 'Gateway: ' . $gl . ' | Método: ' . $metodo . ' | Pedido #' . $ped['id'],
            'Line'        => [['Amount' => round($valor, 2), 'LinkedTxn' => [['TxnId' => $mapa['qb_invoice_id'], 'TxnType' => 'Invoice']]]],
        ];
        $res = $this->apiPost('/payment', $pl);
        $this->salvarMapeamentoPedido((int)$ped['id'], [
            'qb_payment_id'   => $res['Payment']['Id'] ?? null,
            'sincronizado_em' => date('Y-m-d H:i:s'),
        ]);
        $this->registrarLog('payment', (int)$ped['id'], $res['Payment']['Id'] ?? null, 'create', 'success', $pl);
        return $res;
    }

    public function getMapeamentoPedido(int $pid): ?array {
        $s = $this->pdo->prepare('SELECT * FROM quickbooks_pedido_map WHERE pedido_id = ? LIMIT 1');
        $s->execute([$pid]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function getResumoFinanceiro(string $di, string $df): array {
        $res  = $this->apiQuery("SELECT * FROM Invoice WHERE TxnDate >= '" . $di . "' AND TxnDate <= '" . $df . "' MAXRESULTS 1000");
        $invs = $res['QueryResponse']['Invoice'] ?? [];
        $fat  = 0.0; $rec = 0.0; $ab = 0.0;
        foreach ($invs as $inv) {
            $t = (float)($inv['TotalAmt'] ?? 0);
            $b = (float)($inv['Balance']  ?? 0);
            $fat += $t; $ab += $b; $rec += ($t - $b);
        }
        return [
            'total_faturado'  => round($fat, 2),
            'total_recebido'  => round($rec, 2),
            'total_aberto'    => round($ab,  2),
            'qtd_invoices'    => count($invs),
            'data_inicio'     => $di,
            'data_fim'        => $df,
        ];
    }

    public function criarInvoiceDePedido(array $ped, array $itens, array $cli): array {
        $cid = $this->sincronizarCliente($cli);

        // Moeda do pedido
        $moedaPedido = strtoupper(trim((string) ($ped['moeda'] ?? 'BRL')));
        $pedidoEmBrl = ($moedaPedido === 'BRL' || $moedaPedido === '');

        // Taxa de câmbio BRL→USD (QuickBooks sandbox/produção opera em USD)
        // Buscar do sistema; fallback 5.85 conforme configuração da empresa
        $taxaCambio = 5.85;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT valor FROM configuracoes_sistema WHERE chave = 'sistema_usd_brl_rate' LIMIT 1"
            );
            $stmt->execute();
            $taxa = (float) ($stmt->fetchColumn() ?: 0);
            if ($taxa > 0) $taxaCambio = $taxa;
        } catch (\Throwable $e) {}

        // Converter valor BRL para USD se necessário
        $converter = fn(float $v): float => $pedidoEmBrl ? round($v / $taxaCambio, 2) : round($v, 2);

        // Montar line items
        $li = [];
        foreach ($itens as $it) {
            $subtotal  = (float) ($it['subtotal_brl'] ?? $it['subtotal'] ?? 0);
            $unitario  = (float) ($it['preco_unitario_brl'] ?? $it['preco_unitario'] ?? $it['preco'] ?? 0);
            $qty       = (float) ($it['quantidade'] ?? $it['qty'] ?? 1);
            $descricao = (string) ($it['produto_nome'] ?? $it['nome'] ?? 'Produto');

            if ($subtotal <= 0 && $unitario > 0) {
                $subtotal = round($unitario * $qty, 2);
            }

            $li[] = [
                'Amount'              => $converter($subtotal),
                'DetailType'          => 'SalesItemLineDetail',
                'Description'         => $descricao,
                'SalesItemLineDetail' => [
                    'Qty'       => $qty,
                    'UnitPrice' => $converter($unitario),
                ],
            ];
        }

        // Taxa de serviço
        $taxa = (float) ($ped['taxa_servico_brl'] ?? $ped['taxa_servico'] ?? 0);
        if ($taxa > 0) {
            $li[] = [
                'Amount'              => $converter($taxa),
                'DetailType'          => 'SalesItemLineDetail',
                'Description'         => 'Taxa de Servico',
                'SalesItemLineDetail' => ['Qty' => 1, 'UnitPrice' => $converter($taxa)],
            ];
        }

        // Imposto
        $imposto = (float) ($ped['imposto_brl'] ?? $ped['imposto'] ?? $ped['valor_impostos'] ?? 0);
        if ($imposto > 0) {
            $li[] = [
                'Amount'              => $converter($imposto),
                'DetailType'          => 'SalesItemLineDetail',
                'Description'         => 'Imposto de Importacao',
                'SalesItemLineDetail' => ['Qty' => 1, 'UnitPrice' => $converter($imposto)],
            ];
        }

        // Se itens estão em USD mas pedido é BRL, ou total diverge — usar total do pedido
        $totalLinhas = array_sum(array_column($li, 'Amount'));
        $totalPedido = (float) ($ped['total_brl'] ?? $ped['valor_total_brl'] ?? $ped['total'] ?? $ped['valor_total'] ?? $ped['amount'] ?? $ped['valor'] ?? 0);
        $totalUsd    = $converter($totalPedido);

        if ($totalLinhas <= 0 || ($totalPedido > 0 && abs($totalLinhas - $totalUsd) > 0.5)) {
            if ($totalPedido > 0) {
                $descLinha = $pedidoEmBrl
                    ? sprintf('Total do Pedido #%s (R$ %s ÷ %.2f)', $ped['id'], number_format($totalPedido, 2, ',', '.'), $taxaCambio)
                    : sprintf('Total do Pedido #%s (USD)', $ped['id']);
                $li = [[
                    'Amount'              => $totalUsd,
                    'DetailType'          => 'SalesItemLineDetail',
                    'Description'         => $descLinha,
                    'SalesItemLineDetail' => ['Qty' => 1, 'UnitPrice' => $totalUsd],
                ]];
            }
        }

        // Memo com detalhes completos
        $memo = 'Pedido Braziliana #' . $ped['id'];
        if ($pedidoEmBrl) {
            $memo .= sprintf(' | R$ %s (taxa %.2f)', number_format($totalPedido, 2, ',', '.'), $taxaCambio);
        }

        // Detalhamento por componente (campos diretos do pedido)
        $detalhes = [];
        $camposProduto  = ['valor_produtos', 'subtotal_produtos', 'subtotal'];
        $camposTaxa     = ['servicos', 'taxa_servico', 'valor_taxa_servico', 'servico', 'valor_servicos'];
        $camposImposto  = ['impostos', 'imposto', 'valor_impostos', 'imposto_importacao'];
        $camposImpostoL = ['imposto_local', 'sales_tax', 'imposto_eua'];
        $camposFrete    = ['frete', 'valor_frete', 'shipping'];

        foreach ($camposProduto as $c) {
            if (!empty($ped[$c]) && (float)$ped[$c] > 0) {
                $detalhes[] = 'Produtos: R$ ' . number_format((float)$ped[$c], 2, ',', '.');
                break;
            }
        }
        foreach ($camposTaxa as $c) {
            if (!empty($ped[$c]) && (float)$ped[$c] > 0) {
                $detalhes[] = 'Servicos: R$ ' . number_format((float)$ped[$c], 2, ',', '.');
                break;
            }
        }
        foreach ($camposImposto as $c) {
            if (!empty($ped[$c]) && (float)$ped[$c] > 0) {
                $detalhes[] = 'Imposto importacao: R$ ' . number_format((float)$ped[$c], 2, ',', '.');
                break;
            }
        }
        foreach ($camposImpostoL as $c) {
            if (!empty($ped[$c]) && (float)$ped[$c] > 0) {
                $detalhes[] = 'Imposto local: R$ ' . number_format((float)$ped[$c], 2, ',', '.');
                break;
            }
        }

        // Frete
        $frete = null;
        foreach ($camposFrete as $c) {
            if (array_key_exists($c, $ped)) {
                $frete = $ped[$c];
                break;
            }
        }
        if ($frete !== null) {
            $freteVal = (float) $frete;
            $detalhes[] = $freteVal > 0
                ? 'Frete: R$ ' . number_format($freteVal, 2, ',', '.')
                : 'Frete gratis';
        }

        // Forma de pagamento
        $fp = (string)($ped['forma_pagamento'] ?? $ped['payment_method'] ?? '');
        if ($fp !== '') {
            $detalhes[] = 'Pagamento: ' . $fp;
        }

        if (!empty($detalhes)) {
            $memo .= ' | ' . implode(' | ', $detalhes);
        }

        // Split de pagamentos da tabela pedido_pagamentos (quando disponível)
        $pagamentos = $ped['_pagamentos'] ?? [];
        if (!empty($pagamentos)) {
            $labels = [
                'cambioreal'       => 'Cambio Real (Produtos)',
                'cambioreal_taxas' => 'Cambio Real (Taxas)',
                'appmax'           => 'Appmax',
                'stripe'           => 'Stripe',
                'asaas'            => 'Asaas',
            ];
            $memo .= ' | Split:';
            foreach ($pagamentos as $pg) {
                $gw   = $labels[$pg['gateway']] ?? ucfirst($pg['gateway'] ?? '');
                $met  = strtoupper($pg['metodo'] ?? '');
                $val  = number_format((float)($pg['valor'] ?? 0), 2, ',', '.');
                $comp = ucfirst($pg['componente'] ?? '');
                $memo .= " [{$comp} R$ {$val} via {$gw}/{$met}]";
            }
        }

        $pl = [
            'Line'        => $li,
            'CustomerRef' => ['value' => $cid],
            'TxnDate'     => date('Y-m-d', strtotime($ped['created_at'] ?? 'now')),
            'DocNumber'   => 'BRZ-' . $ped['id'],
            'PrivateNote' => $memo,
            'CurrencyRef' => ['value' => 'USD'],
        ];

        $res = $this->apiPost('/invoice', $pl);
        $this->salvarMapeamentoPedido((int) $ped['id'], [
            'qb_invoice_id'         => $res['Invoice']['Id'] ?? null,
            'qb_invoice_doc_number' => $res['Invoice']['DocNumber'] ?? null,
            'qb_customer_id'        => $cid,
        ]);
        $this->registrarLog('invoice', (int) $ped['id'], $res['Invoice']['Id'] ?? null, 'create', 'success', $pl);
        return $res;
    }
    public function sincronizarCliente(array $cli): string {
        $em = (string) ($cli['email'] ?? '');

        // Montar DisplayName com nome real — nunca usar só 'BR'
        $nome = trim((string) ($cli['nome'] ?? $cli['name'] ?? ''));
        if ($nome === '') {
            $nome = $em !== '' ? $em : 'Cliente';
        }

        // Buscar por email primeiro
        if ($em !== '') {
            $res  = $this->apiQuery("SELECT * FROM Customer WHERE PrimaryEmailAddr = '" . addslashes($em) . "' MAXRESULTS 1");
            $rows = $res['QueryResponse']['Customer'] ?? [];
            if (!empty($rows[0]['Id'])) return (string) $rows[0]['Id'];
        }

        // Buscar por DisplayName (evita "Duplicate Name Exists Error")
        try {
            $res = $this->apiQuery("SELECT * FROM Customer WHERE DisplayName = '" . addslashes($nome) . "' MAXRESULTS 1");
            $rows = $res['QueryResponse']['Customer'] ?? [];
            if (!empty($rows[0]['Id'])) return (string) $rows[0]['Id'];
        } catch (\Throwable $e) {}

        $pl = [
            'DisplayName'      => $nome,
            'PrimaryEmailAddr' => ['Address' => $em],
            'PrimaryPhone'     => ['FreeFormNumber' => (string) ($cli['telefone'] ?? '')],
            'BillAddr'         => [
                'Line1'                  => (string) ($cli['endereco'] ?? ''),
                'City'                   => (string) ($cli['cidade']   ?? ''),
                'CountrySubDivisionCode' => (string) ($cli['estado']   ?? ''),
                'PostalCode'             => (string) ($cli['cep']      ?? ''),
                'Country'                => 'BR',
            ],
        ];

        try {
            $res = $this->apiPost('/customer', $pl);
            $id  = (string) ($res['Customer']['Id'] ?? '');
            $this->registrarLog('customer', null, $id, 'create', 'success', $pl);
            return $id;
        } catch (\Throwable $e) {
            // Se deu "Duplicate Name", tentar buscar novamente com variação
            if (strpos($e->getMessage(), 'Duplicate Name') !== false || strpos($e->getMessage(), '6240') !== false) {
                // Tentar com sufixo do pedido_id ou email
                $suffix = $em !== '' ? ' (' . substr($em, 0, 20) . ')' : ' (' . date('YmdHis') . ')';
                $pl['DisplayName'] = $nome . $suffix;
                $res = $this->apiPost('/customer', $pl);
                $id  = (string) ($res['Customer']['Id'] ?? '');
                $this->registrarLog('customer', null, $id, 'create', 'success', $pl);
                return $id;
            }
            throw $e;
        }
    }
    public function listarPayments(array $f=[]): array { $lim=(int)($f["limit"]??100); $off=(int)($f["offset"]??1); return $this->apiQuery("SELECT * FROM Payment ORDERBY TxnDate DESC STARTPOSITION ".$off." MAXRESULTS ".$lim); }
    public function listarCustomers(array $f=[]): array { $lim=(int)($f["limit"]??100); $off=(int)($f["offset"]??1); return $this->apiQuery("SELECT * FROM Customer ORDERBY DisplayName ASC STARTPOSITION ".$off." MAXRESULTS ".$lim); }
    private function salvarMapeamentoPedido(int $pid,array $d): void {
        $ex=$this->getMapeamentoPedido($pid);
        if($ex){$sets=[];$vals=[];foreach($d as $col=>$val){$sets[]=$col."=?";$vals[]=$val;}$vals[]=$pid;$this->pdo->prepare("UPDATE quickbooks_pedido_map SET ".implode(",",$sets)." WHERE pedido_id=?") ->execute($vals);}
        else{$d["pedido_id"]=$pid;$cols=implode(",",array_keys($d));$pls=implode(",",array_fill(0,count($d),"?"));$this->pdo->prepare("INSERT INTO quickbooks_pedido_map(".$cols.") VALUES(".$pls.")") ->execute(array_values($d));}
    }
    private function registrarLog(string $ent,?int $eid,?string $qid,string $ac,string $st,array $pl=[],string $err=""): void {
        try{$uid=isset($_SESSION["usuario_id"])?(int)$_SESSION["usuario_id"]:null;$this->pdo->prepare("INSERT INTO quickbooks_sync_log(entidade,entidade_id,qb_id,acao,status,payload,erro,usuario_id) VALUES(?,?,?,?,?,?,?,?)")->execute([$ent,$eid,$qid,$ac,$st,json_encode($pl,JSON_UNESCAPED_UNICODE),$err?:null,$uid]);}catch(\Throwable $e){}
    }
    public function getLogsRecentes(int $lim = 50): array {
        $lim = max(1, (int) $lim);
        $s = $this->pdo->prepare(
            "SELECT l.*, u.nome AS usuario_nome
             FROM quickbooks_sync_log l
             LEFT JOIN usuarios u ON u.id = l.usuario_id
             ORDER BY l.criado_em DESC
             LIMIT " . $lim
        );
        $s->execute();
        return $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    private function apiGet(string $ep): array { $url=$this->getBaseUrl()."/".$this->getRealmId().$ep."?minorversion=65"; return $this->httpGet($url,["Authorization: Bearer ".$this->getAccessToken(),"Accept: application/json"]); }
    private function apiPost(string $ep,array $pl): array { $url=$this->getBaseUrl()."/".$this->getRealmId().$ep."?minorversion=65"; return $this->httpPost($url,$pl,null,["Authorization: Bearer ".$this->getAccessToken(),"Content-Type: application/json","Accept: application/json"],true); }
    private function apiQuery(string $q): array { $url=$this->getBaseUrl()."/".$this->getRealmId()."/query?query=".urlencode($q)."&minorversion=65"; return $this->httpGet($url,["Authorization: Bearer ".$this->getAccessToken(),"Accept: application/json"]); }
    private function httpGet(string $url,array $h=[]): array { $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]); $b=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); $er=curl_error($ch); curl_close($ch); if($er)throw new \RuntimeException("cURL: ".$er); if($c>=400)throw new \RuntimeException("QB API ".$c.": ".$b); return json_decode((string)$b,true)?:[]; }
    private function httpPost(string $url,array $d,?string $ah=null,array $eh=[],bool $jb=false): array { $h=$eh; if($ah)$h[]="Authorization: ".$ah; $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$jb?json_encode($d):http_build_query($d),CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]); $b=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); $er=curl_error($ch); curl_close($ch); if($er)throw new \RuntimeException("cURL: ".$er); if($c>=400)throw new \RuntimeException("QB API ".$c.": ".$b); return json_decode((string)$b,true)?:[]; }
}
