<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Crea la cabecera de una OT (desde un paro o captura manual) con su primer renglón vacío. */
class CrearOrdenTrabajoController extends Controller
{
    private const MODULO_FOLIOS = 'Mecanicos';

    private const PREFIJO_FOLIOS = 'MEC';

    private const LONGITUD_CONSECUTIVO_FOLIOS = 5;

    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->acceso->exigir('crear', 'No tienes permiso para crear órdenes de trabajo.');

        $datos = OrdenTrabajoDatos::normalizarCabecera(
            $request->validate(OrdenTrabajoDatos::reglasCabecera(), OrdenTrabajoDatos::mensajesCabecera())
        );
        $datos = $this->resolverOrigen($datos, $request->boolean('CapturaManual'));
        OrdenTrabajoDatos::validarOrdenNoVacia($datos);

        $orden = DB::transaction(function () use ($datos): MecOrdenTrabajoModel {
            $orden = MecOrdenTrabajoModel::create([
                ...$datos,
                'Folio' => $this->siguienteFolio(),
                // Fecha de la orden = día en que se genera el folio, no la del paro.
                'Fecha' => now('America/Mexico_City')->toDateString(),
                // Toda orden nace Activa; el estatus solo avanza por el flujo.
                'Estatus' => MecOrdenTrabajoModel::ESTATUS_ACTIVO,
            ]);

            // El trigger de BD puede haber insertado ya el primer renglón.
            if (! MecOrdenTrabajoLineModel::where('Folio', $orden->Folio)->exists()) {
                MecOrdenTrabajoLineModel::create(['Folio' => $orden->Folio]);
            }

            return $orden;
        });

        return response()->json([
            'success' => true,
            'message' => 'Orden de trabajo creada correctamente.',
            'data' => $orden->load('lineas'),
        ], 201);
    }

    /**
     * La cabecera procede de un paro elegible o de una captura manual explícita. Los
     * datos del paro se vuelven a leer aquí para no confiar en campos que solo el
     * cliente bloquea.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function resolverOrigen(array $datos, bool $capturaManual): array
    {
        if ($capturaManual) {
            // "Sin Folio de Paro." es solo un texto visible; nunca se guarda.
            return [...$datos, 'FolioParo' => null];
        }

        $folioParo = $datos['FolioParo'] ?? null;
        if ($folioParo === null) {
            throw ValidationException::withMessages([
                'CapturaManual' => ['Selecciona un folio de paro para la máquina o habilita la captura manual.'],
            ]);
        }

        $paro = ManFallasParos::query()->where('Folio', $folioParo)->first();
        if ($paro === null) {
            throw ValidationException::withMessages(['FolioParo' => ['El folio de paro seleccionado ya no existe.']]);
        }

        return [
            ...$datos,
            'TelarId' => trim((string) $paro->MaquinaId),
            'FolioParo' => trim((string) $paro->Folio),
            'Falla' => OrdenTrabajoDatos::textoFalla($paro->Falla, $paro->Descripcion),
            'Comentarios' => OrdenTrabajoDatos::textoComentariosParo($paro),
            'FechaParo' => $paro->Fecha?->toDateString(),
            'HoraParo' => OrdenTrabajoDatos::normalizarHora($paro->Hora),
            // El # de orden se sugiere desde el paro, pero es el único dato que el mecánico corrige a mano.
            'Orden' => $datos['Orden'] ?? (trim((string) $paro->OrdenTrabajo) ?: null),
            'Turno' => $paro->Turno !== null ? (int) $paro->Turno : null,
        ];
    }

    private function siguienteFolio(): string
    {
        $this->asegurarSecuenciaFolios();
        $folio = trim(FolioHelper::obtenerSiguienteFolio(self::MODULO_FOLIOS, self::LONGITUD_CONSECUTIVO_FOLIOS));

        if ($folio === '') {
            throw ValidationException::withMessages([
                'Folio' => ['Configura la secuencia de folios del módulo Mecanicos antes de crear órdenes.'],
            ]);
        }

        return $folio;
    }

    /**
     * Crea la secuencia la primera vez que se use el módulo y la alinea con los
     * folios MEC existentes para no reutilizar un folio anterior.
     */
    private function asegurarSecuenciaFolios(): void
    {
        FolioHelper::asegurarSecuencia(self::MODULO_FOLIOS, self::PREFIJO_FOLIOS, MecOrdenTrabajoModel::query());
    }
}
