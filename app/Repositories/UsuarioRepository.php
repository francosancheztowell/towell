<?php

namespace App\Repositories;

use App\Models\Sistema\Usuario;
use Illuminate\Support\Collection;

class UsuarioRepository
{
    /**
     * Obtener todos los usuarios con paginación manual
     */
    public function getAll(int $page = 1, int $perPage = 50, array $filtros = []): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100); // Máximo 100 por página

        $query = Usuario::select([
            'idusuario', 'numero_empleado', 'nombre', 'area', 
            'turno', 'telefono', 'foto', 'puesto', 'correo', 'enviarMensaje'
        ]);

        // Aplicar filtros
        if (!empty($filtros['numero_empleado'])) {
            $query->where('numero_empleado', 'like', '%' . $filtros['numero_empleado'] . '%');
        }

        if (!empty($filtros['area'])) {
            $query->where('area', $filtros['area']);
        }

        if (!empty($filtros['turno'])) {
            $query->where('turno', $filtros['turno']);
        }

        $query->orderBy('idusuario', 'desc');

        $total = (clone $query)->count();
        $usuarios = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $usuarios,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Obtener todos los usuarios sin paginación (para lista/select)
     */
    public function getAllForSelect(array $filtros = []): Collection
    {
        $query = Usuario::select([
            'idusuario', 'numero_empleado', 'nombre', 'area',
            'turno', 'telefono', 'foto', 'puesto', 'correo', 'enviarMensaje'
        ]);

        if (!empty($filtros['numero_empleado'])) {
            $query->where('numero_empleado', 'like', '%' . $filtros['numero_empleado'] . '%');
        }
        if (!empty($filtros['area'])) {
            $query->where('area', $filtros['area']);
        }
        if (!empty($filtros['turno'])) {
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
        
        if (!$usuario) {
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
        
        if (!$usuario) {
            return false;
        }

        return $usuario->delete();
    }

    /**
     * Obtener usuarios por área
     */
    public function getByArea(string $area): Collection
    {
        return Usuario::porArea($area)->get();
    }
}


















