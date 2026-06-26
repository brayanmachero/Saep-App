@extends('layouts.app')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-main);margin:0;">
            <i class="bi bi-table" style="color:var(--primary-color);"></i> Matriz de Retención y Encargados
        </h1>
        <p style="color:var(--text-muted);font-size:.9rem;margin:.35rem 0 0;">
            Criterios operativos para conservación, supresión, bloqueo y propagación a terceros.
        </p>
    </div>
    <a href="{{ route('proteccion-datos.administrar') }}" style="padding:.6rem 1rem;border:1px solid var(--border-color);border-radius:8px;color:var(--text-main);text-decoration:none;font-size:.85rem;font-weight:600;">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card-glass" style="padding:1.25rem;margin-bottom:1.5rem;border-left:4px solid #f59e0b;">
    <p style="margin:0;color:var(--text-main);font-size:.9rem;line-height:1.55;">
        Esta matriz es una base de trabajo versionada. Los plazos definitivos deben validarse con responsable legal/privacidad antes de automatizar purgas o anonimización programada. La plataforma puede ejecutar acciones sobre los datos que administra; para otros sistemas, repositorios o proveedores, debe quedar una tarea de coordinación y evidencia de gestión con el encargado correspondiente.
    </p>
</div>

<div class="card-glass" style="padding:1.5rem;margin-bottom:1.75rem;">
    <h2 style="font-size:1.05rem;font-weight:700;color:var(--text-main);margin:0 0 1rem;">
        <i class="bi bi-hourglass-split" style="color:var(--primary-color);"></i> Retención por módulo
    </h2>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:.86rem;">
            <thead>
                <tr style="background:var(--bg-color);">
                    <th style="padding:.8rem;text-align:left;border-bottom:2px solid var(--border-color);">Módulo</th>
                    <th style="padding:.8rem;text-align:left;border-bottom:2px solid var(--border-color);">Datos</th>
                    <th style="padding:.8rem;text-align:left;border-bottom:2px solid var(--border-color);">Base</th>
                    <th style="padding:.8rem;text-align:left;border-bottom:2px solid var(--border-color);">Retención</th>
                    <th style="padding:.8rem;text-align:left;border-bottom:2px solid var(--border-color);">Acción</th>
                    <th style="padding:.8rem;text-align:left;border-bottom:2px solid var(--border-color);">Riesgo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matriz as $fila)
                    @php
                        $riesgoColor = match($fila['riesgo'] ?? 'medio') {
                            'critico' => '#dc2626',
                            'alto' => '#ea580c',
                            'medio' => '#f59e0b',
                            default => '#64748b',
                        };
                    @endphp
                    <tr style="border-bottom:1px solid var(--border-color);vertical-align:top;">
                        <td style="padding:.85rem;font-weight:700;color:var(--text-main);">{{ $fila['modulo'] }}</td>
                        <td style="padding:.85rem;color:var(--text-muted);line-height:1.45;">{{ $fila['datos'] }}</td>
                        <td style="padding:.85rem;color:var(--text-main);line-height:1.45;">{{ $fila['base'] }}</td>
                        <td style="padding:.85rem;color:var(--text-main);line-height:1.45;">{{ $fila['retencion'] }}</td>
                        <td style="padding:.85rem;color:var(--text-main);line-height:1.45;">{{ $fila['accion_vencimiento'] }}</td>
                        <td style="padding:.85rem;">
                            <span style="display:inline-flex;padding:.25rem .65rem;border-radius:999px;background:{{ $riesgoColor }}20;color:{{ $riesgoColor }};font-weight:700;font-size:.78rem;">
                                {{ ucfirst($fila['riesgo'] ?? 'medio') }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card-glass" style="padding:1.5rem;">
    <h2 style="font-size:1.05rem;font-weight:700;color:var(--text-main);margin:0 0 1rem;">
        <i class="bi bi-diagram-3" style="color:var(--primary-color);"></i> Encargados externos y propagación
    </h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
        @foreach($encargados as $encargado)
            <div style="border:1px solid var(--border-color);border-radius:8px;padding:1rem;background:var(--bg-color);">
                <div style="font-weight:800;color:var(--text-main);margin-bottom:.25rem;">{{ $encargado['nombre'] }}</div>
                <div style="color:var(--primary-color);font-size:.8rem;font-weight:700;margin-bottom:.75rem;">{{ $encargado['tipo'] }}</div>
                <p style="color:var(--text-muted);font-size:.84rem;line-height:1.45;margin:0 0 .75rem;">{{ $encargado['datos'] }}</p>
                <div style="font-size:.82rem;color:var(--text-main);line-height:1.45;">
                    <strong>Acción ante supresión:</strong> {{ $encargado['accion_supresion'] }}
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
