<?php

declare(strict_types=1);

namespace App\Livewire\Desarrolladores;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasMuestrasDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use App\Models\Planeacion\Muestras;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejedores\TejCatMatrizDesarrolladores;
use App\Services\Planeacion\CatalogosMaterialesLMatService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    /** Tick de la franja de confirmacion. Se exige solo si requiereConfirmacion. */
    public bool $confirmado = false;

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
        'AlturaRizo' => '',
    ];

    /** Las 20 casillas de la codificacion, una letra cada una. */
    public array $codificacion = [];

    /** @var array<int, array<string, mixed>> */
    public array $detalles = [];

    public function mount(string $modo = 'programa'): void
    {
        $this->modo = $modo === 'muestras' ? 'muestras' : 'programa';

        abort_unless(userCan('acceso', $this->moduloSysRoles()), 403, 'Sin permiso para este modulo.');

        $this->codificacion = array_fill(0, 20, '');
        // Del usuario en sesion, no de datosIndex(): ese arreglo se memoiza en una clave
        // compartida y el nombre acababa precargado en la pantalla del siguiente operador,
        // que es el que se escribe en el registro de produccion y sale en el Telegram.
        $this->form['Desarrollador'] = (string) (auth()->user()?->nombre ?? '');
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

    /**
     * El catalogo del area, mas el usuario en sesion si no pertenece a ella: captura
     * gente que cubre el turno sin estar dada de alta como desarrollador. Se hace aqui
     * y no en la consulta porque la consulta se cachea para todos los usuarios.
     */
    #[Computed]
    public function desarrolladores()
    {
        $lista = collect($this->datosIndex()['desarrolladores'] ?? []);
        $usuario = auth()->user();

        if ($usuario && ! $lista->contains('idusuario', $usuario->idusuario)) {
            $lista = collect([$usuario])->merge($lista)->sortBy('nombre')->values();
        }

        return $lista;
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

    /** El numero de telar al que se movera la orden, para poder nombrarlo en pantalla. */
    #[Computed]
    public function telarDestinoNombre(): string
    {
        return trim(explode('|', $this->telarDestino, 2)[1] ?? '');
    }

    /**
     * Guardar puede, de un solo toque, finalizar la orden, moverla a otro telar y
     * -en muestras- borrar el registro. Cuando alguna de esas dos cosas pasa, se pide
     * una confirmacion explicita; el guardado corriente sigue siendo un toque.
     */
    #[Computed]
    public function requiereConfirmacion(): bool
    {
        return $this->hayCambioTelar || $this->modo === 'muestras';
    }

    /** Lo que va a ocurrir al guardar, en una frase, para la franja de confirmacion. */
    #[Computed]
    public function resumenGuardado(): array
    {
        $fila = $this->filaSeleccionada;
        $puntos = [];

        $puntos[] = match ($this->accion) {
            'reprogramar_siguiente' => 'Reprogramar la orden '.($fila['NoProduccion'] ?? '').' al siguiente turno',
            'reprogramar_final' => 'Enviar la orden '.($fila['NoProduccion'] ?? '').' al final del programa',
            default => 'Finalizar la orden '.($fila['NoProduccion'] ?? ''),
        };

        if ($this->hayCambioTelar) {
            $puntos[] = 'Mover el registro del telar '.$this->telarId.' al telar '.$this->telarDestinoNombre;
        }

        if ($this->modo === 'muestras') {
            $puntos[] = 'Eliminar la muestra del programa (no se puede deshacer)';
        }

        return $puntos;
    }

    /**
     * Todo lo que impide guardar, en una sola lista: la pantalla la pinta, el boton se
     * deshabilita mientras no este vacia y guardar() la vuelve a revisar. Antes cada
     * regla vivia en un sitio distinto y las que no tenian sitio -un renglon sin
     * pasadas- no avisaban: el servidor descartaba el detalle entero en silencio.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function problemas(): array
    {
        if ($this->filaSeleccionada === null) {
            return ['Selecciona una produccion.'];
        }

        $faltantes = [];

        if (trim((string) $this->form['NumeroJulioRizo']) === '') {
            $faltantes[] = 'Falta elegir el Julio Rizo.';
        }

        foreach (['EficienciaInicio' => 'de inicio', 'EficienciaFinal' => 'final'] as $campo => $cual) {
            if ($this->form[$campo] === null || $this->form[$campo] === '') {
                $faltantes[] = 'Falta la eficiencia '.$cual.'.';
            }
        }

        if (trim((string) $this->form['AlturaRizo']) === '') {
            $faltantes[] = 'Falta la altura de rizo.';
        }

        // Misma regla que el servidor: entre 10 y 20 caracteres, sin el sufijo .JC5.
        $longitud = mb_strlen($this->codificacionModelo);
        if ($longitud < 10 || $longitud > 20) {
            $faltantes[] = 'La codificacion debe tener entre 10 y 20 caracteres (van '.$longitud.').';
        }

        if ($this->detalles === []) {
            $faltantes[] = 'Captura al menos un renglon de detalle.';
        }

        foreach ($this->detalles as $i => $d) {
            $renglon = 'Renglon '.($i + 1).': ';

            if (! empty($d['noVigente'])) {
                $faltantes[] = $renglon.'el calibre '.($d['Calibre'] ?? '').' ya no esta vigente, elige uno de la lista.';
            } elseif (empty($d['CalibreId'])) {
                $faltantes[] = $renglon.'falta elegir el hilo.';
            }

            if ((int) ($d['Pasadas'] ?? 0) < 1) {
                $faltantes[] = $renglon.'falta el numero de pasadas.';
            }
        }

        if ($this->requiereConfirmacion && ! $this->confirmado) {
            $faltantes[] = 'Confirma lo que va a pasar al guardar.';
        }

        return $faltantes;
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
            $this->telarDestinoNombre,
            $this->requiereConfirmacion,
            $this->resumenGuardado,
            $this->problemas,
            $this->catalogosAx,
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
        unset($this->ordenEnProceso, $this->hayCambioTelar, $this->requiereConfirmacion, $this->resumenGuardado);

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
        $this->confirmado = false;
        $this->detalles = [];
        $this->codificacion = array_fill(0, 20, '');
        $this->form['NumeroJulioRizo'] = '';
        $this->form['NumeroJulioPie'] = '';
        $this->form['EficienciaInicio'] = null;
        $this->form['EficienciaFinal'] = null;
        $this->form['HoraInicio'] = '';
        $this->form['HoraFinal'] = '';
        $this->form['DesperdicioTrama'] = 11;
        $this->form['AlturaRizo'] = '';
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
            ->map(function (array $d): array {
                $fila = [
                    'Calibre' => (string) ($d['Calibre'] ?? ''),
                    'Hilo' => (string) ($d['Hilo'] ?? ''),
                    'Fibra' => (string) ($d['Fibra'] ?? ''),
                    'CodColor' => (string) ($d['CodColor'] ?? ''),
                    'NombreColor' => (string) ($d['NombreColor'] ?? ''),
                    'Pasadas' => $d['Pasadas'] ?? '',
                    'slot' => (string) ($d['pasadasField'] ?? ''),
                ];

                return $fila + $this->resolverCalibre($fila['Calibre'], $fila['Hilo']);
            })
            ->values()
            ->all();

        // Una orden puede venir con huecos (C1 y C3 capturadas, C2 vacia). En pantalla
        // se ven como dos renglones seguidos, asi que se guardan como dos seguidos.
        $this->renumerarSlots();
    }

    /**
     * Hilos que el operador puede elegir. Solo los vigentes: un hilo dado de baja no
     * debe poder capturarse de nuevo, aunque siga en ordenes viejas.
     *
     * @return Collection<int, TejCatMatrizDesarrolladores>
     */
    #[Computed]
    public function calibres()
    {
        return Cache::remember(
            'desarrolladores.calibres.vigentes',
            now()->addMinutes(5),
            fn () => TejCatMatrizDesarrolladores::query()->vigentes()->orderBy('Nombre')->get()
        );
    }

    /**
     * Empata el par (Calibre, Hilo) que trae la orden con un hilo del catalogo.
     *
     * Si no casa con ninguno vigente, el renglon queda marcado y la pantalla lo pinta
     * en rojo: puede ser un hilo dado de baja, o uno de los codigos truncados que hay
     * en produccion (450 en vez de 450.1). En los dos casos hay que reelegirlo.
     *
     * @return array{CalibreId: int|null, noVigente: bool}
     */
    private function resolverCalibre(string $calibre, string $hilo): array
    {
        if (TejCatMatrizDesarrolladores::normalizar($calibre) === null) {
            // Renglon sin calibre todavia (una fila recien agregada): no es un error.
            return ['CalibreId' => null, 'noVigente' => false];
        }

        $hilo = TejCatMatrizDesarrolladores::normalizar($hilo);
        $codigo = TejCatMatrizDesarrolladores::normalizar($calibre);

        $candidatos = $this->calibres->filter(
            fn ($c): bool => TejCatMatrizDesarrolladores::normalizar($c->CodigoInterno) === $codigo
        );

        if ($candidatos->isEmpty()) {
            return ['CalibreId' => null, 'noVigente' => true];
        }

        // Con el divisor se desempata; sin el, el primero de ese codigo. Crudo y tenido
        // comparten codigo interno (10/1 y 10/1T son ambos 10.1) y las columnas son
        // float, asi que no hay forma de distinguirlos por el dato guardado.
        $exacto = $candidatos->first(
            fn ($c): bool => TejCatMatrizDesarrolladores::normalizar($c->Divisor) === $hilo
        );

        $elegido = $exacto ?? $candidatos->first();

        return ['CalibreId' => (int) $elegido->Id, 'noVigente' => false];
    }

    /** Un solo select llena las dos columnas, que es lo que impide que se desparejen. */
    public function elegirCalibre(int $indice, $calibreId): void
    {
        if (! isset($this->detalles[$indice])) {
            return;
        }

        $hilo = $this->calibres->firstWhere('Id', (int) $calibreId);

        if (! $hilo) {
            return;
        }

        $articuloAnterior = $this->codigoAx($this->detalles[$indice]);

        $this->detalles[$indice]['CalibreId'] = (int) $hilo->Id;
        $this->detalles[$indice]['Calibre'] = (string) $hilo->CodigoInterno;
        $this->detalles[$indice]['Hilo'] = (string) $hilo->Divisor;
        $this->detalles[$indice]['noVigente'] = false;

        // Fibra y color cuelgan del articulo en AX: los del hilo anterior ya no aplican.
        // Si el articulo no cambio -otro divisor del mismo hilo- no hay por que borrarlos.
        if (trim((string) $hilo->Codigo) !== $articuloAnterior) {
            $this->detalles[$indice]['Fibra'] = '';
            $this->detalles[$indice]['CodColor'] = '';
            $this->detalles[$indice]['NombreColor'] = '';
        }

        unset($this->catalogosAx, $this->problemas);
        $this->resetValidation('detalles.'.$indice.'.CalibreId');
    }

    /**
     * Los calibres del catalogo, uno por valor guardado.
     *
     * El desplegable ofrecia un renglon por presentacion, asi que HILO 10/1, HILO 10/1
     * TENIDO y HILO LYCRA 10/1 salian como tres opciones distintas que escriben
     * exactamente el mismo calibre 10.1: el operador elegia entre tres iguales sin
     * forma de saber cual le tocaba. La presentacion se elige ahora en la columna Hilo,
     * que es donde de verdad se distinguen.
     *
     * @return list<array{Calibre: string, etiqueta: string}>
     */
    #[Computed]
    public function calibresUnificados(): array
    {
        return $this->calibres
            ->groupBy(fn ($h): string => (string) TejCatMatrizDesarrolladores::normalizar($h->CodigoInterno))
            ->map(fn ($grupo): array => [
                'Calibre' => (string) $grupo->first()->CodigoInterno,
                // Con una sola presentacion el nombre no es ambiguo y ayuda a reconocer
                // el hilo; con varias seria mentira, porque el nombre cambia entre ellas.
                'etiqueta' => $grupo->count() === 1
                    ? trim((string) $grupo->first()->CodigoInterno).' — '.trim((string) $grupo->first()->Nombre)
                    : trim((string) $grupo->first()->CodigoInterno),
            ])
            ->sortBy('Calibre', SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * Las presentaciones que el catalogo registra para un calibre.
     *
     * NO se agrupan por divisor: cada una es un articulo distinto de AX --10/1 ofrece
     * un color y 10/1T ofrece nueve, entre ellos TERMOFIJADO-- asi que elegir entre
     * ellas es justo lo que fija el ItemId con el que se piden fibra y color, aunque
     * las tres dividan entre 10.
     *
     * @return list<array{Id: int, Divisor: string, etiqueta: string}>
     */
    public function hilosDelCalibre(string $calibre): array
    {
        $codigo = TejCatMatrizDesarrolladores::normalizar($calibre);

        if ($codigo === null) {
            return [];
        }

        return $this->calibres
            ->filter(fn ($h): bool => TejCatMatrizDesarrolladores::normalizar($h->CodigoInterno) === $codigo)
            ->sortBy([['Divisor', 'asc'], ['Nombre', 'asc']])
            ->map(fn ($h): array => [
                'Id' => (int) $h->Id,
                'Divisor' => (string) $h->Divisor,
                'etiqueta' => $h->Divisor.' — '.trim((string) $h->Nombre).' ('.trim((string) $h->Codigo).')',
            ])
            ->values()
            ->all();
    }

    /**
     * Elegir el calibre. Con una sola presentacion se resuelve sola y el hilo queda
     * puesto; con varias el hilo se deja en blanco a proposito, porque hasta que el
     * operador elija no se sabe que articulo de AX es este renglon --y sin el no hay
     * fibra ni color-. La lista de pendientes lo reclama: "falta elegir el hilo".
     */
    public function elegirCalibreUnificado(int $indice, string $calibre): void
    {
        if (! isset($this->detalles[$indice])) {
            return;
        }

        $presentaciones = $this->hilosDelCalibre($calibre);

        if (count($presentaciones) === 1) {
            $this->elegirCalibre($indice, $presentaciones[0]['Id']);

            return;
        }

        $this->detalles[$indice]['Calibre'] = $calibre;
        $this->detalles[$indice]['CalibreId'] = null;
        $this->detalles[$indice]['Hilo'] = '';
        $this->detalles[$indice]['noVigente'] = false;
        $this->detalles[$indice]['Fibra'] = '';
        $this->detalles[$indice]['CodColor'] = '';
        $this->detalles[$indice]['NombreColor'] = '';

        unset($this->catalogosAx, $this->problemas);
        $this->resetValidation('detalles.'.$indice.'.CalibreId');
    }

    /** El ItemId de AX del hilo elegido en un renglon. Vacio si aun no hay hilo. */
    private function codigoAx(array $detalle): string
    {
        $hilo = $this->calibres->firstWhere('Id', (int) ($detalle['CalibreId'] ?? 0));

        return $hilo ? trim((string) $hilo->Codigo) : '';
    }

    /**
     * Fibra y color no son texto libre: en AX cuelgan del articulo del hilo, igual que
     * en L.Mat. ConfigId es la fibra, InventColor da el codigo y el nombre del color.
     *
     * Se piden de una sola vez para todos los hilos del detalle --tres consultas a AX
     * por render, no tres por renglon-- reusando el servicio que ya alimenta a L.Mat.
     * Si AX no responde se devuelve vacio: la captura sigue, esos campos son nullable.
     *
     * @return array<string, array{configs: list<string>, colores: list<array{InventColorId: string, Name: string}>}>
     */
    #[Computed]
    public function catalogosAx(): array
    {
        $items = collect($this->detalles)
            ->map(fn (array $d): string => $this->codigoAx($d))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($items === []) {
            return [];
        }

        try {
            return Cache::remember(
                'desarrolladores.ax.'.md5(implode('|', $items)),
                now()->addMinutes(30),
                fn (): array => app(CatalogosMaterialesLMatService::class)->obtener($items)
            );
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Las opciones de un renglon. El valor que ya trae la orden se incluye aunque no
     * este en AX: las capturas viejas son texto libre (272 variantes de fibra, con
     * dedazos) y no se puede dejar sin opcion seleccionada lo que ya esta guardado.
     *
     * Se marca ademas si el valor guardado quedo fuera de AX, para pintarlo. No bloquea
     * el guardado como si lo hace el calibre: las capturas viejas de esta columna son
     * texto libre con 272 variantes, y exigirlas todas dejaria el piso sin poder cerrar
     * una sola orden. Se ve, se corrige al vuelo, pero no detiene el turno.
     *
     * @return array{fibras: list<string>, colores: list<array{InventColorId: string, Name: string}>, fibraFuera: bool, colorFuera: bool}
     */
    public function opcionesFila(array $detalle): array
    {
        $catalogo = $this->catalogosAx[$this->codigoAx($detalle)] ?? ['configs' => [], 'colores' => []];

        $fibras = collect($catalogo['configs'] ?? []);
        $fibraActual = trim((string) ($detalle['Fibra'] ?? ''));
        $fibraFuera = $fibraActual !== '' && ! $fibras->contains($fibraActual);
        if ($fibraFuera) {
            $fibras = $fibras->prepend($fibraActual);
        }

        $colores = collect($catalogo['colores'] ?? []);
        $codigoActual = trim((string) ($detalle['CodColor'] ?? ''));
        $colorFuera = $codigoActual !== '' && ! $colores->contains('InventColorId', $codigoActual);
        if ($colorFuera) {
            $colores = $colores->prepend([
                'InventColorId' => $codigoActual,
                'Name' => trim((string) ($detalle['NombreColor'] ?? '')),
            ]);
        }

        return [
            'fibras' => $fibras->values()->all(),
            'colores' => $colores->values()->all(),
            'fibraFuera' => $fibraFuera,
            'colorFuera' => $colorFuera,
        ];
    }

    /** El nombre del color no se captura: viene con el codigo, del mismo renglon de AX. */
    public function elegirColor(int $indice, string $codColor): void
    {
        if (! isset($this->detalles[$indice])) {
            return;
        }

        $color = collect($this->opcionesFila($this->detalles[$indice])['colores'])
            ->firstWhere('InventColorId', $codColor);

        $this->detalles[$indice]['CodColor'] = $codColor;
        $this->detalles[$indice]['NombreColor'] = $codColor === '' ? '' : (string) ($color['Name'] ?? '');
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

        // La columna es de texto en SQL Server: llega tal como se capturo.
        $this->form['AlturaRizo'] = trim((string) (data_get($registro, 'AlturaRizo') ?? ''));
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

    /** Los renglones de combinacion, que son los unicos que se agregan y se quitan. */
    private function indicesCombinacion(): array
    {
        return array_keys(array_filter(
            $this->detalles,
            fn (array $d): bool => ! str_contains((string) ($d['slot'] ?? ''), 'Trama')
        ));
    }

    /**
     * Los combos ocupan PasadasComb1..N en el orden en que se ven, sin huecos: al
     * quitar C3, C4 pasa a ser C3 y C5 a C4. Antes cada renglon se quedaba con el slot
     * con el que nacio, asi que borrar C3 dejaba C1, C2, C4, C5 y la orden guardaba un
     * hueco en medio. La trama conserva el suyo: no es una combinacion.
     */
    private function renumerarSlots(): void
    {
        $n = 0;
        foreach ($this->indicesCombinacion() as $i) {
            $this->detalles[$i]['slot'] = 'PasadasComb'.(++$n);
        }
    }

    public function agregarFila(): void
    {
        $combos = count($this->indicesCombinacion());

        if ($combos >= 5) {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Solo se pueden capturar 5 combinaciones.');

            return;
        }

        $this->detalles[] = [
            'Calibre' => '', 'Hilo' => '', 'Fibra' => '', 'CodColor' => '',
            'NombreColor' => '', 'Pasadas' => '', 'slot' => 'PasadasComb'.($combos + 1),
            'CalibreId' => null, 'noVigente' => false,
        ];
        unset($this->problemas);
    }

    public function eliminarFila(int $indice): void
    {
        // La trama no se elimina: es el renglon base de la orden, no una combinacion.
        if (str_contains((string) ($this->detalles[$indice]['slot'] ?? ''), 'Trama')) {
            return;
        }

        unset($this->detalles[$indice]);
        $this->detalles = array_values($this->detalles);
        $this->renumerarSlots();
        unset($this->problemas);
    }

    public function guardar(): void
    {
        abort_unless(userCan('acceso', $this->moduloSysRoles()), 403);

        $fila = $this->filaSeleccionada;

        // El boton ya viene deshabilitado con esta misma lista, pero la peticion se
        // puede mandar igual (Enter, dos pestanas, un cliente manipulado): la lista
        // manda tambien aqui, no solo en pantalla.
        if ($this->problemas !== [] || ! $fila) {
            $this->dispatch('aviso', tipo: 'error', texto: $this->problemas[0] ?? 'Selecciona una produccion.');

            return;
        }

        // Deshabilitar el boton mientras viaja tapa el caso normal, pero no el de verdad:
        // dos clicks muy seguidos, o dos pestanas, mandan dos peticiones que el servidor
        // atiende igual. El candado es lo unico que garantiza una sola escritura, porque
        // vive fuera de la peticion. Se suelta siempre en el finally.
        $candado = Cache::lock(
            'desarrolladores:guardar:'.$this->modo.':'.$this->telarId.':'.$this->produccionSeleccionada,
            15
        );

        if (! $candado->get()) {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Ese guardado ya se esta procesando.');

            return;
        }

        try {
            $this->guardarConServicio($fila);
        } finally {
            $candado->release();
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function guardarConServicio(array $fila): void
    {
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
            $this->addError(
                $this->clavePantalla((string) $campo),
                is_array($mensajes) ? (string) reset($mensajes) : (string) $mensajes
            );
        }

        $this->dispatch('aviso', tipo: 'error', texto: $datos['message'] ?? 'No fue posible guardar.');
    }

    /**
     * Traduce la clave que devuelve el servidor a la propiedad que la pantalla pinta.
     *
     * Antes se prefijaba todo con 'form.', asi que los rechazos por TelarDestino, por
     * pasadas[] o por cualquiera de los detalle_* caian en claves que ninguna ranura
     *
     * @error mostraba: el operador veia un toast rojo generico al final de la captura
     * y no habia forma de saber que campo fallo.
     */
    private function clavePantalla(string $campo): string
    {
        if ($campo === 'TelarDestino') {
            return 'telarDestino';
        }

        // pasadas.PasadasComb2 -> la fila del detalle que ocupa ese slot
        if (str_starts_with($campo, 'pasadas.')) {
            $slot = substr($campo, strlen('pasadas.'));
            $indice = $this->indiceDeSlot($slot);

            return $indice === null ? 'guardar' : "detalles.{$indice}.Pasadas";
        }

        // detalle_codcolor.3 -> detalles.3.CodColor
        if (str_starts_with($campo, 'detalle_')) {
            [$nombre, $indice] = array_pad(explode('.', $campo, 2), 2, null);
            $columna = self::COLUMNAS_DETALLE[$nombre] ?? null;

            if ($columna !== null && $indice !== null && ctype_digit((string) $indice)) {
                return "detalles.{$indice}.{$columna}";
            }

            return 'guardar';
        }

        // Sin campo propio en pantalla: se pinta en el resumen de arriba.
        if (in_array($campo, ['NoProduccion', 'NoTelarId', 'registroId', 'accion'], true)) {
            return 'guardar';
        }

        return 'form.'.$campo;
    }

    /** @var array<string, string> */
    private const COLUMNAS_DETALLE = [
        'detalle_calibre' => 'Calibre',
        'detalle_hilo' => 'Hilo',
        'detalle_fibra' => 'Fibra',
        'detalle_codcolor' => 'CodColor',
        'detalle_nombrecolor' => 'NombreColor',
    ];

    private function indiceDeSlot(string $slot): ?int
    {
        foreach ($this->detalles as $i => $detalle) {
            if (($detalle['slot'] ?? '') === $slot) {
                return (int) $i;
            }
        }

        return null;
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
            'AlturaRizo' => $this->form['AlturaRizo'] !== '' ? $this->form['AlturaRizo'] : null,
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
