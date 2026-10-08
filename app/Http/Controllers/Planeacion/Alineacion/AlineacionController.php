<?php

namespace App\Http\Controllers\Planeacion\Alineacion;

use App\Exports\AlineacionExport;
use App\Http\Controllers\Controller;
use App\Services\Planeacion\Alineacion\AlineacionItemsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AlineacionController extends Controller
{
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

    public function __construct(private readonly AlineacionItemsService $items) {}

    /**
     * Vista principal de Alineación (programa de tejido en proceso).
     * Tolerancia, Raz s/n, Tipo Rizo, Tipo Plano y Observaciones se obtienen de CatCodificados
     * por OrdenTejido (No orden). Si no existe la orden, no se hace nada.
     */
    public function index(): View
    {
        try {
            $items = $this->items->obtenerItems();
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
            $items = $this->items->obtenerItems();
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
            $items = $this->items->obtenerItems();
        } catch (\Throwable $e) {
            Log::error('Alineacion: error al exportar Excel', ['error' => $e->getMessage()]);
            abort(500, 'No se pudo generar el Excel de Alineación.');
        }

        $nombreArchivo = 'Alineacion_'.now()->format('d-m-Y_H-i').'.xlsx';

        return Excel::download(new AlineacionExport(
            $items,
            AlineacionItemsService::COLUMNAS,
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
            $items = $this->items->obtenerItems();
        } catch (\Throwable $e) {
            Log::error('Alineacion: error al exportar PDF', ['error' => $e->getMessage()]);
            abort(500, 'No se pudo generar el PDF de Alineación.');
        }

        $html = view('pdf.alineacion', [
            'items' => $items,
            'columnas' => AlineacionItemsService::COLUMNAS,
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
}
