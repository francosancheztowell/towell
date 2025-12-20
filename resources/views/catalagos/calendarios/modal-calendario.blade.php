{{-- Modal para Agregar/Editar Calendario con Tabla de Turnos y Días --}}
<script>
    // Hacer la función disponible globalmente inmediatamente
    window.abrirModalCalendario = async function(mode = 'agregar', datos = null) {
        const esEdicion = mode === 'editar';
        let titulo = esEdicion ? 'Editar Calendario' : 'Agregar Nuevo Calendario';
        const botonTexto = esEdicion ? 'Guardar' : 'Agregar';
        const colorBoton = esEdicion ? '#3b82f6' : '#10b981';

        // Datos por defecto si es edición
        let calendarioId = datos?.calendarioId || '';
        let nombre = datos?.nombre || '';
        let fechaInicial = datos?.fechaInicial || '';
        let fechaFinal = datos?.fechaFinal || '';

        // Si es modo agregar, establecer fechas del año en curso
        if (!esEdicion) {
            const añoActual = new Date().getFullYear();
            fechaInicial = `${añoActual}-01-01`; // Primer día del año
            fechaFinal = `${añoActual}-12-31`; // Último día del año
        }

        // Si es modo agregar, obtener la secuencia automática
        if (!esEdicion) {
            try {
                const response = await fetch('/planeacion/calendarios/json', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.data && result.data.length > 0) {
                        // Buscar el último número en los nombres y CalendarioId de calendarios
                        let maxNumero = 0;

                        result.data.forEach(cal => {
                            // Buscar patrones como "Calendario Tejido 4", "Calendario Tejido 5", etc. en Nombre
                            const matchNombreTejido = cal.Nombre?.match(/Calendario\s+Tejido\s+(\d+)/i);
                            if (matchNombreTejido) {
                                const num = parseInt(matchNombreTejido[1], 10);
                                if (num > maxNumero) {
                                    maxNumero = num;
                                }
                            }

                            // También buscar formato antiguo "Calendario Tej4", "Calendario Tej5" para compatibilidad
                            const matchNombreTej = cal.Nombre?.match(/Calendario\s+Tej(\d+)/i);
                            if (matchNombreTej) {
                                const num = parseInt(matchNombreTej[1], 10);
                                if (num > maxNumero) {
                                    maxNumero = num;
                                }
                            }

                            // También buscar en CalendarioId por si acaso
                            const matchId = cal.CalendarioId?.match(/Calendario\s+Tej(?:ido\s+)?(\d+)/i);
                            if (matchId) {
                                const num = parseInt(matchId[1], 10);
                                if (num > maxNumero) {
                                    maxNumero = num;
                                }
                            }
                        });

                        // Si encontramos un número, generar el siguiente
                        if (maxNumero > 0) {
                            const siguienteNumero = maxNumero + 1;
                            nombre = `Calendario Tejido ${siguienteNumero}`;
                            calendarioId = `Calendario Tej${siguienteNumero}`;
                        } else {
                            // Si no hay calendarios con ese patrón, empezar con 1
                            nombre = 'Calendario Tejido 1';
                            calendarioId = 'Calendario Tej1';
                        }
                    } else {
                        // Si no hay calendarios, empezar con 1
                        nombre = 'Calendario Tejido 1';
                        calendarioId = 'Calendario Tej1';
                    }

                    // Actualizar el título del modal con el nombre generado
                    if (!esEdicion && nombre) {
                        titulo = `Agregar ${nombre}`;
                    }
                }
            } catch (error) {
                console.error('Error al obtener calendarios:', error);
                // Si hay error, usar valores por defecto
                if (!nombre && !calendarioId) {
                    nombre = 'Calendario Tejido 1';
                    calendarioId = 'Calendario Tej1';
                }
                // Actualizar el título del modal con el nombre generado
                if (!esEdicion && nombre) {
                    titulo = `Agregar ${nombre}`;
                }
            }
        }

        // Días de la semana
        const dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
        const diasKeys = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        const turnos = [1, 2, 3];

        // Configuración de turnos (definida una sola vez)
        const turnosConfig = {
            1: { inicio: '06:30', fin: '14:30', horas: 8 },
            2: { inicio: '14:30', fin: '22:30', horas: 8 },
            3: { inicio: '22:30', fin: '06:30', horas: 8 }
        };

        // Días que deben tener fondo gris (martes, jueves, sábado)
        const diasGris = ['martes', 'jueves', 'sabado'];

        const formatoHora24 = (hora) => {
            if (!hora) return '';
            let normalizada = hora;
            if (hora.match(/^\d{2}:\d{2}$/)) {
                normalizada = `${hora}:00`;
            }
            if (normalizada.match(/^\d{2}:\d{2}:\d{2}$/)) {
                const [h, m, s] = normalizada.split(':');
                return `${parseInt(h, 10)}:${m}:${s}`;
            }
            return normalizada;
        };

        // Generar filas de la tabla
        let filasTabla = '';
        turnos.forEach(turno => {
            filasTabla += `
                <tr>
                    <td class="border border-gray-300 px-2 py-1 text-center" style="width:32px; min-width:32px;">
                        <input type="checkbox" id="turno-${turno}-activo" class="turno-checkbox" data-turno="${turno}" checked>
                    </td>
                    <td class="border border-gray-300 px-2 py-1 font-semibold text-base whitespace-nowrap">T${turno}</td>
            `;

            diasKeys.forEach((dia, idx) => {
                const horasId = `turno-${turno}-${dia}-horas`;
                const inicioId = `turno-${turno}-${dia}-inicio`;
                const finId = `turno-${turno}-${dia}-fin`;
                const diaCheckId = `turno-${turno}-${dia}-activo`;

                // Valores por defecto si es edicion
                const turnoDia = datos?.turnos?.[turno]?.[dia];
                const horasVal = turnoDia?.horas ?? '';
                const inicioVal = turnoDia?.inicio ?? '';
                const finVal = turnoDia?.fin ?? '';
                const diaActivo = esEdicion ? (turnoDia?.activo === true) : true;

                const inicioTexto = formatoHora24(inicioVal);
                const finTexto = formatoHora24(finVal);

                // Clase para columnas grises (martes, jueves, sábado)
                const claseGris = diasGris.includes(dia) ? 'bg-gray-100' : '';

                filasTabla += `
                    <td class="border border-gray-300 px-1 py-1 text-center ${claseGris}" style="width:60px; min-width:60px;">
                        <input type="checkbox" id="${diaCheckId}" class="dia-checkbox sr-only" data-turno="${turno}" data-dia="${dia}" ${diaActivo ? 'checked' : ''}>
                        <input type="number" step="0.1" min="0" id="${horasId}"
                            class="w-full text-center text-xs bg-transparent focus:outline-none horas-input"
                            placeholder="Hr" value="${horasVal}"
                            data-turno="${turno}" data-dia="${dia}">
                    </td>
                    <td class="border border-gray-300 px-1 py-1 text-center text-xs ${claseGris}" style="width:60px; min-width:60px;" id="${inicioId}">${inicioTexto}</td>
                    <td class="border border-gray-300 px-1 py-1 text-center text-xs ${claseGris}" style="width:60px; min-width:60px;" id="${finId}">${finTexto}</td>
                `;
            });

            filasTabla += '</tr>';
        });

        // Generar encabezados de dias con checkboxes
        let encabezadosDiasCheckbox = '';
        let encabezadosDiasNombre = '';
        let encabezadosDiasSub = '';
        dias.forEach((dia, idx) => {
            const diaKey = diasKeys[idx];
            const claseGris = diasGris.includes(diaKey) ? 'bg-gray-100' : '';
            encabezadosDiasCheckbox += `
                <th class="border border-gray-300 px-2 py-1 text-center ${claseGris}" colspan="3">
                    <input type="checkbox" id="dia-${diaKey}-activo" class="dia-header-checkbox w-4 h-4" data-dia="${diaKey}" checked>
                </th>
            `;
            encabezadosDiasNombre += `
                <th class="border border-gray-300 px-1 py-1 text-center font-semibold text-sm ${claseGris}" colspan="3">${dia}</th>
            `;
            encabezadosDiasSub += `
                <th class="border border-gray-300 px-1 py-1 text-center text-xs font-semibold ${claseGris}" style="width:60px; min-width:60px;">Horas</th>
                <th class="border border-gray-300 px-1 py-1 text-center text-xs font-semibold ${claseGris}" style="width:60px; min-width:60px;">Inicio</th>
                <th class="border border-gray-300 px-1 py-1 text-center text-xs font-semibold ${claseGris}" style="width:60px; min-width:60px;">Fin</th>
            `;
        });
        Swal.fire({
            title: titulo,
            html: `
                <div class="text-left text-xs space-y-3">
                    <div class="grid grid-cols-12 gap-2 items-end justify-items-center">
                        <div class="col-span-6 text-center w-full">
                            <label class="block text-[11px] font-semibold text-gray-700 text-center">Fecha Inicial</label>
                            <input type="date" id="modal-fecha-inicial"
                                class="w-32 mx-auto border-b border-gray-400 bg-transparent text-xs text-center focus:outline-none"
                                value="${fechaInicial}">
                        </div>
                        <div class="col-span-6 text-center w-full">
                            <label class="block text-[11px] font-semibold text-gray-700 text-center">Fecha Final</label>
                            <input type="date" id="modal-fecha-final"
                                class="w-32 mx-auto border-b border-gray-400 bg-transparent text-xs text-center focus:outline-none"
                                value="${fechaFinal}">
                        </div>
                    </div>
                    <div class="overflow-x-auto max-h-[500px] border border-gray-300">
                        <table class="min-w-full text-xs" style="border-collapse: collapse;">
                            <thead class="sticky top-0 bg-white">
                                <tr>
                                    <th class="border border-gray-300 px-2 py-1" style="width:32px; min-width:32px;"></th>
                                    <th class="border border-gray-300 px-2 py-1" style="width:32px; min-width:32px;"></th>
                                    ${encabezadosDiasCheckbox}
                                </tr>
                                <tr>
                                    <th class="border border-gray-300 px-2 py-1" style="width:32px; min-width:32px;"></th>
                                    <th class="border border-gray-300 px-2 py-1 text-center font-semibold text-base"></th>
                                    ${encabezadosDiasNombre}
                                </tr>
                                <tr>
                                    <th class="border border-gray-300 px-2 py-1"></th>
                                    <th class="border border-gray-300 px-2 py-1"></th>
                                    ${encabezadosDiasSub}
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                ${filasTabla}
                            </tbody>
                        </table>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: botonTexto,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: colorBoton,
            cancelButtonColor: '#6b7280',
            width: '100%',
            maxWidth: '100%',
            didOpen: () => {
                if (!esEdicion) {
                    const fechaInicialInput = document.getElementById('modal-fecha-inicial');
                    const fechaFinalInput = document.getElementById('modal-fecha-final');

                    if (fechaInicialInput && !fechaInicialInput.value && fechaInicial) {
                        fechaInicialInput.value = fechaInicial;
                    }
                    if (fechaFinalInput && !fechaFinalInput.value && fechaFinal) {
                        fechaFinalInput.value = fechaFinal;
                    }
                }

                const baseInicioTurno1 = turnosConfig[1].inicio;

                const toSeconds = (hora) => {
                    const partes = (hora || '00:00:00').split(':');
                    const h = parseInt(partes[0], 10) || 0;
                    const m = parseInt(partes[1], 10) || 0;
                    const s = parseInt(partes[2], 10) || 0;
                    return (h * 3600) + (m * 60) + s;
                };

                const fromSeconds = (segundos) => {
                    const total = ((segundos % 86400) + 86400) % 86400;
                    const h = Math.floor(total / 3600);
                    const m = Math.floor((total % 3600) / 60);
                    const s = total % 60;
                    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                };

                const getHoras = (turno, dia) => {
                    const input = document.getElementById(`turno-${turno}-${dia}-horas`);
                    const valor = parseFloat(input?.value);
                    return Number.isFinite(valor) ? valor : 0;
                };

                const setHorasPorDefecto = (turno, dia) => {
                    const input = document.getElementById(`turno-${turno}-${dia}-horas`);
                    if (input && (input.value === '' || input.value === '0')) {
                        input.value = turnosConfig[turno].horas;
                    }
                };

                const setCeldaTiempo = (turno, dia, inicio, fin) => {
                    const inicioElement = document.getElementById(`turno-${turno}-${dia}-inicio`);
                    const finElement = document.getElementById(`turno-${turno}-${dia}-fin`);
                    if (inicioElement) inicioElement.textContent = inicio;
                    if (finElement) finElement.textContent = fin;
                };

                const isDiaActivo = (turno, dia) => {
                    const checkbox = document.getElementById(`turno-${turno}-${dia}-activo`);
                    return checkbox && checkbox.checked;
                };

                const sumaHorasDia = (dia) => {
                    let total = 0;
                    turnos.forEach(turno => {
                        if (isDiaActivo(turno, dia)) {
                            total += getHoras(turno, dia);
                        }
                    });
                    return total;
                };

                const recalcularDia = (dia) => {
                    let inicioActualSeg = toSeconds(baseInicioTurno1);

                    turnos.forEach(turno => {
                        const activo = isDiaActivo(turno, dia);
                        const horas = getHoras(turno, dia);

                        if (!activo) {
                            setCeldaTiempo(turno, dia, '', '');
                            return;
                        }

                        if (horas <= 0) {
                            setCeldaTiempo(turno, dia, '', '');
                            return;
                        }

                        const finSeg = inicioActualSeg + Math.round(horas * 3600);
                        const inicioTexto = fromSeconds(inicioActualSeg);
                        const finTexto = fromSeconds(finSeg - 2);
                        setCeldaTiempo(turno, dia, inicioTexto, finTexto);
                        inicioActualSeg = finSeg;
                    });
                };

                const recalcularTodo = () => {
                    diasKeys.forEach(dia => recalcularDia(dia));
                };

                if (!esEdicion) {
                    diasKeys.forEach(dia => {
                        turnos.forEach(turno => {
                            if (isDiaActivo(turno, dia)) {
                                setHorasPorDefecto(turno, dia);
                            }
                        });
                        recalcularDia(dia);
                    });
                } else {
                    recalcularTodo();
                }

                const syncTurnoCheckboxes = () => {
                    turnos.forEach(turno => {
                        const anyActive = diasKeys.some(dia => {
                            const check = document.getElementById(`turno-${turno}-${dia}-activo`);
                            return check && check.checked;
                        });
                        const turnoCheck = document.getElementById(`turno-${turno}-activo`);
                        if (turnoCheck) turnoCheck.checked = anyActive;
                    });
                };

                const syncDiaHeaderCheckboxes = () => {
                    diasKeys.forEach(dia => {
                        const anyActive = turnos.some(turno => {
                            const check = document.getElementById(`turno-${turno}-${dia}-activo`);
                            return check && check.checked;
                        });
                        const diaCheck = document.getElementById(`dia-${dia}-activo`);
                        if (diaCheck) diaCheck.checked = anyActive;
                    });
                };

                const syncAllCheckboxes = () => {
                    syncTurnoCheckboxes();
                    syncDiaHeaderCheckboxes();
                };

                document.querySelectorAll('.turno-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const turno = this.dataset.turno;
                        const activo = this.checked;

                        document.querySelectorAll(`.dia-checkbox[data-turno="${turno}"]`).forEach(diaCheck => {
                            diaCheck.checked = activo;
                            toggleDiaInputs(diaCheck);
                            if (activo) {
                                setHorasPorDefecto(turno, diaCheck.dataset.dia);
                            }
                            recalcularDia(diaCheck.dataset.dia);
                        });
                        syncDiaHeaderCheckboxes();
                    });
                });

                document.querySelectorAll('.dia-header-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const dia = this.dataset.dia;
                        const activo = this.checked;

                        document.querySelectorAll(`.dia-checkbox[data-dia="${dia}"]`).forEach(diaCheck => {
                            diaCheck.checked = activo;
                            toggleDiaInputs(diaCheck);
                            if (activo) {
                                setHorasPorDefecto(diaCheck.dataset.turno, dia);
                            }
                        });
                        recalcularDia(dia);
                        syncTurnoCheckboxes();
                    });
                });

                document.querySelectorAll('.dia-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        toggleDiaInputs(this);
                        if (this.checked) {
                            setHorasPorDefecto(this.dataset.turno, this.dataset.dia);
                        }
                        recalcularDia(this.dataset.dia);
                        syncAllCheckboxes();
                    });
                    toggleDiaInputs(checkbox);
                });

                syncAllCheckboxes();

                document.querySelectorAll('.horas-input').forEach(input => {
                    input.addEventListener('input', function() {
                        const valor = parseFloat(this.value);
                        if (!Number.isFinite(valor) || valor < 0) {
                            this.value = '0';
                        }
                        if (this.value !== '' && !Number.isNaN(parseFloat(this.value))) {
                            this.value = String(parseFloat(this.value));
                        }

                        const dia = this.dataset.dia;
                        const turnoActual = this.dataset.turno;
                        let totalOtros = 0;

                        turnos.forEach(turno => {
                            if (String(turno) === String(turnoActual)) return;
                            if (isDiaActivo(turno, dia)) {
                                totalOtros += getHoras(turno, dia);
                            }
                        });

                        const maxActual = Math.max(0, 24 - totalOtros);
                        const actual = getHoras(turnoActual, dia);
                        if (actual > maxActual) {
                            this.value = maxActual.toFixed(1).replace(/\.0$/, '');
                        }

                        recalcularDia(dia);
                    });
                });

                function toggleDiaInputs(checkbox) {
                    const turno = checkbox.dataset.turno;
                    const dia = checkbox.dataset.dia;
                    const activo = checkbox.checked;

                    const horasInput = document.getElementById(`turno-${turno}-${dia}-horas`);
                    const inicioElement = document.getElementById(`turno-${turno}-${dia}-inicio`);
                    const finElement = document.getElementById(`turno-${turno}-${dia}-fin`);

                    if (horasInput) {
                        horasInput.disabled = !activo;
                        horasInput.style.opacity = activo ? '1' : '0.5';
                        if (!activo) {
                            horasInput.value = '0';
                        }
                    }

                    if (!activo) {
                        if (inicioElement) inicioElement.textContent = '';
                        if (finElement) finElement.textContent = '';
                    }
                }
            },
            preConfirm: () => {
                // calendarioId y nombre se generan automáticamente, no se leen de inputs
                const fechaInicialVal = document.getElementById('modal-fecha-inicial').value;
                const fechaFinalVal = document.getElementById('modal-fecha-final').value;

                // Validar que los valores generados existan
                if (!calendarioId || !nombre) {
                    Swal.showValidationMessage('Error al generar el calendario. Por favor intenta de nuevo.');
                    return false;
                }

                if (!fechaInicialVal || !fechaFinalVal) {
                    Swal.showValidationMessage('Por favor completa las fechas');
                    return false;
                }

                // Validar fechas
                if (new Date(fechaFinalVal) < new Date(fechaInicialVal)) {
                    Swal.showValidationMessage('La fecha final debe ser posterior o igual a la fecha inicial');
                    return false;
                }

                // Validar suma de horas por dia (maximo 24)
                for (const dia of diasKeys) {
                    let totalHorasDia = 0;
                    let diaActivo = false;

                    for (const turno of turnos) {
                        const checkbox = document.getElementById(`turno-${turno}-${dia}-activo`);
                        if (!checkbox || !checkbox.checked) continue;

                        diaActivo = true;
                        const horasVal = parseFloat(document.getElementById(`turno-${turno}-${dia}-horas`).value);
                        totalHorasDia += Number.isFinite(horasVal) ? horasVal : 0;
                    }

                    if (diaActivo && totalHorasDia > 24.01) {
                        Swal.showValidationMessage(
                            `La suma de horas para ${dias[diasKeys.indexOf(dia)]} no puede ser mayor a 24 (actual: ${totalHorasDia})`
                        );
                        return false;
                    }
                }

                // Recopilar datos de turnos
                const turnosData = {};
                let errorValidacion = null;

                for (const turno of turnos) {
                    const turnoActivo = document.getElementById(`turno-${turno}-activo`).checked;
                    if (!turnoActivo) continue;

                    turnosData[turno] = {};
                    for (const dia of diasKeys) {
                        const diaActivo = document.getElementById(`turno-${turno}-${dia}-activo`).checked;
                        if (!diaActivo) continue;

                        const horasRaw = document.getElementById(`turno-${turno}-${dia}-horas`).value;
                        const inicioElement = document.getElementById(`turno-${turno}-${dia}-inicio`);
                        const finElement = document.getElementById(`turno-${turno}-${dia}-fin`);
                        const inicio = inicioElement ? inicioElement.textContent.trim() : '';
                        const fin = finElement ? finElement.textContent.trim() : '';

                        const horas = parseFloat(horasRaw);
                        if (!Number.isFinite(horas) || horas <= 0) continue;
                        if (!inicio || !fin) {
                            errorValidacion = `Por favor completa todos los campos para Turno ${turno} - ${dias[diasKeys.indexOf(dia)]}`;
                            break;
                        }

                        turnosData[turno][dia] = {
                            horas: horas,
                            inicio: inicio,
                            fin: fin,
                            activo: true
                        };
                    }

                    if (errorValidacion) break;
                    if (!Object.keys(turnosData[turno]).length) {
                        delete turnosData[turno];
                    }
                }

                if (errorValidacion) {
                    Swal.showValidationMessage(errorValidacion);
                    return false;
                }

                return {
                    calendarioId: calendarioId, // Usar el valor generado automáticamente
                    nombre: nombre, // Usar el valor generado automáticamente
                    fechaInicial: fechaInicialVal,
                    fechaFinal: fechaFinalVal,
                    turnos: turnosData
                };
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            // Aquí procesarías los datos y crearías las líneas de calendario
            // Por ahora solo guardamos el calendario maestro
            const url = esEdicion
                ? `/planeacion/calendarios/${encodeURIComponent(result.value.calendarioId)}/masivo`
                : '/planeacion/calendarios';

            const method = esEdicion ? 'PUT' : 'POST';
            const body = esEdicion
                ? JSON.stringify({
                    Nombre: result.value.nombre,
                    FechaInicial: result.value.fechaInicial,
                    FechaFinal: result.value.fechaFinal,
                    Turnos: result.value.turnos
                })
                : JSON.stringify({
                    CalendarioId: result.value.calendarioId,
                    Nombre: result.value.nombre,
                    FechaInicial: result.value.fechaInicial,
                    FechaFinal: result.value.fechaFinal,
                    Turnos: result.value.turnos
                });

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: body
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Aquí crearías las líneas de calendario basadas en los turnos
                        // Por ahora solo mostramos el mensaje
                        showToast(data.message, 'success');
                        location.reload();
                    } else {
                        showToast(data.message || `Error al ${esEdicion ? 'actualizar' : 'crear'} calendario`, 'error');
                    }
                })
                .catch(() => showToast(`Error al ${esEdicion ? 'actualizar' : 'crear'} calendario`, 'error'));
        });
    }
</script>
