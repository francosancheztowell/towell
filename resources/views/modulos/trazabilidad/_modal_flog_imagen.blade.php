{{-- Modal visor imagen Flog: pantalla completa, zoom y descarga --}}
<div id="modal-flog-imagen" class="hidden fixed inset-0" role="dialog" aria-modal="true" aria-labelledby="modal-flog-imagen-titulo">
    <div class="modal-flog-imagen__backdrop absolute inset-0" data-modal-flog-close></div>
    <div class="modal-flog-imagen__shell">
        <header class="modal-flog-imagen__toolbar">
            <p id="modal-flog-imagen-titulo" class="modal-flog-imagen__titulo"></p>
            <div class="modal-flog-imagen__tools">
                <button type="button" class="modal-flog-imagen__btn" data-flog-zoom-out title="Alejar" aria-label="Alejar">
                    <i class="fa-solid fa-magnifying-glass-minus"></i>
                </button>
                <span class="modal-flog-imagen__zoom-label" data-flog-zoom-label>100%</span>
                <button type="button" class="modal-flog-imagen__btn" data-flog-zoom-in title="Acercar" aria-label="Acercar">
                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                </button>
                <button type="button" class="modal-flog-imagen__btn" data-flog-zoom-reset title="Ajustar a pantalla" aria-label="Ajustar a pantalla">
                    <i class="fa-solid fa-compress"></i>
                </button>
                <button type="button" class="modal-flog-imagen__btn modal-flog-imagen__btn--primary" data-flog-download title="Descargar imagen" aria-label="Descargar imagen">
                    <i class="fa-solid fa-download"></i>
                    <span class="hidden sm:inline">Descargar</span>
                </button>
                <button type="button" class="modal-flog-imagen__btn" data-modal-flog-close title="Cerrar" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </header>
        <div class="modal-flog-imagen__stage" data-flog-stage>
            <div class="modal-flog-imagen__viewport" data-flog-viewport>
                <img src="" alt="" data-modal-flog-img draggable="false">
            </div>
            <p class="modal-flog-imagen__hint">Rueda o botones para zoom · Arrastra la imagen · Click fuera para cerrar</p>
        </div>
    </div>
</div>
