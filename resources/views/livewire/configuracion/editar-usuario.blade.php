@php
    // Casillas nativas en azul (la rejilla es grande para flux:checkbox, ver abajo).
    $casilla = 'size-4 cursor-pointer rounded accent-blue-700';
    $titulos = ['acceso' => 'Acceso', 'crear' => 'Crear', 'modificar' => 'Modificar', 'eliminar' => 'Eliminar', 'registrar' => 'Registrar'];
@endphp
{{-- Pantalla blanca de punta a punta: datos arriba, y Permisos | Máquinas ocupando el alto que sobra.
     --color-accent azul: Guardar, el switch y los demás Flux en la paleta azul / azul marino. --}}
<form id="usuario-form" wire:submit="guardar" class="flex min-h-0 flex-1 flex-col gap-4"
      style="--color-accent: var(--color-blue-700); --color-accent-content: var(--color-blue-700); --color-accent-foreground: var(--color-white);">
    {{-- Teléfono, correo y foto ya no se editan aquí; se conservan los guardados.
         Una sola línea desde 1280 px: datos, "Copiar de…" (trae permisos, departamentos y Supervisor de
         otro usuario; nada se guarda hasta Guardar) y las acciones. --}}
    <div class="flex shrink-0 flex-wrap items-end gap-3">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-[repeat(5,minmax(0,1fr))_minmax(0,1.6fr)]">
            <flux:select wire:model.live="form.area" label="Área">
                <option value="">—</option>
                @foreach ($areas as $area)
                    <option value="{{ $area }}">{{ $area }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.blur="form.nombre" label="Nombre" required />
            <flux:select wire:model.live="form.puesto" label="Puesto">
                <option value="">—</option>
                @foreach ($puestos as $puesto)
                    <option value="{{ $puesto }}">{{ $puesto }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="form.turno" label="Turno">
                <option value="1">Turno 1</option>
                <option value="2">Turno 2</option>
                <option value="3">Turno 3</option>
                <option value="4">Turno 4 (Cubre descansos)</option>
            </flux:select>
            <flux:input type="password" wire:model.blur="form.contrasenia" label="Contraseña nueva" viewable
                        autocomplete="new-password" placeholder="Sin cambio" />
            <flux:select wire:change="copiarDe($event.target.value)" label="Copiar permisos y departamentos de">
                <option value="">Elige un usuario…</option>
                @foreach ($otrosUsuarios as $u)
                    <option value="{{ $u->idusuario }}">{{ $u->nombre }} ({{ $u->numero_empleado }})</option>
                @endforeach
            </flux:select>
        </div>

        {{-- Guardar es submit del form: Flux pone su spinner mientras wire:submit trabaja. --}}
        <div class="flex items-center gap-2">
            @if ($sinGuardar)
                <flux:badge color="blue" size="sm" icon="pencil-square">Sin guardar</flux:badge>
            @endif
            <flux:button href="{{ route('configuracion.usuarios.select') }}" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
        </div>
    </div>

    @php
        // Solo azules (decisión del owner): los módulos principales alternan azul marino y azul
        // para distinguirse, y sus submódulos heredan la franja izquierda.
        $paletas = [
            ['franja' => 'border-l-blue-900', 'suave' => 'border-l-blue-200', 'fondo' => 'bg-blue-50', 'texto' => 'text-blue-950', 'chip' => 'bg-blue-900 text-white'],
            ['franja' => 'border-l-blue-500', 'suave' => 'border-l-sky-200', 'fondo' => 'bg-sky-50', 'texto' => 'text-blue-900', 'chip' => 'bg-blue-500 text-white'],
        ];
        // Encabezados de permiso en azul marino; las casillas, en azul.
        $columnas = array_fill_keys(array_keys($titulos), ['th' => 'bg-blue-900', 'casilla' => 'accent-blue-700']);
        // Grupos: cada módulo principal con sus descendientes (los módulos vienen en orden de árbol).
        $grupos = [];
        foreach ($this->modulos as $m) {
            if ((int) $m->Nivel === 1 || $grupos === []) {
                $grupos[] = ['raiz' => $m, 'hijos' => []];
            } else {
                $grupos[array_key_last($grupos)]['hijos'][] = $m;
            }
        }
        $puntosDepto = ['bg-blue-900', 'bg-blue-600', 'bg-sky-500', 'bg-blue-400'];
    @endphp

    <div class="flex min-h-0 flex-1 flex-col gap-4 xl:flex-row">
        {{-- Permisos: la tabla toma el alto que sobra y el scroll vive en ella. --}}
        <section class="tabla-pantalla min-h-72 xl:flex-1" aria-labelledby="permisos-titulo">
            <div class="mb-2 flex flex-wrap items-center gap-2" x-data>
                <flux:heading id="permisos-titulo" level="3">Permisos</flux:heading>
                <flux:icon.loading wire:loading wire:target="permisos,columna,todos,copiarDe" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="max-2xl:hidden">Un módulo principal aplica el cambio a sus submódulos.</flux:text>
                <flux:spacer />
                <flux:button size="sm" variant="ghost" icon="chevron-double-up" x-on:click="$dispatch('permisos-plegar', false)">Contraer</flux:button>
                <flux:button size="sm" variant="ghost" icon="chevron-double-down" x-on:click="$dispatch('permisos-plegar', true)">Expandir</flux:button>
                <flux:button size="sm" icon="check" wire:click="todos(true)">Todos</flux:button>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="todos(false)">Ninguno</flux:button>
            </div>
            {{-- Tabla y casillas nativas a propósito: 550 flux:checkbox + 660 celdas llevaban la
                 pantalla a 4 s por carga y 5 s por clic (medido). --}}
            <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-zinc-200 shadow-sm transition-opacity"
                 wire:loading.class="opacity-60" wire:target="permisos,columna,todos,copiarDe">
                <table class="w-full border-separate border-spacing-0 text-sm text-zinc-700">
                    <thead class="sticky top-0 z-10 text-white">
                        <tr>
                            <th class="bg-blue-950 px-3 py-2 text-left font-semibold">Módulo</th>
                            @foreach ($titulos as $campo => $titulo)
                                <th class="w-28 px-2 py-2 font-semibold {{ $columnas[$campo]['th'] }}">
                                    <label class="inline-flex cursor-pointer items-center gap-2">
                                        {{ $titulo }}
                                        <input type="checkbox" wire:click="columna('{{ $campo }}')" @checked($this->columnaCompleta($campo))
                                               class="size-4 cursor-pointer rounded accent-white" aria-label="Marcar toda la columna {{ $titulo }}">
                                    </label>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($grupos as $i => $grupo)
                        @php
                            $c = $paletas[$i % count($paletas)];
                            $raiz = $grupo['raiz'];
                            $conAcceso = collect($grupo['hijos'])->filter(fn ($h) => $permisos[$h->idrol]['acceso'] ?? false)->count();
                        @endphp
                        <tbody wire:key="g-{{ $raiz->idrol }}" x-data="{ abierto: false }" x-on:permisos-plegar.window="abierto = $event.detail">
                            <tr class="{{ $c['fondo'] }} {{ $c['texto'] }} font-semibold">
                                <td class="border-b border-l-4 border-white {{ $c['franja'] }} py-2 pl-2 pr-3">
                                    <button type="button" x-on:click="abierto = ! abierto" class="flex w-full items-center gap-2 text-left"
                                            x-bind:aria-expanded="abierto">
                                        <flux:icon.chevron-right variant="micro" class="shrink-0 transition-transform" x-bind:class="abierto && 'rotate-90'" />
                                        {{ $raiz->modulo }}
                                        @if ($grupo['hijos'] !== [])
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $c['chip'] }}">{{ $conAcceso }}/{{ count($grupo['hijos']) }}</span>
                                        @endif
                                    </button>
                                </td>
                                @foreach ($titulos as $campo => $titulo)
                                    <td class="border-b border-white py-2 text-center">
                                        <input type="checkbox" wire:model.live="permisos.{{ $raiz->idrol }}.{{ $campo }}"
                                               class="size-[1.125rem] cursor-pointer rounded transition-transform active:scale-90 {{ $columnas[$campo]['casilla'] }}"
                                               aria-label="{{ $titulo }} en {{ $raiz->modulo }}">
                                    </td>
                                @endforeach
                            </tr>
                            @foreach ($grupo['hijos'] as $m)
                                @php $nivel = (int) $m->Nivel; @endphp
                                <tr wire:key="m-{{ $m->idrol }}" x-show="abierto" class="transition-colors hover:bg-zinc-50">
                                    <td class="border-b border-l-4 border-zinc-100 {{ $c['suave'] }} py-1.5 pr-3" style="padding-left: {{ 0.75 + ($nivel - 1) * 1.25 }}rem">
                                        <span class="text-zinc-300">└</span> {{ $m->modulo }}
                                    </td>
                                    @foreach ($titulos as $campo => $titulo)
                                        <td class="border-b border-zinc-100 py-1.5 text-center">
                                            <input type="checkbox" wire:model.live="permisos.{{ $m->idrol }}.{{ $campo }}"
                                                   class="size-4 cursor-pointer rounded transition-transform active:scale-90 {{ $columnas[$campo]['casilla'] }}"
                                                   aria-label="{{ $titulo }} en {{ $m->modulo }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </section>

        {{-- Departamentos (SYSDepartamentosAsignacion): en paros ve todas sus máquinas, también las
             que se den de alta después. Jacquard, Itema, Smith y Karl Mayer además eligen telares, que van
             a TelTelaresOperador. Supervisor es uno para toda la asignación. --}}
        @php
            $conTelares = \App\Services\UsuarioService::DEPARTAMENTOS_CON_TELARES;
            $simples = $this->maquinasPorDepto->except($conTelares);
            $deTelares = $this->maquinasPorDepto->only($conTelares);
        @endphp
        <section class="flex min-h-72 flex-col xl:w-96" aria-labelledby="deptos-titulo">
            <div class="mb-2 flex items-center gap-2">
                <flux:heading id="deptos-titulo" level="3">Departamentos</flux:heading>
                <flux:badge size="sm" :color="$departamentos === [] ? 'red' : 'blue'">{{ count($departamentos) }}</flux:badge>
                <flux:icon.loading wire:loading wire:target="departamentos,telares,copiarDe" class="size-4 text-zinc-400" />
                <flux:spacer />
                <flux:switch wire:model.live="supervisor" label="Supervisor" align="left" />
            </div>
            @if ($departamentos === [])
                <flux:callout variant="warning" icon="exclamation-triangle" class="mb-2"
                              heading="Sin departamentos asignados este usuario no puede reportar paros." />
            @endif
            <div class="min-h-0 flex-1 space-y-3 overflow-auto rounded-xl border border-blue-200 bg-blue-100 p-2.5 transition-opacity"
                 wire:loading.class="opacity-60" wire:target="departamentos,telares,copiarDe">
                {{-- Telares: tarjeta con dropdown de telares (TelTelaresOperador). --}}
                @foreach ($deTelares as $depto => $lista)
                    @php
                        $elegidos = $lista->whereIn('MaquinaId', $telares)->count();
                        $marcado = in_array($depto, $departamentos, true);
                    @endphp
                    <div wire:key="t-{{ $depto }}" x-data="{ abierto: false }"
                         @class([
                             'rounded-xl border bg-white shadow-xs transition',
                             'border-blue-500 ring-1 ring-blue-200' => $marcado,
                             'border-zinc-200' => ! $marcado,
                         ])>
                        <div class="flex items-center gap-3 px-3 py-2.5">
                            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                <input type="checkbox" wire:model.live="departamentos" value="{{ $depto }}" class="{{ $casilla }}">
                                <span class="font-semibold text-blue-950">{{ $depto }}</span>
                            </label>
                            <button type="button" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto"
                                    class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition {{ $elegidos ? 'bg-blue-900 text-white' : 'bg-blue-100 text-blue-800 hover:bg-blue-200' }}">
                                {{ $elegidos }}/{{ $lista->count() }} telares
                                <flux:icon.chevron-down variant="micro" class="transition-transform" x-bind:class="abierto && 'rotate-180'" />
                            </button>
                        </div>
                        <div x-show="abierto" x-collapse class="border-t border-zinc-100 px-3 pb-3 pt-2">
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(3.75rem,1fr))] gap-1.5">
                                @foreach ($lista as $maq)
                                    <label wire:key="tl-{{ $maq->MaquinaId }}" title="{{ $maq->Nombre }}"
                                           class="flex cursor-pointer items-center justify-center rounded-md border border-zinc-200 py-1.5 text-sm font-medium text-zinc-600 transition hover:border-blue-400 hover:text-blue-800 has-checked:border-blue-700 has-checked:bg-blue-700 has-checked:text-white">
                                        <input type="checkbox" wire:model.live="telares" value="{{ $maq->MaquinaId }}" class="sr-only">
                                        {{ $maq->MaquinaId }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- El resto: solo el departamento (ve todas sus máquinas). --}}
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($simples as $depto => $lista)
                        <label wire:key="d-{{ $depto }}" title="{{ $lista->pluck('MaquinaId')->implode(', ') }}"
                               class="group flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-xs transition hover:border-blue-400 has-checked:border-blue-900 has-checked:bg-blue-900 has-checked:text-white">
                            <input type="checkbox" wire:model.live="departamentos" value="{{ $depto }}" class="sr-only">
                            <flux:icon.check variant="micro" class="hidden shrink-0 group-has-checked:block" />
                            <span class="truncate">{{ $depto }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <flux:error name="departamentos.*" />
            <flux:error name="telares.*" />
        </section>
    </div>
</form>
