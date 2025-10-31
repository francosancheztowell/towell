@extends('layouts.app')

@section('page-title', 'Editar Programa de Tejido')
@section('navbar-right')
<!-- Botón Actualizar en la barra de navegación -->
<button onclick="actualizar()" class="bg-blue-600 hover:bg-blue-700 flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
    <i class="fas fa-edit"></i>
    Actualizar
</button>
@endsection
@section('content')
<div class="w-full">

    @include('modulos.programa-tejido-nuevo._form')
</div>
@endsection

<script>
// Helpers de fecha
function parseDateFlexible(str) {
    if (!str) return null;
    let s = String(str).trim();
    // Quitar milisegundos tipo .000
    s = s.replace(/\.\d{3}$/,'');
    // dd/mm/yyyy -> yyyy-mm-dd
    if (/^\d{2}\/\d{2}\/\d{4}/.test(s)) {
        const [d,m,y] = s.split(/[\/\s]/);
        // si hay hora después, conservarla
        const time = s.split(' ')[1] || '00:00:00';
        s = `${y}-${m}-${d}T${time}`;
    }
    // yyyy-mm-dd hh:mm:ss -> yyyy-mm-ddThh:mm:ss
    if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/.test(s)) {
        s = s.replace(' ', 'T');
    }
    // yyyy-mm-dd -> agregar hora por defecto
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        s += 'T00:00:00';
    }
    const d = new Date(s);
    if (isNaN(d.getTime())) return null;
    return d;
}
function formatYmdHms(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

// Regla Calendario Tej3: ventana no laborable Sábado 18:29 -> Lunes 07:00
function aplicarCalendarioTej3(dateObj) {
    if (!(dateObj instanceof Date) || isNaN(dateObj)) return dateObj;

    const d = new Date(dateObj.getTime());

    // Calcular el rango no laborable de la semana donde cae d
    const day = d.getDay(); // 0=Dom, 6=Sáb
    // Obtener el sábado de esa semana a las 18:29:00
    const sab = new Date(d.getTime());
    const diffToSat = (6 - day + 7) % 7; // días hasta sábado
    sab.setDate(d.getDate() + (day <= 6 ? -((day+1)%7) : 0));
    // Reposicionar sab al sábado real de la semana del d
    sab.setDate(d.getDate() - ((day + 1) % 7));
    sab.setHours(18, 29, 0, 0);

    // Lunes siguiente 07:00:00
    const lun = new Date(sab.getTime());
    lun.setDate(sab.getDate() + 2); // sábado -> lunes
    lun.setHours(7, 0, 0, 0);

    // Si d cae entre sábado 18:29 y lunes 07:00, empujar a lunes 07:00
    if (d.getTime() >= sab.getTime() && d.getTime() < lun.getTime()) {
        return lun;
    }
    return d;
}

// Sumar horas reales de trabajo según Calendario Tej3:
// - Lunes a viernes: toda la jornada disponible
// - Sábado: solo hasta 18:29
// - Domingo: no laborable; continuar el lunes 07:00
function sumarHorasTej3(startDate, horas) {
    if (!(startDate instanceof Date) || isNaN(startDate)) return startDate;
    let cur = new Date(startDate.getTime());
    let remaining = Number(horas) || 0;
    const msPerHour = 3600000;

    // Helper para avanzar a lunes 07:00
    const toMonday0700 = (d) => {
        const day = d.getDay();
        const diffToMon = (8 - day) % 7; // 1=Mon; si Sunday(0) -> +1
        d = new Date(d.getTime());
        d.setDate(d.getDate() + diffToMon);
        d.setHours(7,0,0,0);
        return d;
    };

    while (remaining > 0.0001) {
        const day = cur.getDay(); // 0=Dom,6=Sab

        // Si es domingo, saltar a lunes 07:00
        if (day === 0) { cur = toMonday0700(cur); continue; }

        // Si es sábado y ya pasó 18:29, saltar a lunes 07:00
        if (day === 6) {
            const sabEnd = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate(), 18, 29, 0, 0);
            if (cur.getTime() >= sabEnd.getTime()) { cur = toMonday0700(cur); continue; }
            // ventana final de hoy
            const availableHours = (sabEnd.getTime() - cur.getTime()) / msPerHour;
            if (remaining <= availableHours) {
                cur = new Date(cur.getTime() + remaining * msPerHour);
                remaining = 0;
            } else {
                remaining -= availableHours;
                cur = toMonday0700(cur); // siguiente ventana
            }
            continue;
        }

        // Lunes a Viernes: ventana hasta fin del día (23:59:59)
        const endOfDay = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate()+1, 0, 0, 0, 0); // próximo día 00:00
        let nextStart = new Date(endOfDay.getTime());
        // Si mañana es domingo, saltar a lunes 07:00
        if (nextStart.getDay() === 0) nextStart = toMonday0700(nextStart);
        const availableHours = (endOfDay.getTime() - cur.getTime()) / msPerHour;

        if (remaining <= availableHours) {
            cur = new Date(cur.getTime() + remaining * msPerHour);
            remaining = 0;
        } else {
            remaining -= availableHours;
            cur = nextStart;
        }
    }

    return cur;
}

