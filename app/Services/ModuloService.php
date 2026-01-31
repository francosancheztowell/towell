<?php

namespace App\Services;

use App\Models\Sistema\SYSRoles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;


class ModuloService
{
    private const CACHE_TTL = 3600; // 1 hora
    // Versionado para evitar que el cache viejo conserve rutas fallback incorrectas (acentos, '/')
    private const CACHE_PREFIX = 'modulos_v2';

    /**
     * Método genérico para obtener módulos por nivel y usuario
     * Optimizado para aprovechar índices: IX_SYSRoles_Nivel_Dependencia_orden e IX_SYSUsuariosRoles_idrol_idusuario_acceso
     */
    private function getModulosPorNivelYUsuario(
        int $idusuario,
        int $nivel,
        ?string $dependencia = null,
        ?string $cacheKey = null
    ): Collection {
        $cacheKey = $cacheKey ?? "{$this->getCachePrefix()}_nivel{$nivel}_user_{$idusuario}" . ($dependencia ? "_dep{$dependencia}" : "");

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function() use ($idusuario, $nivel, $dependencia) {
            // Optimización: Filtrar primero por índices de SYSRoles (Nivel, Dependencia) antes del JOIN
            // Esto permite que SQL Server use el índice IX_SYSRoles_Nivel_Dependencia_orden eficientemente
            $query = SYSRoles::query()
                ->where('Nivel', $nivel);

            // Filtros específicos por nivel (optimizados para usar índice compuesto)
            if ($nivel == 1) {
                // Módulos principales: sin dependencia
                $query->whereNull('Dependencia');
            } elseif ($dependencia) {
                // Submódulos: buscar por dependencia (la DB ya tiene la estructura correcta)
                $query->where('Dependencia', $dependencia);
            }

            // JOIN optimizado: SQL Server puede usar IX_SYSUsuariosRoles_idrol_idusuario_acceso
            // El índice tiene idrol primero, perfecto para el JOIN
            $query->join('SYSUsuariosRoles', function($join) use ($idusuario) {
                $join->on('SYSRoles.idrol', '=', 'SYSUsuariosRoles.idrol')
                     ->where('SYSUsuariosRoles.idusuario', '=', $idusuario)
                     ->where('SYSUsuariosRoles.acceso', '=', true);
            });

            // Select específico: solo columnas necesarias para reducir transferencia de datos
            // Las columnas en INCLUDE del índice están disponibles sin lookup adicional
            return $query
                ->select(
                    'SYSRoles.idrol',
                    'SYSRoles.orden',
                    'SYSRoles.modulo',
                    'SYSRoles.imagen',
                    'SYSRoles.Ruta',
                    'SYSRoles.Nivel',
                    'SYSRoles.Dependencia',
                    'SYSUsuariosRoles.acceso as usuario_acceso',
                    'SYSUsuariosRoles.crear as usuario_crear',
                    'SYSUsuariosRoles.modificar as usuario_modificar',
                    'SYSUsuariosRoles.eliminar as usuario_eliminar',
                    'SYSUsuariosRoles.registrar as usuario_registrar'
                )
                ->orderBy('SYSRoles.orden') // Aprovecha índice compuesto con orden incluido
                ->get()
                ->map(function($modulo) {
                    return [
                        'nombre' => $modulo->modulo,
                        'imagen' => $modulo->imagen ?? 'default.png',
                        'ruta' => $this->normalizarRuta($modulo->Ruta, $modulo),
                        'ruta_tipo' => 'url',
                        'orden' => $modulo->orden,
                        'nivel' => $modulo->Nivel,
                        'dependencia' => $modulo->Dependencia,
                        'acceso' => $modulo->usuario_acceso ?? 0,
                        'crear' => $modulo->usuario_crear ?? 0,
                        'modificar' => $modulo->usuario_modificar ?? 0,
                        'eliminar' => $modulo->usuario_eliminar ?? 0,
                        'registrar' => $modulo->usuario_registrar ?? 0,
                    ];
                })
                ->values()
                ->toArray();
        });

