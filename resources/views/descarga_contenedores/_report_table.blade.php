<div style="overflow-x:auto">
    <table class="data-table report-table">
        <thead>
            <tr>
                <th>{{ $nameLabel }}</th>
                <th>Reg.</th>
                <th>Cajas</th>
                <th>Pallets</th>
                <th>Pago ref.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td><strong>{{ $row->nombre }}</strong></td>
                <td>{{ $row->total }}</td>
                <td>{{ number_format((float) $row->cajas, 0, ',', '.') }}</td>
                <td>{{ isset($row->pallets) ? number_format((float) $row->pallets, 1, ',', '.') : '—' }}</td>
                <td>${{ number_format((float) $row->pago_total, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:1.25rem;color:var(--text-muted)">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
