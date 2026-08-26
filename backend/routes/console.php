<?php

declare(strict_types=1);

use App\Jobs\PurgeExpiredFormSubmissions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Descarte automatizado por retenção (ver docs/protecao-de-dados.md e
// docs/estrutura-site.md §2.2). Depende de `php artisan schedule:work` (ou cron real em
// produção) estar rodando — não dispara sozinho.
Schedule::job(new PurgeExpiredFormSubmissions)->daily();
