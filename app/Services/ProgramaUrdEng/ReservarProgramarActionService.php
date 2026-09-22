<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejedores\TejNotificaTejedorModel;
use App\Models\Tejido\TejInventarioTelares;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mutaciones de la pantalla Reservar y Programar: actualizar, reservar y liberar.
 *
 * Vivian en el controller, donde solo las alcanzaba una peticion HTTP. El
 * componente Livewire de la fase 6 las necesita como servicio, y ademas asi se
 * puede probar el flujo sin pasar por la ruta.
 */
final class ReservarProgramarActionService
{
    private const STATUS_ACTIVO = 'Activo';

    public function __construct(
        private InventarioTelaresService $telaresService,
        private ProgramasUrdidoEngomadoService $programasService,
        private InventarioReservasService $reservasService
    ) {}

    /**
     * Reserva una pieza y marca el telar en una sola operacion.
     *
     * El navegador hacia dos POST seguidos (actualizar-telar y luego
     * reservar-inventario): si el segundo fallaba, el telar se quedaba con
     * no_julio y no_orden puestos pero sin fila en InvTelasReservadas, y la
     * pantalla lo mostraba como reservado para siempre.
     *
     * @param  array<string, mixed>  $reserva  datos de la pieza, ya validados
     * @param  array<string, mixed>  $camposTelar  metros, no_julio, no_orden, localidad...
     * @return array{created: bool, message: string, telares_actualizados: int}
     */
    public function reservarConTelar(array $reserva, array $camposTelar = []): array
    {
        return DB::transaction(function () use ($reserva, $camposTelar): array {
            $actualizados = 0;

            if ($camposTelar !== []) {
                $actualizados = $this->actualizarInventarioTelares(
                    isset($reserva['TejInventarioTelaresId']) ? (int) $reserva['TejInventarioTelaresId'] : null,
                    (string) ($reserva['NoTelarId'] ?? ''),
                    $this->telaresService->normalizeTipo($reserva['Tipo'] ?? null),
                    $camposTelar
                );

                if ($actualizados === -1) {
                    throw new DomainException('Telar no encontrado o no está activo');
                }
            }

            $resultado = $this->reservasService->ejecutarReserva($reserva);

            return [
                'created' => $resultado['created'],
                'message' => $resultado['message'],
                'telares_actualizados' => $actualizados,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{tej_inventario_telares: int, urd_programa_urdido: int, eng_programa_engomado: int}
     *
     * @throws DomainException si no hay nada que actualizar o el telar no existe
     */
    public function actualizarTelar(array $datos): array
    {
        $noTelar = (string) ($datos['no_telar'] ?? '');
        $tipo = $this->telaresService->normalizeTipo($datos['tipo'] ?? null);
        $id = self::lleno($datos, 'id') ? (int) $datos['id'] : null;
        $fecha = self::lleno($datos, 'fecha') ? substr(trim((string) $datos['fecha']), 0, 10) : null;
        $turno = self::lleno($datos, 'turno') ? $datos['turno'] : null;

        $updateInventario = self::camposDeInventario($datos);
        $updateProgramas = self::camposDeProgramas($datos, $tipo);

        if ($updateInventario === [] && $updateProgramas === []) {
            throw new DomainException('No hay campos para actualizar');
        }

        return DB::transaction(function () use ($datos, $noTelar, $tipo, $id, $fecha, $turno, $updateInventario, $updateProgramas): array {
            $inventario = 0;
            $urdido = 0;
            $engomado = 0;

            if ($updateInventario !== []) {
                $inventario = $this->actualizarInventarioTelares($id, $noTelar, $tipo, $updateInventario, $fecha, $turno);
                if ($inventario === -1) {
                    throw new RuntimeException('Telar no encontrado o no está activo');
                }
            }

            // Los programas (UrdProgramaUrdido / EngProgramaEngomado) se tocan solo por Folio.
            // Programacion de Requerimientos manda solo_inventario=true y no los toca.
            if ($updateProgramas !== [] && empty($datos['solo_inventario'])) {
                $telar = $this->telarPorIdONumero($id, $noTelar, $tipo);
                $folio = $telar ? trim((string) ($telar->no_orden ?? '')) : '';

                if ($folio !== '') {
                    $resultado = $this->programasService->actualizar(
                        $noTelar,
                        $updateProgramas['tipo'] ?? $tipo,
                        $updateProgramas,
                        $folio
                    );
                    $urdido = $resultado['urdido'] ?? 0;
                    $engomado = $resultado['engomado'] ?? 0;
                }
            }

            return [
                'tej_inventario_telares' => $inventario,
                'urd_programa_urdido' => $urdido,
                'eng_programa_engomado' => $engomado,
            ];
        });
    }

    /**
     * Libera un telar: borra su reserva activa, resetea la notificacion del
     * tejedor y limpia los campos del telar. Son tres escrituras y van juntas.
     *
     * @return array{telar: TejInventarioTelares, reservas_eliminadas: int}
     *
     * @throws DomainException si el telar no existe o no esta reservado
     */
    public function liberar(?int $id, string $noTelar, ?string $tipo): array
    {
        $telar = $this->telarParaLiberar($id, $noTelar, $tipo);

        if (! $telar) {
            throw new DomainException('Telar no encontrado o no esta activo');
        }

        $noJulio = trim((string) ($telar->no_julio ?? ''));
        $noOrden = trim((string) ($telar->no_orden ?? ''));
        $tipoTelar = $this->telaresService->normalizeTipo($telar->tipo ?? $tipo);

        // Una barra se suelta por TejInventarioTelaresId, asi que su no_orden no hace
        // falta. Exigirlo dejaba la barra trabada si un julio entro sin lote: Reservado
        // en true, no_julio puesto, no_orden null, y liberar se negaba para siempre.
        if ($noJulio === '' || (! self::esBarraKm($telar->tipo) && $noOrden === '')) {
            throw new DomainException('Este telar no esta reservado (no tiene no_julio y no_orden)');
        }

        return DB::transaction(function () use ($telar, $noTelar, $noJulio, $noOrden, $tipoTelar): array {
            // Solo la reserva que coincide con No. Julio y Lote: un mismo telar
            // puede tener varias activas (Rizo 00043-455 + Pie 00044-454).
            // Una barra de Karl Mayer lleva hasta cuatro julios y se sueltan juntos:
            // liberar uno solo dejaria los otros tres sin fila de telar que los explique.
            $eliminadas = InvTelasReservadas::where('Status', 'Reservado')
                ->when(
                    self::esBarraKm($telar->tipo),
                    fn ($q) => $q->where('TejInventarioTelaresId', $telar->id),
                    fn ($q) => $q->where('NoTelarId', $noTelar)
                        ->where('InventSerialId', $noJulio)
                        ->where('InventBatchId', $noOrden)
                        ->when($tipoTelar !== null && $tipoTelar !== '', fn ($q2) => $q2->where('Tipo', $tipoTelar))
                )
                ->get()
                ->each(fn ($r) => $r->delete())
                ->count();

            $notifica = TejNotificaTejedorModel::query()
                ->whereRaw('LTRIM(RTRIM(telar)) = ?', [trim($noTelar)])
                ->whereRaw('LTRIM(RTRIM(no_julio)) = ?', [$noJulio])
                ->whereRaw('LTRIM(RTRIM(no_orden)) = ?', [$noOrden])
                ->when(
                    $tipoTelar !== null && $tipoTelar !== '',
                    fn ($q) => $q->whereRaw('LOWER(LTRIM(RTRIM(tipo))) = ?', [mb_strtolower(trim((string) $tipoTelar), 'UTF-8')])
                )
                ->orderByDesc('Fecha')
                ->orderByDesc('id')
                ->first();

            $notifica?->update(['no_julio' => null, 'no_orden' => null, 'Reserva' => 0]);

            // 'hilo' (la fibra) NO se limpia: viene del requerimiento, igual que cuenta
            // y calibre, y reservar nunca lo escribe. Borrarlo al liberar lo perdia para
            // siempre y descuadraba el resumen de semanas, que agrupa telares por hilo.
            $telar->update([
                'metros' => null,
                'no_julio' => null,
                'no_julio2' => null,
                'no_julio3' => null,
                'no_julio4' => null,
                'no_orden' => null,
                'no_orden2' => null,
                'no_orden3' => null,
                'no_orden4' => null,
                'Reservado' => false,
                'Programado' => false,
                'ConfigId' => null,
                'InventSizeId' => null,
                'InventColorId' => null,
                'localidad' => null,
                'LoteProveedor' => null,
                'NoProveedor' => null,
            ]);

            return ['telar' => $telar->fresh(), 'reservas_eliminadas' => $eliminadas];
        });
    }

    public function mensajeDeActualizacion(string $noTelar, array $detalle): string
    {
        $etiquetas = [
            'tej_inventario_telares' => 'TejInventarioTelares',
            'urd_programa_urdido' => 'UrdProgramaUrdido',
            'eng_programa_engomado' => 'EngProgramaEngomado',
        ];

        $partes = [];
        foreach ($etiquetas as $clave => $tabla) {
            if (($detalle[$clave] ?? 0) > 0) {
                $partes[] = "{$detalle[$clave]} registro(s) en {$tabla}";
            }
        }

        return $partes === []
            ? "Telar {$noTelar} actualizado (no se encontraron registros para actualizar)"
            : "Telar {$noTelar} actualizado: ".implode(', ', $partes);
    }

    /* ==================== Privados ==================== */

    /**
     * @return int registros actualizados, o -1 si no se encontro el telar
     */
    /**
     * Julio y su orden, en la misma posición. Una barra de Karl Mayer usa las
     * cuatro parejas porque cada julio puede venir de otra orden. Rizo y Pie
     * solo usan la primera, que es la que cruza Atadores contra AtaMontadoTelas.
     */
    public const PARES_JULIO = InventarioTelaresService::PARES_JULIO;

    /** El tipo de una barra de Karl Mayer es '1'..'4'; Rizo y Pie van por su nombre. */
    public static function esBarraKm($tipo): bool
    {
        return (bool) preg_match('/^[1-4]$/', trim((string) ($tipo ?? '')));
    }

    /**
     * Coloca el julio entrante en la primera columna libre de la barra.
     *
     * Sin esto, reservar el segundo julio pisaba el primero: el update trae
     * siempre 'no_julio'. Para Rizo/Pie no cambia nada, sigue siendo esa columna.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     *
     * @throws DomainException si la barra ya tiene sus cuatro julios
     */
    private function acomodarJulio(TejInventarioTelares $telar, array $update): array
    {
        if (! isset($update['no_julio']) || ! self::esBarraKm($telar->tipo)) {
            return $update;
        }

        $julio = trim((string) $update['no_julio']);
        $orden = trim((string) ($update['no_orden'] ?? ''));
        $ocupados = [];
        foreach (self::PARES_JULIO as $columna => $columnaOrden) {
            $ocupados[$columna] = trim((string) ($telar->{$columna} ?? ''));
        }

        // El update siempre trae no_julio y no_orden. Si se dejaran, el segundo
        // julio pisaría la orden del primero.
        unset($update['no_julio'], $update['no_orden']);

        // Reservar dos veces la misma pieza no gasta una columna.
        if ($julio === '' || in_array($julio, $ocupados, true)) {
            return $update;
        }

        foreach (self::PARES_JULIO as $columna => $columnaOrden) {
            if ($ocupados[$columna] === '') {
                $update[$columna] = $julio;
                if ($orden !== '') {
                    $update[$columnaOrden] = $orden;
                }

                return $update;
            }
        }

        throw new DomainException(
            "La barra {$telar->tipo} del telar {$telar->no_telar} ya tiene sus cuatro julios."
        );
    }

    private function actualizarInventarioTelares(?int $id, string $noTelar, ?string $tipo, array $update, ?string $fecha = null, $turno = null): int
    {
        if ($id) {
            $telar = TejInventarioTelares::where('id', $id)->where('status', self::STATUS_ACTIVO)->first();
            if (! $telar) {
                return -1;
            }
            $telar->update($this->acomodarJulio($telar, $update));

            return 1;
        }

        // Sin id: no_telar + tipo + fecha + turno acotan al registro exacto.
        $query = TejInventarioTelares::where('no_telar', $noTelar)->where('status', self::STATUS_ACTIVO);
        if ($tipo !== null) {
            $query->where('tipo', $tipo);
        }
        if ($fecha !== null && $fecha !== '') {
            $query->whereDate('fecha', $fecha);
        }
        if ($turno !== null && $turno !== '') {
            $query->where('turno', $turno);
        }

        $telares = $query->get();
        if ($telares->isEmpty()) {
            return -1;
        }

        foreach ($telares as $telar) {
            $telar->update($this->acomodarJulio($telar, $update));
        }

        return $telares->count();
    }

    private function telarPorIdONumero(?int $id, string $noTelar, ?string $tipo): ?TejInventarioTelares
    {
        if ($id) {
            return TejInventarioTelares::where('id', $id)->where('status', self::STATUS_ACTIVO)->first();
        }

        $query = TejInventarioTelares::where('no_telar', $noTelar)->where('status', self::STATUS_ACTIVO);
        if ($tipo !== null && $tipo !== '') {
            $query->where('tipo', $tipo);
        }

        return $query->first();
    }

    private function telarParaLiberar(?int $id, string $noTelar, ?string $tipo): ?TejInventarioTelares
    {
        $q = TejInventarioTelares::where('status', self::STATUS_ACTIVO);

        if ($id) {
            $q->where('id', $id);
        } else {
            $q->where('no_telar', $noTelar);
            if ($tipo !== null) {
                $q->where('tipo', $tipo);
            }
        }

        // Priorizar los que de verdad estan reservados.
        $telar = (clone $q)
            ->whereNotNull('no_julio')->where('no_julio', '!=', '')
            ->whereNotNull('no_orden')->where('no_orden', '!=', '')
            ->first();

        return $telar ?: $q->first();
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function camposDeInventario(array $d): array
    {
        $update = [];

        if (self::lleno($d, 'metros')) {
            $update['metros'] = (float) $d['metros'];
        }
        if (self::lleno($d, 'no_julio')) {
            $update['no_julio'] = (string) $d['no_julio'];
        }
        if (self::lleno($d, 'no_orden')) {
            $update['no_orden'] = (string) $d['no_orden'];
            $update['LoteProveedor'] = (string) $d['no_orden'];
        }
        if (self::lleno($d, 'localidad')) {
            $update['localidad'] = (string) $d['localidad'];
        }
        if (self::lleno($d, 'tipo_atado')) {
            $update['tipo_atado'] = (string) $d['tipo_atado'];
        }
        if (self::lleno($d, 'hilo')) {
            $update['hilo'] = (string) $d['hilo'];
        }
        // cuenta y calibre se limpian a proposito: un '' o null borra el valor.
        if (array_key_exists('cuenta', $d)) {
            $update['cuenta'] = (string) ($d['cuenta'] ?? '');
        }
        if (array_key_exists('calibre', $d)) {
            $update['calibre'] = ($d['calibre'] !== '' && $d['calibre'] !== null) ? (float) $d['calibre'] : null;
        }
        if (self::lleno($d, 'lote_proveedor')) {
            $update['LoteProveedor'] = (string) $d['lote_proveedor'];
        }
        if (self::lleno($d, 'no_proveedor')) {
            $update['NoProveedor'] = (string) $d['no_proveedor'];
        }

        return $update;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function camposDeProgramas(array $d, ?string $tipoNormalizado): array
    {
        $update = [];

        if (self::lleno($d, 'hilo')) {
            $update['hilo'] = (string) $d['hilo'];
        }
        if (self::lleno($d, 'cuenta')) {
            $update['cuenta'] = (string) $d['cuenta'];
        }
        if (self::lleno($d, 'calibre')) {
            $update['calibre'] = (float) $d['calibre'];
        }
        if (self::lleno($d, 'tipo')) {
            $update['tipo'] = $tipoNormalizado;
        }

        return $update;
    }

    /** Equivalente a Request::filled() sobre un array. */
    private static function lleno(array $d, string $clave): bool
    {
        if (! array_key_exists($clave, $d)) {
            return false;
        }

        $v = $d[$clave];

        return $v !== null && (! is_string($v) || trim($v) !== '');
    }
}
