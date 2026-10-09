{{-- Rollos teñidos de una máquina: lo llena production-detail.ts y lo abre con el evento modal-show de Flux. --}}
<flux:modal name="rollos-maquina" class="w-full md:max-w-4xl">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg" level="2">Rollos teñidos · <span data-rollos-maquina></span></flux:heading>
            <flux:text class="mt-1">Detalle por orden de lo que pasó por esta máquina.</flux:text>
        </div>

        <dl class="grid grid-cols-3 gap-3">
            <div class="rounded-lg bg-zinc-50 px-3 py-2.5">
                <dt class="text-xs text-zinc-500">Órdenes</dt>
                <dd class="text-xl font-semibold tabular-nums text-zinc-900" data-rollos-ordenes></dd>
            </div>
            <div class="rounded-lg bg-zinc-50 px-3 py-2.5">
                <dt class="text-xs text-zinc-500">Piezas</dt>
                <dd class="text-xl font-semibold tabular-nums text-zinc-900" id="modal-rollos-total-pzas"></dd>
            </div>
            <div class="rounded-lg bg-zinc-50 px-3 py-2.5">
                <dt class="text-xs text-zinc-500">Kilos</dt>
                <dd class="text-xl font-semibold tabular-nums text-zinc-900" id="modal-rollos-total-kg"></dd>
            </div>
        </dl>

        <flux:table class="traza-tabla" container:class="traza-tabla-limitada traza-tabla-limitada--alta">
            <flux:table.columns sticky>
                <flux:table.column>Orden</flux:table.column>
                <flux:table.column>Artículo</flux:table.column>
                <flux:table.column>Color</flux:table.column>
                <flux:table.column align="end">Piezas</flux:table.column>
                <flux:table.column align="end">Kilos</flux:table.column>
            </flux:table.columns>
            <flux:table.rows id="modal-rollos-maquina-body" />
        </flux:table>
    </div>
</flux:modal>
