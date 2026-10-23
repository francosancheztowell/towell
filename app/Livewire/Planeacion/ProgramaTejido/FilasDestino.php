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
    ];

    private const TOLERANCIA_SALDO = 0.5;

    /**
     * Datos del registro de origen más lo que decide el diálogo: grupo y saldo disponible (el
     * del original, o Σ del grupo si redistribuye).
     *
     * @param  Collection<int, ReqProgramaTejido>  $grupo
     * @return array<string, mixed>
     */
    public static function origen(ReqProgramaTejido $registro, Collection $grupo): array
    {
        return self::datosDe($registro) + [
            'salonBd' => trim((string) $registro->getAttribute('SalonTejidoId')),
            'ordCompartida' => (int) $registro->getAttribute('OrdCompartida') ?: null,
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
            // Sin aplicación = "NA" (NINGUNA en ReqAplicaciones), igual que en la grilla.
            'flog' => $d['flog'], 'descripcion' => $d['descripcion'], 'aplicacion' => $d['aplicacion'] ?: 'NA',
            'custName' => $d['custName'], 'itemId' => $d['itemId'], 'inventSizeId' => $d['inventSizeId'],
            'pedido' => '', 'porcSeg' => self::numero($d['porcSeg']), 'saldo' => '',
            'produccion' => 0.0, 'registroId' => null, 'existente' => false, 'aviso' => null,
        ], $extra);
    }

    /**
     * Copia con el pedido del original; su saldo sale del pedido (sin producción). $conTelar: con
     * el telar del registro elegido (la fila que trae el diálogo al abrir).
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    public static function filaDuplicar(array $o, bool $conTelar = false): array
    {
        // La primera fila trae el telar del registro elegido; las que se agregan, sin telar.
        $fila = self::fila($o, ['pedido' => self::numero($o['pedido']), 'telar' => $conTelar ? $o['telar'] : '']);
        $fila['saldo'] = self::numero(self::saldo($fila));

        return $fila;
    }

    /**
     * Saldo = pedido × (1 + %seg/100) − producción, en los dos modos: el saldo no se captura, se
     * captura el pedido. Sin recortar a 0: negativo = el pedido es menor a lo ya producido.
     *
     * @param  array<string, mixed>  $fila
     */
    public static function saldo(array $fila): float
    {
        $factor = 1 + max(0.0, NumeroPrograma::sanitizeNumber($fila['porcSeg'])) / 100;

        return round(NumeroPrograma::sanitizeNumber($fila['pedido']) * $factor - (float) $fila['produccion'], 2);
    }

    /**
     * Fila que ya existe en BD (el original o un miembro del grupo). Su pedido inicial sale del saldo
     * pendiente, que es lo que se reparte; si no coincide con el pedido del programa, lo avisa.
     *
     * @return array<string, mixed>
     */
    public static function filaExistente(ReqProgramaTejido $r): array
    {
        $d = self::datosDe($r);
        $pedido = NumeroPrograma::pedidoDesdeSaldo($d['saldo'], $d['produccion'], $d['porcSeg']);
        $aviso = abs($pedido - $d['pedido']) > self::TOLERANCIA_SALDO
            ? 'En el programa el pedido es '.number_format($d['pedido'], 2).'; con el saldo pendiente da '.number_format($pedido, 2).'.'
            : null;

        return self::fila($d, [
            'telar' => $d['telar'], 'pedido' => self::numero($pedido), 'produccion' => $d['produccion'],
            'registroId' => $d['id'], 'existente' => true, 'aviso' => $aviso,
        ]);
    }

    /**
     * Disponible, asignado (Σ saldos) y diferencia. Cuadra con ±0.5 y sin saldos negativos.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{disponible: float, asignado: float, diferencia: float, cuadra: bool}
     */
    public static function cuadre(array $filas, float $disponible): array
    {
        $saldos = array_map(self::saldo(...), $filas);
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
            // DividirTejido recibe el saldo y de él despeja TotalPedido: se calcula aquí desde el pedido capturado.
            'destinos' => array_map(fn (array $f) => self::destino($f) + [
                'saldo' => self::numero(self::saldo($f)),
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
            // El saldo no se captura en Duplicar: sale de pedido y %seg aquí, no de lo que mande la pantalla.
            'destinos' => array_map(fn (array $f) => self::destino($f) + self::sinVacios([
                'pedido' => $f['pedido'],
                'saldo' => trim((string) $f['pedido']) === '' ? '' : self::numero(self::saldo($f)),
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
     * Una fila nueva sin telar ni pedido es la vacía que se ofrece por si acaso: no se manda.
     *
     * @param  array<string, mixed>  $f
     */
    public static function filaUtil(array $f): bool
    {
        return $f['existente'] || trim((string) $f['telar']) !== '' || NumeroPrograma::sanitizeNumber($f['pedido']) != 0.0;
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
