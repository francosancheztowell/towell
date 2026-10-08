<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatCalendarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\CalendarioNombreRequest;
use App\Http\Requests\Planeacion\Catalogos\CalendarioRequest;
use App\Http\Requests\Planeacion\Catalogos\EliminarLineasRangoRequest;
use App\Http\Requests\Planeacion\Catalogos\ExcelCatalogoRequest;
use App\Http\Requests\Planeacion\Catalogos\LineaCalendarioRequest;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqCalendarioTab;
use App\Services\Planeacion\Calendarios\CalendarioService;
use App\Services\Planeacion\Calendarios\RecalcularProgramasCalendario;
use App\Services\Planeacion\Calendarios\TurnosCalendario;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de Calendarios (19-06b: de 1 301 a ~250 líneas). La lógica vive en
 * app/Services/Planeacion/Calendarios; aquí solo validación → servicio → respuesta.
 */
class CalendarioController extends Controller
{
    use HandlesApiErrors;

    /** Recálculos grandes: hasta 15 minutos y 1 GB (como antes). */
    private const RECALC_TIMEOUT_SECONDS = 900;

    public function index(): View
    {
        return view('catalagos.calendarios.index', [
            'calendarioTab' => ReqCalendarioTab::orderBy('CalendarioId')->get(),
            'calendarioLine' => ReqCalendarioLine::orderBy('CalendarioId')->orderBy('FechaInicio')->get(),
        ]);
    }

