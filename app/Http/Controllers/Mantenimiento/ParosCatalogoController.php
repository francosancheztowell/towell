<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\CatParosFallas;
use App\Services\Mantenimiento\ParosCatalogoService;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos de la cascada de "Reportar paro": departamentos, máquinas, tipos de falla,
 * fallas y orden de trabajo sugerida. Solo lectura.
 */
class ParosCatalogoController extends Controller
{
    use HandlesApiErrors;

    public function __construct(private readonly ParosCatalogoService $catalogo) {}

    /** Departamentos en los que el usuario puede reportar: los de sus máquinas asignadas. */
    public function departamentos(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->catalogo->departamentos(Auth::user())]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener departamentos de mantenimiento', 'No se pudieron cargar los departamentos.');
        }
    }

    /**
     * Departamentos para el combo Área del reporte: los de URDCatalogoMaquinas más Atadores,
     * Calidad y Tejido. La vista les suma los `Depto` de los paros cargados (históricos).
     */
    public function departamentosCatalogoFiltros(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->catalogo->departamentosDelCatalogo()]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener catálogo de departamentos para filtros', 'No se pudieron cargar los departamentos.');
        }
    }

    public function maquinas(string $departamento): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->catalogo->maquinas($departamento, Auth::user())]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener máquinas de mantenimiento', 'No se pudieron cargar las máquinas.', context: ['departamento' => $departamento]);
        }
    }

    /**
     * Tipos de falla disponibles para un departamento.
     *
     * Se derivan de CatParosFallas para no ofrecer tipos que dejarían el combo de
     * fallas vacío. "Calidad" se añade siempre: sus fallas se pueden reportar desde
     * cualquier departamento.
     */
    public function tiposFalla(string $departamento): JsonResponse
    {
        try {
            $departamentosConsulta = array_unique([
                ...$this->catalogo->catalogoDepartamentos($departamento),
                'Calidad',
            ]);

            $tiposFalla = CatParosFallas::query()
                ->whereIn('Departamento', $departamentosConsulta)
                ->whereNotNull('TipoFallaId')
                ->distinct()
                ->orderBy('TipoFallaId')
                ->pluck('TipoFallaId');

            return response()->json(['success' => true, 'data' => $tiposFalla]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener tipos de falla', 'No se pudieron cargar los tipos de falla.', context: ['departamento' => $departamento]);
        }
    }

    /**
     * Fallas por departamento desde CatParosFallas.
     *
     * Si se proporciona tipoFallaId, se filtran las fallas por ese tipo.
     */
    public function fallas(string $departamento, ?string $tipoFallaId = null): JsonResponse
    {
        try {
            $departamentosConsulta = $this->catalogo->catalogoDepartamentos($departamento);

            // Las fallas de tipo "Calidad" sólo existen bajo el departamento Calidad,
            // así que se agregan sin importar el departamento seleccionado.
            if (strtoupper(trim((string) $tipoFallaId)) === 'CALIDAD') {
                $departamentosConsulta[] = 'Calidad';
            }

            $query = CatParosFallas::query()
                ->whereIn('Departamento', array_unique($departamentosConsulta));

            if (! empty($tipoFallaId)) {
                $query->where('TipoFallaId', $tipoFallaId);
            }

            $items = $query
                ->orderByRaw('CASE WHEN Departamento = ? THEN 0 ELSE 1 END', ['Calidad'])
                ->orderBy('Falla')
                ->get(['Id', 'Falla', 'Descripcion', 'Abreviado', 'Seccion', 'TipoFallaId', 'Departamento'])
                ->unique(fn ($item) => mb_strtoupper(trim((string) $item->Falla)).'|'.mb_strtoupper(trim((string) ($item->Descripcion ?? ''))))
                ->values();

            return response()->json(['success' => true, 'data' => $items]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener fallas', 'No se pudieron cargar las fallas.', context: ['departamento' => $departamento, 'tipo_falla' => $tipoFallaId]);
        }
    }

    /**
     * Orden de trabajo sugerida por departamento y máquina.
     *
     * - Urdido -> UrdProgramaUrdido (Status activo, por MaquinaId)
     * - Engomado -> EngProgramaEngomado (Status activo, por MaquinaEng)
     * - Resto -> ReqProgramaTejido (EnProceso = 1, por telar)
     *
     * El telar ya identifica su salón, así que no se filtra por SalonTejidoId.
     */
    public function ordenTrabajo(string $departamento, string $maquina): JsonResponse
    {
        try {
            $depUpper = strtoupper(trim($departamento));

            // 'Parcial' también es orden viva en la máquina (producción la sigue
            // capturando), igual que en ModuloProduccionUrdido/Engomado.
            $statusActivos = ['En Proceso', 'Parcial'];

            if ($depUpper === 'URDIDO') {
                // Mantenimiento registra la Karl Mayer como KM1; el programa, como 'Karl Mayer'.
                $maquinaPrograma = strcasecmp(trim($maquina), 'KM1') === 0 ? 'Karl Mayer' : $maquina;

                $rows = DB::table('UrdProgramaUrdido')
                    ->where('MaquinaId', $maquinaPrograma)
                    ->whereIn('Status', $statusActivos)
                    ->orderByDesc('FechaProg')
                    ->limit(5)
                    ->get(['Folio as Orden_Prod', 'FechaProg as Fecha', 'MaquinaId']);
            } elseif ($depUpper === 'ENGOMADO') {
                $rows = DB::table('EngProgramaEngomado')
                    ->where('MaquinaEng', $maquina)
                    ->whereIn('Status', $statusActivos)
                    ->orderByDesc('FechaProg')
                    ->limit(5)
                    ->get(['Folio as Orden_Prod', 'FechaProg as Fecha', 'MaquinaEng', 'SalonTejidoId']);
            } else {
                $rows = DB::table('ReqProgramaTejido')
                    ->where('NoTelarId', $maquina)
                    ->where('EnProceso', 1)
                    ->orderByDesc('FechaInicio')
                    ->limit(5)
                    ->get(['NoProduccion as Orden_Prod', 'NombreProducto', 'FechaInicio', 'SalonTejidoId', 'NoTelarId']);
            }

            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener orden de trabajo sugerida', 'No se pudo consultar la orden de trabajo.', context: ['departamento' => $departamento, 'maquina' => $maquina]);
        }
    }
}
