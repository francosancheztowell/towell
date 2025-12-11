<?php

namespace App\Services;

use App\Models\SYSRoles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;


class ModuloService
{
    private const CACHE_TTL = 3600; // 1 hora
    private const CACHE_PREFIX = 'modulos';

    /**
     * Obtener módulos principales para un usuario con caché
     */
    public function getModulosPrincipalesPorUsuario(int $idusuario): Collection
    {
        $cacheKey = "{$this->getCachePrefix()}_principales_user_{$idusuario}";

        // Cachear como array y convertir a Collection al recuperar
        $data = Cache::remember($cacheKey, self::CACHE_TTL, function() use ($idusuario) {
            // Usar join para optimizar la consulta
            return SYSRoles::modulosPrincipales()
                ->join('SYSUsuariosRoles', 'SYSRoles.idrol', '=', 'SYSUsuariosRoles.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->select(
                    'SYSRoles.*',
                    'SYSUsuariosRoles.acceso as usuario_acceso',
                    'SYSUsuariosRoles.crear as usuario_crear',
                    'SYSUsuariosRoles.modificar as usuario_modificar',
                    'SYSUsuariosRoles.eliminar as usuario_eliminar',
                    'SYSUsuariosRoles.registrar as usuario_registrar'
                )
                ->orderBy('SYSRoles.orden')
                ->get()
                ->map(function($modulo) {
                    return [
                        'nombre' => $modulo->modulo,
                        'imagen' => $modulo->imagen ?? 'default.png',
                        'ruta' => $this->generarRutaModuloPrincipal($modulo->modulo, $modulo->orden),
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
                ->sortBy(function($modulo) {
                    // Si es Configuración, ponerlo primero (orden 0), sino usar su orden normal
                    return $modulo['nombre'] === 'Configuración' ? '0' : $modulo['orden'];
                })
                ->values()
                ->toArray(); // Convertir a array para el caché
        });

        // Siempre devolver como Collection
        return collect($data);
    }

    /**
     * Obtener submódulos de un módulo principal para un usuario
     */
    public function getSubmodulosPorModuloPrincipal(string $moduloPrincipal, int $idusuario, ?SYSRoles $moduloPadre = null): Collection
    {
        $cacheKey = "{$this->getCachePrefix()}_submodulos_{$moduloPrincipal}_user_{$idusuario}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function() use ($moduloPrincipal, $idusuario, $moduloPadre) {
            $moduloPadreResuelto = $moduloPadre ?: $this->buscarModuloPrincipal($moduloPrincipal);

            if (!$moduloPadreResuelto) {
                return [];
        }

        // Usar join para optimizar la consulta
        // Convertir orden a string para asegurar comparación correcta con Dependencia
            $ordenPadre = (string) $moduloPadreResuelto->orden;

            return SYSRoles::where('Dependencia', $ordenPadre)
            ->where('Nivel', 2)
            ->join('SYSUsuariosRoles', 'SYSRoles.idrol', '=', 'SYSUsuariosRoles.idrol')
            ->where('SYSUsuariosRoles.idusuario', $idusuario)
            ->where('SYSUsuariosRoles.acceso', true)
            ->select(
                'SYSRoles.*',
                'SYSUsuariosRoles.acceso as usuario_acceso',
                'SYSUsuariosRoles.crear as usuario_crear',
                'SYSUsuariosRoles.modificar as usuario_modificar',
                'SYSUsuariosRoles.eliminar as usuario_eliminar',
                'SYSUsuariosRoles.registrar as usuario_registrar'
            )
            ->orderBy('SYSRoles.orden')
                ->get()
                ->map(function($modulo) {
                return [
                    'nombre' => $modulo->modulo,
                    'imagen' => $modulo->imagen ?? 'default.png',
                    'ruta' => $this->generarRutaSubModulo($modulo->modulo, $modulo->orden, $modulo->Dependencia),
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

        // Siempre devolver como Collection
        return collect($data);
    }

    /**
     * Obtener submódulos de nivel 3
     */
    public function getSubmodulosNivel3(string $ordenPadre, int $idusuario): Collection
    {
        // Primero, obtener el módulo padre para verificar su dependencia
        $moduloPadre = SYSRoles::where('orden', $ordenPadre)->first();

        // Si el módulo padre tiene dependencia 500 (Atadores), buscar módulos nieto por prefijo de orden
        // Los módulos nieto de Atadores tienen Dependencia = ordenPadre (ej: 503) y orden que empieza con el orden del padre
        if ($moduloPadre && $moduloPadre->Dependencia == '500') {
            // Buscar módulos de nivel 3 que tengan Dependencia = ordenPadre
            // Intentar dos patrones: con guión (503-1) y con dos puntos (503:1)
            $query = SYSRoles::where('Nivel', 3)
                ->where('Dependencia', $ordenPadre)
                ->where(function($q) use ($ordenPadre) {
                    $q->where('orden', 'like', $ordenPadre . '-%')
                      ->orWhere('orden', 'like', $ordenPadre . ':%');
                })
                ->join('SYSUsuariosRoles', 'SYSRoles.idrol', '=', 'SYSUsuariosRoles.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true);
        } else {
            // Para otros módulos, usar la lógica original (buscar por Dependencia = ordenPadre)
            // Convertir ordenPadre a string para asegurar comparación correcta
            $ordenPadreStr = (string) $ordenPadre;
            $query = SYSRoles::where('Dependencia', $ordenPadreStr)
                ->where('Nivel', 3)
                ->join('SYSUsuariosRoles', 'SYSRoles.idrol', '=', 'SYSUsuariosRoles.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true);
        }

        return $query
            ->select(
                'SYSRoles.*',
                'SYSUsuariosRoles.acceso as usuario_acceso',
                'SYSUsuariosRoles.crear as usuario_crear',
                'SYSUsuariosRoles.modificar as usuario_modificar',
                'SYSUsuariosRoles.eliminar as usuario_eliminar',
                'SYSUsuariosRoles.registrar as usuario_registrar'
            )
            ->orderBy('SYSRoles.orden')
            ->get()
            ->map(function($modulo) {
                return [
                    'nombre' => $modulo->modulo,
                    'imagen' => $modulo->imagen ?? 'default.png',
                    'ruta' => $this->generarRutaSubModulo($modulo->modulo, $modulo->orden, $modulo->Dependencia),
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
            ->values(); // Asegurar que sea una Collection indexada
    }

    /**
     * Obtener todos los módulos ordenados
     */
    public function getAllModulos(): Collection
    {
        return SYSRoles::orderBy('orden')->get();
    }

    /**
     * Buscar módulo principal por nombre o slug
     */
    public function buscarModuloPrincipal(string $moduloPrincipal): ?SYSRoles
    {
        $cacheKey = "{$this->getCachePrefix()}_modulo_principal_{$moduloPrincipal}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($moduloPrincipal) {
        $slugToNombre = [
            'planeacion' => 'Planeación',
            'tejido' => 'Tejido',
            'urdido' => 'Urdido',
            'engomado' => 'Engomado',
            'atadores' => 'Atadores',
            'tejedores' => 'Tejedores',
            'mantenimiento' => 'Mantenimiento',
            'programa-urd-eng' => 'Programa Urd / Eng',
            'configuracion' => 'Configuración',
        ];

        $buscado = $slugToNombre[$moduloPrincipal] ?? $moduloPrincipal;

        // Buscar primero por nombre, sin filtrar por acceso (para encontrar el módulo padre)
        $modulo = SYSRoles::where('Nivel', 1)
            ->whereNull('Dependencia')
            ->where('modulo', $buscado)
            ->first();

        if (!$modulo) {
            $rangos = [
                'planeacion' => 100,
                'tejido' => 200,
                'urdido' => 300,
                'engomado' => 400,
                'atadores' => 500,
                'tejedores' => 600,
                'programa-urd-eng' => 700,
                'mantenimiento' => 800,
                'configuracion' => 900,
            ];

            if (isset($rangos[$moduloPrincipal])) {
                // Buscar por orden, sin filtrar por acceso
                $modulo = SYSRoles::where('Nivel', 1)
                    ->whereNull('Dependencia')
                    ->where('orden', $rangos[$moduloPrincipal])
                    ->first();
            }
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

        $modulos = ['planeacion', 'tejido', 'urdido', 'engomado', 'atadores', 'tejedores', 'mantenimiento', 'programa-urd-eng', 'configuracion'];
            foreach ($modulos as $modulo) {
            Cache::forget("{$prefix}_submodulos_{$modulo}_user_{$idusuario}");
        }
    }

    /**
     * Generar ruta para módulo principal
     */
    private function generarRutaModuloPrincipal(string $nombreModulo, string $orden): string
    {
        $rutasEspeciales = [
            'Planeación' => '/submodulos/planeacion',
            'Tejido' => '/submodulos/tejido',
            'Urdido' => '/submodulos/urdido',
            'Engomado' => '/submodulos/engomado',
            'Atadores' => '/submodulos/atadores',
            'Tejedores' => '/submodulos/tejedores',
            'Mantenimiento' => '/submodulos/mantenimiento',
            'Programa Urd / Eng' => '/submodulos/programa-urd-eng',
            'Configuración' => '/modulo-configuracion',
        ];

        return $rutasEspeciales[$nombreModulo] ?? '/submodulos/' . strtolower(str_replace(' ', '-', $nombreModulo));
    }

    /**
     * Generar ruta para submódulo
     */
    private function generarRutaSubModulo(string $nombreModulo, string $orden, ?string $dependencia = null): string
    {
        // Caso especial: Configurar puede estar en Tejido o Tejedores
        if (strtolower(trim($nombreModulo)) === 'configurar') {
            if ($dependencia == 600 || (is_string($dependencia) && strpos($dependencia, '600') === 0)) {
                return '/tejedores/configurar';
            }
            return '/tejido/configurar';
        }

        // Caso especial: Configuración puede estar en Urdido o Engomado
        if (strtolower(trim($nombreModulo)) === 'configuracion' || strtolower(trim($nombreModulo)) === 'configuración') {
            // Si viene de Engomado (dependencia 400)
            if ($dependencia == 400 || (is_string($dependencia) && strpos($dependencia, '400') === 0)) {
                return '/submodulos-nivel3/404'; // Configuración de Engomado
            }
            // Si viene de Urdido (dependencia 300)
            if ($dependencia == 300 || (is_string($dependencia) && strpos($dependencia, '300') === 0)) {
                return '/urdido/configuracion';
            }
            // Si viene de Atadores (dependencia 500) - ya se maneja abajo
        }

        // Caso especial: Catalogos y Configuracion de Atadores (dependencia 500)
        // SOLO aplicar si la dependencia es 500 (Atadores), NO para otros módulos
        if ($dependencia == 500 || (is_string($dependencia) && strpos($dependencia, '500') === 0)) {
            if (strtolower(trim($nombreModulo)) === 'catalogos' || strtolower(trim($nombreModulo)) === 'catálogos') {
                return '/submodulos-nivel3/503'; // Redirigir a nivel 3 de Catalogos de Atadores
            }
            if (strtolower(trim($nombreModulo)) === 'configuracion' || strtolower(trim($nombreModulo)) === 'configuración') {
                return '/submodulos-nivel3/502'; // Redirigir a nivel 3 de Configuracion de Atadores
            }
        }

        // Mapeo completo de rutas
        $rutasSubModulos = [
            // Submódulos de Planeación
            'Simulaciones' => '/simulacion',
            'Alineación' => '/planeacion/alineacion',
            'Reportes' => '/planeacion/reportes',
            'Reportes Planeación' => '/planeacion/reportes',
            'Producciones Terminadas' => '/planeacion/producciones-terminadas',
            'Catálogos' => '/planeacion/catalogos',
            'Catalogos' => '/planeacion/catalogos',
            'Catálogos (Cat.)' => '/planeacion/catalogos',
            'Catalogos (Cat.)' => '/planeacion/catalogos',
            'Catálogos de Planeación' => '/planeacion/catalogos',
            'Catalogos de Planeacion' => '/planeacion/catalogos',

            // Submódulos de Catálogos (nivel 3)
            'Telares' => '/planeacion/catalogos/telares',
            'Eficiencias STD' => '/planeacion/catalogos/eficiencia',
            'Velocidad STD' => '/planeacion/catalogos/velocidad',
            'Calendarios' => '/planeacion/catalogos/calendarios',
            'Aplicaciones (Cat.)' => '/planeacion/catalogos/aplicaciones',
            'Modelos' => '/planeacion/catalogos/modelos',
            'Matriz Calibres' => '/planeacion/catalogos/matriz-calibres',
            'Matriz Hilos' => '/planeacion/catalogos/matriz-hilos',
            'Codificación Modelos' => '/planeacion/catalogos/codificacion-modelos',

            // Submódulos de Programa Tejido
            'Programa Tejido' => '/planeacion/programa-tejido',
            'Programa de Tejido' => '/planeacion/programa-tejido',
            'Programa Tejido (Cat.)' => '/planeacion/programa-tejido',
            'Orden de Cambio' => '/tejido/orden-cambio',
            'Marbetes' => '/tejido/marbetes',

            // Nietos de Inv Telas
            'Jacquard' => '/tejido/inventario-telas/jacquard',
            'Itema' => '/tejido/inventario-telas/itema',
            'Karl Mayer' => '/tejido/karl-mayer',

            // Módulos de Tejido
            'Inv Telas' => '/tejido/inventario-telas',
            'Cortes de Eficiencia' => '/modulo-cortes-de-eficiencia/consultar',
            'Marcas Finales- Cortes de Eficiencia' => '/modulo-marcas/consultar',
            'Marcas Finales' => '/modulo-marcas/consultar',
            'Inv Trama' => '/tejido/inventario',
            'Producción Reenconado Cabezuela' => '/tejido/produccion-reenconado',

            // Nietos de Cortes de Eficiencia
            'Nuevos cortes de eficiencia' => '/modulo-cortes-de-eficiencia',
            'Consultar eficiencia' => '/modulo-cortes-de-eficiencia/consultar',

            // Nietos de Marcas Finales
            'Nuevas Marcas Finales' => '/modulo-marcas',
            'Nuevas marcas finales' => '/modulo-marcas',
            'nuevas marcas finales' => '/modulo-marcas',
            'Consultar Marcas Finales' => '/modulo-marcas/consultar',
            'Consultar marcas finales' => '/modulo-marcas/consultar',
            'consultar marcas finales' => '/modulo-marcas/consultar',

            // Nietos de Inv Trama
            'Nuevo requerimiento' => '/tejido/inventario/trama/nuevo-requerimiento',
            'Consultar requerimiento' => '/tejido/inventario/trama/consultar-requerimiento',

            // Submódulos de Configurar
            'Secuencia Inv Telas' => '/tejido/secuencia-inv-telas',
            'Secuencia Corte de Eficiencia' => '/tejido/secuencia-corte-eficiencia',
            'Secuencia Inv Trama' => '/tejido/secuencia-inv-trama',
            'Secuencia Marcas Finales' => '/tejido/secuencia-marcas-finales',

            // Tejedores » Configurar
            'Telares x Operador' => '/tel-telares-operador',
            'Catalogo Desarrolladores' => 'catalogo-desarrolladores',
            'ActividadesBPM' => '/tel-actividades-bpm',
            'Actividades BPM' => '/tel-actividades-bpm',

            // Módulos de Urdido
            'Programa Urdido' => '/urdido/programar-urdido',
            'BPM (Buenas Practicas Manufactura) Urd' => '/urdido/bpm',
            'Reportes Urdido' => '/urdido/reportes',
            'Catalogos Julios' => '/urdido/catalogos-julios',
            'Catalogos de Paros' => '/urdido/catalogos-paros',
            'Producción Urdido' => '/urdido/modulo-produccion-urdido',
            'Produccion Urdido' => '/urdido/modulo-produccion-urdido',
            'Módulo Producción Urdido' => '/urdido/modulo-produccion-urdido',
            // 'Modulo Produccion Urdido' => '/urdido/modulo-produccion-urdido',
            'Configuración' => '/urdido/configuracion',

            // Módulos de Engomado
            'Programa Engomado' => '/engomado/programar-engomado',
            'BPM (Buenas Practicas Manufactura) Eng' => '/engomado/bpm',
            'BPM Engomado' => '/engomado/bpm',
            'Reportes Engomado' => '/engomado/reportes',
            'Producción Engomado' => '/engomado/produccion',
            'Captura de Formula' => '/engomado/captura-formula',

            // Módulos de Atadores
            'Programa Atadores' => '/atadores/programa',
            'Montadod de Julios en Telar'=> '/atadores/montado',

            // Catálogos de Atadores (nivel 3)
            'Actividades' => '/atadores/catalogos/actividades',
            'Comentarios' => '/atadores/catalogos/comentarios',
            'Maquinas' => '/atadores/catalogos/maquinas',
            'Máquinas' => '/atadores/catalogos/maquinas',

            // Módulos de Tejedores
            'BPM Tejedores' => '/tejedores/bpm',
            'Desarrolladores' => '/tejedores/desarrolladores',
            'Mecánicos' => '/tejedores/mecanicos',
            'Notificar Montado de Rollo'=> 'tejedores/notificar-mont-rollos',
            // 'Notificar Montado de Julio' => '/tejedores/notificar-montado-julios',
            // 'Notificar Montado de Julio (Tej.)' => '/tejedores/notificar-montado-julios',

            // Módulos de Programa Urd/Eng
            'Reservar y Programar' => '/programa-urd-eng/reservar-programar',

            // Módulos de Mantenimiento
            'Solicitudes' => '/mantenimientos/reporte-fallos-paros',
            'Reportes Fallos y Paros' => '/mantenimientos/reporte-fallos-paros',

            // Módulos de configuración
            'Usuarios' => '/configuracion/usuarios/select',
            'Parametros' => '/configuracion/parametros',
            'Base Datos Principal' => '/configuracion/base-datos',
            'BD Pro (ERP Productivo)' => '/configuracion/bd-pro-productivo',
            'BD Pro (ERP Pruebas)' => '/configuracion/bd-pro-pruebas',
            'BD Tow (ERP Productivo)' => '/configuracion/bd-tow-productivo',
            'BD Tow (ERP Pruebas)' => '/configuracion/bd-tow-pruebas',
            'Ambiente' => '/configuracion/ambiente',

            // Nietos de Utilería
            'Cargar Catálogos' => '/configuracion/utileria/cargar-catalogos',
            'Cargar Orden de Producción' => '/configuracion/cargar-orden-produccion',
            'Cargar Planeación' => '/configuracion/cargar-planeacion',
            'Modulos' => '/configuracion/utileria/modulos',

            // Catálogos de Urdido (nivel 3)
            'Catalogo Maquinas' => '/urdido/catalogo-maquinas',
            'Catálogo Máquinas' => '/urdido/catalogo-maquinas',
            'Catálogo Maquinas' => '/urdido/catalogo-maquinas',
            'Actividades BPM Urdido'=> '/urdido/configuracion/actividades-bpm',

            // Catálogos de Engomado (nivel 3)
            'Actividades BPM Engomado' => '/engomado/configuracion/actividades-bpm',
            'Actividades Engomado' => '/engomado/configuracion/actividades-bpm',

        ];

        // Buscar coincidencia exacta
        if (isset($rutasSubModulos[$nombreModulo])) {
            return $rutasSubModulos[$nombreModulo];
        }

        // Buscar coincidencia insensible a mayúsculas
        $nombreModuloLower = strtolower(trim($nombreModulo));
        foreach ($rutasSubModulos as $key => $ruta) {
            if (strtolower(trim($key)) === $nombreModuloLower) {
                return $ruta;
            }
        }
        // Búsqueda flexible para "Catalogo Maquinas" o variantes
        $nombreModuloLower = strtolower(trim($nombreModulo));
        if ((strpos($nombreModuloLower, 'catalogo') !== false || strpos($nombreModuloLower, 'catálogo') !== false) &&
            (strpos($nombreModuloLower, 'maquina') !== false || strpos($nombreModuloLower, 'máquina') !== false)) {
            // Si la dependencia es 304 (Configuración de Urdido), redirigir al catálogo de máquinas de urdido
            if ($dependencia == 304 || (is_string($dependencia) && strpos($dependencia, '304') === 0)) {
                return '/urdido/catalogo-maquinas';
            }
        }

        // Verificación especial para catálogos de Planeación (dependencia 100, orden 104)
        // Solo aplicar si NO es de Atadores (dependencia 500) y es de Planeación (dependencia 100)
        if (($dependencia == 100 || (is_string($dependencia) && strpos($dependencia, '100') === 0)) &&
            ($dependencia != 500 && (is_string($dependencia) && strpos($dependencia, '500') === false)) &&
            (strpos(strtolower($nombreModulo), 'catálogo') !== false ||
            strpos(strtolower($nombreModulo), 'catalog') !== false ||
            $orden == '104')) {
            return '/planeacion/catalogos';
        }

        // Verificar si el módulo tiene nietos (nivel 3)
        $tieneNietos = SYSRoles::where('Nivel', 3)
            ->where('Dependencia', $orden)
            ->exists();

        if ($tieneNietos) {
            $rutasDescriptivas = [
                '104' => '/planeacion/catalogos', // Catálogos de Planeación
                '202' => '/modulo-marcas/consultar',
                '203' => '/tejido/inventario',
                '206' => '/tejido/cortes-eficiencia',
                '502' => '/atadores/configuracion', // Configuración de Atadores
                '503' => '/atadores/catalogos', // Catálogos de Atadores
                '909' => '/configuracion/utileria',
            ];

            return $rutasDescriptivas[$orden] ?? '/submodulos-nivel3/' . $orden;
        }

        // Verificar si el orden contiene separador de nivel 3
        $posSeparador = (strpos($orden, '-') !== false) ? strpos($orden, '-') : strpos($orden, '_');
        if ($posSeparador !== false) {
            $moduloPadre = substr($orden, 0, $posSeparador);
            $rutasDescriptivas = [
                '202' => '/modulo-marcas/consultar',
                '203' => '/tejido/inventario',
                '206' => '/tejido/cortes-eficiencia',
                '909' => '/configuracion/utileria',
            ];
            return $rutasDescriptivas[$moduloPadre] ?? '/submodulos-nivel3/' . $moduloPadre;
        }

        // Ruta genérica por defecto
        return '/modulo-' . strtolower(str_replace(' ', '-', $nombreModulo));
    }

    /**
     * Obtener prefijo de caché
     */
    private function getCachePrefix(): string
    {
        return self::CACHE_PREFIX;
    }
}

