<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

/**
 * Autocompletado de Clave modelo y Flog en las filas de DuplicarDividir: al escribir se sugieren
 * hasta 10 opciones; al elegir o salir del campo se confirma (la clave se busca en Modelos del
 * salón, el flog trae su proyecto). Sin JS: la lista la pinta Livewire.
 */
trait AutocompletaClaveFlog
{
    /** Autocompletado abierto: ['campo' => clave|flog, 'i' => fila, 'items' => list<string>]. */
    public array $sugerencias = [];

    /** Vincular usa las mismas filas que duplicar. */
    private function llave(): string
    {
        return $this->modo === 'dividir' ? 'dividir' : 'duplicar';
    }

    private function sugerir(string $campo, int $i, string $texto): void
    {
        $items = $campo === 'clave' ? CatalogosDestino::buscarClaves($texto) : CatalogosDestino::buscarFlogs($texto);
        // Si lo escrito ya es la única opción, no hay nada que sugerir.
        $this->sugerencias = $items === [] || $items === [trim($texto)] ? [] : ['campo' => $campo, 'i' => $i, 'items' => $items];
    }

    public function elegir(string $campo, int $i, string $valor): void
    {
        $llave = $this->llave();
        if (! isset($this->filas[$llave][$i]) || ! in_array($campo, ['clave', 'flog'], true)) {
            return;
        }
        $this->sugerencias = [];
        $this->filas[$llave][$i][$campo] = $valor;
        $this->confirmar($campo, $i);
    }

    /** El select de telar manda "SALON|TELAR": fija los dos; si cambia el salón, la clave se recarga para él. */
    public function elegirTelar(int $i, string $valor): void
    {
        $llave = $this->llave();
        [$salon, $telar] = array_pad(explode('|', $valor, 2), 2, '');
        $valido = in_array($valor, array_column(CatalogosDestino::telaresParaClave((string) ($this->filas[$llave][$i]['clave'] ?? '')), 'valor'), true);
        if (! isset($this->filas[$llave][$i]) || ! $valido) {
            return;
        }
        $cambiaSalon = $this->filas[$llave][$i]['salon'] !== $salon;
        $this->filas[$llave][$i]['salon'] = $salon;
        $this->filas[$llave][$i]['telar'] = $telar;
        if ($cambiaSalon) {
            $this->cargarClave($llave, $i, $telar);
        }
    }

    /** Al salir del campo (o al elegir): la clave se busca en Modelos; el flog trae su proyecto. */
    public function confirmar(string $campo, int $i): void
    {
        $this->sugerencias = [];
        $llave = $this->llave();
        if (! isset($this->filas[$llave][$i])) {
            return;
        }
        if ($campo === 'clave') {
            $this->cargarClave($llave, $i);
        }
        $flog = trim((string) $this->filas[$llave][$i]['flog']);
        $proyecto = $campo === 'flog' ? (CatalogosDestino::flogs()[$flog] ?? '') : '';
        if ($proyecto !== '') {
            $this->filas[$llave][$i]['descripcion'] = $proyecto;
        }
    }
}
