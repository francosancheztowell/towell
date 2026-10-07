{{-- Un módulo del árbol y, debajo, sus hijos. $hijos = $modulos agrupados por Dependencia (orden del padre). --}}
@php
    $nivel = (int) $m->Nivel;
    $descendientes = $hijos->get($m->orden, collect());
    $n = (int) ($conAcceso[$m->idrol] ?? 0);
    // Nivel 1 abierto (se ven sus submódulos); el resto cerrado hasta que se pida.
    $abierto = $nivel === 1;
@endphp
<li data-modulo-item>
    <div class="group/nodo flex items-center gap-0.5 rounded-lg pr-1 transition-colors duration-150 hover:bg-white has-[[aria-current=true]]:bg-blue-50 has-[[aria-current=true]]:hover:bg-blue-50"
         style="padding-left: {{ ($nivel - 1) * 1.125 }}rem">
        @if ($descendientes->isNotEmpty())
            <button type="button" data-modulo-plegar aria-expanded="{{ $abierto ? 'true' : 'false' }}"
                    aria-label="Mostrar u ocultar submódulos de {{ $m->modulo }}"
                    class="grid size-8 shrink-0 place-items-center rounded-md text-slate-400 transition-colors hover:bg-slate-200/70 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-blue-500">
                <i class="fa-solid fa-chevron-right text-[0.625rem] transition-transform duration-150 in-aria-expanded:rotate-90" aria-hidden="true"></i>
            </button>
        @else
            <span class="size-8 shrink-0" aria-hidden="true"></span>
        @endif

        <button type="button" data-modulo-nodo aria-current="false"
                data-key="{{ $m->idrol }}"
                data-orden="{{ $m->orden }}"
                data-modulo="{{ $m->modulo }}"
                data-nivel="{{ $nivel }}"
                data-dependencia="{{ $m->Dependencia }}"
                data-ruta="{{ $m->Ruta }}"
                data-acceso="{{ (int) $m->acceso }}"
                data-crear="{{ (int) $m->crear }}"
                data-modificar="{{ (int) $m->modificar }}"
                data-eliminar="{{ (int) $m->eliminar }}"
                data-reigstrar="{{ (int) $m->reigstrar }}"
                data-con-acceso="{{ $n }}"
                class="flex min-h-9 min-w-0 flex-1 items-center gap-2 rounded-md py-1.5 pl-1 text-left text-sm focus-visible:outline-2 focus-visible:outline-blue-500 {{ $nivel === 1 ? 'font-semibold text-slate-900' : 'text-slate-700' }} aria-[current=true]:font-semibold aria-[current=true]:text-blue-700">
            <span class="min-w-0 flex-1 truncate" data-modulo-nombre>{{ $m->modulo }}</span>
            @unless ($m->Ruta)
                <span class="size-1.5 shrink-0 rounded-full bg-amber-500" title="Sin ruta"><span class="sr-only">Sin ruta</span></span>
            @endunless
            <span class="shrink-0 font-mono text-caption tabular-nums text-slate-500">{{ $m->orden }}</span>
            <span class="min-w-8 shrink-0 rounded-full px-1.5 text-center text-caption font-medium tabular-nums {{ $n ? 'bg-slate-200/70 text-slate-700' : 'text-slate-400' }}"
                  data-modulo-conteo title="{{ $n }} {{ $n === 1 ? 'usuario' : 'usuarios' }} con acceso">{{ $n }}</span>
        </button>
    </div>

    @if ($descendientes->isNotEmpty())
        <ul data-modulo-hijos class="{{ $abierto ? '' : 'hidden' }}">
            @foreach ($descendientes as $hijo)
                @include('modulos.gestion-modulos._nodo', ['m' => $hijo])
            @endforeach
        </ul>
    @endif
</li>
