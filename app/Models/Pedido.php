<?php
namespace App\Models;

class Pedido extends Model {
    protected $table = 'pedidos';

    public function createWithItems($clienteId, $items, $servicos, $impostos) {
        $this->connection->beginTransaction();
        
        try {
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['subtotal'];
            }
            
            $totalServicos = array_sum($servicos);
            $totalImpostos = $subtotal * ($impostos / 100);
            $total = $subtotal + $totalServicos + $totalImpostos;
            
            $pedidoData = [
                'cliente_id' => $clienteId,
                'subtotal' => $subtotal,
                'servicos' => $totalServicos,
                'impostos' => $totalImpostos,
                'total' => $total,
                'status' => 'selecao'
            ];
            
            $this->create($pedidoData);
            $pedidoId = $this->connection->lastInsertId();
            
            foreach ($items as $item) {
                $itemStmt = $this->connection->prepare("INSERT INTO pedido_items (pedido_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal)");
                $itemStmt->bindParam(':pedido_id', $pedidoId);
                $itemStmt->bindParam(':produto_id', $item['produto_id']);
                $itemStmt->bindParam(':quantidade', $item['quantidade']);
                $itemStmt->bindParam(':preco_unitario', $item['preco_unitario']);
                $itemStmt->bindParam(':subtotal', $item['subtotal']);
                $itemStmt->execute();
            }
            
            $this->connection->commit();
            return $pedidoId;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function updateStatus($id, $status) {
        return $this->update($id, ['status' => $status]);
    }

    public function getComItems($id) {
        $stmt = $this->connection->prepare("
            SELECT p.*, c.nome as cliente_nome, c.email as cliente_email 
            FROM {$this->table} p 
            JOIN clientes c ON p.cliente_id = c.id 
            WHERE p.id = :id
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($pedido) {
            $itemsStmt = $this->connection->prepare("
                SELECT pi.*, pr.nome as produto_nome 
                FROM pedido_items pi 
                JOIN produtos pr ON pi.produto_id = pr.id 
                WHERE pi.pedido_id = :id
            ");
            $itemsStmt->bindParam(':id', $id);
            $itemsStmt->execute();
            $pedido['items'] = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $pedido;
    }

    public function addRastreamento($pedidoId, $etapa, $descricao, $local = null) {
        $stmt = $this->connection->prepare("INSERT INTO rastreamento (pedido_id, etapa, descricao, local) VALUES (:pedido_id, :etapa, :descricao, :local)");
        $stmt->bindParam(':pedido_id', $pedidoId);
        $stmt->bindParam(':etapa', $etapa);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':local', $local);
        return $stmt->execute();
    }

    public function getRastreamento($pedidoId) {
        $stmt = $this->connection->prepare("SELECT * FROM rastreamento WHERE pedido_id = :id ORDER BY data_hora DESC");
        $stmt->bindParam(':id', $pedidoId);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
