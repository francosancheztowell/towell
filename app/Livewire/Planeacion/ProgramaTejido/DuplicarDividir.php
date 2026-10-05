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
 * - Dividir reparte SOLO el saldo: Σ saldos == saldo disponible (±0.5) o no se guarda.
 * - Guardar valida con las reglas del FormRequest y llama a DividirTejido/DuplicarTejido sin
 *   HTTP. Al éxito despacha 'pt-duplicar-guardado' con el mismo JSON que daba el endpoint.
 */
class DuplicarDividir extends Component
{
    use AutocompletaClaveFlog;

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
            'duplicar' => [FilasDestino::filaDuplicar($this->origen)],
            'dividir' => FilasDestino::filasDividir($grupo, $this->origen),
        ];
        $this->modo = $this->esGrupo ? 'dividir' : 'duplicar';
        $this->registroId = $id;
    }

    public function agregarFila(): void
    {
        $llave = $this->llave();
        $this->filas[$llave][] = $llave === 'duplicar' ? FilasDestino::filaDuplicar($this->origen) : FilasDestino::fila($this->origen);
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
        return $this->esGrupo || ($this->origen['conNoProduccion'] ?? false);
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

        match ($campo) {
            // Regla 3 (duplicar, sin producción): saldo = pedido × (1 + %seg/100).
            'pedido', 'porcSeg' => $modo === 'duplicar'
                ? $this->filas[$modo][$i]['saldo'] = FilasDestino::saldoDuplicar($fila['pedido'], $fila['porcSeg'])
                : null,
            // Otro salón: el telar ya no vale y la clave se vuelve a buscar en ese salón.
            'salon' => $this->cargarClave($modo, $i, telar: ''),
            // Mientras escribe solo se sugiere; la clave se valida al salir del campo o al elegir.
            'clave', 'flog' => $this->sugerir($campo, $i, (string) $valor),
            default => null,
        };
    }

    /** La clave debe existir en Modelos para el salón de la fila; trae producto, flog y descripción. */
    private function cargarClave(string $modo, int $i, ?string $telar = null): void
    {
        $campo = "filas.$modo.$i.clave";
        $this->resetErrorBag($campo);
        $this->filas[$modo][$i]['telar'] = $telar ?? $this->filas[$modo][$i]['telar'];
        $fila = $this->filas[$modo][$i];
        $clave = trim((string) $fila['clave']);
        // Sin columna Salón: si la clave no está en el salón de la fila pero sí en otro, la fila se
        // mueve a ese salón y el telar se vuelve a elegir (el select solo lista salones de la clave).
        $salones = CatalogosDestino::salonesDeClave($clave);
        if ($salones !== [] && ! in_array($fila['salon'], $salones, true)) {
            [$fila['salon'], $fila['telar']] = [$salones[0], ''];
        }
        $datos = $clave === '' ? [] : CatalogosDestino::datosClave($clave, (string) $fila['salon']);
        if ($datos === null) {
            $this->addError($campo, "La clave {$clave} no existe en Modelos.");

            return;
        }

        $datos['producto'] = ($datos['producto'] ?? '') ?: $fila['producto'];
        $this->filas[$modo][$i] = array_merge($fila, $datos);
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
        if ($this->registroId === null) {
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
     * @return array<string, mixed>|null el JSON del endpoint, o null con $aviso puesto
     */
    private function ejecutar(bool $dividir, bool $vincular): ?array
    {
        $payload = $dividir
            ? FilasDestino::payloadDividir($this->origen, $this->filas['dividir'], (int) $this->registroId, $this->esGrupo)
            : FilasDestino::payloadDuplicar($this->origen, $this->filas['duplicar'], (int) $this->registroId, $vincular);
        $respuesta = FilasDestino::correr($dividir, $payload);
        $json = (array) $respuesta->getData(true);
        if ($respuesta->getStatusCode() < 400 && ! empty($json['success'])) {
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
            'puedeVincular' => $abierto && $this->puedeVincular(),
        ]);
    }
}