// Recalcula FECHA FIN usando capacidad: horas = cantidad / (StdToaHra * EficienciaSTD)
function calcularFechaFinalFila(tr) {
    const cantidadEl = document.getElementById('cantidad-input');
    const inicioEl = document.getElementById('fecha-inicio-input');
    const finEl = document.getElementById('fecha-fin-input');

    if (!cantidadEl || !inicioEl || !finEl) return;

    const cantidadNueva = Number(cantidadEl.value || 0);
    const inicioVal = (inicioEl.value || '').trim();
    const finOriginalVal = (finEl.getAttribute('data-original') || finEl.value || '').trim();

    // Conservar la FECHA FIN original en data-original la primera vez
    if (!finEl.getAttribute('data-original') && finEl.value) {
        finEl.setAttribute('data-original', finEl.value);
    }

    const dInicio = parseDateFlexible(inicioVal);
    const dFinOriginal = parseDateFlexible(finOriginalVal);
    if (!dInicio) return;

    // Capacidad desde data-attrs
    const row = tr || cantidadEl.closest('tr');
    const stdToa = Number((row?.getAttribute('data-stdtoa')) || 0); // toallas/hora a 100%
    let eficiencia = Number((row?.getAttribute('data-eficiencia')) || 1); // 0..1 o 0..100
    if (eficiencia > 1) eficiencia = eficiencia / 100;

    if (stdToa > 0 && eficiencia > 0) {
        const horasNecesarias = Math.max(0, cantidadNueva) / (stdToa * eficiencia);
        // Sumar horas considerando calendarios Tej3
        const nuevoFin = sumarHorasTej3(dInicio, horasNecesarias);
        finEl.value = formatYmdHms(nuevoFin);
    } else if (dFinOriginal) {
        // Fallback proporcional si no hay capacidad en datos
        const cantidadOriginal = Number(cantidadEl.getAttribute('data-original') || cantidadEl.defaultValue || cantidadEl.value || 0);
        const durMs = dFinOriginal.getTime() - dInicio.getTime();
        if ((cantidadOriginal > 0) && (durMs >= 0)) {
            const msPorPieza = durMs / cantidadOriginal;
            const nuevaDurMs = msPorPieza * Math.max(0, cantidadNueva);
            const horasProporcionales = nuevaDurMs / 3600000;
            const nuevoFin = sumarHorasTej3(dInicio, horasProporcionales);
            finEl.value = formatYmdHms(nuevoFin);
        } else {
            finEl.value = formatYmdHms(dInicio);
        }
    } else {
        finEl.value = formatYmdHms(dInicio);
    }

    // Recalcular campos derivados con la nueva fecha fin
    try {
        const finCalc = parseDateFlexible(finEl.value);
        const horas = (finCalc && dInicio) ? Math.max(0, (finCalc.getTime() - dInicio.getTime()) / 3600000) : 0;
        const dias = horas / 24; // DiasEficiencia en decimal
        const row2 = tr || cantidadEl.closest('tr');
        let stdToa100 = Number((row2?.getAttribute('data-stdtoa')) || 0);
        let eficiencia = Number((row2?.getAttribute('data-eficiencia')) || 1);
        if (eficiencia > 1) eficiencia = eficiencia / 100;
        const pesoCrudo = Number((row2?.getAttribute('data-pesocrudo')) || 0);
        const velocidadStd = Number((row2?.getAttribute('data-velocidadstd')) || 0);
        const totalPedido = Number((row2?.getAttribute('data-totalpedido')) || 0);

        // Si no hay StdToaHra base, derivarlo de los datos efectivos
        if (!(stdToa100 > 0) && horas > 0) {
            const toallasPorHoraEfect = totalPedido / horas; // efectivas observadas
            stdToa100 = (eficiencia > 0) ? (toallasPorHoraEfect / eficiencia) : toallasPorHoraEfect;
        }

        // Días Eficiencia como diferencia real entre fechas (en días decimales)
        const diasEficiencia = dias; // ya calculado arriba
        // Std/Día = StdToaHra * 24 (SIN eficiencia)
        const stdDia = stdToa100 * 24;
        // Std/Hr Efectivo = TotalPedido / (DiasEficiencia * 24)
        const stdHrsEfect = (diasEficiencia > 0) ? (totalPedido / (diasEficiencia * 24)) : 0;
        // Prod(Kg)/Día = StdDia * PesoCrudo / 1000
        const prodKgDia = (stdDia * pesoCrudo) / 1000;
        // Prod(Kg)/Día 2 = (PesoCrudo * StdHrsEfect * 24) / 1000
        const prodKgDia2 = (pesoCrudo * stdHrsEfect * 24) / 1000;
        const diasJornada = velocidadStd / 24;
        const horasProd = (stdToa100>0 && eficiencia>0) ? (totalPedido / (stdToa100 * eficiencia)) : 0;

        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = (Number.isFinite(val) ? val.toFixed(2) : ''); };
        setVal('StdToaHra-input', stdToa100);
        setVal('StdDia-input', stdDia);
        setVal('ProdKgDia-input', prodKgDia);
        setVal('ProdKgDia2-input', prodKgDia2);
        setVal('StdHrsEfect-input', stdHrsEfect);
        setVal('DiasJornada-input', diasJornada);
        setVal('HorasProd-input', horasProd);
        const elDE = document.getElementById('DiasEficiencia-input'); if (elDE) elDE.value = (Number.isFinite(diasEficiencia)? diasEficiencia.toFixed(2):'');
    } catch (_) {}
}

