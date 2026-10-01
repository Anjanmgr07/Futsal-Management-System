document.querySelectorAll('[data-dialog]').forEach((trigger) => {
    const dialog = document.getElementById(trigger.dataset.dialog);
    if (!dialog) return;

    trigger.addEventListener('click', () => dialog.showModal());
    dialog.querySelector('.dialog-close')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});