{{--
    Botones Nuevo / Editar / Eliminar de un catálogo con App\Livewire\Concerns\ConCrud.
    Va dentro de <x-slot:acciones> de x-tabla (se teletransporta al navbar).
    @prop array   $puede        ['crear','modificar','eliminar'] => bool (ConCrud::permisos)
    @prop ?string $seleccionado
    @prop string  $confirmar    Título del aviso antes de borrar
--}}
@props(['puede', 'seleccionado' => null, 'confirmar' => '¿Eliminar el registro?'])

@if ($puede['crear'])
    <flux:button variant="primary" color="blue" icon="plus" class="min-h-touch" wire:click="abrirAlta">Nuevo</flux:button>
@endif
{{ $slot }}
@if ($puede['modificar'])
    <flux:button icon="pencil-square" class="min-h-touch" wire:click="abrirEdicion" :disabled="$seleccionado === null">Editar</flux:button>
@endif
@if ($puede['eliminar'])
    <flux:button variant="danger" icon="trash" class="min-h-touch" :disabled="$seleccionado === null"
                 x-on:click="notify.confirm({ title: @js($confirmar), text: 'Se borra el registro seleccionado. No se puede deshacer.', confirmText: 'Sí, eliminar', confirmColor: '#dc2626' }).then(ok => ok && $wire.eliminar())">
        Eliminar
    </flux:button>
@endif
