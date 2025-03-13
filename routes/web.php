<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    // Authentication Routes
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('chat.index');
    })->name('home');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Chat Routes
    Route::get('/chats', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chats/{chat}', [ChatController::class, 'show'])->name('chat.show');
    Route::get('/chats/{chat}/password', [ChatController::class, 'password'])->name('chat.password');
    Route::post('/chats/{chat}/password', [ChatController::class, 'storePassword'])->name('chat.password.store');
    Route::post('/chats/{chat}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::post('/chats', [ChatController::class, 'createChat'])->name('chat.create');
    Route::get('/chats/{chat}/messages/new', [ChatController::class, 'getNewMessages'])->name('chat.messages.new');
    Route::post('/messages/decrypt', [ChatController::class, 'decryptMessage'])->name('chat.messages.decrypt');
});
