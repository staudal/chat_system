import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

try {
    // For debugging
    console.log('Echo Config:', {
        key: import.meta.env.VITE_REVERB_APP_KEY,
        host: import.meta.env.VITE_REVERB_HOST,
        port: import.meta.env.VITE_REVERB_PORT,
        scheme: import.meta.env.VITE_REVERB_SCHEME
    });

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        enableLogging: true,
    });
    
    // Event listeners for connection management
    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('Successfully connected to Reverb WebSocket server');
    });
    
    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        console.log('Disconnected from Reverb WebSocket server');
    });
    
    window.Echo.connector.pusher.connection.bind('error', (error) => {
        console.error('Error connecting to Reverb WebSocket server:', error);
    });
} catch (error) {
    console.error('Failed to initialize Echo:', error);
}
