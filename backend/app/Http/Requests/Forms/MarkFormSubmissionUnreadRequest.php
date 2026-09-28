<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Http\Requests\Forms\Concerns\ResolvesBoundSubmission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Compartilhado pelas cinco rotas `DELETE /api/v1/{recurso}/{uuid}/read`.
 *
 * Sem nenhuma regra de validação — a requisição não tem corpo: o alvo é a própria URL e quem
 * age é a sessão. Existe pela autorização, que é a razão de ser desta classe: marcar como não
 * lido é escrita no registro, e quem decide é a Policy do recurso (`update`), a mesma da
 * mudança de status.
 */
final class MarkFormSubmissionUnreadRequest extends FormRequest
{
    use ResolvesBoundSubmission;

    public function authorize(): bool
    {
        $submission = $this->boundSubmission();

        return $submission !== null && ($this->user()?->can('update', $submission) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
