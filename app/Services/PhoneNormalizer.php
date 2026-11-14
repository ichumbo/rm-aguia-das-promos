<?php

namespace App\Services;

use App\Exceptions\InvalidPhoneException;

class PhoneNormalizer
{
    /**
     * Normalize a phone number to E.164 (+<digits>).
     *
     * Rules:
     * - Country code must be explicit (+CC...) unless $defaultCountryCode is supplied by caller.
     * - For Brazil (+55), DDD (2 digits, 11-99 without leading zero) is mandatory; never invent DDD.
     * - Never invent the ninth digit: both 10-digit (DDD + 8) and 11-digit (DDD + 9 + 8) are kept as provided.
     *
     * @throws InvalidPhoneException
     */
    public function normalize(string $raw, ?string $defaultCountryCode = null): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || preg_match('/[a-zA-Z]/', $trimmed)) {
            throw new InvalidPhoneException('Telefone inválido: formato não reconhecido.');
        }

        $hasPlus = str_starts_with($trimmed, '+');
        if (! $hasPlus && str_starts_with($trimmed, '00')) {
            $trimmed = '+'.substr($trimmed, 2);
            $hasPlus = true;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            throw new InvalidPhoneException('Telefone inválido: nenhum dígito encontrado.');
        }

        if (! $hasPlus) {
            $countryDigits = $defaultCountryCode !== null ? preg_replace('/\D+/', '', $defaultCountryCode) : '';
            if ($countryDigits === '') {
                throw new InvalidPhoneException('Informe o código do país explícito (ex: +55).');
            }

            // If user already typed the explicit country code without '+', only accept when clearly matching 55 + 10/11 digits
            if ($countryDigits === '55' && (strlen($digits) === 12 || strlen($digits) === 13) && str_starts_with($digits, '55')) {
                $national = substr($digits, 2);
                $this->validateBrazilNational($national);

                return '+'.$digits;
            }

            if ($countryDigits === '55') {
                $this->validateBrazilNational($digits);

                return '+55'.$digits;
            }

            $digits = $countryDigits.$digits;
        }

        // E.164 general bounds: 8 to 15 digits, first digit 1-9
        if (! preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            throw new InvalidPhoneException('Telefone inválido fora do padrão E.164.');
        }

        if (str_starts_with($digits, '55')) {
            $national = substr($digits, 2);
            $this->validateBrazilNational($national);
        }

        return '+'.$digits;
    }

    public function digitsOnly(string $e164): string
    {
        return preg_replace('/\D+/', '', $e164) ?? '';
    }

    public function mask(string $e164): string
    {
        $clean = trim($e164);
        $len = strlen($clean);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        $prefix = substr($clean, 0, 5);
        $suffix = substr($clean, -4);
        $stars = str_repeat('*', max(3, $len - 9));

        return $prefix.$stars.$suffix;
    }

    private function validateBrazilNational(string $national): void
    {
        $len = strlen($national);

        if ($len !== 10 && $len !== 11) {
            throw new InvalidPhoneException('Telefone brasileiro deve conter DDD + 8 ou 9 dígitos (sem inventar DDD ou nono dígito).');
        }

        $ddd = substr($national, 0, 2);
        if (! preg_match('/^[1-9]{2}$/', $ddd)) {
            throw new InvalidPhoneException('DDD brasileiro inválido.');
        }

        $subscriber = substr($national, 2);
        if ($len === 11 && ! str_starts_with($subscriber, '9')) {
            throw new InvalidPhoneException('Celular brasileiro de 11 dígitos (DDD + 9 dígitos) deve iniciar com 9 após o DDD.');
        }

        if ($len === 10 && str_starts_with($subscriber, '0')) {
            throw new InvalidPhoneException('Número brasileiro de 10 dígitos inválido.');
        }
    }
}
