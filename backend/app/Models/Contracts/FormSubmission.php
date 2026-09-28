<?php

declare(strict_types=1);

namespace App\Models\Contracts;

/**
 * Marca as cinco entidades de formulário recebido (ver docs/dominio.md). Implementada por
 * App\Models\Concerns\IsFormSubmission — um model que usa a trait ganha o método de graça.
 *
 * Existe por uma necessidade concreta: a tela de Auditoria lê `activity_log`, cujo `subject` é uma
 * relação polimórfica e chega ao código como `Model` genérico. Sem um tipo que diga "isto é um
 * formulário recebido, e formulário recebido tem identificador público", a única forma de ler o
 * uuid ali seria `getAttribute('uuid')` com cast de string — acesso destipado exatamente no lugar
 * onde a regra 4 do CLAUDE.md (uuid, nunca id sequencial) precisa valer.
 */
interface FormSubmission
{
    /**
     * O identificador que entra em rota e payload. Nunca a chave sequencial.
     */
    public function publicId(): string;
}
