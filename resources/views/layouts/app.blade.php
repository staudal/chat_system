<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Secure Chat') }}</title>

    <!-- Scripts -->
    @vite(['resources/js/app.js'])
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    <script>
        window.Laravel = {!! json_encode(['csrfToken' => csrf_token()]) !!};
    </script>
    
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        nav .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        nav ul li {
            margin-left: 1rem;
        }
        
        nav a {
            text-decoration: none;
            color: #333;
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .card {
            border: 1px solid #ced4da;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #ced4da;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        /* Chat specific styles */
        .chat-container {
            display: flex;
            height: calc(100vh - 100px);
        }
        
        .sidebar {
            width: 30%;
            border-right: 1px solid #ced4da;
            overflow-y: auto;
        }
        
        .chat-area {
            width: 70%;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid #ced4da;
            background-color: #f8f9fa;
        }
        
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .message-form {
            padding: 1rem;
            border-top: 1px solid #ced4da;
            background-color: #f8f9fa;
            display: flex;
        }
        
        .message-form input {
            flex: 1;
            margin-right: 0.5rem;
        }
        
        .message {
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-radius: 4px;
            max-width: 70%;
        }
        
        .message.sender {
            background-color: #d1ecf1;
            color: #0c5460;
            align-self: flex-end;
            margin-left: auto;
        }
        
        .message.receiver {
            background-color: #f8f9fa;
            color: #333;
            align-self: flex-start;
        }
        
        .message-meta {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .user-list {
            list-style: none;
            padding: 0;
        }
        
        .user-item {
            padding: 0.5rem;
            border-bottom: 1px solid #ced4da;
        }
        
        .user-item:hover {
            background-color: #f8f9fa;
        }
        
        .chat-list {
            list-style: none;
            padding: 0;
        }
        
        .chat-item {
            padding: 0.5rem;
            border-bottom: 1px solid #ced4da;
        }
        
        .chat-item:hover {
            background-color: #f8f9fa;
        }
        
        .chat-item a {
            text-decoration: none;
            color: #333;
            display: block;
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">
            <a href="{{ route('home') }}">Secure Chat</a>
        </div>
        <ul>
            @guest
                <li><a href="{{ route('login') }}">Login</a></li>
                <li><a href="{{ route('register') }}">Register</a></li>
            @else
                <li>{{ Auth::user()->name }}</li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Logout</button>
                    </form>
                </li>
            @endguest
        </ul>
    </nav>

    <div class="container">
        @yield('content')
    </div>

    @yield('scripts')
</body>
</html>