<?php

namespace App\Http\Controllers;

use App\Http\Resources\PronosticoResource;
use App\Services\PronosticosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Sincroniza (guarda en ReqPronosticos) y luego lee desde ReqPronosticos.
     */
    public function get(Request $request)
    {
        try {
            Log::info('GET /pronosticos - Inicio', [
                'query' => $request->query(),
            ]);

            $meses = $this->parseMeses($request);
            Log::info('GET /pronosticos - Meses', ['meses' => $meses]);

            if (!empty($meses)) {
                $this->service->obtenerPronosticos($meses);
            }

            // Leer desde ProdTowel ordenado por FlogsId
            $registros = DB::connection('sqlsrv')
                ->table('dbo.ReqPronosticos')
                ->orderBy('FlogsId')
                ->get();

            $batas = [];
            $otros = [];

            foreach ($registros as $reg) {
                $itemTypeId = $reg->ItemTypeId ?? 0;
                $esBata = is_numeric($itemTypeId) && (int)$itemTypeId >= 10 && (int)$itemTypeId <= 19;

                $item = (object)[
                    'IDFLOG'           => $reg->FlogsId ?? null,
                    'ESTADO'           => $reg->Estado ?? null,
                    'NOMBREPROYECTO'   => $reg->NombreProyecto ?? null,
                    'CUSTNAME'         => $reg->CustName ?? null,
                    'CATEGORIACALIDAD' => $reg->CategoriaCalidad ?? 'NAC - 1',
                    'ANCHO'            => $reg->Ancho ?? null,
                    'LARGO'            => $reg->Largo ?? null,
                    'ITEMID'           => $reg->ItemId ?? null,
                    'ITEMNAME'         => $reg->ItemName ?? null,
                    'INVENTSIZEID'     => $reg->InventSizeId ?? null,
                    'TIPOHILOID'       => $reg->TipoHilo ?? null,
                    'VALORAGREGADO'    => $reg->ValorAgregado ?? null,
                    'FECHACANCELACION' => $reg->FechaCancelacion ?? null,
                    'CANTIDAD'         => $reg->Cantidad ?? 0,
                    'ITEMTYPEID'       => $reg->ItemTypeId ?? null,
                    'RASURADO'         => $reg->Rasurado ?? null,
                    'RASURADOCRUDO'    => $reg->Rasurado ?? null, // Para compatibilidad con el frontend
                ];

                if ($esBata) $batas[] = $item; else $otros[] = $item;
            }

            Log::info('GET /pronosticos - OK', [
                'batas' => count($batas),
                'otros' => count($otros),
            ]);

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
            'vista' => 'modulos.programa-tejido-nuevo.altas',
            'prefill' => $prefill,
        ]);

        return view('modulos.programa-tejido-nuevo.altas', compact('prefill'));
    }
}
