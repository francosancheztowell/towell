<?php

namespace App\Http\Controllers\Urdido\Configuracion;

use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Traits\ProduccionTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

    protected function maxKgNetoAllowed(): ?float
    {
        return 700.0;
    }

    protected function getModuleNameForPermissions(): string
    {
        return 'Producción Urdido';
    }

    /**
     * Verifica si el usuario puede editar según permisos del módulo (no área).
     * Usa el módulo asociado a la ruta de producción urdido en SYSRoles.
     */
    private function usuarioPuedeEditar(): bool
    {
        $modulo = $this->getModuleNameForPermissions();

        return function_exists('userCan') && userCan('modificar', $modulo);
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

    /**
     * Hilos en la fila de produccion es una PROYECCION del plan de julios: es
     * readonly en la captura, el operador nunca lo teclea. Mantenerlo sincronizado
     * a mano desde cada ruta de edicion del programa es lo que dejaba grupos
     * huerfanos (plan dice 395, filas dicen 461) y duplicaba la orden. Aqui se
     * vuelve a derivar por posicion, sin importar que ruta causo el desajuste.
     *
     * Una fila CON captura o en AX no se toca: su Hilos es historia de produccion,
     * no un hueco por llenar. Se registra para que un humano decida.
     */
    private function reproyectarHilosDesdePlan(UrdProgramaUrdido $orden, Collection $julios, Collection $existentes): void
    {
        // El plan expandido: una entrada de Hilos por cada julio, en el mismo
        // orden en que getJuliosForOrder() creo las filas originalmente.
        $plan = [];
        foreach ($julios as $julio) {
            $n = (int) ($julio->Julios ?? 0);
            $hilos = $julio->Hilos !== null ? (int) $julio->Hilos : null;
            for ($i = 0; $i < $n; $i++) {
                $plan[] = $hilos;
            }
        }

        if (empty($plan)) {
            return;
        }

        $realineadas = 0;
        $intocables = [];

        foreach ($existentes->values() as $pos => $registro) {
            if (! array_key_exists($pos, $plan)) {
                break; // sobrantes: los resuelve el conteo, no esta funcion
            }

            $esperado = $plan[$pos];
            $actual = $registro->Hilos !== null ? (int) $registro->Hilos : null;

            if ($actual === $esperado) {
                continue;
            }

            if ($this->registroBloqueadoPorAx($registro) || $this->registroTieneCaptura($registro)) {
                $intocables[] = ['id' => $registro->Id, 'hilos_fila' => $actual, 'hilos_plan' => $esperado];

                continue;
            }

            $registro->Hilos = $esperado;
            $registro->save();
            $realineadas++;
        }

        if ($realineadas > 0 || ! empty($intocables)) {
            Log::warning('Hilos de produccion desalineado del plan de julios', [
                'folio' => $orden->Folio,
                'realineadas' => $realineadas,
                'con_captura_sin_tocar' => $intocables,
            ]);
        }
    }

    /**
     * Version por registro de scopeTieneCaptura(): julio, peso o roturas.
     */
    private function registroTieneCaptura($registro): bool
    {
        if (trim((string) ($registro->NoJulio ?? '')) !== '') {
            return true;
        }

        if ($registro->KgBruto !== null && (float) $registro->KgBruto != 0.0) {
            return true;
        }

        foreach (['Hilatura', 'Maquina', 'Operac', 'Transf', 'Vueltas', 'Diametro'] as $campo) {
            if ((float) ($registro->{$campo} ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Una fila tiene captura real del operador si trae julio, peso o roturas.
     *
     * Fecha, Oficial 1, Turno y Metros NO cuentan: el trait los autollena en
     * cada carga de página, así que un esqueleto nunca corrido también los trae.
     */
    private function scopeTieneCaptura($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNotNull('NoJulio')->where('NoJulio', '!=', '');
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('KgBruto')->where('KgBruto', '!=', 0);
            })
            ->orWhere('Hilatura', '>', 0)
            ->orWhere('Maquina', '>', 0)
            ->orWhere('Operac', '>', 0)
            ->orWhere('Transf', '>', 0)
            ->orWhere('Vueltas', '>', 0)
            ->orWhere('Diametro', '>', 0);
    }

    // ─── index() refactorizado ───────────────────────────────────────

    public function index(Request $request)
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', $this->getModuleNameForPermissions()),
            403,
            'No tiene acceso a este módulo.'
        );

        $ordenId = $request->query('orden_id');

        if (! $ordenId) {
            return view('modulos.urdido.modulo-produccion-urdido', $this->getEmptyViewData());
        }

        $orden = UrdProgramaUrdido::find($ordenId);
        if (! $orden) {
            return redirect()->route('urdido.programar.urdido')->with('error', 'Orden no encontrada');
        }

        $julios = $this->getJuliosForOrder($orden);
        $totalRegistros = $this->calculateTotalRegistros($julios);

        // ponytail: el GET solo muta si el usuario puede capturar. Un lector (o un
        // prefetch del navegador) ya no cambia Status ni crea/borra filas.
        // Si se quiere mutación 100% explícita, mover esto a un POST /abrir-orden.
        if ($this->usuarioPuedeEditar()) {
            $redirect = $this->transitionToEnProceso($orden);
            if ($redirect) {
                return $redirect;
            }

            $this->ensureProductionRecordsExist($orden, $julios, $totalRegistros);
            $this->traitRefrescarFechaEnRegistrosVacios($orden);
            $this->traitAutollenarOficial1EnRegistrosSinHoraInicial($orden);
        }

        $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)->orderBy('Id')->get();

        return view('modulos.urdido.modulo-produccion-urdido',
            $this->prepareViewData($orden, $julios, $registrosProduccion, $totalRegistros));
    }

    private function getEmptyViewData(): array
    {
        return [
            'orden' => null,
            'julios' => collect([]),
            'engomado' => null,
            'metros' => '0',
            'destino' => null,
            'isKarlMayer' => false,
            'hilo' => null,
            'tipoAtado' => null,
            'nomEmpl' => null,
            'observaciones' => '',
            'totalRegistros' => 0,
            'registrosProduccion' => collect([]),
            'canEdit' => $this->usuarioPuedeEditar(),
            'maxKgNeto' => $this->maxKgNetoAllowed(),
            'ordenIncorrecta' => false,
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
            // ponytail: filtrar en SQL en vez de hidratar toda la tabla y contar en PHP.
            // Sin % final: ancla al número (evita que "Coy 1" cuente a "Coy 12").
            $patron = $mcCoyActual === 4 ? '%karl%mayer%' : "%coy%{$mcCoyActual}";
            $ordenesEnProceso = UrdProgramaUrdido::where('Status', 'En Proceso')
                ->where('Id', '!=', $orden->Id)
                ->where('MaquinaId', 'like', $patron)
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

            $user = Auth::user();
            $claveUsuario = $user ? ($user->numero_empleado ?? null) : null;
            $nombreUsuario = $user ? ($user->nombre ?? null) : null;
            $turnoUsuario = $user ? ($user->turno ?? null) : null;
            if (! $turnoUsuario) {
                $turnoUsuario = TurnoHelper::getTurnoActual();
            }
            $metrosOrden = $orden->Metros ?? 0;

            // Realinear Hilos con el plan ANTES de contar, si no el conteo por
            // grupo ve huerfanos que no existen y crea una orden duplicada.
            $this->reproyectarHilosDesdePlan($orden, $julios, $existentes);

            // Calcular expected por Hilos
            $expectedPorHilos = [];
            foreach ($julios as $julio) {
                $numJulio = (int) ($julio->Julios ?? 0);
                $hilos = $julio->Hilos !== null ? (string) $julio->Hilos : 'null';
                if ($numJulio > 0) {
                    $expectedPorHilos[$hilos] = ($expectedPorHilos[$hilos] ?? 0) + $numJulio;
                }
            }

            // Calcular existentes por Hilos
            $existentesPorHilos = [];
            foreach ($existentes as $reg) {
                $key = (string) ($reg->Hilos ?? 'null');
                $existentesPorHilos[$key] = ($existentesPorHilos[$key] ?? 0) + 1;
            }

            // Determinar que crear y que eliminar
            $registrosACrear = [];
            $idsAEliminar = [];

            foreach ($expectedPorHilos as $hilos => $expected) {
                $actual = $existentesPorHilos[$hilos] ?? 0;
                $diff = $actual - $expected;

                if ($diff > 0) {
                    // Solo se eliminan filas VACIAS: sin captura iniciada, sin julio,
                    // sin peso y no enviadas a AX. Si no alcanzan, se deja el sobrante
                    // y se registra: nunca se borra trabajo real para cuadrar el conteo.
                    $sobrantes = UrdProduccionUrdido::where('Folio', $orden->Folio)
                        ->where('Hilos', $hilos === 'null' ? null : $hilos)
                        ->where(function ($q) {
                            $q->whereNull('HoraInicial')->orWhere('HoraInicial', '');
                        })
                        ->where(function ($q) {
                            $q->whereNull('NoJulio')->orWhere('NoJulio', '');
                        })
                        ->where(function ($q) {
                            $q->whereNull('KgBruto')->orWhere('KgBruto', 0);
                        })
                        ->where(function ($q) {
                            $q->whereNull('AX')->orWhere('AX', '!=', 1);
                        })
                        ->orderBy('Id', 'desc')  // los mas nuevos primero
                        ->limit($diff)
                        ->pluck('Id')
                        ->toArray();

                    if (count($sobrantes) < $diff) {
                        Log::warning('Sobran registros de produccion con captura; no se eliminan', [
                            'folio' => $orden->Folio,
                            'hilos' => $hilos,
                            'sobrantes' => $diff,
                            'eliminables' => count($sobrantes),
                        ]);
                    }

                    $idsAEliminar = array_merge($idsAEliminar, $sobrantes);
                } elseif ($diff < 0) {
                    // Faltan - crear $diff registros
                    for ($i = 0; $i < abs($diff); $i++) {
                        $data = [
                            'Folio' => $orden->Folio,
                            'TipoAtado' => $orden->TipoAtado ?? null,
                            'NoJulio' => null,
                            'Hilos' => $hilos === 'null' ? null : $hilos,
                            'Fecha' => now()->format('Y-m-d'),
                        ];
                        if (! empty($claveUsuario)) {
                            $data['CveEmpl1'] = $claveUsuario;
                        }
                        if (! empty($nombreUsuario)) {
                            $data['NomEmpl1'] = $nombreUsuario;
                        }
                        if ($metrosOrden > 0) {
                            $data['Metros1'] = round($metrosOrden, 2);
                        }
                        if (! empty($turnoUsuario)) {
                            $data['Turno1'] = (int) $turnoUsuario;
                        }

                        $registrosACrear[] = $data;
                    }
                }
            }

            // Eliminar sobrantes
            if (! empty($idsAEliminar)) {
                UrdProduccionUrdido::whereIn('Id', $idsAEliminar)->delete();
                Log::info('Eliminados registros sobrantes de UrdProduccionUrdido', [
                    'folio' => $orden->Folio,
                    'ids_eliminados' => $idsAEliminar,
                    'cantidad' => count($idsAEliminar),
                ]);
            }

            // La reconciliación agrupa por Hilos, pero Hilos no es estable: si el
            // plan de julios se edita despues de crear las filas, el grupo viejo
            // queda huerfano (nunca se cuenta ni se borra) y el grupo nuevo se crea
            // desde cero, duplicando la orden. Se acota por el TOTAL del folio.
            $filasTrasBorrado = $existentes->count() - count($idsAEliminar);
            $cupo = max(0, $totalRegistros - $filasTrasBorrado);

            if (count($registrosACrear) > $cupo) {
                Log::warning('Alta de produccion recortada al total del plan de julios', [
                    'folio' => $orden->Folio,
                    'plan_total' => $totalRegistros,
                    'filas_actuales' => $filasTrasBorrado,
                    'solicitadas' => count($registrosACrear),
                    'creadas' => $cupo,
                    'hilos_existentes' => array_keys($existentesPorHilos),
                    'hilos_plan' => array_keys($expectedPorHilos),
                ]);
                $registrosACrear = array_slice($registrosACrear, 0, $cupo);
            }

            // Crear faltantes (un solo INSERT en vez de N round-trips)
            if (! empty($registrosACrear)) {
                UrdProduccionUrdido::insert($registrosACrear);
            }
        } catch (\Throwable $e) {
            Log::error('Error al sincronizar registros en UrdProduccionUrdido', [
                'folio' => $orden->Folio,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function prepareViewData(UrdProgramaUrdido $orden, Collection $julios, Collection $registrosProduccion, int $totalRegistros): array
    {
        $engomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();

        $user = Auth::user();

        $destino = $orden->SalonTejidoId ?? ($engomado ? $engomado->SalonTejidoId : null);
        $isKarlMayer = stripos($destino ?? '', 'karl') !== false || stripos($destino ?? '', 'karlmayer') !== false;

        return [
            'orden' => $orden,
            'julios' => $julios,
            'engomado' => $engomado,
            'metros' => $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0',
            'destino' => $destino,
            'isKarlMayer' => $isKarlMayer,
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
            'canEdit' => $this->usuarioPuedeEditar(),
            'maxKgNeto' => $this->maxKgNetoAllowed(),
            'ordenIncorrecta' => (int) ($orden->Incorrecto ?? 0) === 1,
        ];
    }

    // ─── endpoints específicos de Urdido ─────────────────────────────

    public function actualizarCamposProduccion(Request $request): JsonResponse
    {
        $this->ensureUserCanEdit();
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                // Hilos no se edita aquí: es la llave que usa ensureProductionRecordsExist()
                // para decidir cuántas filas crear/eliminar por folio.
                'campo' => 'required|string|in:Hilatura,Maquina,Operac,Transf,Vueltas,Diametro',
                'valor' => 'nullable|numeric|min:0|max:99999',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (! $registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            if ($bloqueado = $this->jsonIfRegistroBloqueadoPorAx($registro)) {
                return $bloqueado;
            }

            $campo = $request->campo;
            $floatCampos = ['Vueltas', 'Diametro'];
            $registro->$campo = $request->valor !== null
                ? (in_array($campo, $floatCampos) ? (float) $request->valor : (int) $request->valor)
                : null;
            $registro->save();
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($campo).' actualizado correctamente',
                'data' => ['campo' => $campo, 'valor' => $registro->$campo],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar campos de producción', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Error al actualizar campo: '.$e->getMessage()], 500);
        }
    }

    public function getUsuariosUrdido(): JsonResponse
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', $this->getModuleNameForPermissions()),
            403,
            'No tiene acceso a este módulo.'
        );

        try {
            // Incluye usuarios con área Urdido y el idusuario indicado (p. ej. oficial que en prod no tiene área Urdido).
            $idUsuarioExtraOficiales = 22;

            $usuarios = SYSUsuario::select(['idusuario', 'numero_empleado', 'nombre', 'turno'])
                ->where(function ($q) use ($idUsuarioExtraOficiales) {
                    $q->where('area', 'Urdido')
                        ->orWhere('idusuario', $idUsuarioExtraOficiales);
                })
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

            return response()->json(['success' => false, 'error' => 'Error al obtener usuarios: '.$e->getMessage()], 500);
        }
    }

    public function finalizar(Request $request): JsonResponse
    {
        $this->ensureUserCanEdit();
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'confirmar_descarte' => 'nullable|boolean',
            ]);

            $orden = UrdProgramaUrdido::find($request->orden_id);

            if (! $orden) {
                return response()->json(['success' => false, 'error' => 'Orden no encontrada'], 404);
            }

            if ((int) ($orden->Incorrecto ?? 0) === 1) {
                return response()->json([
                    'success' => false,
                    'error' => 'La orden está marcada con cuenta/calibre incorrecta. Un supervisor debe liberarla en Programa Urdido antes de finalizar.',
                ], 422);
            }

            if (! in_array($orden->Status, ['En Proceso', 'Parcial'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo se puede finalizar una orden en estado "En Proceso" o "Parcial". Estado actual: '.$orden->Status,
                ], 422);
            }

            if ($this->traitHasNegativeKgNetoByFolio($orden->Folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden porque existen registros con Kg Neto negativo.',
                ], 422);
            }

            $registrosInvalidos = UrdProduccionUrdido::where('Folio', $orden->Folio)
                ->whereNotNull('HoraInicial')
                ->whereNotNull('HoraFinal')
                ->where(function ($q) {
                    $q->whereNull('NoJulio')
                        ->orWhere('NoJulio', '')
                        ->orWhereNull('KgBruto')
                        ->orWhere('KgBruto', 0);
                })
                ->count();

            if ($registrosInvalidos > 0) {
                return response()->json([
                    'success' => false,
                    'error' => "No se puede finalizar: hay {$registrosInvalidos} registro(s) con No. Julio vacío o Kg Bruto en cero. Revisa los registros antes de finalizar.",
                ], 422);
            }

            // Validar Vueltas y Diámetro requeridos para Karl Mayer
            $destino = $orden->SalonTejidoId;
            if (! $destino) {
                $engomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();
                $destino = $engomado ? $engomado->SalonTejidoId : null;
            }
            $isKarlMayer = stripos($destino ?? '', 'karl') !== false;

            if ($isKarlMayer) {
                $registrosSinKM = UrdProduccionUrdido::where('Folio', $orden->Folio)
                    ->whereNotNull('HoraInicial')
                    ->whereNotNull('HoraFinal')
                    ->where(function ($q) {
                        $q->whereNull('Vueltas')
                            ->orWhere('Vueltas', 0)
                            ->orWhereNull('Diametro')
                            ->orWhere('Diametro', 0);
                    })
                    ->count();

                if ($registrosSinKM > 0) {
                    return response()->json([
                        'success' => false,
                        'error' => "No se puede finalizar: hay {$registrosSinKM} registro(s) sin Vueltas o Diámetro. Estos campos son obligatorios para Karl Mayer.",
                    ], 422);
                }
            }

            // Las filas se pre-crean como esqueleto desde el plan de julios
            // (ver ensureProductionRecordsExist). Las que quedan sin horas son
            // julios planeados que no se corrieron: se descartan sin preguntar.
            // Solo se pide confirmación si alguna trae captura real, porque ahí
            // sí se estaría tirando trabajo del operador.
            $conCaptura = UrdProduccionUrdido::where('Folio', $orden->Folio)
                ->where(function ($query) {
                    $query->whereNull('HoraInicial')->orWhereNull('HoraFinal');
                })
                ->where(fn ($q) => $this->scopeTieneCaptura($q))
                ->count();

            if ($conCaptura > 0 && ! $request->boolean('confirmar_descarte')) {
                return response()->json([
                    'success' => false,
                    'requiere_confirmacion' => true,
                    'registros_a_descartar' => $conCaptura,
                    'error' => "Hay {$conCaptura} registro(s) con captura (julio, peso o roturas) a los que les falta Hora Inicial u Hora Final. Al finalizar se descartarán.",
                ], 422);
            }

            $fechaCierre = $this->resolveMonthlyClosureDateContext();

            DB::connection('sqlsrv')->transaction(function () use ($orden, $fechaCierre) {
                // Eliminar registros sin HoraInicial o HoraFinal antes de consolidar el cierre.
                UrdProduccionUrdido::where('Folio', $orden->Folio)
                    ->where(function ($query) {
                        $query->whereNull('HoraInicial')->orWhereNull('HoraFinal');
                    })
                    ->where(function ($query) {
                        $query->whereNull('AX')->orWhere('AX', '!=', 1);
                    })
                    ->delete();

                // No tocar filas ya procesadas en AX.
                UrdProduccionUrdido::where('Folio', $orden->Folio)
                    ->where(function ($query) {
                        $query->whereNull('AX')->orWhere('AX', '!=', 1);
                    })
                    ->update(['Finalizar' => 1]);

                if ($fechaCierre['applies']) {
                    $this->updateProduccionFechaByFolio($orden->Folio, $fechaCierre['fecha_efectiva']);
                }

                $orden->Status = 'Finalizado';
                $orden->FechaFinaliza = $fechaCierre['fecha_efectiva'];
                $orden->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Orden finalizada correctamente',
                'data' => [
                    'orden_id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'status' => $orden->Status,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al finalizar orden de urdido', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Error al finalizar la orden: '.$e->getMessage()], 500);
        }
    }
}
