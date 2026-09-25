{{--
    Fila de "sin registros" para el @empty de x-ui.table / x-tabla (DS-03/08).

    @prop int    $colspan
    @prop string $message  (default 'No hay registros')
    @prop string $icon     Font Awesome (default 'fa-inbox')
    @slot default          Acción opcional bajo el mensaje (p. ej. un x-ui.button "Crear")
--}}
@props(['colspan' => 1, 'message' => 'No hay registros', 'icon' => 'fa-inbox'])

<tr {{ $attributes }}>
    <td colspan="{{ max((int) $colspan, 1) }}" class="px-4 py-14 text-center">
        <i class="fa-solid {{ $icon }} text-3xl text-slate-300" aria-hidden="true"></i>
        <p class="mt-3 font-semibold text-slate-600">{{ $message }}</p>
        @if ($slot->isNotEmpty())
            <div class="mt-3">{{ $slot }}</div>
        @endif
    </td>
</tr>
