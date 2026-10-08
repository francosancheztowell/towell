@extends('layouts.app')

@section('title', 'Gestión de Módulos')

@php
    // Árbol: cada módulo cuelga del orden de su padre. $modulos ya viene en orden natural.
    $hijos = $modulos->groupBy(fn ($m) => (string) $m->Dependencia);
    $raices = $hijos->get('', collect());
    $permisos = ['acceso' => 'Acceso', 'crear' => 'Crear', 'modificar' => 'Modificar', 'eliminar' => 'Eliminar', 'registrar' => 'Registrar'];
    $puedeCrear = userCan('crear', 101);
    $puedeModificar = userCan('modificar', 101);
    $puedeEliminar = userCan('eliminar', 101);
@endphp

@section('navbar-right')
    @if ($puedeCrear)
        <flux:button variant="primary" icon="plus" class="min-h-touch" data-abrir-dialogo="createModal">Nuevo módulo</flux:button>
    @endif
@endsection

@section('content')
    {{-- Éxito, error y validación los pinta x-ui.flash (layout). --}}
    <div id="gestion-modulos" class="flex h-full min-h-0 flex-col bg-white md:flex-row"
         data-url-permisos="{{ route('configuracion.utileria.modulos.permisos', '__ID__') }}"
         data-url-permisos-update="{{ route('configuracion.utileria.modulos.permisos.update', '__ID__') }}"
         data-url-update="{{ route('configuracion.utileria.modulos.update', '__ID__') }}"
         data-url-destroy="{{ route('configuracion.utileria.modulos.destroy', '__ID__') }}"
         data-url-sync="{{ route('configuracion.utileria.modulos.sincronizar.permisos', '__ID__') }}"
         data-puede-editar-permisos="{{ $puedeEditarPermisos ? '1' : '0' }}"
         data-total-usuarios="{{ $totalUsuarios }}">

        {{-- Árbol de módulos --}}
        <aside class="flex max-h-[42dvh] min-h-0 shrink-0 flex-col border-b border-slate-200 bg-slate-50 md:max-h-none md:w-80 md:border-b-0 md:border-r lg:w-96"
               aria-label="Módulos">
            <div class="flex items-center gap-2 border-b border-slate-200 p-3">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Buscar módulo</span>
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                    <input type="search" data-arbol-buscar placeholder="Buscar por nombre u orden"
                           class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-800 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </label>
                <button type="button" data-arbol-plegar-todo aria-pressed="false" title="Expandir todo"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-blue-500">
                    <i class="fa-solid fa-up-right-and-down-left-from-center text-xs" aria-hidden="true"></i>
                    <span class="sr-only">Expandir o contraer todo</span>
                </button>
            </div>

            <div class="flex items-center justify-between px-4 pb-1 pt-3 text-caption font-medium text-slate-500">
                <span>{{ $modulos->count() }} módulos</span>
                <span title="Usuarios con acceso"><i class="fa-solid fa-user text-[0.625rem]" aria-hidden="true"></i> con acceso</span>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-2 pb-4" aria-label="Árbol de módulos">
                @if ($raices->isEmpty())
                    <p class="px-3 py-8 text-center text-sm text-slate-500">No hay módulos registrados.</p>
                @else
                    <ul data-arbol class="space-y-px">
                        @foreach ($raices as $m)
                            @include('modulos.gestion-modulos._nodo', ['m' => $m])
                        @endforeach
                    </ul>
                @endif
                <p data-arbol-sin-resultados class="hidden px-3 py-8 text-center text-sm text-slate-500">Ningún módulo coincide con la búsqueda.</p>
            </nav>
        </aside>

        {{-- Detalle del módulo y permisos de los usuarios --}}
        <section class="relative flex min-h-0 min-w-0 flex-1 flex-col" aria-live="polite">
            <div data-panel-vacio class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-sitemap text-xl" aria-hidden="true"></i>
                </span>
                <h2 class="text-base font-semibold text-slate-900">Elige un módulo</h2>
                <p class="max-w-sm text-sm text-slate-600">
                    Verás quién tiene acceso y qué puede hacer cada usuario. Marca varios usuarios para quitarles o darles permisos de una sola vez.
                </p>
            </div>

            <div data-panel-detalle class="hidden min-h-0 flex-1 flex-col">
                <header class="border-b border-slate-200 px-4 pb-4 pt-4 md:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p data-det-padres class="truncate text-caption text-slate-500"></p>
                            <h2 data-det-nombre class="mt-0.5 truncate text-xl font-semibold text-slate-900"></h2>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                                <span class="font-mono tabular-nums" data-det-orden></span>
                                <span data-det-nivel class="rounded-full px-2 py-0.5 text-caption font-medium"></span>
                                <span data-det-ruta class="min-w-0 truncate font-mono text-caption"></span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if ($puedeModificar)
                                <flux:button size="sm" icon="pencil-square" data-accion="editar">Editar</flux:button>
                                <flux:button size="sm" icon="arrow-path" data-accion="sincronizar" title="Crea la fila de este módulo a los usuarios que no la tienen">Sincronizar</flux:button>
                            @endif
                            @if ($puedeEliminar)
                                <flux:button size="sm" variant="ghost" icon="trash" data-accion="eliminar" class="text-red-600! hover:bg-red-50!">Eliminar</flux:button>
                            @endif
                        </div>
                    </div>
                </header>

                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-4 py-3 md:px-6">
                    <label class="relative min-w-48 flex-1">
                        <span class="sr-only">Buscar usuario</span>
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                        <input type="search" data-filtro-texto placeholder="Buscar usuario o número de empleado"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-800 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </label>
                    <label class="shrink-0">
                        <span class="sr-only">Área</span>
                        <select data-filtro-area class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                            <option value="">Todas las áreas</option>
                        </select>
                    </label>
                    <fieldset class="flex shrink-0 rounded-lg border border-slate-200 bg-slate-100 p-0.5 text-sm">
                        <legend class="sr-only">Mostrar</legend>
                        @foreach (['todos' => 'Todos', 'con' => 'Con acceso', 'sin' => 'Sin acceso'] as $valor => $texto)
                            <label class="cursor-pointer">
                                <input type="radio" name="filtro-acceso" value="{{ $valor }}" class="peer sr-only" @checked($valor === 'con')>
                                <span class="block rounded-md px-3 py-1.5 font-medium text-slate-600 transition-colors peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm peer-focus-visible:outline-2 peer-focus-visible:outline-blue-500">
                                    {{ $texto }} <span class="tabular-nums text-slate-500" data-filtro-conteo="{{ $valor }}"></span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                </div>

                {{-- relative: ancla los sr-only (absolute) de cada fila; sin él estiran la sección y sale un segundo scroll. --}}
                <div class="relative min-h-0 flex-1 overflow-auto" data-usuarios-scroll>
                    <table class="w-full min-w-[44rem] border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-10 bg-white">
                            <tr class="text-left text-caption font-semibold text-slate-600">
                                <th scope="col" class="w-12 border-b border-slate-200 py-2 pl-4 md:pl-6">
                                    <label class="grid size-touch -m-3 place-items-center">
                                        <span class="sr-only">Seleccionar todos los usuarios visibles</span>
                                        <input type="checkbox" data-sel-todos class="size-4 rounded border-slate-300 text-blue-600 focus-visible:outline-2 focus-visible:outline-blue-500" @disabled(! $puedeEditarPermisos)>
                                    </label>
                                </th>
                                <th scope="col" class="border-b border-slate-200 py-2 pr-3">Usuario</th>
                                <th scope="col" class="border-b border-slate-200 py-2 pr-3">Área</th>
                                @foreach ($permisos as $campo => $texto)
                                    <th scope="col" data-col="{{ $campo }}" class="w-24 border-b border-slate-200 px-1 py-2 text-center">
                                        <span class="block">{{ $texto }}</span>
                                        <span class="block font-normal tabular-nums text-slate-500" data-col-conteo="{{ $campo }}">—</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody data-usuarios-cuerpo></tbody>
                    </table>
                </div>

                {{-- Moldes que clona index.ts: el texto entra con textContent, nunca como HTML. --}}
                <template data-tpl-fila>
                    <tr data-id class="transition-colors duration-150 hover:bg-slate-50 data-[marcado=true]:bg-blue-50/70 data-[marcado=true]:hover:bg-blue-50/70">
                        <td class="border-b border-slate-100 py-1 pl-4 md:pl-6">
                            <label class="-m-3 grid size-touch place-items-center">
                                <span class="sr-only" data-f-sel-texto></span>
                                <input type="checkbox" data-sel class="size-4 rounded border-slate-300 text-blue-600 focus-visible:outline-2 focus-visible:outline-blue-500" @disabled(! $puedeEditarPermisos)>
                            </label>
                        </td>
                        <td class="border-b border-slate-100 py-2 pr-3">
                            <span class="block font-medium text-slate-900 data-[sin-acceso]:text-slate-600" data-f-nombre></span>
                            <span class="block text-caption text-slate-500" data-f-detalle></span>
                        </td>
                        <td class="border-b border-slate-100 py-2 pr-3 text-slate-600" data-f-area></td>
                        @foreach ($permisos as $campo => $texto)
                            <td class="border-b border-slate-100 text-center">
                                @if ($puedeEditarPermisos)
                                    <button type="button" data-permiso="{{ $campo }}" data-activo="false" aria-pressed="false"
                                            class="group/permiso inline-grid size-touch place-items-center rounded-lg transition-colors duration-150 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-blue-500">
                                @else
                                    <span data-permiso-ver="{{ $campo }}" data-activo="false" role="img" class="group/permiso inline-grid size-9 place-items-center">
                                @endif
                                        <span class="hidden size-6 place-items-center rounded-md bg-blue-600 text-white shadow-sm group-data-[activo=true]/permiso:grid">
                                            <i class="fa-solid fa-check text-[0.6875rem]" aria-hidden="true"></i>
                                        </span>
                                        <span class="block size-6 rounded-md border-[1.5px] border-slate-300 bg-white transition-colors group-hover/permiso:border-slate-500 group-data-[activo=true]/permiso:hidden"></span>
                                @if ($puedeEditarPermisos)
                                    </button>
                                @else
                                    </span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </template>
                <template data-tpl-esqueleto>
                    <tr aria-hidden="true">
                        <td class="border-b border-slate-100 py-3.5 pl-4 md:pl-6"><div class="h-3 w-4 rounded bg-slate-200 motion-safe:animate-pulse"></div></td>
                        <td class="border-b border-slate-100 py-3.5 pr-3"><div class="h-3 w-40 rounded bg-slate-200 motion-safe:animate-pulse"></div></td>
                        <td class="border-b border-slate-100 py-3.5 pr-3"><div class="h-3 w-20 rounded bg-slate-200 motion-safe:animate-pulse"></div></td>
                        @foreach ($permisos as $campo => $texto)
                            <td class="border-b border-slate-100 py-3.5"><div class="mx-auto size-6 rounded-md bg-slate-100 motion-safe:animate-pulse"></div></td>
                        @endforeach
                    </tr>
                </template>
                <template data-tpl-estado>
                    <tr>
                        <td colspan="8" class="px-6 py-14 text-center">
                            <p class="text-sm text-slate-700" data-estado-texto></p>
                            <button type="button" data-estado-boton
                                    class="mt-3 min-h-10 rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-500"></button>
                        </td>
                    </tr>
                </template>

                {{-- Barra de acciones en grupo: aparece al marcar usuarios. --}}
                <div data-lote hidden
                     class="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex justify-center px-3 pb-3 md:px-6 md:pb-5">
                    <div class="pointer-events-auto flex max-w-full flex-wrap items-center gap-2 rounded-xl bg-slate-900 py-2 pl-4 pr-2 text-sm text-white shadow-[0_8px_24px_-6px_rgb(15_23_42/0.45)]">
                        <span class="mr-1 font-medium tabular-nums" data-lote-conteo></span>
                        <button type="button" data-lote-accion="dar-acceso"
                                class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-white/10 px-3 font-medium transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white">
                            <i class="fa-solid fa-check text-xs" aria-hidden="true"></i> Dar acceso
                        </button>
                        <button type="button" data-lote-accion="quitar-todo"
                                class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-red-500 px-3 font-medium transition-colors hover:bg-red-600 focus-visible:outline-2 focus-visible:outline-white">
                            <i class="fa-solid fa-user-minus text-xs" aria-hidden="true"></i> Quitar del módulo
                        </button>
                        <span class="mx-1 hidden h-6 w-px bg-white/20 sm:block" aria-hidden="true"></span>
                        <label class="inline-flex items-center gap-1">
                            <span class="sr-only">Permiso a cambiar</span>
                            <select data-lote-permiso class="h-10 rounded-lg border-0 bg-white/10 px-2 text-sm text-white focus-visible:outline-2 focus-visible:outline-white [&>option]:text-slate-900">
                                @foreach (array_slice($permisos, 1, null, true) as $campo => $texto)
                                    <option value="{{ $campo }}">{{ $texto }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="button" data-lote-accion="activar"
                                class="min-h-10 rounded-lg px-3 font-medium transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white">Activar</button>
                        <button type="button" data-lote-accion="desactivar"
                                class="min-h-10 rounded-lg px-3 font-medium transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white">Quitar</button>
                        <button type="button" data-lote-accion="limpiar" title="Deseleccionar"
                                class="grid size-10 place-items-center rounded-lg text-white/70 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">Deseleccionar</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    {{-- <dialog> nativo con el aspecto de los demás diálogos (.ui-dialogo, utils/dialogo.ts).
         Esc, foco y fondo los da el navegador; Cancelar cierra con formmethod="dialog". --}}
    <dialog id="createModal" data-dialog-nativo aria-labelledby="createModal-titulo"
            class="ui-dialogo ui-dialogo--xl ui-dialogo--formulario">
        <form data-envio-cargando action="{{ route('configuracion.utileria.modulos.store') }}" method="POST" enctype="multipart/form-data" class="ui-dialogo__cuerpo">
            <h2 id="createModal-titulo" class="ui-dialogo__titulo">Nuevo módulo</h2>
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1">Orden <span class="text-red-500">*</span></label>
                    <input type="text" id="createOrden" name="orden" required readonly class="bg-zinc-100" placeholder="Se calculará automáticamente">
                </div>
                <div>
                    <label class="block mb-1">Nombre del Módulo <span class="text-red-500">*</span></label>
                    <input type="text" name="modulo" required autofocus placeholder="Ej: Configuración, Tejido">
                </div>
                <div>
                    <label class="block mb-1">Nivel <span class="text-red-500">*</span></label>
                    <select name="Nivel" id="createNivel" required>
                        <option value="">Seleccionar</option>
                        <option value="1">Nivel 1 (Principal)</option>
                        <option value="2">Nivel 2 (Submódulo)</option>
                        <option value="3">Nivel 3 (Submódulo)</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1">Dependencia</label>
                    <select name="Dependencia" id="createDependencia">
                        <option value="">Seleccionar dependencia</option>
                    </select>
                    <p id="createDependenciaHelp" class="text-xs text-gray-500 mt-1">Selecciona primero el nivel</p>
                </div>
                {{-- Sin Ruta, moduleNameForRoute() no puede resolver el modulo y los
                     permisos quedan obligados a buscarse por nombre, que esta repetido
                     en SYSRoles. Asi nacio el bug de Utileria (188 sin Ruta vs 67). --}}
                <div class="md:col-span-2">
                    <label class="block mb-1">Ruta</label>
                    <input type="text" name="Ruta" id="createRuta" placeholder="Ej: /tejido/invtelas">
                    <p class="text-xs text-gray-500 mt-1">URL de la pantalla, empezando con <code>/</code>. Sin ella el módulo no se puede resolver por ruta y los permisos dependen del nombre.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4">
                @php($permLabels = ['acceso' => 'Acceso','crear' => 'Crear','modificar' => 'Modificar','eliminar' => 'Eliminar','reigstrar' => 'Registrar'])
                @foreach($permLabels as $name => $label)
                    <label class="flex items-center min-h-touch">
                        <input type="checkbox" name="{{ $name }}" value="1" {{ $name==='acceso' ? 'checked' : '' }}>
                        <span class="ml-2">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4">
                <label class="block mb-1">Imagen (opcional)</label>
                <input type="file" name="imagen_archivo" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:min-h-touch file:rounded-lg file:border file:border-zinc-200 file:bg-white file:px-4 file:font-medium file:text-zinc-800 hover:file:bg-zinc-50">
                <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF. Máximo: 2MB</p>
            </div>

            <div class="ui-dialogo__botones">
                <button type="submit" formmethod="dialog" formnovalidate class="ui-dialogo__boton ui-dialogo__boton--secundario">Cancelar</button>
                <button type="submit" class="ui-dialogo__boton ui-dialogo__boton--primario">Guardar</button>
            </div>
        </form>
    </dialog>

    <dialog id="editModal" data-dialog-nativo aria-labelledby="editModal-titulo"
            class="ui-dialogo ui-dialogo--xl ui-dialogo--formulario">
        <form id="editForm" data-envio-cargando action="#" method="POST" enctype="multipart/form-data" class="ui-dialogo__cuerpo">
            <h2 id="editModal-titulo" class="ui-dialogo__titulo">Editar módulo</h2>
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1">Orden <span class="text-red-500">*</span></label>
                    <input type="text" id="editOrden" name="orden" required readonly class="bg-zinc-100">
                </div>
                <div>
                    <label class="block mb-1">Nombre del Módulo <span class="text-red-500">*</span></label>
                    <input type="text" id="editModulo" name="modulo" required autofocus>
                </div>
                <div>
                    <label class="block mb-1">Nivel <span class="text-red-500">*</span></label>
                    <select id="editNivel" name="Nivel" required>
                        <option value="1">Nivel 1 (Principal)</option>
                        <option value="2">Nivel 2 (Submódulo)</option>
                        <option value="3">Nivel 3 (Submódulo)</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1">Dependencia</label>
                    <select id="editDependencia" name="Dependencia">
                        <option value="">Seleccionar dependencia</option>
                    </select>
                    <p id="editDependenciaHelp" class="text-xs text-gray-500 mt-1">Selecciona primero el nivel</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1">Ruta</label>
                    <input type="text" id="editRuta" name="Ruta" placeholder="Ej: /tejido/invtelas">
                    <p class="text-xs text-gray-500 mt-1">URL de la pantalla, empezando con <code>/</code>. Los módulos sin ruta no se pueden resolver con <code>moduleNameForRoute()</code>.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4">
                @foreach($permLabels as $name => $label)
                    <label class="flex items-center min-h-touch">
                        <input type="checkbox" id="edit_{{ $name }}" name="{{ $name }}" value="1">
                        <span class="ml-2">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4">
                <label class="block mb-1">Reemplazar Imagen (opcional)</label>
                <input type="file" name="imagen_archivo" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:min-h-touch file:rounded-lg file:border file:border-zinc-200 file:bg-white file:px-4 file:font-medium file:text-zinc-800 hover:file:bg-zinc-50">
            </div>

            <div class="ui-dialogo__botones">
                <button type="submit" formmethod="dialog" formnovalidate class="ui-dialogo__boton ui-dialogo__boton--secundario">Cancelar</button>
                <button type="submit" data-texto-cargando="Actualizando…" class="ui-dialogo__boton ui-dialogo__boton--primario">Actualizar</button>
            </div>
        </form>
    </dialog>

    @vite('resources/js/modulos/configuracion/gestion-modulos/index.ts')
@endsection
