<?php

declare(strict_types=1);

namespace App\Http\Requests\Content\PageImages;

use App\Enums\PageImageRole;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RemovePageImageRequest extends FormRequest
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
        return ['role' => ['required', Rule::enum(PageImageRole::class)]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['role.required' => 'Diga se é a capa ou a galeria.', 'role.enum' => 'Diga se é a capa ou a galeria.'];
    }

    public function role(): PageImageRole
    {
        return PageImageRole::from($this->string('role')->value());
    }
}
