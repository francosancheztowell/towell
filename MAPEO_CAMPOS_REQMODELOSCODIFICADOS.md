# Tabla de Mapeo: ReqModelosCodificados → Formulario → ReqProgramaTejido

## 📋 Resumen del Flujo:
1. **Origen**: `ReqModelosCodificados` (BD)
2. **Formulario**: Inputs HTML en `create.blade.php`
3. **Destino**: `ReqProgramaTejido` (BD)

---

## 📊 Tabla Completa de Mapeo

| Campo en ReqModelosCodificados | Input HTML (ID) | Campo en ReqProgramaTejido | Observaciones |
|-------------------------------|-----------------|----------------------------|---------------|
| **CAMPOS BÁSICOS** |
| `TamanoClave` | `clave-modelo-input` | `TamanoClave` | Usado para búsqueda |
| `SalonTejidoId` | `salon-select` | `SalonTejidoId` | Select dropdown |
| `FlogsId` | `idflog-select` | `FlogsId` | Select dropdown |
| `Nombre` | `nombre-modelo` | `NombreProducto` | Se mapea a NombreProducto al guardar |
| `NombreProyecto` | `nombre-proyecto` | `NombreProyecto` | - |
| `InventSizeId` | `tamano` | `InventSizeId` | - |
| `Rasurado` | `rasurado` | `Rasurado` | - |
| **RIZO** |
| `CuentaRizo` | `cuenta-rizo` | `CuentaRizo` | - |
| `CalibreRizo` | `calibre-rizo` | `CalibreRizo` | Campo BLANCO (base) |
| `CalibreRizo2` | `calibre-rizo` | `CalibreRizo2` | Campo VERDE (*2) - Prioridad si existe |
| `FibraRizo` | `hilo-rizo` | `FibraRizo` | - |
| **TRAMA** |
| `CalibreTrama` | `calibre-trama` | `CalibreTrama` | Campo BLANCO (base) |
| `CalibreTrama2` | `calibre-trama` | `CalibreTrama2` | Campo VERDE (*2) - Prioridad si existe |
| `CodColorTrama` | `cod-color-1` | `CodColorTrama` | - |
| `ColorTrama` | `nombre-color-1` | `ColorTrama` | - |
| `FibraId` | `hilo-trama` | `FibraTrama` | Se mapea a FibraTrama |
| **PIE** |
| `CalibrePie` | `calibre-pie` | `CalibrePie` | Campo BLANCO (base) |
| `CalibrePie2` | `calibre-pie` | `CalibrePie2` | Campo VERDE (*2) - Prioridad si existe |
| `CuentaPie` | `cuenta-pie` | `CuentaPie` | - |
| `FibraPie` | `hilo-pie` | `FibraPie` | - |
| **COMBINACIONES C1-C5 - CALIBRES** |
| `CalibreComb1` | - | `CalibreComb1` | Campo BLANCO (base) - NO se muestra en formulario |
| `CalibreComb12` | `calibre-c1` | `CalibreComb12` | Campo VERDE (*2) |
| `CalibreComb2` | - | `CalibreComb2` | Campo BLANCO (base) - NO se muestra en formulario |
| `CalibreComb22` | `calibre-c2` | `CalibreComb22` | Campo VERDE (*2) |
| `CalibreComb3` | - | `CalibreComb3` | Campo BLANCO (base) - NO se muestra en formulario |
| `CalibreComb32` | `calibre-c3` | `CalibreComb32` | Campo VERDE (*2) |
| `CalibreComb4` | - | `CalibreComb4` | Campo BLANCO (base) - NO se muestra en formulario |
| `CalibreComb42` | `calibre-c4` | `CalibreComb42` | Campo VERDE (*2) |
| `CalibreComb5` | - | `CalibreComb5` | Campo BLANCO (base) - NO se muestra en formulario |
| `CalibreComb52` | `calibre-c5` | `CalibreComb52` | Campo VERDE (*2) |
| **COMBINACIONES C1-C5 - FIBRAS** |
| `FibraComb1` | `hilo-c1` | `FibraComb1` | - |
| `FibraComb2` | `hilo-c2` | `FibraComb2` | - |
| `FibraComb3` | `hilo-c3` | `FibraComb3` | - |
| `FibraComb4` | `hilo-c4` | `FibraComb4` | - |
| `FibraComb5` | `hilo-c5` | `FibraComb5` | - |
| **COLORES C1-C5** |
| `CodColorC1` | `cod-color-2` | `CodColorComb1` | - |
| `NomColorC1` | `nombre-color-2` | `NombreCC1` | - |
| `CodColorC2` | `cod-color-3` | `CodColorComb2` | - |
| `NomColorC2` | `nombre-color-3` | `NombreCC2` | - |
| `CodColorC3` | `cod-color-4` | `CodColorComb3` | - |
| `NomColorC3` | `nombre-color-4` | `NombreCC3` | - |
| `CodColorC4` | `cod-color-5` | `CodColorComb4` | - |
| `NomColorC4` | `nombre-color-5` | `NombreCC4` | - |
| `CodColorC5` | `cod-color-6` | `CodColorComb5` | - |
| `NomColorC5` | `nombre-color-6` | `NombreCC5` | - |
| **MEDIDAS Y ESPECIFICACIONES** |
| `AnchoToalla` | `ancho` | `AnchoToalla` | - |
| `LargoToalla` | `largo-toalla` | `LargoToalla` | - |
| `PesoCrudo` | `peso-crudo` | `PesoCrudo` | Usado para cálculos |
| `Luchaje` | `luchaje` | `Luchaje` | Usado para cálculos |
| `Peine` | `peine` | `Peine` | - |
| `NoTiras` | `no-tiras` | `NoTiras` | Usado para cálculos |
| `Repeticiones` | `repeticiones` | `Repeticiones` | Usado para cálculos |
| `TotalMarbetes` | - | - | Usado para cálculos (no se guarda) |
| `Total` | `total` | - | Usado para cálculos (no se guarda) |
| `MedidaPlano` | `medida-plano` | `MedidaPlano` | - |
| **OTROS CAMPOS** |
| `PasadasComb1` | - | `PasadasComb1` | No visible en formulario |
| `PasadasComb2` | - | `PasadasComb2` | No visible en formulario |
| `PasadasComb3` | - | `PasadasComb3` | No visible en formulario |
| `PasadasComb4` | - | `PasadasComb4` | No visible en formulario |
| `PasadasComb5` | - | `PasadasComb5` | No visible en formulario |
| `PasadasTrama` | - | `PasadasTrama` | Calculado desde `Total` |
| `Obs` | - | `Observaciones` | Se mapea a Observaciones |
| `EficienciaSTD` | `eficiencia-std` | `EficienciaSTD` | Se calcula o se carga |
| `VelocidadSTD` | `velocidad-std` | `VelocidadSTD` | Se calcula o se carga |
| `Maquina` | `maquina` | `Maquina` | Se genera automáticamente |

