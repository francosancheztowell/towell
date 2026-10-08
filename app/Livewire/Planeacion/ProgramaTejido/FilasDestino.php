<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

use App\Http\Requests\Planeacion\DividirSaldoRequest;
use App\Http\Requests\Planeacion\DuplicarTejidoRequest;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\DividirTejido;
use App\Services\Planeacion\ProgramaTejido\DuplicarTejido;
use App\Support\Planeacion\NumeroPrograma;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Forma de las filas del diálogo Duplicar/Dividir y del payload que leen DividirTejido y
 * DuplicarTejido. Sin estado ni consultas: lo usa DuplicarDividir.
 */
final class FilasDestino
{
    /** Nombres legibles para los mensajes de validación (destinos.N.campo). */
    private const ATRIBUTOS = [
        'destinos.*.telar' => 'telar',
        'destinos.*.pedido' => 'pedido',
        'destinos.*.saldo' => 'saldo',
        'destinos.*.porcentaje_segundos' => '% de segundas',
        'destinos.*.observaciones' => 'observaciones',
    ];

    private const TOLERANCIA_SALDO = 0.5;

    /**
     * Datos del registro de origen más lo que decide el diálogo: grupo, NoProduccion y saldo
     * disponible (el del original, o Σ del grupo si redistribuye).
     *
     * @param  Collection<int, ReqProgramaTejido>  $grupo
     * @return array<string, mixed>
     */
    public static function origen(ReqProgramaTejido $registro, Collection $grupo): array
    {
        return self::datosDe($registro) + [
            'salonBd' => trim((string) $registro->getAttribute('SalonTejidoId')),
            'ordCompartida' => (int) $registro->getAttribute('OrdCompartida') ?: null,
            'conNoProduccion' => trim((string) $registro->getAttribute('NoProduccion')) !== '',
            'disponible' => round((float) $grupo->sum(fn ($r) => (float) $r->getAttribute('SaldoPedido')), 2),
        ];
    }

    /**
     * Dividir: las filas que ya existen (el original o los miembros del grupo) con su saldo, más
     * una fila nueva vacía para capturar el telar al que se pasa saldo.
     *
     * @param  Collection<int, ReqProgramaTejido>  $grupo
     * @param  array<string, mixed>  $origen
     * @return list<array<string, mixed>>
     */
    public static function filasDividir(Collection $grupo, array $origen): array
    {
        $filas = $grupo->map(self::filaExistente(...))->values()->all();
        $filas[] = self::fila($origen);

        return $filas;
    }

    /** @return array<string, mixed> */
    public static function datosDe(ReqProgramaTejido $r): array
    {
        $texto = fn (string $campo): string => trim((string) $r->getAttribute($campo));

        return [
            'id' => (int) $r->getAttribute('Id'),
            'salon' => TelarSalonResolver::normalizeSalon($r->getAttribute('SalonTejidoId'), $r->getAttribute('NoTelarId')),
            'telar' => $texto('NoTelarId'),
            'clave' => $texto('TamanoClave'),
            'itemId' => $texto('ItemId'),
            'inventSizeId' => $texto('InventSizeId'),
            'producto' => $texto('NombreProducto'),
            'flog' => $texto('FlogsId'),
            'descripcion' => $texto('NombreProyecto'),
            'custName' => $texto('CustName'),
            'aplicacion' => $texto('AplicacionId'),
            'pedido' => (float) $r->getAttribute('TotalPedido'),
            'saldo' => (float) $r->getAttribute('SaldoPedido'),
            'produccion' => (float) $r->getAttribute('Produccion'),
            'porcSeg' => (float) $r->getAttribute('PorcentajeSegundos'),
        ];
    }

    /**
     * Fila editable con los datos de $d (origen o miembro del grupo).
     *
     * @param  array<string, mixed>  $d
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function fila(array $d, array $extra = []): array
    {
        return array_merge([
            'salon' => $d['salon'], 'telar' => '', 'clave' => $d['clave'], 'producto' => $d['producto'],
            'flog' => $d['flog'], 'descripcion' => $d['descripcion'], 'aplicacion' => $d['aplicacion'],
            'custName' => $d['custName'], 'itemId' => $d['itemId'], 'inventSizeId' => $d['inventSizeId'],
            'pedido' => '', 'porcSeg' => self::numero($d['porcSeg']), 'saldo' => '', 'observaciones' => '',
            'produccion' => 0.0, 'registroId' => null, 'existente' => false,
        ], $extra);
    }

    /**
     * Copia con el pedido del original y saldo = pedido × (1 + %seg/100) (sin producción).
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    public static function filaDuplicar(array $o): array
    {
        return self::fila($o, [
            'pedido' => self::numero($o['pedido']),
            'saldo' => self::saldoDuplicar($o['pedido'], $o['porcSeg']),
        ]);
    }

    /** Regla 3 sin producción: saldo = pedido × (1 + %seg/100). */
    public static function saldoDuplicar(mixed $pedido, mixed $porcSeg): string
    {
        return self::numero(NumeroPrograma::sanitizeNumber($pedido) * (1 + NumeroPrograma::sanitizeNumber($porcSeg) / 100));
    }

    /**
     * Fila que ya existe en BD (el original o un miembro del grupo), con su saldo.
     *
     * @return array<string, mixed>
     */
    public static function filaExistente(ReqProgramaTejido $r): array
    {
        $d = self::datosDe($r);

        return self::fila($d, [
            'telar' => $d['telar'], 'saldo' => self::numero($d['saldo']), 'produccion' => $d['produccion'],
            'registroId' => $d['id'], 'existente' => true,
        ]);
    }

