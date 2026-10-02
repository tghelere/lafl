<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Actions\Partners\Data\PartnerData;
use App\Http\Requests\Media\Concerns\ValidatesImageUpload;
use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    use ValidatesImageUpload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Partner::class) ?? false;
    }

    /**
     * Nome só com espaço vira vazio antes da validação e cai no `required`; link vazio vira
     * ausente (o campo é opcional).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'url' => is_string($this->input('url')) && trim($this->input('url')) !== '' ? trim($this->input('url')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'logo' => [$this->logoRequired() ? 'required' : 'sometimes', ...$this->imageFileRules()],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** A criação exige a logo; a atualização só a troca se vier outra. */
    protected function logoRequired(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do parceiro.',
            'name.max' => 'O nome pode ter até 150 caracteres.',
            'logo.required' => 'Envie a logo do parceiro.',
            'logo.file' => 'Envie a logo do parceiro.',
            'logo.uploaded' => 'O envio da logo falhou. Confira se ela tem menos de 10 MB e tente de novo.',
            'logo.mimetypes' => 'A logo precisa ser PNG, JPG ou WebP.',
            'logo.max' => 'A logo não pode passar de 10 MB.',
            'url.url' => 'Informe um endereço válido, começando com http:// ou https://.',
            'url.max' => 'O endereço pode ter até 2048 caracteres.',
            'position.integer' => 'A ordem precisa ser um número inteiro.',
        ];
    }

    public function toDto(): PartnerData
    {
        return new PartnerData(
            name: $this->string('name')->value(),
            url: $this->filled('url') ? $this->string('url')->value() : null,
            logoPath: $this->file('logo') !== null ? (string) $this->file('logo')->getRealPath() : null,
            position: $this->filled('position') ? $this->integer('position') : null,
            isActive: $this->has('is_active') ? $this->boolean('is_active') : true,
        );
    }
}
