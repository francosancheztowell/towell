{{-- Panel /admin · Detalle de un error (MON-25): la incidencia, sus eventos y su estado. --}}
@php
    $red = in_array($error->Origen, ['red', 'livewire'], true);
    $porPagina = $eventos->countBy(fn ($e) => $e->Url ?: '—')->sortDesc();
    $usuarios = $eventos->pluck('UsuarioId')->filter()->unique()->count();
    $barras = \App\Livewire\Admin\Resumen::barrasPorError(collect([$error->Id]))[$error->Id] ?? array_fill(0, 24, 0);
    $panel = 'rounded-xl border border-(--adm-line) bg-(--adm-panel)';
    $etiqueta = 'text-caption text-(--adm-ink-3)';
@endphp
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <section class="min-w-0 space-y-5">
        {{-- La incidencia --}}
        <div class="{{ $panel }}">
            <div class="space-y-3 px-5 py-4">
                <div class="flex flex-wrap items-center gap-2">
                    @include('livewire.admin.partials.estado-error', ['estado' => $error->Estado])
                    <flux:badge size="sm" color="zinc" inset="top bottom" class="adm-mono">{{ $error->Origen }}</flux:badge>
                    <span class="ms-auto adm-mono text-(--adm-ink-3)">#{{ $error->Id }} · {{ substr((string) $error->Huella, 0, 12) }}</span>
                </div>
                <h2 class="break-all text-lg font-semibold tracking-tight text-(--adm-ink)">{{ $error->Clase }}</h2>
                <p class="whitespace-pre-wrap break-words text-sm text-(--adm-ink-2)">{{ $error->Mensaje }}</p>

                @if ($diagnostico)
                    <flux:callout icon="information-circle" color="amber" class="text-sm">
                        <flux:callout.heading>Qué significa</flux:callout.heading>
                        <flux:callout.text>{{ $diagnostico }}</flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            <dl class="grid grid-cols-2 border-t border-(--adm-line) sm:grid-cols-4 sm:divide-x sm:divide-(--adm-line)">
                <div class="px-5 py-3"><dt class="{{ $etiqueta }}">Ocurrencias</dt><dd class="mt-0.5 text-base font-semibold" data-cifra>{{ number_format((int) $error->Ocurrencias) }}</dd></div>
                <div class="px-5 py-3"><dt class="{{ $etiqueta }}">Usuarios afectados</dt><dd class="mt-0.5 text-base font-semibold" data-cifra>{{ $usuarios }}</dd></div>
                <div class="px-5 py-3"><dt class="{{ $etiqueta }}">Primera vez</dt><dd class="mt-0.5 text-sm" title="{{ $error->PrimeraVez?->format('d/m/Y H:i') }}">{{ $error->PrimeraVez?->locale('es')->diffForHumans() }}</dd></div>
                <div class="px-5 py-3"><dt class="{{ $etiqueta }}">Última vez</dt><dd class="mt-0.5 text-sm" title="{{ $error->UltimaVez?->format('d/m/Y H:i') }}">{{ $error->UltimaVez?->locale('es')->diffForHumans() }}</dd></div>
            </dl>

            <div class="border-t border-(--adm-line) px-5 py-4">
                <p class="{{ $etiqueta }} mb-2">Eventos guardados por hora · últimas 24 h</p>
                <x-admin.barras :valores="$barras" :alto="40" :ejes="true" class="text-(--adm-err)" />
            </div>
        </div>

        @if ($causas->isNotEmpty())
            <div class="{{ $panel }} border-(--adm-err)/40">
                <h3 class="border-b border-(--adm-line) px-5 py-3 text-sm font-semibold text-(--adm-err)">
                    Causa en el servidor <span class="font-normal text-(--adm-ink-3)">· mismo usuario, ±10 s</span>
                </h3>
                <ul class="divide-y divide-(--adm-line)">
                    @foreach ($causas as $causa)
                        <li wire:key="causa-{{ $causa->Id }}" class="px-5 py-3 text-sm">
                            <a href="{{ route('admin.errores.show', $causa->Id) }}" class="font-semibold text-(--adm-accent) hover:underline">#{{ $causa->Id }} {{ $causa->Clase }}</a>
                            <p class="mt-0.5 break-words text-(--adm-ink-2)">{{ \Illuminate\Support\Str::limit($causa->Mensaje, 160) }}</p>
                            @if ($causa->Archivo)<p class="adm-mono mt-0.5 text-(--adm-ink-3)">{{ $causa->Archivo }}:{{ $causa->Linea }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Eventos --}}
        <div class="{{ $panel }} overflow-hidden">
            <h3 class="flex items-center justify-between border-b border-(--adm-line) px-5 py-3 text-sm font-semibold">
                Últimos eventos
                <span class="font-normal text-(--adm-ink-3)">{{ $eventos->count() }} de máx. {{ \App\Livewire\Admin\ErrorDetalle::MAX_EVENTOS }}</span>
            </h3>
            <ul class="divide-y divide-(--adm-line)">
                @forelse ($eventos as $evento)
                    <li class="px-5 py-3 text-sm" wire:key="evento-{{ $evento->Id }}">
                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <span class="font-medium tabular-nums text-(--adm-ink)">{{ $evento->Fecha?->format('d/m/Y H:i:s') }}</span>
                            <span class="text-(--adm-ink-2)">{{ $evento->UsuarioNombre ? $evento->UsuarioNombre.' (#'.$evento->UsuarioNumero.')' : 'Sin usuario' }}</span>
                            <span class="text-(--adm-ink-3)">{{ $evento->DispositivoNombre ?: $evento->DispositivoModelo ?: 'Sin dispositivo' }}</span>
                            @if ($evento->Metodo || $evento->Status)
                                <span class="adm-mono text-(--adm-ink-3)">{{ $evento->Metodo }} {{ $evento->Status }}</span>
                            @endif
                            @if ($evento->VersionFront)
                                <span class="adm-mono text-(--adm-ink-3)">front {{ $evento->VersionFront }}</span>
                            @endif
                        </div>
                        @if ($evento->Url)
                            <p class="adm-mono mt-1 break-all text-(--adm-ink-3)">{{ $red ? 'Página: ' : '' }}{{ $evento->Url }}</p>
                        @endif
                        @if ($evento->Traza)
                            <details class="group mt-2" @if ($red) open @endif>
                                <summary class="inline-flex cursor-pointer select-none items-center gap-1 text-caption font-medium text-(--adm-accent)">
                                    <flux:icon.chevron-right variant="micro" class="size-3.5 transition-transform group-open:rotate-90" />
                                    {{ $red ? 'Contexto' : 'Traza' }}
                                </summary>
                                <pre class="adm-mono mt-2 max-h-80 overflow-auto rounded-lg border border-(--adm-line) bg-(--adm-bg) p-3 leading-relaxed text-(--adm-ink-2)">{{ $evento->Traza }}</pre>
                            </details>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-(--adm-ink-2)">Sin eventos guardados (se guardan hasta 50 por día).</li>
                @endforelse
            </ul>
        </div>
    </section>

    {{-- Estado y datos --}}
    <aside class="space-y-5">
        <form wire:submit="guardar" class="{{ $panel }} space-y-4 p-4">
            <h3 class="text-sm font-semibold">Estado</h3>
            <flux:radio.group wire:model.live="estado" variant="segmented" size="sm" aria-label="Estado del error">
                @foreach ($estados as $opcion)
                    <flux:radio :value="$opcion" :label="ucfirst($opcion)" />
                @endforeach
            </flux:radio.group>
            @error('estado') <p class="text-caption text-(--adm-err)">{{ $message }}</p> @enderror

            <flux:textarea wire:model="nota" rows="4" maxlength="500" label="Nota (opcional, máx. 500)"
                           placeholder="Qué se hizo o por qué se ignora" />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            <p class="{{ $etiqueta }}">Si un error resuelto vuelve a ocurrir, regresa a «nuevo» y se manda la alerta de regresión.</p>
        </form>

        <dl class="{{ $panel }} space-y-3 p-4 text-sm">
            <div><dt class="{{ $etiqueta }}">{{ $red ? 'Petición' : 'Archivo' }}</dt><dd class="adm-mono mt-0.5 break-all">{{ $error->Archivo ? $error->Archivo.':'.$error->Linea : '—' }}</dd></div>
            <div><dt class="{{ $etiqueta }}">Ruta</dt><dd class="adm-mono mt-0.5 break-all">{{ $error->Ruta ?: '—' }}</dd></div>
            @if ($porPagina->isNotEmpty())
                <div>
                    <dt class="{{ $etiqueta }}">Páginas donde ocurrió</dt>
                    <dd class="mt-1 flex flex-wrap gap-1.5">
                        @foreach ($porPagina->take(6) as $pagina => $n)
                            <span class="adm-mono rounded-md border border-(--adm-line) px-1.5 py-0.5 text-(--adm-ink-2)">{{ $pagina }} ×{{ $n }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
            <div><dt class="{{ $etiqueta }}">Resuelto</dt>
                <dd class="mt-0.5">{{ $error->ResueltoEn ? $error->ResueltoEn->format('d/m/Y H:i').' · '.($error->ResueltoPorNombre ?? '#'.$error->ResueltoPor) : '—' }}</dd></div>
        </dl>
    </aside>
</div>
