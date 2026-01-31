<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para dbo.SYSMensajes
 * Campos: Id, DepartamentoId, Telefono, Token, ChatId, Activo, FechaRegistro
 */
class SYSMensaje extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'dbo.SYSMensajes';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'DepartamentoId',
        'Telefono',
        'Token',
        'ChatId',
        'Activo',
    ];

    protected $casts = [
        'Id' => 'integer',
        'DepartamentoId' => 'integer',
        'Telefono' => 'string',
        'Token' => 'string',
        'ChatId' => 'string',
        'Activo' => 'boolean',
        'FechaRegistro' => 'datetime',
    ];

    public function departamento()
    {
        return $this->belongsTo(SysDepartamento::class, 'DepartamentoId', 'id');
    }
}
