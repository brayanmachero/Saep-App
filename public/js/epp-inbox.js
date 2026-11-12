document.querySelectorAll('[data-epp-panel]').forEach(button => {
    const panel = document.getElementById(button.dataset.eppPanel);
    button.addEventListener('click', () => panel.showModal());
    panel.querySelector('[data-close]').addEventListener('click', () => panel.close());
    panel.addEventListener('click', event => {
        if (event.target === panel && event.clientX < panel.getBoundingClientRect().left) panel.close();
    });
    if (panel.dataset.open === '1') panel.showModal();
});
