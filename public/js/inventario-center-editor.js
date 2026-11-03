(() => {
    const fields = {coordinador_nombre_origen: 'nombre', cargo_contacto: 'cargo', correo_contacto: 'correo', telefono_contacto: 'telefono', jefe_operaciones: 'jefe_operaciones'};
    document.querySelectorAll('[data-center-editor]').forEach(form => {
        const select = form.elements.coordinador_id;
        const create = form.elements.crear_coordinador;
        const automatic = new Map();
        const read = () => {
            try { return JSON.parse(select.selectedOptions[0]?.dataset.profile || '{}'); }
            catch { return {}; }
        };
        const initial = read();
        Object.entries(fields).forEach(([name, key]) => {
            const input = form.elements[name];
            if (input.value === (initial[key] || '')) automatic.set(name, input.value);
            input.addEventListener('input', () => automatic.delete(name));
        });
        function populate() {
            if (create.checked) return;
            const profile = read();
            Object.entries(fields).forEach(([name, key]) => {
                const input = form.elements[name];
                const value = profile[key] || '';
                if (!input.value || automatic.get(name) === input.value) {
                    input.value = value;
                    automatic.set(name, value);
                }
            });
        }
        select.addEventListener('change', () => { create.checked = false; populate(); });
        create.addEventListener('change', () => {
            if (create.checked) {
                select.value = '';
                Object.keys(fields).forEach(name => {
                    if (automatic.get(name) === form.elements[name].value) form.elements[name].value = '';
                });
                automatic.clear();
                form.elements.coordinador_nombre_origen.focus();
            }
        });
        form.elements.coordinador_nombre_origen.addEventListener('change', event => {
            if (create.checked) return;
            const matches = Array.from(select.options).filter(option => {
                try { return JSON.parse(option.dataset.profile || '{}').nombre?.toLocaleLowerCase() === event.target.value.trim().toLocaleLowerCase(); }
                catch { return false; }
            });
            if (matches.length === 1) { select.value = matches[0].value; select.dispatchEvent(new Event('change', {bubbles: true})); }
        });
        populate();
    });
})();
