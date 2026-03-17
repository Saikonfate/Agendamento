document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-flash-message]').forEach((element) => {
        window.setTimeout(() => {
            element.classList.add('is-hiding');
            window.setTimeout(() => element.remove(), 200);
        }, 4200);
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLFormElement)) {
                return;
            }

            const submitter = event.submitter;
            const fallbackButton = target.querySelector('button[type="submit"], input[type="submit"]');
            const button = submitter instanceof HTMLElement ? submitter : fallbackButton;

            if (!(button instanceof HTMLElement) || button.hasAttribute('data-no-loading-feedback')) {
                return;
            }

            if (button instanceof HTMLButtonElement) {
                if (!button.dataset.originalLabel) {
                    button.dataset.originalLabel = button.innerHTML;
                }

                button.disabled = true;
                button.classList.add('ui-submit-loading');
                button.textContent = button.dataset.loadingText || 'Processando...';
                return;
            }

            if (button instanceof HTMLInputElement) {
                if (!button.dataset.originalLabel) {
                    button.dataset.originalLabel = button.value;
                }

                button.disabled = true;
                button.classList.add('ui-submit-loading');
                button.value = button.dataset.loadingText || 'Processando...';
            }
        });
    });
});
