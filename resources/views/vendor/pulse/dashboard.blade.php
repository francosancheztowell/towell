{{--
    Dashboard de Pulse (fase 14). Solo las tarjetas de los recorders encendidos en
    config/pulse.php: sin servers, exceptions (fuente única SYSMonError), cache ni queues.
    Con PULSE_ENABLED=false no se consultan las tablas (la migración se salta).
--}}
<x-pulse>
    @if (config('pulse.enabled'))
        <livewire:pulse.usage cols="4" rows="2" />

        <livewire:pulse.slow-requests cols="8" />

        <livewire:pulse.slow-queries cols="full" />

        <livewire:pulse.slow-jobs cols="6" />

        <livewire:pulse.slow-outgoing-requests cols="6" />
    @else
        <div class="default:col-span-full rounded-xl bg-white p-6 text-sm text-gray-700 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-900 dark:text-gray-300">
            <h2 class="text-base font-bold">Pulse está apagado</h2>
            <p class="mt-2">
                Este servidor tiene <code>PULSE_ENABLED=false</code>. El panel de
                <a href="{{ url('/admin') }}" class="underline">monitoreo</a> sigue funcionando con sus propias tablas.
            </p>
            <p class="mt-2">
                Para encenderlo: extensión <code>pdo_sqlite</code> activa, <code>PULSE_ENABLED=true</code>,
                <code>php artisan migrate</code> y <code>php artisan optimize</code>
                (ver .planning/phases/14-mon-pulse/14-01-SUMMARY.md).
            </p>
        </div>
    @endif
</x-pulse>
