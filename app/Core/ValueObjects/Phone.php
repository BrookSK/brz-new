<?php
/**
 * @file Phone.php
 * @responsibilidade Value Object para Telefone
 * @descrição Representa um número de telefone brasileiro com validação
 * @conexão Usado pela entidade User e em validações
 */

namespace App\Core\ValueObjects;

class Phone {
    private string $value;
    private string $ddd;
    private string $number;
    private string $countryCode;

    public function __construct(string $phone) {
        $this->validate($phone);
        $this->format($phone);
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getDDD(): string {
        return $this->ddd;
    }

    public function getNumber(): string {
        return $this->number;
    }

    public function getCountryCode(): string {
        return $this->countryCode;
    }

    public function getFullNumber(): string {
        return $this->countryCode . $this->ddd . $this->number;
    }

    public function formatNational(): string {
        return "({$this->ddd}) {$this->getFormattedNumber()}";
    }

    public function formatInternational(): string {
        return "+{$this->countryCode} ({$this->ddd}) {$this->getFormattedNumber()}";
    }

    private function getFormattedNumber(): string {
        if (strlen($this->number) === 8) {
            return substr($this->number, 0, 4) . '-' . substr($this->number, 4);
        } else {
            return substr($this->number, 0, 5) . '-' . substr($this->number, 5);
        }
    }

    private function validate(string $phone): void {
        $phone = $this->cleanPhone($phone);
        
        if (empty($phone)) {
            throw new \InvalidArgumentException('Telefone não pode ser vazio');
        }

        if (strlen($phone) < 10 || strlen($phone) > 13) {
            throw new \InvalidArgumentException('Telefone deve ter entre 10 e 13 dígitos (incluindo DDD)');
        }

        // Verificar se todos os dígitos são iguais
        if (preg_match('/^(\d)\1+$/', $phone)) {
            throw new \InvalidArgumentException('Telefone inválido');
        }

        // Validar DDD
        $ddd = substr($phone, -10, 2);
        if (!$this->isValidDDD($ddd)) {
            throw new \InvalidArgumentException('DDD inválido');
        }
    }

    private function cleanPhone(string $phone): string {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    private function isValidDDD(string $ddd): bool {
        $validDDDs = [
            '11', '12', '13', '14', '15', '16', '17', '18', '19', // São Paulo
            '21', '22', '24', // Rio de Janeiro
            '27', '28', // Espírito Santo
            '31', '32', '33', '34', '35', '37', '38', // Minas Gerais
            '41', '42', '43', '44', '45', '46', // Paraná
            '47', '48', '49', // Santa Catarina
            '51', '53', '54', '55', // Rio Grande do Sul
            '61', // Distrito Federal
            '62', '64', // Goiás
            '63', // Tocantins
            '65', '66', // Mato Grosso
            '67', // Mato Grosso do Sul
            '68', // Acre
            '69', // Rondônia
            '71', '73', '74', '75', '77', // Bahia
            '79', // Sergipe
            '81', '82', '83', '85', '87', '88', // Pernambuco, Paraíba, Rio Grande do Norte, Ceará
            '91', '92', '93', '94', '95', '96', '97', '98', '99' // Pará, Amazonas, Amapá, Acre, Roraima, Maranhão
        ];

        return in_array($ddd, $validDDDs);
    }

    private function format(string $phone): void {
        $phone = $this->cleanPhone($phone);
        
        // Extrair código do país (se tiver)
        if (strlen($phone) === 13 && substr($phone, 0, 2) === '55') {
            $this->countryCode = substr($phone, 0, 2);
            $this->ddd = substr($phone, 2, 2);
            $this->number = substr($phone, 4);
        } elseif (strlen($phone) === 12) {
            $this->countryCode = '55';
            $this->ddd = substr($phone, 0, 2);
            $this->number = substr($phone, 2);
        } else {
            $this->countryCode = '55';
            $this->ddd = substr($phone, 0, 2);
            $this->number = substr($phone, 2);
        }

        $this->value = $this->formatNational();
    }

    public function equals(Phone $other): bool {
        return $this->getFullNumber() === $other->getFullNumber();
    }

    public function isMobile(): bool {
        // Números de celular no Brasil começam com 9 (exceto alguns casos especiais)
        return strlen($this->number) === 9 && $this->number[0] === '9';
    }

    public function isLandline(): bool {
        return !$this->isMobile();
    }

    public function __toString(): string {
        return $this->value;
    }

    public function mask(): string {
        if (strlen($this->number) === 8) {
            return "({$this->ddd}) ****-" . substr($this->number, -4);
        } else {
            return "({$this->ddd}) *****-" . substr($this->number, -4);
        }
    }
}
