<?php

declare(strict_types=1);

namespace App\Livewire\Costos;

use App\Livewire\Concerns\ConTabla;
use App\Models\Costos\CosCuota;
use App\Models\Costos\CosCuotasReal;
use App\Models\Costos\CosCuotasSTD;
use App\Support\Costos\EsquemaCuota;
use App\Support\FiltroAx;
use App\Support\PaginacionCompat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Cuotas de costos: las dos tablas (Real y STD) en una pantalla, una pestaña cada una.
 * Misma tabla, búsqueda y CRUD; la pestaña elige el modelo. La llave de una cuota es
 * (Depto, Año, Mes): ver App\Models\Costos\CosCuota.
 */
class Cuotas extends Component
{
    use ConTabla;

    public const MODULO = 'Cuotas';

    /** @var array<string, class-string<CosCuota>> */
    public const TABLAS = [
        'real' => CosCuotasReal::class,
        'std' => CosCuotasSTD::class,
    ];

    /** Siempre abre en la estándar (primera pestaña); no va en la URL para que un enlace viejo no abra la real. */
    public string $tabla = 'std';

    /** Año elegido en las pills junto a las pestañas; '' = todos. String: es el valor del radio. */
    #[Url(except: '')]
    public string $anio = '';

    /** Método de costeo: 'absorbente' toma todas las columnas; 'directo' deja fuera las fijas. */
    #[Url(except: 'absorbente')]
    public string $costeo = 'absorbente';

    /** Llave en edición; '' = alta nueva; null = diálogo cerrado. */
    public ?string $editando = null;

    /** El alta abierta es un duplicado de otra fila (solo cambia el título). */
    public bool $duplicando = false;

    /** Llave de la cuota que ya existe con el Depto/Año/Mes del formulario; el diálogo ofrece reemplazarla. */
    public ?string $conflicto = null;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a las cuotas de costos.');

