# Ejemplos Visuales de Títulos

## 🎨 Guía Visual de Uso del Componente page-title

### 1. Título Simple (Sin Componente)
```php
@section('page-title')
    Producción en Proceso
@endsection
```
**Cuándo usar:** Para páginas simples sin necesidad de decoración adicional.

---

### 2. Título con Ícono - Azul (Default)
```php
@section('page-title')
    <x-page-title 
        title="Catálogo de Telares" 
        icon="fas fa-industry"
    />
@endsection
```
**Resultado Visual:**
- 🔵 Ícono en círculo azul claro
- Título con gradiente azul
- Efecto hover: escala y rotación suave

---

### 3. Título con Ícono y Subtítulo - Verde
```php
@section('page-title')
    <x-page-title 
        title="Importación Exitosa" 
        icon="fas fa-check-circle"
        subtitle="Todos los datos se procesaron correctamente"
        color="green"
    />
@endsection
```
**Resultado Visual:**
- 🟢 Ícono en círculo verde
- Título con gradiente verde
- Subtítulo en gris debajo

---

### 4. Título con Badge - Morado
```php
@section('page-title')
    <x-page-title 
        title="Dashboard Avanzado" 
        icon="fas fa-chart-pie"
        badge="Beta"
        color="purple"
    />
@endsection
```
**Resultado Visual:**
- 🟣 Ícono en círculo morado
- Título con gradiente morado
- Badge "Beta" en pill morado claro

---

### 5. Título Completo - Naranja
```php
@section('page-title')
    <x-page-title 
        title="Tareas Pendientes" 
        icon="fas fa-tasks"
        subtitle="Acciones que requieren revisión"
        badge="12"
        color="orange"
    />
@endsection
```
**Resultado Visual:**
- 🟠 Ícono en círculo naranja
- Título con gradiente naranja
- Subtítulo descriptivo
- Badge "12" indicando cantidad

---

### 6. Título de Alerta - Rojo
```php
@section('page-title')
    <x-page-title 
        title="Paros Activos" 
        icon="fas fa-exclamation-triangle"
        subtitle="Requiere atención inmediata"
        badge="URGENTE"
        color="red"
    />
@endsection
```
**Resultado Visual:**
- 🔴 Ícono en círculo rojo
- Título con gradiente rojo
- Subtítulo de advertencia
- Badge "URGENTE" resaltado

---

## 🎯 Casos de Uso por Módulo

### Módulo de Producción
```php
<x-page-title 
    title="Producción en Proceso" 
    icon="fas fa-cogs"
    subtitle="Monitoreo en tiempo real"
    color="blue"
/>
```

### Módulo de Planeación
```php
<x-page-title 
    title="Planeación de Producción" 
    icon="fas fa-calendar-alt"
    subtitle="Programación semanal"
    color="purple"
/>
```

### Módulo de Calidad
```php
<x-page-title 
    title="Control de Calidad" 
    icon="fas fa-clipboard-check"
    subtitle="Inspecciones y auditorías"
    badge="En Vivo"
    color="green"
/>
```

### Módulo de Inventario
```php
<x-page-title 
    title="Inventario de Materia Prima" 
    icon="fas fa-boxes"
    subtitle="Stock disponible"
    badge="Bajo Stock"
    color="orange"
/>
```

### Módulo de Fallas
```php
<x-page-title 
    title="Registro de Fallas" 
    icon="fas fa-tools"
    subtitle="Incidencias reportadas"
    badge="5 Activas"
    color="red"
/>
```

---

## 🔧 Tips de Diseño

### Íconos Recomendados por Categoría

**Gestión y Administración:**
- `fas fa-cog` - Configuración
- `fas fa-users` - Usuarios
- `fas fa-user-shield` - Administrador
- `fas fa-database` - Base de datos

**Producción y Procesos:**
- `fas fa-industry` - Fábrica/Telares
- `fas fa-cogs` - Procesos
- `fas fa-tachometer-alt` - Dashboard
- `fas fa-chart-line` - Eficiencia

**Planeación y Calendario:**
- `fas fa-calendar-alt` - Calendario
- `fas fa-calendar-check` - Planeación
- `fas fa-clock` - Horarios
- `fas fa-tasks` - Tareas

**Alertas y Notificaciones:**
- `fas fa-bell` - Notificaciones
- `fas fa-exclamation-triangle` - Advertencias
- `fas fa-exclamation-circle` - Alertas
- `fas fa-fire` - Urgente

**Reportes y Análisis:**
- `fas fa-chart-bar` - Gráficos
- `fas fa-chart-pie` - Estadísticas
- `fas fa-file-alt` - Reportes
- `fas fa-analytics` - Análisis

---

## 📱 Comportamiento Responsive

### Móvil (< 768px)
- Ícono: 40px × 40px
- Título: 1rem (16px)
- Animación más rápida (0.4s)

### Tablet (768px - 1024px)
- Ícono: 48px × 48px
- Título: 1.5rem (24px)
- Optimizado para touch

### Desktop (> 1024px)
- Ícono: 48px × 48px
- Título: 2rem (32px)
- Efectos hover completos

---

## ✨ Efectos Interactivos

### Animación de Entrada
- **Duración:** 0.6 segundos
- **Efecto:** Fade-in + Scale (95% → 100%)
- **Curva:** Cubic-bezier suave

### Hover en Ícono
- **Escala:** 110%
- **Rotación:** 5 grados
- **Sombra:** Aumenta

### Gradiente en Título
- **Efecto:** Background-clip text
- **Colores:** Degradado del color seleccionado
- **Transición:** Suave y elegante

---

## 🚀 Mejores Prácticas

1. **Usa íconos descriptivos** que representen claramente la función del módulo
2. **Subtítulos concisos** - máximo 50 caracteres
3. **Badges para datos numéricos** - ej: "5 Pendientes", "92%"
4. **Colores consistentes** - mantén el mismo color para módulos relacionados
5. **Evita redundancia** - no repitas información del título en el subtítulo

---

## 🎨 Paleta de Colores Completa

```
Blue:   #2563eb → #1d4ed8 (Default - Información)
Green:  #16a34a → #15803d (Éxito - Confirmación)
Purple: #9333ea → #7e22ce (Especial - Premium)
Orange: #ea580c → #c2410c (Advertencia Suave)
Red:    #dc2626 → #b91c1c (Alerta - Error)
```

---

## 📝 Checklist de Implementación

- [ ] Elegir el color apropiado según el contexto
- [ ] Seleccionar un ícono descriptivo de FontAwesome
- [ ] Escribir un título claro y conciso
- [ ] Agregar subtítulo si necesita contexto adicional
- [ ] Incluir badge solo si hay información relevante (número, estado)
- [ ] Verificar que se vea bien en móvil, tablet y desktop
- [ ] Probar la animación de entrada
- [ ] Confirmar que el ícono hace hover correctamente

