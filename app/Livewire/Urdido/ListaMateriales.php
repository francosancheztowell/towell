<?php

declare(strict_types=1);

namespace App\Livewire\Urdido;

use App\Livewire\Concerns\ConCrud;
use App\Livewire\Concerns\ConTabla;
use App\Models\Urdido\Urdbom;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\ProgramaUrdEng\BomMaterialesService;
use App\Services\Urdido\CumpKardexService;
use App\Support\FiltroAx;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Lista de materiales de urdido (Urdbom): tabla + alta, edición y borrado. */
class ListaMateriales extends Component
{
    use ConCrud;
    use ConTabla;

    public const MODULO = 205;

    public const MODELO = Urdbom::class;

    public const CAMPOS = ['Folio', 'Lmat', 'Calibre', 'Config', 'Color', 'Cantidad', 'Porcentaje', 'cump', 'importe'];

    /** Modal "Crear desde urdido" abierto. */
    public bool $importando = false;

    /** @var array{desde?: string, hasta?: string, folio?: string} */
    public array $importar = [];

    /** Modal "Calcular costos" abierto. */
    public bool $calculandoCostos = false;

    /** @var array{porFecha: bool, anterior: bool, existencia: bool} mes del kardex (el de producción o el último anterior) y respaldo EXISTENCIACU */
    public array $costos = ['porFecha' => true, 'anterior' => true, 'existencia' => true];

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso a la lista de materiales de urdido.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        $numero = fn (string $campo, int $dec = 2) => fn (Urdbom $f) => $f->{$campo} === null ? '' : number_format((float) $f->{$campo}, $dec);

        // La tabla va agrupada por folio: solo se ordena por lo que existe en el renglón del grupo.
        // `Materiales` solo lo trae el renglón del grupo (COUNT); en un material es null.
        return [
            ['campo' => 'Folio', 'titulo' => 'Folio', 'filtro' => FiltroAx::folio('Folio'), 'filtroAyuda' => '1-20, 35, 001*'],
            ['campo' => 'Lmat', 'titulo' => 'No. Lmat', 'filtro' => true],
            ['campo' => 'Calibre', 'titulo' => 'Calibre', 'filtro' => true, 'orden' => false,
                'valor' => fn (Urdbom $f) => $f->Materiales !== null ? $f->Materiales.' material'.($f->Materiales == 1 ? '' : 'es') : $f->Calibre],
            ['campo' => 'Config', 'titulo' => 'Config', 'filtro' => true, 'orden' => false, 'clase' => 'hidden sm:table-cell'],
            ['campo' => 'Color', 'titulo' => 'Color', 'filtro' => true, 'orden' => false, 'clase' => 'hidden sm:table-cell'],
            ['campo' => 'Cantidad', 'titulo' => 'Cantidad', 'alinear' => 'end', 'orden' => false, 'valor' => $numero('Cantidad')],
            ['campo' => 'Porcentaje', 'titulo' => '%', 'alinear' => 'end', 'orden' => false, 'valor' => $numero('Porcentaje')],
            // cump es costo unitario: en el renglón del grupo no se suma (null), importe sí.
            ['campo' => 'cump', 'titulo' => 'Cump', 'alinear' => 'end', 'orden' => false, 'clase' => 'col-acento', 'valor' => $numero('cump', 4)],
            ['campo' => 'importe', 'titulo' => 'Importe', 'alinear' => 'end', 'orden' => false, 'clase' => 'col-acento', 'valor' => $numero('importe')],
        ];
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        // Límites = los de las columnas de dbo.Urdbom (varchar y decimal(18,2)).
        $datos = $this->validate([
            'form.Folio' => ['required', 'string', 'max:20'],
            'form.Lmat' => ['required', 'string', 'max:100'],
            'form.Calibre' => ['nullable', 'string', 'max:50'],
            'form.Config' => ['nullable', 'string', 'max:50'],
            'form.Color' => ['nullable', 'string', 'max:50'],
            'form.Cantidad' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'form.Porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'form.cump' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'form.importe' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], attributes: [
            'form.Folio' => 'folio',
            'form.Lmat' => 'No. Lmat',
            'form.Cantidad' => 'cantidad',
            'form.Porcentaje' => 'porcentaje',
        ])['form'];

        $datos = $this->vaciosANull($datos);
        // cump/importe son NOT NULL DEFAULT 0 en dbo.Urdbom.
        $datos['cump'] ??= 0;
        $datos['importe'] ??= 0;

