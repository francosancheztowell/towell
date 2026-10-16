<?php

namespace App\Services;

use App\Models\Sistema\SYSMaquinaAsignacion;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Repositories\UsuarioRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuarioService
{
    /**
     * Puestos que ofrece el formulario. Atadores, Marcas, Cortes, UrdEng y BPM comparan
     * strtolower(puesto) contra 'supervisor' / 'atador': no renombrar esos dos.
     */
    public const PUESTOS = [
        'Tejedor', 'Supervisor', 'Atador', 'Aux Atador', 'Engomado', 'Urdido', 'Mecánico',
        'Eléctrico', 'Tramero', 'Desarrollador', 'Calidad', 'Contador', 'Planeación', 'Auxiliar',
    ];

    /** El puesto de la lista que corresponde a uno guardado ("Mecanico", "AUX ATADOR"…); si no hay, el mismo. */
    public static function puestoDeLista(?string $puesto): ?string
    {
        $clave = fn (?string $p) => Str::lower(Str::ascii(trim((string) $p)));

        return collect(self::PUESTOS)->first(fn ($p) => $clave($p) === $clave($puesto)) ?? $puesto;
    }

    public function __construct(
        private UsuarioRepository $usuarioRepository,
        private PermissionService $permissionService
    ) {}

    /**
     * Crear usuario
     */
    public function create(array $data, ?UploadedFile $foto = null, array $permisos = []): Usuario
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

        // Siempre: el trigger tr_SYSUsuario_expand_roles inserta filas con valores por defecto,
        // y el alta arranca sin permisos (se copian después desde la edición).
        $this->permissionService->guardarPermisos($permisos, $usuario->idusuario);

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

            $maquinas = SYSMaquinaAsignacion::where('idusuario', $origen->idusuario)->get();
            $this->guardarMaquinas($usuario->idusuario, $maquinas->pluck('MaquinaId')->all(), (bool) $maquinas->first()?->Supervisor);

            return $usuario;
        });
    }

    /**
     * Actualizar usuario, permisos y máquinas en una transacción. Solo se tocan las columnas
     * que llegan en $data: teléfono, correo y foto ya no están en el formulario y se conservan.
     *
     * @param  list<string>|null  $maquinas  MaquinaId asignadas; null = no tocar las máquinas
     */
    public function update(int $id, array $data, ?UploadedFile $foto = null, array $permisos = [], ?array $maquinas = null, bool $supervisor = false): bool
    {
        if ($foto) {
            $data['foto'] = $this->guardarFoto($foto);
        }

        // Hashear contraseña solo si se proporciona
        if (! empty($data['contrasenia'])) {
            $data['contrasenia'] = Hash::make($data['contrasenia']);
        } else {
            unset($data['contrasenia']);
        }

        return DB::connection('sqlsrv')->transaction(function () use ($id, $data, $permisos, $maquinas, $supervisor) {
            $actualizado = $this->usuarioRepository->update($id, $data);

            // Guardar permisos desde el formulario (aunque estén en blanco)
            $this->permissionService->guardarPermisos($permisos, $id);

            if ($maquinas !== null) {
                $this->guardarMaquinas($id, $maquinas, $supervisor);
            }

            return $actualizado;
        });
    }

    /**
     * Reemplaza las máquinas del usuario. Supervisor es uno solo para toda la asignación.
     *
     * @param  list<string>  $maquinas
     */
    public function guardarMaquinas(int $idusuario, array $maquinas, bool $supervisor): void
    {
        SYSMaquinaAsignacion::where('idusuario', $idusuario)->delete();

        foreach (array_unique($maquinas) as $maquinaId) {
            SYSMaquinaAsignacion::create(['idusuario' => $idusuario, 'MaquinaId' => $maquinaId, 'Supervisor' => $supervisor]);
        }
    }

    /**
     * Eliminar usuario
     */
    public function delete(int $id): bool
    {
        SYSUsuariosRoles::porUsuario($id)->delete();

        return $this->usuarioRepository->delete($id);
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
