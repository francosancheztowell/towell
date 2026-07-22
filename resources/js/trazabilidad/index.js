// jQuery se carga vía Vite (módulo diferido); esperar a DOMContentLoaded para
// garantizar que window.$ ya está definido.
document.addEventListener('DOMContentLoaded', function () {
    const configNode = document.getElementById('trazabilidad-config');
    if (!configNode) return;

    const config = JSON.parse(configNode.textContent || '{}');
    const RUTA = config.rutas?.index || '/trazabilidad';
    const RUTA_REDBOOTH = config.rutas?.redbooth || `${RUTA}/redbooth`;
    const $resultado = $('#resultado');

    const $modalRollos = $('#modal-rollos-maquina');
    if ($modalRollos.length) {
        $modalRollos.appendTo(document.body);
    }

    const $modalFlogImg = $('#modal-flog-imagen');
    if ($modalFlogImg.length) {
        $modalFlogImg.appendTo(document.body);
    }

    const $scrollMain = $('main.app-main');
    let sincronizandoSelects = false;

    function hayModalTrazaAbierto() {
        const rollosAbierto = $modalRollos.length
            && !$modalRollos.hasClass('hidden')
            && $modalRollos.css('display') !== 'none';
        const flogAbierto = $modalFlogImg.length && !$modalFlogImg.hasClass('hidden');
        const $modalTelares = $('#modal-resumen-telares');
        const telaresAbierto = $modalTelares.length
            && !$modalTelares.hasClass('hidden')
            && $modalTelares.css('display') !== 'none';
        return rollosAbierto || flogAbierto || telaresAbierto;
    }

    /** Bloquea/desbloquea según modales visibles (evita contador desfasado tras varias aperturas). */
    function sincronizarScrollPagina() {
        if (!$scrollMain.length) return;
        if (hayModalTrazaAbierto()) {
            $scrollMain.css('overflow-y', 'hidden');
        } else {
            $scrollMain.css('overflow-y', 'auto');
        }
    }

    function liberarScrollPagina() {
        if ($scrollMain.length) {
            $scrollMain.css('overflow-y', 'auto');
        }
    }

    function bloquearScrollPagina() {
        sincronizarScrollPagina();
    }

    function desbloquearScrollPagina() {
        sincronizarScrollPagina();
    }

    /** Cierra Select2, quita foco atrapado y restaura scroll en main (tras filtrar Flog). */
    function restaurarInteraccionScroll() {
        $('.filtro-select').each(function () {
            const $el = $(this);
            if ($el.data('select2')) {
                try {
                    $el.select2('close');
                } catch (e) { /* ignore */ }
            }
        });
        $('.select2-container--open').removeClass('select2-container--open');

        const ae = document.activeElement;
        if (ae && (ae.classList.contains('select2-search__field') || ae.closest('.select2-container'))) {
            ae.blur();
        }

        sincronizarScrollPagina();
        liberarScrollPagina();
    }

    function escaparHtml(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function leerValorSelect($el) {
        const val = $el.val();
        if (val === null || val === undefined) return '';
        if (Array.isArray(val)) return val[0] || '';
        return String(val);
    }

    const flogVisor = {
        src: '',
        titulo: '',
        scale: 1,
        baseScale: 1,
        panX: 0,
        panY: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        panStartX: 0,
        panStartY: 0,
    };

    const $flogStage = () => $modalFlogImg.find('[data-flog-stage]');
    const $flogViewport = () => $modalFlogImg.find('[data-flog-viewport]');
    const $flogImg = () => $modalFlogImg.find('[data-modal-flog-img]');

    function actualizarLabelZoomFlog() {
        const pct = flogVisor.baseScale > 0
            ? Math.round((flogVisor.scale / flogVisor.baseScale) * 100)
            : 100;
        $modalFlogImg.find('[data-flog-zoom-label]').text(pct + '%');
    }

    function aplicarTransformFlog() {
        $flogViewport().css(
            'transform',
            'translate(' + flogVisor.panX + 'px, ' + flogVisor.panY + 'px) scale(' + flogVisor.scale + ')'
        );
        actualizarLabelZoomFlog();
    }

    function ajustarImagenFlogAPantalla() {
        const img = $flogImg()[0];
        const stage = $flogStage()[0];
        if (!img || !stage || !img.naturalWidth || !img.naturalHeight) return;

        const pad = 48;
        const sw = Math.max(stage.clientWidth - pad, 200);
        const sh = Math.max(stage.clientHeight - pad, 200);
        flogVisor.baseScale = Math.min(sw / img.naturalWidth, sh / img.naturalHeight);
        flogVisor.scale = flogVisor.baseScale;
        flogVisor.panX = 0;
        flogVisor.panY = 0;

        $flogImg().css({
            width: img.naturalWidth + 'px',
            height: img.naturalHeight + 'px',
        });
        aplicarTransformFlog();
    }

    function cambiarZoomFlog(factor) {
        const min = flogVisor.baseScale * 0.35;
        const max = flogVisor.baseScale * 12;
        flogVisor.scale = Math.min(max, Math.max(min, flogVisor.scale * factor));
        aplicarTransformFlog();
    }

    function nombreArchivoFlog(src, titulo) {
        try {
            const url = new URL(src, window.location.origin);
            const file = url.searchParams.get('file');
            if (file) return file;
        } catch (e) { /* ignore */ }
        const base = (titulo || 'imagen-flog')
            .replace(/[^\w\s\-\.áéíóúñÁÉÍÓÚÑ]/g, '')
            .trim()
            .replace(/\s+/g, '_')
            .slice(0, 80) || 'imagen-flog';
        return base + '.jpg';
    }

    async function descargarImagenFlog() {
        if (!flogVisor.src) return;
        const nombre = nombreArchivoFlog(flogVisor.src, flogVisor.titulo);
        try {
            const res = await fetch(flogVisor.src, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('No se pudo obtener la imagen');
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nombre;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            window.notify?.success('Descarga iniciada');
        } catch (err) {
            window.notify?.error(err.message || 'No se pudo descargar la imagen');
        }
    }

    function abrirModalFlogImagen(src, titulo) {
        if (!src || !$modalFlogImg.length) return;
        flogVisor.src = src;
        flogVisor.titulo = titulo || 'Imagen';
        flogVisor.scale = 1;
        flogVisor.baseScale = 1;
        flogVisor.panX = 0;
        flogVisor.panY = 0;

        const $img = $flogImg();
        $img.off('load.flogVisor').on('load.flogVisor', function () {
            ajustarImagenFlogAPantalla();
        });
        $img.attr({ src: src, alt: titulo || 'Imagen' });
        if ($img[0]?.complete && $img[0].naturalWidth) {
            ajustarImagenFlogAPantalla();
        }

        $('#modal-flog-imagen-titulo').text(titulo || '');
        $modalFlogImg.removeClass('hidden');
        bloquearScrollPagina();
    }

    function cerrarModalFlogImagen() {
        if (!$modalFlogImg.length || $modalFlogImg.hasClass('hidden')) return;
        $modalFlogImg.addClass('hidden');
        $flogImg().off('load.flogVisor').attr('src', '');
        $flogViewport().css('transform', '');
        flogVisor.src = '';
        flogVisor.dragging = false;
        $flogStage().removeClass('is-dragging');
        desbloquearScrollPagina();
    }

    function marcarImagenFlogRota(img) {
        if (!img || img.classList.contains('is-broken')) return;
        img.classList.add('is-broken');
        const fallback = img.nextElementSibling;
        if (fallback?.classList?.contains('flog-visual-frame__sin-img')) {
            fallback.hidden = false;
        }
        const frame = img.closest('.flog-visual-frame');
        if (frame) {
            frame.removeAttribute('data-flog-zoom');
            frame.style.cursor = 'default';
        }
        const thumbBtn = img.closest('.flog-lineas-thumb');
        if (thumbBtn) {
            const celda = thumbBtn.closest('td');
            thumbBtn.remove();
            if (celda && !celda.textContent.trim()) {
                celda.textContent = '—';
            }
        }
    }

    function initImagenesFlog($root) {
        $root.find('[data-flog-img]').each(function () {
            if (this.complete && this.naturalWidth === 0) {
                marcarImagenFlogRota(this);
            }
        });
    }

    $resultado.on('error', '[data-flog-img]', function () {
        marcarImagenFlogRota(this);
    });

    $resultado.on('click', '[data-flog-zoom]', function () {
        const $el = $(this);
        const src = $el.data('flog-zoom') || $el.find('img').attr('src');
        if (!src) return;
        const titulo = $el.data('flog-zoom-title')
            || $el.find('.flog-visual-frame__caption').text().trim()
            || $el.attr('aria-label')
            || 'Imagen';
        abrirModalFlogImagen(src, titulo);
    });

    $resultado.on('keydown', '[data-flog-zoom]', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    $modalFlogImg.on('click', '[data-modal-flog-close]', cerrarModalFlogImagen);
    $modalFlogImg.on('click', '[data-flog-zoom-in]', function () { cambiarZoomFlog(1.2); });
    $modalFlogImg.on('click', '[data-flog-zoom-out]', function () { cambiarZoomFlog(1 / 1.2); });
    $modalFlogImg.on('click', '[data-flog-zoom-reset]', ajustarImagenFlogAPantalla);
    $modalFlogImg.on('click', '[data-flog-download]', descargarImagenFlog);

    $modalFlogImg.on('click', function (e) {
        if ($(e.target).is('.modal-flog-imagen__backdrop')) {
            cerrarModalFlogImagen();
        }
    });

    $modalFlogImg.on('click', '[data-flog-stage]', function (e) {
        if ($modalFlogImg.hasClass('hidden')) return;
        // Cerrar al hacer click en el área vacía alrededor de la imagen (no sobre la imagen).
        if (e.target.tagName !== 'IMG') {
            cerrarModalFlogImagen();
        }
    });

    $modalFlogImg.on('wheel', '[data-flog-stage]', function (e) {
        if ($modalFlogImg.hasClass('hidden')) return;
        e.preventDefault();
        const factor = e.originalEvent.deltaY < 0 ? 1.12 : 1 / 1.12;
        cambiarZoomFlog(factor);
    });

    $modalFlogImg.on('mousedown', '[data-flog-stage]', function (e) {
        if (e.button !== 0 || e.target.tagName !== 'IMG') return;
        flogVisor.dragging = true;
        flogVisor.dragStartX = e.clientX;
        flogVisor.dragStartY = e.clientY;
        flogVisor.panStartX = flogVisor.panX;
        flogVisor.panStartY = flogVisor.panY;
        $flogStage().addClass('is-dragging');
    });

    $(document).on('mousemove.flogVisor', function (e) {
        if (!flogVisor.dragging || $modalFlogImg.hasClass('hidden')) return;
        flogVisor.panX = flogVisor.panStartX + (e.clientX - flogVisor.dragStartX);
        flogVisor.panY = flogVisor.panStartY + (e.clientY - flogVisor.dragStartY);
        aplicarTransformFlog();
    });

    $(document).on('mouseup.flogVisor', function () {
        if (!flogVisor.dragging) return;
        flogVisor.dragging = false;
        $flogStage().removeClass('is-dragging');
    });

    $(window).on('resize.flogVisor', function () {
        if (!$modalFlogImg.hasClass('hidden')) {
            ajustarImagenFlogAPantalla();
        }
    });

    $('.filtro-select').select2({
        width: '100%',
        placeholder: 'Todos',
        allowClear: true,
        dropdownCssClass: 'traza-select2-dd'
    });

    // ===== Pestañas Trazabilidad / Producción / Flogs =====
    // Las pestañas viven dentro de #resultado (se re-renderiza en cada AJAX), así
    // que el handler es delegado y la pestaña activa se reaplica tras cada carga.
    let tabActivo = 'trazabilidad';
    function aplicarTab(tab) {
        tabActivo = tab;
        const $r = $('#resultado');
        $r.find('[data-pane]').addClass('hidden');
        $r.find('[data-pane="' + tab + '"]').removeClass('hidden');
        $r.find('.traza-tab').each(function () {
            const activo = $(this).data('tab') === tab;
            $(this).toggleClass('text-blue-600 border-blue-600', activo)
                   .toggleClass('text-slate-400 border-transparent hover:text-slate-600', !activo);
        });
    }
    $resultado.on('click', '.traza-tab', function () {
        aplicarTab($(this).data('tab'));
    });

    // Reconstruye un select  (Flog/Tamaño) preservando el valor.
    function rebuildSelect(id, opciones, seleccionado) {
        const $el = $(id);
        const val = seleccionado == null ? '' : String(seleccionado);
        if ($el.data('select2')) {
            try {
                $el.select2('close');
            } catch (e) { /* ignore */ }
        }
        let html = '<option value=""></option>';
        const valores = new Set();
        (opciones || []).forEach(function (v) {
            const s = String(v);
            valores.add(s);
            const sel = s === val ? ' selected' : '';
            html += '<option value="' + escaparHtml(s) + '"' + sel + '>' + escaparHtml(s) + '</option>';
        });
        if (val && !valores.has(val)) {
            html += '<option value="' + escaparHtml(val) + '" selected>' + escaparHtml(val) + '</option>';
        }
        sincronizandoSelects = true;
        $el.html(html);
        $el.val(val || null).trigger('change');
        sincronizandoSelects = false;
    }

    // Reconstruye un select combinado "código / nombre": [{codigo, label}].
    function rebuildCombo(id, opciones, seleccionado) {
        const $el = $(id);
        const val = seleccionado == null ? '' : String(seleccionado);
        if ($el.data('select2')) {
            try {
                $el.select2('close');
            } catch (e) { /* ignore */ }
        }
        let html = '<option value=""></option>';
        let labelSel = val;
        const codigos = new Set();
        (opciones || []).forEach(function (o) {
            const codigo = String(o.codigo);
            codigos.add(codigo);
            const sel = codigo === val ? ' selected' : '';
            if (sel) labelSel = o.label || codigo;
            html += '<option value="' + escaparHtml(codigo) + '"' + sel + '>'
                + escaparHtml(o.label) + '</option>';
        });
        if (val && !codigos.has(val)) {
            const fallback = $el.data('traza-last-label') || val;
            labelSel = fallback;
            html += '<option value="' + escaparHtml(val) + '" selected>' + escaparHtml(fallback) + '</option>';
        }
        sincronizandoSelects = true;
        $el.html(html);
        $el.val(val || null).trigger('change');
        if (val) $el.data('traza-last-label', labelSel);
        sincronizandoSelects = false;
    }

    // Resumen de conteos arriba de los selects (solo si hay Flog).
    function rebuildResumen(counts, hayFlog) {
        const $c = $('#resumen-conteos');
        if (!hayFlog) { $c.addClass('hidden').html(''); return; }
        // n === 1 → singular, si no → plural.
        const item = (n, singular, plural, icon) =>
            '<span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1">'
            + '<i class="fa-solid ' + icon + '"></i>' + n + ' ' + (Number(n) === 1 ? singular : plural) + '</span>';
        $c.removeClass('hidden').html(
            item(counts.articulo, 'artículo', 'artículos', 'fa-box') +
            item(counts.tamano, 'tamaño', 'tamaños', 'fa-ruler')
        );
    }

    // Meses seleccionados (multi) desde el input oculto CSV.
    function mesesSeleccionados() {
        return ($('#filtro-mes').val() || '').split(',').filter(Boolean);
    }

    function hayFiltroPrincipal(f) {
        return !!(f.flog || f.articulo || f.tamano || f.color || f.mes);
    }

    function sincronizarUrl(filtros, metrica) {
        const query = new URLSearchParams();
        ['flog', 'articulo', 'tamano', 'color', 'mes'].forEach(function (campo) {
            const valor = filtros[campo];
            if (valor !== null && valor !== undefined && String(valor).trim() !== '') {
                query.set(campo, String(valor));
            }
        });
        query.set('metrica', metrica === 'peso' ? 'peso' : 'cantidad');

        const qs = query.toString();
        window.history.replaceState(
            { trazabilidad: true },
            '',
            qs ? `${RUTA}?${qs}` : RUTA,
        );
    }

    function formatNum(n, dec) {
        return Number(n || 0).toLocaleString('es-MX', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec,
        });
    }

    function abrirModalRollosMaquina(maquina, filas) {
        const $modal = $('#modal-rollos-maquina');
        if (!$modal.length) return;

        let totalPzas = 0;
        let totalKg = 0;
        let rowsHtml = '';

        (filas || []).forEach(function (f) {
            const pzas = Number(f.cantidad || 0);
            const kg = Number(f.peso || 0);
            totalPzas += pzas;
            totalKg += kg;
            const articulo = [f.articulo, f.nombreArticulo].filter(Boolean).join(' · ');
            const color = [f.color, f.nombreColor].filter(Boolean).join(' · ');
            rowsHtml += '<tr class="border-b border-slate-100 hover:bg-slate-50/80">'
                + '<td class="px-3 py-2 font-mono font-semibold">' + (f.orden || '—') + '</td>'
                + '<td class="px-3 py-2">' + (articulo || '—') + '</td>'
                + '<td class="px-3 py-2">' + (color || '—') + '</td>'
                + '<td class="px-3 py-2 text-right tabular-nums">' + formatNum(pzas, 0) + '</td>'
                + '<td class="px-3 py-2 text-right tabular-nums">' + formatNum(kg, 2) + '</td>'
                + '</tr>';
        });

        $('#modal-rollos-maquina-titulo').text(maquina || 'Detalle máquina');
        $('#modal-rollos-maquina-body').html(rowsHtml);
        $('#modal-rollos-total-pzas').text(formatNum(totalPzas, 0));
        $('#modal-rollos-total-kg').text(formatNum(totalKg, 2));
        $modal.removeClass('hidden').css('display', 'flex');
        bloquearScrollPagina();
    }

    function cerrarModalRollosMaquina() {
        const $modal = $('#modal-rollos-maquina');
        if (!$modal.length || $modal.hasClass('hidden')) return;
        $modal.addClass('hidden').css('display', '');
        desbloquearScrollPagina();
    }

    $resultado.on('click', '.prod-rollos-maquina-card', function () {
        const maquina = $(this).data('maquina');
        let filas = $(this).data('filas');
        if (typeof filas === 'string') {
            try { filas = JSON.parse(filas); } catch (e) { filas = []; }
        }
        abrirModalRollosMaquina(maquina, filas);
    });

    $resultado.on('click', '[data-modal-rollos-close]', cerrarModalRollosMaquina);

    $('#modal-rollos-maquina').on('click', '[data-modal-rollos-close]', cerrarModalRollosMaquina);
    $('#modal-rollos-maquina').on('click', function (e) {
        if (e.target === this) cerrarModalRollosMaquina();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && !$('#modal-rollos-maquina').hasClass('hidden')) {
            cerrarModalRollosMaquina();
        }
        if (e.key === 'Escape' && !$('#modal-resumen-telares').hasClass('hidden')) {
            cerrarModalResumenTelares();
        }
        if (e.key === 'Escape' && !$modalFlogImg.hasClass('hidden')) {
            cerrarModalFlogImagen();
        }
        if (!$modalFlogImg.hasClass('hidden')) {
            if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                cambiarZoomFlog(1.2);
            } else if (e.key === '-') {
                e.preventDefault();
                cambiarZoomFlog(1 / 1.2);
            } else if (e.key === '0') {
                e.preventDefault();
                ajustarImagenFlogAPantalla();
            }
        }
    });

    // Renderiza los badges de meses [{mes, nombre}] en la barra de filtros (multi-select).
    function rebuildMeses(meses) {
        const activos = mesesSeleccionados();
        let html = '';
        (meses || []).forEach(function (m) {
            const esActivo = activos.includes(String(m.mes));
            const cls = esActivo
                ? 'bg-blue-600 border-blue-600 text-white'
                : 'bg-white border-slate-200 text-slate-600 hover:border-blue-400 hover:text-blue-600';
            html += '<a href="#" data-mes="' + m.mes + '" '
                  + 'class="badge-mes inline-flex items-center rounded-full text-xs font-semibold px-3 py-1 border transition-colors ' + cls + '">'
                  + m.nombre + '</a>';
        });
        if (!html) {
            html = '<span class="text-xs text-slate-400 italic">Sin meses para los filtros actuales</span>';
        }
        $('#meses-badges').html(html);
    }

    function valoresActuales() {
        return {
            flog:     leerValorSelect($('#filtro-flog')),
            articulo: leerValorSelect($('#filtro-articulo')),
            tamano:   leerValorSelect($('#filtro-tamano')),
            color:    leerValorSelect($('#filtro-color')),
            mes:      $('#filtro-mes').val() || '',
            metrica:  $('#filtro-metrica').val() || 'cantidad',
        };
    }

    function abrirRegistroRedbooth(registro) {
        const registroId = Number(registro?.registroId || 0);
        if (!registroId || typeof window.abrirModalRedboothProgramaTejido !== 'function') {
            window.notify?.warning('La orden no tiene un registro de Programa Tejido o CatCodificados disponible.');
            return;
        }

        window.abrirModalRedboothProgramaTejido({
            registroId,
            source: registro.source === 'catcodificados' ? 'catcodificados' : 'programa',
            flogAsignacion: registro.flogAsignacion || '',
            totalOrdenes: Number(registro.totalOrdenes || 0),
        });
    }

    async function abrirRedboothDelFlog() {
        const flog = valoresActuales().flog;
        if (!flog) {
            window.notify?.warning('Selecciona un Flog para consultar Redbooth.');
            return;
        }

        const $boton = $('#btn-redbooth');
        const $icono = $boton.find('i');
        $boton.prop('disabled', true);
        $icono.removeClass('fa-comments').addClass('fa-circle-notch fa-spin');

        try {
            const data = await window.http.get(RUTA_REDBOOTH, { params: { flog } });
            const ordenes = Array.isArray(data?.ordenes) ? data.ordenes : [];
            if (!ordenes.length) {
                window.notify?.warning('No se encontraron órdenes vinculadas a este Flog.');
                return;
            }

            if (data?.primerVinculo) {
                abrirRegistroRedbooth(data.primerVinculo);
                return;
            }

            const disponibles = ordenes.filter(orden => Number(orden.registroId || 0) > 0);
            if (!disponibles.length) {
                window.notify?.warning('Las órdenes del Flog no existen en Programa Tejido ni en CatCodificados.');
                return;
            }

            disponibles[0].flogAsignacion = flog;
            disponibles[0].totalOrdenes = disponibles.length;
            abrirRegistroRedbooth(disponibles[0]);
        } catch (error) {
            window.Swal?.fire({
                icon: 'error',
                title: 'No se pudo consultar Redbooth',
                text: error.message || 'Ocurrió un error al buscar las órdenes del Flog.',
            });
        } finally {
            $boton.prop('disabled', false);
            $icono.removeClass('fa-circle-notch fa-spin').addClass('fa-comments');
        }
    }

    // Secuencia de peticiones: matriz primero, producción y flogs después. Solo la
    // respuesta más reciente se aplica (evita condiciones de carrera).
    let reqSeq = 0;
    let prodSeq = 0;
    let flogsSeq = 0;

    let prodFiltroActivo = 'todos';
    let flogLineaFiltroActivo = 'todos';

    function aplicarFiltroLineasFlog(filtro) {
        flogLineaFiltroActivo = filtro || 'todos';
        const $wrap = $('#flogs-contenido .flog-lineas-wrap');
        if (!$wrap.length) return;

        $wrap.find('.flog-lineas-filtro-btn').each(function () {
            $(this).toggleClass('is-active', String($(this).data('flog-linea-filtro')) === String(flogLineaFiltroActivo));
        });

        let visibles = 0;
        const $filas = $wrap.find('.flog-lineas-table tbody tr[data-estado-linea]');
        $filas.each(function () {
            const cod = String($(this).data('estado-linea') ?? '');
            const visible = flogLineaFiltroActivo === 'todos' || cod === String(flogLineaFiltroActivo);
            $(this).toggle(visible);
            if (visible) visibles++;
        });

        $wrap.find('.flog-lineas-sin-filtro').toggleClass('hidden', visibles > 0 || $filas.length === 0);
    }

    $resultado.on('click', '.flog-lineas-filtro-btn', function () {
        aplicarFiltroLineasFlog($(this).data('flog-linea-filtro'));
    });

    $resultado.on('click', '.flog-card__toggle', function () {
        const $btn = $(this);
        const $card = $btn.closest('.flog-card--collapsible');
        if (!$card.length) return;
        const expanded = $card.toggleClass('is-expanded').hasClass('is-expanded');
        $btn.attr('aria-expanded', expanded);
        $btn.attr('title', expanded ? 'Ocultar información general' : 'Mostrar información general');
    });

    function aplicarFiltroProduccion(filter) {
        prodFiltroActivo = filter || 'todos';
        const $crudo = $('#produccion-contenido .prod-area--crudo');
        if (!$crudo.length) return;

        $crudo.find('.prod-filter-btn').each(function () {
            $(this).toggleClass('is-active', $(this).data('filter') === prodFiltroActivo);
        });

        let visibles = 0;

        $crudo.find('.prod-crudo-card').each(function () {
            const visible = prodFiltroActivo === 'todos'
                || $(this).data('estado') === prodFiltroActivo;
            $(this).toggle(visible);
            if (visible) visibles++;
        });

        const totalItems = $crudo.find('.prod-crudo-card').length;
        $crudo.find('.prod-sin-resultados').toggle(visibles === 0 && totalItems > 0);
    }

    $resultado.on('click', '.prod-filter-btn', function () {
        aplicarFiltroProduccion($(this).data('filter'));
    });

    function abrirModalResumenTelares() {
        const $modal = $('#modal-resumen-telares');
        if (!$modal.length) return;
        $modal.removeClass('hidden').css('display', 'flex');
        bloquearScrollPagina();
    }

    function cerrarModalResumenTelares() {
        const $modal = $('#modal-resumen-telares');
        if (!$modal.length || $modal.hasClass('hidden')) return;
        $modal.addClass('hidden').css('display', '');
        desbloquearScrollPagina();
    }

    $resultado.on('click', '[data-abrir-modal-telares]', abrirModalResumenTelares);
    $resultado.on('click', '[data-modal-resumen-telares-close]', cerrarModalResumenTelares);

    function actualizarBadgeProduccion(cantidad) {
        const $tab = $('#resultado .traza-tab[data-tab="produccion"]');
        $tab.find('.prod-alert-badge').remove();
        if (cantidad > 0) {
            $tab.append(
                '<span class="prod-alert-badge inline-flex items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold min-w-4 h-4 px-1"'
                + ' title="' + cantidad + ' orden(es) con producción en otro telar">' + cantidad + '</span>'
            );
        }
    }

    async function cargarFlogs(params, seqMatriz) {
        const seq = ++flogsSeq;
        try {
            const data = await window.http.get(RUTA, { params: { ...params, part: 'flogs' } });
            if (seq !== flogsSeq || seqMatriz !== reqSeq) return;
            const $cont = $('#flogs-contenido');
            if ($cont.length) {
                $cont.html(data.flogsHtml);
                flogLineaFiltroActivo = 'todos';
                initImagenesFlog($cont);
            }
            restaurarInteraccionScroll();
        } catch (err) {
            if (seq === flogsSeq && seqMatriz === reqSeq) {
                const $cont = $('#flogs-contenido');
                if ($cont.length) {
                    $cont.html(
                        '<div class="bg-white border border-red-200 rounded-2xl p-8 text-center">'
                        + '<p class="text-red-600 font-semibold">No se pudo cargar la información del Flog.</p>'
                        + '<p class="text-slate-400 text-sm mt-1">' + (err.message || '') + '</p></div>'
                    );
                }
            }
        }
    }

    async function cargarProduccion(params, seqMatriz) {
        const seq = ++prodSeq;
        try {
            const data = await window.http.get(RUTA, { params: { ...params, part: 'produccion' } });
            if (seq !== prodSeq || seqMatriz !== reqSeq) return;
            const $cont = $('#produccion-contenido');
            if ($cont.length) {
                $cont.html(data.produccionHtml);
                aplicarFiltroProduccion(prodFiltroActivo);
            }
            actualizarBadgeProduccion(data.prodAlertas || 0);
            restaurarInteraccionScroll();
        } catch (err) {
            if (seq === prodSeq && seqMatriz === reqSeq) {
                const $cont = $('#produccion-contenido');
                if ($cont.length) {
                    $cont.html(
                        '<div class="bg-white border border-red-200 rounded-2xl p-8 text-center">'
                        + '<p class="text-red-600 font-semibold">No se pudo cargar la producción.</p>'
                        + '<p class="text-slate-400 text-sm mt-1">' + (err.message || '') + '</p></div>'
                    );
                }
            }
        }
    }

    async function aplicar(params) {
        const seq = ++reqSeq;
        prodSeq++;
        flogsSeq++;
        $resultado.css('opacity', 0.5);
        try {
            const data = await window.http.get(RUTA, { params: { ...params, part: 'matriz' } });
            if (seq !== reqSeq) return;
            $resultado.html(data.resultado);

            sincronizandoSelects = true;
            rebuildSelect('#filtro-flog', data.opciones.flog, data.filtros.flog);
            rebuildCombo('#filtro-articulo', data.opciones.articulo, data.filtros.articulo);
            rebuildSelect('#filtro-tamano', data.opciones.tamano, data.filtros.tamano);
            rebuildCombo('#filtro-color', data.opciones.color, data.filtros.color);
            sincronizandoSelects = false;

            $('#filtro-mes').val(data.filtros.mes || '');
            rebuildMeses(data.opciones.mes);
            rebuildResumen({
                articulo: (data.opciones.articulo || []).length,
                tamano:   (data.opciones.tamano || []).length,
                color:    (data.opciones.color || []).length,
            }, !!data.filtros.flog);
            $('#btn-redbooth')
                .toggleClass('hidden', !data.filtros.flog)
                .toggleClass('flex', !!data.filtros.flog);
            sincronizarUrl(data.filtros, params.metrica);

        } catch (err) {
            if (seq === reqSeq) window.notify?.error(err.message || 'Error al cargar la trazabilidad');
        } finally {
            if (seq === reqSeq) {
                $resultado.css('opacity', '');
                restaurarInteraccionScroll();
            }
        }
    }

    function envolverDetalle(titulo, contenido) {
        return '<section class="space-y-4">'
            + '<header class="flex items-center justify-between gap-3">'
            + '<div><p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Detalle</p>'
            + '<h2 class="text-xl font-bold text-slate-800">' + escaparHtml(titulo) + '</h2></div>'
            + '<button type="button" data-volver-resumen '
            + 'class="inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-bold text-white hover:bg-blue-600">'
            + '<i class="fa-solid fa-arrow-left"></i> Volver al resumen</button></header>'
            + '<div>' + contenido + '</div></section>';
    }

    async function abrirDetalleResumen(tipo) {
        if (tipo === 'ventas') {
            const ventas = '<div class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">'
                + '<i class="fa-solid fa-receipt text-3xl text-slate-300"></i>'
                + '<p class="mt-4 font-bold text-slate-600">Detalle de ventas pendiente de conexión</p>'
                + '<p class="mt-1 text-sm text-slate-400">Esta pantalla es únicamente frontend por el momento.</p></div>';
            $resultado.html(envolverDetalle('Ventas', ventas));
            return;
        }

        const titulos = {
            flogs: 'Flog',
            trazabilidad: 'Trazabilidad',
            produccion: 'Producción',
        };
        $resultado.css('opacity', 0.5);
        try {
            const params = { ...valoresActuales(), part: tipo };
            const data = await window.http.get(RUTA, { params });
            let html = tipo === 'flogs'
                ? data.flogsHtml
                : (tipo === 'produccion' ? data.produccionHtml : data.detalleHtml);
            if (tipo === 'flogs') html = '<div id="flogs-contenido">' + (html || '') + '</div>';
            if (tipo === 'produccion') html = '<div id="produccion-contenido">' + (html || '') + '</div>';
            $resultado.html(envolverDetalle(titulos[tipo] || 'Detalle', html || ''));
            if (tipo === 'flogs') initImagenesFlog($resultado);
            if (tipo === 'produccion') aplicarFiltroProduccion('todos');
            restaurarInteraccionScroll();
        } catch (err) {
            window.notify?.error(err.message || 'No se pudo cargar el detalle.');
        } finally {
            $resultado.css('opacity', '');
        }
    }

    $resultado.on('click', '[data-resumen-detalle]', function () {
        abrirDetalleResumen(String($(this).data('resumen-detalle') || ''));
    });

    $resultado.on('click', '[data-volver-resumen]', function () {
        aplicar(valoresActuales());
    });

    function programarAplicarFiltros() {
        clearTimeout(debounceFiltro);
        debounceFiltro = setTimeout(function () {
            if (sincronizandoSelects) return;
            aplicar(valoresActuales());
        }, 100);
    }

    // select2:select guarda etiqueta; change dispara aplicar (select2:clear también dispara change).
    let debounceFiltro = null;
    $('.filtro-select').on('select2:select', function (e) {
        if (sincronizandoSelects) return;
        if (e.params && e.params.data) {
            $(this).data('traza-last-label', e.params.data.text || '');
        }
    });
    $('.filtro-select').on('select2:clear', function () {
        if (sincronizandoSelects) return;
        $(this).removeData('traza-last-label');
    });
    $('.filtro-select').on('change', function () {
        if (sincronizandoSelects) return;
        programarAplicarFiltros();
    });
    $('.filtro-select').on('select2:close', function () {
        setTimeout(restaurarInteraccionScroll, 0);
    });

    function actualizarTogglePeriodo($toggle, abierto) {
        $toggle.attr('aria-expanded', abierto ? 'true' : 'false');
        $toggle.find('.periodo-caret').toggleClass('rotate-90', abierto);
    }

    function marcarSubtotalPeriodo(nivel, key, abierto) {
        const atributo = nivel === 'mes' ? 'data-mes-key' : 'data-semana-key';
        $resultado.find('[data-periodo-nivel="' + nivel + '"][' + atributo + '="' + key + '"]')
            .toggleClass('traza-periodo-subtotal-abierto', abierto);
    }

    function actualizarBotonExpandirPeriodos() {
        const $boton = $resultado.find('[data-expandir-periodos]');
        if (!$boton.length) return;

        const $toggles = $resultado.find('[data-periodo-toggle]');
        const todosAbiertos = $toggles.length > 0 && $toggles.toArray().every(function (toggle) {
            return $(toggle).attr('aria-expanded') === 'true';
        });

        $boton.attr('aria-expanded', todosAbiertos ? 'true' : 'false');
        $boton.find('[data-expandir-periodos-label]').text(todosAbiertos ? 'Contraer todo' : 'Expandir todo');
        $boton.find('i').toggleClass('fa-expand', !todosAbiertos).toggleClass('fa-compress', todosAbiertos);
    }

    function cambiarSemana(semanaKey, abrir) {
        $resultado.find('[data-periodo-nivel="dia"][data-semana-key="' + semanaKey + '"]')
            .toggleClass('hidden', !abrir);

        const $toggle = $resultado.find('[data-periodo-toggle="semana"][data-periodo-key="' + semanaKey + '"]');
        actualizarTogglePeriodo($toggle, abrir);
        marcarSubtotalPeriodo('semana', semanaKey, abrir);
    }

    function cambiarMes(mesKey, abrir) {
        const $semanas = $resultado.find('[data-periodo-nivel="semana"][data-mes-key="' + mesKey + '"]');
        $semanas.toggleClass('hidden', !abrir);

        const $toggle = $resultado.find('[data-periodo-toggle="mes"][data-periodo-key="' + mesKey + '"]');
        actualizarTogglePeriodo($toggle, abrir);
        marcarSubtotalPeriodo('mes', mesKey, abrir);

        if (!abrir) {
            $resultado.find('[data-periodo-nivel="semana"][data-mes-key="' + mesKey + '"] [data-periodo-toggle="semana"]').each(function () {
                cambiarSemana(String($(this).data('periodo-key')), false);
            });
        }
    }

    // Las fechas se muestran como un árbol de columnas: mes -> semana -> día.
    // La delegación mantiene los controles activos después de cada respuesta AJAX.
    $resultado.on('click', '[data-periodo-toggle]', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $toggle = $(this);
        const nivel = String($toggle.data('periodo-toggle'));
        const key = String($toggle.data('periodo-key'));
        const abrir = $toggle.attr('aria-expanded') !== 'true';

        if (nivel === 'mes') {
            cambiarMes(key, abrir);
        } else {
            cambiarSemana(key, abrir);
        }

        actualizarBotonExpandirPeriodos();
    });

    $resultado.on('click', '[data-expandir-periodos]', function () {
        const abrir = $(this).attr('aria-expanded') !== 'true';

        $resultado.find('[data-periodo-nivel="semana"], [data-periodo-nivel="dia"]')
            .toggleClass('hidden', !abrir);
        $resultado.find('[data-periodo-toggle]').each(function () {
            actualizarTogglePeriodo($(this), abrir);
        });
        $resultado.find('[data-periodo-nivel="mes"], [data-periodo-nivel="semana"]')
            .toggleClass('traza-periodo-subtotal-abierto', abrir);
        actualizarBotonExpandirPeriodos();
    });

    // Dropdown por área: al hacer click en la fila se muestran/ocultan sus sub-filas
    // de desglose por artículo/color. Se delega en #resultado porque su contenido
    // se reemplaza completo en cada respuesta AJAX.
    $resultado.on('click', '.area-fila', function () {
        const key = $(this).data('area-key');
        const abierto = $(this).hasClass('area-abierta');
        $(this).toggleClass('area-abierta');
        $(this).find('.area-caret').toggleClass('rotate-90', !abierto);
        $resultado.find('tr.detalle-fila[data-area-key="' + key + '"]').toggleClass('hidden', abierto);
    });

    // Click en un badge de mes (multi-select: agrega/quita ese mes).
    $('#meses-badges').on('click', '.badge-mes', function (e) {
        e.preventDefault();
        const m = String($(this).data('mes'));
        let sel = mesesSeleccionados();
        sel = sel.includes(m) ? sel.filter(function (x) { return x !== m; }) : sel.concat(m);
        $('#filtro-mes').val(sel.join(','));
        aplicar(valoresActuales());
    });

    // Render inicial de los badges de meses (desde los datos del servidor).
    rebuildMeses(config.mesesDisponibles || []);

    // Render inicial del resumen de conteos.
    rebuildResumen(config.conteosIniciales || {}, Boolean(config.hayFlog));

    // Switch Material / Kilos: cambia la métrica de la matriz (sin recargar).
    $('.btn-metrica').on('click', function () {
        const metrica = $(this).data('metrica');
        $('#filtro-metrica').val(metrica);
        // Estado visual del segmentado (incluye clases de hover correctas).
        $('.btn-metrica').removeClass('bg-blue-600 text-white hover:bg-blue-700')
                         .addClass('bg-white text-slate-600 hover:bg-slate-50');
        $(this).removeClass('bg-white text-slate-600 hover:bg-slate-50')
               .addClass('bg-blue-600 text-white hover:bg-blue-700');
        aplicar(valoresActuales());
    });

    $('#btn-redbooth').on('click', abrirRedboothDelFlog);

    // Botón Restablecer del navbar: limpia todos los filtros (sin recargar).
    // La métrica (Material/Kilos) NO se resetea, es una preferencia de visualización.
    $('#btn-restablecer').on('click', function () {
        sincronizandoSelects = true;
        $('.filtro-select').val(null).trigger('change');
        sincronizandoSelects = false;
        $('#filtro-mes').val('');
        aplicar({
            flog: '', articulo: '', tamano: '', color: '', mes: '',
            metrica: $('#filtro-metrica').val() || 'cantidad',
        });
    });

    // Garantizar scroll libre al cargar y si algún modal quedó mal cerrado.
    liberarScrollPagina();
    $(window).on('pageshow.trazaScroll', liberarScrollPagina);
    $(document).on('visibilitychange.trazaScroll', function () {
        if (!document.hidden) {
            restaurarInteraccionScroll();
        }
    });
    // Si el scroll quedó bloqueado sin modal abierto (p. ej. tras cambiar Flog), recuperar al intentar bajar.
    document.addEventListener('wheel', function () {
        if (!hayModalTrazaAbierto() && $scrollMain.length && $scrollMain.css('overflow-y') === 'hidden') {
            restaurarInteraccionScroll();
        }
    }, { passive: true, capture: true });
});
