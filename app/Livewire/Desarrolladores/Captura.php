<?php

declare(strict_types=1);

namespace App\Livewire\Desarrolladores;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasMuestrasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use App\Models\Planeacion\Muestras;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Captura de desarrollador. Una sola pantalla para los dos modos: el programa de
 * tejido y las muestras, que hasta ahora eran dos vistas y dos archivos de JS con
 * 669 lineas identicas entre si (y la de muestras con las columnas descuadradas).
 *
 * El guardado no reimplementa nada: arma la peticion y se la pasa al servicio que
 * ya existia, con toda su validacion y su transaccion.
 */
class Captura extends Component
{
    /** 'programa' | 'muestras' */
    public string $modo = 'programa';

    // ── Seleccion ─────────────────────────────────────────────────────────
    public string $telarId = '';

    public ?int $produccionSeleccionada = null;

    public string $telarDestino = '';

    public string $accion = 'finalizar';

    // ── Formulario ────────────────────────────────────────────────────────
    /** @var array<string, mixed> */
    public array $form = [
        'NumeroJulioRizo' => '',
        'NumeroJulioPie' => '',
        'EficienciaInicio' => null,
        'EficienciaFinal' => null,
        'Desarrollador' => '',
        'HoraInicio' => '',
        'HoraFinal' => '',
        'DesperdicioTrama' => 11,
    ];

    /** Las 20 casillas de la codificacion, una letra cada una. */
    public array $codificacion = [];

    /** @var array<int, array<string, mixed>> */
    public array $detalles = [];

    public bool $mostrarReprogramar = false;

    public function mount(string $modo = 'programa'): void
    {
        $this->modo = $modo === 'muestras' ? 'muestras' : 'programa';

        abort_unless(userCan('acceso', $this->moduloSysRoles()), 403, 'Sin permiso para este modulo.');

        $this->codificacion = array_fill(0, 20, '');
        $this->form['Desarrollador'] = (string) ($this->datosIndex()['desarrolladorActual'] ?? '');
    }

    /** Nombre del modulo en SYSRoles segun el modo. */
    private function moduloSysRoles(): string
    {
        return $this->modo === 'muestras' ? 'Desarrolladores Muestras' : 'Desarrolladores';
    }

    /** @return class-string<ReqProgramaTejido> */
    private function modeloPrograma(): string
    {
        return $this->modo === 'muestras' ? Muestras::class : ReqProgramaTejido::class;
    }

    private function consultas(): ConsultasDesarrolladorService
    {
        return $this->modo === 'muestras'
            ? app(ConsultasMuestrasDesarrolladorService::class)
            : app(ConsultasDesarrolladorService::class);
    }

    /**
     * Catalogos de la pantalla. Cada interaccion de Livewire es una peticion nueva,
     * y estas consultas no dependen de lo que el usuario esta capturando, asi que se
     * memoizan en cache: obtenerJuliosPorTipo recorre AtaMontadoTelas entera y era la
     * consulta mas cara del modulo, ejecutada dos veces por carga.
     *
     * @return array<string, mixed>
     */
    private function datosIndex(): array
    {
        return Cache::remember(
            'desarrolladores.index.'.$this->modo,
            now()->addMinutes(5),
            fn (): array => $this->consultas()->obtenerDatosIndex()
        );
    }

    // ── Datos derivados ───────────────────────────────────────────────────

    #[Computed]
    public function telares()
    {
        return $this->datosIndex()['telares'] ?? collect();
    }

    #[Computed]
    public function telaresDestino()
    {
        return $this->datosIndex()['telaresDestino'] ?? collect();
    }

    #[Computed]
    public function desarrolladores()
    {
        return $this->datosIndex()['desarrolladores'] ?? collect();
    }

    #[Computed]
    public function juliosRizo()
    {
        return $this->julios['juliosRizo'] ?? collect();
    }

    #[Computed]
    public function juliosPie()
    {
        return $this->julios['juliosPie'] ?? collect();
    }

