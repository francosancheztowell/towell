<?php

declare(strict_types=1);

namespace App\Livewire\Tejedores;

use App\Livewire\Concerns\ConTabla;
use App\Models\Tejedores\TejCatMatrizDesarrolladores;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catalogo de calibres de la captura de desarrolladores.
 *
 * Hasta ahora la tabla solo se poblaba por migracion, asi que corregir un divisor
 * equivocado -o dar de baja un hilo- exigia entrar a SQL Server. Un divisor mal
 * puesto no falla ni avisa: L.Mat calcula peso 0 para esa trama y el rizo absorbe
 * la diferencia, de modo que el dato tiene que poder mantenerlo Planeacion.
 *
 * No hay borrado fisico a proposito: las ordenes viejas siguen apuntando a estos
 * codigos y perder el renglon dejaria su calibre sin nombre. Dar de baja es poner
 * Vigente en 0, que lo saca de los desplegables sin romper el historico.
 */
class CatalogoCalibres extends Component
{
    use ConTabla;

    public const MODULO = 'Catalogo Calibres';

    /** '', 'vigentes' o 'baja'. Por defecto se ven todos. */
    #[Url(except: '')]
    public string $vigenciaFiltro = '';

    /** Id en edicion; '' = alta nueva; null = modal cerrado. */
    public ?string $editando = null;

    public bool $confirmandoBaja = false;

