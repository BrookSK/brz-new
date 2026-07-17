<?php
namespace App\Models;

class PacoteRecebido extends Model {
    protected $table = 'pacotes_recebidos';

    /**
     * Lista NCM pré-definida para cadastro de pacotes
     */
    public static function getNcmOptions(): array {
        return [
            // Vestuário
            '61091000' => 'Camiseta',
            '61101200' => 'Casaco/Moletom',
            '62046200' => 'Calca Jeans',
            '62034200' => 'Calca Masculina',
            '62044400' => 'Vestido',
            '62114300' => 'Conjunto Esportivo',
            '61121200' => 'Agasalho/Jaqueta',
            '62101000' => 'Roupa Intima',
            '61082200' => 'Calcinha/Lingerie',
            '61071200' => 'Cueca/Boxer',
            '61151000' => 'Meia',
            '62160000' => 'Luvas',
            '65050090' => 'Boné/Chapéu',
            '62171000' => 'Gravata',
            '61121100' => 'Moletom com Capuz',

            // Calçados
            '64041900' => 'Calcados (Tênis)',
            '64039990' => 'Sapato de Couro',
            '64021900' => 'Bota',
            '64041100' => 'Chinelo/Sandália',

            // Bolsas e Acessórios
            '42022200' => 'Bolsa',
            '42023100' => 'Carteira',
            '42021200' => 'Mala de Viagem',
            '42029200' => 'Mochila',
            '42023900' => 'Necessaire',

            // Joias e Bijuterias
            '71171900' => 'Bijuteria',
            '71131100' => 'Joia de Prata',
            '71131900' => 'Joia de Ouro',
            '91012100' => 'Relógio de Pulso',

            // Cosméticos e Higiene
            '33049900' => 'Cosmeticos',
            '33051000' => 'Shampoo',
            '33061000' => 'Creme Dental',
            '33030090' => 'Perfume',
            '33042000' => 'Maquiagem (Olhos)',
            '33041000' => 'Maquiagem (Lábios)',
            '33049100' => 'Pó/Base Facial',
            '33059000' => 'Condicionador/Creme Capilar',
            '33079000' => 'Desodorante',
            '34011100' => 'Sabonete',
            '96032100' => 'Escova de Dentes',

            // Eletrônicos
            '85176200' => 'Eletronicos (Geral)',
            '85171200' => 'Celular/Smartphone',
            '84713019' => 'Notebook/Tablet',
            '84718000' => 'Acessorios de Informatica',
            '85183000' => 'Fone de Ouvido/Headset',
            '85287200' => 'Monitor/TV',
            '84715000' => 'Desktop/PC',
            '84433200' => 'Impressora',
            '85234990' => 'Pen Drive/HD Externo',
            '85044090' => 'Carregador/Fonte',
            '85076000' => 'Bateria/Power Bank',
            '84716090' => 'Teclado/Mouse',
            '85285200' => 'Webcam',
            '85279900' => 'Caixa de Som/Speaker',
            '85219000' => 'Gravador de Vídeo/DVD',
            '90063100' => 'Câmera Fotográfica',
            '95045000' => 'Controle de Videogame',
            '95049000' => 'Console de Videogame',
            '85177090' => 'Capinha de Celular',
            '85235100' => 'Cartão de Memória',

            // Brinquedos e Bebê
            '95030099' => 'Brinquedos',
            '95030021' => 'Boneca/Action Figure',
            '95030031' => 'Brinquedo Eletrônico',
            '95061900' => 'Artigos de Esporte',
            '94018000' => 'Cadeira de Bebê',

            // Saúde e Suplementos
            '30049099' => 'Suplementos/Vitaminas',
            '21069030' => 'Whey Protein/Suplemento Alimentar',
            '30039099' => 'Medicamento (Sem Receita)',
            '90189099' => 'Equipamento Médico Pessoal',

            // Casa e Cozinha
            '39269090' => 'Acessorios Plastico',
            '69111000' => 'Louça/Porcelana',
            '73239300' => 'Utensílio de Cozinha (Inox)',
            '94016100' => 'Sofá/Poltrona',
            '94035000' => 'Móvel para Quarto',
            '63025100' => 'Toalha de Banho',
            '63023100' => 'Roupa de Cama/Lençol',
            '84501200' => 'Máquina de Lavar (até 10kg)',
            '85162900' => 'Aquecedor/Radiador',
            '84181000' => 'Geladeira/Frigobar',
            '85167100' => 'Cafeteira',
            '85094000' => 'Liquidificador/Mixer',

            // Ferramentas
            '82055900' => 'Ferramenta Manual',
            '84672900' => 'Ferramenta Elétrica',

            // Livros e Papelaria
            '49019900' => 'Livro',
            '48201000' => 'Caderno/Bloco',

            // Alimentos
            '17049090' => 'Doces/Chocolates',
            '09012100' => 'Café Torrado',
            '22030000' => 'Cerveja',

            // Automotivo
            '87089900' => 'Peça Automotiva',
            '40111000' => 'Pneu',

            // Pet
            '23099090' => 'Ração/Petisco Animal',

            // Instrumentos Musicais
            '92029000' => 'Instrumento Musical (Cordas)',
            '92060000' => 'Instrumento de Percussão',

            // Outros
            '96190000' => 'Fralda/Absorvente',
            '63079000' => 'Pano/Tecido (Geral)',
            '49090000' => 'Cartão Postal/Impresso',
            '97019000' => 'Obra de Arte/Quadro',
        ];
    }