    /**
     * Rizo y pie salen de la MISMA consulta, asi que se memoiza aqui y no en cada uno.
     * Sin esto, obtenerJuliosPorTelar() corria dos veces --una por cada propiedad-- y
     * como internamente pide los dos tipos, eran cuatro consultas para traer dos listas.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function julios(): array
    {
        if ($this->telarId === '') {
            return ['juliosRizo' => collect(), 'juliosPie' => collect()];
        }

        $resultado = $this->consultas()->obtenerJuliosPorTelar($this->telarId);

        return $resultado['success'] ? $resultado : ['juliosRizo' => collect(), 'juliosPie' => collect()];
    }

    #[Computed]
    public function producciones()
    {
        if ($this->telarId === '') {
            return collect();
        }

        $resultado = $this->consultas()->obtenerProducciones($this->telarId);

        return $resultado['success'] ? collect($resultado['producciones']) : collect();
    }

    /**
     * La orden que el telar tiene en curso. Antes salian dos fetch en paralelo sin
     * abortar, asi que al cambiar de telar rapido un banner viejo podia pisar al nuevo.
     * Como propiedad calculada se resuelve una sola vez por render.
     */
    #[Computed]
    public function ordenEnProceso(): ?array
    {
        $telar = $this->telarDelBanner();

        if ($telar === '') {
            return null;
        }

        $modelo = $this->modeloPrograma();
        $orden = $modelo::query()
            ->where('NoTelarId', $telar)
            ->where('EnProceso', 1)
            ->select('NoProduccion', 'NombreProducto', 'FechaInicio')
            ->first();

        if (! $orden) {
            return ['telar' => $telar, 'noProduccion' => 'Sin orden', 'nombre' => '-', 'fecha' => '-'];
        }

        return [
            'telar' => $telar,
            'noProduccion' => (string) $orden->NoProduccion,
            'nombre' => (string) ($orden->NombreProducto ?? '-'),
            'fecha' => $orden->FechaInicio ? $orden->FechaInicio->format('d/m/Y') : '-',
        ];
    }

    /** Con cambio de telar el banner mira al destino; si no, al telar actual. */
    private function telarDelBanner(): string
    {
        if ($this->telarDestino !== '' && str_contains($this->telarDestino, '|')) {
            return trim(explode('|', $this->telarDestino, 2)[1] ?? '');
        }

        return $this->telarId;
    }

    #[Computed]
    public function filaSeleccionada(): ?array
    {
        if ($this->produccionSeleccionada === null) {
            return null;
        }

        $fila = $this->producciones->firstWhere('Id', $this->produccionSeleccionada);

        if (! $fila) {
            return null;
        }

        // (array) sobre un modelo Eloquent no devuelve los atributos, sino las
        // propiedades protegidas con claves ilegibles.
        $datos = is_object($fila) && method_exists($fila, 'toArray') ? $fila->toArray() : (array) $fila;

        return $datos + [
            'NoProduccion' => '', 'SalonTejidoId' => '', 'TamanoClave' => '',
            'NombreProducto' => '', 'FechaInicio' => null,
        ];
    }

    /**
     * El total no se captura: es la suma de las pasadas del detalle.
     */
    #[Computed]
    public function totalPasadas(): int
    {
        return (int) collect($this->detalles)->sum(fn (array $d): int => (int) ($d['Pasadas'] ?? 0));
    }

    #[Computed]
    public function codificacionModelo(): string
    {
        return strtoupper(trim(implode('', $this->codificacion)));
    }

    #[Computed]
    public function hayCambioTelar(): bool
    {
        $fila = $this->filaSeleccionada;

        if (! $fila || $this->telarDestino === '') {
            return false;
        }

        return $this->telarDestino !== ($fila['SalonTejidoId'] ?? '').'|'.$this->telarId;
    }

    // ── Acciones ──────────────────────────────────────────────────────────

    /**
     * Livewire cachea las propiedades calculadas durante toda la peticion. Cuando cambia
     * el telar o la fila elegida hay que soltar ese cache, o la pantalla seguiria
     * pintando los datos de la seleccion anterior.
     */
    private function olvidarDerivados(): void
    {
        unset(
            $this->producciones,
            $this->ordenEnProceso,
            $this->julios,
            $this->juliosRizo,
            $this->juliosPie,
            $this->filaSeleccionada,
            $this->hayCambioTelar,
        );
    }

    public function updatedTelarId(): void
    {
        $this->reset(['produccionSeleccionada', 'telarDestino', 'accion']);
        $this->accion = 'finalizar';
        $this->limpiarCaptura();
        $this->olvidarDerivados();
    }

