{{-- Línea de filtros --}}
<form method="GET" action="{{ route('trazabilidad.index') }}" id="form-filtros"
      class="bg-white border border-slate-200 rounded-2xl shadow-sm p-2.5 md:p-3 mb-3">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-3">
        <div>
            <label for="filtro-flog" class="block text-xs font-semibold text-slate-500 mb-0.5">Flog</label>
            <select name="flog" id="filtro-flog" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todos</option>
                @foreach ($opcionesFlog as $opt)
                    <option value="{{ $opt }}" @selected(($filtros['flog'] ?? '') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filtro-articulo" class="block text-xs font-semibold text-slate-500 mb-0.5">Artículo</label>
            <select name="articulo" id="filtro-articulo" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todos</option>
                @foreach ($opcionesArticulo as $opt)
                    <option value="{{ $opt['codigo'] }}" @selected(($filtros['articulo'] ?? '') === $opt['codigo'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filtro-tamano" class="block text-xs font-semibold text-slate-500 mb-0.5">Tamaño</label>
            <select name="tamano" id="filtro-tamano" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todos</option>
                @foreach ($opcionesTamano as $opt)
                    <option value="{{ $opt }}" @selected(($filtros['tamano'] ?? '') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <input type="hidden" name="mes" id="filtro-mes" value="{{ $filtros['mes'] ?? '' }}">
    {{-- Se conserva para las vistas de detalle y la exportación. --}}
    <input type="hidden" name="metrica" id="filtro-metrica" value="{{ $metrica ?? 'cantidad' }}">
</form>
