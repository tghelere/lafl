<?php

declare(strict_types=1);

use App\Jobs\PurgeExpiredFormSubmissions;

test('job roda sem erro quando nenhuma entidade está registrada', function (): void {
    config(['forms.submission_models' => []]);

    (new PurgeExpiredFormSubmissions)->handle();
})->throwsNoExceptions();
