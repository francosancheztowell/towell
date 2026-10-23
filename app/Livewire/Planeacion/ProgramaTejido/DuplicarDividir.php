<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Duplicar / Dividir un registro de Programa Tejido o Muestras: un <dialog> con switch de modo.
 *
 * - Se abre con el evento Livewire 'pt-duplicar-abrir' {id}. Cada modo guarda sus filas, así
 *   que cambiar de modo no pierde lo capturado.
 * - Dividir reparte SOLO el saldo: Σ saldos == saldo disponible (±0.5) o no se guarda. Se captura
 *   el pedido de cada fila y el saldo sale de él (pedido × (1 + %seg) − producción); el registro
 *   abierto toma la diferencia mientras se teclea (duplicar.ts). Sin saldo pendiente no se divide.
 * - Guardar valida con las reglas del FormRequest y llama a DividirTejido/DuplicarTejido sin
 *   HTTP. Al éxito despacha 'pt-duplicar-guardado' con el mismo JSON que daba el endpoint.
 */
class DuplicarDividir extends Component
{
    use AutocompletaClaveFlog;
    use ValidaFilasDestino;

    /** Programa o Muestras: decide tabla y módulo de permiso. Llega en mount() y no cambia. */
    #[Locked]
    public string $superficie = 'programa';

    /** Registro abierto; null = diálogo cerrado. */
    #[Locked]
    public ?int $registroId = null;

    /** @var array<string, mixed> datos del registro de origen (y saldo disponible del grupo) */
    #[Locked]
    public array $origen = [];

    /** OrdCompartida con 2+ filas: dividir redistribuye el saldo del grupo. */
    #[Locked]
    public bool $esGrupo = false;

    /** duplicar | vincular | dividir. Vincular es un duplicar cuyas copias comparten OrdCompartida. */
    public string $modo = 'duplicar';

    /** @var array{duplicar: list<array<string, mixed>>, dividir: list<array<string, mixed>>} */
    public array $filas = ['duplicar' => [], 'dividir' => []];

    /** Error de negocio (422) o de servidor, dentro del diálogo. */
    public ?string $aviso = null;

    public function mount(string $superficie = 'programa'): void
    {
        $this->superficie = (ProgramaTejidoSurface::tryFrom($superficie) ?? abort(404))->value;
        $this->fijarTabla();
    }

    /** Cada petición (abrir, set de una celda, agregar/quitar fila, guardar) exige 'crear'. */
    public function hydrate(): void
    {
        $this->autorizar('crear');
        $this->fijarTabla();
    }

    /** En /livewire/update no corre ProgramaTejidoContext: la tabla de Muestras se fija aquí. */
    private function fijarTabla(): void
    {
        $superficie = ProgramaTejidoSurface::from($this->superficie);
        if ($superficie->esMuestras()) {
            config([
                'planeacion.programa_tejido_table' => $superficie->tabla(),
                'planeacion.programa_tejido_line_table' => $superficie->tablaLineas(),
            ]);
        }
    }

    /** idrol del permiso (por idrol: los nombres de SYSRoles se repiten). */
    private function modulo(): int
    {
        return ProgramaTejidoSurface::from($this->superficie)->moduloPermiso();
    }

    private function autorizar(string $accion): void
    {
        abort_unless(userCan($accion, $this->modulo()), 403);
    }

    #[On('pt-duplicar-abrir')]
    public function abrir(int $id): void
    {
        $registro = CatalogosDestino::registro($id);
        if ($registro === null) {
            $this->dispatch('aviso', tipo: 'error', texto: 'El registro ya no existe. Recarga la página.');

            return;
        }

        $grupo = CatalogosDestino::grupo($registro);
        $this->resetValidation();
        $this->aviso = null;
        $this->esGrupo = $grupo->count() >= 2;
        $this->origen = FilasDestino::origen($registro, $grupo);
        $this->filas = [
            'duplicar' => [FilasDestino::filaDuplicar($this->origen, conTelar: true)],
            'dividir' => FilasDestino::filasDividir($grupo, $this->origen),
        ];
        $this->modo = $this->esGrupo && $this->puedeDividir() ? 'dividir' : 'duplicar';
        $this->registroId = $id;
        $this->marcarEditables();
    }

    public function agregarFila(): void
    {
        $llave = $this->llave();
        $this->filas[$llave][] = $llave === 'duplicar' ? FilasDestino::filaDuplicar($this->origen) : FilasDestino::fila($this->origen);
        $this->marcar($llave, array_key_last($this->filas[$llave]));
    }

    public function quitarFila(int $i): void
    {
        $filas = $this->filas[$this->llave()] ?? [];
        if (! isset($filas[$i]) || $filas[$i]['existente'] || count($filas) <= 1) {
            return;
        }
        unset($filas[$i]);
        $this->filas[$this->llave()] = array_values($filas);
        $this->sugerencias = [];
        $this->resetValidation();
        $this->marcarEditables();
    }

