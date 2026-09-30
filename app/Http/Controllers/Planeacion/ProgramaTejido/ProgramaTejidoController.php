<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Actions\Planeacion\ProgramaTejido\ActualizarProgramaTejido;
use App\Actions\Planeacion\ProgramaTejido\MutacionRechazada;
use App\Data\Planeacion\ProgramaTejido\CambiosProgramaTejido;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Http\Requests\Planeacion\ProgramaTejido\ActualizarProgramaTejidoRequest;
use App\Services\Planeacion\ProgramaTejido\MutacionesV2;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadComparison;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadService;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use App\Services\Planeacion\ProgramaTejido\ShellV2;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoController.php
 *
 * @description Controlador principal para el programa de tejido. Mantiene: index, update,
 *              destroy, destroyEnProceso. Catálogos, operaciones, balanceo y calendarios están
 *              en ProgramaTejido*Controller dedicados. También sirve la vista de Muestras.
 *              El alta va por ProgramaTejidoOperacionesController (duplicar/dividir).
 *
 * @dependencies UpdateTejido, EliminarTejido, UtilityHelpers
 */
class ProgramaTejidoController extends Controller
{
    public function index(ProgramaTejidoReadService $lectura)
    {
        // La superficie llega explícita a la vista (PT-02): antes $isMuestras se calculaba y
        // no se pasaba, así que Muestras mostraba Redbooth y acciones que no soporta.
        $superficie = ProgramaTejidoSurface::actual();
        $contexto = [
            'superficie' => $superficie,
            'isMuestras' => $superficie->esMuestras(),
            'capacidades' => $superficie->capacidades(),
            'basePath' => $superficie->basePath(),
            'apiPath' => $superficie->apiPath(),
            'linePath' => $superficie->linePath(),
            'pageTitle' => $superficie->titulo(),
        ];

        // Shell Livewire v2 (PT 03), solo por canary: la grilla la lee el componente. Apagado,
        // todo lo de abajo queda igual y la respuesta es la legacy byte a byte.
        if (ShellV2::activo()) {
            return view('modulos.programa-tejido.req-programa-tejido-v2', [
                ...$contexto,
                'columns' => UtilityHelpers::getTableColumns(),
                'hiddenFields' => $lectura->columnasOcultas(Auth::id()),
            ]);
        }

        try {
            $registros = $lectura->registrosGrilla($superficie);

            $columns = UtilityHelpers::getTableColumns();
            $hiddenFields = $lectura->columnasOcultas(Auth::id());

            ProgramaTejidoReadComparison::programar($superficie, $registros);

            return view('modulos.programa-tejido.req-programa-tejido', [
                ...$contexto,
                'registros' => $registros,
                'columns' => $columns,
                'hiddenFields' => $hiddenFields,
            ]);
        } catch (\Throwable $e) {
            LogFacade::error('Error al cargar programa de tejido', [
                'superficie' => $superficie->value,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Error ≠ vacío: la vista pinta un estado de error, no "No hay registros / carga un
            // Excel". El detalle técnico se queda en el log, no en el HTML.
            return view('modulos.programa-tejido.req-programa-tejido', [
                ...$contexto,
                'registros' => collect(),
                'columns' => UtilityHelpers::getTableColumns(),
                'hiddenFields' => [],
                'error' => 'No se pudieron cargar los registros. Intenta de nuevo; si persiste, avisa a Sistemas.',
            ]);
        }
    }

    public function update(Request $request, int $id)
    {
        if (MutacionesV2::activa('actualizar')) {
            return MutacionesV2::medir('actualizar', 'v2', fn () => $this->actualizarV2(
                app(ActualizarProgramaTejidoRequest::class), $id, app(ActualizarProgramaTejido::class)
            ));
        }

        return MutacionesV2::medir('actualizar', 'legacy', fn () => UpdateTejido::actualizar($request, $id));
    }

    /**
     * Edición inline v2 (PT-05): FormRequest → DTO → Action. Mismo JSON que el legacy; un
     * fallo de derivados revierte todo y responde 500 sin el detalle técnico.
     */
    private function actualizarV2(ActualizarProgramaTejidoRequest $request, int $id, ActualizarProgramaTejido $accion): JsonResponse
    {
        try {
            $registro = $accion->ejecutar(CambiosProgramaTejido::desdeRequest($request, $id));
        } catch (MutacionRechazada $e) {
            return $e->respuesta;
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el programa de tejido. Los cambios se revirtieron.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'data' => UtilityHelpers::extractResumen($registro),
        ]);
    }

    public function destroy(int $id)
    {
        return EliminarTejido::eliminar($id);
    }

    public function destroyEnProceso(int $id)
    {
        return EliminarTejido::eliminarEnProceso($id);
    }
}
