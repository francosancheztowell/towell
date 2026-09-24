<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Planeacion\Catalogos\CatCodificados;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gate de invariantes de Programa Tejido / Muestras (PT-01, 01.5).
 *
 * Solo SELECT: no escribe, no bloquea (sin transacción) y no dispara observers.
 * Las tablas salen de config('planeacion.superficies'), no del contexto por URL.
 *
 * Severidad "error" = invariante con línea base 0 verificada en live (research
 * 2026-07-22); "aviso" = regla por confirmar con datos reales, no rompe el gate.
 *
 * Exit: 0 sin errores · 1 alguna invariante "error" > 0 · 2 no se pudo consultar.
 */
final class PlaneacionProgramaTejidoHealthCheck extends Command
{
    protected $signature = 'planeacion:programa-tejido-health
        {--superficie=todas : programa, muestras o todas}
        {--json : Salida JSON estructurada}';

    protected $description = 'Verifica (solo lectura) posiciones, EnProceso, líneas, grupos y CatCodificados de Programa Tejido / Muestras.';

    public function handle(): int
    {
        $superficies = (array) config('planeacion.superficies', []);
        $pedida = (string) $this->option('superficie');
        $nombres = $pedida === 'todas' ? array_keys($superficies) : [$pedida];

        // Sin superficies (p. ej. config cacheada antes de existir config/planeacion.php)
        // no se revisó nada: eso no es "sano".
        if ($nombres === []) {
            $this->error('config(planeacion.superficies) está vacío: correr php artisan config:clear.');

            return 2;
        }

        foreach ($nombres as $nombre) {
            if (! isset($superficies[$nombre])) {
                $this->error("Superficie desconocida: {$nombre}. Usa programa, muestras o todas.");

                return 2;
            }
        }

        $db = DB::connection();
        $reporte = [
            'generado' => now()->toIso8601String(),
            'conexion' => $db->getName(),
            'ok' => true,
            'superficies' => [],
        ];
        $exit = 0;

        foreach ($nombres as $nombre) {
            try {
                $checks = $this->checks($db, $superficies[$nombre]['tabla'], $superficies[$nombre]['tabla_lineas']);
            } catch (Throwable $e) {
                $reporte['ok'] = false;
                $reporte['superficies'][$nombre] = ['error' => $e->getMessage()];
                $exit = 2;

                continue;
            }

            $reporte['superficies'][$nombre] = [
                'tabla' => $superficies[$nombre]['tabla'],
                'tabla_lineas' => $superficies[$nombre]['tabla_lineas'],
                'checks' => $checks,
            ];

            foreach ($checks as $check) {
                if (! $check['ok']) {
                    $reporte['ok'] = false;
                    $exit = max($exit, 1);
                }
            }
        }

        $this->option('json')
            ? $this->line((string) json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE))
            : $this->imprimir($reporte);

