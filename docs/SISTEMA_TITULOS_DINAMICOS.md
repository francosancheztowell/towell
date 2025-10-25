# Sistema de Títulos Dinámicos

## Descripción
Sistema flexible para mostrar títulos dinámicos en el navbar de la aplicación con animaciones y estilos responsive.

## Uso Básico

### Método 1: Usando @section('page-title')

En cualquier vista que extienda `layouts.app`, puedes agregar un título así:

```php
@extends('layouts.app')

@section('page-title')
    Producción en Proceso
@endsection

@section('content')
    <!-- Tu contenido aquí -->
@endsection
```

### Método 2: Usando el Componente page-title

Para títulos más elaborados con íconos y subtítulos:

```php
@extends('layouts.app')

@section('page-title')
    <x-page-title 
        title="Catálogo de Telares" 
        icon="fas fa-cog"
        subtitle="Gestión de maquinaria" 
    />
@endsection

@section('content')
    <!-- Tu contenido aquí -->
@endsection
```

## Ejemplos

### Título Simple
```php
@section('page-title')
    Producción en Proceso
@endsection
```

### Título con Ícono
```php
@section('page-title')
    <x-page-title 
        title="Planeación" 
        icon="fas fa-calendar-alt" 
    />
@endsection
```

### Título con Ícono y Subtítulo
```php
@section('page-title')
    <x-page-title 
        title="Catálogo de Eficiencia" 
        icon="fas fa-chart-line"
        subtitle="Estándares de producción" 
    />
@endsection
```

### Título con Badge (Insignia)
```php
@section('page-title')
    <x-page-title 
        title="Nueva Función" 
        icon="fas fa-star"
        badge="Nuevo" 
        color="green"
    />
@endsection
```

### Título con Color Personalizado
```php
@section('page-title')
    <x-page-title 
        title="Alertas Críticas" 
        icon="fas fa-exclamation-triangle"
        subtitle="Requiere atención inmediata"
        color="red"
    />
@endsection
```

### Título Completo con Todas las Opciones
```php
@section('page-title')
    <x-page-title 
        title="Panel de Control" 
        icon="fas fa-tachometer-alt"
        subtitle="Monitoreo en tiempo real"
        badge="En Vivo"
        color="purple"
    />
@endsection
```

### Título Dinámico con Variables
```php
@section('page-title')
    Orden #{{ $orden->numero }}
@endsection
```

## Propiedades del Componente page-title

| Propiedad | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `title` | string | Sí | El texto del título principal |
| `icon` | string | No | Clase de FontAwesome para el ícono |
| `subtitle` | string | No | Texto descriptivo debajo del título |
| `badge` | string | No | Texto de insignia (ej: "Nuevo", "Beta") |
| `color` | string | No | Color del tema: blue, green, purple, orange, red (default: blue) |

## Características del Diseño Mejorado

✅ **Responsive**: Se adapta perfectamente a móviles, tablets y desktop  
✅ **Animado**: Aparece con animación suave y elegante (fade-in + scale)  
✅ **Gradientes**: Títulos con efecto degradado de color  
✅ **Íconos Interactivos**: Efecto hover con escala y rotación  
✅ **Badges**: Insignias para resaltar información  
✅ **5 Esquemas de Color**: Blue, Green, Purple, Orange, Red  
✅ **Sombras Dinámicas**: Efectos de profundidad en hover  
✅ **Flexible**: Acepta texto simple o componentes complejos  
✅ **Accesible**: Usa etiquetas semánticas HTML5  
✅ **Optimizado para Tablets**: Tamaños ajustados específicamente

## Estilos CSS

El sistema incluye:
- Animación de fade-in al cargar
- Tamaños responsive (lg, md, base)
- Transiciones suaves
- Compatibilidad con tablets

## Migración de Código Antiguo

### Antes:
```php
@if (Route::currentRouteName() === 'produccion.index')
    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Producción en Proceso</h1>
@endif
```

### Después:
```php
@section('page-title')
    Producción en Proceso
@endsection
```

## Notas

- Si no se define `@section('page-title')`, no se mostrará ningún título (comportamiento opcional)
- El componente `page-title` es más adecuado para páginas de catálogos y módulos principales
- Para títulos simples, usar directamente `@section('page-title')` es suficiente
- Los íconos requieren FontAwesome (ya incluido en el proyecto)

## Paleta de Colores y Uso Recomendado

| Color | Uso Recomendado | Ejemplo |
|-------|-----------------|---------|
| `blue` (default) | Páginas principales, información general | Catálogos, Listados |
| `green` | Éxito, confirmaciones, procesos completados | Reportes exitosos, Datos actualizados |
| `purple` | Paneles especiales, dashboards, estadísticas | Panel de Control, Analytics |
| `orange` | Advertencias suaves, acciones pendientes | Tareas pendientes, Revisión requerida |
| `red` | Alertas, errores, situaciones críticas | Paros, Fallas, Emergencias |

## Ejemplos en Vistas Existentes

### produccionProceso.blade.php
```php
@section('page-title')
    Producción en Proceso
@endsection
```

### Catálogo de Telares (ejemplo)
```php
@section('page-title')
    <x-page-title 
        title="Catálogo de Telares" 
        icon="fas fa-industry"
        subtitle="Gestión de maquinaria de tejido"
        color="blue"
    />
@endsection
```

### Planeación (ejemplo)
```php
@section('page-title')
    <x-page-title 
        title="Planeación" 
        icon="fas fa-calendar-check"
        subtitle="Programación de producción"
        color="purple"
    />
@endsection
```

### Notificaciones de Paros (ejemplo)
```php
@section('page-title')
    <x-page-title 
        title="Paros de Telares" 
        icon="fas fa-exclamation-triangle"
        subtitle="Requiere atención inmediata"
        badge="3 Activos"
        color="red"
    />
@endsection
```

### Dashboard de Eficiencia (ejemplo)
```php
@section('page-title')
    <x-page-title 
        title="Eficiencia Global" 
        icon="fas fa-chart-line"
        subtitle="Indicadores de rendimiento"
        badge="92%"
        color="green"
    />
@endsection
```