    public function seleccionar(int $id): void
    {
        if ($this->produccionSeleccionada === $id) {
            $this->cancelar();

            return;
        }

        $this->produccionSeleccionada = $id;
        $this->accion = 'finalizar';
        $this->limpiarCaptura();
        $this->olvidarDerivados();

        $fila = $this->filaSeleccionada;

        if (! $fila) {
            return;
        }

        // Por defecto el destino es el propio telar: seleccionar no es cambiar de telar.
        $this->telarDestino = ($fila['SalonTejidoId'] ?? '').'|'.$this->telarId;
        unset($this->ordenEnProceso, $this->hayCambioTelar);

        $this->cargarDetalles((string) ($fila['NoProduccion'] ?? ''), $id);
        $this->precargarDesdeCatCodificados((string) ($fila['NoProduccion'] ?? ''));
        $this->precargarCodigoDibujo((string) ($fila['SalonTejidoId'] ?? ''), (string) ($fila['TamanoClave'] ?? ''));
    }

    public function cancelar(): void
    {
        $this->produccionSeleccionada = null;
        $this->telarDestino = '';
        $this->accion = 'finalizar';
        $this->limpiarCaptura();
        $this->olvidarDerivados();
        $this->resetValidation();
    }

    private function limpiarCaptura(): void
    {
        $this->detalles = [];
        $this->codificacion = array_fill(0, 20, '');
        $this->form['NumeroJulioRizo'] = '';
        $this->form['NumeroJulioPie'] = '';
        $this->form['EficienciaInicio'] = null;
        $this->form['EficienciaFinal'] = null;
        $this->form['HoraInicio'] = '';
        $this->form['HoraFinal'] = '';
        $this->form['DesperdicioTrama'] = 11;
    }

