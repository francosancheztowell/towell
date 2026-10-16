<?php

namespace App\Services;

use App\Models\Sistema\SYSDepartamentoAsignacion;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Repositories\UsuarioRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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

    /**
     * Departamentos cuyos telares se asignan uno por uno en TelTelaresOperador (lo leen también
     * Mecánicos, BPM y Desarrolladores). El resto se asigna solo por departamento.
     * Itema y Smith son un mismo salón en TelTelaresOperador ("Smith"/"SMIT"): ver salonDe().
     */
    public const DEPARTAMENTOS_CON_TELARES = ['Jacquard', 'Itema', 'Smith', 'Karl Mayer'];

    /**
     * Telares (MaquinaId) de DEPARTAMENTOS_CON_TELARES que el empleado tiene en TelTelaresOperador.
     * Por catálogo y no por SalonTejidoId: el salón "Smith" junta los telares de Itema y de Smith.
     *
     * @return list<string>
     */
    public static function telaresDe(?string $numeroEmpleado): array
    {
        if (trim((string) $numeroEmpleado) === '') {
            return [];
        }

        return TelTelaresOperador::where('numero_empleado', $numeroEmpleado)
            ->whereIn('NoTelarId', self::telaresDelCatalogo()->keys())
            ->pluck('NoTelarId')
            ->map(fn ($t) => trim((string) $t))
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<string, string> MaquinaId => Departamento de DEPARTAMENTOS_CON_TELARES */
    public static function telaresDelCatalogo(): Collection
    {
        return URDCatalogoMaquina::whereIn('Departamento', self::DEPARTAMENTOS_CON_TELARES)
            ->pluck('Departamento', 'MaquinaId')
            ->mapWithKeys(fn ($depto, $id) => [trim((string) $id) => (string) $depto]);
    }

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

            $deptos = SYSDepartamentoAsignacion::where('idusuario', $origen->idusuario)->get();
            $supervisor = $deptos->contains('Supervisor', true);
            $this->guardarDepartamentos($usuario->idusuario, $deptos->pluck('Departamento')->all(), $supervisor);
            $this->guardarTelares($usuario, self::telaresDe($origen->numero_empleado), $supervisor);

            return $usuario;
        });
    }

    /**
     * Actualizar usuario, permisos y departamentos en una transacción. Solo se tocan las columnas
     * que llegan en $data: teléfono, correo y foto ya no están en el formulario y se conservan.
     *
     * @param  list<string>|null  $departamentos  de URDCatalogoMaquinas; null = no tocar la asignación
     * @param  list<string>|null  $telares  MaquinaId de Jacquard/Itema/Smith/Karl Mayer; null = no tocar TelTelaresOperador
     */
    public function update(int $id, array $data, ?UploadedFile $foto = null, array $permisos = [], ?array $departamentos = null, bool $supervisor = false, ?array $telares = null): bool
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

        return DB::connection('sqlsrv')->transaction(function () use ($id, $data, $permisos, $departamentos, $supervisor, $telares) {
            $actualizado = $this->usuarioRepository->update($id, $data);

            // Guardar permisos desde el formulario (aunque estén en blanco)
            $this->permissionService->guardarPermisos($permisos, $id);

            if ($departamentos !== null) {
                $this->guardarDepartamentos($id, $departamentos, $supervisor);
            }

            if ($telares !== null && ($usuario = $this->usuarioRepository->findById($id))) {
                $this->guardarTelares($usuario, $telares, $supervisor);
            }

            return $actualizado;
        });
    }

    /**
     * Reemplaza los telares de Jacquard/Itema/Smith/Karl Mayer del empleado en TelTelaresOperador
     * con su nombre y turno actuales. Solo borra filas de telares de esos departamentos según el
     * catálogo: las de telares que no están en él (p. ej. el 212 de Jacquard) se conservan.
     *
     * @param  list<string>  $telares
     */
    public function guardarTelares(Usuario $usuario, array $telares, bool $supervisor): void
    {
        $numero = (string) $usuario->numero_empleado;
        if (trim($numero) === '') {
            return;
        }

        $catalogo = self::telaresDelCatalogo();
        TelTelaresOperador::where('numero_empleado', $numero)->whereIn('NoTelarId', $catalogo->keys())->delete();

        foreach (array_unique($telares) as $telar) {
            if (! $catalogo->has($telar)) {
                continue;
            }
            TelTelaresOperador::create([
                'numero_empleado' => $numero,
                'nombreEmpl' => $usuario->nombre,
                'NoTelarId' => $telar,
                'Turno' => $usuario->turno,
                'SalonTejidoId' => URDCatalogoMaquina::salonDe($catalogo[$telar]),
                'Supervisor' => (int) $supervisor,
            ]);
        }
    }

    /**
     * Reemplaza los departamentos del usuario. Supervisor es uno solo para toda la asignación.
     *
     * @param  list<string>  $departamentos
     */
    public function guardarDepartamentos(int $idusuario, array $departamentos, bool $supervisor): void
    {
        SYSDepartamentoAsignacion::where('idusuario', $idusuario)->delete();

        foreach (array_unique($departamentos) as $departamento) {
            SYSDepartamentoAsignacion::create(['idusuario' => $idusuario, 'Departamento' => $departamento, 'Supervisor' => $supervisor]);
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
