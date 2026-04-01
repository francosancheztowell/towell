/**
 * Store centralizado para el estado de la tabla de programa-tejido.
 * Permite actualizaciones optimistas (cambio inmediato en UI, sync con servidor en background).
 */
class PTStore {
    constructor() {
        this.registros = new Map(); // id -> registro data
        this.listeners = new Set();
    }

    /** Obtener todos los registros como array. */
    getAll() {
        return Array.from(this.registros.values());
    }

    /** Obtener un registro por ID. */
    get(id) {
        return this.registros.get(String(id));
    }

    /** Agregar o actualizar un registro (merge parcial). */
    set(id, data) {
        this.registros.set(String(id), { ...this.registros.get(String(id)), ...data });
        this.notify();
    }

    /** Agregar nuevo registro (duplicar, crear repaso). */
    add(data) {
        const id = data.Id || data.id;
        if (id) {
            this.registros.set(String(id), data);
            this.notify();
            return id;
        }
        return null;
    }

    /** Eliminar registro del store. */
    remove(id) {
        this.registros.delete(String(id));
        this.notify();
    }

    /**
     * Suscribirse a cambios del store.
     * @param {function(Array): void} fn - Callback que recibe todos los registros
     * @returns {function(): void} Función para cancelar la suscripción
     */
    subscribe(fn) {
        this.listeners.add(fn);
        return () => this.listeners.delete(fn);
    }

    /** Notificar a todos los suscriptores. */
    notify() {
        this.listeners.forEach(fn => fn(this.getAll()));
    }

    /** Cargar datos iniciales desde el servidor (reemplaza estado completo). */
    loadFromServer(data) {
        this.registros.clear();
        data.forEach(r => {
            const id = r.Id || r.id;
            if (id) this.registros.set(String(id), r);
        });
        this.notify();
    }
}

// Instancia global única
window.PTStore = new PTStore();
