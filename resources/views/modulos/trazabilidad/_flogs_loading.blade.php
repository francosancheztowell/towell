{{-- Skeleton — pestaña Flogs --}}
<div class="flog-wrap animate-pulse" id="flogs-loading">
    <div class="flog-card">
        <div class="flog-card__head">
            <div class="flog-card__icon bg-slate-200"></div>
            <div class="h-4 w-48 bg-slate-200 rounded"></div>
        </div>
        <div class="flog-card__body">
            <div class="flog-fields">
                @for ($i = 0; $i < 8; $i++)
                    <div class="flog-campo space-y-2">
                        <div class="h-3 w-24 bg-slate-200 rounded"></div>
                        <div class="h-4 w-full bg-slate-100 rounded"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
    <div class="flog-grid-2 flog-grid-2--empaque">
        @for ($c = 0; $c < 2; $c++)
            <div class="flog-card">
                <div class="flog-card__head">
                    <div class="flog-card__icon bg-slate-200"></div>
                    <div class="h-4 w-28 bg-slate-200 rounded"></div>
                </div>
                <div class="flog-card__body">
                    <div class="rounded-lg bg-slate-100 min-h-[220px]"></div>
                </div>
            </div>
        @endfor
    </div>
</div>
