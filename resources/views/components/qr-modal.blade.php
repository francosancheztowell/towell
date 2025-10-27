{{-- resources/views/components/qr-modal.blade.php (versión optimizada para tablets) --}}
@props([
  'id' => 'qr-video-container',
  'title' => 'Escanea tu código…',
  'autoStart' => true,
])

<div
  id="{{ $id }}"
  class="fixed inset-0 z-50 hidden items-center justify-center bg-black/90"
  role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
  style="padding-left: env(safe-area-inset-left); padding-right: env(safe-area-inset-right); padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom);"
>
  <div class="relative w-full max-w-xl md:max-w-2xl lg:max-w-3xl mx-4">
    <!-- Contenedor de cámara -->
    <div class="relative rounded-2xl overflow-hidden shadow-2xl ring-1 ring-white/10">
      <video id="{{ $id }}-video"
             class="w-full h-auto bg-black"
             autoplay muted playsinline
             aria-label="Visor de cámara para escanear QR">
      </video>

      <!-- Overlay ROI -->
      <div class="pointer-events-none absolute inset-0 grid place-items-center">
        <!-- El tamaño del marco se adapta vía CSS + JS (tablet-friendly) -->
        <div id="{{ $id }}-frame" class="relative rounded-2xl border border-white/70 backdrop-blur-[1px] frame-size">
          <!-- Esquinas -->
          <div class="absolute inset-0">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
          </div>
          <!-- Línea de escaneo -->
          <div class="scanline"></div>
        </div>
      </div>

      <!-- Mensajes -->
      <div class="pointer-events-none absolute inset-x-0 bottom-0 p-4 md:p-5 space-y-2">
        <div id="{{ $id }}-msg"
             class="mx-auto w-max max-w-[92%] rounded-lg bg-black/60 px-3 md:px-4 py-2 md:py-2.5 text-white text-sm md:text-base text-center">
          {{ $title }}
        </div>
        <div class="mx-auto w-max max-w-[92%] rounded-lg bg-black/40 px-3 md:px-4 py-2 text-white/80 text-xs md:text-sm text-center">
          Centra el QR dentro del marco
        </div>
      </div>

      <!-- Controles táctiles -->
      <div class="absolute top-3 right-3 flex gap-2 pointer-events-auto">
        <!-- Cambiar cámara -->
        <button type="button" id="{{ $id }}-switch"
          class="ctrl-btn" title="Cambiar cámara" aria-label="Cambiar cámara">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  d="M3 7h3l2-2h8l2 2h3v10H3V7zM8 12h8"/>
          </svg>
          <span class="ctrl-text">Cámara</span>
        </button>

        <!-- Flash -->
        <button type="button" id="{{ $id }}-torch"
          class="ctrl-btn hidden" title="Flash" aria-label="Flash">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  d="M7 2l10 9h-7l4 9L7 11h7L7 2z"/>
          </svg>
          <span class="ctrl-text">Flash</span>
        </button>

        <!-- Cerrar -->
        <button type="button" id="{{ $id }}-close"
          class="ctrl-btn danger" title="Cerrar" aria-label="Cerrar">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"/>
          </svg>
          <span class="ctrl-text">Cerrar</span>
        </button>
      </div>

      <!-- Zoom -->
      <div class="absolute bottom-28 md:bottom-32 right-3 pointer-events-auto flex items-center gap-3">
        <label for="{{ $id }}-zoom" class="hidden md:block text-white/80 text-xs">Zoom</label>
        <input type="range" id="{{ $id }}-zoom"
               class="zoom-range hidden w-40 md:w-56 accent-sky-400 cursor-pointer"
               min="1" max="5" step="0.1" value="1" aria-label="Zoom">
      </div>

      <!-- Indicador de orientación (tablets) -->
      <div id="{{ $id }}-orient"
           class="orientation-hint hidden">
        <div class="hint-card">
          <svg class="hint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="6" width="18" height="12" rx="2" ry="2" stroke-width="2"/>
            <path d="M12 8v8" stroke-width="2"/>
          </svg>
          <div class="hint-text">
            Gira tu tablet a <strong>horizontal</strong> para una mejor lectura.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  /* --- Tamaños y controles adaptados a tablets --- */
  .frame-size {
    width: min(78vmin, 68vw);    /* más grande en tablets */
    height: min(78vmin, 68vw);
  }
  @media (min-width: 768px) {    /* md+: tablets en adelante */
    .frame-size {
      width: min(75vmin, 62vw);
      height: min(75vmin, 62vw);
    }
  }

  .corner { position:absolute; width: 14%; height:14%; border-color:#38bdf8; }
  .corner.tl { top:0; left:0; border-top:5px solid; border-left:5px solid; border-top-left-radius:14px; }
  .corner.tr { top:0; right:0; border-top:5px solid; border-right:5px solid; border-top-right-radius:14px; }
  .corner.bl { bottom:0; left:0; border-bottom:5px solid; border-left:5px solid; border-bottom-left-radius:14px; }
  .corner.br { bottom:0; right:0; border-bottom:5px solid; border-right:5px solid; border-bottom-right-radius:14px; }

  @keyframes scan {
    0% { top: 12%; opacity:.25 }
    10% { opacity:1 }
    50% { top: 88%; opacity:1 }
    100% { top: 12%; opacity:.25 }
  }
  .scanline {
    position:absolute; left:6%; right:6%;
    height:2px; background:rgba(255,255,255,.9);
    box-shadow:0 0 12px rgba(255,255,255,.9);
    animation: scan 2.6s linear infinite;
  }

  .ctrl-btn {
    display:inline-flex; align-items:center; gap:.5rem;
    padding:.6rem .9rem; border-radius:9999px;
    background:rgba(255,255,255,.14); color:#fff;
    box-shadow:0 2px 14px rgba(0,0,0,.25);
    border:1px solid rgba(255,255,255,.22);
    transition:background .2s, transform .1s;
    touch-action:manipulation; -webkit-tap-highlight-color:transparent;
  }
  .ctrl-btn:hover { background: rgba(255,255,255,.24); }
  .ctrl-btn:active { transform: scale(.98); }
  .ctrl-btn.danger { background:#dc2626; border-color:#ef4444; }
  .ctrl-btn.danger:hover { background:#b91c1c; }
  .ctrl-text { display:none; font-size:.8rem }
  @media (min-width: 768px) { .ctrl-text{ display:inline } }

  .icon { width:22px; height:22px }

  .zoom-range { height: 32px }

  .orientation-hint {
    position:absolute; inset:0; display:grid; place-items:center;
    background: rgba(0,0,0,.45);
    pointer-events: none;
  }
  .hint-card {
    display:flex; align-items:center; gap:.8rem;
    background: rgba(15,23,42,.9);
    border: 1px solid rgba(255,255,255,.15);
    color:white; padding: .9rem 1.1rem; border-radius: 14px;
    box-shadow: 0 6px 28px rgba(0,0,0,.35);
  }
  .hint-icon { width:28px; height:28px }
  .hint-text { font-size: .95rem }
</style>

<script>
(() => {
  class QRScanner {
    constructor(rootId) {
      this.root = document.getElementById(rootId);
      this.video = this.root.querySelector('#{{ $id }}-video');
      this.msg   = this.root.querySelector('#{{ $id }}-msg');
      this.btnClose  = this.root.querySelector('#{{ $id }}-close');
      this.btnSwitch = this.root.querySelector('#{{ $id }}-switch');
      this.btnTorch  = this.root.querySelector('#{{ $id }}-torch');
      this.rngZoom   = this.root.querySelector('#{{ $id }}-zoom');
      this.orientHint= this.root.querySelector('#{{ $id }}-orient');

      this.stream = null;
      this.track  = null;
      this.scanning = false;
      this.facing = 'user'; // frontal por defecto
      this.sameHits = 0; this.lastHit = null;

      // Canvas reutilizable y escalado por DPR (mejor nitidez en tablets)
      this.canvas = document.createElement('canvas');
      this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });

      this.barcode = ('BarcodeDetector' in window)
        ? new BarcodeDetector({ formats: ['qr_code'] })
        : null;

      // Wake Lock (evitar que la pantalla se apague en tablets)
      this.wakeLock = null;

      // Bindings
      this._loop = this._loop.bind(this);
      this._onVisibility = this._onVisibility.bind(this);
      this._onResize = this._onResize.bind(this);

      // UI
      this.btnClose.addEventListener('click', () => this.stop());
      this.btnSwitch.addEventListener('click', async () => {
        this.facing = (this.facing === 'user') ? 'environment' : 'user';
        await this.restart();
      });

      document.addEventListener('visibilitychange', this._onVisibility);
      window.addEventListener('resize', this._onResize);
      window.addEventListener('orientationchange', this._onResize);
    }

    async start() {
      this.root.classList.remove('hidden');
      this.root.classList.add('flex');
      await this._startStream();
      this._updateOrientationHint();
      await this._requestWakeLock();
      this.scanning = true;
      this._loop();
    }

    async restart() {
      await this._stopStreamOnly();
      await this._startStream();
      this.scanning = true;
    }

    async _startStream() {
      const constraints = {
        video: {
          facingMode: { ideal: this.facing },
          width:  { ideal: 1920 },  // mejor lectura de QR chicos en tablets
          height: { ideal: 1080 },
          advanced: [{ focusMode: 'continuous' }]
        },
        audio: false
      };

      try {
        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
        this.video.srcObject = this.stream;
        this.track = this.stream.getVideoTracks()[0];
        await this.video.play();

        // Ajuste de espejo (solo frontal)
        this.video.style.transform = (this.facing === 'user') ? 'scaleX(-1)' : 'none';

        // Torch/Zoom si están disponibles
        const caps = this.track.getCapabilities?.() || {};
        const sets = this.track.getSettings?.() || {};

        const hasTorch = caps.torch === true;
        this.btnTorch.classList.toggle('hidden', !hasTorch);
        if (hasTorch) {
          this.btnTorch.onclick = async () => {
            const s = this.track.getSettings?.() || {};
            await this.track.applyConstraints({ advanced: [{ torch: !s.torch }] });
          };
        }

        if (caps.zoom) {
          this.rngZoom.classList.remove('hidden');
          this.rngZoom.min  = caps.zoom.min ?? 1;
          this.rngZoom.max  = caps.zoom.max ?? 5;
          this.rngZoom.step = caps.zoom.step ?? 0.1;
          this.rngZoom.value = sets.zoom ?? this.rngZoom.min;
          this.rngZoom.oninput = () =>
            this.track.applyConstraints({ advanced: [{ zoom: Number(this.rngZoom.value) }] });
        } else {
          this.rngZoom.classList.add('hidden');
        }

        this._setMsg('Apunta el código al marco');
        this._resizeCanvasForDPR();
      } catch (err) {
        console.error('Acceso cámara:', err);
        this._setMsg('No se pudo acceder a la cámara. Revisa permisos.');
        throw err;
      }
    }

    _resizeCanvasForDPR() {
      // Escala el canvas a DPR (nitidez) respetando el frame actual
      const vw = this.video.videoWidth || 1280;
      const vh = this.video.videoHeight || 720;
      const dpr = Math.min(window.devicePixelRatio || 1, 2); // limitar a 2 por rendimiento en tablets
      this.canvas.width = Math.floor(vw * dpr);
      this.canvas.height = Math.floor(vh * dpr);
      this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    async _stopStreamOnly() {
      this.scanning = false;
      if (this.stream) {
        this.stream.getTracks().forEach(t => t.stop());
        this.stream = null;
        this.track = null;
      }
    }

    async stop() {
      this.scanning = false;
      await this._stopStreamOnly();
      await this._releaseWakeLock();
      this.root.classList.add('hidden');
      this.root.classList.remove('flex');
    }

    _onVisibility() {
      if (document.hidden) {
        this.scanning = false;
        this._releaseWakeLock();
      } else if (!this.root.classList.contains('hidden')) {
        this.scanning = true;
        this._requestWakeLock();
        this._loop();
      }
    }

    _onResize() {
      this._updateOrientationHint();
      this._resizeCanvasForDPR();
    }

    _updateOrientationHint() {
      // En tablets, sugiere horizontal cuando altura > anchura (portrait)
      const isTablet = Math.min(window.innerWidth, window.innerHeight) >= 600;
      const isPortrait = window.innerHeight > window.innerWidth;
      const show = isTablet && isPortrait;
      this.orientHint.classList.toggle('hidden', !show);
    }

    _setMsg(txt) { if (this.msg) this.msg.textContent = txt; }

    async _loop() {
      if (!this.scanning) return;
      if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
        try { await this._scanFrame(); } catch {}
      }
      requestAnimationFrame(this._loop);
    }

    async _scanFrame() {
      const vw = this.video.videoWidth;
      const vh = this.video.videoHeight;
      if (!vw || !vh) return;

      // ROI tablet-friendly (70% del lado menor en tablets, 60% en phones)
      const isTablet = Math.min(window.innerWidth, window.innerHeight) >= 600;
      const ratio = isTablet ? 0.70 : 0.60;
      const side = Math.floor(Math.min(vw, vh) * ratio);
      const sx = Math.floor((vw - side) / 2);
      const sy = Math.floor((vh - side) / 2);

      // Espejo sólo si frontal
      if (this.facing === 'user') {
        this.ctx.setTransform(-1, 0, 0, 1, side, 0);
      } else {
        this.ctx.setTransform(1, 0, 0, 1, 0, 0);
      }

      // Dibuja ROI al canvas escalado
      this.ctx.drawImage(this.video, sx, sy, side, side, 0, 0, side, side);
      this.ctx.setTransform(1, 0, 0, 1, 0, 0);

      // 1) Nativo
      if (this.barcode) {
        try {
          const blob = await new Promise(res => this.canvas.toBlob(res, 'image/png', .9));
          const bmp = await createImageBitmap(blob);
          const codes = await this.barcode.detect(bmp);
          if (codes?.length) return this._maybeConfirm(codes[0].rawValue);
        } catch { /* fallback */ }
      }

      // 2) jsQR
      if (typeof jsQR === 'function') {
        const img = this.ctx.getImageData(0, 0, side, side);
        const qr = jsQR(img.data, side, side, { inversionAttempts: 'attemptBoth' });
        if (qr?.data) return this._maybeConfirm(qr.data);
      }
    }

    async _maybeConfirm(value) {
      if (value === this.lastHit) this.sameHits++;
      else { this.lastHit = value; this.sameHits = 1; }

      if (this.sameHits >= 2) {
        this.scanning = false;
        this._setMsg('Verificando código…');
        await this._authenticate(value);
      }
    }

    async _authenticate(qrData) {
      try {
        const resp = await fetch('/login-qr', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ numero_empleado: qrData })
        });
        const data = await resp.json();
        if (data.success) {
          this._setMsg('¡Acceso exitoso! Redirigiendo…');
          setTimeout(() => window.location.href = '/produccionProceso', 700);
        } else {
          this._setMsg(data.message ? `Error: ${data.message}` : 'Código inválido. Reintenta.');
          setTimeout(() => this.restart(), 900);
        }
      } catch (e) {
        console.error('Auth error:', e);
        this._setMsg('Error de red. Reintentando…');
        setTimeout(() => this.restart(), 1100);
      }
    }

    async _requestWakeLock() {
      try {
        if ('wakeLock' in navigator) {
          this.wakeLock = await navigator.wakeLock.request('screen');
          this.wakeLock.addEventListener?.('release', () => {});
        }
      } catch {}
    }
    async _releaseWakeLock() {
      try { await this.wakeLock?.release(); this.wakeLock = null; } catch {}
    }
  }

  // API global mínima
  window.__qrScanners = window.__qrScanners || {};
  window.openQRModal = (modalId) => {
    if (!window.__qrScanners[modalId]) window.__qrScanners[modalId] = new QRScanner(modalId);
    window.__qrScanners[modalId].start();
  };
  window.closeQRModal = (modalId) => window.__qrScanners[modalId]?.stop();
})();
</script>

@if($autoStart)
<script>
  // Auto-abrir en móviles/tablets si la pestaña activa es QR (opcional)
  document.addEventListener('DOMContentLoaded', () => {
    const isMobileOrTablet = /Android|iPhone|iPad|iPod|Windows Phone|webOS/i.test(navigator.userAgent);
    if (isMobileOrTablet) {
      setTimeout(() => {
        const activeTab = document.querySelector('.tabs-container')?.dataset.active;
        if (activeTab === 'qr') openQRModal('{{ $id }}');
      }, 500);
    }
  });
</script>
@endif
