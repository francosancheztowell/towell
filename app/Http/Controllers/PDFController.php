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
     *  - parcial = 1 (solo engomado): imprime registros con Impresion=0, luego los marca Impresion=1
     *  - reimpresion = 1: orden finalizada, para engomado solo registros con Impresion=0
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

            $esParcial = strtolower($tipo) === 'engomado' && $request->boolean('parcial');

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
            $registrosProduccion = $this->obtenerRegistrosProduccion($orden->Folio, $tipo, $esParcial, $request->boolean('reimpresion'));

            if ($esParcial && (!$registrosProduccion || $registrosProduccion->count() === 0)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'No hay registros pendientes de impresión. Todos los registros ya fueron impresos anteriormente.',
                ], 422);
            }

            // 4) Julios
            $julios = UrdJuliosOrden::where('Folio', $orden->Folio)
                ->whereNotNull('Julios')
                ->orderBy('Julios')
                ->get();

            // 5) Logo en base64 (sin usar GD, solo leyendo el archivo)
            $logoBase64 = $this->cargarLogoBase64();
            $esReimpresion = $request->boolean('reimpresion');

            // 6) Para engomado, agrupar registros por NoJulio para generar papeletas (1 papeleta por julio)
            $registrosPorJulio = collect();
            if (strtolower($tipo) === 'engomado' && $registrosProduccion && $registrosProduccion->count() > 0) {
                // Filtrar solo registros que tienen NoJulio asignado
                $registrosConJulio = $registrosProduccion->filter(function($registro) {
                    return !empty($registro->NoJulio);
                });
                
                if ($registrosConJulio->count() > 0) {
                    $registrosPorJulio = $registrosConJulio->groupBy('NoJulio');
                } else {
                    // Si no hay registros con NoJulio, usar todos los registros como una sola papeleta
                    $registrosPorJulio = collect([null => $registrosProduccion]);
                }
            }

            // 6) Renderizar vista Blade a HTML
            $vistaPdf = strtolower($tipo) === 'engomado'
                ? 'pdf.engomadopdf'
                : 'pdf.orden-urdido-engomado';

            $html = view($vistaPdf, [
                'orden'              => $orden,
                'ordenEngomado'      => $ordenEngomado, // Datos de engomado para urdido
                'registrosProduccion'=> $registrosProduccion,
                'registrosPorJulio'  => $registrosPorJulio, // Agrupados por NoJulio
                'julios'             => $julios,
                'tipo'               => $tipo,
                'logoBase64'         => $logoBase64,
                'esReimpresion'      => $esReimpresion,
            ])->render();

            // 6) Configurar DomPDF
            $dompdf = $this->crearDompdf($html);

            // 6.1) Si es impresión parcial de engomado, marcar registros como impresos
            if ($esParcial && strtolower($tipo) === 'engomado' && $registrosProduccion && $registrosProduccion->count() > 0) {
                $ids = $registrosProduccion->pluck('Id')->filter()->values()->all();
                if (!empty($ids)) {
                    try {
                        EngProduccionEngomado::whereIn('Id', $ids)->update(['Impresion' => 1]);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo marcar Impresion=1 (¿columna existe?): ' . $e->getMessage());
                    }
                }
            }

            // 7) Devolver PDF: descarga (attachment) en reimpresión; inline en el resto
            $nombreArchivo = $this->construirNombreArchivo($orden, $tipo, $esParcial);
            $disposition = $esReimpresion ? 'attachment' : 'inline';

            return response($dompdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', $disposition . '; filename="' . $nombreArchivo . '"');

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
     *
     * Para engomado parcial: solo registros con Finalizar=1 e Impresion=NULL/0.
     * Tras generar el PDF parcial se marcan con Impresion=1 para no repetirlos.
     * Al desmarcar Finalizar (→0), Impresion se resetea a NULL vía onRegistroDesmarcado.
     *
     * @param  bool  $esParcial       Si true (engomado): marca Impresion=1 en los registros tras generar el PDF
     * @param  bool  $esReimpresion   No afecta el filtrado en engomado
     */
    protected function obtenerRegistrosProduccion(string $folio, string $tipo, bool $esParcial = false, bool $esReimpresion = false)
    {
        $tipo = strtolower($tipo);

        if ($tipo === 'engomado') {
            try {
                // Solo registros marcados como listos (Finalizar=1) y aún no impresos (Impresion=NULL/0)
                return EngProduccionEngomado::where('Folio', $folio)
                    ->where('Finalizar', 1)
                    ->whereRaw('(Impresion IS NULL OR Impresion = 0)')
                    ->orderBy('Id')
                    ->get();
            } catch (\Throwable $e) {
                // Columna Impresion puede no existir aún; usar todos los registros con Finalizar=1
                Log::warning('Columna Impresion no encontrada, usando registros con Finalizar=1: ' . $e->getMessage());
                return EngProduccionEngomado::where('Folio', $folio)
                    ->where('Finalizar', 1)
                    ->orderBy('Id')
                    ->get();
            }
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
    protected function construirNombreArchivo($orden, string $tipo, bool $esParcial = false): string
    {
        $folio = $orden->Folio ?? 'ORDEN';
        $tipo  = strtoupper($tipo);
        $sufijo = $esParcial ? '_PARCIAL' : '';

        if ($tipo === 'ENGOMADO') {
            return "ORDEN_ENGOMADO_{$folio}{$sufijo}.pdf";
        }

        return "ORDEN_URDIDO_ENGOMADO_{$folio}.pdf";
    }
}
