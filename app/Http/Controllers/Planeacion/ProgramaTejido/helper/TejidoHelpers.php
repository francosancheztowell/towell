<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqVelocidadStd;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use App\Services\Planeacion\ProgramaTejido\CatalogoModelos;
use App\Services\Planeacion\ProgramaTejido\Edicion\ClaveModelo;
use App\Services\Planeacion\ProgramaTejido\EntregasPrograma;
use App\Services\Planeacion\ProgramaTejido\EstandaresTelar;
use App\Services\Planeacion\ProgramaTejido\FormulasEficiencia;
use App\Services\Planeacion\ProgramaTejido\HorasProduccion;
use App\Services\Planeacion\ProgramaTejido\PosicionesTelar;
use App\Support\Planeacion\NumeroPrograma;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;

/**
 * Helpers de Programa Tejido para los controllers (Balancear, Duplicar, Dividir, Update...).
 *
 * @deprecated La lógica vive en FormulasEficiencia, HorasProduccion, EntregasPrograma,
 *             EstandaresTelar, CalendarioProduccion, PosicionesTelar, CatalogoModelos,
 *             NumeroPrograma y TelarSalonResolver; el código nuevo va directo
 *             a ellas. Aquí solo quedan los helpers propios de dividir/duplicar.
 *             ponytail: adaptador temporal, retirar al migrar consumidores (los de
 *             app/Http/Controllers y los tests que aún llaman a TejidoHelpers).
 */
class TejidoHelpers
{
    /** Duración por defecto cuando se crea/duplica un registro sin fechas calculadas */
    public const DEFAULT_DURACION_DIAS = CalendarioProduccion::DEFAULT_DURACION_DIAS;

    /** Duración por defecto para registros de tipo REPASO */
    public const DEFAULT_DURACION_REPASO_HORAS = CalendarioProduccion::DEFAULT_DURACION_REPASO_HORAS;

    public const FORMULAS_CTX_BALANCEAR = FormulasEficiencia::FORMULAS_CTX_BALANCEAR;

    public const FORMULAS_CTX_PEDIDO_INHERIT = FormulasEficiencia::FORMULAS_CTX_PEDIDO_INHERIT;

    /** @see ClaveModelo::limpiarConstruccionSegunSalon() */
    public static function limpiarConstruccionSegunSalon(ReqProgramaTejido $registro): void
    {
        ClaveModelo::limpiarConstruccionSegunSalon($registro);
    }

    /**
     * Saldo = Pedido × (1 + %seg/100) − Producción, despejado para el pedido: cada fila de
     * Dividir captura saldo y su TotalPedido se deriva de él.
     */
    public static function pedidoDesdeSaldo(float $saldo, ReqProgramaTejido $registro): float
    {
        $produccion = (float) $registro->getAttribute('Produccion');
        $porcentajeSegundos = max(0.0, (float) $registro->getAttribute('PorcentajeSegundos'));

        return round(($saldo + $produccion) / (1 + $porcentajeSegundos / 100), 2);
    }

    /**
     * Dividir/duplicar a otro salón exige que la clave modelo exista en ReqModelosCodificados
     * para ese salón. Una consulta para todas las filas.
     *
     * @param  array<int, array{0: string, 1: ?string}>  $pares  [salón destino, TamanoClave]
     * @return string|null mensaje para el 422, o null si todas existen
     */
    public static function claveFaltanteEnSalon(array $pares): ?string
    {
        if ($pares === []) {
            return null;
        }
        $llave = fn ($salon, $clave) => TelarSalonResolver::normalizeSalon((string) $salon).'|'.mb_strtoupper(trim((string) $clave));

        $existentes = ReqModelosCodificados::query()
            ->whereIn('SalonTejidoId', array_merge(...array_map(fn ($p) => TelarSalonResolver::salonAliases($p[0]) ?: [$p[0]], $pares)))
            ->whereIn('TamanoClave', array_map(fn ($p) => trim((string) $p[1]), $pares))
            ->get(['SalonTejidoId', 'TamanoClave'])
            ->mapWithKeys(fn ($m) => [$llave($m->getAttribute('SalonTejidoId'), $m->getAttribute('TamanoClave')) => true]);

        foreach ($pares as [$salon, $clave]) {
            if (trim((string) $clave) === '' || ! $existentes->has($llave($salon, $clave))) {
                return "La clave modelo '".trim((string) $clave)."' no existe en Modelos para el salón {$salon}. Dala de alta antes de pasarla a ese salón.";
            }
        }

        return null;
    }

    /**
     * Indica si el producto es un repaso (NombreProducto empieza con REPASO).
     * Para repasos con saldo bajo se usa duración mínima de medio día en lugar de 30 días.
     */
    public static function esRepaso($programaOrNombre): bool
    {
        return CalendarioProduccion::esRepaso($programaOrNombre);
    }

    // ===== Delegados (ponytail: adaptador temporal, retirar al migrar consumidores) =====

