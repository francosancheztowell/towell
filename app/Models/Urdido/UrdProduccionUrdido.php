<?php

namespace App\Models\Urdido;

use App\Models\Engomado\CatDefectosUrdEng;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $Folio
 * @property Carbon|string|null $Fecha
 * @property float|null $KgNeto
 * @property string|null $HoraInicial
 * @property string|null $HoraFinal
 * @property string|null $ImporteMP decimal(18,4): KgNeto × Σ(cump × %) del folio, ver CumpKardexService
 * @property string|null $NoJulio
 * @property int|string|null $Hilos
 * @property float|null $Metros1
 * @property float|null $Metros2
 * @property float|null $Metros3
 * @property string|null $NomEmpl1
 * @property string|null $NomEmpl2
 * @property string|null $NomEmpl3
 * @property bool|int|null $Finalizar
 * @property int|null $ClaveDefecto
 * @property float|null $Penalizacion
 * @property-read UrdProgramaUrdido|null $programa
 * @property-read CatDefectosUrdEng|null $defecto
 */
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

    /** @return BelongsTo<UrdProgramaUrdido, $this> */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(UrdProgramaUrdido::class, 'Folio', 'Folio');
    }

    /** @return BelongsTo<CatDefectosUrdEng, $this> */
    public function defecto(): BelongsTo
    {
        return $this->belongsTo(CatDefectosUrdEng::class, 'ClaveDefecto', 'Id');
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
