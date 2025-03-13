#!/bin/bash

# Server startup script for Chat System with WebSockets

# Start Reverb WebSocket server
echo "Starting Reverb WebSocket server..."
php artisan reverb:start &
REVERB_PID=$!

# Start PHP development server
echo "Starting PHP development server..."
php artisan serve &
PHP_PID=$!

# Function to cleanup processes on exit
function cleanup {
    echo "Shutting down servers..."
    kill $REVERB_PID
    kill $PHP_PID
    exit
}

# Register the cleanup function for these signals
trap cleanup SIGINT SIGTERM

echo ""
echo "✅ Servers are running!"
echo "🔒 Secure Chat System with end-to-end encryption is available at: http://localhost:8000"
echo "🔌 WebSocket server running at: ws://localhost:8080"
echo ""
echo "Press Ctrl+C to stop all servers."

# Keep the script running
wait