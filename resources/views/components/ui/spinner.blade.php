{{--
    Spinner (DS-07) para cargas locales (un botón, una tarjeta). Para bloquear la pantalla
    completa está el loader global: window.loader.show()/hide().

    @prop string $size   'sm' (16 px) | 'md' (24 px, default) | 'lg' (40 px)
    @prop string $label  Texto para lectores de pantalla (default 'Cargando…'); visible si $inline

    <x-ui.spinner />  <x-ui.spinner size="sm" label="Guardando" :inline="true" />
--}}
@props(['size' => 'md', 'label' => 'Cargando…', 'inline' => false])

@php
    $tam = ['sm' => 'size-4 border-2', 'md' => 'size-6 border-2', 'lg' => 'size-10 border-4'][$size] ?? 'size-6 border-2';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2 text-sm text-ink-muted']) }} role="status">
    <span class="{{ $tam }} animate-spin rounded-full border-current border-t-transparent text-primary" aria-hidden="true"></span>
    <span @class(['sr-only' => ! $inline])>{{ $label }}</span>
</span>
