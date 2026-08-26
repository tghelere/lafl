<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\Public\EnrollmentInterestController;
use App\Http\Controllers\Api\V1\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\V1\Public\ProgramApplicationController;
use App\Http\Controllers\Api\V1\Public\TransparencyDocumentController as PublicTransparencyDocumentController;
use App\Http\Controllers\Api\V1\TransparencyDocumentController;
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

    // Seis formulários públicos (ver docs/estrutura-site.md §3.2). Rate limit por IP e
    // honeypot em vez de CAPTCHA de terceiro (ver docs/roadmap.md e App\Support\Honeypot).
    Route::middleware('throttle:public-forms')->group(function (): void {
        Route::post('/enrollment-interests', [EnrollmentInterestController::class, 'store'])
            ->name('enrollment-interests.store');
        Route::post('/program-applications', [ProgramApplicationController::class, 'store'])
            ->name('program-applications.store');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('pages', PageController::class)->parameters(['pages' => 'page']);
    Route::apiResource('transparency-documents', TransparencyDocumentController::class)
        ->parameters(['transparency-documents' => 'transparencyDocument']);
});
