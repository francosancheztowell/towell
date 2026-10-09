<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lecturas JSON de órdenes de trabajo: listado filtrado, una orden y paros elegibles. */
class ConsultaOrdenesTrabajoController extends Controller
{
    private const HORAS_HISTORIAL_PAROS = 16;

    /** ponytail: tope del listado sin paginar; con "Todos" ya no trae la tabla entera. PaginacionCompat si hace falta ver más. */
    private const LIMITE_REGISTROS = 500;

    /** Filtro de la UI → columna, comparadas con LIKE. */
    private const FILTROS_LIKE = [
        'folio' => 'Folio',
        'telar' => 'TelarId',
        'folio_paro' => 'FolioParo',
        'orden' => 'Orden',
        'falla' => 'Falla',
        'tipo_falla' => 'TipoFalla',
    ];

    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function registros(Request $request): JsonResponse
    {
        $datos = array_map(fn ($valor): string => trim((string) $valor), $request->validate([
            'fecha' => ['nullable', 'date'],
            'estatus' => ['nullable', 'string', 'max:15'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'folio' => ['nullable', 'string', 'max:30'],
            'telar' => ['nullable', 'string', 'max:50'],
            'folio_paro' => ['nullable', 'string', 'max:30'],
            'orden' => ['nullable', 'string', 'max:20'],
            'falla' => ['nullable', 'string', 'max:150'],
            'tipo_falla' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:5'],
            'mecanico' => ['nullable', 'string', 'max:150'],
        ]));
        // Sin when(): when('0') es falso y "0" es una búsqueda válida (telar 201).
        $datos = array_filter($datos, fn (string $valor): bool => $valor !== '');

        $query = MecOrdenTrabajoModel::query()->addSelect(['NomMecanico' => MecOrdenTrabajoLineModel::query()
            ->select('NomOperador')
            ->whereColumn('MecOrdenTrabajoLine.Folio', 'MecOrdenTrabajoTable.Folio')
            ->where('NomOperador', '!=', '')
            ->orderBy('Id')
            ->limit(1),
        ]);

        if (isset($datos['fecha'])) {
            $query->whereDate('Fecha', $datos['fecha']);
        }
        if (isset($datos['estatus'])) {
            $this->filtrarEstatus($query, $datos['estatus']);
        }
        if (isset($datos['turno'])) {
            $query->where('Turno', $datos['turno']);
        }
        if (isset($datos['mecanico'])) {
            $query->whereExists($this->conMecanico($datos['mecanico']));
        }
        foreach (array_intersect_key(self::FILTROS_LIKE, $datos) as $clave => $columna) {
            $query->where($columna, 'like', "%{$datos[$clave]}%");
        }
        if (isset($datos['buscar'])) {
            $query->where(function (Builder $o) use ($datos): void {
                foreach (self::FILTROS_LIKE as $columna) {
                    $o->orWhere($columna, 'like', "%{$datos['buscar']}%");
                }
                $o->orWhereExists($this->conMecanico($datos['buscar']));
            });
        }

        $this->acceso->filtrarTelaresTejedor($query);

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('Fecha')->orderByDesc('Folio')->limit(self::LIMITE_REGISTROS)->get(),
        ]);
    }

    public function show(string $folio): JsonResponse
    {
        $orden = $this->acceso->orden($folio);

        return response()->json(['success' => true, 'data' => $orden->load('lineas')]);
    }

    /**
     * Paros de un telar de las últimas 16 horas, elegibles para crear una OT.
     *
     * Un mismo paro puede originar varias órdenes (una intervención puede requerir
     * varios pases), así que no se excluyen los folios ya ligados a otra orden.
     */
    public function parosHistorial(Request $request): JsonResponse
    {
        $datos = $request->validate(['TelarId' => ['required', 'string', 'max:50']]);
        $desde = Carbon::now('America/Mexico_City')->subHours(self::HORAS_HISTORIAL_PAROS);

        $paros = ManFallasParos::query()
            ->where('MaquinaId', trim($datos['TelarId']))
            ->where('Folio', '<>', '')
            ->where(fn (Builder $q) => $q->where('Fecha', '>', $desde->toDateString())
                ->orWhere(fn (Builder $mismoDia) => $mismoDia
                    ->where('Fecha', $desde->toDateString())
                    ->where('Hora', '>=', $desde->format('H:i:s'))))
            ->orderByDesc('Fecha')
            ->orderByDesc('Hora')
            ->get(['Id', 'Folio', 'Fecha', 'Hora', 'MaquinaId', 'Falla', 'Descripcion', 'OrdenTrabajo', 'Turno', 'Estatus', 'Obs', 'ObsCierre'])
            ->each(fn (ManFallasParos $paro) => $paro->setAttribute('FallaTexto', OrdenTrabajoDatos::textoFalla($paro->Falla, $paro->Descripcion))
                ->setAttribute('ComentariosTexto', OrdenTrabajoDatos::textoComentariosParo($paro)));

        return response()->json([
            'success' => true,
            'data' => $paros,
            'captura_manual_permitida' => $paros->isEmpty(),
        ]);
    }

    /** Activo incluye las filas con Estatus NULL o vacío (ver MecOrdenTrabajoModel::estatus). */
    private function filtrarEstatus(Builder $query, string $estatus): void
    {
        if ($estatus !== MecOrdenTrabajoModel::ESTATUS_ACTIVO) {
            $query->where('Estatus', $estatus);

            return;
        }

        $query->where(fn (Builder $q) => $q->where('Estatus', $estatus)->orWhereNull('Estatus')->orWhere('Estatus', ''));
    }

    private function conMecanico(string $nombre): \Closure
    {
        return fn (QueryBuilder $exists) => $exists->selectRaw('1')
            ->from('MecOrdenTrabajoLine')
            ->whereColumn('MecOrdenTrabajoLine.Folio', 'MecOrdenTrabajoTable.Folio')
            ->where('NomOperador', 'like', "%{$nombre}%");
    }
}
