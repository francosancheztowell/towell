@extends('layouts.app')

@section('page-title', $titulo)

@section('content')
    @php
        $tipo = trim((string) $item->Tipo);
        $tipoTexto = preg_match('/^[1-4]$/', $tipo) ? 'Barra '.$tipo : $tipo;
    @endphp
    <div class="container mx-auto px-4 py-6 max-w-3xl">
        <a href="{{ route('atadores.calificar', ['no_julio' => $item->NoJulio, 'no_orden' => $item->NoProduccion]) }}"
            class="inline-flex items-center text-sm text-blue-700 hover:text-blue-900 mb-4">
            Volver al atado
        </a>

        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <span class="block text-xs text-gray-500">Telar</span>
                    <span class="font-semibold text-gray-800">{{ $item->NoTelarId ?? '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Tipo</span>
                    <span class="font-semibold text-gray-800">{{ $tipoTexto !== '' ? $tipoTexto : '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Julio</span>
                    <span class="font-semibold text-gray-800">{{ $item->NoJulio }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Orden</span>
                    <span class="font-semibold text-gray-800">{{ $item->NoProduccion }}</span>
                </div>
            </div>
        </div>

        @include('modulos.atadores.calificar-atadores._proceso-km', [
            'titulo' => $titulo,
            'prefijo' => $proceso,
            'registro' => $registro,
        ])
    </div>
@endsection

@push('scripts')
<script>
    const currentNoJulio = @json($item->NoJulio);
    const currentNoOrden = @json($item->NoProduccion);

    function guardarProcesoKm(prefijo) {
        const estado = document.getElementById(prefijo + '_estado');
        fetch(@json(route('atadores.save')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                action: 'km_' + prefijo,
                no_julio: currentNoJulio,
                no_orden: currentNoOrden,
                cve1: document.getElementById(prefijo + '_cve1')?.value || '',
                nombre1: document.getElementById(prefijo + '_nombre1')?.value || '',
                cve2: document.getElementById(prefijo + '_cve2')?.value || '',
                nombre2: document.getElementById(prefijo + '_nombre2')?.value || '',
                cve3: document.getElementById(prefijo + '_cve3')?.value || '',
                nombre3: document.getElementById(prefijo + '_nombre3')?.value || '',
                fecha_inicio: document.getElementById(prefijo + '_inicio')?.value || '',
                fecha_fin: document.getElementById(prefijo + '_fin')?.value || ''
            })
        })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo guardar' });
                    return;
                }
                if (estado) {
                    estado.classList.remove('hidden');
                    setTimeout(() => estado.classList.add('hidden'), 2000);
                }
                if (window.Swal) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Guardado', showConfirmButton: false, timer: 1600, timerProgressBar: true });
                }
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo guardar' });
            });
    }
</script>
@endpush
