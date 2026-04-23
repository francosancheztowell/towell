<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrdenFinalizadaAuditoria extends Model
{
    protected $table = 'OrdenFinalizadaAuditoria';

    protected $primaryKey = 'Id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'CreatedAt',
        'Source',
        'Action',
        'UserId',
        'ActorName',
        'ReqProgramaTejidoId',
        'NoProduccion',
        'SalonOrigen',
        'TelarOrigen',
        'SalonDestino',
        'TelarDestino',
        'PayloadJson',
    ];

    protected $casts = [
        'CreatedAt' => 'datetime',
        'UserId' => 'integer',
        'ReqProgramaTejidoId' => 'integer',
    ];

    public const SOURCE_UTILERIA = 'utileria_finalizar';

    public const SOURCE_DESARROLLADOR = 'desarrollador_finalizar';

    public const ACTION_UTILERIA_ELIMINAR = 'finalizar_eliminar';

    public const ACTION_DEV_FINALIZAR = 'dev_finalizar';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function registrarUtileriaFinalizar(
        int $reqProgramaTejidoId,
        string $noProduccion,
        ?string $salon,
        ?string $telar,
        array $payload = []
    ): void {
        static::query()->create([
            'CreatedAt' => now(),
            'Source' => self::SOURCE_UTILERIA,
            'Action' => self::ACTION_UTILERIA_ELIMINAR,
            'UserId' => Auth::id(),
            'ActorName' => null,
            'ReqProgramaTejidoId' => $reqProgramaTejidoId,
            'NoProduccion' => $noProduccion,
            'SalonOrigen' => $salon,
            'TelarOrigen' => $telar,
            'SalonDestino' => null,
            'TelarDestino' => null,
            'PayloadJson' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function registrarDesarrolladorFinalizar(
        int $reqProgramaTejidoId,
        string $noProduccion,
        ?string $salonOrigen,
        ?string $telarOrigen,
        ?string $salonDestino,
        ?string $telarDestino,
        ?string $actorName,
        array $payload = []
    ): void {
        static::query()->create([
            'CreatedAt' => now(),
            'Source' => self::SOURCE_DESARROLLADOR,
            'Action' => self::ACTION_DEV_FINALIZAR,
            'UserId' => Auth::id(),
            'ActorName' => $actorName,
            'ReqProgramaTejidoId' => $reqProgramaTejidoId,
            'NoProduccion' => $noProduccion,
            'SalonOrigen' => $salonOrigen,
            'TelarOrigen' => $telarOrigen,
            'SalonDestino' => $salonDestino,
            'TelarDestino' => $telarDestino,
            'PayloadJson' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }
}
