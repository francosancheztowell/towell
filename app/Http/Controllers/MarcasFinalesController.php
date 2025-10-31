<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\TejMarcas;
use App\Models\TejMarcasLine;
use App\Helpers\TurnoHelper;

class MarcasFinalesController extends Controller
{
    /**
     * Muestra la vista principal de Marcas Finales
     */
    public function index()
    {
        // Obtener todas las marcas ordenadas por fecha descendente
        $marcas = TejMarcas::with('marcasLine')
            ->orderBy('Date', 'desc')
            ->orderBy('Folio', 'desc')
            ->get();

        return view('modulos.marcas-finales', [
            'marcas' => $marcas
        ]);
    }

    /**
     * Crear nueva marca
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $turno = TurnoHelper::getTurnoActual();
            $fecha = now('America/Mexico_City')->toDateString();
            $folio = $this->generarFolioSecuencial();

            // Crear registro principal
            $marca = TejMarcas::create([
                'Folio' => $folio,
                'Date' => $fecha,
                'Turno' => $turno,
                'Status' => 'En Proceso',
                'numero_empleado' => $request->numero_empleado ?? '',
                'nombreEmpl' => $request->nombre_empleado ?? '',
            ]);

            // Crear líneas para cada telar (201-211)
            $telares = range(201, 211);
            foreach ($telares as $telar) {
                TejMarcasLine::create([
                    'Folio' => $folio,
                    'Date' => $fecha,
                    'Turno' => $turno,
                    'SalonTejidoId' => $this->determinarSalon($telar),
                    'NoTelarId' => (string)$telar,
                    'Eficiencia' => 0,
                    'Marcas' => 0,
                    'Trama' => 0,
                    'Pie' => 0,
                    'Rizo' => 0,
                    'Otros' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Marca creada exitosamente',
                'folio' => $folio
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear marca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una marca específica
     */
    public function show($folio)
    {
        $marca = TejMarcas::with('marcasLine')->where('Folio', $folio)->first();

        if (!$marca) {
            return response()->json([
                'success' => false,
                'message' => 'Marca no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'marca' => $marca
        ]);
    }

    /**
     * Actualizar una marca
     */
    public function update(Request $request, $folio)
    {
        try {
            DB::beginTransaction();

            $marca = TejMarcas::where('Folio', $folio)->first();
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

            // Actualizar datos principales
            $marca->update([
                'numero_empleado' => $request->numero_empleado ?? $marca->numero_empleado,
                'nombreEmpl' => $request->nombre_empleado ?? $marca->nombreEmpl,
            ]);

            // Actualizar líneas si vienen en el request
            if ($request->has('lineas')) {
                foreach ($request->lineas as $linea) {
                    TejMarcasLine::where('Folio', $folio)
                        ->where('NoTelarId', $linea['telar'])
                        ->update([
                            'Eficiencia' => $linea['eficiencia'] ?? 0,
                            'Marcas' => $linea['marcas'] ?? 0,
                            'Trama' => $linea['trama'] ?? 0,
                            'Pie' => $linea['pie'] ?? 0,
                            'Rizo' => $linea['rizo'] ?? 0,
                            'Otros' => $linea['otros'] ?? 0,
                        ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Marca actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar marca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalizar una marca
     */
    public function finalizar(Request $request, $folio)
    {
        try {
            $marca = TejMarcas::where('Folio', $folio)->first();
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

            $marca->Status = 'Finalizado';
            $marca->save();

            return response()->json([
                'success' => true,
                'message' => 'Marca finalizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar marca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar folio secuencial
     */
    private function generarFolioSecuencial(): string
    {
        $ultimo = TejMarcas::orderByDesc('Folio')->first();
        if (!$ultimo || !preg_match('/^M(\d{4})$/', $ultimo->Folio, $m)) {
            return 'M0001';
        }
        $num = intval($m[1]) + 1;
        return 'M' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Determinar salón basado en el telar
     */
    private function determinarSalon(int $telar): string
    {
        if ($telar >= 201 && $telar <= 215) {
            return 'JACQUARD';
        }
        if ($telar >= 299 && $telar <= 320) {
            return 'ITEMA';
        }
        return 'JACQUARD';
    }
}




















