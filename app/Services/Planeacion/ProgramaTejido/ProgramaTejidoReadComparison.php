<?php

declare(strict_types=1);

namespace App\Services\Planeacion\ProgramaTejido;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Comparación shadow legacy ↔ read v2 (PT-02 · 02.4).
 *
 * Con planeacion.read_v2.shadow_sample > 0, una fracción de las cargas legacy de la
 * grilla se compara contra la lectura v2 DESPUÉS de responder (terminating): no suma
 * latencia al usuario ni escribe nada. Solo registra divergencias (ids/campos, no valores).
 */
final class ProgramaTejidoReadComparison
{
    /** Máximo de Id divergentes que se listan por evento: el log no es un volcado. */
    private const MAX_IDS = 20;

    public function __construct(private readonly ProgramaTejidoReadService $lectura) {}

    /**
     * @param  Collection<int, \App\Models\Planeacion\ReqProgramaTejido>  $legacy
     */
    public static function programar(ProgramaTejidoSurface $superficie, Collection $legacy): void
    {
        $muestra = (float) config('planeacion.read_v2.shadow_sample', 0);
        if ($muestra <= 0 || $legacy->isEmpty() || mt_rand() / mt_getrandmax() > $muestra) {
            return;
        }

        app()->terminating(function () use ($superficie, $legacy): void {
            try {
                app(self::class)->comparar($superficie, $legacy);
            } catch (Throwable $e) {
                // La sombra nunca afecta a la pantalla; si falla, se sabe pero no se propaga.
                Log::warning('programa_tejido.read_v2.shadow_error', ['superficie' => $superficie->value, 'message' => $e->getMessage()]);
            }
        });
    }

    /**
     * @param  Collection<int, \App\Models\Planeacion\ReqProgramaTejido>  $legacy
     * @return array{faltan: list<int>, sobran: list<int>, campos: array<int, list<string>>, orden: bool}
     */
    public function comparar(ProgramaTejidoSurface $superficie, Collection $legacy): array
    {
        $filasLegacy = [];
        foreach ($legacy as $modelo) {
            $filasLegacy[(int) $modelo->Id] = $modelo->getAttributes();
        }
        $columnas = array_values(array_intersect(
            array_keys((array) reset($filasLegacy)),
            $this->lectura->columnasPermitidas($superficie)
        ));
        $v2 = $this->leerTodoV2($superficie, $columnas);

        $campos = [];
        foreach ($filasLegacy as $id => $fila) {
            if (! isset($v2[$id])) {
                continue;
            }
            foreach ($fila as $campo => $valor) {
                if (array_key_exists($campo, $v2[$id]) && self::normalizar($valor) !== self::normalizar($v2[$id][$campo])) {
                    $campos[$id][] = $campo;
                }
            }
        }

        $resultado = [
            'faltan' => array_values(array_diff(array_keys($filasLegacy), array_keys($v2))),
            'sobran' => array_values(array_diff(array_keys($v2), array_keys($filasLegacy))),
            'campos' => $campos,
            // v2 agrega Id como desempate; legacy no, así que en empates exactos de telar/salón/
            // posición/fecha el orden legacy es indefinido y esto puede marcar falsos positivos.
            'orden' => array_values(array_intersect(array_keys($v2), array_keys($filasLegacy))) === array_values(array_intersect(array_keys($filasLegacy), array_keys($v2))),
        ];

        if ($resultado['faltan'] !== [] || $resultado['sobran'] !== [] || $campos !== [] || ! $resultado['orden']) {
            Log::warning('programa_tejido.read_v2.divergencia', [
                'superficie' => $superficie->value,
                'filas_legacy' => count($filasLegacy),
                'faltan' => array_slice($resultado['faltan'], 0, self::MAX_IDS),
                'sobran' => array_slice($resultado['sobran'], 0, self::MAX_IDS),
                'campos' => array_slice($campos, 0, self::MAX_IDS, true),
                'orden_igual' => $resultado['orden'],
                'request_id' => request()->header('X-Request-Id'),
            ]);
        }

        return $resultado;
    }

    /**
     * Recorre el endpoint v2 real (leer(), paginado de 100 en 100) sin filtros: así la sombra
     * ejercita paginación, orden y proyección, no una consulta paralela por Id.
     *
     * @param  list<string>  $columnas
     * @return array<int, array<string, mixed>> por Id, en el orden en que v2 los devolvió
     */
    private function leerTodoV2(ProgramaTejidoSurface $superficie, array $columnas): array
    {
        $filas = [];
        $pagina = 1;
        do {
            $respuesta = $this->lectura->leer($superficie, ['page' => $pagina, 'per_page' => 100, 'columnas' => $columnas]);
            foreach ($respuesta['data'] as $fila) {
                $filas[(int) $fila['Id']] = $fila;
            }
            $pagina++;
        } while ($pagina <= $respuesta['meta']['paginas']);

        return $filas;
    }

    /**
     * Mismo valor lógico, mismo resultado: '', null → null; numérico → float redondeado;
     * fecha → 'Y-m-d H:i:s'; bool → 0/1; texto sin espacios de borde.
     */
    public static function normalizar(mixed $valor): string|float|null
    {
        return match (true) {
            $valor === null, $valor === '' => null,
            $valor instanceof DateTimeInterface => $valor->format('Y-m-d H:i:s'),
            is_bool($valor) => (float) $valor,
            is_numeric($valor) => round((float) $valor, 6),
            default => trim((string) $valor),
        };
    }
}
