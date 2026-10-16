<?php

declare(strict_types=1);

namespace App\Livewire\Configuracion;

use App\Models\Sistema\SysDepartamentos;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Services\ModuloService;
use App\Services\UsuarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Edición de un usuario y sus permisos por módulo (antes form_usuario.blade.php + 300 líneas
 * de JS inline). Las reglas de los permisos viven aquí:
 *  - sin acceso no hay crear/modificar/eliminar/registrar; marcar cualquiera de ellos da acceso;
 *  - un módulo de Nivel 1 arrastra el mismo cambio a todos sus descendientes (Nivel 2 y 3).
 *
 * @property-read Collection<int, SYSRoles> $modulos
 * @property-read array<int, list<int>> $descendientes
 */
class EditarUsuario extends Component
{
    use WithFileUploads;

    public const CAMPOS = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];

    private const MODULO_USUARIOS = 59;

    #[Locked]
    public int $usuarioId;

    /** @var array<string, string> */
    public array $form = [];

    public $foto = null;

    /** @var array<int, array<string, bool>> idrol => [campo => bool] */
    public array $permisos = [];

    public function mount(int $usuarioId): void
    {
        abort_unless(userCan('modificar', self::MODULO_USUARIOS), 403);

        $usuario = Usuario::findOrFail($usuarioId);
        $this->usuarioId = $usuarioId;
        $this->form = [
            'area' => (string) $usuario->area,
            'numero_empleado' => (string) $usuario->numero_empleado,
            'nombre' => (string) $usuario->nombre,
            'puesto' => (string) $usuario->puesto,
            'turno' => (string) $usuario->turno,
            'telefono' => (string) $usuario->telefono,
            'correo' => (string) $usuario->correo,
            'contrasenia' => '',
        ];

        $guardados = SYSUsuariosRoles::porUsuario($usuarioId)->get()->keyBy('idrol');
        foreach ($this->modulos as $m) {
            $fila = $guardados->get($m->idrol);
            $acceso = (bool) ($fila->acceso ?? false);
            foreach (self::CAMPOS as $campo) {
                // Lo guardado sin acceso no vale: se muestra como lo que realmente aplica.
                $this->permisos[$m->idrol][$campo] = $acceso && (bool) ($fila->{$campo} ?? false);
            }
        }
    }

    /**
     * Módulos en orden de árbol: cada padre seguido de sus hijos. Ordenar solo por `orden`
     * (texto) mete "Trazabilidad" (1000) entre "Planeación" (100) y sus submódulos (101…).
     *
     * @return Collection<int, SYSRoles>
     */
    #[Computed]
    public function modulos(): Collection
    {
        $todos = app(ModuloService::class)->getAllModulos();
        $porPadre = $todos->sortBy('orden', SORT_NATURAL)->groupBy(fn ($m) => (string) $m->Dependencia);
        $arbol = [];
        $agregar = function (string $padre) use (&$agregar, &$arbol, $porPadre): void {
            foreach ($porPadre->get($padre, []) as $m) {
                if (! isset($arbol[$m->idrol])) { // un ciclo en Dependencia no cuelga la pantalla
                    $arbol[$m->idrol] = $m;
                    $agregar((string) $m->orden);
                }
            }
        };
        $agregar('');

        // Huérfanos (Dependencia que no existe): al final, para no perder su permiso al guardar.
        return collect(array_values($arbol))->concat($todos->whereNotIn('idrol', array_keys($arbol))->values());
    }

    /** @return array<int, list<int>> idrol de Nivel 1 => idrol de sus descendientes */
    #[Computed]
    public function descendientes(): array
    {
        $porOrden = $this->modulos->keyBy('orden');
        $raizDe = function (SYSRoles $m) use ($porOrden): ?SYSRoles {
            while ($m && (int) $m->Nivel > 1) {
                $m = $porOrden->get($m->Dependencia);
            }

            return $m;
        };

        $mapa = [];
        foreach ($this->modulos as $m) {
            if ((int) $m->Nivel > 1 && ($raiz = $raizDe($m)) && (int) $raiz->Nivel === 1) {
                $mapa[$raiz->idrol][] = $m->idrol;
            }
        }

        return $mapa;
    }

    public function alternar(int $idrol, string $campo): void
    {
        abort_unless(in_array($campo, self::CAMPOS, true) && isset($this->permisos[$idrol]), 422);

        $valor = ! $this->permisos[$idrol][$campo];
        foreach ([$idrol, ...($this->descendientes[$idrol] ?? [])] as $id) {
            $this->fijar($id, $campo, $valor);
        }
    }

    /** Encabezado de columna: marca la columna entera, o la desmarca si ya estaba toda marcada. */
    public function columna(string $campo): void
    {
        abort_unless(in_array($campo, self::CAMPOS, true), 422);

        $valor = ! $this->columnaCompleta($campo);
        foreach (array_keys($this->permisos) as $id) {
            $this->fijar($id, $campo, $valor);
        }
    }

    public function todos(bool $valor): void
    {
        foreach ($this->permisos as $id => $fila) {
            $this->permisos[$id] = array_fill_keys(self::CAMPOS, $valor);
        }
    }

    public function columnaCompleta(string $campo): bool
    {
        return $this->permisos !== [] && ! in_array(false, array_column($this->permisos, $campo), true);
    }

    public function guardar(UsuarioService $usuarios, ModuloService $moduloService)
    {
        abort_unless(userCan('modificar', self::MODULO_USUARIOS), 403);

        $datos = $this->validate([
            'form.area' => 'nullable|string|max:100',
            'form.nombre' => 'required|string|max:255',
            'form.puesto' => 'nullable|string|max:100',
            'form.turno' => ['nullable', Rule::in(['1', '2', '3', '4'])],
            'form.telefono' => 'nullable|string|max:12',
            'form.correo' => 'nullable|email|max:255',
            'form.contrasenia' => 'nullable|string|min:4',
            'foto' => 'nullable|image|max:10240',
        ], [], [
            'form.nombre' => 'nombre', 'form.telefono' => 'teléfono', 'form.correo' => 'correo',
            'form.contrasenia' => 'contraseña', 'foto' => 'foto',
        ])['form'];

        // Livewire no pasa por ConvertEmptyStringsToNull. numero_empleado no va en las reglas:
        // no se edita aquí (es la llave con la que inicia sesión).
        $datos = array_map(fn ($v) => $v === '' ? null : $v, $datos);

        // Formato que espera PermissionService::guardarPermisos(): modulo_{idrol}_{campo} presente = 1.
        $marcados = [];
        foreach ($this->permisos as $idrol => $fila) {
            foreach (array_keys(array_filter($fila)) as $campo) {
                $marcados["modulo_{$idrol}_{$campo}"] = 1;
            }
        }

        $usuarios->update($this->usuarioId, $datos, $this->foto, $marcados);
        $moduloService->limpiarCacheUsuario($this->usuarioId);

        session()->flash('success', "Usuario #{$this->form['numero_empleado']} actualizado correctamente.");

        return $this->redirectRoute('configuracion.usuarios.select');
    }

    public function render(): View
    {
        return view('livewire.configuracion.editar-usuario', [
            'departamentos' => SysDepartamentos::orderBy('Depto')->pluck('Depto'),
        ]);
    }

    private function fijar(int $idrol, string $campo, bool $valor): void
    {
        if (! isset($this->permisos[$idrol])) {
            return;
        }
        $this->permisos[$idrol][$campo] = $valor;

        if ($campo === 'acceso' && ! $valor) {
            $this->permisos[$idrol] = array_fill_keys(self::CAMPOS, false);
        } elseif ($campo !== 'acceso' && $valor) {
            $this->permisos[$idrol]['acceso'] = true;
        }
    }
}
