<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Catalogos;

use App\Enums\Planeacion\Densidad;
use App\Enums\Planeacion\VarianteEstandar;
use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqVelocidadStd;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alta, edición y borrado de Eficiencia STD / Velocidad STD con una sola implementación
 * (antes dos controllers copiados). Editar el estándar lo aplica a los programas de tejido que lo
 * usan y los guarda con el observer, que recalcula sus fórmulas (StdDia, HorasProd, líneas…).
 */
final class EstandarCatalogoService
{
    /** @param  array<string, mixed>  $datos  validados por EstandarRequest */
    public function crear(VarianteEstandar $variante, array $datos): ResultadoCatalogo
    {
        $clave = $this->clave($datos, ($datos['SalonTejidoId'] ?? null) ?: $variante->salonPorDefectoAlta());
        if ($this->duplicados($variante, $clave)->exists()) {
            return ResultadoCatalogo::rechazo($variante->mensajeDuplicado(false));
        }

        $variante->modelo()::create($clave + [$variante->columna() => $datos[$variante->columna()]]);

        return ResultadoCatalogo::ok($variante->mensajeCreado($clave['SalonTejidoId'], $clave['NoTelarId'], $clave['FibraId']));
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(VarianteEstandar $variante, ReqEficienciaStd|ReqVelocidadStd $registro, array $datos): ResultadoCatalogo
    {
        $clave = $this->clave($datos, ($datos['SalonTejidoId'] ?? null) ?: 'JACQUARD');
        if ($this->duplicados($variante, $clave)->where('Id', '!=', (int) $registro->Id)->exists()) {
            return ResultadoCatalogo::rechazo($variante->mensajeDuplicado(true));
        }

        $anterior = $this->clave($registro->only(['NoTelarId', 'FibraId', 'Densidad']), $registro->SalonTejidoId);
        $valorAnterior = $variante->valor($registro->{$variante->columna()} ?? 0);
        $valor = $variante->valor($datos[$variante->columna()]);

        $registro->update($clave + [$variante->columna() => $valor]);

        $cambioClave = $this->llaveUso($anterior) !== $this->llaveUso($clave);
        if ($cambioClave || abs($valorAnterior - $valor) > 0.0001) {
            // Los programas que usaban el estándar con su clave anterior y los que caen en la nueva.
            foreach (array_unique([$this->llaveUso($anterior), $this->llaveUso($clave)], SORT_REGULAR) as $uso) {
                $this->aplicarAProgramas($variante, $uso, $valor);
            }
        }

        $etiqueta = ucfirst($variante->nombre());

        return ResultadoCatalogo::ok("{$etiqueta} para '{$clave['SalonTejidoId']} {$clave['NoTelarId']} - {$clave['FibraId']}' actualizada exitosamente");
    }

    public function eliminar(VarianteEstandar $variante, ReqEficienciaStd|ReqVelocidadStd $registro): ResultadoCatalogo
    {
        $uso = $this->llaveUso($this->clave($registro->only(['NoTelarId', 'FibraId', 'Densidad']), $registro->SalonTejidoId));
        if ($this->programasQueUsan($uso)->exists()) {
            return ResultadoCatalogo::rechazo("No se puede eliminar la {$variante->nombre()} porque esta siendo utilizada en el programa de tejido.");
        }

        $registro->delete();

        return ResultadoCatalogo::ok(ucfirst($variante->nombre())." para '{$uso['NoTelarId']} - {$uso['FibraId']}' eliminada exitosamente");
    }

    /**
     * Programas del telar que usan la fibra (en rizo o trama) y cuya densidad —derivada del
     * calibre de trama— coincide con la del estándar.
     *
     * @param  array{NoTelarId: string, FibraId: string, Densidad: string}  $uso
     * @return Builder<ReqProgramaTejido>
     */
    public function programasQueUsan(array $uso): Builder
    {
        // COALESCE(CalibreTrama, CalibreTrama2) es el `$p->CalibreTrama ?? $p->CalibreTrama2` de antes;
        // el ORM no expresa COALESCE. Parámetro enlazado como entero (en sqlite un texto no compara
        // como número); compatible con SQL Server 2008 R2.
        return ReqProgramaTejido::query()
            ->where('NoTelarId', $uso['NoTelarId'])
            ->where(fn (Builder $q) => $q->where('FibraRizo', $uso['FibraId'])->orWhere('FibraTrama', $uso['FibraId']))
            ->when(
                $uso['Densidad'] === Densidad::Alta->value,
                fn (Builder $q) => $q->whereRaw('COALESCE(CalibreTrama, CalibreTrama2) > ?', [Densidad::CALIBRE_TRAMA_ALTA]),
                fn (Builder $q) => $uso['Densidad'] === Densidad::Normal->value
                    ? $q->whereRaw('(COALESCE(CalibreTrama, CalibreTrama2) IS NULL OR COALESCE(CalibreTrama, CalibreTrama2) <= ?)', [Densidad::CALIBRE_TRAMA_ALTA])
                    : $q->whereRaw('1 = 0'), // una densidad fuera de Normal/Alta no la usa ningún programa
            );
    }

    /** @param  array{NoTelarId: string, FibraId: string, Densidad: string}  $uso */
    private function aplicarAProgramas(VarianteEstandar $variante, array $uso, float|int $valor): void
    {
        $columna = $variante->columnaPrograma();
        foreach ($this->programasQueUsan($uso)->get() as $programa) {
            if (abs((float) ($programa->{$columna} ?? 0) - $valor) <= 0.0001) {
                continue;
            }
            $programa->{$columna} = $valor;
            $programa->setAttribute('UpdatedAt', now());
            // save() con eventos: ReqProgramaTejidoObserver recalcula las fórmulas y las líneas.
            $programa->save();
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{SalonTejidoId: string, NoTelarId: string, FibraId: string, Densidad: string}
     */
    private function clave(array $datos, ?string $salon): array
    {
        return [
            'SalonTejidoId' => (string) $salon,
            'NoTelarId' => (string) $datos['NoTelarId'],
            'FibraId' => (string) $datos['FibraId'],
            'Densidad' => Densidad::deTexto($datos['Densidad'] ?? null),
        ];
    }

    /**
     * @param  array{SalonTejidoId: string, NoTelarId: string, FibraId: string, Densidad: string}  $clave
     * @return array{NoTelarId: string, FibraId: string, Densidad: string}
     */
    private function llaveUso(array $clave): array
    {
        return ['NoTelarId' => $clave['NoTelarId'], 'FibraId' => $clave['FibraId'], 'Densidad' => $clave['Densidad']];
    }

    /**
     * @param  array{SalonTejidoId: string, NoTelarId: string, FibraId: string, Densidad: string}  $clave
     * @return Builder<ReqEficienciaStd>|Builder<ReqVelocidadStd>
     */
    private function duplicados(VarianteEstandar $variante, array $clave): Builder
    {
        return $variante->modelo()::query()
            ->when($variante->duplicadoPorSalon(), fn (Builder $q) => $q->where('SalonTejidoId', $clave['SalonTejidoId']))
            ->where('NoTelarId', $clave['NoTelarId'])
            ->where('FibraId', $clave['FibraId'])
            ->where('Densidad', $clave['Densidad']);
    }
}
