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
</div>