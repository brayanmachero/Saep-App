<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización {{ $cotizacion->numero }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.4;
            font-size: 10pt;
        }

        .page {
            width: 21cm;
            height: 29.7cm;
            padding: 15mm;
            margin: 0;
            background: white;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1e40af;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo {
            width: 150px;
        }

        .logo img {
            max-width: 100%;
            height: auto;
        }

        .company-info {
            text-align: right;
            font-size: 9pt;
        }

        .company-info h1 {
            color: #1e40af;
            font-size: 16pt;
            margin-bottom: 5px;
        }

        .company-info p {
            margin: 2px 0;
            color: #666;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            color: rgba(0, 0, 0, 0.08);
            font-weight: bold;
            z-index: -1;
        }

        .quotation-header {
            background: #f3f4f6;
            border: 2px solid #e5e7eb;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            font-size: 9pt;
        }

        .quotation-header-item {
            padding: 5px;
        }

        .quotation-header-label {
            color: #666;
            font-weight: 600;
            font-size: 8pt;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .quotation-header-value {
            color: #1e40af;
            font-weight: 700;
            font-size: 11pt;
        }

        .section-title {
            background: #1e40af;
            color: white;
            padding: 8px 12px;
            margin: 15px 0 10px 0;
            font-weight: 700;
            font-size: 11pt;
            border-radius: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        thead {
            background: #e5e7eb;
            color: #374151;
            font-weight: 600;
            font-size: 9pt;
        }

        th {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            font-size: 9pt;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .amount {
            font-weight: 600;
            color: #1e40af;
        }

        .summary-box {
            background: #f0f9ff;
            border: 2px solid #1e40af;
            padding: 12px;
            margin-top: 12px;
            border-radius: 6px;
            font-size: 9pt;
        }

        .summary-row {
            display: grid;
            grid-template-columns: 1fr 150px;
            gap: 10px;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #dbeafe;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            text-align: right;
            font-weight: 500;
        }

        .summary-value {
            font-weight: 700;
            color: #1e40af;
        }

        .total-row {
            background: #1e40af;
            color: white;
            padding: 10px;
            font-size: 12pt;
            font-weight: 700;
            display: grid;
            grid-template-columns: 1fr 150px;
            gap: 10px;
            border-radius: 3px;
            margin-top: 8px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }

        .note-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 8px 10px;
            margin-top: 10px;
            font-size: 8pt;
            border-radius: 3px;
        }

        .client-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 9pt;
        }

        .client-info-box {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 3px;
            border: 1px solid #e5e7eb;
        }

        .client-info-label {
            color: #666;
            font-weight: 600;
            font-size: 8pt;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .client-info-value {
            color: #1f2937;
            font-weight: 500;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    @if($es_borrador ?? false)
    <div class="watermark">BORRADOR</div>
    @endif

    <div class="page">
        {{-- Header con Logo --}}
        <div class="header">
            <div class="logo">
                @if($logo)
                <img src="data:image/png;base64,{{ $logo }}" alt="SAEP">
                @else
                <div style="color:#1e40af;font-weight:bold;font-size:14pt">SAEP</div>
                @endif
            </div>
            <div class="company-info">
                <h1>COTIZACIÓN</h1>
                <p><strong>{{ $cotizacion->numero }}</strong></p>
                <p>{{ $cotizacion->fecha_cotizacion->format('d de F de Y') }}</p>
            </div>
        </div>

        {{-- Info de Cotización --}}
        <div class="quotation-header">
            <div class="quotation-header-item">
                <div class="quotation-header-label">Cliente</div>
                <div class="quotation-header-value">{{ substr($cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre, 0, 25) }}</div>
            </div>
            <div class="quotation-header-item">
                <div class="quotation-header-label">RUT</div>
                <div class="quotation-header-value">{{ $cotizacion->cliente->rut }}</div>
            </div>
            <div class="quotation-header-item">
                <div class="quotation-header-label">Modalidad</div>
                <div class="quotation-header-value">{{ $cotizacion->modalidad->codigo }}</div>
            </div>
            <div class="quotation-header-item">
                <div class="quotation-header-label">Centro Costo</div>
                <div class="quotation-header-value">{{ substr($cotizacion->centroCosto->nombre, 0, 20) }}</div>
            </div>
        </div>

        {{-- Información del Cliente --}}
        <div class="client-info">
            <div class="client-info-box">
                <div class="client-info-label">Email</div>
                <div class="client-info-value">{{ $cotizacion->cliente->email }}</div>
                <div class="client-info-label" style="margin-top:6px">Teléfono</div>
                <div class="client-info-value">{{ $cotizacion->cliente->telefono ?? 'N/A' }}</div>
            </div>
            <div class="client-info-box">
                <div class="client-info-label">Dirección</div>
                <div class="client-info-value">{{ $cotizacion->cliente->direccion ?? '—' }}</div>
                <div class="client-info-label" style="margin-top:6px">Región</div>
                <div class="client-info-value">{{ $cotizacion->cliente->region ?? '—' }}</div>
            </div>
        </div>

        {{-- Detalles de Remuneraciones --}}
        <div class="section-title">Remuneraciones y Conceptos</div>
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right" style="width:100px">Valor Base</th>
                    <th class="text-right" style="width:80px">%</th>
                    <th class="text-right" style="width:100px">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detalles->where('tipo', 'remuneracion') as $detalle)
                <tr>
                    <td><strong>{{ $detalle->concepto }}</strong></td>
                    <td class="text-right amount">${{ number_format($detalle->valor_base, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($detalle->porcentaje, 2) }}%</td>
                    <td class="text-right amount">${{ number_format($detalle->valor, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="background:#e5e7eb;font-weight:700">
                    <td colspan="3" class="text-right">SUBTOTAL REMUNERACIONES</td>
                    <td class="text-right amount">${{ number_format($cotizacion->total_remuneraciones, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Cotizaciones (ISES) --}}
        <div class="section-title">Cotizaciones (ISES)</div>
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right" style="width:100px">Valor Base</th>
                    <th class="text-right" style="width:80px">%</th>
                    <th class="text-right" style="width:100px">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detalles->where('tipo', 'cotizacion') as $detalle)
                <tr>
                    <td>{{ $detalle->concepto }}</td>
                    <td class="text-right">${{ number_format($detalle->valor_base, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($detalle->porcentaje, 2) }}%</td>
                    <td class="text-right amount">${{ number_format($detalle->valor, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="background:#e5e7eb;font-weight:700">
                    <td colspan="3" class="text-right">TOTAL COTIZACIONES</td>
                    <td class="text-right amount">${{ number_format($cotizacion->total_cotizaciones, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Provisiones --}}
        <div class="section-title">Provisiones y Beneficios</div>
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right" style="width:100px">Valor Base</th>
                    <th class="text-right" style="width:80px">%</th>
                    <th class="text-right" style="width:100px">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detalles->where('tipo', 'provision') as $detalle)
                <tr>
                    <td>{{ $detalle->concepto }}</td>
                    <td class="text-right">${{ number_format($detalle->valor_base, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($detalle->porcentaje, 2) }}%</td>
                    <td class="text-right amount">${{ number_format($detalle->valor, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="background:#e5e7eb;font-weight:700">
                    <td colspan="3" class="text-right">TOTAL PROVISIONES</td>
                    <td class="text-right amount">${{ number_format($cotizacion->total_provisiones, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Gastos Operacionales --}}
        <div class="section-title">Gastos Operacionales</div>
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right" style="width:100px">Valor Base</th>
                    <th class="text-right" style="width:80px">%</th>
                    <th class="text-right" style="width:100px">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detalles->where('tipo', 'gasto') as $detalle)
                <tr>
                    <td>{{ $detalle->concepto }}</td>
                    <td class="text-right">${{ number_format($detalle->valor_base, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($detalle->porcentaje, 2) }}%</td>
                    <td class="text-right amount">${{ number_format($detalle->valor, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="background:#e5e7eb;font-weight:700">
                    <td colspan="3" class="text-right">TOTAL GASTOS</td>
                    <td class="text-right amount">${{ number_format($cotizacion->total_gastos, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Resumen Final --}}
        <div class="summary-box">
            <div class="summary-row">
                <div class="summary-label">Subtotal:</div>
                <div class="summary-value">${{ number_format($cotizacion->subtotal, 0, ',', '.') }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Margen ({{ number_format($cotizacion->datos_calculo['margen_porcentaje'] ?? 0, 2) }}%):</div>
                <div class="summary-value">${{ number_format($cotizacion->datos_calculo['margen'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="total-row">
                <div>PRECIO VENTA TOTAL</div>
                <div class="text-right">${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Observaciones --}}
        @if($cotizacion->observaciones)
        <div class="note-box">
            <strong>Notas:</strong> {{ $cotizacion->observaciones }}
        </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            <p><strong>SAEP - Soluciones de Asesoramiento en Recursos Humanos</strong></p>
            <p>Este documento es una cotización válida y confidencial. Generado automáticamente por el sistema.</p>
            <p style="margin-top:8px;color:#999">Documento: {{ $cotizacion->numero }} | Versión: {{ $cotizacion->version }} | Fecha: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</body>
</html>