        if ($esAlta) {
            $this->seleccionado = (string) Urdbom::create($datos)->getKey();
        } else {
            $this->buscar((string) $this->editando)->update($datos);
        }

        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Material agregado.' : 'Material actualizado.');
    }

    public function abrirImportar(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->resetValidation();
        $this->importar = ['desde' => '', 'hasta' => '', 'folio' => ''];
        $this->importando = true;
    }

    /**
     * Crea filas de Urdbom desde las órdenes de UrdProgramaUrdido (filtradas por FechaProg y/o folio):
     * Lmat = BomId de la orden; Calibre/Config/Color/Cantidad = líneas de AX BOM con ese BOMID;
     * Porcentaje = parte de la cantidad dentro del BOM. Los folios que ya están en Urdbom se saltan.
     */
    public function importarDesdeUrdido(BomMaterialesService $ax, CumpKardexService $cump): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $f = $this->validate([
            'importar.desde' => ['nullable', 'date'],
            'importar.hasta' => ['nullable', 'date', 'after_or_equal:importar.desde'],
            'importar.folio' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['importar.desde' => 'desde', 'importar.hasta' => 'hasta'])['importar'];

        $ordenes = UrdProgramaUrdido::query()
            ->whereNotNull('BomId')->where('BomId', '<>', '')
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->where('FechaProg', '>=', $d))
            ->when($f['hasta'] ?? null, fn ($q, $d) => $q->where('FechaProg', '<=', $d))
            ->when(trim((string) ($f['folio'] ?? '')), fn ($q, $folio) => FiltroAx::folio('Folio')($q, explode(',', $folio)))
            ->whereNotIn('Folio', Urdbom::query()->select('Folio')->whereNotNull('Folio'))
            ->get(['Folio', 'BomId']);

        $bom = $ax->lineasBom($ordenes->pluck('BomId')->map(fn ($b) => trim($b))->unique()->values()->all());

        $filas = [];
        $sinBom = 0;
        foreach ($ordenes->unique('Folio') as $orden) {
            $lineas = $bom->get(trim($orden->BomId));
            if ($lineas === null) {
                $sinBom++;

                continue;
            }
            $total = (float) $lineas->sum(fn ($l) => (float) $l->BOMQTY);
            foreach ($lineas as $l) {
                $filas[] = [
                    'Folio' => $orden->Folio,
                    'Lmat' => trim($orden->BomId),
                    'Calibre' => trim((string) $l->ITEMID),
                    'Config' => trim((string) $l->CONFIGID),
                    'Color' => trim((string) $l->INVENTCOLORID),
                    'Cantidad' => round((float) $l->BOMQTY, 2),
                    'Porcentaje' => $total > 0 ? round((float) $l->BOMQTY / $total * 100, 2) : null,
                ];
            }
        }

        // 7 columnas × 250 filas < 2100 parámetros de SQL Server.
        DB::connection('sqlsrv')->transaction(function () use ($filas) {
            foreach (array_chunk($filas, 250) as $lote) {
                Urdbom::insert($lote);
            }
        });
        $cump->actualizar(array_values(array_unique(array_column($filas, 'Folio'))));

        $this->importando = false;
        $folios = $ordenes->unique('Folio')->count() - $sinBom;
        $texto = $folios === 0 && $sinBom === 0
            ? 'No hay órdenes nuevas en ese rango.'
            : "Se crearon {$folios} folio(s), ".count($filas).' material(es).'.($sinBom ? " {$sinBom} sin BOM en AX." : '');
        $this->dispatch('aviso', tipo: $folios > 0 ? 'success' : 'warning', texto: $texto);
    }

    public function abrirCostos(): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $this->costos = ['porFecha' => true, 'anterior' => true, 'existencia' => true];
        $this->calculandoCostos = true;
    }

    /** Recalcula cump, importe de Urdbom e ImporteMP de los julios de producción (ver CumpKardexService). */
    public function calcularCump(CumpKardexService $cump): void
    {
        abort_unless(userCan('modificar', self::MODULO), 403);

        $n = $cump->actualizar(null, (bool) $this->costos['porFecha'], (bool) $this->costos['anterior'], (bool) $this->costos['existencia']);
        $this->calculandoCostos = false;
        $texto = $n === ['materiales' => 0, 'produccion' => 0] ? 'Los costos ya estaban al día.'
            : "Se actualizaron {$n['materiales']} material(es) y {$n['produccion']} julio(s) de producción.";
        $this->dispatch('aviso', tipo: 'success', texto: $texto);
    }

    public function render(): View
    {
        // Filtros y búsqueda se aplican a los materiales; luego se agrupa por folio (un renglón por folio,
        // que se despliega con sus materiales). Los hijos llevan los mismos filtros: cuadran con el conteo.
        $materiales = fn () => $this->aplicarTabla(Urdbom::query(), ['Folio', 'Lmat', 'Calibre', 'Config', 'Color']);

        $grupos = $materiales()
            ->select('Folio')
            ->selectRaw('MAX(Lmat) AS Lmat, COUNT(*) AS Materiales, SUM(Cantidad) AS Cantidad, SUM(Porcentaje) AS Porcentaje, SUM(importe) AS importe')
            ->groupBy('Folio')
            ->when($this->ordenPor === '', fn ($q) => $q->orderBy('Folio'));
        $grupos = $this->paginar($grupos);

        $hijos = $materiales()
            ->whereIn('Folio', collect($grupos->items())->pluck('Folio')->all())
            ->orderBy('Folio')->orderBy('Calibre')
            ->get()
            ->groupBy('Folio');

        return view('livewire.urdido.lista-materiales', [
            'filas' => $grupos,
            'hijos' => $hijos,
            'puede' => $this->permisos(),
        ]);
    }
}
