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
 *   files/<file_path> os PDFs, no mesmo caminho relativo que têm no disco "local"
 *
 * Só conteúdo institucional entra. Usuários, formulários recebidos, log de auditoria,
 * contadores de download e o que está na lixeira ficam de fora, de propósito — ver
 * docs/deploy.md §10.
 */
final class ContentPackage
{
    /** Sobe quando o formato muda de um jeito que um importador antigo não entenderia. */
    public const FORMAT_VERSION = 1;

    public const MANIFEST = 'manifest.json';

    public const PAGES = 'pages.json';

    public const DOCUMENTS = 'documents.json';

    public const FILES_DIR = 'files';

    /** Onde `SaveTransparencyDocument` grava; o importador não aceita caminho fora daqui. */
    public const DOCUMENT_FILE_PATTERN = '#^transparency-documents/[A-Za-z0-9._-]+$#';
}