    public function updatedModo(): void
    {
        $permitidos = array_keys(array_filter([
            'duplicar' => true,
            'vincular' => $this->puedeVincular(),
            'dividir' => $this->puedeDividir(),
        ]));
        $this->modo = in_array($this->modo, $permitidos, true) ? $this->modo : 'duplicar';
        $this->aviso = null;
    }

    /** Dividir exige que el original tenga NoProduccion (o que ya haya grupo que redistribuir). */
    public function puedeDividir(): bool
    {
        return $this->porQueNoDivide() === null;
    }

    /** null = se puede dividir. Necesita NoProduccion (o un grupo que redistribuir) y saldo pendiente. */
    public function porQueNoDivide(): ?string
    {
        if (! $this->esGrupo && ! ($this->origen['conNoProduccion'] ?? false)) {
            return 'el registro no tiene No. de producción. Libéralo primero.';
        }

        return (float) ($this->origen['disponible'] ?? 0) > 0 ? null : 'no hay saldo pendiente que dividir.';
    }

    public function puedeVincular(): bool
    {
        return userCan('modificar', $this->modulo());
    }

    public function updatedFilas(mixed $valor, string $llave): void
    {
        [$modo, $i, $campo] = array_pad(explode('.', $llave), 3, '');
        $i = (int) $i;
        $fila = $this->filas[$modo][$i] ?? null;
        if ($fila === null) {
            return;
        }
        $this->aviso = null;

        if ($campo === 'clave' || $campo === 'flog') {
            // Mientras escribe solo se sugiere y no se marca: se valida al cambiar el campo, al elegir o al guardar.
            $this->resetErrorBag("filas.$modo.$i.$campo");
            $this->sugerir($campo, $i, (string) $valor);

            return;
        }
        match ($campo) {
            // Regla 3 (duplicar, sin producción): saldo = pedido × (1 + %seg/100).
            'pedido', 'porcSeg' => $modo === 'duplicar'
                ? $this->filas[$modo][$i]['saldo'] = FilasDestino::numero(FilasDestino::saldo($fila))
                : null,
            // Otro salón: el telar ya no vale y la clave se vuelve a buscar en ese salón.
            'salon' => $this->cargarClave($modo, $i, telar: ''),
            default => null,
        };
    }

    /**
     * Indicador de dividir: disponible (saldo del original o del grupo), asignado y diferencia.
     *
     * @return array{disponible: float, asignado: float, diferencia: float, cuadra: bool}
     */
    public function cuadre(): array
    {
        return FilasDestino::cuadre($this->filas['dividir'], (float) ($this->origen['disponible'] ?? 0));
    }

    public function guardar(): void
    {
        $dividir = $this->modo === 'dividir';
        $vincular = $this->modo === 'vincular';
        if ($vincular) {
            $this->autorizar('modificar');
        }
        if ($this->registroId === null || ! $this->listo()) {
            return;
        }

        $cuadra = ! $dividir || ($this->puedeDividir() && $this->cuadre()['cuadra']);
        $this->aviso = $cuadra ? null : 'La suma de saldos tiene que ser igual al saldo disponible.';
        $json = $cuadra ? $this->ejecutar($dividir, $vincular) : null;
        if ($json !== null) {
            $this->dispatch('pt-duplicar-guardado', ...$json);
            $this->cerrar();
        }
    }

    /**
     * Valida con las reglas del FormRequest y corre la misma lógica que el endpoint.
     *
     * @return array<string, mixed>|null el resultado, o null con $aviso puesto
     */
    private function ejecutar(bool $dividir, bool $vincular): ?array
    {
        $payload = $dividir
            ? FilasDestino::payloadDividir($this->origen, $this->filas['dividir'], (int) $this->registroId, $this->esGrupo)
            : FilasDestino::payloadDuplicar($this->origen, $this->filas['duplicar'], (int) $this->registroId, $vincular);
        $json = FilasDestino::correr($dividir, $payload);
        if (! empty($json['success'])) {
            return $json;
        }

        // El aviso de calendario trae HTML: aquí va como texto.
        $this->aviso = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($json['message'] ?? 'No se pudo guardar.'))));

        return null;
    }

    public function cerrar(): void
    {
        $this->registroId = null;
        $this->origen = [];
        $this->filas = ['duplicar' => [], 'dividir' => []];
        $this->aviso = null;
        $this->sugerencias = [];
        $this->resetValidation();
    }

    public function render(): View
    {
        $abierto = $this->registroId !== null;

        return view('livewire.planeacion.programa-tejido.duplicar-dividir', [
            'telaresDe' => fn (?string $clave) => CatalogosDestino::telaresParaClave((string) $clave),
            'aplicaciones' => $abierto ? CatalogosDestino::aplicaciones() : [],
            'cuadre' => $abierto ? $this->cuadre() : null,
            'pendiente' => $abierto ? $this->pendiente() : null,
            'puedeVincular' => $abierto && $this->puedeVincular(),
        ]);
    }
}
