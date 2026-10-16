@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Kardex MP y Producción en Proceso" />
@endsection

@php
    $tabs = [
        'resumen' => 'Resumen y flujo',
        'kardex' => 'Kardex por almacén',
        'crudo' => 'Qué se tejió (Crudo)',
        'cuadre' => 'Cuadre 3 fuentes',
        'correspondencia' => 'Correspondencia E↔S',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-[1500px] space-y-4 px-4 py-4 md:px-6" data-kardex
     data-url-periodos="{{ route('kardex.datos.periodos') }}" data-url-libro="{{ route('kardex.datos.libro') }}">

    <div class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm font-semibold text-ink">Mes
            <select data-kardex-periodo-select disabled
                    class="min-h-touch rounded-md border border-line bg-white px-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                <option>Cargando…</option>
            </select>
        </label>
        <p class="text-sm text-ink-muted" data-kardex-periodo></p>
        <x-ui.badge tone="info" icon="fa-book" class="ms-auto"><span data-kardex-fuente>Cargando libro…</span></x-ui.badge>
    </div>

    {{-- Pestañas --}}
    <div class="-mx-4 overflow-x-auto border-b border-line px-4 md:mx-0 md:px-0">
        <div role="tablist" aria-label="Secciones del kardex" class="flex min-w-max gap-1">
            @foreach ($tabs as $clave => $texto)
                <button type="button" role="tab" id="kardex-tab-{{ $clave }}" aria-controls="kardex-panel-{{ $clave }}"
                        aria-selected="false" tabindex="-1" data-kardex-tab="{{ $clave }}"
                        class="group inline-flex min-h-touch items-center gap-2 border-b-2 border-transparent px-4 text-sm font-semibold uppercase tracking-wide text-ink-muted transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary aria-selected:border-primary aria-selected:text-blue-900">
                    <span class="inline-flex size-6 items-center justify-center rounded-full bg-slate-100 text-caption text-slate-600 group-aria-selected:bg-primary group-aria-selected:text-white">{{ $loop->iteration }}</span>
                    {{ $texto }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- 1. Resumen y flujo --}}
    <div role="tabpanel" id="kardex-panel-resumen" aria-labelledby="kardex-tab-resumen" class="space-y-4" hidden>
        <div data-kardex-kpis></div>
        <section class="rounded-xl border border-line bg-surface p-4 shadow-sm md:p-5">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-blue-900">Flujo de la cadena (kg)</h2>
            <p class="mb-2 text-caption text-ink-muted">Pasa el cursor sobre una caja o flecha para resaltar su recorrido; haz clic para ver el detalle.
                Urdido, julios, tejido y crudo salen del libro; materia prima, pretejidos y goma todavía son simulados.</p>
            <div data-kardex-flujo></div>
            <div class="mt-3 rounded-r-lg border-l-4 border-primary bg-primary-soft px-4 py-3 text-sm leading-relaxed text-slate-700">
                Todas las flechas son <b>netas</b> (envío − devolución), por eso cuadran con las columnas del libro. Dentro de cada caja:
                <b>inicial (física del corte anterior) → final (física del corte)</b>. En los pretejidos, la línea <b>«± recuentos / regresos»</b>
                son los recuentos de trama en telares y los regresos menores; con ella las tres cajas de trama cuadran:
                inicial + MP + MPBB − salida a crudo ± recuentos = final. Los flujos menores (compras, reproceso, recuentos del corte) no se dibujan;
                el cuadre exacto por almacén está en la pestaña Cuadre.
            </div>
        </section>
        <section class="rounded-xl border border-line bg-surface p-4 shadow-sm md:p-5">
            <h2 class="flex flex-wrap items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-900">Producción diaria por proceso (kg por día) <x-ui.badge tone="warning">Simulado</x-ui.badge></h2>
            <div class="mt-2 flex flex-wrap gap-4 text-caption text-ink-muted">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#2a78d6]"></span>Urdido (julios urdidos)</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#eb6834]"></span>Engomado (julios tela)</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#1baf7a]"></span>Tejido (telares, sin rasurado)</span>
            </div>
            <div class="relative mt-3 h-72"><canvas data-kardex-grafica-dia aria-label="Producción diaria por proceso" role="img"></canvas></div>
        </section>
    </div>

    {{-- 2. Kardex por almacén --}}
    <div role="tabpanel" id="kardex-panel-kardex" aria-labelledby="kardex-tab-kardex" hidden>
        <div class="rounded-r-lg border-l-4 border-primary bg-primary-soft px-4 py-3 text-sm text-slate-700">
            <b>Kardex de producción — réplica de la hoja REs del libro</b> (cifras amarradas, tal cual el libro). Haz clic en cualquier renglón
            para el desglose por artículo, y dentro del desglose en un artículo para ver sus movimientos uno por uno. Los encabezados ordenan la tabla.
        </div>
        <div data-kardex-secciones></div>
    </div>

    {{-- 3. Qué se tejió (Crudo) --}}
    <div role="tabpanel" id="kardex-panel-crudo" aria-labelledby="kardex-tab-crudo" class="space-y-4" hidden>
        <div data-kardex-crudo-kpis></div>
        <x-ui.alert type="warning" :dismissible="false" message="El detalle por artículo, telar y segundas es simulado: sale del archivo de Entradas a Crudo, que todavía no se carga. Del libro vienen la producción del mes y la columna «kg KdxCrudo»." />
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Artículos tejidos — piezas, kg, tamaños, telares y segundas</h2>
        <div class="flex flex-wrap items-end gap-3 rounded-lg border border-line bg-surface p-3">
            <label class="flex flex-col gap-1 text-caption font-semibold text-ink-muted">Buscar
                <input type="search" data-kardex-filtro="busqueda" placeholder="Código o nombre" class="min-h-touch w-56 rounded-md border border-line px-3 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
            </label>
            @foreach (['tamano' => 'Tamaño', 'telar' => 'Telar', 'salon' => 'Salón'] as $filtro => $texto)
                <label class="flex flex-col gap-1 text-caption font-semibold text-ink-muted">{{ $texto }}
                    <select data-kardex-filtro="{{ $filtro }}" class="min-h-touch w-40 rounded-md border border-line bg-white px-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="">Todos</option>
                    </select>
                </label>
            @endforeach
        </div>
        <div data-kardex-crudo-sumas></div>
        <div data-kardex-crudo-articulos></div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <h3 class="text-sm font-bold text-ink">Producción diaria y % de segundas</h3>
                <div class="relative mt-2 h-[340px]"><canvas data-kardex-grafica-crudo-dia role="img" aria-label="Producción diaria y porcentaje de segundas"></canvas></div>
            </section>
            <section class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <h3 class="text-sm font-bold text-ink">kg tejidos por telar <span class="font-normal text-ink-muted">(en rojo, los 3 con más segundas)</span></h3>
                <div class="relative mt-2 h-[340px]"><canvas data-kardex-grafica-telar role="img" aria-label="Kilos tejidos por telar"></canvas></div>
            </section>
        </div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Calidad por salón — primeras vs segundas (kg)</h2>
        <div data-kardex-crudo-salones></div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Detalle por telar — con su % de segundas</h2>
        <div data-kardex-crudo-telares></div>
    </div>

    {{-- 4. Cuadre 3 fuentes --}}
    <div role="tabpanel" id="kardex-panel-cuadre" aria-labelledby="kardex-tab-cuadre" class="space-y-4" hidden>
        <x-ui.alert type="warning" :dismissible="false" message="Simulado: el cuadre necesita el respaldo del inventario del ERP y el archivo de movimientos del mes." />
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Verificación de la base: hojas Kdx del libro vs archivo de movimientos (kg)</h2>
        <div data-kardex-verificacion></div>
        <div class="rounded-r-lg border-l-4 border-primary bg-primary-soft px-4 py-3 text-sm text-slate-700">
            <b>Sí cuadran.</b> Cada columna de las hojas Kdx se reproduce desde el archivo de movimientos con la regla indicada.
            Las únicas diferencias son las marcadas, y todas tienen causa identificada (corte de hora del respaldo o el ajuste propio del libro).
        </div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Cuadre por almacén (kg)</h2>
        <div data-kardex-cuadre></div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="space-y-3 rounded-xl border border-line bg-surface p-4 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Lectura del cuadre</h2>
                <div class="rounded-r-lg border-l-4 border-primary bg-primary-soft px-4 py-3 text-sm leading-relaxed text-slate-700">
                    <b>La cadena completa cuadra:</b> inicial (física disponible del corte anterior) + movimientos del mes + ajuste implícito =
                    <b>final ERP (física disponible del corte)</b>, y ese final coincide con el libro en todos los almacenes.
                    La meta de MP/MPBB viene del archivo <b>KDX MP CONSOL</b>.
                </div>
                <div class="rounded-r-lg border-l-4 border-amber-400 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900">
                    <b>El corte de hora — la causa de las diferencias vs el final ERP:</b> los respaldos son fotos tomadas a media tarde, pero
                    el archivo de movimientos trae los días completos. Lo que se produce o consume después de la hora del respaldo aparece en los
                    movimientos pero todavía no en el inventario. La columna «Movs tras corte / recuentos» junta ese efecto + los recuentos de
                    trama de los PTE. <b>Solución: correr el respaldo al cierre real del día (23:59 del último día o madrugada del día 1)</b>.
                </div>
                <div class="rounded-r-lg border-l-4 border-amber-400 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900">
                    <b>Entradas/salidas brutas vs neteadas:</b> el movimiento trae los flujos brutos (ida y devolución por separado); el libro los
                    presenta neteados. Ejemplo MP → Urdido: 202,671.54 de ida − 65,219.14 de regreso ≈ los 137,115 de la columna MPKg del libro.
                </div>
            </section>
            <section class="space-y-3 rounded-xl border border-line bg-surface p-4 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Reglas de lectura aplicadas</h2>
                <ul class="list-disc space-y-1 rounded-r-lg border-l-4 border-primary bg-primary-soft py-3 pe-4 ps-8 text-sm text-slate-700">
                    <li>El export trae miles con coma («1,000.00»); se limpian antes de sumar.</li>
                    <li>Órdenes de cuarentena: netean a cero y no traen tipo E/S; se excluyen del kardex.</li>
                    <li>Cantidad = Peso Estimado = kg en todos los almacenes de este tramo.</li>
                    <li>Transferencias: cada una aparece como S en el almacén que entrega y E en el que recibe; cada almacén usa solo sus renglones.</li>
                    <li>Movimientos internos (mismo almacén origen y destino) se muestran aparte y netean ≈ 0.</li>
                </ul>
                <h3 class="pt-2 text-sm font-bold text-ink">Columnas de las hojas Kdx (totales del libro)</h3>
                <div data-kardex-columnas-kdx></div>
            </section>
        </div>
        <section class="rounded-xl border border-line bg-surface p-4 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Alimentación mensual del kardex — archivos a subir cada mes</h2>
            <ol class="mt-3 list-decimal space-y-1 ps-6 text-sm text-slate-700">
                <li><b>Respaldo del cierre del mes anterior</b> (Inventario Disponible del ERP): el inicial del mes, columna <b>Física disponible</b>.</li>
                <li><b>Respaldo del cierre del mes actual</b>: el final ERP contra el que cierra el kardex (será el inicial del mes siguiente).</li>
                <li><b>MOV FINAL del mes</b> (export completo de movimientos). Si se puede, incluir los movimientos de <b>ajuste/recuento</b>.</li>
                <li><b>Kardex Prod Proc del mes</b> (libro con hojas Kdx + hoja REs): la meta oficial, excepto materia prima.</li>
                <li><b>KDX MP CONSOL del mes</b>: la referencia oficial de A-MP y A-MPBB.</li>
                <li><b>ENTRADAS A CRUDO del mes</b> (hojas «Entradas a Crudo» + «Retazo»): la fuente de la pestaña Qué se tejió.</li>
            </ol>
        </section>
        <p class="text-caption text-ink-muted" data-kardex-fuentes></p>
    </div>

    {{-- 5. Correspondencia E↔S --}}
    <div role="tabpanel" id="kardex-panel-correspondencia" aria-labelledby="kardex-tab-correspondencia" class="space-y-4" hidden>
        <x-ui.alert type="warning" :dismissible="false" message="Simulado: la correspondencia E↔S necesita el archivo de movimientos del mes." />
        <div data-kardex-corresp-kpis></div>
        <div class="rounded-r-lg border-l-4 border-primary bg-primary-soft px-4 py-3 text-sm text-slate-700" data-kardex-corresp-encabezado></div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Traspasos entre almacenes — lo que salió del origen vs lo que entró al destino (kg)</h2>
        <p class="text-sm text-ink-muted">
            Cada traspaso se registra dos veces: como <b>salida (S)</b> en el almacén que entrega y como <b>entrada (E)</b> en el que recibe.
            Aquí se comparan los dos lados de cada par origen → destino, con la salida atribuida al <b>destino real</b>. Haz clic en un renglón para ver ambos lados.
        </p>
        <div data-kardex-corresp-traspasos></div>
        <div data-kardex-corresp-redirigidos hidden></div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-900">Procesos — lo consumido hacia cada almacén vs la producción que entró (kg)</h2>
        <p class="text-sm text-ink-muted">
            En los procesos la entrada <b>no</b> es espejo de la salida: el material se transforma y cambia de peso (goma, agua que se evapora,
            peso estimado del producto). Se compara el <b>consumo con destino a cada almacén</b> contra la <b>producción que entró</b>.
        </p>
        <div class="grid gap-4 lg:grid-cols-2" data-kardex-corresp-procesos></div>
        <p class="text-caption text-ink-muted">Base: archivo de movimientos completo del mes (los dos lados de cada traspaso). Las transacciones de ajuste/recuento no son traspasos y viven en la pestaña Cuadre.</p>
    </div>
</div>

<x-ui.modal-base id="kardex-modal" title="Detalle" size="2xl" :closeOnBackdrop="true">
    <div class="max-h-[75vh] overflow-y-auto" id="kardex-modal-cuerpo"></div>
</x-ui.modal-base>
@endsection

@push('scripts')
    @vite('resources/js/modulos/kardex/index.ts')
@endpush
