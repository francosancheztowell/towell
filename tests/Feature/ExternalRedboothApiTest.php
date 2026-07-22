<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Integraciones\RedboothCredential;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ExternalRedboothApiTest extends TestCase
{
    private const API_KEY = 'test-external-api-key-with-more-than-32-characters';

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
            'redbooth.external_api_key' => self::API_KEY,
            'redbooth.external_user_id' => 1,
            'redbooth.api_url' => 'https://redbooth.com/api/3',
            'redbooth.token_url' => 'https://redbooth.com/oauth2/token',
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

        RedboothCredential::query()->create([
            'usuario_id' => 1,
            'access_token' => 'valid-redbooth-token',
            'refresh_token' => 'refresh-redbooth-token',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_external_api_rejects_requests_without_api_key(): void
    {
        $this->getJson('/api/v1/redbooth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'API key inválida.');
    }

    public function test_external_api_rejects_an_invalid_api_key(): void
    {
        $this->withToken('wrong-key')
            ->getJson('/api/v1/redbooth/me')
            ->assertUnauthorized();
    }

    public function test_external_api_uses_technical_user_redbooth_connection(): void
    {
        Http::fake([
            'https://redbooth.com/api/3/me' => Http::response([
                'id' => 6289927,
                'first_name' => 'Usuario',
            ]),
        ]);

        $this->withToken(self::API_KEY)
            ->getJson('/api/v1/redbooth/me')
            ->assertOk()
            ->assertJsonPath('id', 6289927);

        Http::assertSent(fn ($request): bool => $request->hasHeader(
            'Authorization',
            'Bearer valid-redbooth-token',
        ));
    }

    public function test_external_tasks_validates_and_forwards_filters(): void
    {
        Http::fake([
            'https://redbooth.com/api/3/tasks*' => Http::response([
                ['id' => 62542504, 'name' => 'Tarea externa'],
            ]),
        ]);

        $this->withHeader('X-API-Key', self::API_KEY)
            ->getJson('/api/v1/redbooth/tasks?project_id=2113514&per_page=20')
            ->assertOk()
            ->assertJsonPath('0.name', 'Tarea externa');

        Http::assertSent(fn ($request): bool => str_contains(
            $request->url(),
            'project_id=2113514',
        ));
    }

    public function test_external_tasks_rejects_missing_project_id(): void
    {
        $this->withToken(self::API_KEY)
            ->getJson('/api/v1/redbooth/tasks')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }
}
