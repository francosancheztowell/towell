// Chart.js aislado del bundle global: solo lo cargan las 4 vistas que dibujan.
// Antes viajaba en `app.js` (~205 KB) y se descargaba en las 107 vistas de layouts.app.
// Las vistas consumidoras construyen sus graficas dentro de DOMContentLoaded, que
// dispara despues de los modulos de Vite, asi que `window.Chart` ya esta listo.
import Chart from 'chart.js/auto';

window.Chart = Chart;
