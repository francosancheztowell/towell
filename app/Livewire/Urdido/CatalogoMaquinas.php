<?php

declare(strict_types=1);

namespace App\Livewire\Urdido;

use App\Livewire\Concerns\ConCrud;
use App\Livewire\Concerns\ConTabla;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Catálogo de máquinas de urdido (URDCatalogoMaquinas): tabla + alta, edición y borrado. */
class CatalogoMaquinas extends Component
{
    use ConCrud;
    use ConTabla;

    public const MODULO = 156;

    public const MODELO = URDCatalogoMaquina::class;

    public const CAMPOS = ['MaquinaId', 'Nombre', 'Departamento', 'Codificacion'];

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso al catálogo de máquinas.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        return [
            ['campo' => 'MaquinaId', 'titulo' => 'Máquina ID', 'filtro' => true],
            ['campo' => 'Nombre', 'titulo' => 'Nombre', 'filtro' => true],
            ['campo' => 'Departamento', 'titulo' => 'Departamento', 'filtro' => true],
            ['campo' => 'Codificacion', 'titulo' => 'Codificación', 'filtro' => true, 'clase' => 'font-mono'],
        ];
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        // Livewire no pasa por TrimStrings: " MC 1 " sería otra llave.
        $this->form = array_map(fn ($v) => trim((string) $v), $this->form);

        $datos = $this->vaciosANull($this->validate([
            'form.MaquinaId' => ['required', 'string', 'max:50',
                Rule::unique('sqlsrv.URDCatalogoMaquinas', 'MaquinaId')->ignore($esAlta ? null : $this->editando, 'MaquinaId')],
            'form.Nombre' => ['nullable', 'string', 'max:100'],
            'form.Departamento' => ['nullable', 'string', 'max:50'],
            'form.Codificacion' => ['nullable', 'string', 'max:45'],
        ], attributes: [
            'form.MaquinaId' => 'máquina ID',
            'form.Nombre' => 'nombre',
            'form.Departamento' => 'departamento',
            'form.Codificacion' => 'codificación',
        ])['form']);

        if ($esAlta) {
            URDCatalogoMaquina::create($datos);
        } elseif ($datos['MaquinaId'] !== $this->editando) {
            // La llave es el MaquinaId: cambiarlo es borrar y crear (como antes), en una sola transacción.
            DB::connection('sqlsrv')->transaction(function () use ($datos) {
                $this->buscar((string) $this->editando)->delete();
                URDCatalogoMaquina::create($datos);
            });
        } else {
            $this->buscar((string) $this->editando)->update($datos);
        }

        $this->seleccionado = $datos['MaquinaId'];
        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Máquina agregada.' : 'Máquina actualizada.');
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla(URDCatalogoMaquina::query(), self::CAMPOS)
            ->when($this->ordenPor === '', fn ($q) => $q->orderBy('MaquinaId'));

        return view('livewire.urdido.catalogo-maquinas', [
            'filas' => $this->paginar($filas),
            'puede' => $this->permisos(),
        ]);
    }
}
