<?php

declare(strict_types=1);

namespace App\Services\Programas;

use App\Models\Urdido\UrdProgramaUrdido;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaModulo;
use Illuminate\Database\Eloquent\Model;

class ProgramBoardReadService
{
    /**
     * @return array<string, mixed>|null
     */
    public function order(ProgramaModulo $module, int $orderId): ?array
    {
        $modelClass = $module->programModel();
        $order = $modelClass::query()
            ->select($this->columns($module))
            ->find($orderId);

        if (! $order) {
            return null;
        }

        $laneKey = $module->laneKey(
            (string) $order->getAttribute($module->machineColumn())
        );
        if ($laneKey === null) {
            return null;
        }

        $urdidoStatus = $module === ProgramaModulo::Engomado
            ? UrdProgramaUrdido::query()->where('Folio', $order->Folio)->value('Status')
            : null;

        return $this->card(
            $module,
            $order,
            $laneKey,
            (int) ($order->Prioridad ?: 1),
            $urdidoStatus
        );
    }

    /**
     * @return array{
     *   lanes:array<int, array{key:string,label:string,short:string,orders:array<int, array<string,mixed>>}>,
     *   summary:array{total:int,programado:int,en_proceso:int,parcial:int,metros:float}
     * }
     */
    public function board(ProgramaModulo $module, string $search = '', string $status = 'todos'): array
    {
        $machineColumn = $module->machineColumn();
        $modelClass = $module->programModel();

        $query = $modelClass::query()
            ->select($this->columns($module))
            ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
            ->whereNotNull($machineColumn)
            ->where($machineColumn, '!=', '');

        if (in_array($status, ProgramaConfig::ACTIVE_STATUSES, true)) {
            $query->where('Status', $status);
        }

        $search = mb_substr(trim($search), 0, 80);
        if ($search !== '') {
            $query->where(function ($nested) use ($search, $machineColumn): void {
                $like = "%{$search}%";
                $nested->where('Folio', 'like', $like)
                    ->orWhere('InventSizeId', 'like', $like)
                    ->orWhere('Fibra', 'like', $like)
                    ->orWhere($machineColumn, 'like', $like);
            });
        }

        $orders = $query
            ->orderByRaw('CASE WHEN Prioridad IS NULL OR Prioridad <= 0 THEN 1 ELSE 0 END')
            ->orderBy('Prioridad')
            ->orderBy($module->fallbackOrderColumn())
            ->orderBy('Id')
            ->get();

        $urdidoStatuses = $module === ProgramaModulo::Engomado
            ? UrdProgramaUrdido::query()
                ->whereIn('Folio', $orders->pluck('Folio')->filter()->unique()->values())
                ->pluck('Status', 'Folio')
            : collect();

        $laneDefinitions = collect($module->lanes())->keyBy('key');
        $grouped = $laneDefinitions->map(fn (array $lane): array => [
            ...$lane,
            'orders' => [],
        ])->all();

        $summary = [
            'total' => 0,
            'programado' => 0,
            'en_proceso' => 0,
            'parcial' => 0,
            'metros' => 0.0,
        ];

        foreach ($orders as $index => $order) {
            $laneKey = $module->laneKey($order->getAttribute($machineColumn));
            if ($laneKey === null || ! isset($grouped[$laneKey])) {
                continue;
            }

            $card = $this->card(
                $module,
                $order,
                $laneKey,
                $index + 1,
                $urdidoStatuses->get((string) $order->Folio)
            );
            $grouped[$laneKey]['orders'][] = $card;

            $summary['total']++;
            $summary['metros'] += (float) ($order->Metros ?? 0);
            match ((string) $order->Status) {
                'Programado' => $summary['programado']++,
                'En Proceso' => $summary['en_proceso']++,
                'Parcial' => $summary['parcial']++,
                default => null,
            };
        }

        return [
            'lanes' => array_values($grouped),
            'summary' => $summary,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function columns(ProgramaModulo $module): array
    {
        $columns = [
            'Id',
            'Folio',
            'RizoPie',
            'Cuenta',
            'Calibre',
            'Fibra',
            'InventSizeId',
            'Metros',
            $module->machineColumn(),
            'Status',
            'FechaProg',
            'Prioridad',
            'Observaciones',
        ];

        if ($module === ProgramaModulo::Urdido) {
            array_push(
                $columns,
                'CreatedAt',
                'Calidad',
                'CalidadComentario',
                'AutorizaCalidad',
                'FechaCalidad'
            );
        } else {
            $columns[] = 'BomFormula';
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return array<string, mixed>
     */
    private function card(
        ProgramaModulo $module,
        Model $order,
        string $laneKey,
        int $fallbackPriority,
        ?string $urdidoStatus,
    ): array {
        $size = trim((string) ($order->InventSizeId ?? ''));
        if ($size === '') {
            $size = trim(implode(' / ', array_filter([
                $order->Cuenta ?? null,
                $order->Calibre ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '')));
        }

        $priority = (int) ($order->Prioridad ?? 0);

        return [
            'id' => (int) $order->Id,
            'folio' => (string) ($order->Folio ?? ''),
            'type' => (string) ($order->RizoPie ?? ''),
            'size' => $size,
            'configuration' => (string) ($order->Fibra ?? ''),
            'meters' => (float) ($order->Metros ?? 0),
            'machine' => (string) $order->getAttribute($module->machineColumn()),
            'lane' => $laneKey,
            'status' => (string) ($order->Status ?? ''),
            'priority' => $priority > 0 ? $priority : $fallbackPriority,
            'observations' => (string) ($order->Observaciones ?? ''),
            'formula' => $module === ProgramaModulo::Engomado
                ? (string) ($order->BomFormula ?? '')
                : '',
            'quality' => $module === ProgramaModulo::Urdido
                ? (string) ($order->Calidad ?? '')
                : '',
            'quality_comment' => $module === ProgramaModulo::Urdido
                ? (string) ($order->CalidadComentario ?? '')
                : '',
            'quality_author' => $module === ProgramaModulo::Urdido
                ? (string) ($order->AutorizaCalidad ?? '')
                : '',
            'quality_date' => $module === ProgramaModulo::Urdido
                ? $order->FechaCalidad?->format('d/m/Y H:i')
                : null,
            'urdido_finished' => $module === ProgramaModulo::Engomado
                ? $urdidoStatus === 'Finalizado'
                : true,
        ];
    }
}
