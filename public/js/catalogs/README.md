# Sistema de Catálogos Refactorizado

## Arquitectura

Este sistema implementa una arquitectura limpia usando el patrón **Template Method** y **Herencia** para eliminar código duplicado en los catálogos.

### Estructura

```
public/js/catalogs/
├── CatalogBase.js          # Clase base abstracta
├── AplicacionesCatalog.js  # Implementación específica
└── README.md               # Esta documentación

resources/views/components/catalogs/
├── catalog-table.blade.php      # Componente de tabla reutilizable
└── catalog-form-field.blade.php # Componente de campo de formulario
```

## Clase Base: CatalogBase

La clase `CatalogBase` proporciona toda la funcionalidad común:

### Funcionalidades Incluidas

1. **Gestión de Estado**
   - Selección de filas
   - Estado de botones (habilitar/deshabilitar)
   - Filtros y caché de filtros
   - Datos originales y datos actuales

2. **Operaciones CRUD**
   - Create (Crear)
   - Read (Leer) - mediante renderizado de tabla
   - Update (Editar)
   - Delete (Eliminar)

3. **Filtros**
   - Sistema de filtros con caché
   - Limpiar filtros
   - Aplicar múltiples filtros

4. **Excel**
   - Subida de archivos Excel
   - Validación de archivos

5. **UI Helpers**
   - Toast notifications
   - Modales SweetAlert2
   - Validación de formularios

### Métodos Template (deben ser implementados)

```javascript
getCreateFormHTML()        // HTML del formulario de creación
getEditFormHTML(data)      // HTML del formulario de edición
validateCreateData(data)   // Validación antes de crear
validateEditData(data)     // Validación antes de editar
processData(data, action)  // Procesar datos antes de enviar
renderRow(item)            // Renderizar una fila de la tabla
getRowId(row)              // Obtener ID desde el elemento HTML
getRowData(row)            // Obtener datos desde el elemento HTML
```

## Crear un Nuevo Catálogo

### Paso 1: Crear la clase específica

```javascript
class MiCatalogo extends CatalogBase {
    constructor(config) {
        super({
            tableBodyId: 'mi-catalogo-body',
            route: 'mi-catalogo',
            idField: 'id',
            fields: [
                { name: 'campo1', label: 'Campo 1', type: 'text', required: true },
                { name: 'campo2', label: 'Campo 2', type: 'number', required: false }
            ],
            enableFilters: true,
            enableExcel: true,
            ...config
        });
    }

    getCreateFormHTML() {
        return `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label>Campo 1 *</label>
                    <input id="swal-campo1" type="text" class="swal2-input" required>
                </div>
                <div>
                    <label>Campo 2</label>
                    <input id="swal-campo2" type="number" class="swal2-input">
                </div>
            </div>
        `;
    }

    getEditFormHTML(data) {
        return `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label>Campo 1 *</label>
                    <input id="swal-edit-campo1" type="text" class="swal2-input" value="${data.campo1 || ''}" required>
                </div>
                <div>
                    <label>Campo 2</label>
                    <input id="swal-edit-campo2" type="number" class="swal2-input" value="${data.campo2 || ''}">
                </div>
            </div>
        `;
    }

    validateCreateData(data) {
        if (!data.campo1) {
            return { valid: false, message: 'Campo 1 es obligatorio' };
        }
        return { valid: true };
    }

    validateEditData(data) {
        return this.validateCreateData(data);
    }

    renderRow(item) {
        return `
            <td>${item.campo1 || ''}</td>
            <td>${item.campo2 || ''}</td>
        `;
    }

    extractFormData(action) {
        const prefix = action === 'create' ? 'swal-' : 'swal-edit-';
        return {
            campo1: document.getElementById(`${prefix}campo1`).value.trim(),
            campo2: document.getElementById(`${prefix}campo2`).value.trim()
        };
    }
}
```

### Paso 2: Crear la vista Blade

```blade
@extends('layouts.app')

@section('page-title', 'Mi Catálogo')

@section('content')
<div class="w-full">
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[640px]">
            <table class="table table-bordered table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th>Campo 1</th>
                        <th>Campo 2</th>
                    </tr>
                </thead>
                <tbody id="mi-catalogo-body">
                    @foreach ($items as $item)
                        <tr onclick="window.catalogManager?.selectRow(this, '{{ $item->id }}', '{{ $item->id }}')"
                            data-id="{{ $item->id }}"
                            data-campo1="{{ $item->campo1 }}"
                            data-campo2="{{ $item->campo2 }}">
                            <td>{{ $item->campo1 }}</td>
                            <td>{{ $item->campo2 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="{{ asset('js/catalogs/CatalogBase.js') }}"></script>
<script src="{{ asset('js/catalogs/MiCatalogo.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.catalogManager = new MiCatalogo({
        initialData: @json($items)
    });

    // Funciones globales para el navbar
    window.agregarMiCatalogo = () => window.catalogManager.create();
    window.editarMiCatalogo = () => window.catalogManager.edit();
    window.eliminarMiCatalogo = () => window.catalogManager.delete();
});
</script>
@endsection
```

## Beneficios de la Refactorización

1. **Código DRY (Don't Repeat Yourself)**
   - Eliminación de ~80% de código duplicado
   - Lógica común centralizada

2. **Mantenibilidad**
   - Cambios en un solo lugar afectan a todos los catálogos
   - Fácil de extender y modificar

3. **Consistencia**
   - Misma UX en todos los catálogos
   - Comportamiento predecible

4. **Testabilidad**
   - Clases más pequeñas y enfocadas
   - Fácil de testear individualmente

5. **Escalabilidad**
   - Agregar nuevos catálogos es rápido
   - Solo implementar métodos específicos

## Patrones de Diseño Utilizados

1. **Template Method Pattern**
   - Clase base define el flujo
   - Clases hijas implementan detalles específicos

2. **Strategy Pattern**
   - Diferentes estrategias de filtrado
   - Diferentes estrategias de validación

3. **Factory Pattern**
   - Creación de modales dinámicos
   - Creación de formularios

## Próximos Pasos

1. Refactorizar catálogos restantes:
   - Calendarios
   - Eficiencia
   - Telares
   - Velocidad
   - Matriz de Hilos
   - Codificación

2. Mejoras futuras:
   - Paginación
   - Ordenamiento
   - Búsqueda en tiempo real
   - Exportación a Excel
   - Importación masiva

