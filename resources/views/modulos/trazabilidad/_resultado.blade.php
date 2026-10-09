@unless ($hayFiltro)
    <flux:card class="flex flex-col items-center gap-2 border-dashed px-6 py-14 text-center">
        <flux:icon.magnifying-glass class="size-8 text-blue-500" />
        <flux:heading size="lg" level="2">Elige un Flog, artículo o tamaño</flux:heading>
        <flux:text class="max-w-md">Con cualquiera de los tres filtros ves el pedido, el programa de tejido y su recorrido por las áreas.</flux:text>
    </flux:card>
@else
    @php $resumen = $resumenFlog ?? []; @endphp

    <section aria-labelledby="titulo-resumen-trazabilidad">
        <h2 id="titulo-resumen-trazabilidad" class="sr-only">Resumen</h2>

        <div class="grid grid-cols-1 items-stretch gap-3 lg:grid-cols-2">
            @include('modulos.trazabilidad.resumen._flog', ['resumen' => $resumen])
            @include('modulos.trazabilidad.resumen._avance', ['resumen' => $resumen])
            @include('modulos.trazabilidad.resumen._trazabilidad', ['resumen' => $resumen])
            @include('modulos.trazabilidad.resumen._ventas')
        </div>
    </section>
@endunless
