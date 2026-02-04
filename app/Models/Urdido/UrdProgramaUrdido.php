<?php

namespace App\Models\Urdido;

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
        'InventSizeId',
        'Metros',
        'Kilos',
        'SalonTejidoId',
        'MaquinaId',
        'BomId',
        'FechaProg',
        'Status',
        'FolioConsumo',
        'BomFormula',
        'TipoAtado',
        'CveEmpl',
        'NomEmpl',
        'LoteProveedor',
        'Observaciones',
        'Prioridad',
        'CreatedAt',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Calibre' => 'float',
        'FechaReq' => 'date',
        'Metros' => 'float',
        'Kilos' => 'float',
        'FechaProg' => 'date',
        'Prioridad' => 'integer',
        'CreatedAt' => 'datetime',
        'Observaciones' => 'string',
    ];

    /**
     * Extraer el número de MC Coy del campo MaquinaId
     * Ejemplos: "Mc Coy 1" -> 1, "Mc Coy 2" -> 2
     *
     * @return int|null
     */
    public function getMcCoyNumberAttribute(): ?int
    {
        if (empty($this->MaquinaId)) {
            return null;
        }

        // Buscar patrón "Mc Coy X" o "Mc Coy X" (case insensitive)
        if (preg_match('/mc\s*coy\s*(\d+)/i', $this->MaquinaId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

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

