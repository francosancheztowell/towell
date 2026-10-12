<?php

namespace App\Repositories;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Collection;

class UsuarioRepository
{
    /**
     * Obtener todos los usuarios sin paginación (para lista/select)
     */
    public function getAllForSelect(array $filtros = []): Collection
    {
        $query = Usuario::select([
            'idusuario', 'numero_empleado', 'nombre', 'area',
            'turno', 'telefono', 'foto', 'puesto', 'correo', 'enviarMensaje',
        ]);

        if (! empty($filtros['numero_empleado'])) {
            $query->where('numero_empleado', 'like', '%'.$filtros['numero_empleado'].'%');
        }
        if (! empty($filtros['area'])) {
            $query->where('area', $filtros['area']);
        }
        if (! empty($filtros['turno'])) {
            $query->where('turno', $filtros['turno']);
        }

        return $query->orderBy('idusuario', 'asc')->get();
    }

    /**
     * Obtener usuario por ID
     */
    public function findById(int $id): ?Usuario
    {
        return Usuario::find($id);
    }

    /**
     * Crear usuario
     */
    public function create(array $data): Usuario
    {
        return Usuario::create($data);
    }

    /**
     * Actualizar usuario
     */
    public function update(int $id, array $data): bool
    {
        $usuario = $this->findById($id);

        if (! $usuario) {
            return false;
        }

        return $usuario->update($data);
    }

    /**
     * Eliminar usuario
     */
    public function delete(int $id): bool
    {
        $usuario = $this->findById($id);

        if (! $usuario) {
            return false;
        }

        return $usuario->delete();
    }
}
