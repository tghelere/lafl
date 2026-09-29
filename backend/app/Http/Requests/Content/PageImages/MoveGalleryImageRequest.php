<?php

declare(strict_types=1);

namespace App\Http\Requests\Content\PageImages;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

final class MoveGalleryImageRequest extends FormRequest
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
        return ['direction' => ['required', 'in:up,down']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'direction.required' => 'Diga se a imagem sobe ou desce.',
            'direction.in' => 'Diga se a imagem sobe ou desce.',
        ];
    }

    public function direction(): string
    {
        return $this->string('direction')->value();
    }
}
