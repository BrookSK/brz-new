<?php
/**
 * @file AuthenticationException.php
 * @responsibilidade Exceção para erros de autenticação
 * @descrição Lançada quando há falha na autenticação do usuário
 * @conexão Usada por AuthService e Controllers
 */

namespace App\Shared\Exceptions;

use Exception;

class AuthenticationException extends Exception {
    private ?string $reason;

    public function __construct(string $message = 'Falha na autenticação', ?string $reason = null, int $code = 401, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->reason = $reason;
    }

    public function getReason(): ?string {
        return $this->reason;
    }

    public function toArray(): array {
        return [
            'error' => 'AuthenticationException',
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'code' => $this->getCode()
        ];
    }
}
