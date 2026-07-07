<?php

namespace App\Models\Tejido;

use Illuminate\Database\Eloquent\Model;

class TejEficienciaLine extends Model
{
    protected $table = "TejEficienciaLine";

    protected $fillable = [
        "Folio",
        "Date",
        "Turno",
        "NoTelarId",
        "SalonTejidoId",
        "RpmStd",
        "EficienciaSTD",
        "RpmR1",
        "EficienciaR1",
        "RpmR2",
        "EficienciaR2",
        "RpmR3",
        "EficienciaR3",
        "ObsR1",
        "ObsR2",
        "ObsR3",
        "StatusOB1",
        "StatusOB2",
        "StatusOB3",
    ];

    const CREATED_AT = "created_at";
    const UPDATED_AT = "updated_at";

    protected $casts = [
        'Date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Esta tabla no tiene columna 'id'; su llave natural es compuesta
     * (Folio + NoTelarId + Turno + Date, ver índice único creado en
     * dedupe_and_constrain_tej_eficiencia_line). Eloquent no soporta
     * llaves primarias compuestas, así que se sobreescribe la
     * resolución de la llave para que save()/update()/delete() por
     * instancia usen esas columnas en el WHERE en lugar del 'id'
     * inexistente (evita el error "Invalid column name 'id'" en
     * updateOrCreate()).
     */
    protected $compositeKeyColumns = ['Folio', 'NoTelarId', 'Turno', 'Date'];

    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->compositeKeyColumns as $column) {
            $query->where($column, '=', $this->original[$column] ?? $this->getAttribute($column));
        }

        return $query;
    }

    protected function setKeysForSelectQuery($query)
    {
        return $this->setKeysForSaveQuery($query);
    }

    /**
     * Relación con el encabezado de eficiencia
     */
    public function tejEficiencia()
    {
        return $this->belongsTo(TejEficiencia::class, 'Folio', 'Folio');
    }

    /**
     * Relación con el telar
     */
    public function telar()
    {
        return $this->belongsTo(\App\Models\Planeacion\ReqTelares::class, 'NoTelarId', 'NoTelarId');
    }
}
