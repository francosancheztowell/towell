<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoFlogProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

final class MachineFlogSummary extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $program = null;

    /** @var list<string> */
    public array $purchBarcodes = [];

    public bool $loaded = false;

    /** @var array<string, mixed>|null */
    public ?array $summary = null;

    private CrudoFlogProvider $provider;

    public function boot(CrudoFlogProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function mount(): void
    {
        $this->purchBarcodes = array_slice(array_values(array_unique(array_filter(array_map(
            static fn (mixed $barcode): string => trim((string) $barcode),
            $this->purchBarcodes,
        )))), 0, 50);
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        try {
            $this->summary = $this->provider->find($this->program, $this->purchBarcodes);
        } catch (Throwable $exception) {
            report($exception);
            $this->summary = [
                'status' => 'error',
                'source' => null,
                'flog' => '',
                'client' => '',
                'itemId' => '',
                'inventSizeId' => '',
                'simulationSalesUrl' => null,
                'simulationDesignUrl' => null,
                'lineMatched' => false,
            ];
        }
    }

    public function render(): View
    {
        return view('livewire.crudo.machine-flog-summary');
    }
}
