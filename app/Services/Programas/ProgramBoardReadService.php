<?php

declare(strict_types=1);

namespace App\Services\Programas;

use App\Models\Mantenimiento\ManFallasParos;
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

        $bloqueadoPorAx = $module->productionModel()::folioTieneAx((string) $order->Folio);

        return $this->card(
            $module,
            $order,
            $laneKey,
            (int) ($order->Prioridad ?: 1),
            $urdidoStatus,
            $bloqueadoPorAx
        );
    }

    /**
     * @return array{
     *   lanes:array<int, array{key:string,label:string,short:string,paro:array{folio:string,fecha:string,hora:string,falla:string,total:int,detalle:list<string>}|null,orders:array<int, array<string,mixed>>}>,
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

        $foliosConAx = array_fill_keys(
            $module->productionModel()::foliosConAx($orders->pluck('Folio')->all()),
            true
        );

        $paros = $this->parosActivos();
        $laneDefinitions = collect($module->lanes())->keyBy('key');
        $grouped = $laneDefinitions->map(fn (array $lane): array => [
            ...$lane,
            'paro' => $paros[self::maquinaParo($lane['label'])] ?? $paros[self::maquinaParo($lane['short'])] ?? null,
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
                $urdidoStatuses->get((string) $order->Folio),
                isset($foliosConAx[(string) $order->Folio])
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
     * Paros Activos por máquina (el más reciente manda en el badge), indexado por nombre normalizado.
     * Mantenimiento guarda "Mc Coy 1", "KM1", "WestPoint 2"; los carriles dicen
     * "MC Coy 1", "Karl Mayer" (KM1), "West Point 2": se comparan sin espacios ni mayúsculas.
     *
     * @return array<string, array{folio:string,fecha:string,hora:string,falla:string,total:int,detalle:list<string>}>
     */
    private function parosActivos(): array
    {
        $paros = [];
        ManFallasParos::query()
            ->select('Id', 'Folio', 'MaquinaId', 'Fecha', 'Hora', 'Falla', 'TipoFallaId')
            ->where('Estatus', 'Activo')
            ->orderByDesc('Id')
            ->toBase()
            ->get()
            ->each(function (object $paro) use (&$paros): void {
                $maquina = self::maquinaParo((string) $paro->MaquinaId);
                $folio = trim((string) $paro->Folio);
                $fecha = self::fechaParo($paro->Fecha ?? null);
                $hora = substr(trim((string) $paro->Hora), 0, 5);
                $falla = trim((string) ($paro->TipoFallaId ?: $paro->Falla));
                $cuando = trim($fecha.' '.$hora);
                $paros[$maquina] ??= ['folio' => $folio, 'fecha' => $fecha, 'hora' => $hora, 'falla' => $falla, 'total' => 0, 'detalle' => []];
                $paros[$maquina]['total']++;
                $paros[$maquina]['detalle'][] = trim("{$folio} · {$cuando} · {$falla}", ' ·');
            });

        return $paros;
    }

    private static function fechaParo(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }

        $valor = trim((string) $fecha);
        if ($valor === '') {
            return '';
        }

        $momento = strtotime($valor);

        return $momento === false ? '' : date('d/m/Y', $momento);
    }

    private static function maquinaParo(string $maquina): string
    {
        return strtolower(str_replace(' ', '', trim($maquina)));
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
                'FechaCalidad',
                ...array_keys(\App\Models\Urdido\UrdProgramaUrdido::CALIDAD_PUNTOS),
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
        bool $bloqueadoPorAx = false,
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
            'quality_points' => $module === ProgramaModulo::Urdido
                ? collect(\App\Models\Urdido\UrdProgramaUrdido::CALIDAD_PUNTOS)
                    ->mapWithKeys(fn (string $label, string $field): array => [
                        $field => $order->{$field} === null ? null : (bool) $order->{$field},
                    ])
                    ->all()
                : [],
            'urdido_finished' => $module === ProgramaModulo::Engomado
                ? $urdidoStatus === 'Finalizado'
                : true,
            'bloqueado_por_ax' => $bloqueadoPorAx,
        ];
    }
}
