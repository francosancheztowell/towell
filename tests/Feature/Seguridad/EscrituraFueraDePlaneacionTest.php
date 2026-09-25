<?php

namespace Tests\Feature\Seguridad;

use Closure;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route as RutaLaravel;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use Tests\TestCase;

/**
 * SEC-05 — Inventario de escrituras fuera de Planeación (Planeación la cubre
 * tests/Feature/Planeacion/PlaneacionEscrituraAutorizacionTest).
 *
 * Rompe si aparece un POST/PUT/PATCH/DELETE de la app sin module.permission (enforce o
 * auditar) que no esté justificado aquí. Mapa completo en
 * .planning/phases/20-arq-sec/20-03-MAPA-AUTHZ.md.
 */
class EscrituraFueraDePlaneacionTest extends TestCase
{
    /** Clave = métodos + URI como los da el router. */
    private const SIN_PERMISO_DE_MODULO = [
        'POST api/mantenimiento/paros' => 'excepción del owner: alta de paros abierta a cualquier autenticado',
        'PUT api/mantenimiento/paros/{id}/finalizar' => 'excepción del owner: el piso cierra sus paros (RutasDestructivasPermisoTest::EXENTAS)',
        'POST login' => 'autenticación (sin sesión)',
        'POST logout' => 'cierre de la propia sesión',
        'POST telemetria/dispositivo/nombre' => 'telemetría del propio dispositivo (fase 12)',
        'POST telemetria/error' => 'telemetría: errores de cliente',
        'POST telemetria/latido' => 'telemetría: latido',
        'POST telemetria/vista' => 'telemetría: vistas',
        'POST telemetria/vista/{uuid}/fin' => 'telemetría: fin de vista',
        // D3 (20-03): sin módulo identificable en el código; el owner consulta SYSRoles y se audita después.
        'POST tejedores/atadodejulio/notificar' => 'pendiente de idrol (20-03-MAPA-AUTHZ §Pendientes)',
        'POST tejedores/cortadoderollo/notificar' => 'pendiente de idrol (20-03-MAPA-AUTHZ §Pendientes)',
        'POST tejedores/cortadoderollo/insertar' => 'pendiente de idrol (20-03-MAPA-AUTHZ §Pendientes)',
        'POST atadores/reportes-atadores/oee/despachar' => 'pendiente de idrol (20-03-MAPA-AUTHZ §Pendientes)',
        // Solo se conoce el nombre del módulo (el controller ya valida con userCan por nombre) y
        // los gates van por idrol (RutasDestructivasPermisoTest): pendientes de la consulta a SYSRoles.
        'POST Crudo/auditorias' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST Crudo/auditorias/paro' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST engomado/programar-engomado/actualizar-prioridades' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST engomado/programar-engomado/actualizar-status' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST engomado/programar-engomado/guardar-observaciones' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST engomado/programar-engomado/intercambiar-prioridad' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST mecanicos/reportes/estado-maquina/excel' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST mecanicos/reportes/estado-maquina/pdf' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST mecanicos/reportes/estado-maquina/telegram-imagen' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST mecanicos/reportes/ot-diarias/excel' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST mecanicos/reportes/ot-diarias/pdf' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST mecanicos/reportes/ot-diarias/telegram-imagen' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST urdido/programar-urdido/actualizar-calidad' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST urdido/programar-urdido/actualizar-prioridades' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST urdido/programar-urdido/actualizar-status' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST urdido/programar-urdido/guardar-observaciones' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST urdido/programar-urdido/intercambiar-prioridad' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
        'POST urdido/programar-urdido/marcar-incorrecto' => 'pendiente de idrol: módulo por nombre (20-03-MAPA-AUTHZ §Pendientes)',
    ];

