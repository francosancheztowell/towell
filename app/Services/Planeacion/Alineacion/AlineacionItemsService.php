<?php

namespace App\Services\Planeacion\Alineacion;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\Alineacion\FormulasAlineacion as F;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Filas de Planeación > Alineación (ReqProgramaTejido en proceso + CatCodificados +
 * ReqModelosCodificados + paros activos). La comparten la vista/exports de Alineación y el
 * andón de Crudo (MachineDetail), con la misma caché de 60 s.
 */
class AlineacionItemsService
{
    /** Llave compartida: la vista de Alineación y el andón de Crudo leen el mismo resultado. */
    public const CACHE_KEY = 'alineacion_items';

    /**
     * Columnas en orden de visualización (keys usados en cada fila de datos).
     * Campos del modelo ReqProgramaTejido se mapean; los que no existen quedan en blanco.
     *
     * @var list<string>
     */
    public const COLUMNAS = [
        'NoTelarId', 'NoProduccion', 'FechaCambio', 'FechaCompromiso', 'ItemId', 'NombreProducto',
        'Tolerancia', 'RazSN', 'TipoRizo', 'CalibreRizo', 'Ancho', 'LargoCrudo', 'PesoCrudo',
        'Luchaje', 'TipoPlano', 'MedidaPlano', 'NoTiras', 'FibraRizo', 'FibraPie', 'CalibreTrama',
        'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'AnchoToalla', 'PesoGRM2',
        'PesoMin', 'PesoMax', 'MuestraMin', 'MuestraMax', 'TotalPedido', 'ProdAcumMesAnt',
        'ProdAcumMes', 'Produccion', 'SaldoPedido', 'DiasEficiencia', 'ProdKgDia', 'DiasPorEjecutar',
        'Observaciones',
    ];

    /**
     * Columnas que no salen directo del atributo homónimo: atributo alterno, o null = vacía.
     *
     * @var array<string, string|null>
     */
    private const MAPEO_ESPECIAL = [
        'FechaCompromiso' => 'EntregaCte',
        'Tolerancia' => null,
        'RazSN' => null,
        'TipoRizo' => null,
        'TipoPlano' => null,
        'PesoMin' => null,
        'PesoMax' => null,
        'ProdAcumMesAnt' => 'Produccion',
        'ProdAcumMes' => null,
    ];

    /**
     * Cacheado 60s: varios usuarios con la pantalla abierta comparten el mismo resultado
     * en vez de golpear SQL Server cada uno con su propio polling.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerItems(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $registros = ReqProgramaTejido::query()
                ->enProceso(true)
                ->ordenadoAlineacion()
                ->get();

            $catCodPorOrden = $this->obtenerCatCodificadosPorOrden($registros);
            $modelosPorClave = $this->obtenerModelosCodificadosPorClave($registros);
            $telaresConParoActivo = $this->obtenerTelaresConParoActivo();

            return $registros->map(fn (ReqProgramaTejido $r) => $this->mapearItem($r, $catCodPorOrden, $telaresConParoActivo, $modelosPorClave))->all();
        });
    }

    /**
     * Mapea un registro ReqProgramaTejido al array asociativo esperado por la vista.
     * Tolerancia, RazSN, TipoRizo, TipoPlano, Observaciones y PesoGRM2 (Peso Muestra) vienen de
     * CatCodificados por OrdenTejido.
     *
     * @param  array<string, CatCodificados>  $catCodPorOrden
     * @param  array<int, string>  $telaresConParoActivo
     * @param  array<string, ReqModelosCodificados>  $modelosPorClave
     * @return array<string, mixed>
     */
    public function mapearItem(ReqProgramaTejido $r, array $catCodPorOrden = [], array $telaresConParoActivo = [], array $modelosPorClave = []): array
    {
        $cat = $catCodPorOrden[trim((string) ($r->getAttribute('NoProduccion') ?? ''))] ?? null;
        $calculadas = $this->columnasCalibreFibra($r) + $this->columnasDeCatalogo($r, $cat, $modelosPorClave);

        $item = [];
        foreach (self::COLUMNAS as $key) {
            $item[$key] = $this->valorColumna($r, $key, $calculadas);
        }
        $item['DiasPorEjecutar'] = $this->diasPorEjecutar($r);
        // FechaTejido en Y-m-d para cálculo de Días de prod. en el front (catcodificados)
        $fechaTejido = $cat?->getAttribute('FechaTejido');
        $item['FechaTejido'] = $fechaTejido ? Carbon::parse($fechaTejido)->format('Y-m-d') : '';

        $item = $this->vaciarCeros($item);

        // Indica si el telar tiene un paro activo en ManFallasParos
        $noTelar = trim((string) ($r->getAttribute('NoTelarId') ?? ''));
        $item['_tieneParoActivo'] = $noTelar !== '' && in_array($noTelar, $telaresConParoActivo, true);

        // Karl Mayer (401/402) no tiene rizo/pie/trama/cenefas sino Barra 1-4. Fuera de
        // COLUMNAS a propósito: la tabla, el Excel y el PDF no cambian; lo usa el andón de Crudo.
        $item['_esKarlMayer'] = TelarSalonResolver::esKarlMayer($r->getAttribute('SalonTejidoId'), $noTelar);
        $item['_barras'] = $item['_esKarlMayer'] ? $this->barrasKarlMayer($r) : [];

        return $item;
    }

