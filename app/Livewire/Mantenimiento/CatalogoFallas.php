<?php

declare(strict_types=1);

namespace App\Livewire\Mantenimiento;

use App\Livewire\Concerns\ConTabla;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\CatTipoFalla;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class CatalogoFallas extends Component
{
    use ConTabla;

    private const MODULO = 'Catalogo de Fallas';

    #[Url(except: '')]
    public string $tipoFallaFiltro = '';

    #[Url(except: '')]
    public string $departamentoFiltro = '';

    /** Id en edición; '' = alta nueva; null = modal cerrado. */
    public ?string $editando = null;

    public bool $confirmandoBorrado = false;

    /** @var array<string, string> */
    public array $form = [
        'TipoFallaId' => '',
        'Departamento' => '',
        'Falla' => '',
        'Descripcion' => '',
        'Abreviado' => '',
        'Seccion' => '',
    ];

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso al catálogo de fallas.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        return [
            ['campo' => 'TipoFallaId', 'titulo' => 'Tipo de falla'],
            ['campo' => 'Departamento', 'titulo' => 'Departamento'],
            ['campo' => 'Falla', 'titulo' => 'Falla'],
            ['campo' => 'Descripcion', 'titulo' => 'Descripción', 'clase' => 'hidden md:table-cell'],
            ['campo' => 'Abreviado', 'titulo' => 'Abreviado', 'clase' => 'hidden sm:table-cell'],
            ['campo' => 'Seccion', 'titulo' => 'Sección', 'clase' => 'hidden lg:table-cell'],
        ];
    }

    public function updatedTipoFallaFiltro(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedDepartamentoFiltro(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->tipoFallaFiltro = '';
        $this->departamentoFiltro = '';
        $this->buscar = '';
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function abrirAlta(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->resetValidation();
        $this->form = array_fill_keys(array_keys($this->form), '');
        $this->editando = '';
    }

    public function abrirEdicion(?string $id = null): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $id ??= $this->seleccionado;
        if ($id === null) {
            return;
        }

        $falla = CatParosFallas::findOrFail($id);

        $this->resetValidation();
        $this->form = [
            'TipoFallaId' => (string) $falla->TipoFallaId,
            'Departamento' => (string) $falla->Departamento,
            'Falla' => (string) $falla->Falla,
            'Descripcion' => (string) ($falla->Descripcion ?? ''),
            'Abreviado' => (string) ($falla->Abreviado ?? ''),
            'Seccion' => (string) ($falla->Seccion ?? ''),
        ];
        $this->seleccionado = (string) $id;
        $this->editando = (string) $id;
    }

    public function cerrar(): void
    {
        $this->editando = null;
        $this->confirmandoBorrado = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        $datos = $this->validate([
            'form.TipoFallaId' => ['required', 'string', 'max:50'],
            'form.Departamento' => ['required', 'string', 'max:50'],
            'form.Falla' => ['required', 'string', 'max:100'],
            'form.Descripcion' => ['nullable', 'string', 'max:255'],
            'form.Abreviado' => ['nullable', 'string', 'max:50'],
            'form.Seccion' => ['nullable', 'string', 'max:50'],
        ], attributes: [
            'form.TipoFallaId' => 'tipo de falla',
            'form.Departamento' => 'departamento',
            'form.Falla' => 'falla',
            'form.Descripcion' => 'descripción',
            'form.Abreviado' => 'abreviado',
            'form.Seccion' => 'sección',
        ])['form'];

        if ($esAlta) {
            $falla = CatParosFallas::create($datos);
            $this->seleccionado = (string) $falla->getKey();
        } else {
            CatParosFallas::findOrFail($this->editando)->update($datos);
        }

        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Falla creada.' : 'Falla actualizada.');
    }

    public function confirmarBorrado(): void
    {
        abort_unless(userCan('eliminar', self::MODULO), 403);

        if ($this->seleccionado !== null) {
            $this->confirmandoBorrado = true;
        }
    }

    public function eliminar(): void
    {
        abort_unless(userCan('eliminar', self::MODULO), 403);

        if ($this->seleccionado !== null) {
            CatParosFallas::findOrFail($this->seleccionado)->delete();
            $this->seleccionado = null;
            $this->dispatch('aviso', tipo: 'success', texto: 'Falla eliminada.');
        }

        $this->confirmandoBorrado = false;
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla(
            CatParosFallas::query()
                ->when($this->tipoFallaFiltro !== '', fn ($q) => $q->where('TipoFallaId', $this->tipoFallaFiltro))
                ->when($this->departamentoFiltro !== '', fn ($q) => $q->where('Departamento', $this->departamentoFiltro)),
            ['Falla', 'Descripcion', 'Abreviado', 'TipoFallaId', 'Departamento'],
        );

        // Sin orden elegido, el catálogo se lee por tipo → departamento → falla.
        if ($this->ordenPor === '') {
            $filas->orderBy('TipoFallaId')->orderBy('Departamento')->orderBy('Falla');
        }

        return view('livewire.mantenimiento.catalogo-fallas', [
            'filas' => $this->paginar($filas),
            'tiposFalla' => CatTipoFalla::query()->orderBy('TipoFallaId')->pluck('TipoFallaId'),
            'departamentos' => CatParosFallas::query()
                ->whereNotNull('Departamento')
                ->distinct()
                ->orderBy('Departamento')
                ->pluck('Departamento'),
            'puedeCrear' => userCan('crear', self::MODULO),
            'puedeModificar' => userCan('modificar', self::MODULO),
            'puedeEliminar' => userCan('eliminar', self::MODULO),
        ]);
    }
}
