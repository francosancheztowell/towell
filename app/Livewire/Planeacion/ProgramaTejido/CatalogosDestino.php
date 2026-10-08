<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqTelares;
use App\Services\Planeacion\Liberar\LiberarFlogSugeridoService;
use App\Services\Planeacion\ProgramaTejido\CatalogoModelos;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Catálogos del diálogo Duplicar/Dividir: telares por salón, aplicaciones y los datos de una
 * clave modelo. Una consulta por catálogo (cacheada), no una por salón ni por fila.
 */
final class CatalogosDestino
{
    private const SEGUNDOS_CACHE = 600;

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
        return Cache::remember('pt-duplicar:telares', self::SEGUNDOS_CACHE, fn () => ReqTelares::query()
            ->whereNotNull('NoTelarId')
            ->where('NoTelarId', '!=', '')
            ->get(['SalonTejidoId', 'NoTelarId'])
            ->groupBy(fn ($t) => TelarSalonResolver::normalizeSalon($t->getAttribute('SalonTejidoId'), $t->getAttribute('NoTelarId')))
            ->map(fn ($g) => $g->map(fn ($t) => trim((string) $t->getAttribute('NoTelarId')))->unique()
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

        return $clave === '' ? [] : Cache::remember('pt-duplicar:salones-clave:'.mb_strtoupper($clave), self::SEGUNDOS_CACHE,
            fn () => ReqModelosCodificados::query()->where('TamanoClave', $clave)->distinct()->pluck('SalonTejidoId')
                ->map(fn ($s) => TelarSalonResolver::normalizeSalon((string) $s))->unique()->values()->all());
    }

    /**
     * Claves (de cualquier salón) que empiezan con $texto: TOP 10 con LIKE prefijo (usa índice; hay
     * ~6,000 claves, por eso no va como lista completa).
     *
     * @return list<string>
     */
    public static function buscarClaves(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        return ReqModelosCodificados::query()
            ->where('TamanoClave', 'like', addcslashes($texto, '%_[').'%')
            ->distinct()
            ->orderBy('TamanoClave')
            ->limit(10)
            ->pluck('TamanoClave')
            ->map(fn ($c) => trim((string) $c))
            ->all();
    }

    /**
     * Flogs activos de AX (estados 3, 4, 5, 21) con su proyecto: ~300, una consulta cacheada.
     * ponytail: query directa a TwFlogsTable; si crece, moverla al repositorio de AX.
     *
     * @return array<string, string> IDFLOG => NAMEPROYECT
     */
    public static function flogs(): array
    {
        return Cache::remember('pt-duplicar:flogs', self::SEGUNDOS_CACHE, function (): array {
            try {
                return DB::connection('sqlsrv_ti')->table('dbo.TwFlogsTable')
                    ->whereIn('EstadoFlog', [3, 4, 5, 21])
                    ->whereNotNull('IDFLOG')
                    ->orderBy('IDFLOG')
                    ->pluck('NAMEPROYECT', 'IDFLOG')
                    ->mapWithKeys(fn ($proyecto, $id) => [trim((string) $id) => trim((string) $proyecto)])
                    ->all();
            } catch (Throwable $e) {
                report($e);

                return []; // sin AX el flog se escribe a mano, como antes
            }
        });
    }

    /** @return list<string> */
    public static function buscarFlogs(string $texto): array
    {
        $texto = mb_strtoupper(trim($texto));

        return $texto === '' ? [] : array_slice(array_values(array_filter(
            array_keys(self::flogs()),
            fn (string $id) => str_contains(mb_strtoupper($id), $texto)
        )), 0, 10);
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
