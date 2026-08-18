<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Toda a API é versionada. Nenhuma rota deve ser adicionada fora de v1 sem uma decisão
// explícita de versionamento — ver docs/arquitetura.md, seção "Contrato da API".
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