        return collect($data);
    }

    /**
     * Obtener módulos principales para un usuario con caché
     */
    public function getModulosPrincipalesPorUsuario(int $idusuario): Collection
    {
        $cacheKey = "{$this->getCachePrefix()}_principales_user_{$idusuario}";
        $result = $this->getModulosPorNivelYUsuario($idusuario, 1, null, $cacheKey);

        // Ordenar: Configuración primero
        return $result->sortBy(function($modulo) {
            return $modulo['nombre'] === 'Configuración' ? '0' : $modulo['orden'];
        })->values();
    }

    /**
     * Obtener submódulos de un módulo principal para un usuario
     */
    public function getSubmodulosPorModuloPrincipal(string $moduloPrincipal, int $idusuario, ?SYSRoles $moduloPadre = null): Collection
    {
        $moduloPadreResuelto = $moduloPadre ?: $this->buscarModuloPrincipal($moduloPrincipal);

        if (!$moduloPadreResuelto) {
            return collect([]);
        }

        $ordenPadre = (string) $moduloPadreResuelto->orden;
        $cacheKey = "{$this->getCachePrefix()}_submodulos_{$moduloPrincipal}_user_{$idusuario}";

        return $this->getModulosPorNivelYUsuario($idusuario, 2, $ordenPadre, $cacheKey);
    }

    /**
     * Obtener submódulos de nivel 3
     */
    public function getSubmodulosNivel3(string $ordenPadre, int $idusuario): Collection
    {
        return $this->getModulosPorNivelYUsuario($idusuario, 3, $ordenPadre);
    }

    /**
     * Obtener todos los módulos ordenados
     * Optimizado para usar índice IX_SYSRoles_orden
     */
    public function getAllModulos(): Collection
    {
        return SYSRoles::select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia', 'acceso', 'crear', 'modificar', 'eliminar', 'reigstrar')
            ->orderBy('orden') // Usa índice IX_SYSRoles_orden
            ->get();
    }

    /**
     * Buscar módulo principal por nombre, slug, ruta u orden
     * Busca directamente en la base de datos sin mapeos hardcodeados
     * Optimizado para aprovechar índices: IX_SYSRoles_Nivel_Dependencia (con Ruta en INCLUDE)
     */
    public function buscarModuloPrincipal(string $moduloPrincipal): ?SYSRoles
    {
        // Normalizar el parámetro para evitar problemas de caché con variaciones
        $moduloNormalizado = strtolower(trim($moduloPrincipal));
        $cacheKey = "{$this->getCachePrefix()}_modulo_principal_{$moduloNormalizado}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($moduloPrincipal) {
            // Buscar módulo principal (Nivel 1, sin Dependencia)
            // NO filtramos por acceso aquí porque puede que el módulo exista pero el usuario
            // no tenga acceso (se eliminaron módulos). Eso se valida después en showSubModulos.
            // Optimización: Usa índice IX_SYSRoles_Nivel_Dependencia eficientemente
            $baseQuery = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia');

            // 1. Buscar por nombre exacto (puede usar índice si existe en modulo)
            // Priorizar búsquedas exactas que pueden usar índices
            $modulo = (clone $baseQuery)
                ->where('modulo', $moduloPrincipal)
                ->select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia')
                ->first();

            // 2. Buscar por ruta exacta (si el parámetro ya es una ruta como /planeacion)
            // Ruta está en INCLUDE del índice, acceso rápido sin lookup adicional
            if (!$modulo && str_starts_with($moduloPrincipal, '/')) {
                $modulo = (clone $baseQuery)
                    ->where('Ruta', $moduloPrincipal)
                    ->select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia')
                    ->first();
            }

            // 3. Si no encuentra, buscar por nombre que contenga el slug (insensible a mayusculas)
            // NOTA: LIKE '%texto%' no puede usar índices eficientemente, pero es necesario como fallback
            if (!$modulo) {
                $modulo = (clone $baseQuery)
                    ->whereRaw('LOWER(modulo) LIKE ?', ['%' . strtolower($moduloPrincipal) . '%'])
                    ->select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia')
                    ->first();
            }

            // 4. Último intento: buscar por ruta que contenga el slug (sin acentos)
            // NOTA: LIKE '%texto%' no puede usar índices eficientemente, pero es necesario como fallback
            if (!$modulo) {
                $slug = strtolower(str_replace(['_', ' '], '-', $moduloPrincipal));
                $modulo = (clone $baseQuery)
                    ->whereRaw('LOWER(Ruta) LIKE ?', ["%{$slug}%"])
                    ->select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia')
                    ->first();
            }

            return $modulo;
        });
    }

    /**
     * Limpiar caché de módulos para un usuario
     */
    public function limpiarCacheUsuario(int $idusuario): void
    {
        $prefix = $this->getCachePrefix();

        Cache::forget("{$prefix}_principales_user_{$idusuario}");

        $modulos = ['planeacion', 'tejido', 'urdido', 'engomado', 'atadores', 'tejedores', 'mantenimiento', 'programa-urd-eng', 'programaurdeng', 'configuracion'];
        foreach ($modulos as $modulo) {
            Cache::forget("{$prefix}_submodulos_{$modulo}_user_{$idusuario}");
        }
    }

    /**
     * Generar ruta de fallback si no existe en la base de datos
     * Solo para casos donde falte el campo Ruta (compatibilidad)
     */
    private function generarRutaFallback(SYSRoles $modulo): string
    {
        // Si es nivel 1, usar /submodulos/{orden}
        if ($modulo->Nivel == 1) {
            // Usar el `orden` (numérico) evita problemas de resolución por acentos o caracteres especiales
            // en el nombre del módulo (ej. "Planeación", "Programa Urd / Eng").
            return '/submodulos/' . $modulo->orden;
        }

        // Para nivel 2 y 3, intentar construir desde el padre
        // Optimización: Usa índice IX_SYSRoles_orden para búsqueda rápida
        if ($modulo->Dependencia) {
            $padre = SYSRoles::where('orden', $modulo->Dependencia)
                ->select('orden', 'Ruta', 'Nivel')
                ->first();
            if ($padre) {
                // Si el padre tiene ruta, construir desde ahí
                if ($padre->Ruta) {
                    $slug = strtolower(str_replace(' ', '-', $modulo->modulo));
                    $slug = preg_replace('/[^a-z0-9-]/', '', $slug); // Limpiar caracteres especiales
                    return $padre->Ruta . '/' . $slug;
                }
                // Si el padre no tiene ruta pero tiene orden, construir desde el orden del padre
                if ($padre->Nivel == 1) {
                    $slug = strtolower(str_replace(' ', '-', $modulo->modulo));
                    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
                    return '/submodulos/' . $padre->orden . '/' . $slug;
                }
            }
        }

        // Fallback genérico (último recurso)
        $slug = strtolower(str_replace(' ', '-', $modulo->modulo));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        return '/modulo-' . $slug;
    }

    /**
     * Normalizar la ruta proveniente de DB para evitar links "muertos".
     * - Asegura que las rutas internas empiecen con "/"
     * - Si viene vacía, usa fallback estable
     */
    private function normalizarRuta(?string $rutaDb, SYSRoles $modulo): string
    {
        $ruta = trim((string) ($rutaDb ?? ''));

        if ($ruta === '') {
            return $this->generarRutaFallback($modulo);
        }

        // Si es URL absoluta, respetarla
        if (preg_match('/^https?:\/\//i', $ruta)) {
            return $ruta;
        }

        // Normalizar slashes y asegurar slash inicial
        $ruta = str_replace('\\', '/', $ruta);
        if ($ruta[0] !== '/') {
            $ruta = '/' . $ruta;
        }

        return $ruta;
    }

    /**
     * Obtener prefijo de caché (incluye APP_ENV para que local y production no compartan caché).
     * Si en producción no ves el menú/BPM: ejecuta php artisan config:clear y php artisan cache:clear.
     */
    private function getCachePrefix(): string
    {
        $env = app()->environment();
        return self::CACHE_PREFIX . '_' . $env;
    }
}
