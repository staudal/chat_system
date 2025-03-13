@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Chats</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Your Chats</div>
                <div class="card-body">
                    @if($chats->isEmpty())
                        <p>You have no chats yet. Start a new chat with someone.</p>
                    @else
                        <ul class="chat-list">
                            @foreach($chats as $chat)
                                <li class="chat-item">
                                    <a href="{{ route('chat.show', $chat) }}">
                                        @if($chat->user1_id == $user->id)
                                            {{ $chat->user2->name }}
                                        @else
                                            {{ $chat->user1->name }}
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Start a New Chat</div>
                <div class="card-body">
                    @if($users->isEmpty())
                        <p>No other users are available.</p>
                    @else
                        <form action="{{ route('chat.create') }}" method="POST">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="user_id">Select a user to chat with:</label>
                                <select name="user_id" id="user_id" class="form-control">
                                    @foreach($users as $userOption)
                                        <option value="{{ $userOption->id }}">{{ $userOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password">Your Password (for end-to-end encryption):</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                                <small class="form-text text-muted">
                                    Your password is needed to encrypt the chat key. The server cannot read your messages without this password.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Start Secure Chat</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection