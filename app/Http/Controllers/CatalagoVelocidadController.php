<?php

namespace App\Http\Controllers;

use App\Models\CatalagoVelocidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalagoVelocidadController extends Controller
{
    public function index(Request $request)
    {
        // Datos de ejemplo estáticos (solo frontend) - Muchos más datos para probar scroll
        $velocidad = collect([
            (object)['telar' => 'T001', 'tipo_hilo' => 'Algodón 100%', 'velocidad' => '850 RPM', 'densidad' => '120 hilos/cm'],
            (object)['telar' => 'T002', 'tipo_hilo' => 'Poliester 100%', 'velocidad' => '920 RPM', 'densidad' => '110 hilos/cm'],
            (object)['telar' => 'T003', 'tipo_hilo' => 'Algodón 50%', 'velocidad' => '780 RPM', 'densidad' => '130 hilos/cm'],
            (object)['telar' => 'T004', 'tipo_hilo' => 'Rayón 100%', 'velocidad' => '880 RPM', 'densidad' => '115 hilos/cm'],
            (object)['telar' => 'T005', 'tipo_hilo' => 'Algodón 80%', 'velocidad' => '900 RPM', 'densidad' => '125 hilos/cm'],
            (object)['telar' => 'T006', 'tipo_hilo' => 'Poliester 50%', 'velocidad' => '820 RPM', 'densidad' => '118 hilos/cm'],
            (object)['telar' => 'T007', 'tipo_hilo' => 'Viscosa 100%', 'velocidad' => '860 RPM', 'densidad' => '122 hilos/cm'],
            (object)['telar' => 'T008', 'tipo_hilo' => 'Algodón 60%', 'velocidad' => '940 RPM', 'densidad' => '128 hilos/cm'],
            (object)['telar' => 'T009', 'tipo_hilo' => 'Poliester 70%', 'velocidad' => '890 RPM', 'densidad' => '135 hilos/cm'],
            (object)['telar' => 'T010', 'tipo_hilo' => 'Algodón 70%', 'velocidad' => '910 RPM', 'densidad' => '140 hilos/cm'],
            (object)['telar' => 'T011', 'tipo_hilo' => 'Rayón 80%', 'velocidad' => '870 RPM', 'densidad' => '132 hilos/cm'],
            (object)['telar' => 'T012', 'tipo_hilo' => 'Poliester 90%', 'velocidad' => '930 RPM', 'densidad' => '138 hilos/cm'],
            (object)['telar' => 'T013', 'tipo_hilo' => 'Algodón 95%', 'velocidad' => '960 RPM', 'densidad' => '142 hilos/cm'],
            (object)['telar' => 'T014', 'tipo_hilo' => 'Poliester 85%', 'velocidad' => '880 RPM', 'densidad' => '145 hilos/cm'],
            (object)['telar' => 'T015', 'tipo_hilo' => 'Viscosa 75%', 'velocidad' => '840 RPM', 'densidad' => '148 hilos/cm'],
            (object)['telar' => 'T016', 'tipo_hilo' => 'Algodón 45%', 'velocidad' => '760 RPM', 'densidad' => '150 hilos/cm'],
            (object)['telar' => 'T017', 'tipo_hilo' => 'Rayón 65%', 'velocidad' => '830 RPM', 'densidad' => '152 hilos/cm'],
            (object)['telar' => 'T018', 'tipo_hilo' => 'Poliester 55%', 'velocidad' => '810 RPM', 'densidad' => '155 hilos/cm'],
            (object)['telar' => 'T019', 'tipo_hilo' => 'Algodón 85%', 'velocidad' => '920 RPM', 'densidad' => '158 hilos/cm'],
            (object)['telar' => 'T020', 'tipo_hilo' => 'Viscosa 95%', 'velocidad' => '950 RPM', 'densidad' => '160 hilos/cm'],
            (object)['telar' => 'T021', 'tipo_hilo' => 'Poliester 65%', 'velocidad' => '870 RPM', 'densidad' => '162 hilos/cm'],
            (object)['telar' => 'T022', 'tipo_hilo' => 'Algodón 75%', 'velocidad' => '890 RPM', 'densidad' => '165 hilos/cm'],
            (object)['telar' => 'T023', 'tipo_hilo' => 'Rayón 55%', 'velocidad' => '790 RPM', 'densidad' => '168 hilos/cm'],
            (object)['telar' => 'T024', 'tipo_hilo' => 'Poliester 75%', 'velocidad' => '910 RPM', 'densidad' => '170 hilos/cm'],
            (object)['telar' => 'T025', 'tipo_hilo' => 'Algodón 65%', 'velocidad' => '850 RPM', 'densidad' => '172 hilos/cm'],
            (object)['telar' => 'T026', 'tipo_hilo' => 'Viscosa 85%', 'velocidad' => '930 RPM', 'densidad' => '175 hilos/cm'],
            (object)['telar' => 'T027', 'tipo_hilo' => 'Poliester 45%', 'velocidad' => '770 RPM', 'densidad' => '178 hilos/cm'],
            (object)['telar' => 'T028', 'tipo_hilo' => 'Algodón 55%', 'velocidad' => '800 RPM', 'densidad' => '180 hilos/cm'],
            (object)['telar' => 'T029', 'tipo_hilo' => 'Rayón 75%', 'velocidad' => '900 RPM', 'densidad' => '182 hilos/cm'],
            (object)['telar' => 'T030', 'tipo_hilo' => 'Poliester 85%', 'velocidad' => '940 RPM', 'densidad' => '185 hilos/cm'],
            (object)['telar' => 'T031', 'tipo_hilo' => 'Algodón 35%', 'velocidad' => '740 RPM', 'densidad' => '188 hilos/cm'],
            (object)['telar' => 'T032', 'tipo_hilo' => 'Viscosa 65%', 'velocidad' => '860 RPM', 'densidad' => '190 hilos/cm'],
            (object)['telar' => 'T033', 'tipo_hilo' => 'Poliester 95%', 'velocidad' => '970 RPM', 'densidad' => '192 hilos/cm'],
            (object)['telar' => 'T034', 'tipo_hilo' => 'Algodón 25%', 'velocidad' => '720 RPM', 'densidad' => '195 hilos/cm'],
            (object)['telar' => 'T035', 'tipo_hilo' => 'Rayón 45%', 'velocidad' => '780 RPM', 'densidad' => '198 hilos/cm'],
            (object)['telar' => 'T036', 'tipo_hilo' => 'Poliester 35%', 'velocidad' => '750 RPM', 'densidad' => '200 hilos/cm'],
            (object)['telar' => 'T037', 'tipo_hilo' => 'Algodón 15%', 'velocidad' => '700 RPM', 'densidad' => '202 hilos/cm'],
            (object)['telar' => 'T038', 'tipo_hilo' => 'Viscosa 25%', 'velocidad' => '730 RPM', 'densidad' => '205 hilos/cm'],
            (object)['telar' => 'T039', 'tipo_hilo' => 'Poliester 15%', 'velocidad' => '710 RPM', 'densidad' => '208 hilos/cm'],
            (object)['telar' => 'T040', 'tipo_hilo' => 'Algodón 5%', 'velocidad' => '680 RPM', 'densidad' => '210 hilos/cm'],
        ]);

        // Aplicar filtros de búsqueda (solo frontend)
        if ($request->telar) {
            $velocidad = $velocidad->filter(function ($item) use ($request) {
                return stripos($item->telar, $request->telar) !== false;
            });
        }

        if ($request->tipo_hilo) {
            $velocidad = $velocidad->filter(function ($item) use ($request) {
                return stripos($item->tipo_hilo, $request->tipo_hilo) !== false;
            });
        }

        if ($request->velocidad) {
            $velocidad = $velocidad->filter(function ($item) use ($request) {
                return stripos($item->velocidad, $request->velocidad) !== false;
            });
        }

        if ($request->densidad) {
            $velocidad = $velocidad->filter(function ($item) use ($request) {
                return stripos($item->densidad, $request->densidad) !== false;
            });
        }

        // Mostrar todos los datos sin paginación
        $total = $velocidad->count();

        $noResults = $velocidad->isEmpty();

        return view('catalagos.catalagoVelocidad', [
            'velocidad' => $velocidad,
            'noResults' => $noResults,
            'total' => $total
        ]);
    }


    public function create()
    {
        return view('catalagos.velocidadCreate');
    }

    public function store(Request $request)
    {
        $request->validate([
            'telar' => 'required',
            'tipo_hilo' => 'required',
            'velocidad' => 'required',
            'densidad' => 'required',
        ]);

        CatalagoVelocidad::create([
            'telar' => $request->telar,
            'tipo_hilo' => $request->tipo_hilo,
            'velocidad' => $request->velocidad,
            'densidad' => $request->densidad,
        ]);

        return redirect()->route('velocidad.index')->with('success', 'Velocidad agregada exitosamente!');
    }
}
