<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\ProgramaUrdEng\InventarioTelaresService;
use App\Services\ProgramaUrdEng\ProgramasUrdidoEngomadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReservarProgramarController extends Controller
{
    private const STATUS_ACTIVO = 'Activo';

    public function __construct(
        private InventarioTelaresService $telaresService,
        private ProgramasUrdidoEngomadoService $programasService
    ) {}

    /* ==================== Vistas ==================== */

    public function index()
    {
        try {
            $rows = $this->telaresService->baseQuery()->limit(1000)->get();

            try {
                $this->telaresService->validarYActualizarNoOrden($rows);
            } catch (\Throwable $e) {
                Log::warning('validarYActualizarNoOrden (no crítico)', ['msg' => $e->getMessage()]);
            }

            return view('modulos.programa_urd_eng.reservar-programar', [
                'inventarioTelares' => $this->telaresService->normalizeTelares($rows),
                'columnOptions'     => $this->columnOptionsData(),
                'esSupervisor'      => $this->resolveEsSupervisor(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ReservarProgramarController::index', ['msg' => $e->getMessage()]);
            return view('modulos.programa_urd_eng.reservar-programar', [
                'inventarioTelares' => collect([]),
                'columnOptions'     => $this->columnOptionsData(),
                'esSupervisor'      => $this->resolveEsSupervisor(),
            ]);
        }
    }

    public function programacionRequerimientos(Request $request)
    {
        $telares = $this->parseTelaresFromQuery($request->query('telares'));
        $maquinasUrdido = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->orderBy('Nombre')->pluck('Nombre')->toArray();

        return view('modulos.programa_urd_eng.programacion-requerimientos', [
            'telaresSeleccionados' => $telares,
            'opcionesUrdido' => $maquinasUrdido,
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
        try {
            $request->validate([
                'no_telar' => ['required', 'string', 'max:50'],
                'tipo'     => ['nullable', 'string', 'max:20'],
                'metros'   => ['nullable', 'numeric'],
                'no_julio' => ['nullable', 'string', 'max:50'],
                'no_orden' => ['nullable', 'string', 'max:50'],
                'localidad' => ['nullable', 'string', 'max:10'],
                'tipo_atado' => ['nullable', 'string', 'in:Normal,Especial'],
                'hilo'     => ['nullable', 'string', 'max:50'],
                'cuenta'   => ['nullable', 'string', 'max:50'],
                'calibre'  => ['nullable', 'numeric'],
                'id'       => ['nullable', 'integer'],
                'folio'    => ['nullable', 'string', 'max:50'],
                'lote_proveedor' => ['nullable', 'string', 'max:50'],
                'no_proveedor'   => ['nullable', 'string', 'max:50'],
            ]);

            $noTelar = (string) $request->input('no_telar');
            $tipo = $this->telaresService->normalizeTipo($request->input('tipo'));
            $id = $request->filled('id') ? (int) $request->input('id') : null;
            $folio = $request->input('folio');

            // Resolver folio si no viene explícito
            if (empty($folio)) {
                $folio = $this->resolverFolioParaActualizar($id, $noTelar, $request);
            }

            // Separar campos para inventario vs programas
            $updateInventario = $this->extraerCamposInventario($request);
            $updateProgramas = $this->extraerCamposProgramas($request);

            if (empty($updateProgramas) && empty($updateInventario)) {
                return response()->json(['success' => false, 'message' => 'No hay campos para actualizar'], 400);
            }

            $actualizadosInventario = 0;
            $actualizadosUrdido = 0;
            $actualizadosEngomado = 0;

            // Actualizar inventario
            if (!empty($updateInventario)) {
                $actualizadosInventario = $this->actualizarInventarioTelares($id, $noTelar, $tipo, $updateInventario);
                if ($actualizadosInventario === -1) {
                    return response()->json(['success' => false, 'message' => 'Telar no encontrado o no está activo'], 404);
                }
            }

            // Actualizar programas (urdido/engomado)
            if (!empty($updateProgramas)) {
                $tipoParaProgramas = $updateProgramas['tipo'] ?? $tipo;
                $resultado = $this->programasService->actualizar($noTelar, $tipoParaProgramas, $updateProgramas, $folio);
                $actualizadosUrdido = $resultado['urdido'] ?? 0;
                $actualizadosEngomado = $resultado['engomado'] ?? 0;
            }

            return response()->json([
                'success' => true,
                'message' => $this->construirMensajeActualizacion($noTelar, $actualizadosInventario, $actualizadosUrdido, $actualizadosEngomado),
                'detalle' => [
                    'tej_inventario_telares' => $actualizadosInventario,
                    'urd_programa_urdido' => $actualizadosUrdido,
                    'eng_programa_engomado' => $actualizadosEngomado,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('actualizarTelar', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al actualizar el telar: ' . $e->getMessage()], 500);
        }
    }

    public function liberarTelar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id'       => ['nullable', 'integer'],
                'no_telar' => ['required', 'string', 'max:50'],
                'tipo'     => ['nullable', 'string', 'max:20'],
            ]);

            $id = $request->filled('id') ? (int) $request->input('id') : null;
            $noTelar = (string) $request->input('no_telar');
            $tipo = $this->telaresService->normalizeTipo($request->input('tipo'));

            // Buscar telar activo
            $telar = $this->buscarTelarParaLiberar($id, $noTelar, $tipo);
            if (!$telar) {
                return response()->json(['success' => false, 'message' => 'Telar no encontrado o no esta activo'], 404);
            }

            $noJulio = trim((string)($telar->no_julio ?? ''));
            $noOrden = trim((string)($telar->no_orden ?? ''));
            if ($noJulio === '' || $noOrden === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este telar no esta reservado (no tiene no_julio y no_orden)',
                ], 400);
            }

            // Eliminar reservas activas
            $eliminadas = InvTelasReservadas::where('NoTelarId', $noTelar)
                ->where('Status', 'Reservado')
                ->get()
                ->each(fn ($r) => $r->delete())
                ->count();

            // Limpiar campos al liberar el telar
            $telar->update([
                'hilo' => null,
                'metros' => null,
                'no_julio' => null,
                'no_orden' => null,
                'Reservado' => false,
                'Programado' => false,
                'ConfigId' => null,
                'InventSizeId' => null,
                'InventColorId' => null,
                'localidad' => null,
                'LoteProveedor' => null,
                'NoProveedor' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} liberado correctamente. {$eliminadas} reserva(s) eliminada(s).",
                'data'    => $telar->fresh(),
                'reservas_eliminadas' => $eliminadas,
            ]);
        } catch (\Throwable $e) {
            Log::error('liberarTelar', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al liberar el telar: ' . $e->getMessage()], 500);
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

    private function parseTelaresFromQuery(?string $telaresJson): array
    {
        if (!$telaresJson) return [];
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

    private function resolverFolioParaActualizar(?int $id, string $noTelar, Request $request): string
    {
        if ($id) {
            $telarRecord = TejInventarioTelares::where('id', $id)
                ->where('status', self::STATUS_ACTIVO)->first();
            if ($telarRecord) return trim((string)($telarRecord->no_orden ?? ''));
        }
        if ($request->filled('no_orden')) {
            return trim((string) $request->input('no_orden'));
        }
        return '';
    }

    private function extraerCamposInventario(Request $request): array
    {
        $update = [];
        if ($request->filled('metros'))   $update['metros'] = (float) $request->input('metros');
        if ($request->filled('no_julio')) $update['no_julio'] = (string) $request->input('no_julio');
        if ($request->filled('no_orden')) {
            $update['no_orden'] = (string) $request->input('no_orden');
            $update['LoteProveedor'] = (string) $request->input('no_orden');
        }
        if ($request->filled('localidad'))   $update['localidad'] = (string) $request->input('localidad');
        if ($request->filled('tipo_atado'))  $update['tipo_atado'] = (string) $request->input('tipo_atado');
        if ($request->filled('hilo'))        $update['hilo'] = (string) $request->input('hilo');
        if ($request->filled('lote_proveedor')) $update['LoteProveedor'] = (string) $request->input('lote_proveedor');
        if ($request->filled('no_proveedor'))   $update['NoProveedor'] = (string) $request->input('no_proveedor');
        return $update;
    }

    private function extraerCamposProgramas(Request $request): array
    {
        $update = [];
        if ($request->filled('hilo'))    $update['hilo'] = (string) $request->input('hilo');
        if ($request->filled('cuenta'))  $update['cuenta'] = (string) $request->input('cuenta');
        if ($request->filled('calibre')) $update['calibre'] = (float) $request->input('calibre');
        if ($request->filled('tipo'))    $update['tipo'] = $this->telaresService->normalizeTipo($request->input('tipo'));
        return $update;
    }

    /**
     * @return int Registros actualizados, o -1 si no se encontró el telar
     */
    private function actualizarInventarioTelares(?int $id, string $noTelar, ?string $tipo, array $updateData): int
    {
        if ($id) {
            $telar = TejInventarioTelares::where('id', $id)
                ->where('no_telar', $noTelar)
                ->where('status', self::STATUS_ACTIVO)
                ->first();

            if (!$telar) return -1;
            $telar->update($updateData);
            return 1;
        }

        $query = TejInventarioTelares::where('no_telar', $noTelar)->where('status', self::STATUS_ACTIVO);
        if ($tipo !== null) $query->where('tipo', $tipo);

        $telares = $query->get();
        if ($telares->isEmpty()) return -1;

        $count = 0;
        foreach ($telares as $telar) {
            $telar->update($updateData);
            $count++;
        }
        return $count;
    }

    private function buscarTelarParaLiberar(?int $id, string $noTelar, ?string $tipo)
    {
        $q = TejInventarioTelares::where('status', self::STATUS_ACTIVO);
        if ($id) {
            $q->where('id', (int) $id);
        } else {
            $q->where('no_telar', $noTelar);
            if ($tipo !== null) $q->where('tipo', $tipo);
        }

        // Priorizar registros realmente reservados
        $telar = (clone $q)
            ->whereNotNull('no_julio')->where('no_julio', '!=', '')
            ->whereNotNull('no_orden')->where('no_orden', '!=', '')
            ->first();

        return $telar ?: $q->first();
    }

    private function construirMensajeActualizacion(string $noTelar, int $inv, int $urd, int $eng): string
    {
        $partes = [];
        if ($inv > 0) $partes[] = "{$inv} registro(s) en TejInventarioTelares";
        if ($urd > 0) $partes[] = "{$urd} registro(s) en UrdProgramaUrdido";
        if ($eng > 0) $partes[] = "{$eng} registro(s) en EngProgramaEngomado";

        $msg = "Telar {$noTelar} actualizado";
        return !empty($partes) ? $msg . ': ' . implode(', ', $partes) : $msg . ' (no se encontraron registros para actualizar)';
    }

    private function resolveEsSupervisor(): bool
    {
        $puesto = strtolower(trim((string) data_get(Auth::user(), 'puesto', '')));
        return $puesto === 'supervisor';
    }
}