    /**
     * Buscar pacotes pendentes por suite (para auto-adição ao carrinho)
     */
    public function getPendentesPorSuite(int $suite): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE numero_suite = :suite AND status = 'pendente' ORDER BY data_recebimento DESC"
        );
        $stmt->execute([':suite' => $suite]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar pacotes por usuario
     */
    public function getByUsuario(int $usuarioId): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE usuario_id = :uid ORDER BY created_at DESC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar pacotes por pedido
     */
    public function getByPedido(int $pedidoId): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE pedido_id = :pid ORDER BY id ASC"
        );
        $stmt->execute([':pid' => $pedidoId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Listagem com filtros e paginação
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 20): array {
        $where = [];
        $params = [];

        if (!empty($filtros['suite'])) {
            $where[] = 'numero_suite = :suite';
            $params[':suite'] = (int) $filtros['suite'];
        }
        if (!empty($filtros['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['data_inicio'])) {
            $where[] = 'data_recebimento >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $where[] = 'data_recebimento <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        if (!empty($filtros['busca'])) {
            $where[] = '(nome LIKE :busca OR fornecedor LIKE :busca2 OR CAST(numero_suite AS CHAR) LIKE :busca3)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
            $params[':busca2'] = '%' . $filtros['busca'] . '%';
            $params[':busca3'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($pagina - 1) * $porPagina;

        // Total
        $stmtTotal = $this->connection->prepare("SELECT COUNT(*) FROM {$this->table} {$whereClause}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        // Registros
        $sql = "SELECT p.*, u.nome AS usuario_nome, u.email AS usuario_email 
                FROM {$this->table} p 
                LEFT JOIN usuarios u ON u.id = p.usuario_id 
                {$whereClause} 
                ORDER BY p.created_at DESC 
                LIMIT {$porPagina} OFFSET {$offset}";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'registros' => $registros,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ];
    }

    /**
     * Atualizar status do pacote
     */
    public function atualizarStatus(int $id, string $status, ?int $pedidoId = null): bool {
        $data = ['status' => $status];
        if ($pedidoId !== null) {
            $data['pedido_id'] = $pedidoId;
        }
        return $this->update($id, $data);
    }

    /**
     * Buscar pacotes pendentes com dias expirados (para cron)
     */
    public function getPendentesComDias(): array {
        $stmt = $this->connection->prepare(
            "SELECT *, DATEDIFF(CURDATE(), data_recebimento) AS dias_desde_recebimento 
             FROM {$this->table} 
             WHERE status = 'pendente' 
             ORDER BY data_recebimento ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar usuario por suite
     */
    public function buscarUsuarioPorSuite(int $suite): ?array {
        $stmt = $this->connection->prepare(
            "SELECT id, nome, email, suite, telefone FROM usuarios WHERE suite = :suite LIMIT 1"
        );
        $stmt->execute([':suite' => $suite]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
