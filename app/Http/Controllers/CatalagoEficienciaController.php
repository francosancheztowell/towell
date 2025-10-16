<?php

namespace App\Http\Controllers;

use App\Models\CatalagoEficiencia;
use App\Models\CatalagoVelocidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalagoEficienciaController extends Controller
{
    public function index(Request $request)
    {
        // Datos de ejemplo estáticos (solo frontend) - Muchos más datos para probar scroll
        $eficiencia = collect([
            (object)['telar' => 'T001', 'tipo_hilo' => 'Algodón 100%', 'eficiencia' => '85%', 'densidad' => '120 hilos/cm'],
            (object)['telar' => 'T002', 'tipo_hilo' => 'Poliester 100%', 'eficiencia' => '92%', 'densidad' => '110 hilos/cm'],
            (object)['telar' => 'T003', 'tipo_hilo' => 'Algodón 50%', 'eficiencia' => '78%', 'densidad' => '130 hilos/cm'],
            (object)['telar' => 'T004', 'tipo_hilo' => 'Rayón 100%', 'eficiencia' => '88%', 'densidad' => '115 hilos/cm'],
            (object)['telar' => 'T005', 'tipo_hilo' => 'Algodón 80%', 'eficiencia' => '90%', 'densidad' => '125 hilos/cm'],
            (object)['telar' => 'T006', 'tipo_hilo' => 'Poliester 50%', 'eficiencia' => '82%', 'densidad' => '118 hilos/cm'],
            (object)['telar' => 'T007', 'tipo_hilo' => 'Viscosa 100%', 'eficiencia' => '86%', 'densidad' => '122 hilos/cm'],
            (object)['telar' => 'T008', 'tipo_hilo' => 'Algodón 60%', 'eficiencia' => '94%', 'densidad' => '128 hilos/cm'],
            (object)['telar' => 'T009', 'tipo_hilo' => 'Poliester 70%', 'eficiencia' => '89%', 'densidad' => '135 hilos/cm'],
            (object)['telar' => 'T010', 'tipo_hilo' => 'Algodón 70%', 'eficiencia' => '91%', 'densidad' => '140 hilos/cm'],
            (object)['telar' => 'T011', 'tipo_hilo' => 'Rayón 80%', 'eficiencia' => '87%', 'densidad' => '132 hilos/cm'],
            (object)['telar' => 'T012', 'tipo_hilo' => 'Poliester 90%', 'eficiencia' => '93%', 'densidad' => '138 hilos/cm'],
            (object)['telar' => 'T013', 'tipo_hilo' => 'Algodón 95%', 'eficiencia' => '96%', 'densidad' => '142 hilos/cm'],
            (object)['telar' => 'T014', 'tipo_hilo' => 'Poliester 85%', 'eficiencia' => '88%', 'densidad' => '145 hilos/cm'],
            (object)['telar' => 'T015', 'tipo_hilo' => 'Viscosa 75%', 'eficiencia' => '84%', 'densidad' => '148 hilos/cm'],
            (object)['telar' => 'T016', 'tipo_hilo' => 'Algodón 45%', 'eficiencia' => '76%', 'densidad' => '150 hilos/cm'],
            (object)['telar' => 'T017', 'tipo_hilo' => 'Rayón 65%', 'eficiencia' => '83%', 'densidad' => '152 hilos/cm'],
            (object)['telar' => 'T018', 'tipo_hilo' => 'Poliester 55%', 'eficiencia' => '81%', 'densidad' => '155 hilos/cm'],
            (object)['telar' => 'T019', 'tipo_hilo' => 'Algodón 85%', 'eficiencia' => '92%', 'densidad' => '158 hilos/cm'],
            (object)['telar' => 'T020', 'tipo_hilo' => 'Viscosa 95%', 'eficiencia' => '95%', 'densidad' => '160 hilos/cm'],
            (object)['telar' => 'T021', 'tipo_hilo' => 'Poliester 65%', 'eficiencia' => '87%', 'densidad' => '162 hilos/cm'],
            (object)['telar' => 'T022', 'tipo_hilo' => 'Algodón 75%', 'eficiencia' => '89%', 'densidad' => '165 hilos/cm'],
            (object)['telar' => 'T023', 'tipo_hilo' => 'Rayón 55%', 'eficiencia' => '79%', 'densidad' => '168 hilos/cm'],
            (object)['telar' => 'T024', 'tipo_hilo' => 'Poliester 75%', 'eficiencia' => '91%', 'densidad' => '170 hilos/cm'],
            (object)['telar' => 'T025', 'tipo_hilo' => 'Algodón 65%', 'eficiencia' => '85%', 'densidad' => '172 hilos/cm'],
            (object)['telar' => 'T026', 'tipo_hilo' => 'Viscosa 85%', 'eficiencia' => '93%', 'densidad' => '175 hilos/cm'],
            (object)['telar' => 'T027', 'tipo_hilo' => 'Poliester 45%', 'eficiencia' => '77%', 'densidad' => '178 hilos/cm'],
            (object)['telar' => 'T028', 'tipo_hilo' => 'Algodón 55%', 'eficiencia' => '80%', 'densidad' => '180 hilos/cm'],
            (object)['telar' => 'T029', 'tipo_hilo' => 'Rayón 75%', 'eficiencia' => '90%', 'densidad' => '182 hilos/cm'],
            (object)['telar' => 'T030', 'tipo_hilo' => 'Poliester 85%', 'eficiencia' => '94%', 'densidad' => '185 hilos/cm'],
            (object)['telar' => 'T031', 'tipo_hilo' => 'Algodón 35%', 'eficiencia' => '74%', 'densidad' => '188 hilos/cm'],
            (object)['telar' => 'T032', 'tipo_hilo' => 'Viscosa 65%', 'eficiencia' => '86%', 'densidad' => '190 hilos/cm'],
            (object)['telar' => 'T033', 'tipo_hilo' => 'Poliester 95%', 'eficiencia' => '97%', 'densidad' => '192 hilos/cm'],
            (object)['telar' => 'T034', 'tipo_hilo' => 'Algodón 25%', 'eficiencia' => '72%', 'densidad' => '195 hilos/cm'],
            (object)['telar' => 'T035', 'tipo_hilo' => 'Rayón 45%', 'eficiencia' => '78%', 'densidad' => '198 hilos/cm'],
            (object)['telar' => 'T036', 'tipo_hilo' => 'Poliester 35%', 'eficiencia' => '75%', 'densidad' => '200 hilos/cm'],
            (object)['telar' => 'T037', 'tipo_hilo' => 'Algodón 15%', 'eficiencia' => '70%', 'densidad' => '202 hilos/cm'],
            (object)['telar' => 'T038', 'tipo_hilo' => 'Viscosa 25%', 'eficiencia' => '73%', 'densidad' => '205 hilos/cm'],
            (object)['telar' => 'T039', 'tipo_hilo' => 'Poliester 15%', 'eficiencia' => '71%', 'densidad' => '208 hilos/cm'],
            (object)['telar' => 'T040', 'tipo_hilo' => 'Algodón 5%', 'eficiencia' => '68%', 'densidad' => '210 hilos/cm'],
        ]);

        // Aplicar filtros de búsqueda (solo frontend)
        if ($request->telar) {
            $eficiencia = $eficiencia->filter(function ($item) use ($request) {
                return stripos($item->telar, $request->telar) !== false;
            });
        }

        if ($request->tipo_hilo) {
            $eficiencia = $eficiencia->filter(function ($item) use ($request) {
                return stripos($item->tipo_hilo, $request->tipo_hilo) !== false;
            });
        }

        if ($request->eficiencia) {
            $eficiencia = $eficiencia->filter(function ($item) use ($request) {
                return stripos($item->eficiencia, $request->eficiencia) !== false;
            });
        }

        if ($request->densidad) {
            $eficiencia = $eficiencia->filter(function ($item) use ($request) {
                return stripos($item->densidad, $request->densidad) !== false;
            });
        }

        // Mostrar todos los datos sin paginación
        $total = $eficiencia->count();

        $noResults = $eficiencia->isEmpty();

        return view('catalagos.catalagoEficiencia', [
            'eficiencia' => $eficiencia,
            'noResults' => $noResults,
            'total' => $total
        ]);
    }

    public function create()
    {
        return view('catalagos.eficienciaCreate');
    }

    public function store(Request $request)
    {
        // Validación de los datos
        $request->validate([
            'telar' => 'required',
            'tipo_hilo' => 'required',
            'eficiencia' => 'required',
            'densidad' => 'required',
        ]);

        // Crear una nueva entrada en la tabla de eficiencia
        CatalagoEficiencia::create([
            'telar' => $request->telar,
            'tipo_hilo' => $request->tipo_hilo,
            'eficiencia' => $request->eficiencia,
            'densidad' => $request->densidad,
        ]);

        // Redirigir a la lista de eficiencias con un mensaje de éxito
        return redirect()->route('eficiencia.index')->with('success', 'Eficiencia agregada exitosamente!');
    }

    public function edit($id)
    {
        $registro = CatalagoEficiencia::findOrFail($id);
        return view('catalagos.Eficiencia-edit', compact('registro'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'telar' => 'required',
            'tipo_hilo' => 'required',
            'eficiencia' => 'required',
            'densidad' => 'required',
        ]);

        $registro = CatalagoEficiencia::findOrFail($id);
        $registro->update($request->all());

        return redirect()->route('eficiencia.index')->with('success', 'Registro actualizado correctamente.');
    }

    public function destroy($id)
    {
        $registro = CatalagoVelocidad::findOrFail($id);
        $registro->delete();

        return redirect()->route('eficiencia.index')->with('success', 'Registro eliminado correctamente.');
    }
}
