@php
    $bloqueado = in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado']);
    $fechaInicio = $registro?->FechaInicio ? \Carbon\Carbon::parse($registro->FechaInicio)->format('Y-m-d\TH:i') : '';
    $fechaFin = $registro?->FechaFin ? \Carbon\Carbon::parse($registro->FechaFin)->format('Y-m-d\TH:i') : '';
@endphp
<div class="bg-white rounded-lg shadow-md p-4">
    <h3 id="titulo-{{ $prefijo }}" class="text-xl font-bold text-center text-gray-800 mb-1 border-b pb-2">{{ $titulo }}</h3>
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_inicio">Fecha inicio</label>
                <input type="datetime-local" id="{{ $prefijo }}_inicio" value="{{ $fechaInicio }}"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    onchange="autoGuardarKm('{{ $prefijo }}')"
                    @disabled($bloqueado)>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_fin">Fecha fin</label>
                <input type="datetime-local" id="{{ $prefijo }}_fin" value="{{ $fechaFin }}"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    onchange="autoGuardarKm('{{ $prefijo }}')"
                    @disabled($bloqueado)>
            </div>
        </div>
        @foreach ([1, 2, 3] as $n)
            @php
                $cveGuardada = trim((string) ($registro?->{'CveEmpl'.$n} ?? ''));
                $nomGuardado = trim((string) ($registro?->{'NomEmpl'.$n} ?? ''));
            @endphp
            <div class="rounded-lg border border-gray-200 p-2">
                <div class="flex items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_cve{{ $n }}">Empleado {{ $n }}</label>
                        <select id="{{ $prefijo }}_cve{{ $n }}" data-km-empleado="{{ $prefijo }}" data-fila="{{ $n }}"
                            data-nombre-destino="{{ $prefijo }}_nombre{{ $n }}" data-valor-actual="{{ $cveGuardada }}"
                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="if (alCambiarEmpleadoKm(this)) autoGuardarKm('{{ $prefijo }}')"
                            onfocus="this.dataset.prev=this.value"
                            @disabled($bloqueado)>
                            <option value="">Seleccione…</option>
                            @if($cveGuardada !== '')
                                <option value="{{ $cveGuardada }}" data-nombre="{{ $nomGuardado }}" selected>{{ $cveGuardada }}{{ $nomGuardado !== '' ? ' - '.$nomGuardado : '' }}</option>
                            @endif
                        </select>
                    </div>
                    @unless($bloqueado)
                        <button type="button" onclick="asignarmeKm('{{ $prefijo }}', {{ $n }})" title="Asignarme a mí en empleado {{ $n }}" aria-label="Asignarme a mí en empleado {{ $n }}"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                            <i class="fas fa-user-plus text-xs" aria-hidden="true"></i>
                        </button>
                    @endunless
                </div>
                <input type="hidden" id="{{ $prefijo }}_nombre{{ $n }}" value="{{ $nomGuardado }}">
            </div>
        @endforeach
    </div>
</div>

