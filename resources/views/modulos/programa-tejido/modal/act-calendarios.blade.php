{{-- Modal Actualizar Calendarios - Estructura tipo Excel --}}
<div id="modalActCalendarios" class="hidden fixed inset-0 overflow-hidden h-full w-full z-50 backdrop-blur-sm" style="display: none; background-color: rgba(0, 0, 0, 0.3);">
  <div class="relative top-4 mx-auto p-4 border w-11/12 max-w-7xl shadow-2xl rounded-lg bg-white overflow-hidden" style="height: 90vh; max-height: 90vh; display: flex; flex-direction: column;">
    {{-- Header del Modal --}}
    <div class="flex items-center justify-between mb-2 flex-shrink-0">
      <div class="text-sm font-semibold text-gray-700">
        Actualizar Calendarios
      </div>

    </div>

    {{-- Contenido del Modal --}}
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
      {{-- Select de Calendario --}}
      <div class="mb-2 flex-shrink-0 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
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
      <div class="flex-1 min-h-0 border border-gray-300 rounded-lg overflow-y-auto overflow-x-hidden">
        <table id="tablaRegistros" class="min-w-full bg-white text-sm">
          <tbody id="tbodyRegistros" class="divide-y divide-gray-200">
            {{-- Las filas se cargarán dinámicamente con JavaScript --}}
          </tbody>
        </table>
      </div>

      {{-- Botones de acción --}}
      <div class="flex justify-end gap-3 mt-3 pt-3 border-t border-gray-200 flex-shrink-0">
        <button type="button" onclick="cerrarModalActCalendarios()" class="px-6 py-2.5 bg-gray-500 text-white rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 shadow-md hover:shadow-lg transition-all font-medium">
          Cancelar
        </button>
        <button type="button" id="btnGuardarCalendarios" onclick="guardarCalendariosSeleccionados()" class="px-6 py-2.5 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-md hover:shadow-lg transition-all font-medium">
          Guardar
        </button>
      </div>
    </div>
  </div>
</div>

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
    modal.style.display = 'flex';
    modal.style.alignItems = 'flex-start';
    modal.style.justifyContent = 'center';
    document.body.style.overflow = 'hidden'; // Prevenir scroll del body

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
      modal.style.display = 'none';
      document.body.style.overflow = ''; // Restaurar scroll del body
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

      // Mostrar mensaje de éxito
      if (typeof window.showToast === 'function') {
        window.showToast(result.message || `Se actualizaron ${registrosSeleccionados.length} registro(s) con el calendario ${calendarioId}`, 'success');
      } else {
        alert(result.message || `Se actualizaron ${registrosSeleccionados.length} registro(s) con el calendario ${calendarioId}`);
      }

      // Cerrar el modal
      cerrarModalActCalendarios();

      // Recargar la página para reflejar los cambios
      // El recálculo ya se hizo en el backend a través de UpdateTejido
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

    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const modal = document.getElementById('modalActCalendarios');
        if (modal && !modal.classList.contains('hidden')) {
          cerrarModalActCalendarios();
        }
      }
    });
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
