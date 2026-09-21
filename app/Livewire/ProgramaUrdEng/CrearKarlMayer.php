<?php

declare(strict_types=1);

namespace App\Livewire\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\OrdenKarlMayerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Alta de orden de urdido para Karl Mayer.
 *
 * Hibrido a proposito: los campos del formulario viven aqui (wire:model
 * diferido, sin ida y vuelta por tecla) y la validacion es de servidor. La
 * tabla de materiales del ERP, sus totales y el buscador de tamanos siguen en
 * TypeScript: son calculo en vivo sobre datos que no se persisten hasta que se
 * pulsa Crear, y meterlos en Livewire seria un round-trip por pulsacion.
 *
 * El TS empuja la seleccion de materiales con $wire.set('materiales', ...).
 */
class CrearKarlMayer extends Component
{
    public const MODULO = 'Programa Urd / Eng';

    public string $noTelar = '';

    public string $barras = '';

    public string $fibra = '';

    public string $tamano = '';

    public string $cuenta = '';

    public string $calibre = '';

    public string $metros = '';

    public string $fechaProgramada = '';

    public string $tipoAtado = 'Normal';

    public string $bomId = '';

    public string $loteProveedor = '';

    public string $observaciones = '';

    /** @var array<int, string> */
    public array $julios = ['4', '', '', ''];

    /** @var array<int, string> */
    public array $hilos = ['', '', '', ''];

    /** @var array<int, string> */
    public array $obs = ['', '', '', ''];

    /**
     * Piezas marcadas en la tabla de inventario. Las escribe el TS.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $materiales = [];

    /** Antes de guardar se pide cuando se necesita el material. */
    public bool $pidiendoFechaRequerimiento = false;

    public string $fechaRequerimiento = '';

    #[Locked]
    public bool $puedeCrear = false;

    public ?string $dataError = null;

    private OrdenKarlMayerService $ordenes;

    public function boot(OrdenKarlMayerService $ordenes): void
    {
        $this->ordenes = $ordenes;
        $this->authorizeAccess();
    }

    public function mount(): void
    {
        $this->fechaProgramada = now()->format('Y-m-d');
        $this->resolvePermissions();
    }

    /** protected para que los tests lo puedan neutralizar. */
    protected function authorizeAccess(): void
    {
        abort_unless(Auth::check(), 403, 'La sesión del usuario ya no es válida.');
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a Programa Urd / Eng.');
    }

