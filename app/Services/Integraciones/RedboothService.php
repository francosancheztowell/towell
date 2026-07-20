<?php

declare(strict_types=1);

namespace App\Services\Integraciones;

use App\Models\Integraciones\RedboothCredential;
use App\Models\Sistema\Usuario;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class RedboothService
{
    private const TOKEN_EXPIRY_MARGIN_SECONDS = 60;

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        return sprintf(
            '%s?%s',
            config('redbooth.authorize_url'),
            http_build_query([
                'client_id' => config('redbooth.client_id'),
                'redirect_uri' => config('redbooth.redirect_uri'),
                'response_type' => 'code',
                'state' => $state,
            ]),
        );
    }

    public function exchangeAuthorizationCode(Usuario $usuario, string $code): RedboothCredential
    {
        $this->assertConfigured();

        $tokens = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post((string) config('redbooth.token_url'), [
                'client_id' => config('redbooth.client_id'),
                'client_secret' => config('redbooth.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('redbooth.redirect_uri'),
            ])
            ->throw()
            ->json();

        return $this->storeTokens($usuario, $this->validateTokenPayload($tokens));
    }

    /** @return array<string, mixed> */
    public function me(Usuario $usuario): array
    {
        return $this->request($usuario)->get('/me')->throw()->json();
    }

    /**
     * @param  array<string, int|string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function activities(Usuario $usuario, array $filters): array
    {
        return $this->request($usuario)->get('/activities', $filters)->throw()->json();
    }

    /**
     * @param  array<string, bool|int|string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function tasks(Usuario $usuario, array $filters): array
    {
        return $this->request($usuario)->get('/tasks', $filters)->throw()->json();
    }

    /**
     * @param  array<string, bool|int|string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function users(Usuario $usuario, array $filters): array
    {
        return $this->request($usuario)->get('/users', $filters)->throw()->json();
    }

    /**
     * @param  array<string, int|string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function comments(Usuario $usuario, array $filters): array
    {
        return $this->request($usuario)->get('/comments', $filters)->throw()->json();
    }

    /**
     * @param  array<string, bool|int|string>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function files(Usuario $usuario, array $filters): array
    {
        return $this->request($usuario)->get('/files', $filters)->throw()->json();
    }

    public function fileDownloadUrl(Usuario $usuario, int $fileId): string
    {
        $response = $this->request($usuario)
            ->withoutRedirecting()
            ->get("/files/{$fileId}/download")
            ->throw();
        $location = trim((string) $response->header('Location'));

        if ($location === '' || ! str_starts_with($location, 'https://')) {
            throw new RuntimeException('Redbooth no devolvió una URL segura de descarga.');
        }

        return $location;
    }

    public function disconnect(Usuario $usuario): void
    {
        RedboothCredential::query()
            ->where('usuario_id', (int) $usuario->getKey())
            ->delete();
    }

    private function request(Usuario $usuario): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('redbooth.api_url'), '/'))
            ->acceptJson()
            ->withToken($this->accessToken($usuario))
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }

    private function accessToken(Usuario $usuario): string
    {
        $credential = $this->credentialFor($usuario);

        if ($credential->expires_at->isAfter(now()->addSeconds(self::TOKEN_EXPIRY_MARGIN_SECONDS))) {
            return (string) $credential->access_token;
        }

        return Cache::lock('redbooth-refresh-'.$usuario->getKey(), 30)
            ->block(10, function () use ($usuario): string {
                $credential = $this->credentialFor($usuario);

                if ($credential->expires_at->isAfter(now()->addSeconds(self::TOKEN_EXPIRY_MARGIN_SECONDS))) {
                    return (string) $credential->access_token;
                }

                $tokens = Http::asForm()
                    ->acceptJson()
                    ->timeout(20)
                    ->post((string) config('redbooth.token_url'), [
                        'client_id' => config('redbooth.client_id'),
                        'client_secret' => config('redbooth.client_secret'),
                        'refresh_token' => $credential->refresh_token,
                        'grant_type' => 'refresh_token',
                    ])
                    ->throw()
                    ->json();

                return (string) $this
                    ->storeTokens($usuario, $this->validateTokenPayload($tokens))
                    ->access_token;
            });
    }

    private function credentialFor(Usuario $usuario): RedboothCredential
    {
        $credential = RedboothCredential::query()
            ->where('usuario_id', (int) $usuario->getKey())
            ->first();

        if (! $credential) {
            throw new RuntimeException('La cuenta de Redbooth no está conectada.');
        }

        return $credential;
    }

    /** @param array{access_token: string, refresh_token: string, expires_in: int, token_type?: string, scope?: string|null} $tokens */
    private function storeTokens(Usuario $usuario, array $tokens): RedboothCredential
    {
        return RedboothCredential::query()->updateOrCreate(
            ['usuario_id' => (int) $usuario->getKey()],
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => $tokens['token_type'] ?? 'bearer',
                'scope' => $tokens['scope'] ?? null,
                'expires_at' => now()->addSeconds($tokens['expires_in']),
            ],
        );
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type?: string, scope?: string|null}
     */
    private function validateTokenPayload(mixed $payload): array
    {
        if (! is_array($payload)
            || ! is_string(Arr::get($payload, 'access_token'))
            || ! is_string(Arr::get($payload, 'refresh_token'))
            || filter_var(Arr::get($payload, 'expires_in'), FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('Redbooth devolvió una respuesta de tokens incompleta.');
        }

        return [
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'],
            'expires_in' => (int) $payload['expires_in'],
            'token_type' => is_string($payload['token_type'] ?? null) ? $payload['token_type'] : 'bearer',
            'scope' => is_string($payload['scope'] ?? null) ? $payload['scope'] : null,
        ];
    }

    private function assertConfigured(): void
    {
        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (blank(config("redbooth.{$key}"))) {
                throw new RuntimeException("Falta configurar redbooth.{$key}.");
            }
        }
    }
}
