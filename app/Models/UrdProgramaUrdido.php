<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrdProgramaUrdido extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdProgramaUrdido';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'NoTelarId',
        'RizoPie',
        'Cuenta',
        'Calibre',
        'FechaReq',
        'Fibra',
        'Metros',
        'Kilos',
        'NoProduccion',
        'SalonTejidoId',
        'MaquinaId',
        'BomId',
        'FechaProg',
        'Status',
        'FolioConsumo',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Calibre' => 'float',
        'FechaReq' => 'date',
        'Metros' => 'float',
        'Kilos' => 'float',
        'FechaProg' => 'date',
        'CreatedAt' => 'datetime',
    ];

    /**
     * Relación con EngProgramaEngomado (1:1)
     */
    public function engomado()
    {
        return $this->hasOne(EngProgramaEngomado::class, 'Folio', 'Folio');
    }

    /**
     * Relación con UrdJuliosOrden (1:N)
     */
    public function julios()
    {
        return $this->hasMany(UrdJuliosOrden::class, 'Folio', 'Folio');
    }

    /**
     * Relación con UrdConsumoHilo (1:N)
     */
    public function consumos()
    {
        return $this->hasMany(UrdConsumoHilo::class, 'Folio', 'Folio');
    }
}

