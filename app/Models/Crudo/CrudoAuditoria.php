<?php

declare(strict_types=1);

namespace App\Models\Crudo;

use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CrudoAuditoria extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'dbo.CrudoAuditorias';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Fecha',
        'NoTelarId',
        'Salon',
        'OrdenTrabajo',
        'Turno',
        'CveEmpl',
        'NomEmpl',
        'AlineacionOrden',
        'DibujoJacquard',
        'IdentificacionJulio',
        'Marbetes',
        'Observaciones',
        'Defecto1Id',
        'Defecto1Pzas',
        'Defecto2Id',
        'Defecto2Pzas',
        'Defecto3Id',
        'Defecto3Pzas',
        'Defecto4Id',
        'Defecto4Pzas',
        'Defecto5Id',
        'Defecto5Pzas',
        'ParoId',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'datetime',
        'Turno' => 'integer',
        'AlineacionOrden' => 'boolean',
        'DibujoJacquard' => 'boolean',
        'IdentificacionJulio' => 'boolean',
        'Marbetes' => 'integer',
        'Defecto1Id' => 'integer',
        'Defecto1Pzas' => 'integer',
        'Defecto2Id' => 'integer',
        'Defecto2Pzas' => 'integer',
        'Defecto3Id' => 'integer',
        'Defecto3Pzas' => 'integer',
        'Defecto4Id' => 'integer',
        'Defecto4Pzas' => 'integer',
        'Defecto5Id' => 'integer',
        'Defecto5Pzas' => 'integer',
        'ParoId' => 'integer',
    ];

    public function paro(): BelongsTo
    {
        return $this->belongsTo(ManFallasParos::class, 'ParoId', 'Id');
    }

    public function defecto1(): BelongsTo
    {
        return $this->belongsTo(CatParosFallas::class, 'Defecto1Id', 'Id');
    }

    public function defecto2(): BelongsTo
    {
        return $this->belongsTo(CatParosFallas::class, 'Defecto2Id', 'Id');
    }

    public function defecto3(): BelongsTo
    {
        return $this->belongsTo(CatParosFallas::class, 'Defecto3Id', 'Id');
    }

    public function defecto4(): BelongsTo
    {
        return $this->belongsTo(CatParosFallas::class, 'Defecto4Id', 'Id');
    }

    public function defecto5(): BelongsTo
    {
        return $this->belongsTo(CatParosFallas::class, 'Defecto5Id', 'Id');
    }
}
