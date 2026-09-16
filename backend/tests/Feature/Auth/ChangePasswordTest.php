<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

test('troca de senha exige a senha atual correta', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $response = $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-errada',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('current_password');

    expect(Hash::check('senha-atual', $user->fresh()->password))->toBeTrue();
});

test('troca de senha com dados válidos atualiza a senha do usuário', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $response = $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ]);

    $response->assertNoContent();

    expect(Hash::check('nova-senha-forte-123', $user->fresh()->password))->toBeTrue();
});

test('troca de senha exige confirmação igual à nova senha', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'outra-coisa',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

test('troca de senha sem sessão autenticada retorna 401', function (): void {
    $this->putJson('/api/v1/auth/password', [
        'current_password' => 'x',
        'password' => 'y',
        'password_confirmation' => 'y',
    ])->assertUnauthorized();
});

test('troca de senha registra evento de auditoria', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ])->assertNoContent();

    expect(Activity::where('event', 'password_changed')->where('causer_id', $user->id)->exists())->toBeTrue();
});

/**
 * Sessão real via cookie (não actingAs, que injeta o usuário direto no guard sem passar pela
 * sessão) — precisa de duas "janelas" independentes do mesmo usuário para provar que trocar a
 * senha não derruba quem trocou, mas derruba a outra.
 *
 * Duas armadilhas do client de teste do Laravel encontradas escrevendo isto:
 * - `getJson`/`postJson`/`putJson` nunca mandam cookie nenhum, mesmo depois de `withCookie()`
 *   — só mandam com `withCredentials()` ligado antes (`prepareCookiesForJsonRequest()` só
 *   inclui cookies quando esse flag está ativo; sem ele, toda chamada *Json era, na prática,
 *   uma sessão nova, e a "sessão" real vindo era só o estado do guard preso no container
 *   entre chamadas — não uma sessão de verdade).
 * - `withCookie()` já criptografa o valor sozinho (mesmo formato de um Set-Cookie de
 *   verdade) — precisa do valor **decifrado** do cookie (`getCookie($nome, true)`), não do
 *   valor cru da resposta, senão fica cifrado duas vezes e a sessão não é encontrada.
 *
 * Sanctum também só ativa sessão/cookie (EnsureFrontendRequestsAreStateful) quando a
 * requisição tem Origin ou Referer batendo com um domínio de SANCTUM_STATEFUL_DOMAINS.
 */
test('trocar a própria senha mantém a sessão que trocou e derruba as demais sessões abertas', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);
    $cookieName = config('session.cookie');

    $this->withCredentials()->withHeader('Origin', 'http://localhost:5173');

    $login1 = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'senha-atual',
    ])->assertOk();
    $session1 = (string) $login1->getCookie($cookieName, true)->getValue();

    // Uma requisição autenticada na janela 1 antes da troca, para o AuthenticateSession do
    // Sanctum gravar o hash da senha atual nesta sessão especificamente.
    $this->withCookie($cookieName, $session1)->getJson('/api/v1/auth/user')->assertOk();

    $login2 = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'senha-atual',
    ])->assertOk();
    $session2 = (string) $login2->getCookie($cookieName, true)->getValue();

    expect($session2)->not->toBe($session1);

    $this->withCookie($cookieName, $session2)->getJson('/api/v1/auth/user')->assertOk();

    // Troca a senha usando a janela 1.
    $this->withCookie($cookieName, $session1)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ])->assertNoContent();

    // A janela que trocou continua valendo.
    $this->withCookie($cookieName, $session1)->getJson('/api/v1/auth/user')->assertOk();

    // A outra janela do mesmo usuário caiu.
    $this->withCookie($cookieName, $session2)->getJson('/api/v1/auth/user')->assertUnauthorized();
});