        if (! isset(self::TABLAS[$this->tabla])) {
            $this->tabla = 'std';
        }
    }

    /**
     * Al cambiar de pestaña se conservan los filtros (año, Depto/Año/Mes, costeo): las dos tablas
     * tienen esas columnas y se comparan con el mismo filtro. La selección sí era de la otra tabla.
     * Un año que la otra tabla no tiene lo limpia render().
     */
    public function updatedTabla(): void
    {
        if (! isset(self::TABLAS[$this->tabla])) {
            $this->tabla = 'std';
        }

        // Un orden por una columna que esta tabla no tiene (MOI en STD) lo ignora ConTabla.
        $this->editando = null;
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedCosteo(): void
    {
        // Si la columna ordenada se ocultó (directo), ConTabla deja de ordenar por ella.
        $this->costeo = $this->costeo === 'directo' ? 'directo' : 'absorbente';
    }

    public function updatedAnio(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    /**
     * Años de la tabla con cuántas cuotas tiene cada uno, para los badges.
     *
     * @return array<int, int> año => filas
     */
    private function anios(): array
    {
        return $this->modelo()::conteoPorAnio();
    }

    /** @return class-string<CosCuota> */
    private function modelo(): string
    {
        return self::TABLAS[$this->tabla] ?? CosCuotasSTD::class;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        $columnas = [
            // Filtros como en AX (comas = varios valores; rangos con .., >, <, ! para excluir).
            ['campo' => 'Depto', 'titulo' => 'Depto', 'filtro' => true, 'filtroAyuda' => 'Urdido, Engomado', 'alinear' => 'center'],
            [
                'campo' => 'Año', 'titulo' => 'Año', 'filtro' => FiltroAx::numero('Año'), 'filtroAyuda' => '2026, 2027 · 2026..2027',
                'clase' => 'tabular-nums', 'alinear' => 'center',
            ],
            // Se ve el nombre; se ordena por el número guardado, así que va de Enero a Diciembre.
            // El filtro acepta nombre o número: "enero, febrero", "ene..mar", "!dic".
            [
                'campo' => 'Mes', 'titulo' => 'Mes', 'filtro' => FiltroAx::mes('Mes'), 'filtroAyuda' => 'enero, febrero · ene..mar',
                'alinear' => 'center',
                'valor' => fn ($fila): ?string => CosCuota::MESES[$fila->Mes] ?? ($fila->Mes === null ? null : (string) $fila->Mes),
            ],
        ];

        $campos = $this->modelo()::columnasValor();
        if ($this->costeo === 'directo') {
            $campos = array_diff($campos, CosCuota::FIJOS);
        }

        foreach ($campos as $campo) {
            $columnas[] = [
                'campo' => $campo,
                'titulo' => CosCuota::ETIQUETAS[$campo],
                // Las "Sab" van en morado (col-acento, app.css) para distinguirlas de un vistazo.
                'clase' => 'tabular-nums whitespace-nowrap'.(str_starts_with($campo, 'Sab') ? ' col-acento' : ''),
                'alinear' => 'center',
                'valor' => fn ($fila): ?string => $fila->{$campo} === null
                    ? null
                    : number_format((float) $fila->{$campo}, in_array($campo, EsquemaCuota::MINUTOS, true) ? 0 : 4),
            ];
        }

        return $columnas;
    }

    public function abrirAlta(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->resetValidation();
        $this->conflicto = null;
        $this->form = array_fill_keys([...CosCuota::LLAVE, ...$this->modelo()::columnasValor()], '');
        $this->form['Año'] = (string) now()->year;
        $this->form['Mes'] = (string) now()->month;
        $this->duplicando = false;
        $this->editando = '';
    }

    /**
     * Alta con los valores de la fila seleccionada, propuesta para el mes siguiente
     * (diciembre → enero del año siguiente) para que la llave no choque con la original.
     */
    public function duplicar(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $cuota = $this->seleccionado === null ? null : $this->modelo()::clave($this->seleccionado)->first();
        if ($cuota === null) {
            return;
        }

        $this->resetValidation();
        $this->conflicto = null;
        $this->llenarForm($cuota);
        [$this->form['Año'], $this->form['Mes']] = array_map('strval', $cuota->mesSiguiente());
        $this->duplicando = true;
        $this->editando = '';
    }

    public function abrirEdicion(?string $id = null): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $id ??= $this->seleccionado;
        $cuota = $id === null ? null : $this->modelo()::clave($id)->first();
        if ($cuota === null) {
            return;
        }

        $this->resetValidation();
        $this->conflicto = null;
        $this->llenarForm($cuota);
        $this->seleccionado = $id;
        $this->duplicando = false;
        $this->editando = $id;
    }

    private function llenarForm(CosCuota $cuota): void
    {
        $this->form = ['Depto' => (string) $cuota->Depto, 'Año' => (string) $cuota->Año, 'Mes' => (string) $cuota->Mes];
        foreach ($this->modelo()::columnasValor() as $campo) {
            // Sin ceros de relleno: 16.6667 y no 16.66670000; vacío si es NULL.
            $this->form[$campo] = $cuota->{$campo} === null ? '' : rtrim(rtrim((string) $cuota->{$campo}, '0'), '.');
        }
    }

    public function cerrar(): void
    {
        $this->editando = null;
        $this->duplicando = false;
        $this->conflicto = null;
        $this->resetValidation();
    }

    /**
     * Guarda el formulario. Si Depto + Año + Mes ya existen en otra fila no guarda una segunda
     * línea: deja $conflicto con esa fila y el diálogo ofrece "Reemplazar" (reemplazar()).
     */
    public function guardar(bool $reemplazar = false): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        $valores = $this->validarForm();
        $modelo = $this->modelo();
        $existente = $modelo::existente($valores['Depto'], $valores['Año'], $valores['Mes']);
        $chocaConOtra = $existente !== null && ($esAlta || $existente->getKey() !== $this->editando);

        if ($chocaConOtra && ! $reemplazar) {
            $this->conflicto = $existente->getKey();

            return;
        }

        $cuota = $modelo::guardarCuota($valores, $esAlta ? null : $this->editando, $chocaConOtra ? $existente : null);

        $this->seleccionado = $cuota->getKey();
        $this->editando = null;
        $this->conflicto = null;
        $this->dispatch('aviso', tipo: 'success', texto: match (true) {
            $chocaConOtra => 'Cuota reemplazada.',
            $this->duplicando => 'Cuota duplicada.',
            $esAlta => 'Cuota creada.',
            default => 'Cuota actualizada.',
        });
        $this->duplicando = false;
    }

    /** "Reemplazar" del aviso de llave repetida: sobrescribe (edita) la cuota que ya existe. */
    public function reemplazar(): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $this->guardar(reemplazar: true);
    }

    /** Cambiar Depto, Año o Mes después del aviso lo invalida: se revisa de nuevo al guardar. */
    public function updatedForm(mixed $valor, string $campo): void
    {
        if (in_array($campo, CosCuota::LLAVE, true)) {
            $this->conflicto = null;
        }
    }

    /**
     * Valida el formulario con el esquema de una cuota (App\Support\Costos\EsquemaCuota) y
     * devuelve los valores listos para guardar.
     *
     * @return array<string, mixed>
     */
    private function validarForm(): array
    {
        $columnas = $this->modelo()::columnasValor();
        $this->form = EsquemaCuota::normalizar($this->form);

        $datos = $this->validate(
            EsquemaCuota::reglas($columnas, $this->form),
            EsquemaCuota::mensajes(),
            EsquemaCuota::atributos($columnas),
        )['form'];

        $valores = EsquemaCuota::valores($datos, $columnas);
        if (EsquemaCuota::vacia($valores)) {
            throw ValidationException::withMessages(['form.Minutos' => 'Captura al menos un valor: una cuota vacía no sirve.']);
        }

        return $valores;
    }

    public function eliminar(): void
    {
        abort_unless(userCan('eliminar', self::MODULO), 403);

        $cuota = $this->seleccionado === null ? null : $this->modelo()::clave($this->seleccionado)->first();
        if ($cuota === null) {
            return;
        }

        $cuota->delete();
        $this->seleccionado = null;
        $this->dispatch('aviso', tipo: 'success', texto: 'Cuota eliminada.');
    }

    /**
     * Sin paginación (la tabla no muestra pie): una sola página con todo.
     * ponytail: tope de 1000 filas (~40 deptos × 2 años × 12 meses); si crece, volver a paginar.
     */
    protected function paginar(Builder $query): LengthAwarePaginator
    {
        return PaginacionCompat::paginar($query, 1000, 1);
    }

    public function render(): View
    {
        $modelo = $this->modelo();
        $anios = $this->anios();
        if ($this->anio !== '' && ! isset($anios[(int) $this->anio])) {
            $this->anio = ''; // año de la URL que esta tabla no tiene
        }

        // Sin buscador general: se filtra por año (badges) y por columna desde el botón Filtrar.
        $filas = $this->aplicarTabla(
            $modelo::query()->when($this->anio !== '', fn ($q) => $q->where('Año', (int) $this->anio)),
            [],
        );

        // Sin orden elegido: como se capturan, por año y mes.
        if ($this->ordenPor === '') {
            $filas->orderBy('Año')->orderBy('Mes')->orderBy('Depto');
        }

        return view('livewire.costos.cuotas', [
            'filas' => $this->paginar($filas),
            'campos' => $modelo::columnasValor(),
            'anios' => $anios,
            'puede' => [
                'crear' => userCan('crear', self::MODULO),
                'modificar' => userCan('modificar', self::MODULO),
                'eliminar' => userCan('eliminar', self::MODULO),
            ],
        ]);
    }
}
