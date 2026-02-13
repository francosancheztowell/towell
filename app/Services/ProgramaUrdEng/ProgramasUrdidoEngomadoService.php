<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Support\Facades\Log;

class ProgramasUrdidoEngomadoService
{
    /**
     * Actualizar UrdProgramaUrdido y EngProgramaEngomado usando SOLO Folio como identificador.
     *
     * - Busca por Folio, toma first() y actualiza esa única instancia (no mass update).
     * - NUNCA por NoTelarId/no_telar (puede repetirse). Solo Folio.
     * - El $folio debe provenir del telar en BD (no_orden), no del request.
     */
    public function actualizar(string $noTelar, ?string $tipo, array $update, ?string $folio = null): array
    {
        $resultado = ['urdido' => 0, 'engomado' => 0];
        $updateProgramas = $this->mapearCampos($update);
        if (empty($updateProgramas)) return $resultado;

        $folioLimpio = $folio !== null ? trim($folio) : '';

        if ($folioLimpio === '') {
            Log::warning('ProgramasUrdidoEngomadoService: No se proporcionó Folio, no se puede actualizar', [
                'no_telar' => $noTelar,
                'tipo' => $tipo,
                'campos' => array_keys($update),
            ]);
            return $resultado;
        }

        try {
            // Solo por Folio. Actualizar un solo registro (first) para no afectar otros por si hay duplicados.
            $urdido = UrdProgramaUrdido::where('Folio', $folioLimpio)->first();
            if ($urdido) {
                $camposAudit = self::buildCamposAntesDespues($urdido, $updateProgramas);
                $urdido->update($updateProgramas);
                $resultado['urdido'] = 1;
                AuditoriaUrdEng::registrar(
                    AuditoriaUrdEng::TABLA_URDIDO,
                    (int) $urdido->Id,
                    $urdido->Folio,
                    AuditoriaUrdEng::ACCION_UPDATE,
                    $camposAudit
                );
            }
            $engomado = EngProgramaEngomado::where('Folio', $folioLimpio)->first();
            if ($engomado) {
                $camposAudit = self::buildCamposAntesDespues($engomado, $updateProgramas);
                $engomado->update($updateProgramas);
                $resultado['engomado'] = 1;
                AuditoriaUrdEng::registrar(
                    AuditoriaUrdEng::TABLA_ENGOMADO,
                    (int) $engomado->Id,
                    $engomado->Folio,
                    AuditoriaUrdEng::ACCION_UPDATE,
                    $camposAudit
                );
            }
        } catch (\Throwable $e) {
            Log::error('ProgramasUrdidoEngomadoService', [
                'no_telar' => $noTelar, 'folio' => $folio, 'error' => $e->getMessage()
            ]);
        }
        return $resultado;
    }

    private static function buildCamposAntesDespues($modelo, array $updateProgramas): string
    {
        $partes = [];
        foreach ($updateProgramas as $campo => $valorNuevo) {
            $valorAnterior = $modelo->getAttribute($campo);
            $partes[] = AuditoriaUrdEng::formatoCampo($campo, $valorAnterior, $valorNuevo);
        }
        return implode(', ', $partes);
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
}
