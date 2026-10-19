<?php

declare(strict_types=1);

namespace App\Livewire\Urdido;

use App\Livewire\Concerns\ConCrud;
use App\Livewire\Concerns\ConTabla;
use App\Models\UrdEngomado\UrdEngCalMaquina;
use App\Models\Urdido\URDCatalogoMaquina;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Calendario de máquinas de urdido (UrdEngCalMaquinas): minutos disponibles por máquina y turno.
 * Tabla filtrada por máquina y mes, alta/edición/borrado de un turno y "Generar mes" desde una plantilla.
 *
 * @property-read array<int, string> $maquinas
 */
class CalendarioMaquinas extends Component
{
    use ConCrud {
        abrirAlta as protected abrirAltaCrud;
        abrirEdicion as protected abrirEdicionCrud;
    }
    use ConTabla;

    public const MODULO = 211;

    public const MODELO = UrdEngCalMaquina::class;

    public const CAMPOS = ['Maquina', 'FechaInicio', 'Minutos', 'Turno'];

    public const DEPARTAMENTO = 'Urdido';

    /** Turnos (cuadrillas): el 4 cubre descansos según UrdEngCalMaquina::ROTACION_URDIDO. */
    public const TURNOS = [1, 2, 3, 4];

    public const NOMBRES_VENTANA = [1 => 'Mañana', 2 => 'Tarde', 3 => 'Noche'];

    /** URDCatalogoMaquinas.Id; '' = todas. */
    #[Url(except: '')]
    public string $maquinaFiltro = '';

    /** 'Y-m'; '' = todos los meses. */
    #[Url(except: '')]
    public string $mes = '';

    /** Formulario de "Generar mes"; null = modal cerrado. */
    public ?array $generar = null;

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso al calendario de máquinas.');

