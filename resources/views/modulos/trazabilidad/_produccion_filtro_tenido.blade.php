{{-- Filtro compacto de nombre color (solo Rollos Teñido). Vacío = todos. --}}
@php
    $opcionesTenido = collect($opcionesNombrecolorTenido ?? []);
    $nombresColorSel = collect(explode('|', (string) ($filtros['nombrecolor'] ?? '')))
        ->map(fn ($v) => trim($v))->filter()->values()->all();
@endphp

@if ($opcionesTenido->isNotEmpty())
    <div class="prod-filtro-tenido mb-4 max-w-xl">
        <label for="filtro-nombrecolor-tenido" class="block text-xs font-semibold text-slate-500 mb-1">
            Color teñido
            <span class="font-normal text-slate-400">· todos por defecto</span>
        </label>
        <select id="filtro-nombrecolor-tenido" multiple class="w-full">
            @foreach ($opcionesTenido as $opt)
                <option value="{{ $opt }}" @selected(in_array($opt, $nombresColorSel, true))>{{ $opt }}</option>
            @endforeach
        </select>
        <p class="text-[11px] text-slate-400 mt-1">
            {{ $opcionesTenido->count() }} colores disponibles. Elige uno o más para acotar rollos teñidos.
        </p>
    </div>
@endif