@once
@push('scripts')
<script>
    const kmYo = @json(auth()->check() ? ['cve' => (string) (auth()->user()->numero_empleado ?? ''), 'nombre' => (string) (auth()->user()->nombre ?? ''), 'area' => (string) (auth()->user()->area ?? '')] : null);
    const kmEmpleadosBaseUrl = @json(url('/obtener-empleados'));
    const kmEmpleadosCache = {};

    function opcionEmpleadoKm(cve, nombre) {
        const op = document.createElement('option');
        op.value = cve;
        op.textContent = nombre ? cve + ' - ' + nombre : cve;
        if (nombre) op.dataset.nombre = nombre;
        return op;
    }

    function sincronizarNombreKm(select) {
        const destino = document.getElementById(select.dataset.nombreDestino || '');
        if (!destino) return;
        const elegida = select.selectedOptions ? select.selectedOptions[0] : null;
        destino.value = (elegida && elegida.dataset.nombre) || '';
    }

    function avisarKm(icono, titulo, texto) {
        if (window.Swal) {
            Swal.fire({ icon: icono, title: titulo, text: texto, confirmButtonText: 'Entendido', confirmButtonColor: '#2563eb' });
        } else {
            alert(titulo + ': ' + texto);
        }
    }

    function nombreTarjetaKm(prefijo) {
        return prefijo === 'enhebrado' ? 'Enhebrado' : 'Montado';
    }

    function cvesOcupadasKm(prefijo, exceptoN) {
        const ocupadas = [];
        [1, 2, 3].forEach((m) => {
            if (Number(m) === Number(exceptoN)) return;
            const s = document.getElementById(prefijo + '_cve' + m);
            const v = s && s.value ? String(s.value).trim() : '';
            if (v) ocupadas.push(v);
        });
        return ocupadas;
    }

    function alCambiarEmpleadoKm(select) {
        const prefijo = select.dataset.kmEmpleado || '';
        const n = select.dataset.fila || '';
        const cve = String(select.value || '').trim();
        if (cve && cvesOcupadasKm(prefijo, n).includes(cve)) {
            avisarKm('warning', 'Empleado repetido', 'Ese empleado ya está en otra fila de ' + nombreTarjetaKm(prefijo) + '. Cada persona solo puede estar en una.');
            select.value = select.dataset.prev || '';
            sincronizarNombreKm(select);
            return false;
        }
        select.dataset.prev = select.value;
        sincronizarNombreKm(select);
        if (cve && String(n) === '1') {
            ponerFechaInicioKmSiVacia(prefijo);
        }
        return true;
    }

    function ponerFechaInicioKmSiVacia(prefijo) {
        if (!prefijo) return;
        const inicio = document.getElementById(prefijo + '_inicio');
        if (!inicio || inicio.disabled || inicio.value) return;
        const hoy = new Date();
        const yyyy = hoy.getFullYear();
        const mm = String(hoy.getMonth() + 1).padStart(2, '0');
        const dd = String(hoy.getDate()).padStart(2, '0');
        const hh = String(hoy.getHours()).padStart(2, '0');
        const mi = String(hoy.getMinutes()).padStart(2, '0');
        inicio.value = yyyy + '-' + mm + '-' + dd + 'T' + hh + ':' + mi;
    }

    function autoGuardarKm(prefijo) {
        if (typeof guardarProcesoKm === 'function') guardarProcesoKm(prefijo);
    }

    function asignarmeKm(prefijo, n) {
        if (!kmYo || !kmYo.cve) {
            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Sin sesión', text: 'No se pudo identificar al usuario actual.' });
            return;
        }
        const select = document.getElementById(prefijo + '_cve' + n);
        if (!select || select.disabled) return;
        if (cvesOcupadasKm(prefijo, n).includes(kmYo.cve)) {
            avisarKm('warning', 'Ya estás registrado', 'Ya estás en otra fila de ' + nombreTarjetaKm(prefijo) + '. Cada persona solo puede estar en una.');
            return;
        }
        let op = Array.from(select.options).find((o) => o.value === kmYo.cve);
        if (!op) {
            op = opcionEmpleadoKm(kmYo.cve, kmYo.nombre);
            select.appendChild(op);
        }
        select.value = kmYo.cve;
        sincronizarNombreKm(select);
        if (Number(n) === 1) ponerFechaInicioKmSiVacia(prefijo);
        autoGuardarKm(prefijo);
    }

    async function listaEmpleadosKm() {
        if (!kmYo || !kmYo.area) return [];
        if (kmEmpleadosCache[kmYo.area]) return kmEmpleadosCache[kmYo.area];
        try {
            const res = await fetch(kmEmpleadosBaseUrl + '/' + encodeURIComponent(kmYo.area), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return [];
            const datos = await res.json();
            kmEmpleadosCache[kmYo.area] = Array.isArray(datos) ? datos : [];
            return kmEmpleadosCache[kmYo.area];
        } catch (e) {
            return [];
        }
    }

    function nombreDeEmpleadoKm(e) {
        return String(e.nombre ?? e.nombre_completo ?? e.name ?? '');
    }

    function cveDeEmpleadoKm(e) {
        return String(e.numero_empleado ?? e.cve ?? e.clave ?? e.id ?? '');
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const selects = Array.from(document.querySelectorAll('select[data-km-empleado]'));
        if (!selects.length) return;
        const empleados = await listaEmpleadosKm();
        if (!empleados.length) return;
        selects.forEach((select) => {
            const actual = select.dataset.valorActual || select.value || '';
            empleados.forEach((e) => {
                const cve = cveDeEmpleadoKm(e);
                if (!cve) return;
                const existente = Array.from(select.options).find((o) => o.value === cve);
                const nom = nombreDeEmpleadoKm(e);
                if (existente) {
                    if (nom && !existente.dataset.nombre) {
                        existente.dataset.nombre = nom;
                        existente.textContent = cve + ' - ' + nom;
                    }
                    return;
                }
                select.appendChild(opcionEmpleadoKm(cve, nom));
            });
            if (actual) select.value = actual;
            sincronizarNombreKm(select);
        });
    });
</script>
@endpush
@endonce
