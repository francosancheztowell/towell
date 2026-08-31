<?php

namespace App\Http\Controllers\Planeacion\Alineacion;

use App\Exports\AlineacionExport;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AlineacionController extends Controller
{
    /**
     * Columnas en orden de visualización (keys usados en cada fila de datos).
     * Campos del modelo ReqProgramaTejido se mapean; los que no existen quedan en blanco.
     *
     * @var array<int, string>
     */
    private array $columnas = [
        'NoTelarId',
        'NoProduccion',
        'FechaCambio',
        'FechaCompromiso',
        'ItemId',
        'NombreProducto',
        'Tolerancia',
        'RazSN',
        'TipoRizo',
        'CalibreRizo',
        'Ancho',
        'LargoCrudo',
        'PesoCrudo',
        'Luchaje',
        'TipoPlano',
        'MedidaPlano',
        'NoTiras',
        'FibraRizo',
        'FibraPie',
        'CalibreTrama',
        'PasadasComb1',
        'PasadasComb2',
        'PasadasComb3',
        'PasadasComb4',
        'AnchoToalla',
        'PesoGRM2',
        'PesoMin',
        'PesoMax',
        'MuestraMin',
        'MuestraMax',
        'TotalPedido',
        'ProdAcumMesAnt',
        'ProdAcumMes',
        'Produccion',
        'SaldoPedido',
        'DiasEficiencia',
        'ProdKgDia',
        'DiasPorEjecutar',
        'Observaciones',
    ];

    /**
     * Etiquetas de columna para Excel/PDF (independiente de la vista web, igual criterio
     * de desacople que usa resources/views/planeacion/alineacion/index.blade.php).
     *
     * @var array<string, string>
     */
    private array $columnLabels = [
        'NoTelarId' => 'Telar', 'NoProduccion' => 'No. Orden', 'FechaCambio' => 'Fecha de cambio',
        'FechaCompromiso' => 'Fecha comprom.', 'ItemId' => 'Clave AX', 'NombreProducto' => 'Modelo',
        'Tolerancia' => 'Tolerancia', 'RazSN' => 'Raz. S/N', 'TipoRizo' => 'Tipo Rizo', 'CalibreRizo' => 'Alt Rizo',
        'Ancho' => 'Crudo Anc.', 'LargoCrudo' => 'Crudo Lar.', 'PesoCrudo' => 'Crudo Peso', 'Luchaje' => 'Luc.',
        'TipoPlano' => 'Tipo Plano', 'MedidaPlano' => 'Med. plano', 'NoTiras' => 'Tiras',
        'FibraRizo' => 'Hilo Rizo', 'FibraPie' => 'Hilo Pie', 'CalibreTrama' => 'Hilo Trama',
        'PasadasComb1' => 'Cenefa 1', 'PasadasComb2' => 'Cenefa 2', 'PasadasComb3' => 'Cenefa 3', 'PasadasComb4' => 'Cenefa 4',
        'AnchoToalla' => 'Med. Cen.', 'PesoGRM2' => 'Peso Muestra',
        'PesoMin' => 'Peso Min', 'PesoMax' => 'Peso Max',
        'MuestraMin' => 'Muestra Min', 'MuestraMax' => 'Muestra Max',
        'TotalPedido' => 'Cantidad Solicitada', 'ProdAcumMesAnt' => 'Prod. Acum. Mes Ant.',
        'ProdAcumMes' => 'Prod. Acum. Mes', 'Produccion' => 'Prod. Acum.', 'SaldoPedido' => 'Diferencia',
        'DiasEficiencia' => 'Días de prod.', 'ProdKgDia' => 'Prod. Prom. X Día', 'DiasPorEjecutar' => 'Días por Ejecutar',
        'Observaciones' => 'Observaciones',
    ];

    /**
     * Subencabezados para las columnas agrupadas en Excel/PDF (fila 2 de encabezado).
     *
     * @var array<string, string>
     */
    private array $subColumnLabels = [
        'Ancho' => 'Anc.', 'LargoCrudo' => 'Lar.', 'PesoCrudo' => 'Peso',
        'FibraRizo' => 'Rizo', 'FibraPie' => 'Pie', 'CalibreTrama' => 'Trama',
        'PasadasComb1' => '1', 'PasadasComb2' => '2', 'PasadasComb3' => '3', 'PasadasComb4' => '4',
        'PesoMin' => 'Mín.', 'PesoMax' => 'Máx.',
        'MuestraMin' => 'Mín.', 'MuestraMax' => 'Máx.',
    ];

    /**
     * Grupos de columnas con encabezado combinado (fila 1) para Excel/PDF.
     *
     * @var array<string, array<int, string>>
     */
    private array $headerGroups = [
        'Crudo' => ['Ancho', 'LargoCrudo', 'PesoCrudo'],
        'Hilo' => ['FibraRizo', 'FibraPie', 'CalibreTrama'],
        'Cenefa Trama' => ['PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4'],
        'Peso' => ['PesoMin', 'PesoMax'],
        'Muestra' => ['MuestraMin', 'MuestraMax'],
    ];

    /**
     * Última columna (inclusive) que se pinta en azul y negritas: de Telar a Tolerancia.
     */
    private const ULTIMA_COLUMNA_DESTACADA = 'Tolerancia';

    /**
     * Vista principal de Alineación (programa de tejido en proceso).
     * Tolerancia, Raz s/n, Tipo Rizo, Tipo Plano y Observaciones se obtienen de CatCodificados
     * por OrdenTejido (No orden). Si no existe la orden, no se hace nada.
     */
    public function index(): View
    {
        try {
            $items = $this->obtenerItemsAlineacion();
        } catch (\Throwable $e) {
            Log::error('Alineacion: error al cargar items', ['error' => $e->getMessage()]);
            $items = [];
        }

        return view('planeacion.alineacion.index', [
            'items' => $items,
        ]);
    }

    /**
     * API: Devuelve los items de alineación en JSON (para refresco automático cada 5 min).
     */
    public function apiData(): JsonResponse
    {
        try {
            $items = $this->obtenerItemsAlineacion();
        } catch (\Throwable $e) {
            Log::error('Alineacion: error al refrescar items', ['error' => $e->getMessage()]);

            return response()->json(['s' => false, 'message' => 'No se pudieron actualizar los datos.'], 500);
        }

        return response()->json(['s' => true, 'items' => $items]);
    }

    /**
     * Descarga el reporte de Alineación en Excel.
     */
    public function exportarExcel(): BinaryFileResponse
    {
        try {
            $items = $this->obtenerItemsAlineacion();
        } catch (\Throwable $e) {
            Log::error('Alineacion: error al exportar Excel', ['error' => $e->getMessage()]);
            abort(500, 'No se pudo generar el Excel de Alineación.');
        }

        $nombreArchivo = 'Alineacion_'.now()->format('d-m-Y_H-i').'.xlsx';

        return Excel::download(new AlineacionExport(
            $items,
            $this->columnas,
            $this->columnLabels,
            $this->subColumnLabels,
            $this->headerGroups,
            self::ULTIMA_COLUMNA_DESTACADA,
            $this->rutaLogo(),
        ), $nombreArchivo);
    }

    /**
     * Descarga el reporte de Alineación en PDF (horizontal, por lo ancho de la tabla).
     */
    public function exportarPdf(): Response
    {
        try {
            $items = $this->obtenerItemsAlineacion();
        } catch (\Throwable $e) {
            Log::error('Alineacion: error al exportar PDF', ['error' => $e->getMessage()]);
            abort(500, 'No se pudo generar el PDF de Alineación.');
        }

        $html = view('pdf.alineacion', [
            'items' => $items,
            'columnas' => $this->columnas,
            'columnLabels' => $this->columnLabels,
            'subColumnLabels' => $this->subColumnLabels,
            'headerGroups' => $this->headerGroups,
            'ultimaColumnaDestacada' => self::ULTIMA_COLUMNA_DESTACADA,
            'logoBase64' => $this->cargarLogoBase64(),
            'generadoEn' => now()->locale('es')->translatedFormat('d M Y H:i'),
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        // "folio" en dompdf = 8.5x13in (612x936pt), que es el tamaño oficio.
        $dompdf->setPaper('folio', 'landscape');
        $dompdf->render();

        $nombreArchivo = 'Alineacion_'.now()->format('d-m-Y_H-i').'.pdf';

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$nombreArchivo.'"');
    }

    /**
     * Ruta absoluta del logo Towell usado en Excel/PDF (mismo archivo que PDFController).
     */
    private function rutaLogo(): ?string
    {
        $ruta = public_path('images/fondosTowell/logo.png');

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Logo Towell en base64 para incrustarlo en el PDF.
     */
    private function cargarLogoBase64(): ?string
    {
        $ruta = $this->rutaLogo();
        if ($ruta === null) {
            return null;
        }

        $contenido = file_get_contents($ruta);

        return $contenido !== false ? 'data:image/png;base64,'.base64_encode($contenido) : null;
    }

    /**
     * Obtiene los items de alineación (ReqProgramaTejido + CatCodificados).
     * Cacheado 60s: varios usuarios con la pantalla abierta comparten el mismo resultado
     * en vez de golpear SQL Server cada uno con su propio polling.
     *
     * @return array<int, array<string, mixed>>
     */
    private function obtenerItemsAlineacion(): array
    {
        return Cache::remember('alineacion_items', 60, function () {
            $registros = ReqProgramaTejido::query()
                ->enProceso(true)
                ->ordenadoAlineacion()
                ->get();

            $catCodPorOrden = $this->obtenerCatCodificadosPorOrden($registros);
            $modelosPorClave = $this->obtenerModelosCodificadosPorClave($registros);
            $telaresConParoActivo = $this->obtenerTelaresConParoActivo();

            return $registros->map(fn (ReqProgramaTejido $r) => $this->mapearProgramaTejidoAItem($r, $catCodPorOrden, $telaresConParoActivo, $modelosPorClave))->all();
        });
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
            $map[$this->claveModelo($m->ItemId, $m->InventSizeId, $m->ClaveModelo)] ??= $m;
            $map[$this->claveModelo($m->ItemId, $m->InventSizeId)] ??= $m;
        }

        return $map;
    }

    /** Con $claveModelo en null devuelve la clave corta, la de solo ItemId|InventSizeId. */
    private function claveModelo($itemId, $inventSizeId, $claveModelo = null): string
    {
        $clave = trim((string) ($itemId ?? '')).'|'.trim((string) ($inventSizeId ?? ''));

        return $claveModelo === null ? $clave : $clave.'|'.trim((string) $claveModelo);
    }

    /**
     * Obtiene mapa de CatCodificados por OrdenTejido (No orden).
     * Solo busca por orden; si no aparece, no hace nada.
     *
     * @return array<string, CatCodificados>
     */
    private function obtenerCatCodificadosPorOrden(Collection $registros): array
    {
        $ordenes = [];
        foreach ($registros as $r) {
            $noOrden = trim((string) ($r->NoProduccion ?? ''));
            if ($noOrden !== '') {
                $ordenes[$noOrden] = true;
            }
        }

        if (empty($ordenes)) {
            return [];
        }

        $ids = array_values(array_map('strval', array_keys($ordenes)));
        // Sin CAST sobre la columna: se deja que SQL Server convierta el parámetro
        // al tipo de OrdenTejido, permitiendo index seek en vez de scan.
        $cats = CatCodificados::query()
            ->select(['Id', 'ItemId', 'OrdenTejido', 'FechaTejido', 'Tolerancia', 'Razurada', 'TipoRizo', 'DobladilloId', 'Obs5', 'PesoMuestra', 'MedidaCenefa', 'MedidaPlano', 'AlturaRizo'])
            ->whereIn('OrdenTejido', $ids)
            ->orderByDesc('Id')
            ->get();

        $map = [];
        foreach ($cats as $c) {
            $key = trim((string) ($c->OrdenTejido ?? ''));
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $c;
            }
        }

        return $map;
    }

    /**
     * Mapea un registro ReqProgramaTejido al array asociativo esperado por la vista.
     * Tolerancia, RazSN, TipoRizo, TipoPlano, Observaciones y PesoGRM2 (Peso Muestra) vienen de
     * CatCodificados por OrdenTejido.
     *
     * @param  array<string, CatCodificados>  $catCodPorOrden
     * @return array<string, mixed>
     */
    private function mapearProgramaTejidoAItem(ReqProgramaTejido $r, array $catCodPorOrden = [], array $telaresConParoActivo = [], array $modelosPorClave = []): array
    {
        $noOrden = trim((string) ($r->NoProduccion ?? ''));
        $cat = $catCodPorOrden[$noOrden] ?? null;
        // Respaldo para Tipo Rizo / Alt Rizo / Med. Cen. cuando el catalogo no los trae.
        // La clave exacta manda, pero el respaldo se resuelve campo por campo y no de
        // golpe: hay modelos que empatan exacto y traen el campo vacio mientras otro
        // renglon del mismo ItemId|InventSizeId si lo tiene. Elegir un solo modelo y
        // quedarse con sus huecos dejaba Alt Rizo en 15 de 36 pudiendo llenar 29.
        $modelo = $modelosPorClave[$this->claveModelo($r->ItemId, $r->InventSizeId, $r->TamanoClave)] ?? null;
        $modeloPar = $modelosPorClave[$this->claveModelo($r->ItemId, $r->InventSizeId)] ?? null;

        $item = [];
        $mapeoEspecial = [
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

        $concatCalibreFibra = [
            // Hilo Rizo = CalibreRizo + FibraRizo (el CalibreRizo del programa, no el AlturaRizo del catalogo).
            'FibraRizo' => fn () => $this->concatCalibreFibra($r->CalibreRizo, $r->FibraRizo),
            // Hilo Pie = CalibrePie + NombreCPie (mismo formato "calibre/fibra" que las cenefas).
            'FibraPie' => fn () => $this->concatCalibreFibra($r->CalibrePie, $r->NombreCPie),
            'PasadasComb1' => fn () => $this->concatCalibreFibra($r->CalibreComb1, $r->FibraComb1),
            'PasadasComb2' => fn () => $this->concatCalibreFibra($r->CalibreComb2, $r->FibraComb2),
            'PasadasComb3' => fn () => $this->concatCalibreFibra($r->CalibreComb3, $r->FibraComb3),
            'PasadasComb4' => fn () => $this->concatCalibreFibra($r->CalibreComb4, $r->FibraComb4),
        ];

        [$pesoMinAlineacion, $pesoMaxAlineacion] = $this->rangoPesoAlineacion($cat, $r->PesoCrudo);
        // Muestra Min/Max salen del Peso Muestra del catalogo, no del peso crudo.
        [$muestraMinAlineacion, $muestraMaxAlineacion] = $this->rangoMuestraAlineacion($cat, $r->PesoCrudo, $r->Ancho, $r->LargoCrudo);

        $deCat = [
            'FechaCambio' => fn () => $cat?->FechaTejido ? $this->formatDateAlineacion($cat->FechaTejido, 'd M Y') : '',
            'Tolerancia' => fn () => $cat?->Tolerancia,
            'RazSN' => fn () => $cat?->Razurada,
            'TipoRizo' => fn () => $this->primeroConDato($cat?->TipoRizo, $modelo?->TipoRizo, $modeloPar?->TipoRizo),
            'TipoPlano' => fn () => $cat?->DobladilloId,
            'Observaciones' => fn () => $cat?->Obs5,
            // PesoMuestra es nvarchar en SQL Server y arrastra ruido de float ("4.8200002"):
            // se redondea aquí para que web, Excel y PDF muestren lo mismo.
            'PesoGRM2' => fn () => $cat?->PesoMuestra !== null ? round((float) $cat->PesoMuestra, 3) : null,
            // "Med. Cen." es texto con diagonales ("7/2.5", "1/1/1/1/1"), no un ancho numérico.
            // Primero CatCodificados por orden; si no hay dato, ReqModelosCodificados por clave.
            'AnchoToalla' => fn () => $this->primeroConDato($cat?->MedidaCenefa, $modelo?->MedidaCenefa, $modeloPar?->MedidaCenefa),
            'MedidaPlano' => fn () => $cat?->MedidaPlano,
            // La columna se llama CalibreRizo por historia, pero muestra "Alt Rizo":
            // el dato real es CatCodificados.AlturaRizo, no el calibre del programa.
            'CalibreRizo' => fn () => $this->primeroConDato($cat?->AlturaRizo, $modelo?->AlturaRizo, $modeloPar?->AlturaRizo),
            'PesoMin' => fn () => $pesoMinAlineacion,
            'PesoMax' => fn () => $pesoMaxAlineacion,
            'MuestraMin' => fn () => $muestraMinAlineacion,
            'MuestraMax' => fn () => $muestraMaxAlineacion,
            // "Días de prod." = días transcurridos desde FechaTejido (CatCodificados), no el
            // DiasEficiencia crudo de ReqProgramaTejido (formula distinta de ProgramaTejido).
            // Única fuente de verdad: se calcula aquí en servidor, no se recalcula en el cliente.
            'DiasEficiencia' => fn () => $cat?->FechaTejido
                ? number_format(Carbon::parse($cat->FechaTejido)->diffInSeconds(Carbon::now()) / 86400, 1)
                : '',
        ];

        foreach ($this->columnas as $key) {
            if (isset($concatCalibreFibra[$key])) {
                $item[$key] = $concatCalibreFibra[$key]();

                continue;
            }
            if (isset($deCat[$key])) {
                $item[$key] = $deCat[$key]() ?? '';

                continue;
            }
            if (array_key_exists($key, $mapeoEspecial)) {
                $attr = $mapeoEspecial[$key];
                if ($attr === null) {
                    $item[$key] = '';

                    continue;
                }
                $value = $r->getAttribute($attr);
                $item[$key] = $value !== null && $value !== '' ? (
                    $attr === 'UpdatedAt' ? $this->formatDateAlineacion($value, 'd M Y H:i') : (
                        $attr === 'EntregaCte' ? $this->formatDateAlineacion($value, 'd M Y') : $value
                    )
                ) : '';

                continue;
            }
            $value = $r->getAttribute($key);
            if ($value === null) {
                $item[$key] = '';

                continue;
            }
            $item[$key] = $value;
        }
        // D?as por ejecutar = Diferencia / Prod. Prom. X D?a (SaldoPedido / ProdKgDia)
        $prodPromDia = $r->ProdKgDia;
        $diferencia = $r->SaldoPedido;
        $item['DiasPorEjecutar'] = ($prodPromDia !== null && $prodPromDia > 0 && $diferencia !== null)
            ? round($diferencia / $prodPromDia, 2)
            : 'ABIERTO';
        // FechaTejido en Y-m-d para cálculo de Días de prod. en el front (catcodificados)
        $item['FechaTejido'] = $cat?->FechaTejido ? Carbon::parse($cat->FechaTejido)->format('Y-m-d') : '';

        // Un cero no es un dato: en este reporte significa "no capturado". Se vacían al final,
        // ya con DiasPorEjecutar calculado, y solo sobre las columnas visibles (FechaTejido y
        // _tieneParoActivo quedan intactos porque los consume el front, no el usuario).
        foreach ($this->columnas as $key) {
            if ($this->esCeroSinDato($item[$key] ?? '')) {
                $item[$key] = '';
            }
        }

        // Indica si el telar tiene un paro activo en ManFallasParos
        $noTelar = trim((string) ($r->NoTelarId ?? ''));
        $item['_tieneParoActivo'] = $noTelar !== '' && in_array($noTelar, $telaresConParoActivo, true);

        return $item;
    }

    /**
     * ¿El valor es un cero disfrazado? Cubre "0", "0.0", "0/0" y "0/0/0/0" (las cenefas
     * concatenan con diagonal). Un valor mixto como "0/ALG" no cuenta: ahí sí hay dato.
     */
    private function esCeroSinDato(mixed $valor): bool
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return false;
        }

        foreach (preg_split('#\s*/\s*#', $texto) as $parte) {
            if (! is_numeric($parte) || (float) $parte !== 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Peso Min/Max: formulas AH/AI de la hoja ALINEACION.
     *   AH = IF(G="N", M/(1+3%), M/(1+0%))
     *   AI = IF(G="N", M*(1+3%), M*(1+5%))
     *
     * El minimo divide y el maximo multiplica: asi esta capturado en la hoja, no es simetrico.
     *
     * @return array{0: ''|int, 1: ''|int}
     */
    private function rangoPesoAlineacion(?CatCodificados $cat, mixed $pesoCrudo): array
    {
        $m = (float) ($pesoCrudo ?? 0);
        if ($m <= 0) {
            return ['', ''];
        }
        $esN = $this->toleranciaEsN($cat);

        return [
            (int) round($esN ? $m / 1.03 : $m / 1.00),
            (int) round($esN ? $m * 1.03 : $m * 1.05),
        ];
    }

    /**
     * Muestra Min/Max: formulas AJ/AK de la hoja ALINEACION. Solo aplican cuando el
     * articulo se pesa por muestra ("Mu"); si se pesa la pieza completa ("To") van vacias.
     *   AJ = IF(AF="Mu", IF(G="N", AB*(1-2%), AB*(1+0%)), "")
     *   AK = IF(AG="Mu", IF(G="N", AB*(1+2%), AB*(1+4%)), "")
     *
     * @return array{0: ''|float, 1: ''|float}
     */
    private function rangoMuestraAlineacion(?CatCodificados $cat, mixed $pesoCrudo, mixed $ancho, mixed $largo): array
    {
        $ab = (float) ($cat?->PesoMuestra ?? 0);
        if ($ab <= 0 || ! $this->sePesaPorMuestra($pesoCrudo, $ancho, $largo)) {
            return ['', ''];
        }
        $esN = $this->toleranciaEsN($cat);

        return [
            round($esN ? $ab * 0.98 : $ab * 1.00, 3),
            round($esN ? $ab * 1.02 : $ab * 1.04, 3),
        ];
    }

    /**
     * Columna AF de la hoja: "To" (se pesa la pieza completa) vs "Mu" (se pesa una muestra).
     *   AF = IF(M<=220,"To", IF(AC<=0.3,"To", IF(AE>=AC*1.2,"To","Mu")))   con AC = (Anc*Lar)/10000
     *
     * ponytail: la tercera condicion (AE, area de tiras) necesita el largo de tira que en la
     * hoja se captura a mano fila por fila (col. AD) y no existe en la base; se omite.
     */
    private function sePesaPorMuestra(mixed $pesoCrudo, mixed $ancho, mixed $largo): bool
    {
        $m = (float) ($pesoCrudo ?? 0);
        if ($m <= 0 || $m <= 220) {
            return false;
        }
        $areaCrudo = ((float) ($ancho ?? 0) * (float) ($largo ?? 0)) / 10000;

        return $areaCrudo > 0.3;
    }

    /**
     * Columna G de la hoja: la tolerancia del catalogo es "N".
     */
    private function toleranciaEsN(?CatCodificados $cat): bool
    {
        return trim((string) ($cat?->Tolerancia ?? '')) === 'N';
    }

    /**
     * Primer valor con dato real (no null, no cadena vacia).
     */
    private function primeroConDato(mixed ...$valores): mixed
    {
        foreach ($valores as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * Concatena Calibre/Fibra para Cenefa Trama (ReqProgramaTejido).
     */
    private function concatCalibreFibra($calibre, $fibra): string
    {
        $c = trim((string) ($calibre ?? ''));
        $f = trim((string) ($fibra ?? ''));
        if ($c === '' && $f === '') {
            return '';
        }
        if ($c === '' || $f === '') {
            return $c.$f;
        }

        return $c.'/'.$f;
    }

    /**
     * Formatea fecha/datetime en español para la vista Alineación.
     *
     * @param  mixed  $value
     */
    private function formatDateAlineacion($value, string $format): string
    {
        try {
            return Carbon::parse($value)->locale('es')->translatedFormat($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
