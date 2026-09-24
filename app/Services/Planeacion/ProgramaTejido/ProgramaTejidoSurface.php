<?php

declare(strict_types=1);

namespace App\Services\Planeacion\ProgramaTejido;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Superficie de Programa Tejido (PT-02 · 02.1): Programa o Muestras.
 *
 * Única fuente para decidir la superficie de un request y leer su fila de
 * config('planeacion.superficies'): tablas, módulo de permiso (idrol), capacidades
 * y rutas base del front. El código nuevo no decide la superficie con request()->is()
 * ni con str_replace sobre la URL.
 */
enum ProgramaTejidoSurface: string
{
    case Programa = 'programa';
    case Muestras = 'muestras';

    /**
     * Prefijos exactos de Muestras: el segmento completo, no "empieza con". Antes el
     * patrón 'muestras*' también atrapaba URIs como 'muestrasx/...' (hallazgo 8 de PT-01).
     */
    private const PREFIJOS_MUESTRAS = ['planeacion/muestras', 'planeacion/muestras-line', 'muestras'];

    public static function fromPath(string $path): self
    {
        // Decodificado, como lo compara el router (UriValidator hace rawurldecode): si no,
        // '/planeacion/%6Duestras/1' enruta a muestras.* (permiso de Muestras) pero caería en
        // las tablas de Programa. Revisión de seguridad de PT-02.
        $path = trim(rawurldecode($path), '/');
        foreach (self::PREFIJOS_MUESTRAS as $prefijo) {
            if ($path === $prefijo || str_starts_with($path, $prefijo.'/')) {
                return self::Muestras;
            }
        }

        return self::Programa;
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromPath($request->decodedPath());
    }

    /**
     * Superficie del request en curso. En consola/colas el path es '/' → Programa.
     */
    public static function actual(): self
    {
        return self::fromRequest(request());
    }

    public function esMuestras(): bool
    {
        return $this === self::Muestras;
    }

    public function tabla(): string
    {
        return (string) $this->config('tabla');
    }

    public function tablaLineas(): string
    {
        return (string) $this->config('tabla_lineas');
    }

    /** idrol de SYSRoles con el que se autorizan las mutaciones de esta superficie. */
    public function moduloPermiso(): int
    {
        return (int) $this->config('modulo_permiso');
    }

    /**
     * Columnas físicas que necesita cada capacidad. Una capacidad decidida A (true) no se
     * ofrece mientras alguna siga en columnas_ausentes, es decir, hasta que el DBA aplique
     * su database/sql/pt_muestras_*.sql y la sesión PT actualice el config.
     */
    private const COLUMNAS_POR_CAPACIDAD = [
        'redbooth' => ['IdRedbooth', 'NombreRedbooth'],
        'marbetes' => ['NoMarbete', 'RollosProgramados', 'ProduccionMarbetes'],
        'produccion' => ['RollosProgramados', 'ProdId'],
    ];

    /**
     * true = la superficie soporta HOY la capacidad: decidida (Programa, o alternativa A en
     * Muestras) y con sus columnas físicas. false/null o columnas pendientes = no se ofrece.
     */
    public function soporta(string $capacidad): bool
    {
        if ($this->config("capacidades.{$capacidad}") !== true) {
            return false;
        }
        $ausentes = (array) $this->config('columnas_ausentes');

        return array_intersect(self::COLUMNAS_POR_CAPACIDAD[$capacidad] ?? [], $ausentes) === [];
    }

    /**
     * Guard de capacidad B (decisión 01.3): corta con 422 explícito en vez del 500/404/éxito
     * aparente de antes. Se llama antes de tocar datos o archivos.
     *
     * @throws HttpResponseException
     */
    public function exigir(string $capacidad): void
    {
        if ($this->soporta($capacidad)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => "La acción no está disponible en {$this->titulo()}.",
            'capacidad' => $capacidad,
            'superficie' => $this->value,
        ], 422));
    }

    /** @return array<string, bool> */
    public function capacidades(): array
    {
        $capacidades = [];
        foreach (array_keys((array) $this->config('capacidades')) as $capacidad) {
            $capacidades[$capacidad] = $this->soporta($capacidad);
        }

        return $capacidades;
    }

    public function basePath(): string
    {
        return $this->esMuestras() ? '/planeacion/muestras' : '/planeacion/programa-tejido';
    }

    public function apiPath(): string
    {
        return $this->esMuestras() ? '/muestras' : '/programa-tejido';
    }

    public function linePath(): string
    {
        return $this->esMuestras() ? '/planeacion/muestras-line' : '/planeacion/req-programa-tejido-line';
    }

    public function titulo(): string
    {
        return $this->esMuestras() ? 'Muestras' : 'Programa Tejido';
    }

    private function config(string $clave): mixed
    {
        return config("planeacion.superficies.{$this->value}.{$clave}");
    }
}
