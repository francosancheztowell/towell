<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Alta / edición / borrado de un catálogo simple sobre ConTabla. El componente define:
 *   const MODULO  idrol del permiso (userCan por idrol: los nombres de SYSRoles se repiten), o modulo()
 *   const MODELO  clase Eloquent
 *   const CAMPOS  campos del formulario
 * y su propio guardar() (validación) y render(). Vista: x-tabla-acciones-crud + <dialog> con $editando.
 */
trait ConCrud
{
    /** Llave en edición; '' = alta nueva; null = modal cerrado. */
    public ?string $editando = null;

    /** @var array<string, string> */
    public array $form = [];

    public function abrirAlta(): void
    {
        abort_unless(userCan('crear', $this->modulo()), 403);

        $this->resetValidation();
        $this->form = array_fill_keys(static::CAMPOS, '');
        $this->editando = '';
    }

    public function abrirEdicion(?string $id = null): void
    {
        abort_unless(userCan('modificar', $this->modulo()), 403);

        $id ??= $this->seleccionado;
        if ($id === null) {
            return;
        }

        $this->resetValidation();
        $this->form = array_map(fn ($v) => (string) $v, $this->buscar($id)->only(static::CAMPOS));
        $this->seleccionado = $this->editando = $id;
    }

    public function cerrar(): void
    {
        $this->editando = null;
        $this->resetValidation();
    }

    public function eliminar(): void
    {
        abort_unless(userCan('eliminar', $this->modulo()), 403);

        if ($this->seleccionado !== null) {
            $this->buscar($this->seleccionado)->delete();
            $this->seleccionado = null;
            $this->dispatch('aviso', tipo: 'success', texto: 'Registro eliminado.');
        }
    }

    /** @return array{crear: bool, modificar: bool, eliminar: bool} */
    protected function permisos(): array
    {
        return [
            'crear' => userCan('crear', $this->modulo()),
            'modificar' => userCan('modificar', $this->modulo()),
            'eliminar' => userCan('eliminar', $this->modulo()),
        ];
    }

    /** idrol del permiso; se sobrescribe si depende del componente (ej. Julios Urdido / Engomado). */
    protected function modulo(): int
    {
        return static::MODULO;
    }

    /** '' → NULL: las columnas aceptan nulos y un número vacío no es 0. */
    protected function vaciosANull(array $datos): array
    {
        return array_map(fn ($v) => $v === '' ? null : $v, $datos);
    }

    protected function buscar(string $id): Model
    {
        return (static::MODELO)::findOrFail($id);
    }
}
