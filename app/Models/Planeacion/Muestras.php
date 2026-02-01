<?php

namespace App\Models\Planeacion;

class Muestras extends ReqProgramaTejido
{
    protected $table = 'MuestrasPrograma';

    public function getTable()
    {
        return $this->table;
    }

    public static function tableName(): string
    {
        return (new static())->table;
    }

    public function lineas()
    {
        return $this->hasMany(MuestrasLine::class, 'ProgramaId', 'Id');
    }
}
