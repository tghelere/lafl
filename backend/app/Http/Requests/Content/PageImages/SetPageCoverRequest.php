<?php

declare(strict_types=1);

namespace App\Http\Requests\Content\PageImages;

use App\Models\Media;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Trocar a capa por uma imagem que já está na biblioteca. A imagem vem pelo uuid, nunca pelo
 * id sequencial (CLAUDE.md, regra 4).
 */
final class SetPageCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page && ($this->user()?->can('update', $page) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['media' => ['required', 'uuid', Rule::exists('media', 'uuid')]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media.required' => 'Escolha a imagem da capa.',
            'media.uuid' => 'Escolha a imagem da capa.',
            'media.exists' => 'Esta imagem não existe mais na biblioteca.',
        ];
    }

    public function media(): Media
    {
        return Media::query()->where('uuid', $this->string('media')->value())->firstOrFail();
    }
}
