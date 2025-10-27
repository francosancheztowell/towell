/**
 * Sistema Simple de Precarga de Módulos - Towell
 * Versión Simplificada para Mejor Rendimiento
 */

(function() {
    'use strict';

    const ModulePrefetch = {
        config: {
            preloadDelay: 1500, // ms
        },

        modulesToPrefetch: [
            'planeacion',
            'tejido',
            'urdido',
            'engomado',
            'atadores',
            'tejedores',
            'programa-urd-eng',
            'mantenimiento',
            'configuracion'
        ],

        /**
         * Inicializar el sistema
         */
        init() {
            if (!window.location.pathname.includes('produccionProceso')) {
                return;
            }

            console.log('[ModulePrefetch] Iniciando precarga...');

            setTimeout(() => {
                this.prefetchAllModules();
            }, this.config.preloadDelay);
        },

        /**
         * Precargar todos los módulos
         */
        async prefetchAllModules() {
            for (const modulo of this.modulesToPrefetch) {
                this.prefetchModule(modulo);
            }
        },

        /**
         * Precargar un módulo específico
         */
        async prefetchModule(modulo) {
            try {
                const url = `/api/submodulos/${modulo}`;
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    // Guardar en localStorage para acceso rápido
                    localStorage.setItem(`prefetch_${modulo}`, JSON.stringify({
                        data: data,
                        timestamp: Date.now()
                    }));
                    console.log(`[ModulePrefetch] ✓ Precargado: ${modulo}`);
                }
            } catch (error) {
                console.warn(`[ModulePrefetch] Error en ${modulo}:`, error);
            }
        },

        /**
         * Obtener módulo desde caché
         */
        getCachedModule(modulo) {
            try {
                const cached = localStorage.getItem(`prefetch_${modulo}`);
                if (cached) {
                    const parsed = JSON.parse(cached);
                    // Cache válido por 30 minutos
                    if (Date.now() - parsed.timestamp < 30 * 60 * 1000) {
                        return parsed.data;
                    }
                }
            } catch (e) {
                console.warn('Error leyendo caché:', e);
            }
            return null;
        },

        /**
         * Limpiar caché
         */
        clearCache() {
            this.modulesToPrefetch.forEach(modulo => {
                localStorage.removeItem(`prefetch_${modulo}`);
            });
            console.log('[ModulePrefetch] Caché limpiado');
        }
    };

    // Auto-inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ModulePrefetch.init());
    } else {
        ModulePrefetch.init();
    }

    // Exponer globalmente
    window.ModulePrefetch = ModulePrefetch;

})();
