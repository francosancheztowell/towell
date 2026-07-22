{{-- Modal detalle rollos por máquina (se mueve a body vía JS) --}}
<div id="modal-rollos-maquina" class="hidden fixed inset-0 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-rollos-maquina-titulo">
    <div class="modal-rollos-maquina__backdrop absolute inset-0" data-modal-rollos-close></div>
    <div class="modal-rollos-maquina__panel relative bg-white rounded-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 shrink-0">
            <h4 id="modal-rollos-maquina-titulo" class="text-lg font-bold text-slate-800 truncate"></h4>
            <button type="button" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg transition-colors" data-modal-rollos-close aria-label="Cerrar">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="overflow-auto flex-1 p-4">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <th class="px-3 py-2 border-b border-slate-200">Orden</th>
                        <th class="px-3 py-2 border-b border-slate-200">Artículo</th>
                        <th class="px-3 py-2 border-b border-slate-200">Color</th>
                        <th class="px-3 py-2 border-b border-slate-200 text-right">Pzas</th>
                        <th class="px-3 py-2 border-b border-slate-200 text-right">Kg</th>
                    </tr>
                </thead>
                <tbody id="modal-rollos-maquina-body" class="text-slate-700"></tbody>
                <tfoot>
                    <tr class="bg-blue-50 font-bold text-slate-800">
                        <td colspan="3" class="px-3 py-2 border-t border-slate-200">Total</td>
                        <td id="modal-rollos-total-pzas" class="px-3 py-2 border-t border-slate-200 text-right tabular-nums"></td>
                        <td id="modal-rollos-total-kg" class="px-3 py-2 border-t border-slate-200 text-right tabular-nums"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