    /**
     * @param  array<string, mixed>  $calculadas
     */
    private function valorColumna(ReqProgramaTejido $r, string $key, array $calculadas): mixed
    {
        if (array_key_exists($key, $calculadas)) {
            return $calculadas[$key];
        }
        if (! array_key_exists($key, self::MAPEO_ESPECIAL)) {
            return $r->getAttribute($key) ?? '';
        }
        $attr = self::MAPEO_ESPECIAL[$key];
        if ($attr === null) {
            return '';
        }
        $value = $r->getAttribute($attr);
        if ($value === null || $value === '') {
            return '';
        }

        return $attr === 'EntregaCte' ? F::formatearFecha($value, 'd M Y') : $value;
    }

    /**
     * Hilo Rizo/Pie y Cenefas: "calibre/fibra" del programa.
     *
     * @return array<string, string>
     */
    private function columnasCalibreFibra(ReqProgramaTejido $r): array
    {
        $columnas = [
            // Hilo Rizo = CalibreRizo + FibraRizo (el CalibreRizo del programa, no el AlturaRizo del catalogo).
            'FibraRizo' => F::concatCalibreFibra($r->getAttribute('CalibreRizo'), $r->getAttribute('FibraRizo')),
            // Hilo Pie = CalibrePie + NombreCPie (mismo formato "calibre/fibra" que las cenefas).
            'FibraPie' => F::concatCalibreFibra($r->getAttribute('CalibrePie'), $r->getAttribute('NombreCPie')),
        ];
        foreach ([1, 2, 3, 4] as $n) {
            $columnas["PasadasComb{$n}"] = F::concatCalibreFibra($r->getAttribute("CalibreComb{$n}"), $r->getAttribute("FibraComb{$n}"));
        }

        return $columnas;
    }