        return $exit;
    }

    /**
     * @return list<array{id: string, severidad: string, valor: int, ok: bool, descripcion: string}>
     */
    private function checks(ConnectionInterface $db, string $tabla, string $tablaLineas): array
    {
        $t = fn (): Builder => $db->table($tabla);
        $grupos = fn (Builder $q): int => $db->query()->fromSub($q, 'g')->count();

        $valores = [
            ['cabeceras', 'info', $t()->count(), 'Filas de cabecera'],
            ['lineas', 'info', $db->table($tablaLineas)->count(), 'Filas de líneas diarias'],

            ['posiciones_duplicadas', 'error', $grupos(
                $t()->select('SalonTejidoId', 'NoTelarId', 'Posicion')
                    ->whereNotNull('Posicion')
                    ->groupBy('SalonTejidoId', 'NoTelarId', 'Posicion')
                    ->havingRaw('COUNT(*) > 1')
            ), 'Grupos (SalonTejidoId, NoTelarId, Posicion) repetidos'],

            ['telares_multi_en_proceso', 'error', $grupos(
                $t()->select('SalonTejidoId', 'NoTelarId')
                    ->where('EnProceso', 1)
                    ->groupBy('SalonTejidoId', 'NoTelarId')
                    ->havingRaw('COUNT(*) > 1')
            ), 'Telares con más de un EnProceso = 1'],

            ['posiciones_nulas', 'error', $t()->whereNull('Posicion')->count(), 'Cabeceras sin Posicion'],

            ['lineas_huerfanas', 'error', $db->table($tablaLineas.' as l')
                ->whereNotExists(fn (Builder $q) => $q->from($tabla.' as p')->whereColumn('p.Id', 'l.ProgramaId'))
                ->count(), 'Líneas cuyo ProgramaId no existe en su cabecera'],

            // Mismo criterio que el observer para generar líneas: fechas válidas y cantidad > 0
            // (SaldoPedido ?? Produccion ?? TotalPedido).
            ['programas_sin_lineas', 'error', $db->table($tabla.' as p')
                ->whereNotNull('p.FechaInicio')
                ->whereNotNull('p.FechaFinal')
                ->whereColumn('p.FechaFinal', '>', 'p.FechaInicio')
                ->whereRaw('COALESCE(p.SaldoPedido, p.Produccion, p.TotalPedido, 0) > 0')
                ->whereNotExists(fn (Builder $q) => $q->from($tablaLineas.' as l')->whereColumn('l.ProgramaId', 'p.Id'))
                ->count(), 'Programas programados con cantidad > 0 y sin líneas'],

            ['telares_multi_ultimo', 'aviso', $grupos(
                $t()->select('SalonTejidoId', 'NoTelarId')
                    ->whereIn('Ultimo', ['1', 'UL'])
                    ->groupBy('SalonTejidoId', 'NoTelarId')
                    ->havingRaw('COUNT(*) > 1')
            ), 'Telares con más de un registro marcado Ultimo (1/UL)'],

            ['grupos_sin_un_lider', 'aviso', $grupos(
                $t()->select('OrdCompartida')
                    ->whereNotNull('OrdCompartida')
                    ->groupBy('OrdCompartida')
                    ->havingRaw('SUM(CASE WHEN OrdCompartidaLider = 1 THEN 1 ELSE 0 END) <> 1')
            ), 'OrdCompartida con cero o más de un líder'],

            ['sin_fila_cat_codificados', 'aviso', $db->table($tabla.' as p')
                ->whereNotNull('p.NoProduccion')
                ->where('p.NoProduccion', '<>', '')
                ->whereNotExists(fn (Builder $q) => $q->from((new CatCodificados)->getTable().' as c')
                    ->whereColumn('c.OrdenTejido', 'p.NoProduccion'))
                ->count(), 'Cabeceras con NoProduccion sin fila en CatCodificados'],
        ];

        return array_map(fn (array $v): array => [
            'id' => $v[0],
            'severidad' => $v[1],
            'valor' => (int) $v[2],
            'ok' => $v[1] !== 'error' || (int) $v[2] === 0,
            'descripcion' => $v[3],
        ], $valores);
    }

    private function imprimir(array $reporte): void
    {
        foreach ($reporte['superficies'] as $nombre => $datos) {
            $this->info(strtoupper($nombre).(isset($datos['tabla']) ? " ({$datos['tabla']} / {$datos['tabla_lineas']})" : ''));

            if (isset($datos['error'])) {
                $this->error('  No se pudo consultar: '.$datos['error']);

                continue;
            }

            $this->table(
                ['Check', 'Severidad', 'Valor', 'Estado'],
                array_map(fn (array $c): array => [
                    $c['id'], $c['severidad'], $c['valor'], $c['ok'] ? 'OK' : 'FALLA',
                ], $datos['checks'])
            );
        }

        $reporte['ok'] ? $this->info('Invariantes OK.') : $this->error('Hay invariantes rotas o sin consultar.');
    }
}
