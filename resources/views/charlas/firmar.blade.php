@extends('layouts.app')

@section('title', 'Firmar Asistencia — ' . $charla->titulo)

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <!-- SAEP Brand Header -->
    <div style="text-align:center;padding:1.5rem 0 1rem;margin-bottom:1rem;">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg"
             alt="SAEP" style="height:48px;margin-bottom:0.75rem;"
             onerror="this.style.display='none';document.getElementById('saep-text-fallback').style.display='block';">
        <div id="saep-text-fallback" style="display:none;font-size:1.6rem;font-weight:900;color:#0056b3;letter-spacing:0.1em;">SAEP</div>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">Servicios de Asesorías a Empresas Ltda.</p>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;border-left:3px solid #0056b3;">
        <h2 style="font-size:1.1rem;font-weight:800;margin:0 0 0.4rem;">{{ $charla->titulo }}</h2>
        <p style="font-size:0.82rem;color:var(--text-muted);margin:0;">
            <i class="bi bi-calendar3"></i> {{ $charla->fecha_programada->format('d/m/Y H:i') }}
            @if($charla->lugar)
            &nbsp;&bull;&nbsp;<i class="bi bi-geo-alt"></i> {{ $charla->lugar }}
            @endif
        </p>
    </div>

    <!-- Trabajador info -->
    <div class="glass-card" style="margin-bottom:1rem;display:flex;align-items:center;gap:1rem;">
        <div class="avatar" style="width:46px;height:46px;font-size:1.1rem;flex-shrink:0;">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</div>
        <div>
            <p style="font-size:1rem;font-weight:700;margin:0;">{{ auth()->user()->name }}</p>
            <p style="font-size:0.78rem;color:var(--text-muted);margin:0;">{{ auth()->user()->rol->nombre ?? 'Trabajador' }}</p>
        </div>
        <div style="margin-left:auto;">
            <span style="font-size:0.72rem;padding:4px 10px;border-radius:6px;background:rgba(217,119,6,0.12);color:#d97706;font-weight:700;">
                Pendiente de firma
            </span>
        </div>
    </div>

    <!-- Contenido a leer -->
    @if($charla->contenido)
    <div class="glass-card" style="margin-bottom:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <h3 style="font-size:0.85rem;font-weight:700;margin:0;color:var(--text-main);">
                <i class="bi bi-book-fill" style="color:#0056b3;"></i> Contenido de la Charla
            </h3>
            <span id="read-badge" style="font-size:0.72rem;padding:3px 8px;border-radius:6px;background:rgba(217,119,6,0.12);color:#d97706;font-weight:700;">
                <i class="bi bi-eye"></i> Debes leer hasta el final
            </span>
        </div>
        <div id="contenido-scroll"
             style="max-height:200px;overflow-y:auto;font-size:0.85rem;line-height:1.7;padding:1rem;background:rgba(255,255,255,0.03);border:1px solid var(--surface-border);border-radius:10px;white-space:pre-wrap;">{{ $charla->contenido }}</div>
        <p style="font-size:0.73rem;color:var(--text-muted);margin-top:0.4rem;">
            <i class="bi bi-info-circle"></i> Desplázate hasta el final para habilitar la firma.
        </p>
    </div>
    @endif

    <!-- Firma form -->
    <form id="firma-form" method="POST" action="{{ route('charlas.guardarFirma', [$charla, $asistente]) }}">
        @csrf

        <input type="hidden" name="firma_imagen" id="firma_imagen">
        <input type="hidden" name="geo_latitud" id="geo_latitud">
        <input type="hidden" name="geo_longitud" id="geo_longitud">

        <div class="glass-card" style="margin-bottom:1rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <h3 style="font-size:0.85rem;font-weight:700;margin:0;">
                    <i class="bi bi-pen-fill" style="color:#0056b3;"></i> Firma Digital
                </h3>
                <button type="button" id="btn-limpiar" onclick="clearCanvas()"
                    style="font-size:0.75rem;padding:4px 10px;border:none;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text-muted);cursor:pointer;">
                    <i class="bi bi-eraser"></i> Limpiar
                </button>
            </div>
            <canvas id="firma-canvas"
                style="width:100%;height:180px;border:2px dashed var(--surface-border);border-radius:12px;cursor:crosshair;touch-action:none;background:rgba(255,255,255,0.02);"
                width="640" height="180"></canvas>
            <p style="font-size:0.73rem;color:var(--text-muted);margin-top:0.4rem;text-align:center;">
                Dibuja tu firma en el recuadro &mdash; compatible con mouse y pantalla táctil
            </p>
        </div>

        <!-- Geolocation status -->
        <div id="geo-status" style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
            <i class="bi bi-geo-alt"></i> <span id="geo-text">Capturando ubicación...</span>
        </div>

        <!-- Confirmación legal -->
        <div class="glass-card" style="margin-bottom:1.25rem;padding:0.9rem 1.1rem;">
            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;">
                <input type="checkbox" id="confirmar-lectura" style="margin-top:3px;width:16px;height:16px;accent-color:#0056b3;flex-shrink:0;">
                <span style="font-size:0.82rem;line-height:1.55;color:var(--text-muted);">
                    Declaro que he leído el contenido de esta charla, asistí a la instancia de capacitación y que la firma
                    presentada es de mi autoría. Entiendo que este acto tiene valor legal equivalente a una firma manuscrita
                    conforme a la <strong>Ley N° 19.799</strong> sobre documentos electrónicos.
                </span>
            </label>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('charlas.show', $charla) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" id="btn-firmar" class="btn-premium" disabled style="opacity:0.5;cursor:not-allowed;">
                <i class="bi bi-pen-fill"></i> Firmar Asistencia
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const canvas = document.getElementById('firma-canvas');
    const ctx    = canvas.getContext('2d');
    let drawing  = false;
    let hasSig   = false;
    let hasRead  = {{ $charla->contenido ? 'false' : 'true' }};
    let hasGeo   = true; // geo is optional, don't block
    let hasConfirm = false;

    // Resize canvas
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width  = rect.width  * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.strokeStyle = '#0056b3';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches) {
            return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
        }
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }

    function startDraw(e) { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
    function drawLine(e)  { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasSig = true; checkReady(); }
    function stopDraw(e)  { drawing = false; }

    canvas.addEventListener('mousedown',  startDraw);
    canvas.addEventListener('mousemove',  drawLine);
    canvas.addEventListener('mouseup',    stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove',  drawLine,  { passive: false });
    canvas.addEventListener('touchend',   stopDraw);

    window.clearCanvas = function () {
        const rect = canvas.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width, rect.height);
        hasSig = false;
        checkReady();
    };

    // Content scroll detection
    const contentDiv = document.getElementById('contenido-scroll');
    if (contentDiv) {
        contentDiv.addEventListener('scroll', function () {
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 5) {
                hasRead = true;
                document.getElementById('read-badge').innerHTML =
                    '<i class="bi bi-check-circle-fill" style="color:#16a34a;"></i> <span style="color:#16a34a;">Contenido leído</span>';
                checkReady();
            }
        });
    }

    // Checkbox
    document.getElementById('confirmar-lectura').addEventListener('change', function () {
        hasConfirm = this.checked;
        checkReady();
    });

    // Geolocation
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                document.getElementById('geo_latitud').value  = pos.coords.latitude;
                document.getElementById('geo_longitud').value = pos.coords.longitude;
                document.getElementById('geo-text').textContent = 'Ubicación capturada (' +
                    pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4) + ')';
                document.querySelector('#geo-status i').className = 'bi bi-geo-alt-fill';
                document.getElementById('geo-status').style.color = '#16a34a';
            },
            function () {
                document.getElementById('geo-text').textContent = 'Ubicación no disponible (continuarás sin geo)';
            },
            { timeout: 10000 }
        );
    } else {
        document.getElementById('geo-text').textContent = 'Geolocalización no disponible en este navegador';
    }

    function checkReady() {
        const ready = hasSig && hasRead && hasConfirm;
        const btn = document.getElementById('btn-firmar');
        btn.disabled = !ready;
        btn.style.opacity  = ready ? '1' : '0.5';
        btn.style.cursor   = ready ? 'pointer' : 'not-allowed';
    }

    document.getElementById('firma-form').addEventListener('submit', function (e) {
        if (!hasSig) { e.preventDefault(); alert('Debes dibujar tu firma antes de enviar.'); return; }
        document.getElementById('firma_imagen').value = canvas.toDataURL('image/png');
    });
})();
</script>
@endpush

