<?php

namespace App\Http\Controllers;

use App\Http\Resources\PronosticoResource;
use App\Services\PronosticosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PronosticosController extends Controller
{
    public function __construct(private PronosticosService $service) {}

    /**
     * Vista principal (sin datos). El front pedirá por GET con fetch.
     */
    public function index()
    {
        // Mes actual para preseleccionar en el front
        $mesActual = now()->format('Y-m');
        return view('modulos.alta-pronosticos', compact('mesActual'));
    }

    /**
     * GET /pronosticos?meses[]=YYYY-MM&meses[]=YYYY-MM
     * Devuelve JSON con {batas:[], otros:[]}
     */
    public function get(Request $request)
    {
        try {
            $meses = $this->parseMeses($request);
            
            if (empty($meses)) {
                return response()->json(['batas' => [], 'otros' => []]);
            }

            [$batas, $otros] = $this->service->obtenerPronosticos($meses);

            // Opcional: envolver en Resource para consistencia
            return response()->json([
                'batas' => PronosticoResource::collection(collect($batas)),
                'otros' => PronosticoResource::collection(collect($otros)),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Parámetros inválidos',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('GET /pronosticos error', ['ex' => $e]);
            return response()->json([
                'message' => 'Ocurrió un error al generar los pronósticos.',
            ], 500);
        }
    }

    /**
     * Normaliza meses desde query:
     * - Acepta ?meses[]=YYYY-MM&meses[]=YYYY-MM
     * - Acepta ?meses=YYYY-MM,YYYY-MM
     */
    private function parseMeses(Request $request): array
    {
        $meses = $request->query('meses', []);
        
        if (is_string($meses)) {
            // Soporta lista separada por comas
            $meses = array_filter(array_map('trim', explode(',', $meses)));
        }

        $validados = [];
        foreach ($meses as $m) {
            // Valida formato YYYY-MM
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
                throw ValidationException::withMessages([
                    'meses' => ["El valor \"$m\" no tiene formato YYYY-MM."],
                ]);
            }
            $validados[] = $m;
        }

        return array_values(array_unique($validados));
    }
}


