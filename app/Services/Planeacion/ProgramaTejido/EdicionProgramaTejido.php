<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Actions\Planeacion\ProgramaTejido\MutacionRechazada;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\NoProduccionCierreAxService;
use App\Services\Planeacion\ProgramaTejido\Edicion\ClaveModelo;
use App\Services\Planeacion\ProgramaTejido\Edicion\Derivados;
use App\Services\Planeacion\ProgramaTejido\Edicion\Persistencia;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Log;

/**
 * Edición inline de un renglón del programa de tejido, en tres pasos: aplicarCambios()
 * (campos validados, sin guardar), recalcularDerivados() (FechaFinal/fórmulas) y persistir()
 * (guardado con CatCodificados, cascada y líneas). La usan el PUT legacy (UpdateTejido) y la
 * Action v2 (ActualizarProgramaTejido). Los rechazos de negocio salen como
 * MutacionRechazada (422 con el cuerpo legacy); el endpoint la convierte en JSON.
 */
final class EdicionProgramaTejido
{
    private const FLAGS = ['afectaCalendario', 'afectaDuracion', 'afectaFormulas', 'afectaAplicacion', 'fechaFinalManual', 'editoAncho'];

    /**
     * Aplica los campos validados al registro (sin guardar) y devuelve las banderas de qué
     * afecta el cambio. El orden importa: velocidad_std va antes de la clave modelo (el
     * modelo la pisa) e idflog después (TipoPedido del flog gana sobre el del modelo).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     *
     * @throws MutacionRechazada clave modelo inexistente u orden cerrada en AX (422 legacy)
     */
    public static function aplicarCambios(ReqProgramaTejido $registro, array $data): array
    {
        $flags = array_fill_keys(self::FLAGS, false);

        self::asignarTexto($registro, $data, 'hilo', 'FibraRizo');
        self::asignarNumero($registro, $data, 'velocidad_std', 'VelocidadSTD');
        self::asignarNumero($registro, $data, 'eficiencia_std', 'EficienciaSTD');
        if (self::asignarTexto($registro, $data, 'calendario_id', 'CalendarioId')) {
            $flags['afectaCalendario'] = true;
        }
        if (array_key_exists('tamano_clave', $data) && ClaveModelo::aplicar($registro, $data)) {
            $flags['afectaDuracion'] = $flags['afectaFormulas'] = true;
        }
        if (array_key_exists('no_produccion', $data)) {
            self::aplicarNoProduccion($registro, $data['no_produccion']);
        }

        self::aplicarPedidoYProyecto($registro, $data, $flags);
        self::aplicarMedidas($registro, $data, $flags);
        self::aplicarEntregas($registro, $data, $flags);

        return $flags;
    }

    /**
     * @param  array<string, bool>  $flags
     *
     * @see Derivados::recalcular()
     */
    public static function recalcularDerivados(ReqProgramaTejido $registro, array $flags, float $horasProdAntes, float $cantidadAntes): void
    {
        Derivados::recalcular($registro, $flags, $horasProdAntes, $cantidadAntes);
    }

    /**
     * Corre dentro de la transacción de quien llama. $estricto = false es el legacy (un
     * fallo de aplicación en líneas se registra y se confirma igual); true (v2) revierte.
     *
     * @param  array<string, bool>  $flags
     *
     * @see Persistencia::guardar()
     */
    public static function persistir(ReqProgramaTejido $registro, array $flags, string $fechaFinalAntes, bool $estricto): void
    {
        Persistencia::guardar($registro, $flags, $fechaFinalAntes, $estricto);
    }

    /** '' (o falsy) se guarda como null. Devuelve si el campo venía en el PUT. */
    private static function asignarTexto(ReqProgramaTejido $registro, array $data, string $campo, string $columna): bool
    {
        if (! array_key_exists($campo, $data)) {
            return false;
        }
        $registro->setAttribute($columna, $data[$campo] ?: null);

        return true;
    }

    private static function asignarNumero(ReqProgramaTejido $registro, array $data, string $campo, string $columna): bool
    {
        if (! array_key_exists($campo, $data)) {
            return false;
        }
        $registro->setAttribute($columna, $data[$campo] !== null ? (float) $data[$campo] : null);

        return true;
    }

    private static function asignarFecha(ReqProgramaTejido $registro, array $data, string $campo, string $columna): void
    {
        if (! array_key_exists($campo, $data)) {
            return;
        }
        if (! $data[$campo]) {
            $registro->setAttribute($columna, null);

            return;
        }

        try {
            $registro->setAttribute($columna, Carbon::parse($data[$campo]));
        } catch (InvalidFormatException) {
            // Fecha inválida: no se asigna.
        } catch (\Throwable $e) {
            Log::warning('DateHelpers: Error al asignar fecha segura', [
                'atributo' => $columna, 'valor' => $data[$campo], 'error' => $e->getMessage(),
            ]);
        }
    }