// Calcula las fórmulas actuales basadas en los valores de la UI y data-attrs
function calcularFormulasActuales(tr) {
    const cantidadEl = document.getElementById('cantidad-input');
    const inicioEl = document.getElementById('fecha-inicio-input');
    const finEl = document.getElementById('fecha-fin-input');
    const row = tr || cantidadEl?.closest('tr');

    const dInicio = parseDateFlexible(inicioEl?.value || '');
    const dFin = parseDateFlexible(finEl?.value || '');
    const horas = (dInicio && dFin) ? Math.max(0, (dFin.getTime() - dInicio.getTime()) / 3600000) : 0;
    const dias = horas / 24; // DiasEficiencia en decimal

    let stdToa100 = Number((row?.getAttribute('data-stdtoa')) || 0);
    let eficiencia = Number((row?.getAttribute('data-eficiencia')) || 1);
    if (eficiencia > 1) eficiencia = eficiencia / 100;
    const pesoCrudo = Number((row?.getAttribute('data-pesocrudo')) || 0);
    const velocidadStd = Number((row?.getAttribute('data-velocidadstd')) || 0);
    const totalPedido = Number((row?.getAttribute('data-totalpedido')) || 0);

    if (!(stdToa100 > 0) && horas > 0) {
        const toallasHoraEff = totalPedido / horas;
        stdToa100 = (eficiencia > 0) ? (toallasHoraEff / eficiencia) : toallasHoraEff;
    }

    // StdDia = StdToaHra * 24 (SIN eficiencia)
    const stdDia = stdToa100 * 24;
    // StdHrsEfect = TotalPedido / (DiasEficiencia * 24)
    const stdHrsEfect = (dias > 0) ? (totalPedido / (dias * 24)) : 0;
    // ProdKgDia = StdDia * PesoCrudo / 1000
    const prodKgDia = (stdDia * pesoCrudo) / 1000;
    // ProdKgDia2 = (PesoCrudo * StdHrsEfect * 24) / 1000
    const prodKgDia2 = (pesoCrudo * stdHrsEfect * 24) / 1000;
    const diasJornada = velocidadStd / 24;
    const horasProd = (stdToa100>0 && eficiencia>0) ? (totalPedido / (stdToa100 * eficiencia)) : 0;

    return {
        dias_eficiencia: dias,
        std_toa_hra: stdToa100,
        std_dia: stdDia,
        std_hrs_efect: stdHrsEfect,
        prod_kg_dia: prodKgDia,
        prod_kg_dia2: prodKgDia2,
        dias_jornada: diasJornada,
        horas_prod: horasProd,
    };
}

