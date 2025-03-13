import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

// Load Echo for WebSocket communication - wrap in a try/catch to prevent crashes if it fails
try {
    import('./echo').then(() => {
        console.log('WebSocket setup complete with Reverb');
    }).catch(err => {
        console.error('Failed to load Echo:', err);
    });
} catch (error) {
    console.error('Error setting up WebSockets:', error);
}
