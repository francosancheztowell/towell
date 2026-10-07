{{--
    Barras por hora del panel /admin (firma del panel): una barra por cubeta, la última es la hora en curso.

    @prop list<int> $valores   Cubetas de PanelConsultas::porHora()
    @prop int       $alto      Alto en px (default 22)
    @prop string    $etiqueta  Qué cuentan: "eventos", "vistas" (lector de pantalla y lectura al pasar)
    @prop bool      $ejes      Marcas de tiempo debajo (-24 h · -12 h · ahora)
    @prop bool      $escala    Línea punteada del máximo con su valor, arriba a la derecha

    Color: el de texto del componente (class="text-(--adm-err)"); las cubetas en cero van en --adm-bar.
    Al pasar sobre una barra, resources/js/modulos/admin/index.ts muestra su data-lectura.
--}}
@props(['valores' => [], 'alto' => 22, 'etiqueta' => 'eventos', 'ejes' => false, 'escala' => false])

@php
    $valores = array_values($valores);
    $n = max(1, count($valores));
    $max = max(1, ...$valores ?: [0]);
    $total = array_sum($valores);
    $paso = 4;
    $ancho = $n * $paso - 1.6;
    $cuando = function (int $atras): string {
        if ($atras === 0) {
            return 'esta hora';
        }

        return $atras < 24 ? 'hace '.$atras.' h' : 'hace '.intdiv($atras, 24).' d '.($atras % 24).' h';
    };
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <div class="relative">
        @if ($escala)
            <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-(--adm-line-strong)" aria-hidden="true"></div>
            <span class="pointer-events-none absolute end-0 top-0 -translate-y-full pb-0.5 text-caption tabular-nums text-(--adm-ink-3)" aria-hidden="true">{{ number_format($max) }}</span>
        @endif
        <svg class="adm-barras block w-full" viewBox="0 0 {{ $ancho }} {{ $alto }}" preserveAspectRatio="none" height="{{ $alto }}"
             role="img" aria-label="{{ number_format($total) }} {{ $etiqueta }} en las últimas {{ $n }} horas">
            @foreach ($valores as $i => $v)
                @php $h = $v > 0 ? max(2, round($alto * $v / $max, 1)) : 1; @endphp
                <rect x="{{ $i * $paso }}" y="{{ $alto - $h }}" width="2.4" height="{{ $h }}" rx="0.4"
                      data-lectura="{{ ucfirst($cuando($n - 1 - $i)) }} · {{ number_format($v) }} {{ $etiqueta }}"
                      @if ($v > 0) data-lleno @endif @if ($loop->last) data-ahora @endif />
            @endforeach
        </svg>
    </div>
    @if ($ejes)
        <div class="mt-1.5 flex justify-between text-caption tabular-nums text-(--adm-ink-3)" aria-hidden="true">
            @if ($n > 48)
                <span>-{{ intdiv($n, 24) }} d</span><span>-{{ intdiv($n, 48) }} d</span><span>ahora</span>
            @else
                <span>-{{ $n }} h</span><span>-{{ intdiv($n, 2) }} h</span><span>ahora</span>
            @endif
        </div>
    @endif
</div>
