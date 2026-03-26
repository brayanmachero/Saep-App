@extends('layouts.app')

@section('title', 'Previsualización de Importación')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-eye-fill" style="color:var(--primary-color)"></i> Previsualización</h2>
            <p class="page-subheading">
                Revisa los datos antes de importar — {{ $totalRows }} registro(s) encontrados
            </p>
        </div>
        <a href="{{ route('importacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    {{-- Resumen --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-people-fill" style="font-size:1.15rem;color:var(--primary-color);"></i>
            </div>
            <div>
                <p style="font-size:1.4rem;font-weight:800;margin:0;color:var(--text-main);">{{ $totalRows }}</p>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;">Registros</p>
            </div>
        </div>
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-layout-three-columns" style="font-size:1.15rem;color:#d97706;"></i>
            </div>
            <div>
                <p style="font-size:1.4rem;font-weight:800;margin:0;color:var(--text-main);">{{ count($headers) }}</p>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;">Columnas detectadas</p>
            </div>
        </div>
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-filetype-csv" style="font-size:1.15rem;color:#059669;"></i>
            </div>
            <div>
                <p style="font-size:1.4rem;font-weight:800;margin:0;color:var(--text-main);">{{ ucfirst($tipo) }}</p>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;">Tipo de importación</p>
            </div>
        </div>
    </div>

    {{-- Columnas detectadas --}}
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-columns-gap"></i> Columnas detectadas
        </h3>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            @foreach($headers as $header)
                <span class="badge secondary" style="font-size:.8rem;">{{ $header }}</span>
            @endforeach
        </div>
    </div>

    {{-- Preview de datos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-table"></i> Vista previa
            @if($totalRows > 20)
                <span style="text-transform:none;letter-spacing:normal;font-weight:400;">(primeros 20 de {{ $totalRows }})</span>
            @endif
        </h3>
        <div class="glass-table-container">
            <table class="glass-table" style="font-size:.8rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        @foreach($headers as $h)
                            <th style="white-space:nowrap;">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        @foreach($headers as $h)
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $row[$h] ?? '' }}
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="glass-card" style="background:var(--surface-color);border:1px solid var(--surface-border);">
        <div style="display:flex;gap:1rem;justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <p style="margin:0;font-size:.85rem;color:var(--text-muted);">
                <i class="bi bi-info-circle"></i> Revisa los datos antes de confirmar la importación.
            </p>
            <div style="display:flex;gap:.75rem;align-items:center;">
                <a href="{{ route('importacion.index') }}" class="btn-ghost">
                    <i class="bi bi-x"></i> Cancelar
                </a>
                <form method="POST" action="{{ route('importacion.import') }}">
                    @csrf
                    <button type="submit" class="btn-premium" onclick="this.disabled=true;this.form.submit();">
                        <i class="bi bi-cloud-upload-fill"></i> Importar {{ $totalRows }} registro(s)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
