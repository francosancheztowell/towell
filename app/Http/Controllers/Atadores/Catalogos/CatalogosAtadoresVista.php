<?php

declare(strict_types=1);

namespace App\Http\Controllers\Atadores\Catalogos;

/**
 * Configuración de la vista única de los catálogos de atadores (piloto DS-12).
 *
 * Actividades, Comentarios y Máquinas eran tres vistas ~80 % iguales; ahora comparten
 * resources/views/modulos/catalogos-atadores/index.blade.php y
 * resources/js/modulos/catalogos-atadores/index.ts. Lo que cambia entre ellas vive aquí.
 * Rutas, validaciones y respuestas JSON siguen en cada controller, sin cambios.
 *
 * @phpstan-type Columna array{campo: string, titulo: string, sufijo?: string, clase?: string}
 * @phpstan-type Campo array{nombre: string, etiqueta: string, tipo: string, requerido?: bool, placeholder?: string, min?: int, max?: int, step?: string, filas?: int}
 * @phpstan-type Catalogo array{clave: string, titulo: string, modulo: string, endpoint: string, llave: string, columnas: list<Columna>, campos: list<Campo>, textos: array<string, string>}
 */
final class CatalogosAtadoresVista
{
    /** @return Catalogo */
    public static function actividades(): array
    {
        return [
            'clave' => 'actividades',
            'titulo' => 'Catálogo de Actividades',
            'modulo' => 'Actividades',
            'endpoint' => route('atadores.catalogos.actividades', absolute: false),
            'llave' => 'ActividadId',
            'columnas' => [
                ['campo' => 'ActividadId', 'titulo' => 'Actividad ID', 'clase' => 'whitespace-nowrap font-medium'],
                ['campo' => 'Porcentaje', 'titulo' => 'Porcentaje', 'sufijo' => '%', 'clase' => 'whitespace-nowrap'],
            ],
            'campos' => [
                ['nombre' => 'ActividadId', 'etiqueta' => 'Actividad ID', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: MONTAJE'],
                ['nombre' => 'Porcentaje', 'etiqueta' => 'Porcentaje', 'tipo' => 'number', 'requerido' => true, 'min' => 0, 'max' => 100, 'step' => '0.01', 'placeholder' => '0-100'],
            ],
            'textos' => self::textos('Actividad', 'la actividad', 'esta actividad', 'Nueva', 'No hay actividades registradas'),
        ];
    }

    /** @return Catalogo */
    public static function comentarios(): array
    {
        return [
            'clave' => 'comentarios',
            'titulo' => 'Catálogo de Comentarios',
            'modulo' => 'Comentarios',
            'endpoint' => route('atadores.catalogos.comentarios', absolute: false),
            'llave' => 'Nota1',
            'columnas' => [
                ['campo' => 'Nota1', 'titulo' => 'Nota 1', 'clase' => 'font-medium'],
                ['campo' => 'Nota2', 'titulo' => 'Nota 2'],
            ],
            'campos' => [
                ['nombre' => 'Nota1', 'etiqueta' => 'Nota 1', 'tipo' => 'textarea', 'requerido' => true, 'filas' => 3, 'placeholder' => 'Ingrese la primera nota'],
                ['nombre' => 'Nota2', 'etiqueta' => 'Nota 2', 'tipo' => 'textarea', 'filas' => 3, 'placeholder' => 'Ingrese la segunda nota (opcional)'],
            ],
            'textos' => self::textos('Comentario', 'el comentario', 'este comentario', 'Nuevo', 'No hay comentarios registrados'),
        ];
    }

    /** @return Catalogo */
    public static function maquinas(): array
    {
        return [
            'clave' => 'maquinas',
            'titulo' => 'Máquinas Atadores',
            'modulo' => 'Maquinas',
            'endpoint' => route('atadores.catalogos.maquinas', absolute: false),
            'llave' => 'MaquinaId',
            'columnas' => [
                ['campo' => 'MaquinaId', 'titulo' => 'Máquina ID', 'clase' => 'font-medium'],
            ],
            'campos' => [
                ['nombre' => 'MaquinaId', 'etiqueta' => 'Máquina ID', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ingrese el ID de la máquina'],
            ],
            'textos' => self::textos('Máquina', 'la máquina', 'esta máquina', 'Nueva', 'No hay máquinas registradas'),
        ];
    }

    /** @return array<string, string> */
    private static function textos(string $nombre, string $conArticulo, string $demostrativo, string $nuevo, string $vacio): array
    {
        return [
            'nuevo' => "{$nuevo} {$nombre}",
            'editar' => "Editar {$nombre}",
            'eliminar' => "Eliminar {$nombre}",
            'vacio' => $vacio,
            'confirmar' => "¿Está seguro que desea eliminar {$demostrativo}?",
            'errorCargar' => "No se pudo cargar {$conArticulo}",
            'errorGuardar' => "No se pudo guardar {$conArticulo}",
            'errorEliminar' => "No se pudo eliminar {$conArticulo}",
        ];
    }
}