        // Un mes de una máquina son ~90 turnos: 25 por página obliga a paginar siempre.
        if ($this->porPagina === 25) {
            $this->porPagina = 100;
        }
        if ($this->mes === '') {
            $this->mes = now()->format('Y-m');
        }
    }

    /** @return array<int, string> Id => nombre de las máquinas del departamento. */
    #[Computed]
    public function maquinas(): array
    {
        return URDCatalogoMaquina::query()
            ->where('Departamento', self::DEPARTAMENTO)
            ->orderBy('MaquinaId')
            ->get(['Id', 'MaquinaId', 'Nombre'])
            ->mapWithKeys(fn (URDCatalogoMaquina $m): array => [$m->Id => $m->Nombre ?: $m->MaquinaId])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columnas(): array
    {
        $maquinas = $this->maquinas;

        return [
            ['campo' => 'Maquina', 'titulo' => 'Máquina', 'valor' => fn (UrdEngCalMaquina $f) => $maquinas[$f->Maquina] ?? $f->Maquina],
            ['campo' => 'FechaInicio', 'titulo' => 'Día', 'valor' => fn (UrdEngCalMaquina $f) => ucfirst($f->FechaInicio->locale('es')->isoFormat('ddd DD/MM/YYYY'))],
            ['campo' => 'Turno', 'titulo' => 'Turno', 'alinear' => 'center'],
            ['campo' => '', 'titulo' => 'Inicio', 'alinear' => 'center', 'valor' => fn (UrdEngCalMaquina $f) => $f->FechaInicio->format('H:i')],
            ['campo' => '', 'titulo' => 'Fin', 'alinear' => 'center', 'valor' => fn (UrdEngCalMaquina $f) => $this->textoFin($f)],
            ['campo' => 'Minutos', 'titulo' => 'Minutos', 'alinear' => 'end'],
        ];
    }

    public function updatedMaquinaFiltro(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedMes(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function abrirAlta(): void
    {
        $this->abrirAltaCrud();
        $this->form = ['Maquina' => $this->maquinaFiltro, 'FechaInicio' => '', 'Minutos' => '480', 'Turno' => '1'];
    }

    public function abrirEdicion(?string $id = null): void
    {
        $this->abrirEdicionCrud($id);
        if ($this->editando !== null) {
            // <input type="datetime-local"> pide 'Y-m-d\TH:i'.
            $this->form['FechaInicio'] = CarbonImmutable::parse($this->form['FechaInicio'])->format('Y-m-d\TH:i');
        }
    }

    /** Inicio en una ventana de reloj (06:30 / 14:30 / 22:30): el turno lo dice la rotación. */
    public function updatedFormFechaInicio(string $valor): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valor) !== 1) {
            return;
        }
        $inicio = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $valor);
        $ventana = array_search($inicio->format('H:i'), UrdEngCalMaquina::VENTANAS, true);
        if ($ventana !== false) {
            $this->form['Turno'] = (string) UrdEngCalMaquina::ROTACION_URDIDO[$inicio->dayOfWeekIso][$ventana];
        }
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        $datos = $this->validate([
            'form.Maquina' => ['required', Rule::in(array_keys($this->maquinas))],
            'form.FechaInicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'form.Minutos' => ['required', 'integer', 'min:1', 'max:'.UrdEngCalMaquina::MAX_MINUTOS],
            'form.Turno' => ['required', Rule::in(self::TURNOS)],
        ], attributes: [
            'form.Maquina' => 'máquina',
            'form.FechaInicio' => 'inicio',
            'form.Minutos' => 'minutos',
            'form.Turno' => 'turno',
        ])['form'];

        $datos = [
            'Maquina' => (int) $datos['Maquina'],
            'FechaInicio' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $datos['FechaInicio']),
            'Minutos' => (int) $datos['Minutos'],
            'Turno' => (int) $datos['Turno'],
        ];

        if (UrdEngCalMaquina::seEncima($datos['Maquina'], $datos['FechaInicio'], $datos['Minutos'], $esAlta ? null : (int) $this->editando)) {
            $this->addError('form.FechaInicio', 'Se encima con otro turno de la máquina.');

            return;
        }

        $fila = $esAlta ? UrdEngCalMaquina::create($datos) : tap($this->buscar((string) $this->editando))->update($datos);

        $this->seleccionado = (string) $fila->getKey();
        $this->editando = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Turno agregado.' : 'Turno actualizado.');
    }

    public function abrirGenerar(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->resetValidation();
        $this->generar = [
            'Maquina' => $this->maquinaFiltro,
            'mes' => $this->mes ?: now()->format('Y-m'),
            // 1 = lunes … 7 = domingo; con la rotación se trabaja toda la semana.
            'dias' => ['1', '2', '3', '4', '5', '6', '7'],
            'ventanas' => collect(UrdEngCalMaquina::VENTANAS)
                ->map(fn (string $hora): array => ['activo' => true, 'hora' => $hora, 'minutos' => '480'])
                ->all(),
        ];
    }

    public function cerrarGenerar(): void
    {
        $this->generar = null;
        $this->resetValidation();
    }

    /** Crea los turnos del mes que no se encimen con los que ya hay; los demás se omiten. */
    public function generarMes(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $g = $this->validate([
            'generar.Maquina' => ['required', Rule::in(array_keys($this->maquinas))],
            'generar.mes' => ['required', 'date_format:Y-m'],
            'generar.dias' => ['required', 'array', 'min:1'],
            'generar.dias.*' => [Rule::in(['1', '2', '3', '4', '5', '6', '7'])],
            'generar.ventanas.*.hora' => ['required', 'date_format:H:i'],
            'generar.ventanas.*.minutos' => ['required', 'integer', 'min:1', 'max:'.UrdEngCalMaquina::MAX_MINUTOS],
        ], attributes: [
            'generar.Maquina' => 'máquina',
            'generar.mes' => 'mes',
            'generar.dias' => 'días',
            'generar.ventanas.*.hora' => 'hora de inicio',
            'generar.ventanas.*.minutos' => 'minutos',
        ])['generar'];

        $ventanas = collect($this->generar['ventanas'])
            ->filter(fn (array $v): bool => (bool) $v['activo'])
            ->map(fn (array $v, int|string $ventana): array => ['ventana' => (int) $ventana, 'hora' => $v['hora'], 'minutos' => (int) $v['minutos']])
            ->values()
            ->all();

        if ($ventanas === []) {
            $this->addError('generar.ventanas', 'Marca al menos un horario.');

            return;
        }

        $maquina = (int) $g['Maquina'];
        $filas = UrdEngCalMaquina::plantillaMes($g['mes'], $ventanas, array_map('intval', $g['dias']));

        $creados = DB::connection('sqlsrv')->transaction(function () use ($filas, $maquina): int {
            $creados = 0;
            foreach ($filas as $fila) {
                if (! UrdEngCalMaquina::seEncima($maquina, $fila['FechaInicio'], $fila['Minutos'])) {
                    UrdEngCalMaquina::create($fila + ['Maquina' => $maquina]);
                    $creados++;
                }
            }

            return $creados;
        });

        $omitidos = count($filas) - $creados;
        $this->maquinaFiltro = (string) $maquina;
        $this->mes = $g['mes'];
        $this->generar = null;
        $this->resetPage();
        $this->dispatch('aviso', tipo: 'success', texto: "Se agregaron {$creados} turnos."
            .($omitidos > 0 ? " {$omitidos} se omitieron porque se enciman con turnos que ya estaban." : ''));
    }

    public function render(): View
    {
        $filas = $this->aplicarTabla(UrdEngCalMaquina::query(), [])
            ->when($this->maquinaFiltro !== '', fn ($q) => $q->where('Maquina', (int) $this->maquinaFiltro))
            ->when($this->mesValido(), function ($q) {
                $inicio = CarbonImmutable::createFromFormat('!Y-m', $this->mes);
                $q->where('FechaInicio', '>=', $inicio)->where('FechaInicio', '<', $inicio->addMonth());
            })
            ->when($this->ordenPor === '', fn ($q) => $q->orderBy('FechaInicio')->orderBy('Maquina'));

        return view('livewire.urdido.calendario-maquinas', [
            'filas' => $this->paginar($filas),
            'puede' => $this->permisos(),
        ]);
    }

    private function mesValido(): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes) === 1;
    }

    /** Hora de fin; con el día si cae en otro (turno 3). */
    private function textoFin(UrdEngCalMaquina $fila): string
    {
        $fin = $fila->fin();

        return $fin->isSameDay($fila->FechaInicio) ? $fin->format('H:i') : $fin->format('H:i (d/m)');
    }
}
