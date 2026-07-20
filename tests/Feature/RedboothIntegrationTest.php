<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Integraciones\RedboothCredential;
use App\Models\Sistema\Usuario;
use App\Services\Integraciones\RedboothService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('NoProduccion', 60)->nullable();
            $table->string('NoTelarId', 60)->nullable();
            $table->integer('IdRedbooth')->nullable();
            $table->string('NombreRedbooth', 255)->nullable();
        });
        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('OrdenTejido', 60)->nullable();
            $table->string('TelarId', 60)->nullable();
            $table->integer('IdRedbooth')->nullable();
            $table->string('NombreRedbooth', 255)->nullable();
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

    public function test_tasks_returns_the_tasks_for_a_redbooth_project(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://redbooth.com/api/3/tasks*' => Http::response([
                ['id' => 991, 'project_id' => 2113514, 'name' => 'Tarea Towell'],
            ]),
        ]);

        $this->actingAs($usuario)
            ->getJson(route('redbooth.tasks', [
                'project_id' => 2113514,
                'order' => 'created_at-DESC',
                'per_page' => 100,
            ]))
            ->assertOk()
            ->assertJsonPath('0.project_id', 2113514)
            ->assertJsonPath('0.name', 'Tarea Towell');

        Http::assertSent(fn ($request): bool => str_contains(
            $request->url(),
            'project_id=2113514',
        ));
    }

    public function test_comments_returns_the_messages_for_a_task(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://redbooth.com/api/3/comments*' => Http::response([
                [
                    'id' => 411197000,
                    'target_type' => 'Task',
                    'target_id' => 63054266,
                    'body' => 'Mensaje de la tarea',
                ],
            ]),
        ]);

        $this->actingAs($usuario)
            ->getJson(route('redbooth.comments', [
                'target_type' => 'Task',
                'target_id' => 63054266,
                'project_id' => 2113514,
                'order' => 'created_at-ASC',
            ]))
            ->assertOk()
            ->assertJsonPath('0.target_id', 63054266)
            ->assertJsonPath('0.body', 'Mensaje de la tarea');

        Http::assertSent(fn ($request): bool => str_contains(
            $request->url(),
            'target_id=63054266',
        ));
    }

    public function test_files_returns_images_attached_to_a_task(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://redbooth.com/api/3/files*' => Http::response([
                [
                    'id' => 3698064,
                    'backend_id' => '3757001',
                    'name' => 'evidencia.png',
                    'mime_type' => 'image/png',
                    'project_id' => 2113514,
                ],
            ]),
        ]);

        $this->actingAs($usuario)
            ->getJson(route('redbooth.files', [
                'project_id' => 2113514,
                'target_type' => 'Task',
                'target_id' => 63054266,
                'type' => 'file',
            ]))
            ->assertOk()
            ->assertJsonPath('0.mime_type', 'image/png')
            ->assertJsonPath('0.name', 'evidencia.png');

        Http::assertSent(fn ($request): bool => str_contains(
            $request->url(),
            'target_id=63054266',
        ));
    }

    public function test_images_excludes_non_image_attachments(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://redbooth.com/api/3/files*' => Http::response([
                ['id' => 1, 'name' => 'foto.jpg', 'mime_type' => 'image/jpeg'],
                ['id' => 2, 'name' => 'minuta.pdf', 'mime_type' => 'application/pdf'],
                ['id' => 3, 'name' => 'captura.png', 'mime_type' => 'image/png'],
            ]),
        ]);

        $this->actingAs($usuario)
            ->getJson(route('redbooth.images', [
                'project_id' => 2113514,
                'target_type' => 'Task',
                'target_id' => 62659302,
                'type' => 'file',
            ]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'foto.jpg')
            ->assertJsonPath('1.name', 'captura.png');
    }

    public function test_download_redirects_to_the_temporary_redbooth_file_url(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://redbooth.com/api/3/files/33022149/download' => Http::response(
                '',
                302,
                ['Location' => 'https://assets.example.test/Minuta_Comercial_S25.pdf?signature=test'],
            ),
        ]);

        $this->actingAs($usuario)
            ->get(route('redbooth.files.download', ['fileId' => 33022149]))
            ->assertRedirect('https://assets.example.test/Minuta_Comercial_S25.pdf?signature=test');
    }

    public function test_programa_tejido_redbooth_options_returns_task_ids_and_names(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://redbooth.com/api/3/tasks*' => Http::response([
                ['id' => 62542504, 'name' => '1.ALPURA MB', 'deleted' => false],
                ['id' => 62542505, 'name' => 'Registro eliminado', 'deleted' => true],
            ]),
        ]);

        $this->actingAs($usuario)
            ->getJson(route('programa-tejido.redbooth.proyectos', ['q' => 'ALPURA']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', 62542504)
            ->assertJsonPath('results.0.name', '1.ALPURA MB')
            ->assertJsonPath('results.0.text', '62542504 — 1.ALPURA MB');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'project_id=2113514')
            && str_contains($request->url(), 'task_list_id=6863455'));
    }

    public function test_programa_tejido_saves_redbooth_id_and_name_in_both_tables(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        $programaId = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '36737',
            'NoTelarId' => '204',
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '36737',
            'TelarId' => 'OTRO-TELAR',
        ]);
        Http::fake([
            'https://redbooth.com/api/3/tasks*' => Http::response([
                ['id' => 62542504, 'name' => '1.ALPURA MB', 'deleted' => false],
            ]),
        ]);

        $this->actingAs($usuario)
            ->postJson(route('programa-tejido.redbooth.store'), [
                'req_programa_tejido_id' => $programaId,
                'redbooth_task_id' => 62542504,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('idRedbooth', 62542504)
            ->assertJsonPath('nombreRedbooth', '1.ALPURA MB')
            ->assertJsonPath('catCodificadosActualizados', 1);

        $this->assertDatabaseHas('ReqProgramaTejido', [
            'Id' => $programaId,
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ], 'sqlsrv');
        $this->assertDatabaseHas('CatCodificados', [
            'OrdenTejido' => '36737',
            'TelarId' => 'OTRO-TELAR',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ], 'sqlsrv');
    }

    public function test_programa_tejido_redbooth_viewer_returns_task_comments_and_files(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        $programaId = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '36737',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ]);
        Http::fake([
            'https://redbooth.com/api/3/tasks*' => Http::response([
                ['id' => 62542504, 'name' => '1.ALPURA MB', 'status' => 'open', 'project_id' => 2113514],
            ]),
            'https://redbooth.com/api/3/comments*' => Http::response([
                [
                    'id' => 8,
                    'target_id' => 62542504,
                    'user_id' => 6289927,
                    'body' => '',
                    'body_html' => '<span><div><p></p></div></span>',
                    'created_at' => 300,
                ],
                [
                    'id' => 9,
                    'target_id' => 62542504,
                    'user_id' => 6289927,
                    'body' => 'Comentario anterior',
                    'created_at' => 100,
                ],
                [
                    'id' => 10,
                    'target_id' => 62542504,
                    'user_id' => 6289927,
                    'body' => 'Seguimiento de calidad',
                    'file_ids' => [33022149],
                    'created_at' => 200,
                ],
            ]),
            'https://redbooth.com/api/3/users*' => Http::response([
                ['id' => 6289927, 'first_name' => 'Francisco', 'last_name' => 'Hernández'],
            ]),
            'https://redbooth.com/api/3/files*' => Http::response([
                ['id' => 33022149, 'name' => 'evidencia.jpg', 'mime_type' => 'image/jpeg'],
                ['id' => 33022150, 'name' => 'minuta.pdf', 'mime_type' => 'application/pdf'],
            ]),
        ]);

        $this->actingAs($usuario)
            ->getJson(route('programa-tejido.redbooth.show', ['programa' => $programaId]))
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('task.name', '1.ALPURA MB')
            ->assertJsonPath('comments.0.body', 'Seguimiento de calidad')
            ->assertJsonPath('comments.1.body', 'Comentario anterior')
            ->assertJsonCount(2, 'comments')
            ->assertJsonPath('comments.0.user_name', 'Francisco Hernández')
            ->assertJsonPath('comments.0.files.0.name', 'evidencia.jpg')
            ->assertJsonPath('comments.0.files.0.is_image', true)
            ->assertJsonPath('files.0.is_image', true)
            ->assertJsonPath('files.1.is_image', false)
            ->assertJsonPath(
                'files.0.download_url',
                route('redbooth.files.download', ['fileId' => 33022149]),
            );
    }

    public function test_programa_tejido_removes_redbooth_link_from_req_and_cat_by_order(): void
    {
        $usuario = $this->usuario();
        $programaId = DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '36737',
            'NoTelarId' => '204',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '36737',
            'TelarId' => 'OTRO-TELAR',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ]);

        $this->actingAs($usuario)
            ->deleteJson(route('programa-tejido.redbooth.destroy', ['programa' => $programaId]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('catCodificadosActualizados', 1);

        $this->assertDatabaseHas('ReqProgramaTejido', [
            'Id' => $programaId,
            'IdRedbooth' => null,
            'NombreRedbooth' => null,
        ], 'sqlsrv');
        $this->assertDatabaseHas('CatCodificados', [
            'OrdenTejido' => '36737',
            'TelarId' => 'OTRO-TELAR',
            'IdRedbooth' => null,
            'NombreRedbooth' => null,
        ], 'sqlsrv');
    }

    public function test_cat_codificados_saves_redbooth_link_and_syncs_req_by_order(): void
    {
        $usuario = $this->usuario();
        RedboothCredential::query()->create([
            'usuario_id' => 7,
            'access_token' => 'valid-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            'NoProduccion' => '36737',
            'NoTelarId' => '204',
        ]);
        $catId = DB::connection('sqlsrv')->table('CatCodificados')->insertGetId([
            'OrdenTejido' => '36737',
            'TelarId' => 'OTRO-TELAR',
        ]);
        Http::fake([
            'https://redbooth.com/api/3/tasks*' => Http::response([
                ['id' => 62542504, 'name' => '1.ALPURA MB', 'deleted' => false],
            ]),
        ]);

        $this->actingAs($usuario)
            ->postJson(route('programa-tejido.redbooth.store'), [
                'source' => 'catcodificados',
                'cat_codificados_id' => $catId,
                'redbooth_task_id' => 62542504,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('CatCodificados', [
            'Id' => $catId,
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ], 'sqlsrv');
        $this->assertDatabaseHas('ReqProgramaTejido', [
            'NoProduccion' => '36737',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ], 'sqlsrv');
    }

    public function test_cat_codificados_without_link_opens_the_redbooth_selector(): void
    {
        $catId = DB::connection('sqlsrv')->table('CatCodificados')->insertGetId([
            'OrdenTejido' => '36737',
        ]);

        $this->actingAs($this->usuario())
            ->getJson(route('programa-tejido.redbooth.show', [
                'programa' => $catId,
                'source' => 'catcodificados',
            ]))
            ->assertOk()
            ->assertJsonPath('linked', false)
            ->assertJsonPath('programaId', $catId);
    }

    public function test_cat_codificados_removes_redbooth_link_and_syncs_req_by_order(): void
    {
        $usuario = $this->usuario();
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            'NoProduccion' => '36737',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ]);
        $catId = DB::connection('sqlsrv')->table('CatCodificados')->insertGetId([
            'OrdenTejido' => '36737',
            'IdRedbooth' => 62542504,
            'NombreRedbooth' => '1.ALPURA MB',
        ]);

        $this->actingAs($usuario)
            ->deleteJson(route('programa-tejido.redbooth.destroy', [
                'programa' => $catId,
                'source' => 'catcodificados',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('CatCodificados', [
            'Id' => $catId,
            'IdRedbooth' => null,
            'NombreRedbooth' => null,
        ], 'sqlsrv');
        $this->assertDatabaseHas('ReqProgramaTejido', [
            'NoProduccion' => '36737',
            'IdRedbooth' => null,
            'NombreRedbooth' => null,
        ], 'sqlsrv');
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