    private static function aplicarNoProduccion(ReqProgramaTejido $registro, mixed $valor): void
    {
        $actual = trim((string) ($registro->NoProduccion ?? ''));
        $nueva = trim((string) ($valor ?? ''));

        if ($nueva !== '' && $nueva !== $actual && app(NoProduccionCierreAxService::class)->estaCerrada($nueva)) {
            throw MutacionRechazada::con([
                'success' => false,
                'code' => 'orden_cerrada_ax',
                'field' => 'no_produccion',
                'message' => "La orden {$nueva} ya está cerrada en AX y no puede asignarse como No. Producción.",
            ]);
        }

        $registro->NoProduccion = $nueva !== '' ? $nueva : null;
    }

    /** rasurado, pedido (TotalPedido/SaldoPedido), programar_prod, idflog, descripcion, aplicacion_id. */
    private static function aplicarPedidoYProyecto(ReqProgramaTejido $registro, array $data, array &$flags): void
    {
        self::asignarTexto($registro, $data, 'rasurado', 'Rasurado');

        if (array_key_exists('pedido', $data) && $data['pedido'] !== null) {
            $totalPedido = (float) $data['pedido'];
            $registro->TotalPedido = $totalPedido;
            $registro->SaldoPedido = max(0, $totalPedido - (float) ($registro->Produccion ?? 0));
            $flags['afectaDuracion'] = $flags['afectaFormulas'] = true;
        }

        self::asignarFecha($registro, $data, 'programar_prod', 'ProgramarProd');

        if (array_key_exists('idflog', $data)) {
            $registro->asignarFlog($data['idflog']);
        }

        self::asignarTexto($registro, $data, 'descripcion', 'NombreProyecto');

        if (array_key_exists('aplicacion_id', $data)) {
            $nueva = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
            $anterior = $registro->AplicacionId;
            $registro->AplicacionId = $nueva;
            if ((string) $anterior !== (string) $nueva) {
                $flags['afectaAplicacion'] = true;
            }
        }
    }

    /** no_tiras, peine, largo_crudo, luchaje, peso_crudo y ancho/ancho_toalla (solo PesoGRM2). */
    private static function aplicarMedidas(ReqProgramaTejido $registro, array $data, array &$flags): void
    {
        if (self::asignarNumero($registro, $data, 'no_tiras', 'NoTiras')) {
            $flags['afectaDuracion'] = $flags['afectaFormulas'] = true;
        }
        self::asignarNumero($registro, $data, 'peine', 'Peine');
        self::asignarNumero($registro, $data, 'largo_crudo', 'LargoCrudo');
        if (self::asignarNumero($registro, $data, 'luchaje', 'Luchaje')) {
            $flags['afectaDuracion'] = $flags['afectaFormulas'] = true;
        }
        if (self::asignarNumero($registro, $data, 'peso_crudo', 'PesoCrudo')) {
            $flags['afectaFormulas'] = true;
        }

        // Ancho y AnchoToalla van juntos; editar ancho no marca afectaFormulas.
        foreach (['ancho_toalla' => ['AnchoToalla', 'Ancho'], 'ancho' => ['Ancho', 'AnchoToalla']] as $campo => $columnas) {
            if (! array_key_exists($campo, $data)) {
                continue;
            }
            $valor = $data[$campo] !== null && $data[$campo] !== '' ? (float) $data[$campo] : null;
            foreach ($columnas as $columna) {
                $registro->setAttribute($columna, $valor);
            }
            $flags['editoAncho'] = true;
        }
    }

    private static function aplicarEntregas(ReqProgramaTejido $registro, array $data, array &$flags): void
    {
        self::asignarFecha($registro, $data, 'entrega_produc', 'EntregaProduc');
        self::asignarFecha($registro, $data, 'entrega_pt', 'EntregaPT');
        self::asignarFecha($registro, $data, 'entrega_cte', 'EntregaCte');
        self::asignarNumero($registro, $data, 'pt_vs_cte', 'PTvsCte');

        if (array_key_exists('fecha_final', $data) && $data['fecha_final']) {
            $registro->FechaFinal = Carbon::parse($data['fecha_final'])->format('Y-m-d H:i:s');
            $flags['fechaFinalManual'] = true;
        }
    }
}
