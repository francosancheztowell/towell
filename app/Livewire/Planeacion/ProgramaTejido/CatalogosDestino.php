<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Planeacion\Liberar\LiberarFlogSugeridoService;
use App\Services\Planeacion\ProgramaTejido\CatalogoModelos;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Catálogos del diálogo Duplicar/Dividir: telares por salón, aplicaciones y los datos de una
 * clave modelo. Una consulta por catálogo (cacheada), no una por salón ni por fila.
 */
final class CatalogosDestino
{
    private const SEGUNDOS_CACHE = 600;

    /** Opciones de la lista de Flog (con scroll). */
    private const SUGERENCIAS_FLOG = 30;

    /** Solo lo que pinta el diálogo y lo que necesita el cálculo del saldo. */
    private const COLUMNAS = [
        'Id', 'SalonTejidoId', 'NoTelarId', 'TamanoClave', 'ItemId', 'InventSizeId', 'NombreProducto',
        'FlogsId', 'NombreProyecto', 'CustName', 'AplicacionId', 'TotalPedido', 'SaldoPedido',
        'Produccion', 'PorcentajeSegundos', 'OrdCompartida', 'NoProduccion', 'FechaInicio',
    ];

    public static function registro(int $id): ?ReqProgramaTejido
    {
        return ReqProgramaTejido::query()->select(self::COLUMNAS)->find($id);
    }

    /** @return Collection<int, ReqProgramaTejido> el grupo OrdCompartida (una consulta) o solo el registro */
    public static function grupo(ReqProgramaTejido $registro): Collection
    {
        $ord = (int) $registro->getAttribute('OrdCompartida');
        $grupo = $ord > 0
            ? ReqProgramaTejido::query()->select(self::COLUMNAS)->where('OrdCompartida', $ord)->orderBy('FechaInicio')->get()
            : null;

        return $grupo !== null && $grupo->contains('Id', $registro->getAttribute('Id')) ? $grupo->toBase() : collect([$registro]);
    }

    /** @return array<string, list<string>> salón canónico => telares ordenados */
    public static function telares(): array
    {
        return Cache::remember('pt-duplicar:telares', self::SEGUNDOS_CACHE, fn () => URDCatalogoMaquina::query()
            ->telares()
            ->get(['Departamento', 'MaquinaId'])
            ->groupBy(fn (URDCatalogoMaquina $t) => TelarSalonResolver::normalizeSalon($t->salon(), $t->MaquinaId))
            ->map(fn ($g) => $g->map(fn (URDCatalogoMaquina $t) => trim($t->MaquinaId))->unique()
                ->sortBy(fn ($t) => TelarSalonResolver::telarSortKey($t))->values()->all())
            ->all());
    }

    /** @return list<string> */
    public static function aplicaciones(): array
    {
        return Cache::remember('pt-duplicar:aplicaciones', self::SEGUNDOS_CACHE, fn () => ReqAplicaciones::query()
            ->whereNotNull('AplicacionId')
            ->where('AplicacionId', '!=', '')
            ->distinct()
            ->orderBy('AplicacionId')
            ->pluck('AplicacionId')
            ->map(fn ($a) => trim((string) $a))
            ->all());
    }

    /**
     * Datos de la clave en Modelos para el salón, con el flog vigente de AX si responde (si no,
     * el de Modelos). null = la clave no existe en ese salón.
     *
     * @return array{producto: string, itemId: string, inventSizeId: string, flog: string, descripcion: string}|null
     */
    public static function datosClave(string $clave, string $salon): ?array
    {
        $modelo = CatalogoModelos::datosArray($clave, $salon);
        if ($modelo === null) {
            return null;
        }

        $texto = fn (string $campo): string => trim((string) ($modelo[$campo] ?? ''));
        $flog = self::flogVigente($texto('ItemId'), $texto('InventSizeId'));

        return [
            'producto' => $texto('Nombre'),
            'itemId' => $texto('ItemId'),
            'inventSizeId' => $texto('InventSizeId'),
            'flog' => $flog['flogsId'] ?? $texto('FlogsId'),
            'descripcion' => $flog['nombreProyecto'] ?? $texto('NombreProyecto'),
        ];
    }

