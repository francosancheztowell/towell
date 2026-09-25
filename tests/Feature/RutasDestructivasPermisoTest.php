<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Toda ruta que borra o finaliza algo exige permiso de modulo en el router.
 *
 * userCan() vivia solo en vistas y en 18 de 139 controllers, asi que el menu ocultaba
 * el boton pero la URL seguia respondiendo a cualquier usuario autenticado.
 */
class RutasDestructivasPermisoTest extends TestCase
{
    /**
     * Excepciones deliberadas, con su razon. Solo se agrega aqui por decision explicita.
     *
     * @var array<string, string>
     */
    private const EXENTAS = [
        // Franco pidio revertir el gate: cualquier operador puede reportar y cerrar un paro.
        // Lo cubre MantenimientoParosAuthorizationTest.
        'api/mantenimiento/paros/{id}/finalizar' => 'decision de negocio: el piso cierra sus propios paros',
    ];

    public function test_toda_ruta_destructiva_exige_permiso_de_modulo(): void
    {
        $sinGate = [];

        foreach (Route::getRoutes() as $ruta) {
            if (! $this->esDestructiva($ruta)) {
                continue;
            }
            if (array_key_exists($ruta->uri(), self::EXENTAS)) {
                continue;
            }
            if ($this->gatesDe($ruta) === []) {
                $sinGate[] = implode('|', $this->verbos($ruta)).' /'.$ruta->uri();
            }
        }

        sort($sinGate);
        $this->assertSame([], $sinGate, "Rutas destructivas sin module.permission:\n".implode("\n", $sinGate));
    }

    /**
     * Autorizar/rechazar es visto bueno de supervision, no edicion: quien llena el check-list
     * no debe poder firmarlo. La convencion del repo para eso es `registrar`
     * (ver app/Livewire/Mecanicos/VerificaMaquina/Show.php:177).
     *
     * @return array<string, array{0: string}>
     */
    public static function rutasDeAutorizacion(): array
    {
        return [
            'eng-bpm-line autorizar' => ['eng-bpm-line.autorizar'],
            'eng-bpm-line rechazar' => ['eng-bpm-line.rechazar'],
            'urd-bpm-line autorizar' => ['urd-bpm-line.autorizar'],
            'urd-bpm-line rechazar' => ['urd-bpm-line.rechazar'],
            'tel-bpm autorizar' => ['tel-bpm.authorize'],
            'tel-bpm rechazar' => ['tel-bpm.reject'],
            'ordenes de trabajo autorizar' => ['mecanicos.ordenes-trabajo.autorizar'],
        ];
    }

    #[DataProvider('rutasDeAutorizacion')]
    public function test_autorizar_y_rechazar_exigen_registrar(string $nombreRuta): void
    {
        $ruta = Route::getRoutes()->getByName($nombreRuta);

        $this->assertNotNull($ruta, "No se encontro la ruta [{$nombreRuta}].");

        $acciones = array_column($this->gatesDe($ruta), 0);

        $this->assertContains(
            'registrar',
            $acciones,
            "[{$nombreRuta}] no exige 'registrar'. Con 'modificar' el mismo operador que llena ".
            'el check-list podria firmarlo.'
        );
    }

    /**
     * Todo gate va por SYSRoles.idrol, nunca por nombre.
     *
     * userPermissions() indexa con keyBy(strtolower(modulo)) y hay 5 nombres repetidos en
     * SYSRoles, asi que un gate por nombre resuelve a una fila arbitraria: la query no lleva
     * ORDER BY. Paso en produccion con "Utileria", que validaba el modulo de Configuracion
     * en pantallas de Planeacion y negaba el permiso a 54 personas que si lo tenian.
     */
    public function test_todo_gate_se_referencia_por_idrol(): void
    {
        $porNombre = [];

        foreach (Route::getRoutes() as $ruta) {
            foreach ($this->gatesDe($ruta) as [$accion, $modulo]) {
                if (! ctype_digit($modulo)) {
                    $porNombre[] = "/{$ruta->uri()} usa [{$modulo}]; pasar el idrol de SYSRoles";
                }
            }
        }

        $porNombre = array_values(array_unique($porNombre));
        sort($porNombre);
        $this->assertSame([], $porNombre, "Gates por nombre en vez de idrol:\n".implode("\n", $porNombre));
    }

    public function test_la_excepcion_de_paros_sigue_existiendo_y_sin_gate(): void
    {
        $ruta = Route::getRoutes()->getByName('api.mantenimiento.paros.finalizar');

        $this->assertNotNull($ruta, 'Desaparecio api.mantenimiento.paros.finalizar.');
        $this->assertSame(
            [],
            $this->gatesDe($ruta),
            'Se le puso gate a finalizar paro; era una decision de negocio explicita (ver EXENTAS).'
        );
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function gatesDe(IlluminateRoute $ruta): array
    {
        $gates = [];
        foreach ($ruta->gatherMiddleware() as $mw) {
            if (! is_string($mw) || ! str_starts_with($mw, 'module.permission:')) {
                continue;
            }
            [, $args] = explode(':', $mw, 2);
            // El tercer parámetro opcional es el modo (`auditar`, SEC-05).
            [$accion, $modulo] = array_pad(explode(',', $args), 2, '');
            $gates[] = [trim($accion), trim($modulo)];
        }

        return $gates;
    }

    /** @return array<int, string> */
    private function verbos(IlluminateRoute $ruta): array
    {
        return array_values(array_diff($ruta->methods(), ['HEAD']));
    }

    private function esDestructiva(IlluminateRoute $ruta): bool
    {
        $accion = is_string($ruta->getAction('uses')) ? $ruta->getAction('uses') : 'Closure';

        // Route::redirect() registra todos los verbos pero no muta nada.
        if (str_contains($accion, 'RedirectController') || str_starts_with($ruta->uri(), '_debugbar')) {
            return false;
        }

        $verbos = $this->verbos($ruta);
        if (! array_intersect($verbos, ['DELETE', 'POST', 'PUT', 'PATCH'])) {
            return false;
        }

        return in_array('DELETE', $verbos, true)
            || (bool) preg_match('/(destroy|elimina|borra|cancela|anula|finaliza)/i', $accion.' '.$ruta->uri());
    }
}
