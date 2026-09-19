<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqTelares;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\ProgramaUrdEng\InventarioTelaresService;
use App\Services\ProgramaUrdEng\ReservarProgramarActionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservarProgramarController extends Controller
{
    private const STATUS_ACTIVO = 'Activo';

    /** Debe coincidir con SYSRoles.modulo. */
    private const MODULO = 'Programa Urd / Eng';

    public function __construct(
        private InventarioTelaresService $telaresService,
        private ReservarProgramarActionService $acciones
    ) {}

    /* ==================== Vistas ==================== */

    public function index()
    {
        try {
            // ponytail: sin paginar; son ~1k telares activos. Pasa a PaginacionCompat al migrar la vista.
            $telares = $this->telaresService->normalizeTelares(
                $this->telaresService->baseQuery()->limit(1000)->get()
            );
        } catch (\Throwable $e) {
            Log::error('ReservarProgramarController::index', ['msg' => $e->getMessage()]);
            $telares = collect([]);
        }

        return view('modulos.programa_urd_eng.reservar-programar', [
            'inventarioTelares' => $telares,
            'columnOptions' => $this->columnOptionsData(),
            'canModificar' => userCan('modificar', self::MODULO),
            'canCrear' => userCan('crear', self::MODULO),
            'canEliminar' => userCan('eliminar', self::MODULO),
        ]);
    }

    public function programacionRequerimientos(Request $request)
    {
        $telares = $this->parseTelaresFromQuery($request->query('telares'));
        $telares = $this->enriquecerTelaresConId($telares);

        $maquinasUrdido = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->where('Nombre', '!=', 'KM1')
            ->orderBy('Nombre')->pluck('Nombre')->toArray();

        return view('modulos.programa_urd_eng.programacion-requerimientos', [
            'telaresSeleccionados' => $telares,
            'opcionesUrdido' => $maquinasUrdido,
        ]);
    }

    /**
     * Obtener el grupo (Destino) de la tabla ReqTelares por NoTelarId.
     * Uso: GET ?notelarid=XXX o ?notelarid=XXX&salon_tejido_id=YYY
     */
    public function getGrupoByTelar(Request $request): JsonResponse
    {
        $request->validate([
            'notelarid' => ['required', 'string', 'max:50'],
            'salon_tejido_id' => ['nullable', 'string', 'max:50'],
        ]);

        $query = ReqTelares::where('NoTelarId', $request->input('notelarid'));

        if ($request->filled('salon_tejido_id')) {
            $query->where('SalonTejidoId', $request->input('salon_tejido_id'));
        }

        $telar = $query->first();

        if (! $telar) {
            return response()->json([
                'success' => true,
                'grupo' => null,
                'message' => 'No se encontró telar en ReqTelares',
            ]);
        }

        $grupo = $telar->Grupo !== null && trim((string) $telar->Grupo) !== ''
            ? trim((string) $telar->Grupo)
            : null;

        return response()->json([
            'success' => true,
            'grupo' => $grupo,
        ]);
    }

    public function creacionOrdenes(Request $request)
    {
        $telares = $this->parseTelaresFromQuery($request->query('telares'));
        $maquinasUrdido = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->orderBy('Nombre')->pluck('Nombre')->toArray();

        return view('modulos.programa_urd_eng.creacion-ordenes', [
            'telaresSeleccionados' => $telares,
            'opcionesUrdido' => $maquinasUrdido,
        ]);
    }

    public function karlMayer()
    {
        return view('modulos.programa_urd_eng.karl-mayer.crear-karl-mayer');
    }

    /* ==================== API Endpoints ==================== */

    public function programarTelar(Request $request): JsonResponse
    {
        try {
            $request->validate(['no_telar' => ['required', 'string', 'max:50']]);
            $noTelar = (string) $request->string('no_telar');

            return response()->json([
                'success' => true,
                'message' => "El telar {$noTelar} ha sido programado exitosamente.",
                'no_telar' => $noTelar,
            ]);
        } catch (\Throwable $e) {
            Log::error('programarTelar', ['msg' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al programar el telar'], 500);
        }
    }

    public function actualizarTelar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'no_telar' => ['required', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'metros' => ['nullable', 'numeric'],
            'no_julio' => ['nullable', 'string', 'max:50'],
            'no_orden' => ['nullable', 'string', 'max:50'],
            'localidad' => ['nullable', 'string', 'max:10'],
            'tipo_atado' => ['nullable', 'string', 'in:Normal,Especial'],
            'hilo' => ['nullable', 'string', 'max:50'],
            'cuenta' => ['nullable', 'string', 'max:50'],
            'calibre' => ['nullable', 'numeric'],
            'id' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'string'],
            'turno' => ['nullable'],
            'folio' => ['nullable', 'string', 'max:50'],
            'lote_proveedor' => ['nullable', 'string', 'max:50'],
            'no_proveedor' => ['nullable', 'string', 'max:50'],
            'solo_inventario' => ['nullable', 'boolean'],
        ]);

        $noTelar = (string) $datos['no_telar'];

        try {
            $detalle = $this->acciones->actualizarTelar($datos);

            return response()->json([
                'success' => true,
                'message' => $this->acciones->mensajeDeActualizacion($noTelar, $detalle),
                'detalle' => $detalle,
            ]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('actualizarTelar', ['msg' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al actualizar el telar: '.$e->getMessage()], 500);
        }
    }

    public function liberarTelar(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['nullable', 'integer'],
            'no_telar' => ['required', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', 'max:20'],
        ]);

        $noTelar = (string) $request->input('no_telar');

        try {
            $resultado = $this->acciones->liberar(
                $request->filled('id') ? (int) $request->input('id') : null,
                $noTelar,
                $this->telaresService->normalizeTipo($request->input('tipo')),
            );

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} liberado correctamente. {$resultado['reservas_eliminadas']} reserva(s) eliminada(s).",
                'data' => $resultado['telar'],
                'reservas_eliminadas' => $resultado['reservas_eliminadas'],
            ]);
        } catch (DomainException $e) {
            $status = str_contains($e->getMessage(), 'no encontrado') ? 404 : 400;

            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        } catch (\Throwable $e) {
            Log::error('liberarTelar', ['msg' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al liberar el telar: '.$e->getMessage()], 500);
        }
    }

    /** Definición de columnas para filtros (telares e inventario). Se envía con la vista para evitar petición extra. */
    private function columnOptionsData(): array
    {
        return [
            'telares' => [
                ['field' => 'no_telar', 'label' => 'No. Telar'],
                ['field' => 'tipo', 'label' => 'Tipo'],
                ['field' => 'cuenta', 'label' => 'Cuenta'],
                ['field' => 'calibre', 'label' => 'Calibre'],
                ['field' => 'fecha', 'label' => 'Fecha'],
                ['field' => 'turno', 'label' => 'Turno'],
                ['field' => 'hilo', 'label' => 'Hilo'],
                ['field' => 'metros', 'label' => 'Metros'],
                ['field' => 'no_julio', 'label' => 'No. Julio'],
                ['field' => 'no_orden', 'label' => 'No. Orden'],
                ['field' => 'reservado', 'label' => 'Reservado'],
                ['field' => 'programado', 'label' => 'Programado'],
                ['field' => 'tipo_atado', 'label' => 'Tipo Atado'],
                ['field' => 'salon', 'label' => 'Salón'],
            ],
            'inventario' => [
                ['field' => 'ItemId', 'label' => 'Artículo'],
                ['field' => 'Tipo', 'label' => 'Tipo'],
                ['field' => 'ConfigId', 'label' => 'Fibra'],
                ['field' => 'InventSizeId', 'label' => 'Cuenta'],
                ['field' => 'InventColorId', 'label' => 'Cod Color'],
                ['field' => 'InventBatchId', 'label' => 'Lote'],
                ['field' => 'WMSLocationId', 'label' => 'Localidad'],
                ['field' => 'InventSerialId', 'label' => 'Num. Julio'],
                ['field' => 'ProdDate', 'label' => 'Fecha'],
                ['field' => 'Metros', 'label' => 'Metros'],
                ['field' => 'InventQty', 'label' => 'Kilos'],
                ['field' => 'NoTelarId', 'label' => 'Telar'],
            ],
        ];
    }

    /* ==================== Métodos privados ==================== */

    /**
     * Para cada telar sin id, busca el registro real en tej_inventario_telares
     * usando no_telar + tipo (+ fecha + turno si disponibles) y le asigna el id de BD.
     */
    private function enriquecerTelaresConId(array $telares): array
    {
        foreach ($telares as &$t) {
            if (! empty($t['id'])) {
                continue;
            }

            $noTelar = trim((string) ($t['no_telar'] ?? ''));
            if ($noTelar === '') {
                continue;
            }

            $query = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', self::STATUS_ACTIVO);

            $tipo = $this->telaresService->normalizeTipo($t['tipo'] ?? null);
            if ($tipo !== null) {
                $query->where('tipo', $tipo);
            }

            if (! empty($t['fecha'])) {
                $query->whereDate('fecha', $t['fecha']);
            }
            if (isset($t['turno']) && $t['turno'] !== '' && $t['turno'] !== null) {
                $query->where('turno', $t['turno']);
            }

            $registros = $query->get(['id', 'fecha', 'turno']);

            if ($registros->count() === 1) {
                $r = $registros->first();
                $t['id'] = $r->id;
                $t['fecha'] = $r->fecha ? substr(trim((string) $r->fecha), 0, 10) : null;
                $t['turno'] = $r->turno;
            } elseif ($registros->count() > 1) {
                $r = $registros->first();
                $t['id'] = $r->id;
                $t['fecha'] = $r->fecha ? substr(trim((string) $r->fecha), 0, 10) : null;
                $t['turno'] = $r->turno;
                Log::warning('enriquecerTelaresConId: múltiples registros', [
                    'no_telar' => $noTelar,
                    'tipo' => $tipo,
                    'count' => $registros->count(),
                    'ids' => $registros->pluck('id')->toArray(),
                ]);
            }
        }
        unset($t);

        return $telares;
    }

    private function parseTelaresFromQuery(?string $telaresJson): array
    {
        if (! $telaresJson) {
            return [];
        }
        try {
            // Laravel ya decodifica los query params automáticamente,
            // no aplicar urldecode() adicional para evitar doble decodificación
            // que corrompe valores con caracteres especiales (+, %, etc.)
            return json_decode($telaresJson, true) ?: [];
        } catch (\Throwable $e) {
            Log::error('parseTelaresFromQuery', ['msg' => $e->getMessage()]);

            return [];
        }
    }
}
