/**
 * Scripts principales de la aplicación
 */

// Logout modal
(function() {
    const btn = document.getElementById('logout-btn');
    if (!btn) return;
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: '¿Confirma cerrar sesión?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, salir',
            cancelButtonText: 'Cancelar'
        }).then(res => {
            if (res.isConfirmed) document.getElementById('logout-form').submit();
        });
    });
})();

// Botón atrás
document.addEventListener('DOMContentLoaded', function() {
    const btnBack = document.getElementById('btn-back');
    const homePath = '/produccionProceso';
    if (btnBack && location.pathname !== homePath) {
        btnBack.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
        btnBack.classList.add('flex', 'opacity-100', 'visible');
        btnBack.addEventListener('click', function() {
            if (history.length > 1 && document.referrer) {
                history.back();
            } else {
                location.href = homePath;
            }
        });
    }
});

// Menú usuario compacto
(function() {
    const btn = document.getElementById('btn-user-avatar');
    const modal = document.getElementById('user-modal');
    let open = false;

    function show(e) {
        e && e.stopPropagation();
        modal.classList.remove('opacity-0', 'invisible', 'scale-95');
        modal.classList.add('opacity-100', 'visible', 'scale-100');
        open = true;
    }

    function hide() {
        modal.classList.remove('opacity-100', 'visible', 'scale-100');
        modal.classList.add('opacity-0', 'invisible', 'scale-95');
        open = false;
    }

    if (btn && modal) {
        btn.addEventListener('click', e => open ? hide() : show(e));
        document.addEventListener('click', e => {
            if (open && !modal.contains(e.target) && !btn.contains(e.target)) hide();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && open) hide();
        });
    }
})();

// Persistencia UI simple
window.addEventListener('pageshow', function() {
    if (sessionStorage.getItem('forceReload')) {
        sessionStorage.removeItem('forceReload');
        location.reload();
    }
});

// Toastr base
if (typeof toastr !== 'undefined') {
    toastr.options = {
        closeButton: true,
        debug: false,
        newestOnTop: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        preventDuplicates: false,
        showDuration: '300',
        hideDuration: '1000',
        timeOut: '5000',
        extendedTimeOut: '1000',
        showEasing: 'swing',
        hideEasing: 'linear',
        showMethod: 'fadeIn',
        hideMethod: 'fadeOut'
    };
}

// Optimización navegación simple
(function() {
    const path = location.pathname;
    const prev = sessionStorage.getItem('lastNavbarPath');
    document.documentElement.setAttribute('data-navbar-loaded', prev === path ? 'true' : 'false');
    sessionStorage.setItem('lastNavbarPath', path);
    document.addEventListener('click', e => {
        const link = e.target.closest('a[href]');
        if (!link || link.target === '_blank') return;
        if (link.hostname !== location.hostname) return;
    });
    window.addEventListener('pageshow', () => {});
})();



