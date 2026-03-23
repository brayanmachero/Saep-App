@extends('layouts.app')

@section('title', 'Firmar Asistencia')

@section('content')
<div class="page-container" style="max-width:640px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Firmar Asistencia</h2>
            <p class="page-subheading">{{ $charla->titulo }}</p>
        </div>
        <a href="{{ route('charlas.show', $charla) }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card">
        <div style="display:flex;align-items:center;gap:1rem;padding-bottom:1.25rem;border-bottom:1px solid var(--surface-border);margin-bottom:1.5rem;">
            <div class="avatar" style="width:50px;height:50px;font-size:1.1rem;flex-shrink:0;">
                {{ strtoupper(substr($asistente->usuario->name ?? 'U', 0, 1)) }}
            </div>
            <div>
                <strong style="font-size:1rem;">{{ $asistente->usuario->name }}</strong>
                <p style="margin:0.2rem 0 0;color:var(--text-muted);font-size:0.85rem;">
                    {{ $asistente->usuario->rol->nombre ?? '' }} &bull;
                    {{ $charla->fecha_programada->format('d/m/Y H:i') }} &bull;
                    {{ $charla->lugar ?: 'Sin lugar' }}
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('charlas.guardarFirma', [$charla, $asistente]) }}" id="sign-form">
            @csrf

            <div class="form-group">
                <label style="font-size:0.85rem;margin-bottom:0.5rem;">
                    Firma Digital *
                    <small style="color:var(--text-muted);font-weight:400;"> — Dibuja tu firma en el recuadro</small>
                </label>
                <div style="border:2px solid var(--surface-border);border-radius:12px;overflow:hidden;background:white;position:relative;">
                    <canvas id="signature-pad" width="580" height="200"
                        style="width:100%;height:200px;display:block;cursor:crosshair;touch-action:none;"></canvas>
                    <div id="sig-placeholder" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                        color:#aaa;font-size:0.9rem;pointer-events:none;">
                        <i class="bi bi-pen" style="margin-right:0.5rem;"></i> Firma aquí
                    </div>
                </div>
                <input type="hidden" name="firma_imagen" id="firma_imagen">
                <div style="display:flex;gap:0.75rem;margin-top:0.75rem;">
                    <button type="button" class="btn-ghost" onclick="clearPad()">
                        <i class="bi bi-eraser"></i> Limpiar
                    </button>
                    <span id="sig-status" style="font-size:0.8rem;color:var(--text-muted);align-self:center;"></span>
                </div>
            </div>

            <div style="padding:1rem;background:rgba(79,70,229,0.07);border-radius:10px;font-size:0.82rem;
                color:var(--text-muted);line-height:1.6;margin-top:0.75rem;">
                <i class="bi bi-shield-check" style="color:var(--primary-color);margin-right:0.4rem;"></i>
                Al firmar confirmo que asistí y comprendo el contenido de la charla
                <strong>{{ $charla->titulo }}</strong> realizada el
                <strong>{{ $charla->fecha_programada->format('d/m/Y') }}</strong>.
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <a href="{{ route('charlas.show', $charla) }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn-premium" id="btn-firmar" disabled>
                    <i class="bi bi-pen-fill"></i> Confirmar Firma
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const canvas = document.getElementById('signature-pad');
const ctx = canvas.getContext('2d');
const placeholder = document.getElementById('sig-placeholder');
const btnFirmar = document.getElementById('btn-firmar');
const sigStatus = document.getElementById('sig-status');
let drawing = false;
let hasSig = false;

ctx.strokeStyle = '#1e1b4b';
ctx.lineWidth = 2.5;
ctx.lineCap = 'round';
ctx.lineJoin = 'round';

const getPos = e => {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const src = e.touches ? e.touches[0] : e;
    return { x: (src.clientX - rect.left) * scaleX, y: (src.clientY - rect.top) * scaleY };
};

canvas.addEventListener('mousedown', e => {
    drawing = true;
    ctx.beginPath();
    const p = getPos(e);
    ctx.moveTo(p.x, p.y);
    placeholder.style.display = 'none';
});
canvas.addEventListener('mousemove', e => {
    if (!drawing) return;
    const p = getPos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
});
canvas.addEventListener('mouseup', () => {
    drawing = false;
    finalizeStroke();
});
canvas.addEventListener('mouseleave', () => { if (drawing) { drawing = false; finalizeStroke(); } });

canvas.addEventListener('touchstart', e => {
    e.preventDefault();
    drawing = true;
    ctx.beginPath();
    const p = getPos(e);
    ctx.moveTo(p.x, p.y);
    placeholder.style.display = 'none';
}, { passive: false });
canvas.addEventListener('touchmove', e => {
    e.preventDefault();
    if (!drawing) return;
    const p = getPos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
}, { passive: false });
canvas.addEventListener('touchend', () => { drawing = false; finalizeStroke(); });

function finalizeStroke() {
    hasSig = true;
    const dataUrl = canvas.toDataURL('image/png');
    document.getElementById('firma_imagen').value = dataUrl;
    btnFirmar.disabled = false;
    sigStatus.textContent = '✓ Firma lista';
    sigStatus.style.color = '#16a34a';
}

window.clearPad = function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSig = false;
    document.getElementById('firma_imagen').value = '';
    btnFirmar.disabled = true;
    placeholder.style.display = 'flex';
    sigStatus.textContent = '';
};

document.getElementById('sign-form').addEventListener('submit', function (e) {
    if (!hasSig) {
        e.preventDefault();
        alert('Debes dibujar tu firma antes de confirmar.');
    }
});
</script>
@endpush
