<?php
namespace App\Controllers;

use Config\Database;
use App\Models\PedidoEcommerce;
use App\Services\AuthService;
use App\Services\CorreiosPrepostagemService;
use App\Services\CorreiosTokenService;

class AdminRemessaCorreiosController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getConfigEntregaValue(string $key, $default = '') {
        // supports both:
        // - key/value schema (configuracoes_sistema with columns chave/valor)
        // - single-row schema (configuracoes_sistema with columns sigep_*)
        try {
            if (!$this->tableExists('configuracoes_sistema')) {
                return $default;
            }

            $cols = [];
            try {
                $st = $this->connection->query('DESCRIBE configuracoes_sistema');
                $cols = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $k = (string) $key;

            if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $fullKey = 'entrega_' . $k;
                $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $stmt->execute([$fullKey]);
                $v = $stmt->fetchColumn();
                if ($v === false || $v === null) {
                    return $default;
                }
                return $v;
            }

            // single-row/columns
            $colName = (strpos($k, 'sigep_') === 0) ? $k : ('sigep_' . $k);
            if (in_array($colName, $cols, true)) {
                $stmt = $this->connection->query('SELECT ' . $colName . ' FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                $v = $stmt->fetchColumn();
                if ($v === false || $v === null) {
                    return $default;
                }
                return $v;
            }

            return $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    private function getSigepConfig(): array {
        $enabled = (string) $this->getConfigEntregaValue('sigep_enabled', '0');

        return [
            'enabled' => ($enabled === '1' || $enabled === 'true' || $enabled === 'on'),
            'ambiente' => (string) $this->getConfigEntregaValue('sigep_ambiente', 'homologacao'),
            'usuario' => (string) $this->getConfigEntregaValue('sigep_usuario', ''),
            'senha' => (string) $this->getConfigEntregaValue('sigep_senha', ''),
            'contrato' => (string) $this->getConfigEntregaValue('sigep_numero_contrato', ''),
            'cartao' => (string) $this->getConfigEntregaValue('sigep_cartao_postagem', ''),
            'cnpj' => (string) $this->getConfigEntregaValue('sigep_cnpj', ''),
            'servico' => (string) $this->getConfigEntregaValue('sigep_servico', 'PAC'),
            'servico_codigo' => (string) $this->getConfigEntregaValue('sigep_servico_codigo', ''),
        ];
    }

    private function getCorreiosProviderConfig(): array {
        $provider = (string) $this->getConfigEntregaValue('correios_provider', 'sigep');
        $provider = strtolower(trim($provider));
        if ($provider !== 'prepostagem_v3') {
            $provider = 'sigep';
        }

        return [
            'provider' => $provider,
            'ambiente' => (string) $this->getConfigEntregaValue('sigep_ambiente', 'homologacao'),
            'prepostagem_token' => (string) $this->getConfigEntregaValue('correios_prepostagem_token', ''),
            'prepostagem_id_correios' => (string) $this->getConfigEntregaValue('correios_prepostagem_id_correios', ''),
            'prepostagem_codigo_servico' => (string) $this->getConfigEntregaValue('correios_prepostagem_codigo_servico', ''),
            'prepostagem_sender_json' => (string) $this->getConfigEntregaValue('correios_prepostagem_sender_json', ''),
        ];
    }

    private function getPrepostagemBaseUrl(string $ambiente): string {
        $amb = strtolower(trim($ambiente));
        if ($amb === 'producao' || $amb === 'production') {
            return 'https://api.correios.com.br/prepostagem';
        }
        return 'https://apihom.correios.com.br/prepostagem';
    }

    private function parseJsonConfig(string $json, string $label): array {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \Exception($label . ': JSON inválido');
        }
        return $decoded;
    }

    private function onlyDigits(string $v): string {
        $v = (string) $v;
        $v = preg_replace('/\D+/', '', $v);
        return (string) $v;
    }

    private function isValidCpf(string $cpf): bool {
        $cpf = $this->onlyDigits($cpf);
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        $sum = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum += ((int) $cpf[$i]) * $w;
        }
        $d1 = 11 - ($sum % 11);
        if ($d1 >= 10) $d1 = 0;
        if ($d1 !== (int) $cpf[9]) return false;

        $sum = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum += ((int) $cpf[$i]) * $w;
        }
        $d2 = 11 - ($sum % 11);
        if ($d2 >= 10) $d2 = 0;
        return $d2 === (int) $cpf[10];
    }

    private function isValidCnpj(string $cnpj): bool {
        $cnpj = $this->onlyDigits($cnpj);
        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

        $calc = function(string $c, array $w): int {
            $sum = 0;
            for ($i = 0; $i < count($w); $i++) {
                $sum += ((int) $c[$i]) * $w[$i];
            }
            $r = $sum % 11;
            return ($r < 2) ? 0 : (11 - $r);
        };

        $w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $d1 = $calc($cnpj, $w1);
        if ($d1 !== (int) $cnpj[12]) return false;

        $w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        $d2 = $calc($cnpj, $w2);
        return $d2 === (int) $cnpj[13];
    }

    private function isValidCpfCnpj(string $doc): bool {
        $d = $this->onlyDigits($doc);
        if ($d === '') return false;
        if (strlen($d) === 11) return $this->isValidCpf($d);
        if (strlen($d) === 14) return $this->isValidCnpj($d);
        return false;
    }

    private function pickFirstNonEmpty(array $row, array $keys): string {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row)) {
                $v = trim((string) ($row[$k] ?? ''));
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    private function buildPrepostagemPayload(array $pedido, array $cfg): array {
        $sender = $this->parseJsonConfig((string) ($cfg['prepostagem_sender_json'] ?? ''), 'Pré-Postagem: remetente');
        if (empty($sender)) {
            throw new \Exception('Pré-Postagem: configure o remetente (JSON) no Admin');
        }

        if (isset($sender['pais'])) {
            unset($sender['pais']);
        }
        if (isset($sender['endereco']) && is_array($sender['endereco'])) {
            if (isset($sender['endereco']['pais'])) {
                unset($sender['endereco']['pais']);
            }
        }

        if (isset($sender['canalExternoOrigem'])) {
            unset($sender['canalExternoOrigem']);
        }

        $destNome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? ''));
        $destEmail = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ''));
        $destTel = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ''));

        $destDoc = $this->pickFirstNonEmpty($pedido, ['cliente_cpf_cnpj', 'cpf_cnpj', 'cpfCnpj', 'cpf', 'cnpj', 'documento', 'document']);
        if ($destDoc === '' && isset($pedido['cliente']) && is_array($pedido['cliente'])) {
            $destDoc = $this->pickFirstNonEmpty((array) $pedido['cliente'], ['cpf_cnpj', 'cpfCnpj', 'cpf', 'cnpj', 'documento', 'document']);
        }
        $destDocDigits = $this->onlyDigits($destDoc);
        if ($destDocDigits === '' || !$this->isValidCpfCnpj($destDocDigits)) {
            throw new \Exception('Pré-Postagem: CPF/CNPJ do destinatário inválido ou ausente');
        }

        $cep = $this->onlyDigits((string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? '')));
        $logradouro = (string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''));
        $numero = (string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''));
        $complemento = (string) ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''));
        $bairro = (string) ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ''));
        $cidade = (string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ''));
        $uf = (string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? ''));

        if ($destNome === '' || $cep === '' || $logradouro === '' || $numero === '' || $bairro === '' || $cidade === '' || $uf === '') {
            throw new \Exception('Pré-Postagem: pedido sem dados completos de endereço do destinatário');
        }

        $ddd = '';
        $telefone8 = '';
        $digits = $this->onlyDigits($destTel);
        if (strlen($digits) >= 10) {
            $ddd = substr($digits, 0, 2);
            $telefone8 = substr($digits, 2, 8);
        }

        $destinatario = [
            'nome' => $destNome,
            'email' => $destEmail,
            'cpfCnpj' => $destDocDigits,
            'dddTelefone' => $ddd,
            'telefone' => $telefone8,
            'endereco' => [
                'cep' => $cep,
                'logradouro' => $logradouro,
                'numero' => $numero,
                'complemento' => $complemento,
                'bairro' => $bairro,
                'cidade' => $cidade,
                'uf' => $uf,
                'regiao' => '',
            ],
        ];

        $codigoServico = (string) ($cfg['prepostagem_codigo_servico'] ?? '');
        if ($codigoServico === '') {
            throw new \Exception('Pré-Postagem: informe o código do serviço nas configurações');
        }

        $idCorreios = trim((string) ($cfg['prepostagem_id_correios'] ?? ''));
        if ($idCorreios === '') {
            throw new \Exception('Pré-Postagem: informe o idCorreios nas configurações');
        }

        $items = [];
        if (isset($pedido['items']) && is_array($pedido['items'])) {
            $items = $pedido['items'];
        } elseif (isset($pedido['itens']) && is_array($pedido['itens'])) {
            $items = $pedido['itens'];
        }
        if (empty($items)) {
            throw new \Exception('Pré-Postagem: pedido sem itens');
        }

        $itensDeclaracao = [];
        $idx = 0;
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $idx++;
            $qtd = (int) ($it['quantidade'] ?? ($it['qty'] ?? 0));
            if ($qtd <= 0) {
                throw new \Exception('Pré-Postagem: item #' . $idx . ' com quantidade inválida');
            }

            $nomeItem = trim((string) ($it['nome'] ?? ($it['nome_produto'] ?? ($it['produto_nome'] ?? ''))));
            if ($nomeItem === '') {
                $nomeItem = 'Item ' . $idx;
            }

            $ncm = $this->onlyDigits((string) ($it['ncm'] ?? ($it['codigo_ncm'] ?? '')));
            if ($ncm === '' || strlen($ncm) < 8) {
                throw new \Exception('Pré-Postagem: item #' . $idx . ' sem NCM');
            }

            $valor = null;
            foreach (['valor', 'preco_unitario', 'price', 'preco', 'valor_unitario'] as $k) {
                if (isset($it[$k]) && is_numeric($it[$k])) {
                    $valor = (float) $it[$k];
                    break;
                }
            }
            if ($valor === null || $valor <= 0) {
                throw new \Exception('Pré-Postagem: item #' . $idx . ' com valor inválido');
            }

            $itensDeclaracao[] = [
                'conteudo' => substr($nomeItem, 0, 60),
                'quantidade' => (string) $qtd,
                'valor' => number_format($valor, 2, '.', ''),
                'ncm' => $ncm,
            ];
        }

        if (empty($itensDeclaracao)) {
            throw new \Exception('Pré-Postagem: pedido sem itens válidos para declaração de conteúdo');
        }

        $pesoKg = null;
        foreach (['peso_total', 'peso'] as $k) {
            if (isset($pedido[$k]) && is_numeric($pedido[$k])) {
                $pesoKg = (float) $pedido[$k];
                break;
            }
        }
        if ($pesoKg === null || $pesoKg <= 0) {
            throw new \Exception('Pré-Postagem: pedido com peso inválido (peso_total/peso)');
        }
        $pesoGramas = (int) round($pesoKg * 1000);
        if ($pesoGramas <= 0) {
            throw new \Exception('Pré-Postagem: pedido com peso inválido');
        }

        $altura = null;
        $largura = null;
        $comprimento = null;
        foreach (['altura', 'altura_cm', 'altura_pacote', 'package_height'] as $k) {
            if (isset($pedido[$k]) && is_numeric($pedido[$k])) {
                $altura = (float) $pedido[$k];
                break;
            }
        }
        foreach (['largura', 'largura_cm', 'largura_pacote', 'package_width'] as $k) {
            if (isset($pedido[$k]) && is_numeric($pedido[$k])) {
                $largura = (float) $pedido[$k];
                break;
            }
        }
        foreach (['comprimento', 'comprimento_cm', 'comprimento_pacote', 'package_length'] as $k) {
            if (isset($pedido[$k]) && is_numeric($pedido[$k])) {
                $comprimento = (float) $pedido[$k];
                break;
            }
        }
        if ($altura === null || $largura === null || $comprimento === null || $altura <= 0 || $largura <= 0 || $comprimento <= 0) {
            throw new \Exception('Pré-Postagem: pedido com dimensões inválidas (altura/largura/comprimento)');
        }

        $altura = (int) round($altura);
        $largura = (int) round($largura);
        $comprimento = (int) round($comprimento);
        if ($altura <= 0 || $largura <= 0 || $comprimento <= 0) {
            throw new \Exception('Pré-Postagem: pedido com dimensões inválidas');
        }

        $formato = '2';

        $payload = [
            'idCorreios' => $idCorreios,
            'remetente' => $sender,
            'destinatario' => $destinatario,
            'codigoServico' => $codigoServico,
            'itensDeclaracaoConteudo' => $itensDeclaracao,
            'pesoInformado' => (string) $pesoGramas,
            'codigoFormatoObjetoInformado' => (string) $formato,
            'alturaInformada' => (string) $altura,
            'larguraInformada' => (string) $largura,
            'comprimentoInformado' => (string) $comprimento,
            'cienteObjetoNaoProibido' => '1',
            'solicitarColeta' => 'N',
            'observacao' => 'Pedido #' . (int) ($pedido['id'] ?? 0),
        ];

        if (isset($payload['canalExternoOrigem'])) {
            unset($payload['canalExternoOrigem']);
        }

        $pedidoExternoOrigem = (string) ($pedido['codigo_pedido'] ?? ('PED-' . str_pad((string) ($pedido['id'] ?? 0), 6, '0', STR_PAD_LEFT)));
        $pedidoExternoOrigem = trim($pedidoExternoOrigem);
        if ($pedidoExternoOrigem !== '') {
            $payload['pedidoExternoOrigem'] = $pedidoExternoOrigem;
        }

        return $payload;
    }

    private function solicitarEtiquetaSigep(array $cfg): string {
        if (empty($cfg['usuario']) || empty($cfg['senha']) || empty($cfg['cartao']) || empty($cfg['contrato']) || empty($cfg['servico_codigo'])) {
            throw new \Exception('SIGEP: preencha usuário/senha/contrato/cartão/código do serviço no Admin');
        }

        if (!class_exists('\\SoapClient')) {
            throw new \Exception('SIGEP: extensão SOAP não disponível no PHP do servidor');
        }

        $amb = strtolower(trim((string) ($cfg['ambiente'] ?? 'homologacao')));
        $wsdl = ($amb === 'producao' || $amb === 'production')
            ? 'https://apps.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl'
            : 'https://apphom.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl';

        $localWsdl = __DIR__ . '/../Resources/wsdl/AtendeCliente.wsdl';
        if (is_file($localWsdl)) {
            $wsdl = $localWsdl;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'protocol_version' => 1.1,
                'ignore_errors' => true,
                'header' => "Connection: close\r\n"
                    . "Accept: text/xml, application/xml;q=0.9, */*;q=0.8\r\n"
                    . "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) brz-sigep/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $client = new \SoapClient($wsdl, [
                'exceptions' => true,
                'trace' => false,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => 20,
                'stream_context' => $context,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            ]);
        } catch (\Throwable $e) {
            $extra = [];
            $extra[] = 'allow_url_fopen=' . (ini_get('allow_url_fopen') ? '1' : '0');
            $extra[] = 'openssl.cafile=' . (string) ini_get('openssl.cafile');
            $extra[] = 'curl.cainfo=' . (string) ini_get('curl.cainfo');
            throw new \Exception('SIGEP falhou ao carregar WSDL: ' . $e->getMessage() . ' | ' . implode(', ', $extra));
        }

        // Observação: o SIGEP varia por contrato. Aqui tentamos usar solicitaEtiquetas.
        // Para funcionar em definitivo, o admin deve preencher o código do serviço do contrato.
        $params = [
            'tipoDestinatario' => 'C',
            'identificador' => (string) ($cfg['cnpj'] ?? ''),
            'idServico' => (string) ($cfg['servico_codigo'] ?? ''),
            'qtdEtiquetas' => 1,
            'usuario' => (string) ($cfg['usuario'] ?? ''),
            'senha' => (string) ($cfg['senha'] ?? ''),
        ];

        $resp = $client->__soapCall('solicitaEtiquetas', [$params]);

        // Normalização best-effort
        $raw = null;
        if (is_object($resp)) {
            if (isset($resp->return)) {
                $raw = $resp->return;
            } elseif (isset($resp->return->return)) {
                $raw = $resp->return->return;
            }
        }

        if (is_array($raw) && !empty($raw[0])) {
            return (string) $raw[0];
        }
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        throw new \Exception('SIGEP: resposta inesperada ao solicitar etiqueta');
    }

    private function normalizarEtiquetaCorreios(string $code): string {
        $c = strtoupper(trim($code));
        $c = preg_replace('/\s+/', '', $c);
        return (string) $c;
    }

    private function calcularDvEtiqueta(string $semDv): string {
        $c = $this->normalizarEtiquetaCorreios($semDv);
        if (!preg_match('/^[A-Z]{2}[0-9]{8}[A-Z]{2}$/', $c)) {
            throw new \Exception('SIGEP: etiqueta sem DV em formato inválido');
        }

        $num = substr($c, 2, 8);
        $pesos = [8, 6, 4, 2, 3, 5, 9, 7];
        $soma = 0;
        for ($i = 0; $i < 8; $i++) {
            $dig = (int) $num[$i];
            $soma += $dig * $pesos[$i];
        }
        $resto = $soma % 11;
        $dv = 11 - $resto;
        if ($dv === 10) {
            $dv = 0;
        } elseif ($dv === 11) {
            $dv = 5;
        }
        return (string) $dv;
    }

    private function completarEtiquetaComDv(string $code): array {
        $c = $this->normalizarEtiquetaCorreios($code);

        if (preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $c)) {
            return [
                'etiqueta' => $c,
                'sem_dv' => substr($c, 0, 2) . substr($c, 2, 8) . substr($c, 11, 2),
                'dv' => substr($c, 10, 1),
            ];
        }

        if (preg_match('/^[A-Z]{2}[0-9]{8}[A-Z]{2}$/', $c)) {
            $dv = $this->calcularDvEtiqueta($c);
            $full = substr($c, 0, 10) . $dv . substr($c, 10, 2);
            return ['etiqueta' => $full, 'sem_dv' => $c, 'dv' => $dv];
        }

        // Se vier em um formato diferente, não inventar
        return ['etiqueta' => $c, 'sem_dv' => null, 'dv' => null];
    }

    private function getColsCorreiosEtiquetas(): array {
        try {
            $st = $this->connection->query('DESCRIBE correios_etiquetas');
            return $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        try {
            // Buscar remessas prontas para etiqueta (status = remessa_gerada)
            $remessasProntas = $this->getRemessasProntas();
            
            // Buscar etiquetas geradas
            $etiquetasGeradas = $this->getEtiquetasGeradas();
            
            // Buscar etiquetas impressas
            $etiquetasImpressas = $this->getEtiquetasImpressas();

        } catch (\Exception $e) {
            $remessasProntas = [];
            $etiquetasGeradas = [];
            $etiquetasImpressas = [];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa Correios - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .bg-purple { background-color: #6f42c1 !important; }
        .badge.bg-purple { background-color: #6f42c1 !important; color: #fff !important; }
        .status-pronta { background-color: #17a2b8; }
        .status-etiqueta { background-color: #6f42c1; }
        .status-impressa { background-color: #28a745; }
        .status-postada { background-color: #007bff; }
        .remessa-card { 
            transition: transform 0.2s; 
            border-left: 4px solid #17a2b8;
        }
        .remessa-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .etiqueta-card { 
            transition: all 0.3s; 
            border-left: 4px solid #6f42c1;
        }
        .etiqueta-card:hover { 
            transform: translateX(5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .codigo-etiqueta {
            font-family: "Courier New", monospace;
            font-size: 14px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('remessa-correios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shipping-fast me-2"></i>Remessa Correios</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="gerarLoteEtiquetas()">
                            <i class="fas fa-tags me-1"></i>Gerar Lote
                        </button>
                        <button type="button" class="btn btn-warning me-2" onclick="imprimirTodasEtiquetas()">
                            <i class="fas fa-print me-1"></i>Imprimir Todas
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Prontas</h5>
                                <h3>' . count($remessasProntas) . '</h3>
                                <small>Aguardando etiqueta</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-purple text-white">
                            <div class="card-body">
                                <h5 class="card-title">Etiquetas</h5>
                                <h3>' . count($etiquetasGeradas) . '</h3>
                                <small>Geradas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Impressas</h5>
                                <h3>' . count($etiquetasImpressas) . '</h3>
                                <small>Prontas para postagem</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Postadas</h5>
                                <h3>' . $this->getTotalPostadas() . '</h3>
                                <small>Enviadas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remessas Prontas para Etiqueta -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Remessas Prontas para Etiqueta</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data Remessa</th>
                                                <th>Peso</th>
                                                <th>Valor</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($remessasProntas as $remessa) {
                                            echo '<tr class="remessa-card">
                                                <td><input type="checkbox" class="remessa-checkbox" value="' . (int) ($remessa['pedido_id'] ?? 0) . '"></td>
                                                <td><strong>' . (!empty($remessa['janela_id']) ? ('#' . str_pad((int) ($remessa['janela_id'] ?? 0), 6, '0', STR_PAD_LEFT)) : '-') . '</strong></td>
                                                <td>#' . str_pad($remessa['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($remessa['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($remessa['created_at'])) . '</td>
                                                <td>' . number_format($remessa['peso_total'] ?? 1.0, 3, ',', '.') . ' kg</td>
                                                <td>R$ ' . number_format($remessa['valor_total'] ?? 0, 2, ',', '.') . '</td>
                                                <td>
                                                    <button class="btn btn-sm btn-purple" onclick="gerarEtiqueta(' . (int) ($remessa['pedido_id'] ?? 0) . ')">
                                                        <i class="fas fa-tags"></i> Gerar Etiqueta
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesRemessa(' . (int) ($remessa['pedido_id'] ?? 0) . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($remessasProntas)) {
                                            echo '<tr><td colspan="8" class="text-center text-muted">Nenhuma remessa pronta para etiqueta encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Etiquetas Geradas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-purple text-white">
                                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Etiquetas Geradas</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Etiqueta</th>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Código</th>
                                                <th>Data Geração</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($etiquetasGeradas as $etiqueta) {
                                            echo '<tr class="etiqueta-card">
                                                <td><strong>#' . str_pad($etiqueta['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($etiqueta['remessa_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>#' . str_pad($etiqueta['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($etiqueta['cliente_nome'] ?? 'N/A') . '</td>
                                                <td><div class="codigo-etiqueta">' . htmlspecialchars($etiqueta['codigo_etiqueta']) . '</div></td>
                                                <td>' . date('d/m/Y H:i', strtotime($etiqueta['created_at'])) . '</td>
                                                <td><span class="badge bg-purple">Etiqueta Gerada</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="imprimirEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-print"></i> Imprimir
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="regerarEtiqueta(' . (int)($etiqueta['pedido_id'] ?? 0) . ', ' . $etiqueta['id'] . ')" title="Deletar etiqueta atual e gerar nova com as medidas atuais do pedido">
                                                        <i class="fas fa-redo"></i> Regerar
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="rastrearEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($etiquetasGeradas)) {
                                            echo '<tr><td colspan="8" class="text-center text-muted">Nenhuma etiqueta gerada encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Etiquetas Impressas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Etiquetas Impressas</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Etiqueta</th>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Código</th>
                                                <th>Data Impressão</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($etiquetasImpressas as $etiqueta) {
                                            echo '<tr class="table-success">
                                                <td><strong>#' . str_pad($etiqueta['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($etiqueta['remessa_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>#' . str_pad($etiqueta['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($etiqueta['cliente_nome'] ?? 'N/A') . '</td>
                                                <td><div class="codigo-etiqueta">' . htmlspecialchars($etiqueta['codigo_etiqueta']) . '</div></td>
                                                <td>' . date('d/m/Y H:i', strtotime($etiqueta['data_impressao'])) . '</td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="imprimirEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-print"></i> Reimprimir
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="confirmarPostagem(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-check"></i> Confirmar Postagem
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="regerarEtiqueta(' . (int)($etiqueta['pedido_id'] ?? 0) . ', ' . $etiqueta['id'] . ')" title="Deletar etiqueta atual e gerar nova com as medidas atuais do pedido">
                                                        <i class="fas fa-redo"></i> Regerar
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="rastrearEtiqueta(' . $etiqueta['id'] . ')">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($etiquetasImpressas)) {
                                            echo '<tr><td colspan="7" class="text-center text-muted">Nenhuma etiqueta impressa encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll() {
            const checkboxes = document.querySelectorAll(\'.remessa-checkbox\');
            const selectAll = document.getElementById(\'selectAll\');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function gerarEtiqueta(remessaId) {
            if (confirm("Deseja gerar a etiqueta dos Correios para esta remessa?")) {
                fetch("/admin/remessa-correios/gerar-etiqueta/" + remessaId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Etiqueta gerada com sucesso! Código: " + data.codigo_etiqueta);
                        location.reload();
                    } else {
                        alert("Erro ao gerar etiqueta: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao gerar etiqueta");
                });
            }
        }

        function gerarLoteEtiquetas() {
            const checkboxes = document.querySelectorAll(\'.remessa-checkbox:checked\');
            
            if (checkboxes.length === 0) {
                alert("Selecione pelo menos uma remessa para gerar etiquetas em lote");
                return;
            }
            
            if (confirm("Deseja gerar etiquetas para " + checkboxes.length + " remessas selecionadas?")) {
                const remessas = Array.from(checkboxes).map(cb => cb.value);
                
                fetch("/admin/remessa-correios/gerar-lote-etiquetas", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ remessas: remessas })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert("Erro ao gerar lote: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao gerar lote de etiquetas");
                });
            }
        }

        function imprimirEtiqueta(etiquetaId) {
            window.open("/admin/remessa-correios/imprimir-etiqueta/" + etiquetaId, "_blank");
        }

        function imprimirTodasEtiquetas() {
            window.open("/admin/remessa-correios/imprimir-todas-etiquetas", "_blank");
        }

        function rastrearEtiqueta(etiquetaId) {
            window.open("/admin/remessa-correios/rastrear/" + etiquetaId, "_blank");
        }

        function confirmarPostagem(etiquetaId) {
            if (confirm("Confirmar postagem desta etiqueta?")) {
                fetch("/admin/remessa-correios/confirmar-postagem/" + etiquetaId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Postagem confirmada com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao confirmar postagem: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao confirmar postagem");
                });
            }
        }

        function verDetalhesRemessa(pedidoId) {
            window.open("/admin/pedidos/detalhes/" + pedidoId, "_blank");
        }

        function regerarEtiqueta(pedidoId, etiquetaId) {
            if (!confirm("Isso vai DELETAR a etiqueta atual (#" + etiquetaId + ") e gerar uma nova com as medidas atuais do pedido. Continuar?")) return;
            fetch("/admin/remessa-correios/regerar-etiqueta/" + pedidoId, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ etiqueta_id: etiquetaId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("Etiqueta regerada com sucesso! Novo código: " + data.codigo_etiqueta);
                    location.reload();
                } else {
                    alert("Erro ao regerar etiqueta: " + data.message);
                }
            })
            .catch(() => alert("Erro ao regerar etiqueta"));
        }
    </script>
</body>
</html>';
        exit;
    }

    public function gerarLoteEtiquetas($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        try {
            $raw = file_get_contents('php://input');
            $payload = json_decode((string) $raw, true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $ids = $payload['remessas'] ?? $payload['pedidos'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'Nenhum pedido selecionado']);
                exit;
            }

            $ok = 0;
            $erros = [];

            foreach ($ids as $id) {
                $pid = (int) $id;
                if ($pid <= 0) {
                    continue;
                }
                try {
                    $this->connection->beginTransaction();
                    $this->criarEtiquetaCorreiosParaPedido($pid);
                    $this->connection->commit();
                    $ok++;
                } catch (\Exception $e) {
                    try {
                        $this->connection->rollBack();
                    } catch (\Exception $e2) {
                    }
                    $erros[] = 'Pedido #' . $pid . ': ' . $e->getMessage();
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Etiquetas geradas: ' . $ok,
                'errors' => $erros,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    public function imprimirTodasEtiquetas($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        try {
            $stmt = $this->connection->prepare("SELECT * FROM correios_etiquetas WHERE status IN ('gerada','impressa') ORDER BY created_at DESC");
            $stmt->execute();
            $etiquetas = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // best-effort: marcar como impressa
            try {
                $stmtUp = $this->connection->prepare("UPDATE correios_etiquetas SET status = 'impressa', data_impressao = COALESCE(data_impressao, NOW()) WHERE status = 'gerada'");
                $stmtUp->execute();
            } catch (\Exception $e) {
            }

            echo '<!DOCTYPE html><html><head><title>Etiquetas Correios</title><style>body{font-family:Arial,sans-serif;margin:20px}.etiqueta{border:2px solid #000;padding:20px;width:400px;margin:0 auto 20px}.header{text-align:center;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px}.codigo{font-size:18px;font-weight:bold;text-align:center;margin-bottom:20px}.section{margin-bottom:15px}.label{font-weight:bold}@media print{body{margin:0}}</style></head><body>';

            foreach ($etiquetas as $etiqueta) {
                $dadosRemetente = json_decode((string) ($etiqueta['dados_remetente'] ?? ''), true);
                $dadosDestinatario = json_decode((string) ($etiqueta['dados_destinatario'] ?? ''), true);
                if (!is_array($dadosRemetente)) $dadosRemetente = [];
                if (!is_array($dadosDestinatario)) $dadosDestinatario = [];

                echo '<div class="etiqueta">'
                    . '<div class="header"><h3>CORREIOS - ETIQUETA DE POSTAGEM</h3></div>'
                    . '<div class="codigo">CÓDIGO: ' . htmlspecialchars((string) ($etiqueta['codigo_etiqueta'] ?? '')) . '</div>'
                    . '<div class="section"><div class="label">REMETENTE:</div>'
                    . '<div>' . htmlspecialchars((string) ($dadosRemetente['nome'] ?? '')) . '</div>'
                    . '<div>' . htmlspecialchars((string) ($dadosRemetente['endereco'] ?? '')) . '</div>'
                    . '<div>' . htmlspecialchars((string) (($dadosRemetente['cidade'] ?? '') . '/' . ($dadosRemetente['estado'] ?? ''))) . ' - CEP: ' . htmlspecialchars((string) ($dadosRemetente['cep'] ?? '')) . '</div>'
                    . '</div>'
                    . '<div class="section"><div class="label">DESTINATÁRIO:</div>'
                    . '<div>' . htmlspecialchars((string) ($dadosDestinatario['nome'] ?? '')) . '</div>'
                    . '<div>' . htmlspecialchars((string) ($dadosDestinatario['endereco'] ?? '')) . '</div>'
                    . '<div>' . htmlspecialchars((string) (($dadosDestinatario['cidade'] ?? '') . '/' . ($dadosDestinatario['estado'] ?? ''))) . ' - CEP: ' . htmlspecialchars((string) ($dadosDestinatario['cep'] ?? '')) . '</div>'
                    . '</div>'
                    . '</div>';
            }

            echo '<script>window.onload=function(){window.print();}</script></body></html>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        exit;
    }

    public function rastrearEtiqueta($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $etiquetaId = (int) $request->getParam('id');
        try {
            $stmt = $this->connection->prepare('SELECT codigo_etiqueta FROM correios_etiquetas WHERE id = ? LIMIT 1');
            $stmt->execute([$etiquetaId]);
            $codigo = (string) ($stmt->fetchColumn() ?: '');
            if ($codigo === '') {
                echo '<div class="alert alert-danger">Etiqueta não encontrada</div>';
                exit;
            }

            $url = 'https://rastreamento.correios.com.br/app/index.php?objeto=' . urlencode($codigo);
            header('Location: ' . $url);
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        exit;
    }

    private function criarEtiquetaCorreiosParaPedido(int $pedidoId): array {
        if ($pedidoId <= 0) {
            throw new \Exception('Pedido inválido');
        }

        $stmtExiste = $this->connection->prepare('SELECT id, codigo_etiqueta FROM correios_etiquetas WHERE pedido_id = ? LIMIT 1');
        $stmtExiste->execute([$pedidoId]);
        $rowExiste = $stmtExiste->fetch(\PDO::FETCH_ASSOC);
        if (is_array($rowExiste) && !empty($rowExiste['id'])) {
            throw new \Exception('Já existe etiqueta Correios para este pedido');
        }

        $pedidoModel = new PedidoEcommerce();
        $pedido = null;
        try {
            $pedido = $pedidoModel->getComDetalhes($pedidoId);
        } catch (\Exception $e) {
            $pedido = null;
        }

        if (!is_array($pedido) || empty($pedido['id'])) {
            throw new \Exception('Pedido não encontrado');
        }

        $cfgSigep = $this->getSigepConfig();
        $cfgProvider = $this->getCorreiosProviderConfig();
        $colsCE = $this->getColsCorreiosEtiquetas();

        $sigepUsed = 0;
        $sigepReq = null;
        $sigepResp = null;
        $sigepErr = null;
        $sigepAmb = (string) ($cfgSigep['ambiente'] ?? '');
        $sigepSemDv = null;
        $sigepDv = null;

        $prepostagemId = null;
        $prepostagemReciboRotulo = null;
        $prepostagemReq = null;
        $prepostagemResp = null;
        $prepostagemErr = null;

        $codigoEtiqueta = '';
        $providerUsed = (string) ($cfgProvider['provider'] ?? 'sigep');

        if ($providerUsed === 'prepostagem_v3') {
            $token = (string) ($cfgProvider['prepostagem_token'] ?? '');
            if (trim($token) === '') {
                try {
                    $tokSvc = new CorreiosTokenService();
                    $rTok = $tokSvc->getValidTokenFromSigep('prepostagem');
                    if (!empty($rTok['success']) && !empty($rTok['token'])) {
                        $token = (string) $rTok['token'];
                    }
                } catch (\Exception $e) {
                }
            }
            if (trim($token) === '') {
                throw new \Exception('Pré-Postagem: configure o token (Cartão de Postagem) no Admin');
            }
            $baseUrl = $this->getPrepostagemBaseUrl((string) ($cfgProvider['ambiente'] ?? 'homologacao'));
            $svc = new CorreiosPrepostagemService($baseUrl, $token);

            $payload = $this->buildPrepostagemPayload($pedido, $cfgProvider);
            $prepostagemReq = $payload;

            $r = $svc->criarPrepostagem($payload);
            if (empty($r['success']) && stripos((string) ($r['error'] ?? ''), 'GTW-007') !== false) {
                try {
                    $tokSvc = new CorreiosTokenService();
                    $rTok = $tokSvc->getValidTokenFromSigep('prepostagem');
                    if (!empty($rTok['success']) && !empty($rTok['token'])) {
                        $token = (string) $rTok['token'];
                        $svc = new CorreiosPrepostagemService($baseUrl, $token);
                        $r = $svc->criarPrepostagem($payload);
                    }
                } catch (\Exception $e) {
                }
            }
            if (empty($r['success'])) {
                $prepostagemErr = (string) ($r['error'] ?? 'Falha ao criar pré-postagem');
                $prepostagemResp = $r['raw'] ?? $r;
                throw new \Exception('Pré-Postagem falhou ao criar: ' . $prepostagemErr);
            }
            $prepostagemResp = $r['data'] ?? null;

            $prepostagemId = is_array($prepostagemResp) ? ($prepostagemResp['id'] ?? null) : null;
            $codigoEtiqueta = is_array($prepostagemResp) ? ((string) ($prepostagemResp['codigoObjeto'] ?? '')) : '';
            $codigoEtiqueta = $this->normalizarEtiquetaCorreios($codigoEtiqueta);
            if ($codigoEtiqueta === '') {
                throw new \Exception('Pré-Postagem: resposta sem codigoObjeto');
            }

            // solicitar rótulo assíncrono (best-effort)
            try {
                $rotPayload = [
                    'tipoRotulo' => 'P',
                    'formatoRotulo' => 'ET',
                    'imprimeRemetente' => 'S',
                    'idsPrePostagem' => $prepostagemId ? [(string) $prepostagemId] : [],
                ];
                $rr = $svc->solicitarRotuloAssincronoPdf($rotPayload);
                if (!empty($rr['success'])) {
                    $d = $rr['data'] ?? [];
                    if (is_array($d)) {
                        foreach (['idRecibo', 'recibo', 'reciboSolicitacaoAssincronaRotulo', 'reciboSolicitacaoAssincrona'] as $k) {
                            if (!empty($d[$k]) && is_string($d[$k])) {
                                $prepostagemReciboRotulo = (string) $d[$k];
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        } else {
            if (empty($cfgSigep['enabled'])) {
                throw new \Exception('SIGEP está desabilitado. Habilite e configure em /admin/configuracoes > Entrega > Correios (SIGEP Web).');
            }

            $hasMin = !empty($cfgSigep['usuario']) && !empty($cfgSigep['senha']) && !empty($cfgSigep['contrato']) && !empty($cfgSigep['cartao']) && !empty($cfgSigep['servico_codigo']);
            if (!$hasMin) {
                throw new \Exception('SIGEP: configuração incompleta. Preencha usuário, senha, contrato, cartão de postagem e código do serviço.');
            }

            $sigepUsed = 1;
            $sigepReq = [
                'ambiente' => $sigepAmb,
                'usuario' => (string) ($cfgSigep['usuario'] ?? ''),
                'contrato' => (string) ($cfgSigep['contrato'] ?? ''),
                'cartao' => (string) ($cfgSigep['cartao'] ?? ''),
                'cnpj' => (string) ($cfgSigep['cnpj'] ?? ''),
                'servico_codigo' => (string) ($cfgSigep['servico_codigo'] ?? ''),
            ];

            try {
                $raw = $this->solicitarEtiquetaSigep($cfgSigep);
                $sigepResp = ['raw' => $raw];
                $packed = $this->completarEtiquetaComDv($raw);
                $codigoEtiqueta = (string) ($packed['etiqueta'] ?? $raw);
                $sigepSemDv = $packed['sem_dv'] ?? null;
                $sigepDv = $packed['dv'] ?? null;
            } catch (\Exception $e) {
                $sigepErr = $e->getMessage();
                throw new \Exception('SIGEP falhou ao gerar etiqueta: ' . $sigepErr);
            }
        }

        $codigoEtiqueta = $this->normalizarEtiquetaCorreios($codigoEtiqueta);
        if (!preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $codigoEtiqueta)) {
            throw new \Exception('Correios retornou uma etiqueta em formato inválido: ' . $codigoEtiqueta);
        }

        $cols = ['pedido_id', 'codigo_etiqueta', 'dados_remetente', 'dados_destinatario', 'status', 'created_at'];
        $vals = [$pedidoId, $codigoEtiqueta, json_encode($this->getDadosRemetente()), json_encode($this->getDadosDestinatario($pedido))];
        $ph = ['?', '?', '?', '?', "'gerada'", 'NOW()'];

        if (in_array('sigep_enabled_used', $colsCE, true)) {
            $cols[] = 'sigep_enabled_used';
            $vals[] = $sigepUsed;
            $ph[] = '?';
        }
        if (in_array('sigep_ambiente', $colsCE, true)) {
            $cols[] = 'sigep_ambiente';
            $vals[] = $sigepAmb;
            $ph[] = '?';
        }
        if (in_array('sigep_etiqueta_sem_dv', $colsCE, true)) {
            $cols[] = 'sigep_etiqueta_sem_dv';
            $vals[] = $sigepSemDv;
            $ph[] = '?';
        }
        if (in_array('sigep_dv', $colsCE, true)) {
            $cols[] = 'sigep_dv';
            $vals[] = $sigepDv;
            $ph[] = '?';
        }
        if (in_array('sigep_last_request_json', $colsCE, true)) {
            $cols[] = 'sigep_last_request_json';
            $vals[] = $sigepReq !== null ? json_encode($sigepReq) : null;
            $ph[] = '?';
        }
        if (in_array('sigep_last_response_json', $colsCE, true)) {
            $cols[] = 'sigep_last_response_json';
            $vals[] = $sigepResp !== null ? json_encode($sigepResp) : null;
            $ph[] = '?';
        }
        if (in_array('sigep_error', $colsCE, true)) {
            $cols[] = 'sigep_error';
            $vals[] = $sigepErr;
            $ph[] = '?';
        }

        if (in_array('correios_provider', $colsCE, true)) {
            $cols[] = 'correios_provider';
            $vals[] = $providerUsed;
            $ph[] = '?';
        }
        if (in_array('prepostagem_id', $colsCE, true)) {
            $cols[] = 'prepostagem_id';
            $vals[] = $prepostagemId;
            $ph[] = '?';
        }
        if (in_array('prepostagem_last_request_json', $colsCE, true)) {
            $cols[] = 'prepostagem_last_request_json';
            $vals[] = $prepostagemReq !== null ? json_encode($prepostagemReq) : null;
            $ph[] = '?';
        }
        if (in_array('prepostagem_last_response_json', $colsCE, true)) {
            $cols[] = 'prepostagem_last_response_json';
            $vals[] = $prepostagemResp !== null ? json_encode($prepostagemResp) : null;
            $ph[] = '?';
        }
        if (in_array('prepostagem_error', $colsCE, true)) {
            $cols[] = 'prepostagem_error';
            $vals[] = $prepostagemErr;
            $ph[] = '?';
        }
        if (in_array('prepostagem_recibo_rotulo', $colsCE, true)) {
            $cols[] = 'prepostagem_recibo_rotulo';
            $vals[] = $prepostagemReciboRotulo;
            $ph[] = '?';
        }

        $stmt = $this->connection->prepare('INSERT INTO correios_etiquetas (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')');
        $stmt->execute($vals);

        $etiquetaId = (int) $this->connection->lastInsertId();

        try {
            $obs = 'Etiqueta Correios gerada (remessa Brasil)';
            if (!empty($codigoEtiqueta)) {
                $obs .= ' - Rastreio: ' . $codigoEtiqueta;
            }
            $pedidoModel->atualizarStatus((int) $pedidoId, 'enviado', $obs, $_SESSION['usuario_id'] ?? null);
        } catch (\Exception $e) {
        }

        return ['etiqueta_id' => $etiquetaId, 'codigo_etiqueta' => $codigoEtiqueta];
    }

    private function getRemessasProntas() {
        $colsPedidos = [];
        try {
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }

        $statusWhere = "(LOWER(COALESCE(p.status,'')) IN ('pago','paid','approved','aprovado','confirmado','confirmed'))";
        if (is_array($colsPedidos) && in_array('payment_status', $colsPedidos, true)) {
            $statusWhere .= " OR (UPPER(COALESCE(p.payment_status,'')) IN ('APPROVED','CONFIRMED','RECEIVED','PAID','SUCCEEDED','SUCCESS'))";
        }
        if (is_array($colsPedidos) && in_array('status_pagamento', $colsPedidos, true)) {
            $statusWhere .= " OR (UPPER(COALESCE(p.status_pagamento,'')) IN ('APPROVED','CONFIRMED','RECEIVED','PAID','SUCCEEDED','SUCCESS','PAGO','APROVADO'))";
        }

        $joinEndereco = '';
        $wherePais = '1=1';
        if (is_array($colsPedidos) && in_array('endereco_entrega_id', $colsPedidos, true) && $this->tableExists('enderecos')) {
            $joinEndereco = " LEFT JOIN enderecos e ON e.id = p.endereco_entrega_id ";
            $wherePais = "UPPER(COALESCE(e.pais,'BR')) = 'BR'";
        }

        $totalExpr = (is_array($colsPedidos) && in_array('total', $colsPedidos, true)) ? 'p.total' : (in_array('valor_total', $colsPedidos, true) ? 'p.valor_total' : '0');

        $stmt = $this->connection->prepare(" 
            SELECT
                p.id AS pedido_id,
                NULL AS janela_id,
                u.nome as cliente_nome,
                p.usuario_id,
                p.created_at,
                {$totalExpr} as valor_total
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            {$joinEndereco}
            LEFT JOIN correios_etiquetas ce ON ce.pedido_id = p.id
            WHERE ({$statusWhere})
              AND {$wherePais}
              AND ce.id IS NULL
            ORDER BY p.created_at ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r['id'] = (int) ($r['pedido_id'] ?? 0);
            $r['pedido_id'] = (int) ($r['pedido_id'] ?? 0);
            $r['valor_total'] = $r['valor_total'] ?? null;
            $r['peso_total'] = $r['peso_total'] ?? null;
            $r['created_at'] = $r['created_at'] ?? null;
        }
        unset($r);
        return $rows;
    }

    private function getEtiquetasGeradas() {
        $stmt = $this->connection->prepare(" 
            SELECT ce.*, p.usuario_id, u.nome as cliente_nome
            FROM correios_etiquetas ce
            LEFT JOIN pedidos p ON p.id = ce.pedido_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE ce.status = 'gerada'
              AND ce.data_impressao IS NULL
            ORDER BY ce.created_at DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['remessa_id'] = (int) ($r['pedido_id'] ?? 0);
        }
        unset($r);
        return $rows;
    }

    private function getEtiquetasImpressas() {
        $stmt = $this->connection->prepare(" 
            SELECT ce.*, p.usuario_id, u.nome as cliente_nome
            FROM correios_etiquetas ce
            LEFT JOIN pedidos p ON p.id = ce.pedido_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE ce.status = 'impressa'
              AND ce.data_postagem IS NULL
            ORDER BY ce.data_impressao DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['remessa_id'] = (int) ($r['pedido_id'] ?? 0);
        }
        unset($r);
        return $rows;
    }

    private function getTotalPostadas() {
        $stmt = $this->connection->prepare(" 
            SELECT COUNT(*) as total FROM correios_etiquetas 
            WHERE status = 'postada'
        ");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function gerarEtiqueta($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $pedidoId = (int) $request->getParam('id');
        
        try {
            $this->connection->beginTransaction();

            $r = $this->criarEtiquetaCorreiosParaPedido((int) $pedidoId);
            $etiquetaId = (int) ($r['etiqueta_id'] ?? 0);
            $codigoEtiqueta = (string) ($r['codigo_etiqueta'] ?? '');
            
            $this->connection->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Etiqueta gerada com sucesso!',
                'etiqueta_id' => $etiquetaId,
                'codigo_etiqueta' => $codigoEtiqueta
            ]);
            
        } catch (\Exception $e) {
            $this->connection->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    public function regerarEtiqueta($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $pedidoId = (int) $request->getParam('id');

        try {
            $this->connection->beginTransaction();

            // Deletar etiqueta existente
            $stDel = $this->connection->prepare('DELETE FROM correios_etiquetas WHERE pedido_id = ?');
            $stDel->execute([$pedidoId]);

            // Gerar nova etiqueta com medidas atuais
            $r = $this->criarEtiquetaCorreiosParaPedido($pedidoId);
            $this->connection->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Etiqueta regerada com sucesso!',
                'etiqueta_id' => (int) ($r['etiqueta_id'] ?? 0),
                'codigo_etiqueta' => (string) ($r['codigo_etiqueta'] ?? ''),
            ]);
        } catch (\Exception $e) {
            try { $this->connection->rollBack(); } catch (\Exception $e2) {}
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    private function getDadosRemetente() {
        $nome = 'Braziliana';
        $cnpj = '';
        $endereco = '';
        $cidade = '';
        $estado = '';
        $cep = '';
        $telefone = '';

        try {
            $cfg = $this->getCorreiosProviderConfig();
            $json = (string) ($cfg['prepostagem_sender_json'] ?? '');
            $sender = $this->parseJsonConfig($json, 'Remetente');

            if (isset($sender['nome']) && is_string($sender['nome']) && trim($sender['nome']) !== '') {
                $nome = trim($sender['nome']);
            }

            if (isset($sender['cpfCnpj']) && is_string($sender['cpfCnpj'])) {
                $cnpj = trim($sender['cpfCnpj']);
            } elseif (isset($sender['cnpj']) && is_string($sender['cnpj'])) {
                $cnpj = trim($sender['cnpj']);
            }

            $ddd = '';
            if (isset($sender['dddTelefone']) && is_string($sender['dddTelefone'])) {
                $ddd = trim($sender['dddTelefone']);
            } elseif (isset($sender['ddd']) && is_string($sender['ddd'])) {
                $ddd = trim($sender['ddd']);
            }
            $tel = '';
            if (isset($sender['telefone']) && is_string($sender['telefone'])) {
                $tel = trim($sender['telefone']);
            }
            $ddd = preg_replace('/\D+/', '', $ddd);
            $tel = preg_replace('/\D+/', '', $tel);
            if ($ddd !== '' && $tel !== '') {
                $telefone = '(' . $ddd . ') ' . $tel;
            } elseif ($tel !== '') {
                $telefone = $tel;
            }

            if (isset($sender['endereco']) && is_array($sender['endereco'])) {
                $e = $sender['endereco'];
                $logradouro = isset($e['logradouro']) ? trim((string) $e['logradouro']) : '';
                $numero = isset($e['numero']) ? trim((string) $e['numero']) : '';
                $complemento = isset($e['complemento']) ? trim((string) $e['complemento']) : '';
                $bairro = isset($e['bairro']) ? trim((string) $e['bairro']) : '';
                $cidade = isset($e['cidade']) ? trim((string) $e['cidade']) : $cidade;
                $estado = isset($e['uf']) ? trim((string) $e['uf']) : $estado;
                $cep = isset($e['cep']) ? trim((string) $e['cep']) : $cep;

                $parts = [];
                if ($logradouro !== '') $parts[] = $logradouro;
                if ($numero !== '') $parts[] = $numero;
                if ($complemento !== '') $parts[] = $complemento;
                if ($bairro !== '') $parts[] = $bairro;
                $endereco = implode(', ', $parts);
            }
        } catch (\Exception $e) {
        }

        if ($endereco === '') {
            $endereco = 'Endereço não disponível';
        }
        if ($cidade === '') {
            $cidade = 'Cidade';
        }
        if ($estado === '') {
            $estado = 'SP';
        }
        if ($cep === '') {
            $cep = '00000-000';
        }

        return [
            'nome' => $nome,
            'cnpj' => $cnpj,
            'endereco' => $endereco,
            'cidade' => $cidade,
            'estado' => $estado,
            'cep' => $cep,
            'telefone' => $telefone,
        ];
    }

    private function getDadosDestinatario($pedido) {
        $nome = (string) ($pedido['cliente_nome'] ?? ($pedido['nome'] ?? ''));
        $email = (string) ($pedido['cliente_email'] ?? ($pedido['email'] ?? ''));

        $endereco = 'Endereço não disponível';
        $cidade = 'Cidade';
        $estado = 'SP';
        $cep = '00000-000';
        $telefone = '';

        $cep2 = (string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? ''));
        $logradouro2 = (string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''));
        $numero2 = (string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''));
        $complemento2 = (string) ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''));
        $bairro2 = (string) ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ''));
        $cidade2 = (string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ''));
        $estado2 = (string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? ''));
        $telefone2 = (string) ($pedido['cliente_telefone'] ?? ($pedido['telefone'] ?? ''));

        if ($logradouro2 !== '') {
            $parts = [];
            $parts[] = $logradouro2;
            if (trim($numero2) !== '') {
                $parts[] = trim($numero2);
            }
            if (trim($complemento2) !== '') {
                $parts[] = trim($complemento2);
            }
            if (trim($bairro2) !== '') {
                $parts[] = trim($bairro2);
            }
            $endereco = implode(', ', $parts);
        }
        if ($cidade2 !== '') {
            $cidade = $cidade2;
        }
        if ($estado2 !== '') {
            $estado = $estado2;
        }
        if ($cep2 !== '') {
            $cep = $cep2;
        }
        if ($telefone2 !== '') {
            $telefone = $telefone2;
        }

        if (isset($pedido['endereco']) && is_array($pedido['endereco'])) {
            $end = $pedido['endereco'];
            $endereco = (string) ($end['endereco'] ?? ($end['logradouro'] ?? $endereco));
            $cidade = (string) ($end['cidade'] ?? $cidade);
            $estado = (string) ($end['estado'] ?? $estado);
            $cep = (string) ($end['cep'] ?? $cep);
            $telefone = (string) ($end['telefone'] ?? $telefone);
        }

        return [
            'nome' => $nome,
            'email' => $email,
            'endereco' => $endereco,
            'cidade' => $cidade,
            'estado' => $estado,
            'cep' => $cep,
            'telefone' => $telefone
        ];
    }

    public function confirmarPostagem($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $etiquetaId = $request->getParam('id');
        
        try {
            $stmt = $this->connection->prepare(" 
                UPDATE correios_etiquetas 
                SET status = 'postada', data_postagem = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$etiquetaId]);

            try {
                $stmtP = $this->connection->prepare('SELECT pedido_id FROM correios_etiquetas WHERE id = ? LIMIT 1');
                $stmtP->execute([$etiquetaId]);
                $pid = (int) ($stmtP->fetchColumn() ?: 0);
                if ($pid > 0) {
                    $pedidoModel = new PedidoEcommerce();
                    $pedidoModel->atualizarStatus($pid, 'enviado', 'Postagem Correios confirmada', $_SESSION['usuario_id'] ?? null);
                }
            } catch (\Exception $e) {
            }
            
            echo json_encode(['success' => true, 'message' => 'Postagem confirmada com sucesso!']);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    public function imprimirEtiqueta($request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        $etiquetaId = $request->getParam('id');
        
        try {
            $stmt = $this->connection->prepare(" 
                SELECT * FROM correios_etiquetas WHERE id = ?
            ");
            $stmt->execute([$etiquetaId]);
            $etiqueta = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$etiqueta) {
                echo '<div class="alert alert-danger">Etiqueta não encontrada</div>';
                exit;
            }
            
            // Marcar como impressa
            $stmt = $this->connection->prepare(" 
                UPDATE correios_etiquetas 
                SET status = 'impressa', data_impressao = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$etiquetaId]);
            
            // Gerar HTML da etiqueta para impressão
            $dadosRemetente = json_decode($etiqueta['dados_remetente'], true);
            $dadosDestinatario = json_decode($etiqueta['dados_destinatario'], true);
            
            $codigo = (string) ($etiqueta['codigo_etiqueta'] ?? '');
            $codigoHtml = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
            $codigoJs = json_encode($codigo);

            $remNome = htmlspecialchars((string) ($dadosRemetente['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $remEnd = htmlspecialchars((string) ($dadosRemetente['endereco'] ?? ''), ENT_QUOTES, 'UTF-8');
            $remCidadeUf = htmlspecialchars((string) (($dadosRemetente['cidade'] ?? '') . '/' . ($dadosRemetente['estado'] ?? '')), ENT_QUOTES, 'UTF-8');
            $remCep = htmlspecialchars((string) ($dadosRemetente['cep'] ?? ''), ENT_QUOTES, 'UTF-8');

            $destNome = htmlspecialchars((string) ($dadosDestinatario['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $destEnd = htmlspecialchars((string) ($dadosDestinatario['endereco'] ?? ''), ENT_QUOTES, 'UTF-8');
            $destCidadeUf = htmlspecialchars((string) (($dadosDestinatario['cidade'] ?? '') . '/' . ($dadosDestinatario['estado'] ?? '')), ENT_QUOTES, 'UTF-8');
            $destCep = htmlspecialchars((string) ($dadosDestinatario['cep'] ?? ''), ENT_QUOTES, 'UTF-8');

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Etiqueta Correios #{$codigoHtml}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .etiqueta {
            border: 2px solid #000;
            padding: 16px;
            width: 420px;
            margin: 0 auto;
        }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
        .codigo { font-size: 16px; font-weight: bold; text-align: center; margin: 10px 0; letter-spacing: 1px; }
        .barcode { display: flex; justify-content: center; margin: 6px 0 12px; }
        .section { margin-bottom: 10px; }
        .label { font-weight: bold; }
        .small { font-size: 12px; }
        @media print { body { margin: 0; } }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <div class="etiqueta">
        <div class="header">
            <h3 style="margin:0">CORREIOS</h3>
            <div class="small">Etiqueta de postagem</div>
        </div>

        <div class="barcode"><svg id="barcode"></svg></div>
        <div class="codigo">{$codigoHtml}</div>

        <div class="section">
            <div class="label">REMETENTE</div>
            <div>{$remNome}</div>
            <div class="small">{$remEnd}</div>
            <div class="small">{$remCidadeUf} - CEP: {$remCep}</div>
        </div>

        <div class="section">
            <div class="label">DESTINATÁRIO</div>
            <div>{$destNome}</div>
            <div class="small">{$destEnd}</div>
            <div class="small">{$destCidadeUf} - CEP: {$destCep}</div>
        </div>
    </div>
    <script>
        (function(){
            var code = {$codigoJs};
            try {
                JsBarcode('#barcode', code, {
                    format: 'CODE128',
                    displayValue: false,
                    margin: 0,
                    height: 56
                });
            } catch (e) {}
            window.onload = function(){ window.print(); };
        })();
    </script>
</body>
</html>
HTML;

            echo $html;
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
        exit;
    }
}
