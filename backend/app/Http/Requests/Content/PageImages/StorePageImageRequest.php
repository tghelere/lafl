<?php

declare(strict_types=1);

namespace App\Http\Requests\Content\PageImages;

use App\Http\Requests\Media\Concerns\ValidatesImageUpload;
use App\Http\Requests\Media\Concerns\ValidatesMediaDetails;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Enviar uma foto direto para a galeria de uma página: as mesmas regras do envio pela
 * biblioteca (StoreMediaRequest), e as duas permissões — editar a página e enviar imagem.
 */
final class StorePageImageRequest extends FormRequest
{
    use ValidatesImageUpload, ValidatesMediaDetails;

    public function authorize(): bool
    {
        $page = $this->route('page');
        $user = $this->user();

        return $page instanceof Page
            && $user !== null
            && $user->can('update', $page)
            && $user->can('create', Media::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('alt'))) {
            $this->merge(['alt' => trim($this->input('alt'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', ...$this->imageFileRules()],
            ...$this->detailRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...$this->imageFileMessages(), ...$this->detailMessages()];
    }

    public function uploadedPath(): string
    {
        return (string) $this->file('file')?->getRealPath();
    }
}
