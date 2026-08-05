<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class MachineFloor extends Component
{
    /** @var array<string, array<string, mixed>> */
    public array $floorLayouts = [];

    public function render(): View
    {
        return view('livewire.crudo.machine-floor');
    }
}
