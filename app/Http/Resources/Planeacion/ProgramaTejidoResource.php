<?php

declare(strict_types=1);

namespace App\Http\Resources\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProgramaTejidoResource extends JsonResource
{
    /** @return array<string, bool|float|int|string|null> */
    public function toArray(Request $request): array
    {
        /** @var ReqProgramaTejido $model */
        $model = $this->resource;

        return [
            'id' => (int) $model->Id,
            'en_proceso' => (bool) $model->getAttribute('EnProceso'),
            'salon' => self::nullableString($model->getAttribute('SalonTejidoId')),
            'telar' => self::nullableString($model->getAttribute('NoTelarId')),
            'posicion' => self::nullableInteger($model->getAttribute('Posicion')),
            'orden_produccion' => self::nullableString($model->getAttribute('NoProduccion')),
            'producto' => self::nullableString($model->getAttribute('NombreProducto')),
            'item_id' => self::nullableString($model->getAttribute('ItemId')),
            'invent_size_id' => self::nullableString($model->getAttribute('InventSizeId')),
            'flog_id' => self::nullableString($model->getAttribute('FlogsId')),
            'total_pedido' => self::nullableFloat($model->getAttribute('TotalPedido')),
            'produccion' => self::nullableFloat($model->getAttribute('Produccion')),
            'saldo_pedido' => self::nullableFloat($model->getAttribute('SaldoPedido')),
            'fecha_inicio' => self::nullableDate($model->getAttribute('FechaInicio')),
            'fecha_final' => self::nullableDate($model->getAttribute('FechaFinal')),
            'prioridad' => self::nullableString($model->getAttribute('Prioridad')),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private static function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function nullableDate(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : null;
    }
}
