@extends('layouts.app')

@section('title', 'Dashboard — ' . $formulario->nombre)

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-bar-chart-line-fill" style="color:var(--primary-color);"></i>
                Dashboard: {{ $formulario->nombre }}
            </h2>
            <p class="page-subheading">{{ $formulario->codigo }} &bull; {{ $totalResp }} respuesta(s) analizadas</p>
        </div>
        <div style="display:flex;gap:0.75rem;">
            <a href="{{ route('formularios.show', $formulario) }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Formulario
            </a>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="glass-card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('formularios.dashboard', $formulario) }}" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
            <div>
                <label style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-input" style="font-size:.82rem;padding:.4rem .65rem;">
            </div>
            <div>
                <label style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-input" style="font-size:.82rem;padding:.4rem .65rem;">
            </div>
            <div>
                <label style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Estado</label>
                <select name="estado" class="form-input" style="font-size:.82rem;padding:.4rem .65rem;width:150px;">
                    <option value="">Todos</option>
                    @foreach(['Pendiente','Aprobado','Rechazado','Borrador','Revisión'] as $e)
                        <option value="{{ $e }}" {{ request('estado') == $e ? 'selected' : '' }}>{{ $e }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-premium" style="font-size:.82rem;padding:.45rem 1rem;">
                <i class="bi bi-funnel-fill"></i> Filtrar
            </button>
            @if(request()->hasAny(['desde','hasta','estado']))
                <a href="{{ route('formularios.dashboard', $formulario) }}" class="btn-ghost" style="font-size:.82rem;padding:.45rem .75rem;">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            @endif
        </form>
    </div>

    {{-- KPIs --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin-bottom:1.25rem;">
        <div class="glass-card" style="padding:.85rem 1rem;text-align:center;">
            <div style="font-size:1.6rem;font-weight:800;color:var(--primary-color);">{{ $totalResp }}</div>
            <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;">Total Respuestas</div>
        </div>
        <div class="glass-card" style="padding:.85rem 1rem;text-align:center;">
            <div style="font-size:1.6rem;font-weight:800;color:#16a34a;">{{ $byEstado['Aprobado'] ?? 0 }}</div>
            <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;">Aprobadas</div>
        </div>
        <div class="glass-card" style="padding:.85rem 1rem;text-align:center;">
            <div style="font-size:1.6rem;font-weight:800;color:#d97706;">{{ $byEstado['Pendiente'] ?? 0 }}</div>
            <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;">Pendientes</div>
        </div>
        <div class="glass-card" style="padding:.85rem 1rem;text-align:center;">
            <div style="font-size:1.6rem;font-weight:800;color:#dc2626;">{{ $byEstado['Rechazado'] ?? 0 }}</div>
            <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;">Rechazadas</div>
        </div>
        @if(($byEstado['Borrador'] ?? 0) > 0)
        <div class="glass-card" style="padding:.85rem 1rem;text-align:center;">
            <div style="font-size:1.6rem;font-weight:800;color:#6b7280;">{{ $byEstado['Borrador'] ?? 0 }}</div>
            <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;">Borradores</div>
        </div>
        @endif
    </div>

    {{-- Row 1: Trend + Estado donut --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem;">
        <div class="glass-card">
            <h3 style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin-bottom:.75rem;">
                <i class="bi bi-graph-up"></i> Tendencia Mensual
            </h3>
            <div style="height:260px;"><canvas id="trendChart"></canvas></div>
        </div>
        <div class="glass-card">
            <h3 style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin-bottom:.75rem;">
                <i class="bi bi-pie-chart-fill"></i> Por Estado
            </h3>
            <div style="height:260px;display:flex;align-items:center;justify-content:center;"><canvas id="estadoChart"></canvas></div>
        </div>
    </div>

    {{-- Row 2: By User + By Department --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
        <div class="glass-card">
            <h3 style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin-bottom:.75rem;">
                <i class="bi bi-people-fill"></i> Top 10 Solicitantes
            </h3>
            <div style="height:280px;"><canvas id="userChart"></canvas></div>
        </div>
        <div class="glass-card">
            <h3 style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin-bottom:.75rem;">
                <i class="bi bi-building"></i> Por Departamento
            </h3>
            <div style="height:280px;"><canvas id="deptoChart"></canvas></div>
        </div>
    </div>

    {{-- Dynamic field charts --}}
    @if(count($fieldCharts) > 0)
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:1rem;margin-bottom:1rem;">
        @foreach($fieldCharts as $idx => $fc)
        <div class="glass-card">
            <h3 style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin-bottom:.75rem;">
                <i class="bi bi-tag-fill"></i> {{ $fc['label'] }}
                <span style="font-size:.68rem;font-weight:400;text-transform:none;background:rgba(79,70,229,.1);color:var(--primary-color);padding:.15rem .4rem;border-radius:4px;margin-left:.3rem;">
                    {{ count($fc['data']) }} opciones
                </span>
            </h3>
            <div style="height:260px;"><canvas id="fieldChart{{ $idx }}"></canvas></div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const isDark = document.body.classList.contains('dark-mode');
const gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
const textColor = isDark ? '#94a3b8' : '#64748b';

Chart.defaults.color = textColor;
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';

const palette = [
    '#6366f1','#f97316','#06b6d4','#22c55e','#ef4444','#a855f7',
    '#ec4899','#14b8a6','#f59e0b','#3b82f6','#84cc16','#e11d48',
    '#8b5cf6','#0ea5e9','#d946ef','#64748b'
];

// === Trend chart ===
const trendLabels = @json($trend->keys()->values());
const trendData = @json($trend->values());

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: trendLabels.map(l => {
            const [y, m] = l.split('-');
            const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            return months[parseInt(m)-1] + ' ' + y.slice(2);
        }),
        datasets: [{
            label: 'Respuestas',
            data: trendData,
            backgroundColor: 'rgba(99,102,241,.65)',
            borderColor: '#6366f1',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: gridColor } },
            x: { grid: { display: false } }
        }
    }
});

// === Estado donut ===
const estadoData = @json($byEstado);
const estadoColors = {
    'Aprobado': '#22c55e', 'Pendiente': '#f59e0b',
    'Rechazado': '#ef4444', 'Borrador': '#94a3b8', 'Revisión': '#f97316'
};

new Chart(document.getElementById('estadoChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(estadoData),
        datasets: [{
            data: Object.values(estadoData),
            backgroundColor: Object.keys(estadoData).map(k => estadoColors[k] || '#6b7280'),
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 12, font: { size: 11 } } }
        }
    }
});

// === Top Users horizontal bar ===
const userLabels = @json($byUser->keys()->values());
const userData = @json($byUser->values());

new Chart(document.getElementById('userChart'), {
    type: 'bar',
    data: {
        labels: userLabels,
        datasets: [{
            data: userData,
            backgroundColor: palette.slice(0, userLabels.length),
            borderWidth: 0,
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: gridColor } },
            y: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// === Department horizontal bar ===
const deptoLabels = @json($byDepto->keys()->values());
const deptoData = @json($byDepto->values());

new Chart(document.getElementById('deptoChart'), {
    type: 'bar',
    data: {
        labels: deptoLabels,
        datasets: [{
            data: deptoData,
            backgroundColor: palette.slice(2, 2 + deptoLabels.length),
            borderWidth: 0,
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: gridColor } },
            y: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// === Dynamic field charts ===
const fieldCharts = @json($fieldCharts);
fieldCharts.forEach((fc, idx) => {
    const el = document.getElementById('fieldChart' + idx);
    if (!el) return;

    const labels = Object.keys(fc.data);
    const values = Object.values(fc.data);
    const useDonut = labels.length <= 8;

    new Chart(el, {
        type: useDonut ? 'doughnut' : 'bar',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: useDonut
                    ? palette.slice(0, labels.length)
                    : palette.slice(0, labels.length),
                borderWidth: 0,
                borderRadius: useDonut ? 0 : 4,
            }]
        },
        options: useDonut ? {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: { position: 'right', labels: { padding: 8, font: { size: 10 }, boxWidth: 10 } }
            }
        } : {
            indexAxis: labels.length > 6 ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: gridColor } },
                y: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });
});
</script>
@endpush
