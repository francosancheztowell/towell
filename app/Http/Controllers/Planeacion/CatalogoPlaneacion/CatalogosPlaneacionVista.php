<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion;

use App\Models\Urdido\URDCatalogoMaquina;

/**
 * Configuración de los catálogos de Planeación que comparten el CRUD en TS (19-06b):
 * formulario (campos), filtros y textos. La vista la pinta con catalagos/comun/modales y la pasa
 * a resources/js/modulos/catalogos-planeacion/** en data-catalogo. Las tablas siguen en cada
 * vista (mismo diseño); rutas, validaciones y JSON viven en cada controller.
 *
 * @phpstan-type Campo array{nombre: string, etiqueta: string, tipo: string, requerido?: bool, placeholder?: string, maxlength?: int, min?: int|float, max?: int|float, step?: string, opciones?: list<string>, depende?: string, opcionesPor?: array<string, list<int|string>>, sufijo?: string, porDefecto?: string, ancho?: string}
 * @phpstan-type Filtro array{nombre: string, campo: string, etiqueta: string, modo: string, control?: string, opciones?: list<string>, depende?: string, opcionesPor?: array<string, list<int|string>>, escala?: int|float, porDefecto?: string, placeholder?: string}
 * @phpstan-type Catalogo array{clave: string, ruta: string, endpoint: string, llave: string, campos: list<Campo>, filtros: list<Filtro>, textos: array<string, string>}
 */
final class CatalogosPlaneacionVista
{
    /** Telares por salón que ofrecían los selects de Eficiencia y Velocidad (listas distintas desde antes). */
    private const TELARES_EFICIENCIA = [
        'JACQUARD' => [201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 213, 214, 215],
        'SMITH' => [299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317, 318, 319, 320],
    ];

    private const TELARES_VELOCIDAD = [
        'JACQUARD' => [201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217, 218, 219, 220],
        'SMITH' => [299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317, 318, 319, 320],
    ];

    /** Mismos tipos que MatrizCalibreClave::TIPOS (BARRA1..4 = Karl Mayer, una clave por barra). */
    public const TIPOS_MATRIZ_CALIBRES = ['RIZO', 'PIE', 'TRAMA', 'BARRA1', 'BARRA2', 'BARRA3', 'BARRA4'];

    /** @return Catalogo */
    public static function telares(): array
    {
        return [
            'clave' => 'telares',
            'ruta' => 'telares',
            'endpoint' => route('planeacion.telares.store', absolute: false),
            'llave' => 'uid',
            'campos' => [
                ['nombre' => 'SalonTejidoId', 'etiqueta' => 'Salón', 'tipo' => 'select', 'requerido' => true, 'opciones' => URDCatalogoMaquina::DEPARTAMENTOS_TELARES],
                ['nombre' => 'NoTelarId', 'etiqueta' => 'Telar', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 10, 'placeholder' => '200, 300'],
            ],
            'filtros' => [
                self::filtroTexto('salon', 'SalonTejidoId', 'Salón', 'Jacquard / Itema / Smith / Karl Mayer'),
                self::filtroTexto('telar', 'NoTelarId', 'Telar', '200'),
            ],
            'textos' => self::textos('Telar', 'el telar'),
        ];
    }

