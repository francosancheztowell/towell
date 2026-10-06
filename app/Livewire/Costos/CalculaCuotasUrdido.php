<?php

declare(strict_types=1);

namespace App\Livewire\Costos;

use App\Services\Costos\CuotasUrdidoService;

/**
 * Botón "Calcular Urdido" de Cuotas: cuotas reales de Urdido desde AX, producción y paros
 * (App\Services\Costos\CuotasUrdidoService), mes por mes del rango. Reemplaza las columnas
 * calculadas; lo capturado a mano (prorrateos, maquila) se queda.
 */
trait CalculaCuotasUrdido
{
    /** @var array{año?: string, desde?: string, hasta?: string, conParos?: bool, tiempoMuerto?: bool}|null diálogo abierto; null = cerrado */
    public ?array $calculo = null;

    public function abrirCalculo(): void
    {
        $this->autorizarCalculo();

        $this->resetValidation();
        $mes = (string) now()->subMonth()->month; // el último mes cerrado
        $this->calculo = ['año' => (string) now()->year, 'desde' => $mes, 'hasta' => $mes, 'conParos' => false, 'tiempoMuerto' => false];
    }

    public function calcularUrdido(CuotasUrdidoService $servicio): void
    {
        $this->autorizarCalculo();

        $datos = $this->validate([
            'calculo.año' => ['required', 'integer', 'digits:4', 'between:2000,'.(now()->year + 1)],
            'calculo.desde' => ['required', 'integer', 'between:1,12'],
            'calculo.hasta' => ['required', 'integer', 'between:1,12', 'gte:calculo.desde'],
            'calculo.conParos' => ['boolean'],
            'calculo.tiempoMuerto' => ['boolean'],
        ], ['calculo.hasta.gte' => 'El mes final no puede ser antes del inicial.'], [
            'calculo.año' => 'año', 'calculo.desde' => 'desde', 'calculo.hasta' => 'hasta',
        ])['calculo'];

        $r = $servicio->actualizarRango((int) $datos['año'], (int) $datos['desde'], (int) $datos['hasta'], (bool) ($datos['tiempoMuerto'] ?? false), (bool) ($datos['conParos'] ?? false));

        $this->calculo = null;
        $this->tabla = 'real';
        $this->anio = (string) $datos['año'];
        $this->dispatch('aviso', tipo: $r['completo'] ? 'success' : 'warning', texto: $r['texto']);
    }

    /** Crea y reemplaza cuotas: pide los dos permisos. */
    private function autorizarCalculo(): void
    {
        abort_unless(userCan('crear', self::MODULO) && userCan('modificar', self::MODULO), 403);
    }
}
