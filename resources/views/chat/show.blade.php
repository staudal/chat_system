@extends('layouts.app')

@section('content')
<div class="chat-container">
    <div class="sidebar">
        <div class="card">
            <div class="card-header">
                <h2>Chat with {{ $otherUser->name }}</h2>
                <a href="{{ route('chat.index') }}" class="btn btn-secondary btn-sm">Back to All Chats</a>
            </div>
            <div class="card-body">
                <p>This chat is end-to-end encrypted. Only you and {{ $otherUser->name }} can read the messages.</p>
            </div>
        </div>
    </div>

    <div class="chat-area">
        <div class="messages" id="messages">
            @foreach($messages as $message)
                <div class="message {{ $message->sender_id === Auth::id() ? 'sender' : 'receiver' }}">
                    <div class="message-content">
                        {{ $message->decrypted_content }}
                    </div>
                    <div class="message-meta">
                        {{ $message->sender->name }} - {{ $message->created_at->format('M d, H:i') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="message-form">
            <form id="messageForm" action="{{ route('chat.messages.store', $chat) }}" method="POST">
                @csrf
                <div class="input-group">
                    <input type="text" name="message" id="messageInput" class="form-control" placeholder="Type your message..." required>
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Scroll to bottom of messages container
        const messagesContainer = document.getElementById('messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Store the last message ID
        let lastMessageId = {{ $messages->last()->id ?? 0 }};

        // Submit message form with AJAX
        $('#messageForm').submit(function(e) {
            e.preventDefault();

            const messageInput = $('#messageInput');
            const message = messageInput.val();

            if (!message) return;

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // Add the message to the chat
                        const messageHtml = `
                            <div class="message sender">
                                <div class="message-content">
                                    ${response.message.content}
                                </div>
                                <div class="message-meta">
                                    ${response.message.sender_name} - ${response.message.created_at}
                                </div>
                            </div>
                        `;

                        $('#messages').append(messageHtml);

                        // Update the last message ID
                        lastMessageId = response.message.id;

                        // Clear the input field
                        messageInput.val('');

                        // Scroll to bottom
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                },
                error: function(xhr) {
                    console.error('Error sending message:', xhr.responseText);
                    alert('Error sending message. Please try again.');
                }
            });
        });

        // Listen for WebSocket events for new messages
        function setupWebsockets() {
            // Check if Echo is properly initialized
            if (typeof window.Echo === 'undefined') {
                console.error('Laravel Echo is not initialized');
                // Fallback to polling
                setInterval(checkForNewMessages, 5000);
                return;
            }

            try {
                // Subscribe to the private chat channel
                const chatChannel = window.Echo.private(`chat.{{ $chat->id }}`);
                
                // Debug channel subscription 
                console.log('Subscribed to channel: chat.{{ $chat->id }}');
                
                // Track processed messages to avoid duplicates
                const processedMessageIds = new Set();
                
                // Function to process new message data with deduplication
                function processNewMessage(data) {
                    // Only process each message once
                    if (processedMessageIds.has(data.id)) {
                        console.log('Skipping already processed message:', data.id);
                        return;
                    }
                    
                    // Only process messages from other users
                    if (data.sender_id !== {{ Auth::id() }}) {
                        console.log('Processing new message ID:', data.id);
                        
                        // Mark as processed to avoid duplicates
                        processedMessageIds.add(data.id);
                        
                        // Decrypt the message
                        $.ajax({
                            url: "{{ route('chat.messages.decrypt') }}",
                            type: 'POST',
                            data: {
                                message_id: data.id,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Add the message to the chat
                                    const messageHtml = `
                                        <div class="message receiver">
                                            <div class="message-content">
                                                ${response.content}
                                            </div>
                                            <div class="message-meta">
                                                ${data.sender_name} - ${data.created_at}
                                            </div>
                                        </div>
                                    `;

                                    $('#messages').append(messageHtml);

                                    // Update the last message ID if greater
                                    if (data.id > lastMessageId) {
                                        lastMessageId = data.id;
                                    }

                                    // Scroll to bottom
                                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                }
                            },
                            error: function(xhr) {
                                console.error('Error decrypting message:', xhr.responseText);
                            }
                        });
                    }
                }
                
                // Listen only for the message.created custom event (single event source)
                chatChannel.listen('.message.created', function(data) {
                    processNewMessage(data);
                });
            } catch (error) {
                console.error('Error setting up WebSockets:', error);
                // Fallback to polling
                setInterval(checkForNewMessages, 5000);
            }
        }

        // Fallback function to check for new messages using polling
        function checkForNewMessages() {
            $.ajax({
                url: "{{ route('chat.messages.new', $chat) }}",
                type: 'GET',
                data: {
                    last_message_id: lastMessageId
                },
                success: function(response) {
                    if (response.messages && response.messages.length > 0) {
                        response.messages.forEach(function(message) {
                            // Add the message to the chat
                            const messageHtml = `
                                <div class="message receiver">
                                    <div class="message-content">
                                        ${message.content}
                                    </div>
                                    <div class="message-meta">
                                        ${message.sender_name} - ${message.created_at}
                                    </div>
                                </div>
                            `;
                            
                            $('#messages').append(messageHtml);
                            
                            // Update the last message ID if greater
                            if (message.id > lastMessageId) {
                                lastMessageId = message.id;
                            }
                        });
                        
                        // Scroll to bottom
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                },
                error: function(xhr) {
                    console.error('Error checking for new messages:', xhr.responseText);
                }
            });
        }

        // Try to initialize WebSockets, but wait for Echo to be available
        function waitForEcho(maxAttempts = 10) {
            if (typeof window.Echo !== 'undefined') {
                console.log('Echo is available, setting up WebSockets');
                setupWebsockets();
                return;
            }
            
            if (maxAttempts <= 0) {
                console.error('Echo not available after maximum attempts');
                // Fall back to polling if Echo initialization fails
                setInterval(checkForNewMessages, 5000);
                return;
            }
            
            console.log('Waiting for Echo to initialize...');
            setTimeout(() => waitForEcho(maxAttempts - 1), 500);
        }
        
        // Start waiting for Echo
        waitForEcho();
    });
</script>
@endsection
