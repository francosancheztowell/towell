<div>
    <label class="block text-sm font-medium mb-2">Seleccionar Telar</label>
    <select name="telar_operador" id="telarOperador" class="w-full md:w-60 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <option value="" disabled selected>Selecciona un Telar</option>
        @foreach ($telares ?? [] as $telar)
            <option value="{{ $telar->NoTelarId }}">
                {{ $telar->NoTelarId }}
            </option>
        @endforeach
    </select>

    <!-- Filtro Registro en Proceso -->
    <div id="filtroOrdenContainer" class="hidden mt-3">
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="filtroSoloConOrden" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 cursor-pointer">
            <span class="text-xs font-medium text-gray-700">Filtrar por registro en proceso</span>
        </label>
        <div id="msgValidacionOrden" class="hidden text-xs text-red-600 font-medium flex items-center gap-1 mt-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span></span>
        </div>
    </div>
</div>