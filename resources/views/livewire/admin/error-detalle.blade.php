{{-- Panel /admin · Detalle de un error (MON-25). --}}
@php
    $colores = [
        'nuevo' => 'bg-red-100 text-red-700',
        'visto' => 'bg-amber-100 text-amber-700',
        'resuelto' => 'bg-emerald-100 text-emerald-700',
        'ignorado' => 'bg-slate-200 text-slate-600',
    ];
    $red = in_array($error->Origen, ['red', 'livewire'], true);
    $porPagina = $eventos->countBy(fn ($e) => $e->Url ?: '—')->sortDesc();
    $usuarios = $eventos->pluck('UsuarioId')->filter()->unique()->count();
@endphp
<div class="grid gap-3 lg:grid-cols-3">
    <section class="space-y-3 lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.errores') }}" class="text-sm font-semibold text-blue-600 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Errores
                </a>
                <span class="rounded-full px-2 py-0.5 text-xs font-bold uppercase {{ $colores[$error->Estado] ?? 'bg-slate-100' }}">{{ $error->Estado }}</span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $error->Origen }}</span>
                <span class="ms-auto text-xs text-slate-400">#{{ $error->Id }} · huella {{ substr((string) $error->Huella, 0, 12) }}</span>
            </div>
            <h2 class="mt-3 break-all text-lg font-bold text-slate-800">{{ $error->Clase }}</h2>
            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-700">{{ $error->Mensaje }}</p>

            @if ($diagnostico)
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <span class="font-semibold"><i class="fa-solid fa-circle-info"></i> Qué significa:</span> {{ $diagnostico }}
                </div>
            @endif

            @if ($eventos->isNotEmpty())
                <p class="mt-3 flex flex-wrap items-center gap-1 text-sm text-slate-600">
                    Afecta a <strong>{{ $usuarios }}</strong> {{ $usuarios === 1 ? 'usuario' : 'usuarios' }} en
                    @foreach ($porPagina->take(4) as $pagina => $n)
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $pagina }} ×{{ $n }}</span>
                    @endforeach
                </p>
            @endif

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                <div><dt class="text-xs font-semibold text-slate-500">{{ $red ? 'Petición' : 'Archivo' }}</dt><dd class="break-all">{{ $error->Archivo ? $error->Archivo.':'.$error->Linea : '—' }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Ruta</dt><dd class="break-all">{{ $error->Ruta ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Ocurrencias</dt><dd>{{ number_format((int) $error->Ocurrencias) }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Primera vez</dt><dd>{{ $error->PrimeraVez?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Última vez</dt><dd>{{ $error->UltimaVez?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Resuelto</dt>
                    <dd>{{ $error->ResueltoEn ? $error->ResueltoEn->format('d/m/Y H:i').' · '.($error->ResueltoPorNombre ?? '#'.$error->ResueltoPor) : '—' }}</dd></div>
            </dl>
        </div>

        @if ($causas->isNotEmpty())
            <div class="rounded-xl border border-red-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-red-700">Causa en el servidor <span class="font-normal text-slate-400">(mismo usuario, ±10 s)</span></h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($causas as $causa)
                        <li wire:key="causa-{{ $causa->Id }}">
                            <a href="{{ route('admin.errores.show', $causa->Id) }}" class="font-semibold text-blue-600 hover:underline">#{{ $causa->Id }} {{ $causa->Clase }}</a>
                            <span class="break-words text-slate-700">{{ \Illuminate\Support\Str::limit($causa->Mensaje, 160) }}</span>
                            @if ($causa->Archivo)<span class="text-xs text-slate-400">{{ $causa->Archivo }}:{{ $causa->Linea }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50/60 px-4 py-2.5 text-sm font-bold text-slate-700">
                Últimos eventos <span class="font-normal text-slate-400">({{ $eventos->count() }} de máx. {{ \App\Livewire\Admin\ErrorDetalle::MAX_EVENTOS }})</span>
            </h3>
            <ul class="divide-y divide-slate-100">
                @forelse ($eventos as $evento)
                    <li class="px-4 py-2.5 text-sm" wire:key="evento-{{ $evento->Id }}">
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-slate-700">
                            <span class="font-semibold">{{ $evento->Fecha?->format('d/m/Y H:i:s') }}</span>
                            <span>{{ $evento->UsuarioNombre ? $evento->UsuarioNombre.' (#'.$evento->UsuarioNumero.')' : 'Sin usuario' }}</span>
                            <span class="text-slate-500">{{ $evento->DispositivoNombre ?: $evento->DispositivoModelo ?: 'Sin dispositivo' }}</span>
                            @if ($evento->Metodo || $evento->Status)
                                <span class="text-slate-500">{{ $evento->Metodo }} {{ $evento->Status }}</span>
                            @endif
                            @if ($evento->VersionFront)
                                <span class="text-xs text-slate-400">front {{ $evento->VersionFront }}</span>
                            @endif
                        </div>
                        @if ($evento->Url)
                            <div class="mt-0.5 break-all text-xs text-slate-500">{{ $red ? 'Página: ' : '' }}{{ $evento->Url }}</div>
                        @endif
                        @if ($evento->Traza)
                            <details class="mt-1" @if ($red) open @endif>
                                <summary class="cursor-pointer text-xs font-semibold text-blue-600">{{ $red ? 'Contexto' : 'Traza' }}</summary>
                                <pre class="mt-1 max-h-72 overflow-auto rounded bg-slate-900 p-2 text-[11px] leading-snug text-slate-100">{{ $evento->Traza }}</pre>
                            </details>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-slate-500">Sin eventos guardados (se guardan hasta 50 por día).</li>
                @endforelse
            </ul>
        </div>
    </section>

    <form wire:submit="guardar" class="h-fit space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-bold text-slate-700">Estado</h3>
        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Estado del error">
            @foreach ($estados as $opcion)
                <label @class([
                    'flex cursor-pointer items-center justify-center rounded-lg border px-2 py-2 text-sm font-semibold transition',
                    'border-blue-500 bg-blue-50 text-blue-700 ring-2 ring-blue-200' => $estado === $opcion,
                    'border-slate-300 text-slate-600 hover:bg-slate-50' => $estado !== $opcion,
                ])>
                    <input type="radio" wire:model.live="estado" value="{{ $opcion }}" class="sr-only">
                    {{ ucfirst($opcion) }}
                </label>
            @endforeach
        </div>
        @error('estado') <span class="block text-xs text-red-600">{{ $message }}</span> @enderror

        <label class="block">
            <span class="mb-1 block text-xs font-semibold text-slate-500">Nota (opcional, máx. 500)</span>
            <textarea wire:model="nota" rows="4" maxlength="500"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                      placeholder="Qué se hizo o por qué se ignora"></textarea>
            @error('nota') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <x-ui.button type="submit" :full-width="true" wire:loading.attr="disabled" wire:target="guardar">Guardar</x-ui.button>
        <p class="text-xs text-slate-400">Si un error resuelto vuelve a ocurrir, regresa a «nuevo» y se manda la alerta de regresión.</p>
    </form>
</div>
