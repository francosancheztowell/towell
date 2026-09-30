<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;

/**
 * Tablas de Atadores en sqlite (espejo de las columnas que usa el módulo) para los tests de 19-03.
 * El usuario queda autenticado con acceso/crear/modificar de Programa Atadores (idrol 45).
 */
trait EsquemaAtadores
{
    use UsesSqlsrvSqlite;

    protected function prepararAtadores(array $acciones = ['acceso', 'crear', 'modificar']): void
    {
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $schema = Schema::connection('sqlsrv');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        $schema->create('dbo.ReqTelares', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('NoTelarId')->nullable();
        });

        $schema->create('AtaMontadoTelas', function (Blueprint $t) {
            $t->increments('Id');
            foreach (['Estatus', 'Turno', 'NoJulio', 'NoProduccion', 'no_julio2', 'no_julio3', 'no_julio4',
                'no_orden2', 'no_orden3', 'no_orden4', 'Tipo', 'NoTelarId', 'LoteProveedor', 'NoProveedor',
                'HoraParo', 'HoraArranque', 'Calidad', 'Limpieza', 'CveSupervisor', 'NomSupervisor', 'Obs',
                'CveTejedor', 'NomTejedor', 'comments_sup', 'comments_ata', 'comments_tej', 'HrInicio',
                'FolioParo', 'ConfigId', 'InventSizeId', 'InventColorId', 'TiempoParo'] as $col) {
                $t->string($col)->nullable();
            }
            $t->date('Fecha')->nullable();
            $t->float('Metros')->nullable();
            $t->float('MergaKg')->nullable();
            $t->dateTime('FechaSupervisor')->nullable();
            $t->date('FechaInicio')->nullable();
            $t->date('FechaParo')->nullable();
            $t->date('FechaArranque')->nullable();
        });

        $schema->create('AtaDevoluciones', function (Blueprint $t) {
            $t->increments('Id');
            $t->integer('RefId')->nullable();
            foreach (['NoJulio', 'Ubicacion', 'Cuenta', 'Calibre', 'Hilo', 'NoProduccion', 'Tipo', 'Obs',
                'ConfigId', 'InventSizeId', 'InventColorId', 'Estatus', 'NoTelarId', 'CveOperador',
                'LoteOriginal', 'FolioDev'] as $col) {
                $t->string($col)->nullable();
            }
            $t->float('Kilos')->nullable();
            $t->float('Metros')->nullable();
            $t->date('FechaDevol')->nullable();
            $t->integer('InvTelasReservadaId')->nullable();
            $t->integer('AX')->nullable();
        });

