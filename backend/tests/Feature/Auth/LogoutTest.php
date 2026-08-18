<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('logout invalida a sessão do usuário autenticado', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    // Guard explícito: o middleware auth:sanctum troca o guard "padrão" do container para
    // 'sanctum' durante a própria requisição de logout; assertGuest() sem argumento
    // resolveria esse guard (que cacheia o usuário separadamente), não o 'web' que a Action
    // efetivamente desloga. Isso é um artefato do container reaproveitado entre requisições
    // simuladas no teste — não acontece em produção, onde cada requisição é isolada.
    $this->assertGuest('web');
});

test('logout registra evento de auditoria', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(Activity::where('event', 'logout')->where('causer_id', $user->id)->exists())->toBeTrue();
});

test('logout sem sessão autenticada retorna 401', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
