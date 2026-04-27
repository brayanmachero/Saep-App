@extends('layouts.app')
@section('title','Monitor de Correos')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Monitor de Correos</h2>
            <p class="page-subheading">Historial de todos los emails enviados o fallidos desde la plataforma</p>
        </div>
        {{-- Limpiar registros antiguos --}}
        <div>
            <button type="button" class="btn-ghost" onclick="document.getElementById('modal-limpiar').classList.remove('hidden')">
                <i class="bi bi-trash3"></i> Limpiar antiguos
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-envelope"></i></div>
            <div class="stat-info"><h3>{{ $stats['total'] }}</h3><p>Total</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info"><h3>{{ $stats['sent'] }}</h3><p>Enviados</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-info"><h3>{{ $stats['failed'] }}</h3><p>Fallidos</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:#6366f1;"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-info"><h3>{{ $stats['hoy'] }}</h3><p>Hoy</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon {{ $stats['hoy_err'] > 0 ? 'danger' : 'success' }}">
                <i class="bi bi-{{ $stats['hoy_err'] > 0 ? 'exclamation-triangle-fill' : 'shield-check' }}"></i>
            </div>
            <div class="stat-info"><h3>{{ $stats['hoy_err'] }}</h3><p>Errores hoy</p></div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert-success glass-card" style="padding:.75rem 1rem;margin-bottom:1rem;border-left:4px solid #16a34a;color:#166534;">
        <i class="bi bi-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('mail-logs.index') }}" class="filter-form glass-card" style="margin-bottom:1.25rem;">
        <div class="filter-group">
            <label>Estado</label>
            <select name="status" class="form-input">
                <option value="">Todos</option>
                <option value="sent"   {{ request('status') == 'sent'   ? 'selected' : '' }}>Enviados</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Fallidos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Tipo de mail</label>
            <select name="mailable" class="form-input">
                <option value="">Todos</option>
                @foreach($mailables as $m)
                <option value="{{ $m }}" {{ request('mailable') == $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Desde</label>
            <input type="date" name="desde" class="form-input"
                value="{{ request('desde', request()->hasAny(['status','mailable','desde','hasta','buscar']) ? '' : now()->format('Y-m-d')) }}">
        </div>
        <div class="filter-group">
            <label>Hasta</label>
            <input type="date" name="hasta" class="form-input" value="{{ request('hasta') }}">
        </div>
        <div class="filter-group">
            <label>Buscar</label>
            <input type="text" name="buscar" class="form-input" value="{{ request('buscar') }}" placeholder="Email, asunto...">
        </div>
        <div class="filter-group" style="align-self:flex-end;">
            <button type="submit" class="btn-secondary"><i class="bi bi-search"></i> Buscar</button>
            <a href="{{ route('mail-logs.index') }}" class="btn-ghost">Limpiar</a>
        </div>
    </form>

    {{-- Tabla --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th style="width:155px;">Fecha/Hora</th>
                <th>Tipo de mail</th>
                <th>Asunto</th>
                <th>Destinatario</th>
                <th style="width:90px;">Estado</th>
                <th style="width:50px;"></th>
            </tr></thead>
            <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size:13px;white-space:nowrap;">
                    {{ $log->sent_at ? $log->sent_at->format('d/m/Y H:i:s') : $log->created_at->format('d/m/Y H:i:s') }}
                </td>
                <td>
                    @if($log->mailable)
                        <span class="badge badge-secondary" style="font-size:11px;">{{ $log->mailable }}</span>
                    @else
                        <span style="color:var(--text-muted);font-size:12px;">–</span>
                    @endif
                </td>
                <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->subject }}">
                    {{ $log->subject ?? '(sin asunto)' }}
                </td>
                <td>
                    <span style="font-size:13px;">{{ $log->to_email }}</span>
                    @if($log->to_name)
                        <br><small style="color:var(--text-muted);">{{ $log->to_name }}</small>
                    @endif
                </td>
                <td>
                    @if($log->status === 'sent')
                        <span class="badge badge-success"><i class="bi bi-check-circle"></i> Enviado</span>
                    @else
                        <span class="badge badge-danger"><i class="bi bi-x-circle"></i> Fallido</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('mail-logs.show', $log) }}" class="icon-btn" title="Ver detalle">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">
                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                No hay registros con los filtros seleccionados
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if($logs->hasPages())
        <div style="padding:1rem;">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modal limpiar --}}
<div id="modal-limpiar" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
    <div class="glass-card" style="width:380px;padding:1.5rem;">
        <h3 style="margin:0 0 1rem;">Limpiar registros</h3>
        <form method="POST" action="{{ route('mail-logs.limpiar') }}">
            @csrf @method('DELETE')
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:1rem;">
                Elimina registros de correos con más de X días de antigüedad. Esta acción es irreversible.
            </p>
            <div class="filter-group" style="margin-bottom:1rem;">
                <label>Eliminar registros de más de</label>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <input type="number" name="dias" class="form-input" value="30" min="1" max="365" style="width:80px;">
                    <span style="color:var(--text-muted);">días</span>
                </div>
            </div>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" class="btn-ghost" onclick="document.getElementById('modal-limpiar').classList.add('hidden')">Cancelar</button>
                <button type="submit" class="btn-premium" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
                    <i class="bi bi-trash3"></i> Eliminar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
