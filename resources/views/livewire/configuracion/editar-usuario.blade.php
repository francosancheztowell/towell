@php
    $input = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200';
    $etiqueta = 'block text-xs font-medium text-slate-600';
    $titulos = ['acceso' => 'Acceso', 'crear' => 'Crear', 'modificar' => 'Modificar', 'eliminar' => 'Eliminar', 'registrar' => 'Registrar'];
@endphp
<form id="usuario-form" wire:submit="guardar" class="flex min-h-0 flex-1 flex-col gap-2">
    {{-- Datos: una sola franja; teléfono, correo y foto casi nadie los llena, van plegados. --}}
    <div class="grid shrink-0 grid-cols-2 gap-2 rounded-lg bg-white p-2 shadow-sm md:grid-cols-3 xl:grid-cols-6">
        <div>
            <label for="area" class="{{ $etiqueta }}">Área</label>
            <select id="area" wire:model="form.area" class="{{ $input }}">
                <option value="">—</option>
                @foreach ($departamentos as $depto)
                    <option value="{{ $depto }}">{{ $depto }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="numero_empleado" class="{{ $etiqueta }}">No. empleado</label>
            <input id="numero_empleado" value="{{ $form['numero_empleado'] }}" readonly
                   class="{{ $input }} bg-slate-100 text-slate-500">
        </div>
        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre</label>
            <input id="nombre" wire:model="form.nombre" required class="{{ $input }}">
            @error('form.nombre') <p class="text-caption text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="puesto" class="{{ $etiqueta }}">Puesto</label>
            <input id="puesto" wire:model="form.puesto" class="{{ $input }}">
        </div>
        <div>
            <label for="turno" class="{{ $etiqueta }}">Turno</label>
            <select id="turno" wire:model="form.turno" class="{{ $input }}">
                <option value="1">Turno 1</option>
                <option value="2">Turno 2</option>
                <option value="3">Turno 3</option>
                <option value="4">Turno 4 (Cubre descansos)</option>
            </select>
        </div>
        <div>
            <label for="contrasenia" class="{{ $etiqueta }}">Contraseña nueva</label>
            <input id="contrasenia" type="password" wire:model="form.contrasenia" autocomplete="new-password"
                   placeholder="Vacío = no cambia" class="{{ $input }}">
            @error('form.contrasenia') <p class="text-caption text-red-600">{{ $message }}</p> @enderror
        </div>

        <details class="col-span-full" @if ($errors->hasAny(['form.telefono', 'form.correo', 'foto'])) open @endif>
            <summary class="cursor-pointer text-xs font-medium text-blue-700">Teléfono, correo y foto</summary>
            <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                <div>
                    <label for="telefono" class="{{ $etiqueta }}">Teléfono</label>
                    <input id="telefono" type="tel" inputmode="numeric" wire:model="form.telefono" maxlength="12" class="{{ $input }}">
                    @error('form.telefono') <p class="text-caption text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="correo" class="{{ $etiqueta }}">Correo</label>
                    <input id="correo" type="email" wire:model="form.correo" class="{{ $input }}">
                    @error('form.correo') <p class="text-caption text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="foto" class="{{ $etiqueta }}">Foto</label>
                    <input id="foto" type="file" accept="image/*" wire:model="foto"
                           class="block w-full text-sm text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-blue-700">
                    @error('foto') <p class="text-caption text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </details>
    </div>

    {{-- Permisos: ocupa todo el alto restante, con encabezado fijo. --}}
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="flex shrink-0 items-center gap-2 border-b border-slate-200 px-2 py-1.5 text-sm">
            <span class="font-semibold text-slate-700">Permisos</span>
            <span class="text-caption text-slate-500">Un módulo principal aplica el cambio a todos sus submódulos.</span>
            <button type="button" wire:click="todos(true)" class="ml-auto rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">Todos</button>
            <button type="button" wire:click="todos(false)" class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">Ninguno</button>
        </div>

        <div class="min-h-0 flex-1 overflow-auto" wire:loading.class="opacity-70" wire:target="alternar,columna,todos">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-blue-600 text-xs uppercase text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left font-semibold">Módulo</th>
                        @foreach ($titulos as $campo => $titulo)
                            <th class="w-24 px-1 py-1.5 font-semibold">
                                <label class="flex cursor-pointer items-center justify-center gap-1">
                                    {{ $titulo }}
                                    <input type="checkbox" wire:click="columna('{{ $campo }}')" @checked($this->columnaCompleta($campo))
                                           class="size-3.5 cursor-pointer rounded" aria-label="Marcar toda la columna {{ $titulo }}">
                                </label>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->modulos as $m)
                        @php($nivel = (int) $m->Nivel)
                        <tr wire:key="m-{{ $m->idrol }}"
                            class="border-b {{ $nivel === 1 ? 'border-sky-200 bg-sky-50 font-semibold text-blue-950' : 'border-slate-100 text-slate-700 hover:bg-slate-50' }}">
                            <td class="py-1 pr-2" style="padding-left: {{ 0.75 + max(0, $nivel - 1) * 1.25 }}rem">
                                @if ($nivel > 1)<span class="text-slate-300">└</span>@endif
                                {{ $m->modulo }}
                            </td>
                            @foreach ($titulos as $campo => $titulo)
                                <td class="py-1 text-center">
                                    <input type="checkbox" wire:click="alternar({{ $m->idrol }}, '{{ $campo }}')"
                                           @checked($permisos[$m->idrol][$campo] ?? false)
                                           class="size-4 cursor-pointer rounded border-slate-300 text-blue-600"
                                           aria-label="{{ $titulo }} en {{ $m->modulo }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</form>
