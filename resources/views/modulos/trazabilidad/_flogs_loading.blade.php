{{-- Skeleton — pestaña Flogs --}}
<div class="flog-wrap" id="flogs-loading">
    <div class="flog-card flog-card--collapsible is-expanded">
        <div class="flog-card__head">
            <div class="flog-card__icon bg-slate-200"></div>
            <div class="h-4 w-48 bg-slate-200 rounded flex-1"></div>
            <div class="w-8 h-8 bg-slate-100 rounded border border-slate-200"></div>
        </div>
        <div class="flog-card__body">
            <div class="h-10 bg-slate-100 rounded mb-3"></div>
            <div class="grid grid-cols-7 gap-2">
                @for ($i = 0; $i < 14; $i++)
                    <div class="h-12 bg-slate-50 rounded border border-slate-100"></div>
                @endfor
            </div>
        </div>
    </div>
    <div class="flog-card">
        <div class="flog-card__head">
            <div class="flog-card__icon bg-slate-200"></div>
            <div class="h-4 w-40 bg-slate-200 rounded"></div>
        </div>
        <div class="flog-card__body flog-meta-tables">
            @foreach (['Empaques', 'Etiquetas'] as $titulo)
                <div class="flog-meta-table-wrap space-y-2">
                    <div class="h-3 w-24 bg-slate-200 rounded"></div>
                    <div class="h-16 bg-slate-50 rounded border border-slate-100"></div>
                </div>
            @endforeach
        </div>
    </div>
</div>
