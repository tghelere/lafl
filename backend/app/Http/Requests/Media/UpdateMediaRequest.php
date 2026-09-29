<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Http\Requests\Media\Concerns\ValidatesMediaDetails;
use App\Models\Media;
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
        $rules = $this->detailRules();

        // Marcar como foto de assistido tira a imagem do site e não se desfaz (UpdateMediaDetails).
        // Um ato assim não pode acontecer por um valor que chegou trocado no corpo da requisição:
        // a API exige, além da declaração, a confirmação explícita de quem marca. A tela pede
        // essa confirmação digitada (MediaDetailView.vue); outro cliente precisaria mandá-la
        // também.
        if ($this->isMarking()) {
            $rules['confirm_marking'] = ['accepted'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->detailMessages(),
            'confirm_marking.accepted' => 'Confirme que a marcação tira a imagem do site em todas as páginas e não pode ser desfeita.',
        ];
    }

    private function isMarking(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media && ! $media->depicts_assisted_minor && $this->boolean('depicts_assisted_minor');
    }
}
