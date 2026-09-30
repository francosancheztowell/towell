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

            // El trigger dbo.tr_SYSUsuario_expand_roles ya le insertó una fila por cada módulo
            // de SYSRoles con valores por defecto: insertar encima viola PK_SYSUsuariosRoles.
            // Se reemplazan por las del origen, igual que guardarPermisos() (borrar e insertar).
            SYSUsuariosRoles::porUsuario($usuario->idusuario)->delete();

            $columnas = ['idrol', 'acceso', 'crear', 'modificar', 'eliminar', 'registrar'];
            DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insertUsing(
                ['idusuario', ...$columnas, 'assigned_at'],
                DB::connection('sqlsrv')->table('SYSUsuariosRoles')
                    ->selectRaw('? as idusuario', [$usuario->idusuario])
                    ->addSelect($columnas)
                    ->selectRaw('? as assigned_at', [now()])
                    ->where('idusuario', $origen->idusuario)
            );

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
