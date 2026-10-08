<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqVelocidadStd;
use App\Services\Planeacion\ProgramaTejido\EstandaresTelar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryHelpers
{
    public static function getStdValue(string $tabla, string $campo, string $responseKey, Request $request)
    {
        $fibraId = $request->input('fibra_id');
        $noTelar = $request->input('no_telar_id');
        $calTra = $request->input('calibre_trama');

        if ($fibraId === null || $noTelar === null || $calTra === null) {
            return response()->json(['error' => 'Faltan parámetros requeridos'], 400);
        }

        $densidad = ((float) $calTra > 40) ? 'Alta' : 'Normal';

        try {
            $valor = DB::table($tabla)
                ->where('FibraId', $fibraId)
                ->where('NoTelarId', $noTelar)
                ->where('Densidad', $densidad)
                ->value($campo);

            return response()->json([
                $responseKey => $valor,
                'densidad' => $densidad,
                'calibre_trama' => $calTra,
            ]);
        } catch (\Throwable $e) {
            Log::error("get{$campo}Std error", ['msg' => $e->getMessage()]);

            return response()->json(['error' => "Error al obtener {$campo} estándar"], 500);
        }
    }

    public static function pluckDistinctNonEmpty(string $table, string $column, ?string $connection = null)
    {
        $builder = $connection
            ? DB::connection($connection)->table($table)
            : DB::table($table);

        return $builder
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->pluck($column)
            ->filter()
            ->sort()
            ->values();
    }

    public static function findModeloDestino(string $salonDestino, ReqProgramaTejido $registro): ?ReqModelosCodificados
    {
        return ReqModelosCodificados::where('SalonTejidoId', $salonDestino)
            ->where(function ($q) use ($registro) {
                $q->where('ClaveModelo', $registro->TamanoClave)
                    ->orWhere('TamanoClave', $registro->TamanoClave);
            })
            ->first();
    }

    /**
     * ponytail: adaptador temporal, retirar al migrar consumidores
     * (MoverOrdenesController, ProgramaTejidoOperacionesController). $nuevoSalon nunca se usó.
     */
    public static function resolverStdSegunTelar(ReqProgramaTejido $registro, ?ReqModelosCodificados $modeloDestino, string $nuevoTelar, string $nuevoSalon): array
    {
        return EstandaresTelar::resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar);
    }

    public static function getEficienciaVelocidadStd(string $fibraId, string $noTelar, float $calibreTrama): array
    {
        $densidad = ($calibreTrama > 40) ? 'Alta' : 'Normal';

        $eficiencia = ReqEficienciaStd::where('FibraId', $fibraId)
            ->where('NoTelarId', $noTelar)
            ->where('Densidad', $densidad)
            ->value('Eficiencia');

        $velocidad = ReqVelocidadStd::where('FibraId', $fibraId)
            ->where('NoTelarId', $noTelar)
            ->where('Densidad', $densidad)
            ->value('Velocidad');

        return [
            'eficiencia' => $eficiencia,
            'velocidad' => $velocidad,
            'densidad' => $densidad,
            'calibre_trama' => $calibreTrama,
        ];
    }
}
