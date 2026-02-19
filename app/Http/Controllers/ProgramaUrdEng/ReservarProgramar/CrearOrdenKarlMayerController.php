<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Crea órdenes de urdido para Karl Mayer (solo UrdProgramaUrdido y UrdJuliosOrden).
 * No crea registros en Engomado.
 */
class CrearOrdenKarlMayerController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'no_telar' => 'required|string|max:50',
            'barras' => 'required|string|max:20',
            'fibra' => 'required|string|max:100',
            'tamano' => 'required|string|max:50',
            'cuenta' => 'nullable|string|max:50',
            'calibre' => 'nullable|string|max:50',
            'metros' => 'required|numeric|min:0',
            'fecha_programada' => 'required|date',
            'tipo_atado' => 'required|string|in:Normal,Especial',
            'bom_id' => 'required|string|max:50',
            'lote_proveedor' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:500',
            'julios' => 'required|array',
            'julios.*' => 'nullable',
            'hilos' => 'required|array',
            'hilos.*' => 'nullable',
            'obs' => 'array',
            'obs.*' => 'nullable|string|max:255',
        ]);

        $usuario = Auth::user();
        $numeroEmpleado = $usuario->numero_empleado ?? null;
        $nombreEmpleado = $usuario->nombre ?? null;

        try {
            DB::beginTransaction();

            $folioConsumo = SSYSFoliosSecuencia::nextFolio('CambioHilo', 5)['folio'];
            $folio = $this->obtenerFolioUrdEng();

            $observaciones = $this->emptyToNull($validated['observaciones'] ?? '');

            $urdido = UrdProgramaUrdido::create([
                'Folio' => $folio,
                'FolioConsumo' => $folioConsumo,
                'NoTelarId' => trim($validated['no_telar']),
                'RizoPie' => $this->emptyToNull($validated['barras'] ?? ''),
                'Cuenta' => $this->emptyToNull($validated['cuenta'] ?? ''),
                'Calibre' => $this->parseFloatOrNull($validated['calibre'] ?? null),
                'FechaReq' => $validated['fecha_programada'],
                'Fibra' => trim($validated['fibra']),
                'InventSizeId' => trim($validated['tamano']),
                'Metros' => (float) $validated['metros'],
                'Kilos' => null,
                'SalonTejidoId' => 'Karl Mayer',
                'MaquinaId' => 'Karl Mayer',
                'BomId' => trim($validated['bom_id']),
                'FechaProg' => $validated['fecha_programada'],
                'Status' => 'Programado',
                'BomFormula' => null,
                'TipoAtado' => trim($validated['tipo_atado']),
                'CveEmpl' => $numeroEmpleado,
                'NomEmpl' => $nombreEmpleado,
                'LoteProveedor' => $this->emptyToNull($validated['lote_proveedor'] ?? ''),
                'Observaciones' => $observaciones,
            ]);

            $camposCreate = ['Folio', 'FolioConsumo', 'Cuenta', 'Calibre', 'Fibra', 'Metros', 'BomId', 'TipoAtado'];
            AuditoriaUrdEng::registrar(
                AuditoriaUrdEng::TABLA_URDIDO,
                (int) $urdido->Id,
                $urdido->Folio,
                AuditoriaUrdEng::ACCION_CREATE,
                $this->camposCreateParaAuditoria($urdido, $camposCreate)
            );

            $julios = $validated['julios'] ?? [];
            $hilos = $validated['hilos'] ?? [];
            $obs = $validated['obs'] ?? [];

            $maxRows = max(count($julios), count($hilos), count($obs));
            for ($i = 0; $i < $maxRows; $i++) {
                $j = isset($julios[$i]) && $julios[$i] !== '' && $julios[$i] !== null ? (int) $julios[$i] : null;
                $h = isset($hilos[$i]) && $hilos[$i] !== '' && $hilos[$i] !== null ? (int) $hilos[$i] : null;
                if ($j !== null || $h !== null) {
                    UrdJuliosOrden::create([
                        'Folio' => $folio,
                        'Julios' => $j,
                        'Hilos' => $h,
                        'Obs' => $this->emptyToNull($obs[$i] ?? ''),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden Karl Mayer creada exitosamente',
                'data' => [
                    'folio' => $folio,
                    'folio_consumo' => $folioConsumo,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('CrearOrdenKarlMayer: validación', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CrearOrdenKarlMayer', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => 'Error al crear la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function obtenerFolioUrdEng(): string
    {
        try {
            return SSYSFoliosSecuencia::nextFolio('URD/ENG', 5)['folio'];
        } catch (\Exception $e) {
            return SSYSFoliosSecuencia::nextFolioById(14, 5)['folio'];
        }
    }

    private function emptyToNull(?string $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function parseFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        $f = filter_var($v, FILTER_VALIDATE_FLOAT);
        return $f !== false ? (float) $f : null;
    }

    private function camposCreateParaAuditoria(object $modelo, array $nombresCampos): string
    {
        $partes = [];
        foreach ($nombresCampos as $campo) {
            $valor = $modelo->getAttribute($campo);
            $partes[] = AuditoriaUrdEng::formatoCampo($campo, null, $valor);
        }
        return implode(', ', $partes);
    }
}
