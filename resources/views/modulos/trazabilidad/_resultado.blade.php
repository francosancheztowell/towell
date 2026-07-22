@unless ($hayFiltro)
    <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 md:p-14 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-magnifying-glass text-blue-500 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">Selecciona al menos un filtro para ver la trazabilidad</p>
        <p class="text-slate-400 text-sm mt-1">
            Puedes empezar por Artículo, Tamaño o Mes, y luego afinar con un Flog.
        </p>
    </div>
@else
    @php $resumen = $resumenFlog ?? []; @endphp

    <section aria-labelledby="titulo-resumen-trazabilidad">
        <div class="mb-3">
            <h2 id="titulo-resumen-trazabilidad" class="text-md font-bold text-slate-800">Resumen</h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-2.5 items-stretch">
            @include('modulos.trazabilidad.resumen._flog', ['resumen' => $resumen])
            @include('modulos.trazabilidad.resumen._avance', ['resumen' => $resumen])
            @include('modulos.trazabilidad.resumen._trazabilidad', ['resumen' => $resumen])
            @include('modulos.trazabilidad.resumen._ventas')
        </div>
    </section>
@endunless
