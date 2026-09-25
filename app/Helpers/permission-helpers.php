<?php

use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

if (! function_exists('userCan')) {
    /**
     * Verificar si el usuario actual tiene un permiso específico
     *
     * @param  string  $action  - 'crear', 'modificar', 'eliminar', 'acceso', 'registrar'
     * @param  string|int  $module  - Nombre del módulo o ID del rol
     */
    function userCan(string $action, $module): bool
    {
        $userId = Auth::id();

        if (! $userId) {
            return false;
        }

        try {
            $permission = userPermissions($module);

            if (! $permission) {
                return false;
            }

            return isset($permission->$action) && $permission->$action == 1;

        } catch (Exception $e) {
            Log::error('Error checking permission', [
                'action' => $action,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

if (! function_exists('userEsArea')) {
    /**
     * Verificar si el usuario actual pertenece a un área (campo `area` de SYSUsuario).
     *
     * Complementa a userCan(): hay reglas de negocio que dependen del área a la que
     * pertenece la persona y no de un permiso configurable por módulo (ej. solo el
     * área de Calidad puede aprobar o rechazar una orden de urdido).
     */
    function userEsArea(string $area): bool
    {
        $areaUsuario = mb_strtolower(trim((string) (Auth::user()->area ?? '')));

        return $areaUsuario !== '' && $areaUsuario === mb_strtolower(trim($area));
    }
}

if (! function_exists('moduleNameForRoute')) {
    /**
     * Obtener el nombre del módulo en SYSRoles para una ruta.
     * Útil para validar permisos en pantallas que tienen su propio módulo (ej. Producción Urdido).
     *
     * @param  string|null  $path  Ruta a buscar (ej. 'urdido/modulo-produccion-urdido'). Si null, usa request()->path()
     * @return string|null Nombre del módulo o null si no se encuentra
     */
    function moduleNameForRoute(?string $path = null): ?string
    {
        $rutaNormalizada = '/'.ltrim($path ?? request()->path(), '/');

        // PERF-04: hasta 3 queries a SYSRoles (una con LIKE '%x%') por llamada. Se memoiza por
        // request en el contenedor (como userPermissions) y entre requests en la cache con el
        // prefijo y TTL de ModuloService. El "no encontrado" también se guarda (como ''). La
        // versión la rota cualquier escritura de SYSRoles (AppServiceProvider::boot).
        $memo = app()->bound('permisos.modulo_ruta') ? app('permisos.modulo_ruta') : [];
        if (! array_key_exists($rutaNormalizada, $memo)) {
            $prefijo = moduleNameForRouteCachePrefix();
            $version = Cache::rememberForever($prefijo.'_version', fn (): string => Str::random(8));
            $llave = $prefijo.'_'.$version.'_'.sha1($rutaNormalizada);

            $memo[$rutaNormalizada] = Cache::remember(
                $llave,
                3600, // ModuloService::CACHE_TTL
                fn (): string => buscarModuloPorRuta($rutaNormalizada) ?? '',
            );
            app()->instance('permisos.modulo_ruta', $memo);
        }

        return $memo[$rutaNormalizada] === '' ? null : $memo[$rutaNormalizada];
    }
}

if (! function_exists('moduleNameForRouteCachePrefix')) {
    /** Mismo prefijo que ModuloService::getCachePrefix() (modulos_v3_<APP_ENV>). */
    function moduleNameForRouteCachePrefix(): string
    {
        return 'modulos_v3_'.app()->environment().'_modulo_ruta';
    }
}

if (! function_exists('olvidarModulosPorRuta')) {
    /** Invalida lo que cacheó moduleNameForRoute(): se llama al escribir SYSRoles. */
    function olvidarModulosPorRuta(): void
    {
        Cache::forget(moduleNameForRouteCachePrefix().'_version');
        app()->forgetInstance('permisos.modulo_ruta');
    }
}

if (! function_exists('buscarModuloPorRuta')) {
    /** Las 3 búsquedas originales de moduleNameForRoute(), sin caché. */
    function buscarModuloPorRuta(string $rutaNormalizada): ?string
    {
        // 1. Buscar coincidencia exacta
        $modulo = SYSRoles::where('Ruta', $rutaNormalizada)->select('modulo')->first();
        if ($modulo) {
            return $modulo->modulo;
        }

        // 2. Buscar por prefijo (ruta más específica)
        $modulo = SYSRoles::where('Ruta', 'LIKE', $rutaNormalizada.'%')
            ->select('modulo')
            ->orderByRaw('LEN(Ruta) DESC')
            ->first();
        if ($modulo) {
            return $modulo->modulo;
        }

        // 3. Buscar por última parte de la ruta (ej. modulo-produccion-urdido)
        $partes = array_filter(explode('/', trim($rutaNormalizada, '/')));
        if (count($partes) > 0) {
            $ultimaParte = end($partes);
            $modulo = SYSRoles::where('Ruta', 'LIKE', '%'.$ultimaParte.'%')
                ->select('modulo')
                ->orderByRaw('LEN(Ruta) DESC')
                ->first();
            if ($modulo) {
                return $modulo->modulo;
            }
        }

        return null;
    }
}

if (! function_exists('userPermissions')) {
    /**
     * Obtener todos los permisos del usuario para un módulo
     *
     * @param  string|int  $module  - Nombre del módulo o ID del rol
     * @return object|null
     */
    function userPermissions($module, ?int $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if (! $userId) {
            return null;
        }

        try {
            // Memoización por request: SYSRoles es un catálogo chico y cada query paga ~40ms de red,
            // así que se cargan completos una sola vez en lugar de un query por módulo consultado.
            // Se guarda en el contenedor y no en static: así el ciclo de vida es exactamente
            // el del request (y cada test arranca con la memoria limpia).
            if (! app()->bound('permisos.roles')) {
                // Solo idrol/modulo: evita arrastrar 'imagen' y demás columnas pesadas
                app()->instance(
                    'permisos.roles',
                    SYSRoles::select('idrol', 'modulo')->get()->keyBy(fn ($r) => mb_strtolower($r->modulo))
                );
            }

            $rolesPorModulo = app('permisos.roles');

            if (is_numeric($module)) {
                $rolId = (int) $module;
            } else {
                $rol = $rolesPorModulo[mb_strtolower($module)] ?? null;

                if (! $rol) {
                    return null;
                }

                $rolId = $rol->idrol;
            }

            $claveUsuario = 'permisos.usuario.'.$userId;
            if (! app()->bound($claveUsuario)) {
                app()->instance(
                    $claveUsuario,
                    SYSUsuariosRoles::where('idusuario', $userId)->get()->keyBy('idrol')
                );
            }

            return app($claveUsuario)[$rolId] ?? null;

        } catch (Exception $e) {
            Log::error('Error getting user permissions', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
