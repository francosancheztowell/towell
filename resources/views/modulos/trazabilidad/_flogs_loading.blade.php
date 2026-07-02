{{-- Skeleton mientras carga la pestaña Flogs --}}
<div class="space-y-4 animate-pulse" id="flogs-loading">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="h-10 bg-blue-100/80 border-b border-blue-200"></div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            @for ($i = 0; $i < 10; $i++)
                <div class="space-y-2 border-b border-dashed border-slate-200 pb-3">
                    <div class="h-3 w-32 bg-slate-200 rounded"></div>
                    <div class="h-4 w-full max-w-xs bg-slate-100 rounded"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
