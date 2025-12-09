<?php
namespace App\Http\Controllers;

use App\Models\TelTelaresOperador;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use League\Config\Exception\ValidationException;
use Illuminate\Support\Facades\DB;

class TelDesarrolladoresController extends Controller
{
    public function index(Request $request)
    {

        $telares = $this->obtenerTelares();

        return view('modulos.desarrolladores.desarrolladores', compact( 'telares'));
    }

    protected function obtenerTelares()
    {
        return TelTelaresOperador::select('NoTelarId')
            ->whereNotNull('NoTelarId')
            ->groupBy('NoTelarId')
            ->orderBy('NoTelarId')
            ->get();
    }

    public function obtenerProducciones(Request $request, $telarId)
    {
        try {
            $producciones = \App\Models\ReqProgramaTejido::where('NoTelarId', $telarId)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->select('NoProduccion', 'NombreProducto', 'FechaInicio')
                ->distinct()
                ->orderBy('NoProduccion')
                ->get();

            return response()->json([
                'success' => true,
                'producciones' => $producciones
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las producciones: ' . $e->getMessage()
            ], 500);
        }
    }

    public function formularioDesarrollador(Request $request, $telarId, $noProduccion)
    {
        $datosProduccion = \App\Models\ReqProgramaTejido::where('NoTelarId', $telarId)
            ->where('NoProduccion', $noProduccion)
            ->first();
        
        return view('modulos.desarrolladores.formulario', compact('datosProduccion', 'telarId', 'noProduccion'));
    }

    protected function store(Request $request){
        try {
            $validated = $request->validate([
                'NoTelarId' => 'required|string',
                'NumeroJulioRizo' => 'required|string|max:50',
                'NumeroJulioPie' => 'required|string|max:50',
                'TotalPasadasDibujo' => 'required|numeric|min:0',
                'HoraInicio' => 'required|date_format:H:i',
                'EficienciaInicio' => 'required|numeric|min:0|max:100',
                'HoraFinal' => 'required|date_format:H:i',
                'EficienciaFinal' => 'required|numeric|min:0|max:100',
                'Desarrollador' => 'required|string|max:100',
                'TramaAnchoPeine' => 'required|numeric|min:0',
                'DesperdicioTrama' => 'required|numeric|min:0',
                'LongitudLuchaTot' => 'required|numeric|min:0',
                'CodificacionModelo' => 'required|string|max:100'
            ]);

            Log::info('Datos de desarrollador guardados:', $validated);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Datos guardados correctamente'
                ]);
            }

            return redirect()->route('desarrolladores')
                ->with('success', 'Datos guardados correctamente');
        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $e->errors()
                ], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            Log::error('Error al guardar datos de desarrollador: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error al guardar los datos'
                ], 500);
            }
            
            return back()->with('error', 'Ocurrió un error al guardar los datos')->withInput();
        }
    }


}
