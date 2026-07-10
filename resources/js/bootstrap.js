import * as bootstrap from 'bootstrap';
// Expõe o Bootstrap globalmente para o código que usa `new bootstrap.Modal(...)`
// nas vistas (a importação ESM, por si só, não cria window.bootstrap).
window.bootstrap = bootstrap;
const { Tooltip } = bootstrap;

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

document.addEventListener('DOMContentLoaded', () => {
  const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(el => { try { new Tooltip(el); } catch(_){} });
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 *
 * Só é inicializado quando a edição colaborativa (Laravel Reverb) está configurada,
 * i.e. quando VITE_REVERB_APP_KEY está definida. Caso contrário, `window.Echo`
 * permanece indefinido e o resto do sistema funciona normalmente (fallback clássico).
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
  window.Pusher = Pusher;
  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
  });
}
