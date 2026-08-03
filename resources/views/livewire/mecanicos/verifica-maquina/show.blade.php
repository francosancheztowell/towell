@php
    // Datos mínimos que necesita Alpine para filtrar del lado del cliente.
    $telaresAlpine = array_map(fn (array $telar) => [
        'id' => $telar['NoTelarId'],
        'salon' => $telar['SalonTejidoId'],
        'nombre' => $telar['Nombre'],
    ], $telares);
@endphp

<div
    class="flex h-full min-h-0 flex-row items-start gap-3 sm:gap-4"
    wire:key="verifica-maquina-show-{{ $folio }}"
    x-data="{
        estatus: @js($estatus),
        horaFin: @js($horaFin),
        puedeFinalizarFlag: @js($puedeFinalizarFlag),
        esSupervisorFlag: @js($esSupervisorFlag),
        puedeCapturarFlag: @js($puedeCapturarFlag),
        soloLectura: @js($soloLectura),

        modalFinalizar: false,
        modalIncompletos: false,
        modalAutorizar: false,
        procesando: false,

        telares: @js($telaresAlpine),

        // '' = Todas (vista general, siempre solo-lectura). Cualquier otro
        // valor = una de las 3 máquinas, con acordeón exclusivo: solo una
        // máquina puede estar abierta/seleccionada a la vez.
        maquinaAbierta: '',
        // Solo un telar a la vez: la matriz enfoca un telar por captura.
        telarSeleccionado: null,

        get puedeCapturar() { return this.estatus === 'Activo' && this.puedeCapturarFlag },
        get puedeFinalizar() { return this.estatus === 'Activo' && this.puedeFinalizarFlag },
        get puedeAutorizar() { return this.estatus === 'Terminado' && this.esSupervisorFlag },
        get esSoloLectura() { return this.estatus !== 'Activo' },

        // 'Todas' es siempre de solo-lectura, sin importar permisos o estatus:
        // solo se puede capturar dentro de una máquina específica.
        get puedeEditarAhora() { return this.puedeCapturar && this.maquinaAbierta !== '' },

        badgeClass() {
            if (this.estatus === 'Activo') return 'bg-blue-100 text-blue-800'
            if (this.estatus === 'Terminado') return 'bg-amber-100 text-amber-800'
            if (this.estatus === 'Autorizado') return 'bg-green-100 text-green-800'
            return 'bg-gray-100 text-gray-700'
        },

        get telaresDeMaquina() { return this.telares.filter((telar) => telar.salon === this.maquinaAbierta) },

        get visibles() {
            if (this.maquinaAbierta === '') return this.telares
            return this.telarSeleccionado ? this.telares.filter((telar) => telar.id === this.telarSeleccionado) : []
        },

        seleccionarTelar(id) {
            // Un solo telar a la vez: tocar el mismo lo deselecciona.
            this.telarSeleccionado = this.telarSeleccionado === id ? null : id
        },

        seleccionarMaquina(valor) {
            if (this.maquinaAbierta === valor) return
            this.maquinaAbierta = valor
            // Acordeón exclusivo: cambiar de máquina limpia la selección anterior.
            this.telarSeleccionado = null
        },

        /*
         * Ocultar columnas reescribiendo una sola regla CSS es O(1) en el DOM:
         * evita recorrer las ~1.1k celdas y evita un viaje al servidor.
         */
        aplicarFiltroColumnas() {
            if (this.maquinaAbierta === '') {
                this.$refs.colStyle.textContent = ''
                return
            }
            const ocultos = this.telares
                .filter((telar) => telar.id !== this.telarSeleccionado)
                .map((telar) => '.vm-col-' + telar.id)
            this.$refs.colStyle.textContent = ocultos.length ? ocultos.join(',') + '{display:none}' : ''
        },

        async onCeldaClick(event) {
            const boton = event.target.closest('.vm-mini')
            if (!boton || !this.puedeEditarAhora) return

            const contenedor = boton.closest('.vm-triple')
            if (!contenedor) return

            const valor = boton.dataset.val
            if (contenedor.dataset.v === valor) return

            const anterior = contenedor.dataset.v || ''
            // Pintado optimista: la celda responde al instante.
            contenedor.dataset.v = valor
            contenedor.querySelectorAll('.vm-mini').forEach((b) => b.classList.toggle('vm-mini-on', b.dataset.val === valor))

            try {
                const promedio = await $wire.capturar(contenedor.dataset.t, parseInt(contenedor.dataset.a, 10), valor)
                const celdaPromedio = this.$refs.matriz.querySelector('[data-prom=\'' + contenedor.dataset.a + '\']')
                if (celdaPromedio) {
                    celdaPromedio.textContent = (promedio === null || promedio === undefined) ? '—' : promedio
                }
            } catch (error) {
                contenedor.dataset.v = anterior
                contenedor.querySelectorAll('.vm-mini').forEach((b) => b.classList.toggle('vm-mini-on', b.dataset.val === anterior))
            }
        },

        aplicarFinalizado(res) {
            if (!res || !res.ok) return
            this.estatus = res.estatus
            this.horaFin = res.horaFin || this.horaFin
            this.modalFinalizar = false
            this.modalIncompletos = false
        },

        async onConfirmarFinalizar() {
            if (this.procesando) return
            this.procesando = true
            try {
                const res = await $wire.confirmarFinalizar()
                if (res && res.incompleto) {
                    this.modalFinalizar = false
                    this.modalIncompletos = true
                    return
                }
                this.aplicarFinalizado(res)
            } finally {
                this.procesando = false
            }
        },

        async onConfirmarFinalizarIncompletos() {
            if (this.procesando) return
            this.procesando = true
            try {
                this.aplicarFinalizado(await $wire.confirmarFinalizarConIncompletos())
            } finally {
                this.procesando = false
            }
        },

        async onAutorizar() {
            if (this.procesando) return
            this.procesando = true
            try {
                const res = await $wire.autorizar()
                if (res && res.ok) {
                    this.estatus = res.estatus
                    this.modalAutorizar = false
                }
            } finally {
                this.procesando = false
            }
        }
    }"
    x-effect="aplicarFiltroColumnas()"
