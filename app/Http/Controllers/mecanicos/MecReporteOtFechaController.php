<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos;

use App\Http\Controllers\Controller;
use App\Services\Mecanicos\ReporteOtFechaService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Reporte "OT x Fecha" (SYSRoles idrol 209). El permiso de acceso lo aplica la ruta
 * (module.permission:acceso,209).
 */
class MecReporteOtFechaController extends Controller
{
    public function __construct(
        private readonly ReporteOtFechaService $reporte,
    ) {}

    public function index(Request $request): View
    {
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));
        $mecanico = trim((string) $request->query('mecanico', ''));
        $reporte = null;
        $error = null;

        if ($desde !== '' || $hasta !== '') {
            try {
                $reporte = $this->reporte->build($desde, $hasta, $mecanico);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        } else {
            $desde = $hasta = $this->reporte->hoy();
        }

        return view('modulos.mecanicos.reportes.ot-fecha', [
            'desde' => $desde,
            'hasta' => $hasta,
            'mecanico' => $mecanico,
            'mecanicos' => $this->reporte->catalogoMecanicos(),
            'reporte' => $reporte,
            'error' => $error,
        ]);
    }
}
