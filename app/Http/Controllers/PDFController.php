<?php

namespace App\Http\Controllers;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdJuliosOrden;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PDFController extends Controller
{
    /**
     * Generar PDF de orden de urdido y engomado.
     *
     * Parámetros (query string):
     *  - orden_id (obligatorio)
     *  - tipo = 'urdido' | 'engomado' (por defecto 'urdido')
     */
    public function generarPDFUrdidoEngomado(Request $request)
    {
        try {
            $ordenId = $request->query('orden_id');
            $tipo = $request->query('tipo', 'urdido'); // 'urdido' o 'engomado'

            if (!$ordenId) {
                return response()->json([
                    'success' => false,
                    'error'   => 'El parámetro "orden_id" es requerido.',
                ], 422);
            }

            // 1) Obtener orden según el tipo
            $orden = $this->obtenerOrden($ordenId, $tipo);

            if (!$orden) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Orden no encontrada.',
                ], 404);
            }

            if ($request->boolean('reimpresion')) {
                if (strtolower($tipo) === 'engomado' && ($orden->Status ?? '') !== 'Finalizado') {
                    return response()->json([
                        'success' => false,
                        'error'   => 'Solo se pueden reimprimir ordenes con status Finalizado.',
                    ], 422);
                }
                if (strtolower($tipo) === 'urdido' && ($orden->Status ?? '') !== 'Finalizado') {
                    return response()->json([
                        'success' => false,
                        'error'   => 'Solo se pueden reimprimir ordenes con status Finalizado.',
                    ], 422);
                }
            }

            // 2) Si es urdido, obtener también los datos de engomado para el footer
            $ordenEngomado = null;
            if (strtolower($tipo) === 'urdido' && $orden->Folio) {
                $ordenEngomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();
            }

            // 3) Registros de producción
            $registrosProduccion = $this->obtenerRegistrosProduccion($orden->Folio, $tipo);

            // 4) Julios
            $julios = UrdJuliosOrden::where('Folio', $orden->Folio)
                ->whereNotNull('Julios')
                ->orderBy('Julios')
                ->get();

            // 5) Logo en base64 (sin usar GD, solo leyendo el archivo)
            $logoBase64 = $this->cargarLogoBase64();
            $esReimpresion = $request->boolean('reimpresion');

            // 6) Renderizar vista Blade a HTML
            $vistaPdf = strtolower($tipo) === 'engomado'
                ? 'pdf.engomadopdf'
                : 'pdf.orden-urdido-engomado';

            $html = view($vistaPdf, [
                'orden'              => $orden,
                'ordenEngomado'      => $ordenEngomado, // Datos de engomado para urdido
                'registrosProduccion'=> $registrosProduccion,
                'julios'             => $julios,
                'tipo'               => $tipo,
                'logoBase64'         => $logoBase64,
                'esReimpresion'      => $esReimpresion,
            ])->render();

            // 6) Configurar DomPDF
            $dompdf = $this->crearDompdf($html);

            // 7) Devolver PDF inline
            $nombreArchivo = $this->construirNombreArchivo($orden, $tipo);

            return response($dompdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $nombreArchivo . '"');

        } catch (\Throwable $e) {
            Log::error('Error al generar PDF de urdido/engomado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Error al generar PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener la orden según tipo.
     */
    protected function obtenerOrden(int|string $id, string $tipo)
    {
        $tipo = strtolower($tipo);

        return $tipo === 'engomado'
            ? EngProgramaEngomado::find($id)
            : UrdProgramaUrdido::find($id);
    }

    /**
     * Obtener registros de producción según el tipo.
     */
    protected function obtenerRegistrosProduccion(string $folio, string $tipo)
    {
        $tipo = strtolower($tipo);

        if ($tipo === 'engomado') {
            return EngProduccionEngomado::where('Folio', $folio)
                ->orderBy('Id')
                ->get();
        }

        return UrdProduccionUrdido::where('Folio', $folio)
            ->orderBy('Id')
            ->get();
    }

    /**
     * Cargar el logo como base64 sin usar GD.
     *
     * @return string|null
     */
    protected function cargarLogoBase64(): ?string
    {
        try {
            $logoPath = public_path('images/fondosTowell/logo.png');

            if (!file_exists($logoPath) || !is_readable($logoPath)) {
                Log::warning('Logo no encontrado o sin permisos de lectura para PDF', [
                    'path'   => $logoPath,
                    'exists' => file_exists($logoPath),
                    'readable' => is_readable($logoPath),
                ]);
                return null;
            }

            $logoData = file_get_contents($logoPath);

            if ($logoData === false || $logoData === '') {
                Log::warning('No se pudo leer el archivo de logo o está vacío', [
                    'path' => $logoPath,
                ]);
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($logoData);
        } catch (\Throwable $e) {
            Log::warning('Excepción al cargar el logo para PDF', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear y configurar instancia de Dompdf.
     */
    protected function crearDompdf(string $html): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);     // por si en el futuro usas imágenes remotas
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');

        try {
            $dompdf->render();
        } catch (\Throwable $e) {
            Log::error('Error al renderizar PDF en Dompdf', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $dompdf;
    }

    /**
     * Construir nombre de archivo para el PDF.
     */
    protected function construirNombreArchivo($orden, string $tipo): string
    {
        $folio = $orden->Folio ?? 'ORDEN';
        $tipo  = strtoupper($tipo);

        if ($tipo === 'ENGOMADO') {
            return "ORDEN_ENGOMADO_{$folio}.pdf";
        }

        return "ORDEN_URDIDO_ENGOMADO_{$folio}.pdf";
    }
}
