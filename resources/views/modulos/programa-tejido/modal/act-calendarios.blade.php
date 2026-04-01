{{-- Modal Actualizar Calendarios --}}
<x-ui.modal-base id="modalActCalendarios" title="Actualizar Calendarios" size="lg" onclose="cerrarModalActCalendarios()">

  {{-- Select de Calendario --}}
  <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
    <label for="selectCalendario" class="text-xs font-semibold text-gray-600">
      Calendario
    </label>
    <select id="selectCalendario" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm hover:border-blue-400">
      <option value="">Seleccione un calendario...</option>
    </select>
    <label class="text-xs text-gray-600 flex items-center gap-2">
      <input type="checkbox" id="selectAllRegistros" class="w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer">
      Seleccionar todo
    </label>
  </div>

  {{-- Tabla tipo Excel con registros de ProgramaTejido --}}
  <div class="border border-gray-300 rounded-lg overflow-y-auto overflow-x-hidden" style="max-height: 50vh;">
    <table id="tablaRegistros" class="min-w-full bg-white text-sm">
      <tbody id="tbodyRegistros" class="divide-y divide-gray-200">
        {{-- Las filas se cargarán dinámicamente con JavaScript --}}
      </tbody>
    </table>
  </div>

  {{-- Botones de acción --}}
  <div class="flex justify-end gap-3 mt-3 pt-3 border-t border-gray-200">
    <button type="button" onclick="cerrarModalActCalendarios()" class="modal-btn-secondary">
      Cancelar
    </button>
    <button type="button" id="btnGuardarCalendarios" onclick="guardarCalendariosSeleccionados()" class="modal-btn-primary">
      Guardar
    </button>
  </div>

</x-ui.modal-base>

