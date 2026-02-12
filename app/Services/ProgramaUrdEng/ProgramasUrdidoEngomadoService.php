<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Support\Facades\Log;

class ProgramasUrdidoEngomadoService
{
    /**
     * Actualizar UrdProgramaUrdido y EngProgramaEngomado usando Folio como identificador oficial.
     * NUNCA se busca por NoTelarId porque puede haber múltiples telares con el mismo NoTelarId.
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
            $resultado['urdido'] = UrdProgramaUrdido::where('Folio', $folioLimpio)->update($updateProgramas);
            $resultado['engomado'] = EngProgramaEngomado::where('Folio', $folioLimpio)->update($updateProgramas);
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
}