// Inicializar data-original al cargar
document.addEventListener('DOMContentLoaded', () => {
    const finEl = document.getElementById('fecha-fin-input');
    const cantidadEl = document.getElementById('cantidad-input');
    if (finEl && finEl.value && !finEl.getAttribute('data-original')) {
        finEl.setAttribute('data-original', finEl.value);
    }
    if (cantidadEl && !cantidadEl.getAttribute('data-original')) {
        const orig = Number(cantidadEl.defaultValue || cantidadEl.value || 0);
        cantidadEl.setAttribute('data-original', String(orig));
    }
    // Listeners para recalcular fórmulas
    const tr = cantidadEl ? cantidadEl.closest('tr') : null;
    if (cantidadEl) {
        cantidadEl.addEventListener('input', () => calcularFechaFinalFila(tr));
        cantidadEl.addEventListener('change', () => calcularFechaFinalFila(tr));
    }
    if (finEl) {
        finEl.addEventListener('change', () => calcularFechaFinalFila(tr));
    }
    // Primer pintado
    setTimeout(() => calcularFechaFinalFila(tr), 0);
});

async function actualizar() {
    try {
        const cantidadEl = document.getElementById('cantidad-input');
        const cantidad = cantidadEl ? Number(cantidadEl.value || 0) : 0;
        const finEl = document.getElementById('fecha-fin-input');
        const fechaFin = finEl ? finEl.value : null;
        const tr = cantidadEl ? cantidadEl.closest('tr') : null;
        const f = calcularFormulasActuales(tr);
        // Campos adicionales a actualizar (solo los NO deshabilitados en el form)
        const nc1 = document.getElementById('nombre-color-1')?.value ?? null; // NombreCC1
        const nc2 = document.getElementById('nombre-color-2')?.value ?? null; // NombreCC2
        const nc3 = document.getElementById('nombre-color-3')?.value ?? null; // NombreCC3
        const nc6 = document.getElementById('nombre-color-6')?.value ?? null; // NombreCC5
        const c1  = document.getElementById('calibre-c1')?.value ?? null; // CalibreComb12
        const c2  = document.getElementById('calibre-c2')?.value ?? null; // CalibreComb22
        const c3  = document.getElementById('calibre-c3')?.value ?? null; // CalibreComb32
        const c4  = document.getElementById('calibre-c4')?.value ?? null; // CalibreComb42
        const c5  = document.getElementById('calibre-c5')?.value ?? null; // CalibreComb52
        const ctr = document.getElementById('calibre-trama')?.value ?? null;   // CalibreTrama
        const ftr = document.getElementById('hilo-trama')?.value ?? null;      // FibraTrama
        const fc2 = document.getElementById('hilo-c2')?.value ?? null;         // FibraComb2
        const fc4 = document.getElementById('hilo-c4')?.value ?? null;         // FibraComb4
        const fc1 = document.getElementById('hilo-c1')?.value ?? null;         // FibraComb1
        const fc3 = document.getElementById('hilo-c3')?.value ?? null;         // FibraComb3
        const fc5 = document.getElementById('hilo-c5')?.value ?? null;         // FibraComb5
        const cod1 = document.getElementById('cod-color-1')?.value ?? null;    // CodColorTrama
        const cod2 = document.getElementById('cod-color-2')?.value ?? null;    // CodColorComb2
        const cod3 = document.getElementById('cod-color-3')?.value ?? null;    // CodColorComb4
        const cod4 = document.getElementById('cod-color-4')?.value ?? null;    // CodColorComb1
        const cod5 = document.getElementById('cod-color-5')?.value ?? null;    // CodColorComb3
        const cod6 = document.getElementById('cod-color-6')?.value ?? null;    // CodColorComb5

        const id = {{ $registro->Id ?? $registro->id }};

        const resp = await fetch(`/planeacion/programa-tejido/${encodeURIComponent(id)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                cantidad,
                fecha_fin: fechaFin,
                // nombres color
                nombre_color_1: nc1,
                nombre_color_2: nc2,
                nombre_color_3: nc3,
                nombre_color_6: nc6,
                // calibres
                calibre_trama: ctr !== null && ctr !== '' ? Number(ctr) : null,
                calibre_c1: c1 !== null && c1 !== '' ? Number(c1) : null,
                calibre_c2: c2 !== null && c2 !== '' ? Number(c2) : null,
                calibre_c3: c3 !== null && c3 !== '' ? Number(c3) : null,
                calibre_c4: c4 !== null && c4 !== '' ? Number(c4) : null,
                calibre_c5: c5 !== null && c5 !== '' ? Number(c5) : null,
                // fibras
                fibra_trama: ftr,
                fibra_c1: fc1,
                fibra_c2: fc2,
                fibra_c3: fc3,
                fibra_c4: fc4,
                fibra_c5: fc5,
                // codigos color
                cod_color_1: cod1,
                cod_color_2: cod2,
                cod_color_3: cod3,
                cod_color_4: cod4,
                cod_color_5: cod5,
                cod_color_6: cod6,
                // fórmulas calculadas
                dias_eficiencia: Number.isFinite(f.dias_eficiencia) ? Number(f.dias_eficiencia.toFixed(4)) : null,
                std_toa_hra: Number.isFinite(f.std_toa_hra) ? Number(f.std_toa_hra.toFixed(4)) : null,
                std_dia: Number.isFinite(f.std_dia) ? Number(f.std_dia.toFixed(4)) : null,
                std_hrs_efect: Number.isFinite(f.std_hrs_efect) ? Number(f.std_hrs_efect.toFixed(4)) : null,
                prod_kg_dia: Number.isFinite(f.prod_kg_dia) ? Number(f.prod_kg_dia.toFixed(4)) : null,
                prod_kg_dia2: Number.isFinite(f.prod_kg_dia2) ? Number(f.prod_kg_dia2.toFixed(4)) : null,
                dias_jornada: Number.isFinite(f.dias_jornada) ? Number(f.dias_jornada.toFixed(4)) : null,
                horas_prod: Number.isFinite(f.horas_prod) ? Number(f.horas_prod.toFixed(4)) : null,
            })
        });

        const data = await (resp.ok ? resp.json() : resp.text().then(t => { throw new Error(t || `HTTP ${resp.status}`); }));
        if (!data.success) throw new Error(data.message || 'No se pudo actualizar');

        // Reflejar cambios sin recargar
        if (cantidadEl && data.data) {
            const nuevo = (data.data.SaldoPedido ?? data.data.Produccion ?? cantidad);
            cantidadEl.value = Number(nuevo);
        }
        if (data.data) {
            if (document.getElementById('nombre-color-1') && 'NombreCC1' in data.data) document.getElementById('nombre-color-1').value = data.data.NombreCC1 ?? '';
            if (document.getElementById('nombre-color-2') && 'NombreCC2' in data.data) document.getElementById('nombre-color-2').value = data.data.NombreCC2 ?? '';
            if (document.getElementById('nombre-color-3') && 'NombreCC3' in data.data) document.getElementById('nombre-color-3').value = data.data.NombreCC3 ?? '';
            if (document.getElementById('nombre-color-6') && 'NombreCC5' in data.data) document.getElementById('nombre-color-6').value = data.data.NombreCC5 ?? '';
            if (document.getElementById('calibre-trama') && 'CalibreTrama' in data.data) document.getElementById('calibre-trama').value = data.data.CalibreTrama ?? '';
            if (document.getElementById('calibre-c1') && 'CalibreComb12' in data.data) document.getElementById('calibre-c1').value = data.data.CalibreComb12 ?? '';
            if (document.getElementById('calibre-c2') && 'CalibreComb22' in data.data) document.getElementById('calibre-c2').value = data.data.CalibreComb22 ?? '';
            if (document.getElementById('calibre-c3') && 'CalibreComb32' in data.data) document.getElementById('calibre-c3').value = data.data.CalibreComb32 ?? '';
            if (document.getElementById('calibre-c4') && 'CalibreComb42' in data.data) document.getElementById('calibre-c4').value = data.data.CalibreComb42 ?? '';
            if (document.getElementById('calibre-c5') && 'CalibreComb52' in data.data) document.getElementById('calibre-c5').value = data.data.CalibreComb52 ?? '';
            if (document.getElementById('hilo-trama') && 'FibraTrama' in data.data) document.getElementById('hilo-trama').value = data.data.FibraTrama ?? '';
            if (document.getElementById('hilo-c1') && 'FibraComb1' in data.data) document.getElementById('hilo-c1').value = data.data.FibraComb1 ?? '';
            if (document.getElementById('hilo-c2') && 'FibraComb2' in data.data) document.getElementById('hilo-c2').value = data.data.FibraComb2 ?? '';
            if (document.getElementById('hilo-c3') && 'FibraComb3' in data.data) document.getElementById('hilo-c3').value = data.data.FibraComb3 ?? '';
            if (document.getElementById('hilo-c4') && 'FibraComb4' in data.data) document.getElementById('hilo-c4').value = data.data.FibraComb4 ?? '';
            if (document.getElementById('hilo-c5') && 'FibraComb5' in data.data) document.getElementById('hilo-c5').value = data.data.FibraComb5 ?? '';
            if (document.getElementById('cod-color-1') && 'CodColorTrama' in data.data) document.getElementById('cod-color-1').value = data.data.CodColorTrama ?? '';
            if (document.getElementById('cod-color-2') && 'CodColorComb2' in data.data) document.getElementById('cod-color-2').value = data.data.CodColorComb2 ?? '';
            if (document.getElementById('cod-color-3') && 'CodColorComb4' in data.data) document.getElementById('cod-color-3').value = data.data.CodColorComb4 ?? '';
            if (document.getElementById('cod-color-4') && 'CodColorComb1' in data.data) document.getElementById('cod-color-4').value = data.data.CodColorComb1 ?? '';
            if (document.getElementById('cod-color-5') && 'CodColorComb3' in data.data) document.getElementById('cod-color-5').value = data.data.CodColorComb3 ?? '';
            if (document.getElementById('cod-color-6') && 'CodColorComb5' in data.data) document.getElementById('cod-color-6').value = data.data.CodColorComb5 ?? '';
        }
        if (window.Swal) {
            await Swal.fire({ icon:'success', title:'Actualizado correctamente', timer:1200, showConfirmButton:false });
            // Repintar fórmulas después de guardar
            calcularFechaFinalFila(tr);
            window.location.href = '/planeacion/programa-tejido';
        }
    } catch (e) {
        if (window.Swal) {
            Swal.fire({ icon:'error', title:'No se pudo actualizar', text: (e && e.message) ? e.message : '' });
        } else {
            alert(e.message || 'No se pudo actualizar');
        }
    }
}
</script>


