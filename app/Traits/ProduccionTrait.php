<?php

namespace App\Traits;

use App\Helpers\TurnoHelper;
use App\Models\Urdido\UrdCatJulios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait compartido entre ModuloProduccionUrdidoController y ModuloProduccionEngomadoController.
 *
 * Cada controller que use este trait debe implementar los siguientes métodos:
 *   - getProduccionModelClass(): string   → clase Eloquent de producción
 *   - getProgramaModelClass(): string     → clase Eloquent de programa
 *   - getDepartamento(): string           → 'Urdido' | 'Engomado'
 *   - shouldRoundKgBruto(): bool          → true solo en Engomado
 */
trait ProduccionTrait
{
    abstract protected function getProduccionModelClass(): string;
    abstract protected function getProgramaModelClass(): string;
    abstract protected function getDepartamento(): string;
    abstract protected function shouldRoundKgBruto(): bool;

    // ─── helpers reutilizables ────────────────────────────────────────

    protected function traitHasNegativeKgNetoByFolio(string $folio): bool
    {
        $model = $this->getProduccionModelClass();

        return $model::where('Folio', $folio)
            ->whereNotNull('KgNeto')
            ->where('KgNeto', '<', 0)
            ->exists();
    }

    protected function traitHasHoraInicialCaptured($registro): bool
    {
        return $registro->HoraInicial !== null && trim((string) $registro->HoraInicial) !== '';
    }

    protected function traitAutollenarOficial1EnRegistrosSinHoraInicial($orden): void
    {
        $usuarioActual = Auth::user();
        if (!$usuarioActual) {
            return;
        }

        $claveUsuario = $usuarioActual->numero_empleado ?? null;
        $nombreUsuario = $usuarioActual->nombre ?? null;
        if (empty($claveUsuario) || empty($nombreUsuario)) {
            return;
        }

        $turnoUsuario = $usuarioActual->turno ?? TurnoHelper::getTurnoActual();

        $model = $this->getProduccionModelClass();
        $model::where('Folio', $orden->Folio)
            ->where(function ($query) {
                $query->whereNull('HoraInicial')->orWhere('HoraInicial', '');
            })
            ->update([
                'CveEmpl1' => $claveUsuario,
                'NomEmpl1' => $nombreUsuario,
                'Turno1' => $turnoUsuario !== null && $turnoUsuario !== '' ? (int) $turnoUsuario : null,
            ]);
    }

    // ─── endpoints compartidos ───────────────────────────────────────

