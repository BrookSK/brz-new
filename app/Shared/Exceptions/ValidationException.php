<?php
/**
 * @file ValidationException.php
 * @responsibilidade Exceção para erros de validação
 * @descrição Lançada quando dados de entrada são inválidos
 * @conexão Usada por Services, Controllers e Validators
 */

namespace App\Shared\Exceptions;

use Exception;

class ValidationException extends Exception {
    private array $errors;

    public function __construct(string $message, array $errors = [], int $code = 400, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function getFirstError(): ?string {
        return $this->errors[0] ?? null;
    }

    public function addError(string $field, string $message): void {
        $this->errors[$field] = $message;
    }

    public function toArray(): array {
        return [
            'error' => 'ValidationException',
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'code' => $this->getCode()
        ];
    }
}
