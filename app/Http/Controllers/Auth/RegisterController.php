<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserKeyPair;
use App\Services\ChatService;
use App\Services\CryptoService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    protected CryptoService $cryptoService;

    public function __construct(CryptoService $cryptoService)
    {
        $this->cryptoService = $cryptoService;
    }

    /**
     * Show registration form
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        try {
            // Generate key pair for the user
            $keyPair = $this->cryptoService->generateKeyPair($request->password);

            // Store the key pair
            UserKeyPair::create([
                'user_id' => $user->id,
                'public_key' => $keyPair['public_key'],
                'encrypted_private_key' => $keyPair['encrypted_private_key'],
                'key_pair_salt' => $keyPair['key_pair_salt'],
            ]);
            
            // Store the password in the session for E2E encryption operations
            app(ChatService::class)->storePasswordForSession($request->password);
        } catch (Exception $e) {
            Log::error('Failed to create key pair for user: ' . $e->getMessage());
            return back()->with('error', 'Registration failed. Please try again.');
        }

        // Log the user in
        Auth::login($user);

        return redirect()->route('chat.index');
    }
}
