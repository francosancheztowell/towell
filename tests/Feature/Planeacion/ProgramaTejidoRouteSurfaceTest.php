<?php

namespace Tests\Feature\Planeacion;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PT-01 · 01.1 — Superficie HTTP completa de Planeación / Programa Tejido / Muestras.
 *
 * Snapshot versionado en tests/fixtures/planeacion/programa-tejido/rutas.json
 * (minúscula a propósito: tests/fixtures ya existe y en Windows/Laragon
 * "Fixtures" y "fixtures" son la misma carpeta).
 *
 * Cualquier alta, baja o cambio de método/URI/nombre/middleware/action falla.
 * Si el cambio es intencional, regenerar y revisar el diff en el PR:
 *   PT_ROUTES_SNAPSHOT=update php artisan test --filter=ProgramaTejidoRouteSurfaceTest
 */
class ProgramaTejidoRouteSurfaceTest extends TestCase
{
    private const SNAPSHOT = 'tests/fixtures/planeacion/programa-tejido/rutas.json';

    /**
     * Diferencias Programa ↔ Muestras ya revisadas. Clave = método + URI normalizada a
     * Programa. 'intencional' = así debe ser; 'gap' = defecto conocido, se corrige en
     * otra fase (ver 01-DECISION-PROGRAMA-MUESTRAS.md). Una diferencia nueva sin
     * clasificar rompe el test.
     */
    private const DIFERENCIAS = [
        // Redbooth: exclusiva de Programa (decisión 01.3 B, PT-02). La UI de Muestras la oculta y
        // el controller responde 422 si alguna vez se enruta desde Muestras.
        'GET planeacion/programa-tejido/redbooth/proyectos' => ['intencional', 'redbooth exclusiva de Programa (01.3 B)'],
        'POST planeacion/programa-tejido/redbooth' => ['intencional', 'redbooth exclusiva de Programa (01.3 B)'],
        'GET planeacion/programa-tejido/redbooth/{programa}' => ['intencional', 'redbooth exclusiva de Programa (01.3 B)'],
        'DELETE planeacion/programa-tejido/redbooth/{programa}' => ['intencional', 'redbooth exclusiva de Programa (01.3 B)'],
        // Editor de marbetes: marbetes es A (01.3), pero Muestras no tiene NoMarbete/RollosProgramados
        // hasta aplicar database/sql/pt_muestras_marbetes.sql. Se abre en PT-05/06 (02-MUESTRAS-LIBERAR.md).
        'GET planeacion/programa-tejido/marbetes' => ['gap', 'editor de marbetes sin ruta en Muestras (espera DDL 01.3 A)'],
        'POST planeacion/programa-tejido/marbetes' => ['gap', 'editor de marbetes sin ruta en Muestras (espera DDL 01.3 A)'],
        // Auditoría lee solo el historial de Programa; sin requerimiento para Muestras.
        'GET planeacion/programa-tejido/auditoria' => ['intencional', 'auditoría solo de Programa'],
    ];

    public function test_la_superficie_http_coincide_con_el_snapshot(): void
    {
        $actual = $this->inventario();
        $ruta = base_path(self::SNAPSHOT);

        if (getenv('PT_ROUTES_SNAPSHOT') === 'update') {
            if (! is_dir(dirname($ruta))) {
                mkdir(dirname($ruta), 0775, true);
            }
            // Una ruta / par por línea: el diff del PR muestra exactamente qué contrato cambió.
            $linea = fn ($v) => json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $bloque = fn (array $filas, bool $conClave) => implode(",\n", array_map(
                fn ($k, $v) => '    '.($conClave ? $linea((string) $k).': ' : '').$linea($v),
                array_keys($filas),
                $filas
            ));
            file_put_contents($ruta, "{\n  \"totales\": ".$linea($actual['totales']).",\n"
                ."  \"rutas\": [\n".$bloque($actual['rutas'], false)."\n  ],\n"
                ."  \"paridad\": {\n".$bloque($actual['paridad'], true)."\n  }\n}\n");
        }

        $this->assertFileExists($ruta, 'Falta el snapshot: correr con PT_ROUTES_SNAPSHOT=update');
        $esperado = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);

        $clave = fn (array $r) => $r['metodos'].' '.$r['uri'];
        $esperadoPorClave = array_column(array_map(fn ($r) => [$clave($r), $r], $esperado['rutas']), 1, 0);
        $actualPorClave = array_column(array_map(fn ($r) => [$clave($r), $r], $actual['rutas']), 1, 0);

