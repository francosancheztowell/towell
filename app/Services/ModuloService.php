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
                ->values()
                ->toArray(); // Convertir a array para el caché
        });

        // Siempre devolver como Collection
        return collect($data);
    }

    /**
     * Obtener submódulos de un módulo principal para un usuario
     */
    public function getSubmodulosPorModuloPrincipal(string $moduloPrincipal, int $idusuario): Collection
    {
        $cacheKey = "{$this->getCachePrefix()}_submodulos_{$moduloPrincipal}_user_{$idusuario}";

        // Cachear como array y convertir a Collection al recuperar
        $data = Cache::remember($cacheKey, self::CACHE_TTL, function() use ($moduloPrincipal, $idusuario) {
            $moduloPadre = $this->buscarModuloPrincipal($moduloPrincipal);

            if (!$moduloPadre) {
                return [];
            }

            // Usar join para optimizar la consulta
            return SYSRoles::submodulosDe($moduloPadre->orden, 2)
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
                ->toArray(); // Convertir a array para el caché
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
        // Los módulos nieto de Atadores tienen Dependencia = 500 pero orden que empieza con el orden del padre (ej: 503-1, 503-2)
        if ($moduloPadre && $moduloPadre->Dependencia == '500') {
            // Buscar módulos de nivel 3 que tengan Dependencia = 500 y orden que empiece con el orden del padre
            $query = SYSRoles::where('Nivel', 3)
                ->where('Dependencia', '500')
                ->where('orden', 'like', $ordenPadre . '-%')
                ->join('SYSUsuariosRoles', 'SYSRoles.idrol', '=', 'SYSUsuariosRoles.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true);
        } else {
            // Para otros módulos, usar la lógica original (buscar por Dependencia = ordenPadre)
            $query = SYSRoles::submodulosDe($ordenPadre, 3)
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

        $modulo = SYSRoles::modulosPrincipales()
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
                $modulo = SYSRoles::modulosPrincipales()
                    ->where('orden', $rangos[$moduloPrincipal])
                    ->first();
            }
        }

        return $modulo;
    }

    /**
     * Limpiar caché de módulos para un usuario
     */
    public function limpiarCacheUsuario(int $idusuario): void
    {
        $patterns = [
            "{$this->getCachePrefix()}_principales_user_{$idusuario}",
            "{$this->getCachePrefix()}_submodulos_*_user_{$idusuario}",
        ];

        foreach ($patterns as $pattern) {
            // Limpiar caché específico
            Cache::forget("{$this->getCachePrefix()}_principales_user_{$idusuario}");

            // Limpiar submódulos de todos los módulos
            $modulos = ['planeacion', 'tejido', 'urdido', 'engomado', 'atadores', 'tejedores', 'mantenimiento', 'programa-urd-eng'];
            foreach ($modulos as $modulo) {
                Cache::forget("{$this->getCachePrefix()}_submodulos_{$modulo}_user_{$idusuario}");
            }
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

        // Caso especial: Catalogos y Configuracion de Atadores (dependencia 500)
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
            'Simulaciones' => '/planeacion/simulaciones',
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
            'Cortes de Eficiencia' => '/tejido/cortes-eficiencia',
            'Marcas Finales- Cortes de Eficiencia' => '/tejido/marcas-finales',
            'Marcas Finales' => '/tejido/marcas-finales',
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
            'Telares por Operador' => '/tel-telares-operador',
            'Telares x operador' => '/tel-telares-operador',
            'ActividadesBPM' => '/tel-actividades-bpm',
            'Actividades BPM' => '/tel-actividades-bpm',

            // Módulos de Urdido
            'Programa Urdido' => '/urdido/programar-requerimientos',
            'BPM (Buenas Practicas Manufactura) Urd' => '/urdido/bpm',
            'Reportes Urdido' => '/urdido/reportes',
            'Catalogos Julios' => '/urdido/catalogos-julios',
            'Catalogos de Paros' => '/urdido/catalogos-paros',

            // Módulos de Engomado
            'Programa Engomado' => '/engomado/programar-requerimientos',
            'BPM (Buenas Practicas Manufactura) Eng' => '/engomado/bpm',
            'Reportes Engomado' => '/engomado/reportes',
            'Producción Engomado' => '/engomado/produccion',

            // Módulos de Atadores
            'Programa Atadores' => '/atadores/programa',

            // Módulos de Tejedores
            'BPM Tejedores' => '/tejedores/bpm',
            'Desarrolladores' => '/tejedores/desarrolladores',
            'Mecánicos' => '/tejedores/mecanicos',

            // Módulos de Programa Urd/Eng
            'Reservar y Programar' => '/programa-urd-eng/reservar-programar',

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
                '202' => '/tejido/marcas-finales',
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
                '202' => '/tejido/marcas-finales',
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

