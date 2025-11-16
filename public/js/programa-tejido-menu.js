/**
 * Scripts específicos para el menú de Programa de Tejido
 */

(function() {
    const btn = document.getElementById('layoutBtnAddMenu');
    const menu = document.getElementById('layoutAddMenu');
    if (!btn || !menu) return;
    
    const open = () => {
        menu.classList.remove('hidden');
        requestAnimationFrame(() => {
            menu.classList.remove('opacity-0', 'scale-95');
            menu.classList.add('opacity-100', 'scale-100');
        });
    };
    
    const close = () => {
        menu.classList.add('opacity-0', 'scale-95');
        menu.classList.remove('opacity-100', 'scale-100');
        setTimeout(() => menu.classList.add('hidden'), 150);
    };
    
    let shown = false;
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        shown ? (close(), shown = false) : (open(), shown = true);
    });
    
    document.addEventListener('click', (e) => {
        if (shown && !menu.contains(e.target) && e.target !== btn) {
            close();
            shown = false;
        }
    });

    // Nuevo registro
    const nuevoReg = document.getElementById('menuNuevoRegistro');
    if (nuevoReg) {
        nuevoReg.addEventListener('click', () => {
            close();
            if (typeof abrirNuevo === 'function') {
                abrirNuevo();
            } else {
                window.location.href = '/planeacion/programa-tejido/nuevo';
            }
        });
    }

    // Alta de pronósticos
    const altaPron = document.getElementById('menuAltaPronosticos');
    if (altaPron) {
        altaPron.addEventListener('click', () => {
            close();
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth() + 1;

            const meses = [];
            for (let i = -12; i <= 3; i++) {
                const date = new Date(currentYear, currentMonth + i - 1, 1);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const label = date.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
                meses.push({ value: `${year}-${month}`, label: label.charAt(0).toUpperCase() + label.slice(1) });
            }

            const mesesHTML = meses.map(m => `<option value="${m.value}">${m.label}</option>`).join('');

            const html = `
                <div class="text-left">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mes Inicial:</label>
                        <select id="mesInicial" class="w-full border rounded px-3 py-2 text-sm">
                            ${mesesHTML}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mes Final:</label>
                        <select id="mesFinal" class="w-full border rounded px-3 py-2 text-sm">
                            ${mesesHTML}
                        </select>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        Se mostrarán los pronósticos del rango seleccionado (inclusive).
                    </p>
                </div>
            `;

            Swal.fire({
                title: 'Seleccionar Rango de Meses',
                html: html,
                width: 500,
                showCancelButton: true,
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                didOpen: () => {
                    const mesActual = `${currentYear}-${String(currentMonth).padStart(2, '0')}`;
                    document.getElementById('mesInicial').value = mesActual;
                    document.getElementById('mesFinal').value = mesActual;
                },
                preConfirm: () => {
                    const mesInicial = document.getElementById('mesInicial').value;
                    const mesFinal = document.getElementById('mesFinal').value;

                    if (!mesInicial || !mesFinal) {
                        Swal.showValidationMessage('Por favor seleccione ambos meses');
                        return false;
                    }

                    if (mesInicial > mesFinal) {
                        Swal.showValidationMessage('El mes inicial debe ser menor o igual al mes final');
                        return false;
                    }

                    const mesesSeleccionados = [];
                    const [yearIni, monthIni] = mesInicial.split('-').map(Number);
                    const [yearFin, monthFin] = mesFinal.split('-').map(Number);

                    let currentYear = yearIni;
                    let currentMonth = monthIni;

                    while (currentYear < yearFin || (currentYear === yearFin && currentMonth <= monthFin)) {
                        mesesSeleccionados.push(`${currentYear}-${String(currentMonth).padStart(2, '0')}`);
                        currentMonth++;
                        if (currentMonth > 12) {
                            currentMonth = 1;
                            currentYear++;
                        }
                    }

                    return mesesSeleccionados;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const url = new URL(window.location.origin + '/planeacion/programa-tejido/alta-pronosticos');
                    result.value.forEach(mes => {
                        url.searchParams.append('meses[]', mes);
                    });
                    window.location.href = url.toString();
                }
            });
        });
    }
})();


















