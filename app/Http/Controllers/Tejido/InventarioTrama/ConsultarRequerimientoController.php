<?php

namespace App\Http\Controllers\Tejido\InventarioTrama;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejTrama;
use App\Models\Tejido\TejTramaConsumos;
use App\Services\Tejido\InventarioTrama\RequerimientoStatusService;

class ConsultarRequerimientoController extends Controller
{
    public function __construct(private readonly RequerimientoStatusService $statusService) {}

    /**
     * Muestra la vista de consultar requerimientos con datos de TejTrama y TejTramaConsumos
     */
    /**
     * Página contenedora; el listado y el detalle viven en el componente Livewire
     * (consulta perezosa por folio, sin cargar todos los consumos).
     */
    public function index()
    {
        return view('modulos.inventario-trama.consultar-requerimiento');
    }

    /**
     * Mostrar resumen de artículos de un folio
     */
    public function resumen($folio)
    {
        $requerimiento = TejTrama::where('Folio', $folio)->first();

        if (! $requerimiento) {
            abort(404, 'Requerimiento no encontrado');
        }

        $consumos = TejTramaConsumos::where('Folio', $folio)->get();

        // Agrupar por salón y preparar datos para la vista
        $consumosPorSalon = $consumos->groupBy('SalonTejidoId');

        return view('modulos.inventario-trama.resumen-articulos', [
            'requerimiento' => $requerimiento,
            'consumosPorSalon' => $consumosPorSalon,
            'totalConsumos' => $consumos->count(),
        ]);
    }
}
