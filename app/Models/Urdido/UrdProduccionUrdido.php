<?php

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Model;

class UrdProduccionUrdido extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdProduccionUrdido';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'HoraInicial',
        'HoraFinal',
        'NoJulio',
        'Hilos',
        'KgBruto',
        'Tara',
        'KgNeto',
        'Hilatura',
        'Maquina',
        'Operac',
        'Transf',
        'TipoAtado',
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
        'Finalizar',
        'AX',
        'Vueltas',
        'Diametro',
        'Penalizacion',
        'OperadorDefecto',
        'NoEmplDefecto',
        'ClaveDefecto',
        'FechaDefecto',
    ];

    public function programa()
    {
        return $this->belongsTo(UrdProgramaUrdido::class, 'Folio', 'Folio');
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

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'HoraInicial' => 'string',
        'HoraFinal' => 'string',
        'Hilos' => 'integer',
        'KgBruto' => 'float',
        'Tara' => 'float',
        'KgNeto' => 'float',
        'Hilatura' => 'integer',
        'Maquina' => 'integer',
        'Operac' => 'integer',
        'Transf' => 'integer',
        'Turno1' => 'integer',
        'Metros1' => 'float',
        'Turno2' => 'integer',
        'Metros2' => 'float',
        'Turno3' => 'integer',
        'Metros3' => 'float',
        'Finalizar' => 'integer',
        'AX' => 'integer',
        'Vueltas' => 'float',
        'Diametro' => 'float',
        'Penalizacion' => 'float',
        'NoEmplDefecto' => 'integer',
        'ClaveDefecto' => 'integer',
        'FechaDefecto' => 'datetime',
    ];
}



