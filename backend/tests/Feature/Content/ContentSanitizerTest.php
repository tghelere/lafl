<?php

declare(strict_types=1);

use App\Support\Html\ContentSanitizer;
use Database\Seeders\ContentPagesSeeder;

beforeEach(function (): void {
    $this->sanitizer = new ContentSanitizer;
});

dataset('payloads perigosos', [
    'tag script solta' => ['<p>ok</p><script>alert(1)</script>', 'alert'],
    'tag script no meio do parágrafo' => ['<p>a<script>alert(1)</script>b</p>', 'alert'],
    'atributo onclick' => ['<p onclick="alert(1)">texto</p>', 'onclick'],
    'atributo onmouseover em link' => ['<a href="/x" onmouseover="alert(1)">link</a>', 'onmouseover'],
    'atributo onerror em imagem' => ['<img src=x onerror="alert(1)">', 'onerror'],
    'href javascript:' => ['<p><a href="javascript:alert(1)">clique</a></p>', 'javascript'],
    'href JaVaScRiPt: maiúsculas' => ['<p><a href="JaVaScRiPt:alert(1)">clique</a></p>', 'avascript'],
    'href data:' => ['<p><a href="data:text/html,olá">clique</a></p>', 'data:'],
    'atributo style' => ['<p style="position:fixed;top:0">texto</p>', 'style'],
    'tag style' => ['<style>body{display:none}</style><p>ok</p>', 'display:none'],
    'iframe' => ['<iframe src="https://exemplo.invalid"></iframe><p>ok</p>', 'iframe'],
    'svg com onload' => ['<svg onload="alert(1)"></svg>', 'onload'],
    'form com input' => ['<form action="/x"><input name="a"></form><p>ok</p>', '<form'],
]);

test('remove o que é perigoso', function (string $html, string $needle): void {
    expect($this->sanitizer->sanitize($html))->not->toContain($needle);
})->with('payloads perigosos');

test('o texto legítimo em volta do payload perigoso sobrevive', function (): void {
    $output = $this->sanitizer->sanitize('<p>antes<script>alert(1)</script>depois</p>');

    expect($output)->toBe('<p>antesdepois</p>');
});

test('link com href perigoso perde só o href, não o texto', function (): void {
    $output = $this->sanitizer->sanitize('<p><a href="javascript:alert(1)">clique aqui</a></p>');

    expect($output)->toBe('<p><a>clique aqui</a></p>');
});

/**
 * O comportamento padrão do componente para tag desconhecida é derrubar o elemento junto com
 * os filhos — texto colado de editor externo (Word, Google Docs), que vem embrulhado em
 * div/span, sumiria inteiro sem aviso ao salvar. Ver a lista BLOCKED_ELEMENTS.
 */
test('wrapper fora da allowlist perde a tag mas preserva o texto', function (string $html, string $expected): void {
    expect($this->sanitizer->sanitize($html))->toBe($expected);
})->with([
    'div' => ['<div class="x"><p>dentro</p></div>', '<p>dentro</p>'],
    'span' => ['<p>a <span lang="pt">meio</span> b</p>', '<p>a meio b</p>'],
    'colagem do Word' => ['<div><p class="MsoNormal"><span lang="PT">Colado</span></p></div>', '<p>Colado</p>'],
    'b e i legados' => ['<p><b>negrito</b> e <i>itálico</i></p>', '<p>negrito e itálico</p>'],
    'tabela' => ['<table><tr><td>célula</td></tr></table>', 'célula'],
]);

