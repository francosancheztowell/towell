<?php

declare(strict_types=1);

namespace Tests\Feature\Tejedores;

use App\Http\Middleware\EnsureModulePermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * El front de BPM Line (tel-bpm-line/index.blade.php) solo entiende `{ok, valor}` o `{ok:false, msg}`:
 * cualquier otra forma de respuesta dejaba la celda sin cambiar y sin aviso.
 */
class TelBpmLineToggleTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->withoutMiddleware([ValidateCsrfToken::class, EnsureModulePermission::class]);
        $this->actingAs($this->createUsuario(), 'web');

        Schema::connection('sqlsrv')->create('TelBPM', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->string('Status')->nullable();
            $table->string('Comentarios')->nullable();
        });
        Schema::connection('sqlsrv')->create('TelBPMLine', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio');
            $table->integer('Orden');
            $table->string('NoTelarId');
            $table->string('Actividad')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('TurnoRecibe')->nullable();
            $table->string('Valor')->nullable();
        });
        Schema::connection('sqlsrv')->create('TelActividadesBPM', function (Blueprint $table) {
            $table->integer('Orden');
            $table->string('Actividad');
        });

        DB::table('TelBPM')->insert(['Folio' => 'BT0001', 'Status' => 'Creado']);
        DB::table('TelActividadesBPM')->insert(['Orden' => 1, 'Actividad' => 'Limpieza']);
    }

    public function test_la_celda_recorre_ok_x_mantenimiento_y_vacio(): void
    {
        foreach (['OK', 'X', 'M', null] as $esperado) {
            $this->toggle()->assertOk()->assertExactJson(['ok' => true, 'valor' => $esperado]);
        }

        $this->assertSame(1, DB::table('TelBPMLine')->count());
    }

    public function test_sin_actividad_en_el_payload_se_toma_del_catalogo(): void
    {
        $this->toggle(['Actividad' => null])->assertOk();

        $this->assertSame('Limpieza', DB::table('TelBPMLine')->value('Actividad'));
    }

    public function test_cada_telar_tiene_su_propia_celda(): void
    {
        $this->toggle(['NoTelarId' => '201'])->assertJson(['valor' => 'OK']);
        $this->toggle(['NoTelarId' => '202'])->assertJson(['valor' => 'OK']);
        $this->toggle(['NoTelarId' => '201'])->assertJson(['valor' => 'X']);

        $this->assertSame('OK', DB::table('TelBPMLine')->where('NoTelarId', '202')->value('Valor'));
    }

    public function test_un_folio_terminado_no_se_edita_y_responde_msg(): void
    {
        DB::table('TelBPM')->update(['Status' => 'Terminado']);

        $this->toggle()->assertStatus(422)->assertExactJson(['ok' => false, 'msg' => 'Edición sólo en estado Creado']);
        $this->assertSame(0, DB::table('TelBPMLine')->count());
    }

    public function test_payload_invalido_responde_422_json(): void
    {
        $this->toggle(['Orden' => 0, 'NoTelarId' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['Orden', 'NoTelarId']);
    }

    public function test_folio_inexistente_responde_404(): void
    {
        $this->postJson(route('tel-bpm-line.toggle', 'NOEXISTE'), $this->payload())->assertNotFound();
    }

    /** @param array<string, mixed> $cambios */
    private function toggle(array $cambios = []): TestResponse
    {
        return $this->postJson(route('tel-bpm-line.toggle', 'BT0001'), $this->payload($cambios));
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function payload(array $cambios = []): array
    {
        return $cambios + [
            'Orden' => 1,
            'NoTelarId' => '201',
            'SalonTejidoId' => 'JACQUARD',
            'TurnoRecibe' => '1',
            'Actividad' => 'Limpieza',
        ];
    }
}
