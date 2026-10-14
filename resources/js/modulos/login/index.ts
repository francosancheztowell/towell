/**
 * Login: al enviar, el botón muestra "Accediendo…" con spinner. El spinner y el bloqueo del doble
 * clic los pone app.css con form[data-loading] (la misma regla que usa Livewire en x-ui.button).
 * `submit` solo llega si la validación nativa pasó, así que un campo vacío no deja el botón ocupado.
 */
const form = document.getElementById('loginForm') as HTMLFormElement | null;

form?.addEventListener('submit', () => {
    form.setAttribute('data-loading', '');
    form.setAttribute('aria-busy', 'true');
    const texto = form.querySelector<HTMLElement>('[data-texto-login]');
    if (texto) texto.textContent = 'Accediendo…';
});

// Volver con Atrás desde el bfcache dejaría el botón ocupado.
window.addEventListener('pageshow', () => {
    form?.removeAttribute('data-loading');
    form?.removeAttribute('aria-busy');
    const texto = form?.querySelector<HTMLElement>('[data-texto-login]');
    if (texto) texto.textContent = 'Iniciar Sesión';
});
