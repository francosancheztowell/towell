<?php

declare(strict_types=1);

namespace App\Http\Resources\Crudo;

use App\Models\Mantenimiento\CatParosFallas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

final class CrudoAuditHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defects = $this->defects();
        $principalIndex = $this->principalDefectIndex($defects);

        foreach ($defects as $index => &$defect) {
            $defect['principal'] = $index === $principalIndex;
        }
        unset($defect);

        $checklist = [
            [
                'key' => 'alineacion_orden',
                'label' => 'Alineación',
                'resultado' => $this->answer($this->AlineacionOrden),
            ],
        ];

        if ($this->isJacquard()) {
            $checklist[] = [
                'key' => 'dibujo_jacquard',
                'label' => 'Dibujo JAC',
                'resultado' => $this->answer($this->DibujoJacquard),
            ];
        }

        $checklist[] = [
            'key' => 'identificacion_julio',
            'label' => 'Ident. julio',
            'resultado' => $this->answer($this->IdentificacionJulio),
        ];

        return [
            'id' => (int) $this->Id,
            'fecha' => $this->Fecha?->format('Y-m-d'),
            'hora' => $this->Fecha?->format('H:i'),
            'telar' => (string) $this->NoTelarId,
            'salon' => (string) $this->Salon,
            'orden' => $this->OrdenTrabajo,
            'turno' => (int) $this->Turno,
            'auditor' => $this->NomEmpl ?: $this->CveEmpl ?: 'Sin registrar',
            'checklist' => $checklist,
            'observaciones' => $this->Observaciones,
            'defectos' => $defects,
            'paro' => $this->ParoId === null ? null : [
                'id' => (int) $this->ParoId,
                'folio' => $this->whenLoaded('paro', fn () => $this->paro?->Folio),
                'falla' => $this->whenLoaded(
                    'paro',
                    fn () => $this->paro?->Descripcion ?: $this->paro?->Falla,
                ),
                'estatus' => $this->whenLoaded('paro', fn () => $this->paro?->Estatus),
            ],
        ];
    }

    /**
     * @return list<array{id: int, falla: string, descripcion: string|null, piezas: int, principal?: bool}>
     */
    private function defects(): array
    {
        $defects = [];

        for ($slot = 1; $slot <= 5; $slot++) {
            $pieces = (int) $this->getAttribute("Defecto{$slot}Pzas");
            $defectId = $this->getAttribute("Defecto{$slot}Id");

            if ($defectId === null || $pieces <= 0) {
                continue;
            }

            $catalog = $this->getRelationValue("defecto{$slot}");
            $defects[] = [
                'id' => (int) $defectId,
                'falla' => $catalog instanceof CatParosFallas
                    ? trim((string) $catalog->Falla)
                    : 'Defecto '.(int) $defectId,
                'descripcion' => $catalog instanceof CatParosFallas
                    ? ($catalog->Descripcion ?: null)
                    : null,
                'piezas' => $pieces,
            ];
        }

        return $defects;
    }

    /**
     * @param  list<array{piezas: int}>  $defects
     */
    private function principalDefectIndex(array $defects): ?int
    {
        if ($defects === []) {
            return null;
        }

        $principalIndex = 0;
        foreach ($defects as $index => $defect) {
            if ($defect['piezas'] > $defects[$principalIndex]['piezas']) {
                $principalIndex = $index;
            }
        }

        return $principalIndex;
    }

    private function answer(?bool $answer): string
    {
        return match ($answer) {
            true => 'bien',
            false => 'mal',
            null => 'sin_evaluar',
        };
    }

    private function isJacquard(): bool
    {
        return str_contains(Str::upper(trim((string) $this->Salon)), 'JAC');
    }
}
