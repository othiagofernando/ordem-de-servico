<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BrazilianDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) === 11 && $this->isValidCpf($digits)) {
            return;
        }

        if (strlen($digits) === 14 && $this->isValidCnpj($digits)) {
            return;
        }

        $fail('O documento informado deve ser um CPF ou CNPJ válido.');
    }

    protected function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;

            for ($i = 0; $i < $t; $i++) {
                $sum += ((int) $cpf[$i]) * (($t + 1) - $i);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    protected function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstDigit = $this->calculateCnpjDigit(substr($cnpj, 0, 12), $firstWeights);
        $secondDigit = $this->calculateCnpjDigit(substr($cnpj, 0, 12).$firstDigit, $secondWeights);

        return $cnpj[12] === (string) $firstDigit && $cnpj[13] === (string) $secondDigit;
    }

    protected function calculateCnpjDigit(string $base, array $weights): int
    {
        $sum = 0;

        foreach (str_split($base) as $index => $digit) {
            $sum += ((int) $digit) * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
