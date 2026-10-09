<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Sistema\Usuario;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use App\Services\Mecanicos\RefaccionesParoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/** Pantallas de órdenes de trabajo: listado (index) y captura de renglones. */
class VistaOrdenesTrabajoController extends Controller
{
    /**
     * Área de SYSUsuario que agrupa a los mecánicos que capturan intervenciones.
     * Se compara en mayúsculas porque el campo se captura a mano.
     */
    private const AREA_MANTENIMIENTO = 'MANTENIMIENTO';

    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function index(): View
    {
        $modoTejedor = $this->acceso->modoTejedor();
        $ahora = now('America/Mexico_City');

        return view('modulos.mecanicos.ordenes-trabajo.index', [
            'telares' => $this->catalogoTelares(),
            'tiposFalla' => OrdenTrabajoDatos::TIPOS_FALLA,
            'modoTejedor' => $modoTejedor,
            'puedeCrear' => $this->acceso->puede('crear') && ! $modoTejedor,
            'puedeEditar' => $this->acceso->puede('modificar') && ! $modoTejedor,
            'turnoSugerido' => (int) TurnoHelper::getTurnoActual(),
            'fechaSugerida' => $ahora->toDateString(),
            'horaSugerida' => $ahora->format('H:i'),
        ]);
    }

    public function captura(string $folio, RefaccionesParoService $refaccionesParo): View
    {
        $orden = $this->acceso->orden($folio);
        $orden->load('lineas');
        $usuario = Auth::user();

        return view('modulos.mecanicos.ordenes-trabajo.captura', [
            'orden' => $orden,
            'refacciones' => $refaccionesParo->porFolioParo($orden->FolioParo),
            'operadores' => $this->operadoresMecanicos(),
            ...$this->permisosCaptura($orden),
            'usuarioCve' => trim((string) ($usuario->numero_empleado ?? '')),
            'usuarioNombre' => trim((string) ($usuario->nombre ?? '')),
            'turnoSugerido' => (int) TurnoHelper::getTurnoActual(),
            'fechaSugerida' => now('America/Mexico_City')->toDateString(),
        ]);
    }

    /**
     * Qué botones ve el usuario según su rol y el estatus de la orden. El back vuelve
     * a validar cada acción; esto solo evita mostrar lo que respondería 403/422.
     *
     * @return array<string, bool>
     */
    private function permisosCaptura(MecOrdenTrabajoModel $orden): array
    {
        $modoTejedor = $this->acceso->modoTejedor();
        $estatus = $orden->estatus();
        $captura = $orden->admiteCaptura() && ! $modoTejedor;

        return [
            'modoTejedor' => $modoTejedor,
            'esSupervisor' => $this->acceso->puedeRegistrar(),
            'puedeCrear' => $captura && $this->acceso->puede('crear'),
            'puedeEditar' => $captura && $this->acceso->puede('modificar'),
            // En captura solo se eliminan renglones (la orden completa se elimina desde el índice).
            'puedeEliminar' => $this->acceso->puedeEliminarLineas() && ! $this->acceso->bloqueaEliminarLinea($estatus),
            'puedeFinalizar' => $this->acceso->puedeFinalizar() && $estatus === $orden::ESTATUS_ACTIVO,
            'puedeCalificar' => $this->acceso->puedeCalificar() && $estatus === $orden::ESTATUS_TERMINADO,
            'puedeAutorizar' => $this->acceso->puedeRegistrar() && $estatus === $orden::ESTATUS_CALIFICADO,
            'bloqueada' => $estatus === $orden::ESTATUS_AUTORIZADO,
            'bloqueadaEdicion' => ! $orden->admiteCaptura(),
        ];
    }

    /**
     * Mecánicos que pueden aparecer como "capturando" en un renglón.
     *
     * Salen de SYSUsuario (área Mantenimiento) y no de ManOperadoresMantenimiento:
     * quien captura una intervención es una persona con cuenta y permisos en el
     * sistema. Las claves (CveEmpl/NomEmpl/Turno) son el contrato de la vista.
     *
     * @return list<array{CveEmpl: string, NomEmpl: string, Turno: int|null}>
     */
    private function operadoresMecanicos(): array
    {
        return Usuario::query()
            // UPPER/LTRIM/RTRIM: el área se captura a mano; parametrizado.
            ->whereRaw('UPPER(LTRIM(RTRIM(area))) = ?', [self::AREA_MANTENIMIENTO])
            ->orderBy('nombre')
            ->get(['numero_empleado', 'nombre', 'turno'])
            ->map(fn (Usuario $usuario): array => [
                'CveEmpl' => trim((string) $usuario->numero_empleado),
                'NomEmpl' => trim((string) $usuario->nombre),
                'Turno' => $usuario->turno !== null ? (int) $usuario->turno : null,
            ])
            ->filter(fn (array $operador): bool => $operador['CveEmpl'] !== '')
            ->values()
            ->all();
    }

    /**
     * Catálogo completo de máquinas (dbo.URDCatalogoMaquinas). `grupo` (Departamento)
     * agrupa el select por área.
     *
     * @return list<array{id: string, label: string, grupo: string}>
     */
    private function catalogoTelares(): array
    {
        return URDCatalogoMaquina::query()
            ->select('MaquinaId', 'Nombre', 'Departamento')
            ->whereNotNull('MaquinaId')
            ->where('MaquinaId', '!=', '')
            ->get()
            ->map(function (URDCatalogoMaquina $maquina): array {
                $maquinaId = trim((string) $maquina->getAttribute('MaquinaId'));
                $nombre = trim((string) $maquina->getAttribute('Nombre'));
                $grupo = trim((string) $maquina->getAttribute('Departamento')) ?: 'Sin área';
                $salon = in_array($grupo, URDCatalogoMaquina::DEPARTAMENTOS_TELARES, true) ? $maquina->salon() : '';

                // Los telares traen la marca como Nombre (igual al área): no aporta en la etiqueta.
                $detalle = match (true) {
                    $salon !== '' => "Salón {$salon}",
                    $nombre !== '' && strcasecmp($nombre, $maquinaId) !== 0 && strcasecmp($nombre, $grupo) !== 0 => $nombre,
                    default => '',
                };

                return [
                    'id' => $maquinaId,
                    'label' => $detalle !== '' ? "{$maquinaId} · {$detalle}" : $maquinaId,
                    'grupo' => $grupo,
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '')
            ->unique('id')
            // Orden natural: RECT2 antes que RECT10.
            ->sort(fn (array $a, array $b): int => strnatcasecmp($a['grupo'], $b['grupo']) ?: strnatcasecmp($a['id'], $b['id']))
            ->values()
            ->all();
    }
}
