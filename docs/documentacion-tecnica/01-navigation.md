# Fase 01 - Navigation

## Objetivo

Esta fase resuelve el home autenticado, la jerarquia de modulos y submodulos, los breadcrumbs logicos y la entrega de fotos de usuario para la interfaz principal.

## Rutas principales

| Grupo | Rutas |
| --- | --- |
| Home | `GET /produccionProceso` |
| Jerarquia | `GET /submodulos/{modulo}`, `GET /submodulos-nivel3/{moduloPadre}` |
| API de navegacion | `GET /api/submodulos/{moduloPrincipal}`, `GET /api/modulo-padre` |
| Recursos | `GET /storage/usuarios/{filename}` |

## Controladores y funciones

| Archivo | Funciones documentadas | Funcion tecnica |
| --- | --- | --- |
| `app/Http/Controllers/UsuarioController.php` | `index`, `showSubModulos`, `showSubModulosNivel3`, `getSubModulosAPI`, `getModuloPadre` | Construye el menu, valida acceso por permisos y resuelve hijos de nivel 2 y 3. |
| `app/Http/Controllers/StorageController.php` | `usuarioFoto` | Sirve fotografias desde almacenamiento publico. |

## Archivos tecnicos relacionados

| Archivo | Rol en el modulo |
| --- | --- |
| `app/Services/ModuloService.php` | Consulta modulos por usuario y cachea resultados. |
| `app/Models/Sistema/SYSRoles.php` | Define `Nivel`, `Dependencia`, `Ruta`, `orden` y nombre de modulo. |
| `app/Models/Sistema/SYSUsuariosRoles.php` | Filtra acceso efectivo del usuario. |
| `resources/views/produccionProceso.blade.php` | Dashboard principal de modulos. |
| `resources/views/modulos/submodulos.blade.php` | Renderizado de submodulos. |
| `resources/views/components/layout/module-grid.blade.php` | Componente reutilizable para tarjetas de modulo. |

## Funcionamiento tecnico

1. Despues del login, `UsuarioController@index` consulta los modulos principales permitidos.
2. `ModuloService` usa cache por usuario para reducir consultas repetidas.
3. `showSubModulos` y `showSubModulosNivel3` resuelven el modulo padre usando nombre, ruta u orden.
4. `getModuloPadre` ayuda a reconstruir la ruta de navegacion desde el frontend.

## Diagrama

```mermaid
flowchart LR
    A[/produccionProceso/] --> B[UsuarioController index]
    B --> C[ModuloService]
    C --> D[SYSRoles]
    C --> E[SYSUsuariosRoles]
    B --> F[produccionProceso.blade.php]
    F --> G[Tarjetas de modulos]
    G --> H[/submodulos/{modulo}/]
    H --> I[showSubModulos]
    I --> J[submodulos.blade.php]
```

## Notas tecnicas

- Cambios de permisos requieren limpieza de cache para reflejarse de inmediato.
- La jerarquia depende de la integridad de `Nivel`, `Dependencia`, `Ruta` y `orden` en `SYSRoles`.
- El `module-grid` contiene casos especiales hardcodeados para algunos nombres historicos.
