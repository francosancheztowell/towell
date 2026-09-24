<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Filtro desde/hasta (Y-m-d, inclusivo) para las tablas del panel. Una fecha
 * inválida en la URL se ignora en vez de romper la consulta.
 */
trait FiltroFechas
{
    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    protected function aplicarFechas(Builder $query, string $columna): Builder
    {
        $desde = $this->fecha($this->desde);
        $hasta = $this->fecha($this->hasta);

        return $query
            ->when($desde, fn (Builder $q) => $q->where($columna, '>=', $desde->startOfDay()))
            ->when($hasta, fn (Builder $q) => $q->where($columna, '<', $hasta->addDay()->startOfDay()));
    }

    protected function fecha(string $valor): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $valor) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
