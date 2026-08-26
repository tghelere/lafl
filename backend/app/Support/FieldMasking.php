<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mascaramento de dado pessoal em listagem administrativa (ver docs/protecao-de-dados.md,
 * "Padrão de exposição na interface" — o princípio de "listagem mascarada, ficha individual
 * completa" vale aqui para os seis formulários recebidos, mesmo o titular sendo adulto). O
 * valor completo só aparece no detalhe, que é auditado (ver
 * App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess).
 */
final class FieldMasking
{
    public static function firstName(?string $fullName): ?string
    {
        if ($fullName === null || trim($fullName) === '') {
            return $fullName;
        }

        return explode(' ', trim($fullName))[0];
    }

    /**
     * Mantém só os últimos dígitos — mesmo princípio de `cpf_last_digits` na matriz de
     * classificação de dados.
     */
    public static function lastDigits(?string $value, int $keep = 4): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '••••';
        }

        return '••••'.substr($digits, -$keep);
    }

    /**
     * Mantém o domínio inteiro, esconde a parte local — suficiente para reconhecer a
     * organização de quem escreveu sem expor o endereço completo na listagem.
     */
    public static function email(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $at = strrpos($email, '@');

        return $at === false ? '•••' : '•••'.substr($email, $at);
    }
}
