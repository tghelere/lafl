<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\Public\PageController as PublicPageController;
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
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('pages', PageController::class)->parameters(['pages' => 'page']);
});
