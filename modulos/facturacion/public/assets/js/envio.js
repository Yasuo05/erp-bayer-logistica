(() => {
    const form = document.querySelector('#envio-filters');
    if (!form) return;

    const search = form.querySelector('input[name="buscar"]');
    const status = form.querySelector('select[name="estado"]');
    let timer;

    const submit = () => {
        form.requestSubmit();
    };

    search?.addEventListener('input', (event) => {
        if (event.isComposing) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(submit, 400);
    });

    status?.addEventListener('change', submit);
})();
