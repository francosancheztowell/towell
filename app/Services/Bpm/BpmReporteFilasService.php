<?php

declare(strict_types=1);

namespace App\Services\Bpm;

use App\Support\Bpm\AreaBpm;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Filas del reporte BPM (vista y Excel): una por línea de checklist, con la cabecera repetida.
 * Cabecera y líneas van en dos consultas para no repetir los campos de cabecera en cada línea.
 */
final class BpmReporteFilasService
{
    public function filas(AreaBpm $area, string $fechaIni, string $fechaFin, bool $soloFinalizados): Collection
    {
        $cabeceras = $this->cabeceras($area, $fechaIni, $fechaFin, $soloFinalizados);
        if ($cabeceras->isEmpty()) {
            return collect();
        }

        $lineas = $this->lineasPorFolio($area, $cabeceras->pluck('Folio')->all());

        return self::marcarInicio($this->expandir($area, $cabeceras, $lineas));
    }

    public static function mapearValor(int $valor): string
    {
        if ($valor === 1) {
            return 'CORRECTO';
        }
        if ($valor === 2) {
            return 'INCORRECTO';
        }

        return 'S/N';
    }

    public static function normalizarClave(mixed $clave): ?int
    {
        if ($clave === null || $clave === '') {
            return null;
        }

        $texto = trim((string) $clave);
        if ($texto === '' || ! is_numeric($texto)) {
            return null;
        }

        return (int) $texto;
    }

    public static function marcarInicio(Collection $filas): Collection
    {
        $folioAnterior = null;

        return $filas->map(function ($fila) use (&$folioAnterior) {
            $folioActual = (string) ($fila->Folio ?? '');
            $fila->InicioFolio = ($folioActual !== '' && $folioActual !== $folioAnterior) ? '•' : null;
            $folioAnterior = $folioActual;

            return $fila;
        });
    }

    private function cabeceras(AreaBpm $area, string $fechaIni, string $fechaFin, bool $soloFinalizados): Collection
    {
        $modelo = $area->modelo();

        return $modelo::query()
            // Fecha es datetime: intervalo semiabierto [ini, fin+1dia) para no perder
            // los registros del ultimo dia capturados despues de medianoche.
            ->where('Fecha', '>=', $fechaIni)
            ->where('Fecha', '<', Carbon::parse($fechaFin)->addDay()->toDateString())
            ->when($soloFinalizados, fn ($q) => $q->whereIn('Status', ['Terminado', 'Autorizado']))
            ->orderBy('Folio')
            ->get([
                'Folio', 'Status', 'Fecha',
                'CveEmplEnt', 'NombreEmplEnt', 'TurnoEntrega',
                'CveEmplRec', 'NombreEmplRec', 'TurnoRecibe',
                'CveEmplAutoriza', $area->columnaAutoriza(),
            ]);
    }

    /**
     * @param  array<int, mixed>  $folios
     */
    private function lineasPorFolio(AreaBpm $area, array $folios): Collection
    {
        $cabecera = new ($area->modelo());
        $linea = new ($area->modeloLinea());

        return DB::connection($cabecera->getConnectionName())
            ->table($linea->getTable())
            ->whereIn('Folio', $folios)
            ->orderBy('Orden')
            ->get(['Folio', 'Orden', 'Actividad', 'Valor'])
            ->groupBy('Folio');
    }

    private function expandir(AreaBpm $area, Collection $cabeceras, Collection $lineasPorFolio): Collection
    {
        $filas = collect();
        $columnaAutoriza = $area->columnaAutoriza();

        foreach ($cabeceras as $cabecera) {
            $base = $this->base($cabecera, $columnaAutoriza);
            $lineas = $lineasPorFolio->get($cabecera->Folio) ?? collect([null]);

            foreach ($lineas as $linea) {
                $filas->push((object) ($base + $this->lineaDe(is_object($linea) ? $linea : null)));
            }
        }

        return $filas;
    }

    /**
     * Un folio sin líneas sigue saliendo una vez, igual que el leftJoin de antes.
     *
     * @return array{Orden: mixed, Actividad: mixed, Valor: mixed, ValorTexto: string}
     */
    private function lineaDe(?object $linea): array
    {
        if ($linea === null) {
            return [
                'Orden' => null,
                'Actividad' => null,
                'Valor' => null,
                'ValorTexto' => self::mapearValor(0),
            ];
        }

        return [
            'Orden' => $linea->Orden ?? null,
            'Actividad' => $linea->Actividad ?? null,
            'Valor' => $linea->Valor ?? null,
            'ValorTexto' => self::mapearValor((int) ($linea->Valor ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function base(object $cabecera, string $columnaAutoriza): array
    {
        return [
            'Folio' => $cabecera->Folio,
            'Status' => $cabecera->Status,
            'Fecha' => $cabecera->Fecha,
            'CveEmplEnt' => self::normalizarClave($cabecera->CveEmplEnt ?? null),
            'NombreEmplEnt' => $cabecera->NombreEmplEnt,
            'TurnoEntrega' => $cabecera->TurnoEntrega,
            'CveEmplRec' => self::normalizarClave($cabecera->CveEmplRec ?? null),
            'NombreEmplRec' => $cabecera->NombreEmplRec,
            'TurnoRecibe' => $cabecera->TurnoRecibe,
            'CveEmplAutoriza' => self::normalizarClave($cabecera->CveEmplAutoriza ?? null),
            'NombreEmplAutoriza' => $cabecera->{$columnaAutoriza} ?? null,
        ];
    }
}