    private function cargarDetalles(string $noProduccion, int $registroId): void
    {
        $resultado = $noProduccion !== ''
            ? $this->consultas()->obtenerDetallesOrden($noProduccion)
            : $this->consultas()->obtenerDetallesOrdenPorId($registroId);

        if (! ($resultado['success'] ?? false)) {
            $this->detalles = [];

            return;
        }

        $this->detalles = collect($resultado['detalles'] ?? [])
            ->map(fn (array $d): array => [
                'Calibre' => (string) ($d['Calibre'] ?? ''),
                'Hilo' => (string) ($d['Hilo'] ?? ''),
                'Fibra' => (string) ($d['Fibra'] ?? ''),
                'CodColor' => (string) ($d['CodColor'] ?? ''),
                'NombreColor' => (string) ($d['NombreColor'] ?? ''),
                'Pasadas' => $d['Pasadas'] ?? '',
                'slot' => (string) ($d['pasadasField'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function precargarDesdeCatCodificados(string $noProduccion): void
    {
        if ($noProduccion === '' || $this->telarId === '') {
            return;
        }

        $resultado = $this->consultas()->obtenerRegistroCatCodificado($this->telarId, $noProduccion);

        if (! ($resultado['success'] ?? false)) {
            return;
        }

        $registro = $resultado['registro'] ?? [];

        $this->form['NumeroJulioRizo'] = (string) (data_get($registro, 'JulioRizo') ?? '');
        $this->form['NumeroJulioPie'] = (string) (data_get($registro, 'JulioPie') ?? '');
        $this->form['EficienciaInicio'] = data_get($registro, 'EfiInicial');
        $this->form['EficienciaFinal'] = data_get($registro, 'EfiFinal');

        $desperdicio = data_get($registro, 'DesperdicioTrama');
        if ($desperdicio !== null && $desperdicio !== '') {
            $this->form['DesperdicioTrama'] = $desperdicio;
        }
    }

    private function precargarCodigoDibujo(string $salon, string $tamano): void
    {
        if ($salon === '' || $tamano === '') {
            return;
        }

        $resultado = $this->consultas()->obtenerCodigoDibujo($salon, $tamano);

        if (! ($resultado['success'] ?? false)) {
            return;
        }

        $codigo = (string) ($resultado['codigoDibujo'] ?? '');
        // El sufijo .JC5 se pinta aparte, no ocupa casillas.
        $codigo = (string) preg_replace('/\.(?:JC5|JCS)$/i', '', strtoupper(trim($codigo)));

        $letras = str_split(substr($codigo, 0, 20));
        $this->codificacion = array_pad($letras, 20, '');
    }

    /** Cada combinacion ocupa un slot PasadasComb1..5; la trama tiene el suyo. */
    public function agregarFila(): void
    {
        $usados = collect($this->detalles)->pluck('slot')->filter()->all();

        $slot = collect(range(1, 5))
            ->map(fn (int $i): string => "PasadasComb{$i}")
            ->reject(fn (string $s): bool => in_array($s, $usados, true))
            ->first();

        if (! $slot) {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Solo se pueden capturar 5 combinaciones.');

            return;
        }

        $this->detalles[] = [
            'Calibre' => '', 'Hilo' => '', 'Fibra' => '', 'CodColor' => '',
            'NombreColor' => '', 'Pasadas' => '', 'slot' => $slot,
        ];
    }

    public function eliminarFila(int $indice): void
    {
        unset($this->detalles[$indice]);
        $this->detalles = array_values($this->detalles);
    }

    public function guardar(): void
    {
        abort_unless(userCan('acceso', $this->moduloSysRoles()), 403);

        $fila = $this->filaSeleccionada;

        if (! $fila) {
            $this->dispatch('aviso', tipo: 'error', texto: 'Selecciona una produccion.');

            return;
        }

        // ponytail: se reutiliza el store que ya existe en vez de reimplementar 800
        // lineas de validacion, transaccion y movimiento. Se pide en modo ajax para
        // recibir JSON y poder mapear los errores al formulario.
        $peticion = Request::create(
            $this->modo === 'muestras' ? '/desarrolladores-muestras' : '/desarrolladores',
            'POST',
            $this->cargaUtil($fila),
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );
        $peticion->setUserResolver(fn () => auth()->user());

        $respuesta = $this->modo === 'muestras'
            ? app(ProcesarMuestrasDesarrolladorService::class)->store($peticion)
            : app(ProcesarDesarrolladorService::class)->store($peticion);

        $datos = method_exists($respuesta, 'getData') ? (array) $respuesta->getData(true) : [];

        if (($datos['success'] ?? false) === true) {
            $this->cancelar();
            $this->olvidarDerivados();
            $this->dispatch('aviso', tipo: 'success', texto: $datos['message'] ?? 'Datos guardados correctamente');

            return;
        }

        foreach (($datos['errors'] ?? []) as $campo => $mensajes) {
            $this->addError('form.'.$campo, is_array($mensajes) ? (string) reset($mensajes) : (string) $mensajes);
        }

        $this->dispatch('aviso', tipo: 'error', texto: $datos['message'] ?? 'No fue posible guardar.');
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function cargaUtil(array $fila): array
    {
        $pasadas = [];
        foreach ($this->detalles as $detalle) {
            $slot = (string) ($detalle['slot'] ?? '');
            if ($slot !== '' && $detalle['Pasadas'] !== '' && $detalle['Pasadas'] !== null) {
                $pasadas[$slot] = (int) $detalle['Pasadas'];
            }
        }

        return [
            'NoTelarId' => $this->telarId,
            'NoProduccion' => (string) ($fila['NoProduccion'] ?? ''),
            'registroId' => $this->produccionSeleccionada,
            'accion' => $this->accion,
            'CambioTelarActivo' => $this->hayCambioTelar ? '1' : '0',
            'TelarDestino' => $this->hayCambioTelar ? $this->telarDestino : '',
            'NumeroJulioRizo' => $this->form['NumeroJulioRizo'],
            'NumeroJulioPie' => $this->form['NumeroJulioPie'],
            'TotalPasadasDibujo' => $this->totalPasadas,
            'HoraInicio' => $this->form['HoraInicio'] ?: null,
            'HoraFinal' => $this->form['HoraFinal'] ?: null,
            'EficienciaInicio' => $this->form['EficienciaInicio'],
            'EficienciaFinal' => $this->form['EficienciaFinal'],
            'Desarrollador' => $this->form['Desarrollador'],
            'DesperdicioTrama' => $this->form['DesperdicioTrama'],
            'CodificacionModelo' => $this->codificacionModelo,
            'pasadas' => $pasadas,
            'detalle_calibre' => array_column($this->detalles, 'Calibre'),
            'detalle_hilo' => array_column($this->detalles, 'Hilo'),
            'detalle_fibra' => array_column($this->detalles, 'Fibra'),
            'detalle_codcolor' => array_column($this->detalles, 'CodColor'),
            'detalle_nombrecolor' => array_column($this->detalles, 'NombreColor'),
        ];
    }

    public function render(): View
    {
        return view('livewire.desarrolladores.captura');
    }
}
