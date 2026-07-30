<?php

declare(strict_types=1);

namespace Tests\Unit\Programas;

use App\Livewire\UrdEng\ProgramBoard;
use App\Services\Programas\ProgramBoardActionService;
use App\Services\Programas\ProgramBoardReadService;
use App\Support\Programas\ProgramaModulo;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramBoardLivewireTest extends TestCase
{
    private FakeProgramBoardReadService $readService;

    private FakeProgramBoardActionService $actionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->readService = new FakeProgramBoardReadService;
        $this->actionService = new FakeProgramBoardActionService;
        $this->app->instance(ProgramBoardReadService::class, $this->readService);
        $this->app->instance(ProgramBoardActionService::class, $this->actionService);
    }

    public function test_it_renders_selects_and_edits_an_urdido_order(): void
    {
        Livewire::test(TestableProgramBoard::class, ['module' => 'urdido'])
            ->assertSee('MC Coy 1')
            ->assertSee('URD-100')
            ->call('selectOrder', 100)
            ->assertSet('selectedOrderId', 100)
            ->assertSee('Orden seleccionada')
            ->call('openObservations')
            ->assertSet('showObservations', true)
            ->set('observations', 'Observación actualizada')
            ->call('saveObservations')
            ->assertSet('showObservations', false)
            ->assertDispatched('program-board-notify');

        $this->assertSame('Observación actualizada', $this->actionService->savedObservations);
    }

    public function test_cancellation_requires_explicit_confirmation(): void
    {
        Livewire::test(TestableProgramBoard::class, ['module' => 'urdido'])
            ->call('selectOrder', 100)
            ->set('pendingStatus', 'Cancelado')
            ->call('changeStatus')
            ->assertSet('showCancellationConfirmation', true)
            ->call('confirmCancellation')
            ->assertSet('showCancellationConfirmation', false);

        $this->assertSame('Cancelado', $this->actionService->changedStatus);
    }

    public function test_reorder_calls_the_shared_action_and_reenables_polling(): void
    {
        Livewire::test(TestableProgramBoard::class, ['module' => 'urdido'])
            ->call('setInteractionPaused', true)
            ->assertSet('interactionPaused', true)
            ->call('reorder', 100, 101)
            ->assertSet('interactionPaused', false)
            ->assertDispatched('program-board-updated');

        $this->assertSame([100, 101], $this->actionService->swapped);
    }

    public function test_engomado_renders_the_urdido_prerequisite(): void
    {
        Livewire::test(TestableProgramBoard::class, ['module' => 'engomado'])
            ->assertSee('West Point 2')
            ->assertSee('ENG-200')
            ->assertSee('Urdido pendiente');
    }
}

class TestableProgramBoard extends ProgramBoard
{
    protected function authorizeAccess(): void {}

    protected function resolvePermissions(ProgramaModulo $module): void
    {
        $this->canEdit = true;
        $this->canLoadProduction = true;
        $this->canReprint = true;
        $this->canEvaluateQuality = $module === ProgramaModulo::Urdido;
    }
}

class FakeProgramBoardReadService extends ProgramBoardReadService
{
    public function board(ProgramaModulo $module, string $search = '', string $status = 'todos'): array
    {
        $order = $this->order($module, $module === ProgramaModulo::Urdido ? 100 : 200);
        $lanes = collect($module->lanes())->map(function (array $lane) use ($order): array {
            return [
                ...$lane,
                'orders' => $lane['key'] === '1' && $order !== null ? [$order] : [],
            ];
        })->all();

        return [
            'lanes' => $lanes,
            'summary' => [
                'total' => 1,
                'programado' => 1,
                'en_proceso' => 0,
                'parcial' => 0,
                'metros' => 1200.0,
            ],
        ];
    }

    public function order(ProgramaModulo $module, int $orderId): ?array
    {
        if ($module === ProgramaModulo::Urdido && in_array($orderId, [100, 101], true)) {
            return [
                'id' => $orderId,
                'folio' => $orderId === 100 ? 'URD-100' : 'URD-101',
                'type' => 'Rizo',
                'size' => '20/1',
                'configuration' => 'Algodón',
                'meters' => 1200.0,
                'machine' => 'Mc Coy 1',
                'lane' => '1',
                'status' => 'Programado',
                'priority' => $orderId === 100 ? 1 : 2,
                'observations' => 'Observación inicial',
                'formula' => '',
                'quality' => 'A',
                'quality_comment' => '',
                'quality_author' => '',
                'quality_date' => null,
                'urdido_finished' => true,
            ];
        }

        if ($module === ProgramaModulo::Engomado && $orderId === 200) {
            return [
                'id' => 200,
                'folio' => 'ENG-200',
                'type' => 'Pie',
                'size' => '16/1',
                'configuration' => 'Poliéster',
                'meters' => 900.0,
                'machine' => 'West Point 2',
                'lane' => '1',
                'status' => 'Programado',
                'priority' => 1,
                'observations' => '',
                'formula' => 'TE-PD-ENF-01',
                'quality' => '',
                'quality_comment' => '',
                'quality_author' => '',
                'quality_date' => null,
                'urdido_finished' => false,
            ];
        }

        return null;
    }
}

class FakeProgramBoardActionService extends ProgramBoardActionService
{
    /** @var array{int,int}|null */
    public ?array $swapped = null;

    public ?string $savedObservations = null;

    public ?string $changedStatus = null;

    public function __construct() {}

    public function swapPriorities(ProgramaModulo $module, int $sourceId, int $targetId): void
    {
        $this->swapped = [$sourceId, $targetId];
    }

    public function saveObservations(ProgramaModulo $module, int $orderId, string $observations): void
    {
        $this->savedObservations = $observations;
    }

    public function changeStatus(ProgramaModulo $module, int $orderId, string $newStatus): void
    {
        $this->changedStatus = $newStatus;
    }

    public function productionBlockReason(ProgramaModulo $module, int $orderId): ?string
    {
        return null;
    }
}
