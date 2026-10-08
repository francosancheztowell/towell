<?php

namespace App\Services\Planeacion\ProgramaTejido\Edicion;

use App\Actions\Planeacion\ProgramaTejido\MutacionRechazada;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\CatalogoModelos;
use App\Support\Planeacion\TelarSalonResolver;

/**
 * Cambio de clave modelo en la edición inline: copia el modelo codificado del salón al
 * renglón (tipos, recortes y guardas del legacy) o rechaza con el 422 de siempre.
 *
 * @internal Paso de EdicionProgramaTejido (aplicarCambios → recalcularDerivados → persistir).
 */
final class ClaveModelo
{
    /**
     * Construccion de Jacquard/Smit: rizo, pie, trama y las cinco combinaciones.
     * Karl Mayer no teje nada de esto.
     */
    private const CONSTRUCCION_STD = [
        'CuentaRizo', 'CalibreRizo', 'CalibreRizo2', 'FibraRizo',
        'CuentaPie', 'CalibrePie', 'CalibrePie2', 'FibraPie', 'CodColorCtaPie', 'NombreCPie',
        'CalibreTrama', 'CalibreTrama2', 'FibraTrama', 'PasadasTrama', 'CodColorTrama', 'ColorTrama',
        'PasadasComb1', 'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorComb1', 'NombreCC1',
        'PasadasComb2', 'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorComb2', 'NombreCC2',
        'PasadasComb3', 'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorComb3', 'NombreCC3',
        'PasadasComb4', 'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorComb4', 'NombreCC4',
        'PasadasComb5', 'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorComb5', 'NombreCC5',
    ];

    /** Construccion de Karl Mayer: cuatro barras. Ningun otro salon las usa. */
    private const CONSTRUCCION_KM = [
        'CuentaBarra1', 'CalibreBarra1', 'CalibreBarra12', 'CodColorBarra1', 'ColorBarra1', 'FibraBarra1', 'PasadasBarra1',
        'CuentaBarra2', 'CalibreBarra2', 'CalibreBarra22', 'CodColorBarra2', 'ColorBarra2', 'FibraBarra2', 'PasadasBarra2',
        'CuentaBarra3', 'CalibreBarra3', 'CalibreBarra32', 'CodColorBarra3', 'ColorBarra3', 'FibraBarra3', 'PasadasBarra3',
        'CuentaBarra4', 'CalibreBarra4', 'CalibreBarra42', 'CodColorBarra4', 'ColorBarra4', 'FibraBarra4', 'PasadasBarra4',
    ];

    /**
     * Deja en null la construccion que no corresponde al salon del registro.
     *
     * Karl Mayer teje con cuatro barras: rizo, pie, trama y C1-C5 no aplican. Al reves,
     * Jacquard/Smit no usan barras. Sin esto, cambiar la clave modelo arrastra la
     * construccion del modelo anterior en las columnas que el nuevo salon no escribe.
     */
    public static function limpiarConstruccionSegunSalon(ReqProgramaTejido $registro): void
    {
        $km = TelarSalonResolver::esKarlMayer($registro->SalonTejidoId ?? null, $registro->NoTelarId ?? null);

        foreach ($km ? self::CONSTRUCCION_STD : self::CONSTRUCCION_KM as $columna) {
            $registro->setAttribute($columna, null);
        }
    }

    /**
     * Clave vacía: solo se limpia. Con clave: copia el modelo codificado del salón o
     * rechaza (avisando si la clave está en otro salón). Devuelve si se copió un modelo.
     */
    public static function aplicar(ReqProgramaTejido $registro, array $data): bool
    {
        $nuevaClave = $data['tamano_clave'] ?: null;
        if (empty($nuevaClave)) {
            $registro->TamanoClave = null;

            return false;
        }

        // ponytail: el salón ya no se filtra a Jacquard/Smit. Solo acota la búsqueda del
        // modelo codificado; Karl Mayer (y cualquier salón nuevo) también trae sus datos.
        $salon = $registro->SalonTejidoId ?? '';
        $modelo = CatalogoModelos::porTamanoClave($nuevaClave, $salon, self::columnasSelect());
        if (! $modelo) {
            throw self::rechazoClave($nuevaClave, $salon);
        }

        $registro->TamanoClave = $nuevaClave;
        self::copiarModelo($registro, $modelo, $data);
        self::limpiarConstruccionSegunSalon($registro);

        return true;
    }

    private static function rechazoClave(string $clave, string $salon): MutacionRechazada
    {
        // ponytail: una sola búsqueda sin filtro de salón; si cae en el salón actual no hay nada que avisar.
        $otro = CatalogoModelos::porTamanoClave($clave, null, ['SalonTejidoId']);
        $salonEncontrado = trim((string) ($otro->SalonTejidoId ?? ''));

        if ($salonEncontrado === '' || strcasecmp($salonEncontrado, trim($salon)) === 0) {
            return MutacionRechazada::con([
                'success' => false,
                'message' => "La clave modelo \"{$clave}\" no existe en Modelos Codificados",
                'tipo' => 'error',
            ]);
        }

        return MutacionRechazada::con([
            'success' => false,
            'message' => "La clave modelo \"{$clave}\" no existe en el salón actual ({$salon}), pero se encontró en el salón: {$salonEncontrado}",
            'tipo' => 'alerta',
            'salon_encontrado' => $salonEncontrado,
        ]);
    }

