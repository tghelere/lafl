<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Http\Requests\Media\Concerns\ValidatesImageUpload;
use Illuminate\Foundation\Http\FormRequest;

final class ReplaceMediaFileRequest extends FormRequest
{
    use ValidatesImageUpload;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('media')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', ...$this->imageFileRules()]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->imageFileMessages();
    }

    public function uploadedPath(): string
    {
        return (string) $this->file('file')?->getRealPath();
    }
}
