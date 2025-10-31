# ✅ Cambio: Altura de Tabla ReqProgramaTejidoLine

## Problema
La tabla de líneas diarias en la página de EDITAR ocupaba demasiada altura, obligando a hacer scroll en toda la pantalla.

## Solución
Agregué estilos CSS para limitar la altura de la tabla a 400px con scroll interno:

### Cambios en `resources/views/components/req-programa-tejido-line-table.blade.php`

**Antes:**
```blade
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
```

**Después:**
```blade
<div class="overflow-x-auto max-h-96" style="max-height: 400px; overflow-y: auto;">
    <table class="min-w-full divide-y divide-gray-200">
```

## Resultado
✅ La tabla de líneas diarias tiene altura máxima de 400px
✅ Si hay más de ~10-15 líneas, aparece barra de scroll INTERNO (no en toda la página)
✅ La página es más fluida sin necesidad de hacer scroll global

## Aplicaciones
- ✅ En EDIT: Tabla de líneas con scroll interno
- ✅ En CREATE: Tabla de líneas con scroll interno (después de guardar)

