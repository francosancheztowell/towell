<?php

declare(strict_types=1);

namespace App\Livewire\Mecanicos\VerificaMaquina;

use App\Helpers\FolioHelper;
use App\Models\Mecanicos\MecVerificaMaquinaModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const MODULO_PERMISO = 'Estado Maquina';

    private const MODULO_FOLIOS = 'MecVerificaMaquina';

    private const PREFIJO_FOLIOS = 'VM';

    private const LONGITUD_CONSECUTIVO_FOLIOS = 5;

    #[Url(except: '')]
    public string $estatus = '';

    public string $turno = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function filtrarEstatus(string $estatus): void
    {
        $permitidos = ['', 'Activo', 'Terminado', 'Autorizado'];
        abort_unless(in_array($estatus, $permitidos, true), 422, 'Estatus de filtro no válido.');

        $this->estatus = $estatus;
        $this->resetPage();
    }

    public function updatedEstatus(): void
    {
        $this->resetPage();
    }

    public function crear(): void
    {
        $this->authorizeAccess();
        abort_unless(userCan('crear', self::MODULO_PERMISO), 403, 'No tienes permiso para crear verificaciones.');

        $validated = $this->validate([
            'turno' => ['required', 'integer', 'between:1,4'],
        ], [], ['turno' => 'Turno']);

        $usuario = Auth::user();

        try {
            $folio = DB::transaction(function () use ($usuario, $validated): string {
                $this->asegurarSecuenciaFolios();

                $folio = trim(FolioHelper::obtenerSiguienteFolio(self::MODULO_FOLIOS, self::LONGITUD_CONSECUTIVO_FOLIOS));

                if ($folio === '') {
                    throw new \RuntimeException('Folio vacío generado para MecVerificaMaquina.');
                }

                $ahora = now('America/Mexico_City');

                MecVerificaMaquinaModel::create([
                    'Folio' => $folio,
                    'Fecha' => $ahora->toDateString(),
                    'TurnoRecibe' => (int) $validated['turno'],
                    'CveOperador' => $usuario?->numero_empleado,
                    'NomOperador' => $usuario?->nombre,
                    'Estatus' => 'Activo',
                    'HoraInicio' => $ahora->format('H:i:s'),
                ]);

                return $folio;
            });
        } catch (\Throwable $exception) {
            Log::error('Error al crear verificación de máquina', ['error' => $exception->getMessage()]);
            $this->addError('turno', 'No se pudo crear la verificación. Intenta de nuevo.');

            return;
        }

        $this->redirect(route('mecanicos.estado-maquina.show', $folio), navigate: true);
    }

    public function render(): View
    {
        $verificaciones = MecVerificaMaquinaModel::query()
            ->when($this->estatus !== '', fn ($query) => $query->where('Estatus', $this->estatus))
            ->orderByDesc('Fecha')
            ->orderByDesc('HoraInicio')
            ->orderByDesc('Folio')
            ->paginate(15, [
                'Folio',
                'Fecha',
                'TurnoRecibe',
                'CveOperador',
                'NomOperador',
                'Estatus',
                'HoraInicio',
                'HoraFin',
            ]);

        $usuario = Auth::user();

        return view('livewire.mecanicos.verifica-maquina.index', [
            'verificaciones' => $verificaciones,
            'operadorClave' => $usuario?->numero_empleado,
            'operadorNombre' => $usuario?->nombre,
            'puedeEditar' => userCan('modificar', self::MODULO_PERMISO),
        ]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(userCan('acceso', self::MODULO_PERMISO), 403, 'No tienes acceso al módulo de Estado de Máquina.');
    }

    /**
     * Crea la secuencia la primera vez que se use el módulo y la alinea con
     * los folios VM existentes para no reutilizar un folio anterior.
     */
    private function asegurarSecuenciaFolios(): void
    {
        DB::transaction(function (): void {
            $secuencia = SSYSFoliosSecuencia::query()
                ->where('modulo', self::MODULO_FOLIOS)
                ->lockForUpdate()
                ->first();

            if ($secuencia !== null) {
                return;
            }

            $ultimoFolio = (string) MecVerificaMaquinaModel::query()
                ->where('Folio', 'like', self::PREFIJO_FOLIOS.'%')
                ->orderByDesc('Folio')
                ->value('Folio');

            $consecutivoInicial = (int) substr($ultimoFolio, strlen(self::PREFIJO_FOLIOS));

            SSYSFoliosSecuencia::create([
                'modulo' => self::MODULO_FOLIOS,
                'prefijo' => self::PREFIJO_FOLIOS,
                'consecutivo' => $consecutivoInicial,
            ]);
        });
    }
}
