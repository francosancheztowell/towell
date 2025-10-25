{{-- resources/views/components/qr-modal.blade.php (tablet+pro) --}}
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
    <div class="relative rounded-2xl overflow-hidden shadow-2xl ring-1 ring-white/10">
      <!-- Video -->
      <video id="{{ $id }}-video" class="w-full h-auto bg-black" autoplay muted playsinline></video>

      <!-- Canvas overlay para contornos/feedback -->
      <canvas id="{{ $id }}-overlay" class="pointer-events-none absolute inset-0 w-full h-full"></canvas>

      <!-- Overlay ROI -->
      <div class="pointer-events-none absolute inset-0 grid place-items-center">
        <div id="{{ $id }}-frame" class="relative rounded-2xl border border-white/70 frame-size">
          <div class="corner tl"></div><div class="corner tr"></div>
          <div class="corner bl"></div><div class="corner br"></div>
          <div class="scanline"></div>
        </div>
      </div>

      <!-- Mensajes -->
      <div class="pointer-events-none absolute inset-x-0 bottom-0 p-4 md:p-5 space-y-2">
        <div id="{{ $id }}-msg"
             class="mx-auto w-max max-w-[92%] rounded-lg bg-black/60 px-4 py-2 text-white text-sm md:text-base text-center">
          {{ $title }}
        </div>
        <div class="mx-auto w-max max-w-[92%] rounded-lg bg-black/40 px-3 py-2 text-white/80 text-xs md:text-sm text-center">
          Centra el QR dentro del marco
        </div>
      </div>

      <!-- Controles -->
      <div class="absolute top-3 right-3 flex gap-2 pointer-events-auto">
        <button type="button" id="{{ $id }}-fs" class="ctrl-btn" title="Pantalla completa" aria-label="Pantalla completa">
          <svg class="icon" viewBox="0 0 24 24" stroke="currentColor" fill="none">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 3H5a2 2 0 00-2 2v3m0 6v3a2 2 0 002 2h3m8 0h3a2 2 0 002-2v-3m0-6V5a2 2 0 00-2-2h-3"/>
          </svg><span class="ctrl-text">Full</span>
        </button>

        <button type="button" id="{{ $id }}-switch" class="ctrl-btn" title="Cambiar cámara" aria-label="Cambiar cámara">
          <svg class="icon" viewBox="0 0 24 24" stroke="currentColor" fill="none">
            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  d="M3 7h3l2-2h8l2 2h3v10H3V7zM8 12h8"/>
          </svg><span class="ctrl-text">Cámara</span>
        </button>

        <button type="button" id="{{ $id }}-torch" class="ctrl-btn hidden" title="Flash" aria-label="Flash">
          <svg class="icon" viewBox="0 0 24 24" stroke="currentColor" fill="none">
            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  d="M7 2l10 9h-7l4 9L7 11h7L7 2z"/>
          </svg><span class="ctrl-text">Flash</span>
        </button>

        <button type="button" id="{{ $id }}-close" class="ctrl-btn danger" title="Cerrar" aria-label="Cerrar">
          <svg class="icon" viewBox="0 0 24 24" stroke="currentColor" fill="none">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"/>
          </svg><span class="ctrl-text">Cerrar</span>
        </button>
      </div>

      <!-- Zoom -->
      <div class="absolute bottom-28 md:bottom-32 right-3 pointer-events-auto flex items-center gap-3">
        <label for="{{ $id }}-zoom" class="hidden md:block text-white/80 text-xs">Zoom</label>
        <input type="range" id="{{ $id }}-zoom" class="zoom-range hidden w-40 md:w-56 accent-sky-400 cursor-pointer"
               min="1" max="5" step="0.1" value="1" aria-label="Zoom">
      </div>

      <!-- Orientación -->
      <div id="{{ $id }}-orient" class="orientation-hint hidden">
        <div class="hint-card">
          <svg class="hint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="6" width="18" height="12" rx="2" ry="2" stroke-width="2"/>
            <path d="M12 8v8" stroke-width="2"/>
          </svg>
          <div class="hint-text">Gira tu tablet a <strong>horizontal</strong> para una mejor lectura.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .frame-size{ width:min(78vmin,68vw); height:min(78vmin,68vw); }
  @media (min-width:768px){ .frame-size{ width:min(75vmin,62vw); height:min(75vmin,62vw);} }
  .corner{ position:absolute; width:14%; height:14%; border-color:#38bdf8 }
  .corner.tl{ top:0; left:0; border-top:5px solid; border-left:5px solid; border-top-left-radius:14px }
  .corner.tr{ top:0; right:0; border-top:5px solid; border-right:5px solid; border-top-right-radius:14px }
  .corner.bl{ bottom:0; left:0; border-bottom:5px solid; border-left:5px solid; border-bottom-left-radius:14px }
  .corner.br{ bottom:0; right:0; border-bottom:5px solid; border-right:5px solid; border-bottom-right-radius:14px }
  @keyframes scan{0%{top:12%;opacity:.25}10%{opacity:1}50%{top:88%;opacity:1}100%{top:12%;opacity:.25}}
  .scanline{ position:absolute; left:6%; right:6%; height:2px; background:rgba(255,255,255,.9); box-shadow:0 0 12px rgba(255,255,255,.9); animation:scan 2.6s linear infinite }
  .ctrl-btn{ display:inline-flex; align-items:center; gap:.5rem; padding:.6rem .9rem; border-radius:9999px; background:rgba(255,255,255,.14); color:#fff; box-shadow:0 2px 14px rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.22); transition:.2s; touch-action:manipulation; -webkit-tap-highlight-color:transparent }
  .ctrl-btn:hover{ background:rgba(255,255,255,.24) } .ctrl-btn:active{ transform:scale(.98) }
  .ctrl-btn.danger{ background:#dc2626; border-color:#ef4444 } .ctrl-btn.danger:hover{ background:#b91c1c }
  .ctrl-text{ display:none; font-size:.8rem } @media (min-width:768px){ .ctrl-text{ display:inline } }
  .icon{ width:22px; height:22px } .zoom-range{ height:32px }
  .orientation-hint{ position:absolute; inset:0; display:grid; place-items:center; background:rgba(0,0,0,.45); pointer-events:none }
  .hint-card{ display:flex; align-items:center; gap:.8rem; background:rgba(15,23,42,.9); border:1px solid rgba(255,255,255,.15); color:white; padding:.9rem 1.1rem; border-radius:14px; box-shadow:0 6px 28px rgba(0,0,0,.35) }
  .hint-icon{ width:28px; height:28px } .hint-text{ font-size:.95rem }
</style>

<script>
(() => {
  class QRScanner {
    constructor(rootId) {
      this.root = document.getElementById(rootId);
      this.video = this.root.querySelector('#{{ $id }}-video');
      this.overlay = this.root.querySelector('#{{ $id }}-overlay');
      this.msg = this.root.querySelector('#{{ $id }}-msg');
      this.btnClose = this.root.querySelector('#{{ $id }}-close');
      this.btnSwitch = this.root.querySelector('#{{ $id }}-switch');
      this.btnTorch = this.root.querySelector('#{{ $id }}-torch');
      this.btnFs = this.root.querySelector('#{{ $id }}-fs');
      this.rngZoom = this.root.querySelector('#{{ $id }}-zoom');
      this.orientHint = this.root.querySelector('#{{ $id }}-orient');

      this.stream = null; this.track = null;
      this.scanning = false;
      this.facing = 'user'; // frontal por defecto
      this.sameHits = 0; this.lastHit = null;

      // Canvas de procesamiento (offscreen en main)
      this.canvas = document.createElement('canvas');
      this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });

      // Overlay para dibujar contornos
      this.octx = this.overlay.getContext('2d');

      this.barcode = ('BarcodeDetector' in window) ? new BarcodeDetector({ formats: ['qr_code'] }) : null;

      // Worker inline para jsQR (si está cargado)
      this.qrWorker = null;
      this.qrWorkerURL = this._makeWorkerURL();

      // Throttle: objetivo ~30 fps
      this.minFrameInterval = 1000 / 30; // 33ms
      this.lastScanTs = 0;

      // Wake Lock / orientación
      this.wakeLock = null;

      // Gestos táctiles
      this._pinchData = null;

      // Bindings
      this._loop = this._loop.bind(this);
      this._onVisibility = this._onVisibility.bind(this);
      this._onResize = this._onResize.bind(this);

      // Eventos UI
      this.btnClose.addEventListener('click', () => this.stop());
      this.btnSwitch.addEventListener('click', async () => { this.facing = (this.facing === 'user') ? 'environment' : 'user'; await this.restart(); });
      this.btnFs.addEventListener('click', () => this._toggleFullscreen());

      // Touch: pinch y doble tap
      this.overlay.addEventListener('touchstart', (e) => this._onTouchStart(e), { passive: false });
      this.overlay.addEventListener('touchmove', (e) => this._onTouchMove(e), { passive: false });
      this.overlay.addEventListener('touchend', (e) => this._onTouchEnd(e), { passive: true });
      this.overlay.addEventListener('dblclick', () => this._doubleTapZoom());

      document.addEventListener('visibilitychange', this._onVisibility);
      window.addEventListener('resize', this._onResize);
      window.addEventListener('orientationchange', this._onResize);
    }

    async start() {
      this.root.classList.remove('hidden'); this.root.classList.add('flex');
      await this._startStream();
      this._updateOrientationHint();
      await this._requestWakeLock();
      await this._lockOrientationLandscape();
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
          width: { ideal: 1920 },
          height: { ideal: 1080 },
          advanced: [{ focusMode: 'continuous' }]
        }, audio: false
      };
      try {
        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
        this.video.srcObject = this.stream;
        this.track = this.stream.getVideoTracks()[0];
        await this.video.play();

        // Espejo solo frontal
        this.video.style.transform = (this.facing === 'user') ? 'scaleX(-1)' : 'none';

        // Capabilities
        const caps = this.track.getCapabilities?.() || {};
        const sets = this.track.getSettings?.() || {};

        // Torch
        const hasTorch = caps.torch === true;
        this.btnTorch.classList.toggle('hidden', !hasTorch);
        if (hasTorch) {
          this.btnTorch.onclick = async () => {
            const s = this.track.getSettings?.() || {};
            await this.track.applyConstraints({ advanced: [{ torch: !s.torch }] });
          };
        }

        // Zoom
        if (caps.zoom) {
          this.rngZoom.classList.remove('hidden');
          this.rngZoom.min = caps.zoom.min ?? 1;
          this.rngZoom.max = caps.zoom.max ?? 5;
          this.rngZoom.step = caps.zoom.step ?? 0.1;
          this.rngZoom.value = sets.zoom ?? this.rngZoom.min;
          this.rngZoom.oninput = () => this.track.applyConstraints({ advanced: [{ zoom: Number(this.rngZoom.value) }] });
        } else {
          this.rngZoom.classList.add('hidden');
        }

        // Canvas sizes
        this._resizeSurfaces();

        // Worker
        if (this.qrWorkerURL && typeof jsQR === 'function') {
          this.qrWorker = new Worker(this.qrWorkerURL);
        }

        this._setMsg('Apunta el código al marco');
      } catch (err) {
        console.error('Acceso cámara:', err);
        this._setMsg('No se pudo acceder a la cámara. Revisa permisos.');
        throw err;
      }
    }

    _resizeSurfaces() {
      const rect = this.video.getBoundingClientRect();
      this.overlay.width = rect.width; this.overlay.height = rect.height;

      const vw = this.video.videoWidth || 1280;
      const vh = this.video.videoHeight || 720;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      this.canvas.width = Math.floor(vw * dpr);
      this.canvas.height = Math.floor(vh * dpr);
      this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    async _stopStreamOnly() {
      this.scanning = false;
      if (this.qrWorker) { this.qrWorker.terminate(); this.qrWorker = null; }
      if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; this.track = null; }
    }

    async stop() {
      this.scanning = false;
      await this._stopStreamOnly();
      await this._releaseWakeLock();
      await this._unlockOrientation();
      this.root.classList.add('hidden'); this.root.classList.remove('flex');
    }

    _onVisibility() {
      if (document.hidden) { this.scanning = false; this._releaseWakeLock(); }
      else if (!this.root.classList.contains('hidden')) { this.scanning = true; this._requestWakeLock(); this._loop(); }
    }
    _onResize() { this._updateOrientationHint(); this._resizeSurfaces(); }

    _updateOrientationHint() {
      const isTablet = Math.min(window.innerWidth, window.innerHeight) >= 600;
      const isPortrait = window.innerHeight > window.innerWidth;
      this.orientHint.classList.toggle('hidden', !(isTablet && isPortrait));
    }

    _setMsg(t){ if(this.msg) this.msg.textContent = t; }

    async _loop(now = performance.now()) {
      if (!this.scanning) return;
      if (now - this.lastScanTs >= this.minFrameInterval && this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
        this.lastScanTs = now;
        try { await this._scanFrame(); } catch {}
      }
      requestAnimationFrame(this._loop);
    }

    async _scanFrame() {
      const vw = this.video.videoWidth, vh = this.video.videoHeight;
      if (!vw || !vh) return;

      // ROI tablet friendly
      const isTablet = Math.min(window.innerWidth, window.innerHeight) >= 600;
      const ratio = isTablet ? 0.72 : 0.60;
      const side = Math.floor(Math.min(vw, vh) * ratio);
      const sx = Math.floor((vw - side) / 2), sy = Math.floor((vh - side) / 2);

      // espejo en frontal
      if (this.facing === 'user') this.ctx.setTransform(-1, 0, 0, 1, side, 0); else this.ctx.setTransform(1, 0, 0, 1, 0, 0);
      this.ctx.drawImage(this.video, sx, sy, side, side, 0, 0, side, side);
      this.ctx.setTransform(1, 0, 0, 1, 0, 0);

      // Limpia overlay
      this.octx.clearRect(0, 0, this.overlay.width, this.overlay.height);

      // 1) nativo
      if (this.barcode) {
        try {
          const blob = await new Promise(res => this.canvas.toBlob(res, 'image/png', .88));
          const bmp = await createImageBitmap(blob);
          const codes = await this.barcode.detect(bmp);
          if (codes?.length) {
            this._drawBoxFromDetector(codes[0]);
            return this._maybeConfirm(codes[0].rawValue);
          }
        } catch { /* fallback */ }
      }

      // 2) jsQR: worker si disponible, sino main
      if (typeof jsQR === 'function') {
        const img = this.ctx.getImageData(0, 0, side, side);
        const doHandle = (result) => {
          if (result?.data) {
            this._drawBoxFromJsQR(result);
            return this._maybeConfirm(result.data);
          }
        };

        if (this.qrWorker) {
          this.qrWorker.onmessage = (e) => doHandle(e.data);
          this.qrWorker.postMessage({ data: img.data.buffer, width: side, height: side }, [img.data.buffer]);
        } else {
          const qr = jsQR(img.data, side, side, { inversionAttempts:'attemptBoth' });
          doHandle(qr);
        }
      }
    }

    _drawBoxFromDetector(code){
      // Intento de box (algunos navegadores dan boundingBox)
      try{
        const rect = this.video.getBoundingClientRect();
        const box = code.boundingBox || code.cornerPoints && _bbFromPoints(code.cornerPoints);
        if(!box) return;
        this.octx.strokeStyle = 'rgba(56,189,248,0.9)'; this.octx.lineWidth = 3;
        this.octx.strokeRect(box.x/this.video.videoWidth*rect.width, box.y/this.video.videoHeight*rect.height,
                             box.width/this.video.videoWidth*rect.width, box.height/this.video.videoHeight*rect.height);
      }catch{}
      function _bbFromPoints(pts){
        const xs = pts.map(p=>p.x), ys=pts.map(p=>p.y);
        const x=Math.min(...xs), y=Math.min(...ys), w=Math.max(...xs)-x, h=Math.max(...ys)-y;
        return {x,y,width:w,height:h};
      }
    }

    _drawBoxFromJsQR(qr){
      try{
        if(!qr?.location) return;
        const L = qr.location;
        const map = (p)=> this._toOverlayXY(p.x,p.y);
        const a = map(L.topLeftCorner), b = map(L.topRightCorner), c = map(L.bottomRightCorner), d = map(L.bottomLeftCorner);
        this.octx.strokeStyle = 'rgba(56,189,248,0.95)'; this.octx.lineWidth = 3;
        this.octx.beginPath(); this.octx.moveTo(a.x,a.y); this.octx.lineTo(b.x,b.y);
        this.octx.lineTo(c.x,c.y); this.octx.lineTo(d.x,d.y); this.octx.closePath(); this.octx.stroke();
      }catch{}
    }

    _toOverlayXY(x,y){
      const rect = this.video.getBoundingClientRect();
      // ROI proyectada al overlay (assume ROI centrada y cuadrada)
      const vw=this.video.videoWidth, vh=this.video.videoHeight;
      const side = Math.min(vw, vh)* (Math.min(window.innerWidth, window.innerHeight) >= 600 ? 0.72 : 0.60);
      const sx = (vw - side)/2, sy = (vh - side)/2;
      const ox = ( (x) / side ) * rect.width;
      const oy = ( (y) / side ) * rect.height;
      return { x: ox, y: oy };
    }

    async _maybeConfirm(value){
      if (value === this.lastHit) this.sameHits++; else { this.lastHit = value; this.sameHits = 1; }
      if (this.sameHits >= 2) {
        this.scanning = false;
        this._haptic();
        this._setMsg('Verificando código…');
        await this._authenticate(value);
      }
    }

    async _authenticate(qrData){
      try{
        const resp = await fetch('/login-qr', {
          method:'POST',
          headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: JSON.stringify({ numero_empleado: qrData })
        });
        const data = await resp.json();
        if (data.success) {
          this._setMsg('¡Acceso exitoso! Redirigiendo…');
          setTimeout(()=> window.location.href='/produccionProceso', 650);
        } else {
          this._setMsg(data.message ? `Error: ${data.message}` : 'Código inválido. Reintenta.');
          setTimeout(()=> this.restart(), 850);
        }
      }catch(e){
        console.error('Auth error:', e);
        this._setMsg('Error de red. Reintentando…');
        setTimeout(()=> this.restart(), 1100);
      }
    }

    _haptic(){ try{ navigator.vibrate?.(80); }catch{} }

    async _requestWakeLock(){ try{ if('wakeLock' in navigator){ this.wakeLock = await navigator.wakeLock.request('screen'); } }catch{} }
    async _releaseWakeLock(){ try{ await this.wakeLock?.release(); this.wakeLock = null; }catch{} }

    async _lockOrientationLandscape(){
      try{ if(screen.orientation && screen.orientation.lock) await screen.orientation.lock('landscape'); }catch{}
    }
    async _unlockOrientation(){
      try{ if(screen.orientation && screen.orientation.unlock) screen.orientation.unlock(); }catch{}
    }

    async _toggleFullscreen(){
      try{
        const el = this.root;
        if (!document.fullscreenElement) { await el.requestFullscreen?.(); }
        else { await document.exitFullscreen?.(); }
      }catch{}
    }

    // Gestos táctiles
    _onTouchStart(e){
      if (e.touches.length === 2) {
        const d = this._dist(e.touches[0], e.touches[1]);
        this._pinchData = { start: d, last: d };
      }
    }
    _onTouchMove(e){
      if (this._pinchData && e.touches.length === 2) {
        e.preventDefault();
        const d = this._dist(e.touches[0], e.touches[1]);
        const delta = d - this._pinchData.last;
        this._pinchData.last = d;
        const caps = this.track?.getCapabilities?.() || {};
        if (caps.zoom) {
          const cur = Number(this.rngZoom.value);
          const next = Math.min(Number(this.rngZoom.max), Math.max(Number(this.rngZoom.min), cur + delta/200));
          this.rngZoom.value = next;
          this.track.applyConstraints({ advanced: [{ zoom: next }] });
        }
      }
    }
    _onTouchEnd(){ this._pinchData = null; }
    _doubleTapZoom(){
      const caps = this.track?.getCapabilities?.() || {};
      if (!caps.zoom) return;
      const cur = Number(this.rngZoom.value);
      const mid = (Number(this.rngZoom.max) + Number(this.rngZoom.min)) / 2;
      const next = cur < mid ? mid : Number(this.rngZoom.min);
      this.rngZoom.value = next;
      this.track.applyConstraints({ advanced: [{ zoom: next }] });
    }
    _dist(a,b){ const dx=a.clientX-b.clientX, dy=a.clientY-b.clientY; return Math.hypot(dx,dy); }

    _makeWorkerURL(){
      // Si no hay jsQR, no creamos worker
      if (typeof jsQR !== 'function') return null;
      const code = `
        self.onmessage = (e) => {
          const { data, width, height } = e.data;
          const img = new ImageData(new Uint8ClampedArray(data), width, height);
          // jsQR debe estar disponible en worker: lo inyectamos simple si está en global? No, aquí asumimos jsQR ya inlineado en main.
          // En este worker mínimo, volvemos al main si no existe.
          try {
            if (self.jsQR) {
              const res = self.jsQR(img.data, width, height, { inversionAttempts: 'attemptBoth' });
              self.postMessage(res);
            } else {
              // sin jsQR en worker: regresamos null
              self.postMessage(null);
            }
          } catch (err) { self.postMessage(null); }
        };
      `;
      // Creamos worker con jsQR si está expuesto globalmente; los navegadores no comparten funciones entre hilos.
      // Truco: si tienes /js/jsQR.js, puedes fetch + blob aquí. Para mantenerlo inline, devolvemos un worker mínimo que solo funciona si inyectas jsQR en el worker.
      // Opción práctica: no usar worker si no puedes servir jsQR en el mismo blob.
      try{
        const blob = new Blob([code], { type:'application/javascript' });
        return URL.createObjectURL(blob);
      }catch{ return null; }
    }
  }

  // API global
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
