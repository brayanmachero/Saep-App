@extends('layouts.app')

@section('title', 'Firma Relator — ' . $charla->titulo)

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

    <div class="glass-card" style="margin-bottom:1rem;border-left:3px solid #7c3aed;">
        <h2 style="font-size:1.1rem;font-weight:800;margin:0 0 0.4rem;">{{ $charla->titulo }}</h2>
        <p style="font-size:0.82rem;color:var(--text-muted);margin:0;">
            <i class="bi bi-calendar3"></i> {{ $charla->fecha_programada->format('d/m/Y H:i') }}
            @if($charla->lugar)
            &nbsp;&bull;&nbsp;<i class="bi bi-geo-alt"></i> {{ $charla->lugar }}
            @endif
        </p>
    </div>

    <!-- Relator info -->
    <div class="glass-card" style="margin-bottom:1rem;display:flex;align-items:center;gap:1rem;">
        <div class="avatar" style="width:46px;height:46px;font-size:1.1rem;flex-shrink:0;background:rgba(124,58,237,0.2);color:#7c3aed;">
            {{ strtoupper(substr(auth()->user()->name,0,1)) }}
        </div>
        <div>
            <p style="font-size:1rem;font-weight:700;margin:0;">{{ auth()->user()->name }}</p>
            <p style="font-size:0.78rem;color:var(--text-muted);margin:0;">{{ $relator->rolLabel }}</p>
        </div>
        <div style="margin-left:auto;">
            <span style="font-size:0.72rem;padding:4px 10px;border-radius:6px;background:rgba(124,58,237,0.1);color:#7c3aed;font-weight:700;">
                <i class="bi bi-person-badge-fill"></i> Firma de Relator
            </span>
        </div>
    </div>

    <!-- Asistentes summary -->
    <div class="glass-card" style="margin-bottom:1rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.6rem;font-weight:700;">
            <i class="bi bi-people-fill"></i> Asistentes de la Charla
        </h3>
        @php $prog = $charla->firmaProgress; @endphp
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.4rem;">
            <div style="flex:1;height:6px;background:rgba(255,255,255,0.08);border-radius:99px;">
                <div style="height:100%;width:{{ $prog['percent'] }}%;background:#16a34a;border-radius:99px;"></div>
            </div>
            <span style="font-size:0.82rem;font-weight:700;">{{ $prog['firmados'] }}/{{ $prog['total'] }} firmaron</span>
        </div>
        <p style="font-size:0.75rem;color:var(--text-muted);margin:0;">
            Al firmar como relator, certificas que has dictado esta charla a los asistentes listados.
        </p>
    </div>

    <!-- Firma form -->
    <form id="firma-form" method="POST" action="{{ route('charlas.guardarFirmaRelator', [$charla, $relator]) }}">
        @csrf

        <input type="hidden" name="firma_imagen" id="firma_imagen">
        <input type="hidden" name="geo_latitud" id="geo_latitud">
        <input type="hidden" name="geo_longitud" id="geo_longitud">

        <div class="glass-card" style="margin-bottom:1rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <h3 style="font-size:0.85rem;font-weight:700;margin:0;">
                    <i class="bi bi-pen-fill" style="color:#7c3aed;"></i> Firma Digital del Relator
                </h3>
                <button type="button" onclick="clearCanvas()"
                    style="font-size:0.75rem;padding:4px 10px;border:none;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text-muted);cursor:pointer;">
                    <i class="bi bi-eraser"></i> Limpiar
                </button>
            </div>
            <canvas id="firma-canvas"
                style="width:100%;height:180px;border:2px dashed rgba(124,58,237,0.4);border-radius:12px;cursor:crosshair;touch-action:none;background:rgba(124,58,237,0.03);"
                width="640" height="180"></canvas>
            <p style="font-size:0.73rem;color:var(--text-muted);margin-top:0.4rem;text-align:center;">
                Dibuja tu firma — compatible con mouse y pantalla táctil
            </p>
        </div>

        <!-- Geolocation status -->
        <div id="geo-status" style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
            <i class="bi bi-geo-alt"></i> <span id="geo-text">Capturando ubicación...</span>
        </div>

        <!-- Declaración legal -->
        <div class="glass-card" style="margin-bottom:1.25rem;padding:0.9rem 1.1rem;">
            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;">
                <input type="checkbox" id="confirmar" style="margin-top:3px;width:16px;height:16px;accent-color:#7c3aed;flex-shrink:0;">
                <span style="font-size:0.82rem;line-height:1.55;color:var(--text-muted);">
                    Certifico que he dictado la presente charla/capacitación a los trabajadores indicados, que el contenido
                    es verídico y que la firma presentada es de mi autoría. Este acto tiene valor legal equivalente
                    a una firma manuscrita conforme a la <strong>Ley N° 19.799</strong>.
                </span>
            </label>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('charlas.show', $charla) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" id="btn-firmar" class="btn-premium" disabled
                style="opacity:0.5;cursor:not-allowed;background:linear-gradient(135deg,#7c3aed,#6d28d9);">
                <i class="bi bi-pen-fill"></i> Firmar como Relator
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
    let hasConfirm = false;

    function resizeCanvas() {
        const rect  = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width  = rect.width  * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.strokeStyle = '#7c3aed';
        ctx.lineWidth   = 2.5;
        ctx.lineCap     = 'round';
        ctx.lineJoin    = 'round';
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
    function stopDraw()   { drawing = false; }

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

    document.getElementById('confirmar').addEventListener('change', function () {
        hasConfirm = this.checked;
        checkReady();
    });

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
    }

    function checkReady() {
        const ready = hasSig && hasConfirm;
        const btn   = document.getElementById('btn-firmar');
        btn.disabled     = !ready;
        btn.style.opacity = ready ? '1' : '0.5';
        btn.style.cursor  = ready ? 'pointer' : 'not-allowed';
    }

    document.getElementById('firma-form').addEventListener('submit', function (e) {
        if (!hasSig) { e.preventDefault(); alert('Debes dibujar tu firma antes de enviar.'); return; }
        document.getElementById('firma_imagen').value = canvas.toDataURL('image/png');
    });
})();
</script>
@endpush
