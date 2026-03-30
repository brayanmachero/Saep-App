 @extends('layouts.app')

@section('title', 'Mis Notas')

@push('styles')
<style>
/* ===== DICTATION PANEL ===== */
.dictation-panel {
    background: var(--glass-bg, rgba(255,255,255,0.08));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.12));
    border-radius: 16px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.dictation-textarea {
    width: 100%;
    min-height: 100px;
    background: var(--input-bg, rgba(0,0,0,0.15));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.1));
    border-radius: 12px;
    color: var(--text-primary, #fff);
    padding: 0.875rem 1rem;
    font-size: 0.95rem;
    resize: vertical;
    font-family: inherit;
    transition: border-color 0.2s;
}
.dictation-textarea:focus {
    outline: none;
    border-color: var(--accent, #6366f1);
}
.dictation-textarea::placeholder {
    color: var(--text-muted, rgba(255,255,255,0.4));
}
.dictation-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.75rem;
    flex-wrap: wrap;
}
.btn-mic {
    width: 48px; height: 48px;
    border-radius: 50%;
    border: 2px solid var(--accent, #6366f1);
    background: transparent;
    color: var(--accent, #6366f1);
    font-size: 1.25rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s;
    flex-shrink: 0;
}
.btn-mic:hover { background: var(--accent, #6366f1); color: #fff; }
.btn-mic.recording {
    background: #ef4444;
    border-color: #ef4444;
    color: #fff;
    animation: pulse-mic 1.2s infinite;
}
@keyframes pulse-mic {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
    50% { box-shadow: 0 0 0 12px rgba(239,68,68,0); }
}
.mic-status {
    font-size: 0.8rem;
    color: var(--text-muted, rgba(255,255,255,0.5));
}
.mic-status.active { color: #ef4444; font-weight: 600; }
.dictation-meta {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex: 1;
    min-width: 0;
    flex-wrap: wrap;
}
.dictation-meta select,
.dictation-meta input[type="date"] {
    background: var(--input-bg, rgba(0,0,0,0.15));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.1));
    border-radius: 8px;
    color: var(--text-primary, #fff);
    padding: 0.5rem 0.65rem;
    font-size: 0.85rem;
    min-width: 0;
}
.dictation-meta select option { background: #1e1e2e; color: #fff; }
.btn-save-nota {
    padding: 0.55rem 1.25rem;
    background: var(--accent, #6366f1);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.9rem;
    transition: opacity 0.2s;
    white-space: nowrap;
}
.btn-save-nota:hover { opacity: 0.85; }
.btn-save-nota:disabled { opacity: 0.4; cursor: not-allowed; }

/* ===== STATS BAR ===== */
.notas-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.stat-mini {
    background: var(--glass-bg, rgba(255,255,255,0.06));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.08));
    border-radius: 12px;
    padding: 0.85rem;
    text-align: center;
}
.stat-mini .stat-number { font-size: 1.5rem; font-weight: 700; color: var(--accent, #6366f1); }
.stat-mini .stat-label  { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }

/* ===== FILTERS ===== */
.notas-filters {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.notas-filters select,
.notas-filters input {
    background: var(--input-bg, rgba(0,0,0,0.15));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.1));
    border-radius: 8px;
    color: var(--text-primary, #fff);
    padding: 0.45rem 0.65rem;
    font-size: 0.85rem;
}
.notas-filters select option { background: #1e1e2e; }
.notas-filters .search-input {
    flex: 1;
    min-width: 150px;
}

/* ===== NOTES LIST ===== */
.notas-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.nota-card {
    background: var(--glass-bg, rgba(255,255,255,0.06));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.08));
    border-radius: 14px;
    padding: 1rem 1.1rem;
    transition: all 0.2s;
    position: relative;
}
.nota-card:hover {
    border-color: var(--accent, #6366f1);
}
.nota-card.completada {
    opacity: 0.55;
}
.nota-card.completada .nota-text {
    text-decoration: line-through;
}
.nota-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.nota-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.cat-General      { background: rgba(99,102,241,0.2); color: #818cf8; }
.cat-Reunión      { background: rgba(59,130,246,0.2); color: #60a5fa; }
.cat-Tarea        { background: rgba(234,179,8,0.2);  color: #facc15; }
.cat-Recordatorio { background: rgba(168,85,247,0.2); color: #c084fc; }
.cat-Horas.Extra,
.cat-Horas        { background: rgba(20,184,166,0.2); color: #2dd4bf; }
.cat-Personal     { background: rgba(236,72,153,0.2); color: #f472b6; }
.cat-Urgente      { background: rgba(239,68,68,0.2);  color: #f87171; }
.cat-Idea         { background: rgba(34,197,94,0.2);  color: #4ade80; }
.nota-actions {
    display: flex;
    gap: 0.3rem;
    flex-shrink: 0;
}
.nota-actions button {
    width: 30px; height: 30px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    border-radius: 8px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    transition: all 0.15s;
}
.nota-actions button:hover { background: rgba(255,255,255,0.08); color: var(--text-primary); }
.nota-actions button.check-btn:hover { color: #4ade80; }
.nota-actions button.delete-btn:hover { color: #f87171; }
.nota-text {
    font-size: 0.92rem;
    color: var(--text-primary, #e2e8f0);
    line-height: 1.5;
    word-break: break-word;
}
.nota-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    font-size: 0.72rem;
    color: var(--text-muted);
    flex-wrap: wrap;
    gap: 0.3rem;
}
.nota-reminder {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #c084fc;
}
.nota-origen { opacity: 0.6; }
.nota-empty {
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
}
.nota-empty i { font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3; }

/* ===== CATEGORY SIDEBAR (desktop) ===== */
.notas-layout {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1.25rem;
    align-items: start;
}
.cat-sidebar {
    background: var(--glass-bg, rgba(255,255,255,0.05));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.08));
    border-radius: 14px;
    padding: 0.75rem;
    position: sticky;
    top: 80px;
}
.cat-sidebar-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    padding: 0.4rem 0.6rem;
    margin-bottom: 0.3rem;
}
.cat-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.6rem;
    border-radius: 8px;
    font-size: 0.82rem;
    color: var(--text-secondary, #94a3b8);
    text-decoration: none;
    transition: background 0.15s;
}
.cat-link:hover { background: rgba(255,255,255,0.06); }
.cat-link.active { background: var(--accent, #6366f1); color: #fff; }
.cat-count {
    font-size: 0.7rem;
    background: rgba(255,255,255,0.1);
    padding: 0.15rem 0.4rem;
    border-radius: 10px;
}
.cat-link.active .cat-count { background: rgba(255,255,255,0.25); }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .notas-layout { grid-template-columns: 1fr; }
    .cat-sidebar { display: none; }
    .dictation-controls { flex-direction: column; align-items: stretch; }
    .dictation-meta { flex-direction: column; }
    .dictation-meta select,
    .dictation-meta input[type="date"] { width: 100%; }
    .btn-mic { align-self: center; }
    .notas-stats { grid-template-columns: repeat(2, 1fr); }
    .notas-filters { flex-direction: column; }
    .notas-filters select,
    .notas-filters input { width: 100%; }
    .nota-card { padding: 0.85rem; }
}
</style>
@endpush

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Mis Notas</h2>
            <p class="page-subheading">Dictado por voz con clasificación inteligente</p>
        </div>
    </div>

    {{-- DICTATION PANEL --}}
    <div class="dictation-panel">
        <form id="nota-form" method="POST" action="{{ route('notas.store') }}">
            @csrf
            <textarea id="dictation-text" name="contenido" class="dictation-textarea"
                placeholder="Escribe o dicta tu nota... Ej: 'Recordar revisar horas extra de Juan el viernes'" required></textarea>

            <div class="dictation-controls">
                <button type="button" id="btn-mic" class="btn-mic" title="Iniciar dictado por voz">
                    <i class="bi bi-mic-fill"></i>
                </button>
                <span id="mic-status" class="mic-status">Pulsa el micrófono para dictar</span>

                <div class="dictation-meta">
                    <select name="categoria" id="nota-categoria">
                        <option value="auto">Auto-clasificar</option>
                        @foreach(\App\Models\NotaPersonal::CATEGORIAS as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="fecha_recordatorio" id="nota-fecha" title="Fecha recordatorio (opcional)">
                    <input type="hidden" name="origen" id="nota-origen" value="texto">
                </div>

                <button type="submit" class="btn-save-nota" id="btn-save">
                    <i class="bi bi-plus-lg"></i> Guardar
                </button>
            </div>
        </form>
    </div>

    {{-- STATS --}}
    <div class="notas-stats">
        <div class="stat-mini">
            <div class="stat-number">{{ $stats['total'] }}</div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-mini">
            <div class="stat-number">{{ $stats['pendientes'] }}</div>
            <div class="stat-label">Pendientes</div>
        </div>
        <div class="stat-mini">
            <div class="stat-number" style="color:#c084fc;">{{ $stats['hoy'] }}</div>
            <div class="stat-label">Recordatorios hoy</div>
        </div>
        <div class="stat-mini">
            <div class="stat-number" style="color:#2dd4bf;">{{ $stats['categorias']->count() }}</div>
            <div class="stat-label">Categorías usadas</div>
        </div>
    </div>

    {{-- FILTERS --}}
    <form method="GET" action="{{ route('notas.index') }}" class="notas-filters">
        <select name="categoria" onchange="this.form.submit()">
            <option value="Todas">Todas las categorías</option>
            @foreach(\App\Models\NotaPersonal::CATEGORIAS as $cat)
                <option value="{{ $cat }}" {{ request('categoria') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
        </select>
        <select name="estado" onchange="this.form.submit()">
            <option value="">Todos</option>
            <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendientes</option>
            <option value="completada" {{ request('estado') === 'completada' ? 'selected' : '' }}>Completadas</option>
        </select>
        <input type="month" name="mes" value="{{ request('mes') }}" onchange="this.form.submit()">
        <input type="text" name="buscar" class="search-input" placeholder="Buscar en notas..."
            value="{{ request('buscar') }}">
        <button type="submit" class="btn-save-nota" style="padding:0.45rem 0.85rem;">
            <i class="bi bi-search"></i>
        </button>
    </form>

    {{-- NOTAS LIST WITH CATEGORY SIDEBAR --}}
    <div class="notas-layout">
        {{-- Category sidebar (desktop) --}}
        <div class="cat-sidebar">
            <div class="cat-sidebar-title">Categorías</div>
            <a href="{{ route('notas.index') }}" class="cat-link {{ !request('categoria') || request('categoria') === 'Todas' ? 'active' : '' }}">
                Todas <span class="cat-count">{{ $stats['total'] }}</span>
            </a>
            @foreach($stats['categorias'] as $cat => $count)
            <a href="{{ route('notas.index', ['categoria' => $cat]) }}" class="cat-link {{ request('categoria') === $cat ? 'active' : '' }}">
                {{ $cat }} <span class="cat-count">{{ $count }}</span>
            </a>
            @endforeach
        </div>

        {{-- Notes --}}
        <div>
            <div class="notas-list">
                @forelse($notas as $nota)
                <div class="nota-card {{ $nota->completada ? 'completada' : '' }}" data-id="{{ $nota->id }}">
                    <div class="nota-header">
                        <span class="nota-cat-badge cat-{{ Str::before($nota->categoria, ' ') }}">
                            {{ $nota->categoria }}
                        </span>
                        <div class="nota-actions">
                            <button type="button" class="check-btn" onclick="toggleNota({{ $nota->id }})" title="{{ $nota->completada ? 'Marcar pendiente' : 'Completar' }}">
                                <i class="bi {{ $nota->completada ? 'bi-arrow-counterclockwise' : 'bi-check-lg' }}"></i>
                            </button>
                            <button type="button" class="delete-btn" onclick="eliminarNota({{ $nota->id }})" title="Eliminar">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                    <div class="nota-text">{{ $nota->contenido }}</div>
                    <div class="nota-footer">
                        <span>{{ $nota->created_at->diffForHumans() }}</span>
                        <span>
                            @if($nota->fecha_recordatorio)
                                <span class="nota-reminder">
                                    <i class="bi bi-bell-fill"></i>
                                    {{ $nota->fecha_recordatorio->format('d/m/Y') }}
                                </span>
                            @endif
                            <span class="nota-origen">
                                <i class="bi bi-{{ $nota->origen === 'voz' ? 'mic-fill' : 'keyboard' }}"></i>
                            </span>
                        </span>
                    </div>
                </div>
                @empty
                <div class="nota-empty">
                    <i class="bi bi-journal-text"></i>
                    <p>No hay notas aún. ¡Dicta tu primera nota!</p>
                </div>
                @endforelse
            </div>

            @if($notas->hasPages())
            <div style="margin-top:1rem;">{{ $notas->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnMic    = document.getElementById('btn-mic');
    const micStatus = document.getElementById('mic-status');
    const textarea  = document.getElementById('dictation-text');
    const origenField = document.getElementById('nota-origen');
    let recognition = null;
    let isRecording = false;

    // ===== WEB SPEECH API =====
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
        btnMic.style.display = 'none';
        micStatus.textContent = 'Dictado no disponible en este navegador';
        return;
    }

    recognition = new SpeechRecognition();
    recognition.lang = 'es-CL';
    recognition.continuous = true;
    recognition.interimResults = true;

    let finalTranscript = textarea.value;

    recognition.onresult = function(event) {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) {
                finalTranscript += (finalTranscript ? ' ' : '') + transcript;
            } else {
                interim += transcript;
            }
        }
        textarea.value = finalTranscript + (interim ? ' ' + interim : '');
        textarea.scrollTop = textarea.scrollHeight;
    };

    recognition.onstart = function() {
        isRecording = true;
        btnMic.classList.add('recording');
        micStatus.textContent = 'Escuchando... habla ahora';
        micStatus.classList.add('active');
        origenField.value = 'voz';
    };

    recognition.onend = function() {
        if (isRecording) {
            // Auto-restart if still in recording mode
            try { recognition.start(); } catch(e) {}
            return;
        }
        btnMic.classList.remove('recording');
        micStatus.textContent = 'Dictado finalizado';
        micStatus.classList.remove('active');
    };

    recognition.onerror = function(event) {
        if (event.error === 'not-allowed') {
            micStatus.textContent = 'Permite el acceso al micrófono';
        } else if (event.error !== 'aborted') {
            micStatus.textContent = 'Error: ' + event.error;
        }
        isRecording = false;
        btnMic.classList.remove('recording');
        micStatus.classList.remove('active');
    };

    btnMic.addEventListener('click', function() {
        if (isRecording) {
            isRecording = false;
            recognition.stop();
            btnMic.classList.remove('recording');
            micStatus.textContent = 'Pulsa el micrófono para dictar';
            micStatus.classList.remove('active');
        } else {
            finalTranscript = textarea.value;
            recognition.start();
        }
    });

    // ===== FORM SUBMIT (AJAX) =====
    const form = document.getElementById('nota-form');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = textarea.value.trim();
        if (!text) return;

        // Stop recording if active
        if (isRecording) {
            isRecording = false;
            recognition.stop();
        }

        const btn = document.getElementById('btn-save');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData,
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al guardar la nota');
            }
        })
        .catch(() => alert('Error de conexión'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-lg"></i> Guardar';
        });
    });
});

// ===== TOGGLE COMPLETADA =====
function toggleNota(id) {
    fetch(`/notas/${id}/toggle`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]').value,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

// ===== ELIMINAR =====
function eliminarNota(id) {
    if (!confirm('¿Eliminar esta nota?')) return;
    fetch(`/notas/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]').value,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}
</script>
@endpush
