<?php
/**
 * @file Email.php
 * @responsibilidade Value Object para Email
 * @descrição Representa um endereço de email com validação
 * @conexão Usado pela entidade User e em validações
 */

namespace App\Core\ValueObjects;

class Email {
    private string $value;

    public function __construct(string $email) {
        $this->validate($email);
        $this->value = strtolower(trim($email));
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getDomain(): string {
        return substr(strrchr($this->value, "@"), 1);
    }

    public function getLocal(): string {
        return strstr($this->value, "@", true);
    }

    private function validate(string $email): void {
        $email = trim($email);
        
        if (empty($email)) {
            throw new \InvalidArgumentException('Email não pode ser vazio');
        }

        if (strlen($email) > 254) {
            throw new \InvalidArgumentException('Email muito longo (máximo 254 caracteres)');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }

        // Validações adicionais
        $this->validateDomain($email);
        $this->validateLocal($email);
    }

    private function validateDomain(string $email): void {
        $domain = substr(strrchr($email, "@"), 1);
        
        if (strlen($domain) < 4) {
            throw new \InvalidArgumentException('Domínio do email muito curto');
        }

        if (!checkdnsrr($domain, "MX") && !checkdnsrr($domain, "A")) {
            // Em ambiente de desenvolvimento, pode ser necessário desabilitar esta verificação
            if (getenv('APP_ENV') !== 'development') {
                throw new \InvalidArgumentException('Domínio do email não existe');
            }
        }
    }

    private function validateLocal(string $email): void {
        $local = strstr($email, "@", true);
        
        if (strlen($local) < 1) {
            throw new \InvalidArgumentException('Parte local do email muito curta');
        }

        if (strlen($local) > 64) {
            throw new \InvalidArgumentException('Parte local do email muito longa');
        }

        // Verificar caracteres inválidos
        if (preg_match('/[\s<>"]/', $local)) {
            throw new \InvalidArgumentException('Email contém caracteres inválidos');
        }
    }

    public function equals(Email $other): bool {
        return $this->value === $other->value;
    }

    public function __toString(): string {
        return $this->value;
    }

    public function mask(): string {
        $email = $this->value;
        $parts = explode('@', $email);
        $local = $parts[0];
        $domain = $parts[1];
        
        if (strlen($local) <= 2) {
            return $email;
        }
        
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', strlen($local) - 2);
        return $maskedLocal . '@' . $domain;
    }
}
