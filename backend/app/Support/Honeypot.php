<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Proteção comum aos seis formulários públicos (ver docs/estrutura-site.md §2.3): sem
 * CAPTCHA de terceiro, que implicaria cookie e o banner de consentimento que o projeto evita
 * ao escolher o Umami (ver docs/decisoes/0006-umami-em-vez-de-google-analytics.md).
 *
 * O campo não entra nas regras do FormRequest de propósito — validá-lo geraria um 422 que
 * ensina um bot mais cuidadoso a esvaziar o campo e tentar de novo. Em vez disso, o
 * Controller aceita a submissão normalmente (mesma resposta de sucesso) e simplesmente não
 * grava nada — ver cada Controller público de formulário.
 */
final class Honeypot
{
    public const FIELD = 'website';

    public static function triggered(Request $request): bool
    {
        return $request->filled(self::FIELD);
    }
}