        $this->assertSame([], array_values(array_diff(array_keys($actualPorClave), array_keys($esperadoPorClave))), 'Rutas nuevas no aprobadas');
        $this->assertSame([], array_values(array_diff(array_keys($esperadoPorClave), array_keys($actualPorClave))), 'Rutas eliminadas no aprobadas');
        foreach ($esperadoPorClave as $k => $fila) {
            $this->assertSame($fila, $actualPorClave[$k], "Contrato cambiado: {$k}");
        }
        $this->assertSame($esperado['paridad'], $actual['paridad'], 'La paridad Programa/Muestras cambió');
        $this->assertSame($esperado['totales'], $actual['totales']);
    }

    public function test_las_197_rutas_bajo_planeacion_tienen_superficie_y_capacidad(): void
    {
        $bajoPlaneacion = array_filter($this->inventario()['rutas'], fn ($r) => str_starts_with($r['uri'], 'planeacion'));

        // Línea base del research (artisan route:list --path=planeacion, 2026-07-22): 195.
        // PT-02 suma 2: lectura v2 de Programa y de Muestras (planeacion/*/v2/registros).
        // ERP-F0-08 (fase 08, main) quita 2 sin consumidor: codificacion-modelos/buscar y codificacion/api/recalcular-marbetes.
        // + 1: redirect /planeacion/catalogos/catalogoCodificacion (404 del menú, 2026-09-25).
        $this->assertCount(196, $bajoPlaneacion);
        foreach ($bajoPlaneacion as $r) {
            $this->assertNotSame('', $r['capacidad'], "Sin capacidad: {$r['metodos']} {$r['uri']}");
        }
    }

    public function test_toda_diferencia_programa_muestras_esta_clasificada(): void
    {
        $sinClasificar = [];
        foreach ($this->inventario()['paridad'] as $clave => $estado) {
            if ($estado['estado'] === 'igual') {
                continue;
            }
            if (! isset(self::DIFERENCIAS[$clave]) || self::DIFERENCIAS[$clave][0] === 'igual') {
                $sinClasificar[$clave] = $estado;
            }
        }

        $this->assertSame([], $sinClasificar, 'Diferencias Programa/Muestras sin clasificar (intencional o gap)');
    }

    public function test_las_mutaciones_de_muestras_exigen_el_modulo_de_muestras(): void
    {
        $modulo = config('planeacion.superficies.muestras.modulo_permiso');
        $ajenas = [];

        foreach ($this->inventario()['rutas'] as $r) {
            if ($r['superficie'] !== 'muestras') {
                continue;
            }
            foreach ($r['middleware'] as $m) {
                if (preg_match('/^module\.permission:\w+,(\d+)$/', $m, $x) && (int) $x[1] !== $modulo) {
                    $ajenas[] = "{$r['metodos']} {$r['uri']} → {$m}";
                }
            }
        }

        // PT-01.1 corrigió el único gap (liberar exigía crear,2): ninguna ruta de Muestras
        // valida con el módulo de Programa.
        $this->assertSame([], $ajenas);
    }

    /**
     * @return array{totales: array<string, int>, rutas: list<array<string, mixed>>, paridad: array<string, array<string, string>>}
     */
    private function inventario(): array
    {
        $rutas = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! $this->enAlcance($uri)) {
                continue;
            }

            $superficie = $this->superficie($uri);
            $rutas[] = [
                'metodos' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $uri,
                'nombre' => $route->getName(),
                'middleware' => array_values($route->gatherMiddleware()),
                'accion' => $this->accion($route),
                'superficie' => $superficie,
                'capacidad' => $this->capacidad($uri, $superficie),
            ];
        }

        usort($rutas, fn ($a, $b) => [$a['uri'], $a['metodos']] <=> [$b['uri'], $b['metodos']]);

        $totales = ['total' => count($rutas)];
        foreach ($rutas as $r) {
            $totales[$r['superficie']] = ($totales[$r['superficie']] ?? 0) + 1;
        }
        ksort($totales);

        return ['totales' => $totales, 'rutas' => $rutas, 'paridad' => $this->paridad($rutas)];
    }

    private function enAlcance(string $uri): bool
    {
        return str_starts_with($uri, 'planeacion')
            || str_starts_with($uri, 'programa-tejido/')
            || str_starts_with($uri, 'muestras/')
            || str_starts_with($uri, 'modulo-codificaci');
    }

    private function superficie(string $uri): string
    {
        return match (true) {
            str_starts_with($uri, 'planeacion/muestras'), str_starts_with($uri, 'muestras/') => 'muestras',
            str_starts_with($uri, 'planeacion/programa-tejido'), str_starts_with($uri, 'programa-tejido/'),
            str_starts_with($uri, 'planeacion/req-programa-tejido-line'), str_starts_with($uri, 'planeacion/utileria'),
            $uri === 'planeacion/programatejido' => 'programa',
            default => 'planeacion',
        };
    }

    private function capacidad(string $uri, string $superficie): string
    {
        if ($superficie === 'planeacion') {
            // Catálogos, codificación, LMat, alineación: primer segmento funcional.
            $partes = explode('/', $uri);

            return $partes[1] ?? $partes[0];
        }

        $reglas = [
            'redbooth' => '#/redbooth#',
            'marbetes' => '#/marbetes#',
            'liberar' => '#/liberar-ordenes#',
            'descarga' => '#/descargar-programa#',
            'reimpresion' => '#/reimprimir-ordenes#',
            'finalizacion' => '#utileria/finalizar#',
            'mover' => '#utileria/mover#',
            'utileria' => '#utileria#',
            'auditoria' => '#/auditoria#',
            'balanceo' => '#balance#',
            'grupos' => '#duplicar|dividir|vincular|desvincular|ord-compartida#',
            'secuencia' => '#prioridad/mover|cambio-telar|cambiar-telar#',
            'calendario' => '#calendarios-masivo|reprogramar|recalcular-fechas|all-registros-json|calendario-#',
            'repaso' => '#crear-repaso#',
            'columnas' => '#/columnas#',
            'lineas' => '#-line$#',
            'catalogos' => '#^(programa-tejido|muestras)/#',
            'redireccion' => '#^planeacion/programatejido$#',
        ];
        foreach ($reglas as $capacidad => $patron) {
            if (preg_match($patron, $uri)) {
                return $capacidad;
            }
        }

        return preg_match('#\{id\}$#', $uri) ? 'edicion' : 'lectura';
    }

    private function accion(IlluminateRoute $route): string
    {
        $accion = $route->getActionName();

        return $accion === 'Closure' ? 'Closure' : str_replace('App\\Http\\Controllers\\', '', $accion);
    }

    /**
     * Empareja cada ruta de Muestras con su equivalente de Programa (misma URI tras
     * normalizar el prefijo) y compara action + middleware, con el módulo 5↔2 mapeado.
     *
     * @param  list<array<string, mixed>>  $rutas
     * @return array<string, array<string, string>>
     */
    private function paridad(array $rutas): array
    {
        $modMuestras = config('planeacion.superficies.muestras.modulo_permiso');
        $modPrograma = config('planeacion.superficies.programa.modulo_permiso');
        $aPrograma = fn (string $uri) => preg_replace(
            ['#^planeacion/muestras-line#', '#^planeacion/muestras#', '#^muestras/#'],
            ['planeacion/req-programa-tejido-line', 'planeacion/programa-tejido', 'programa-tejido/'],
            $uri
        );
        // El módulo propio de cada superficie se normaliza a ",<propio>"; un módulo ajeno
        // (p. ej. Muestras validando con el 2 de Programa) queda visible como diferencia.
        $contrato = fn (array $r, bool $esMuestras) => $r['accion'].' ['.implode(', ', array_map(
            fn ($m) => preg_replace('#^(module\\.permission:\\w+),'.($esMuestras ? $modMuestras : $modPrograma).'$#', '$1,<propio>', $m),
            $r['middleware']
        )).']';

        $programa = [];
        $muestras = [];
        foreach ($rutas as $r) {
            if ($r['superficie'] === 'programa' && ! in_array($r['capacidad'], ['finalizacion', 'mover', 'utileria', 'redireccion'], true)) {
                $programa[$r['metodos'].' '.$r['uri']] = $r;
            } elseif ($r['superficie'] === 'muestras') {
                $muestras[$r['metodos'].' '.$aPrograma($r['uri'])] = $r;
            }
        }

        $resultado = [];
        foreach (array_unique([...array_keys($programa), ...array_keys($muestras)]) as $clave) {
            $p = $programa[$clave] ?? null;
            $m = $muestras[$clave] ?? null;
            $resultado[$clave] = match (true) {
                $m === null => ['estado' => 'solo_programa'],
                $p === null => ['estado' => 'solo_muestras'],
                $contrato($p, false) === $contrato($m, true) => ['estado' => 'igual'],
                default => ['estado' => 'difiere', 'programa' => $contrato($p, false), 'muestras' => $contrato($m, true)],
            };
        }
        ksort($resultado);

        return $resultado;
    }
}
