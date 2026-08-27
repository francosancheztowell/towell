<?php

declare(strict_types=1);

namespace App\Services\Programas;

use App\Jobs\Programas\SendUrdidoQualityNotification;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaModulo;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgramBoardActionService
{
    public function __construct(
        private readonly ProgramaPrioridadService $priorityService,
    ) {}

    public function swapPriorities(ProgramaModulo $module, int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }

        if (! function_exists('userCan') || ! userCan('modificar', $module->permissionModule())) {
            throw new DomainException('No tienes permiso para cambiar la prioridad.');
        }

        $modelClass = $module->programModel();
        $orders = $modelClass::query()
            ->whereIn('Id', [$sourceId, $targetId])
            ->get(['Id', $module->machineColumn()]);

        if ($orders->count() !== 2) {
            throw new DomainException('No fue posible encontrar las dos órdenes.');
        }

        $lanes = $orders
            ->map(fn (Model $order): ?string => $module->laneKey(
                $order->getAttribute($module->machineColumn())
            ))
            ->filter()
            ->unique();

        if ($lanes->count() !== 1) {
            throw new DomainException('La prioridad solo puede cambiarse dentro de la misma máquina.');
        }

        $this->priorityService->swapPriorities($modelClass, $sourceId, $targetId);
    }

    public function saveObservations(
        ProgramaModulo $module,
        int $orderId,
        string $observations,
    ): void {
        $this->ensureSupervisor();

        $modelClass = $module->programModel();
        $connection = (new $modelClass)->getConnectionName();

        DB::connection($connection)->transaction(function () use ($modelClass, $orderId, $observations): void {
            $order = $modelClass::query()->lockForUpdate()->findOrFail($orderId);
            $order->Observaciones = $observations;
            $order->save();
        });
    }

    public function changeStatus(
        ProgramaModulo $module,
        int $orderId,
        string $newStatus,
    ): void {
        $this->ensureSupervisor();

        if (! in_array($newStatus, ProgramaConfig::STATUS_OPTIONS, true)) {
            throw new DomainException('El estado seleccionado no es válido.');
        }

        $modelClass = $module->programModel();
        $connection = (new $modelClass)->getConnectionName();

        DB::connection($connection)->transaction(function () use ($module, $modelClass, $orderId, $newStatus): void {
            /** @var Model $order */
            $order = $modelClass::query()->lockForUpdate()->findOrFail($orderId);
            $previousStatus = (string) $order->Status;

            if ($previousStatus === $newStatus) {
                return;
            }

            if (
                ProgramaConfig::estatusBloqueadoPorAxProduccion($newStatus)
                && $module->productionModel()::folioTieneAx((string) $order->Folio)
            ) {
                $tabla = $module === ProgramaModulo::Engomado
                    ? 'EngProduccionEngomado'
                    : 'UrdProduccionUrdido';
                throw new DomainException(ProgramaConfig::mensajeAxBloqueaEstatus($tabla));
            }

            if ($newStatus === 'En Proceso') {
                $blockReason = $this->productionBlockReasonForOrder($module, $order, true);
                if ($blockReason !== null) {
                    throw new DomainException($blockReason);
                }
            }

            $order->Status = $newStatus;

            if ($newStatus === 'Cancelado') {
                $order->Prioridad = null;
                $order->save();
                $this->deleteProductionForCancelledOrder($module, (string) $order->Folio);
                $this->recalculateActivePriorities($module);

                return;
            }

            if ($previousStatus === 'Cancelado') {
                $order->Prioridad = $this->nextPriority($module);
            }

            $order->save();
        });
    }

    public function saveQuality(
        ProgramaModulo $module,
        int $orderId,
        string $quality,
        string $comment,
    ): void {
        if ($module !== ProgramaModulo::Urdido) {
            throw new DomainException('La evaluación de calidad solo aplica a Urdido.');
        }

        if (! function_exists('userEsArea') || ! userEsArea('Calidad')) {
            throw new DomainException('Solo el área de Calidad puede evaluar una orden.');
        }

        if (! in_array($quality, ['A', 'R', 'O'], true)) {
            throw new DomainException('Selecciona un estado de calidad válido.');
        }

        $user = Auth::user();
        if (! $user) {
            throw new DomainException('La sesión del usuario ya no es válida.');
        }

        $modelClass = $module->programModel();
        $connection = (new $modelClass)->getConnectionName();

        $payload = DB::connection($connection)->transaction(
            function () use ($modelClass, $orderId, $quality, $comment, $user): array {
                $order = $modelClass::query()->lockForUpdate()->findOrFail($orderId);
                $order->Calidad = $quality;
                $order->CalidadComentario = $comment;
                $order->AutorizaCalidad = (string) ($user->nombre ?? '');
                $order->FechaCalidad = now();
                $order->save();

                return [
                    'folio' => (string) ($order->Folio ?? ''),
                    'date' => $order->FechaCalidad?->format('d/m/Y H:i'),
                    'author' => (string) ($order->AutorizaCalidad ?? ''),
                    'machine' => (string) ($order->MaquinaId ?? ''),
                    'supplier_lot' => (string) ($order->LoteProveedor ?? ''),
                    'fiber' => (string) ($order->Fibra ?? ''),
                    'size' => (string) ($order->InventSizeId ?? ''),
                    'quality' => (string) ($order->Calidad ?? ''),
                    'comment' => (string) ($order->CalidadComentario ?? ''),
                ];
            }
        );

        SendUrdidoQualityNotification::dispatchAfterResponse($payload);
    }

    public function productionBlockReason(ProgramaModulo $module, int $orderId): ?string
    {
        $modelClass = $module->programModel();
        $order = $modelClass::query()->findOrFail($orderId);

        return $this->productionBlockReasonForOrder($module, $order);
    }

    protected function ensureSupervisor(): void
    {
        $position = trim((string) (Auth::user()?->puesto ?? ''));

        if ($position === '' || stripos($position, 'supervisor') === false) {
            throw new DomainException('Solo un supervisor puede modificar esta información.');
        }
    }

    private function productionBlockReasonForOrder(
        ProgramaModulo $module,
        Model $order,
        bool $lockRows = false,
    ): ?string {
        if ($module === ProgramaModulo::Engomado) {
            $query = UrdProgramaUrdido::query()
                ->where('Folio', $order->Folio);

            if ($lockRows) {
                $query->lockForUpdate();
            }

            $urdidoStatus = $query->value('Status');

            return $urdidoStatus === 'Finalizado'
                ? null
                : 'La orden de Urdido debe estar finalizada antes de iniciar Engomado.';
        }

        $lane = $module->laneKey((string) $order->MaquinaId);
        if ($lane === null) {
            return null;
        }

        $query = $module->programModel()::query()
            ->where('Status', 'En Proceso')
            ->where('Id', '!=', $order->Id)
            ->whereNotNull('MaquinaId');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        $inProcess = $query
            ->get(['Id', 'MaquinaId'])
            ->filter(fn (Model $candidate): bool => $module->laneKey(
                (string) $candidate->MaquinaId
            ) === $lane)
            ->count();

        if ($inProcess < 2) {
            return null;
        }

        $machine = $lane === '4' ? 'Karl Mayer' : "MC Coy {$lane}";

        return "Ya existen 2 órdenes en proceso en {$machine}. Finaliza una antes de cargar otra.";
    }

    private function deleteProductionForCancelledOrder(ProgramaModulo $module, string $folio): void
    {
        $productionModel = $module->productionModel();
        $productionModel::query()->where('Folio', $folio)->delete();

        if ($module !== ProgramaModulo::Urdido) {
            return;
        }

        $engomado = EngProgramaEngomado::query()
            ->where('Folio', $folio)
            ->lockForUpdate()
            ->first();

        if (! $engomado || in_array($engomado->Status, ['Cancelado', 'Finalizado'], true)) {
            return;
        }

        $engomado->Status = 'Cancelado';
        $engomado->Prioridad = null;
        $engomado->save();
        EngProduccionEngomado::query()->where('Folio', $folio)->delete();
    }

    private function recalculateActivePriorities(ProgramaModulo $module): void
    {
        $modelClass = $module->programModel();
        $orders = $modelClass::query()
            ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
            ->orderByRaw('CASE WHEN Prioridad IS NULL OR Prioridad <= 0 THEN 1 ELSE 0 END')
            ->orderBy('Prioridad')
            ->orderBy($module->fallbackOrderColumn())
            ->orderBy('Id')
            ->lockForUpdate()
            ->get();

        foreach ($orders as $index => $order) {
            $order->Prioridad = $index + 1;
            $order->save();
        }
    }

    private function nextPriority(ProgramaModulo $module): int
    {
        $modelClass = $module->programModel();

        return (int) ($modelClass::query()
            ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
            ->whereNotNull('Prioridad')
            ->max('Prioridad') ?? 0) + 1;
    }
}