    public function getCalendariosJson(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => ReqCalendarioTab::orderBy('CalendarioId')->get(['CalendarioId', 'Nombre'])]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Obtener calendarios', 'Error al obtener calendarios');
        }
    }

    public function store(CalendarioRequest $request, CalendarioService $calendarios): JsonResponse
    {
        try {
            $r = $calendarios->crear($request->validated());

            return response()->json(['success' => true, 'message' => 'Calendario creado exitosamente', 'data' => $r['calendario'], 'lineas_creadas' => $r['lineas']]);
        } catch (\InvalidArgumentException $e) {
            // Mensajes de TurnosCalendario (rango invertido, más de 24 h por día): escritos por el código.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear calendario', 'Error interno del servidor');
        }
    }

    public function update(CalendarioNombreRequest $request, string $id): JsonResponse
    {
        $calendario = ReqCalendarioTab::find($id);
        if (! $calendario) {
            return response()->json(['success' => false, 'message' => 'Calendario no encontrado'], 404);
        }
        $calendario->update(['Nombre' => $request->validated('Nombre')]);

        return response()->json(['success' => true, 'message' => 'Calendario actualizado exitosamente', 'data' => $calendario]);
    }

    public function getCalendarioDetalle(string $calendarioId, TurnosCalendario $turnos): JsonResponse
    {
        $calendario = ReqCalendarioTab::find($calendarioId);
        if (! $calendario) {
            return response()->json(['success' => false, 'message' => 'Calendario no encontrado'], 404);
        }
        $lineas = ReqCalendarioLine::where('CalendarioId', $calendarioId)->orderBy('FechaInicio', 'desc')
            ->get(['FechaInicio', 'FechaFin', 'HorasTurno', 'Turno']);

        return response()->json(['success' => true, 'data' => [
            'calendarioId' => $calendario->CalendarioId,
            'nombre' => $calendario->Nombre,
        ] + $turnos->resumen($lineas)]);
    }

    public function updateMasivo(CalendarioRequest $request, string $calendarioId, CalendarioService $calendarios): JsonResponse
    {
        $calendario = ReqCalendarioTab::find($calendarioId);
        if (! $calendario) {
            return response()->json(['success' => false, 'message' => 'Calendario no encontrado'], 404);
        }

        try {
            $datos = $request->validated();
            $lineas = $calendarios->actualizarMasivo($calendario, $datos);

            return response()->json([
                'success' => true,
                'message' => "Calendario actualizado exitosamente. Se actualizaron las líneas del rango {$datos['FechaInicial']} al {$datos['FechaFinal']}",
                'lineas_creadas' => $lineas,
                'rango_actualizado' => ['fecha_inicial' => $datos['FechaInicial'], 'fecha_final' => $datos['FechaFinal']],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar calendario (masivo)', 'Error interno del servidor');
        }
    }

    public function destroy(string $id, CalendarioService $calendarios): JsonResponse
    {
        $calendario = ReqCalendarioTab::find($id);
        if (! $calendario) {
            return response()->json(['success' => false, 'message' => 'Calendario no encontrado'], 404);
        }

        try {
            $r = $calendarios->eliminar($calendario);

            return response()->json($r->aJson(), $r->status);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar calendario', 'Error interno del servidor');
        }
    }

    public function storeLine(LineaCalendarioRequest $request, CalendarioService $calendarios): JsonResponse
    {
        $this->ampliarLimites();
        $datos = $request->validated();
        if (! ReqCalendarioTab::whereKey($datos['CalendarioId'])->exists()) {
            return response()->json(['success' => false, 'message' => 'El calendario especificado no existe'], 422);
        }

        try {
            $r = $calendarios->crearLinea($datos);

            return response()->json(['success' => true, 'message' => 'Línea de calendario creada exitosamente', 'data' => $r['linea'], 'recalculo' => $r['recalculo']]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear línea de calendario', 'Error interno del servidor');
        }
    }

    public function updateLine(LineaCalendarioRequest $request, string $id, CalendarioService $calendarios): JsonResponse
    {
        $this->ampliarLimites();
        $linea = ReqCalendarioLine::find($id);
        if (! $linea) {
            return response()->json(['success' => false, 'message' => 'Línea de calendario no encontrada'], 404);
        }

        try {
            $recalculo = $calendarios->actualizarLinea($linea, $request->validated());

            return response()->json(['success' => true, 'message' => 'Línea de calendario actualizada exitosamente', 'data' => $linea->fresh(), 'recalculo' => $recalculo]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar línea de calendario', 'Error interno del servidor');
        }
    }

    public function destroyLine(string $id, CalendarioService $calendarios): JsonResponse
    {
        $this->ampliarLimites();
        $linea = ReqCalendarioLine::find($id);
        if (! $linea) {
            return response()->json(['success' => false, 'message' => 'Línea de calendario no encontrada'], 404);
        }

        try {
            return response()->json(['success' => true, 'message' => 'Línea de calendario eliminada exitosamente', 'recalculo' => $calendarios->eliminarLinea($linea)]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar línea de calendario', 'Error interno del servidor');
        }
    }

    public function destroyLineasPorRango(EliminarLineasRangoRequest $request, string $calendarioId, CalendarioService $calendarios): JsonResponse
    {
        $this->ampliarLimites();
        if (! ReqCalendarioTab::whereKey($calendarioId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Calendario no encontrado'], 404);
        }

        try {
            $r = $calendarios->eliminarLineasPorRango($calendarioId, $request->validated('fechaInicio'), $request->validated('fechaFin'), $request->turnos());
            if ($r['eliminadas'] === 0) {
                return response()->json(['success' => false, 'message' => 'No se encontraron lineas que coincidan con los criterios especificados'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$r['eliminadas']} linea(s) de calendario exitosamente",
                'eliminadas' => $r['eliminadas'],
                'recalculo' => $r['recalculo'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar líneas de calendario por rango', 'Error interno del servidor', context: ['calendario_id' => $calendarioId]);
        }
    }

    public function procesarExcel(ExcelCatalogoRequest $request, CalendarioService $calendarios): JsonResponse
    {
        $this->ampliarLimites();

        try {
            $tipo = $request->input('tipo') === 'lineas' ? 'lineas' : 'calendarios';

            return response()->json(['success' => true, 'message' => 'Archivo procesado exitosamente', 'data' => $calendarios->importarExcel($tipo, $request->file('archivo_excel'))]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Excel de calendarios', 'Error al procesar el Excel de calendarios.');
        }
    }

    public function recalcularProgramas(Request $request, string $calendarioId, RecalcularProgramasCalendario $recalcular): JsonResponse
    {
        $this->ampliarLimites();
        $calendario = ReqCalendarioTab::find($calendarioId);
        if (! $calendario) {
            // Antes listaba todos los calendarios existentes en el mensaje.
            return response()->json(['success' => false, 'message' => "Calendario '{$calendarioId}' no encontrado"], 404);
        }

        try {
            $stats = $request->isMethod('post') ? $recalcular->recalcular($calendarioId, null, null, $request->boolean('regenerar_lineas')) : null;

            return response()->json(['success' => true, 'message' => 'Recálculo completado exitosamente', 'data' => [
                'calendario_id' => $calendarioId,
                'calendario_nombre' => $calendario->Nombre,
                'recalculo' => $stats,
            ]]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, "Recálculo manual {$calendarioId}", 'Error interno del servidor durante el recálculo');
        }
    }

    private function ampliarLimites(): void
    {
        ini_set('max_execution_time', (string) self::RECALC_TIMEOUT_SECONDS);
        set_time_limit(self::RECALC_TIMEOUT_SECONDS);
        ini_set('memory_limit', '1024M');
    }
}
