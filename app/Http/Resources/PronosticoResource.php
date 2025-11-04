<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PronosticoResource extends JsonResource
{
    public function toArray($request)
    {
        // Campos comunes; si alguno no existe, null
        return [
            'IDFLOG'         => $this->IDFLOG ?? null,
            'CUSTNAME'       => $this->CUSTNAME ?? null,
            'ITEMID'         => $this->ITEMID ?? null,
            'INVENTSIZEID'   => $this->INVENTSIZEID ?? null,
            'ITEMNAME'       => $this->ITEMNAME ?? null,
            'TIPOHILOID'     => $this->TIPOHILOID ?? null,
            'RASURADOCRUDO'  => $this->RASURADOCRUDO ?? null,
            'VALORAGREGADO'  => $this->VALORAGREGADO ?? null,
            'ANCHO'          => $this->ANCHO ?? null,
            'ITEMTYPEID'     => $this->ITEMTYPEID ?? null,
            'CODIGOBARRAS'   => $this->CODIGOBARRAS ?? null,
            'PORENTREGAR'    => $this->PORENTREGAR ?? null,
            
            // Campos específicos de batas:
            'TOTAL_RESULTADO'=> $this->TOTAL_RESULTADO ?? null,
            'TOTAL_INVENTQTY'=> $this->TOTAL_INVENTQTY ?? null,
            'SUM_BOMQTY'     => $this->SUM_BOMQTY ?? null,
            'N_FACTORES'     => $this->N_FACTORES ?? null,
            'PROM_BOMQTY'    => $this->PROM_BOMQTY ?? null,
            
            // Auxiliares de agrupación (otros)
            'ANIO'           => $this->ANIO ?? null,
            'MES'            => $this->MES ?? null,
            
            // Fecha mínima en batas
            'FECHA'          => isset($this->FECHA) ? (string)$this->FECHA : null,
        ];
    }
}


