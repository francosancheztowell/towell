{{-- Skeleton — pestaña Flogs (sin animación en galería de imágenes) --}}
<div class="flog-wrap" id="flogs-loading">
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
    <div class="flog-card">
        <div class="flog-card__head">
            <div class="flog-card__icon bg-slate-200"></div>
            <div class="h-4 w-40 bg-slate-200 rounded"></div>
        </div>
        <div class="flog-card__body flog-visual-layout">
            <div class="flog-visual-meta space-y-3">
                @for ($i = 0; $i < 3; $i++)
                    <div class="space-y-2">
                        <div class="h-3 w-20 bg-slate-200 rounded"></div>
                        <div class="h-4 w-full bg-slate-100 rounded"></div>
                    </div>
                @endfor
            </div>
            <div class="flog-visual-gallery flog-visual-gallery--solo flex-1">
                <div class="flog-visual-frame min-h-[320px] bg-white border border-slate-200 rounded-lg"></div>
            </div>
        </div>
    </div>
</div>
