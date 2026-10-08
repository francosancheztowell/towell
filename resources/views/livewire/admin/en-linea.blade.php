{{-- Panel /admin · En línea (MON-22). Se refresca solo mientras la pestaña está visible. --}}
<div class="space-y-4" @if ($renombrando === null) wire:poll.visible.{{ $pollSeconds }}s @endif>
    {{-- Estado: filtra la tabla. Volver a pulsar el activo regresa a "en línea e inactivos". --}}
    <div class="inline-flex flex-wrap gap-1 rounded-xl border border-(--adm-line) bg-(--adm-panel) p-1" role="group" aria-label="Filtrar por estado">
        @foreach ([
            ['', 'Activos', null, $conteos['en_linea'] + $conteos['inactivo']],
            ['en_linea', 'En línea', 'vivo', $conteos['en_linea']],
            ['inactivo', 'Inactivos', 'warn', $conteos['inactivo']],
            ['desconectado', 'Desconectados 24 h', 'apagado', $conteos['desconectado']],
        ] as [$clave, $titulo, $punto, $n])
            <button type="button" wire:click="$set('estado', '{{ $clave }}')" aria-pressed="{{ $estado === $clave ? 'true' : 'false' }}"
                    @class([
                        'inline-flex min-h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors',
                        'bg-(--adm-hover) text-(--adm-ink)' => $estado === $clave,
                        'text-(--adm-ink-2) hover:text-(--adm-ink)' => $estado !== $clave,
                    ])>
                @if ($punto === 'vivo')
                    <span @class(['adm-vivo' => $n > 0, 'size-2 rounded-full bg-(--adm-line-strong)' => $n === 0]) aria-hidden="true"></span>
                @elseif ($punto === 'warn')
                    <span class="size-2 rounded-full bg-(--adm-warn)" aria-hidden="true"></span>
                @elseif ($punto === 'apagado')
                    <span class="size-2 rounded-full border border-(--adm-ink-3)" aria-hidden="true"></span>
                @endif
                {{ $titulo }}
                <span class="tabular-nums text-(--adm-ink-3)">{{ $n }}</span>
            </button>
        @endforeach
    </div>

    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :seleccion-inmediata="true"
             vacio="No hay dispositivos con ese estado"
             vacio-icono="fa-tablet-screen-button"
             buscar-placeholder="Buscar dispositivo, usuario, IP o página…">

        <x-slot:acciones>
            <flux:button size="sm" icon="tag" wire:click="abrirRenombrar" :disabled="$seleccionado === null"
                         title="{{ $seleccionado === null ? 'Selecciona un dispositivo' : 'Renombrar dispositivo' }}">Renombrar</flux:button>
            <flux:button size="sm" variant="danger" icon="arrow-right-start-on-rectangle" :disabled="$seleccionado === null"
                         title="{{ $seleccionado === null ? 'Selecciona un dispositivo' : 'Cerrar la sesión de este dispositivo' }}"
                         x-on:click="notify.confirm({ title: '¿Cerrar la sesión de este dispositivo?', text: 'Se cierra solo en este equipo, en su siguiente actividad. Las demás sesiones del usuario siguen.', confirmText: 'Cerrar sesión', confirmColor: '#dc2626' }).then(ok => ok && $wire.cerrarSesion())">Cerrar sesión</flux:button>
        </x-slot:acciones>
    </x-tabla>

    @if ($renombrando !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50" role="dialog" aria-modal="true" aria-labelledby="titulo-renombrar">
            <div class="flex min-h-full items-center justify-center p-4">
                <form wire:submit="guardarNombre" class="w-full max-w-md overflow-hidden rounded-xl border border-(--adm-line) bg-(--adm-panel) shadow-(--adm-shadow)">
                    <div class="flex items-center justify-between border-b border-(--adm-line) px-5 py-3">
                        <h2 id="titulo-renombrar" class="text-sm font-semibold">Renombrar dispositivo</h2>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancelarRenombrar" aria-label="Cerrar" />
                    </div>
                    <div class="px-5 py-4">
                        <flux:input wire:model="nombre" maxlength="80" autofocus label="Nombre (vacío = sin nombre)" placeholder="Ej. Tablet telar 12" />
                    </div>
                    <div class="flex justify-end gap-2 border-t border-(--adm-line) px-5 py-3">
                        <flux:button size="sm" variant="ghost" wire:click="cancelarRenombrar">Cancelar</flux:button>
                        <flux:button size="sm" type="submit" variant="primary">Guardar</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
