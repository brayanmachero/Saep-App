@extends('layouts.app')
@section('title', 'Histórico - ' . $cotizacion->numero)
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Histórico de Versiones</h2>
            <p class="page-subheading">Todas las versiones de la cotización {{ $cotizacion->numero }}</p>
        </div>
        <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    {{-- Timeline de Versiones --}}
    <div class="glass-card">
        <div style="position:relative;padding:2rem 0">
            @foreach($versiones as $idx => $version)
            <div style="display:flex;gap:2rem;margin-bottom:3rem;position:relative">
                {{-- Timeline Dot --}}
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0">
                    <div style="
                        width:40px;
                        height:40px;
                        border-radius:50%;
                        background:{{ $version->id === $cotizacion->id ? 'var(--accent-primary)' : 'var(--bg-tertiary)' }};
                        border:3px solid {{ $version->id === $cotizacion->id ? 'var(--accent-primary)' : 'var(--surface-border)' }};
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:{{ $version->id === $cotizacion->id ? 'white' : 'var(--text-muted)' }};
                        font-weight:700;
                        font-size:.9rem
                    ">
                        {{ $version->version }}
                    </div>
                    @if($idx < $versiones->count() - 1)
                    <div style="width:3px;height:80px;background:var(--surface-border);margin-top:.5rem"></div>
                    @endif
                </div>

                {{-- Content --}}
                <div style="flex:1;margin-top:.5rem">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:1rem;flex-wrap:wrap;gap:1rem">
                        <div>
                            <h4 style="margin:0 0 .25rem 0;font-size:1rem">
                                Versión {{ $version->version }}
                                @if($version->id === $cotizacion->id)
                                <span class="badge badge-success">Actual</span>
                                @endif
                            </h4>
                            <div style="font-size:.85rem;color:var(--text-muted)">
                                <i class="bi bi-calendar3"></i> {{ $version->created_at->format('d/m/Y H:i') }}
                                • <i class="bi bi-person-fill"></i> {{ $version->usuario->name ?? 'Sistema' }}
                            </div>
                        </div>

                        <div style="display:flex;gap:.5rem">
                            <a href="{{ route('comercial.cotizaciones.show', $version) }}" class="btn-secondary" style="font-size:.85rem">
                                <i class="bi bi-eye-fill"></i> Ver
                            </a>
                            <a href="{{ route('comercial.cotizaciones.pdf', $version) }}" class="btn-secondary" style="font-size:.85rem" target="_blank">
                                <i class="bi bi-file-pdf-fill"></i> PDF
                            </a>
                        </div>
                    </div>

                    {{-- Cambios realizados --}}
                    @if($version->auditorias->where('accion', 'versionada')->first())
                    <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--accent-secondary);margin-bottom:1rem">
                        <div style="font-size:.85rem;font-weight:600;margin-bottom:.5rem"><i class="bi bi-arrow-repeat"></i> Cambios en esta versión:</div>
                        <ul style="margin:0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted)">
                            @if($version->cotizacion_anterior_id)
                            @php
                                $auditoria = $version->auditorias->where('accion', 'versionada')->first();
                                $cambios = $auditoria->cambios ?? [];
                            @endphp
                            @if(is_array($cambios) && count($cambios) > 0)
                                @foreach($cambios as $campo => $valores)
                                <li>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $campo)) }}:</strong>
                                    {{ $valores['old'] ?? 'N/A' }} → {{ $valores['new'] ?? 'N/A' }}
                                </li>
                                @endforeach
                            @else
                            <li>Recálculo de valores basado en parámetros actualizados</li>
                            @endif
                            @else
                            <li>Versión original de la cotización</li>
                            @endif
                        </ul>
                    </div>
                    @endif

                    {{-- Resumen financiero --}}
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;padding:.75rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:.5rem">
                        <div>
                            <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em">Remuneraciones</div>
                            <strong>${{ number_format($version->total_remuneraciones, 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em">Cotizaciones</div>
                            <strong>${{ number_format($version->total_cotizaciones, 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em">Provisiones</div>
                            <strong>${{ number_format($version->total_provisiones, 0, ',', '.') }}</strong>
                        </div>
                        <div style="border-left:2px solid var(--accent-primary);padding-left:.75rem">
                            <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em">Precio Venta</div>
                            <strong style="color:var(--accent-primary)">${{ number_format($version->precio_venta, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    {{-- Estado --}}
                    @if($version->estado)
                    <div style="margin-top:.75rem;padding:.5rem .75rem;background:var(--bg-tertiary);border-radius:.35rem;font-size:.85rem;display:inline-block">
                        <strong>Estado:</strong>
                        <span class="badge {{
                            $version->estado === 'vigente' ? 'badge-success' :
                            ($version->estado === 'aprobada' ? 'badge-info' : 'badge-warning')
                        }}" style="margin-left:.5rem">
                            {{ ucfirst(str_replace('_', ' ', $version->estado)) }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
