<?php

declare(strict_types=1);

namespace App\Services\Planeacion;

use App\Models\Planeacion\Catalogos\CatMatrizCalibres;
use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

final class MatrizCalibresService
{
    private const TOLERANCIA_CALIBRE = 0.0001;

    public function buscar(MatrizCalibreClave $clave): ?CatMatrizCalibres
    {
        return $this->queryPorClave($clave)
            ->orderByDesc('Id')
            ->first();
    }

    /**
     * Aprende una equivalencia completa. Una misma clave siempre actualiza la
     * misma fila global, independientemente de la orden que la originó.
     *
     * @param  array<string, mixed>  $salida
     */
    public function aprender(MatrizCalibreClave $clave, array $salida): ?CatMatrizCalibres
    {
        $salidaNormalizada = $this->normalizarSalida($salida);
        if ($salidaNormalizada === null) {
            return null;
        }

        $registro = $this->buscar($clave);
        if ($registro !== null) {
            $registro->fill([...$clave->toArray(), ...$salidaNormalizada]);
            $registro->save();

            return $registro->fresh();
        }

        try {
            return CatMatrizCalibres::query()->create([
                ...$clave->toArray(),
                ...$salidaNormalizada,
            ]);
        } catch (QueryException $exception) {
            // Otro proceso pudo insertar la misma clave entre buscar() y create().
            $registro = $this->buscar($clave);
            if ($registro === null) {
                throw $exception;
            }

            $registro->fill($salidaNormalizada);
            $registro->save();

            return $registro->fresh();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function guardarRegistroCompleto(array $data, ?CatMatrizCalibres $registro = null): CatMatrizCalibres
    {
        $clave = MatrizCalibreClave::fromArray($data);
        $salida = $this->normalizarSalida($data);
        if ($salida === null) {
            throw new \InvalidArgumentException('Los cuatro campos de salida de Matriz de Calibres son obligatorios.');
        }

        if ($registro === null) {
            return $this->aprender($clave, $salida)
                ?? throw new \RuntimeException('No se pudo guardar la equivalencia.');
        }

        $equivalente = $this->buscar($clave);
        if ($equivalente !== null && ! $equivalente->is($registro)) {
            $equivalente->fill([...$clave->toArray(), ...$salida]);
            $equivalente->save();
            $registro->delete();

            return $equivalente->fresh();
        }

        $registro->fill([...$clave->toArray(), ...$salida]);
        $registro->save();

        return $registro->fresh();
    }

    private function queryPorClave(MatrizCalibreClave $clave): Builder
    {
        $query = CatMatrizCalibres::query()
            ->where('Tipo', $clave->tipo);

        $query = $clave->calibre === null
            ? $query->whereNull('Calibre')
            : $query->whereBetween('Calibre', [
                $clave->calibre - self::TOLERANCIA_CALIBRE,
                $clave->calibre + self::TOLERANCIA_CALIBRE,
            ]);

        $query = $clave->fibraId === null
            ? $query->whereNull('FibraId')
            : $query->where('FibraId', $clave->fibraId);

        return $clave->cuenta === null
            ? $query->whereNull('Cuenta')
            : $query->where('Cuenta', $clave->cuenta);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ItemId: string, ConfigId: string, InventSizeId: string, InventColorId: string}|null
     */
    private function normalizarSalida(array $data): ?array
    {
        $itemId = trim((string) ($data['ItemId'] ?? $data['itemId'] ?? ''));
        $configId = trim((string) ($data['ConfigId'] ?? $data['configId'] ?? ''));
        $inventSizeId = preg_replace('/\s+/', '', trim((string) ($data['InventSizeId'] ?? $data['inventSizeId'] ?? ''))) ?? '';
        $inventColorId = trim((string) ($data['InventColorId'] ?? $data['inventColorId'] ?? ''));

        if ($itemId === '' || $configId === '' || $inventSizeId === '' || $inventColorId === '') {
            return null;
        }

        return [
            'ItemId' => mb_substr($itemId, 0, 60),
            'ConfigId' => mb_substr($configId, 0, 60),
            'InventSizeId' => mb_substr($inventSizeId, 0, 60),
            'InventColorId' => mb_substr($inventColorId, 0, 60),
        ];
    }
}
