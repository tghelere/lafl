<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Http\Requests\Media\Concerns\ValidatesMediaDetails;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMediaRequest extends FormRequest
{
    use ValidatesMediaDetails;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('media')) ?? false;
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
        return $this->detailRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->detailMessages();
    }
}
