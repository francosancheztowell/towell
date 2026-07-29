<?php

declare(strict_types=1);

namespace App\Livewire\Mecanicos\VerificaMaquina;

use App\Models\Mecanicos\MecActividadesModel;
use App\Models\Mecanicos\MecVerificaMaquinaLineModel;
use App\Models\Mecanicos\MecVerificaMaquinaModel;
use App\Models\Planeacion\ReqTelares;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    private const MODULO_PERMISO = 'Estado Maquina';

    private const VALORES_VALIDOS = ['1', '2', '3'];

    public string $folio;

    public function mount(string $folio): void
    {
        $this->authorizeAccess();

        abort_unless(MecVerificaMaquinaModel::whereKey($folio)->exists(), 404);

        $this->folio = $folio;
    }

    public function capturar(string $noTelarId, int $actividadId, string $valor): void
    {
        $this->authorizeAccess();
        abort_unless($this->puedeCapturar(), 403, 'No tienes permiso para capturar esta verificación.');
        abort_unless(in_array($valor, self::VALORES_VALIDOS, true), 422, 'Valor inválido.');

        $actividad = MecActividadesModel::findOrFail($actividadId);

        MecVerificaMaquinaLineModel::updateOrCreate(
            [
                'Folio' => $this->folio,
                'NoTelarId' => $noTelarId,
                'Actividad' => $actividad->Actividad,
            ],
            [
                'Orden' => $actividad->Orden,
                'Valor' => $valor,
            ],
        );
    }

    public function finalizar(): void
    {
        $this->authorizeAccess();
        abort_unless(userCan('modificar', self::MODULO_PERMISO), 403, 'No tienes permiso para finalizar esta verificación.');

        MecVerificaMaquinaModel::whereKey($this->folio)->update([
            'Estatus' => 'Terminado',
            'HoraFin' => now('America/Mexico_City')->format('H:i:s'),
        ]);
    }

    public function render(): View
    {
        $verificacion = MecVerificaMaquinaModel::findOrFail($this->folio);

        $telares = ReqTelares::query()
            ->orderBy('NoTelarId')
            ->get(['NoTelarId', 'Nombre']);

        $actividades = MecActividadesModel::query()
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get(['Id', 'Orden', 'Actividad']);

        $lineas = MecVerificaMaquinaLineModel::query()
            ->where('Folio', $this->folio)
            ->get(['NoTelarId', 'Actividad', 'Valor']);

        $valores = [];
        foreach ($lineas as $linea) {
            $valores[$linea->NoTelarId.'|'.$linea->Actividad] = $linea->Valor;
        }

        $promedios = [];
        foreach ($actividades as $actividad) {
            $valoresActividad = $lineas->where('Actividad', $actividad->Actividad)
                ->pluck('Valor')
                ->filter(fn ($valor) => is_numeric($valor))
                ->map(fn ($valor) => (float) $valor);

            $promedios[$actividad->Actividad] = $valoresActividad->isNotEmpty()
                ? round($valoresActividad->avg(), 1)
                : null;
        }

        return view('livewire.mecanicos.verifica-maquina.show', [
            'verificacion' => $verificacion,
            'telares' => $telares,
            'actividades' => $actividades,
            'valores' => $valores,
            'promedios' => $promedios,
            'puedeCapturar' => $this->puedeCapturar(),
            'puedeFinalizar' => userCan('modificar', self::MODULO_PERMISO),
        ]);
    }

    private function puedeCapturar(): bool
    {
        return userCan('crear', self::MODULO_PERMISO) || userCan('modificar', self::MODULO_PERMISO);
    }

    private function authorizeAccess(): void
    {
        abort_unless(userCan('acceso', self::MODULO_PERMISO), 403, 'No tienes acceso al módulo de Estado de Máquina.');
    }
}
