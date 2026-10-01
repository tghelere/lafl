<?php

declare(strict_types=1);

namespace App\Actions\Transparency\Import;

use RuntimeException;

/**
 * Lote recusado inteiro, antes (ou durante) a gravação — `problems` é o que o comando mostra.
 */
final class ImportTransparencyBatchException extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode("\n", $problems));
    }
}
