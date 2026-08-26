<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\EnrollmentInterest;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\VolunteerApplication;

/**
 * Prova explícita pedida na Etapa 1 da sessão 6: comunicacao não acessa nenhum dos seis
 * formulários pela API administrativa, e bazar só acessa pickup_requests — desta vez batendo
 * na API de verdade (índice, detalhe, status), não só na Policy diretamente (isso já foi
 * testado na sessão 5, ver tests/Feature/Forms/ContactMessageTest.php).
 */
dataset('formularios', [
    'enrollment-interests' => ['enrollment-interests', EnrollmentInterest::class],
    'program-applications' => ['program-applications', ProgramApplication::class],
    'pickup-requests' => ['pickup-requests', PickupRequest::class],
    'volunteer-applications' => ['volunteer-applications', VolunteerApplication::class],
    'partnership-inquiries' => ['partnership-inquiries', PartnershipInquiry::class],
    'contact-messages' => ['contact-messages', ContactMessage::class],
]);

test('comunicacao recebe 403 em índice, detalhe e status de todos os seis formulários', function (string $resource, string $modelClass): void {
    $user = userWithRole(Role::Comunicacao->value);
    $submission = $modelClass::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/{$resource}")->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/{$resource}/{$submission->uuid}")->assertForbidden();
    $this->actingAs($user)->patchJson("/api/v1/{$resource}/{$submission->uuid}/status", ['status' => 'done'])
        ->assertForbidden();
})->with('formularios');

test('bazar só acessa pickup-requests entre os seis formulários', function (string $resource, string $modelClass): void {
    $user = userWithRole(Role::Bazar->value);
    $submission = $modelClass::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/v1/{$resource}");

    if ($resource === 'pickup-requests') {
        $response->assertOk();
        $this->actingAs($user)->getJson("/api/v1/{$resource}/{$submission->uuid}")->assertOk();
    } else {
        $response->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/{$resource}/{$submission->uuid}")->assertForbidden();
    }
})->with('formularios');
