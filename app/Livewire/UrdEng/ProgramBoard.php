<?php

declare(strict_types=1);

namespace App\Livewire\UrdEng;

use App\Services\Programas\ProgramBoardActionService;
use App\Services\Programas\ProgramBoardReadService;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaModulo;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class ProgramBoard extends Component
{
    #[Locked]
    public string $module = ProgramaModulo::Urdido->value;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'todos')]
    public string $status = 'todos';

    #[Url(as: 'orden', except: null)]
    public ?int $selectedOrderId = null;

    public string $pendingStatus = '';

    public string $observations = '';

    public string $quality = '';

    public string $qualityComment = '';

    public bool $showObservations = false;

    public bool $showQuality = false;

    public bool $showCancellationConfirmation = false;

    public bool $interactionPaused = false;

    #[Locked]
    public bool $canEdit = false;

    #[Locked]
    public bool $canLoadProduction = false;

    #[Locked]
    public bool $canReprint = false;

    #[Locked]
    public bool $canEvaluateQuality = false;

    public ?string $dataError = null;

    private ProgramBoardReadService $readService;

    private ProgramBoardActionService $actionService;

    public function boot(
        ProgramBoardReadService $readService,
        ProgramBoardActionService $actionService,
    ): void {
        $this->authorizeAccess();
        $this->readService = $readService;
        $this->actionService = $actionService;
    }

    public function mount(string $module): void
    {
        $resolved = ProgramaModulo::resolve($module);
        $this->module = $resolved->value;
        $this->search = $this->normalizeSearch($this->search);
        $this->status = $this->normalizeStatus($this->status);
        $this->resolvePermissions($resolved);
    }

    public function updatedSearch(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->selectedOrderId = null;
    }

    public function updatedStatus(): void
    {
        $this->status = $this->normalizeStatus($this->status);
        $this->selectedOrderId = null;
    }

    public function selectOrder(int $orderId): void
    {
        $order = $this->readService->order($this->moduleEnum(), $orderId);
        abort_unless($order !== null, 404, 'Orden no encontrada.');

        $this->selectedOrderId = $orderId;
        $this->pendingStatus = (string) $order['status'];
    }

    public function clearSelection(): void
    {
        $this->selectedOrderId = null;
        $this->pendingStatus = '';
        $this->closeModal();
    }

    public function refreshBoard(): void
    {
        if ($this->interactionPaused) {
            return;
        }

        $this->dataError = null;
        $this->dispatch('program-board-updated');
    }

    public function setInteractionPaused(bool $paused): void
    {
        $this->interactionPaused = $paused;
    }

    public function reorder(int $sourceId, int $targetId): void
    {
        try {
            $this->actionService->swapPriorities(
                $this->moduleEnum(),
                $sourceId,
                $targetId
            );
            $this->notify('success', 'Prioridad actualizada.');
        } catch (DomainException $exception) {
            $this->notify('warning', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->notify('error', 'No fue posible actualizar la prioridad.');
        } finally {
            $this->interactionPaused = false;
            $this->dispatch('program-board-updated');
        }
    }

    public function openObservations(): void
    {
        abort_unless($this->canEdit, 403);
        $order = $this->selectedOrder();

        $this->observations = (string) $order['observations'];
        $this->showObservations = true;
        $this->pauseForModal();
    }

    public function saveObservations(): void
    {
        abort_unless($this->canEdit, 403);
        $validated = $this->validate([
            'selectedOrderId' => ['required', 'integer'],
            'observations' => ['nullable', 'string', 'max:'.ProgramaConfig::OBSERVACIONES_MAX_LENGTH],
        ]);

        try {
            $this->actionService->saveObservations(
                $this->moduleEnum(),
                (int) $validated['selectedOrderId'],
                trim((string) $validated['observations'])
            );
            $this->closeModal();
            $this->notify('success', 'Observaciones guardadas.');
        } catch (DomainException $exception) {
            $this->addError('observations', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('observations', 'No fue posible guardar las observaciones.');
        }
    }

    public function changeStatus(): void
    {
        abort_unless($this->canEdit, 403);
        $this->validate([
            'selectedOrderId' => ['required', 'integer'],
            'pendingStatus' => ['required', 'string', 'in:'.implode(',', ProgramaConfig::STATUS_OPTIONS)],
        ]);

        if ($this->pendingStatus === 'Cancelado') {
            $this->showCancellationConfirmation = true;
            $this->pauseForModal();

            return;
        }

        $this->performStatusChange();
    }

    public function confirmCancellation(): void
    {
        abort_unless($this->canEdit, 403);
        abort_unless($this->pendingStatus === 'Cancelado', 422);

        $this->performStatusChange();
    }

    public function openQuality(): void
    {
        abort_unless($this->canEvaluateQuality, 403);
        $order = $this->selectedOrder();

        $this->quality = (string) $order['quality'];
        $this->qualityComment = (string) $order['quality_comment'];
        $this->showQuality = true;
        $this->pauseForModal();
    }

    public function saveQuality(): void
    {
        abort_unless($this->canEvaluateQuality, 403);
        $validated = $this->validate([
            'selectedOrderId' => ['required', 'integer'],
            'quality' => ['required', 'string', 'in:A,R,O'],
            'qualityComment' => [
                'nullable',
                'string',
                'max:'.ProgramaConfig::CALIDAD_COMENTARIO_MAX_LENGTH,
            ],
        ]);

        try {
            $this->actionService->saveQuality(
                $this->moduleEnum(),
                (int) $validated['selectedOrderId'],
                (string) $validated['quality'],
                trim((string) $validated['qualityComment'])
            );
            $this->closeModal();
            $this->notify('success', 'Evaluación de calidad guardada.');
        } catch (DomainException $exception) {
            $this->addError('quality', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('quality', 'No fue posible guardar la evaluación.');
        }
    }

    public function openProduction(): mixed
    {
        abort_unless(
            function_exists('userCan')
            && userCan('crear', $this->moduleEnum()->permissionModule()),
            403
        );
        abort_unless($this->selectedOrderId !== null, 422, 'Selecciona una orden.');

        try {
            $reason = $this->actionService->productionBlockReason(
                $this->moduleEnum(),
                $this->selectedOrderId
            );

            if ($reason !== null) {
                $this->notify('warning', $reason);

                return null;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->notify('error', 'No fue posible validar la orden seleccionada.');

            return null;
        }

        return $this->redirectRoute(
            $this->moduleEnum()->productionRouteName(),
            ['orden_id' => $this->selectedOrderId],
            navigate: false
        );
    }

    public function closeModal(): void
    {
        $this->showObservations = false;
        $this->showQuality = false;
        $this->showCancellationConfirmation = false;
        $this->interactionPaused = false;
        $this->resetValidation();
        $this->dispatch('program-board-modal', open: false);
    }

    public function render(): View
    {
        try {
            $board = $this->readService->board(
                $this->moduleEnum(),
                $this->search,
                $this->status
            );
            $this->dataError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->dataError = 'No fue posible consultar las órdenes.';
            $board = [
                'lanes' => collect($this->moduleEnum()->lanes())
                    ->map(fn (array $lane): array => [...$lane, 'orders' => []])
                    ->all(),
                'summary' => [
                    'total' => 0,
                    'programado' => 0,
                    'en_proceso' => 0,
                    'parcial' => 0,
                    'metros' => 0.0,
                ],
            ];
        }

        $selectedOrder = null;
        if ($this->selectedOrderId !== null) {
            foreach ($board['lanes'] as $lane) {
                foreach ($lane['orders'] as $order) {
                    if ((int) $order['id'] === $this->selectedOrderId) {
                        $selectedOrder = $order;
                        break 2;
                    }
                }
            }
        }

        $module = $this->moduleEnum();

        return view('livewire.urd-eng.program-board', [
            'board' => $board,
            'selectedOrder' => $selectedOrder,
            'moduleMeta' => [
                'value' => $module->value,
                'title' => $module->title(),
                'isUrdido' => $module === ProgramaModulo::Urdido,
                'reprintUrl' => route($module->reprintRouteName()),
                'legacyUrl' => route($module->legacyRouteName()),
            ],
            'statusOptions' => ProgramaConfig::STATUS_OPTIONS,
            'statusBloqueadosPorAx' => ProgramaConfig::STATUS_BLOQUEADOS_CON_AX_PRODUCCION,
            'pollSeconds' => (int) config('program-board.poll_seconds', 15),
            'observationsMaxLength' => ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
            'qualityCommentMaxLength' => ProgramaConfig::CALIDAD_COMENTARIO_MAX_LENGTH,
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Auth::check(), 403, 'La sesión del usuario ya no es válida.');
    }

    protected function resolvePermissions(ProgramaModulo $module): void
    {
        $position = trim((string) (Auth::user()?->puesto ?? ''));
        $this->canEdit = $position !== '' && stripos($position, 'supervisor') !== false;

        $this->canLoadProduction = function_exists('userCan')
            && userCan('crear', $module->permissionModule());
        $this->canReprint = function_exists('userCan')
            && userCan('registrar', $module->permissionModule());
        $this->canEvaluateQuality = $module === ProgramaModulo::Urdido
            && function_exists('userEsArea')
            && userEsArea('Calidad');
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedOrder(): array
    {
        abort_unless($this->selectedOrderId !== null, 422, 'Selecciona una orden.');

        $order = $this->readService->order($this->moduleEnum(), $this->selectedOrderId);
        abort_unless($order !== null, 404, 'Orden no encontrada.');

        return $order;
    }

    private function performStatusChange(): void
    {
        try {
            $this->actionService->changeStatus(
                $this->moduleEnum(),
                (int) $this->selectedOrderId,
                $this->pendingStatus
            );
            $this->closeModal();
            $this->notify('success', 'Estado actualizado.');
        } catch (DomainException $exception) {
            $this->showCancellationConfirmation = false;
            $this->addError('pendingStatus', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->showCancellationConfirmation = false;
            $this->addError('pendingStatus', 'No fue posible actualizar el estado.');
        }
    }

    private function pauseForModal(): void
    {
        $this->interactionPaused = true;
        $this->dispatch('program-board-modal', open: true);
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('program-board-notify', type: $type, message: $message);
    }

    private function moduleEnum(): ProgramaModulo
    {
        return ProgramaModulo::resolve($this->module);
    }

    private function normalizeSearch(string $search): string
    {
        return mb_substr(trim($search), 0, 80);
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['todos', ...ProgramaConfig::ACTIVE_STATUSES], true)
            ? $status
            : 'todos';
    }
}
