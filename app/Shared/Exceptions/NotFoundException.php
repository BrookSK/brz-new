<?php
/**
 * @file NotFoundException.php
 * @responsibilidade Exceção para recursos não encontrados
 * @descrição Lançada quando um recurso não é encontrado no sistema
 * @conexão Usada por Services e Controllers
 */

namespace App\Shared\Exceptions;

use Exception;

class NotFoundException extends Exception {
    private string $resource;
    private mixed $identifier;

    public function __construct(string $resource, mixed $identifier = null, int $code = 404, ?Exception $previous = null) {
        $message = $identifier 
            ? "{$resource} com identificador '{$identifier}' não encontrado"
            : "{$resource} não encontrado";
            
        parent::__construct($message, $code, $previous);
        $this->resource = $resource;
        $this->identifier = $identifier;
    }

    public function getResource(): string {
        return $this->resource;
    }

    public function getIdentifier(): mixed {
        return $this->identifier;
    }

    public function toArray(): array {
        return [
            'error' => 'NotFoundException',
            'message' => $this->getMessage(),
            'resource' => $this->resource,
            'identifier' => $this->identifier,
            'code' => $this->getCode()
        ];
    }
}
