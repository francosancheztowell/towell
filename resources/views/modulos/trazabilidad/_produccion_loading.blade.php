{{-- Skeleton mientras carga la pestaña Producción --}}
<section class="prod-area prod-area--crudo mb-10" aria-labelledby="prod-titulo-crudo-loading">
    <h3 id="prod-titulo-crudo-loading" class="text-lg md:text-xl font-bold text-slate-800 mb-4">
        Crudo
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3" id="produccion-loading">
        @for ($i = 0; $i < 4; $i++)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 overflow-hidden flex animate-pulse">
                <div class="w-1.5 bg-slate-200 shrink-0"></div>
                <div class="flex-1 p-3.5 space-y-3">
                    <div class="flex justify-between">
                        <div class="space-y-2">
                            <div class="h-7 w-20 bg-slate-200 rounded"></div>
                            <div class="h-3 w-24 bg-slate-200 rounded"></div>
                        </div>
                        <div class="h-6 w-24 bg-slate-200 rounded-full"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @for ($j = 0; $j < 4; $j++)
                            <div class="h-14 bg-white border border-slate-200 rounded-lg"></div>
                        @endfor
                    </div>
                    <div class="h-2 bg-slate-200 rounded-full"></div>
                </div>
            </div>
        @endfor
    </div>
</section>

<section class="prod-area prod-area--rollos-tenido" aria-labelledby="prod-titulo-rollos-tenido-loading">
    <h3 id="prod-titulo-rollos-tenido-loading" class="text-lg md:text-xl font-bold text-slate-800 mb-4">
        Rollos Teñido
    </h3>
</section>
