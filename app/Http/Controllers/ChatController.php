<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ChatController extends Controller
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Display a listing of chats.
     */
    public function index()
    {
        $user = Auth::user();
        $chats = $this->chatService->getUserChats($user);
        $users = User::where('id', '!=', $user->id)->get();

        return view('chat.index', compact('chats', 'users', 'user'));
    }

    /**
     * Show chat messages
     */
    public function show(Request $request, Chat $chat)
    {
        // Ensure the authenticated user is part of this chat
        if ($chat->user1_id !== Auth::id() && $chat->user2_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            // Check if the chat key is already in the session
            if (Session::has('chat_keys.' . $chat->id)) {
                // We can decrypt messages without password
                $messages = $this->chatService->getChatMessages($chat);
                $otherUser = $chat->getOtherUser(Auth::id());
                
                return view('chat.show', compact('chat', 'messages', 'otherUser'));
            }
            
            // No chat key in session, try with password from session
            $password = $this->chatService->getPasswordFromSession();
            
            // If we don't have the password, ask for it
            if (!$password) {
                Log::info('Password not found in session, redirecting to password entry');
                return view('chat.password', compact('chat'));
            }
            
            // Attempt to decrypt with the password
            $messages = $this->chatService->getChatMessages($chat, $password);
            $otherUser = $chat->getOtherUser(Auth::id());
            
            return view('chat.show', compact('chat', 'messages', 'otherUser'));
        } catch (Exception $e) {
            // Log the actual error for debugging
            Log::error('Failed to get chat messages: ' . $e->getMessage());
            
            // If decryption fails, password is likely wrong or there's another issue
            return redirect()->route('chat.password', $chat)
                ->with('error', 'Failed to decrypt messages. Please enter your password again.');
        }
    }

    /**
     * Password entry form
     */
    public function password(Chat $chat)
    {
        // Ensure the authenticated user is part of this chat
        if ($chat->user1_id !== Auth::id() && $chat->user2_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('chat.password', compact('chat'));
    }
    
    /**
     * Store password in session
     */
    public function storePassword(Request $request, Chat $chat)
    {
        // Ensure the authenticated user is part of this chat
        if ($chat->user1_id !== Auth::id() && $chat->user2_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        $request->validate([
            'password' => 'required|string',
        ]);
        
        $this->chatService->storePasswordForSession($request->password);
        
        return redirect()->route('chat.show', $chat);
    }

    /**
     * Store a new message
     */
    public function storeMessage(Request $request, Chat $chat)
    {
        // Ensure the authenticated user is part of this chat
        if ($chat->user1_id !== Auth::id() && $chat->user2_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'message' => 'required|string',
        ]);
        
        try {
            // Check if we have the chat key in session
            if (Session::has('chat_keys.' . $chat->id)) {
                // We can send message without password
                $message = $this->chatService->sendMessage($chat, Auth::user(), $request->message);
            } else {
                // Try with password
                $password = $this->chatService->getPasswordFromSession();
                
                if (!$password) {
                    if ($request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Password required',
                            'redirect' => route('chat.password', $chat)
                        ], 403);
                    }
                    
                    return redirect()->route('chat.password', $chat);
                }
                
                $message = $this->chatService->sendMessage($chat, Auth::user(), $request->message, $password);
            }
            
            // If this is an AJAX request, return the message
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => [
                        'id' => $message->id,
                        'sender_name' => Auth::user()->name,
                        'content' => $request->message,
                        'created_at' => $message->created_at->format('M d, H:i'),
                        'is_sender' => true,
                    ]
                ]);
            }

            return redirect()->route('chat.show', $chat);
        } catch (Exception $e) {
            Log::error('Failed to send message: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'redirect' => route('chat.password', $chat)
                ], 403);
            }
            
            return redirect()->route('chat.password', $chat)->with('error', $e->getMessage());
        }
    }

    /**
     * Create a new chat with another user
     */
    public function createChat(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string',
        ]);
        
        $password = $request->password;
        $this->chatService->storePasswordForSession($password);

        try {
            $otherUser = User::findOrFail($request->user_id);
            $chat = $this->chatService->findOrCreateChat(Auth::user(), $otherUser, $password);
            
            return redirect()->route('chat.show', $chat);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    /**
     * Get new messages (for polling/ajax) - Kept for fallback
     */
    public function getNewMessages(Request $request, Chat $chat)
    {
        // Ensure the authenticated user is part of this chat
        if ($chat->user1_id !== Auth::id() && $chat->user2_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        $lastMessageId = $request->input('last_message_id', 0);
        
        $messages = $chat->messages()
            ->with('sender')
            ->where('id', '>', $lastMessageId)
            ->where('sender_id', '!=', Auth::id())
            ->get();
            
        // Mark messages as read
        foreach ($messages as $message) {
            $message->update(['is_read' => true]);
        }
        
        try {
            // Check if we have the chat key in session
            if (Session::has('chat_keys.' . $chat->id)) {
                // We can decrypt messages without password
                $allDecryptedMessages = $this->chatService->getChatMessages($chat);
            } else {
                // Try with password
                $password = $this->chatService->getPasswordFromSession();
                
                if (!$password) {
                    return response()->json([
                        'error' => 'Password required',
                        'redirect' => route('chat.password', $chat)
                    ], 403);
                }
                
                // Get all decrypted messages
                $allDecryptedMessages = $this->chatService->getChatMessages($chat, $password);
            }
            
            // Extract just the ones we need
            $decryptedMessages = $messages->map(function ($message) use ($allDecryptedMessages) {
                $decryptedMessage = $allDecryptedMessages->where('id', $message->id)->first();
                $decryptedContent = $decryptedMessage ? $decryptedMessage->decrypted_content : '[Failed to decrypt message]';
                    
                return [
                    'id' => $message->id,
                    'sender_name' => $message->sender->name,
                    'content' => $decryptedContent,
                    'created_at' => $message->created_at->format('M d, H:i'),
                    'is_sender' => false,
                ];
            });
            
            return response()->json(['messages' => $decryptedMessages]);
        } catch (Exception $e) {
            Log::error('Failed to decrypt new messages: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to decrypt messages',
                'redirect' => route('chat.password', $chat)
            ], 403);
        }
    }
    
    /**
     * Decrypt a single message (for real-time WebSocket updates)
     */
    public function decryptMessage(Request $request)
    {
        $request->validate([
            'message_id' => 'required|integer|exists:messages,id',
        ]);
        
        $message = Message::findOrFail($request->message_id);
        $chat = $message->chat;
        
        // Ensure the authenticated user is part of this chat
        if ($chat->user1_id !== Auth::id() && $chat->user2_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            // Mark message as read
            $message->update(['is_read' => true]);
            
            // Check if we have the chat key in session
            if (Session::has('chat_keys.' . $chat->id)) {
                // We can decrypt messages without password
                $decryptedMessage = $this->chatService->decryptMessage($message);
            } else {
                // Try with password
                $password = $this->chatService->getPasswordFromSession();
                
                if (!$password) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Password required',
                        'redirect' => route('chat.password', $chat)
                    ], 403);
                }
                
                // Decrypt the message
                $decryptedMessage = $this->chatService->decryptMessage($message, $password);
            }
            
            return response()->json([
                'success' => true,
                'content' => $decryptedMessage,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to decrypt message: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to decrypt message',
                'redirect' => route('chat.password', $chat)
            ], 403);
        }
    }
}
