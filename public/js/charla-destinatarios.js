// Standalone so rankings and copying remain available if charts fail to load.
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('destinatarios-tabla');
    if (!table) return;
    const select = document.getElementById('destinatarios-orden');
    const button = document.getElementById('destinatarios-copiar');
    const status = document.getElementById('destinatarios-estado');
    const title = document.getElementById('destinatarios-titulo');
    const rows = [...table.tBodies[0].rows].map(element => {
        const cells = [...element.cells].map(cell => cell.textContent.trim());
        return { element, cells, total: Number(cells[1]), completed: Number(cells[2]),
            pending: Number(element.dataset.pendientes), name: cells[0] };
    });
    let visible = [];
    const alphabetical = (a, b) => a.name.localeCompare(b.name, 'es');
    function refresh() {
        const mode = select.value;
        const ordered = [...rows].sort((a, b) => {
            const rateDifference = a.completed / a.total - b.completed / b.total;
            if (mode === 'mayor' || mode === 'menor') {
                return (mode === 'mayor' ? -rateDifference : rateDifference)
                    || b.total - a.total || alphabetical(a, b);
            }
            return (mode === 'pendientes' ? b.pending - a.pending : 0) || alphabetical(a, b);
        });
        visible = mode === 'todos' ? ordered : ordered.slice(0, 10);
        rows.forEach(row => { row.element.hidden = !visible.includes(row); });
        ordered.forEach(row => table.tBodies[0].append(row.element));
        title.textContent = select.selectedOptions[0].textContent;
        status.textContent = `${visible.length} de ${rows.length} destinatarios del período filtrado. `
            + (mode === 'mayor' || mode === 'menor'
                ? 'Orden por porcentaje; en empate, más registros y luego nombre.' : '');
        button.disabled = visible.length === 0;
    }
    select.value = 'pendientes';
    select.addEventListener('change', refresh);
    refresh();

    // Draw a clean report image directly: no remote service or screenshot dependency.
    function reportImage() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('No se pudo crear la imagen.');
        const width = 1400;
        const wrap = (text, maxWidth) => {
            const lines = [];
            let line = '';
            for (const char of text) {
                if (ctx.measureText(line + char).width > maxWidth && line) {
                    lines.push(line); line = '';
                }
                line += char;
            }
            lines.push(line);
            return lines;
        };
        ctx.font = '16px Arial';
        const filterLines = wrap(table.dataset.periodo, width - 64);
        const prepared = visible.map(row => ({ row, lines: wrap(row.name, 520) }));
        const headerY = 100 + filterLines.length * 22;
        const height = headerY + 62 + prepared.reduce((sum, item) => sum + Math.max(48, item.lines.length * 22 + 20), 0) + 86;
        // Keep large "Todos" exports within practical browser canvas limits.
        if (height > 16000) throw new Error('La tabla es demasiado grande para una imagen. Reduce el período o selecciona un Top 10.');
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.fillStyle = '#172033';
        ctx.font = 'bold 24px Arial';
        ctx.fillText('Seguimiento Charlas SST · Destinatario actual en Kizeo', 32, 42);
        ctx.font = 'bold 18px Arial';
        ctx.fillText(`${title.textContent} · ${visible.length} de ${rows.length} destinatarios`, 32, 75);
        ctx.font = '16px Arial';
        ctx.fillStyle = '#526079';
        filterLines.forEach((line, i) => ctx.fillText(line, 32, 103 + i * 22));
        ctx.fillStyle = '#edf1f6';
        ctx.fillRect(24, headerY, width - 48, 62);
        const centers = [650, 800, 965, 1135, 1300];
        ctx.fillStyle = '#334155';
        ctx.font = 'bold 16px Arial';
        ctx.fillText('Destinatario', 32, headerY + 35);
        ctx.textAlign = 'center';
        ['Registros', 'Completadas', 'Recuperadas', 'Transferidas', 'Tasa'].forEach((label, i) =>
            ctx.fillText(label, centers[i], headerY + 26));
        ctx.font = '14px Arial';
        [2, 3].forEach(i => ctx.fillText('(pendientes)', centers[i], headerY + 47));
        let y = headerY + 62;
        prepared.forEach(({ row, lines }, index) => {
            const h = Math.max(48, lines.length * 22 + 20);
            ctx.fillStyle = index % 2 ? '#f4f7fa' : '#ffffff';
            ctx.fillRect(24, y, width - 48, h);
            ctx.textAlign = 'left';
            ctx.font = '16px Arial';
            ctx.fillStyle = '#172033';
            lines.forEach((line, i) => ctx.fillText(line, 32, y + 28 + i * 22));
            ctx.textAlign = 'center';
            row.cells.slice(1).forEach((value, i) => {
                ctx.fillStyle = ['#172033', '#15803d', '#b45309', '#c2410c', '#6d28d9'][i];
                ctx.fillText(value, centers[i], y + h / 2 + 6);
            });
            y += h;
        });
        ctx.textAlign = 'left';
        ctx.fillStyle = '#526079';
        ctx.font = '15px Arial';
        ctx.fillText('Tasa del grupo filtrado; no representa el historial completo de la persona.', 32, y + 32);
        ctx.fillText('Fuente: Kizeo / SAEP. Los registros sin destinatario no se incluyen en esta tabla.', 32, y + 56);
        return new Promise((resolve, reject) => canvas.toBlob(blob =>
            blob ? resolve(blob) : reject(new Error('No se pudo generar el PNG.')), 'image/png'));
    }
    button.addEventListener('click', async () => {
        button.disabled = true;
        select.disabled = true;
        status.textContent = 'Preparando imagen…';
        let blobPromise;
        const download = async () => {
            const blob = await blobPromise;
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `charlas-sst-destinatarios-${select.value}.png`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            status.textContent = 'No se pudo acceder al portapapeles. Se descargó el PNG para adjuntarlo al correo.';
        };
        try {
            blobPromise = reportImage();
            if (navigator.clipboard?.write && typeof ClipboardItem !== 'undefined') {
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]);
                    status.textContent = 'Imagen copiada. Puedes pegarla en el correo con Ctrl+V.';
                } catch {
                    await download();
                }
            } else {
                await download();
            }
        } catch (error) {
            status.textContent = error.message || 'No se pudo copiar la imagen.';
        } finally {
            button.disabled = false;
            select.disabled = false;
        }
    });
});
