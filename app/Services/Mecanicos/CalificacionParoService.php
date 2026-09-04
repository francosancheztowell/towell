<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Sistema\Usuario;

/**
 * Traslada la calificación de un paro a las órdenes de trabajo que nacieron de él.
 *
 * Una orden de trabajo mecánica se crea desde un paro (MecOrdenTrabajoTable.FolioParo
 * apunta a ManFallasParos.Folio) y ese paro se califica al cerrarlo en Mantenimiento,
 * con estrellas 1-5 en ManFallasParos.Calidad. Esa nota ES la calificación del
 * servicio: pedírsela otra vez al tejedor duplicaría la captura y podría contradecirla.
 *
 * Los dos módulos pueden cerrar en cualquier orden, así que la herencia se dispara
 * desde ambos lados y este servicio es la única definición de la regla:
 *
 *  - El paro ya estaba cerrado cuando el mecánico finaliza la orden
 *    → OrdenesTrabajoMecaController::finalizar llama a calificarOrden().
 *  - La orden ya existe y el paro se cierra después
 *    → MantenimientoParosController::finalizar llama a propagarAOrdenesDelParo().
 *
 * Cuando la orden es de captura manual (sin FolioParo) no hay nada que heredar y
 * el tejedor la califica a mano, como siempre.
 */
class CalificacionParoService
{
    /**
     * Escala compartida por ManFallasParos.Calidad y MecOrdenTrabajoLine.Calificacion.
     * Las dos columnas miden lo mismo, así que tienen que medirlo igual.
     */
    public const CALIFICACION_MINIMA = 1;

    public const CALIFICACION_MAXIMA = 5;

    /**
     * Estatus de cierre en ManFallasParos. Coincide en texto con el de la orden,
     * pero es el ciclo de vida de otro módulo: se declara aparte para que
     * renombrar uno no arrastre al otro.
     */
    private const ESTATUS_PARO_TERMINADO = 'Terminado';

    private const ESTATUS_ORDEN_ACTIVO = 'Activo';

    private const ESTATUS_ORDEN_TERMINADO = 'Terminado';

    private const ESTATUS_ORDEN_CALIFICADO = 'Calificado';

    /**
     * Aplica la calificación del paro a los renglones sin calificar de una orden.
     *
     * Los renglones que el tejedor ya calificó a mano no se tocan: esa nota es
     * más específica que la del paro. Si con esto quedan todos calificados y la
     * orden ya estaba finalizada, avanza a Calificado y solo le falta autorización.
     * Una orden todavía Activa no avanza: el mecánico puede seguir agregando
     * renglones, y finalizar() vuelve a evaluarla al cerrarla.
     *
     * @param  array{Calificacion: int, CveTejedor: ?string, NomTejedor: ?string}|null  $calificacion
     *                                                                                                 Calificación ya resuelta. Se pasa desde propagarAOrdenesDelParo para no
     *                                                                                                 releer el mismo paro por cada orden.
     * @return bool true si había una calificación que heredar.
     */
    public function calificarOrden(MecOrdenTrabajoModel $orden, ?array $calificacion = null): bool
    {
        $calificacion ??= $this->calificacionDeFolio($orden->FolioParo);

        if ($calificacion === null) {
            return false;
        }

        $folio = trim((string) $orden->Folio);

        if ($folio === '') {
            return false;
        }

        MecOrdenTrabajoLineModel::query()
            ->where('Folio', $folio)
            ->whereNull('Calificacion')
            ->update($calificacion);

        if (
            $this->estatusEs($orden->Estatus, self::ESTATUS_ORDEN_TERMINADO)
            && $this->todosLosRenglonesCalificados($folio)
        ) {
            $orden->update(['Estatus' => self::ESTATUS_ORDEN_CALIFICADO]);
        }

        return true;
    }

