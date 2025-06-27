// resources/js/bootstrap.js

import _ from 'lodash';
window._ = _;

/**
 * We'll load the Axios HTTP library which provides a clean, convenient interface
 * for making HTTP requests to your Laravel application. By default, we
 * automatically attach the CSRF token to these requests.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time features.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     wsHost: import.meta.env.VITE_PUSHER_APP_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_APP_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_APP_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_APP_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });

/**
 * Load Bootstrap's JavaScript bundle.
 * This is CRUCIAL to ensure the `bootstrap` object is available globally.
 * Make sure this is imported ONLY ONCE across your entire application.
 */
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

axios.defaults.withCredentials = true;  // Cho phép gửi cookie
axios.defaults.baseURL = 'http://duy.test';  // URL backend

// Trong resources/js/bootstrap.js
axios.interceptors.request.use(config => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    config.headers['X-Requested-With'] = 'XMLHttpRequest';
    return config;
});