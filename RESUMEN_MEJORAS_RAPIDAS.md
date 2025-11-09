# Resumen de Mejoras Rápidas - Towell

Este documento contiene un resumen ejecutivo de las mejoras más impactantes que se pueden implementar rápidamente.

## Mejoras de Alto Impacto y Fácil Implementación

### 1. Agregar Índices a la Base de Datos (30 minutos)

**Impacto**: Reducción del 50-70% en tiempo de consultas

```sql
-- Ejecutar en SQL Server Management Studio
CREATE NONCLUSTERED INDEX IX_SYSUsuariosRoles_idusuario_idrol 
ON SYSUsuariosRoles (idusuario, idrol) 
INCLUDE (acceso, crear, modificar, eliminar, registrar);

CREATE NONCLUSTERED INDEX IX_SYSRoles_Nivel_Dependencia 
ON SYSRoles (Nivel, Dependencia) 
INCLUDE (orden, modulo, imagen);

CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_Salon_NoTelar 
ON ReqProgramaTejido (SalonTejidoId, NoTelarId) 
INCLUDE (EnProceso, Ultimo, FechaInicio, FechaFinal);
```

### 2. Agregar Límites a Consultas sin Paginación (1 hora)

**Archivos a modificar**:
- `app/Http/Controllers/UsuarioController.php` - línea 83
- `app/Http/Controllers/ReservarProgramarController.php` - línea 24

**Antes**:
```php
$usuarios = Usuario::query()->get(); // Sin límite
```

**Después**:
```php
$usuarios = Usuario::query()
    ->limit(100) // Agregar límite
    ->get();
```

### 3. Implementar Caché para Módulos (2 horas)

**Archivo**: `app/Http/Controllers/UsuarioController.php`

**Mejora**: El caché ya existe pero se puede optimizar invalidándolo correctamente.

### 4. Optimizar Consultas N+1 (3 horas)

**Archivo**: `app/Http/Controllers/UsuarioController.php` - método `index()`

**Antes**:
```php
$modulos = SYSRoles::orderBy('orden')->get();
// Luego se consultan permisos individualmente
```

**Después**:
```php
$usuarioActual = Auth::user();
$modulos = SYSRoles::with(['permisosUsuario' => function($q) use ($usuarioActual) {
    $q->where('idusuario', $usuarioActual->idusuario);
}])
->whereHas('permisosUsuario', function($q) use ($usuarioActual) {
    $q->where('idusuario', $usuarioActual->idusuario)->where('acceso', true);
})
->where('Nivel', 1)
->whereNull('Dependencia')
->orderBy('orden')
->get();
```

**Nota**: Requiere agregar relación en el modelo `SYSRoles`:

```php
// app/Models/SYSRoles.php
public function permisosUsuario()
{
    return $this->hasMany(SYSUsuariosRoles::class, 'idrol', 'idrol');
}
```

### 5. Agrupar Consultas en Loops (2 horas)

**Archivo**: `app/Http/Controllers/ProgramaTejidoController.php` - línea 221

**Antes**:
```php
foreach ($request->input('telares', []) as $fila) {
    DB::statement("UPDATE ReqProgramaTejido..."); // Query por cada iteración
}
```

**Después**:
```php
// Agrupar todos los telares a actualizar
$telaresIds = array_column($request->input('telares', []), 'no_telar_id');

// Una sola consulta
DB::table('ReqProgramaTejido')
    ->where('SalonTejidoId', $salon)
    ->whereIn('NoTelarId', $telaresIds)
    ->whereIn('Ultimo', ['1', 'UL'])
    ->update(['Ultimo' => 0]);
```

### 6. Optimizar Búsquedas con LIKE (1 hora)

**Archivo**: `app/Http/Controllers/ReservarProgramarController.php` - línea 61

**Antes**:
```php
$q->where($col, 'like', "%{$val}%"); // Lento con comodín al inicio
```

**Después**:
```php
// Si la búsqueda tiene al menos 3 caracteres, usar comodín solo al final
if (strlen($val) >= 3) {
    $q->where($col, 'like', "{$val}%"); // Más rápido con índice
} else {
    // Para búsquedas cortas, considerar búsqueda exacta
    $q->where($col, $val);
}
```

### 7. Crear Form Request para Validación (1 hora por formulario)

**Ejemplo**: Crear `app/Http/Requests/StoreProgramaTejidoRequest.php`

**Beneficios**:
- Validación centralizada
- Mensajes de error personalizados
- Código más limpio en controladores

### 8. Implementar Rate Limiting (30 minutos)

**Archivo**: `routes/web.php`

```php
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    // Rutas protegidas - máximo 60 requests por minuto
});
```

## Plan de Implementación Recomendado

### Semana 1: Mejoras de Base de Datos
- ✅ Agregar índices
- ✅ Optimizar consultas con LIKE
- ✅ Agregar límites a consultas

### Semana 2: Optimización de Consultas
- ✅ Optimizar consultas N+1
- ✅ Agrupar consultas en loops
- ✅ Implementar caché mejorado

### Semana 3: Refactorización de Código
- ✅ Crear Form Requests
- ✅ Extraer lógica a servicios
- ✅ Implementar rate limiting

## Métricas para Medir Mejoras

### Antes de Implementar
1. Registrar tiempo de carga de páginas principales
2. Contar número de consultas SQL por request
3. Medir tiempo de respuesta de APIs
4. Registrar uso de memoria

### Después de Implementar
1. Comparar tiempos de carga
2. Verificar reducción de consultas
3. Medir mejora en tiempo de respuesta
4. Comparar uso de memoria

## Herramientas para Monitoreo

### Laravel Debugbar
```bash
composer require barryvdh/laravel-debugbar --dev
```

### Laravel Telescope (Solo desarrollo)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

### Query Log
```php
// En AppServiceProvider
DB::listen(function ($query) {
    Log::info('Query: ' . $query->sql);
    Log::info('Time: ' . $query->time);
});
```

## Checklist de Implementación

### Fase 1: Base de Datos
- [ ] Crear índices recomendados
- [ ] Verificar que los índices se usan (EXPLAIN PLAN)
- [ ] Monitorear mejora en tiempo de consultas

### Fase 2: Consultas
- [ ] Agregar límites a consultas sin paginación
- [ ] Optimizar consultas N+1
- [ ] Agrupar consultas en loops
- [ ] Optimizar búsquedas con LIKE

### Fase 3: Caché
- [ ] Verificar que el caché funciona correctamente
- [ ] Implementar invalidación de caché
- [ ] Considerar usar Redis para producción

### Fase 4: Código
- [ ] Crear Form Requests principales
- [ ] Extraer lógica a servicios
- [ ] Implementar rate limiting
- [ ] Agregar validación de permisos

## Notas Importantes

1. **Hacer backups** antes de cualquier cambio en producción
2. **Probar en desarrollo** antes de implementar en producción
3. **Monitorear** el impacto de cada cambio
4. **Documentar** los cambios realizados
5. **Comunicar** los cambios al equipo

## Soporte

Para dudas o problemas durante la implementación, consultar:
- Documentación completa: `MEJORAS_PROYECTO.md`
- Documentación de Laravel: https://laravel.com/docs
- Documentación de SQL Server: https://docs.microsoft.com/sql


