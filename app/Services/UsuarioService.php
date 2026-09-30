<?php

namespace App\Services;

use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Repositories\UsuarioRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    public function create(array $data, ?UploadedFile $foto = null, array $permisos = []): \App\Models\Sistema\Usuario
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
        if (! empty($permisos)) {
            $this->permissionService->guardarPermisos($permisos, $usuario->idusuario);
        }

        return $usuario;
    }

    /**
     * Crear un usuario nuevo con los mismos permisos que $origen.
     *
     * Del origen se heredan área y puesto (mismo rol en planta); teléfono, correo y foto
     * son personales y no se copian. Usuario y permisos se crean en una sola transacción:
     * un usuario sin permisos a medias es peor que no crearlo.
     *
     * @param  array{numero_empleado: string, nombre: string, turno: string, contrasenia: string}  $data
     */
    public function duplicar(Usuario $origen, array $data): Usuario
    {
        return DB::connection('sqlsrv')->transaction(function () use ($origen, $data) {
            $usuario = $this->usuarioRepository->create([
                'numero_empleado' => $data['numero_empleado'],
                'nombre' => $data['nombre'],
                'turno' => $data['turno'],
                'contrasenia' => Hash::make($data['contrasenia']),
                'area' => $origen->area,
                'puesto' => $origen->puesto,
                'remember_token' => Str::random(60),
            ]);

            $ahora = now();
            $permisos = SYSUsuariosRoles::porUsuario($origen->idusuario)
                ->get(['idrol', 'acceso', 'crear', 'modificar', 'eliminar', 'registrar'])
                ->map(fn (SYSUsuariosRoles $p) => [
                    'idusuario' => $usuario->idusuario,
                    'idrol' => $p->idrol,
                    'acceso' => (int) $p->acceso,
                    'crear' => (int) $p->crear,
                    'modificar' => (int) $p->modificar,
                    'eliminar' => (int) $p->eliminar,
                    'registrar' => (int) $p->registrar,
                    'assigned_at' => $ahora,
                ]);

            // Por lotes: SQL Server admite 2100 parámetros por sentencia (8 columnas × 200 = 1600).
            foreach ($permisos->chunk(200) as $lote) {
                SYSUsuariosRoles::insert($lote->values()->all());
            }

            return $usuario;
        });
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
        if (isset($data['contrasenia']) && ! empty($data['contrasenia'])) {
            $data['contrasenia'] = Hash::make($data['contrasenia']);
        } else {
            unset($data['contrasenia']);
        }

        // Actualizar usuario
        $actualizado = $this->usuarioRepository->update($id, $data);

        // Guardar permisos desde el formulario (aunque estén en blanco)
        $this->permissionService->guardarPermisos($permisos, $id);

        if ($actualizado) {
        }

        return $actualizado;
    }

    /**
     * Eliminar usuario
     */
    public function delete(int $id): bool
    {
        // Eliminar permisos primero
        \App\Models\Sistema\SYSUsuariosRoles::porUsuario($id)->delete();

        // Eliminar usuario
        $eliminado = $this->usuarioRepository->delete($id);

        if ($eliminado) {
        }

        return $eliminado;
    }

    /**
     * Guardar foto de usuario
     */
    private function guardarFoto(UploadedFile $foto): string
    {
        $fileName = time().'_'.$foto->getClientOriginalName();
        $foto->move(public_path('images/fotos_usuarios'), $fileName);

        return $fileName;
    }
}