    /**
     * Columnas que salen de CatCodificados (por orden) o, de respaldo, de ReqModelosCodificados.
     *
     * La clave exacta manda, pero el respaldo se resuelve campo por campo y no de
     * golpe: hay modelos que empatan exacto y traen el campo vacio mientras otro
     * renglon del mismo ItemId|InventSizeId si lo tiene. Elegir un solo modelo y
     * quedarse con sus huecos dejaba Alt Rizo en 15 de 36 pudiendo llenar 29.
     *
     * @param  array<string, ReqModelosCodificados>  $modelosPorClave
     * @return array<string, mixed>
     */
    private function columnasDeCatalogo(ReqProgramaTejido $r, ?CatCodificados $cat, array $modelosPorClave): array
    {
        $modelo = $modelosPorClave[$this->claveModelo($r->getAttribute('ItemId'), $r->getAttribute('InventSizeId'), $r->getAttribute('TamanoClave'))] ?? null;
        $modeloPar = $modelosPorClave[$this->claveModelo($r->getAttribute('ItemId'), $r->getAttribute('InventSizeId'))] ?? null;
        $deModelo = fn (string $campo) => F::primeroConDato($cat?->getAttribute($campo), $modelo?->getAttribute($campo), $modeloPar?->getAttribute($campo));
        $deCat = fn (string $campo) => $cat?->getAttribute($campo);

        $fechaTejido = $deCat('FechaTejido');
        $pesoMuestra = $deCat('PesoMuestra');
        [$pesoMin, $pesoMax] = F::rangoPeso($cat, $r->getAttribute('PesoCrudo'));
        // Muestra Min/Max salen del Peso Muestra del catalogo, no del peso crudo.
        [$muestraMin, $muestraMax] = F::rangoMuestra($cat, $r->getAttribute('PesoCrudo'), $r->getAttribute('Ancho'), $r->getAttribute('LargoCrudo'));

        $columnas = [
            'FechaCambio' => $fechaTejido ? F::formatearFecha($fechaTejido, 'd M Y') : '',
            'Tolerancia' => $deCat('Tolerancia'),
            'RazSN' => $deCat('Razurada'),
            'TipoRizo' => $deModelo('TipoRizo'),
            'TipoPlano' => $deCat('DobladilloId'),
            'Observaciones' => $deCat('Obs5'),
            // PesoMuestra es nvarchar en SQL Server y arrastra ruido de float ("4.8200002"):
            // se redondea aquí para que web, Excel y PDF muestren lo mismo.
            'PesoGRM2' => $pesoMuestra !== null ? round((float) $pesoMuestra, 3) : null,
            // "Med. Cen." es texto con diagonales ("7/2.5", "1/1/1/1/1"), no un ancho numérico.
            'AnchoToalla' => $deModelo('MedidaCenefa'),
            'MedidaPlano' => $deCat('MedidaPlano'),
            // La columna se llama CalibreRizo por historia, pero muestra "Alt Rizo":
            // el dato real es CatCodificados.AlturaRizo, no el calibre del programa.
            'CalibreRizo' => $deModelo('AlturaRizo'),
            'PesoMin' => $pesoMin,
            'PesoMax' => $pesoMax,
            'MuestraMin' => $muestraMin,
            'MuestraMax' => $muestraMax,
            // "Días de prod." = días transcurridos desde FechaTejido (CatCodificados), no el
            // DiasEficiencia crudo de ReqProgramaTejido (formula distinta de ProgramaTejido).
            // Única fuente de verdad: se calcula aquí en servidor, no se recalcula en el cliente.
            'DiasEficiencia' => $fechaTejido
                ? number_format(Carbon::parse($fechaTejido)->diffInSeconds(Carbon::now()) / 86400, 1)
                : '',
        ];

        return array_map(fn ($v) => $v ?? '', $columnas);
    }

    /** Días por ejecutar = Diferencia / Prod. Prom. X Día (SaldoPedido / ProdKgDia). */
    private function diasPorEjecutar(ReqProgramaTejido $r): float|string
    {
        $prodPromDia = $r->getAttribute('ProdKgDia');
        $diferencia = $r->getAttribute('SaldoPedido');

        return ($prodPromDia !== null && $prodPromDia > 0 && $diferencia !== null)
            ? round($diferencia / $prodPromDia, 2)
            : 'ABIERTO';
    }

    /**
     * Un cero no es un dato: en este reporte significa "no capturado". Se vacían al final,
     * ya con DiasPorEjecutar calculado, y solo sobre las columnas visibles (FechaTejido y
     * _tieneParoActivo quedan intactos porque los consume el front, no el usuario).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function vaciarCeros(array $item): array
    {
        foreach (self::COLUMNAS as $key) {
            if (F::esCeroSinDato($item[$key] ?? '')) {
                $item[$key] = '';
            }
        }

        return $item;
    }

    /**
     * Barras capturadas en el programa; una barra sin ningún dato no se lista.
     *
     * @return list<array{barra: int, cuenta: string, calibre: string, fibra: string, color: string, pasadas: string}>
     */
    private function barrasKarlMayer(ReqProgramaTejido $r): array
    {
        $barras = [];
        foreach ([1, 2, 3, 4] as $n) {
            $color = trim(implode(' ', array_filter([
                trim((string) $r->getAttribute("CodColorBarra{$n}")),
                trim((string) $r->getAttribute("ColorBarra{$n}")),
            ])));
            $barra = [
                'barra' => $n,
                'cuenta' => trim((string) $r->getAttribute("CuentaBarra{$n}")),
                'calibre' => trim((string) $r->getAttribute("CalibreBarra{$n}")),
                'fibra' => trim((string) $r->getAttribute("FibraBarra{$n}")),
                'color' => $color,
                'pasadas' => F::esCeroSinDato($r->getAttribute("PasadasBarra{$n}"))
                    ? '' : trim((string) $r->getAttribute("PasadasBarra{$n}")),
            ];
            if (implode('', array_slice($barra, 1)) !== '') {
                $barras[] = $barra;
            }
        }

        return $barras;
    }

