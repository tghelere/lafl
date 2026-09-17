<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Marcos institucionais
    |--------------------------------------------------------------------------
    |
    | Fonte única das datas de que o site deriva idade — nenhuma delas é digitada em texto
    | de página, em .vue ou em seeder (ver App\Services\InstitutionalFacts e
    | docs/tarefas/02-numeros-calculados.md). Todas confirmadas pelo cliente em 17/09/2026,
    | ver docs/contexto.md.
    |
    | O formato do valor declara a precisão, e a precisão muda a conta:
    |
    | - "AAAA-MM-DD" — data completa: o aniversário é respeitado (em 11/07/2026 a associação
    |   ainda tem 72 anos, não 73).
    | - "AAAA" — só o ano: a idade sai por diferença de ano, porque o dia não é conhecido.
    |   Confirmando o dia, basta trocar o valor por uma data completa; nada mais muda.
    |
    */

    'milestones' => [
        'association_founded' => '1953-07-12',
        'headquarters_construction_started' => '1957-04-18',
        'headquarters_inaugurated' => '1963-11-15',
        'bazaar_opened' => '1968',
        'cei_created' => '2002',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fuso horário institucional
    |--------------------------------------------------------------------------
    |
    | A aplicação grava timestamp em UTC (config/app.php), que é o certo. Idade em anos, não:
    | "hoje" é o dia em Londrina, e das 21h à meia-noite de Londrina o UTC já está no dia
    | seguinte — sem isto, a associação faria aniversário no site três horas antes da hora.
    |
    */

    'timezone' => 'America/Sao_Paulo',

];
