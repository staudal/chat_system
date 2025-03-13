@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('End-to-End Encryption Password') }}</div>

                <div class="card-body">
                    <div class="alert alert-info">
                        <p>For security, your account password is needed to decrypt this chat. This is required because:</p>
                        <ul>
                            <li>Your private key is encrypted with your password</li>
                            <li>Your private key is needed to decrypt the chat key</li>
                            <li>The chat key is needed to decrypt messages</li>
                        </ul>
                        <p><strong>The server cannot read your messages without your password.</strong></p>
                    </div>

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('chat.password.store', $chat) }}">
                        @csrf

                        <div class="form-group">
                            <label for="password">{{ __('Your Password') }}</label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group mt-3">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Decrypt Messages') }}
                            </button>
                            <a href="{{ route('chat.index') }}" class="btn btn-secondary">
                                {{ __('Back to Chats') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection