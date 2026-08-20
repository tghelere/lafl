<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use App\Actions\Content\Data\PageData;
use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Page::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)?$/',
                Rule::unique('pages', 'slug'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(PageStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'Informe o slug da página.',
            'slug.regex' => 'O slug deve usar apenas letras minúsculas, números e hífen, com no máximo uma barra separando dois níveis (ex.: "quem-somos/nossa-historia").',
            'slug.unique' => 'Já existe uma página com este slug.',
            'title.required' => 'Informe o título da página.',
            'content.required' => 'Informe o conteúdo da página.',
            'status.required' => 'Informe o status de publicação.',
        ];
    }

    public function toDto(): PageData
    {
        return new PageData(
            slug: $this->string('slug')->value(),
            title: $this->string('title')->value(),
            content: $this->string('content')->value(),
            metaTitle: $this->filled('meta_title') ? $this->string('meta_title')->value() : null,
            metaDescription: $this->filled('meta_description') ? $this->string('meta_description')->value() : null,
            status: PageStatus::from($this->string('status')->value()),
        );
    }
}
