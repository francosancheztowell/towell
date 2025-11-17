# Rutas del Módulo de Simulación

## ✅ Configuración Completa

Se han configurado las siguientes rutas para el módulo de simulación:

### Rutas Principales

| Descripción | URL | Nombre de Ruta |
|-------------|-----|----------------|
| Vista principal (Programa Simulación) | `/simulacion` | `simulacion.index` |
| Alta de Pronósticos | `/simulacion/alta-pronosticos` | `simulacion.alta-pronosticos` |
| Altas Especiales | `/simulacion/altas-especiales` | `simulacion.altas-especiales` |
| Crear Nuevo Registro | `/simulacion/nuevo` | `simulacion.nuevo` |
| Nuevo Pronóstico | `/simulacion/pronosticos/nuevo` | `simulacion.pronosticos.nuevo` |
| Nueva Alta Especial | `/simulacion/altas-especiales/nuevo` | `simulacion.altas-especiales.nuevo` |

## 📁 Archivos de Vista Asociados

| Ruta | Archivo Vista |
|------|---------------|
| `/simulacion` | `resources/views/modulos/simulacion/req-programa-tejido.blade.php` |
| `/simulacion/alta-pronosticos` | `resources/views/modulos/simulacion/alta-pronosticos.blade.php` |
| `/simulacion/altas-especiales` | `resources/views/modulos/simulacion/altas-especiales.blade.php` |
| `/simulacion/nuevo` | `resources/views/modulos/simulacion/simulacionform/create.blade.php` |
| `/simulacion/pronosticos/nuevo` | `resources/views/modulos/simulacion/simulacionform/pronosticos.blade.php` |
| `/simulacion/altas-especiales/nuevo` | `resources/views/modulos/simulacion/simulacionform/altas.blade.php` |

## 🔧 Cómo Acceder

### Desde la aplicación:
1. Inicia sesión en la aplicación
2. Navega a: `http://localhost:8000/simulacion`
3. O accede directamente a cualquiera de las rutas listadas arriba

### Desde el código Laravel:
```php
// Redirigir a simulación
return redirect()->route('simulacion.index');

// Redirigir a alta de pronósticos
return redirect()->route('simulacion.alta-pronosticos');

// Generar URL
$url = route('simulacion.altas-especiales');
```

### Desde Blade:
```blade
{{-- Link a simulación --}}
<a href="{{ route('simulacion.index') }}">Ver Simulación</a>

{{-- Link a alta de pronósticos --}}
<a href="{{ route('simulacion.alta-pronosticos') }}">Alta de Pronósticos</a>
```

## 📊 Estado Actual

- ✅ Rutas configuradas en `routes/web.php`
- ✅ Vistas creadas en `resources/views/modulos/simulacion/`
- ✅ Estructura de carpetas completa
- ⚠️ Las vistas actualmente muestran datos vacíos (por defecto)
- ⚠️ Los controladores pueden agregarse más adelante si se necesita lógica adicional

## 💡 Notas Importantes

1. **Datos Vacíos**: Por ahora, las vistas de simulación muestran datos vacíos por defecto. Esto es intencional para que puedas agregar tu propia lógica y datos de prueba.

2. **Misma Estructura**: El módulo de simulación tiene exactamente la misma estructura que programa-tejido, solo que apunta a archivos diferentes.

3. **Sin Controladores**: Las rutas utilizan closures (funciones anónimas) directamente. Si necesitas lógica más compleja, puedes crear controladores dedicados.

4. **Próximos Pasos**:
   - Crear controladores si necesitas lógica backend
   - Crear modelos si necesitas una tabla de base de datos separada
   - Agregar datos de prueba o conectar a una tabla específica

## 🎨 Diferencias con Programa Tejido

Aunque las vistas son idénticas en estructura, están en carpetas separadas:
- **Programa Tejido**: `resources/views/modulos/programa-tejido/`
- **Simulación**: `resources/views/modulos/simulacion/`

Esto permite que ambos módulos coexistan y se puedan modificar independientemente.