    /**
     * Telares de los salones donde existe la clave (todos si no hay clave), por número: 201 … 402.
     * El valor lleva el salón ("SALON|TELAR"): elegir el telar fija el salón de la fila.
     *
     * @return list<array{valor: string, telar: string, salon: string}>
     */
    public static function telaresParaClave(string $clave): array
    {
        $telares = self::telares();
        $salones = self::salonesDeClave($clave) ?: array_keys($telares);

        $opciones = [];
        foreach ($salones as $salon) {
            foreach ($telares[$salon] ?? [] as $telar) {
                $opciones[] = ['valor' => $salon.'|'.$telar, 'telar' => $telar, 'salon' => $salon];
            }
        }
        usort($opciones, fn ($a, $b) => TelarSalonResolver::telarSortKey($a['telar']) <=> TelarSalonResolver::telarSortKey($b['telar']));

        return $opciones;
    }

    /** @return list<string> salones (canónicos) donde la clave existe en Modelos; [] si en ninguno. */
    public static function salonesDeClave(string $clave): array
    {
        $clave = trim($clave);

        return $clave === '' ? [] : Cache::remember(self::llaveSalones($clave), self::SEGUNDOS_CACHE,
            fn () => ReqModelosCodificados::query()->where('TamanoClave', $clave)->distinct()->pluck('SalonTejidoId')
                ->map(fn ($s) => TelarSalonResolver::normalizeSalon((string) $s))->unique()->values()->all());
    }

    private static function llaveSalones(string $clave): string
    {
        return 'pt-duplicar:salones-clave:'.mb_strtoupper(trim($clave));
    }

    /** ¿La clave existe en Modelos para ese salón? Si no, se relee sin caché (modelo recién dado de alta). */
    public static function claveEnSalon(string $clave, string $salon): bool
    {
        if (trim($clave) === '') {
            return false;
        }
        if (in_array($salon, self::salonesDeClave($clave), true)) {
            return true;
        }
        Cache::forget(self::llaveSalones($clave));

        return in_array($salon, self::salonesDeClave($clave), true);
    }

    /**
     * Claves (de cualquier salón) que contienen $texto, con su talla y producto. La talla va antes
     * del artículo (MB7336, MANOS906), así que "7336" tiene que encontrar MB7336. Orden: las del
     * artículo de la fila (sus otras tallas; sin texto, solo esas), luego las del artículo escrito,
     * luego las que empiezan con el texto. TOP 10 de ~6,000 claves (el LIKE '%x%' mide 25-160 ms).
     *
     * @return list<array{valor: string, detalle: string}>
     */
    public static function buscarClaves(string $texto, string $itemId = ''): array
    {
        $texto = trim($texto);
        $itemId = trim($itemId);
        $like = addcslashes($texto, '%_[');
        $consulta = fn (bool $delArticulo) => ReqModelosCodificados::query()
            ->selectRaw('TamanoClave, MAX(InventSizeId) AS InventSizeId, MAX(Nombre) AS Nombre')
            ->when($texto !== '', fn ($q) => $q->where('TamanoClave', 'like', '%'.$like.'%'))
            ->when($delArticulo, fn ($q) => $q->where('ItemId', $itemId))
            ->groupBy('TamanoClave')
            // Raw: orden por relevancia (artículo exacto, luego "empieza con"); parametrizado.
            ->orderByRaw('CASE WHEN MAX(LTRIM(RTRIM(ItemId))) = ? THEN 0 WHEN TamanoClave LIKE ? THEN 1 ELSE 2 END', [$texto, $like.'%'])
            ->orderBy('TamanoClave')
            ->limit(10)
            ->get();
        $modelos = $itemId !== '' ? $consulta(true) : collect();
        if ($texto !== '') {
            $modelos = $modelos->concat($consulta(false));
        }

        return $modelos->unique(fn ($m) => mb_strtoupper(trim((string) $m->getAttribute('TamanoClave'))))->take(10)
            ->map(fn ($m) => [
                'valor' => trim((string) $m->getAttribute('TamanoClave')),
                'detalle' => implode(' · ', array_filter([trim((string) $m->getAttribute('InventSizeId')), trim((string) $m->getAttribute('Nombre'))])),
            ])
            ->values()->all();
    }

