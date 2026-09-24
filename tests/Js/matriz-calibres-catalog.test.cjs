// Formulario del catálogo Matriz de Calibres (public/js/catalogs/MatrizCalibresCatalog.js).
const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function cargarCatalogo() {
    const codigo = fs.readFileSync(
        path.join(__dirname, '../../public/js/catalogs/MatrizCalibresCatalog.js'),
        'utf8',
    );
    const contexto = {
        window: {},
        document: { getElementById: () => null },
        // CatalogBase real depende del DOM; aquí solo hace falta lo que usa el constructor.
        CatalogBase: class {
            constructor(config) {
                this.config = config;
                this.state = { originalData: config.initialData || [], currentData: [] };
            }
        },
    };
    vm.createContext(contexto);
    vm.runInContext(codigo, contexto);
    return new contexto.window.MatrizCalibresCatalog({ initialData: [] });
}

const opcionSeleccionada = (html) => (html.match(/<option value="([^"]*)" selected>/) || [])[1];

test('editar una fila BARRA conserva su tipo seleccionado', () => {
    const catalogo = cargarCatalogo();
    for (const tipo of ['BARRA1', 'BARRA2', 'BARRA3', 'BARRA4', 'RIZO', 'PIE', 'TRAMA']) {
        const html = catalogo.getEditFormHTML({ tipo, calibre: '75', fibraid: 'FIL', cuenta: '2104' });
        assert.equal(opcionSeleccionada(html), tipo, `al editar ${tipo} el select debe quedar en ${tipo}`);
    }
});

test('el alta ofrece las cuatro barras', () => {
    const html = cargarCatalogo().getCreateFormHTML();
    for (const tipo of ['BARRA1', 'BARRA2', 'BARRA3', 'BARRA4']) {
        assert.match(html, new RegExp(`<option value="${tipo}"`));
    }
});

test('un tipo desconocido no se pierde al editar', () => {
    const html = cargarCatalogo().getEditFormHTML({ tipo: 'OTRO' });
    assert.equal(opcionSeleccionada(html), 'OTRO');
});

test('las barras exigen cuenta, fibra y calibre; trama no lleva cuenta', () => {
    const catalogo = cargarCatalogo();
    const salida = { ItemId: 'JULIO-URDIDO', ConfigId: 'ALG-OPEN', InventSizeId: '2028-370/1', InventColorId: '1000' };

    assert.equal(catalogo.validateCreateData({ Tipo: 'BARRA2', Calibre: '75', FibraId: 'FIL', Cuenta: '', ...salida }).valid, false);
    assert.match(catalogo.validateCreateData({ Tipo: 'BARRA2', Calibre: '75', FibraId: 'FIL', Cuenta: '', ...salida }).message, /Cuenta/);
    assert.equal(catalogo.validateCreateData({ Tipo: 'BARRA2', Calibre: '', FibraId: 'FIL', Cuenta: '2104', ...salida }).valid, false);
    assert.equal(catalogo.validateCreateData({ Tipo: 'BARRA2', Calibre: '75', FibraId: 'FIL', Cuenta: '2104', ...salida }).valid, true);
    assert.equal(catalogo.validateEditData({ Tipo: 'BARRA4', Calibre: '75', FibraId: 'FIL', Cuenta: '3030', ...salida }).valid, true);

    const trama = catalogo.processData({ Tipo: 'trama', Calibre: '10.06', FibraId: 'open', Cuenta: '99', ...salida });
    assert.equal(trama.Tipo, 'TRAMA');
    assert.equal(trama.Cuenta, null);
    assert.equal(trama.Calibre, 10.1);

    const barra = catalogo.processData({ Tipo: 'barra3', Calibre: '75', FibraId: 'fil. 370', Cuenta: '2139', ...salida });
    assert.equal(barra.Tipo, 'BARRA3');
    assert.equal(barra.Cuenta, '2139');
    assert.equal(barra.FibraId, 'FIL. 370');
});
