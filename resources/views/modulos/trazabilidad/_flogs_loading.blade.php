{{-- Skeleton compacto — pestaña Flogs --}}
<div class="flog-grid-top animate-pulse" id="flogs-loading">
    @for ($c = 0; $c < 2; $c++)
        <div class="flog-card">
            <div class="flog-card__head">
                <div class="flog-card__icon bg-slate-200"></div>
                <div class="h-3 w-40 bg-slate-200 rounded"></div>
            </div>
            <div class="flog-card__body flog-card__body--2col">
                @for ($col = 0; $col < 2; $col++)
                    <div class="flog-col space-y-0">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="flog-fila">
                                <div class="h-2 w-16 bg-slate-200 rounded"></div>
                                <div class="h-2.5 w-full bg-slate-100 rounded ml-auto"></div>
                            </div>
                        @endfor
                    </div>
                @endfor
            </div>
        </div>
    @endfor
</div>