<script>
  // Función para abrir el modal de Actualizar Calendarios
  window.abrirModalActCalendarios = async function() {
    const modal = document.getElementById('modalActCalendarios');
    if (!modal) {
      console.error('Modal no encontrado');
      return;
    }

    // Ocultar dropdown
    const dropdown = document.getElementById('actualizarDropdownMenu');
    if (dropdown) {
      dropdown.classList.add('hidden');
    }

    // Mostrar modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Cargar calendarios en el select y registros en la tabla
    await Promise.all([
      cargarCalendariosEnSelect(),
      cargarRegistrosEnTabla()
    ]);
  };

  // Función para cerrar el modal
  window.cerrarModalActCalendarios = function() {
    const modal = document.getElementById('modalActCalendarios');
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    }
  };

  // Función para cargar calendarios en el select
  async function cargarCalendariosEnSelect() {
    const select = document.getElementById('selectCalendario');
    if (!select) return;

    try {
      // Mostrar loader
      if (window.PT && window.PT.loader) {
        window.PT.loader.show();
      }

      // Obtener calendarios desde el backend
      const response = await fetch('/planeacion/calendarios/json', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
      });

      if (!response.ok) {
        throw new Error('Error al obtener calendarios');
      }

      const result = await response.json();

      if (!result.success || !result.data) {
        throw new Error('No se pudieron obtener los calendarios');
      }

      // Limpiar select (mantener la opción por defecto)
      select.innerHTML = '<option value="">Seleccione un calendario...</option>';

      // Agregar opciones de calendarios
      result.data.forEach((calendario) => {
        const option = document.createElement('option');
        option.value = calendario.CalendarioId;
        option.textContent = `${calendario.CalendarioId} - ${calendario.Nombre}`;
        select.appendChild(option);
      });

    } catch (error) {
      console.error('Error al cargar calendarios:', error);
      if (typeof window.showToast === 'function') {
        window.showToast('Error al cargar los calendarios: ' + error.message, 'error');
      } else {
        alert('Error al cargar los calendarios: ' + error.message);
      }
    } finally {
      // Ocultar loader
      if (window.PT && window.PT.loader) {
        window.PT.loader.hide();
      }
    }
  }

  // Función para cargar registros de ProgramaTejido en la tabla
  async function cargarRegistrosEnTabla() {
    const tbody = document.getElementById('tbodyRegistros');
    if (!tbody) return;

    try {
      // Mostrar loader
      if (window.PT && window.PT.loader) {
        window.PT.loader.show();
      }

      // Obtener registros desde el backend
      const response = await fetch('/planeacion/programa-tejido/all-registros-json', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
      });

      if (!response.ok) {
        throw new Error('Error al obtener registros');
      }

      const result = await response.json();

      if (!result.success || !result.data) {
        throw new Error('No se pudieron obtener los registros');
      }

      // Limpiar tbody
      tbody.innerHTML = '';

      // Crear filas para cada registro
      result.data.forEach((registro) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-blue-50 transition';
        row.dataset.registroId = registro.Id;

        // Checkbox (seleccionado por defecto)
        const tdCheckbox = document.createElement('td');
        tdCheckbox.className = 'px-3 py-2';
        tdCheckbox.innerHTML = `
          <input type="checkbox"
                 class="checkbox-registro w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                 data-registro-id="${registro.Id}"
                 checked
                 title="Seleccionar">
        `;

        // Telares (NoTelarId)
        const tdTelares = document.createElement('td');
        tdTelares.className = 'px-3 py-2 text-sm font-semibold text-gray-800';
        tdTelares.textContent = registro.NoTelarId || '';

        // Producto (NombreProducto)
        const tdProducto = document.createElement('td');
        tdProducto.className = 'px-3 py-2 text-sm text-gray-700';
        tdProducto.textContent = registro.NombreProducto || '';

        row.appendChild(tdCheckbox);
        row.appendChild(tdTelares);
        row.appendChild(tdProducto);

        tbody.appendChild(row);
      });

      // Si no hay registros, mostrar mensaje
      if (result.data.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td colspan="3" class="px-3 py-4 text-center text-gray-500">
            No hay registros disponibles
          </td>
        `;
        tbody.appendChild(row);
      }

      // Configurar evento para "Seleccionar todos"
      const selectAll = document.getElementById('selectAllRegistros');
      if (selectAll) {
        selectAll.checked = true; // Por defecto todos seleccionados
        selectAll.onchange = function() {
          const checkboxes = document.querySelectorAll('.checkbox-registro');
          checkboxes.forEach(cb => {
            cb.checked = this.checked;
          });
        };
      }

    } catch (error) {
      console.error('Error al cargar registros:', error);
      if (typeof window.showToast === 'function') {
        window.showToast('Error al cargar los registros: ' + error.message, 'error');
      } else {
        alert('Error al cargar los registros: ' + error.message);
      }
    } finally {
      // Ocultar loader
      if (window.PT && window.PT.loader) {
        window.PT.loader.hide();
      }
    }
  }

  // Función para guardar calendarios seleccionados
  window.guardarCalendariosSeleccionados = async function() {
    const selectCalendario = document.getElementById('selectCalendario');
    const calendarioId = selectCalendario?.value;

    if (!calendarioId || calendarioId === '') {
      if (typeof window.showToast === 'function') {
        window.showToast('Por favor selecciona un calendario', 'warning');
      } else {
        alert('Por favor selecciona un calendario');
      }
      return;
    }

    const checkboxes = document.querySelectorAll('.checkbox-registro:checked');
    const registrosSeleccionados = Array.from(checkboxes).map(cb => cb.dataset.registroId);

    if (registrosSeleccionados.length === 0) {
      if (typeof window.showToast === 'function') {
        window.showToast('Por favor selecciona al menos un registro', 'warning');
      } else {
        alert('Por favor selecciona al menos un registro');
      }
      return;
    }

    try {
      // Mostrar loader
      if (window.PT && window.PT.loader) {
        window.PT.loader.show();
      }

      // Llamar al endpoint para actualizar los calendarios
      const response = await fetch('/planeacion/programa-tejido/actualizar-calendarios-masivo', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({
          calendario_id: calendarioId,
          registros_ids: registrosSeleccionados
        })
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Error al actualizar los calendarios');
      }

      // Mostrar mensaje de éxito con información del tiempo
      const tiempoMsg = result.data?.tiempo_segundos ? ` en ${result.data.tiempo_segundos}s` : '';
      if (typeof window.showToast === 'function') {
        window.showToast(result.message || `Se actualizaron ${registrosSeleccionados.length} registro(s) con el calendario ${calendarioId}${tiempoMsg}`, 'success');
      } else {
        alert(result.message || `Se actualizaron ${registrosSeleccionados.length} registro(s) con el calendario ${calendarioId}${tiempoMsg}`);
      }

      // Cerrar el modal
      cerrarModalActCalendarios();

      // Recargar la página para reflejar los cambios
      setTimeout(() => {
        window.location.reload();
      }, 500);

    } catch (error) {
      console.error('Error al actualizar calendarios:', error);
      if (typeof window.showToast === 'function') {
        window.showToast('Error al actualizar los calendarios: ' + error.message, 'error');
      } else {
        alert('Error al actualizar los calendarios: ' + error.message);
      }
    } finally {
      // Ocultar loader
      if (window.PT && window.PT.loader) {
        window.PT.loader.hide();
      }
    }
  };

  // Cerrar modal al hacer click fuera de él
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalActCalendarios');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          cerrarModalActCalendarios();
        }
      });
    }
  });
</script>

<style>
  #tablaRegistros {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
  }

  #tablaRegistros td {
    border-bottom: 1px solid #e5e7eb;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  #tablaRegistros tbody tr:last-child td {
    border-bottom: 0;
  }

  #tablaRegistros tbody tr:hover {
    background-color: #f3f4f6;
  }
</style>