test('preserva as tags que o editor do painel oferece', function (): void {
    $html = '<h2>Título</h2>'
        .'<h3>Subtítulo</h3>'
        .'<p>Parágrafo com <strong>negrito</strong> e <em>itálico</em>.</p>'
        .'<ul><li>sem ordem</li></ul>'
        .'<ol><li>com ordem</li></ol>'
        .'<p>Quebra<br />de linha</p>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

test('aceita os esquemas de link previstos e recusa os demais', function (string $href, bool $keeps): void {
    $output = $this->sanitizer->sanitize(sprintf('<p><a href="%s">x</a></p>', $href));

    expect(str_contains($output, 'href='))->toBe($keeps);
})->with([
    'https' => ['https://exemplo.invalid/a', true],
    'http' => ['http://exemplo.invalid/a', true],
    'mailto' => ['mailto:contato@exemplo.invalid', true],
    'tel' => ['tel:+554333222373', true],
    'caminho relativo' => ['/transparencia', true],
    'javascript' => ['javascript:alert(1)', false],
    'data' => ['data:text/html,x', false],
    'vbscript' => ['vbscript:msgbox(1)', false],
]);

test('link externo recebe rel de segurança e link interno não', function (): void {
    $externo = $this->sanitizer->sanitize('<p><a href="https://exemplo.invalid">fora</a></p>');
    $interno = $this->sanitizer->sanitize('<p><a href="/transparencia">dentro</a></p>');

    expect($externo)->toBe('<p><a href="https://exemplo.invalid" rel="noopener noreferrer">fora</a></p>')
        ->and($interno)->toBe('<p><a href="/transparencia">dentro</a></p>');
});

test('rel divergente em link externo é corrigido, não acumulado', function (): void {
    $output = $this->sanitizer->sanitize('<p><a href="https://exemplo.invalid" rel="nofollow">x</a></p>');

    expect($output)->toBe('<p><a href="https://exemplo.invalid" rel="noopener noreferrer">x</a></p>');
});

test('class em link aceita só os tokens de botão do site', function (string $class, string $expected): void {
    $output = $this->sanitizer->sanitize(sprintf('<p><a class="%s" href="/x">x</a></p>', $class));

    expect($output)->toBe($expected);
})->with([
    'botão primário' => ['btn btn--primary', '<p><a class="btn btn--primary" href="/x">x</a></p>'],
    'classe arbitrária some' => ['qualquer-coisa', '<p><a href="/x">x</a></p>'],
    'mistura mantém só o conhecido' => ['qualquer-coisa btn', '<p><a class="btn" href="/x">x</a></p>'],
]);

/**
 * Salvar duas vezes seguidas não pode mudar o conteúdo na segunda — se a saída do
 * sanitizador não fosse entrada estável dele mesmo, cada salvamento degradaria o texto um
 * pouco mais (o caso clássico é `&` virar `&amp;` a cada passagem).
 */
test('sanitizar duas vezes dá o mesmo resultado que sanitizar uma vez', function (string $html): void {
    $uma = $this->sanitizer->sanitize($html);

    expect($this->sanitizer->sanitize($uma))->toBe($uma);
})->with([
    'e comercial' => ['<p>Bolsas &amp; doações</p>'],
    'aspas' => ['<p>Ele disse "olá"</p>'],
    'acentos e travessão' => ['<p>Educação infantil — três pilares</p>'],
    'link externo' => ['<p><a href="https://exemplo.invalid/?a=1&amp;b=2">x</a></p>'],
    'link com query' => ['<p><a href="https://wa.me/55?text=Olá">x</a></p>'],
    'payload perigoso' => ['<p>a<script>alert(1)</script></p><div>b</div>'],
]);

/**
 * A garantia que importa para o conteúdo já publicado: passar pelo sanitizador não altera
 * nem um byte do que o seeder institucional traz hoje. Se alguém acrescentar uma estrutura
 * nova ao conteúdo sem estendê-la na allowlist, este teste acusa antes de a página ir ao ar
 * mutilada.
 */
test('conteúdo de todas as páginas do seeder atravessa o sanitizador sem nenhuma alteração', function (): void {
    $method = new ReflectionMethod(ContentPagesSeeder::class, 'pages');
    $method->setAccessible(true);

    /** @var list<array{slug: string, content: string}> $pages */
    $pages = $method->invoke(new ContentPagesSeeder);

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        expect($this->sanitizer->sanitize($page['content']))
            ->toBe($page['content'], "conteúdo alterado na página \"{$page['slug']}\"");
    }
});
