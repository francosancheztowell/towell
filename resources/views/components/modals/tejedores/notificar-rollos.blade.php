<div id="modalNotificarRollos" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-4">
        <h2 class="text-lg font-semibold mb-3">Notificar rollos</h2>

        {{-- contenido del modal (inputs, selects, etc.) --}}
        <div class="mb-3">
            <label class="block text-sm font-medium">Telar</label>
            <select id="selectTelarRollos" class="w-full border rounded px-2 py-1 text-sm"></select>
        </div>

        <div id="detalleTelarRollos" class="text-sm mb-4">
            {{-- detalles vía AJAX --}}
        </div>

        <div class="flex justify-end gap-2">
            <button type="button"
                onclick="cerrarModalNotificarRollos()"
                class="px-3 py-1 text-sm border rounded">
                Cancelar
            </button>
            <button type="button"
                onclick="enviarNotificacionRollos()"
                class="px-3 py-1 text-sm rounded bg-blue-600 text-white">
                Notificar
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let registroRollosId = null;

    window.abrirModalNotificarRollos = function () {
        cargarTelaresRollos();
        const modal = document.getElementById('modalNotificarRollos');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    window.cerrarModalNotificarRollos = function () {
        const modal = document.getElementById('modalNotificarRollos');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    async function cargarTelaresRollos() {
        const resp = await fetch('{{ route('notificar.mont.rollos.telares') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();
        const select = document.getElementById('selectTelarRollos');
        select.innerHTML = '<option value="">Seleccione...</option>';

        data.telares.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.no_telar;
            opt.textContent = `${t.no_telar} - ${t.tipo}`;
            select.appendChild(opt);
        });

        select.onchange = () => cargarDetalleRollos(select.value);
    }

    async function cargarDetalleRollos(noTelar) {
        if (!noTelar) return;

        const resp = await fetch(`{{ route('notificar.mont.rollos.detalle') }}?no_telar=${encodeURIComponent(noTelar)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();
        registroRollosId = data.detalles.id;

        document.getElementById('detalleTelarRollos').innerHTML = `
            <p><strong>Cuenta:</strong> ${data.detalles.cuenta}</p>
            <p><strong>Calibre:</strong> ${data.detalles.calibre}</p>
            <p><strong>Metros:</strong> ${data.detalles.metros}</p>
        `;
    }

    async function enviarNotificacionRollos() {
        if (!registroRollosId) return;

        const resp = await fetch('{{ route('notificar.mont.rollos.notificar') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: registroRollosId })
        });

        const data = await resp.json();

        if (data.success) {
            alert('Notificado correctamente a las ' + data.horaParo);
            cerrarModalNotificarRollos();
        } else {
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    }
</script>
@endpush
