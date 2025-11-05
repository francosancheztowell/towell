<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PronosticoResource extends JsonResource
{
    public function toArray($request)
    {
        // Campos ordenados según tabla ReqPronosticos
        return [
            // 1. IdFlog
            'IDFLOG'         => $this->IDFLOG ?? null,
            // 2. Estado
            'ESTADO'         => $this->ESTADO ?? null,
            // 3. Proyecto
            'NOMBREPROYECTO' => $this->NOMBREPROYECTO ?? null,
            // 4. Cliente
            'CUSTNAME'       => $this->CUSTNAME ?? null,
            // 5. Calidad
            'CATEGORIACALIDAD' => $this->CATEGORIACALIDAD ?? null,
            // 6. Ancho
            'ANCHO'          => $this->ANCHO ?? null,
            // 7. Largo
            'LARGO'          => $this->LARGO ?? null,
            // 8. Articulo
            'ITEMID'         => $this->ITEMID ?? null,
            // 9. Nombre
            'ITEMNAME'       => $this->ITEMNAME ?? null,
            // 10. Tamaño
            'INVENTSIZEID'   => $this->INVENTSIZEID ?? null,
            // 11. TipoHilo
            'TIPOHILOID'     => $this->TIPOHILOID ?? null,
            // 12. Valor Agregad
            'VALORAGREGADO'  => $this->VALORAGREGADO ?? null,
            // 13. Fecha Cancel
            'FECHACANCELACION' => $this->FECHACANCELACION ?? null,
            // 14. Cantidad
            'CANTIDAD'       => $this->CANTIDAD ?? null,
            // 15. Tipo Articulo
            'ITEMTYPEID'     => $this->ITEMTYPEID ?? null,

            // Campos adicionales (no en orden principal)
            'RASURADOCRUDO'  => $this->RASURADOCRUDO ?? null,
            'RASURADO'       => $this->RASURADO ?? null,
            'CODIGOBARRAS'   => $this->CODIGOBARRAS ?? null,

            // Campos legacy (mantener para compatibilidad)
            'PORENTREGAR'    => $this->PORENTREGAR ?? null,
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




