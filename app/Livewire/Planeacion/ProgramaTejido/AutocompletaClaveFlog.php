<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

/**
 * Autocompletado de Clave modelo y Flog en las filas de DuplicarDividir: al escribir se sugieren
 * opciones (Flog también al enfocar); al elegir o cambiar el campo se confirma (la clave se busca
 * en Modelos del salón, el flog trae su proyecto) y se valida. La lista la pinta Livewire.
 */
trait AutocompletaClaveFlog
{
    /** Autocompletado abierto: ['campo' => clave|flog, 'i' => fila, 'items' => list<{valor, detalle}>]. */
    public array $sugerencias = [];

    /** Vincular usa las mismas filas que duplicar. */
    private function llave(): string
    {
        return $this->modo === 'dividir' ? 'dividir' : 'duplicar';
    }

    /** Al enfocar Flog la lista sale sin escribir: los flogs vigentes, primero los del artículo de la fila. */
    public function abrirSugerencias(string $campo, int $i): void
    {
        if (isset($this->filas[$this->llave()][$i]) && in_array($campo, ['clave', 'flog'], true)) {
            $this->sugerir($campo, $i, '');
        }
    }

    /** Opciones ['valor', 'detalle']: la clave con su talla y producto; el flog solo. */
    private function sugerir(string $campo, int $i, string $texto): void
    {
        $fila = $this->filas[$this->llave()][$i];
        $items = $campo === 'clave'
            ? CatalogosDestino::buscarClaves($texto, (string) $fila['itemId'])
            : array_map(fn (string $flog) => ['valor' => $flog, 'detalle' => ''],
                CatalogosDestino::buscarFlogs($texto, CatalogosDestino::flogsDelArticulo((string) $fila['itemId'], (string) $fila['inventSizeId'])));
        // Si lo escrito ya es la única opción, no hay nada que sugerir.
        $unica = array_column($items, 'valor') === [trim($texto)];
        $this->sugerencias = $items === [] || $unica ? [] : ['campo' => $campo, 'i' => $i, 'items' => $items];
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
        if (! isset($this->filas[$llave][$i])) {
            return;
        }
        // La opción "Telar" (sin valor) quita el telar: antes se ignoraba, la fila se quedaba con el
        // anterior y se podía guardar con un telar que la pantalla ya no mostraba.
        if ($valor === '') {
            $this->filas[$llave][$i]['telar'] = '';

            return;
        }
        [$salon, $telar] = array_pad(explode('|', $valor, 2), 2, '');
        if (! in_array($valor, array_column(CatalogosDestino::telaresParaClave((string) $this->filas[$llave][$i]['clave']), 'valor'), true)) {
            return;
        }
        $cambiaSalon = $this->filas[$llave][$i]['salon'] !== $salon;
        $this->filas[$llave][$i]['salon'] = $salon;
        $this->filas[$llave][$i]['telar'] = $telar;
        $this->resetErrorBag("filas.$llave.$i.telar");
        if ($cambiaSalon) {
            $this->cargarClave($llave, $i, $telar);
        }
    }

    /** Al cambiar el campo (o al elegir): la clave se busca en Modelos; el flog trae su proyecto. Ambos se validan. */
    public function confirmar(string $campo, int $i): void
    {
        $this->sugerencias = [];
        $llave = $this->llave();
        if (! isset($this->filas[$llave][$i])) {
            return;
        }
        if ($campo === 'clave') {
            $this->cargarClave($llave, $i);

            return;
        }
        // Los IDFLOG de AX van en mayúsculas: "rs-jul24-…" escrito a mano es el mismo flog.
        $flog = mb_strtoupper(trim((string) $this->filas[$llave][$i]['flog']));
        $this->filas[$llave][$i]['flog'] = $flog;
        $proyecto = CatalogosDestino::flogs()[$flog] ?? '';
        if ($proyecto !== '') {
            $this->filas[$llave][$i]['descripcion'] = $proyecto;
        }
        $this->marcar($llave, $i);
    }

    /** Busca la clave en Modelos del salón de la fila: trae producto, flog y descripción, y marca lo que no sirva. */
    private function cargarClave(string $modo, int $i, ?string $telar = null): void
    {
        $this->filas[$modo][$i]['telar'] = $telar ?? $this->filas[$modo][$i]['telar'];
        $fila = $this->filas[$modo][$i];
        $clave = trim((string) $fila['clave']);
        // Sin columna Salón: si la clave no está en el salón de la fila pero sí en otro, la fila se
        // mueve a ese salón y el telar se vuelve a elegir (el select solo lista salones de la clave).
        $salones = CatalogosDestino::salonesDeClave($clave);
        if ($salones !== [] && ! in_array($fila['salon'], $salones, true)) {
            [$fila['salon'], $fila['telar']] = [$salones[0], ''];
        }
        $datos = $clave === '' ? null : CatalogosDestino::datosClave($clave, (string) $fila['salon']);
        if ($datos !== null) {
            $datos['producto'] = $datos['producto'] ?: $fila['producto'];
            $this->filas[$modo][$i] = array_merge($fila, $datos);
        }
        $this->marcar($modo, $i);
    }
}
