# 📦 Guía de Componentes Reutilizables - TOWELL

## 🎯 Objetivo

Este documento proporciona una referencia completa de todos los componentes Blade reutilizables disponibles en el proyecto TOWELL. Los componentes siguen las mejores prácticas de código limpio y evitan la duplicación (principio DRY - Don't Repeat Yourself).

---

## 📋 Índice de Componentes

1. [Alert](#1-alert) - Mensajes de alerta
2. [Page Header](#2-page-header) - Headers de página
3. [Form Select](#3-form-select) - Selectores de formulario
4. [Form Input](#4-form-input) - Inputs de formulario
5. [Action Button](#5-action-button) - Botones de acción
6. [Card](#6-card) - Tarjetas contenedoras
7. [Module Grid](#7-module-grid) - Grid de módulos
8. [Back Button](#8-back-button) - Botón de retroceso
9. [Navigation Bar](#9-navigation-bar) - Barra de navegación

---

## 1. Alert

### Descripción
Componente para mostrar mensajes de alerta con diferentes tipos (error, success, warning, info).

### Props
| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `type` | string | `'info'` | Tipo de alerta: 'error', 'success', 'warning', 'info' |
| `title` | string | `null` | Título de la alerta |
| `message` | string | `null` | Mensaje principal |
| `items` | array | `[]` | Lista de mensajes para bullets |
| `dismissible` | bool | `true` | Si la alerta puede cerrarse |

### Ejemplos

```blade
<!-- Alerta de éxito simple -->
<x-alert type="success" title="¡Éxito!" message="Operación completada correctamente" />

<!-- Alerta de error con lista -->
<x-alert type="error" title="Errores encontrados" :items="$errors->all()" />

<!-- Alerta con contenido personalizado -->
<x-alert type="warning">
    <p>Tu contenido personalizado aquí</p>
</x-alert>

<!-- Mostrar errores de validación -->
@if ($errors->any())
    <x-alert type="error" title="Lo sentimos, ocurrió un problema:" :items="$errors->all()" />
@endif

<!-- Mostrar mensaje de sesión -->
@if (session('success'))
    <x-alert type="success" title="¡Operación exitosa!" :message="session('success')" />
@endif
```

### Reemplaza
```blade
<!-- ANTES -->
@if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
        <strong class="font-bold">Lo sentimos, ocurrió un problema:</strong>
        <ul class="list-disc pl-5 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- DESPUÉS -->
@if ($errors->any())
    <x-alert type="error" title="Lo sentimos, ocurrió un problema:" :items="$errors->all()" />
@endif
```

---

## 2. Page Header

### Descripción
Header de página con gradiente y estilos consistentes para títulos de secciones.

### Props
| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `title` | string | requerido | Título principal |
| `subtitle` | string | `null` | Subtítulo opcional |
| `gradient` | string | `'blue'` | Tipo de gradiente: 'blue', 'yellow', 'green', 'red', 'purple' |
| `size` | string | `'lg'` | Tamaño del texto: 'sm', 'md', 'lg', 'xl' |
| `centered` | bool | `true` | Si el contenido debe estar centrado |
| `rounded` | bool | `true` | Si debe tener bordes redondeados |

### Ejemplos

```blade
<!-- Header simple -->
<x-page-header title="PRODUCCIÓN EN PROCESO" />

<!-- Header con subtítulo y gradiente amarillo -->
<x-page-header 
    title="PROGRAMACIÓN DE REQUERIMIENTOS" 
    subtitle="Seleccione los elementos a programar"
    gradient="yellow" 
/>

<!-- Header con acciones adicionales -->
<x-page-header title="Dashboard">
    <x-slot:actions>
        <x-action-button>Actualizar</x-action-button>
    </x-slot:actions>
</x-page-header>
```

### Reemplaza
```blade
<!-- ANTES -->
<div class="bg-gradient-to-r from-blue-500 via-blue-400 to-blue-600 rounded-2xl shadow-lg p-4">
    <h1 class="text-2xl md:text-3xl font-bold text-white text-center">
        PRODUCCIÓN EN PROCESO
    </h1>
</div>

<!-- DESPUÉS -->
<x-page-header title="PRODUCCIÓN EN PROCESO" />
```

---

## 3. Form Select

### Descripción
Select/dropdown reutilizable con label y estilos consistentes.

### Props
| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `name` | string | requerido | Nombre del campo |
| `label` | string | `null` | Label del campo |
| `options` | array | `[]` | Array de opciones |
| `selected` | string | `null` | Valor seleccionado |
| `required` | bool | `false` | Si es requerido |
| `placeholder` | string | `'Seleccione una opción'` | Texto del placeholder |
| `labelWidth` | string | `'w-28'` | Ancho del label |
| `inline` | bool | `true` | Label en línea |

### Ejemplos

```blade
<!-- Select básico -->
<x-form-select 
    name="telar" 
    label="Telar:" 
    :options="range(207, 230)" 
    required 
/>

<!-- Select con opciones personalizadas -->
<x-form-select 
    name="tipo" 
    label="Tipo:" 
    :options="['mecanica' => 'Mecánica', 'electrica' => 'Eléctrica']"
    selected="mecanica"
/>

<!-- Select de una colección -->
<x-form-select 
    name="categoria" 
    label="Categoría:" 
    :options="$categorias->pluck('nombre', 'id')"
/>
```

### Reemplaza
```blade
<!-- ANTES -->
<div class="flex items-center gap-2">
    <label class="w-28 text-base font-semibold text-gray-800">Telar:</label>
    <select name="telar" class="flex-1 p-1 border border-gray-300 rounded text-sm" required>
        @for ($i = 207; $i <= 230; $i++)
            <option value="{{ $i }}">{{ $i }}</option>
        @endfor
    </select>
</div>

<!-- DESPUÉS -->
<x-form-select name="telar" label="Telar:" :options="range(207, 230)" required />
```

---

## 4. Form Input

### Descripción
Input de formulario reutilizable con label, validación y diferentes tipos.

### Props
| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `name` | string | requerido | Nombre del campo |
| `label` | string | `null` | Label del campo |
| `type` | string | `'text'` | Tipo: 'text', 'number', 'email', 'password', 'date', 'time' |
| `value` | string | `null` | Valor del campo |
| `required` | bool | `false` | Si es requerido |
| `placeholder` | string | `null` | Placeholder |
| `labelWidth` | string | `'w-28'` | Ancho del label |
| `inline` | bool | `true` | Label en línea |
| `help` | string | `null` | Texto de ayuda |

### Ejemplos

```blade
<!-- Input de texto -->
<x-form-input name="nombre" label="Nombre:" required />

<!-- Input numérico -->
<x-form-input 
    name="cantidad" 
    label="Cantidad:" 
    type="number" 
    :value="old('cantidad', 10)"
/>

<!-- Input con ayuda -->
<x-form-input 
    name="email" 
    label="Email:" 
    type="email"
    help="Ingrese un email válido"
/>
```

---

## 5. Action Button

### Descripción
Botón de acción con diferentes variantes, tamaños e iconos.

### Props
| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `variant` | string | `'primary'` | Variante: 'primary', 'success', 'danger', 'warning', 'secondary' |
| `size` | string | `'md'` | Tamaño: 'sm', 'md', 'lg' |
| `type` | string | `'button'` | Tipo: 'button', 'submit', 'reset' |
| `icon` | string | `null` | Icono: 'check', 'plus', 'trash', 'edit', 'save' |
| `loading` | bool | `false` | Estado de carga |
| `fullWidth` | bool | `false` | Ancho completo |

### Ejemplos

```blade
<!-- Botón primario -->
<x-action-button type="submit">
    Guardar
</x-action-button>

<!-- Botón de éxito con icono -->
<x-action-button variant="success" icon="check">
    Confirmar
</x-action-button>

<!-- Botón en estado de carga -->
<x-action-button :loading="$isProcessing">
    Procesando...
</x-action-button>

<!-- Botón de peligro grande -->
<x-action-button variant="danger" size="lg" icon="trash">
    Eliminar
</x-action-button>
```

---

## 6. Card

### Descripción
Tarjeta/contenedor para agrupar contenido relacionado.

### Props
| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `title` | string | `null` | Título de la tarjeta |
| `subtitle` | string | `null` | Subtítulo |
| `shadow` | bool | `true` | Con sombra |
| `border` | bool | `true` | Con borde |
| `rounded` | bool | `true` | Bordes redondeados |
| `padding` | string | `'md'` | Padding: 'none', 'sm', 'md', 'lg' |
| `bg` | string | `'white'` | Fondo: 'white', 'gray', 'blue' |

### Ejemplos

```blade
<!-- Card simple -->
<x-card title="Información del Producto">
    <p>Detalles del producto...</p>
</x-card>

<!-- Card con header y footer slots -->
<x-card>
    <x-slot:header>
        <h3 class="text-xl font-bold">Header Personalizado</h3>
    </x-slot:header>
    
    <div class="space-y-4">
        <p>Contenido principal de la tarjeta</p>
    </div>
    
    <x-slot:footer>
        <x-action-button>Acción</x-action-button>
    </x-slot:footer>
</x-card>

<!-- Card sin padding (para imágenes) -->
<x-card padding="none">
    <img src="imagen.jpg" class="w-full">
    <div class="p-4">
        <p>Texto debajo de la imagen</p>
    </div>
</x-card>
```

---

## 🎨 Mejores Prácticas

### 1. **Consistencia**
Usa siempre los componentes en lugar de HTML duplicado:
```blade
<!-- ✅ BIEN -->
<x-alert type="success" message="Guardado correctamente" />

<!-- ❌ MAL -->
<div class="bg-green-100 border...">Guardado correctamente</div>
```

### 2. **Reutilización**
Si encuentras código repetido, considera crear un componente:
```blade
<!-- Si esto se repite en múltiples archivos -->
<div class="flex items-center gap-2">
    <label>Campo:</label>
    <input type="text">
</div>

<!-- Créa un componente -->
<x-form-input name="campo" label="Campo:" />
```

### 3. **Documentación**
Cada componente incluye documentación inline con:
- Descripción clara
- Lista de props con tipos y defaults
- Ejemplos de uso
- Casos de uso comunes

### 4. **Composición**
Los componentes se pueden componer entre sí:
```blade
<x-card title="Formulario de Registro">
    <form method="POST">
        @csrf
        <x-form-input name="nombre" label="Nombre:" required />
        <x-form-input name="email" label="Email:" type="email" required />
        <x-action-button type="submit" variant="success">
            Registrar
        </x-action-button>
    </form>
</x-card>
```

---

## 📝 Guía de Migración

### Paso 1: Identificar código duplicado
Busca patrones repetidos en tus vistas blade.

### Paso 2: Reemplazar con componentes
Usa los componentes existentes o crea nuevos siguiendo el patrón.

### Paso 3: Probar
Verifica que la funcionalidad y el diseño se mantengan.

### Paso 4: Limpiar
Elimina el código antiguo una vez verificado.

---

## 🔧 Creación de Nuevos Componentes

Si necesitas crear un nuevo componente:

1. **Crea el archivo** en `resources/views/components/`
2. **Documenta el componente** siguiendo el formato:
```blade
{{--
    Componente: Nombre
    
    Descripción:
        [Descripción detallada]
    
    Props:
        @param tipo $nombre - Descripción
    
    Uso:
        [Ejemplos de uso]
--}}
```
3. **Define los props** con valores por defecto
4. **Implementa la lógica** y el HTML
5. **Actualiza esta documentación**

---

## 📚 Recursos Adicionales

- [Laravel Blade Components](https://laravel.com/docs/blade#components)
- [Tailwind CSS](https://tailwindcss.com/)
- [Principios SOLID](https://es.wikipedia.org/wiki/SOLID)
- [DRY Principle](https://es.wikipedia.org/wiki/No_te_repitas)

---

**Última actualización:** {{ date('d/m/Y') }}
**Mantenedor:** Equipo de Desarrollo TOWELL




















