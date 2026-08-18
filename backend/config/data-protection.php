<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Chave de criptografia de campo
    |--------------------------------------------------------------------------
    |
    | Usada por App\Casts\FieldEncrypted para cifrar dado pessoal de assistido/responsável.
    | Sempre separada do APP_KEY — ver docs/protecao-de-dados.md.
    |
    */

    'field_encryption_key' => env('FIELD_ENCRYPTION_KEY'),

    'field_encryption_previous_keys' => array_filter(
        explode(',', (string) env('FIELD_ENCRYPTION_PREVIOUS_KEYS', ''))
    ),

    /*
    |--------------------------------------------------------------------------
    | Chave dos blind indexes
    |--------------------------------------------------------------------------
    |
    | Usada por App\Services\BlindIndexService para o HMAC-SHA256 de busca/checagem de
    | duplicidade sem descriptografar. Separada de APP_KEY e de field_encryption_key.
    |
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

    'blind_index_previous_keys' => array_filter(
        explode(',', (string) env('BLIND_INDEX_PREVIOUS_KEYS', ''))
    ),

];
