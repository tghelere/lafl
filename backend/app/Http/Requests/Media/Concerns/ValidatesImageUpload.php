<?php

declare(strict_types=1);

namespace App\Http\Requests\Media\Concerns;

trait ValidatesImageUpload
{
    /** Em KB, como a regra `max` do Laravel. Abaixo dos 20M do PHP e do Nginx (infra/). */
    public const MAX_KILOBYTES = 10240;

    /**
     * `mimetypes` confere o tipo pelo conteúdo (finfo), não pela extensão. É a primeira
     * barreira; a segunda é App\Services\Media\ImageProcessor, que decodifica de verdade.
     *
     * @return list<string>
     */
    protected function imageFileRules(): array
    {
        return ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_KILOBYTES];
    }

    /**
     * @return array<string, string>
     */
    protected function imageFileMessages(): array
    {
        return [
            'file.required' => 'Selecione a imagem.',
            'file.file' => 'Selecione a imagem.',
            'file.uploaded' => 'O envio da imagem falhou. Confira se ela tem menos de 10 MB e tente de novo.',
            'file.mimetypes' => 'A imagem precisa ser JPEG, PNG ou WebP.',
            'file.max' => 'A imagem não pode passar de 10 MB.',
        ];
    }
}
