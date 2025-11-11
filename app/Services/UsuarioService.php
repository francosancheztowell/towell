<?php

namespace App\Services;

use App\Repositories\UsuarioRepository;
use App\Services\PermissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsuarioService
{
    public function __construct(
        private UsuarioRepository $usuarioRepository,
        private PermissionService $permissionService
    ) {}

    /**
     * Crear usuario
     */
    public function create(array $data, ?UploadedFile $foto = null, array $permisos = []): \App\Models\Usuario
    {
        // Procesar foto
        if ($foto) {
            $data['foto'] = $this->guardarFoto($foto);
        }

        // Hashear contraseña si se proporciona
        if (isset($data['contrasenia'])) {
            $data['contrasenia'] = Hash::make($data['contrasenia']);
        }

        // Generar remember_token
        $data['remember_token'] = Str::random(60);

        // Crear usuario
        $usuario = $this->usuarioRepository->create($data);

        // Guardar permisos si se proporcionan
        if (!empty($permisos)) {
            $this->permissionService->guardarPermisos($permisos, $usuario->idusuario);
        }

        Log::info('Usuario creado exitosamente', ['usuario_id' => $usuario->idusuario]);

        return $usuario;
    }

    /**
     * Actualizar usuario
     */
    public function update(int $id, array $data, ?UploadedFile $foto = null, array $permisos = []): bool
    {
        // Procesar foto si se proporciona
        if ($foto) {
            $data['foto'] = $this->guardarFoto($foto);
        }

        // Hashear contraseña solo si se proporciona
        if (isset($data['contrasenia']) && !empty($data['contrasenia'])) {
            $data['contrasenia'] = Hash::make($data['contrasenia']);
        } else {
            unset($data['contrasenia']);
        }

        // Actualizar usuario
        $actualizado = $this->usuarioRepository->update($id, $data);

        // Guardar permisos si se proporcionan
        if (!empty($permisos)) {
            $this->permissionService->guardarPermisos($permisos, $id);
        }

        if ($actualizado) {
            Log::info('Usuario actualizado exitosamente', ['usuario_id' => $id]);
        }

        return $actualizado;
    }

    /**
     * Eliminar usuario
     */
    public function delete(int $id): bool
    {
        // Eliminar permisos primero
        \App\Models\SYSUsuariosRoles::porUsuario($id)->delete();

        // Eliminar usuario
        $eliminado = $this->usuarioRepository->delete($id);

        if ($eliminado) {
            Log::info('Usuario eliminado exitosamente', ['usuario_id' => $id]);
        }

        return $eliminado;
    }

    /**
     * Guardar foto de usuario
     */
    private function guardarFoto(UploadedFile $foto): string
    {
        $fileName = time() . '_' . $foto->getClientOriginalName();
        $foto->move(public_path('images/fotos_usuarios'), $fileName);
        return $fileName;
    }
}





