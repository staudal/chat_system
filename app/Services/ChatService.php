<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\UserKeyPair;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ChatService
{
    protected CryptoService $cryptoService;

    public function __construct(CryptoService $cryptoService)
    {
        $this->cryptoService = $cryptoService;
    }

    /**
     * Create a new chat between two users.
     *
     * @param User $user1
     * @param User $user2
     * @param string|null $currentUserPassword Password of the user creating the chat
     * @return Chat
     * @throws Exception
     */
    public function createChat(User $user1, User $user2, ?string $currentUserPassword = null): Chat
    {
        // Check if a chat already exists between these users
        $existingChat = Chat::where(function ($query) use ($user1, $user2) {
            $query->where('user1_id', $user1->id)
                  ->where('user2_id', $user2->id);
        })->orWhere(function ($query) use ($user1, $user2) {
            $query->where('user1_id', $user2->id)
                  ->where('user2_id', $user1->id);
        })->first();

        if ($existingChat) {
            return $existingChat;
        }

        // Need password to create a new chat (for E2E encryption)
        if (!$currentUserPassword) {
            throw new Exception('Password is required to create a new chat');
        }

        // Get public keys for both users
        $user1KeyPair = UserKeyPair::where('user_id', $user1->id)->first();
        $user2KeyPair = UserKeyPair::where('user_id', $user2->id)->first();

        if (!$user1KeyPair) {
            throw new Exception('Missing key pair for user: ' . $user1->name);
        }
        
        if (!$user2KeyPair) {
            throw new Exception('Missing key pair for user: ' . $user2->name);
        }

        // Generate a shared symmetric key for the chat
        $chatKey = $this->cryptoService->generateChatKey();

        // Encrypt the chat key with each user's public key
        $encryptedChatKeyUser1 = $this->cryptoService->encryptWithPublicKey(
            $chatKey,
            $user1KeyPair->public_key
        );

        if (!$encryptedChatKeyUser1) {
            throw new Exception('Failed to encrypt chat key for user1: ' . $user1->name);
        }

        $encryptedChatKeyUser2 = $this->cryptoService->encryptWithPublicKey(
            $chatKey,
            $user2KeyPair->public_key
        );

        if (!$encryptedChatKeyUser2) {
            throw new Exception('Failed to encrypt chat key for user2: ' . $user2->name);
        }

        // Create a new chat with encrypted keys for both users
        $chat = Chat::create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
            'encrypted_chat_key_user1' => $encryptedChatKeyUser1,
            'encrypted_chat_key_user2' => $encryptedChatKeyUser2,
        ]);
        
        // Store the chat key in the session for the current user
        // This allows the current user to access the chat immediately without re-entering password
        Session::put('chat_keys.' . $chat->id, $chatKey);
        
        Log::info('Created new chat with ID: ' . $chat->id);
        return $chat;
    }

    /**
     * Send a message in a chat.
     *
     * @param Chat $chat
     * @param User $sender
     * @param string $content
     * @param string|null $password Current user's password to decrypt their private key (optional if chat key in session)
     * @return Message
     * @throws Exception
     */
    public function sendMessage(Chat $chat, User $sender, string $content, ?string $password = null): Message
    {
        // First check if we have the chat key already in the session
        $chatKey = Session::get('chat_keys.' . $chat->id);
        
        if (!$chatKey) {
            // If not in session, we need the password to decrypt it
            if (!$password) {
                throw new Exception('Password is required to send a message in this chat');
            }
            
            // Get the encrypted chat key for the sender
            $encryptedChatKey = $chat->getEncryptedChatKeyForUser($sender->id);
            if (!$encryptedChatKey) {
                throw new Exception('Chat key not found for user');
            }

            // Get the sender's key pair
            $keyPair = UserKeyPair::where('user_id', $sender->id)->first();
            if (!$keyPair) {
                throw new Exception('Key pair not found for user');
            }

            // Decrypt the user's private key using their password
            $privateKey = $this->cryptoService->getDecryptedPrivateKey(
                $keyPair->encrypted_private_key,
                $password
            );
            
            if (!$privateKey) {
                throw new Exception('Invalid password or corrupted private key');
            }

            // Decrypt the chat key using the private key
            $chatKey = $this->cryptoService->decryptWithPrivateKey(
                $encryptedChatKey,
                $privateKey
            );
            
            if (!$chatKey) {
                throw new Exception('Failed to decrypt chat key');
            }
            
            // Store the chat key in the session for future use
            Session::put('chat_keys.' . $chat->id, $chatKey);
        }

        // Encrypt the message content using the chat key
        $encryptedData = $this->cryptoService->encryptMessage($content, $chatKey);

        // Create and return the new message
        return Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $sender->id,
            'encrypted_content' => $encryptedData['encrypted_content'],
            'iv' => $encryptedData['iv'],
            'is_read' => false,
        ]);
    }

    /**
     * Get all chats for a user.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserChats(User $user)
    {
        return Chat::where('user1_id', $user->id)
                   ->orWhere('user2_id', $user->id)
                   ->with(['user1', 'user2'])
                   ->get();
    }

    /**
     * Get all messages for a chat.
     *
     * @param Chat $chat
     * @param string|null $password Current user's password to decrypt their private key (optional if chat key in session)
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws Exception
     */
    public function getChatMessages(Chat $chat, ?string $password = null)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new Exception('User not authenticated');
        }

        // Get all messages
        $messages = $chat->messages()->with('sender')->orderBy('created_at', 'asc')->get();
        
        // Mark unread messages as read
        $unreadMessages = $messages->where('is_read', false)
                                  ->where('sender_id', '!=', $currentUser->id);
        
        foreach ($unreadMessages as $message) {
            $message->update(['is_read' => true]);
        }
        
        // First check if we have the chat key already in the session
        $chatKey = Session::get('chat_keys.' . $chat->id);
        
        if (!$chatKey) {
            // If not in session, we need the password to decrypt it
            if (!$password) {
                throw new Exception('Password is required to decrypt this chat');
            }
            
            // Get the encrypted chat key for the current user
            $encryptedChatKey = $chat->getEncryptedChatKeyForUser($currentUser->id);
            if (!$encryptedChatKey) {
                throw new Exception('Chat key not found for user');
            }

            // Get the user's key pair
            $keyPair = UserKeyPair::where('user_id', $currentUser->id)->first();
            if (!$keyPair) {
                throw new Exception('Key pair not found for user');
            }

            // Decrypt the user's private key using their password
            $privateKey = $this->cryptoService->getDecryptedPrivateKey(
                $keyPair->encrypted_private_key,
                $password
            );
            
            if (!$privateKey) {
                throw new Exception('Invalid password or corrupted private key');
            }

            // Decrypt the chat key using the private key
            $chatKey = $this->cryptoService->decryptWithPrivateKey(
                $encryptedChatKey,
                $privateKey
            );
            
            if (!$chatKey) {
                throw new Exception('Failed to decrypt chat key');
            }
            
            // Store the chat key in the session for future use
            Session::put('chat_keys.' . $chat->id, $chatKey);
        }
        
        // Decrypt the messages using the chat key
        $decryptedMessages = $messages->map(function ($message) use ($chatKey) {
            try {
                $decryptedContent = $this->cryptoService->decryptMessage(
                    $message->encrypted_content,
                    $chatKey
                );
                
                $message->decrypted_content = $decryptedContent;
            } catch (Exception $e) {
                $message->decrypted_content = '[Failed to decrypt message]';
                Log::error('Failed to decrypt message: ' . $e->getMessage());
            }
            
            return $message;
        });
        
        return $decryptedMessages;
    }
    
    /**
     * Find or create a chat with another user.
     *
     * @param User $currentUser
     * @param User $otherUser
     * @param string|null $password Current user's password needed for creating a new chat
     * @return Chat
     * @throws Exception
     */
    public function findOrCreateChat(User $currentUser, User $otherUser, ?string $password = null): Chat
    {
        $chat = Chat::where(function ($query) use ($currentUser, $otherUser) {
            $query->where('user1_id', $currentUser->id)
                  ->where('user2_id', $otherUser->id);
        })->orWhere(function ($query) use ($currentUser, $otherUser) {
            $query->where('user1_id', $otherUser->id)
                  ->where('user2_id', $currentUser->id);
        })->first();
        
        if (!$chat) {
            if (!$password) {
                throw new Exception('Password is required to create a new chat');
            }
            $chat = $this->createChat($currentUser, $otherUser, $password);
        }
        
        return $chat;
    }
    
    /**
     * Store user's password in session for later use in E2E encryption
     * 
     * @param string $password
     */
    public function storePasswordForSession(string $password): void
    {
        Session::put('user_password', $password);
    }
    
    /**
     * Get user's password from session
     * 
     * @return string|null
     */
    public function getPasswordFromSession(): ?string
    {
        return Session::get('user_password');
    }
    
    /**
     * Decrypt a single message
     * 
     * @param Message $message
     * @param string|null $password
     * @return string
     * @throws Exception
     */
    public function decryptMessage(Message $message, ?string $password = null): string
    {
        $chat = $message->chat;
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            throw new Exception('User not authenticated');
        }
        
        // Get chat key from session or decrypt it
        $chatKey = $this->getChatKeyForUser($chat, $currentUser, $password);
        
        // Decrypt the message content
        return $this->cryptoService->decryptMessage(
            $message->encrypted_content,
            $chatKey
        );
    }
    
    /**
     * Get the chat key for a user, either from session or by decrypting it
     * 
     * @param Chat $chat
     * @param User $user
     * @param string|null $password
     * @return string
     * @throws Exception
     */
    protected function getChatKeyForUser(Chat $chat, User $user, ?string $password = null): string
    {
        // First check if we have the chat key already in the session
        $chatKey = Session::get('chat_keys.' . $chat->id);
        
        if ($chatKey) {
            return $chatKey;
        }
        
        // If not in session, we need the password to decrypt it
        if (!$password) {
            throw new Exception('Password is required to decrypt this chat');
        }
        
        // Get the encrypted chat key for the user
        $encryptedChatKey = $chat->getEncryptedChatKeyForUser($user->id);
        if (!$encryptedChatKey) {
            throw new Exception('Chat key not found for user');
        }

        // Get the user's key pair
        $keyPair = UserKeyPair::where('user_id', $user->id)->first();
        if (!$keyPair) {
            throw new Exception('Key pair not found for user');
        }

        // Decrypt the user's private key using their password
        $privateKey = $this->cryptoService->getDecryptedPrivateKey(
            $keyPair->encrypted_private_key,
            $password
        );
        
        if (!$privateKey) {
            throw new Exception('Invalid password or corrupted private key');
        }

        // Decrypt the chat key using the private key
        $chatKey = $this->cryptoService->decryptWithPrivateKey(
            $encryptedChatKey,
            $privateKey
        );
        
        if (!$chatKey) {
            throw new Exception('Failed to decrypt chat key');
        }
        
        // Store the chat key in the session for future use
        Session::put('chat_keys.' . $chat->id, $chatKey);
        
        return $chatKey;
    }
}