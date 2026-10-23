<?php

declare(strict_types=1);

namespace App\Livewire\Planeacion\ProgramaTejido;

/**
 * Validación de las filas de DuplicarDividir: telar elegido, clave en Modelos del salón y flog
 * vigente en AX. Marca el campo, y pendiente() dice por qué el botón está bloqueado.
 */
trait ValidaFilasDestino
{
    /** Las filas que se capturan (no las que ya existen en BD) muestran desde el inicio si su clave o flog no sirven. */
    private function marcarEditables(): void
    {
        foreach (['duplicar', 'dividir'] as $llave) {
            foreach ($this->filas[$llave] as $i => $fila) {
                if (! $fila['existente']) {
                    $this->marcar($llave, $i);
                }
            }
        }
    }

    /** Marca la clave que no está en Modelos del salón de la fila y el flog que no está vigente en AX. */
    private function marcar(string $llave, int $i): void
    {
        $base = "filas.$llave.$i";
        $fila = $this->filas[$llave][$i];
        $this->resetErrorBag(["$base.clave", "$base.flog"]);
        $clave = trim((string) $fila['clave']);
        if (! CatalogosDestino::claveEnSalon($clave, (string) $fila['salon'])) {
            $this->addError("$base.clave", $clave === '' ? 'Captura la clave modelo.' : "La clave {$clave} no existe en Modelos de {$fila['salon']}.");
        }
        // El flog puede ir vacío; si se escribe, tiene que existir y estar vigente (que no vaya mal escrito).
        $flog = trim((string) $fila['flog']);
        if ($flog !== '' && ! CatalogosDestino::esFlogVigente($flog)) {
            $this->addError("$base.flog", "El flog {$flog} no está vigente en AX.");
        }
    }

    /**
     * Filas que se van a crear: todas en Duplicar/Vincular; en Dividir las nuevas con telar o saldo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function filasAGuardar(): array
    {
        $filas = $this->filas[$this->llave()];

        return $this->llave() === 'duplicar' ? $filas : array_filter($filas, fn (array $f) => ! $f['existente'] && FilasDestino::filaUtil($f));
    }

    /**
     * Por qué no se puede guardar todavía (null = se puede): el botón se bloquea y lo dice. '' =
     * bloqueado sin aviso: la clave o el flog malos ya traen su mensaje bajo el campo.
     */
    public function pendiente(): ?string
    {
        $llave = $this->llave();
        $filas = $this->filasAGuardar();
        if ($this->modo === 'vincular' && count($filas) < 2) {
            return 'Agrega al menos 2 órdenes para vincularlas entre sí.';
        }
        $numeros = fn (array $indices) => implode(', ', array_map(fn (int $i) => $i + 1, $indices));
        $sinTelar = array_keys(array_filter($filas, fn (array $f) => trim((string) $f['telar']) === ''));
        if ($sinTelar !== []) {
            return 'Elige el telar en la fila '.$numeros($sinTelar).'.';
        }
        // En Dividir también cuentan las filas que ya existen: su pedido se captura y no puede bajar de lo producido.
        $menores = array_keys(array_filter($this->filas[$llave], fn (array $f) => FilasDestino::filaUtil($f) && FilasDestino::saldo($f) < 0));
        if ($menores !== []) {
            return 'El pedido de la fila '.$numeros($menores).' es menor a lo ya producido.';
        }
        $marcadas = array_filter(array_keys($filas), fn (int $i) => $this->getErrorBag()->has("filas.$llave.$i.*"));

        return $marcadas === [] ? null : '';
    }

    /** Telar, clave y flog de cada fila que se crea, y pedido no menor a lo producido; marca lo que falte. */
    private function listo(): bool
    {
        $llave = $this->llave();
        foreach ($this->filasAGuardar() as $i => $fila) {
            $this->marcar($llave, $i);
            $this->resetErrorBag("filas.$llave.$i.telar");
            if (trim((string) $fila['telar']) === '') {
                $this->addError("filas.$llave.$i.telar", 'Elige el telar.');
            }
        }
        foreach ($this->filas[$llave] as $i => $fila) {
            $this->resetErrorBag("filas.$llave.$i.pedido");
            if (FilasDestino::filaUtil($fila) && FilasDestino::saldo($fila) < 0) {
                $this->addError("filas.$llave.$i.pedido", 'El pedido no puede ser menor a lo ya producido ('.number_format((float) $fila['produccion']).').');
            }
        }

        return $this->pendiente() === null;
    }
}
