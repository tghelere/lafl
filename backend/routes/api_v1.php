<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PartnershipInquiryController;
use App\Http\Controllers\Api\V1\PickupRequestController;
use App\Http\Controllers\Api\V1\ProgramApplicationController;
use App\Http\Controllers\Api\V1\Public\ContactMessageController as PublicContactMessageController;
use App\Http\Controllers\Api\V1\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\V1\Public\PartnershipInquiryController as PublicPartnershipInquiryController;
use App\Http\Controllers\Api\V1\Public\PickupRequestController as PublicPickupRequestController;
use App\Http\Controllers\Api\V1\Public\ProgramApplicationController as PublicProgramApplicationController;
use App\Http\Controllers\Api\V1\Public\TransparencyDocumentController as PublicTransparencyDocumentController;
use App\Http\Controllers\Api\V1\Public\VolunteerApplicationController as PublicVolunteerApplicationController;
use App\Http\Controllers\Api\V1\TransparencyDocumentController;
use App\Http\Controllers\Api\V1\VolunteerApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    });
});

// Sem autenticação, só conteúdo publicado (ver docs/estrutura-site.md, Parte 3). `{slug}`
// aceita barra porque pages é plana com slug de até dois níveis (ex.:
// "quem-somos/nossa-historia") — ver decisão de formato de slug em docs/roadmap.md.
Route::prefix('public')->name('public.')->group(function (): void {
    Route::get('/pages/{slug}', [PublicPageController::class, 'show'])
        ->where('slug', '.*')
        ->name('pages.show');

    Route::get('/transparency-documents', [PublicTransparencyDocumentController::class, 'index'])
        ->name('transparency-documents.index');
    Route::get('/transparency-documents/{uuid}/download', [PublicTransparencyDocumentController::class, 'download'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('transparency-documents.download');

    // Cinco formulários públicos (ver docs/estrutura-site.md §3.2). Rate limit por IP e
    // honeypot em vez de CAPTCHA de terceiro (ver docs/roadmap.md e App\Support\Honeypot).
    Route::middleware('throttle:public-forms')->group(function (): void {
        Route::post('/program-applications', [PublicProgramApplicationController::class, 'store'])
            ->name('program-applications.store');
        Route::post('/pickup-requests', [PublicPickupRequestController::class, 'store'])
            ->name('pickup-requests.store');
        Route::post('/volunteer-applications', [PublicVolunteerApplicationController::class, 'store'])
            ->name('volunteer-applications.store');
        Route::post('/partnership-inquiries', [PublicPartnershipInquiryController::class, 'store'])
            ->name('partnership-inquiries.store');
        Route::post('/contact-messages', [PublicContactMessageController::class, 'store'])
            ->name('contact-messages.store');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('pages', PageController::class)->parameters(['pages' => 'page']);
    Route::apiResource('transparency-documents', TransparencyDocumentController::class)
        ->parameters(['transparency-documents' => 'transparencyDocument']);

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

    // Leitura administrativa dos cinco formulários recebidos (ver docs/estrutura-site.md
    // §4.5): listagem, detalhe e mudança de status com anotação — nada de criação/exclusão
    // aqui, os registros só nascem pelo formulário público (ver Parte 3 daquele documento).
    Route::apiResource('program-applications', ProgramApplicationController::class)
        ->only(['index', 'show'])
        ->parameters(['program-applications' => 'programApplication']);
    Route::patch('/program-applications/{programApplication}/status', [ProgramApplicationController::class, 'updateStatus'])
        ->name('program-applications.status');

    Route::apiResource('pickup-requests', PickupRequestController::class)
        ->only(['index', 'show'])
        ->parameters(['pickup-requests' => 'pickupRequest']);
    Route::patch('/pickup-requests/{pickupRequest}/status', [PickupRequestController::class, 'updateStatus'])
        ->name('pickup-requests.status');

    Route::apiResource('volunteer-applications', VolunteerApplicationController::class)
        ->only(['index', 'show'])
        ->parameters(['volunteer-applications' => 'volunteerApplication']);
    Route::patch('/volunteer-applications/{volunteerApplication}/status', [VolunteerApplicationController::class, 'updateStatus'])
        ->name('volunteer-applications.status');

    Route::apiResource('partnership-inquiries', PartnershipInquiryController::class)
        ->only(['index', 'show'])
        ->parameters(['partnership-inquiries' => 'partnershipInquiry']);
    Route::patch('/partnership-inquiries/{partnershipInquiry}/status', [PartnershipInquiryController::class, 'updateStatus'])
        ->name('partnership-inquiries.status');

    Route::apiResource('contact-messages', ContactMessageController::class)
        ->only(['index', 'show'])
        ->parameters(['contact-messages' => 'contactMessage']);
    Route::patch('/contact-messages/{contactMessage}/status', [ContactMessageController::class, 'updateStatus'])
        ->name('contact-messages.status');
});