    public static function obtenerSiguientePosicionDisponible(string $salonTejidoId, string $noTelarId): int
    {
        return PosicionesTelar::siguienteDisponible($salonTejidoId, $noTelarId);
    }

    /** @param  list<array{0: string, 1: string}>  $telares */
    public static function reservadorDePosiciones(array $telares): \Closure
    {
        return PosicionesTelar::reservador($telares);
    }

    public static function recalcularPosicionesPorTelar(string $salonTejidoId, string $noTelarId): void
    {
        PosicionesTelar::recalcular($salonTejidoId, $noTelarId);
    }

    public static function sanitizeNumber($value): float
    {
        return NumeroPrograma::sanitizeNumber($value);
    }

    public static function sanitizeNullableNumber($value): ?float
    {
        return NumeroPrograma::sanitizeNullableNumber($value);
    }

    public static function construirMaquinaConSalon(?string $maquinaBase, ?string $salon, $telar): string
    {
        return TelarSalonResolver::construirMaquina($maquinaBase, $salon, $telar);
    }

    public static function resolverFechaFinal(Carbon $inicio, float $horas, ?string $calendarioId): Carbon
    {
        return CalendarioProduccion::resolverFechaFinal($inicio, $horas, $calendarioId);
    }

    public static function finDesdeHoras(Carbon $inicio, float $horas, ?string $calendarioId): Carbon
    {
        return CalendarioProduccion::finDesdeHoras($inicio, $horas, $calendarioId);
    }

    public static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio, ?array $lines = null): ?Carbon
    {
        return CalendarioProduccion::snapInicioAlCalendario($calendarioId, $fechaInicio, $lines);
    }

    public static function calcularHorasProd(ReqProgramaTejido $programa, ?callable $obtenerModeloCallback = null): float
    {
        return HorasProduccion::calcularHorasProd($programa, $obtenerModeloCallback);
    }

    public static function calcularHorasProdFromParams(
        float $vel,
        float $efic,
        float $cantidad,
        float $noTiras,
        float $total,
        float $luchaje,
        float $repeticiones
    ): float {
        return HorasProduccion::calcularHorasProdFromParams($vel, $efic, $cantidad, $noTiras, $total, $luchaje, $repeticiones);
    }

    public static function stdToaHraKarlMayer(ReqProgramaTejido $programa): ?float
    {
        return HorasProduccion::stdToaHraKarlMayer($programa);
    }

    public static function resolverDiasEntrega(ReqProgramaTejido $programa): int
    {
        return EntregasPrograma::resolverDiasEntrega($programa);
    }

    public static function calcularFormulasEficiencia(
        ReqProgramaTejido $programa,
        array $modeloParams,
        bool $includeEntregaCte = false,
        bool $includePTvsCte = false,
        bool $fallbackEntregaCteFromProgram = false,
        ?float $stdToaHraAnterior = null,
        ?callable $checkVelocidadCambio = null
    ): array {
        return FormulasEficiencia::calcularFormulasEficiencia(
            $programa,
            $modeloParams,
            $includeEntregaCte,
            $includePTvsCte,
            $fallbackEntregaCteFromProgram,
            $stdToaHraAnterior,
            $checkVelocidadCambio
        );
    }

    public static function calcularFormulasEficienciaPorContexto(
        ReqProgramaTejido $programa,
        string $contexto,
        ?callable $obtenerModeloCallback = null
    ): array {
        return FormulasEficiencia::calcularFormulasEficienciaPorContexto($programa, $contexto, $obtenerModeloCallback);
    }

    public static function obtenerModeloParams(ReqProgramaTejido $programa, ?callable $obtenerModeloCallback = null): array
    {
        return HorasProduccion::obtenerModeloParams($programa, $obtenerModeloCallback);
    }

    public static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p): void
    {
        EstandaresTelar::aplicarStdDesdeCatalogos($p);
    }

    public static function resolverTipoTelarStd(?string $maquina, ?string $salonTejidoId): string
    {
        return EstandaresTelar::resolverTipoTelarStd($maquina, $salonTejidoId);
    }

    public static function buscarStdVelocidad(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqVelocidadStd
    {
        return EstandaresTelar::buscarStdVelocidad($tipoTelar, $telar, $fibraId, $densidad);
    }

    public static function buscarStdEficiencia(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqEficienciaStd
    {
        return EstandaresTelar::buscarStdEficiencia($tipoTelar, $telar, $fibraId, $densidad);
    }

    public static function obtenerModeloPorTamanoClave(
        string $tamanoClave,
        ?string $salonTejidoId = null,
        array $selectCols = ['*']
    ): ?ReqModelosCodificados {
        return CatalogoModelos::porTamanoClave($tamanoClave, $salonTejidoId, $selectCols);
    }

    public static function obtenerDatosModeloCodificadoArray(string $tamanoClave, string $salon): ?array
    {
        return CatalogoModelos::datosArray($tamanoClave, $salon);
    }
}