    /**
     * Devuelve un array de MaquinaId (como strings) que tienen al menos un paro activo
     * en ManFallasParos (Estatus = 'Activo').
     *
     * @return array<int, string>
     */
    private function obtenerTelaresConParoActivo(): array
    {
        return ManFallasParos::query()
            ->where('Estatus', 'Activo')
            ->pluck('MaquinaId')
            ->map(fn ($id) => trim((string) ($id ?? '')))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Mapa de ReqModelosCodificados, respaldo de Tipo Rizo / Altura Rizo / Med. Cen.
     * cuando CatCodificados no los trae.
     *
     * Cada modelo se indexa DOS veces: por ItemId|InventSizeId|ClaveModelo, que es la
     * clave exacta, y por ItemId|InventSizeId. Buscar solo por la exacta no encontraba
     * nada: la mitad del catalogo (3013 de 6172 renglones) tiene ClaveModelo con el
     * marcador '(MODELO NUEVO)', mientras el programa trae ahi el tamano real
     * -PULLMAN7630-, asi que el tercer segmento no empataba nunca y las tres columnas
     * salian vacias en los 36 renglones en proceso.
     *
     * Las dos claves no chocan porque tienen distinto numero de segmentos, y el par se
     * usa solo si la exacta fallo. Colapsar por par es seguro para estos campos: de los
     * pares con mas de un renglon, ninguno discrepa en Med. Cen. ni en Tipo Rizo, y el
     * unico que discrepa en Altura Rizo lo resuelve el Id mas reciente, igual que antes.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     * @return array<string, ReqModelosCodificados>
     */
    private function obtenerModelosCodificadosPorClave(Collection $registros): array
    {
        $items = $registros->pluck('ItemId')->map(fn ($v) => trim((string) ($v ?? '')))->filter()->unique()->values()->all();
        if (empty($items)) {
            return [];
        }

        $map = [];
        // ponytail: se filtra por ItemId en SQL y la clave compuesta se arma en PHP; el resto
        // del filtro no vale otro indice mientras el set por ItemId sea pequeno.
        foreach (ReqModelosCodificados::query()
            ->select(['Id', 'ItemId', 'InventSizeId', 'ClaveModelo', 'TipoRizo', 'AlturaRizo', 'MedidaCenefa'])
            ->whereIn('ItemId', $items)
            ->orderByDesc('Id')
            ->get() as $m) {
            // orderByDesc('Id') + ??= : ante varios candidatos gana el mas reciente.
            $map[$this->claveModelo($m->getAttribute('ItemId'), $m->getAttribute('InventSizeId'), $m->getAttribute('ClaveModelo'))] ??= $m;
            $map[$this->claveModelo($m->getAttribute('ItemId'), $m->getAttribute('InventSizeId'))] ??= $m;
        }

        return $map;
    }

    /** Con $claveModelo en null devuelve la clave corta, la de solo ItemId|InventSizeId. */
    private function claveModelo(mixed $itemId, mixed $inventSizeId, mixed $claveModelo = null): string
    {
        $clave = trim((string) ($itemId ?? '')).'|'.trim((string) ($inventSizeId ?? ''));

        return $claveModelo === null ? $clave : $clave.'|'.trim((string) $claveModelo);
    }

    /**
     * Obtiene mapa de CatCodificados por OrdenTejido (No orden).
     * Solo busca por orden; si no aparece, no hace nada.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     * @return array<string, CatCodificados>
     */
    private function obtenerCatCodificadosPorOrden(Collection $registros): array
    {
        $ordenes = [];
        foreach ($registros as $r) {
            $noOrden = trim((string) ($r->getAttribute('NoProduccion') ?? ''));
            if ($noOrden !== '') {
                $ordenes[$noOrden] = true;
            }
        }

        if (empty($ordenes)) {
            return [];
        }

        $ids = array_map('strval', array_keys($ordenes));
        // Sin CAST sobre la columna: se deja que SQL Server convierta el parámetro
        // al tipo de OrdenTejido, permitiendo index seek en vez de scan.
        $cats = CatCodificados::query()
            ->select(['Id', 'ItemId', 'OrdenTejido', 'FechaTejido', 'Tolerancia', 'Razurada', 'TipoRizo', 'DobladilloId', 'Obs5', 'PesoMuestra', 'MedidaCenefa', 'MedidaPlano', 'AlturaRizo'])
            ->whereIn('OrdenTejido', $ids)
            ->orderByDesc('Id')
            ->get();

        $map = [];
        foreach ($cats as $c) {
            $key = trim((string) ($c->getAttribute('OrdenTejido') ?? ''));
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $c;
            }
        }

        return $map;
    }
}
