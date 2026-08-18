<?php

declare(strict_types=1);

namespace App\Casts;

use App\Services\BlindIndexService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Cifra de campo pessoal (AES-256-GCM) com chave dedicada, separada do APP_KEY — ver
 * docs/protecao-de-dados.md. O cast `encrypted` nativo do Eloquent sempre usa o encrypter
 * padrão da aplicação; este cast instancia o seu próprio, com FIELD_ENCRYPTION_KEY.
 *
 * A coluna correspondente deve ser sempre `text`.
 *
 * @implements CastsAttributes<string, string>
 */
final class FieldEncrypted implements CastsAttributes
{
    private const CIPHER = 'aes-256-gcm';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->encrypters() as $encrypter) {
            try {
                return $encrypter->decryptString($value);
            } catch (DecryptException) {
                continue;
            }
        }

        throw new DecryptException("Não foi possível decifrar o campo [{$key}] com nenhuma chave configurada.");
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->primaryEncrypter()->encryptString((string) $value);
    }

    private function primaryEncrypter(): Encrypter
    {
        $key = config('data-protection.field_encryption_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'FIELD_ENCRYPTION_KEY não configurada. Ver .env.example e docs/protecao-de-dados.md.'
            );
        }

        return new Encrypter(BlindIndexService::decodeKey($key), self::CIPHER);
    }

    /**
     * Chave ativa primeiro, depois as chaves antigas — para permitir ler dados cifrados com
     * uma chave anterior durante a rotação (comando de re-encriptação em lote é pendência
     * futura, ver docs/protecao-de-dados.md).
     *
     * @return list<Encrypter>
     */
    private function encrypters(): array
    {
        $previous = config('data-protection.field_encryption_previous_keys', []);
        $previous = is_array($previous) ? $previous : [];

        $encrypters = [$this->primaryEncrypter()];

        foreach ($previous as $key) {
            if (is_string($key) && $key !== '') {
                $encrypters[] = new Encrypter(BlindIndexService::decodeKey($key), self::CIPHER);
            }
        }

        return $encrypters;
    }
}
