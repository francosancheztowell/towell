<?php

declare(strict_types=1);

namespace App\Livewire\Configuracion;

use App\Models\Sistema\SYSDepartamentoAsignacion;
use App\Models\Sistema\SysDepartamentos;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\ModuloService;
use App\Services\UsuarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Edición de un usuario, sus permisos por módulo y sus máquinas (antes form_usuario.blade.php
 * + 300 líneas de JS inline). Las reglas de los permisos viven aquí:
 *  - sin acceso no hay crear/modificar/eliminar/registrar; marcar cualquiera de ellos da acceso;
 *  - un módulo de Nivel 1 arrastra el mismo cambio a todos sus descendientes (Nivel 2 y 3).
 * Departamentos (SYSDepartamentosAsignacion): en paros ve todas sus máquinas; sin ninguno no reporta.
 * Teléfono, correo y foto ya no se editan aquí (decisión del owner, 16-oct) y se conservan.
 *
 * @property-read Collection<int, SYSRoles> $modulos
 * @property-read array<int, list<int>> $descendientes
 * @property-read Collection<array-key, EloquentCollection<int, URDCatalogoMaquina>> $maquinasPorDepto
 */
class EditarUsuario extends Component
{
    public const CAMPOS = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];

    private const MODULO_USUARIOS = 59;

    #[Locked]
    public int $usuarioId;

    /** @var array<string, string> */
    public array $form = [];

    /** @var array<int, array<string, bool>> idrol => [campo => bool] */
    public array $permisos = [];

    /** @var list<string> Departamentos de URDCatalogoMaquinas asignados */
    public array $departamentos = [];

    /** @var list<string> Telares (MaquinaId) de Jacquard/Itema/Smith/Karl Mayer: van a TelTelaresOperador */
    public array $telares = [];

    public bool $supervisor = false;

    /** Hay cambios sin guardar (la insignia del navbar). */
    public bool $sinGuardar = false;

    public function mount(int $usuarioId): void
    {
        abort_unless(userCan('modificar', self::MODULO_USUARIOS), 403);

        $usuario = Usuario::findOrFail($usuarioId);
        $this->usuarioId = $usuarioId;
        $this->form = [
            'area' => (string) $usuario->area,
            'numero_empleado' => (string) $usuario->numero_empleado,
            'nombre' => (string) $usuario->nombre,
            'puesto' => (string) UsuarioService::puestoDeLista($usuario->puesto),
            'turno' => (string) $usuario->turno,
            'contrasenia' => '',
        ];
        $this->cargarAccesosDe($usuarioId);
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

    /** @return Collection<array-key, EloquentCollection<int, URDCatalogoMaquina>> */
    #[Computed]
    public function maquinasPorDepto(): Collection
    {
        // Por número de telar en orden natural (201, 202… y no por Secuencia de captura).
        return URDCatalogoMaquina::orderBy('Departamento')->get(['MaquinaId', 'Nombre', 'Departamento'])
            ->groupBy('Departamento')
            ->map(fn ($lista) => $lista->sortBy('MaquinaId', SORT_NATURAL)->values());
    }

    /** "Copiar permisos de…": trae permisos, máquinas y Supervisor de otro usuario (se guardan con Guardar). */
    public function copiarDe(int $idusuario): void
    {
        $origen = Usuario::findOrFail($idusuario, ['idusuario', 'nombre']);
        $this->cargarAccesosDe($idusuario);
        $this->sinGuardar = true;
        $this->dispatch('aviso', tipo: 'info', texto: "Se copiaron los permisos y departamentos de {$origen->nombre}. Guarda para aplicarlos.");
    }

    /** Cualquier wire:model (campos, máquinas, Supervisor, casillas de permisos) deja cambios pendientes. */
    public function updated(): void
    {
        $this->sinGuardar = true;
    }

    /** Marcar un telar marca su departamento (sin él no reportaría paros de ese salón). */
    public function updatedTelares(): void
    {
        $catalogo = UsuarioService::telaresDelCatalogo();
        $deptos = array_map(fn ($t) => $catalogo[$t] ?? null, $this->telares);
        $this->departamentos = array_values(array_unique([...$this->departamentos, ...array_filter($deptos)]));
    }

    public function alternar(int $idrol, string $campo): void
    {
        abort_unless(in_array($campo, self::CAMPOS, true) && isset($this->permisos[$idrol]), 422);

        $valor = ! $this->permisos[$idrol][$campo];
        foreach ([$idrol, ...($this->descendientes[$idrol] ?? [])] as $id) {
            $this->fijar($id, $campo, $valor);
        }
    }

    /**
     * Casilla con wire:model (flux:checkbox): Livewire ya puso el valor nuevo; se regresa y se
     * aplica por alternar() para que cuente la regla de acceso y la cascada del Nivel 1.
     */
    public function updatedPermisos(mixed $valor, string $clave): void
    {
        [$idrol, $campo] = explode('.', $clave) + [1 => ''];
        abort_unless(in_array($campo, self::CAMPOS, true), 422);

        $this->permisos[(int) $idrol][$campo] = ! $valor;
        $this->alternar((int) $idrol, $campo);
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
        $this->sinGuardar = true;
    }

    public function columnaCompleta(string $campo): bool
    {
        return $this->permisos !== [] && ! in_array(false, array_column($this->permisos, $campo), true);
    }

    public function guardar(UsuarioService $usuarios, ModuloService $moduloService)
    {
        abort_unless(userCan('modificar', self::MODULO_USUARIOS), 403);

        $puestoGuardado = (string) Usuario::whereKey($this->usuarioId)->value('puesto');
        $this->withValidator(fn ($v) => $v->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                $this->dispatch('aviso', tipo: 'error', texto: 'Revisa los campos marcados en rojo.');
            }
        }));
        $datos = $this->validate([
            'form.area' => 'nullable|string|max:100',
            'form.nombre' => 'required|string|max:255',
            // El puesto guardado que no está en la lista (p. ej. "Desrrollador") se acepta tal cual.
            'form.puesto' => ['nullable', Rule::in([...UsuarioService::PUESTOS, $puestoGuardado])],
            'form.turno' => ['nullable', Rule::in(['1', '2', '3', '4'])],
            'form.contrasenia' => 'nullable|string|min:4',
            'departamentos.*' => [Rule::in($this->maquinasPorDepto->keys()->map(fn ($d) => (string) $d)->all())],
            'telares.*' => [Rule::in(UsuarioService::telaresDelCatalogo()->keys()->all())],
        ], [], ['form.nombre' => 'nombre', 'form.contrasenia' => 'contraseña', 'departamentos.*' => 'departamento', 'telares.*' => 'telar'])['form'];

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

        // Telares solo de los departamentos que siguen marcados: quitar Jacquard quita sus telares.
        $catalogo = UsuarioService::telaresDelCatalogo();
        $telares = array_values(array_filter($this->telares, fn ($t) => in_array($catalogo[$t] ?? null, $this->departamentos, true)));

        $usuarios->update($this->usuarioId, $datos, null, $marcados, $this->departamentos, $this->supervisor, $telares);
        $moduloService->limpiarCacheUsuario($this->usuarioId);

        session()->flash('success', "Usuario #{$this->form['numero_empleado']} actualizado correctamente.");

        return $this->redirectRoute('configuracion.usuarios.select');
    }

    public function render(): View
    {
        return view('livewire.configuracion.editar-usuario', [
            'areas' => SysDepartamentos::orderBy('Depto')->pluck('Depto'),
            'puestos' => array_unique(array_filter([...UsuarioService::PUESTOS, $this->form['puesto']])),
            'otrosUsuarios' => Usuario::whereKeyNot($this->usuarioId)->orderBy('nombre')->get(['idusuario', 'nombre', 'numero_empleado']),
        ]);
    }

    private function cargarAccesosDe(int $idusuario): void
    {
        $guardados = SYSUsuariosRoles::porUsuario($idusuario)->get()->keyBy('idrol');
        foreach ($this->modulos as $m) {
            $fila = $guardados->get($m->idrol);
            $acceso = (bool) ($fila->acceso ?? false);
            foreach (self::CAMPOS as $campo) {
                // Lo guardado sin acceso no vale: se muestra como lo que realmente aplica.
                $this->permisos[$m->idrol][$campo] = $acceso && (bool) ($fila->{$campo} ?? false);
            }
        }

        $asignados = SYSDepartamentoAsignacion::where('idusuario', $idusuario)->get();
        $this->departamentos = $asignados->map(fn ($a) => (string) $a->Departamento)->values()->all();
        $this->supervisor = $asignados->contains('Supervisor', true);
        $this->telares = UsuarioService::telaresDe(Usuario::whereKey($idusuario)->value('numero_empleado'));
    }

    private function fijar(int $idrol, string $campo, bool $valor): void
    {
        if (! isset($this->permisos[$idrol])) {
            return;
        }
        $this->permisos[$idrol][$campo] = $valor;
        $this->sinGuardar = true;

        if ($campo === 'acceso' && ! $valor) {
            $this->permisos[$idrol] = array_fill_keys(self::CAMPOS, false);
        } elseif ($campo !== 'acceso' && $valor) {
            $this->permisos[$idrol]['acceso'] = true;
        }
    }
}
