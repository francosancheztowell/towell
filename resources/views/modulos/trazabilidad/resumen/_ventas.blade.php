{{-- Maqueta frontend: pendiente de definir y conectar la fuente de datos de Ventas. --}}
<article class="min-h-[290px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col"
         data-sales-frontend-only>
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
            <i class="fa-solid fa-receipt"></i>
        </span>
        <div>
            <h3 class="font-bold text-slate-800">Ventas</h3>
        </div>
    </header>

    <div class="flex-1 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 text-left">Factura</th>
                    <th class="px-3 py-2.5 text-center">Fecha</th>
                    <th class="px-3 py-2.5 text-right">Pzas</th>
                    <th class="px-3 py-2.5 text-right">Kilos</th>
                    <th class="px-4 py-2.5 text-left">Pedido</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center">
                        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-slate-50 text-slate-300">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <p class="mt-3 font-semibold text-slate-500">Ventas pendiente de conexión</p>
                        <p class="mt-1 text-xs text-slate-400">La estructura visual está lista; todavía no consulta información.</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <footer class="border-t border-slate-100 px-4 py-2 text-right">
        <button type="button" data-resumen-detalle="ventas"
                class="btn-ver-detalles inline-flex items-center gap-2 text-sm font-bold text-amber-600 hover:text-amber-800">
            Ver detalles <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </footer>
</article>
