{{--
    Sección de la galería (se usa con @component para que el código de ejemplo pueda ir en
    @verbatim). Variables: $id, $titulo, $ds (opcional), $descripcion y $codigo (slots opcionales).
--}}
<section id="{{ $id }}" class="scroll-mt-24 rounded-xl border border-line bg-surface p-4 shadow-sm md:p-6" aria-labelledby="{{ $id }}-titulo">
    <header class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h2 id="{{ $id }}-titulo" class="text-lg font-bold text-ink">{{ $titulo }}</h2>
        @if (! empty($ds))<x-ui.badge tone="primary">{{ $ds }}</x-ui.badge>@endif
    </header>
    @isset($descripcion)
        <div class="mb-4 max-w-3xl text-sm text-ink-muted">{{ $descripcion }}</div>
    @endisset
    <div class="space-y-4">{{ $slot }}</div>
    @isset($codigo)
        <details class="mt-4 rounded-lg bg-slate-900 text-slate-100">
            <summary class="flex min-h-touch cursor-pointer items-center px-4 text-sm font-semibold">Código</summary>
            <pre class="overflow-x-auto px-4 pb-4 text-caption leading-relaxed"><code>{{ trim((string) $codigo) }}</code></pre>
        </details>
    @endisset
</section>
