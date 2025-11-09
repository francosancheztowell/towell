{{-- @extends('layouts.app')

@section('title', 'Tel · Telares por Operador · Crear')
@section('page-title')
Nuevo Operador
@endsection

@section('content')
<div class="container mx-auto px-3 md:px-6 py-4">
    @if($errors->any())
        <div class="rounded bg-red-100 text-red-800 px-3 py-2 mb-3">
            <ul class="mb-0 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tel-telares-operador.store') }}" class="bg-white rounded shadow p-4 max-w-xl">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Número de Empleado</label>
                <select id="empleadoSelectCreate" name="numero_empleado" class="w-full border rounded px-3 py-2" required>
                    <option value="" disabled {{ old('numero_empleado') ? '' : 'selected' }}>Selecciona empleado</option>
                    @foreach(($usuarios ?? []) as $u)
                        <option value="{{ $u->numero_empleado }}" data-nombre="{{ $u->nombre }}" data-turno="{{ $u->turno }}" {{ old('numero_empleado') == $u->numero_empleado ? 'selected' : '' }}>
                            {{ $u->numero_empleado }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre</label>
                <input type="text" id="nombreCreate" name="nombreEmpl" value="{{ old('nombreEmpl') }}" class="w-full border rounded px-3 py-2 bg-gray-50" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">No. Telar</label>
                <select id="createTelarPage" name="NoTelarId" class="w-full border rounded px-3 py-2" required>
                    <option value="" disabled {{ old('NoTelarId') ? '' : 'selected' }}>Selecciona telar</option>
                    @foreach(($telares ?? []) as $tel)
                        <option value="{{ $tel->NoTelarId }}" data-salon="{{ $tel->SalonTejidoId }}" {{ old('NoTelarId') == $tel->NoTelarId ? 'selected' : '' }}>
                            {{ $tel->NoTelarId }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno</label>
                <input type="text" id="turnoCreate" name="Turno" value="{{ old('Turno') }}" class="w-full border rounded px-3 py-2 bg-gray-50" readonly>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Salón Tejido Id</label>
                <input type="text" id="createSalonPage" name="SalonTejidoId" value="{{ old('SalonTejidoId') }}" class="w-full border rounded px-3 py-2" required>
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <a href="{{ route('tel-telares-operador.index') }}" class="px-4 py-2 rounded bg-gray-200">Cancelar</a>
            <button class="px-4 py-2 rounded bg-green-600 text-white">Guardar</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function(){
        const telarSel = document.getElementById('createTelarPage');
        const salonInp = document.getElementById('createSalonPage');
        if (telarSel && salonInp) {
            telarSel.addEventListener('change', () => {
                const opt = telarSel.options[telarSel.selectedIndex];
                salonInp.value = opt ? (opt.getAttribute('data-salon') || '') : '';
            });
            // Si ya hay seleccionado, inicializar
            const opt = telarSel.options[telarSel.selectedIndex];
            if (opt && !salonInp.value) salonInp.value = opt.getAttribute('data-salon') || '';
        }

        // Vincular empleado -> nombre y turno
        const empSel = document.getElementById('empleadoSelectCreate');
        const nombre = document.getElementById('nombreCreate');
        const turno = document.getElementById('turnoCreate');
        if (empSel && nombre && turno) {
            const sync = () => {
                const op = empSel.options[empSel.selectedIndex];
                nombre.value = op ? (op.getAttribute('data-nombre') || '') : '';
                turno.value = op ? (op.getAttribute('data-turno') || '') : '';
            };
            empSel.addEventListener('change', sync);
            // Inicializar si ya hay valor
            sync();
        }
    })();
</script>
@endpush --}}
