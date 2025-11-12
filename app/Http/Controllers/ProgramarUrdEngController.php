<?php

namespace App\Http\Controllers;

use App\Models\UrdProgramaUrdido;
use App\Models\EngProgramaEngomado;
use App\Models\UrdJuliosOrden;
use App\Models\UrdConsumoHilo;
use App\Models\SSYSFoliosSecuencia;
use App\Helpers\TurnoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProgramarUrdEngController extends Controller
{
    /**
     * Crear órdenes de urdido y engomado
     * Guarda en las 4 tablas en el orden correcto:
     * 1. Generar FolioConsumo (CH) para tabla 3
     * 2. Generar Folio (URD/ENG) para tabla 2
     * 3. Guardar Tabla 2 (UrdProgramaUrdido) PRIMERO (necesario porque Tabla 3 tiene FK)
     * 4. Guardar Tabla 3 (UrdConsumoHilo) con Folio (FK) y FolioConsumo (CH)
     * 5. Guardar Tabla 4 (UrdJuliosOrden) con Folio de tabla 2
     * 6. Guardar Tabla 5 (EngProgramaEngomado) con Folio de tabla 2
     *
     * Nota: Tabla 3 requiere Folio (NOT NULL, FK) por lo que debe crearse después de Tabla 2
     */
    public function crearOrdenes(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validar datos recibidos
            $request->validate([
                'grupo' => 'required|array',
                'materialesEngomado' => 'required|array',
                'construccionUrdido' => 'required|array',
                'datosEngomado' => 'required|array',
            ]);

            $grupo = $request->input('grupo');
            $materialesEngomado = $request->input('materialesEngomado', []);
            $construccionUrdido = $request->input('construccionUrdido', []);
            $datosEngomado = $request->input('datosEngomado', []);

            // Obtener turno actual y usuario
            $turno = TurnoHelper::getTurnoActual();
            $usuario = Auth::user();
            $numeroEmpleado = $usuario->numero_empleado ?? null;
            $nombreEmpleado = $usuario->nombre ?? null;

            // =================== PASO 1: Generar FolioConsumo (CH) para tabla 3 ===================
            $folioConsumoData = SSYSFoliosSecuencia::nextFolio('CambioHilo', 5);
            $folioConsumo = $folioConsumoData['folio']; // Ejemplo: "CH00001"

            // =================== PASO 2: Generar Folio (URD/ENG) para tabla 2 ===================
            $folioData = SSYSFoliosSecuencia::nextFolio('URD/ENG', 5);
            $folio = $folioData['folio']; // Ejemplo: "00001" (sin prefijo si es NULL)

            // =================== PASO 3: Guardar Tabla 2 (UrdProgramaUrdido) PRIMERO ===================
            // Necesitamos crear Tabla 2 primero para que Tabla 3 pueda referenciar el Folio
            $programaUrdido = UrdProgramaUrdido::create([
                'Folio' => $folio,
                'FolioConsumo' => $folioConsumo, // Vinculado con tabla 3 (se guardará después)
                'NoTelarId' => $grupo['telaresStr'] ?? $grupo['noTelarId'] ?? null,
                'RizoPie' => $grupo['tipo'] ?? null, // Rizo o Pie
                'Cuenta' => $grupo['cuenta'] ?? null,
                'Calibre' => isset($grupo['calibre']) ? (float)$grupo['calibre'] : null,
                'FechaReq' => isset($grupo['fechaReq']) ? $grupo['fechaReq'] : null,
                'Fibra' => $grupo['fibra'] ?? $grupo['hilo'] ?? null,
                'Metros' => isset($grupo['metros']) ? (float)$grupo['metros'] : null,
                'Kilos' => isset($grupo['kilos']) ? (float)$grupo['kilos'] : null,
                'SalonTejidoId' => $grupo['salonTejidoId'] ?? $grupo['destino'] ?? null,
                'MaquinaId' => $grupo['maquinaId'] ?? null,
                'BomId' => $grupo['bomId'] ?? null, // L.Mat Urdido
                'FechaProg' => now()->format('Y-m-d'),
                'Status' => $grupo['status'] ?? 'Activo',
            ]);

            // =================== PASO 4: Guardar Tabla 3 (UrdConsumoHilo) CON Folio de Tabla 2 ===================
            // Ahora podemos guardar Tabla 3 porque ya tenemos el Folio de Tabla 2
            // Tabla 3 guarda FolioConsumo (CH) y Folio (URD/ENG) que referencia a Tabla 2
            foreach ($materialesEngomado as $material) {
                UrdConsumoHilo::create([
                    'Folio' => $folio, // FK a UrdProgramaUrdido (requerido, NOT NULL)
                    'FolioConsumo' => $folioConsumo, // Consecutivo propio (CambioHilo - CH)
                    'ItemId' => $material['itemId'] ?? null,
                    'ConfigId' => $material['configId'] ?? null,
                    'InventSizeId' => $material['inventSizeId'] ?? null,
                    'InventColorId' => $material['inventColorId'] ?? null,
                    'InventLocationId' => $material['inventLocationId'] ?? null,
                    'InventBatchId' => $material['inventBatchId'] ?? null,
                    'WMSLocationId' => $material['wmsLocationId'] ?? null,
                    'InventSerialId' => $material['inventSerialId'] ?? null,
                    'InventQty' => isset($material['kilos']) ? (float)$material['kilos'] : null,
                    'ProdDate' => $this->parseProdDate($material['prodDate'] ?? null),
                    'Status' => $material['status'] ?? 'Activo',
                    'NumeroEmpleado' => $material['numeroEmpleado'] ?? $numeroEmpleado,
                    'NombreEmpl' => $material['nombreEmpl'] ?? $nombreEmpleado,
                    'Conos' => isset($material['conos']) ? (int)$material['conos'] : null,
                    'LoteProv' => $material['loteProv'] ?? null,
                    'NoProv' => $material['noProv'] ?? null,
                ]);
            }

            // =================== PASO 5: Guardar Tabla 4 (UrdJuliosOrden) ===================
            foreach ($construccionUrdido as $julio) {
                if (!empty($julio['julios']) || !empty($julio['hilos'])) {
                    UrdJuliosOrden::create([
                        'Folio' => $folio,
                        'Julios' => isset($julio['julios']) && $julio['julios'] !== '' ? (int)$julio['julios'] : null,
                        'Hilos' => isset($julio['hilos']) && $julio['hilos'] !== '' ? (int)$julio['hilos'] : null,
                        'Obs' => $julio['observaciones'] ?? null,
                    ]);
                }
            }

            // =================== PASO 6: Guardar Tabla 5 (EngProgramaEngomado) ===================
            EngProgramaEngomado::create([
                'Folio' => $folio,
                'NoTelarId' => $grupo['telaresStr'] ?? $grupo['noTelarId'] ?? null,
                'RizoPie' => $grupo['tipo'] ?? null,
                'Cuenta' => $grupo['cuenta'] ?? null,
                'Calibre' => isset($grupo['calibre']) ? (float)$grupo['calibre'] : null,
                'FechaReq' => isset($grupo['fechaReq']) ? $grupo['fechaReq'] : null,
                'Fibra' => $grupo['fibra'] ?? $grupo['hilo'] ?? null,
                'Metros' => isset($grupo['metros']) ? (float)$grupo['metros'] : null,
                'Kilos' => isset($grupo['kilos']) ? (float)$grupo['kilos'] : null,
                'SalonTejidoId' => $grupo['salonTejidoId'] ?? $grupo['destino'] ?? null,
                'MaquinaUrd' => $grupo['maquinaId'] ?? null,
                'BomUrd' => $grupo['bomId'] ?? null,
                'FechaProg' => now()->format('Y-m-d'),
                'Status' => $grupo['status'] ?? 'Activo',
                'Nucleo' => isset($datosEngomado['nucleo']) && $datosEngomado['nucleo'] !== '' ? (string)$datosEngomado['nucleo'] : null,
                'NoTelas' => isset($datosEngomado['noTelas']) && $datosEngomado['noTelas'] !== '' ? (int)$datosEngomado['noTelas'] : null,
                'AnchoBalonas' => isset($datosEngomado['anchoBalonas']) && $datosEngomado['anchoBalonas'] !== '' ? (int)$datosEngomado['anchoBalonas'] : null,
                'MetrajeTelas' => isset($datosEngomado['metrajeTelas']) && $datosEngomado['metrajeTelas'] !== '' ? (float)str_replace(',', '', $datosEngomado['metrajeTelas']) : null,
                'Cuentados' => isset($datosEngomado['cuendeadosMin']) && $datosEngomado['cuendeadosMin'] !== '' ? (int)$datosEngomado['cuendeadosMin'] : null,
                'MaquinaEng' => $datosEngomado['maquinaEngomado'] ?? null,
                'BomEng' => $datosEngomado['lMatEngomado'] ?? null,
                'Obs' => $datosEngomado['observaciones'] ?? null,
            ]);

            DB::commit();

            Log::info('Ordenes URD/ENG creadas exitosamente', [
                'folio' => $folio,
                'folioConsumo' => $folioConsumo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Órdenes creadas exitosamente',
                'data' => [
                    'folio' => $folio,
                    'folioConsumo' => $folioConsumo,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Error de validación al crear órdenes URD/ENG', [
                'errors' => $e->errors(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al crear órdenes URD/ENG', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al crear órdenes: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Convertir ProdDate a formato de fecha válido para la base de datos
     * Acepta string de fecha, Carbon, o null
     */
    private function parseProdDate($prodDate)
    {
        // Si está vacío o es null, retornar null
        if (empty($prodDate) || $prodDate === null || $prodDate === '') {
            return null;
        }

        // Si ya es una instancia de Carbon, retornarla
        if ($prodDate instanceof \Carbon\Carbon) {
            return $prodDate->format('Y-m-d');
        }

        // Si es un string, intentar parsearlo
        if (is_string($prodDate)) {
            try {
                // Intentar parsear como fecha
                $date = \Carbon\Carbon::parse($prodDate);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Si no se puede parsear, registrar warning y retornar null
                Log::warning('Error al parsear ProdDate', [
                    'prodDate_original' => $prodDate,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        // Si es un timestamp numérico
        if (is_numeric($prodDate)) {
            try {
                $date = \Carbon\Carbon::createFromTimestamp($prodDate);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('Error al convertir timestamp ProdDate', [
                    'prodDate_original' => $prodDate,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        // Si no coincide con ningún formato conocido, retornar null
        Log::warning('Formato de ProdDate no reconocido', [
            'prodDate_original' => $prodDate,
            'tipo' => gettype($prodDate)
        ]);

        return null;
    }

}