    /** @var array<string, string> */
    public array $form = [
        'Codigo' => '',
        'CodigoInterno' => '',
        'Divisor' => '',
        'Nombre' => '',
        'Vigente' => '1',
    ];

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso al catalogo de calibres.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        return [
            ['campo' => 'Codigo', 'titulo' => 'Codigo AX'],
            ['campo' => 'Nombre', 'titulo' => 'Nombre'],
            ['campo' => 'CodigoInterno', 'titulo' => 'Calibre'],
            ['campo' => 'Divisor', 'titulo' => 'Hilo (divisor)'],
            [
                'campo' => 'Vigente',
                'titulo' => 'Vigente',
                'valor' => fn ($fila): string => $fila->Vigente ? 'Si' : 'No',
            ],
        ];
    }

    public function updatedVigenciaFiltro(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->vigenciaFiltro = '';
        $this->buscar = '';
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function abrirAlta(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->resetValidation();
        $this->form = ['Codigo' => '', 'CodigoInterno' => '', 'Divisor' => '', 'Nombre' => '', 'Vigente' => '1'];
        $this->editando = '';
    }

    public function abrirEdicion(?string $id = null): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $id ??= $this->seleccionado;
        if ($id === null) {
            return;
        }

        $hilo = TejCatMatrizDesarrolladores::findOrFail($id);

        $this->resetValidation();
        $this->form = [
            'Codigo' => (string) $hilo->Codigo,
            'CodigoInterno' => (string) $hilo->CodigoInterno,
            // El casteo a float deja 8.8599999999999994 en pantalla: se recorta a lo
            // que de verdad se capturo, dos decimales, como en el resto del modulo.
            'Divisor' => rtrim(rtrim(number_format((float) $hilo->Divisor, 2, '.', ''), '0'), '.'),
            'Nombre' => (string) $hilo->Nombre,
            'Vigente' => $hilo->Vigente ? '1' : '0',
        ];
        $this->seleccionado = (string) $id;
        $this->editando = (string) $id;
    }

    public function cerrar(): void
    {
        $this->editando = null;
        $this->confirmandoBaja = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        // El codigo es el ItemId de AX y con el se piden fibra y color: se guarda tal
        // cual lo escribe AX, sin espacios y en mayusculas, o el catalogo no empata.
        $this->form['Codigo'] = mb_strtoupper(trim($this->form['Codigo']));
        $this->form['CodigoInterno'] = trim($this->form['CodigoInterno']);
        $this->form['Nombre'] = trim($this->form['Nombre']);

        $datos = $this->validate([
            'form.Codigo' => [
                'required', 'string', 'max:20',
                Rule::unique('sqlsrv.TejCatMatrizDesarrolladores', 'Codigo')
                    ->ignore($esAlta ? null : $this->editando, 'Id'),
            ],
            'form.CodigoInterno' => ['required', 'string', 'max:20'],
            // Divisor 0 no es un dato, es una division por cero esperando: L.Mat lo
            // usa como denominador del peso.
            'form.Divisor' => ['required', 'numeric', 'gt:0'],
            'form.Nombre' => ['required', 'string', 'max:60'],
            'form.Vigente' => ['required', 'in:0,1'],
        ], messages: [
            // La app corre con locale 'en' y no tiene traducciones: los mensajes que
            // lee Planeacion se escriben aqui, en el idioma de la pantalla.
            'form.Codigo.required' => 'Escribe el codigo AX del hilo.',
            'form.Codigo.unique' => 'Ese codigo AX ya esta en el catalogo.',
            'form.Codigo.max' => 'El codigo AX no puede pasar de 20 caracteres.',
            'form.CodigoInterno.required' => 'Escribe el calibre que se guarda en la orden.',
            'form.Divisor.required' => 'Escribe el divisor del hilo.',
            'form.Divisor.numeric' => 'El divisor tiene que ser un numero.',
            'form.Divisor.gt' => 'El divisor tiene que ser mayor que cero: es el denominador con el que L.Mat calcula el peso.',
            'form.Nombre.required' => 'Escribe el nombre que vera el operador.',
            'form.Nombre.max' => 'El nombre no puede pasar de 60 caracteres.',
        ], attributes: [
            'form.Codigo' => 'codigo AX',
            'form.CodigoInterno' => 'calibre',
            'form.Divisor' => 'hilo (divisor)',
            'form.Nombre' => 'nombre',
            'form.Vigente' => 'vigencia',
        ])['form'];

        $atributos = [
            'Codigo' => $datos['Codigo'],
            'CodigoInterno' => $datos['CodigoInterno'],
            'Divisor' => (float) $datos['Divisor'],
            'Nombre' => $datos['Nombre'],
            'Vigente' => $datos['Vigente'] === '1',
        ];

        if ($esAlta) {
            $this->seleccionado = (string) TejCatMatrizDesarrolladores::create($atributos)->getKey();
        } else {
            TejCatMatrizDesarrolladores::findOrFail($this->editando)->update($atributos);
        }

        $this->olvidarCacheDeCaptura();

        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Calibre creado.' : 'Calibre actualizado.');
    }

    /** Fila seleccionada, para poder nombrarla en la franja de confirmacion. */
    public function filaSeleccionada(): ?TejCatMatrizDesarrolladores
    {
        return $this->seleccionado === null
            ? null
            : TejCatMatrizDesarrolladores::find($this->seleccionado);
    }

    /**
     * Dar de baja saca el calibre de los desplegables de captura, y las ordenes que ya
     * lo traian quedan marcadas en rojo y sin poder guardarse hasta que se reelija. Es
     * reversible pero se siente en el piso, asi que se confirma.
     */
    public function confirmarBaja(): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        if ($this->seleccionado !== null) {
            $this->confirmandoBaja = true;
        }
    }

    public function alternarVigencia(): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $hilo = $this->filaSeleccionada();

        if ($hilo) {
            $hilo->update(['Vigente' => ! $hilo->Vigente]);
            $this->olvidarCacheDeCaptura();
            $this->dispatch(
                'aviso',
                tipo: 'success',
                texto: $hilo->Vigente ? 'Calibre puesto en vigente.' : 'Calibre dado de baja.'
            );
        }

        $this->confirmandoBaja = false;
    }

    /**
     * La captura memoiza los calibres vigentes cinco minutos. Sin soltar esa llave, un
     * alta no aparece -y una baja sigue eligiendose- hasta que el cache expire.
     */
    private function olvidarCacheDeCaptura(): void
    {
        Cache::forget('desarrolladores.calibres.vigentes');
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla(
            TejCatMatrizDesarrolladores::query()
                ->when($this->vigenciaFiltro === 'vigentes', fn ($q) => $q->where('Vigente', 1))
                ->when($this->vigenciaFiltro === 'baja', fn ($q) => $q->where('Vigente', 0)),
            ['Codigo', 'CodigoInterno', 'Nombre'],
        );

        // Sin orden elegido se lee por nombre, que es como sale en el desplegable.
        if ($this->ordenPor === '') {
            $filas->orderBy('Nombre');
        }

        return view('livewire.tejedores.catalogo-calibres', [
            'filas' => $this->paginar($filas),
            'seleccion' => $this->filaSeleccionada(),
            'puedeModificar' => userCan('modificar', self::MODULO),
        ]);
    }
}
