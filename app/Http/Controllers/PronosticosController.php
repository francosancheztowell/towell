<?php

namespace App\Http\Controllers;

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
    public function index(Request $request)
    {
        $meses = $this->parseMeses($request);
        $mesActual = !empty($meses) ? $meses[0] : now()->format('Y-m');

        return view('modulos.alta-pronosticos', compact('mesActual', 'meses'));
    }

    /**
     * GET /pronosticos?meses[]=YYYY-MM&meses[]=YYYY-MM
     * Obtiene datos directamente desde las consultas (sin guardar en ReqPronosticos).
     */
    public function get(Request $request)
    {
        try {
            Log::info('GET /pronosticos - Inicio', [
                'query' => $request->query(),
            ]);

            $meses = $this->parseMeses($request);
            Log::info('GET /pronosticos - Meses', ['meses' => $meses]);

            // Obtener datos directamente desde las consultas (sin guardar en ReqPronosticos)
            [$batas, $otros] = $this->service->obtenerPronosticos($meses);

            Log::info('GET /pronosticos - OK', [
                'batas' => count($batas),
                'otros' => count($otros),
            ]);

            return response()->json([
                'otros' => $otros,
                'batas' => $batas,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Parámetros inválidos',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('GET /pronosticos - Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Ocurrió un error al obtener los pronósticos.',
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
            $meses = array_filter(array_map('trim', explode(',', $meses)));
        }

        $validados = [];
        foreach ($meses as $m) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
                throw ValidationException::withMessages([
                    'meses' => ["El valor \"$m\" no tiene formato YYYY-MM."],
                ]);
            }
            $validados[] = $m;
        }

        return array_values(array_unique($validados));
    }

    /**
     * GET /planeacion/programa-tejido/pronosticos/nuevo
     */
    public function nuevo(Request $request)
    {
        Log::info('PronosticosController.nuevo - Método llamado', [
            'url' => $request->fullUrl(),
            'query' => $request->query(),
        ]);

        $prefill = [
            'idflog'          => $request->query('idflog') ?? $request->query('IDFLOG'),
            'itemid'          => $request->query('itemid') ?? $request->query('ITEMID'),
            'inventsizeid'    => $request->query('inventsizeid') ?? $request->query('INVENTSIZEID'),
            'cantidad'        => $request->query('cantidad') ?? $request->query('CANTIDAD'),
            'tipohilo'        => $request->query('tipohilo') ?? $request->query('TIPOHILO'),
            'salon'           => $request->query('salon') ?? $request->query('SALON'),
            'clavemodelo'     => $request->query('clavemodelo') ?? $request->query('CLAVEMODELO'),
            'custname'        => $request->query('custname') ?? $request->query('CUSTNAME'),
            'estado'          => $request->query('estado') ?? $request->query('ESTADO'),
            'nombreproyecto'  => $request->query('nombreproyecto') ?? $request->query('NOMBREPROYECTO'),
            'categoriacalidad'=> $request->query('categoriacalidad') ?? $request->query('CATEGORIACALIDAD'),
        ];

        Log::info('PronosticosController.nuevo - Retornando vista', [
            'vista' => 'modulos.programa-tejido-nuevo.pronosticos',
            'prefill' => $prefill,
        ]);

        return view('modulos.programa-tejido-nuevo.pronosticos', compact('prefill'));
    }
}