    /**
     * Regla 4: pedido derivado del saldo = (saldo + producción) / (1 + %seg/100).
     *
     * @param  array<string, mixed>  $fila
     */
    public static function pedidoDerivado(array $fila): float
    {
        $porcSeg = max(0.0, NumeroPrograma::sanitizeNumber($fila['porcSeg']));

        return round((NumeroPrograma::sanitizeNumber($fila['saldo']) + (float) $fila['produccion']) / (1 + $porcSeg / 100), 2);
    }

    /**
     * Disponible, asignado (Σ saldos) y diferencia. Cuadra con ±0.5 y sin saldos negativos.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{disponible: float, asignado: float, diferencia: float, cuadra: bool}
     */
    public static function cuadre(array $filas, float $disponible): array
    {
        $saldos = array_map(fn ($f) => NumeroPrograma::sanitizeNumber($f['saldo']), $filas);
        $asignado = round(array_sum($saldos), 2);
        $diferencia = round($asignado - $disponible, 2);

        return [
            'disponible' => $disponible,
            'asignado' => $asignado,
            'diferencia' => $diferencia,
            'cuadra' => abs($diferencia) <= self::TOLERANCIA_SALDO && min($saldos ?: [0]) >= 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $o  origen
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    public static function payloadDividir(array $o, array $filas, int $registroId, bool $esGrupo): array
    {
        return self::base($o, $registroId) + [
            'ord_compartida_existente' => $esGrupo ? $o['ordCompartida'] : null,
            'destinos' => array_map(fn (array $f) => self::destino($f) + [
                'saldo' => $f['saldo'],
                'registro_id' => $f['registroId'],
                'es_existente' => $f['existente'],
                'es_nuevo' => ! $f['existente'],
            ], array_values(array_filter($filas, self::filaUtil(...)))),
        ];
    }

    /**
     * @param  array<string, mixed>  $o  origen
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    public static function payloadDuplicar(array $o, array $filas, int $registroId, bool $vincular): array
    {
        return self::base($o, $registroId) + self::sinVacios([
            'tamano_clave' => $o['clave'],
            'cod_articulo' => $o['itemId'],
            'invent_size_id' => $o['inventSizeId'],
            'producto' => $o['producto'],
            'custname' => $o['custName'],
            'flog' => $o['flog'],
            'aplicacion' => $o['aplicacion'],
            'descripcion' => $o['descripcion'],
        ]) + [
            'vincular' => $vincular,
            'ord_compartida_existente' => $vincular ? $o['ordCompartida'] : null,
            'destinos' => array_map(fn (array $f) => self::destino($f) + self::sinVacios([
                'pedido' => $f['pedido'],
                'saldo' => $f['saldo'],
                'aplicacion' => $f['aplicacion'],
            ]), $filas),
        ];
    }

    /**
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    private static function base(array $o, int $registroId): array
    {
        return [
            'salon_tejido_id' => $o['salonBd'] ?: $o['salon'],
            'no_telar_id' => $o['telar'],
            'registro_id_original' => $registroId,
            'salon_destino' => $o['salon'],
        ];
    }

    /**
     * Una fila nueva sin telar ni saldo es la vacía que se ofrece por si acaso: no se manda.
     *
     * @param  array<string, mixed>  $f
     */
    private static function filaUtil(array $f): bool
    {
        return $f['existente'] || trim((string) $f['telar']) !== '' || NumeroPrograma::sanitizeNumber($f['saldo']) != 0.0;
    }

    /**
     * Campos comunes de un destino, con los nombres que leen DividirTejido/DuplicarTejido.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private static function destino(array $f): array
    {
        return self::sinVacios([
            'telar' => $f['telar'],
            'salon_destino' => $f['salon'],
            'porcentaje_segundos' => $f['porcSeg'],
            'observaciones' => $f['observaciones'],
            'tamano_clave' => $f['clave'],
            'producto' => $f['producto'],
            'flog' => $f['flog'],
            'descripcion' => $f['descripcion'],
            'custName' => $f['custName'],
            'itemId' => $f['itemId'],
            'inventSizeId' => $f['inventSizeId'],
        ]);
    }

    /**
     * '' → null y texto recortado: un vacío no pisa el dato del original.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function sinVacios(array $datos): array
    {
        return array_map(function ($v) {
            $v = is_string($v) ? trim($v) : $v;

            return $v === '' ? null : $v;
        }, $datos);
    }

    /** 1234.5 → "1234.5"; sin ceros de relleno. */
    public static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format(round($valor, 2), 2, '.', ''), '0'), '.');
    }

    /**
     * Normaliza cantidades, valida con las reglas del FormRequest y corre la lógica de
     * Dividir/Duplicar sin HTTP (el modal es el único cliente; los tests la llaman directo).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> el resultado; un error trae success=false, message y status
     *
     * @throws ValidationException
     */
    public static function correr(bool $dividir, array $payload): array
    {
        $request = $dividir ? new DividirSaldoRequest : new DuplicarTejidoRequest;
        $payload['destinos'] = $request::normalizarDestinos($payload['destinos'] ?? []);
        $data = Validator::make($payload, $request->rules(), [], self::ATRIBUTOS)->validate();

        return $dividir ? DividirTejido::dividir($data) : DuplicarTejido::duplicar($data);
    }
}
