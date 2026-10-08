<?php

namespace Tests\Feature\Bpm;

use App\Livewire\Bpm\Actividades;
use App\Livewire\Bpm\Checklist;
use App\Livewire\Bpm\Folios;
use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Engomado\EngBpmLineModel;
use App\Models\Engomado\EngBpmModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelActividadesBPM;
use App\Models\Tejedores\TelBpmLineModel;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Urdido\UrdActividadesBpmModel;
use App\Models\Urdido\UrdBpmLineModel;
use App\Models\Urdido\UrdBpmModel;
use App\Support\Bpm\AreaBpm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** BPM de las tres áreas: los mismos componentes Livewire (Folios, Checklist, Actividades). */
class BpmTest extends TestCase
{
    use ModuloUrdEng;
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        foreach ([UrdBpmModel::class, EngBpmModel::class, UrdBpmLineModel::class, EngBpmLineModel::class, TelBpmLineModel::class,
            UrdActividadesBpmModel::class, EngActividadesBpmModel::class, TelActividadesBPM::class,
            SYSUsuario::class, TelTelaresOperador::class] as $modelo) {
            $this->tablaDe($modelo);
        }
        // Llaves de texto (TelBPM.Folio, URDCatalogoMaquinas.MaquinaId): tablaDe() las haría autoincrementales.
        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t): void {
            $t->string('MaquinaId')->primary();
            $t->string('Nombre')->nullable();
            $t->string('Departamento')->nullable();
        });
        Schema::connection('sqlsrv')->create('TelBPM', function (Blueprint $t): void {
            $t->string('Folio')->primary();
            foreach (['CveEmplRec', 'NombreEmplRec', 'TurnoRecibe', 'CveEmplEnt', 'NombreEmplEnt', 'TurnoEntrega', 'CveEmplAutoriza', 'NomEmplAutoriza', 'Status', 'Comentarios'] as $c) {
                $t->string($c)->nullable();
            }
            $t->dateTime('Fecha')->nullable();
        });

        // Folios: SSYSFoliosSecuencias pregunta sus columnas a INFORMATION_SCHEMA.
        $this->createTablaDbo('SSYSFoliosSecuencias', ['Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'modulo' => 'TEXT', 'prefijo' => 'TEXT', 'consecutivo' => 'INTEGER']);
        $db = DB::connection('sqlsrv');
        $db->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $db->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $columna) {
            $db->table('INFORMATION_SCHEMA.COLUMNS')->insert(['TABLE_SCHEMA' => 'dbo', 'TABLE_NAME' => 'SSYSFoliosSecuencias', 'COLUMN_NAME' => $columna]);
        }

        // Quien entrega en Urdido / Engomado (SYSUsuario por área) y en Tejedores (mismo telar).
        $db->table('SYSUsuario')->insert([
            ['idusuario' => 20, 'numero_empleado' => '200', 'nombre' => 'Beto Urdido', 'area' => 'Urdido', 'turno' => '2'],
            ['idusuario' => 21, 'numero_empleado' => '201', 'nombre' => 'Beto Engomado', 'area' => 'Engomado', 'turno' => '2'],
            ['idusuario' => 22, 'numero_empleado' => '0202', 'nombre' => 'Beto Tejedor', 'area' => 'Tejido', 'turno' => '3'],
        ]);
        $db->table('TelTelaresOperador')->insert([
            ['numero_empleado' => '100', 'nombreEmpl' => 'Usuario prueba', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Turno' => '1'],
            ['numero_empleado' => '100', 'nombreEmpl' => 'Usuario prueba', 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'Turno' => '1'],
            ['numero_empleado' => '202', 'nombreEmpl' => 'Beto Tejedor', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Turno' => '3'],
        ]);
        $db->table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => 'MC1', 'Nombre' => 'MC Coy 1', 'Departamento' => 'Urdido'],
            ['MaquinaId' => 'ENG1', 'Nombre' => 'West Point 1', 'Departamento' => 'Engomado'],
        ]);
        $db->table('UrdActividadesBPM')->insert([['Orden' => 1, 'Actividad' => 'Limpieza', 'Maquina' => 'MC'], ['Orden' => 1, 'Actividad' => 'Solo Karl Mayer', 'Maquina' => 'KM']]);
        $db->table('EngActividadesBPM')->insert([['Orden' => 1, 'Actividad' => 'Limpieza'], ['Orden' => 2, 'Actividad' => 'Orden']]);
        $db->table('TelActividadesBPM')->insert([['Actividad' => 'Limpieza'], ['Actividad' => 'Orden']]);
    }

    /** @return array<string, array{AreaBpm, string}> */
    public static function areas(): array
    {
        return [
            'urdido' => [AreaBpm::Urdido, 'MC1'],
            'engomado' => [AreaBpm::Engomado, 'ENG1'],
            'tejedores' => [AreaBpm::Tejedores, ''],
        ];
    }

    #[DataProvider('areas')]
    public function test_crear_marcar_terminar_autorizar(AreaBpm $area, string $maquina): void
    {
        [$modulo, $prefijo, $digitos] = $area->folio();
        $folio = $prefijo.str_pad('8', $digitos, '0', STR_PAD_LEFT);
        DB::connection('sqlsrv')->table('dbo.SSYSFoliosSecuencias')->insert(['modulo' => $modulo, 'prefijo' => $prefijo, 'consecutivo' => 7]);
        $usuario = $this->usuarioCon([$area->modulo() => ['acceso', 'crear', 'modificar', 'eliminar', 'registrar']]);
        $this->actingAs($usuario);

        $entrega = ['urdido' => '200', 'engomado' => '201', 'tejedores' => '202'][$area->value];
        Livewire::test(Folios::class, ['area' => $area])
            ->call('abrirAlta')
            ->set('form.entrega', $entrega)
            ->set('form.maquina', $maquina)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route($area->value.'.bpm.checklist', ['folio' => $folio]));

        $encabezado = ($area->modelo())::where('Folio', $folio)->firstOrFail();
        $this->assertSame(['Creado', '100', $entrega === '202' ? '3' : '2'], [$encabezado->Status, $encabezado->CveEmplRec, $encabezado->TurnoEntrega]);

        // Urdido: solo las actividades de MC Coy; Tejedores: 2 actividades × 2 telares del que recibe.
        $lineas = ($area->modeloLinea())::where('Folio', $folio)->get();
        $this->assertCount(['urdido' => 1, 'engomado' => 2, 'tejedores' => 4][$area->value], $lineas);
        $this->assertSame($area->sinMarca(), $lineas->first()->Valor);

        $checklist = Livewire::test(Checklist::class, ['area' => $area, 'folio' => $folio])->assertOk()->assertSee('Limpieza');
        $checklist->call('terminar');
        $this->assertSame('Creado', $encabezado->fresh()->Status, 'con marcas pendientes no se termina');

        foreach ($lineas as $linea) {
            $checklist->call('marcar', $linea->Id);
        }
        $this->assertSame($area->porTelar() ? 'OK' : '1', $lineas->first()->fresh()->Valor);

        $checklist->call('terminar')->assertRedirect(route($area->value.'.bpm.folios'));
        $this->assertSame('Terminado', $encabezado->fresh()->Status);

        // Autorizar exige supervisor además de 'registrar'.
        $checklist->call('autorizar')->assertForbidden();
        $usuario->setAttribute('puesto', 'Supervisor');
        Livewire::test(Checklist::class, ['area' => $area, 'folio' => $folio])->call('autorizar');
        $encabezado = $encabezado->fresh();
        $this->assertSame(['Autorizado', '100', 'Usuario prueba'], [$encabezado->Status, $encabezado->CveEmplAutoriza, $encabezado->{$area->columnaAutoriza()}]);
    }

    public function test_marca_cicla_y_tejedores_tiene_mantenimiento(): void
    {
        $this->assertSame(['1', '2', '0'], [AreaBpm::Urdido->siguienteMarca('0'), AreaBpm::Urdido->siguienteMarca('1'), AreaBpm::Urdido->siguienteMarca('2')]);
        $this->assertSame(['OK', 'X', 'M', null], [AreaBpm::Tejedores->siguienteMarca(null), AreaBpm::Tejedores->siguienteMarca('OK'), AreaBpm::Tejedores->siguienteMarca('X'), AreaBpm::Tejedores->siguienteMarca('M')]);
    }

    public function test_lista_filtra_por_status_y_borra_solo_creados(): void
    {
        $db = DB::connection('sqlsrv');
        foreach (['BU001' => 'Creado', 'BU002' => 'Terminado', 'BU003' => 'Autorizado'] as $folio => $status) {
            $db->table('UrdBPM')->insert(['Folio' => $folio, 'Status' => $status, 'Fecha' => now(), 'CveEmplRec' => '100', 'NombreEmplRec' => 'Usuario prueba']);
            $db->table('UrdBPMLine')->insert(['Folio' => $folio, 'Orden' => 1, 'Actividad' => 'Limpieza', 'Valor' => '0']);
        }
        $this->actingAs($this->usuarioCon([35 => ['acceso', 'eliminar']]));

        $lista = Livewire::test(Folios::class, ['area' => AreaBpm::Urdido])
            ->assertSee('BU001')->assertSee('BU002')->assertDontSee('BU003')
            ->set('alcance', 'todos')->assertSee('BU003');

        $id = fn (string $folio) => (string) UrdBpmModel::where('Folio', $folio)->value('Id');
        $lista->call('seleccionar', $id('BU002'))->call('eliminar');
        $this->assertTrue(UrdBpmModel::where('Folio', 'BU002')->exists(), 'un folio Terminado no se borra');

        $lista->call('seleccionar', $id('BU001'))->call('eliminar');
        $this->assertFalse(UrdBpmModel::where('Folio', 'BU001')->exists());
        $this->assertSame(0, UrdBpmLineModel::where('Folio', 'BU001')->count(), 'se borra con su checklist');
    }

    public function test_sin_permiso_eliminar_no_borra(): void
    {
        DB::connection('sqlsrv')->table('EngBPM')->insert(['Folio' => 'BE001', 'Status' => 'Creado', 'Fecha' => now()]);
        $this->actingAs($this->usuarioCon([41 => ['acceso']]));

        Livewire::test(Folios::class, ['area' => AreaBpm::Engomado])
            ->set('misFolios', false)
            ->call('seleccionar', (string) EngBpmModel::value('Id'))
            ->call('eliminar')
            ->assertForbidden();
    }

    public function test_actividades_urdido_con_maquina(): void
    {
        $this->actingAs($this->usuarioCon([144 => ['acceso', 'crear']]));

        Livewire::test(Actividades::class, ['area' => AreaBpm::Urdido])
            ->assertSee('Solo Karl Mayer')
            ->call('abrirAlta')
            ->set('form.Actividad', 'Revisar fileta')
            ->set('form.Orden', '2')
            ->call('guardar')
            ->assertHasErrors('form.Maquina')
            ->set('form.Maquina', 'KM')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('KM', UrdActividadesBpmModel::where('Actividad', 'Revisar fileta')->value('Maquina'));
    }

    /** @return array<string, array{string}> */
    public static function urls(): array
    {
        return [
            'urdido folios' => ['/urd-bpm'], 'engomado folios' => ['/eng-bpm'], 'tejedores folios' => ['/tejedores/bpmtejedores'],
            'urdido actividades' => ['/urdido/configuracion/actividadesbpmurdido'], 'engomado actividades' => ['/engomado/configuracion/actividadesbpmengomado'],
            'tejedores actividades' => ['/tejedores/configurar/actividadestejedores'],
        ];
    }

    #[DataProvider('urls')]
    public function test_las_urls_de_siempre_abren_la_pantalla(string $url): void
    {
        $this->actingAs($this->usuarioCon([35 => ['acceso'], 41 => ['acceso'], 47 => ['acceso'], 144 => ['acceso'], 164 => ['acceso'], 173 => ['acceso']]))
            ->get($url)
            ->assertOk()
            ->assertSeeLivewire(str_contains($url, 'activ') ? Actividades::class : Folios::class);
    }
}
