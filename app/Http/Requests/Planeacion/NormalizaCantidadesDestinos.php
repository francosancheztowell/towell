<?php

namespace App\Http\Requests\Planeacion;

/**
 * Quita el separador de miles de las cantidades por destino ("1,500" -> "1500") antes de
 * validar: sin esto, numeric las rechaza y un (float) directo las truncaba a 1.
 * Livewire llama a normalizarDestinos() antes de validar con las mismas rules().
 */
trait NormalizaCantidadesDestinos
{
    private const CANTIDADES_DESTINO = ['pedido', 'saldo', 'pedido_tempo'];

    public static function normalizarDestinos(mixed $destinos): mixed
    {
        if (! is_array($destinos)) {
            return $destinos;
        }

        return array_map(static function ($destino) {
            if (! is_array($destino)) {
                return $destino;
            }
            foreach (self::CANTIDADES_DESTINO as $campo) {
                if (is_string($destino[$campo] ?? null)) {
                    $destino[$campo] = str_replace([',', ' '], '', $destino[$campo]);
                }
            }

            return $destino;
        }, $destinos);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('destinos')) {
            $this->merge(['destinos' => self::normalizarDestinos($this->input('destinos'))]);
        }
    }
}