    /**
     * Reparte la calificación de un paro recién cerrado entre sus órdenes abiertas.
     *
     * Un mismo paro puede originar varias órdenes a propósito (una intervención
     * puede requerir varios pases; ver OrdenesTrabajoMecaController::parosHistorial),
     * así que se recorren todas. Las que ya están Calificadas, Autorizadas o
     * Canceladas quedan fuera: su calificación ya está cerrada.
     *
     * @return int cuántas órdenes se calificaron.
     */
    public function propagarAOrdenesDelParo(ManFallasParos $paro): int
    {
        $calificacion = $this->calificacionDelParo($paro);

        if ($calificacion === null) {
            return 0;
        }

        $folioParo = trim((string) $paro->Folio);

        if ($folioParo === '') {
            return 0;
        }

        $ordenes = MecOrdenTrabajoModel::query()
            ->where('FolioParo', $folioParo)
            ->whereIn('Estatus', [self::ESTATUS_ORDEN_ACTIVO, self::ESTATUS_ORDEN_TERMINADO])
            ->get();

        $calificadas = 0;

        foreach ($ordenes as $orden) {
            if ($this->calificarOrden($orden, $calificacion)) {
                $calificadas++;
            }
        }

        return $calificadas;
    }

    /**
     * Calificación heredable a partir del folio de paro que guarda la orden.
     *
     * @return array{Calificacion: int, CveTejedor: ?string, NomTejedor: ?string}|null
     */
    public function calificacionDeFolio(?string $folioParo): ?array
    {
        $folioParo = trim((string) $folioParo);

        if ($folioParo === '') {
            return null;
        }

        $paro = ManFallasParos::query()
            ->where('Folio', $folioParo)
            ->first(['Folio', 'Estatus', 'Calidad', 'CveAtendio', 'NomAtendio']);

        return $paro === null ? null : $this->calificacionDelParo($paro);
    }

    /**
     * Calificación heredable de un paro concreto, o null si no hay nada que heredar
     * (paro todavía Activo, cerrado sin Calidad, o con una Calidad fuera de escala).
     *
     * @return array{Calificacion: int, CveTejedor: ?string, NomTejedor: ?string}|null
     */
    public function calificacionDelParo(ManFallasParos $paro): ?array
    {
        if (! $this->estatusEs($paro->Estatus, self::ESTATUS_PARO_TERMINADO)) {
            return null;
        }

        if ($paro->Calidad === null) {
            return null;
        }

        $calificacion = (int) $paro->Calidad;

        // Un paro histórico fuera de escala no se "arregla" aquí: se ignora y la
        // orden cae al flujo manual en vez de guardar un valor que no significa nada.
        if ($calificacion < self::CALIFICACION_MINIMA || $calificacion > self::CALIFICACION_MAXIMA) {
            return null;
        }

        $cve = trim((string) ($paro->CveAtendio ?? ''));

        return [
            'Calificacion' => $calificacion,
            'CveTejedor' => $cve !== '' ? $cve : null,
            'NomTejedor' => $this->nombreDelCalificador($cve, $paro->NomAtendio),
        ];
    }

    /**
     * Nombre de quien calificó el paro.
     *
     * Ojo con la asimetría de ManFallasParos: CveAtendio es el número de empleado
     * de quien CIERRA el paro (se toma de la sesión), mientras que NomAtendio sale
     * de un select de operadores de mantenimiento y nombra a quien ATENDIÓ la
     * máquina. Suelen ser personas distintas, así que el nombre se resuelve desde
     * SYSUsuario con la clave real. NomAtendio solo sirve de respaldo cuando esa
     * clave no existe en el catálogo de usuarios.
     */
    private function nombreDelCalificador(string $cveAtendio, ?string $nomAtendio): ?string
    {
        if ($cveAtendio !== '') {
            $nombre = trim((string) Usuario::query()
                ->where('numero_empleado', $cveAtendio)
                ->value('nombre'));

            if ($nombre !== '') {
                return $nombre;
            }
        }

        return trim((string) ($nomAtendio ?? '')) ?: null;
    }

    private function todosLosRenglonesCalificados(string $folio): bool
    {
        $total = MecOrdenTrabajoLineModel::query()
            ->where('Folio', $folio)
            ->count();

        if ($total === 0) {
            return false;
        }

        $calificados = MecOrdenTrabajoLineModel::query()
            ->where('Folio', $folio)
            ->whereNotNull('Calificacion')
            ->whereBetween('Calificacion', [self::CALIFICACION_MINIMA, self::CALIFICACION_MAXIMA])
            ->count();

        return $total === $calificados;
    }

    /**
     * Las columnas de estatus son NVARCHAR con colación case-insensitive: se
     * comparan igual que en MantenimientoParosController::finalizar.
     */
    private function estatusEs(mixed $estatus, string $esperado): bool
    {
        return strcasecmp(trim((string) $estatus), $esperado) === 0;
    }
}