    /**
     * Flogs vigentes de AX con su proyecto, con la misma regla que Liberar (estado 3/4/5/21 y al
     * menos una línea abierta): ~300, una consulta cacheada. $refrescar relee AX (un flog nuevo);
     * si AX no responde se queda el catálogo que había, para no apagar la validación.
     *
     * @return array<string, string> IDFLOG => NAMEPROYECT
     */
    public static function flogs(bool $refrescar = false): array
    {
        $leer = function (): ?array {
            try {
                return app(LiberarFlogSugeridoService::class)->cabecerasVigentes()
                    ->orderBy('ft.IDFLOG')
                    ->pluck('ft.NAMEPROYECT', 'ft.IDFLOG')
                    ->mapWithKeys(fn ($proyecto, $id) => [trim((string) $id) => trim((string) $proyecto)])
                    ->all();
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        };
        if ($refrescar && ($frescos = $leer()) !== null) {
            Cache::put('pt-duplicar:flogs', $frescos, self::SEGUNDOS_CACHE);

            return $frescos;
        }

        // Sin AX el catálogo queda vacío y el flog se escribe a mano, como antes.
        return Cache::remember('pt-duplicar:flogs', self::SEGUNDOS_CACHE, fn () => $leer() ?? []);
    }

    /** ¿El flog está vigente en AX? Sin AX (catálogo vacío) no se bloquea: se captura a mano, como antes. */
    public static function esFlogVigente(string $flog): bool
    {
        $flog = trim($flog);
        $flogs = self::flogs();

        return $flog !== '' && ($flogs === [] || isset($flogs[$flog]) || isset(self::flogs(refrescar: true)[$flog]));
    }

    /**
     * Flogs vigentes que contienen $texto (todos si está vacío), primero los del artículo de la fila.
     *
     * @param  list<string>  $delArticulo
     * @return list<string>
     */
    public static function buscarFlogs(string $texto, array $delArticulo = []): array
    {
        $texto = mb_strtoupper(trim($texto));
        $vigentes = array_map('strval', array_keys(self::flogs()));
        $ids = array_unique([...array_intersect($delArticulo, $vigentes), ...$vigentes]);

        return array_slice(array_values(array_filter(
            $ids,
            fn (string $id) => $texto === '' || str_contains(mb_strtoupper($id), $texto)
        )), 0, self::SUGERENCIAS_FLOG);
    }

    /**
     * Flogs vigentes con línea abierta de ese artículo+talla, del más reciente al más antiguo.
     * Solo ordenan la lista: no se bloquea por artículo (las órdenes FEL no salen en las líneas).
     *
     * @return list<string>
     */
    public static function flogsDelArticulo(string $itemId, string $talla): array
    {
        $itemId = trim($itemId);

        return $itemId === '' ? [] : Cache::remember('pt-duplicar:flogs-articulo:'.mb_strtoupper($itemId.'|'.trim($talla)), self::SEGUNDOS_CACHE,
            fn () => app(LiberarFlogSugeridoService::class)
                ->flogsDelLote([(new ReqProgramaTejido)->forceFill(['ItemId' => $itemId, 'InventSizeId' => $talla])])[LiberarFlogSugeridoService::claveItemTalla($itemId, $talla)] ?? []);
    }

    /** @return array{flogsId: string, nombreProyecto: string}|null */
    private static function flogVigente(string $item, string $talla): ?array
    {
        if ($item === '' || $talla === '') {
            return null;
        }
        try {
            return app(LiberarFlogSugeridoService::class)->sugerir($item, $talla);
        } catch (Throwable $e) {
            report($e);

            return null; // se queda el flog de Modelos; el planeador lo puede corregir
        }
    }
}
