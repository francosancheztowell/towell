{{--
    Componente: Telar Navbar

    Descripción:
        Navbar fijo para navegación rápida entre telares.
        Incluye scroll automático y detección de telar activo.

    Props:
        @param array $telares - Array de números de telares
        @param string $id - ID del navbar (default: 'telares-navbar')
        @param bool $autoShow - Si debe mostrarse automáticamente al hacer scroll (default: true)
        @param int $showThreshold - Umbral de scroll para mostrar (default: 200)

    Uso:
        <x-telares.telar-navbar :telares="[201, 202, 203, 204, 205]" />
        <x-telares.telar-navbar :telares="$telaresJacquard" id="my-navbar" />
--}}

@props([
    'telares' => [],
    'id' => 'telares-navbar',
    'autoShow' => true,
    'showThreshold' => 200
])

<!-- Navbar fijo con números de telares -->
<div
    id="{{ $id }}"
    class="fixed top-16 left-0 right-0 z-40 transition-all duration-300 transform -translate-y-full"
>
    <div class="container mx-auto px-4 py-3">
        <div class="flex flex-wrap justify-center gap-2 max-w-7xl mx-auto">
            @foreach($telares as $telar)
                <button
                    id="nav-telar-{{ $telar }}"
                    class="telar-nav-btn px-3 py-1.5 rounded text-sm font-medium transition-all duration-200 border border-gray-300 bg-gray-100 text-gray-700 hover:bg-blue-100 hover:border-blue-300 hover:text-blue-700"
                    data-telar="{{ $telar }}">
                    {{ $telar }}
                </button>
            @endforeach
        </div>
    </div>
</div>

<script>
class TelarNavbar {
    constructor(navbarId, telares, showThreshold = 200, autoShow = true) {
        this.navbar = document.getElementById(navbarId);
        this.telares = telares;
        this.showThreshold = showThreshold;
        this.isVisible = false;
        this.autoShow = autoShow;

        this.init();
    }

    init() {
        if (!this.navbar) return;

        this.setupScrollListener();
        this.setupClickListeners();
        this.updateActiveOnScroll();

        if (!this.autoShow) {
            this.show();
        }
    }

    setupScrollListener() {
        window.addEventListener('scroll', () => {
            const currentScrollTop = window.pageYOffset;

            // Mostrar/ocultar navbar basado en scroll
            if (this.autoShow) {
                if (currentScrollTop < this.showThreshold) {
                    this.hide();
                } else {
                    this.show();
                }
            }

            // Actualizar telar activo
            this.updateActiveOnScroll();

        });
    }

    setupClickListeners() {
        this.navbar.addEventListener('click', (event) => {
            const button = event.target.closest('.telar-nav-btn');
            if (!button) return;

            const telarNumber = Number(button.dataset.telar);
            if (Number.isNaN(telarNumber)) return;

            this.scrollToTelar(telarNumber);
        });
    }

    scrollToTelar(telarNumber) {
        const element = document.getElementById(`telar-${telarNumber}`);
        if (!element) return;

        // Calcular posición exacta
        const elementRect = element.getBoundingClientRect();
        const absoluteElementTop = elementRect.top + window.pageYOffset;
        const navbarHeight = 56;
        const offsetTop = absoluteElementTop - navbarHeight - 60; // 60px de margen

        window.scrollTo({
            top: Math.max(0, offsetTop),
            behavior: 'smooth'
        });

        // Actualizar botón activo después del scroll
        setTimeout(() => {
            this.updateActiveButton(telarNumber);
        }, 500);
    }

    updateActiveButton(activeTelar) {
        // Remover clase activa de todos los botones
        document.querySelectorAll('.telar-nav-btn').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'border-blue-600', 'text-white');
            btn.classList.add('bg-gray-100', 'border-gray-300', 'text-gray-700');
        });

        // Agregar clase activa al botón del telar actual
        const activeBtn = document.getElementById(`nav-telar-${activeTelar}`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-gray-100', 'border-gray-300', 'text-gray-700');
            activeBtn.classList.add('bg-blue-600', 'border-blue-600', 'text-white');
        }
    }

    updateActiveOnScroll() {
        const navbarHeight = 56;
        const viewportTop = window.pageYOffset + navbarHeight + 60;

        let activeTelar = null;
        let closestDistance = Infinity;

        this.telares.forEach(telar => {
            const element = document.getElementById(`telar-${telar}`);
            if (!element) return;

            const elementRect = element.getBoundingClientRect();
            const elementTop = elementRect.top + window.pageYOffset;
            const elementBottom = elementTop + element.offsetHeight;

            // Si el telar está en el viewport
            if (viewportTop >= elementTop && viewportTop < elementBottom) {
                activeTelar = telar;
                return;
            }

            // Si no está en viewport, encontrar el más cercano
            const distance = Math.abs(elementTop - viewportTop);
            if (distance < closestDistance && elementTop > window.pageYOffset) {
                closestDistance = distance;
                activeTelar = telar;
            }
        });

        if (activeTelar) {
            this.updateActiveButton(activeTelar);
        }
    }

    show() {
        if (!this.isVisible && this.navbar) {
            this.navbar.style.transform = 'translateY(0)';
            this.isVisible = true;
        }
    }

    hide() {
        if (this.isVisible && this.navbar) {
            this.navbar.style.transform = 'translateY(-100%)';
            this.isVisible = false;
        }
    }
}


// Inicializar navbar cuando el DOM este listo
document.addEventListener('DOMContentLoaded', function() {
    const telares = @json($telares);
    const autoShow = @json($autoShow);
    window.telarNavbar = new TelarNavbar('{{ $id }}', telares, {{ $showThreshold }}, autoShow);
});
</script>



