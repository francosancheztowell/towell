<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de modelos codificados (ReqModelosCodificados) por TamanoClave y salón, en 3
 * niveles: exacto (espacios normalizados) → prefijo → contains.
 */
final class CatalogoModelos
{
    /** Columnas que el programa toma del modelo al crear/duplicar/revivir. */
    private const COLUMNAS_DATOS = [
        'TamanoClave', 'SalonTejidoId', 'FlogsId', 'NombreProyecto', 'InventSizeId', 'ItemId', 'Nombre',
        'AnchoToalla', 'LargoToalla', 'CuentaPie', 'MedidaPlano', 'PesoCrudo',
        'NoTiras', 'Luchaje', 'Repeticiones', 'Total', 'CalibreTrama', 'CalibreTrama2',
        'FibraId', 'CalibreRizo', 'CalibreRizo2', 'CuentaRizo', 'CalibrePie', 'CalibrePie2',
        'Peine', 'Rasurado', 'CodColorTrama', 'ColorTrama', 'DobladilloId',
        'PasadasTramaFondoC1', 'FibraTramaFondoC1',
        'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5',
        'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorC1', 'NomColorC1',
        'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorC2', 'NomColorC2',
        'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorC3', 'NomColorC3',
        'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4',
        'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5',
        // Karl Mayer: cuatro barras en vez de rizo/pie/C1-C5.
        'CuentaBarra1', 'CalibreBarra1', 'CalibreBarra12', 'CodColorBarra1', 'ColorBarra1', 'FibraBarra1', 'PasadasBarra1',
        'CuentaBarra2', 'CalibreBarra2', 'CalibreBarra22', 'CodColorBarra2', 'ColorBarra2', 'FibraBarra2', 'PasadasBarra2',
        'CuentaBarra3', 'CalibreBarra3', 'CalibreBarra32', 'CodColorBarra3', 'ColorBarra3', 'FibraBarra3', 'PasadasBarra3',
        'CuentaBarra4', 'CalibreBarra4', 'CalibreBarra42', 'CodColorBarra4', 'ColorBarra4', 'FibraBarra4', 'PasadasBarra4',
    ];

    /** Cache por-request de datosArray (clave: 'salon|tamanoClave') */
    private static array $datosArrayCache = [];

    /**
     * Modelo por TamanoClave; sin salón busca en todos.
     *
     * @param  array<int, string>  $selectCols
     */
    public static function porTamanoClave(
        string $tamanoClave,
        ?string $salonTejidoId = null,
        array $selectCols = ['*']
    ): ?ReqModelosCodificados {
        $tam = trim($tamanoClave);
        if ($tam === '') {
            return null;
        }

        $qBase = ReqModelosCodificados::query();

        if ($salonTejidoId !== null && $salonTejidoId !== '') {
            $salones = TelarSalonResolver::salonAliases($salonTejidoId);
            if (! empty($salones)) {
                self::filtrarSalones($qBase, $salones);
            }
        }

        return self::buscarEnTresNiveles($qBase, (string) preg_replace('/\s+/', ' ', $tam), $selectCols);
    }

    /**
     * Todos los datos del modelo como array, con cache por-request y field-mapping:
     * PasadasTramaFondoC1 → PasadasTrama, FibraTramaFondoC1 → FibraTrama.
     *
     * @return array<string, mixed>|null
     */
    public static function datosArray(string $tamanoClave, string $salon): ?array
    {
        $cacheKey = $salon.'|'.$tamanoClave;
        if (array_key_exists($cacheKey, self::$datosArrayCache)) {
            return self::$datosArrayCache[$cacheKey];
        }

        $tam = trim($tamanoClave);
        if ($tam !== '') {
            $tam = (string) preg_replace('/\s+/', ' ', $tam);
        }

        $qBase = ReqModelosCodificados::query();
        $salones = TelarSalonResolver::salonAliases($salon);
        if (! empty($salones)) {
            self::filtrarSalones($qBase, $salones);
        } else {
            $qBase->where('SalonTejidoId', $salon);
        }

        $datos = self::buscarEnTresNiveles($qBase, $tam, self::COLUMNAS_DATOS);

        $resultado = null;
        if ($datos) {
            $resultado = $datos->toArray();
            $resultado['PasadasTrama'] = $resultado['PasadasTramaFondoC1'] ?? null;
            $resultado['FibraTrama'] = $resultado['FibraTramaFondoC1'] ?? $resultado['FibraId'] ?? null;
            unset($resultado['PasadasTramaFondoC1'], $resultado['FibraTramaFondoC1']);
        }

        return self::$datosArrayCache[$cacheKey] = $resultado;
    }

    /**
     * @param  Builder<ReqModelosCodificados>  $q
     * @param  list<string>  $salones
     */
    private static function filtrarSalones(Builder $q, array $salones): void
    {
        // Raw por el LTRIM/RTRIM sobre la columna (hay salones guardados con espacios); parametrizado.
        $q->whereRaw(
            'LTRIM(RTRIM([SalonTejidoId])) IN ('.implode(',', array_fill(0, count($salones), '?')).')',
            $salones
        );
    }

    /**
     * @param  Builder<ReqModelosCodificados>  $qBase
     * @param  array<int, string>  $selectCols
     */
    private static function buscarEnTresNiveles(Builder $qBase, string $tam, array $selectCols): ?ReqModelosCodificados
    {
        $tamUpper = strtoupper($tam);

        // Raw: el ORM no expresa la comparación con espacios normalizados; parametrizado.
        return (clone $qBase)->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [$tamUpper])->select($selectCols)->first()
            ?? (clone $qBase)->whereRaw('UPPER(TamanoClave) LIKE ?', [$tamUpper.'%'])->select($selectCols)->first()
            ?? (clone $qBase)->whereRaw('UPPER(TamanoClave) LIKE ?', ['%'.$tamUpper.'%'])->select($selectCols)->first();
    }

    /**
     * Dividir/duplicar a otro salón exige que la clave modelo exista en ReqModelosCodificados
     * para ese salón. Una consulta para todas las filas.
     *
     * @param  array<int, array{0: string, 1: ?string}>  $pares  [salón destino, TamanoClave]
     * @return string|null mensaje para el 422, o null si todas existen
     */
    public static function claveFaltanteEnSalon(array $pares): ?string
    {
        if ($pares === []) {
            return null;
        }
        $llave = fn ($salon, $clave) => TelarSalonResolver::normalizeSalon((string) $salon).'|'.mb_strtoupper(trim((string) $clave));

        $existentes = ReqModelosCodificados::query()
            ->whereIn('SalonTejidoId', array_merge(...array_map(fn ($p) => TelarSalonResolver::salonAliases($p[0]) ?: [$p[0]], $pares)))
            ->whereIn('TamanoClave', array_map(fn ($p) => trim((string) $p[1]), $pares))
            ->get(['SalonTejidoId', 'TamanoClave'])
            ->mapWithKeys(fn ($m) => [$llave($m->getAttribute('SalonTejidoId'), $m->getAttribute('TamanoClave')) => true]);

        foreach ($pares as [$salon, $clave]) {
            if (trim((string) $clave) === '' || ! $existentes->has($llave($salon, $clave))) {
                return "La clave modelo '".trim((string) $clave)."' no existe en Modelos para el salón {$salon}. Dala de alta antes de pasarla a ese salón.";
            }
        }

        return null;
    }
}
