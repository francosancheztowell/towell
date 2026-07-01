{{-- Tarjeta agrupada por máquina (Rollos Teñido) — click abre modal con detalle --}}
<button type="button"
        class="prod-rollos-maquina-card prod-card-v2 text-left w-full cursor-pointer transition-shadow hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        data-maquina="{{ $m['titulo'] }}"
        data-filas='@json($m['filas'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)'>
    <div class="prod-card-v2__accent prod-card-v2__accent--rollos" aria-hidden="true"></div>

    <div class="flex flex-col flex-1 p-4 min-w-0">
        <div class="flex items-start justify-between gap-2 mb-2.5">
            <div class="min-w-0">
                <div class="text-2xl font-extrabold text-slate-800 leading-none tracking-tight">
                    {{ $m['titulo'] }}
                </div>
                <div class="text-sm font-semibold text-slate-500 mt-1.5">
                    {{ $m['ordenes'] }} {{ $m['ordenes'] === 1 ? 'orden' : 'órdenes' }}
                </div>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 text-[10px] font-medium px-2 py-0.5 shrink-0">
                <i class="fa-solid fa-table-list text-[9px]"></i>
                Ver detalle
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2 mt-auto">
            <div class="prod-stat-box col-span-2"
                 title="Suma de Cantidad y Peso en TrazaProduccion (área Rollos Teñido) para esta máquina.">
                <div class="prod-stat-label">Producido</div>
                <div class="prod-stat-value prod-stat-value--sm">{{ number_format($m['cantidad']) }} Pzas</div>
                <div class="prod-stat-value prod-stat-value--sm text-blue-800">{{ number_format($m['peso'], 2) }} Kg</div>
            </div>
        </div>
    </div>
</button>
