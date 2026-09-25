<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Livewire\Concerns\ConTabla;
use App\Models\Sistema\Monitoreo\MonError;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/errores — errores agrupados por huella (MON-25). Doble clic o Enter abre el detalle.
 */
class Errores extends Component
{
    use ConTabla;
    use SoloAdmin;

    public const ORIGENES = ['php', 'js', 'livewire', 'red', 'http5xx'];

    /** 'abiertos' (nuevo + visto) · 'todos' · o un estado. */
    #[Url(except: 'abiertos')]
    public string $estado = 'abiertos';

    #[Url(except: '')]
    public string $origen = '';

    public function columnas(): array
    {
        return [
            ['campo' => 'Estado', 'titulo' => 'Estado'],
            ['campo' => 'Origen', 'titulo' => 'Origen', 'clase' => 'hidden sm:table-cell'],
            ['campo' => 'Clase', 'titulo' => 'Clase', 'valor' => fn ($e) => Str::afterLast((string) $e->Clase, '\\')],
            ['campo' => 'Mensaje', 'titulo' => 'Mensaje', 'orden' => false, 'valor' => fn ($e) => Str::limit((string) $e->Mensaje, 110)],
            ['campo' => 'Ruta', 'titulo' => 'Ruta', 'clase' => 'hidden lg:table-cell'],
            ['campo' => 'Ocurrencias', 'titulo' => 'Veces'],
            ['campo' => 'UltimaVez', 'titulo' => 'Última vez', 'valor' => fn ($e) => $e->UltimaVez?->diffForHumans()],
        ];
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function updatedOrigen(): void
    {
        $this->resetPage();
    }

    public function verDetalle(?string $id = null): void
    {
        $id ??= $this->seleccionado;
        if ($id !== null && ctype_digit($id)) {
            $this->redirectRoute('admin.errores.show', ['id' => (int) $id]);
        }
    }

    public function render(): View
    {
        $query = MonError::query()
            ->when($this->estado === 'abiertos', fn ($q) => $q->whereIn('Estado', ['nuevo', 'visto']))
            ->when(in_array($this->estado, MonError::ESTADOS, true), fn ($q) => $q->where('Estado', $this->estado))
            ->when(in_array($this->origen, self::ORIGENES, true), fn ($q) => $q->where('Origen', $this->origen));

        $query = $this->aplicarTabla($query, ['Clase', 'Mensaje', 'Ruta', 'Archivo']);
        if ($this->ordenPor === '') {
            $query->orderByDesc('UltimaVez');
        }

        return view('livewire.admin.errores', [
            'filas' => $this->paginar($query),
            'estados' => MonError::ESTADOS,
            'origenes' => self::ORIGENES,
        ]);
    }
}
