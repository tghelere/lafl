<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Formato do pacote de conteúdo (`conteudo:exportar` / `conteudo:importar`).
 *
 * Um pacote é uma pasta:
 *
 *   manifest.json     versão do formato, origem, contagens e SHA-256 de tudo o mais
 *   pages.json        páginas do CMS (todos os status), com o histórico de slugs
 *   documents.json    documentos de transparência (publicados e não publicados)
 *   media.json        imagens da biblioteca que podem ir para o site (formato 2 em diante)
 *   files/<caminho>   PDFs e imagens (original e derivadas), no mesmo caminho relativo que
 *                     têm no disco "local"
 *
 * Só conteúdo institucional entra. Usuários, formulários recebidos, log de auditoria,
 * contadores de download e o que está na lixeira ficam de fora, de propósito — ver
 * docs/deploy.md §10. Imagem marcada como foto de criança ou adolescente atendido também fica
 * de fora: ela não pode ir para o site, e o pacote existe para levar o site de um ambiente a
 * outro (ver docs/decisoes/0024-biblioteca-de-midia.md).
 */
final class ContentPackage
{
    /**
     * Sobe quando o formato muda de um jeito que um importador antigo não entenderia. A 2
     * acrescentou `media.json`: um pacote 1 levaria as páginas sem as imagens que elas usam.
     */
    public const FORMAT_VERSION = 2;

    public const MANIFEST = 'manifest.json';

    public const PAGES = 'pages.json';

    public const DOCUMENTS = 'documents.json';

    public const MEDIA = 'media.json';

    public const FILES_DIR = 'files';

    /** Onde `SaveTransparencyDocument` grava; o importador não aceita caminho fora daqui. */
    public const DOCUMENT_FILE_PATTERN = '#^transparency-documents/[A-Za-z0-9._-]+$#';

    /**
     * Arquivo de imagem: `media/{uuid}/{versão}/original.{ext}` ou `.../{largura}.webp` — a
     * forma que App\Support\Media\MediaPaths produz. Grupo 1: uuid; grupo 2: versão.
     */
    public const MEDIA_FILE_PATTERN = '#^media/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/(\d+)/(?:original\.(?:jpg|png|webp)|\d{2,4}\.webp)$#';
}
