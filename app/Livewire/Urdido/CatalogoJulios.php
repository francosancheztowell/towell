<?php

declare(strict_types=1);

namespace App\Livewire\Urdido;

use App\Livewire\Concerns\ConCrud;
use App\Livewire\Concerns\ConTabla;
use App\Models\Urdido\UrdCatJulios;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Catálogo de julios (UrdCatJulios), una pantalla por departamento: Urdido (idrol 37) y
 * Engomado (idrol 171). Cada una solo ve y toca sus julios; el No. Julio es único en toda la tabla.
 */
class CatalogoJulios extends Component
{
    use ConCrud;
    use ConTabla;

    public const MODELO = UrdCatJulios::class;

    public const CAMPOS = ['NoJulio', 'Tara'];

    private const MODULOS = ['Urdido' => 37, 'Engomado' => 171];

    /** 'Urdido' o 'Engomado'; lo pone la ruta y el cliente no lo puede cambiar. */
    #[Locked]
    public string $departamento = 'Urdido';

    public function mount(string $departamento): void
    {
        abort_unless(isset(self::MODULOS[$departamento]), 404);
        $this->departamento = $departamento;
        abort_unless(userCan('acceso', $this->modulo()), 403, 'No tienes acceso al catálogo de julios.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        return [
            ['campo' => 'NoJulio', 'titulo' => 'No. Julio', 'filtro' => true],
            ['campo' => 'Tara', 'titulo' => 'Tara', 'alinear' => 'end', 'valor' => fn (UrdCatJulios $j) => number_format((float) $j->Tara, 2)],
        ];
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', $this->modulo()), 403);

        $this->form = array_map(fn ($v) => trim((string) $v), $this->form);

        $datos = $this->validate([
            'form.NoJulio' => ['required', 'string', 'max:50',
                Rule::unique('sqlsrv.UrdCatJulios', 'NoJulio')->ignore($esAlta ? null : $this->editando, 'Id')],
            'form.Tara' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['form.NoJulio' => 'No. Julio', 'form.Tara' => 'tara'])['form'];

        // Tara vacía = 0, como antes.
        $datos = ['NoJulio' => $datos['NoJulio'], 'Tara' => $datos['Tara'] === '' ? 0 : $datos['Tara'], 'Departamento' => $this->departamento];

        if ($esAlta) {
            $this->seleccionado = (string) UrdCatJulios::create($datos)->getKey();
        } else {
            $this->buscar((string) $this->editando)->update($datos);
        }

        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Julio agregado.' : 'Julio actualizado.');
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla($this->julios(), ['NoJulio'])
            ->when($this->ordenPor === '', fn ($q) => $q->orderBy('NoJulio'));

        return view('livewire.urdido.catalogo-julios', [
            'filas' => $this->paginar($filas),
            'puede' => $this->permisos(),
        ]);
    }

    protected function modulo(): int
    {
        return self::MODULOS[$this->departamento];
    }

    /** Solo los julios del departamento de la pantalla: un id ajeno da 404. */
    protected function buscar(string $id): Model
    {
        return $this->julios()->findOrFail($id);
    }

    /** @return Builder<UrdCatJulios> */
    private function julios(): Builder
    {
        return UrdCatJulios::query()->where('Departamento', $this->departamento)->whereNotNull('NoJulio');
    }
}