    /** @return Catalogo */
    public static function aplicaciones(): array
    {
        return [
            'clave' => 'aplicaciones',
            'ruta' => 'aplicaciones',
            'endpoint' => route('planeacion.aplicaciones.store', absolute: false),
            'llave' => 'Id',
            'campos' => [
                ['nombre' => 'AplicacionId', 'etiqueta' => 'Clave', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 50, 'placeholder' => 'APP001'],
                ['nombre' => 'Nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 100, 'placeholder' => 'TR / RZ'],
                ['nombre' => 'Factor', 'etiqueta' => 'Factor', 'tipo' => 'number', 'step' => '0.0001', 'placeholder' => '0'],
            ],
            'filtros' => [
                self::filtroTexto('clave', 'AplicacionId', 'Clave', 'APP001'),
                self::filtroTexto('nombre', 'Nombre', 'Nombre', 'TR / DC'),
                self::filtroTexto('factor', 'Factor', 'Factor', '0 / 1 / 2'),
            ],
            'textos' => self::textos('Aplicación', 'la aplicación', 'Nueva'),
        ];
    }

    /** @return Catalogo */
    public static function matrizHilos(): array
    {
        $numero = fn (string $n) => ['nombre' => $n, 'etiqueta' => $n, 'tipo' => 'number', 'step' => '0.0001', 'placeholder' => '0.0000'];

        return [
            'clave' => 'matriz-hilos',
            'ruta' => 'matriz-hilos',
            'endpoint' => route('planeacion.matriz-hilos.store', absolute: false),
            'llave' => 'Id',
            'campos' => [
                ['nombre' => 'Hilo', 'etiqueta' => 'Hilo', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 30, 'placeholder' => 'Ej: H001'],
                $numero('Calibre'),
                $numero('Calibre2'),
                ['nombre' => 'CalibreAX', 'etiqueta' => 'CalibreAX', 'tipo' => 'text', 'maxlength' => 20, 'placeholder' => 'Calibre AX'],
                ['nombre' => 'Fibra', 'etiqueta' => 'Fibra', 'tipo' => 'text', 'maxlength' => 30, 'placeholder' => 'Tipo de fibra'],
                ['nombre' => 'CodColor', 'etiqueta' => 'CodColor', 'tipo' => 'text', 'maxlength' => 10, 'placeholder' => 'Código color'],
                ['nombre' => 'NombreColor', 'etiqueta' => 'NombreColor', 'tipo' => 'text', 'maxlength' => 60, 'placeholder' => 'Nombre del color', 'ancho' => 'completo'],
                $numero('N1'),
                $numero('N2'),
            ],
            'filtros' => [],
            'textos' => self::textos('Matriz de Hilos', 'el hilo', 'Nueva'),
        ];
    }

    /** @return Catalogo */
    public static function matrizCalibres(): array
    {
        $texto = fn (string $n, string $etiqueta) => ['nombre' => $n, 'etiqueta' => $etiqueta, 'tipo' => 'text', 'maxlength' => 60, 'placeholder' => $n];

        return [
            'clave' => 'matriz-calibres',
            'ruta' => 'matriz-calibres',
            'endpoint' => route('planeacion.catalogos.matrizcalibres.store', absolute: false),
            'llave' => 'Id',
            'campos' => [
                ['nombre' => 'Tipo', 'etiqueta' => 'Tipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => self::TIPOS_MATRIZ_CALIBRES],
                ['nombre' => 'Calibre', 'etiqueta' => 'Calibre (opcional en PIE)', 'tipo' => 'number', 'step' => '0.0001', 'placeholder' => '0.0000'],
                $texto('FibraId', 'Fibra (opcional en PIE)'),
                $texto('Cuenta', 'Cuenta'),
                $texto('ItemId', 'ItemId') + ['requerido' => true],
                $texto('ConfigId', 'Config') + ['requerido' => true],
                $texto('InventSizeId', 'Tamaño') + ['requerido' => true],
                $texto('InventColorId', 'Color') + ['requerido' => true],
            ],
            'filtros' => [],
            'textos' => self::textos('calibre', 'el calibre'),
        ];
    }

    /** @return Catalogo */
    public static function pesosRollos(): array
    {
        return [
            'clave' => 'pesos-rollos',
            'ruta' => 'pesos-rollos',
            'endpoint' => route('planeacion.catalogos.pesos-rollos.store', absolute: false),
            'llave' => 'Id',
            'campos' => [
                ['nombre' => 'ItemId', 'etiqueta' => 'Cod Artículo', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 20, 'placeholder' => 'Ej: ITEM001', 'ancho' => 'completo'],
                ['nombre' => 'ItemName', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 60, 'placeholder' => 'Ej: Tela Algodón', 'ancho' => 'completo'],
                ['nombre' => 'InventSizeId', 'etiqueta' => 'Tamaño', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 10, 'placeholder' => 'Ej: S, M, L', 'ancho' => 'completo'],
                ['nombre' => 'PesoRollo', 'etiqueta' => 'Peso Rollo (kg)', 'tipo' => 'number', 'requerido' => true, 'min' => 0, 'step' => '0.01', 'placeholder' => '0.00', 'ancho' => 'completo'],
            ],
            'filtros' => [
                self::filtroTexto('itemId', 'ItemId', 'Cod Artículo', 'Buscar por Cod Artículo'),
                self::filtroTexto('itemName', 'ItemName', 'Nombre', 'Buscar por nombre'),
                self::filtroTexto('inventSizeId', 'InventSizeId', 'Tamaño', 'Buscar por tamaño'),
                ['nombre' => 'pesoMin', 'campo' => 'PesoRollo', 'etiqueta' => 'Peso Mínimo (kg)', 'modo' => 'min', 'control' => 'number'],
                ['nombre' => 'pesoMax', 'campo' => 'PesoRollo', 'etiqueta' => 'Peso Máximo (kg)', 'modo' => 'max', 'control' => 'number'],
            ],
            'textos' => self::textos('Peso por Rollo', 'el peso por rollo'),
        ];
    }

    /**
     * Eficiencia y Velocidad STD: el mismo catálogo con otra columna de valor (dedupe 19-06b).
     *
     * @param  'eficiencia'|'velocidad'  $variante
     * @return Catalogo
     */
    public static function estandar(string $variante): array
    {
        $esEficiencia = $variante === 'eficiencia';
        $telares = $esEficiencia ? self::TELARES_EFICIENCIA : self::TELARES_VELOCIDAD;
        $salones = array_keys($telares);

        return [
            'clave' => $variante,
            'ruta' => $variante,
            'endpoint' => route("planeacion.$variante.store", absolute: false),
            'llave' => 'Id',
            'campos' => [
                ['nombre' => 'SalonTejidoId', 'etiqueta' => 'Salón', 'tipo' => 'select', 'requerido' => true, 'opciones' => $salones],
                ['nombre' => 'NoTelarId', 'etiqueta' => 'Telar', 'tipo' => 'select', 'requerido' => true, 'opciones' => [], 'depende' => 'SalonTejidoId', 'opcionesPor' => $telares],
                self::campoFibra($esEficiencia),
                ['nombre' => 'Densidad', 'etiqueta' => 'Densidad', 'tipo' => 'select', 'opciones' => ['Normal', 'Alta'], 'porDefecto' => 'Normal'],
                self::campoValor($esEficiencia),
            ],
            'filtros' => [
                ['nombre' => 'salon', 'campo' => 'SalonTejidoId', 'etiqueta' => 'Salón', 'modo' => 'contiene', 'control' => 'select', 'opciones' => $salones],
                ['nombre' => 'telar', 'campo' => 'NoTelarId', 'etiqueta' => 'Telar', 'modo' => 'contiene', 'control' => 'select', 'opciones' => [], 'depende' => 'salon', 'opcionesPor' => $telares],
                self::filtroTexto('fibra', 'FibraId', $esEficiencia ? 'Tipo de Hilo' : 'Fibra', 'H, PAP, FIL370'),
                ['nombre' => 'densidad', 'campo' => 'Densidad', 'etiqueta' => 'Densidad', 'modo' => 'igual', 'control' => 'select', 'opciones' => ['Normal', 'Alta'], 'porDefecto' => 'Normal'],
                ...self::filtrosValor($esEficiencia),
            ],
            'textos' => self::textos($esEficiencia ? 'Eficiencia' : 'Velocidad', $esEficiencia ? 'la eficiencia' : 'la velocidad', 'Nueva'),
        ];
    }

    /** @return Campo */
    private static function campoFibra(bool $esEficiencia): array
    {
        return $esEficiencia
            ? ['nombre' => 'FibraId', 'etiqueta' => 'Hilo', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 120, 'placeholder' => 'H']
            : ['nombre' => 'FibraId', 'etiqueta' => 'Fibra', 'tipo' => 'text', 'requerido' => true, 'maxlength' => 60, 'placeholder' => 'H, PAP'];
    }

    /** @return Campo */
    private static function campoValor(bool $esEficiencia): array
    {
        return $esEficiencia
            ? ['nombre' => 'Eficiencia', 'etiqueta' => 'Eficiencia', 'tipo' => 'range', 'requerido' => true, 'min' => 0, 'max' => 100, 'step' => '1', 'sufijo' => '%', 'porDefecto' => '78', 'ancho' => 'completo']
            : ['nombre' => 'Velocidad', 'etiqueta' => 'Velocidad (RPM)', 'tipo' => 'number', 'requerido' => true, 'min' => 0, 'step' => '1', 'placeholder' => '850', 'ancho' => 'completo'];
    }

    /** @return list<Filtro> */
    private static function filtrosValor(bool $esEficiencia): array
    {
        return $esEficiencia
            ? [
                ['nombre' => 'eficiencia_min', 'campo' => 'Eficiencia', 'etiqueta' => 'Eficiencia Mínima (%)', 'modo' => 'min', 'control' => 'number', 'escala' => 100],
                ['nombre' => 'eficiencia_max', 'campo' => 'Eficiencia', 'etiqueta' => 'Eficiencia Máxima (%)', 'modo' => 'max', 'control' => 'number', 'escala' => 100],
            ]
            : [
                ['nombre' => 'velocidad_min', 'campo' => 'Velocidad', 'etiqueta' => 'Velocidad Mínima (RPM)', 'modo' => 'min', 'control' => 'number'],
                ['nombre' => 'velocidad_max', 'campo' => 'Velocidad', 'etiqueta' => 'Velocidad Máxima (RPM)', 'modo' => 'max', 'control' => 'number'],
            ];
    }

    /** @return Filtro */
    private static function filtroTexto(string $nombre, string $campo, string $etiqueta, string $placeholder): array
    {
        return ['nombre' => $nombre, 'campo' => $campo, 'etiqueta' => $etiqueta, 'modo' => 'contiene', 'control' => 'text', 'placeholder' => $placeholder];
    }

    /** @return array<string, string> */
    private static function textos(string $singular, string $conArticulo, string $nuevo = 'Nuevo'): array
    {
        return [
            'nuevo' => "Crear $nuevo $singular",
            'editar' => "Editar $singular",
            'confirmar' => '¿Eliminar el registro seleccionado?',
            'filtrar' => 'Filtrar registros',
            'sinResultados' => 'No se encontraron resultados',
            'errorGuardar' => "No se pudo guardar $conArticulo",
            'errorEliminar' => "No se pudo eliminar $conArticulo",
            'errorCargar' => 'No se pudo cargar el registro',
        ];
    }
}
