<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Support\Str;

/**
 * Lista única de assinaturas de User-Agent de robô conhecido. Um lugar só, de propósito:
 * quem precisar filtrar robô noutro ponto do sistema usa esta classe em vez de recriar a
 * lista (hoje o único consumidor é
 * App\Actions\Transparency\RegisterTransparencyDocumentDownload, que não soma acesso de robô
 * na contagem de downloads).
 *
 * **Isto é aproximação, não identificação.** User-Agent é texto que o cliente escolhe: um
 * robô que se disfarce de navegador passa, e um navegador de verdade com User-Agent exótico
 * pode ser barrado. Verificação de verdade exigiria DNS reverso do IP (o que o Google
 * documenta como forma correta de validar o Googlebot) — custo e latência que uma contagem
 * aproximada de downloads não justifica.
 */
final class KnownBots
{
    /**
     * Comparadas em minúsculas, como substring. Três grupos: buscadores, pré-visualização de
     * link em rede social/mensageiro (cada compartilhamento de URL dispara um acesso), e
     * rastreadores de SEO e de treinamento de modelo. As três últimas são genéricas e pegam
     * a cauda longa — é o que torna a lista útil sem precisar crescer para sempre.
     *
     * @var list<string>
     */
    private const SIGNATURES = [
        // Buscadores
        'googlebot',
        'google-inspectiontool',
        'storebot-google',
        'bingbot',
        'bingpreview',
        'duckduckbot',
        'yandexbot',
        'baiduspider',
        'applebot',
        'petalbot',
        'seznambot',
        'slurp',
        // Pré-visualização de link
        'facebookexternalhit',
        'facebookcatalog',
        'facebot',
        'twitterbot',
        'linkedinbot',
        'whatsapp',
        'telegrambot',
        'slackbot',
        'discordbot',
        'embedly',
        'skypeuripreview',
        // SEO, arquivamento e coleta para modelo de linguagem
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'dotbot',
        'dataforseobot',
        'ia_archiver',
        'archive.org_bot',
        'gptbot',
        'oai-searchbot',
        'chatgpt-user',
        'claudebot',
        'anthropic-ai',
        'perplexitybot',
        'ccbot',
        'bytespider',
        'amazonbot',
        'meta-externalagent',
        // Genéricas
        // 'bot' sozinho não serve, nem seguido de espaço: casa com "CUBOT NOTE 21", marca de
        // celular que aparece em User-Agent de navegador de verdade (há teste para isso). Com
        // os delimitadores abaixo, casa com "MeuBot/1.0" e "(compatible; MeuBot; +http://…)",
        // que é como robô se apresenta na prática.
        'bot/',
        'bot;',
        'bot)',
        'crawler',
        'spider',
    ];

    /**
     * User-Agent ausente conta como robô: navegador sempre manda o cabeçalho, e o que chega
     * sem ele é, na prática, script. É a escolha conservadora para uma contagem que serve
     * para dizer à instituição quantas pessoas baixaram um documento.
     */
    public static function matches(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        return Str::contains(Str::lower($userAgent), self::SIGNATURES);
    }
}