>
    {{-- Estilos de celda declarados una vez: repetir utilidades en ~1.1k celdas infla el HTML. --}}
    <style>
        .vm-td{padding:.625rem .5rem;text-align:center}
        .vm-th{min-width:4.5rem;padding:.875rem .625rem;text-align:center;font-size:.875rem}

        /* Vista "Todas": una sola celda de solo lectura (comportamiento previo). */
        .vm-view{display:inline-flex;align-items:center;justify-content:center;height:3rem;width:3.5rem;border-radius:.75rem;border-width:2px;border-style:solid;font-size:1.25rem;font-weight:800;font-variant-numeric:tabular-nums;box-shadow:0 1px 2px rgba(0,0,0,.05)}
        .vm-view-on{border-color:#111827;background:#111827;color:#fff}
        .vm-view-off{border-color:#d1d5db;background:#fff;color:#9ca3af}

        /*
         * Vista "Máquina seleccionada": ahora solo se captura un telar a la
         * vez, así que sobra espacio horizontal y los 3 cuadros (1/2/3) se
         * pintan un poco más grandes que la celda de solo lectura de "Todas".
         */
        .vm-triple{display:none;align-items:center;justify-content:center;gap:.75rem}
        .vm-mini{display:inline-flex;align-items:center;justify-content:center;height:4rem;width:4.25rem;border-radius:.875rem;border-width:2px;border-style:solid;font-size:1.5rem;font-weight:800;font-variant-numeric:tabular-nums;box-shadow:0 1px 2px rgba(0,0,0,.05);transition:transform .12s,border-color .12s;cursor:pointer;border-color:#d1d5db;background:#fff;color:#9ca3af}
        .vm-mini:hover{border-color:#374151;transform:scale(1.05)}
        .vm-mini-on{border-color:#111827;background:#111827;color:#fff}

        .vm-modo-editar .vm-view{display:none}
        .vm-modo-editar .vm-triple{display:inline-flex}
        .vm-modo-editar .vm-td{padding:1rem .875rem}

        .vm-locked .vm-view{opacity:.45}
        .vm-locked .vm-mini{cursor:not-allowed;opacity:.45}
        .vm-locked .vm-mini:hover{border-color:inherit;transform:none}
    </style>
    <style x-ref="colStyle"></style>

    {{-- Barra lateral: selección de máquina y sus telares --}}
    <aside class="z-20 flex h-full min-h-0 w-60 shrink-0 flex-col overflow-y-auto sm:w-64 md:w-72">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
            <h2 class="text-sm font-bold text-gray-900">Máquina</h2>
            <p class="mt-0.5 text-[11px] text-gray-500">Selecciona una máquina para elegir sus telares.</p>

            <div class="mt-3 space-y-2">
                @php
                    $opcionesMaquina = [
                        '' => ['label' => 'Todas', 'count' => $totalTelares],
                        'Jacquard' => ['label' => 'Jacquard', 'count' => (int) ($conteoPorMaquina['Jacquard'] ?? 0)],
                        'Smith' => ['label' => 'Smith', 'count' => (int) ($conteoPorMaquina['Smith'] ?? 0)],
                        'KM' => ['label' => 'Karl Mayer', 'count' => (int) ($conteoPorMaquina['KM'] ?? 0)],
                    ];
                @endphp
                @foreach ($opcionesMaquina as $valor => $opcion)
                    <div class="overflow-hidden rounded-lg border transition" :class="maquinaAbierta === @js($valor) ? 'border-gray-900' : 'border-gray-200'">
                        <button type="button" @click="seleccionarMaquina(@js($valor))"
                            class="flex w-full items-center justify-between px-3 py-2.5 text-sm font-semibold transition"
                            :class="maquinaAbierta === @js($valor) ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'">
                            <span class="inline-flex items-center gap-2">
                                @if ($valor !== '')
                                    <i class="fas fa-chevron-right text-[10px] transition-transform" :class="maquinaAbierta === @js($valor) ? 'rotate-90' : ''"></i>
                                @endif
                                {{ $opcion['label'] }}
                            </span>
                            <span class="rounded-full px-1.5 py-0.5 text-[11px] font-bold"
                                :class="maquinaAbierta === @js($valor) ? 'bg-white/20 text-white' : 'bg-white text-gray-600'">
                                {{ $opcion['count'] }}
                            </span>
                        </button>

                        @if ($valor !== '')
                            <div x-show="maquinaAbierta === @js($valor)" x-cloak class="border-t border-gray-100 bg-white p-2.5">
                                <div class="mb-2 flex items-center justify-between gap-2 text-[11px]">
                                    <span class="font-semibold text-gray-500">
                                        <template x-if="telarSeleccionado"><span>Telar <span class="text-gray-900" x-text="telarSeleccionado"></span></span></template>
                                        <template x-if="!telarSeleccionado"><span>Ninguno seleccionado</span></template>
                                    </span>
                                    <button type="button" x-show="telarSeleccionado" x-cloak @click="telarSeleccionado = null" class="font-semibold text-gray-500 hover:underline">Quitar</button>
                                </div>
                                <div class="max-h-64 space-y-1 overflow-y-auto pr-1 lg:max-h-[28rem]">
                                    <template x-for="telar in telaresDeMaquina" :key="telar.id">
                                        <button type="button" @click="seleccionarTelar(telar.id)"
                                            class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition"
                                            :class="telarSeleccionado === telar.id ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50'">
                                            <span class="shrink-0 font-semibold" x-text="telar.id"></span>
                                            <span class="truncate text-xs" :class="telarSeleccionado === telar.id ? 'text-white/70' : 'text-gray-500'" x-text="telar.nombre"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-2.5 py-2 text-center text-xs text-gray-600">
                Mostrando <span class="font-bold text-gray-900" x-text="visibles.length"></span> telar(es)
            </div>
        </div>
    </aside>

    {{-- Contenido principal --}}
    <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col space-y-4">
        <section class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm sm:px-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h1 class="shrink-0 text-base font-bold text-gray-900">Verificación</h1>
                <span class="inline-flex shrink-0 rounded bg-gray-900 px-2 py-0.5 text-xs font-bold text-white">{{ $folio }}</span>
                <span x-show="soloLectura" x-cloak class="inline-flex shrink-0 items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">
                    <i class="fas fa-eye"></i> Solo lectura
                </span>

                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
                    <span><span class="font-medium text-gray-400">Fecha</span> {{ $fecha }}</span>
                    <span class="hidden text-gray-300 sm:inline">|</span>
                    <span class="truncate"><span class="font-medium text-gray-400">Mecánico</span> {{ $nomOperador }}</span>
                    <span class="hidden text-gray-300 sm:inline">|</span>
                    <span><span class="font-medium text-gray-400">Turno</span> {{ $turnoRecibe }}</span>
                    <span class="hidden text-gray-300 sm:inline">|</span>
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="badgeClass()" x-text="estatus"></span>
                    <span class="hidden text-gray-300 sm:inline">|</span>
                    <span><span class="font-medium text-gray-400">Inicio</span> {{ $horaInicio }}</span>
                    <span class="hidden text-gray-300 sm:inline">|</span>
                    <span><span class="font-medium text-gray-400">Fin</span> <span x-text="horaFin"></span></span>
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                    <button type="button" x-show="puedeFinalizar" x-cloak @click="modalFinalizar = true"
                        class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-black">
                        <i class="fas fa-check"></i>
                        Finalizar
                    </button>
                    <button type="button" x-show="puedeAutorizar" x-cloak @click="modalAutorizar = true"
                        class="inline-flex items-center gap-1.5 rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-800">
                        <i class="fas fa-user-check"></i>
                        Autorizar
                    </button>
                </div>
            </div>

            <p x-show="esSoloLectura" x-cloak class="mt-2 text-[11px] text-amber-700">
                Folio en estatus <strong x-text="estatus"></strong>. Solo los <strong>Activo</strong> se pueden editar.
            </p>
            <p x-show="!esSoloLectura && maquinaAbierta === ''" x-cloak class="mt-2 text-[11px] text-gray-500">
                <i class="fas fa-circle-info"></i> "Todas" es solo de consulta. Selecciona una máquina en el panel izquierdo para capturar sus telares.
            </p>
        </section>

        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

            {{--
                wire:ignore: la matriz se pinta una sola vez y a partir de ahí la
                mantiene Alpine (captura optimista + filtros por CSS), así Livewire
                nunca vuelve a diferenciar ~1.1k celdas. La página nunca hace scroll:
                solo este contenedor (las actividades) scrollea, en ambos ejes, con
                encabezados sticky para no perder de vista telar/actividad.
            --}}
            <div
                wire:ignore
                x-ref="matriz"
                :class="{ 'vm-modo-editar': maquinaAbierta !== '', 'vm-locked': !puedeEditarAhora }"
                class="min-h-0 max-w-full flex-1 overflow-auto overscroll-contain"
                tabindex="0"
                aria-label="Cuadrícula de verificación por telar y actividad; desplázate para ver todas las filas y columnas"
            >
                {{--
                    El markup de las ~1.1k celdas va compactado en una sola línea a
                    propósito: la indentación de Blade por celda pesaba más que el
                    propio contenido. Los botones no llevan aria-label porque la
                    tabla expone encabezados con scope y el lector de pantalla ya
                    anuncia telar + actividad al entrar en la celda.
                --}}
                <table class="divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th scope="col" class="sticky left-0 top-0 z-40 min-w-80 max-w-md bg-gray-50 px-4 py-3.5 text-left text-sm">Actividad</th>
                            @foreach ($telares as $telar)<th scope="col" class="vm-col-{{ $telar['NoTelarId'] }} vm-th sticky top-0 z-30 bg-gray-50" title="{{ $telar['Nombre'] }} ({{ $telar['SalonTejidoId'] }})">{{ $telar['NoTelarId'] }}</th>@endforeach
                            <th scope="col" class="sticky top-0 z-30 whitespace-nowrap bg-gray-100 px-4 py-3.5 text-center text-sm">Todos los telares</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white" @click="onCeldaClick($event)">
                        @forelse ($actividades as $actividad)
                            @php $nombreActividad = $actividad['Actividad']; @endphp
                            <tr class="hover:bg-gray-50">
                                <th scope="row" class="sticky left-0 z-20 min-w-80 max-w-md bg-white px-4 py-4 text-left">
                                    <span class="line-clamp-2 text-lg font-bold leading-snug text-gray-900" title="{{ $nombreActividad }}">{{ $nombreActividad }}</span>
                                </th>
                                @foreach ($telares as $telar)@php $v = $valores[$telar['NoTelarId'].'|'.$nombreActividad] ?? ''; @endphp<td class="vm-col-{{ $telar['NoTelarId'] }} vm-td"><span class="vm-view {{ $v !== '' ? 'vm-view-on' : 'vm-view-off' }}">{{ $v !== '' ? $v : '—' }}</span><div class="vm-triple" data-t="{{ $telar['NoTelarId'] }}" data-a="{{ $actividad['Id'] }}"@if ($v !== '') data-v="{{ $v }}"@endif><button type="button" class="vm-mini {{ $v === '1' ? 'vm-mini-on' : '' }}" data-val="1">1</button><button type="button" class="vm-mini {{ $v === '2' ? 'vm-mini-on' : '' }}" data-val="2">2</button><button type="button" class="vm-mini {{ $v === '3' ? 'vm-mini-on' : '' }}" data-val="3">3</button></div></td>@endforeach
                                <td data-prom="{{ $actividad['Id'] }}" class="whitespace-nowrap bg-gray-50 px-4 py-3 text-center text-base font-bold text-gray-800">{{ $promedios[$nombreActividad] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($telares) + 2 }}" class="px-4 py-10 text-center text-sm text-gray-500">No hay actividades configuradas en el catálogo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="maquinaAbierta !== '' && visibles.length === 0" x-cloak class="px-4 py-8 text-center text-sm text-gray-500">
                Selecciona un telar de esta máquina en el panel izquierdo.
            </div>
        </section>
    </div>

    {{-- Modal: confirmar finalizar --}}
    <div x-show="modalFinalizar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true" @click.self="modalFinalizar = false">
        <div class="w-full max-w-md rounded-xl bg-white shadow-2xl" @click.stop>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-bold text-gray-900">Confirmar finalización</h2>
            </div>
            <div class="px-5 py-5">
                <p class="text-sm leading-relaxed text-gray-700">¿Está seguro que quieres finalizar este registro?</p>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:justify-end">
                <button type="button" @click="modalFinalizar = false" :disabled="procesando"
                    class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                    No
                </button>
                <button type="button" @click="onConfirmarFinalizar()" :disabled="procesando"
                    class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black disabled:opacity-60 sm:w-auto">
                    <span x-text="procesando ? 'Finalizando…' : 'Sí'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal: incompletos --}}
    <div x-show="modalIncompletos" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true" @click.self="modalIncompletos = false">
        <div class="w-full max-w-md rounded-xl bg-white shadow-2xl" @click.stop>
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-4">
                <h2 class="text-lg font-bold text-amber-900">Telares incompletos</h2>
            </div>
            <div class="px-5 py-5">
                <p class="text-sm leading-relaxed text-gray-700">
                    Hay telares incompletos o que no se han llenado. ¿Está seguro de finalizar este registro?
                </p>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:justify-end">
                <button type="button" @click="modalIncompletos = false" :disabled="procesando"
                    class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                    No
                </button>
                <button type="button" @click="onConfirmarFinalizarIncompletos()" :disabled="procesando"
                    class="w-full rounded-md bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:opacity-60 sm:w-auto">
                    <span x-text="procesando ? 'Finalizando…' : 'Sí, finalizar'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal: autorizar --}}
    <div x-show="modalAutorizar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true" @click.self="modalAutorizar = false">
        <div class="w-full max-w-md rounded-xl bg-white shadow-2xl" @click.stop>
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-bold text-gray-900">Autorizar verificación</h2>
            </div>
            <div class="px-5 py-5">
                <p class="text-sm leading-relaxed text-gray-700">¿Está seguro que quieres autorizar este registro?</p>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:justify-end">
                <button type="button" @click="modalAutorizar = false" :disabled="procesando"
                    class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                    No
                </button>
                <button type="button" @click="onAutorizar()" :disabled="procesando"
                    class="w-full rounded-md bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-60 sm:w-auto">
                    <span x-text="procesando ? 'Autorizando…' : 'Sí'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
