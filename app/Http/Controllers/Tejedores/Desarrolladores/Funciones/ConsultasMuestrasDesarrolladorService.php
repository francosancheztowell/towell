<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\Muestras;
use App\Helpers\TelDesarrolladoresHelper;
use Exception;
use Illuminate\Support\Collection;

class ConsultasMuestrasDesarrolladorService extends ConsultasDesarrolladorService
{
    /**
     * Obtiene telares destino desde MuestrasPrograma.
     */
    private function obtenerTelaresDestinoMuestras(): Collection
    {
        return Muestras::query()
            ->select('SalonTejidoId', 'NoTelarId')
            ->whereNotNull('SalonTejidoId')
            ->whereNotNull('NoTelarId')
            ->where('NoTelarId', '!=', '')
            ->distinct()
            ->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->get()
            ->map(static function ($row) {
                $salon = trim((string) ($row->SalonTejidoId ?? ''));
                $telar = trim((string) ($row->NoTelarId ?? ''));

                return [
                    'value' => $salon . '|' . $telar,
                    'label' => $telar . ' (' . $salon . ')',
                ];
            })
            ->values();
    }

    /**
     * Override para usar telaresDestino de MuestrasPrograma.
     */
    public function obtenerDatosIndex(): array
    {
        $datos = parent::obtenerDatosIndex();
        $datos['telaresDestino'] = $this->obtenerTelaresDestinoMuestras();
        return $datos;
    }

    /**
     * Obtiene producciones desde MuestrasPrograma.
     */
    public function obtenerProducciones($telarId): array
    {
        try {
            $producciones = Muestras::where('NoTelarId', $telarId)
                ->where(function ($query) {
                    $query->where('EnProceso', 0);
                })
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->select('SalonTejidoId', 'NoProduccion', 'FechaInicio', 'TamanoClave', 'NombreProducto')
                ->distinct()
                ->orderBy('FechaInicio', 'asc')
                ->get();

            return [
                'success' => true,
                'producciones' => $producciones
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener las producciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene detalles de orden desde MuestrasPrograma.
     */
    public function obtenerDetallesOrden($noProduccion): array
    {
        try {
            $ordenData = Muestras::where('NoProduccion', $noProduccion)->first();
            $detalles = [];

            $isZeroish = static function ($value): bool {
                $text = trim((string) ($value ?? ''));
                if ($text === '') return true;
                return (bool) preg_match('/^0+(?:\.0+)?$/', $text);
            };

            $shouldIncludeDetalle = static function (array $fila) use ($isZeroish): bool {
                $calibre = trim((string) ($fila['Calibre'] ?? ''));
                if ($calibre === '') return false;

                $keys = ['Calibre', 'Hilo', 'Fibra', 'CodColor', 'NombreColor', 'Pasadas'];
                foreach ($keys as $key) {
                    if (!$isZeroish($fila[$key] ?? '')) {
                        return true;
                    }
                }
                return false;
            };

            if ($ordenData) {
                $filaTrama = TelDesarrolladoresHelper::mapDetalleFila(
                    $ordenData, 'CalibreTrama', 'CalibreTrama2', 'FibraTrama',
                    'CodColorTrama', 'ColorTrama', 'PasadasTrama'
                );

                if ($shouldIncludeDetalle($filaTrama)) {
                    $detalles[] = $filaTrama;
                }

                for ($i = 1; $i <= 5; $i++) {
                    $filaComb = TelDesarrolladoresHelper::mapDetalleFila(
                        $ordenData, "CalibreComb{$i}", "CalibreComb{$i}2", "FibraComb{$i}",
                        "CodColorComb{$i}", $ordenData->{"NombreCC{$i}"} !== null ? "NombreCC{$i}" : "NomColorC{$i}",
                        "PasadasComb{$i}"
                    );

                    if ($shouldIncludeDetalle($filaComb)) {
                        $detalles[] = $filaComb;
                    }
                }
            }

            return [
                'success' => true,
                'detalles' => $detalles
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage()
            ];
        }
    }
}
