<?php

namespace App\Http\Controllers\Urdido\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Sistema\SYSUsuario;
use App\Traits\ProduccionTrait;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class ModuloProduccionUrdidoController extends Controller
{
    use ProduccionTrait;

    protected function getProduccionModelClass(): string
    {
        return UrdProduccionUrdido::class;
    }

    protected function getProgramaModelClass(): string
    {
        return UrdProgramaUrdido::class;
    }

    protected function getDepartamento(): string
    {
        return 'Urdido';
    }

    protected function shouldRoundKgBruto(): bool
    {
        return false;
    }

    // ─── helpers privados específicos de Urdido ──────────────────────

    private function extractMcCoyNumber(?string $maquinaId): ?int
    {
        if (empty($maquinaId)) {
            return null;
        }

        if (stripos($maquinaId, 'karl mayer') !== false) {
            return 4;
        }

        if (preg_match('/mc\s*coy\s*(\d+)/i', $maquinaId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    // ─── index() refactorizado ───────────────────────────────────────

    public function index(Request $request)
    {
        $ordenId = $request->query('orden_id');

        if ($request->query('check_only') === 'true' && $ordenId) {
            return $this->handleCheckOnlyRequest($ordenId);
        }

        if (!$ordenId) {
            return view('modulos.urdido.modulo-produccion-urdido', $this->getEmptyViewData());
        }

        $orden = UrdProgramaUrdido::find($ordenId);
        if (!$orden) {
            return redirect()->route('urdido.programar.urdido')->with('error', 'Orden no encontrada');
        }

        $redirect = $this->transitionToEnProceso($orden);
        if ($redirect) {
            return $redirect;
        }

        $julios = $this->getJuliosForOrder($orden);
        $totalRegistros = $this->calculateTotalRegistros($julios);

        $this->ensureProductionRecordsExist($orden, $julios, $totalRegistros);
        $this->traitAutollenarOficial1EnRegistrosSinHoraInicial($orden);

        $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)->orderBy('Id')->get();

        return view('modulos.urdido.modulo-produccion-urdido',
            $this->prepareViewData($orden, $julios, $registrosProduccion, $totalRegistros));
    }

    private function handleCheckOnlyRequest(int $ordenId): JsonResponse
    {
        $orden = UrdProgramaUrdido::find($ordenId);
        if (!$orden) {
            return response()->json(['puedeCrear' => false, 'tieneRegistros' => false, 'error' => 'Orden no encontrada'], 404);
        }

        $registrosCount = UrdProduccionUrdido::where('Folio', $orden->Folio)->count();
        $usuarioActual = Auth::user();

        return response()->json([
            'puedeCrear' => true,
            'tieneRegistros' => $registrosCount > 0,
            'usuarioArea' => $usuarioActual ? ($usuarioActual->area ?? null) : null,
        ]);
    }

    private function getEmptyViewData(): array
    {
        return [
            'orden' => null,
            'julios' => collect([]),
            'engomado' => null,
            'metros' => '0',
            'destino' => null,
            'hilo' => null,
            'tipoAtado' => null,
            'nomEmpl' => null,
            'observaciones' => '',
            'totalRegistros' => 0,
            'registrosProduccion' => collect([]),
        ];
    }

    private function transitionToEnProceso(UrdProgramaUrdido $orden): ?RedirectResponse
    {
        if ($orden->Status !== 'Programado') {
            return null;
        }

        $mcCoyActual = $this->extractMcCoyNumber($orden->MaquinaId);
        $limitePorMaquina = 2;

        if ($mcCoyActual !== null) {
            $ordenesEnProceso = UrdProgramaUrdido::where('Status', 'En Proceso')
                ->whereNotNull('MaquinaId')
                ->where('Id', '!=', $orden->Id)
                ->get()
                ->filter(fn ($item) => $this->extractMcCoyNumber($item->MaquinaId) === $mcCoyActual)
                ->count();

            if ($ordenesEnProceso >= $limitePorMaquina) {
                $nombreMaquina = $mcCoyActual === 4 ? 'Karl Mayer' : "MC Coy {$mcCoyActual}";
                return redirect()->route('urdido.programar.urdido')
                    ->with('error', "Ya existen {$limitePorMaquina} ordenes con status \"En Proceso\" en {$nombreMaquina}. No se puede cargar otra orden hasta finalizar alguna de las actuales.");
            }
        }

        try {
            $orden->Status = 'En Proceso';
            $orden->save();
        } catch (\Throwable $e) {
            Log::error('Error al actualizar status a "En Proceso"', [
                'folio' => $orden->Folio,
                'orden_id' => $orden->Id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getJuliosForOrder(UrdProgramaUrdido $orden): Collection
    {
        return UrdJuliosOrden::where('Folio', $orden->Folio)
            ->whereNotNull('Julios')
            ->orderBy('Julios')
            ->get();
    }

    private function calculateTotalRegistros(Collection $julios): int
    {
        $total = 0;
        foreach ($julios as $julio) {
            $n = (int) ($julio->Julios ?? 0);
            if ($n > 0) {
                $total += $n;
            }
        }
        return $total;
    }

    private function ensureProductionRecordsExist(UrdProgramaUrdido $orden, Collection $julios, int $totalRegistros): void
    {
        if ($julios->count() === 0) {
            return;
        }

        try {
            $existentes = UrdProduccionUrdido::where('Folio', $orden->Folio)->orderBy('Id')->get();
            $faltantes = max(0, $totalRegistros - $existentes->count());

            if ($faltantes <= 0) {
                return;
            }

            $user = Auth::user();
            $claveUsuario = $user ? ($user->numero_empleado ?? null) : null;
            $nombreUsuario = $user ? ($user->nombre ?? null) : null;
            $turnoUsuario = $user ? ($user->turno ?? null) : null;
            if (!$turnoUsuario) {
                $turnoUsuario = \App\Helpers\TurnoHelper::getTurnoActual();
            }
            $metrosOrden = $orden->Metros ?? 0;

            $registrosPorHilos = [];
            foreach ($existentes as $reg) {
                $key = (string) ($reg->Hilos ?? 'null');
                $registrosPorHilos[$key] = ($registrosPorHilos[$key] ?? 0) + 1;
            }

            $registrosACrear = [];
            foreach ($julios as $julio) {
                $numJulio = (int) ($julio->Julios ?? 0);
                $hilos = $julio->Hilos ?? null;

                if ($numJulio > 0 && $hilos !== null) {
                    $key = (string) $hilos;
                    $existentesHilos = $registrosPorHilos[$key] ?? 0;
                    $faltantesHilos = max(0, $numJulio - $existentesHilos);

                    for ($i = 0; $i < $faltantesHilos; $i++) {
                        $data = [
                            'Folio' => $orden->Folio,
                            'TipoAtado' => $orden->TipoAtado ?? null,
                            'NoJulio' => null,
                            'Hilos' => $hilos,
                            'Fecha' => now()->format('Y-m-d'),
                        ];
                        if (!empty($claveUsuario)) $data['CveEmpl1'] = $claveUsuario;
                        if (!empty($nombreUsuario)) $data['NomEmpl1'] = $nombreUsuario;
                        if ($metrosOrden > 0) $data['Metros1'] = round($metrosOrden, 2);
                        if (!empty($turnoUsuario)) $data['Turno1'] = (int) $turnoUsuario;

                        $registrosACrear[] = $data;
                    }
                }
            }

            foreach ($registrosACrear as $data) {
                UrdProduccionUrdido::create($data);
            }
        } catch (\Throwable $e) {
            Log::error('Error al crear registros en UrdProduccionUrdido', [
                'folio' => $orden->Folio,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function prepareViewData(UrdProgramaUrdido $orden, Collection $julios, Collection $registrosProduccion, int $totalRegistros): array
    {
        $engomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();

        $user = Auth::user();

        return [
            'orden' => $orden,
            'julios' => $julios,
            'engomado' => $engomado,
            'metros' => $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0',
            'destino' => $orden->SalonTejidoId ?? ($engomado ? $engomado->SalonTejidoId : null),
            'hilo' => $orden->Fibra ?? ($engomado ? $engomado->Fibra : null),
            'tipoAtado' => $orden->TipoAtado ?? ($engomado ? $engomado->TipoAtado : null),
            'nomEmpl' => $orden->NomEmpl ?? null,
            'observaciones' => $engomado ? ($engomado->Obs ?? '') : '',
            'totalRegistros' => $totalRegistros,
            'loteProveedor' => $orden->LoteProveedor ?? null,
            'registrosProduccion' => $registrosProduccion,
            'usuarioNombre' => $user ? ($user->nombre ?? '') : '',
            'usuarioClave' => $user ? ($user->numero_empleado ?? '') : '',
            'usuarioArea' => $user ? ($user->area ?? null) : null,
        ];
    }

    // ─── endpoints específicos de Urdido ─────────────────────────────

    public function actualizarCamposProduccion(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:Hilos,Hilatura,Maquina,Operac,Transf',
                'valor' => 'nullable|integer|min:0|max:9999',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $campo = $request->campo;
            $registro->$campo = $request->valor !== null ? (int) $request->valor : null;
            $registro->save();
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($campo) . ' actualizado correctamente',
                'data' => ['campo' => $campo, 'valor' => $registro->$campo],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar campos de producción', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar campo: ' . $e->getMessage()], 500);
        }
    }

    public function getUsuariosUrdido(): JsonResponse
    {
        try {
            $usuarios = SYSUsuario::select(['idusuario', 'numero_empleado', 'nombre', 'turno'])
                ->where('area', 'Urdido')
                ->whereNotNull('numero_empleado')
                ->orderBy('nombre')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->idusuario,
                    'numero_empleado' => $u->numero_empleado,
                    'nombre' => $u->nombre,
                    'turno' => $u->turno,
                ]);

            return response()->json(['success' => true, 'data' => $usuarios]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener usuarios de Urdido', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al obtener usuarios: ' . $e->getMessage()], 500);
        }
    }

    public function finalizar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $orden = UrdProgramaUrdido::find($request->orden_id);

            if (!$orden) {
                return response()->json(['success' => false, 'error' => 'Orden no encontrada'], 404);
            }

            if (!in_array($orden->Status, ['En Proceso', 'Parcial'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo se puede finalizar una orden en estado "En Proceso" o "Parcial". Estado actual: ' . $orden->Status,
                ], 422);
            }

            if ($this->traitHasNegativeKgNetoByFolio($orden->Folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden porque existen registros con Kg Neto negativo.',
                ], 422);
            }

            // Validar horas
            $errorHoras = $this->validarHorasRegistros($orden->Folio);
            if ($errorHoras) {
                return response()->json(['success' => false, 'error' => $errorHoras], 422);
            }

            // Eliminar registros sin HoraInicial o HoraFinal
            UrdProduccionUrdido::where('Folio', $orden->Folio)
                ->where(function ($query) {
                    $query->whereNull('HoraInicial')->orWhereNull('HoraFinal');
                })
                ->delete();

            UrdProduccionUrdido::where('Folio', $orden->Folio)->update(['Finalizar' => 1]);

            $orden->Status = 'Finalizado';
            $orden->save();

            return response()->json([
                'success' => true,
                'message' => 'Orden finalizada correctamente',
                'data' => [
                    'orden_id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'status' => $orden->Status,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al finalizar orden de urdido', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al finalizar la orden: ' . $e->getMessage()], 500);
        }
    }
}
