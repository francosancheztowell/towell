<?php

namespace App\Models\Engomado;

use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EngProduccionEngomado extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'EngProduccionEngomado';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'HoraInicial',
        'HoraFinal',
        'Tiempo',
        'NoJulio',
        'KgBruto',
        'Tara',
        'KgNeto',
        'Canoa1',
        'Canoa2',
        'Canoa3',
        'Canoa4',
        'Tambor',
        'Humedad',
        'Ubicacion',
        'Roturas',
        'CveEmpl1',
        'NomEmpl1',
        'Metros1',
        'Turno1',
        'CveEmpl2',
        'NomEmpl2',
        'Metros2',
        'Turno2',
        'CveEmpl3',
        'NomEmpl3',
        'Metros3',
        'Turno3',
        'Solidos',
        'Finalizar',
        'AX',
        'Impresion',
        'Penalizacion',
        'OperadorDefecto',
        'NoEmplDefecto',
        'ClaveDefecto',
        'FechaDefecto',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'HoraInicial' => 'string',
        'HoraFinal' => 'string',
        'Tiempo' => 'string',
        'NoJulio' => 'string',
        'KgBruto' => 'float',
        'Tara' => 'float',
        'KgNeto' => 'float',
        'Canoa1' => 'float',
        'Canoa2' => 'float',
        'Canoa3' => 'float',
        'Canoa4' => 'float',
        'Humedad' => 'float',
        'Ubicacion' => 'string',
        'Roturas' => 'integer',
        'Turno1' => 'integer',
        'Metros1' => 'float',
        'Turno2' => 'integer',
        'Metros2' => 'float',
        'Turno3' => 'integer',
        'Metros3' => 'float',
        'Solidos' => 'float',
        'Finalizar' => 'integer',
        'AX' => 'integer',
        'Impresion' => 'boolean',
        'Penalizacion' => 'float',
        'NoEmplDefecto' => 'integer',
        'ClaveDefecto' => 'integer',
        'FechaDefecto' => 'datetime',
    ];

    public function programa(): HasOne
    {
        return $this->hasOne(EngProgramaEngomado::class, 'Folio', 'Folio');
    }

    public static function folioTieneAx(string $folio): bool
    {
        $folio = trim($folio);
        if ($folio === '') {
            return false;
        }

        return static::query()
            ->where('Folio', $folio)
            ->where('AX', 1)
            ->exists();
    }

    /**
     * @param  array<int, string|int|null>  $folios
     * @return array<int, string>
     */
    public static function foliosConAx(array $folios): array
    {
        $folios = array_values(array_unique(array_filter(array_map(
            static fn ($folio): string => trim((string) $folio),
            $folios
        ), static fn (string $folio): bool => $folio !== '')));

        if ($folios === []) {
            return [];
        }

        return static::query()
            ->whereIn('Folio', $folios)
            ->where('AX', 1)
            ->distinct()
            ->pluck('Folio')
            ->map(static fn ($folio): string => (string) $folio)
            ->all();
    }
}

