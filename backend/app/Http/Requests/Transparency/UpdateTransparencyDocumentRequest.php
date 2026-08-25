<?php

declare(strict_types=1);

namespace App\Http\Requests\Transparency;

use App\Actions\Transparency\Data\TransparencyDocumentData;
use App\Enums\TransparencyDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTransparencyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('transparencyDocument')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'type' => ['required', Rule::enum(TransparencyDocumentType::class)],
            // Trocar o arquivo é opcional numa atualização — só metadado pode mudar.
            'file' => ['sometimes', 'file', 'mimes:pdf', 'max:20480'],
            'published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título do documento.',
            'year.required' => 'Informe o ano do documento.',
            'type.required' => 'Informe o tipo do documento.',
            'file.mimes' => 'O documento precisa ser um PDF.',
            'file.max' => 'O documento não pode passar de 20 MB.',
        ];
    }

    public function toDto(): TransparencyDocumentData
    {
        return new TransparencyDocumentData(
            title: $this->string('title')->value(),
            year: $this->integer('year'),
            type: TransparencyDocumentType::from($this->string('type')->value()),
            file: $this->file('file'),
            publish: $this->boolean('published'),
        );
    }
}
