<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Support\Facades\Log;

class ProgramasUrdidoEngomadoService
{
    public function actualizar(string $noTelar, ?string $tipo, array $update, ?string $folio = null): array
    {
        $resultado = ['urdido' => 0, 'engomado' => 0];
        $updateProgramas = $this->mapearCampos($update);
        if (empty($updateProgramas)) return $resultado;

        $folioLimpio = $folio !== null ? trim($folio) : '';

        try {
            if ($folioLimpio !== '') {
                $resultado['urdido'] = UrdProgramaUrdido::where('Folio', $folioLimpio)->update($updateProgramas);
                $resultado['engomado'] = EngProgramaEngomado::where('Folio', $folioLimpio)->update($updateProgramas);
                return $resultado;
            }

            [$queryUrdido, $queryEngomado] = $this->construirQueriesSinFolio($noTelar, $tipo, $update, $updateProgramas);

            $totalUrdido = (clone $queryUrdido)->count();
            $totalEngomado = (clone $queryEngomado)->count();

            if ($totalUrdido > 1 || $totalEngomado > 1) {
                return $this->actualizarSoloUno($queryUrdido, $queryEngomado, $updateProgramas, $noTelar, $tipo);
            }

            $resultado['urdido'] = $queryUrdido->update($updateProgramas);
            $resultado['engomado'] = $queryEngomado->update($updateProgramas);
        } catch (\Throwable $e) {
            Log::error('ProgramasUrdidoEngomadoService', [
                'no_telar' => $noTelar, 'folio' => $folio, 'error' => $e->getMessage()
            ]);
        }
        return $resultado;
    }

    private function mapearCampos(array $update): array
    {
        $out = [];
        $normalizer = new InventarioTelaresService;
        if (isset($update['cuenta'])) $out['Cuenta'] = $update['cuenta'];
        if (isset($update['calibre'])) $out['Calibre'] = (float)$update['calibre'];
        if (isset($update['hilo'])) $out['Fibra'] = $update['hilo'];
        if (isset($update['tipo'])) $out['RizoPie'] = $normalizer->normalizeTipo($update['tipo']);
        return $out;
    }

    private function construirQueriesSinFolio(string $noTelar, ?string $tipo, array $update, array $updateProgramas): array
    {
        $condicionNoTelar = function ($q) use ($noTelar) {
            $q->where('NoTelarId', $noTelar)
                ->orWhere('NoTelarId', 'like', $noTelar . ',%')
                ->orWhere('NoTelarId', 'like', '%,' . $noTelar . ',%')
                ->orWhere('NoTelarId', 'like', '%,' . $noTelar);
        };

        $queryUrdido = UrdProgramaUrdido::where($condicionNoTelar);
        $queryEngomado = EngProgramaEngomado::where($condicionNoTelar);

        $estamosActualizandoTipo = isset($update['tipo']);
        $normalizer = new InventarioTelaresService;

        if ($tipo !== null) {
            if ($estamosActualizandoTipo) {
                $tipoNuevo = $normalizer->normalizeTipo($update['tipo']);
                if ($tipoNuevo) {
                    $queryUrdido->where('RizoPie', '!=', $tipoNuevo);
                    $queryEngomado->where('RizoPie', '!=', $tipoNuevo);
                }
            } else {
                $tipoNorm = $normalizer->normalizeTipo($tipo);
                if ($tipoNorm) {
                    $queryUrdido->where('RizoPie', $tipoNorm);
                    $queryEngomado->where('RizoPie', $tipoNorm);
                }
            }
        }

        return [$queryUrdido, $queryEngomado];
    }

    private function actualizarSoloUno($queryUrdido, $queryEngomado, array $updateProgramas, string $noTelar, ?string $tipo): array
    {
        Log::warning('Múltiples registros sin Folio, limitando a 1', ['no_telar' => $noTelar]);
        $urdido = (clone $queryUrdido)->orderByDesc('Id')->first();
        $engomado = (clone $queryEngomado)->orderByDesc('Id')->first();

        $r = ['urdido' => 0, 'engomado' => 0];
        if ($urdido) {
            UrdProgramaUrdido::where('Id', $urdido->Id)->update($updateProgramas);
            $r['urdido'] = 1;
        }
        if ($engomado) {
            EngProgramaEngomado::where('Id', $engomado->Id)->update($updateProgramas);
            $r['engomado'] = 1;
        }
        return $r;
    }
}