        $schema->create('AtaComentarios', function (Blueprint $t) {
            $t->string('Nota1')->primary();
            $t->string('Nota2')->nullable();
        });
        $schema->create('AtaMaquinas', function (Blueprint $t) {
            $t->string('MaquinaId')->primary();
        });
        $schema->create('AtaActividades', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('ActividadId')->nullable();
            $t->float('Porcentaje')->nullable();
        });
        $schema->create('AtaMontadoMaquinas', function (Blueprint $t) {
            $t->increments('Id');
            foreach (['NoJulio', 'NoProduccion', 'MaquinaId', 'NomEmpleado', 'NomEmpl', 'CveEmpl'] as $col) {
                $t->string($col)->nullable();
            }
            $t->integer('Estado')->nullable();
        });
        $schema->create('AtaMontadoActividades', function (Blueprint $t) {
            $t->increments('Id');
            foreach (['NoJulio', 'NoProduccion', 'ActividadId', 'CveEmpl', 'NomEmpl', 'Turno'] as $col) {
                $t->string($col)->nullable();
            }
            $t->float('Porcentaje')->nullable();
            $t->integer('Estado')->nullable();
        });
        foreach (['AtaKmMontado', 'AtaKmEnhebrado'] as $tabla) {
            $schema->create($tabla, function (Blueprint $t) {
                $t->increments('Id');
                foreach (['NoJulio', 'NoProduccion', 'CveEmpl1', 'NomEmpl1', 'CveEmpl2', 'NomEmpl2', 'CveEmpl3', 'NomEmpl3'] as $col) {
                    $t->string($col)->nullable();
                }
                $t->dateTime('FechaInicio')->nullable();
                $t->dateTime('FechaFin')->nullable();
            });
        }

        $this->crearTablasInventario();

        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Programa Atadores', $acciones, null, 45);
    }

    /** Inventario de telares e historial (lo que lee iniciar y escribe autorizar). */
    private function crearTablasInventario(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->create('TejHistorialInventarioTelares', function (Blueprint $t) {
            $t->increments('Id');
            foreach (['NoTelarId', 'Status', 'Tipo', 'Cuenta', 'Calibre', 'Turno', 'Fibra', 'NoJulio',
                'NoProduccion', 'TipoAtado', 'Localidad', 'LoteProveedor', 'NoProveedor', 'HoraParo'] as $col) {
                $t->string($col)->nullable();
            }
            $t->float('Metros')->nullable();
            $t->dateTime('FechaAtado')->nullable();
            $t->dateTime('FechaRequerimiento')->nullable();
        });

        $schema->create('tej_inventario_telares', function (Blueprint $t) {
            $t->increments('id');
            foreach (['no_telar', 'tipo', 'status', 'no_julio', 'no_julio2', 'no_julio3', 'no_julio4',
                'no_orden', 'no_orden2', 'no_orden3', 'no_orden4', 'cuenta', 'calibre', 'hilo', 'turno',
                'horaParo', 'localidad', 'tipo_atado', 'loteProveedor', 'noProveedor', 'ConfigId',
                'InventSizeId', 'InventColorId'] as $col) {
                $t->string($col)->nullable();
            }
            $t->date('fecha')->nullable();
            $t->float('metros')->nullable();
            $t->timestamps();
        });
    }

    /** Otro usuario con permisos de Programa Atadores (el módulo ya existe en SYSRoles). */
    protected function entrarComo(array $atributos, array $acciones = ['acceso', 'crear']): void
    {
        $usuario = $this->createUsuario($atributos);
        $fila = ['idusuario' => $usuario->getKey(), 'idrol' => 45];
        foreach (['acceso', 'crear', 'modificar', 'eliminar', 'registrar'] as $accion) {
            $fila[$accion] = (int) in_array($accion, $acciones, true);
        }
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert($fila);
        $this->actingAs($usuario, 'web');
    }

    /** Catálogo de checklist con $maquinas máquinas y $actividades actividades. */
    protected function sembrarCatalogoChecklist(int $maquinas, int $actividades): void
    {
        $db = DB::connection('sqlsrv');
        $desde = $db->table('AtaMaquinas')->count();
        for ($i = $desde + 1; $i <= $desde + $maquinas; $i++) {
            $db->table('AtaMaquinas')->insert(['MaquinaId' => 'Maquina '.$i]);
        }
        $desde = $db->table('AtaActividades')->count();
        for ($i = $desde + 1; $i <= $desde + $actividades; $i++) {
            $db->table('AtaActividades')->insert(['ActividadId' => 'Actividad '.$i, 'Porcentaje' => $i]);
        }
    }

    /** Fila de inventario de un telar Jacquard lista para iniciar. */
    protected function inventarioJacquard(array $extra = []): int
    {
        return (int) DB::connection('sqlsrv')->table('tej_inventario_telares')->insertGetId(array_merge([
            'no_telar' => '300',
            'tipo' => 'Rizo',
            'status' => 'Activo',
            'no_julio' => '00010-1',
            'no_orden' => '00010',
            'fecha' => '2026-09-24',
            'turno' => '1',
            'horaParo' => '10:00:00',
        ], $extra));
    }

    /** @return int número de consultas que hizo $accion */
    protected function contarConsultas(callable $accion): int
    {
        $db = DB::connection('sqlsrv');
        $db->flushQueryLog();
        $db->enableQueryLog();
        $accion();
        $total = count($db->getQueryLog());
        $db->disableQueryLog();

        return $total;
    }
}
