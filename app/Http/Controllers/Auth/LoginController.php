<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /**
     * Show login form
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Store the password in the session for E2E encryption operations
            // Important security note: this stores the plaintext password in the session
            // In a production system, consider alternatives like deriving a separate key or 
            // using secure session storage with hardware security modules
            try {
                app(ChatService::class)->storePasswordForSession($request->password);
            } catch (Exception $e) {
                // Log but don't expose the error to users
                Log::error('Failed to store password for session: ' . $e->getMessage());
            }

            return redirect()->intended(route('chat.index'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
