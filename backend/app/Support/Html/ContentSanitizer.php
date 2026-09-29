<?php

declare(strict_types=1);

namespace App\Support\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Allowlist do HTML que pode ser salvo em `pages.content` (ver
 * docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md). O site
 * renderiza esse campo com `v-html`, então o que passa daqui é executado no navegador de
 * quem visita — a sanitização é obrigatória e acontece no backend, nunca só no editor.
 *
 * O conjunto de tags é o que o editor do painel oferece (parágrafo, h2, h3, negrito,
 * itálico, link, lista com e sem ordem, imagem com legenda) mais `<br>` e `class` em `<a>`,
 * que já existem no conteúdo institucional publicado e seriam perdidos sem isto.
 *
 * Imagem: `<figure>`, `<img src alt>` e `<figcaption>`, com `src` restrito à forma canônica da
 * biblioteca (ver MediaSourceAttributeSanitizer). Largura, `srcset` e dimensões NÃO são
 * gravados — quem os põe é a leitura pública (App\Actions\Media\ExpandContentImages), com as
 * derivadas que existem no momento, para que a substituição do arquivo não exija editar
 * página nenhuma. Que a imagem exista e possa ir ao site é conferido por
 * App\Actions\Content\AssertContentImagesArePublishable, que consulta o banco — este filtro
 * é só forma.
 */
final class ContentSanitizer
{
    /**
     * Tags fora da allowlist cujo TEXTO deve sobreviver: o wrapper some, o conteúdo fica.
     * Cobre principalmente colagem vinda de editor externo (Word, Google Docs), que embrulha
     * tudo em div/span. Sem isto o comportamento padrão do componente é derrubar o elemento
     * junto com os filhos — o texto sumiria sem aviso ao salvar.
     *
     * `script`, `style`, `iframe`, `object` e afins ficam de fora desta lista de propósito:
     * para eles o padrão (derrubar com o conteúdo) é o certo, senão o corpo do script viraria
     * texto visível na página.
     *
     * @var list<string>
     */
    private const BLOCKED_ELEMENTS = [
        'div', 'span', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav',
        'h1', 'h4', 'h5', 'h6',
        'b', 'i', 'u', 's', 'small', 'sub', 'sup', 'mark', 'font', 'center',
        'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption',
        'dl', 'dt', 'dd',
    ];

    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('br')
            ->allowElement('a', ['href', 'target', 'rel', 'class'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('img', ['src', 'alt'])
            // Relativo é o ÚNICO formato de imagem aceito (`/midia/{uuid}`); esquema nenhum —
            // nem http(s), nem data:. O allowlist fino de caminho é o sanitizador abaixo.
            ->allowMediaSchemes([])
            ->allowRelativeMedias()
            // Caminho relativo cobre link interno ("/transparencia"), que é a maioria do
            // conteúdo atual; mailto/tel são para contato institucional. `javascript:` e
            // `data:` ficam de fora — é o que impede href executável.
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->withAttributeSanitizer(new LinkClassAttributeSanitizer)
            ->withAttributeSanitizer(new MediaSourceAttributeSanitizer);

        foreach (self::BLOCKED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->forceRelOnExternalLinks($this->dropImagesWithoutSource($this->sanitizer->sanitize($html)));
    }

    /**
     * `<img>` cujo `src` foi recusado sai inteiro: sem `src` ele é um quadro vazio no site. A
     * legenda da `<figure>` fica, como texto — é conteúdo que alguém escreveu.
     *
     * Mesma garantia de forceRelOnExternalLinks: roda sobre a saída já sanitizada, em que `>`
     * não aparece dentro de valor de atributo.
     */
    private function dropImagesWithoutSource(string $html): string
    {
        return (string) preg_replace('/<img\b(?![^>]*\ssrc=")[^>]*>/i', '', $html);
    }

    /**
     * `HtmlSanitizerConfig::forceAttribute()` aplicaria `rel` em todo link, inclusive nos
     * internos, o que sujaria o conteúdo inteiro sem ganho nenhum — `rel` só importa quando
     * o destino é outro site. Daí o passo próprio aqui.
     *
     * A expressão regular roda sobre a saída JÁ sanitizada, não sobre a entrada: o
     * componente escapa `<` e `>` dentro de valor de atributo (ver
     * Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer), então `[^>]*` não tem
     * como atravessar o fim da tag e casar coisa demais.
     */
    private function forceRelOnExternalLinks(string $html): string
    {
        return (string) preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[1];

                if (! preg_match('/\bhref="(https?):/i', $attributes)) {
                    return $matches[0];
                }

                $attributes = (string) preg_replace('/\s+rel="[^"]*"/i', '', $attributes);

                return '<a'.$attributes.' rel="noopener noreferrer">';
            },
            $html,
        );
    }
}
