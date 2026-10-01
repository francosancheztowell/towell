<?php

declare(strict_types=1);

namespace App\Livewire\Mantenimiento;

use App\Livewire\Concerns\ConTabla;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catálogo de operadores de mantenimiento (los que aparecen en "Atendió" al
 * finalizar un paro). Reemplaza la vista Blade con SweetAlert y sus rutas
 * POST/PUT/DELETE (19-08): un solo camino de escritura.
 */
class CatalogoOperadores extends Component
{
    use ConTabla;

    /** idrol de "Mantenimiento": el mismo que exigían las rutas module.permission:*,53. */
    private const MODULO = 53;

    /** Turno 4 = comodín que cubre descansos. */
    public const TURNOS = [1, 2, 3, 4];

    #[Url(except: '')]
    public string $turnoFiltro = '';

    #[Url(except: '')]
    public string $deptoFiltro = '';

    /** Id en edición; '' = alta nueva; null = modal cerrado. */
    public ?string $editando = null;

    public bool $confirmandoBorrado = false;

    /** @var array<string, string> */
    public array $form = [
        'CveEmpl' => '',
        'NomEmpl' => '',
        'Turno' => '',
        'Depto' => '',
        'Telefono' => '',
    ];

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a operadores de mantenimiento.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        return [
            ['campo' => 'CveEmpl', 'filtro' => true, 'titulo' => 'Clave'],
            ['campo' => 'NomEmpl', 'filtro' => true, 'titulo' => 'Nombre'],
            ['campo' => 'Turno', 'filtro' => true, 'titulo' => 'Turno'],
            ['campo' => 'Depto', 'filtro' => true, 'titulo' => 'Departamento', 'clase' => 'hidden sm:table-cell'],
            ['campo' => 'Telefono', 'filtro' => true, 'titulo' => 'Teléfono', 'clase' => 'hidden md:table-cell', 'valor' => fn ($fila) => $fila->Telefono ?: '-'],
        ];
    }

    public function updatedTurnoFiltro(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedDeptoFiltro(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->turnoFiltro = '';
        $this->deptoFiltro = '';
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

        $operador = ManOperadoresMantenimiento::findOrFail($id);

        $this->resetValidation();
        $this->form = [
            'CveEmpl' => (string) $operador->CveEmpl,
            'NomEmpl' => (string) $operador->NomEmpl,
            'Turno' => (string) $operador->Turno,
            'Depto' => (string) $operador->Depto,
            'Telefono' => (string) ($operador->Telefono ?? ''),
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

        // Mismas reglas que tenía el controller.
        $datos = $this->validate([
            'form.CveEmpl' => ['required', 'string', 'max:50'],
            'form.NomEmpl' => ['required', 'string', 'max:255'],
            'form.Turno' => ['required', 'integer', 'min:1', 'max:4'],
            'form.Depto' => ['required', 'string', 'max:100'],
            'form.Telefono' => ['nullable', 'string', 'max:50'],
        ], attributes: [
            'form.CveEmpl' => 'clave',
            'form.NomEmpl' => 'nombre',
            'form.Turno' => 'turno',
            'form.Depto' => 'departamento',
            'form.Telefono' => 'teléfono',
        ])['form'];

        $datos['Turno'] = (int) $datos['Turno'];
        $datos['Telefono'] = trim((string) ($datos['Telefono'] ?? '')) ?: null;

        if ($esAlta) {
            $operador = ManOperadoresMantenimiento::create($datos);
            $this->seleccionado = (string) $operador->getKey();
        } else {
            ManOperadoresMantenimiento::findOrFail($this->editando)->update($datos);
        }

        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Operador creado.' : 'Operador actualizado.');
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
            ManOperadoresMantenimiento::findOrFail($this->seleccionado)->delete();
            $this->seleccionado = null;
            $this->dispatch('aviso', tipo: 'success', texto: 'Operador eliminado.');
        }

        $this->confirmandoBorrado = false;
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla(
            ManOperadoresMantenimiento::query()
                ->when($this->turnoFiltro !== '', fn ($q) => $q->where('Turno', (int) $this->turnoFiltro))
                ->when($this->deptoFiltro !== '', fn ($q) => $q->where('Depto', $this->deptoFiltro)),
            ['CveEmpl', 'NomEmpl', 'Depto', 'Telefono'],
        );

        if ($this->ordenPor === '') {
            $filas->orderBy('NomEmpl');
        }

        return view('livewire.mantenimiento.catalogo-operadores', [
            'filas' => $this->paginar($filas),
            'departamentos' => ManOperadoresMantenimiento::query()
                ->whereNotNull('Depto')
                ->distinct()
                ->orderBy('Depto')
                ->pluck('Depto'),
        ]);
    }
}
