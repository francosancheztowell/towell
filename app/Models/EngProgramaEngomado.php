<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngProgramaEngomado extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'EngProgramaEngomado';

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
        'SalonTejidoId',
        'MaquinaUrd',
        'BomUrd',
        'FechaProg',
        'Status',
        'Nucleo',
        'NoTelas',
        'AnchoBalonas',
        'MetrajeTelas',
        'Cuentados',
        'MaquinaEng',
        'BomEng',
        'Obs',
        'BomFormula',
        'TipoAtado',
        'CveEmpl',
        'NomEmpl',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Calibre' => 'float',
        'FechaReq' => 'date',
        'Metros' => 'float',
        'Kilos' => 'float',
        'FechaProg' => 'date',
        'NoTelas' => 'integer',
        'AnchoBalonas' => 'integer',
        'MetrajeTelas' => 'float',
        'Cuentados' => 'integer',
    ];

    /**
     * Relación con UrdProgramaUrdido (N:1)
     */
    public function programaUrdido()
    {
        return $this->belongsTo(UrdProgramaUrdido::class, 'Folio', 'Folio');
    }
}

