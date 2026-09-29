<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Http\Requests\Media\Concerns\ValidatesImageUpload;
use App\Http\Requests\Media\Concerns\ValidatesMediaDetails;
use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMediaRequest extends FormRequest
{
    use ValidatesImageUpload, ValidatesMediaDetails;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /**
     * `alt` só com espaço vira vazio antes da validação, e cai no `required`.
     */
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