    /**
     * Copia el modelo al registro según columnasModelo(). Una columna nula en el modelo no
     * toca el registro; tampoco la que el mismo PUT edita explícitamente (guardas).
     * FlogsId, NombreProyecto, OrdPrincipal y el color de pie NO se toman del modelo.
     */
    private static function copiarModelo(ReqProgramaTejido $registro, ReqModelosCodificados $modelo, array $data): void
    {
        foreach (self::columnasModelo() as [$origen, $destinos, $tipo, $guardas]) {
            $valor = $modelo->{$origen} ?? null;
            if ($valor === null || array_intersect_key(array_flip($guardas), $data) !== []) {
                continue;
            }
            if ($tipo === 'flog') {
                // Tipo de pedido si viene del FlogsId del modelo (ej. RS, CE)
                if (preg_match('/^([A-Za-z]{2})-/', (string) $valor, $m)) {
                    $registro->TipoPedido = $m[1];
                }

                continue;
            }
            foreach ((array) $destinos as $destino) {
                $registro->setAttribute($destino, self::convertir($valor, $tipo));
            }
        }
    }

    /**
     * [columna del modelo, columna(s) del programa, tipo, campos del PUT que la bloquean].
     * Tipos: string/float/int = cast; tN = '' a null y recorte a N; r50 = recorte a 50.
     * El orden es el de asignación del legacy.
     *
     * @return list<array{0: string, 1: string|list<string>, 2: string, 3: list<string>}>
     */
    private static function columnasModelo(): array
    {
        $cols = [
            ['InventSizeId', 'InventSizeId', 'string', []],
            ['ItemId', 'ItemId', 'string', []],
            ['Nombre', 'NombreProducto', 'r50', ['descripcion']],
            ['FlogsId', 'TipoPedido', 'flog', []],
            ['NoTiras', 'NoTiras', 'float', ['no_tiras']],
            ['Luchaje', 'Luchaje', 'float', ['luchaje']],
            ['Repeticiones', 'Repeticiones', 'float', []],
            ['PesoCrudo', 'PesoCrudo', 'int', ['peso_crudo']],
            ['Peine', 'Peine', 'int', ['peine']],
            ['LargoToalla', 'LargoCrudo', 'int', ['largo_crudo']],
            ['AnchoToalla', ['Ancho', 'AnchoToalla'], 'float', ['ancho', 'ancho_toalla']],
            ['FibraRizo', 'FibraRizo', 't40', ['hilo']],
            ['CuentaRizo', 'CuentaRizo', 't40', []],
            ['CalibreRizo', 'CalibreRizo', 'float', []],
            ['CalibreRizo2', 'CalibreRizo2', 'float', []],
            ['CalibrePie', 'CalibrePie', 'float', []],
            ['CalibrePie2', 'CalibrePie2', 'float', []],
            ['CuentaPie', 'CuentaPie', 't40', []],
            // Cruzado a propósito: el modelo guarda los calibres de trama al revés.
            ['CalibreTrama2', 'CalibreTrama', 'float', []],
            ['CalibreTrama', 'CalibreTrama2', 'float', []],
            ['FibraTramaFondoC1', 'FibraTrama', 't40', []],
            ['PasadasTramaFondoC1', 'PasadasTrama', 'int', []],
            ['CodColorTrama', 'CodColorTrama', 't40', []],
            ['ColorTrama', 'ColorTrama', 't40', []],
        ];
        foreach ([1, 2, 3, 4, 5] as $n) {
            $cols[] = ["PasadasComb{$n}", "PasadasComb{$n}", 'int', []];
        }
        foreach ([1, 2, 3, 4, 5] as $n) {
            array_push($cols,
                ["CalibreComb{$n}", "CalibreComb{$n}", 't40', []],
                ["CalibreComb{$n}2", "CalibreComb{$n}2", 'float', []],
                ["FibraComb{$n}", "FibraComb{$n}", 't40', []],
                ["CodColorC{$n}", "CodColorComb{$n}", 't40', []],
                ["NomColorC{$n}", "NombreCC{$n}", 't60', []],
            );
        }
        // Karl Mayer: las barras se llaman igual en las dos tablas.
        foreach ([1, 2, 3, 4] as $n) {
            foreach (['Cuenta', 'Calibre', 'CodColor', 'Color', 'Fibra'] as $campo) {
                $cols[] = ["{$campo}Barra{$n}", "{$campo}Barra{$n}", 't40', []];
            }
            $cols[] = ["PasadasBarra{$n}", "PasadasBarra{$n}", 'int', []];
        }

        return [
            ...$cols,
            ['MedidaPlano', 'MedidaPlano', 'float', []],
            ['DobladilloId', 'DobladilloId', 't40', []],
            ['VelocidadSTD', 'VelocidadSTD', 'int', []],
            ['Rasurado', 'Rasurado', 't10', ['rasurado']],
        ];
    }

    /** @return list<string> */
    private static function columnasSelect(): array
    {
        return array_values(array_unique(['TamanoClave', 'SalonTejidoId', ...array_column(self::columnasModelo(), 0)]));
    }

    private static function convertir(mixed $valor, string $tipo): mixed
    {
        return match ($tipo) {
            'string' => (string) $valor,
            'float' => (float) $valor,
            'int' => (int) $valor,
            'r50' => mb_substr((string) $valor, 0, 50),
            default => ($valor ?: null) !== null ? mb_substr((string) $valor, 0, (int) substr($tipo, 1)) : null,
        };
    }
}
