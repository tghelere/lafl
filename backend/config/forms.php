<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use App\Models\EnrollmentInterest;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\VolunteerApplication;

return [

    /*
    |--------------------------------------------------------------------------
    | Modelos de formulário recebido
    |--------------------------------------------------------------------------
    |
    | Classes de model que usam App\Models\Concerns\IsFormSubmission — App\Jobs\
    | PurgeExpiredFormSubmissions lê esta lista para saber o que expurgar por expires_at
    | (ver docs/estrutura-site.md §2.2). Cada entidade nova se registra aqui ao ser criada.
    |
    */

    'submission_models' => [
        EnrollmentInterest::class,
        ProgramApplication::class,
        PickupRequest::class,
        VolunteerApplication::class,
        PartnershipInquiry::class,
        ContactMessage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Destinatário de notificação por tipo de formulário
    |--------------------------------------------------------------------------
    |
    | Endereços reais são [LACUNA] (ver docs/contexto.md) — os valores abaixo são
    | placeholders óbvios em domínio reservado (.invalid, RFC 2606), nunca endereço real.
    | Ver docs/roadmap.md.
    |
    */

    'notification_recipients' => [
        'enrollment_interest' => env('FORM_RECIPIENT_ENROLLMENT_INTEREST', 'secretaria-cei@lar-analia-franco.invalid'),
        'program_application' => env('FORM_RECIPIENT_PROGRAM_APPLICATION', 'contraturno@lar-analia-franco.invalid'),
        'pickup_request' => env('FORM_RECIPIENT_PICKUP_REQUEST', 'bazar@lar-analia-franco.invalid'),
        'volunteer_application' => env('FORM_RECIPIENT_VOLUNTEER_APPLICATION', 'voluntariado@lar-analia-franco.invalid'),
        'partnership_inquiry' => env('FORM_RECIPIENT_PARTNERSHIP_INQUIRY', 'parcerias@lar-analia-franco.invalid'),
        'contact_message' => env('FORM_RECIPIENT_CONTACT_MESSAGE', 'contato@lar-analia-franco.invalid'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Painel administrativo
    |--------------------------------------------------------------------------
    |
    | Base para o link no e-mail de notificação. A tela em si não existe ainda (ver
    | docs/roadmap.md) — o link fica quebrado até lá, de propósito: o e-mail já nasce pronto
    | para quando a tela existir, em vez de precisar editar o texto depois.
    |
    */

    'admin_base_url' => env('ADMIN_BASE_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Versão do termo de consentimento
    |--------------------------------------------------------------------------
    |
    | Gravada em consent_terms_version a cada envio (ver docs/protecao-de-dados.md,
    | "Consentimento": termos versionados). A página /politica-de-privacidade ainda não existe
    | (ver docs/roadmap.md) — quando existir, esta versão precisa mudar junto de qualquer
    | alteração relevante no texto da política.
    |
    */

    'consent_terms_version' => env('FORM_CONSENT_TERMS_VERSION', '2026-08-25'),

];
