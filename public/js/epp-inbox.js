document.querySelectorAll('[data-epp-panel]').forEach(button => {
    const panel = document.getElementById(button.dataset.eppPanel);
    button.addEventListener('click', () => panel.showModal());
    panel.querySelector('[data-close]').addEventListener('click', () => panel.close());
    panel.addEventListener('click', event => {
        if (event.target === panel && event.clientX < panel.getBoundingClientRect().left) panel.close();
    });
    if (panel.dataset.open === '1') panel.showModal();
});
document.querySelectorAll('.epp-replace').forEach(form => {
    const picker = form.querySelector('.epp-picker');
    const caption = picker.querySelector('[data-picked]');
    const submit = form.querySelector('button[type="submit"]');
    function updateSelection() {
        const selected = picker.querySelector('input:checked');
        submit.disabled = !selected;
        if (selected) caption.textContent = selected.closest('label').querySelector('[data-option]').textContent;
    }
    picker.addEventListener('change', updateSelection);
    window.addEventListener('pageshow', updateSelection);
    updateSelection();
});
