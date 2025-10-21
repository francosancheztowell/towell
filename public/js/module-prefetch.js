/**
 * Sistema de Precarga de Módulos - Towell
 * Precarga todos los submódulos en background para navegación instantánea
 */

(function() {
    'use strict';

    const ModulePrefetch = {
        // Configuración
        config: {
            cachePrefix: 'towell_modules_',
            cacheExpiry: 60000, // 1 minuto en milisegundos (reducido para desarrollo)
            enableLocalStorage: false, // Deshabilitado para evitar problemas con imágenes actualizadas
            enablePrefetch: true
        },

        // Lista de módulos a precargar
        modulesToPrefetch: [
            'planeacion',
            'tejido',
            'urdido',
            'engomado',
            'atadores',
            'tejedores',
            'programa-urd-eng',
            'mantenimiento'
        ],

        /**
         * Inicializar el sistema de precarga
         */
        init() {
            // Solo ejecutar en la página principal de produccionProceso
            if (!window.location.pathname.includes('produccionProceso')) {
                return;
            }

            console.log('🚀 Iniciando precarga de módulos...');

            // Esperar 500ms después de que la página cargue
            // para no interferir con la carga inicial
            setTimeout(() => {
                this.prefetchAllModules();
            }, 500);

            // Interceptar clicks en módulos para navegación instantánea
            this.setupInstantNavigation();
        },

        /**
         * Precargar todos los módulos en background
         */
        async prefetchAllModules() {
            const startTime = performance.now();
            let loaded = 0;
            let cached = 0;

            for (const modulo of this.modulesToPrefetch) {
                const fromCache = await this.prefetchModule(modulo);
                if (fromCache) {
                    cached++;
                } else {
                    loaded++;
                }
            }

            const endTime = performance.now();
            const duration = (endTime - startTime).toFixed(0);

            console.log(`✅ Precarga completada en ${duration}ms`);
            console.log(`   📦 Desde caché: ${cached} | 🌐 Desde servidor: ${loaded}`);
        },

        /**
         * Precargar un módulo específico
         */
        async prefetchModule(modulo) {
            try {
                // Verificar si ya está en caché
                const cachedData = this.getFromCache(modulo);
                if (cachedData) {
                    console.log(`   ⚡ ${modulo} - desde caché local`);
                    return true;
                }

                // Cargar desde servidor
                const response = await fetch(`/api/submodulos/${modulo}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    console.warn(`   ⚠️ ${modulo} - error ${response.status}`);
                    return false;
                }

                const data = await response.json();

                // Guardar en caché local
                this.saveToCache(modulo, data);

                console.log(`   📥 ${modulo} - cargado (${data.length} submódulos)`);
                return false;

            } catch (error) {
                console.warn(`   ❌ ${modulo} - error:`, error.message);
                return false;
            }
        },

        /**
         * Guardar en localStorage
         */
        saveToCache(key, data) {
            if (!this.config.enableLocalStorage) return;

            try {
                const cacheData = {
                    data: data,
                    timestamp: Date.now(),
                    expiry: Date.now() + this.config.cacheExpiry
                };

                localStorage.setItem(
                    this.config.cachePrefix + key,
                    JSON.stringify(cacheData)
                );
            } catch (e) {
                console.warn('Error guardando en caché:', e.message);
            }
        },

        /**
         * Obtener desde localStorage
         */
        getFromCache(key) {
            if (!this.config.enableLocalStorage) return null;

            try {
                const cached = localStorage.getItem(this.config.cachePrefix + key);
                if (!cached) return null;

                const cacheData = JSON.parse(cached);

                // Verificar si expiró
                if (Date.now() > cacheData.expiry) {
                    localStorage.removeItem(this.config.cachePrefix + key);
                    return null;
                }

                return cacheData.data;
            } catch (e) {
                return null;
            }
        },

        /**
         * Configurar navegación instantánea
         */
        setupInstantNavigation() {
            // Interceptar clicks en enlaces de módulos
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[href*="/submodulos/"]');
                if (!link) return;

                // Intentar navegar instantáneamente si tenemos datos en caché
                const moduloMatch = link.href.match(/\/submodulos\/([^\/\?#]+)/);
                if (moduloMatch) {
                    const modulo = moduloMatch[1];
                    const cachedData = this.getFromCache(modulo);

                    if (cachedData) {
                        console.log(`⚡ Navegación instantánea a: ${modulo}`);
                        // La página se cargará normalmente pero desde caché del servidor
                    }
                }
            });
        },

        /**
         * Limpiar caché (útil para debugging)
         */
        clearCache() {
            for (const modulo of this.modulesToPrefetch) {
                localStorage.removeItem(this.config.cachePrefix + modulo);
            }
            console.log('🗑️ Caché limpiado');
        }
    };

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ModulePrefetch.init());
    } else {
        ModulePrefetch.init();
    }

    // Exponer globalmente para debugging
    window.ModulePrefetch = ModulePrefetch;

})();