    private const ACCIONES = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];

    /** @return list<RutaLaravel> */
    private function escriturasPropiasFueraDePlaneacion(): array
    {
        $snapshot = json_decode((string) file_get_contents(base_path('tests/fixtures/planeacion/programa-tejido/rutas.json')), true, flags: JSON_THROW_ON_ERROR);
        $planeacion = array_flip(array_column($snapshot['rutas'], 'uri'));

        return array_values(array_filter(Route::getRoutes()->getRoutes(), function (RutaLaravel $ruta) use ($planeacion): bool {
            return array_intersect($ruta->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []
                && ! isset($planeacion[$ruta->uri()])
                && $ruta->getActionName() !== RedirectController::class
                && $this->esDeLaApp($ruta);
        }));
    }

    /** Controller de App\ o closure escrito en routes/ (fuera quedan Livewire, debugbar, storage…). */
    private function esDeLaApp(RutaLaravel $ruta): bool
    {
        $uses = $ruta->getAction('uses');

        if ($uses instanceof Closure) {
            return str_starts_with((string) (new ReflectionFunction($uses)->getFileName()), base_path('routes'));
        }

        return is_string($uses) && str_starts_with($uses, 'App\\');
    }

    /** @return list<string> */
    private function permisosDe(RutaLaravel $ruta): array
    {
        return array_values(preg_grep('/^module\.permission:/', $ruta->gatherMiddleware()) ?: []);
    }

    private function clave(RutaLaravel $ruta): string
    {
        return implode('|', array_diff($ruta->methods(), ['HEAD'])).' '.$ruta->uri();
    }

    public function test_toda_escritura_fuera_de_planeacion_tiene_permiso_de_modulo_o_esta_justificada(): void
    {
        $sinPermiso = [];
        foreach ($this->escriturasPropiasFueraDePlaneacion() as $ruta) {
            if ($this->permisosDe($ruta) === []) {
                $sinPermiso[] = $this->clave($ruta);
            }
        }
        sort($sinPermiso);
        $justificadas = array_keys(self::SIN_PERMISO_DE_MODULO);
        sort($justificadas);

        $this->assertSame(
            $justificadas,
            $sinPermiso,
            'Ruta de escritura sin module.permission: agrega module.permission:<accion>,<idrol>[,auditar] o justifícala aquí',
        );
    }

    public function test_los_parametros_de_module_permission_son_validos(): void
    {
        $invalidos = [];
        foreach ($this->escriturasPropiasFueraDePlaneacion() as $ruta) {
            foreach ($this->permisosDe($ruta) as $middleware) {
                $partes = array_map('trim', explode(',', substr($middleware, strlen('module.permission:'))));
                [$accion, $modulo, $modo] = array_pad($partes, 3, null);

                if (count($partes) > 3 || ! in_array($accion, self::ACCIONES, true)
                    || $modulo === null || $modulo === '' || ! in_array($modo, [null, 'auditar'], true)) {
                    $invalidos[] = $this->clave($ruta).' => '.$middleware;
                }
            }
        }

        $this->assertSame([], $invalidos);
    }

    public function test_hay_rutas_en_modo_auditar(): void
    {
        $auditadas = array_filter(
            $this->escriturasPropiasFueraDePlaneacion(),
            fn (RutaLaravel $ruta): bool => preg_grep('/,auditar$/', $this->permisosDe($ruta)) !== [],
        );

        // 63 al cierre de 20-03; baja a medida que cada 19-xx pasa a enforce (SEC-06).
        $this->assertGreaterThan(0, count($auditadas));
    }

    public function test_paros_quedan_abiertos(): void
    {
        foreach (['api.mantenimiento.paros.store', 'api.mantenimiento.paros.finalizar'] as $nombre) {
            $this->assertSame([], $this->permisosDe(Route::getRoutes()->getByName($nombre)), $nombre);
        }
    }

    public function test_los_gates_en_modo_auditar_van_por_idrol(): void
    {
        foreach ($this->escriturasPropiasFueraDePlaneacion() as $ruta) {
            foreach (preg_grep('/,auditar$/', $this->permisosDe($ruta)) as $middleware) {
                $this->assertMatchesRegularExpression('/^module\.permission:[a-z]+,\d+,auditar$/', $middleware, $this->clave($ruta));
            }
        }
    }
}
