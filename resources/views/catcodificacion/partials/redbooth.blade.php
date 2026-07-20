<div id="catCodificadosRedboothMenu" class="fixed hidden min-w-[190px] rounded-md border border-gray-200 bg-white py-1 shadow-xl" style="z-index:15000">
    <button type="button" id="catCodificadosRedboothAction" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700">
        <i class="fas fa-comments text-blue-600"></i>
        <span>Redbooth</span>
    </button>
</div>

@include('modulos.programa-tejido.modal.redbooth', ['redboothContext' => 'catcodificados'])

<script>
(() => {
    const tbody = document.getElementById('catcodificacion-body');
    const menu = document.getElementById('catCodificadosRedboothMenu');
    const action = document.getElementById('catCodificadosRedboothAction');
    if (!tbody || !menu || !action) return;

    let selectedRow = null;
    const hide = () => menu.classList.add('hidden');
    tbody.addEventListener('contextmenu', (event) => {
        const row = event.target.closest('tr[data-cat-id]');
        if (!row) return;
        event.preventDefault();
        selectedRow = row;
        if (!row.classList.contains('codificacion-row-selected')) row.click();

        menu.classList.remove('hidden');
        const rect = menu.getBoundingClientRect();
        menu.style.left = `${Math.max(8, Math.min(event.clientX, window.innerWidth - rect.width - 8))}px`;
        menu.style.top = `${Math.max(8, Math.min(event.clientY, window.innerHeight - rect.height - 8))}px`;
    });
    action.addEventListener('click', () => {
        const registroId = Number(selectedRow?.dataset.catId || 0);
        hide();
        if (registroId && typeof window.abrirModalRedboothProgramaTejido === 'function') {
            window.abrirModalRedboothProgramaTejido({registroId});
        }
    });
    document.addEventListener('click', (event) => { if (!menu.contains(event.target)) hide(); });
    window.addEventListener('blur', hide);
    document.addEventListener('scroll', hide, true);
})();
</script>
