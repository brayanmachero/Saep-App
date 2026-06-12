@extends('layouts.app')
@section('title','Detalle de Correo')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Detalle de correo</h2>
            <p class="page-subheading">#{{ $mailLog->id }} &mdash; {{ $mailLog->sent_at?->format('d/m/Y H:i:s') ?? $mailLog->created_at->format('d/m/Y H:i:s') }}</p>
        </div>
        <a href="{{ route('mail-logs.index') }}" class="btn-ghost"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.25rem;align-items:start;">

        {{-- Panel izquierdo: metadata --}}
        <div class="glass-card" style="padding:1.25rem;">
            <h4 style="margin:0 0 1rem;font-size:15px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Información</h4>
            <dl style="display:grid;gap:.75rem;">
                <div>
                    <dt style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Estado</dt>
                    <dd style="margin:0;margin-top:.25rem;">
                        @if($mailLog->status === 'sent')
                            <span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Enviado correctamente</span>
                        @elseif($mailLog->status === 'blocked')
                            <span class="badge" style="background:rgba(245,158,11,.14);color:#b45309;"><i class="bi bi-slash-circle-fill"></i> Bloqueado por configuracion</span>
                        @else
                            <span class="badge badge-danger"><i class="bi bi-x-circle-fill"></i> Falló el envío</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Asunto</dt>
                    <dd style="margin:0;margin-top:.25rem;font-weight:600;">{{ $mailLog->subject ?? '(sin asunto)' }}</dd>
                </div>
                <div>
                    <dt style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Destinatario</dt>
                    <dd style="margin:0;margin-top:.25rem;">
                        {{ $mailLog->to_email }}
                        @if($mailLog->to_name)
                            <br><small style="color:var(--text-muted);">{{ $mailLog->to_name }}</small>
                        @endif
                    </dd>
                </div>
                @if($mailLog->mailable)
                <div>
                    <dt style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Clase Mailable</dt>
                    <dd style="margin:0;margin-top:.25rem;font-family:monospace;font-size:13px;">{{ $mailLog->mailable }}</dd>
                </div>
                @endif
                <div>
                    <dt style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Enviado</dt>
                    <dd style="margin:0;margin-top:.25rem;font-size:13px;">
                        {{ $mailLog->sent_at?->format('d/m/Y H:i:s') ?? $mailLog->created_at->format('d/m/Y H:i:s') }}
                    </dd>
                </div>
                @if(in_array($mailLog->status, ['failed', 'blocked'], true) && $mailLog->error_message)
                <div style="background:{{ $mailLog->status === 'blocked' ? 'rgba(245,158,11,.10)' : 'rgba(239,68,68,.08)' }};border-radius:.5rem;padding:.75rem;">
                    <dt style="font-size:11px;color:{{ $mailLog->status === 'blocked' ? '#b45309' : '#dc2626' }};text-transform:uppercase;letter-spacing:.05em;font-weight:700;">
                        {{ $mailLog->status === 'blocked' ? 'Motivo' : 'Error' }}
                    </dt>
                    <dd style="margin:.25rem 0 0;font-family:monospace;font-size:12px;color:{{ $mailLog->status === 'blocked' ? '#92400e' : '#dc2626' }};word-break:break-word;">{{ $mailLog->error_message }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Panel derecho: preview HTML --}}
        <div class="glass-card" style="padding:1.25rem;">
            <h4 style="margin:0 0 1rem;font-size:15px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                Preview del email
            </h4>
            @if($mailLog->body_html)
                <div style="border:1px solid var(--border-color);border-radius:.5rem;overflow:hidden;background:#fff;">
                    <iframe
                        id="mail-preview-frame"
                        srcdoc="{{ htmlspecialchars($mailLog->body_html, ENT_QUOTES, 'UTF-8') }}"
                        sandbox="allow-same-origin"
                        style="width:100%;height:600px;border:none;display:block;"
                        title="Preview del correo"
                        loading="lazy"
                    ></iframe>
                </div>
            @else
                <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                    <i class="bi bi-file-earmark-x" style="font-size:2.5rem;display:block;margin-bottom:.75rem;"></i>
                    No hay preview disponible para este correo
                    @if($mailLog->status === 'failed')
                        <br><small>El mail falló antes de generarse el cuerpo HTML</small>
                    @elseif($mailLog->status === 'blocked')
                        <br><small>El mail fue bloqueado antes de enviarse.</small>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
