<?php

namespace App\Services;

class CpfValidator {
    public static function onlyDigits(?string $value): string {
        $v = (string) ($value ?? '');
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    /**
     * Formata CPF (11 dígitos) ou CNPJ (14 dígitos) com máscara.
     * Retorna o valor original se não for CPF nem CNPJ.
     */
    public static function format(?string $value): string {
        $digits = self::onlyDigits($value);
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
        }
        if (strlen($digits) === 14) {
            return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
        }
        return (string) ($value ?? '');
    }


    public static function isValid(?string $cpf): bool {
        $cpf = self::onlyDigits($cpf);
        if ($cpf === '' || strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $digits = array_map('intval', str_split($cpf));

        $sum1 = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum1 += $digits[$i] * $w;
        }
        $d1 = 11 - ($sum1 % 11);
        if ($d1 >= 10) {
            $d1 = 0;
        }
        if ($digits[9] !== $d1) {
            return false;
        }

        $sum2 = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum2 += $digits[$i] * $w;
        }
        $d2 = 11 - ($sum2 % 11);
        if ($d2 >= 10) {
            $d2 = 0;
        }

        return $digits[10] === $d2;
    }
}