---

## 🔄 Lógica de Prioridad para Campos Duplicados

### Campos con versión *2 (verde) y base (blanco):

| Campo Base | Campo *2 | Prioridad en Formulario | Prioridad al Guardar |
|------------|----------|-------------------------|----------------------|
| `CalibreRizo` | `CalibreRizo2` | Se muestra `CalibreRizo2` si existe, sino `CalibreRizo` | Se guardan ambos |
| `CalibrePie` | `CalibrePie2` | Se muestra `CalibrePie2` si existe, sino `CalibrePie` | Se guardan ambos |
| `CalibreTrama` | `CalibreTrama2` | Se muestra `CalibreTrama2` si existe, sino `CalibreTrama` | Se guardan ambos |
| `CalibreComb1` | `CalibreComb12` | Solo se muestra `CalibreComb12` | `CalibreComb1` se guarda desde import |
| `CalibreComb2` | `CalibreComb22` | Solo se muestra `CalibreComb22` | `CalibreComb2` se guarda desde import |
| `CalibreComb3` | `CalibreComb32` | Solo se muestra `CalibreComb32` | `CalibreComb3` se guarda desde import |
| `CalibreComb4` | `CalibreComb42` | Solo se muestra `CalibreComb42` | `CalibreComb4` se guarda desde import |
| `CalibreComb5` | `CalibreComb52` | Solo se muestra `CalibreComb52` | `CalibreComb5` se guarda desde import |

---

## 📝 Notas Importantes:

1. **Campos BLANCOS (base)**: `CalibreComb1-5` NO se muestran en el formulario, pero se guardan en la BD desde la importación de Excel.

2. **Campos VERDES (*2)**: `CalibreComb12-52` SÍ se muestran y se pueden editar en el formulario.

3. **Mapeo en config.js**: El archivo `public/js/programa-tejido/config.js` define el mapeo entre campos de BD e IDs de inputs HTML.

4. **Guardado**: El método `construirPayload()` en `crud-manager.js` recopila todos los datos del formulario y los envía al backend.

5. **Importación Excel**: Durante la importación, los campos base (`CalibreComb1-5`, `CalibreRizo`, `CalibrePie`, `CalibreTrama`) se cargan desde `ReqModelosCodificados`, mientras que los campos *2 se cargan desde el Excel.

---

## 📁 Archivos Involucrados:

- **Frontend**:
  - `public/js/programa-tejido/config.js` - Mapeo de campos
  - `public/js/programa-tejido/form-manager.js` - Carga de datos desde API
  - `public/js/programa-tejido/crud-manager.js` - Construcción de payload para guardar
  - `resources/views/modulos/programa-tejido-nuevo/create.blade.php` - Formulario HTML

- **Backend**:
  - `app/Http/Controllers/ProgramaTejidoController.php` - Endpoint `getDatosRelacionados()`
  - `app/Models/ReqModelosCodificados.php` - Modelo de origen
  - `app/Models/ReqProgramaTejido.php` - Modelo de destino