    protected function resolvePermissions(): void
    {
        $this->puedeCrear = userCan('crear', self::MODULO);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'noTelar' => ['required', Rule::in(OrdenKarlMayerService::TELARES)],
            'barras' => ['required', Rule::in(OrdenKarlMayerService::BARRAS)],
            'fibra' => ['required', 'string', 'max:100'],
            'tamano' => ['required', 'string', 'max:50'],
            'cuenta' => ['nullable', 'string', 'max:50'],
            'calibre' => ['nullable', 'string', 'max:50'],
            'metros' => ['required', 'numeric', 'min:0'],
            'fechaProgramada' => ['required', 'date'],
            'tipoAtado' => ['required', Rule::in(['Normal', 'Especial'])],
            'bomId' => ['required', 'string', 'max:50'],
            'loteProveedor' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'julios' => ['array'],
            'hilos' => ['array'],
            'obs' => ['array'],
            'obs.*' => ['nullable', 'string', 'max:255'],
            'materiales' => ['required', 'array', 'min:1'],
            'materiales.*.itemId' => ['nullable', 'string', 'max:50'],
            'materiales.*.configId' => ['nullable', 'string', 'max:50'],
            'materiales.*.inventSizeId' => ['nullable', 'string', 'max:50'],
            'materiales.*.inventColorId' => ['nullable', 'string', 'max:50'],
            'materiales.*.inventLocationId' => ['nullable', 'string', 'max:50'],
            'materiales.*.inventBatchId' => ['nullable', 'string', 'max:50'],
            'materiales.*.wmsLocationId' => ['nullable', 'string', 'max:50'],
            'materiales.*.inventSerialId' => ['nullable', 'string', 'max:50'],
            'materiales.*.kilos' => ['nullable', 'numeric'],
            'materiales.*.conos' => ['nullable', 'numeric'],
            'materiales.*.loteProv' => ['nullable', 'string', 'max:100'],
            'materiales.*.noProv' => ['nullable', 'string', 'max:100'],
            'materiales.*.prodDate' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'materiales.required' => 'Seleccione al menos un material de la tabla de inventario.',
            'materiales.min' => 'Seleccione al menos un material de la tabla de inventario.',
        ];
    }

    /**
     * Cuenta y calibre salen del tamano: '2960-12/1' -> cuenta 2960, calibre 12.
     */
    public function updatedTamano(string $valor): void
    {
        $tamano = trim($valor);

        if ($tamano === '') {
            $this->cuenta = '';
            $this->calibre = '';

            return;
        }

        if (preg_match('#^([^-]+)-([^/]+)/1$#', $tamano, $m)) {
            $this->cuenta = trim($m[1]);
            $this->calibre = trim($m[2]);

            return;
        }

        $this->cuenta = $tamano;
        $this->calibre = '';
    }

    /** Paso 1 de guardar: validar y pedir la fecha de requerimiento. */
    public function confirmar(): void
    {
        abort_unless($this->puedeCrear, 403, 'No tiene permiso para crear órdenes.');

        $this->validate();

        if (! $this->hayAlgunJulioOHilo()) {
            $this->addError('julios', 'Capture al menos un julio o un número de hilos.');

            return;
        }

        $this->fechaRequerimiento = now()->startOfMinute()->format('Y-m-d\TH:i');
        $this->pidiendoFechaRequerimiento = true;
    }

    public function cancelarFechaRequerimiento(): void
    {
        $this->pidiendoFechaRequerimiento = false;
    }

    /** Paso 2: con la fecha confirmada, crear la orden. */
    public function guardar(): mixed
    {
        abort_unless($this->puedeCrear, 403, 'No tiene permiso para crear órdenes.');

        $this->validate();
        $this->validate(
            ['fechaRequerimiento' => ['required', 'date', 'after_or_equal:'.now()->startOfMinute()->format('Y-m-d H:i')]],
            ['fechaRequerimiento.after_or_equal' => 'La fecha de requerimiento no puede ser anterior a ahora.'],
        );

        $usuario = Auth::user();

        try {
            $resultado = $this->ordenes->crear(
                $this->datosParaElServicio(),
                $usuario->numero_empleado ?? null,
                $usuario->nombre ?? null,
            );
        } catch (Throwable $e) {
            report($e);
            $this->pidiendoFechaRequerimiento = false;
            $this->dispatch('aviso', tipo: 'error', texto: 'No se pudo crear la orden. Intente de nuevo.');

            return null;
        }

        session()->flash('aviso-exito', "Orden Karl Mayer creada. Folio: {$resultado['folio']}");

        return $this->redirectRoute('programa.urd.eng.index', navigate: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosParaElServicio(): array
    {
        return [
            'no_telar' => $this->noTelar,
            'barras' => $this->barras,
            'fibra' => $this->fibra,
            'tamano' => $this->tamano,
            'cuenta' => $this->cuenta,
            'calibre' => $this->calibre,
            'metros' => $this->metros,
            'fecha_programada' => $this->fechaProgramada,
            'tipo_atado' => $this->tipoAtado,
            'bom_id' => $this->bomId,
            'lote_proveedor' => $this->loteProveedor,
            'observaciones' => $this->observaciones,
            'julios' => $this->julios,
            'hilos' => $this->hilos,
            'obs' => $this->obs,
            'materiales' => $this->materiales,
            'fechaRequerimiento' => $this->fechaRequerimiento,
        ];
    }

    private function hayAlgunJulioOHilo(): bool
    {
        foreach (array_keys($this->julios + $this->hilos) as $i) {
            if (trim((string) ($this->julios[$i] ?? '')) !== '' || trim((string) ($this->hilos[$i] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    public function render(): View
    {
        return view('livewire.programa-urd-eng.crear-karl-mayer', [
            'telares' => OrdenKarlMayerService::TELARES,
            'opcionesBarras' => OrdenKarlMayerService::BARRAS,
        ]);
    }
}
