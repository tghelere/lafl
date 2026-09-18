<?php

declare(strict_types=1);

use App\Support\Http\KnownBots;

test('reconhece robô conhecido pelo User-Agent', function (string $userAgent): void {
    expect(KnownBots::matches($userAgent))->toBeTrue();
})->with([
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'WhatsApp/2.23.20.0 A',
    'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
    'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
    'CCBot/2.0 (https://commoncrawl.org/faq/)',
    'MeuCrawler/1.0',
    'AlgumSpider/3.2',
    '(compatible; QualquerBot; +http://exemplo.org)',
]);

test('não confunde navegador de verdade com robô', function (string $userAgent): void {
    expect(KnownBots::matches($userAgent))->toBeFalse();
})->with([
    'firefox' => 'Mozilla/5.0 (X11; Linux x86_64; rv:141.0) Gecko/20100101 Firefox/141.0',
    'chrome' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
    'safari ios' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
    // "Cubot" é marca de celular e aparece em User-Agent de navegador real — é por isso que a
    // assinatura genérica exige delimitador em vez de casar com "bot" solto.
    'android de marca com "bot" no nome' => 'Mozilla/5.0 (Linux; Android 13; CUBOT NOTE 21) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
]);

test('ausência de User-Agent conta como robô', function (?string $userAgent): void {
    expect(KnownBots::matches($userAgent))->toBeTrue();
})->with([null, '', '   ']);