    public function getCatalogosJulios(): JsonResponse
    {
        try {
            $julios = UrdCatJulios::select('NoJulio', 'Tara', 'Departamento')
                ->whereNotNull('NoJulio')
                ->where('Departamento', $this->getDepartamento())
                ->orderBy('NoJulio')
                ->get()
                ->map(fn ($item) => [
                    'julio' => $item->NoJulio,
                    'tara' => $item->Tara ?? 0,
                    'departamento' => $item->Departamento ?? null,
                ])
                ->values();

            return response()->json(['success' => true, 'data' => $julios]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener catálogo de julios', [
                'departamento' => $this->getDepartamento(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener catálogo de julios: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function guardarOficial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'numero_oficial' => 'required|integer|in:1,2,3',
                'cve_empl' => 'nullable|string|max:30',
                'nom_empl' => 'nullable|string|max:150',
                'metros' => 'nullable|numeric|min:0',
                'turno' => 'nullable|integer|in:1,2,3',
            ]);

            $cveEmpl = trim((string) ($request->input('cve_empl') ?? ''));
            $nomEmpl = trim((string) ($request->input('nom_empl') ?? ''));
            if ($cveEmpl === '' && $nomEmpl === '') {
                return response()->json([
                    'success' => false,
                    'error' => 'Debe llenar al menos la clave (No. Operador) o el nombre del oficial.',
                ], 422);
            }

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $numeroOficial = (int) $request->numero_oficial;
            $folio = $registro->Folio;

            // Determinar si hay que propagar el oficial 1 a otros registros del mismo folio
            $propagarOficial = false;
            if ($numeroOficial === 1) {
                $existeOficialEnFolio = $model::where('Folio', $folio)
                    ->whereNotNull("NomEmpl{$numeroOficial}")
                    ->where("NomEmpl{$numeroOficial}", '!=', '')
                    ->exists();
                $propagarOficial = !$existeOficialEnFolio;
            }

            if ($numeroOficial < 1 || $numeroOficial > 3) {
                return response()->json([
                    'success' => false,
                    'error' => 'Número de oficial inválido. Solo se permiten 3 oficiales (1, 2 o 3).',
                ], 422);
            }

            // No permitir repetir No. Operador dentro del mismo registro
            for ($i = 1; $i <= 3; $i++) {
                if ($i === $numeroOficial) {
                    continue;
                }
                $cveExistente = trim((string) ($registro->{"CveEmpl{$i}"} ?? ''));
                if ($cveExistente !== '' && $cveExistente === $cveEmpl) {
                    return response()->json([
                        'success' => false,
                        'error' => "El No. Operador {$cveEmpl} ya está asignado al Oficial {$i}.",
                    ], 422);
                }
            }

            $oficialExistente = !empty($registro->{"NomEmpl{$numeroOficial}"});

            if (!$oficialExistente) {
                $oficialesRegistrados = 0;
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($registro->{"NomEmpl{$i}"})) {
                        $oficialesRegistrados++;
                    }
                }
                if ($oficialesRegistrados >= 3) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Ya se han registrado 3 oficiales (máximo permitido). Solo puedes editar los existentes.',
                    ], 422);
                }
            }

            $registro->{"CveEmpl{$numeroOficial}"} = $cveEmpl !== '' ? $cveEmpl : null;
            $registro->{"NomEmpl{$numeroOficial}"} = $nomEmpl !== '' ? $nomEmpl : null;

            if ($request->exists('metros')) {
                $registro->{"Metros{$numeroOficial}"} = $request->metros;
            }
            if ($request->has('turno')) {
                $registro->{"Turno{$numeroOficial}"} = $request->turno;
            }

            $registro->save();

            if ($propagarOficial) {
                $updateData = [
                    "CveEmpl{$numeroOficial}" => $cveEmpl !== '' ? $cveEmpl : null,
                    "NomEmpl{$numeroOficial}" => $nomEmpl !== '' ? $nomEmpl : null,
                ];
                if ($request->has('turno')) {
                    $updateData["Turno{$numeroOficial}"] = $request->turno;
                }
                // No propagar a registros que ya tienen HoraInicial
                $model::where('Folio', $folio)
                    ->where('Id', '!=', $registro->Id)
                    ->where(function ($q) {
                        $q->whereNull('HoraInicial')->orWhere('HoraInicial', '');
                    })
                    ->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Oficial guardado correctamente',
                'data' => [
                    'cve_empl' => $registro->{"CveEmpl{$numeroOficial}"},
                    'nom_empl' => $registro->{"NomEmpl{$numeroOficial}"},
                    'metros' => $registro->{"Metros{$numeroOficial}"} ?? null,
                    'turno' => $registro->{"Turno{$numeroOficial}"} ?? null,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al guardar oficial', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al guardar oficial: ' . $e->getMessage()], 500);
        }
    }

    public function eliminarOficial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'numero_oficial' => 'required|integer|in:1,2,3',
            ]);

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $n = (int) $request->numero_oficial;
            $registro->{"CveEmpl{$n}"} = null;
            $registro->{"NomEmpl{$n}"} = null;
            $registro->{"Metros{$n}"} = null;
            $registro->{"Turno{$n}"} = null;
            $registro->save();

            return response()->json(['success' => true, 'message' => 'Oficial eliminado correctamente']);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar oficial', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al eliminar oficial: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarTurnoOficial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'numero_oficial' => 'required|integer|in:1,2,3',
                'turno' => 'required|integer|in:1,2,3',
            ]);

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $n = $request->numero_oficial;

            if (empty($registro->{"NomEmpl{$n}"})) {
                return response()->json(['success' => false, 'error' => 'No hay un oficial registrado en esta posición'], 422);
            }

            $registro->{"Turno{$n}"} = $request->turno;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Turno actualizado correctamente',
                'data' => [
                    'numero_oficial' => $n,
                    'nom_empl' => $registro->{"NomEmpl{$n}"},
                    'turno' => $registro->{"Turno{$n}"},
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar turno de oficial', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar turno: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarFecha(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'fecha' => 'required|date',
            ]);

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $registro->Fecha = $request->fecha;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Fecha actualizada correctamente',
                'data' => ['fecha' => $registro->Fecha],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar fecha', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar fecha: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarJulioTara(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'no_julio' => 'nullable|string|max:10',
                'tara' => 'nullable|numeric|min:0',
            ]);

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $registro->NoJulio = $request->no_julio ?? null;

            $taraValue = null;
            if ($request->has('tara') && $request->tara !== null && $request->tara !== '') {
                $taraValue = (float) $request->tara;
            }
            $registro->Tara = $taraValue;

            if ($taraValue !== null) {
                $kgBruto = $registro->KgBruto !== null ? (float) $registro->KgBruto : 0;
                $registro->KgNeto = $kgBruto - $taraValue;
            } else {
                $registro->KgNeto = $registro->KgBruto !== null ? (float) $registro->KgBruto : null;
            }

            $registro->save();
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => 'No. Julio y Tara actualizados correctamente',
                'data' => [
                    'no_julio' => $registro->NoJulio,
                    'tara' => $registro->Tara,
                    'kg_neto' => $registro->KgNeto,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar NoJulio y Tara', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar No. Julio y Tara: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarKgBruto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'kg_bruto' => 'nullable|numeric|min:0',
            ]);

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $kgBrutoValue = null;
            if ($request->has('kg_bruto') && $request->kg_bruto !== null && $request->kg_bruto !== '') {
                $kgBrutoValue = (float) $request->kg_bruto;
                if ($this->shouldRoundKgBruto()) {
                    $kgBrutoValue = round($kgBrutoValue, 2);
                }
            }
            $registro->KgBruto = $kgBrutoValue;

            if ($registro->Tara !== null) {
                $kgBruto = $kgBrutoValue ?? 0;
                $tara = (float) $registro->Tara;
                $registro->KgNeto = $kgBruto - $tara;
            } else {
                $registro->KgNeto = $kgBrutoValue;
            }

            $registro->save();
            $registro->refresh();

            $responseData = [
                'kg_bruto' => $registro->KgBruto,
                'kg_neto' => $registro->KgNeto,
            ];

            if ($this->shouldRoundKgBruto()) {
                $responseData['kg_bruto'] = $registro->KgBruto !== null ? round((float) $registro->KgBruto, 2) : null;
                $responseData['kg_neto'] = $registro->KgNeto !== null ? round((float) $registro->KgNeto, 2) : null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Kg. Bruto actualizado correctamente',
                'data' => $responseData,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar KgBruto', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar Kg. Bruto: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarHoras(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:HoraInicial,HoraFinal',
                'valor' => ['nullable', 'string', 'regex:#^([0-1][0-9]|2[0-3]):[0-5][0-9]$#'],
            ]);

            $model = $this->getProduccionModelClass();
            $registro = $model::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            $campo = $request->campo;
            $valor = $request->valor !== null && $request->valor !== '' ? $request->valor : null;

            $registro->$campo = $valor;
            $registro->save();
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ($campo === 'HoraInicial' ? 'Hora Inicial' : 'Hora Final') . ' actualizada correctamente',
                'data' => ['campo' => $campo, 'valor' => $registro->$campo],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar horas', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar hora: ' . $e->getMessage()], 500);
        }
    }

    public function marcarListo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'listo' => 'required|boolean',
            ]);

            $produccionModel = $this->getProduccionModelClass();
            $programaModel = $this->getProgramaModelClass();

            $registro = $produccionModel::find($request->registro_id);
            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro de producción no encontrado'], 404);
            }

            if ((int) ($registro->AX ?? 0) === 1) {
                return response()->json([
                    'success' => false,
                    'error' => 'Este registro ya fue enviado a AX y no se puede modificar.',
                    'bloqueado_ax' => true,
                ], 422);
            }

            $registro->Finalizar = $request->listo ? 1 : 0;
            $registro->save();

            $orden = $programaModel::where('Folio', $registro->Folio)->first();
            $statusOrden = null;

            if ($orden && in_array($orden->Status, ['En Proceso', 'Parcial'])) {
                $registrosFinalizados = $produccionModel::where('Folio', $registro->Folio)
                    ->where('Finalizar', 1)
                    ->count();

                $orden->Status = $registrosFinalizados > 0 ? 'Parcial' : 'En Proceso';
                $orden->save();
                $statusOrden = $orden->Status;
            }

            return response()->json([
                'success' => true,
                'message' => $request->listo ? 'Registro marcado como listo' : 'Registro desmarcado',
                'data' => [
                    'registro_id' => $registro->Id,
                    'listo' => (int) $registro->Finalizar,
                    'status_orden' => $statusOrden,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al marcar registro como listo', [
                'registro_id' => $request->registro_id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Error al actualizar el registro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Validar que todos los registros de un folio tengan HoraInicial < HoraFinal.
     * Retorna null si todo está bien, o un mensaje de error.
     * Deshabilitado: se permite Hora Fin <= Hora Inicio (ej. turnos que cruzan medianoche).
     */
    protected function validarHorasRegistros(string $folio): ?string
    {
        return null;
    }
}
