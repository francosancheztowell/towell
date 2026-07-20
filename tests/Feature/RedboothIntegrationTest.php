<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Integraciones\RedboothCredential;
use App\Models\Sistema\Usuario;
use App\Services\Integraciones\RedboothService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RedboothIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.sqlsrv' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'redbooth.client_id' => 'client-id',
            'redbooth.client_secret' => 'client-secret',
            'redbooth.redirect_uri' => 'http://localhost/integraciones/redbooth/callback',
            'redbooth.authorize_url' => 'https://redbooth.com/oauth2/authorize',
            'redbooth.token_url' => 'https://redbooth.com/oauth2/token',
            'redbooth.api_url' => 'https://redbooth.com/api/3',
        ]);

        Schema::connection('sqlsrv')->create('RedboothCredentials', function (Blueprint $table): void {
            $table->id();
            $table->integer('usuario_id')->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->string('token_type')->default('bearer');
            $table->string('scope')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function test_connect_redirects_to_redbooth_with_state(): void
    {
        $response = $this->actingAs($this->usuario())->get(route('redbooth.connect'));

        $response->assertRedirectContains('https://redbooth.com/oauth2/authorize?');
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(session('redbooth_oauth_state'), $query['state']);
    }

    public function test_callback_exchanges_code_and_stores_encrypted_tokens(): void
    {
        Http::fake([
            'https://redbooth.com/oauth2/token' => Http::response([
                'access_token' => 'access-secret',
                'refresh_token' => 'refresh-secret',
                'expires_in' => 7200,
                'token_type' => 'bearer',
                'scope' => 'all',
            ]),
        ]);

        $response = $this->actingAs($this->usuario())
            ->withSession(['redbooth_oauth_state' => 'known-state'])
            ->get(route('redbooth.callback', ['code' => 'code-123', 'state' => 'known-state']));

        $response->assertRedirect(route('redbooth.status'));
        $credential = RedboothCredential::query()->firstOrFail();

        $this->assertSame('access-secret', $credential->access_token);
        $this->assertNotSame('access-secret', $credential->getRawOriginal('access_token'));
        Http::assertSent(fn ($request): bool => $request['grant_type'] === 'authorization_code');
    }

    public function test_expired_token_is_refreshed_before_calling_me(): void
    {
        Cache::clear();
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'expired-token',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            'https://redbooth.com/oauth2/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 7200,
            ]),
            'https://redbooth.com/api/3/me' => Http::response(['id' => 123, 'first_name' => 'Towell']),
        ]);

        $me = app(RedboothService::class)->me($usuario);

        $this->assertSame(123, $me['id']);
        $this->assertSame('new-refresh-token', RedboothCredential::query()->firstOrFail()->refresh_token);
        Http::assertSentCount(2);
    }

    public function test_callback_rejects_an_invalid_state(): void
    {
        $this->actingAs($this->usuario())
            ->withSession(['redbooth_oauth_state' => 'expected'])
            ->get(route('redbooth.callback', ['code' => 'code-123', 'state' => 'different']))
            ->assertForbidden();
    }

    private function usuario(): Usuario
    {
        $usuario = new Usuario([
            'idusuario' => 7,
            'numero_empleado' => '7',
            'nombre' => 'Integración Redbooth',
            'contrasenia' => 'test',
        ]);
        $usuario->idusuario = 7;

        return $usuario;
    }
}
