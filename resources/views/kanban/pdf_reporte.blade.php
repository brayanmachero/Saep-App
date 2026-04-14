<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Kanban — {{ $kanban->nombre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #1e293b; font-size: 11px; }
        .header { background: #0f1b4c; color: #fff; padding: 18px 28px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 16px; font-weight: 700; }
        .header .meta { font-size: 10px; opacity: .8; }
        .orange-bar { height: 4px; background: linear-gradient(90deg, #f97316, #fb923c, #f97316); }
        .stats { display: flex; gap: 12px; padding: 14px 28px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .stat-card { flex: 1; text-align: center; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; }
        .stat-value { font-size: 18px; font-weight: 700; }
        .stat-label { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-top: 2px; }
        .board { padding: 16px 28px; }
        .board-title { font-size: 13px; font-weight: 700; margin-bottom: 12px; color: #0f1b4c; }
        .columns { display: flex; gap: 12px; }
        .column { flex: 1; min-width: 0; }
        .col-header { padding: 6px 10px; border-radius: 5px 5px 0 0; font-size: 10px; font-weight: 700; color: #fff; display: flex; justify-content: space-between; }
        .col-body { border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 5px 5px; padding: 6px; min-height: 40px; }
        .task { padding: 6px 8px; margin-bottom: 5px; border-radius: 4px; border-left: 3px solid; background: #fff; border: 1px solid #f1f5f9; }
        .task-title { font-size: 10px; font-weight: 600; margin-bottom: 2px; }
        .task-meta { font-size: 8px; color: #64748b; display: flex; gap: 6px; }
        .priority-alta { border-left-color: #dc2626; }
        .priority-media { border-left-color: #f59e0b; }
        .priority-baja { border-left-color: #16a34a; }
        .tag { display: inline-block; font-size: 7px; padding: 1px 4px; border-radius: 2px; font-weight: 700; margin-right: 2px; }
        .footer { text-align: center; padding: 12px 28px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; margin-top: 12px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <div>
            <h1>{{ $kanban->nombre }}</h1>
            @if($kanban->descripcion)
            <div class="meta">{{ Str::limit($kanban->descripcion, 100) }}</div>
            @endif
        </div>
        <div style="text-align:right;">
            <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            @if($kanban->centroCosto)
            <div class="meta"><strong>CC:</strong> {{ $kanban->centroCosto->nombre }}</div>
            @endif
            <div class="meta"><strong>Creador:</strong> {{ $kanban->creador?->name ?? 'Sistema' }}</div>
        </div>
    </div>
    <div class="orange-bar"></div>

    {{-- Stats --}}
    <div class="stats">
        <div class="stat-card">
            <div class="stat-value" style="color:#0f1b4c;">{{ $totalTareas }}</div>
            <div class="stat-label">Total Tareas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:#dc2626;">{{ $tareasAlta }}</div>
            <div class="stat-label">Prioridad Alta</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:#f59e0b;">{{ $tareasVenc }}</div>
            <div class="stat-label">Vencidas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:#16a34a;">{{ $kanban->columnas->count() }}</div>
            <div class="stat-label">Columnas</div>
        </div>
    </div>

    {{-- Board columns --}}
    <div class="board">
        <div class="board-title"><span style="color:#f97316;">&#9632;</span> Tablero Kanban</div>
        <div class="columns">
            @foreach($kanban->columnas as $columna)
            <div class="column">
                <div class="col-header" style="background:{{ $columna->color }};">
                    <span>{{ $columna->nombre }}</span>
                    <span>{{ $columna->tareas->count() }}</span>
                </div>
                <div class="col-body">
                    @foreach($columna->tareas as $tarea)
                    <div class="task priority-{{ strtolower($tarea->prioridad) }}">
                        @if($tarea->etiquetas->isNotEmpty())
                        <div style="margin-bottom:2px;">
                            @foreach($tarea->etiquetas as $et)
                            <span class="tag" style="background:{{ $et->color }}20;color:{{ $et->color }};">{{ $et->nombre }}</span>
                            @endforeach
                        </div>
                        @endif
                        <div class="task-title">{{ $tarea->titulo }}</div>
                        <div class="task-meta">
                            <span>{{ $tarea->prioridad }}</span>
                            @if($tarea->asignado)
                            <span>{{ $tarea->asignado->name }}</span>
                            @endif
                            @if($tarea->fecha_vencimiento)
                            <span>{{ $tarea->fecha_vencimiento->format('d/m') }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    @if($columna->tareas->isEmpty())
                    <div style="text-align:center;color:#cbd5e1;font-size:8px;padding:8px;">Sin tareas</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        &copy; {{ date('Y') }} S.A.E.P. Ltda. &mdash; saep.cl &bull; Reporte generado automáticamente
    </div>
</body>
</html>
